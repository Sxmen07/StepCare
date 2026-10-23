// user/familyCare/familyCare.js
(function () {
  'use strict';

  // If the user is not logged in, selfData will be null. 
  // We don't need to run any JS on the landing page.
  if (!window.selfData) return; 

  /* ============================================================
     DATA
     ============================================================ */
  const self        = window.selfData         || null;
  const members     = window.membersData      || [];
  const allAppts    = window.appointmentsData || [];
  const initialCode = window.activeCode       || null;

  /* ============================================================
     HELPERS
     ============================================================ */
  function esc(s) {
    return String(s ?? '')
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

  function statusMeta(status) {
    switch (status) {
      case 'pending':     return { label: 'Pending Nurse', color: 'amber'   };
      case 'confirmed':   return { label: 'Confirmed',     color: 'blue'    };
      case 'in_progress': return { label: 'In Progress',   color: 'indigo'  };
      case 'completed':   return { label: 'Completed',     color: 'emerald' };
      case 'cancelled':   return { label: 'Cancelled',     color: 'red'     };
      case 'declined':    return { label: 'Declined',      color: 'red'     };
      default:            return { label: status,          color: 'slate'   };
    }
  }

  const colorMap = {
    amber:   'bg-amber-100 text-amber-800 ring-amber-200',
    blue:    'bg-blue-100 text-blue-800 ring-blue-200',
    indigo:  'bg-indigo-100 text-indigo-800 ring-indigo-200',
    emerald: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
    red:     'bg-red-100 text-red-700 ring-red-200',
    slate:   'bg-slate-100 text-slate-700 ring-slate-200',
  };

  /* ============================================================
     STATE
     ============================================================ */
  let activeMemberID = self ? self.userID : null;

  /* ============================================================
     LEFT: MEMBER LIST
     ============================================================ */
  const memberList  = document.getElementById('memberList');
  const memberCount = document.getElementById('memberCount');
  const noMembers   = document.getElementById('noMembers');

  function memberRow(m) {
    const isActive = String(m.userID) === String(activeMemberID);
    const role = m.iAm === 'owner' ? 'Can view you' : 'You can view';

    return `
      <button type="button"
              data-member-id="${m.userID}"
              class="member-card w-full flex items-center gap-3 p-3 rounded-xl
                     text-left transition
                     ${isActive
                       ? 'bg-[#3C52C3]/5 ring-2 ring-[#3C52C3]'
                       : 'ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300'}">
        <img src="${esc(m.photoURL)}" alt=""
             class="w-11 h-11 rounded-full object-cover flex-shrink-0
                    border-2 ${isActive ? 'border-[#3C52C3]' : 'border-slate-200'}">
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-slate-800 truncate">
            ${esc(m.firstName)} ${esc(m.lastName)}
          </p>
          <p class="text-xs text-slate-500 truncate">
            ${esc(m.relationship || role)}
          </p>
        </div>
      </button>
    `;
  }

  function selfRow() {
    if (!self) return '';
    const isActive = String(self.userID) === String(activeMemberID);

    return `
      <button type="button"
              data-member-id="${self.userID}"
              class="member-card w-full flex items-center gap-3 p-3 rounded-xl
                     text-left transition
                     ${isActive
                       ? 'bg-[#3C52C3]/5 ring-2 ring-[#3C52C3]'
                       : 'ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300'}">
        <img src="${esc(self.userPhotoURL || '../../images/default-user.png')}" alt=""
             class="w-11 h-11 rounded-full object-cover flex-shrink-0
                    border-2 ${isActive ? 'border-[#3C52C3]' : 'border-slate-200'}">
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-slate-800 truncate">
            ${esc(self.firstName)} ${esc(self.lastName)}
          </p>
          <p class="text-xs text-slate-500">You</p>
        </div>
      </button>
    `;
  }

  function renderMembers() {
    memberList.innerHTML = selfRow() + members.map(memberRow).join('');
    memberCount.textContent = members.length + (self ? 1 : 0);
    noMembers.classList.toggle('hidden', members.length > 0);

    memberList.querySelectorAll('.member-card').forEach(btn => {
      btn.addEventListener('click', () => {
        activeMemberID = parseInt(btn.dataset.memberId, 10);
        renderMembers();
        updateActiveHeader();
        renderActivity();
      });
    });
  }

  /* ============================================================
     RIGHT: HEADER + FILTERS + LIST
     ============================================================ */
  const activeMemberPhoto = document.getElementById('activeMemberPhoto');
  const activeMemberName  = document.getElementById('activeMemberName');
  const filterFrom        = document.getElementById('filterFrom');
  const filterTo          = document.getElementById('filterTo');
  const filterCategory    = document.getElementById('filterCategory');
  const filterReset       = document.getElementById('filterReset');
  const resultCount       = document.getElementById('resultCount');
  const activityList      = document.getElementById('activityList');
  const noActivity        = document.getElementById('noActivity');

  function getActiveMember() {
    if (self && String(self.userID) === String(activeMemberID)) {
      return {
        userID:    self.userID,
        firstName: self.firstName,
        lastName:  self.lastName,
        photoURL:  self.userPhotoURL || '../../images/default-user.png',
      };
    }
    return members.find(m => String(m.userID) === String(activeMemberID)) || null;
  }

  function updateActiveHeader() {
    const m = getActiveMember();
    if (!m) return;

    activeMemberPhoto.src = m.photoURL;
    activeMemberName.textContent =
      `${m.firstName} ${m.lastName}` +
      (self && String(m.userID) === String(self.userID) ? ' (You)' : '');
  }

  function populateCategoryFilter() {
    const set = new Set();
    allAppts.forEach(a => {
      if (a.category) set.add(a.category);
    });

    const current = filterCategory.value;
    filterCategory.innerHTML = '<option value="all">All categories</option>' +
      [...set].sort().map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');

    if ([...set].includes(current)) filterCategory.value = current;
  }

  function filteredAppointments() {
    const m = getActiveMember();
    if (!m) return [];

    const from = filterFrom.value ? new Date(filterFrom.value + 'T00:00:00').getTime() : null;
    const to   = filterTo.value   ? new Date(filterTo.value   + 'T23:59:59').getTime() : null;
    const cat  = filterCategory.value;

    return allAppts.filter(a => {
      if (String(a.userID) !== String(m.userID)) return false;
      if (cat !== 'all' && a.category !== cat) return false;

      const dt = new Date(a.startDateTime.replace(' ', 'T')).getTime();
      if (from !== null && dt < from) return false;
      if (to   !== null && dt > to)   return false;
      return true;
    });
  }

  function appointmentCard(a) {
    const meta    = statusMeta(a.appointmentStatus);
    const img     = a.serviceImageURL || '../../images/services/placeholder.jpg';
    const dateStr = fmtDate(a.startDateTime);
    const timeStr = fmtTime(a.startDateTime);

    return `
      <article class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden
                      flex flex-col md:flex-row hover:ring-[#3C52C3]/40 transition">

        <div class="md:w-44 flex-shrink-0 bg-slate-100 aspect-[16/10] md:aspect-auto overflow-hidden">
          <img src="${esc(img)}" alt=""
               class="w-full h-full object-cover"
               onerror="this.src='../../images/services/placeholder.jpg'">
        </div>

        <div class="flex-1 p-5">
          <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
            <div class="min-w-0">
              <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400 mb-1">
                ${esc(a.category || 'General')}
              </p>
              <h3 class="text-lg font-bold text-slate-900 leading-snug">
                ${esc(a.serviceName || 'Service')}
              </h3>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1.5
                         text-[11px] font-bold uppercase tracking-[0.1em] ring-1
                         ${colorMap[meta.color]}">
              ${esc(meta.label)}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-x-6 gap-y-3 mt-4">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 mb-1">Date</p>
              <p class="text-sm font-semibold text-slate-800">${esc(dateStr)}</p>
            </div>
            <div>
              <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 mb-1">Time</p>
              <p class="text-sm font-semibold text-slate-800">${esc(timeStr)}</p>
            </div>
            <div class="col-span-2">
              <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 mb-1">Recipient</p>
              <p class="text-sm font-semibold text-slate-800">${esc(a.patientName || '—')}</p>
            </div>
          </div>
        </div>
      </article>
    `;
  }

  function renderActivity() {
    const list = filteredAppointments();

    activityList.innerHTML = list.map(appointmentCard).join('');

    if (list.length === 0) {
      noActivity.classList.remove('hidden');
      resultCount.textContent = '';
    } else {
      noActivity.classList.add('hidden');
      resultCount.textContent =
        `Showing ${list.length} appointment${list.length === 1 ? '' : 's'}`;
    }
  }

  filterFrom.addEventListener('change', renderActivity);
  filterTo.addEventListener('change', renderActivity);
  filterCategory.addEventListener('change', renderActivity);
  filterReset.addEventListener('click', () => {
    filterFrom.value = '';
    filterTo.value = '';
    filterCategory.value = 'all';
    renderActivity();
  });

  /* ============================================================
     MODALS
     ============================================================ */
  const createModal = document.getElementById('createModal');
  const enterModal  = document.getElementById('enterModal');

  function openModal(el) {
    el.classList.remove('hidden');
    el.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }
  function closeModal(el) {
    el.classList.add('hidden');
    el.classList.remove('flex');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.closeModal);
      if (target) closeModal(target);
    });
  });

  [createModal, enterModal].forEach(m => {
    m.addEventListener('click', e => {
      if (e.target === m) closeModal(m);
    });
  });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (!createModal.classList.contains('hidden')) closeModal(createModal);
    if (!enterModal.classList.contains('hidden'))  closeModal(enterModal);
  });

  /* ============================================================
     CREATE CODE
     ============================================================ */
  const codeDisplay = document.getElementById('codeDisplay');
  const codeLoading = document.getElementById('codeLoading');
  const codeValue   = document.getElementById('codeValue');
  const codeExpires = document.getElementById('codeExpires');
  const codeError   = document.getElementById('codeError');
  const btnCopyCode = document.getElementById('btnCopyCode');

  function showExistingCode() {
    codeLoading.classList.add('hidden');
    codeError.classList.add('hidden');
    codeDisplay.classList.remove('hidden');
    codeValue.textContent = initialCode.accessCode;
    codeExpires.textContent = 'Expires ' + new Date(initialCode.codeExpiredTime.replace(' ', 'T'))
      .toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit' });
  }

  document.getElementById('btnCreateCode').addEventListener('click', async () => {
    codeDisplay.classList.add('hidden');
    codeError.classList.add('hidden');
    codeLoading.classList.remove('hidden');
    openModal(createModal);

    // If there's already an active code, reuse it (don't burn a new one)
    if (initialCode && initialCode.accessCode) {
      showExistingCode();
      return;
    }

    try {
      const res = await fetch('familyCareActions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create' }),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Failed to create code');

      codeLoading.classList.add('hidden');
      codeDisplay.classList.remove('hidden');
      codeValue.textContent = json.code;
      codeExpires.textContent = 'Expires ' + new Date(json.expiresAt.replace(' ', 'T'))
        .toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit' });
    } catch (err) {
      codeLoading.classList.add('hidden');
      codeError.textContent = err.message;
      codeError.classList.remove('hidden');
    }
  });

  btnCopyCode.addEventListener('click', () => {
    const text = codeValue.textContent.trim();
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
      btnCopyCode.textContent = 'Copied!';
      setTimeout(() => { btnCopyCode.textContent = 'Copy code'; }, 1500);
    });
  });

  /* ============================================================
     ENTER CODE
     ============================================================ */
  const enterForm   = document.getElementById('enterForm');
  const enterInput  = document.getElementById('enterCodeInput');
  const enterError  = document.getElementById('enterError');
  const btnSubmit   = document.getElementById('btnSubmitCode');

  document.getElementById('btnEnterCode').addEventListener('click', () => {
    enterInput.value = '';
    enterError.classList.add('hidden');
    openModal(enterModal);
    setTimeout(() => enterInput.focus(), 50);
  });

  enterForm.addEventListener('submit', async e => {
    e.preventDefault();
    enterError.classList.add('hidden');

    const code = enterInput.value.trim().toUpperCase();
    if (code.length < 4) {
      enterError.textContent = 'Please enter a valid code.';
      enterError.classList.remove('hidden');
      return;
    }

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Connecting…';

    try {
      const res = await fetch('familyCareActions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'redeem', code }),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Connection failed');

      // Success → reload the page so PHP rebuilds the member list
      window.location.reload();
    } catch (err) {
      enterError.textContent = err.message;
      enterError.classList.remove('hidden');
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Connect';
    }
  });

  /* ============================================================
     INIT
     ============================================================ */
  renderMembers();
  updateActiveHeader();
  populateCategoryFilter();
  renderActivity();

})();