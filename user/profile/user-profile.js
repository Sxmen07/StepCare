// user/profile/user-profile.js

function toggleEditSection(sectionId) {
  const el = document.getElementById(sectionId);
  if (el) el.classList.toggle('hidden');
}

document.addEventListener("DOMContentLoaded", () => {
  if (window.lucide) lucide.createIcons();

  // ============================================================
  // 1. Toggle Extra Info Dropdown Logic
  // ============================================================
  const toggleBtn = document.getElementById('toggle-extra-info');
  const extraInfo = document.getElementById('extra-info-container');
  const toggleIcon = document.getElementById('toggle-icon');
  const toggleText = document.getElementById('toggle-text');

  if (toggleBtn && extraInfo && toggleIcon && toggleText) {
    toggleBtn.addEventListener('click', () => {
      const isClosed = extraInfo.classList.contains('max-h-0');
      
      if (isClosed) {
        // Open
        extraInfo.classList.remove('max-h-0', 'opacity-0');
        extraInfo.classList.add('max-h-[500px]', 'opacity-100');
        toggleIcon.classList.add('rotate-180');
        toggleText.textContent = 'Less Info'; // Change text
      } else {
        // Close
        extraInfo.classList.add('max-h-0', 'opacity-0');
        extraInfo.classList.remove('max-h-[500px]', 'opacity-100');
        toggleIcon.classList.remove('rotate-180');
        toggleText.textContent = 'More Info'; // Reset text
      }
    });
  }

  let activeUser = null;

  // ============================================================
  // 2. Fetch User Profile from Database
  // ============================================================
  function loadUserProfile() {
    fetch('./user-profile-actions.php?action=fetch_profile')
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          console.error("Profile load error:", data.message);
          return;
        }

        const u = data.user;
        activeUser = u;

        const fullName = `${u.firstName || ''} ${u.lastName || ''}`.trim() || 'User Profile';

        // Basic Info
        document.getElementById('card-user-name').textContent = fullName;
        document.getElementById('info-full-name').textContent = fullName;
        document.getElementById('info-email').textContent = u.email || 'No email registered';

        // Extra Info (Dropdown)
        document.getElementById('info-ic').textContent = u.IC || 'Not provided';
        document.getElementById('info-gender-dob').textContent = `${u.gender || 'N/A'} | ${u.dateOfBirth || 'N/A'}`;
        document.getElementById('info-address').textContent = u.address || 'No address registered';
        document.getElementById('info-emergency').textContent = u.emergencyContactName ? `${u.emergencyContactName} (${u.emergencyContactPhone})` : 'Not provided';

        // Avatars
        const photoPath = u.userPhotoURL || '/StepCare/images/default.jpg';
        document.getElementById('avatar-image').src = photoPath;
        
        const navAvatar = document.getElementById('nav-avatar');
        if (navAvatar) navAvatar.src = photoPath;

        // Stats
        document.getElementById('stat-upcoming').textContent = data.stats.upcoming_appointments || 0;
        const joinedView = document.getElementById('family-joined-view');
        const notJoinedView = document.getElementById('family-not-joined-view');
        if (data.stats.has_family) {
          joinedView.classList.remove('hidden');
          notJoinedView.classList.add('hidden');
          document.getElementById('stat-family-members').textContent = data.stats.family_count;
        } else {
          joinedView.classList.add('hidden');
          notJoinedView.classList.remove('hidden');
        }

        // Appointments List
        const apptsContainer = document.getElementById('appointments-stream-container');
        if (!data.appointments || data.appointments.length === 0) {
          apptsContainer.innerHTML = `
            <div class="py-16 text-center border-2 border-dashed border-neutral-100 rounded-3xl p-8 bg-neutral-50/50">
              <div class="w-12 h-12 rounded-2xl bg-white border border-neutral-200 text-neutral-400 flex items-center justify-center mx-auto mb-3 shadow-2xs">
                <i data-lucide="calendar-x" class="w-6 h-6"></i>
              </div>
              <h4 class="text-sm font-bold text-neutral-800">No appointments scheduled</h4>
              <p class="text-xs text-neutral-400 mt-1 max-w-xs mx-auto leading-relaxed">
                You currently don't have any past or upcoming service bookings.
              </p>
            </div>
          `;
        } else {
          apptsContainer.innerHTML = data.appointments.map(a => {
            const dateObj = new Date(a.appointmentDate);
            const day = isNaN(dateObj.getDate()) ? '26' : dateObj.getDate();
            const month = isNaN(dateObj.getDate()) ? 'Aug' : dateObj.toLocaleString('en-US', { month: 'short' });

            let statusClass = 'bg-blue-50 text-blue-600 border border-blue-200';
            let statusLabel = 'Booked';

            const rawStatus = (a.appointmentStatus || '').toLowerCase();
            if (rawStatus === 'completed') {
              statusClass = 'bg-emerald-50 text-emerald-600 border border-emerald-200';
              statusLabel = 'Completed';
            } else if (rawStatus === 'cancelled') {
              statusClass = 'bg-red-50 text-red-600 border border-red-200';
              statusLabel = 'Cancelled';
            }

            return `
              <div class="flex items-center justify-between p-4 rounded-2xl border border-neutral-100 hover:bg-neutral-50/70 transition">
                <div class="text-center w-12 shrink-0">
                  <p class="text-base font-black text-neutral-900 leading-none">${day}</p>
                  <p class="text-[11px] font-bold text-neutral-400 mt-1 uppercase">${month}</p>
                </div>

                <div class="min-w-0 flex-1 px-6">
                  <h4 class="text-sm font-bold text-neutral-900 truncate">${a.serviceName || 'General Care Service'}</h4>
                  <p class="text-xs text-neutral-400 mt-0.5 truncate">${a.nurseName ? 'Nurse: ' + a.nurseName : 'Staff Assigned'}</p>
                </div>

                <div class="px-4 shrink-0">
                  <span class="px-5 py-2 rounded-full text-xs font-bold tracking-wide capitalize ${statusClass}">
                    ${statusLabel}
                  </span>
                </div>

                <div class="text-right w-24 shrink-0">
                  <span class="text-sm font-black text-neutral-900">
                    RM ${parseFloat(a.price || 0).toFixed(2)}
                  </span>
                </div>
              </div>
            `;
          }).join('');
        }

        // Sync View Texts in Modal
        document.getElementById('view-name').textContent = fullName;
        document.getElementById('view-contact').textContent = `${u.email || '-'} | ${u.phoneNumber || '-'}`;
        document.getElementById('view-identity').textContent = `${u.gender || 'Not specified'} | IC: ${u.IC || '-'} | DOB: ${u.dateOfBirth || '-'}`;
        document.getElementById('view-address').textContent = u.address || 'No address registered';
        document.getElementById('view-emergency').textContent = `${u.emergencyContactName || '-'} (${u.emergencyContactPhone || '-'})`;

        // Prefill Inputs
        document.getElementById('edit-first-name').value = u.firstName || '';
        document.getElementById('edit-last-name').value = u.lastName || '';
        document.getElementById('edit-email').value = u.email || '';
        document.getElementById('edit-phone').value = u.phoneNumber || '';
        document.getElementById('edit-ic').value = u.IC || '';
        document.getElementById('edit-gender').value = u.gender || 'Male';
        document.getElementById('edit-dob').value = u.dateOfBirth || '';
        document.getElementById('edit-address').value = u.address || '';
        document.getElementById('edit-emg-name').value = u.emergencyContactName || '';
        document.getElementById('edit-emg-phone').value = u.emergencyContactPhone || '';

        if (window.lucide) lucide.createIcons();
      })
      .catch(err => console.error("Database fetch error:", err));
  }

  loadUserProfile();

  // ============================================================
  // 3. Avatar Upload Logic
  // ============================================================
  const fileInput = document.getElementById('avatar-file-input');
  const uploadTrigger = document.getElementById('btn-trigger-upload');
  
  if (uploadTrigger && fileInput) {
    uploadTrigger.onclick = () => fileInput.click();

    fileInput.onchange = () => {
      if (!fileInput.files || fileInput.files.length === 0) return;
      const fd = new FormData();
      fd.append('action', 'upload_avatar');
      fd.append('avatar', fileInput.files[0]);

      fetch('./user-profile-actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            const freshUrl = res.photoURL + '?t=' + new Date().getTime();
            document.getElementById('avatar-image').src = freshUrl;
            
            const navAvatar = document.getElementById('nav-avatar');
            if (navAvatar) navAvatar.src = freshUrl;
          } else {
            alert(res.message);
          }
        });
    };
  }

  // ============================================================
  // 4. Modal Controls
  // ============================================================
  const modal = document.getElementById('edit-profile-modal');
  const openBtn = document.getElementById('open-edit-modal-btn');
  const closeBtn = document.getElementById('close-modal-btn');
  const closeX = document.getElementById('close-modal-x');

  if (modal && openBtn && closeBtn && closeX) {
    const hideModal = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    closeBtn.onclick = hideModal;
    closeX.onclick = hideModal;

    openBtn.onclick = () => {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    };
  }

  // ============================================================
  // 5. Submit Profile Changes
  // ============================================================
  const form = document.getElementById('edit-profile-form');
  if (form) {
    form.onsubmit = (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById('save-profile-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = "Saving...";

      const fd = new FormData();
      fd.append('action', 'update_profile');
      fd.append('firstName', document.getElementById('edit-first-name').value);
      fd.append('lastName', document.getElementById('edit-last-name').value);
      fd.append('email', document.getElementById('edit-email').value);
      fd.append('phoneNumber', document.getElementById('edit-phone').value);
      fd.append('IC', document.getElementById('edit-ic').value);
      fd.append('gender', document.getElementById('edit-gender').value);
      fd.append('dateOfBirth', document.getElementById('edit-dob').value);
      fd.append('address', document.getElementById('edit-address').value);
      fd.append('emergencyContactName', document.getElementById('edit-emg-name').value);
      fd.append('emergencyContactPhone', document.getElementById('edit-emg-phone').value);

      fetch('./user-profile-actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          submitBtn.disabled = false;
          submitBtn.textContent = "Save All Changes";
          if (res.success) {
            document.getElementById('edit-profile-modal').classList.add('hidden');
            document.getElementById('edit-profile-modal').classList.remove('flex');
            loadUserProfile(); // Refresh data
          } else {
            alert(res.message);
          }
        })
        .catch(err => {
          submitBtn.disabled = false;
          submitBtn.textContent = "Save All Changes";
          alert("Error: " + err);
        });
    };
  }
});