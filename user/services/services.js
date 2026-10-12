// user/services/services.js

(function () {
  /* ============================================================
     FILTERS (search + categories)
     ============================================================ */
  const searchInput = document.getElementById('searchInput');
  const chips       = document.querySelectorAll('.cat-chip');
  const cards       = document.querySelectorAll('.svc-card');
  const emptyState  = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');

  let activeCat  = 'all';
  let activeTerm = '';

  function applyFilter() {
    let visible = 0;

    cards.forEach(card => {
      const cat  = card.dataset.cat;
      const text = card.dataset.text || '';

      const catMatch  = activeCat === 'all' || cat === activeCat;
      const termMatch = activeTerm === '' || text.includes(activeTerm);

      if (catMatch && termMatch) {
        card.classList.remove('hidden');
        visible++;
      } else {
        card.classList.add('hidden');
      }
    });

    emptyState.classList.toggle('hidden', visible > 0);
    resultCount.textContent = visible === 0
      ? ''
      : `Showing ${visible} service${visible === 1 ? '' : 's'}`;
  }

  let searchTimer = null;
  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      activeTerm = searchInput.value.trim().toLowerCase();
      applyFilter();
    }, 200);
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
     MODAL LOGIC
     ============================================================ */
  const modal        = document.getElementById('detailModal');
  const modalCard    = document.getElementById('modalCard');
  const closeBtn     = document.getElementById('closeModal');
  const modalImg     = document.getElementById('modalImg');
  const modalTitle   = document.getElementById('modalTitle');
  const modalCat     = document.getElementById('modalCategory');
  const modalRating  = document.getElementById('modalRating');
  const modalReviews = document.getElementById('modalReviews');
  const modalDur     = document.getElementById('modalDuration');
  const modalDesc    = document.getElementById('modalDescription');
  const modalFeat    = document.getElementById('modalFeatures');
  const modalBooking = document.getElementById('modalBookingContainer');
  const modalPrice   = document.getElementById('modalPrice');
  const modalUnit    = document.getElementById('modalPriceUnit');

  /* ---------- Helpers ---------- */
  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function todayISO() {
    return new Date().toISOString().split('T')[0];
  }

  /* ---------- Open modal ---------- */
  function openModal(serviceId, scrollToForm) {
    const svc = (window.servicesData || []).find(s => String(s.serviceID) === String(serviceId));
    if (!svc) return;

    /* Image + title */
    modalImg.src = svc.serviceImageURL || '../../images/services/placeholder.jpg';
    modalTitle.textContent = svc.serviceName;
    modalCat.textContent   = svc.category || 'General';

    /* Meta */
    modalRating.textContent  = Number(svc.average_rating).toFixed(2);
    modalReviews.textContent = `(${svc.review_count} reviews)`;
    modalDur.textContent     = (svc.durationMin && svc.durationMax)
      ? `Est. ${svc.durationMin}–${svc.durationMax} mins`
      : '';

    /* Body */
    modalDesc.textContent = svc.description || '';

    /* Features */
    modalFeat.innerHTML = '';
    const features = svc.features ? svc.features.split('|||') : [];
    if (features.length === 0) {
      modalFeat.innerHTML = '<li class="text-slate-400 text-sm">No details listed.</li>';
    } else {
      features.forEach(f => {
        const li = document.createElement('li');
        li.className = 'flex items-start gap-3';
        li.innerHTML = `
          <span class="mt-0.5 w-5 h-5 rounded-full bg-[#10B981] text-white
                       flex items-center justify-center text-xs font-bold flex-shrink-0">✓</span>
          <span>${esc(f)}</span>`;
        modalFeat.appendChild(li);
      });
    }

    /* Price */
    modalPrice.textContent = Number(svc.price).toFixed(2);
    modalUnit.textContent  = svc.price_unit || 'visit';

    /* Booking form or login prompt */
    if (window.isLoggedIn) {
      modalBooking.innerHTML = `
        <h3 class="text-lg font-bold text-slate-800 mb-4">Book this service</h3>
        <form action="bookAppointment.php" method="POST" class="flex flex-col gap-4">
          <input type="hidden" name="serviceID" value="${esc(svc.serviceID)}" />

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-700">Preferred date <span class="text-red-500">*</span></label>
              <input type="date" name="startDate" required min="${todayISO()}"
                     class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm
                            focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-slate-700">Preferred time <span class="text-red-500">*</span></label>
              <input type="time" name="startTime" required
                     class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm
                            focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none" />
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Service address <span class="text-red-500">*</span></label>
            <input type="text" name="locationAddress" required maxlength="500"
                   placeholder="Where should the nurse come?"
                   class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm
                          placeholder-slate-400
                          focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-slate-700">Special instructions (optional)</label>
            <textarea name="specialInstructions" rows="2" maxlength="1000"
                      placeholder="Any allergies, mobility issues, or preferences?"
                      class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm
                             placeholder-slate-400 resize-none
                             focus:border-[#3C52C3] focus:ring-2 focus:ring-[#007FFF]/40 outline-none"></textarea>
          </div>

          <button type="submit"
                  class="mt-2 w-full rounded-lg bg-[#007FFF] px-6 py-3 font-semibold text-white
                         hover:bg-[#0066cc] active:scale-95 transition">
            Confirm booking
          </button>
          <p class="text-xs text-slate-400 text-center">
            You'll receive a confirmation email shortly after booking.
          </p>
        </form>`;
    } else {
      modalBooking.innerHTML = `
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

    /* Show modal */
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    /* If user clicked "Book now", scroll the booking section into view */
    if (scrollToForm) {
      setTimeout(() => {
        modalBooking.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 100);
    }
  }

  /* ---------- Close modal ---------- */
  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  /* ---------- Event bindings ---------- */
  closeBtn.addEventListener('click', closeModal);

  // Click on the dark backdrop (but not on the card) closes the modal
  modal.addEventListener('click', function (e) {
    if (!modalCard.contains(e.target)) closeModal();
  });

  // ESC key closes
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });

  // "View details" buttons
  document.querySelectorAll('.js-view-details').forEach(btn => {
    btn.addEventListener('click', () => {
      openModal(btn.dataset.serviceId, false);
    });
  });

  // "Book now" buttons (opens modal and scrolls to form)
  document.querySelectorAll('.js-book-now').forEach(btn => {
    btn.addEventListener('click', () => {
      openModal(btn.dataset.serviceId, true);
    });
  });

})();