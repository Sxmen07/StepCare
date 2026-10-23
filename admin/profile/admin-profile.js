// English comments only
(function () {
  'use strict';

  let currentAdminData = null;

  document.addEventListener('DOMContentLoaded', () => {
    // 1. Mount sidebar and preserve native toggle behavior
    fetch('/StepCare/components/sidebar-admin.html')
      .then(res => res.text())
      .then(html => {
        const sb = document.getElementById('sidebar-container');
        sb.innerHTML = html;
        sb.querySelectorAll('script').forEach(s => {
          const ns = document.createElement('script');
          ns.textContent = s.textContent;
          document.body.appendChild(ns);
        });
        if (window.lucide) lucide.createIcons();
      });

    // 2. Mount topbar and bind authentic user name
    fetch('/StepCare/components/topbar.html')
      .then(res => res.text())
      .then(html => {
        const tb = document.getElementById('topbar-container');
        tb.innerHTML = html;
        tb.querySelectorAll('script').forEach(s => {
          const ns = document.createElement('script');
          ns.textContent = s.textContent;
          document.body.appendChild(ns);
        });

        // Set title explicitly to Profile
        const title = document.getElementById('topbar-page-title');
        if (title) title.textContent = 'Profile';

        // Re-sync name to topbar if data already loaded
        if (currentAdminData) {
          syncNameToTopbar(currentAdminData);
        }
        if (window.lucide) lucide.createIcons();
      });

    // 3. Load authentic profile directly from database
    loadDatabaseProfile();

    // 4. Initialize modal and avatar upload handlers
    initModalControls();
    initAvatarUpload();
  });

  // Pull authentic data directly from backend
  async function loadDatabaseProfile() {
    try {
      const res = await fetch('./admin_profile_actions.php?action=get_data');
      const data = await res.json();

      if (data.status === 'success') {
        currentAdminData = data.admin;
        renderAdminProfile(data.admin);
        renderSlotsAndMetrics(data.unassigned_count, data.slots);
        syncNameToTopbar(data.admin);
      } else {
        alert(data.message);
      }
    } catch (err) {
      console.error('Network request failed:', err);
    }
  }

  // Populate left profile card with authentic records
  function renderAdminProfile(admin) {
    const fullName = `${admin.firstName || ''} ${admin.lastName || ''}`.trim() || 'Admin';

    document.getElementById('card-admin-name').textContent = fullName;
    document.getElementById('info-admin-name').textContent = fullName;
    document.getElementById('info-admin-email').textContent = admin.email || '-';

    // Support authentic userPhotoURL column
    const photoPath = admin.userPhotoURL || admin.avatar;
    if (photoPath) {
      document.getElementById('admin-avatar-img').src = '/StepCare/' + photoPath + '?t=' + Date.now();
    }

    // Prefill edit form inputs
    document.getElementById('edit-admin-first-name').value = admin.firstName || '';
    document.getElementById('edit-admin-last-name').value = admin.lastName || '';
    document.getElementById('edit-admin-email').value = admin.email || '';
  }

  // Synchronize authenticated name and initial across Topbar
  function syncNameToTopbar(admin) {
    if (!admin) return;
    const fullName = `${admin.firstName || ''} ${admin.lastName || ''}`.trim();
    if (!fullName) return;

    // Persist active name to local storage
    localStorage.setItem('stepcare_username', fullName);

    // Update avatar circle initial
    const avatarBadge = document.getElementById('topbar-avatar-badge') || document.getElementById('user-menu-btn');
    if (avatarBadge && !avatarBadge.querySelector('img')) {
      avatarBadge.textContent = fullName.charAt(0).toUpperCase();
    }

    // Update 'Signed in as' title in topbar dropdown
    const topbarUserName = document.getElementById('topbar-user-fullname') || document.querySelector('.topbar-user-name');
    if (topbarUserName) {
      topbarUserName.textContent = fullName;
    }

    // Update possible name headers in topbar menu
    const menuHeaders = document.querySelectorAll('#user-menu-dropdown p, #topbar-container .text-sm.font-bold');
    menuHeaders.forEach(el => {
      if (el.textContent.toLowerCase().includes('administrator') || el.previousElementSibling?.textContent.includes('Signed in as')) {
        el.textContent = fullName;
      }
    });
  }

  // Render stats counter and schedule slot table
  function renderSlotsAndMetrics(unassignedCount, slots) {
    const numEl = document.getElementById('stat-unassigned-num');
    const descEl = document.getElementById('stat-unassigned-desc');
    const iconWrap = document.getElementById('stat-icon-wrapper');

    const count = parseInt(unassignedCount) || 0;
    numEl.textContent = count;

    if (count === 0) {
      descEl.textContent = 'All appointments assigned';
      iconWrap.className = 'w-16 h-16 rounded-2xl flex items-center justify-center shrink-0 bg-emerald-50 text-emerald-600';
      iconWrap.innerHTML = '<i data-lucide="check" class="w-8 h-8"></i>';
    } else {
      descEl.textContent = 'Appointments needing nurse assignment';
      iconWrap.className = 'w-16 h-16 rounded-2xl flex items-center justify-center shrink-0 bg-amber-50 text-amber-500';
      iconWrap.innerHTML = '<i data-lucide="alert-circle" class="w-8 h-8"></i>';
    }

    const tbody = document.getElementById('admin-slots-body');
    if (!slots || slots.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" class="py-16 text-center">
            <div class="flex flex-col items-center justify-center space-y-3">
              <div class="w-14 h-14 rounded-2xl bg-neutral-100 border border-neutral-200 flex items-center justify-center text-neutral-400">
                <i data-lucide="calendar-x" class="w-7 h-7"></i>
              </div>
              <div>
                <h4 class="text-sm font-bold text-neutral-800">No appointments scheduled yet</h4>
                <p class="text-xs text-neutral-400 mt-0.5">There are currently no assigned or pending service booking records.</p>
              </div>
            </div>
          </td>
        </tr>`;
    } else {
      tbody.innerHTML = slots.map(s => {
        const isAssigned = (s.status || '').toLowerCase() === 'assigned';
        const badgeClass = isAssigned 
          ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
          : 'bg-amber-50 text-amber-700 border-amber-200';
        return `
          <tr class="hover:bg-neutral-50/70 transition">
            <td class="py-3.5 px-4 text-xs font-bold text-neutral-900">${s.date}</td>
            <td class="py-3.5 px-4 text-xs text-neutral-600 font-semibold">${s.timeSlot}</td>
            <td class="py-3.5 px-4 text-xs text-neutral-600">${s.serviceName || 'General Care'}</td>
            <td class="py-3.5 px-4 text-right">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border ${badgeClass}">
                ${isAssigned ? 'Assigned' : 'Pending'}
              </span>
            </td>
          </tr>`;
      }).join('');
    }

    if (window.lucide) lucide.createIcons();
  }

  // Handle edit profile modal interactions
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

    document.getElementById('admin-edit-form').onsubmit = async (e) => {
      e.preventDefault();
      const btn = document.getElementById('save-profile-btn');
      btn.disabled = true;
      btn.textContent = 'Saving...';

      const payload = {
        firstName: document.getElementById('edit-admin-first-name').value.trim(),
        lastName: document.getElementById('edit-admin-last-name').value.trim(),
        email: document.getElementById('edit-admin-email').value.trim(),
        password: document.getElementById('edit-admin-password').value
      };

      try {
        const res = await fetch('./admin_profile_actions.php?action=update_profile', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await res.json();
        
        if (result.status === 'success') {
          hide();
          // Reload authentic state from database immediately
          await loadDatabaseProfile();
          alert('Profile updated successfully in database!');
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

  // Handle avatar upload and update image path
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
          const res = await fetch('./admin_profile_actions.php?action=upload_avatar', {
            method: 'POST',
            body: fd
          });
          const result = await res.json();
          if (result.status === 'success') {
            document.getElementById('admin-avatar-img').src = '/StepCare/' + result.avatar_url + '?t=' + Date.now();
          } else {
            alert(result.message || 'Avatar upload failed');
          }
        } catch (err) {
          alert('Avatar network error: ' + err.message);
        }
      };
    }
  }
})();