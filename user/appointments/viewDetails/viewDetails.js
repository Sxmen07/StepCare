// user/appointments/viewDetails/viewDetails.js
(function () {
  'use strict';

  const wrapper = document.getElementById('pageWrapper');
  const backBtn = document.getElementById('backBtn');

  if (wrapper && backBtn) {
    function goBack() {
      try { sessionStorage.setItem('apptCardsFadeIn', '1'); } catch (e) {}

      wrapper.classList.remove('slide-in-from-right');
      void wrapper.offsetWidth;  // force reflow
      wrapper.classList.add('slide-out-to-right');

      const done = () => { window.location.href = '../appointments.php'; };
      wrapper.addEventListener('animationend', done, { once: true });
      setTimeout(done, 450); // fallback
    }

    backBtn.addEventListener('click', goBack);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && (!proofModal || proofModal.classList.contains('hidden'))) {
        goBack();
      }
    });
  }

  /* ============================================================
     PAYMENT PROOF LIGHTBOX MODAL
     ============================================================ */
  const proofBtn       = document.getElementById('openPaymentProofBtn');
  const proofModal     = document.getElementById('paymentProofModal');
  const closeProofBtn  = document.getElementById('closeProofModalBtn');
  const proofContainer = document.getElementById('proofContainer');
  const proofDateText  = document.getElementById('proofApprovalDate');

  function openProofModal() {
    if (!proofBtn || !proofModal) return;

    const url    = proofBtn.dataset.proofUrl;
    const date   = proofBtn.dataset.approvedDate;
    const status = proofBtn.dataset.status;

    proofDateText.textContent = status === 'paid' ? `Approved on: ${date}` : `Status: ${date}`;

    const isPdf = url.toLowerCase().endsWith('.pdf');
    if (isPdf) {
      proofContainer.innerHTML = `<iframe src="${url}" class="w-full h-96 rounded-xl border border-slate-200"></iframe>`;
    } else {
      proofContainer.innerHTML = `<img src="${url}" alt="Payment Proof" class="max-w-full max-h-[60vh] rounded-xl object-contain shadow-md border border-slate-200" onerror="this.onerror=null; this.src='../../../images/services/placeholder.jpg';">`;
    }

    proofModal.classList.remove('hidden');
    proofModal.classList.add('flex');
  }

  function closeProofModal() {
    if (!proofModal) return;
    proofModal.classList.add('hidden');
    proofModal.classList.remove('flex');
  }

  if (proofBtn)      proofBtn.addEventListener('click', openProofModal);
  if (closeProofBtn) closeProofBtn.addEventListener('click', closeProofModal);
  if (proofModal) {
    proofModal.addEventListener('click', (e) => {
      if (e.target === proofModal) closeProofModal();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && proofModal && !proofModal.classList.contains('hidden')) {
      closeProofModal();
    }
  });

    // Auto-refresh the timeline every 20 seconds
  const timelineContainer = document.getElementById('timelineContainer');
  const appointmentID = new URLSearchParams(window.location.search).get('id');
  
  if (timelineContainer && appointmentID) {
    setInterval(() => {
      fetch(`fetch_timeline.php?id=${appointmentID}`)
        .then(res => res.text())
        .then(html => {
          timelineContainer.innerHTML = html;
        }).catch(e => console.error("Timeline auto-refresh failed", e));
    }, 20000);
  }

})();