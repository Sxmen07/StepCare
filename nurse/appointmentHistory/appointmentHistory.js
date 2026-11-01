// nurse/appointmentHistory/appointmentHistory.js
document.addEventListener("DOMContentLoaded", () => {
  // 修复 Topbar 标题
  const topbarTitle = document.getElementById('topbar-page-title');
  if (topbarTitle) topbarTitle.textContent = 'Appointment History';

  if (window.lucide) lucide.createIcons();

  const historyData = window.historyData || [];
  const stats = window.statsData || { completed: 0, cancelled: 0, earnings: 0 };

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
  // 0. Render Summary Cards
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
  // 1. Render History List
  // ==========================================
  
  const getStatusMeta = (status) => {
    switch (status) {
      case 'completed': return { color: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Completed' };
      case 'cancelled': return { color: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Cancelled' };
      case 'declined':  return { color: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Declined' };
      default:          return { color: 'bg-slate-50 text-slate-700 border-slate-200', label: status };
    }
  };

  function renderHistory(filterText = '') {
    const term = filterText.toLowerCase().trim();
    
    const filtered = historyData.filter(appt => {
      const searchStr = `${appt.firstName} ${appt.lastName} ${appt.serviceName}`.toLowerCase();
      return searchStr.includes(term);
    });

    if (filtered.length === 0) {
      historyList.innerHTML = `
        <div class="py-20 text-center flex flex-col items-center justify-center">
          <div class="w-20 h-20 rounded-full bg-white border border-neutral-100 flex items-center justify-center mb-4 shadow-sm animate-float">
            <i data-lucide="archive" class="w-10 h-10 text-slate-300"></i>
          </div>
          <h3 class="text-base font-bold text-slate-800">No history found</h3>
          <p class="text-sm text-slate-500 mt-1">Try adjusting your search terms.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    historyList.innerHTML = filtered.map((appt, index) => {
      const meta = getStatusMeta(appt.appointmentStatus);
      
      return `
        <div class="history-item bg-white rounded-2xl p-5 flex flex-col md:flex-row md:items-center gap-5 border border-neutral-200/80 hover:shadow-md transition-all duration-300 group" style="animation-delay: ${index * 0.05}s">
          
          <!-- Date Badge -->
          <div class="w-16 h-16 rounded-xl bg-slate-50 border border-slate-100 flex flex-col items-center justify-center shrink-0">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${formatDate(appt.startDateTime).split(',')[0].split(' ')[0]}</span>
            <span class="text-xl font-black text-slate-800 leading-none mt-0.5">${new Date(appt.startDateTime.replace(' ', 'T')).getDate()}</span>
          </div>

          <!-- Details -->
          <div class="flex-1 min-w-0">
            <h4 class="text-base font-bold text-slate-900 truncate">${appt.serviceName}</h4>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-xs font-medium text-slate-500">
              <span class="flex items-center gap-1.5"><i data-lucide="user" class="w-3.5 h-3.5"></i> ${appt.firstName} ${appt.lastName}</span>
              <span class="flex items-center gap-1.5"><i data-lucide="clock" class="w-3.5 h-3.5"></i> ${formatTime(appt.startDateTime)}</span>
              <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> ${formatDate(appt.startDateTime)}</span>
            </div>
          </div>

          <!-- Status & Price -->
          <div class="flex items-center justify-between md:flex-col md:items-end gap-3 shrink-0 border-t md:border-t-0 md:border-l border-slate-100 pt-4 md:pt-0 md:pl-6 mt-2 md:mt-0">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider border ${meta.color}">
              ${meta.label}
            </span>
            <span class="text-sm font-black text-slate-800">
              RM ${parseFloat(appt.price).toFixed(2)}
            </span>
          </div>

        </div>
      `;
    }).join('');
    
    if (window.lucide) lucide.createIcons();
  }

  // Initial render
  renderHistory();

  // Search Listener
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      renderHistory(e.target.value);
    });
  }
});