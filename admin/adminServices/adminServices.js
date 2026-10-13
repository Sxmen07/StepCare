// admin-services.js
(function () {
  'use strict';

  const state = {
    view: 'list',
    search: '',
    pending: {},
    original: {},
  };

  (window.servicesData || []).forEach(s => {
    state.original[s.serviceID] = { ...s };
  });

  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function fmtPrice(n) {
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0 });
  }

  function fmtDur(min, max) {
    if (!min && !max) return '—';
    if (min && max)   return `${min}–${max} mins`;
    return `${min || max} mins`;
  }

  function currentData() {
    return (window.servicesData || []).map(s => {
      const p = state.pending[s.serviceID];
      if (!p) return { ...s, _pending: null };
      if (p.type === 'delete') return { ...s, _pending: 'delete' };
      return { ...s, ...p.data, _pending: p.type };
    });
  }

  /* ============================================================
     RENDER — LIST
     ============================================================ */
  function renderList(items) {
    const host = $('#listRows');
    host.innerHTML = '';

    if (!items.length) {
      $('#emptyState').classList.remove('hidden');
      return;
    }
    $('#emptyState').classList.add('hidden');

    items.forEach(s => {
      const pendingClass =
        s._pending === 'delete' ? 'is-deleted'
        : s._pending ? 'is-pending' : '';

      const row = document.createElement('div');
      row.className = `svc-row ${pendingClass}`;
      row.dataset.id = s.serviceID;

      row.innerHTML = `
        <div class="svc-main">
          <img src="${esc(s.serviceImageURL || '../../images/services/placeholder.jpg')}"
               alt="" class="svc-thumb"
               onerror="this.src='../../images/services/placeholder.jpg'">
          <div class="min-w-0">
            <div class="svc-name">${esc(s.serviceName)}</div>
            <div class="svc-cat">${esc(s.category || 'General')}</div>
          </div>
        </div>

        <div class="svc-cell">
          <span class="rating">
            <span class="star">★</span>
            <span>${Number(s.average_rating).toFixed(2)}</span>
            <span class="muted">(${s.review_count})</span>
          </span>
        </div>

        <div class="svc-cell">
          <span class="price-value">$${fmtPrice(s.price)}</span>
          <span class="price-unit">/${esc(s.price_unit || 'visit')}</span>
        </div>

        <div class="svc-cell text-slate-600 text-sm">
          ${fmtDur(s.durationMin, s.durationMax)}
        </div>

        <div class="svc-cell">
          ${ Number(s.isTopRecommendation)
              ? `<span class="badge-pin"><i data-lucide="pin" class="w-3 h-3"></i> Pinned</span>`
              : `<span class="badge-plain">Active</span>` }
        </div>

        <div class="svc-actions">
          <button type="button" class="icon-btn" data-action="edit" data-id="${s.serviceID}" title="Edit">
            <i data-lucide="pencil" class="w-4 h-4"></i>
          </button>
          <button type="button" class="icon-btn ${Number(s.isTopRecommendation) ? 'pin-active' : ''}"
                  data-action="pin" data-id="${s.serviceID}"
                  title="${Number(s.isTopRecommendation) ? 'Unpin' : 'Pin as top'}">
            <i data-lucide="pin" class="w-4 h-4"></i>
          </button>
          <button type="button" class="icon-btn danger" data-action="delete" data-id="${s.serviceID}" title="Delete">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      `;

      host.appendChild(row);
    });
  }

  /* ============================================================
     RENDER — GRID
     ============================================================ */
  function renderGrid(items) {
    const host = $('#gridCards');
    host.innerHTML = '';

    if (!items.length) {
      $('#emptyState').classList.remove('hidden');
      return;
    }
    $('#emptyState').classList.add('hidden');

    items.forEach(s => {
      const card = document.createElement('div');
      card.className = `grid-card ${s._pending ? 'is-pending' : ''} ${s._pending === 'delete' ? 'opacity-50' : ''}`;

      card.innerHTML = `
        <div class="grid-img-wrap">
          <img src="${esc(s.serviceImageURL || '../../images/services/placeholder.jpg')}"
               alt=""
               onerror="this.src='../../images/services/placeholder.jpg'">
          <div class="grid-badges">
            ${ Number(s.isTopRecommendation)
                ? `<span class="badge-pin"><i data-lucide="pin" class="w-3 h-3"></i> Pinned</span>`
                : '' }
            ${s._pending ? `<span class="badge-plain bg-amber-100 text-amber-700">Pending</span>` : ''}
          </div>
          <div class="grid-actions">
            <button type="button" class="icon-btn" data-action="edit" data-id="${s.serviceID}" title="Edit">
              <i data-lucide="pencil" class="w-4 h-4"></i>
            </button>
            <button type="button" class="icon-btn ${Number(s.isTopRecommendation) ? 'pin-active' : ''}"
                    data-action="pin" data-id="${s.serviceID}"
                    title="${Number(s.isTopRecommendation) ? 'Unpin' : 'Pin'}">
              <i data-lucide="pin" class="w-4 h-4"></i>
            </button>
            <button type="button" class="icon-btn danger" data-action="delete" data-id="${s.serviceID}" title="Delete">
              <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
          </div>
        </div>

        <div class="grid-body">
          <p class="grid-cat">${esc(s.category || 'General')}</p>
          <p class="grid-title">${esc(s.serviceName)}</p>
          <div class="grid-meta">
            <span class="rating">
              <span class="star">★</span>
              <span>${Number(s.average_rating).toFixed(2)}</span>
            </span>
            <span class="price-value">$${fmtPrice(s.price)}<span class="price-unit">/${esc(s.price_unit || 'visit')}</span></span>
          </div>
        </div>
      `;

      host.appendChild(card);
    });
  }

  /* ============================================================
     MAIN RENDER
     ============================================================ */
  function applyFilterAndRender() {
    const all = currentData();

    const filtered = all.filter(s => {
      if (!state.search) return true;
      const hay = (
        (s.serviceName || '') + ' ' +
        (s.category || '') + ' ' +
        (s.description || '')
      ).toLowerCase();
      return hay.includes(state.search);
    });

    if (state.view === 'list') {
      renderList(filtered);
      $('#listView').classList.remove('hidden');
      $('#gridView').classList.add('hidden');
    } else {
      renderGrid(filtered);
      $('#gridView').classList.remove('hidden');
      $('#listView').classList.add('hidden');
    }

    $('#resultCount').textContent = filtered.length
      ? `${filtered.length} service${filtered.length === 1 ? '' : 's'}`
      : '';

    if (window.lucide) lucide.createIcons();
    updatePendingBar();
  }

  /* ============================================================
     PENDING
     ============================================================ */
  function markPending(id, type, data = null) {
    state.pending[id] = { type, data };
    applyFilterAndRender();
  }

  function clearPending() {
    state.pending = {};
    applyFilterAndRender();
  }

  function discardOne(id) {
    delete state.pending[id];
    applyFilterAndRender();
  }

  function updatePendingBar() {
    const count = Object.keys(state.pending).length;
    const bar = $('#pendingBar');

    if (count === 0) {
      bar.classList.add('hidden');
      bar.classList.remove('flex');
      return;
    }

    bar.classList.remove('hidden');
    bar.classList.add('flex');
    $('#pendingCount').textContent =
      `${count} change${count === 1 ? '' : 's'} pending`;
  }

  function buildLaunchSummary() {
    const items = currentData().filter(s => s._pending);
    const host = $('#launchSummary');
    host.innerHTML = '';

    items.forEach(s => {
      const p = state.pending[s.serviceID];
      let label = '';
      let dotColor = '';

      if (p.type === 'delete') {
        label = `Delete "${s.serviceName}"`;
        dotColor = 'bg-red-500';
      } else if (p.type === 'pin') {
        label = `Pin "${s.serviceName}" as Top Pick`;
        dotColor = 'bg-amber-500';
      } else {
        label = `Edit "${s.serviceName}"`;
        dotColor = 'bg-blue-500';
      }

      const li = document.createElement('li');
      li.className = 'flex items-start gap-2';
      li.innerHTML = `
        <span class="mt-1.5 w-2 h-2 rounded-full ${dotColor} flex-shrink-0"></span>
        <span>${esc(label)}</span>
      `;
      host.appendChild(li);
    });
  }

  /* ============================================================
     EVENTS
     ============================================================ */
  let searchTimer = null;
  $('#searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.search = this.value.trim().toLowerCase();
      applyFilterAndRender();
    }, 180);
  });

  $$('.view-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      $$('.view-toggle').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      state.view = btn.dataset.view;
      applyFilterAndRender();
    });
  });

  document.addEventListener('click', function (e) {
    const actionBtn = e.target.closest('[data-action]');
    if (!actionBtn) return;

    const id = actionBtn.dataset.id;
    const action = actionBtn.dataset.action;
    const svc = currentData().find(s => String(s.serviceID) === String(id));
    if (!svc) return;

    if (action === 'edit')      openEditModal(svc);
    else if (action === 'pin')  handlePin(svc);
    else if (action === 'delete') openDeleteModal(svc);
  });

  function handlePin(svc) {
    const newPinned = Number(svc.isTopRecommendation) ? 0 : 1;
    const orig = state.original[svc.serviceID];
    if (Number(orig.isTopRecommendation) === newPinned) {
      discardOne(svc.serviceID);
      return;
    }
    markPending(svc.serviceID, 'pin', { ...svc, isTopRecommendation: newPinned });
  }

  /* ============================================================
     EDIT MODAL
     ============================================================ */
  const editModal = $('#editModal');

  function openEditModal(svc, isNew = false) {
    $('#editTitle').textContent = isNew ? 'Create Service' : 'Edit Service';
    $('#editServiceID').value = isNew ? '' : svc.serviceID;
    $('#editName').value      = svc.serviceName || '';
    $('#editCategory').value  = svc.category || '';
    $('#editImage').value     = svc.serviceImageURL || '';
    $('#editPrice').value     = svc.price || '';
    $('#editUnit').value      = svc.price_unit || 'visit';
    $('#editDurMin').value    = svc.durationMin || '';
    $('#editDurMax').value    = svc.durationMax || '';
    $('#editDesc').value      = svc.description || '';
    $('#editPinned').checked  = Number(svc.isTopRecommendation) === 1;

    editModal.classList.remove('hidden');
    editModal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    if (window.lucide) lucide.createIcons();
  }

  function closeEditModal() {
    editModal.classList.add('hidden');
    editModal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  $('#editClose').addEventListener('click', closeEditModal);
  $('#editCancel').addEventListener('click', closeEditModal);
  editModal.addEventListener('click', e => {
    if (e.target === editModal) closeEditModal();
  });

  $('#editForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const id = $('#editServiceID').value;
    const data = {
      serviceName: $('#editName').value.trim(),
      category:    $('#editCategory').value.trim() || null,
      serviceImageURL: $('#editImage').value.trim() || null,
      price:       parseFloat($('#editPrice').value) || 0,
      price_unit:  $('#editUnit').value.trim() || 'visit',
      durationMin: parseInt($('#editDurMin').value) || null,
      durationMax: parseInt($('#editDurMax').value) || null,
      description: $('#editDesc').value.trim() || null,
      isTopRecommendation: $('#editPinned').checked ? 1 : 0,
    };

    if (id) {
      const orig = state.original[id];
      const changed = Object.keys(data).some(k => String(orig[k] ?? '') !== String(data[k] ?? ''));
      if (!changed) discardOne(id);
      else markPending(id, 'edit', data);
    } else {
      showToast('Create flow not wired to DB yet', 'info');
    }

    closeEditModal();
  });

  /* ============================================================
     DELETE MODAL
     ============================================================ */
  const deleteModal = $('#deleteModal');
  let deleteTarget = null;

  function openDeleteModal(svc) {
    deleteTarget = svc;
    $('#deleteName').textContent = svc.serviceName;
    deleteModal.classList.remove('hidden');
    deleteModal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    if (window.lucide) lucide.createIcons();
  }

  function closeDeleteModal() {
    deleteModal.classList.add('hidden');
    deleteModal.classList.remove('flex');
    document.body.style.overflow = '';
    deleteTarget = null;
  }

  $('#deleteCancel').addEventListener('click', closeDeleteModal);
  deleteModal.addEventListener('click', e => {
    if (e.target === deleteModal) closeDeleteModal();
  });

  $('#deleteConfirm').addEventListener('click', function () {
    if (!deleteTarget) return;
    markPending(deleteTarget.serviceID, 'delete');
    closeDeleteModal();
  });

  /* ============================================================
     CREATE
     ============================================================ */
  $('#createBtn').addEventListener('click', () => {
    openEditModal({
      serviceName: '', category: '', serviceImageURL: '',
      price: '', price_unit: 'visit', durationMin: '', durationMax: '',
      description: '', isTopRecommendation: 0,
    }, true);
  });

  /* ============================================================
     PENDING BAR
     ============================================================ */
  $('#discardBtn').addEventListener('click', () => {
    if (!confirm('Discard all pending changes?')) return;
    clearPending();
    showToast('Changes discarded', 'info');
  });

  /* ============================================================
     LAUNCH
     ============================================================ */
  const launchModal   = $('#launchModal');
  const launchConfirm = $('#launchConfirm');
  const countdownText = $('#countdownText');
  const countdownRing = $('#countdownRing');
  let countdownTimer  = null;
  const TOTAL_SECONDS = 10;
  const CIRCUMFERENCE = 2 * Math.PI * 44;

  function openLaunchModal() {
    const count = Object.keys(state.pending).length;
    if (count === 0) return;

    buildLaunchSummary();

    launchModal.classList.remove('hidden');
    launchModal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    let remaining = TOTAL_SECONDS;
    countdownText.textContent = remaining;
    countdownRing.style.strokeDashoffset = 0;
    launchConfirm.disabled = true;
    launchConfirm.className =
      'flex-1 rounded-xl bg-slate-300 py-3 text-sm font-bold text-white cursor-not-allowed transition disabled:opacity-60';

    clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
      remaining--;
      countdownText.textContent = remaining;
      const progress = (TOTAL_SECONDS - remaining) / TOTAL_SECONDS;
      countdownRing.style.strokeDashoffset = progress * CIRCUMFERENCE;

      if (remaining <= 0) {
        clearInterval(countdownTimer);
        launchConfirm.disabled = false;
        launchConfirm.className =
          'flex-1 rounded-xl bg-[#10B981] py-3 text-sm font-bold text-white hover:bg-[#059669] active:scale-95 transition';
      }
    }, 1000);
  }

  function closeLaunchModal() {
    clearInterval(countdownTimer);
    launchModal.classList.add('hidden');
    launchModal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  $('#launchBtn').addEventListener('click', openLaunchModal);
  $('#launchCancel').addEventListener('click', closeLaunchModal);
  launchModal.addEventListener('click', e => {
    if (e.target === launchModal) closeLaunchModal();
  });

  launchConfirm.addEventListener('click', async function () {
    launchConfirm.disabled = true;
    launchConfirm.textContent = 'Launching…';

    try {
      const res = await fetch('admin-services-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'launch', changes: state.pending }),
      });

      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Server error');

      if (json.services) {
        window.servicesData = json.services;
        state.original = {};
        window.servicesData.forEach(s => { state.original[s.serviceID] = { ...s }; });
      }
      state.pending = {};

      closeLaunchModal();
      applyFilterAndRender();
      showToast(`${json.applied || 0} change${json.applied === 1 ? '' : 's'} launched successfully`, 'success');

    } catch (err) {
      console.error(err);
      showToast('Launch failed: ' + err.message, 'error');
      launchConfirm.disabled = false;
      launchConfirm.textContent = 'Confirm Launch';
    }
  });

  /* ============================================================
     TOAST
     ============================================================ */
  let toastTimer = null;
  function showToast(msg, type = 'success') {
    const toast = $('#toast');
    const icon  = toast.querySelector('i');

    $('#toastMsg').textContent = msg;

    icon.setAttribute('data-lucide',
      type === 'error' ? 'x-circle' : type === 'info' ? 'info' : 'check-circle');
    icon.classList.remove('text-[#10B981]', 'text-red-400', 'text-blue-400');
    icon.classList.add(
      type === 'error' ? 'text-red-400' : type === 'info' ? 'text-blue-400' : 'text-[#10B981]');

    toast.classList.remove('hidden');
    toast.classList.add('flex');
    if (window.lucide) lucide.createIcons();

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.classList.add('hidden');
      toast.classList.remove('flex');
    }, 3500);
  }

  /* ============================================================
     ESC key
     ============================================================ */
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (!editModal.classList.contains('hidden'))       closeEditModal();
    else if (!deleteModal.classList.contains('hidden')) closeDeleteModal();
    else if (!launchModal.classList.contains('hidden')) closeLaunchModal();
  });

  /* ============================================================
     INIT
     ============================================================ */
  applyFilterAndRender();
})();