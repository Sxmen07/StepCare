// user/services/services.js
(function () {
  'use strict';

  /* ============================================================
     FILTERS (WITH SMOOTH ANIMATION)
     ============================================================ */
  const searchInput = document.getElementById('searchInput');
  const chips       = document.querySelectorAll('.cat-chip');
  const cards       = document.querySelectorAll('.svc-card');
  const emptyState  = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');
  const servicesGrid = document.getElementById('servicesGrid'); // Added

  let activeCat  = 'all';
  let activeTerm = '';
  let filterTimeout = null; // Added to handle rapid clicking

  function applyFilter() {
    if (!servicesGrid) return;
    clearTimeout(filterTimeout);

    // 1. Fade out and slightly scale down the grid
    servicesGrid.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
    servicesGrid.style.opacity = '0';
    servicesGrid.style.transform = 'scale(0.98) translateY(10px)';
    servicesGrid.style.pointerEvents = 'none'; // Prevent clicking during animation

    // 2. Wait for fade-out, then update the DOM and fade back in
    filterTimeout = setTimeout(() => {
      let visible = 0;
      cards.forEach(card => {
        const cat  = card.dataset.cat;
        const text = card.dataset.text || '';
        const ok   = (activeCat === 'all' || cat === activeCat) &&
                     (activeTerm === '' || text.includes(activeTerm));
        if (ok) { card.classList.remove('hidden'); visible++; }
        else    { card.classList.add('hidden'); }
      });
      
      emptyState.classList.toggle('hidden', visible > 0);
      resultCount.textContent = visible === 0 ? '' :
        `Showing ${visible} service${visible === 1 ? '' : 's'}`;

      // 3. Fade the grid back in
      servicesGrid.style.opacity = '1';
      servicesGrid.style.transform = 'scale(1) translateY(0)';
      servicesGrid.style.pointerEvents = 'auto';
    }, 200); // Matches the CSS transition duration above
  }

  let searchTimer = null;
  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      activeTerm = searchInput.value.trim().toLowerCase();
      applyFilter();
    }, 300); // Increased slightly to let typing finish before animating
  });

  chips.forEach(chip => {
    chip.addEventListener('click', function () {
      chips.forEach(c => c.classList.remove('is-active'));
      chip.classList.add('is-active');
      activeCat = chip.dataset.cat;
      applyFilter();
    });
  });

  applyFilter();

  /* ============================================================
     SCROLL REVEAL ANIMATION
     ============================================================ */
  const revealElements = document.querySelectorAll('.reveal-on-scroll');
  const revealOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const revealObserver = new IntersectionObserver(function(entries, observer) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target); 
      }
    });
  }, revealOptions);

  revealElements.forEach(el => {
    revealObserver.observe(el);
  });

  /* ============================================================
     HELPERS
     ============================================================ */
  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function getService(id) {
    return (window.servicesData || []).find(s => String(s.serviceID) === String(id));
  }
  
  function getRandomReviews(serviceID) {
    const reviews = window.reviewsData[serviceID] || [];
    
    // Fallback dummy reviews with dates if DB is empty
    if (reviews.length === 0) {
        return [
            { rating: 5, comment: "Excellent service! The nurse was very professional and caring.", firstName: "Sarah", lastName: "M.", createdAt: "2026-10-15 10:00:00" },
            { rating: 4, comment: "Arrived on time and very helpful. Highly recommended.", firstName: "David", lastName: "T.", createdAt: "2026-10-12 14:30:00" },
            { rating: 5, comment: "I felt completely at ease with the care provided.", firstName: "Mei", lastName: "L.", createdAt: "2026-10-10 09:15:00" }
        ];
    }
    
    const shuffled = [...reviews].sort(() => 0.5 - Math.random());
    const count = Math.min(Math.floor(Math.random() * 6) + 5, shuffled.length);
    return shuffled.slice(0, count);
  }

  /* ============================================================
     MODAL
     ============================================================ */
  const modalWrapper  = document.getElementById('modalWrapper');
  const modalSlider   = document.getElementById('modalSlider');

  const closeModalBtn = document.getElementById('closeModal');
  const detailBookNow = document.getElementById('detailBookNow');
  const backToDetail  = document.getElementById('backToDetail');

  const sharedImg      = document.getElementById('sharedImg');
  const sharedTitle    = document.getElementById('sharedTitle');
  const sharedCategory = document.getElementById('sharedCategory');
  const sharedRating   = document.getElementById('sharedRating');
  const sharedReviews  = document.getElementById('sharedReviews');
  const sharedDuration = document.getElementById('sharedDuration');
  const sharedPrice    = document.getElementById('sharedPrice');
  const sharedUnit     = document.getElementById('sharedUnit');

  const detailDesc = document.getElementById('detailDescription');
  const detailFeat = document.getElementById('detailFeatures');
  const detailReviewsList = document.getElementById('detailReviewsList');

  const bookingContainer = document.getElementById('bookingFormContainer');

  let activeServiceID = null;
  let isOpen = false;

  function slideToDetail() {
    modalSlider.style.transform = 'translateX(0)';
  }
  function slideToBooking() {
    modalSlider.style.transform = 'translateX(-50%)';
  }

  function populateShared(svc) {
    sharedImg.src = svc.serviceImageURL || '../../images/services/placeholder.jpg';
    sharedTitle.textContent    = svc.serviceName;
    sharedCategory.textContent = svc.category || 'General';
    sharedRating.textContent   = Number(svc.average_rating).toFixed(2);
    sharedReviews.textContent  = `(${svc.review_count} reviews)`;
    sharedDuration.textContent = (svc.durationMin && svc.durationMax)
      ? `Est. ${svc.durationMin}–${svc.durationMax} mins` : '';
    sharedPrice.textContent    = Number(svc.price).toFixed(2);
    sharedUnit.textContent     = svc.price_unit || 'visit';
  }

  function populateDetail(svc) {
    detailDesc.textContent = svc.description || '';

    // Features
    detailFeat.innerHTML = '';
    const features = svc.features ? svc.features.split('|||') : [];
    if (features.length === 0) {
      detailFeat.innerHTML = '<li class="text-slate-400 text-sm">No details listed.</li>';
    } else {
      features.forEach(f => {
        const li = document.createElement('li');
        li.className = 'flex items-start gap-3';
        li.innerHTML = `
          <span class="mt-0.5 w-5 h-5 rounded-full bg-[#10B981] text-white
                       flex items-center justify-center text-xs font-bold flex-shrink-0">✓</span>
          <span>${esc(f)}</span>`;
        detailFeat.appendChild(li);
      });
    }

    // Reviews
    detailReviewsList.innerHTML = '';
    const reviews = getRandomReviews(svc.serviceID);
    
    reviews.forEach(r => {
        const stars = '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating);
        const initial = r.firstName ? r.firstName.charAt(0) : 'U';
        
        // Format Date
        let formattedDate = '';
        if (r.createdAt) {
            const dateObj = new Date(r.createdAt.replace(' ', 'T'));
            formattedDate = dateObj.toLocaleDateString('en-GB', {
                day: 'numeric', month: 'short', year: 'numeric'
            });
        }
        
        const div = document.createElement('div');
        // Added flex and flex-col to make the card a flex container
        div.className = 'rounded-xl bg-slate-50 p-4 border border-slate-100 flex flex-col';
        div.innerHTML = `
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-[#3C52C3]/10 text-[#3C52C3] flex items-center justify-center font-bold text-xs">
                        ${initial}
                    </div>
                    <p class="text-xs font-bold text-slate-800">${esc(r.firstName)} ${esc(r.lastName || '')}</p>
                </div>
                <!-- Made stars bigger -->
                <div class="text-amber-500 text-sm tracking-widest">
                    ${stars}
                </div>
            </div>
            
            <p class="text-sm text-slate-600 leading-relaxed">"${esc(r.comment)}"</p>
            
            <!-- Date added to the bottom right -->
            <div class="mt-auto pt-4 flex justify-end">
                <div class="text-[11px] font-medium text-slate-400">${formattedDate}</div>
            </div>
        `;
        detailReviewsList.appendChild(div);
    });
  }

  function buildBookingForm(svc) {
    if (!window.isLoggedIn) {
      return `
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-5 text-amber-800">
          <p class="font-semibold mb-1">Please log in to book</p>
          <p class="text-sm mb-4">You need a StepCare account to make an appointment.</p>
          <a href="../../loginNsignup/login/login.html"
             class="inline-block rounded-lg bg-[#3C52C3] text-white px-5 py-2.5
                    font-semibold text-sm hover:bg-[#007FFF] transition">
            Log in / Sign up
          </a>
        </div>`;
    }

    return `
      <form action="saveBooking.php" method="POST" class="flex flex-col gap-5" id="bookingForm">
        <input type="hidden" name="serviceID" value="${esc(svc.serviceID)}" />

        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-slate-700">
            Preferred date <span class="text-red-500">*</span>
          </label>
          <input id="bkDate" type="date" name="startDate" required
                 min="${window.todayISO}" max="${window.maxISO}"
                 class="rounded-lg border border-slate-300 px-4 py-3 text-base
                        focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none" />
        </div>

        <div class="flex flex-col gap-3">
          <label class="text-sm font-semibold text-slate-700">
            Preferred time slot <span class="text-red-500">*</span>
          </label>
          <p id="bkSlotHint" class="text-xs text-slate-500">
            Please select a date first to see available slots.
          </p>
          <div id="bkMorningGroup" class="hidden">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">MORNING - A.M.</p>
            <div id="bkMorningSlots" class="grid grid-cols-3 gap-2"></div>
          </div>
          <div id="bkAfternoonGroup" class="hidden mt-2">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">AFTERNOON - P.M.</p>
            <div id="bkAfternoonSlots" class="grid grid-cols-3 gap-2"></div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-slate-700">
            Who is this service for? <span class="text-red-500">*</span>
          </label>
          <p class="text-xs text-slate-500 -mt-1 mb-1">
            The nurse will ask for this person at the door.
          </p>
          <input type="text" name="patientName" required maxlength="100"
                 value="${esc(window.defaultPatient)}"
                 placeholder="e.g. Mr. Chow Wai Kit"
                 class="rounded-lg border border-slate-300 px-4 py-3 text-base
                        placeholder-slate-400
                        focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none" />
        </div>

        <div class="flex flex-col gap-3">
          <p class="text-sm font-semibold text-slate-700">
            Service address <span class="text-red-500">*</span>
          </p>

          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-slate-500">Street / Building / Unit</label>
            <input type="text" name="location" required maxlength="200"
                   placeholder="e.g. 12, Jalan Merdeka, Taman Sri Indah"
                   class="rounded-lg border border-slate-300 px-4 py-3 text-base
                          placeholder-slate-400
                          focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none" />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-slate-500">Area / Neighbourhood</label>
            <input type="text" name="area" required maxlength="100"
                   placeholder="e.g. Jelutong"
                   class="rounded-lg border border-slate-300 px-4 py-3 text-base
                          placeholder-slate-400
                          focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none" />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-slate-500">Postcode</label>
            <input type="text" name="postcode" readonly value="11600"
                   class="rounded-lg border border-slate-300 bg-slate-100 text-slate-600
                          px-4 py-3 text-base cursor-not-allowed" />
            <p class="text-xs text-slate-400">Currently we only serve <strong>Penang</strong> areas.</p>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-slate-700">
            Special instructions <span class="text-slate-400 font-normal">(optional)</span>
          </label>
          <textarea name="specialInstructions" rows="3" maxlength="1000"
                    placeholder="Any allergies, mobility issues, pet warnings, or preferences?"
                    class="rounded-lg border border-slate-300 px-4 py-3 text-base
                           placeholder-slate-400 resize-none
                           focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none"></textarea>
        </div>

        <div class="border-t border-slate-100 pt-6 flex flex-col sm:flex-row gap-3">
          <button type="button" id="bkCancel"
                  class="flex-1 rounded-xl border border-slate-300 bg-white py-3.5
                         text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
            Cancel
          </button>
          <button type="submit" id="bkSubmit"
                  class="flex-1 rounded-xl bg-[#007FFF] py-3.5 text-sm font-bold text-white
                         hover:bg-[#0066cc] active:scale-[0.98] transition
                         disabled:opacity-60 disabled:cursor-not-allowed">
            Confirm booking
          </button>
        </div>

        <p class="text-xs text-slate-400 text-center -mt-2">
          You'll receive a confirmation email shortly after booking.
        </p>
      </form>`;
  }

  function wireSlotLogic() {
    const dateEl         = document.getElementById('bkDate');
    const hintEl         = document.getElementById('bkSlotHint');
    const morningGroup   = document.getElementById('bkMorningGroup');
    const afternoonGroup = document.getElementById('bkAfternoonGroup');
    const morningSlots   = document.getElementById('bkMorningSlots');
    const afternoonSlots = document.getElementById('bkAfternoonSlots');
    const form           = document.getElementById('bookingForm');
    const cancelBtn      = document.getElementById('bkCancel');
    const submitBtn      = document.getElementById('bkSubmit');

    if (!dateEl) return;

    const availability = window.availability || {};
    const timeSlots    = window.timeSlots    || {};

    function slotButton(time, label, remaining) {
      const disabled = remaining <= 0;
      const wrap = document.createElement('label');
      wrap.className = disabled ? 'cursor-not-allowed' : 'cursor-pointer';
      wrap.innerHTML = `
        <input type="radio" name="startTime" value="${time}"
               class="peer sr-only" ${disabled ? 'disabled' : ''} />
        <div class="rounded-xl border-2 border-slate-200 bg-white text-center py-3 px-2
                    transition select-none
                    hover:border-[#3C52C3]/50
                    peer-checked:border-[#3C52C3] peer-checked:bg-[#3C52C3]/5
                    ${disabled ? 'opacity-50' : ''}">
          <p class="text-sm font-bold text-slate-800">${label}</p>
          <p class="text-xs mt-0.5 ${disabled ? 'text-red-500 font-semibold' : 'text-slate-500'}">
            ${disabled ? 'Full' : `${remaining} left`}
          </p>
        </div>`;
      return wrap;
    }

    dateEl.addEventListener('change', function () {
      const d = this.value;
      if (!d) {
        morningGroup.classList.add('hidden');
        afternoonGroup.classList.add('hidden');
        hintEl.classList.remove('hidden');
        hintEl.textContent = 'Please select a date first to see available slots.';
        return;
      }
      morningSlots.innerHTML = '';
      afternoonSlots.innerHTML = '';
      morningGroup.classList.remove('hidden');
      afternoonGroup.classList.remove('hidden');
      hintEl.classList.add('hidden');

      const dayMap = availability[d] || {};
      Object.entries(timeSlots).forEach(([time, meta]) => {
        const remaining = dayMap[time] ?? 0;
        const el = slotButton(time, meta.label, remaining);
        if (meta.period === 'Morning') morningSlots.appendChild(el);
        else                           afternoonSlots.appendChild(el);
      });
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    form.addEventListener('submit', function (e) {
      const picked = form.querySelector('input[name="startTime"]:checked');
      if (!picked) {
        e.preventDefault();
        alert('Please select a time slot.');
        return;
      }
      submitBtn.disabled = true;
      submitBtn.textContent = 'Booking…';
    });
  }

  function openModal(serviceId, mode) {
    const svc = getService(serviceId);
    if (!svc) return;

    activeServiceID = svc.serviceID;
    populateShared(svc);
    populateDetail(svc);
    bookingContainer.innerHTML = buildBookingForm(svc);
    wireSlotLogic();

    modalSlider.style.transition = 'none';
    if (mode === 'book') slideToBooking();
    else                 slideToDetail();

    modalWrapper.classList.remove('hidden');
    modalWrapper.classList.add('flex');
    document.body.style.overflow = 'hidden';
    isOpen = true;

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        modalSlider.style.transition = 'transform 500ms cubic-bezier(0.4, 0, 0.2, 1)';
      });
    });
  }

  function closeModal() {
    modalWrapper.classList.add('hidden');
    modalWrapper.classList.remove('flex');
    document.body.style.overflow = '';
    isOpen = false;
  }

  document.querySelectorAll('.js-open-detail').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.serviceId, 'view'));
  });
  document.querySelectorAll('.js-open-booking').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.serviceId, 'book'));
  });

  detailBookNow.addEventListener('click', () => {
    if (activeServiceID) slideToBooking();
  });
  backToDetail.addEventListener('click', () => {
    if (activeServiceID) slideToDetail();
  });

  closeModalBtn.addEventListener('click', closeModal);

  modalWrapper.addEventListener('click', e => {
    const card = document.getElementById('modalCard');
    if (!card.contains(e.target)) closeModal();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && isOpen) closeModal();
  });

  /* ============================================================
     BOOKING SUCCESS POPUP
     ============================================================ */
  const successModal = document.getElementById('successModal');
  const successClose = document.getElementById('successClose');

  if (successModal) {
    successModal.classList.remove('hidden');
    successModal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    if (window.history.replaceState) {
      window.history.replaceState({}, document.title, location.pathname);
    }

    function closeSuccess() {
      successModal.classList.add('hidden');
      successModal.classList.remove('flex');
      document.body.style.overflow = '';
    }

    successClose.addEventListener('click', closeSuccess);

    successModal.addEventListener('click', e => {
      const card = document.getElementById('successCard');
      if (card && !card.contains(e.target)) closeSuccess();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !successModal.classList.contains('hidden')) {
        closeSuccess();
      }
    });
  }

})();