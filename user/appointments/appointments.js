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

    // Only show the "no match" state when there ARE cards but none match
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

  // Pull JSON safely from an inert <script type="application/json"> block
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
    if (!appt) {
      console.warn('Appointment not found', id);
      return;
    }

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

})();