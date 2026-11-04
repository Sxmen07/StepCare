// English comments only
(function () {
  'use strict';

  let applicationsList = [];
  let leaveTypesList = [];
  let myBalances = [];
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
    // Mount modular sidebar-nurse
    fetch('/StepCare/components/sidebar-nurse.html')
      .then(res => res.text())
      .then(html => injectComponent('sidebar-container', html));

    // Mount modular topbar
    fetch('/StepCare/components/topbar.html')
      .then(res => res.text())
      .then(html => {
        injectComponent('topbar-container', html);
        const title = document.getElementById('topbar-page-title');
        if (title) title.textContent = 'Leave Applications';
      });

    resetDatePicker();
    loadLeaveTypes();
    loadApplications();
    loadMyBalances();
    initEventHandlers();
  });

  function resetDatePicker() {
    const dInput = document.getElementById('apply-date');
    if (dInput) {
      const today = new Date().toISOString().split('T')[0];
      dInput.min = today;
      dInput.value = today;
    }
  }

  // Switch Edge Tabs (Top Indicator Line perfectly aligned with Admin)
  window.switchTab = function (tab) {
    const btnReview = document.getElementById('tab-btn-review');
    const btnMyApp = document.getElementById('tab-btn-my-app');
    const paneReview = document.getElementById('pane-review');
    const paneMyApp = document.getElementById('pane-myapp');

    // Inactive tab class: Transparent top border, muted gray text
    const inactiveClass = "relative py-4 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border-t-2 border-transparent bg-transparent text-neutral-500 hover:bg-blue-50/60 hover:text-[#3C52C3]";
    // Active tab class: Distinct blue top border, solid white background, brand colored text
    const activeClass = "relative py-4 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border-t-2 border-[#3C52C3] bg-white text-[#3C52C3]";

    btnReview.className = inactiveClass;
    btnMyApp.className = inactiveClass;

    paneReview.classList.add('hidden');
    paneMyApp.classList.add('hidden');

    if (tab === 'review') {
      btnReview.className = activeClass;
      paneReview.classList.remove('hidden');
      loadApplications();
    } else if (tab === 'myapp') {
      btnMyApp.className = activeClass;
      paneMyApp.classList.remove('hidden');
      loadMyBalances();
    }
    if (window.lucide) lucide.createIcons();
  };

  // Helper: Return stylized category badge
  function getCategoryBadge(name) {
    const cat = (name || 'Annual Leave').toLowerCase();
    if (cat.includes('mc') || cat.includes('medical') || cat.includes('sick')) {
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

  // Load Leave Types from leave_types table
  async function loadLeaveTypes() {
    try {
      const res = await fetch('./leave-actions.php?action=fetch_leave_types');
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
      console.error('Error fetching leave types:', e);
    }
  }

  // Load Live Balances & Render Right Column Table (Strict 2 Columns)
  window.loadMyBalances = async function () {
    try {
      const res = await fetch('./leave-actions.php?action=fetch_my_balance');
      const result = await res.json();
      if (result.status === 'success') {
        myBalances = result.data || [];
        updateBalancePreview();
        renderBalanceTable();
      }
    } catch (e) {
      console.error('Error fetching balances:', e);
    }
  };

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

  // Render Right Column Table: Strictly 2 Columns without "All Personnel"
  function renderBalanceTable() {
    const container = document.getElementById('balance-table-container');
    if (!container) return;

    if (myBalances.length === 0) {
      container.innerHTML = `<div class="py-16 text-center text-xs text-neutral-400">No balance records available.</div>`;
      return;
    }

    container.innerHTML = myBalances.map(b => {
      return `
        <div class="app-row grid grid-cols-12 px-8 py-4 items-center border-b border-neutral-100">
          <!-- Column 1: Policy / Leave Name -->
          <div class="col-span-7 flex items-center gap-2 pl-2">
            <span class="text-xs font-bold text-neutral-900">${b.leaveName}</span>
            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-neutral-100 text-neutral-500">Standard</span>
          </div>

          <!-- Column 2: Annual Entitlement & Days Left -->
          <div class="col-span-5 text-right pr-2">
            <span class="text-xs font-mono font-bold text-neutral-800">${b.total} Days / Year</span>
            <span class="text-[11px] text-emerald-600 font-semibold block mt-0.5">(${b.remaining} days left)</span>
          </div>
        </div>
      `;
    }).join('');

    if (window.lucide) lucide.createIcons();
  }

  // Load Tab 1 Applications Table
  window.loadApplications = async function () {
    try {
      const filterStatus = document.getElementById('filter-status').value;
      const res = await fetch(`./leave-actions.php?action=fetch_my_leaves&status=${filterStatus}`);
      const result = await res.json();
      if (result.status === 'success') {
        applicationsList = result.data || [];
        renderTable();
      }
    } catch (err) {
      console.error('Fetch applications error:', err);
    }
  };

  function renderTable() {
    const container = document.getElementById('applications-list-container');
    const searchInput = document.getElementById('app-search-input');
    const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';

    const filtered = applicationsList.filter(item => {
      return item.requestDate.toLowerCase().includes(searchTerm) || 
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

      // Time Frame strictly formatted: No 00:00 - 00:00
      let timeHtml = '';
      if (item.timeDisplay === 'Full Day' || item.timeDisplay === '00:00 - 00:00' || item.isFullDay) {
        timeHtml = `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-[#3C52C3] border border-indigo-100"><i data-lucide="sun" class="w-3 h-3"></i>Full Day</span>`;
      } else {
        timeHtml = `<span class="text-xs font-mono font-medium text-neutral-600">${item.timeDisplay}</span>`;
      }

      const proofLink = item.proofDocument
        ? `<a href="${item.proofDocument}" target="_blank" onclick="event.stopPropagation();" title="View document: ${item.proofDocumentName}" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#3C52C3] hover:underline mt-0.5">
             <i data-lucide="paperclip" class="w-3 h-3"></i>
             <span class="truncate max-w-[130px]">${item.proofDocumentName || 'Proof Document'}</span>
           </a>`
        : '';

      let actionCell = '';
      if (item.status === 'pending') {
        actionCell = `
          <button type="button" 
                  onclick="window.cancelRequest(${item.requestId})" 
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-rose-50 text-neutral-600 hover:text-rose-600 text-xs font-semibold rounded-lg border border-neutral-200 hover:border-rose-200 shadow-2xs transition cursor-pointer">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            <span>Withdraw</span>
          </button>
        `;
      } else if (item.status === 'rejected') {
        const rReason = item.rejectionReason || 'No feedback provided';
        actionCell = `<span class="text-xs text-rose-600 font-medium truncate max-w-[200px]" title="${rReason}"><strong class="font-bold">Reason:</strong> ${rReason}</span>`;
      } else {
        actionCell = `
          <span class="text-xs font-medium text-neutral-500">
            Approved by ${item.reviewedBy || 'Admin'}
          </span>
        `;
      }

      return `
        <div class="app-row grid grid-cols-12 items-center px-8 py-3.5 border-b border-neutral-100">
          <div class="col-span-2 text-xs font-bold text-neutral-900 pl-2">
            ${item.requestDate}
          </div>

          <div class="col-span-2 flex items-center gap-1.5 flex-wrap">
            ${getCategoryBadge(item.leaveName)}
          </div>

          <div class="col-span-2">
            ${timeHtml}
          </div>

          <div class="col-span-2 text-xs text-neutral-600 pr-4">
            <div class="truncate" title="${item.reason}">${item.reason}</div>
            ${proofLink}
          </div>

          <div class="col-span-1 text-center">
            ${statusBadge}
          </div>

          <div class="col-span-3 flex items-center justify-center">
            ${actionCell}
          </div>
        </div>
      `;
    }).join('');

    if (window.lucide) lucide.createIcons();
  }

  // Withdraw pending application
  window.cancelRequest = async function(id) {
    if (!confirm('Are you sure you want to withdraw this application?')) return;
    try {
      const res = await fetch('./leave-actions.php?action=cancel_leave', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ requestId: id })
      });
      const result = await res.json();
      if (result.status === 'success') {
        await loadApplications();
        await loadMyBalances();
      } else {
        alert(result.message || 'Failed to withdraw');
      }
    } catch (e) {
      alert(e.message);
    }
  };

  // Events initialization
  function initEventHandlers() {
    const searchInput = document.getElementById('app-search-input');
    if (searchInput) searchInput.addEventListener('input', renderTable);

    const filterStatus = document.getElementById('filter-status');
    if (filterStatus) filterStatus.addEventListener('change', loadApplications);

    const leaveTypeSelect = document.getElementById('apply-leave-type');
    if (leaveTypeSelect) leaveTypeSelect.addEventListener('change', updateBalancePreview);

    // Full Day Checkbox toggle
    const fullDayCheckbox = document.getElementById('full-day-checkbox');
    const hoursWrapper = document.getElementById('hours-wrapper');
    if (fullDayCheckbox && hoursWrapper) {
      fullDayCheckbox.addEventListener('change', (e) => {
        hoursWrapper.classList.toggle('hidden', e.target.checked);
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

    // Form submission
    const applyForm = document.getElementById('apply-staff-form');
    applyForm.onsubmit = async (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById('submit-apply-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      const isFull = fullDayCheckbox ? fullDayCheckbox.checked : true;

      const fd = new FormData();
      fd.append('leaveTypeID', document.getElementById('apply-leave-type').value);
      fd.append('requestDate', document.getElementById('apply-date').value);
      fd.append('isFullDay', isFull ? 'true' : 'false');
      fd.append('startTime', document.getElementById('apply-start-time').value);
      fd.append('endTime', document.getElementById('apply-end-time').value);
      fd.append('reason', document.getElementById('apply-reason').value.trim());

      if (selectedFile) {
        fd.append('proofDocument', selectedFile);
      }

      try {
        const res = await fetch('./leave-actions.php?action=submit_leave', {
          method: 'POST',
          body: fd
        });
        const result = await res.json();
        if (result.status === 'success') {
          alert(result.message);
          applyForm.reset();
          if (fullDayCheckbox) {
            fullDayCheckbox.checked = true;
            hoursWrapper.classList.add('hidden');
          }
          removeFileBtn.click();
          resetDatePicker();
          await loadMyBalances();
          // Switch to review tab to show latest entry
          window.switchTab('review');
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
  }
})();