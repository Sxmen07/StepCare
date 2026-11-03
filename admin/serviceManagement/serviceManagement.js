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

// 3. Render Merged Service Cards Grid (Background Image + Features + Pricing Footer)
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

  // Build Merged Cards HTML Layout
  grid.innerHTML = filtered.map(service => {
    const imagePath = service.serviceImageURL 
      ? service.serviceImageURL.replace('../..', '/StepCare') 
      : '/StepCare/images/services/elderly.png';

    const durationText = (service.durationMin && service.durationMax) 
      ? `Est. ${service.durationMin}–${service.durationMax} mins` 
      : 'Flexible duration';

    const ratingVal = parseFloat(service.average_rating || 0).toFixed(2);
    const reviewCount = service.review_count || 0;
    const categoryName = (service.category || 'General').toUpperCase();

    // Render up to 2 key feature checkmarks (from user card layout) to keep card clean in admin grid
    const topFeatures = (service.features || []).slice(0, 2).map(f => `
      <li class="flex items-start gap-1.5 text-[11px] text-neutral-300">
        <span class="text-[#34D399] font-bold">✓</span>
        <span class="line-clamp-1">${f}</span>
      </li>
    `).join('');

    return `
      <div class="relative group rounded-3xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 flex flex-col justify-end h-[460px] bg-neutral-900 border border-neutral-200/50 select-none">
        
        <!-- Full Background Image with Zoom on Hover -->
        <div class="absolute inset-0 z-0 overflow-hidden">
          <img src="${imagePath}" 
               alt="${service.serviceName}" 
               class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500 ease-out"
               onerror="this.src='/StepCare/images/services/elderly.png'">
        </div>

        <!-- Dark Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/65 to-black/20 z-10 pointer-events-none"></div>

        <!-- Top Badges if Top Pick -->
        ${service.isTopRecommendation == 1 ? `
          <div class="absolute top-3 left-3 z-20 bg-amber-400 text-amber-950 font-black text-[9px] uppercase tracking-wider px-2.5 py-1 rounded-lg shadow-sm flex items-center gap-1">
            ★ Top Pick
          </div>
        ` : ''}

        <!-- Card Content Container -->
        <div class="relative z-20 p-5 flex flex-col justify-end space-y-2.5">
          
          <!-- Category Tag -->
          <div>
            <span class="text-[10px] font-black tracking-widest text-[#34D399] uppercase">
              ${categoryName}
            </span>
          </div>

          <!-- Service Title -->
          <h3 class="text-lg font-black text-white leading-snug tracking-tight line-clamp-2">
            ${service.serviceName}
          </h3>

          <!-- Ratings & Duration Row -->
          <div class="flex items-center gap-2 text-[11px] text-neutral-300 font-medium">
            <div class="flex items-center gap-1 text-amber-400 font-bold">
              <span>★</span>
              <span>${ratingVal}</span>
              <span class="text-neutral-400 font-normal">(${reviewCount})</span>
            </div>
            <span class="text-neutral-500">•</span>
            <span class="text-neutral-300">${durationText}</span>
          </div>

          <!-- Feature Bullets Preview (From User Card Layout) -->
          ${topFeatures ? `
            <ul class="space-y-1 pt-1">
              ${topFeatures}
            </ul>
          ` : ''}

          <!-- Thin Separator Line -->
          <div class="w-full h-px bg-white/15 my-1"></div>

          <!-- Price Footer Section -->
          <div class="flex items-end justify-between pt-0.5">
            <div>
              <p class="text-[9px] font-semibold text-neutral-400 uppercase tracking-wider">Service fee</p>
              <div class="text-xl font-black text-white tracking-tight leading-none mt-1">
                RM ${parseFloat(service.price).toFixed(2)} 
                <span class="text-[11px] font-normal text-neutral-400">/visit</span>
              </div>
            </div>

            <!-- Active Status Badge -->
            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/20 backdrop-blur-md text-[10px] font-bold text-emerald-400 border border-emerald-500/30">
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

  // Move sliding background pill
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