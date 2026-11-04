// user/appointments/viewDetails/viewDetails.js
(function () {
  'use strict';

  const wrapper = document.getElementById('pageWrapper');
  const backBtn = document.getElementById('backBtn');

  if (!wrapper || !backBtn) return;

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
    if (e.key === 'Escape') goBack();
  });
})();