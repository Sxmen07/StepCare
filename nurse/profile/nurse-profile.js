// English comments only
(function () {
  'use strict';

  let currentNurseData = null;

  function injectComponent(containerId, html) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = html;
    const scripts = container.querySelectorAll("script");
    scripts.forEach(oldScript => {
      const newScript = document.createElement("script");
      Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
      newScript.appendChild(document.createTextNode(oldScript.innerHTML));
      oldScript.parentNode.replaceChild(newScript, oldScript);
    });
    if (window.lucide) lucide.createIcons();
  }

  document.addEventListener('DOMContentLoaded', () => {
    // 1. Mount nurse sidebar
    fetch('/StepCare/components/sidebar-nurse.html')
      .then(res => res.text())
      .then(html => injectComponent('sidebar-container', html));

    // 2. Mount topbar and set title
    fetch('/StepCare/components/topbar.html')
      .then(res => res.text())
      .then(html => {
        injectComponent('topbar-container', html);
        const title = document.getElementById('topbar-page-title');
        if (title) title.textContent = 'Profile';
      });

    // 3. Load authentic nurse profile and today's schedule
    loadNurseProfile();

    // 4. Initialize controls
    initModalControls();
    initAvatarUpload();
  });

  // Load profile from backend
  async function loadNurseProfile() {
    try {
      const res = await fetch('./nurse_profile_actions.php?action=get_data');
      const data = await res.json();

      if (data.status === 'success') {
        currentNurseData = data.nurse;
        renderNurseProfile(data.nurse);
        renderMetricsAndSlots(data.today_total, data.today_pending, data.all_completed, data.slots);
      } else {
        alert(data.message || 'Failed to fetch nurse data');
      }
    } catch (err) {
      console.error('Fetch error:', err);
    }
  }

  // Populate left card
  function renderNurseProfile(nurse) {
    const fullName = `${nurse.firstName || ''} ${nurse.lastName || ''}`.trim() || 'Nurse';

    document.getElementById('card-nurse-name').textContent = fullName;
    document.getElementById('info-nurse-name').textContent = fullName;
    document.getElementById('info-nurse-email').textContent = nurse.email || '-';

    if (nurse.photoURL) {
      document.getElementById('nurse-avatar-img').src = '/StepCare/' + nurse.photoURL + '?t=' + Date.now();
    }

    document.getElementById('edit-nurse-first-name').value = nurse.firstName || '';
    document.getElementById('edit-nurse-last-name').value = nurse.lastName || '';
    document.getElementById('edit-nurse-email').value = nurse.email || '';
  }

  // Render top metric card and schedule slots table
  function renderMetricsAndSlots(totalCount, pendingCount, allCompleted, slots) {
    const numEl = document.getElementById('stat-today-num');
    const descEl = document.getElementById('stat-today-desc');
    const iconWrap = document.getElementById('stat-icon-wrapper');

    const total = parseInt(totalCount) || 0;
    const pending = parseInt(pendingCount) || 0;
    numEl.textContent = total;

    // If all finished or 0 pending, render green tick
    if (allCompleted || total === 0 || pending === 0) {
      descEl.textContent = total > 0 ? 'All appointments completed for today' : 'No appointments scheduled today';
      iconWrap.className = 'w-16 h-16 rounded-2xl flex items-center justify-center shrink-0 bg-emerald-50 text-emerald-600';
      iconWrap.innerHTML = '<i data-lucide="check" class="w-8 h-8"></i>';
    } else {
      descEl.textContent = `${pending} appointment${pending > 1 ? 's' : ''} left for today`;
      iconWrap.className = 'w-16 h-16 rounded-2xl flex items-center justify-center shrink-0 bg-amber-50 text-amber-500';
      iconWrap.innerHTML = '<i data-lucide="clock" class="w-8 h-8"></i>';
    }

    const tbody = document.getElementById('nurse-slots-body');
    if (!slots || slots.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" class="py-16 text-center">
            <div class="flex flex-col items-center justify-center space-y-3">
              <div class="w-14 h-14 rounded-2xl bg-neutral-100 border border-neutral-200 flex items-center justify-center text-neutral-400">
                <i data-lucide="calendar-check" class="w-7 h-7"></i>
              </div>
              <div>
                <h4 class="text-sm font-bold text-neutral-800">No appointments scheduled for today</h4>
                <p class="text-xs text-neutral-400 mt-0.5">You currently have no patient visits or service duties assigned.</p>
              </div>
            </div>
          </td>
        </tr>`;
    } else {
      tbody.innerHTML = slots.map(s => {
        const isDone = (s.status || '').toLowerCase() === 'completed';
        const badgeClass = isDone 
          ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
          : 'bg-indigo-50 text-[#3C52C3] border-indigo-200';
        return `
          <tr class="hover:bg-neutral-50/70 transition">
            <td class="py-3.5 px-4 text-xs font-bold text-neutral-900">${s.date}</td>
            <td class="py-3.5 px-4 text-xs text-neutral-600 font-semibold">${s.timeSlot}</td>
            <td class="py-3.5 px-4 text-xs text-neutral-600">${s.serviceName || 'General Care'}</td>
            <td class="py-3.5 px-4 text-right">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border ${badgeClass}">
                ${isDone ? 'Completed' : 'Scheduled'}
              </span>
            </td>
          </tr>`;
      }).join('');
    }

    if (window.lucide) lucide.createIcons();
  }

  // Edit Profile Modal
  function initModalControls() {
    const modal = document.getElementById('edit-profile-modal');
    const openBtn = document.getElementById('open-edit-modal-btn');
    const closeBtn = document.getElementById('close-modal-btn');
    const closeX = document.getElementById('close-modal-x');

    const show = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const hide = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

    if (openBtn) openBtn.onclick = show;
    if (closeBtn) closeBtn.onclick = hide;
    if (closeX) closeX.onclick = hide;

    document.getElementById('nurse-edit-form').onsubmit = async (e) => {
      e.preventDefault();
      const btn = document.getElementById('save-profile-btn');
      btn.disabled = true;
      btn.textContent = 'Saving...';

      const payload = {
        firstName: document.getElementById('edit-nurse-first-name').value.trim(),
        lastName: document.getElementById('edit-nurse-last-name').value.trim(),
        email: document.getElementById('edit-nurse-email').value.trim(),
        password: document.getElementById('edit-nurse-password').value
      };

      try {
        const res = await fetch('./nurse_profile_actions.php?action=update_profile', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await res.json();
        
        if (result.status === 'success') {
          hide();
          await loadNurseProfile();
          alert('Profile updated successfully!');
        } else {
          alert(result.message || 'Update failed');
        }
      } catch (err) {
        alert('Request failed: ' + err.message);
      } finally {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
      }
    };
  }

  // Handle avatar upload
  function initAvatarUpload() {
    const trigger = document.getElementById('btn-trigger-upload');
    const input = document.getElementById('avatar-file-input');

    if (trigger && input) {
      trigger.onclick = () => input.click();
      input.onchange = async () => {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        
        const fd = new FormData();
        fd.append('avatar', file);

        try {
          const res = await fetch('./nurse_profile_actions.php?action=upload_avatar', {
            method: 'POST',
            body: fd
          });
          const result = await res.json();
          if (result.status === 'success') {
            document.getElementById('nurse-avatar-img').src = '/StepCare/' + result.avatar_url + '?t=' + Date.now();
          } else {
            alert(result.message || 'Avatar upload failed');
          }
        } catch (err) {
          alert('Avatar upload error: ' + err.message);
        }
      };
    }
  }
})();