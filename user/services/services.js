// user/services/services.js
(function () {
  'use strict';

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ============================================================
     FILTERS (WITH SMOOTH ANIMATION)
     ============================================================ */
  const searchInput  = document.getElementById('searchInput');
  const chips        = document.querySelectorAll('.cat-chip');
  const cards        = document.querySelectorAll('.svc-card');
  const emptyState   = document.getElementById('emptyState');
  const resultCount  = document.getElementById('resultCount');
  const servicesGrid = document.getElementById('servicesGrid');

  let activeCat  = 'all';
  let activeTerm = '';
  let filterTimeout = null;

  function applyFilter() {
    if (!servicesGrid) return;
    clearTimeout(filterTimeout);

    servicesGrid.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
    servicesGrid.style.opacity = '0';
    servicesGrid.style.transform = 'scale(0.98) translateY(10px)';
    servicesGrid.style.pointerEvents = 'none';

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

      servicesGrid.style.opacity = '1';
      servicesGrid.style.transform = 'scale(1) translateY(0)';
      servicesGrid.style.pointerEvents = 'auto';
    }, 200);
  }

  let searchTimer = null;
  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      activeTerm = searchInput.value.trim().toLowerCase();
      applyFilter();
    }, 300);
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

  const revealObserver = new IntersectionObserver(function (entries, observer) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        el.classList.add('is-visible');
        observer.unobserve(el);
        setTimeout(() => el.classList.remove('reveal-on-scroll', 'is-visible'), 900);
      }
    });
  }, revealOptions);

  revealElements.forEach(el => revealObserver.observe(el));

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

  function getRect(el) {
    return el ? el.getBoundingClientRect() : null;
  }

  /* ============================================================
     MODAL + FLIP TRANSITION
     ============================================================ */
  const modalWrapper  = document.getElementById('modalWrapper');
  const modalCard     = document.getElementById('modalCard');
  const modalSlider   = document.getElementById('modalSlider');

  const closeModalBtn = document.getElementById('closeModal');
  const detailBookNow = document.getElementById('detailBookNow');
  const backToDetail  = document.getElementById('backToDetail');
  const backToBooking = document.getElementById('backToBooking');

  const sharedImgEl    = document.getElementById('sharedImg');
  const sharedTitle    = document.getElementById('sharedTitle');
  const sharedCategory = document.getElementById('sharedCategory');
  const sharedRating   = document.getElementById('sharedRating');
  const sharedReviews  = document.getElementById('sharedReviews');
  const sharedDuration = document.getElementById('sharedDuration');
  const sharedPrice    = document.getElementById('sharedPrice');
  const sharedUnit     = document.getElementById('sharedUnit');

  const detailDesc        = document.getElementById('detailDescription');
  const detailFeat        = document.getElementById('detailFeatures');
  const detailReviewsList = document.getElementById('detailReviewsList');

  const bookingContainer = document.getElementById('bookingFormContainer');

  // Payment DOM
  const paymentForm = document.getElementById('paymentForm');
  const payFileInput = document.getElementById('payFileInput');
  const payPreviewContainer = document.getElementById('payPreviewContainer');
  const payImagePreview = document.getElementById('payImagePreview');
  const payRemoveFile = document.getElementById('payRemoveFile');
  const paySubmitBtn = document.getElementById('paySubmitBtn');
  const payDropZone = document.getElementById('payDropZone');

  let activeServiceID = null;
  let activeSourceImg = null;
  let isOpen = false;
  let flipAnimating = false;

  // 3 Panels translation
  function slideToDetail()  { modalSlider.style.transform = 'translateX(0)'; }
  function slideToBooking() { modalSlider.style.transform = 'translateX(-33.3333%)'; }
  function slideToPayment() { modalSlider.style.transform = 'translateX(-66.6666%)'; }

  function createImageClone(imgEl, rect, radius) {
    const clone = document.createElement('img');
    clone.src  = imgEl.currentSrc || imgEl.src;
    clone.alt  = '';
    clone.className = 'svc-flip-clone';
    clone.style.top          = rect.top    + 'px';
    clone.style.left         = rect.left   + 'px';
    clone.style.width        = rect.width  + 'px';
    clone.style.height       = rect.height + 'px';
    clone.style.borderRadius = radius;
    document.body.appendChild(clone);
    return clone;
  }

  function populateShared(svc) {
    sharedImgEl.src            = svc.serviceImageURL || '../../images/services/placeholder.jpg';
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

    detailReviewsList.innerHTML = '';
    const reviews = getRandomReviews(svc.serviceID);
    reviews.forEach(r => {
        const stars   = '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating);
        const initial = r.firstName ? r.firstName.charAt(0) : 'U';
        let formattedDate = '';
        if (r.createdAt) {
            const dateObj = new Date(r.createdAt.replace(' ', 'T'));
            formattedDate = dateObj.toLocaleDateString('en-GB', {
                day: 'numeric', month: 'short', year: 'numeric'
            });
        }

        const div = document.createElement('div');
        div.className = 'rounded-xl bg-slate-50 p-4 border border-slate-100 flex flex-col';
        div.innerHTML = `
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-[#3C52C3]/10 text-[#3C52C3] flex items-center justify-center font-bold text-xs">
                        ${initial}
                    </div>
                    <p class="text-xs font-bold text-slate-800">${esc(r.firstName)} ${esc(r.lastName || '')}</p>
                </div>
                <div class="text-amber-500 text-sm tracking-widest">
                    ${stars}
                </div>
            </div>
            <p class="text-sm text-slate-600 leading-relaxed">"${esc(r.comment)}"</p>
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

    let effectiveMinDate = window.todayISO;
    const slots = Object.keys(window.timeSlots || {});

    if (slots.length > 0) {
      const lastSlot = slots[slots.length - 1];
      if (window.serverTimeNow >= lastSlot) {
        const tomorrow = new Date(window.todayISO);
        tomorrow.setDate(tomorrow.getDate() + 1);
        effectiveMinDate = tomorrow.toISOString().split('T')[0];
      }
    }

    return `
      <form action="saveBooking.php" method="POST" class="flex flex-col gap-5" id="bookingForm">
        <input type="hidden" name="serviceID" value="${esc(svc.serviceID)}" />

        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-slate-700">
            Preferred date <span class="text-red-500">*</span>
          </label>
          <input id="bkDate" type="date" name="startDate" required
                 min="${effectiveMinDate}" max="${window.maxISO}"
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
                         disabled:opacity-60 disabled:cursor-not-allowed
                         inline-flex items-center justify-center gap-2">
            Continue to Payment
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
            </svg>
          </button>
        </div>
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

    function slotButton(time, label, remaining, isPast) {
      const disabled = remaining <= 0 || isPast;

      let subText  = `${remaining} left`;
      let subColor = 'text-slate-500';

      if (isPast) {
        subText  = 'Passed';
        subColor = 'text-red-500 font-semibold';
      } else if (remaining <= 0) {
        subText  = 'Full';
        subColor = 'text-red-500 font-semibold';
      }

      const wrap = document.createElement('label');
      wrap.className = disabled ? 'cursor-not-allowed' : 'cursor-pointer';
      wrap.innerHTML = `
        <input type="radio" name="startTime" value="${time}"
               class="peer sr-only" ${disabled ? 'disabled' : ''} />
        <div class="rounded-xl border-2 border-slate-200 bg-white text-center py-3 px-2
                    transition select-none
                    hover:border-[#3C52C3]/50
                    peer-checked:border-[#3C52C3] peer-checked:bg-[#3C52C3]/5
                    ${disabled ? 'opacity-50 bg-slate-50' : ''}">
          <p class="text-sm font-bold text-slate-800">${label}</p>
          <p class="text-xs mt-0.5 ${subColor}">
            ${subText}
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
      const isToday = (d === window.todayISO);
      const currentTime = window.serverTimeNow || "00:00:00";

      Object.entries(timeSlots).forEach(([time, meta]) => {
        const remaining = dayMap[time] ?? 0;
        const isPast = isToday && (time < currentTime);
        const el = slotButton(time, meta.label, remaining, isPast);
        if (meta.period === 'Morning') morningSlots.appendChild(el);
        else                           afternoonSlots.appendChild(el);
      });
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    // ASYNC SUBMIT (保存数据到 Session)
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const picked = form.querySelector('input[name="startTime"]:checked');
      if (!picked) {
        alert('Please select a time slot.');
        return;
      }
      
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.textContent = 'Processing…';

      try {
        const formData = new FormData(form);
        const res = await fetch('saveBooking.php', {
          method: 'POST',
          body: formData,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.success) {
          // 设置支付面板信息
          document.getElementById('payAmount').textContent = `RM ${data.price}`;
          window._currentBooking = data; // 保存以供支付成功后重定向
          
          // 重置支付表单
          payFileInput.value = '';
          payPreviewContainer.classList.add('hidden');
          payImagePreview.src = '';
          paySubmitBtn.disabled = true;

          // 滑到支付面板
          slideToPayment();

          // 恢复按钮状态
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        } else {
          alert(data.message || 'Booking failed.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      } catch (err) {
        alert('Network error occurred.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  }

  /* ============================================================
     PAYMENT LOGIC
     ============================================================ */
  if (payFileInput) {
    payFileInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        if (file.size > 5 * 1024 * 1024) {
          alert('File is too large. Max 5MB.');
          payFileInput.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
          payImagePreview.src = e.target.result;
          payPreviewContainer.classList.remove('hidden');
          paySubmitBtn.disabled = false;
        }
        reader.readAsDataURL(file);
      }
    });

    payRemoveFile.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      payFileInput.value = '';
      payPreviewContainer.classList.add('hidden');
      payImagePreview.src = '';
      paySubmitBtn.disabled = true;
    });

    ['dragenter', 'dragover'].forEach(eventName => {
      payDropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        payDropZone.classList.add('border-[#3C52C3]', 'bg-blue-50');
      }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
      payDropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        payDropZone.classList.remove('border-[#3C52C3]', 'bg-blue-50');
      }, false);
    });
  }

  if (backToBooking) {
    backToBooking.addEventListener('click', () => {
      slideToBooking();
    });
  }

  if (paymentForm) {
    paymentForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const originalText = paySubmitBtn.textContent;
      paySubmitBtn.disabled = true;
      paySubmitBtn.textContent = 'Verifying…';

      try {
        const formData = new FormData(paymentForm);
        const res = await fetch('processPayment.php', {
          method: 'POST',
          body: formData,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.success) {
          const b = window._currentBooking || {};
          const urlParams = new URLSearchParams({
            booked: 1,
            ref: '#SC' + String(data.appointmentID).padStart(6, '0'),
            date: b.prettyDate || '',
            time: b.prettyTime || '',
            service: b.serviceName || ''
          });
          window.location.href = 'services.php?' + urlParams.toString();
        } else {
          alert(data.message || 'Payment upload failed.');
          paySubmitBtn.disabled = false;
          paySubmitBtn.textContent = originalText;
        }
      } catch (err) {
        alert('Network error occurred.');
        paySubmitBtn.disabled = false;
        paySubmitBtn.textContent = originalText;
      }
    });
  }

  /* ============================================================
     OPEN / CLOSE
     ============================================================ */
  const EASE_OUT   = 'cubic-bezier(0.32, 0.72, 0, 1)';
  const EASE_INOUT = 'cubic-bezier(0.65, 0, 0.35, 1)';
  const OPEN_MS    = 650;
  const CLOSE_MS   = 480;
  const BACKDROP   = 'rgba(15, 23, 42, 0.7)';
  const BACKDROP_0 = 'rgba(15, 23, 42, 0)';
  const CARD_RADIUS  = 16;
  const MODAL_RADIUS = 24;

  let activeSourceCard = null;
  let runningAnims = [];

  function anim(el, keyframes, opts) {
    const a = el.animate(keyframes, opts);
    runningAnims.push(a);
    return a;
  }

  function clearAnims() {
    runningAnims.forEach(a => { try { a.cancel(); } catch (_) {} });
    runningAnims = [];
  }

  function insetTo(outer, inner, radius) {
    const t = Math.max(0, inner.top    - outer.top);
    const r = Math.max(0, outer.right  - inner.right);
    const b = Math.max(0, outer.bottom - inner.bottom);
    const l = Math.max(0, inner.left   - outer.left);
    return `inset(${t}px ${r}px ${b}px ${l}px round ${radius}px)`;
  }

  function heroParts() {
    const aside = sharedImgEl.parentElement;
    return {
      gradient: aside.querySelector(':scope > div.absolute'),
      text:     aside.querySelector(':scope > div.relative')
    };
  }

  function layerIn(startAt, total) {
    const { gradient, text } = heroParts();
    const rise = (el, delay, dur, dist) => anim(el, [
      { opacity: 0, transform: `translateY(${dist}px)` },
      { opacity: 1, transform: 'translateY(0)' }
    ], { duration: dur, delay, easing: EASE_OUT, fill: 'backwards' });

    anim(gradient, [{ opacity: 0 }, { opacity: 1 }],
         { duration: total * 0.4, delay: startAt, easing: 'ease-out', fill: 'backwards' });
    Array.from(text.children).forEach((child, i) =>
      rise(child, startAt + 60 + i * 70, total * 0.55, 18));

    const right = modalSlider.parentElement;
    anim(right, [{ opacity: 0 }, { opacity: 1 }],
         { duration: total * 0.4, delay: startAt, easing: 'ease-out', fill: 'backwards' });

    const rows = [
      detailDesc,
      ...Array.from(detailFeat.children),
      ...Array.from(detailReviewsList.children).slice(0, 6)
    ];
    rows.forEach((el, i) => rise(el, startAt + 120 + Math.min(i, 9) * 45, total * 0.55, 16));

    anim(closeModalBtn, [{ opacity: 0, transform: 'scale(0.6)' }, { opacity: 1, transform: 'scale(1)' }],
         { duration: total * 0.5, delay: startAt + 100, easing: EASE_OUT, fill: 'backwards' });
  }

  function openModal(serviceId, mode, sourceImg) {
    if (flipAnimating || isOpen) return;
    const svc = getService(serviceId);
    if (!svc) return;

    flipAnimating    = true;
    activeServiceID  = svc.serviceID;
    activeSourceImg  = sourceImg || null;
    activeSourceCard = sourceImg ? sourceImg.closest('.svc-card') : null;

    populateShared(svc);
    populateDetail(svc);
    bookingContainer.innerHTML = buildBookingForm(svc);
    wireSlotLogic();

    document.body.style.overflow = 'hidden';

    modalWrapper.classList.remove('hidden');
    modalWrapper.classList.add('flex');

    modalSlider.style.transition = 'none';
    if (mode === 'book') slideToBooking(); else slideToDetail();
    void modalWrapper.offsetHeight;
    modalSlider.style.transition = 'transform 500ms cubic-bezier(0.4, 0, 0.2, 1)';

    const cardRect = getRect(modalCard);
    const toImg    = getRect(sharedImgEl);
    const fromImg  = getRect(activeSourceImg);
    const fromCard = getRect(activeSourceCard);

    isOpen = true;
    clearAnims();

    const canFlip = !reduceMotion && fromImg && fromCard && toImg &&
                    fromCard.width > 0 && toImg.width > 0;

    if (!canFlip) {
      const a = anim(modalWrapper, [{ opacity: 0 }, { opacity: 1 }],
                     { duration: 200, easing: 'ease', fill: 'backwards' });
      a.onfinish = () => { clearAnims(); flipAnimating = false; };
      return;
    }

    anim(modalWrapper, [{ backgroundColor: BACKDROP_0 }, { backgroundColor: BACKDROP }],
         { duration: OPEN_MS * 0.8, easing: 'ease-out', fill: 'backwards' });

    const clip = anim(modalCard, [
      { clipPath: insetTo(cardRect, fromCard, CARD_RADIUS) },
      { clipPath: `inset(0px 0px 0px 0px round ${MODAL_RADIUS}px)` }
    ], { duration: OPEN_MS, easing: EASE_OUT, fill: 'backwards' });

    anim(modalCard, [{ opacity: 0 }, { opacity: 1 }],
         { duration: OPEN_MS * 0.25, easing: 'ease-out', fill: 'backwards' });

    const clone = createImageClone(activeSourceImg, fromImg, `${CARD_RADIUS}px ${CARD_RADIUS}px 0 0`);
    const flight = clone.animate([
      { top: fromImg.top + 'px', left: fromImg.left + 'px',
        width: fromImg.width + 'px', height: fromImg.height + 'px',
        borderRadius: `${CARD_RADIUS}px ${CARD_RADIUS}px 0 0` },
      { top: toImg.top + 'px', left: toImg.left + 'px',
        width: toImg.width + 'px', height: toImg.height + 'px',
        borderRadius: `${MODAL_RADIUS}px 0 0 ${MODAL_RADIUS}px` }
    ], { duration: OPEN_MS, easing: EASE_OUT, fill: 'forwards' });
    runningAnims.push(flight);

    anim(sharedImgEl, [{ opacity: 0 }, { opacity: 1 }],
         { duration: OPEN_MS * 0.3, delay: OPEN_MS * 0.7, easing: 'ease-out', fill: 'backwards' });
    const cloneFade = clone.animate([{ opacity: 1 }, { opacity: 0 }],
         { duration: OPEN_MS * 0.3, delay: OPEN_MS * 0.7, easing: 'ease-out', fill: 'forwards' });
    runningAnims.push(cloneFade);

    layerIn(OPEN_MS * 0.35, OPEN_MS);

    clip.onfinish = () => {
      clone.remove();
      clearAnims();
      flipAnimating = false;
    };
  }

  function closeModal() {
    if (!isOpen || flipAnimating) return;
    flipAnimating = true;
    clearAnims();

    const cardRect = getRect(modalCard);
    const fromImg  = getRect(sharedImgEl);
    const toImg    = activeSourceImg  ? getRect(activeSourceImg)  : null;
    const toCard   = activeSourceCard ? getRect(activeSourceCard) : null;

    const canFlip = !reduceMotion && toImg && toCard && fromImg &&
                    toCard.width > 0 && fromImg.width > 0;

    const finish = () => {
      clearAnims();
      modalWrapper.classList.add('hidden');
      modalWrapper.classList.remove('flex');
      document.body.style.overflow = '';
      isOpen = false;
      activeSourceImg  = null;
      activeSourceCard = null;
      flipAnimating = false;
    };

    if (!canFlip) {
      const a = anim(modalWrapper, [{ opacity: 1 }, { opacity: 0 }],
                     { duration: 200, easing: 'ease', fill: 'forwards' });
      a.onfinish = finish;
      return;
    }

    const { gradient, text } = heroParts();
    const right = modalSlider.parentElement;

    [gradient, text, right, closeModalBtn].forEach(el =>
      anim(el, [{ opacity: 1 }, { opacity: 0 }],
           { duration: CLOSE_MS * 0.35, easing: 'ease-in', fill: 'forwards' }));

    anim(modalWrapper, [{ backgroundColor: BACKDROP }, { backgroundColor: BACKDROP_0 }],
         { duration: CLOSE_MS, easing: 'ease-in-out', fill: 'forwards' });

    const clip = anim(modalCard, [
      { clipPath: `inset(0px 0px 0px 0px round ${MODAL_RADIUS}px)` },
      { clipPath: insetTo(cardRect, toCard, CARD_RADIUS) }
    ], { duration: CLOSE_MS, easing: EASE_INOUT, fill: 'forwards' });

    anim(modalCard, [{ opacity: 1 }, { opacity: 0 }],
         { duration: CLOSE_MS * 0.35, delay: CLOSE_MS * 0.65, easing: 'ease-in', fill: 'forwards' });

    sharedImgEl.style.opacity = '0';
    const clone = createImageClone(sharedImgEl, fromImg, `${MODAL_RADIUS}px 0 0 ${MODAL_RADIUS}px`);
    runningAnims.push(clone.animate([
      { top: fromImg.top + 'px', left: fromImg.left + 'px',
        width: fromImg.width + 'px', height: fromImg.height + 'px',
        borderRadius: `${MODAL_RADIUS}px 0 0 ${MODAL_RADIUS}px` },
      { top: toImg.top + 'px', left: toImg.left + 'px',
        width: toImg.width + 'px', height: toImg.height + 'px',
        borderRadius: `${CARD_RADIUS}px ${CARD_RADIUS}px 0 0` }
    ], { duration: CLOSE_MS, easing: EASE_INOUT, fill: 'forwards' }));

    clip.onfinish = () => {
      clone.remove();
      sharedImgEl.style.opacity = '';
      finish();
    };
  }

  /* ============================================================
     CLICK BINDINGS
     ============================================================ */
  document.querySelectorAll('.svc-card').forEach(card => {
    card.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      let mode = 'view';

      if (btn) {
        e.stopPropagation();
        mode = btn.classList.contains('js-open-booking') ? 'book' : 'view';
      }

      const id = card.dataset.serviceId;
      const img = card.querySelector('img');
      openModal(id, mode, img);
    });
  });

  detailBookNow.addEventListener('click', () => {
    if (activeServiceID) slideToBooking();
  });
  backToDetail.addEventListener('click', () => {
    if (activeServiceID) slideToDetail();
  });

  closeModalBtn.addEventListener('click', closeModal);

  modalWrapper.addEventListener('click', e => {
    if (!modalCard.contains(e.target)) closeModal();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && isOpen && !flipAnimating) closeModal();
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