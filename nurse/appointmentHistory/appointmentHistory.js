// nurse/appointmentHistory/appointmentHistory.js
document.addEventListener("DOMContentLoaded", () => {
  // Setup Topbar Title
  const topbarTitle = document.getElementById('topbar-page-title');
  if (topbarTitle) topbarTitle.textContent = 'Appointment History';

  if (window.lucide) lucide.createIcons();

  // Load Data
  const historyData = window.historyData || [];
  const stats = window.statsData || { completed: 0, cancelled: 0, earnings: 0 };

  // DOM Elements
  const summaryContainer = document.getElementById('summary-cards-container');
  const historyList = document.getElementById('history-list');
  const searchInput = document.getElementById('searchInput');

  // Format Helpers
  const formatDate = (dtStr) => {
    const d = new Date(dtStr.replace(' ', 'T'));
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };
  const formatTime = (dtStr) => {
    const d = new Date(dtStr.replace(' ', 'T'));
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  };

  // ==========================================
  // 1. Render Summary Cards
  // ==========================================
  summaryContainer.innerHTML = `
    <!-- Completed -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
      <div>
        <p class="text-xs font-bold text-slate-500 mb-1">Total Completed</p>
        <h3 class="text-4xl font-black text-slate-900">${stats.completed}</h3>
      </div>
      <div class="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-500">
        <i data-lucide="check-circle" class="w-7 h-7"></i>
      </div>
    </div>

    <!-- Cancelled -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
      <div>
        <p class="text-xs font-bold text-slate-500 mb-1">Cancelled</p>
        <h3 class="text-4xl font-black text-slate-900">${stats.cancelled}</h3>
      </div>
      <div class="w-14 h-14 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500">
        <i data-lucide="x-circle" class="w-7 h-7"></i>
      </div>
    </div>

    <!-- Earnings -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
      <div>
        <p class="text-xs font-bold text-slate-500 mb-1">Total Earnings</p>
        <h3 class="text-4xl font-black text-[#3C52C3]">RM ${Number(stats.earnings).toLocaleString('en-US', { minimumFractionDigits: 2 })}</h3>
      </div>
      <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
        <i data-lucide="wallet" class="w-7 h-7"></i>
      </div>
    </div>
  `;
  if (window.lucide) lucide.createIcons();

  // ==========================================
  // 2. Helpers for Status
  // ==========================================
  const getStatusMeta = (status) => {
    switch (status) {
      case 'completed': return { color: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Completed' };
      case 'cancelled': return { color: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Cancelled' };
      case 'declined':  return { color: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Declined' };
      default:          return { color: 'bg-slate-50 text-slate-700 border-slate-200', label: status };
    }
  };

  // ==========================================
  // 3. Filter State & Render Logic
  // ==========================================
  let activeStatusFilter = 'all';
  let activeSearchTerm = '';

  function renderHistory() {
    const filtered = historyData.filter(appt => {
      // 1. Search Text Match
      const searchStr = `${appt.firstName} ${appt.lastName} ${appt.serviceName}`.toLowerCase();
      const matchesSearch = searchStr.includes(activeSearchTerm);

      // 2. Status Match
      let matchesStatus = false;
      if (activeStatusFilter === 'all') {
        matchesStatus = true;
      } else if (activeStatusFilter === 'completed') {
        matchesStatus = (appt.appointmentStatus === 'completed');
      } else if (activeStatusFilter === 'cancelled') {
        // Group 'cancelled' and 'declined' together
        matchesStatus = (appt.appointmentStatus === 'cancelled' || appt.appointmentStatus === 'declined');
      }

      return matchesSearch && matchesStatus;
    });

    if (filtered.length === 0) {
      historyList.innerHTML = `
        <div class="py-20 text-center flex flex-col items-center justify-center bg-white rounded-3xl border border-neutral-200/80 mt-6 shadow-sm">
          <div class="w-20 h-20 rounded-full bg-neutral-50 border border-neutral-100 flex items-center justify-center mb-4 shadow-sm animate-float">
            <i data-lucide="archive" class="w-10 h-10 text-slate-300"></i>
          </div>
          <h3 class="text-base font-bold text-slate-800">No history found</h3>
          <p class="text-sm text-slate-500 mt-1">Try adjusting your search or filters.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    historyList.innerHTML = filtered.map((appt, index) => {
      const meta = getStatusMeta(appt.appointmentStatus);
      
      // CHANGED: Wrapped the row in an <a> tag and added cursor-pointer
      return `
        <a href="../appointment/appointmentDetails/appointmentDetails.php?id=${appt.appointmentID}" 
           class="history-item w-full flex flex-col md:flex-row md:items-center gap-5 py-6 px-4 border-b border-neutral-200/70 hover:bg-slate-50/80 transition-colors duration-200 group cursor-pointer" 
           style="animation-delay: ${index * 0.05}s">
          
          <!-- Date Badge -->
          <div class="w-16 h-16 rounded-xl bg-white border border-slate-200 flex flex-col items-center justify-center shrink-0 shadow-sm group-hover:border-[#3C52C3]/30 transition-colors">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${formatDate(appt.startDateTime).split(',')[0].split(' ')[0]}</span>
            <span class="text-xl font-black text-slate-800 leading-none mt-0.5">${new Date(appt.startDateTime.replace(' ', 'T')).getDate()}</span>
          </div>

          <!-- Details -->
          <div class="flex-1 min-w-0">
            <h4 class="text-lg font-bold text-slate-900 truncate group-hover:text-[#3C52C3] transition-colors">${appt.serviceName}</h4>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-2 text-sm font-medium text-slate-500">
              <span class="flex items-center gap-1.5"><i data-lucide="user" class="w-4 h-4"></i> ${appt.firstName} ${appt.lastName}</span>
              <span class="flex items-center gap-1.5"><i data-lucide="clock" class="w-4 h-4"></i> ${formatTime(appt.startDateTime)}</span>
              <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-4 h-4"></i> ${formatDate(appt.startDateTime)}</span>
            </div>
          </div>

          <!-- Status & Price -->
          <div class="flex items-center justify-between md:flex-col md:items-end gap-3 shrink-0 md:pl-6">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider border ${meta.color}">
              ${meta.label}
            </span>
            <span class="text-base font-black text-slate-800">
              RM ${parseFloat(appt.price).toFixed(2)}
            </span>
          </div>

        </a>
      `;
    }).join('');
    
    if (window.lucide) lucide.createIcons();
  }

  // ==========================================
  // 4. Sliding Filter Logic & Events
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
    if (activeChip) {
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
      activeStatusFilter = this.dataset.filter;
      renderHistory();
    });
  });

  window.addEventListener('resize', () => {
    const activeChip = document.querySelector('.filter-chip.active');
    if (activeChip) {
      filterSlider.style.transition = 'none';
      moveSlider(activeChip);
      setTimeout(() => {
        filterSlider.style.transition = 'left 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), width 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
      }, 50);
    }
  });

  // Search Input
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      activeSearchTerm = e.target.value.toLowerCase().trim();
      renderHistory();
    });
  }

  // Initial Render
  renderHistory();
});