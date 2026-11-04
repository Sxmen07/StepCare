// English comments only
(function () {
  'use strict';

  let staffList = [];
  let isSuperAdmin = false;
  let pendingDelete = null;

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
    // 1. Mount sidebar
    fetch('/StepCare/components/sidebar-admin.html')
      .then(res => res.text())
      .then(html => injectComponent('sidebar-container', html));

    // 2. Mount topbar
    fetch('/StepCare/components/topbar.html')
      .then(res => res.text())
      .then(html => {
        injectComponent('topbar-container', html);
        const title = document.getElementById('topbar-page-title');
        if (title) title.textContent = 'Staff Management';
      });

    // 3. Load staff list
    loadStaffData();

    // 4. Bind events
    initEventHandlers();
  });

  async function loadStaffData() {
    try {
      const res = await fetch('./staff_actions.php?action=fetch_staff');
      const data = await res.json();
      if (data.status === 'success') {
        staffList = data.data || [];
        if (data.is_super_admin !== undefined) {
          isSuperAdmin = Boolean(data.is_super_admin);
        }
        renderTable();
      } else {
        console.error('Fetch error:', data.message);
        renderTable();
      }
    } catch (err) {
      console.error('Request error:', err);
      renderTable();
    }
  }

  function getAvatarBubble(name, role) {
    const clean = (name || 'Staff').trim();
    const parts = clean.split(/\s+/).filter(Boolean);
    let initials = '';
    if (parts.length >= 2) {
      initials = (parts[0][0] + parts[1][0]).toUpperCase();
    } else {
      initials = clean.substring(0, 2).toUpperCase();
    }

    let bgClass = 'bg-[#EEF2FF] text-[#3C52C3]';
    if (role === 'Super Admin') {
      bgClass = 'bg-purple-50 text-purple-700';
    } else if (role === 'Admin') {
      bgClass = 'bg-teal-50 text-teal-700';
    }

    return `
      <div class="w-8 h-8 rounded-full ${bgClass} font-bold text-[11px] flex items-center justify-center border border-black/5 shrink-0 select-none">
        ${initials}
      </div>
    `;
  }

  function renderTable() {
    const container = document.getElementById('staff-list-container');
    const searchInput = document.getElementById('staff-search-input');
    const roleFilter = document.getElementById('filter-role');

    const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const currentRole = roleFilter ? roleFilter.value : 'All';

    const filtered = staffList.filter(item => {
      const matchesRole = (currentRole === 'All') || (item.role === currentRole);
      const matchesSearch = item.name.toLowerCase().includes(searchTerm) || 
                            item.email.toLowerCase().includes(searchTerm) ||
                            item.id.toLowerCase().includes(searchTerm) ||
                            (item.specialization && item.specialization.toLowerCase().includes(searchTerm)) ||
                            (item.created_by && item.created_by.toLowerCase().includes(searchTerm));
      return matchesRole && matchesSearch;
    });

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="py-24 text-center">
          <div class="w-12 h-12 rounded-full bg-neutral-100 text-neutral-400 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="users" class="w-6 h-6"></i>
          </div>
          <p class="text-sm font-semibold text-neutral-600">No staff members found</p>
          <p class="text-xs text-neutral-400 mt-1">There are no personnel records matching your filter.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    container.innerHTML = filtered.map(item => {
      let roleBadge = '';
      if (item.role === 'Super Admin') {
        roleBadge = '<span class="inline-block px-3 py-1 rounded-full text-[11px] font-semibold border bg-purple-50 text-purple-700 border-purple-200">Super Admin</span>';
      } else if (item.role === 'Nurse') {
        roleBadge = '<span class="inline-block px-3 py-1 rounded-full text-[11px] font-semibold border bg-sky-50 text-sky-700 border-sky-200">Nurse</span>';
      } else {
        roleBadge = '<span class="inline-block px-3 py-1 rounded-full text-[11px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">Admin</span>';
      }

      const fallbackBadge = getAvatarBubble(item.name, item.role);
      const avatarHtml = item.photo
        ? `<div class="relative w-8 h-8 shrink-0">
             <img src="${item.photo}" 
                  alt="${item.name}" 
                  class="w-8 h-8 rounded-full object-cover border border-neutral-200" 
                  onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
             <div style="display:none;" class="absolute inset-0">${fallbackBadge}</div>
           </div>`
        : fallbackBadge;

      const isRootSuperAdmin = (item.role === 'Super Admin' && item.raw_id === 1);
      let deleteButtonHtml = '';

      if (!isSuperAdmin) {
        deleteButtonHtml = `
          <button type="button" 
                  disabled
                  title="Only Super Admins can delete accounts"
                  class="w-[88px] inline-flex items-center justify-center gap-1.5 py-1.5 bg-neutral-50 text-neutral-400 text-xs font-semibold rounded-lg border border-neutral-200 shadow-2xs cursor-not-allowed opacity-60">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            <span>Delete</span>
          </button>
        `;
      } else if (isRootSuperAdmin) {
        deleteButtonHtml = `
          <button type="button" 
                  disabled
                  title="The root Super Admin account cannot be deleted"
                  class="w-[88px] inline-flex items-center justify-center gap-1.5 py-1.5 bg-neutral-50 text-neutral-400 text-xs font-semibold rounded-lg border border-neutral-200 shadow-2xs cursor-not-allowed opacity-60">
            <i data-lucide="shield" class="w-3.5 h-3.5 text-neutral-400"></i>
            <span>Protected</span>
          </button>
        `;
      } else {
        deleteButtonHtml = `
          <button type="button" 
                  onclick="window.promptDeleteStaff('${item.id}', ${item.raw_id}, '${item.role}', '${item.name.replace(/'/g, "\\'")}')" 
                  class="w-[88px] inline-flex items-center justify-center gap-1.5 py-1.5 bg-white hover:bg-rose-50 text-rose-600 hover:text-rose-700 text-xs font-semibold rounded-lg border border-rose-200 hover:border-rose-300 shadow-2xs transition cursor-pointer">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            <span>Delete</span>
          </button>
        `;
      }

      const creatorDisplay = item.created_by 
        ? `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md font-mono text-[11px] font-bold bg-neutral-100 text-neutral-600 border border-neutral-200/80">
             <i data-lucide="user-check" class="w-3 h-3 text-neutral-400"></i>
             <span>${item.created_by}</span>
           </span>`
        : '<span class="text-neutral-300 font-mono text-xs">--</span>';

      const specSubtitle = (item.role === 'Nurse' && item.specialization && item.specialization !== '-')
        ? `<span class="text-[10px] text-neutral-400 block font-normal">${item.specialization}</span>`
        : '';

      return `
        <div class="staff-row grid grid-cols-12 items-center px-8 py-3.5 border-b border-neutral-100">
          <div class="col-span-2 text-xs font-mono font-bold text-neutral-500 pl-2">
            ${item.id}
          </div>

          <div class="col-span-3 flex items-center gap-3 min-w-0 pr-4">
            ${avatarHtml}
            <div class="min-w-0">
              <span class="text-xs font-bold text-neutral-900 truncate block">${item.name}</span>
              ${specSubtitle}
            </div>
          </div>

          <div class="col-span-2 text-xs text-neutral-600 font-medium truncate pr-4">
            ${item.email}
          </div>

          <div class="col-span-1 flex items-center justify-center">
            ${roleBadge}
          </div>

          <div class="col-span-2 flex items-center justify-center">
            ${creatorDisplay}
          </div>

          <div class="col-span-2 flex items-center justify-center gap-2">
            <button type="button" 
                    onclick="window.openEditModal('${item.id}')" 
                    class="w-[72px] inline-flex items-center justify-center gap-1.5 py-1.5 bg-white hover:bg-neutral-50 text-neutral-700 hover:text-neutral-900 text-xs font-semibold rounded-lg border border-neutral-200 hover:border-neutral-300 shadow-2xs transition cursor-pointer">
              <i data-lucide="pencil-line" class="w-3.5 h-3.5 text-neutral-400"></i>
              <span>Edit</span>
            </button>
            ${deleteButtonHtml}
          </div>
        </div>
      `;
    }).join('');

    if (window.lucide) lucide.createIcons();
  }

  window.promptDeleteStaff = function (formattedId, rawId, role, staffName) {
    if (!isSuperAdmin) {
      alert('Unauthorized: Only Super Administrators have permission to delete accounts.');
      return;
    }

    pendingDelete = { formattedId, rawId, role };
    const modal = document.getElementById('delete-confirm-modal');
    const descEl = document.getElementById('delete-modal-desc');
    descEl.innerHTML = `Are you sure you want to permanently delete <strong class="text-neutral-900">${staffName}</strong> (<span class="font-mono text-[#3C52C3]">${formattedId}</span>)? This will erase their authenticated profile and credentials.`;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
  };

  window.openEditModal = function (formattedId) {
    const item = staffList.find(s => s.id === formattedId);
    if (!item) return;

    document.getElementById('edit-raw-id').value = item.raw_id;
    document.getElementById('edit-original-role').value = item.role;
    document.getElementById('edit-modal-target-id').textContent = item.id;
    document.getElementById('edit-first-name').value = item.first_name || item.name.split(' ')[0] || '';
    document.getElementById('edit-last-name').value = item.last_name || item.name.split(' ').slice(1).join(' ') || '';

    const emailParts = item.email.split('@');
    document.getElementById('edit-email-prefix').value = emailParts[0] || '';

    const editSpecWrapper = document.getElementById('edit-specialization-wrapper');
    const editSpecInput = document.getElementById('edit-specialization');

    if (item.role === 'Nurse') {
      document.getElementById('edit-role-nurse').checked = true;
      document.getElementById('edit-role-suffix').textContent = '@nurse.stepcare.com';
      if (editSpecWrapper) editSpecWrapper.classList.remove('hidden');
      if (editSpecInput) editSpecInput.value = (item.specialization && item.specialization !== '-') ? item.specialization : '';
    } else {
      document.getElementById('edit-role-admin').checked = true;
      document.getElementById('edit-role-suffix').textContent = '@admin.stepcare.com';
      if (editSpecWrapper) editSpecWrapper.classList.add('hidden');
      if (editSpecInput) editSpecInput.value = '';
    }

    const editModal = document.getElementById('edit-modal');
    editModal.classList.remove('hidden');
    editModal.classList.add('flex');
  };

  function initEventHandlers() {
    const searchInput = document.getElementById('staff-search-input');
    if (searchInput) searchInput.addEventListener('input', renderTable);

    const filterRole = document.getElementById('filter-role');
    if (filterRole) filterRole.addEventListener('change', renderTable);

    const exportBtn = document.getElementById('export-excel-btn');
    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        const currentRole = filterRole ? filterRole.value : 'All';
        window.location.href = `./staff_actions.php?action=export_excel&role=${encodeURIComponent(currentRole)}`;
      });
    }

    const deleteModal = document.getElementById('delete-confirm-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');

    const closeDeleteModal = () => {
      deleteModal.classList.add('hidden');
      deleteModal.classList.remove('flex');
      pendingDelete = null;
    };

    if (cancelDeleteBtn) cancelDeleteBtn.onclick = closeDeleteModal;

    if (confirmDeleteBtn) {
      confirmDeleteBtn.onclick = async () => {
        if (!pendingDelete) return;
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.textContent = 'Deleting...';

        try {
          const res = await fetch('./staff_actions.php?action=delete_staff', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pendingDelete)
          });
          const result = await res.json();

          if (result.status === 'success') {
            closeDeleteModal();
            await loadStaffData();
          } else {
            alert(result.message || 'Failed to delete account');
          }
        } catch (err) {
          alert('Network error: ' + err.message);
        } finally {
          confirmDeleteBtn.disabled = false;
          confirmDeleteBtn.textContent = 'Yes, Delete';
        }
      };
    }

    // Toggle specialization input visibility on role change
    const createSuffixLabel = document.getElementById('create-role-suffix');
    const createSpecWrapper = document.getElementById('create-specialization-wrapper');
    document.querySelectorAll('.create-role-radio').forEach(radio => {
      radio.addEventListener('change', (e) => {
        const isNurse = (e.target.value === 'Nurse');
        if (createSuffixLabel) {
          createSuffixLabel.textContent = isNurse ? '@nurse.stepcare.com' : '@admin.stepcare.com';
        }
        if (createSpecWrapper) {
          createSpecWrapper.classList.toggle('hidden', !isNurse);
        }
      });
    });

    const editSuffixLabel = document.getElementById('edit-role-suffix');
    const editSpecWrapper = document.getElementById('edit-specialization-wrapper');
    document.querySelectorAll('.edit-role-radio').forEach(radio => {
      radio.addEventListener('change', (e) => {
        const isNurse = (e.target.value === 'Nurse');
        if (editSuffixLabel) {
          editSuffixLabel.textContent = isNurse ? '@nurse.stepcare.com' : '@admin.stepcare.com';
        }
        if (editSpecWrapper) {
          editSpecWrapper.classList.toggle('hidden', !isNurse);
        }
      });
    });

    // Create Modal Controls
    const createModal = document.getElementById('create-modal');
    const openCreateBtn = document.getElementById('open-create-modal-btn');
    const closeCreateBtn = document.getElementById('close-modal-btn');
    const closeCreateX = document.getElementById('close-modal-x');

    const showCreate = () => { createModal.classList.remove('hidden'); createModal.classList.add('flex'); };
    const hideCreate = () => { createModal.classList.add('hidden'); createModal.classList.remove('flex'); };

    if (openCreateBtn) openCreateBtn.onclick = showCreate;
    if (closeCreateBtn) closeCreateBtn.onclick = hideCreate;
    if (closeCreateX) closeCreateX.onclick = hideCreate;

    // Edit Modal Controls
    const editModal = document.getElementById('edit-modal');
    const closeEditBtn = document.getElementById('close-edit-modal-btn');
    const closeEditX = document.getElementById('close-edit-modal-x');

    const hideEdit = () => { editModal.classList.add('hidden'); editModal.classList.remove('flex'); };
    if (closeEditBtn) closeEditBtn.onclick = hideEdit;
    if (closeEditX) closeEditX.onclick = hideEdit;

    // Submit Create Form
    const createForm = document.getElementById('create-staff-form');
    createForm.onsubmit = async (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById('submit-create-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Creating...';

      const selectedRole = createForm.querySelector('input[name="staffRole"]:checked').value;
      const specVal = document.getElementById('create-specialization').value.trim();

      const payload = {
        firstName: document.getElementById('create-first-name').value.trim(),
        lastName: document.getElementById('create-last-name').value.trim(),
        emailPrefix: document.getElementById('create-email-prefix').value.trim(),
        role: selectedRole,
        specialization: (selectedRole === 'Nurse') ? (specVal || 'General Nursing') : null
      };

      try {
        const res = await fetch('./staff_actions.php?action=create_staff', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (result.status === 'success') {
          hideCreate();
          createForm.reset();
          if (createSuffixLabel) createSuffixLabel.textContent = '@nurse.stepcare.com';
          if (createSpecWrapper) createSpecWrapper.classList.remove('hidden');

          document.getElementById('res-staff-id').textContent = result.created_user.id;
          document.getElementById('res-staff-name').textContent = result.created_user.name;
          document.getElementById('res-staff-email').textContent = result.created_user.email;
          document.getElementById('res-staff-creator').textContent = result.created_user.created_by;
          document.getElementById('res-staff-pass').textContent = result.created_user.default_password;

          const credModal = document.getElementById('credential-modal');
          credModal.classList.remove('hidden');
          credModal.classList.add('flex');

          await loadStaffData();
        } else {
          alert(result.message || 'Creation failed');
        }
      } catch (err) {
        alert('Request error: ' + err.message);
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account';
      }
    };

    // Submit Edit Form
    const editForm = document.getElementById('edit-staff-form');
    editForm.onsubmit = async (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById('submit-edit-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';

      const selectedRole = editForm.querySelector('input[name="editStaffRole"]:checked').value;
      const editSpecVal = document.getElementById('edit-specialization').value.trim();

      const payload = {
        rawId: document.getElementById('edit-raw-id').value,
        originalRole: document.getElementById('edit-original-role').value,
        firstName: document.getElementById('edit-first-name').value.trim(),
        lastName: document.getElementById('edit-last-name').value.trim(),
        emailPrefix: document.getElementById('edit-email-prefix').value.trim(),
        role: selectedRole,
        specialization: (selectedRole === 'Nurse') ? (editSpecVal || 'General Nursing') : null
      };

      try {
        const res = await fetch('./staff_actions.php?action=update_staff', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (result.status === 'success') {
          hideEdit();
          await loadStaffData();
        } else {
          alert(result.message || 'Update failed');
        }
      } catch (err) {
        alert('Request error: ' + err.message);
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Changes';
      }
    };

    // Dismiss credential modal
    document.getElementById('dismiss-credential-btn').onclick = () => {
      const credModal = document.getElementById('credential-modal');
      credModal.classList.add('hidden');
      credModal.classList.remove('flex');
    };
  }
})();