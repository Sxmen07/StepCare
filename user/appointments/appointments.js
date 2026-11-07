// user/appointments/appointments.js
(function () {
  'use strict';

  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  /* ============================================================
     1. FILTER
     ============================================================ */
  const chips   = $$('.appt-chip');
  const cards   = $$('.appt-card');
  const noMatch = $('#noMatch');
  const countEl = $('#apptCount');

  let activeStatus = 'all';

  function matches(status, filter) {
    if (filter === 'all') return true;
    if (filter === 'confirmed') return status === 'confirmed' || status === 'in_progress';
    if (filter === 'cancelled') return status === 'cancelled' || status === 'declined';
    return status === filter;
  }

  function applyFilter() {
    let visible = 0;
    cards.forEach(card => {
      const ok = matches(card.dataset.status, activeStatus);
      card.classList.toggle('hidden', !ok);
      if (ok) visible++;
    });

    if (countEl) {
      countEl.textContent = visible === 0
        ? ''
        : `Showing ${visible} appointment${visible === 1 ? '' : 's'}`;
    }
    if (noMatch) {
      noMatch.classList.toggle('hidden', !(visible === 0 && cards.length > 0));
    }
  }

  try {
    const saved = sessionStorage.getItem('apptActiveFilter');
    if (saved && saved !== 'all') {
      const chip = chips.find(c => c.dataset.status === saved);
      if (chip) {
        chips.forEach(c => c.classList.remove('is-active'));
        chip.classList.add('is-active');
        activeStatus = saved;
      }
    }
  } catch (e) {}

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('is-active'));
      chip.classList.add('is-active');
      activeStatus = chip.dataset.status;
      try { sessionStorage.setItem('apptActiveFilter', activeStatus); } catch (e) {}
      applyFilter();
    });
  });

  applyFilter();

  /* ============================================================
     2. NAVIGATION & DETAILS
     ============================================================ */
  function navigateToDetails(id) {
    if (!id) return;
    const visible = cards.filter(c => !c.classList.contains('hidden'));
    visible.forEach((card, i) => {
      setTimeout(() => card.classList.add('fade-left'), i * 55);
    });

    const totalMs = visible.length * 55 + 380;
    setTimeout(() => {
      window.location.href = `viewDetails/viewDetails.php?id=${encodeURIComponent(id)}`;
    }, totalMs);
  }

  $$('.js-view-appt').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      navigateToDetails(btn.dataset.viewId);
    });
  });

  cards.forEach(card => {
    card.addEventListener('click', (e) => {
      if (e.target.closest('button, a, input, select, textarea')) return;
      navigateToDetails(card.dataset.id);
    });
  });

  /* ============================================================
     3. POLICY MODAL
     ============================================================ */
  const policyModal    = $('#policyModal');
  const openPolicyBtn  = $('#openPolicyBtn');
  const closePolicyBtn = $('#closePolicyBtn');

  function openPolicy()  { policyModal.classList.remove('hidden');  policyModal.classList.add('flex'); }
  function closePolicy() { policyModal.classList.add('hidden');     policyModal.classList.remove('flex'); }

  if (openPolicyBtn)  openPolicyBtn.addEventListener('click', openPolicy);
  if (closePolicyBtn) closePolicyBtn.addEventListener('click', closePolicy);
  if (policyModal)    policyModal.addEventListener('click', (e) => { if (e.target === policyModal) closePolicy(); });

  /* ============================================================
     4. CANCEL FLOW WITH CHECKBOX REASONS
     ============================================================ */
  const cancelModal    = $('#cancelConfirmModal');
  const btnConfirm     = $('#btnConfirmCancel');
  const btnKeep        = $('#btnKeepAppt');
  const closeCancelBtn = $('#closeCancelModalBtn');
  const cancelOther    = $('#cancelOtherReason');
  const cancelCheckboxes = $$('input[name="cancelReason"]');

  let pendingCancelId  = null;
  let cancelCountdown  = null;

  function openCancelModal(apptId) {
    pendingCancelId = apptId;
    cancelCheckboxes.forEach(cb => cb.checked = false);
    if (cancelOther) cancelOther.value = '';

    btnConfirm.disabled     = true;
    btnConfirm.textContent  = 'Wait 10s...';
    btnConfirm.className    = 'w-full rounded-xl bg-slate-300 py-3.5 text-sm font-bold text-slate-500 transition cursor-not-allowed';

    cancelModal.classList.remove('hidden');
    cancelModal.classList.add('flex');

    let seconds = 10;
    clearInterval(cancelCountdown);
    cancelCountdown = setInterval(() => {
      seconds--;
      if (seconds > 0) {
        btnConfirm.textContent = `Wait ${seconds}s...`;
      } else {
        clearInterval(cancelCountdown);
        btnConfirm.disabled    = false;
        btnConfirm.textContent = 'Yes, Cancel Appointment';
        btnConfirm.className   = 'w-full rounded-xl bg-red-600 py-3.5 text-sm font-bold text-white hover:bg-red-700 active:scale-95 transition';
      }
    }, 1000);
  }

  function closeCancelModal() {
    clearInterval(cancelCountdown);
    cancelModal.classList.add('hidden');
    cancelModal.classList.remove('flex');
    pendingCancelId = null;
  }

  $$('.js-cancel-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openCancelModal(btn.dataset.cancelId);
    });
  });

  if (btnKeep) btnKeep.addEventListener('click', closeCancelModal);
  if (closeCancelBtn) closeCancelBtn.addEventListener('click', closeCancelModal);

  if (btnConfirm) {
    btnConfirm.addEventListener('click', async () => {
      if (!pendingCancelId) return;

      const checkedReasons = cancelCheckboxes.filter(cb => cb.checked).map(cb => cb.value);
      const otherTxt = cancelOther ? cancelOther.value.trim() : '';
      if (otherTxt) checkedReasons.push(otherTxt);

      const finalReason = checkedReasons.length > 0 ? checkedReasons.join('; ') : 'Cancelled by user';

      btnConfirm.disabled    = true;
      btnConfirm.textContent = 'Cancelling…';

      try {
        const form = new FormData();
        form.append('appointmentID', pendingCancelId);
        form.append('cancellationReason', finalReason);

        const res  = await fetch('cancelBooking.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
          window.location.reload();
        } else {
          alert(data.message || 'Could not cancel. Please try again.');
          btnConfirm.disabled    = false;
          btnConfirm.textContent = 'Yes, Cancel Appointment';
        }
      } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
        btnConfirm.disabled    = false;
        btnConfirm.textContent = 'Yes, Cancel Appointment';
      }
    });
  }

  /* ============================================================
     5. RATE & REVIEW & VIEW REVIEW FLOW
     ============================================================ */
  const reviewModal    = $('#reviewModal');
  const reviewTitle    = $('#reviewServiceTitle');
  const reviewNurseTitle = $('#reviewNurseTitle');
  const reviewSubmit   = $('#reviewSubmitBtn');
  const reviewClose    = $('#closeReviewModal');
  const reviewComment  = $('#reviewComment');
  const nurseComment   = $('#nurseComment');
  const reviewStars    = $$('.service-star');
  const nurseStars     = $$('.nurse-star');
  const serviceLabel   = $('#serviceRatingLabel');
  const nurseLabel     = $('#nurseRatingLabel');
  const modalHeaderTitle = $('#reviewModalHeaderTitle');
  const modalHeaderSub = $('#reviewModalHeaderSub');

  let activeReviewId = null;
  let serviceRating   = 0;
  let nurseRating     = 0;

  const LABELS = { 1: 'Poor 😞', 2: 'Fair 😕', 3: 'Good 🙂', 4: 'Great 😃', 5: 'Excellent 🤩' };

  function paintStars(stars, count) {
    stars.forEach(btn => {
      const val = Number(btn.dataset.value);
      btn.style.color = val <= count ? '#f59e0b' : '#cbd5e1';
    });
  }

  function updateSubmitState() {
    const ready = serviceRating > 0 && nurseRating > 0 && activeReviewId;
    reviewSubmit.disabled    = !ready;
    reviewSubmit.className   = ready
      ? 'w-full rounded-xl bg-[#3C52C3] py-3 text-sm font-bold text-white hover:bg-[#2d3e9c] active:scale-95 transition'
      : 'w-full rounded-xl bg-slate-300 py-3 text-sm font-bold text-slate-500 cursor-not-allowed transition';
  }

  /* Open Rate & Review (Editable) */
  $$('.js-review-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      activeReviewId = btn.dataset.reviewId;
      serviceRating = 0;
      nurseRating = 0;

      modalHeaderTitle.textContent = 'Rate & Review';
      modalHeaderSub.textContent = 'Your feedback helps us improve care';
      reviewTitle.textContent = btn.dataset.serviceName || 'Service';
      reviewNurseTitle.textContent = btn.dataset.nurseName || 'Nurse';

      reviewComment.value = '';
      nurseComment.value = '';
      reviewComment.disabled = false;
      nurseComment.disabled = false;
      reviewComment.classList.remove('bg-slate-100');
      nurseComment.classList.remove('bg-slate-100');

      paintStars(reviewStars, 0);
      paintStars(nurseStars, 0);
      serviceLabel.textContent = 'Select service rating';
      nurseLabel.textContent = 'Select nurse rating';

      reviewSubmit.classList.remove('hidden');
      updateSubmitState();

      reviewModal.classList.remove('hidden');
      reviewModal.classList.add('flex');
    });
  });

  /* Open View Review (Read-only) */
  $$('.js-view-review-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      activeReviewId = null;

      modalHeaderTitle.textContent = 'Submitted Review';
      modalHeaderSub.textContent = 'View your feedback for this appointment';
      reviewTitle.textContent = btn.dataset.serviceName || 'Service';
      reviewNurseTitle.textContent = btn.dataset.nurseName || 'Nurse';

      serviceRating = Number(btn.dataset.serviceRating) || 5;
      nurseRating = Number(btn.dataset.nurseRating) || 5;

      paintStars(reviewStars, serviceRating);
      paintStars(nurseStars, nurseRating);

      serviceLabel.textContent = LABELS[serviceRating] || '';
      nurseLabel.textContent = LABELS[nurseRating] || '';

      reviewComment.value = btn.dataset.serviceComment || 'No comment provided.';
      nurseComment.value = btn.dataset.nurseComment || 'No comment provided.';

      reviewComment.disabled = true;
      nurseComment.disabled = true;
      reviewComment.classList.add('bg-slate-100');
      nurseComment.classList.add('bg-slate-100');

      reviewSubmit.classList.add('hidden');

      reviewModal.classList.remove('hidden');
      reviewModal.classList.add('flex');
    });
  });

  reviewStars.forEach(btn => {
    btn.addEventListener('click', () => {
      if (reviewComment.disabled) return;
      serviceRating = Number(btn.dataset.value);
      paintStars(reviewStars, serviceRating);
      serviceLabel.textContent = LABELS[serviceRating] || '';
      updateSubmitState();
    });
  });

  nurseStars.forEach(btn => {
    btn.addEventListener('click', () => {
      if (nurseComment.disabled) return;
      nurseRating = Number(btn.dataset.value);
      paintStars(nurseStars, nurseRating);
      nurseLabel.textContent = LABELS[nurseRating] || '';
      updateSubmitState();
    });
  });

  function closeReviewModal() {
    reviewModal.classList.add('hidden');
    reviewModal.classList.remove('flex');
    activeReviewId = null;
  }

  if (reviewClose) reviewClose.addEventListener('click', closeReviewModal);

  if (reviewModal) {
    reviewModal.addEventListener('click', (e) => {
      if (e.target === reviewModal) closeReviewModal();
    });
  }

  if (reviewSubmit) {
    reviewSubmit.addEventListener('click', async () => {
      if (!activeReviewId || serviceRating < 1 || nurseRating < 1) return;

      reviewSubmit.disabled    = true;
      reviewSubmit.textContent = 'Submitting…';

      try {
        const form = new FormData();
        form.append('appointmentID', activeReviewId);
        form.append('serviceRating', serviceRating);
        form.append('serviceComment', reviewComment.value.trim());
        form.append('nurseRating', nurseRating);
        form.append('nurseComment', nurseComment.value.trim());

        const res  = await fetch('submitReview.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
          window.location.reload();
        } else {
          alert(data.message || 'Could not submit review.');
          reviewSubmit.textContent = 'Submit Review';
          updateSubmitState();
        }
      } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
        reviewSubmit.textContent = 'Submit Review';
        updateSubmitState();
      }
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (policyModal  && !policyModal.classList.contains('hidden'))  closePolicy();
    if (cancelModal  && !cancelModal.classList.contains('hidden'))  closeCancelModal();
    if (reviewModal  && !reviewModal.classList.contains('hidden'))  closeReviewModal();
  });

})();