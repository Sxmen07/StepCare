// user/appointments/appointments.js
(function () {
  'use strict';

  /* ============================================================
     SHORTCUT HELPERS
     ============================================================ */
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

  /* Restore filter from sessionStorage (when returning from viewDetails) */
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
  } catch (e) { /* ignore */ }

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
     2. FADE-IN ON RETURN FROM viewDetails
     ============================================================ */
  try {
    if (sessionStorage.getItem('apptCardsFadeIn') === '1') {
      sessionStorage.removeItem('apptCardsFadeIn');

      const visible = cards.filter(c => !c.classList.contains('hidden'));
      visible.forEach((card, i) => {
        card.style.opacity    = '0';
        card.style.transform  = 'translateX(-40px)';
        card.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
        setTimeout(() => {
          card.style.opacity   = '1';
          card.style.transform = 'translateX(0)';
          setTimeout(() => {
            card.style.opacity    = '';
            card.style.transform  = '';
            card.style.transition = '';
          }, 500);
        }, i * 55);
      });
    }
  } catch (e) { /* ignore */ }

  /* ============================================================
     3. NAVIGATE → viewDetails WITH STAIRCASE FADE-OUT
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

  /* Clicking the card body also opens details (except buttons/links) */
  cards.forEach(card => {
    card.addEventListener('click', (e) => {
      if (e.target.closest('button, a, input, select, textarea')) return;
      navigateToDetails(card.dataset.id);
    });
  });

  /* ============================================================
     4. POLICY MODAL
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
     5. CANCEL FLOW
     ============================================================ */
  const cancelModal    = $('#cancelConfirmModal');
  const btnConfirm     = $('#btnConfirmCancel');
  const btnKeep        = $('#btnKeepAppt');

  let pendingCancelId  = null;
  let cancelCountdown  = null;

  function openCancelModal(apptId) {
    pendingCancelId = apptId;

    /* Reset button to disabled + countdown */
    btnConfirm.disabled     = true;
    btnConfirm.textContent  = 'Wait 10s...';
    btnConfirm.className    = 'w-full rounded-xl bg-slate-300 py-3.5 text-sm font-bold text-slate-500 transition cursor-not-allowed';

    cancelModal.classList.remove('hidden');
    cancelModal.classList.add('flex');

    /* 10-second safety countdown */
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

  if (btnConfirm) {
    btnConfirm.addEventListener('click', async () => {
      if (!pendingCancelId) return;

      btnConfirm.disabled    = true;
      btnConfirm.textContent = 'Cancelling…';

      try {
        const form = new FormData();
        form.append('appointmentID', pendingCancelId);

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
     6. RATE & REVIEW FLOW
     ============================================================ */
  const reviewModal    = $('#reviewModal');
  const reviewTitle    = $('#reviewServiceTitle');
  const reviewSubmit   = $('#reviewSubmitBtn');
  const reviewLater    = $('#reviewLaterBtn');
  const reviewClose    = $('#closeReviewModal');
  const reviewComment  = $('#reviewComment');
  const reviewChar     = $('#reviewCharCount');
  const reviewStars    = $$('.review-star');
  const reviewLabel    = $('#reviewRatingLabel');

  let activeReviewId = null;
  let activeRating   = 0;

  const RATING_LABELS = {
    1: 'Poor 😞',
    2: 'Fair 😕',
    3: 'Good 🙂',
    4: 'Great 😃',
    5: 'Excellent 🤩',
  };

  function updateStars(n) {
    reviewStars.forEach(btn => {
      const val = Number(btn.dataset.value);
      btn.style.color = val <= n ? '#f59e0b' : '#cbd5e1';
    });
  }

  function updateSubmitState() {
    const ready = activeRating > 0 && activeReviewId;
    reviewSubmit.disabled    = !ready;
    reviewSubmit.className   = ready
      ? 'w-full rounded-xl bg-[#3C52C3] py-3.5 text-sm font-bold text-white hover:bg-[#2d3e9c] active:scale-95 transition'
      : 'w-full rounded-xl bg-slate-300 py-3.5 text-sm font-bold text-slate-500 cursor-not-allowed transition';
  }

  function openReviewModal(apptId, serviceName) {
    activeReviewId = apptId;
    activeRating   = 0;

    reviewTitle.textContent   = serviceName || 'Your service';
    reviewComment.value       = '';
    reviewChar.textContent    = '0';
    reviewLabel.textContent   = 'Select a rating';
    reviewLabel.className     = 'text-center text-sm text-slate-400 mb-6 h-5';

    updateStars(0);
    updateSubmitState();

    reviewModal.classList.remove('hidden');
    reviewModal.classList.add('flex');
  }

  function closeReviewModal() {
    reviewModal.classList.add('hidden');
    reviewModal.classList.remove('flex');
    activeReviewId = null;
    activeRating   = 0;
  }

  $$('.js-review-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const card      = btn.closest('.appt-card');
      const titleEl   = card ? card.querySelector('h3') : null;
      const serviceNm = titleEl ? titleEl.textContent.trim() : '';
      openReviewModal(btn.dataset.reviewId, serviceNm);
    });
  });

  reviewStars.forEach(btn => {
    btn.addEventListener('click', () => {
      activeRating = Number(btn.dataset.value);
      updateStars(activeRating);
      reviewLabel.textContent = RATING_LABELS[activeRating] || '';
      reviewLabel.className   = 'text-center text-sm font-semibold text-amber-600 mb-6 h-5';
      updateSubmitState();
    });
  });

  if (reviewComment) {
    reviewComment.addEventListener('input', () => {
      reviewChar.textContent = reviewComment.value.length;
    });
  }

  if (reviewClose) reviewClose.addEventListener('click', closeReviewModal);
  if (reviewLater) reviewLater.addEventListener('click', closeReviewModal);

  if (reviewModal) {
    reviewModal.addEventListener('click', (e) => {
      if (e.target === reviewModal) closeReviewModal();
    });
  }

  if (reviewSubmit) {
    reviewSubmit.addEventListener('click', async () => {
      if (!activeReviewId || activeRating < 1) return;

      reviewSubmit.disabled    = true;
      reviewSubmit.textContent = 'Submitting…';

      try {
        const form = new FormData();
        form.append('appointmentID', activeReviewId);
        form.append('rating',        activeRating);
        form.append('comment',       reviewComment.value.trim());

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

  /* ============================================================
     7. ESCAPE KEY → close any open modal
     ============================================================ */
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (policyModal  && !policyModal.classList.contains('hidden'))  closePolicy();
    if (cancelModal  && !cancelModal.classList.contains('hidden'))  closeCancelModal();
    if (reviewModal  && !reviewModal.classList.contains('hidden'))  closeReviewModal();
  });

})();