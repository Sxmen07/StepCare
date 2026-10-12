// services.js
(function () {
  const searchInput = document.getElementById('searchInput');
  const chips       = document.querySelectorAll('.cat-chip');
  const cards       = document.querySelectorAll('.svc-card');
  const emptyState  = document.getElementById('emptyState');
  const resultCount = document.getElementById('resultCount');

  let activeCat  = 'all';
  let activeTerm = '';

  /* ============================================
     Apply current filter state to all cards
     ============================================ */
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

  /* ============================================
     Search (debounced)
     ============================================ */
  let searchTimer = null;
  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      activeTerm = searchInput.value.trim().toLowerCase();
      applyFilter();
    }, 200);
  });

  /* ============================================
     Category chips
     ============================================ */
  chips.forEach(chip => {
    chip.addEventListener('click', function () {
      chips.forEach(c => c.classList.remove('is-active'));
      chip.classList.add('is-active');

      activeCat = chip.dataset.cat;
      applyFilter();
    });
  });

  /* ============================================
     Initial run (also fills result count)
     ============================================ */
  applyFilter();
})();