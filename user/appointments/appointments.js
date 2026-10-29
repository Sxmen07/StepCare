// user/appointments/appointments.js
(function () {
  'use strict';

  /* ============================================================
     FILTER
     ============================================================ */
  const chips      = document.querySelectorAll('.appt-chip');
  const cards      = document.querySelectorAll('.appt-card');
  const noMatch    = document.getElementById('noMatch');
  const countEl    = document.getElementById('apptCount');

  let activeStatus = 'all';

  function matches(status, filter) {
    if (filter === 'all') return true;
    if (filter === 'confirmed')
      return status === 'confirmed' || status === 'in_progress';
    if (filter === 'cancelled')
      return status === 'cancelled' || status === 'declined';
    return status === filter;
  }

  function applyFilter() {
    let visible = 0;

    cards.forEach(card => {
      const ok = matches(card.dataset.status, activeStatus);
      if (ok) { card.classList.remove('hidden'); visible++; }
      else    { card.classList.add('hidden'); }
    });

    if (countEl) {
      countEl.textContent = visible === 0
        ? ''
        : `Showing ${visible} appointment${visible === 1 ? '' : 's'}`;
    }

    if (noMatch) {
      const shouldShow = visible === 0 && cards.length > 0;
      noMatch.classList.toggle('hidden', !shouldShow);
    }
  }

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('is-active'));
      chip.classList.add('is-active');
      activeStatus = chip.dataset.status;
      applyFilter();
    });
  });

  applyFilter();

  /* ============================================================
     MODAL
     ============================================================ */
  const modal         = document.getElementById('apptModal');
  const modalCard     = document.getElementById('apptModalCard');
  const closeModalBtn = document.getElementById('closeApptModal');

  let appointments = [];
  try {
    const raw = document.getElementById('apptData');
    if (raw) appointments = JSON.parse(raw.textContent || '[]');
  } catch (e) {
    console.error('Could not parse appointment data', e);
  }

  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function fmtDate(dt) {
    const d = new Date(dt.replace(' ', 'T'));
    return d.toLocaleDateString('en-GB', {
      weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
    });
  }

  function fmtTime(dt) {
    const d = new Date(dt.replace(' ', 'T'));
    return d.toLocaleTimeString('en-US', {
      hour: 'numeric', minute: '2-digit', hour12: true
    });
  }

  function statusBadge(status, large) {
    let label = status, color = 'slate';
    switch (status) {
      case 'pending':     label = 'Pending Nurse'; color = 'amber';   break;
      case 'confirmed':   label = 'Confirmed';     color = 'blue';    break;
      case 'in_progress': label = 'In Progress';   color = 'indigo';  break;
      case 'completed':   label = 'Completed';     color = 'emerald'; break;
      case 'cancelled':   label = 'Cancelled';     color = 'red';     break;
      case 'declined':    label = 'Declined';      color = 'red';     break;
    }
    const size = large ? 'px-3 py-1.5 text-xs' : 'px-2.5 py-1 text-[11px]';
    const colorMap = {
      amber:   'bg-amber-100 text-amber-800 ring-amber-200',
      blue:    'bg-blue-100 text-blue-800 ring-blue-200',
      indigo:  'bg-indigo-100 text-indigo-800 ring-indigo-200',
      emerald: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
      red:     'bg-red-100 text-red-700 ring-red-200',
      slate:   'bg-slate-100 text-slate-700 ring-slate-200',
    };
    return `<span class="inline-flex items-center rounded-full font-bold uppercase
                          tracking-wider ring-1 ${colorMap[color]} ${size}">
              ${esc(label)}
            </span>`;
  }

  function openModal(id) {
    const appt = appointments.find(a => String(a.appointmentID) === String(id));
    if (!appt) return;

    document.getElementById('amReference').textContent =
      `Reference #SC${String(appt.appointmentID).padStart(6,'0')}`;
    document.getElementById('amTitle').textContent     = appt.serviceName || 'Service';
    document.getElementById('amStatusBadge').innerHTML = statusBadge(appt.appointmentStatus, true);
    document.getElementById('amPatient').textContent   = appt.patientName || '—';
    document.getElementById('amDateTime').textContent  =
      `${fmtDate(appt.startDateTime)} at ${fmtTime(appt.startDateTime)}`;
    document.getElementById('amAddress').textContent   = appt.locationAddress || '—';
    document.getElementById('amPrice').textContent     =
      `RM${Number(appt.price).toFixed(2)} / ${appt.price_unit || 'visit'}`;

    const nurseRow = document.getElementById('amNurseRow');
    const nurseEl  = document.getElementById('amNurse');
    if (appt.nurseFirstName) {
      nurseEl.textContent = `${appt.nurseFirstName} ${appt.nurseLastName || ''}`.trim();
      nurseRow.classList.remove('hidden');
    } else {
      nurseRow.classList.add('hidden');
    }

    const notesBlock = document.getElementById('amNotesBlock');
    if (appt.specialInstructions) {
      document.getElementById('amNotes').textContent = appt.specialInstructions;
      notesBlock.classList.remove('hidden');
    } else {
      notesBlock.classList.add('hidden');
    }

    document.getElementById('amPendingNote')
      .classList.toggle('hidden', appt.appointmentStatus !== 'pending');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.js-view-appt').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.viewId));
  });

  closeModalBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', e => {
    if (!modalCard.contains(e.target)) closeModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });

  /* ============================================================
     CANCELLATION POLICY & LOGIC
     ============================================================ */
  const policyModal = document.getElementById('policyModal');
  const openPolicyBtn = document.getElementById('openPolicyBtn');
  const closePolicyBtn = document.getElementById('closePolicyBtn');

  if (openPolicyBtn) {
    openPolicyBtn.addEventListener('click', () => {
      policyModal.classList.remove('hidden');
      policyModal.classList.add('flex');
    });
    closePolicyBtn.addEventListener('click', () => {
      policyModal.classList.add('hidden');
      policyModal.classList.remove('flex');
    });
    policyModal.addEventListener('click', (e) => {
      if (e.target === policyModal) {
        policyModal.classList.add('hidden');
        policyModal.classList.remove('flex');
      }
    });
  }

  function checkCancelEligibility(btn) {
    const card = btn.closest('.appt-card');
    const startStr = card.dataset.start;
    if (!startStr) return;

    const apptTime = new Date(startStr.replace(' ', 'T')).getTime();
    const now = new Date().getTime();
    const hoursDiff = (apptTime - now) / (1000 * 60 * 60);

    if (hoursDiff < 4) {
      btn.disabled = true;
      btn.textContent = 'Too Late to Cancel';
      btn.className = 'rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-400 cursor-not-allowed';
      btn.title = 'Cancellations must be made at least 4 hours in advance.';
    }
  }

  document.querySelectorAll('.js-cancel-btn').forEach(checkCancelEligibility);

  /* ============================================================
     CUSTOM CANCEL MODAL LOGIC & 10s COUNTDOWN
     ============================================================ */
  const cancelConfirmModal = document.getElementById('cancelConfirmModal');
  const btnConfirmCancel = document.getElementById('btnConfirmCancel');
  const btnKeepAppt = document.getElementById('btnKeepAppt');

  let cancelTimer = null;
  let activeCancelData = null;

  document.querySelectorAll('.js-cancel-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      if (this.disabled) return;

      // Store context
      activeCancelData = {
        btn: this,
        card: this.closest('.appt-card'),
        id: this.closest('.appt-card').dataset.id
      };

      openCancelModal();
    });
  });

  function openCancelModal() {
    cancelConfirmModal.classList.remove('hidden');
    cancelConfirmModal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    // Reset timer state
    if (cancelTimer) clearInterval(cancelTimer);
    let timeLeft = 10;

    btnConfirmCancel.disabled = true;
    btnConfirmCancel.className = 'w-full rounded-xl bg-slate-300 py-3.5 text-sm font-bold text-slate-500 transition cursor-not-allowed';
    btnConfirmCancel.textContent = `Wait ${timeLeft}s...`;

    // Start countdown
    cancelTimer = setInterval(() => {
      timeLeft--;
      if (timeLeft > 0) {
        btnConfirmCancel.textContent = `Wait ${timeLeft}s...`;
      } else {
        clearInterval(cancelTimer);
        btnConfirmCancel.disabled = false;
        btnConfirmCancel.className = 'w-full rounded-xl bg-red-600 py-3.5 text-sm font-bold text-white hover:bg-red-700 active:scale-[0.98] transition';
        btnConfirmCancel.textContent = 'Yes, Cancel Appointment';
      }
    }, 1000);
  }

  function closeCancelModal() {
    cancelConfirmModal.classList.add('hidden');
    cancelConfirmModal.classList.remove('flex');
    document.body.style.overflow = '';
    if (cancelTimer) clearInterval(cancelTimer);
    activeCancelData = null;
  }

  // Keep Appointment Click
  btnKeepAppt.addEventListener('click', closeCancelModal);

  // Click outside to close
  cancelConfirmModal.addEventListener('click', (e) => {
    if (e.target === cancelConfirmModal) closeCancelModal();
  });

  // Confirm Cancel Click
  btnConfirmCancel.addEventListener('click', function() {
    if (this.disabled || !activeCancelData) return;

    const { btn, card, id } = activeCancelData;
    closeCancelModal(); // Close the modal immediately

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Cancelling...';

    fetch('cancelBooking.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `appointmentID=${id}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        btn.remove(); // Remove cancel button

        card.dataset.status = 'cancelled';
        const badge = card.querySelector('.js-status-badge');
        if (badge) {
          badge.textContent = 'Cancelled';
          badge.className = 'js-status-badge inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.1em] ring-1 flex-shrink-0 bg-red-100 text-red-700 ring-red-200';
        }

        applyFilter();
        setTimeout(() => { window.location.reload(); }, 800);

      } else {
        alert(data.message || 'Failed to cancel appointment.');
        btn.disabled = false;
        btn.textContent = originalText;
      }
    })
    .catch(err => {
      console.error(err);
      alert('An error occurred. Please try again.');
      btn.disabled = false;
      btn.textContent = originalText;
    });
  });

  /* ============================================================
     RATE & REVIEW FLOW
     ============================================================ */
  const reviewModal     = document.getElementById('reviewModal');
  const closeReviewBtn  = document.getElementById('closeReviewModal');
  const reviewLaterBtn  = document.getElementById('reviewLaterBtn');
  const reviewSubmitBtn = document.getElementById('reviewSubmitBtn');
  const reviewTitleEl   = document.getElementById('reviewServiceTitle');
  const reviewCommentEl = document.getElementById('reviewComment');
  const reviewCharCount = document.getElementById('reviewCharCount');
  const reviewRatingLbl = document.getElementById('reviewRatingLabel');
  const reviewStars     = document.querySelectorAll('.review-star');

  const RATING_LABELS = { 1:'Poor', 2:'Fair', 3:'Good', 4:'Very Good', 5:'Excellent' };
  let currentRating    = 0;
  let activeReviewData = null;

  function paintStars(n) {
    reviewStars.forEach((s, i) => {
      s.style.color = i < n ? '#fbbf24' : '#cbd5e1';
    });
  }

  function refreshSubmitState() {
    if (currentRating >= 1) {
      reviewSubmitBtn.disabled = false;
      reviewSubmitBtn.className =
        'w-full rounded-xl bg-[#3C52C3] py-3.5 text-sm font-bold text-white hover:bg-[#2d3e9c] active:scale-[0.98] transition';
    } else {
      reviewSubmitBtn.disabled = true;
      reviewSubmitBtn.className =
        'w-full rounded-xl bg-slate-300 py-3.5 text-sm font-bold text-slate-500 cursor-not-allowed transition';
    }
  }

  reviewStars.forEach((star, idx) => {
    star.addEventListener('click', () => {
      currentRating = idx + 1;
      paintStars(currentRating);
      reviewRatingLbl.textContent = RATING_LABELS[currentRating];
      reviewRatingLbl.classList.remove('text-slate-400');
      reviewRatingLbl.classList.add('text-amber-600', 'font-semibold');
      refreshSubmitState();
    });
    star.addEventListener('mouseenter', () => paintStars(idx + 1));
  });

  const reviewStarsWrap = reviewStars.length ? reviewStars[0].parentElement : null;
  if (reviewStarsWrap) {
    reviewStarsWrap.addEventListener('mouseleave', () => paintStars(currentRating));
  }

  reviewCommentEl.addEventListener('input', () => {
    reviewCharCount.textContent = reviewCommentEl.value.length;
  });

  function openReviewModal(appt, btn) {
    activeReviewData = { appt, btn };
    currentRating = 0;
    paintStars(0);
    reviewRatingLbl.textContent = 'Select a rating';
    reviewRatingLbl.className = 'text-center text-sm text-slate-400 mb-6 h-5';
    reviewCommentEl.value = '';
    reviewCharCount.textContent = '0';
    reviewTitleEl.textContent = appt.serviceName || 'Service';
    refreshSubmitState();

    reviewModal.classList.remove('hidden');
    reviewModal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function closeReviewModal() {
    reviewModal.classList.add('hidden');
    reviewModal.classList.remove('flex');
    document.body.style.overflow = '';
    activeReviewData = null;
  }

  document.querySelectorAll('.js-review-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.reviewId;
      const appt = appointments.find(a => String(a.appointmentID) === String(id));
      if (appt) openReviewModal(appt, btn);
    });
  });

  closeReviewBtn.addEventListener('click', closeReviewModal);
  reviewLaterBtn.addEventListener('click', closeReviewModal);
  reviewModal.addEventListener('click', e => {
    if (e.target === reviewModal) closeReviewModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !reviewModal.classList.contains('hidden')) closeReviewModal();
  });

  reviewSubmitBtn.addEventListener('click', () => {
    if (!activeReviewData || currentRating < 1) return;

    const { appt, btn } = activeReviewData;
    const submittedRating = currentRating;
    const submittedComment = reviewCommentEl.value.trim();

    reviewSubmitBtn.disabled = true;
    reviewSubmitBtn.textContent = 'Submitting…';

    const body =
      `appointmentID=${encodeURIComponent(appt.appointmentID)}` +
      `&rating=${encodeURIComponent(submittedRating)}` +
      `&comment=${encodeURIComponent(submittedComment)}`;

    fetch('submitReview.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success) throw new Error(data.message || 'Failed to submit review');

      closeReviewModal();

      // Replace button with a "Reviewed" pill in place
      const wrapper = document.createElement('div');
      wrapper.className =
        'flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2';
      wrapper.innerHTML =
        '<span class="text-sm font-semibold text-emerald-700">✓ Reviewed</span>' +
        `<span class="text-amber-500 text-sm leading-none">${'★'.repeat(submittedRating)}</span>`;
      btn.replaceWith(wrapper);
    })
    .catch(err => {
      console.error(err);
      alert(err.message || 'An error occurred. Please try again.');
      reviewSubmitBtn.disabled = false;
      reviewSubmitBtn.textContent = 'Submit Review';
      refreshSubmitState();
    });
  });

})();