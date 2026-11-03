// admin/services/serviceManagement.js
document.addEventListener("DOMContentLoaded", () => {
  if (window.lucide) lucide.createIcons();

  const services = window.servicesData || [];
  const servicesList = document.getElementById('servicesList');
  const searchInput = document.getElementById('searchInput');
  const categoryChips = document.querySelectorAll('.filter-chip');
  const resultCount = document.getElementById('resultCount');

  let activeCategory = 'all';
  let activeSearch = '';

  // ==========================================
  // 1. Render Service List
  // ==========================================
  function renderServices() {
    const filtered = services.filter(svc => {
      // Category match
      const matchesCategory = activeCategory === 'all' || (svc.category && svc.category.toLowerCase() === activeCategory);
      
      // Search match
      const searchStr = `${svc.serviceName} ${svc.category} ${svc.description}`.toLowerCase();
      const matchesSearch = searchStr.includes(activeSearch);

      return matchesCategory && matchesSearch;
    });

    resultCount.textContent = `Showing ${filtered.length} service${filtered.length === 1 ? '' : 's'}`;

    if (filtered.length === 0) {
      servicesList.innerHTML = `
        <div class="py-20 text-center flex flex-col items-center justify-center">
          <div class="w-20 h-20 rounded-full bg-neutral-50 border border-neutral-100 flex items-center justify-center mb-4 shadow-sm animate-float">
            <i data-lucide="package-search" class="w-10 h-10 text-slate-300"></i>
          </div>
          <h3 class="text-base font-bold text-slate-800">No services found</h3>
          <p class="text-sm text-slate-500 mt-1">Try adjusting your search or category filters.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    servicesList.innerHTML = filtered.map(svc => {
      const img = svc.serviceImageURL || '/StepCare/images/services/placeholder.jpg';
      const rating = Number(svc.average_rating).toFixed(2);
      const duration = svc.durationMin && svc.durationMax ? `${svc.durationMin}–${svc.durationMax} mins` : 'N/A';

      return `
        <div class="flex flex-col md:flex-row md:items-center gap-6 p-6 hover:bg-slate-50/80 transition-colors group">
          
          <!-- Image -->
          <div class="w-full md:w-32 h-24 shrink-0 rounded-xl overflow-hidden bg-neutral-100 border border-neutral-200">
            <img src="${img}" alt="${svc.serviceName}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='/StepCare/images/services/placeholder.jpg'">
          </div>

          <!-- Main Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 mb-1.5">
              <span class="text-[10px] font-bold uppercase tracking-wider text-[#3C52C3] bg-blue-50 px-2.5 py-0.5 rounded-md border border-blue-100">
                ${svc.category || 'General'}
              </span>
              ${svc.isTopRecommendation == 1 ? `<span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-md border border-amber-200 flex items-center gap-1"><i data-lucide="star" class="w-3 h-3"></i> Top Pick</span>` : ''}
            </div>
            <h3 class="text-lg font-bold text-slate-900 truncate">${svc.serviceName}</h3>
            <p class="text-sm text-slate-500 mt-1 line-clamp-2 leading-relaxed">${svc.description}</p>
          </div>

          <!-- Stats (Rating, Duration) -->
          <div class="flex items-center gap-8 shrink-0 md:w-64">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Rating</p>
              <div class="flex items-center gap-1.5">
                <i data-lucide="star" class="w-4 h-4 text-amber-400 fill-amber-400"></i>
                <span class="text-sm font-bold text-slate-800">${rating}</span>
                <span class="text-xs text-slate-400">(${svc.review_count})</span>
              </div>
            </div>
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Duration</p>
              <p class="text-sm font-semibold text-slate-700">${duration}</p>
            </div>
          </div>

          <!-- Price & Actions -->
          <div class="flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-4 shrink-0 md:w-40">
            <p class="text-xl font-black text-[#3C52C3]">
              RM${Number(svc.price).toFixed(2)} <span class="text-xs font-semibold text-slate-400">/${svc.price_unit}</span>
            </p>
            
            <div class="flex items-center gap-2">
              <button type="button" class="w-9 h-9 rounded-lg border border-neutral-200 bg-white flex items-center justify-center text-slate-600 hover:text-[#3C52C3] hover:border-[#3C52C3] hover:bg-blue-50 transition" title="Edit Service">
                <i data-lucide="edit-2" class="w-4 h-4"></i>
              </button>
              <button type="button" class="w-9 h-9 rounded-lg border border-neutral-200 bg-white flex items-center justify-center text-slate-600 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition" title="Delete Service">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
              </button>
            </div>
          </div>

        </div>
      `;
    }).join('');
    
    if (window.lucide) lucide.createIcons();
  }

  // ==========================================
  // 2. Event Listeners
  // ==========================================
  
  // Search Input
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      activeSearch = e.target.value.toLowerCase().trim();
      renderServices();
    });
  }

  // Category Filtering
  categoryChips.forEach(chip => {
    chip.addEventListener('click', function() {
      // Update active state visually
      categoryChips.forEach(c => c.classList.remove('active'));
      this.classList.add('active');

      // Update state and re-render
      activeCategory = this.dataset.filter;
      renderServices();
    });
  });

  // Dummy Add Button Action
  const addBtn = document.getElementById('addServiceBtn');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      alert('Add Service Modal will open here.');
    });
  }

  // Initial Render
  renderServices();
});