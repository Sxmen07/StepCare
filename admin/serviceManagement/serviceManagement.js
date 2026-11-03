// StepCare - Service Management Controller Logic

let servicesData = [];
let currentCategory = 'all';
let searchQuery = '';

document.addEventListener('DOMContentLoaded', () => {
  loadComponents();
  fetchServices();
  setupSearchAndFilters();
});

// 1. Dynamically Load Shared UI Components with fallback paths
function loadComponents() {
  // Sidebar
  fetch('../../../components/sidebar-admin.html')
    .then(res => res.ok ? res.text() : fetch('/StepCare/components/sidebar-admin.html').then(r => r.text()))
    .then(html => {
      const container = document.getElementById('sidebar-container');
      if (container) container.innerHTML = html;
    })
    .catch(err => console.error("Sidebar load error:", err));

  // Topbar
  fetch('../../../components/topbar.html')
    .then(res => res.ok ? res.text() : fetch('/StepCare/components/topbar.html').then(r => r.text()))
    .then(html => {
      const container = document.getElementById('topbar-container');
      if (container) {
        container.innerHTML = html;
        setTimeout(() => {
          const pageTitle = document.getElementById('topbar-page-title');
          if (pageTitle) pageTitle.textContent = "Service Management";
        }, 50);
      }
    })
    .catch(err => console.error("Topbar load error:", err));
}

// 2. Fetch Service Records from Backend
async function fetchServices() {
  try {
    const response = await fetch('serviceManagement.php?action=fetch_services');
    const result = await response.json();

    if (result.status === 'success') {
      servicesData = result.data;
      renderServices();
    } else {
      console.error("Failed to load services:", result.message);
      document.getElementById('service-count-text').textContent = "Failed to load services.";
    }
  } catch (error) {
    console.error("Error connecting to services API:", error);
    document.getElementById('service-count-text').textContent = "Error loading services.";
  }
}

// 3. Render Service Cards Grid
function renderServices() {
  const grid = document.getElementById('services-grid');
  const emptyState = document.getElementById('empty-state');
  const countText = document.getElementById('service-count-text');

  if (!grid) return;

  // Filter Services by Search & Category
  const filtered = servicesData.filter(service => {
    const matchesCategory = (currentCategory === 'all') || 
      (service.category && service.category.toLowerCase() === currentCategory.toLowerCase());

    const q = searchQuery.toLowerCase();
    const matchesSearch = !q || 
      service.serviceName.toLowerCase().includes(q) || 
      (service.description && service.description.toLowerCase().includes(q)) ||
      (service.category && service.category.toLowerCase().includes(q));

    return matchesCategory && matchesSearch;
  });

  // Update Result Counter
  countText.textContent = `Showing ${filtered.length} of ${servicesData.length} service${servicesData.length === 1 ? '' : 's'}`;

  // Handle Empty Result View
  if (filtered.length === 0) {
    grid.innerHTML = '';
    emptyState.classList.remove('hidden');
    emptyState.classList.add('flex');
    return;
  }

  emptyState.classList.add('hidden');
  emptyState.classList.remove('flex');

  // Build Service Cards Grid HTML
  grid.innerHTML = filtered.map(service => {
    const durationText = (service.durationMin && service.durationMax) 
      ? `${service.durationMin}-${service.durationMax} min` 
      : 'Flexible duration';

    const imagePath = service.serviceImageURL 
      ? service.serviceImageURL.replace('../..', '/StepCare') 
      : '/StepCare/images/services/elderly.png';

    const featuresList = (service.features || []).map(f => `
      <li class="flex items-start gap-2 text-xs text-neutral-600">
        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
        <span class="line-clamp-1">${f}</span>
      </li>
    `).join('');

    const badgesList = (service.badges || []).map(b => `
      <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-[#3C52C3] border border-indigo-100">
        ${b}
      </span>
    `).join('');

    return `
      <div class="bg-white rounded-3xl border border-neutral-200/90 shadow-sm hover:shadow-md transition duration-200 flex flex-col overflow-hidden">
        
        <!-- Card Header / Image Container -->
        <div class="relative h-44 bg-neutral-100 overflow-hidden">
          <img src="${imagePath}" 
               alt="${service.serviceName}" 
               class="w-full h-full object-cover transition-transform duration-300 hover:scale-105"
               onerror="this.src='/StepCare/images/services/elderly.png'">
          
          ${service.isTopRecommendation == 1 ? `
            <div class="absolute top-3 left-3 bg-amber-400 text-amber-950 font-black text-[10px] uppercase tracking-wider px-2.5 py-1 rounded-lg shadow-sm flex items-center gap-1">
              <i data-lucide="sparkles" class="w-3 h-3"></i> Top Pick
            </div>
          ` : ''}

          <div class="absolute top-3 right-3 bg-black/60 backdrop-blur-md text-white font-bold text-[10px] px-2.5 py-1 rounded-lg">
            ${service.category || 'General'}
          </div>
        </div>

        <!-- Card Content Body -->
        <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
          <div>
            <div class="flex items-center justify-between text-xs text-neutral-500 mb-2">
              <div class="flex items-center gap-1 text-amber-500 font-bold">
                <i data-lucide="star" class="w-4 h-4 fill-amber-400"></i>
                <span>${parseFloat(service.average_rating || 0).toFixed(1)}</span>
                <span class="text-neutral-400 font-normal">(${service.review_count || 0})</span>
              </div>
              
              <div class="flex items-center gap-1 font-semibold text-neutral-500">
                <i data-lucide="clock" class="w-3.5 h-3.5 text-neutral-400"></i>
                <span>${durationText}</span>
              </div>
            </div>

            <h3 class="text-base font-bold text-neutral-900 leading-snug line-clamp-1">
              ${service.serviceName}
            </h3>

            <p class="text-xs text-neutral-500 mt-1.5 leading-relaxed line-clamp-2">
              ${service.description}
            </p>

            ${service.badges && service.badges.length > 0 ? `
              <div class="flex flex-wrap gap-1.5 mt-3">
                ${badgesList}
              </div>
            ` : ''}

            ${service.features && service.features.length > 0 ? `
              <ul class="mt-4 space-y-1.5 border-t border-neutral-100 pt-3">
                ${featuresList}
              </ul>
            ` : ''}
          </div>

          <!-- Card Footer Pricing -->
          <div class="pt-3 border-t border-neutral-100 flex items-center justify-between">
            <div>
              <span class="text-xs text-neutral-400 font-medium">From</span>
              <div class="text-lg font-black text-neutral-900 leading-none">
                RM ${parseFloat(service.price).toFixed(2)}
                <span class="text-xs font-normal text-neutral-400">/${service.price_unit}</span>
              </div>
            </div>

            <span class="text-xs font-bold text-[#3C52C3] flex items-center gap-1">
              Active
            </span>
          </div>

        </div>
      </div>
    `;
  }).join('');

  if (window.lucide) lucide.createIcons();
}

// 4. Sliding Pill & Search Event Controllers
function setupSearchAndFilters() {
  const pillsWrapper = document.getElementById('category-pills-wrapper');
  const highlight = document.getElementById('pill-highlight');
  const pills = document.querySelectorAll('.category-pill');
  const searchInput = document.getElementById('service-search-input');
  const clearSearchBtn = document.getElementById('clear-search-btn');

  // Calculates exact dimensions and moves sliding background pill
  function updateHighlight(activeButton) {
    if (!activeButton || !highlight || !pillsWrapper) return;
    
    const wrapperRect = pillsWrapper.getBoundingClientRect();
    const btnRect = activeButton.getBoundingClientRect();

    highlight.style.width = `${btnRect.width}px`;
    highlight.style.height = `${btnRect.height}px`;
    highlight.style.left = `${btnRect.left - wrapperRect.left + pillsWrapper.scrollLeft}px`;
    highlight.style.top = `${btnRect.top - wrapperRect.top}px`;
  }

  // Set default highlight state
  const initialActive = document.querySelector('.category-pill[data-category="all"]');
  setTimeout(() => updateHighlight(initialActive), 50);

  // Pill click handler
  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      pills.forEach(p => {
        p.classList.remove('text-[#3C52C3]', 'font-bold');
        p.classList.add('text-neutral-600', 'font-semibold');
      });

      pill.classList.remove('text-neutral-600', 'font-semibold');
      pill.classList.add('text-[#3C52C3]', 'font-bold');

      updateHighlight(pill);

      currentCategory = pill.getAttribute('data-category');
      renderServices();
    });
  });

  // Window resize highlight adjustment
  window.addEventListener('resize', () => {
    const activePill = document.querySelector('.category-pill.font-bold');
    updateHighlight(activePill);
  });

  // Search input listeners
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchQuery = e.target.value.trim();
      if (clearSearchBtn) {
        if (searchQuery.length > 0) {
          clearSearchBtn.classList.remove('hidden');
        } else {
          clearSearchBtn.classList.add('hidden');
        }
      }
      renderServices();
    });
  }

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      searchQuery = '';
      clearSearchBtn.classList.add('hidden');
      renderServices();
    });
  }
}