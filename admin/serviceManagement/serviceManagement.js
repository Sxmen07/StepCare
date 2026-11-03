// StepCare - Service Management Controller Logic

let servicesData = [];
let currentCategory = 'all';
let searchQuery = '';
let viewMode = 'card';

document.addEventListener('DOMContentLoaded', () => {
  loadComponents();
  fetchServices();
  setupSearchAndFilters();
  setupViewModeToggle();
  setupModalHandlers();
  setupLivePreviewListeners();
});

// 1. Load Components
function loadComponents() {
  fetch('../../../components/sidebar-admin.html')
    .then(res => res.ok ? res.text() : fetch('/StepCare/components/sidebar-admin.html').then(r => r.text()))
    .then(html => { const c = document.getElementById('sidebar-container'); if (c) c.innerHTML = html; })
    .catch(err => console.error("Sidebar error:", err));

  fetch('../../../components/topbar.html')
    .then(res => res.ok ? res.text() : fetch('/StepCare/components/topbar.html').then(r => r.text()))
    .then(html => {
      const c = document.getElementById('topbar-container');
      if (c) {
        c.innerHTML = html;
        setTimeout(() => {
          const t = document.getElementById('topbar-page-title');
          if (t) t.textContent = "Service Management";
        }, 50);
      }
    }).catch(err => console.error("Topbar error:", err));
}

// 2. Fetch Services
async function fetchServices() {
  try {
    const response = await fetch('serviceManagement.php?action=fetch_services');
    const result = await response.json();
    if (result.status === 'success') {
      servicesData = result.data;
      renderServices();
    }
  } catch (error) {
    console.error("Error fetching services:", error);
  }
}

// 3. Render Services Grid / List
function renderServices() {
  const grid = document.getElementById('services-grid');
  const emptyState = document.getElementById('empty-state');
  const countText = document.getElementById('service-count-text');

  if (!grid) return;
  grid.className = viewMode === 'list' ? "flex flex-col space-y-3" : "grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6";

  const filtered = servicesData.filter(service => {
    const matchesCategory = (currentCategory === 'all') || (service.category && service.category.toLowerCase() === currentCategory.toLowerCase());
    const q = searchQuery.toLowerCase();
    const matchesSearch = !q || service.serviceName.toLowerCase().includes(q) || (service.description && service.description.toLowerCase().includes(q)) || (service.category && service.category.toLowerCase().includes(q));
    return matchesCategory && matchesSearch;
  });

  countText.textContent = `Showing ${filtered.length} of ${servicesData.length} service${filtered.length === 1 ? '' : 's'}`;

  if (filtered.length === 0) {
    grid.innerHTML = '';
    emptyState.classList.remove('hidden');
    emptyState.classList.add('flex');
    return;
  }

  emptyState.classList.add('hidden');
  emptyState.classList.remove('flex');

  grid.innerHTML = filtered.map(service => {
    const imagePath = service.serviceImageURL ? service.serviceImageURL.replace('../..', '/StepCare') : '/StepCare/images/services/elderly.png';
    const durationText = (service.durationMin && service.durationMax) ? `Est. ${service.durationMin}–${service.durationMax} mins` : 'Flexible duration';
    const ratingVal = parseFloat(service.average_rating || 0).toFixed(2);
    const reviewCount = service.review_count || 0;
    const categoryName = (service.category || 'General').toUpperCase();

    const topFeatures = (service.features || []).slice(0, 2).map(f => `
      <li class="flex items-start gap-1.5 text-[11px] text-neutral-300">
        <span class="text-[#34D399] font-bold">✓</span>
        <span class="line-clamp-1">${f}</span>
      </li>
    `).join('');

    if (viewMode === 'list') {
      return `
        <div class="relative group bg-white rounded-2xl border border-neutral-200/80 p-4 shadow-sm hover:shadow-md transition flex items-center justify-between gap-4">
          <div class="flex items-center gap-4 min-w-0">
            <img src="${imagePath}" alt="${service.serviceName}" class="w-16 h-16 rounded-xl object-cover shrink-0" onerror="this.src='/StepCare/images/services/elderly.png'">
            <div class="min-w-0">
              <span class="text-[10px] font-bold text-[#3C52C3] uppercase tracking-wider">${categoryName}</span>
              <h4 class="text-sm font-bold text-neutral-900 truncate">${service.serviceName}</h4>
              <p class="text-xs text-neutral-500">RM ${parseFloat(service.price).toFixed(2)} /visit • ${durationText}</p>
            </div>
          </div>
          <div class="flex items-center gap-3 shrink-0">
            <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">Active</span>
            <button type="button" onclick="openEditModal(${service.serviceID})" class="p-2 text-neutral-400 hover:text-[#3C52C3] transition cursor-pointer" title="Edit Service"><i data-lucide="pencil" class="w-4 h-4"></i></button>
          </div>
        </div>
      `;
    }

    return `
      <div class="relative group rounded-3xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 flex flex-col justify-end h-[460px] bg-neutral-900 border border-neutral-200/50 select-none">
        <div class="absolute inset-0 z-0 overflow-hidden">
          <img src="${imagePath}" alt="${service.serviceName}" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500 ease-out" onerror="this.src='/StepCare/images/services/elderly.png'">
        </div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/65 to-black/20 z-10 pointer-events-none"></div>

        <div class="absolute top-3 inset-x-3 z-30 flex items-center justify-between">
          <div>
            ${service.isTopRecommendation == 1 ? `<span class="bg-amber-400 text-amber-950 font-black text-[9px] uppercase tracking-wider px-2.5 py-1 rounded-lg shadow-sm">★ Top Pick</span>` : ''}
          </div>
          <button type="button" onclick="openEditModal(${service.serviceID})" class="p-1.5 text-white/80 hover:text-white transition cursor-pointer focus:outline-none" title="Edit Service">
            <i data-lucide="pencil" class="w-4 h-4 drop-shadow-md"></i>
          </button>
        </div>

        <div class="relative z-20 p-5 flex flex-col justify-end space-y-2.5">
          <div><span class="text-[10px] font-black tracking-widest text-[#34D399] uppercase">${categoryName}</span></div>
          <h3 class="text-lg font-black text-white leading-snug tracking-tight line-clamp-2">${service.serviceName}</h3>
          <div class="flex items-center gap-2 text-[11px] text-neutral-300 font-medium">
            <div class="flex items-center gap-1 text-amber-400 font-bold"><span>★</span><span>${ratingVal}</span><span class="text-neutral-400 font-normal">(${reviewCount})</span></div>
            <span class="text-neutral-500">•</span>
            <span class="text-neutral-300">${durationText}</span>
          </div>
          ${topFeatures ? `<ul class="space-y-1 pt-1">${topFeatures}</ul>` : ''}
          <div class="w-full h-px bg-white/15 my-1"></div>
          <div class="flex items-end justify-between pt-0.5">
            <div>
              <p class="text-[9px] font-semibold text-neutral-400 uppercase tracking-wider">Service fee</p>
              <div class="text-xl font-black text-white tracking-tight leading-none mt-1">RM ${parseFloat(service.price).toFixed(2)} <span class="text-[11px] font-normal text-neutral-400">/visit</span></div>
            </div>
            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/20 backdrop-blur-md text-[10px] font-bold text-emerald-400 border border-emerald-500/30">Active</span>
          </div>
        </div>
      </div>
    `;
  }).join('');

  if (window.lucide) lucide.createIcons();
}

// 4. Modal, Tabs & Live Preview Handlers
function setupModalHandlers() {
  const modal = document.getElementById('service-modal');
  const openAddBtn = document.getElementById('btn-open-add-modal');
  const closeBtn = document.getElementById('close-service-modal');
  const cancelBtn = document.getElementById('cancel-service-modal');
  const form = document.getElementById('service-form');

  if (openAddBtn) {
    openAddBtn.addEventListener('click', () => {
      form.reset();
      document.getElementById('form-service-id').value = '';
      document.getElementById('modal-title').textContent = 'Add New Service';
      document.getElementById('features-container').innerHTML = '';
      document.getElementById('timeslots-container').innerHTML = '';
      const defaultImg = '/StepCare/images/services/elderly.png';
      document.getElementById('preview-img').src = defaultImg;
      document.getElementById('preview-inner-img').src = defaultImg;
      
      addFeatureInput('Blood pressure, ECG spot-check & glucose');
      addFeatureInput('Prescription pillbox reconciliation');
      addSlotInput('8:00 AM');
      addSlotInput('10:00 AM');
      switchModalTab('outer');
      switchPreviewMode('outer');
      updateLivePreview();
      modal.classList.remove('hidden');
    });
  }

  const closeModal = () => modal.classList.add('hidden');
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

  // Google Style Image Drop Zone Preview
  const imageInput = document.getElementById('form-service-image');
  if (imageInput) {
    imageInput.addEventListener('change', function(e) {
      if (e.target.files && e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = function(evt) {
          document.getElementById('preview-img').src = evt.target.result;
          document.getElementById('preview-inner-img').src = evt.target.result;
        };
        reader.readAsDataURL(e.target.files[0]);
      }
    });
  }

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      formData.append('action', 'save_service');

      const features = Array.from(document.querySelectorAll('.feature-input')).map(i => i.value).filter(Boolean);
      formData.append('features', JSON.stringify(features));

      const timeSlots = Array.from(document.querySelectorAll('.slot-input')).map(i => i.value).filter(Boolean);
      formData.append('timeSlots', JSON.stringify(timeSlots));

      try {
        const res = await fetch('serviceManagement.php', { method: 'POST', body: formData });
        const resJson = await res.json();
        if (resJson.status === 'success') {
          closeModal();
          fetchServices();
        } else {
          alert('Error: ' + resJson.message);
        }
      } catch (err) {
        console.error('Save error:', err);
      }
    });
  }
}

function setupLivePreviewListeners() {
  const nameInput = document.getElementById('form-service-name');
  const catSelect = document.getElementById('form-category');
  const priceInput = document.getElementById('form-price');
  const descInput = document.getElementById('form-description');
  const minDur = document.getElementById('form-duration-min');
  const maxDur = document.getElementById('form-duration-max');

  [nameInput, catSelect, priceInput, descInput, minDur, maxDur].forEach(el => {
    if (el) el.addEventListener('input', updateLivePreview);
  });
}

function updateLivePreview() {
  const name = document.getElementById('form-service-name').value.trim() || 'Elderly Mobility and Vitals Assessment';
  const cat = document.getElementById('form-category').value.toUpperCase();
  const price = parseFloat(document.getElementById('form-price').value || 85.00).toFixed(2);
  const desc = document.getElementById('form-description').value.trim() || 'Comprehensive routine wellness triage for aging loved ones. Focus on fall prevention, mobility exercises, medication reconciliation, and heart monitoring.';
  const min = document.getElementById('form-duration-min').value || 45;
  const max = document.getElementById('form-duration-max').value || 60;

  document.getElementById('preview-title').textContent = name;
  document.getElementById('preview-category').textContent = cat;
  document.getElementById('preview-price').textContent = price;
  document.getElementById('preview-desc').textContent = desc;
  document.getElementById('preview-duration').textContent = `Est. ${min}–${max} mins`;

  document.getElementById('preview-inner-title').textContent = name;
  document.getElementById('preview-inner-category').textContent = cat;
  document.getElementById('preview-inner-price').textContent = price;

  const features = Array.from(document.querySelectorAll('.feature-input')).map(i => i.value).filter(Boolean);
  const previewOuterFeaturesUl = document.getElementById('preview-outer-features');
  if (features.length > 0) {
    previewOuterFeaturesUl.innerHTML = features.slice(0, 2).map(f => `
      <li class="flex items-start gap-1.5 text-[9px] text-neutral-300"><span class="text-[#34D399] font-bold">✓</span><span class="line-clamp-1">${f}</span></li>
    `).join('');
  } else {
    previewOuterFeaturesUl.innerHTML = `<li class="text-[9px] text-neutral-400 italic">No features added</li>`;
  }

  const previewFeaturesUl = document.getElementById('preview-features');
  if (features.length > 0) {
    previewFeaturesUl.innerHTML = features.map(f => `
      <li class="flex items-start gap-1 text-[9px] text-neutral-600"><span class="text-emerald-500 font-bold">✓</span><span>${f}</span></li>
    `).join('');
  } else {
    previewFeaturesUl.innerHTML = `<li class="text-[9px] text-neutral-400 italic">No features added</li>`;
  }

  const slots = Array.from(document.querySelectorAll('.slot-input')).map(i => i.value).filter(Boolean);
  const previewSlotsDiv = document.getElementById('preview-timeslots');
  if (slots.length > 0) {
    previewSlotsDiv.innerHTML = slots.map((s, idx) => `
      <span class="px-2 py-1 ${idx === 0 ? 'bg-indigo-50 text-[#3C52C3] border border-indigo-200 font-bold text-[8px]' : 'bg-neutral-50 text-neutral-600 border border-neutral-200 text-[8px]'} rounded-lg text-center">${s}</span>
    `).join('');
  } else {
    previewSlotsDiv.innerHTML = `<span class="text-[8px] text-neutral-400 italic col-span-2">No slots added</span>`;
  }
}

// Sliding Pill Animation for Preview Mode Selector
function updatePreviewPillHighlight(activeBtn) {
  const wrapper = document.getElementById('preview-pills-wrapper');
  const highlight = document.getElementById('preview-pill-highlight');
  if (!wrapper || !highlight || !activeBtn) return;

  const wrapperRect = wrapper.getBoundingClientRect();
  const btnRect = activeBtn.getBoundingClientRect();

  highlight.style.width = `${btnRect.width}px`;
  highlight.style.height = `${btnRect.height}px`;
  highlight.style.left = `${btnRect.left - wrapperRect.left}px`;
  highlight.style.top = `${btnRect.top - wrapperRect.top}px`;
}

function switchPreviewMode(mode) {
  const outerPane = document.getElementById('preview-outer-pane');
  const innerPane = document.getElementById('preview-inner-pane');
  const viewOverview = document.getElementById('preview-view-overview');
  const viewBooking = document.getElementById('preview-view-booking');
  const btnOuter = document.getElementById('preview-mode-outer');
  const btnInner1 = document.getElementById('preview-mode-inner-1');
  const btnInner2 = document.getElementById('preview-mode-inner-2');

  const previewButtons = [btnOuter, btnInner1, btnInner2];
  previewButtons.forEach(b => {
    b.className = "preview-mode-btn relative z-10 px-3 py-1 text-[11px] font-semibold text-neutral-500 hover:text-neutral-900 transition-colors cursor-pointer";
  });

  // Animate card switch with smooth scale & opacity
  const panes = document.querySelectorAll('.preview-card-pane');
  panes.forEach(p => {
    p.classList.add('opacity-0', 'scale-95');
  });

  setTimeout(() => {
    if (mode === 'outer') {
      btnOuter.className = "preview-mode-btn relative z-10 px-3 py-1 text-[11px] font-bold text-white transition-colors cursor-pointer";
      outerPane.classList.remove('hidden');
      innerPane.classList.add('hidden');
      updatePreviewPillHighlight(btnOuter);
      switchModalTab('outer');
    } else if (mode === 'inner_overview') {
      btnInner1.className = "preview-mode-btn relative z-10 px-3 py-1 text-[11px] font-bold text-white transition-colors cursor-pointer";
      outerPane.classList.add('hidden');
      innerPane.classList.remove('hidden');
      viewOverview.classList.remove('hidden');
      viewBooking.classList.add('hidden');
      updatePreviewPillHighlight(btnInner1);
      switchModalTab('inner');
    } else if (mode === 'inner_booking') {
      btnInner2.className = "preview-mode-btn relative z-10 px-3 py-1 text-[11px] font-bold text-white transition-colors cursor-pointer";
      outerPane.classList.add('hidden');
      innerPane.classList.remove('hidden');
      viewOverview.classList.add('hidden');
      viewBooking.classList.remove('hidden');
      updatePreviewPillHighlight(btnInner2);
      switchModalTab('inner');
    }

    setTimeout(() => {
      const activePane = mode === 'outer' ? outerPane : innerPane;
      activePane.classList.remove('opacity-0', 'scale-95');
      activePane.classList.add('opacity-100', 'scale-100');
    }, 50);
  }, 150);
}

function switchModalTab(tab) {
  const tabOuter = document.getElementById('modal-tab-outer');
  const tabInner = document.getElementById('modal-tab-inner');
  const btnOuter = document.getElementById('tab-btn-outer');
  const btnInner = document.getElementById('tab-btn-inner');

  if (tab === 'outer') {
    tabOuter.classList.remove('hidden');
    tabInner.classList.add('hidden');
    btnOuter.className = "px-4 py-2 text-xs font-bold text-[#3C52C3] border-b-2 border-[#3C52C3] transition cursor-pointer";
    btnInner.className = "px-4 py-2 text-xs font-semibold text-neutral-500 hover:text-neutral-800 transition cursor-pointer";
  } else {
    tabOuter.classList.add('hidden');
    tabInner.classList.remove('hidden');
    btnInner.className = "px-4 py-2 text-xs font-bold text-[#3C52C3] border-b-2 border-[#3C52C3] transition cursor-pointer";
    btnOuter.className = "px-4 py-2 text-xs font-semibold text-neutral-500 hover:text-neutral-800 transition cursor-pointer";
    
    // Auto-switch preview to inner: overview when user clicks Inner Page Details form tab
    if (!document.getElementById('preview-inner-pane').classList.contains('hidden') === false) {
      switchPreviewMode('inner_overview');
    }
  }
}

function addFeatureInput(val = '') {
  const container = document.getElementById('features-container');
  const div = document.createElement('div');
  div.className = "flex items-center gap-2";
  div.innerHTML = `
    <input type="text" value="${val}" placeholder="Feature description" class="feature-input flex-1 px-3.5 py-2 bg-neutral-50 border border-neutral-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#3C52C3]/20" oninput="updateLivePreview()">
    <button type="button" onclick="this.parentElement.remove(); updateLivePreview();" class="p-2 text-red-500 hover:bg-red-50 rounded-xl transition cursor-pointer"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
  `;
  container.appendChild(div);
  if (window.lucide) lucide.createIcons();
  updateLivePreview();
}

function addSlotInput(val = '') {
  const container = document.getElementById('timeslots-container');
  const div = document.createElement('div');
  div.className = "flex items-center gap-2";
  div.innerHTML = `
    <input type="text" value="${val}" placeholder="e.g. 8:00 AM" class="slot-input flex-1 px-3.5 py-2 bg-neutral-50 border border-neutral-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#3C52C3]/20" oninput="updateLivePreview()">
    <button type="button" onclick="this.parentElement.remove(); updateLivePreview();" class="p-2 text-red-500 hover:bg-red-50 rounded-xl transition cursor-pointer"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
  `;
  container.appendChild(div);
  if (window.lucide) lucide.createIcons();
  updateLivePreview();
}

function openEditModal(serviceID) {
  const service = servicesData.find(s => s.serviceID == serviceID);
  if (!service) return;

  document.getElementById('form-service-id').value = service.serviceID;
  document.getElementById('form-service-name').value = service.serviceName;
  document.getElementById('form-category').value = service.category;
  document.getElementById('form-price').value = service.price;
  document.getElementById('form-duration-min').value = service.durationMin || 45;
  document.getElementById('form-duration-max').value = service.durationMax || 60;
  document.getElementById('form-existing-image').value = service.serviceImageURL || '';
  document.getElementById('form-description').value = service.description || '';

  const imagePath = service.serviceImageURL ? service.serviceImageURL.replace('../..', '/StepCare') : '/StepCare/images/services/elderly.png';
  document.getElementById('preview-img').src = imagePath;
  document.getElementById('preview-inner-img').src = imagePath;

  const featContainer = document.getElementById('features-container');
  featContainer.innerHTML = '';
  if (service.features && service.features.length > 0) {
    service.features.forEach(f => addFeatureInput(f));
  } else {
    addFeatureInput();
  }

  const slotContainer = document.getElementById('timeslots-container');
  slotContainer.innerHTML = '';
  if (service.timeSlots && service.timeSlots.length > 0) {
    service.timeSlots.forEach(s => addSlotInput(s));
  } else {
    addSlotInput();
  }

  document.getElementById('modal-title').textContent = 'Edit Service: ' + service.serviceName;
  switchModalTab('outer');
  switchPreviewMode('outer');
  updateLivePreview();
  document.getElementById('service-modal').classList.remove('hidden');
}

// 5. View Mode & Search Filters
function setupViewModeToggle() {
  const viewHighlight = document.getElementById('view-pill-highlight');
  const viewPills = document.querySelectorAll('.view-pill');
  const servicesWrapper = document.getElementById('services-wrapper');

  if (!viewHighlight || viewPills.length === 0) return;

  function updateViewHighlight(activeBtn) {
    const parentContainer = activeBtn.parentElement;
    const containerRect = parentContainer.getBoundingClientRect();
    const btnRect = activeBtn.getBoundingClientRect();
    viewHighlight.style.width = `${btnRect.width}px`;
    viewHighlight.style.height = `${btnRect.height}px`;
    viewHighlight.style.left = `${btnRect.left - containerRect.left + parentContainer.scrollLeft}px`;
    viewHighlight.style.top = `${btnRect.top - containerRect.top}px`;
  }

  const initialActiveView = document.querySelector('.view-pill[data-view="card"]');
  setTimeout(() => updateViewHighlight(initialActiveView), 50);

  viewPills.forEach(pill => {
    pill.addEventListener('click', () => {
      const targetMode = pill.getAttribute('data-view');
      if (viewMode === targetMode) return;

      viewPills.forEach(p => {
        p.classList.remove('text-[#3C52C3]', 'font-bold');
        p.classList.add('text-neutral-500', 'font-semibold');
      });
      pill.classList.remove('text-neutral-500', 'font-semibold');
      pill.classList.add('text-[#3C52C3]', 'font-bold');

      updateViewHighlight(pill);

      if (servicesWrapper) {
        servicesWrapper.style.opacity = '0';
        servicesWrapper.style.transform = 'translateY(10px)';
        setTimeout(() => {
          viewMode = targetMode;
          renderServices();
          servicesWrapper.style.opacity = '1';
          servicesWrapper.style.transform = 'translateY(0)';
        }, 150);
      } else {
        viewMode = targetMode;
        renderServices();
      }
    });
  });

  window.addEventListener('resize', () => {
    const activeBtn = document.querySelector('.view-pill.font-bold');
    if (activeBtn) updateViewHighlight(activeBtn);
  });
}

function setupSearchAndFilters() {
  const pillsWrapper = document.getElementById('category-pills-wrapper');
  const highlight = document.getElementById('pill-highlight');
  const pills = document.querySelectorAll('.category-pill');
  const searchInput = document.getElementById('service-search-input');
  const clearSearchBtn = document.getElementById('clear-search-btn');

  function updateHighlight(activeButton) {
    if (!activeButton || !highlight || !pillsWrapper) return;
    const wrapperRect = pillsWrapper.getBoundingClientRect();
    const btnRect = activeButton.getBoundingClientRect();
    highlight.style.width = `${btnRect.width}px`;
    highlight.style.height = `${btnRect.height}px`;
    highlight.style.left = `${btnRect.left - wrapperRect.left + pillsWrapper.scrollLeft}px`;
    highlight.style.top = `${btnRect.top - wrapperRect.top}px`;
  }

  const initialActive = document.querySelector('.category-pill[data-category="all"]');
  setTimeout(() => updateHighlight(initialActive), 50);

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

  window.addEventListener('resize', () => {
    const activePill = document.querySelector('.category-pill.font-bold');
    updateHighlight(activePill);
  });

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchQuery = e.target.value.trim();
      if (clearSearchBtn) {
        clearSearchBtn.classList.toggle('hidden', searchQuery.length === 0);
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