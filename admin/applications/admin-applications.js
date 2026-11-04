// English comments only
(function () {
  'use strict';

  let applicationsList = [];
  let leaveTypesList = [];
  let myBalances = [];
  let isSuperAdmin = false;
  let pendingAction = null;
  let selectedFile = null;

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
    fetch('/StepCare/components/sidebar-admin.html')
      .then(res => res.text())
      .then(html => injectComponent('sidebar-container', html));

    fetch('/StepCare/components/topbar.html')
      .then(res => res.text())
      .then(html => {
        injectComponent('topbar-container', html);
        const title = document.getElementById('topbar-page-title');
        if (title) title.textContent = 'Applications';
      });

    loadLeaveTypes();
    loadApplications();
    loadMyBalances();
    initEventHandlers();
  });

  // Switch Edge Tabs
  window.switchTab = function (tab) {
    const btnReview = document.getElementById('tab-btn-review');
    const btnMyApp = document.getElementById('tab-btn-my-app');
    const btnPolicy = document.getElementById('tab-btn-policy');

    const paneReview = document.getElementById('pane-review');
    const paneMyApp = document.getElementById('pane-myapp');
    const panePolicy = document.getElementById('pane-policy');

    btnReview.className = "relative py-4 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border-t-2 border-transparent bg-transparent text-neutral-500 hover:bg-blue-50/60 hover:text-[#3C52C3]";
    btnMyApp.className = "relative py-4 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border-t-2 border-transparent bg-transparent text-neutral-500 hover:bg-blue-50/60 hover:text-[#3C52C3]";
    btnPolicy.className = "relative py-4 text-xs font-bold transition items-center justify-center gap-2 cursor-pointer border-t-2 border-transparent bg-transparent text-neutral-500 hover:bg-purple-50 hover:text-purple-700 " + (isSuperAdmin ? 'flex' : 'hidden');

    paneReview.classList.add('hidden');
    paneMyApp.classList.add('hidden');
    panePolicy.classList.add('hidden');

    if (tab === 'review') {
      btnReview.className = "relative py-4 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border-t-2 border-[#3C52C3] bg-white text-[#3C52C3]";
      paneReview.classList.remove('hidden');
      loadApplications();
    } else if (tab === 'myapp') {
      btnMyApp.className = "relative py-4 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border-t-2 border-[#3C52C3] bg-white text-[#3C52C3]";
      paneMyApp.classList.remove('hidden');
      loadMyBalances();
      window.loadMyApplications();
    } else if (tab === 'policy') {
      btnPolicy.className = "relative py-4 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border-t-2 border-purple-600 bg-white text-purple-700";
      panePolicy.classList.remove('hidden');
      renderPolicies();
    }
    if (window.lucide) lucide.createIcons();
  };

  // Helper: Return nice category capsule badge
  function getCategoryBadge(name) {
    const cat = (name || 'Annual Leave').toLowerCase();
    if (cat.includes('mc') || cat.includes('medical')) {
      return `<span class="inline-block px-2.5 py-0.5 rounded-md text-[10px] font-bold border bg-amber-50 text-amber-700 border-amber-200">${name}</span>`;
    }
    if (cat.includes('annual')) {
      return `<span class="inline-block px-2.5 py-0.5 rounded-md text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">${name}</span>`;
    }
    if (cat.includes('emergency')) {
      return `<span class="inline-block px-2.5 py-0.5 rounded-md text-[10px] font-bold border bg-rose-50 text-rose-700 border-rose-200">${name}</span>`;
    }
    return `<span class="inline-block px-2.5 py-0.5 rounded-md text-[10px] font-bold border bg-indigo-50 text-indigo-700 border-indigo-200">${name}</span>`;
  }

  // Load Leave Types
  async function loadLeaveTypes() {
    try {
      const res = await fetch('./application_actions.php?action=fetch_leave_types');
      const result = await res.json();
      if (result.status === 'success') {
        leaveTypesList = result.data || [];
        const selectEl = document.getElementById('apply-leave-type');
        if (selectEl) {
          selectEl.innerHTML = leaveTypesList.map(t => 
            `<option value="${t.leaveTypeID}">${t.leaveName} (${t.daysPerYear} days/yr)</option>`
          ).join('');
        }
      }
    } catch (e) {
      console.error(e);
    }
  }

  // Load My Leave Balances (Current Logged Admin)
  async function loadMyBalances() {
    try {
      const res = await fetch('./application_actions.php?action=fetch_my_balance');
      const result = await res.json();
      if (result.status === 'success') {
        myBalances = result.data || [];
        updateBalancePreview();
      }
    } catch (e) {
      console.error(e);
    }
  }

  function updateBalancePreview() {
    const selectEl = document.getElementById('apply-leave-type');
    const previewContainer = document.getElementById('quota-preview-container');
    if (!selectEl || !previewContainer) return;

    const currentId = parseInt(selectEl.value);
    const found = myBalances.find(b => b.leaveTypeID === currentId);

    if (found) {
      const isExhausted = found.remaining <= 0;
      previewContainer.innerHTML = `
        <div class="flex items-center justify-between px-3 py-1.5 rounded-xl ${isExhausted ? 'bg-rose-50 border border-rose-200' : 'bg-emerald-50 border border-emerald-200'}">
          <span class="text-[11px] font-semibold ${isExhausted ? 'text-rose-700' : 'text-emerald-700'}">
            Annual Quota Balance:
          </span>
          <span class="text-[11px] font-mono font-bold ${isExhausted ? 'text-rose-700' : 'text-emerald-700'}">
            ${found.remaining} / ${found.total} Days Left (${found.used} Approved)
          </span>
        </div>
      `;
    } else {
      previewContainer.innerHTML = '';
    }
  }

  // Fetch Applications Review Stream
  async function loadApplications() {
    try {
      const filterStatus = document.getElementById('filter-status').value;
      const targetRole = document.getElementById('filter-target-role').value;

      const res = await fetch(`./application_actions.php?action=fetch_applications&status=${filterStatus}&target_role=${targetRole}`);
      const result = await res.json();
      if (result.status === 'success') {
        applicationsList = result.data || [];
        if (result.is_super_admin !== undefined) {
          isSuperAdmin = Boolean(result.is_super_admin);
          const headerContainer = document.getElementById('tabs-header-container');
          const btnPolicy = document.getElementById('tab-btn-policy');
          if (isSuperAdmin) {
            headerContainer.className = "grid grid-cols-3 bg-neutral-50/40 shrink-0";
            btnPolicy.classList.remove('hidden');
            btnPolicy.classList.add('flex');
          } else {
            headerContainer.className = "grid grid-cols-2 bg-neutral-50/40 shrink-0";
            btnPolicy.classList.add('hidden');
            btnPolicy.classList.remove('flex');
          }
        }
        renderTable();
      }
    } catch (err) {
      console.error('Fetch applications error:', err);
    }
  }

  function getAvatarBubble(name, role) {
    const clean = (name || 'Staff').trim();
    const initials = clean ? clean.split(/\s+/).map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'SC';
    const bgClass = (role === 'Nurse') ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700';
    return `
      <div class="w-8 h-8 rounded-full ${bgClass} font-bold text-[11px] flex items-center justify-center border border-black/5 shrink-0 select-none">
        ${initials}
      </div>
    `;
  }

  // Render applications table rows (Without redundant Role Tag)
  function renderTable() {
    const container = document.getElementById('applications-list-container');
    const searchInput = document.getElementById('app-search-input');
    const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';

    const filtered = applicationsList.filter(item => {
      return item.staffName.toLowerCase().includes(searchTerm) || 
             item.staffId.toLowerCase().includes(searchTerm) ||
             item.leaveName.toLowerCase().includes(searchTerm) ||
             item.reason.toLowerCase().includes(searchTerm);
    });

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="py-24 text-center">
          <div class="w-12 h-12 rounded-full bg-neutral-100 text-neutral-400 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="file-check-2" class="w-6 h-6"></i>
          </div>
          <p class="text-sm font-semibold text-neutral-600">No applications found</p>
          <p class="text-xs text-neutral-400 mt-1">There are no requests matching your filter.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    container.innerHTML = filtered.map(item => {
      let statusBadge = '';
      if (item.status === 'pending') {
        statusBadge = '<span class="inline-block px-3.5 py-1 rounded-full text-xs font-semibold border bg-amber-50 text-amber-700 border-amber-200">Pending</span>';
      } else if (item.status === 'approved') {
        statusBadge = '<span class="inline-block px-3.5 py-1 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">Approved</span>';
      } else {
        const tooltip = item.rejectionReason ? `title="Reason: ${item.rejectionReason.replace(/"/g, '&quot;')}"` : '';
        statusBadge = `<span ${tooltip} class="inline-block px-3.5 py-1 rounded-full text-xs font-semibold border bg-rose-50 text-rose-700 border-rose-200 cursor-help">Rejected</span>`;
      }

      const fallbackBadge = getAvatarBubble(item.staffName, item.applicantType);
      const avatarHtml = item.photo
        ? `<div class="relative w-8 h-8 shrink-0">
             <img src="${item.photo}" alt="${item.staffName}" class="w-8 h-8 rounded-full object-cover border border-neutral-200" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
             <div style="display:none;" class="absolute inset-0">${fallbackBadge}</div>
           </div>`
        : fallbackBadge;

      const proofLink = item.proofDocument
        ? `<a href="${item.proofDocument}" target="_blank" onclick="event.stopPropagation();" title="View document: ${item.proofDocumentName}" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#3C52C3] hover:underline mt-0.5">
             <i data-lucide="paperclip" class="w-3 h-3"></i>
             <span class="truncate max-w-[130px]">${item.proofDocumentName || 'Proof Document'}</span>
           </a>`
        : '';

      let actionCell = '';
      if (item.status === 'pending') {
        if (item.applicantType === 'Admin' && !isSuperAdmin) {
          actionCell = `
            <span title="Only Super Admin can review Admin requests" class="text-xs font-medium text-neutral-400 cursor-help flex items-center justify-center gap-1">
              <i data-lucide="shield" class="w-3.5 h-3.5 text-purple-400"></i>
              <span>Super Admin Only</span>
            </span>
          `;
        } else {
          actionCell = `
            <div class="flex items-center justify-center gap-2">
              <button type="button" 
                      onclick="window.promptAction('${item.applicantType}', ${item.requestId}, 'approved')" 
                      class="w-[76px] inline-flex items-center justify-center gap-1.5 py-1.5 bg-white hover:bg-emerald-50 text-emerald-600 hover:text-emerald-700 text-xs font-semibold rounded-lg border border-emerald-200 hover:border-emerald-300 shadow-2xs transition cursor-pointer">
                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                <span>Approve</span>
              </button>
              <button type="button" 
                      onclick="window.promptAction('${item.applicantType}', ${item.requestId}, 'rejected')" 
                      class="w-[76px] inline-flex items-center justify-center gap-1.5 py-1.5 bg-white hover:bg-rose-50 text-rose-600 hover:text-rose-700 text-xs font-semibold rounded-lg border border-rose-200 hover:border-rose-300 shadow-2xs transition cursor-pointer">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                <span>Reject</span>
              </button>
            </div>
          `;
        }
      } else {
        actionCell = `
          <span class="text-xs font-medium text-neutral-400">
            ${item.reviewedBy ? 'By ' + item.reviewedBy : 'Resolved'}
          </span>
        `;
      }

      return `
        <div class="app-row grid grid-cols-12 items-center px-8 py-3.5 border-b border-neutral-100">
          <div class="col-span-2 text-xs font-mono font-bold text-neutral-500 pl-2">
            ${item.staffId}
          </div>

          <!-- Applicant Name & Dynamic Leave Badge (Cleaned) -->
          <div class="col-span-3 flex items-center gap-3 min-w-0 pr-4">
            ${avatarHtml}
            <div class="min-w-0">
              <div class="text-xs font-bold text-neutral-900 truncate">${item.staffName}</div>
              <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                ${getCategoryBadge(item.leaveName)}
                ${item.balanceInfo ? `<span class="text-[10px] text-neutral-400 font-mono font-semibold">(${item.balanceInfo.remaining}d left)</span>` : ''}
              </div>
            </div>
          </div>

          <div class="col-span-2 text-xs pr-4">
            <div class="font-semibold text-neutral-800">${item.requestDate}</div>
            <div class="text-[11px] font-mono text-neutral-400">${item.startTime} - ${item.endTime}</div>
          </div>

          <div class="col-span-2 text-xs text-neutral-600 pr-4">
            <div class="truncate" title="${item.reason}">${item.reason}</div>
            ${proofLink}
          </div>

          <div class="col-span-1 text-center">
            ${statusBadge}
          </div>

          <div class="col-span-2 flex items-center justify-center">
            ${actionCell}
          </div>
        </div>
      `;
    }).join('');

    if (window.lucide) lucide.createIcons();
  }

  // Render Super Admin Policy Tab (Full Width Edge-to-Edge)
  function renderPolicies() {
    const container = document.getElementById('policy-list-container');
    if (!container) return;

    if (leaveTypesList.length === 0) {
      container.innerHTML = `<div class="py-20 text-center text-xs text-neutral-400">No policy definitions found.</div>`;
      return;
    }

    container.innerHTML = leaveTypesList.map(p => {
      const isCustom = (p.isCustom == 1);
      let roleLabel = '<span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-neutral-100 text-neutral-700">All Personnel</span>';
      if (p.targetRole === 'nurse') roleLabel = '<span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200">Nurse Only</span>';
      if (p.targetRole === 'admin') roleLabel = '<span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Admin Only</span>';

      return `
        <div class="app-row grid grid-cols-12 px-8 py-3.5 items-center border-b border-neutral-100">
          <div class="col-span-4 flex items-center gap-2 pl-2">
            <span class="text-xs font-bold text-neutral-900">${p.leaveName}</span>
            ${isCustom 
              ? '<span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-50 text-purple-700 border border-purple-200">Custom</span>' 
              : '<span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-neutral-100 text-neutral-500">Standard</span>'}
          </div>
          <div class="col-span-3 text-center">${roleLabel}</div>
          <div class="col-span-3 text-center text-xs font-mono font-bold text-neutral-800">${p.daysPerYear} Days / Year</div>
          <div class="col-span-2 flex items-center justify-center gap-2">
            <button type="button" 
                    onclick="window.editPolicy(${p.leaveTypeID}, '${p.leaveName.replace(/'/g, "\\'")}', '${p.targetRole}', ${p.daysPerYear})" 
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-white hover:bg-neutral-50 text-neutral-700 text-xs font-semibold rounded-lg border border-neutral-200 hover:border-neutral-300 shadow-2xs transition cursor-pointer">
              <i data-lucide="pencil-line" class="w-3.5 h-3.5 text-neutral-400"></i>
              <span>Edit</span>
            </button>
            ${isCustom ? `
              <button type="button" 
                      onclick="window.deletePolicy(${p.leaveTypeID})" 
                      class="inline-flex items-center gap-1 px-3 py-1.5 bg-white hover:bg-rose-50 text-rose-600 text-xs font-semibold rounded-lg border border-rose-200 hover:border-rose-300 shadow-2xs transition cursor-pointer">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                <span>Delete</span>
              </button>
            ` : ''}
          </div>
        </div>
      `;
    }).join('');

    if (window.lucide) lucide.createIcons();
  }

  window.openPolicyModal = function () {
    document.getElementById('policy-form').reset();
    document.getElementById('policy-id').value = 0;
    document.getElementById('policy-modal-title').textContent = 'Add Custom Policy';
    document.getElementById('policy-modal').classList.remove('hidden');
    document.getElementById('policy-modal').classList.add('flex');
  };

  window.editPolicy = function (id, name, role, days) {
    document.getElementById('policy-id').value = id;
    document.getElementById('policy-name').value = name;
    document.getElementById('policy-role').value = role;
    document.getElementById('policy-days').value = days;
    document.getElementById('policy-modal-title').textContent = 'Edit Leave Policy';
    document.getElementById('policy-modal').classList.remove('hidden');
    document.getElementById('policy-modal').classList.add('flex');
  };

  window.deletePolicy = async function (id) {
    if (!confirm('Are you sure you want to permanently delete this policy?')) return;
    try {
      const res = await fetch('./application_actions.php?action=delete_leave_type', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ leaveTypeID: id })
      });
      const result = await res.json();
      if (result.status === 'success') {
        await loadLeaveTypes();
        renderPolicies();
      } else {
        alert(result.message);
      }
    } catch (e) {
      alert(e.message);
    }
  };

  // Fetch My Applications Stream for Right Column
  window.loadMyApplications = async function () {
    const container = document.getElementById('my-applications-container');
    try {
      const res = await fetch('./application_actions.php?action=fetch_my_leaves');
      const result = await res.json();

      if (!result.data || result.data.length === 0) {
        container.innerHTML = `
          <div class="py-16 text-center">
            <div class="w-10 h-10 rounded-full bg-neutral-100 text-neutral-400 flex items-center justify-center mx-auto mb-2">
              <i data-lucide="inbox" class="w-5 h-5"></i>
            </div>
            <p class="text-xs font-semibold text-neutral-600">No applications found</p>
            <p class="text-[11px] text-neutral-400 mt-0.5">Submit a request using the form on the left.</p>
          </div>
        `;
        if (window.lucide) lucide.createIcons();
        return;
      }

      container.innerHTML = result.data.map(l => {
        let statusBadge = '';
        if (l.status === 'pending') {
          statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border bg-amber-50 text-amber-700 border-amber-200">Pending</span>';
        } else if (l.status === 'approved') {
          statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">Approved</span>';
        } else {
          statusBadge = `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border bg-rose-50 text-rose-700 border-rose-200">Declined</span>`;
        }

        const proofAttachment = l.proofDocument
          ? `<div class="pt-1">
               <a href="${l.proofDocument}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#3C52C3] hover:underline bg-indigo-50/60 px-2 py-0.5 rounded-md border border-indigo-100">
                 <i data-lucide="paperclip" class="w-3 h-3"></i>
                 <span>${l.proofDocumentName || 'View Document'}</span>
               </a>
             </div>`
          : '';

        return `
          <div class="bg-white p-5 rounded-2xl border border-neutral-200/90 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-neutral-900">${l.requestDate}</span>
                <span class="text-[11px] font-mono text-neutral-400">(${l.startTime} - ${l.endTime})</span>
                ${getCategoryBadge(l.leaveName)}
              </div>
              ${statusBadge}
            </div>
            
            <p class="text-xs text-neutral-600 leading-relaxed">${l.reason}</p>
            ${proofAttachment}
            
            ${l.rejectionReason ? `
              <div class="p-2.5 rounded-xl bg-rose-50/70 border border-rose-100 text-[11px] text-rose-700 leading-normal">
                <strong>Decline Reason:</strong> ${l.rejectionReason}
              </div>
            ` : ''}

            <div class="flex items-center justify-between pt-2 border-t border-neutral-100 text-[11px] text-neutral-400">
              <span>Submitted on ${l.submittedAt}</span>
              ${l.reviewedBy ? `<span class="text-neutral-500 font-medium">Reviewed by ${l.reviewedBy}</span>` : ''}
            </div>
          </div>
        `;
      }).join('');

      if (window.lucide) lucide.createIcons();
    } catch (e) {
      container.innerHTML = `<div class="p-8 text-center text-xs text-red-500">Failed to load applications.</div>`;
    }
  };

  // Prompt Review Modal with balance preview
  window.promptAction = function(applicantType, requestId, targetStatus) {
    const item = applicationsList.find(a => a.requestId === requestId && a.applicantType === applicantType);
    if (!item) return;

    pendingAction = { applicantType, requestId, targetStatus };

    const modal = document.getElementById('action-modal');
    const iconContainer = document.getElementById('modal-icon-container');
    const titleEl = document.getElementById('modal-title');
    const descEl = document.getElementById('modal-desc');
    const rejectWrapper = document.getElementById('reject-reason-wrapper');
    const rejectInput = document.getElementById('reject-reason-input');
    const confirmBtn = document.getElementById('modal-confirm-btn');

    document.getElementById('modal-staff-name').textContent = `${item.staffName} (${item.applicantType})`;
    document.getElementById('modal-leave-name').textContent = item.leaveName || 'Standard';
    document.getElementById('modal-target-date').textContent = item.requestDate;
    document.getElementById('modal-time-range').textContent = `${item.startTime} - ${item.endTime}`;
    document.getElementById('modal-staff-reason').textContent = item.reason;

    // Remaining balance preview inside modal
    const balanceRow = document.getElementById('modal-balance-row');
    const balanceText = document.getElementById('modal-balance-text');
    if (item.balanceInfo) {
      balanceText.textContent = `${item.balanceInfo.remaining} / ${item.balanceInfo.total} Days Left (${item.balanceInfo.used} used this year)`;
      balanceRow.classList.remove('hidden');
    } else {
      balanceRow.classList.add('hidden');
    }

    const proofRow = document.getElementById('modal-proof-row');
    const proofLink = document.getElementById('modal-proof-link');
    if (item.proofDocument) {
      proofLink.href = item.proofDocument;
      proofRow.classList.remove('hidden');
    } else {
      proofRow.classList.add('hidden');
    }

    rejectInput.value = '';

    if (targetStatus === 'approved') {
      rejectWrapper.classList.add('hidden');
      iconContainer.className = 'w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 bg-emerald-50 text-emerald-600';
      iconContainer.innerHTML = '<i data-lucide="check-circle" class="w-8 h-8"></i>';
      titleEl.textContent = 'Approve Application';
      descEl.textContent = `Are you sure you want to approve this request for ${item.staffName}? It will deduct 1 day from their ${item.leaveName} quota.`;
      confirmBtn.className = 'px-7 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition cursor-pointer shadow-sm';
      confirmBtn.textContent = 'Yes, Approve';
    } else {
      rejectWrapper.classList.remove('hidden');
      iconContainer.className = 'w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 bg-rose-50 text-rose-600';
      iconContainer.innerHTML = '<i data-lucide="alert-triangle" class="w-8 h-8"></i>';
      titleEl.textContent = 'Reject Application';
      descEl.textContent = 'Please provide an explanation. The applicant will receive this in their audit record.';
      confirmBtn.className = 'px-7 py-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-sm font-semibold transition cursor-pointer shadow-sm';
      confirmBtn.textContent = 'Yes, Reject Request';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (window.lucide) lucide.createIcons();
  };

  // Events initialization
  function initEventHandlers() {
    const searchInput = document.getElementById('app-search-input');
    if (searchInput) searchInput.addEventListener('input', renderTable);

    const filterStatus = document.getElementById('filter-status');
    if (filterStatus) filterStatus.addEventListener('change', loadApplications);

    const filterRole = document.getElementById('filter-target-role');
    if (filterRole) filterRole.addEventListener('change', loadApplications);

    const leaveTypeSelect = document.getElementById('apply-leave-type');
    if (leaveTypeSelect) leaveTypeSelect.addEventListener('change', updateBalancePreview);

    const exportBtn = document.getElementById('export-excel-btn');
    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        const currentStatus = filterStatus ? filterStatus.value : 'all';
        window.location.href = `./application_actions.php?action=export_excel&status=${encodeURIComponent(currentStatus)}`;
      });
    }

    // File Upload Controls
    const fileInput = document.getElementById('apply-proof-input');
    const idleState = document.getElementById('upload-idle-state');
    const selectedState = document.getElementById('upload-selected-state');
    const fileNameEl = document.getElementById('selected-file-name');
    const fileSizeEl = document.getElementById('selected-file-size');
    const removeFileBtn = document.getElementById('btn-remove-file');

    fileInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;

      selectedFile = file;
      fileNameEl.textContent = file.name;
      fileSizeEl.textContent = (file.size / 1024).toFixed(1) + ' KB';

      idleState.classList.add('hidden');
      selectedState.classList.remove('hidden');
      selectedState.classList.add('flex');
      if (window.lucide) lucide.createIcons();
    });

    removeFileBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      fileInput.value = '';
      selectedFile = null;
      selectedState.classList.add('hidden');
      selectedState.classList.remove('flex');
      idleState.classList.remove('hidden');
    });

    // Submit Application Form with FormData & Quota Validation
    const applyForm = document.getElementById('apply-staff-form');
    applyForm.onsubmit = async (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById('submit-apply-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      const fd = new FormData();
      fd.append('leaveTypeID', document.getElementById('apply-leave-type').value);
      fd.append('requestDate', document.getElementById('apply-date').value);
      fd.append('startTime', document.getElementById('apply-start-time').value);
      fd.append('endTime', document.getElementById('apply-end-time').value);
      fd.append('reason', document.getElementById('apply-reason').value.trim());

      if (selectedFile) {
        fd.append('proofDocument', selectedFile);
      }

      try {
        const res = await fetch('./application_actions.php?action=submit_admin_leave', {
          method: 'POST',
          body: fd
        });
        const result = await res.json();
        if (result.status === 'success') {
          alert(result.message);
          applyForm.reset();
          removeFileBtn.click();
          await loadMyBalances();
          window.loadMyApplications();
        } else {
          alert(result.message || 'Submission failed');
        }
      } catch (err) {
        alert('Request error: ' + err.message);
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i><span>Submit Application</span>';
        if (window.lucide) lucide.createIcons();
      }
    };

    // Policy Form Submit
    const policyForm = document.getElementById('policy-form');
    policyForm.onsubmit = async (e) => {
      e.preventDefault();
      const payload = {
        leaveTypeID: document.getElementById('policy-id').value,
        leaveName: document.getElementById('policy-name').value.trim(),
        targetRole: document.getElementById('policy-role').value,
        daysPerYear: document.getElementById('policy-days').value
      };

      try {
        const res = await fetch('./application_actions.php?action=save_leave_type', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.status === 'success') {
          document.getElementById('policy-modal').classList.add('hidden');
          await loadLeaveTypes();
          await loadMyBalances();
          renderPolicies();
        } else {
          alert(result.message);
        }
      } catch (e) {
        alert(e.message);
      }
    };

    // Modal Controls
    const modal = document.getElementById('action-modal');
    const cancelBtn = document.getElementById('modal-cancel-btn');
    const confirmBtn = document.getElementById('modal-confirm-btn');

    const closeModal = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      pendingAction = null;
    };

    if (cancelBtn) cancelBtn.onclick = closeModal;

    if (confirmBtn) {
      confirmBtn.onclick = async () => {
        if (!pendingAction) return;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Processing...';

        const rejectReasonText = document.getElementById('reject-reason-input').value.trim();

        try {
          const res = await fetch('./application_actions.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              applicantType: pendingAction.applicantType,
              requestId: pendingAction.requestId,
              status: pendingAction.targetStatus,
              rejectionReason: rejectReasonText
            })
          });
          const result = await res.json();

          if (result.status === 'success') {
            closeModal();
            await loadApplications();
            await loadMyBalances();
          } else {
            alert(result.message || 'Operation failed');
          }
        } catch (err) {
          alert('Request failed: ' + err.message);
        } finally {
          confirmBtn.disabled = false;
        }
      };
    }
  }
})();