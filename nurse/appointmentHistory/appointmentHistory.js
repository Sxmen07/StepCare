// nurse/appointmentHistory/appointmentHistory.js
document.addEventListener("DOMContentLoaded", () => {
  // Setup Topbar Title
  const topbarTitle = document.getElementById('topbar-page-title');
  if (topbarTitle) topbarTitle.textContent = 'Appointment History';

  if (window.lucide) lucide.createIcons();

  // Load Data safely
  let historyData = [];
  try {
    historyData = window.historyData || [];
    if (!Array.isArray(historyData)) historyData = [];
  } catch(e) { historyData = []; }

  let stats = { completed: 0, cancelled: 0, earnings: 0 };
  try {
    if (window.statsData) {
      stats.completed = Number(window.statsData.completed) || 0;
      stats.cancelled = Number(window.statsData.cancelled) || 0;
      stats.earnings = Number(window.statsData.earnings) || 0;
    }
  } catch(e) {}

  // DOM Elements
  const summaryContainer = document.getElementById('summary-cards-container');
  const historyList = document.getElementById('history-list');
  const searchInput = document.getElementById('searchInput');
  
  // Filter Elements
  const filterCategory = document.getElementById('filterCategory');
  const resetFiltersBtn = document.getElementById('resetFilters');

  // Airbnb Date Picker Elements
  const dateRangeBtn = document.getElementById('dateRangeBtn');
  const dateRangeMenu = document.getElementById('dateRangeMenu');
  const dateRangeText = document.getElementById('dateRangeText');
  const filterFromDate = document.getElementById('filterFromDate');
  const filterToDate = document.getElementById('filterToDate');
  const applyDatesBtn = document.getElementById('applyDatesBtn');
  const clearDatesBtn = document.getElementById('clearDatesBtn');

  // Format Helpers
  const formatDate = (dtStr) => {
    if (!dtStr) return '';
    try {
      const d = new Date(dtStr.replace(' ', 'T'));
      if (isNaN(d.getTime())) return '';
      return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch (e) { return ''; }
  };
  
  const formatTime = (dtStr) => {
    if (!dtStr) return '';
    try {
      const d = new Date(dtStr.replace(' ', 'T'));
      if (isNaN(d.getTime())) return '';
      return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    } catch (e) { return ''; }
  };

  // ==========================================
  // 1. Render Summary Cards (Updated Earnings)
  // ==========================================
  if (summaryContainer) {
    summaryContainer.innerHTML = `
      <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-500 mb-1">Total Completed</p>
          <h3 class="text-4xl font-black text-slate-900">${stats.completed}</h3>
        </div>
        <div class="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-500">
          <i data-lucide="check-circle" class="w-7 h-7"></i>
        </div>
      </div>

      <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-500 mb-1">Cancelled</p>
          <h3 class="text-4xl font-black text-slate-900">${stats.cancelled}</h3>
        </div>
        <div class="w-14 h-14 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500">
          <i data-lucide="x-circle" class="w-7 h-7"></i>
        </div>
      </div>

      <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-500 mb-1">This Month's Earnings</p>
          <h3 class="text-4xl font-black text-[#3C52C3]">RM ${Number(stats.earnings).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</h3>
          <p class="text-[10px] font-medium text-slate-400 mt-1">After 15% service fee</p>
        </div>
        <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
          <i data-lucide="wallet" class="w-7 h-7"></i>
        </div>
      </div>
    `;
    if (window.lucide) lucide.createIcons();
  }

  // ==========================================
  // 2. Helpers for Status
  // ==========================================
  const getStatusMeta = (status) => {
    const s = String(status || '').toLowerCase();
    switch (s) {
      case 'completed': return { color: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Completed' };
      case 'cancelled': return { color: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Cancelled' };
      case 'declined':  return { color: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Declined' };
      default:          return { color: 'bg-slate-50 text-slate-700 border-slate-200', label: status || 'Unknown' };
    }
  };

  // ==========================================
  // 3. Populate Category Filter
  // ==========================================
  if (filterCategory) {
    const categories = [...new Set(historyData.map(appt => appt.category).filter(Boolean))].sort();
    categories.forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat;
      opt.textContent = cat;
      filterCategory.appendChild(opt);
    });
  }

  // ==========================================
  // 4. Date Range Picker Logic (Airbnb Style)
  // ==========================================
  let activeFromDate = '';
  let activeToDate = '';

  // Toggle Dropdown
  if (dateRangeBtn && dateRangeMenu) {
    dateRangeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      dateRangeMenu.classList.toggle('hidden');
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!dateRangeMenu.contains(e.target) && !dateRangeBtn.contains(e.target)) {
        dateRangeMenu.classList.add('hidden');
      }
    });
  }

  function updateDateRangeText() {
    if (activeFromDate && activeToDate) {
      dateRangeText.textContent = `${formatDate(activeFromDate)} - ${formatDate(activeToDate)}`;
    } else if (activeFromDate) {
      dateRangeText.textContent = `From ${formatDate(activeFromDate)}`;
    } else if (activeToDate) {
      dateRangeText.textContent = `Until ${formatDate(activeToDate)}`;
    } else {
      dateRangeText.textContent = 'Select dates';
    }
  }

  if (applyDatesBtn) {
    applyDatesBtn.addEventListener('click', () => {
      activeFromDate = filterFromDate.value;
      activeToDate = filterToDate.value;
      
      updateDateRangeText();
      dateRangeMenu.classList.add('hidden');
      renderHistory();
    });
  }

  if (clearDatesBtn) {
    clearDatesBtn.addEventListener('click', () => {
      filterFromDate.value = '';
      filterToDate.value = '';
      activeFromDate = '';
      activeToDate = '';
      
      updateDateRangeText();
      dateRangeMenu.classList.add('hidden');
      renderHistory();
    });
  }

  // ==========================================
  // 5. Filter State & Render Logic
  // ==========================================
  let activeStatusFilter = 'all';
  let activeSearchTerm = '';
  let activeCategoryFilter = 'all';

  function renderHistory() {
    if (!historyList) return;

    const filtered = historyData.filter(appt => {
      if (!appt) return false;

      // 1. Search Text Match
      const fn = appt.firstName || '';
      const ln = appt.lastName || '';
      const sn = appt.serviceName || '';
      const searchStr = `${fn} ${ln} ${sn}`.toLowerCase();
      const matchesSearch = searchStr.includes(activeSearchTerm);

      // 2. Status Match
      let matchesStatus = false;
      const apptStatus = String(appt.appointmentStatus || '').toLowerCase();
      
      if (activeStatusFilter === 'all') {
        matchesStatus = true;
      } else if (activeStatusFilter === 'completed') {
        matchesStatus = (apptStatus === 'completed');
      } else if (activeStatusFilter === 'cancelled') {
        matchesStatus = (apptStatus === 'cancelled' || apptStatus === 'declined');
      }

      // 3. Category Match
      const matchesCategory = (activeCategoryFilter === 'all' || appt.category === activeCategoryFilter);

      // 4. Date Range Match
      let matchesDate = true;
      if (appt.startDateTime) {
        const apptDate = appt.startDateTime.split(' ')[0]; 
        if (activeFromDate && apptDate < activeFromDate) matchesDate = false;
        if (activeToDate && apptDate > activeToDate) matchesDate = false;
      }

      return matchesSearch && matchesStatus && matchesCategory && matchesDate;
    });

    if (filtered.length === 0) {
      historyList.innerHTML = `
        <div class="py-20 text-center flex flex-col items-center justify-center bg-white rounded-3xl border border-neutral-200/80 mt-6 shadow-sm">
          <div class="w-20 h-20 rounded-full bg-neutral-50 border border-neutral-100 flex items-center justify-center mb-4 shadow-sm animate-float">
            <i data-lucide="filter-x" class="w-10 h-10 text-slate-300"></i>
          </div>
          <h3 class="text-base font-bold text-slate-800">No history found</h3>
          <p class="text-sm text-slate-500 mt-1">Try adjusting your filters or search.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    historyList.innerHTML = filtered.map((appt, index) => {
      const meta = getStatusMeta(appt.appointmentStatus);
      
      let monthStr = '';
      let dayStr = '';
      try {
        const d = new Date(appt.startDateTime.replace(' ', 'T'));
        if (!isNaN(d.getTime())) {
          const formatted = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
          monthStr = formatted.split(',')[0].split(' ')[0];
          dayStr = d.getDate();
        }
      } catch(e) {}

      const fn = appt.firstName || '';
      const ln = appt.lastName || '';
      const sn = appt.serviceName || 'Service';
      const price = parseFloat(appt.price) || 0;
      const apptId = appt.appointmentID || 0;

      return `
        <a href="../appointment/appointmentDetails/appointmentDetails.php?id=${apptId}&from=history" 
           class="history-item w-full flex flex-col md:flex-row md:items-center gap-5 py-6 px-4 border-b border-neutral-200/70 hover:bg-slate-50/80 transition-colors duration-200 group cursor-pointer" 
           style="animation-delay: ${index * 0.05}s">
          
          <div class="w-16 h-16 rounded-xl bg-white border border-slate-200 flex flex-col items-center justify-center shrink-0 shadow-sm group-hover:border-[#3C52C3]/30 transition-colors">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${monthStr}</span>
            <span class="text-xl font-black text-slate-800 leading-none mt-0.5">${dayStr}</span>
          </div>

          <div class="flex-1 min-w-0">
            <h4 class="text-lg font-bold text-slate-900 truncate group-hover:text-[#3C52C3] transition-colors">${sn}</h4>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-2 text-sm font-medium text-slate-500">
              <span class="flex items-center gap-1.5"><i data-lucide="user" class="w-4 h-4"></i> ${fn} ${ln}</span>
              <span class="flex items-center gap-1.5"><i data-lucide="clock" class="w-4 h-4"></i> ${formatTime(appt.startDateTime)}</span>
              <span class="flex items-center gap-1.5"><i data-lucide="tag" class="w-4 h-4"></i> ${appt.category || 'General'}</span>
            </div>
          </div>

          <div class="flex items-center justify-between md:flex-col md:items-end gap-3 shrink-0 md:pl-6">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider border ${meta.color}">
              ${meta.label}
            </span>
            <span class="text-base font-black text-slate-800">
              RM ${price.toFixed(2)}
            </span>
          </div>

        </a>
      `;
    }).join('');
    
    if (window.lucide) lucide.createIcons();
  }

  // ==========================================
  // 6. Sliding Filter Logic & Events
  // ==========================================
  const filterChips = document.querySelectorAll('.filter-chip');
  const filterSlider = document.getElementById('filterSlider');

  function moveSlider(chip) {
    if (!filterSlider || !chip) return;
    
    filterChips.forEach(c => c.classList.remove('active'));
    chip.classList.add('active');

    const left = chip.offsetLeft;
    const width = chip.offsetWidth;

    filterSlider.style.left = `${left}px`;
    filterSlider.style.width = `${width}px`;
  }

  window.addEventListener('load', () => {
    const activeChip = document.querySelector('.filter-chip.active');
    if (activeChip && filterSlider) {
      filterSlider.style.transition = 'none';
      moveSlider(activeChip);
      setTimeout(() => {
        filterSlider.style.transition = 'left 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), width 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
      }, 50);
    }
  });

  filterChips.forEach(chip => {
    chip.addEventListener('click', function() {
      moveSlider(this);
      activeStatusFilter = this.dataset.filter || 'all';
      renderHistory();
    });
  });

  window.addEventListener('resize', () => {
    const activeChip = document.querySelector('.filter-chip.active');
    if (activeChip && filterSlider) {
      filterSlider.style.transition = 'none';
      moveSlider(activeChip);
      setTimeout(() => {
        filterSlider.style.transition = 'left 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), width 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
      }, 50);
    }
  });

  // ==========================================
  // 7. Input Events & Reset
  // ==========================================
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      activeSearchTerm = e.target.value.toLowerCase().trim();
      renderHistory();
    });
  }

  if (filterCategory) {
    filterCategory.addEventListener('change', (e) => {
      activeCategoryFilter = e.target.value;
      renderHistory();
    });
  }

  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener('click', () => {
      // Reset variables
      activeSearchTerm = '';
      activeCategoryFilter = 'all';
      activeFromDate = '';
      activeToDate = '';
      
      // Reset UI inputs
      if (searchInput) searchInput.value = '';
      if (filterCategory) filterCategory.value = 'all';
      if (filterFromDate) filterFromDate.value = '';
      if (filterToDate) filterToDate.value = '';
      updateDateRangeText();
      
      // Status pill reset
      activeStatusFilter = 'all';
      const allChip = document.querySelector('.filter-chip[data-filter="all"]');
      if (allChip) moveSlider(allChip);

      renderHistory();
    });
  }

  // Initial Render
  renderHistory();
});