// user/familyCare/familyCare.js
(function () {
  'use strict';

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
  let isEditMode = false;
  let selectedConnIDs = new Set();

  // SVG Icons for JS swapping
  const iconEdit = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>`;
  const iconDone = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>`;

  /* ============================================================
     LEFT: MEMBER LIST
     ============================================================ */
  const memberList  = document.getElementById('memberList');
  const memberCount = document.getElementById('memberCount');
  const noMembers   = document.getElementById('noMembers');
  const btnEditMembers = document.getElementById('btnEditMembers');
  const editToolbar = document.getElementById('editToolbar');

  function memberRow(m) {
    const isActive = String(m.userID) === String(activeMemberID);
    const role = m.iAm === 'owner' ? 'Can view you' : 'You can view';
    const isChecked = selectedConnIDs.has(String(m.connectionID));
    const isPinned = m.isPinned == 1;

    return `
      <div class="relative group">
        <button type="button"
                data-member-id="${m.userID}"
                data-conn-id="${m.connectionID}"
                class="member-card w-full flex items-center gap-3 p-3 rounded-xl
                       text-left transition pr-10
                       ${isActive && !isEditMode
                         ? 'bg-[#3C52C3]/5 ring-2 ring-[#3C52C3]'
                         : isChecked && isEditMode
                           ? 'bg-blue-50 ring-2 ring-blue-500'
                           : 'ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300'}">
          
          ${isEditMode ? `
            <div class="flex-shrink-0">
              <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition
                          ${isChecked ? 'bg-blue-500 border-blue-500 text-white' : 'border-slate-300 bg-white'}">
                ${isChecked ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' : ''}
              </div>
            </div>
          ` : ''}

          <div class="relative flex-shrink-0">
            <img src="${esc(m.photoURL)}" alt=""
                 class="w-11 h-11 rounded-full object-cover border-2 ${isActive && !isEditMode ? 'border-[#3C52C3]' : 'border-slate-200'}">
            ${isPinned ? `
              <div class="absolute -top-1 -right-1 bg-white rounded-full p-0.5 shadow-sm ring-1 ring-slate-200">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-amber-500">
                  <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z" clip-rule="evenodd" />
                </svg>
              </div>
            ` : ''}
          </div>

          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-slate-800 truncate">
              ${esc(m.firstName)} ${esc(m.lastName)}
            </p>
            <p class="text-xs text-slate-500 truncate">
              ${esc(m.relationship || role)}
            </p>
          </div>
        </button>
      </div>
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
                     ${isActive && !isEditMode
                       ? 'bg-[#3C52C3]/5 ring-2 ring-[#3C52C3]'
                       : 'ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300'}">
        <img src="${esc(self.userPhotoURL || '../../images/default-user.png')}" alt=""
             class="w-11 h-11 rounded-full object-cover flex-shrink-0
                    border-2 ${isActive && !isEditMode ? 'border-[#3C52C3]' : 'border-slate-200'}">
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
  }

  /* ============================================================
     EDIT MODE TOGGLE
     ============================================================ */
  btnEditMembers.addEventListener('click', () => {
    isEditMode = !isEditMode;
    selectedConnIDs.clear(); // Reset selections
    
    if (isEditMode) {
      btnEditMembers.innerHTML = iconDone;
      btnEditMembers.title = 'Done Editing';
      btnEditMembers.classList.replace('text-[#3C52C3]', 'text-emerald-600');
      btnEditMembers.classList.replace('hover:text-[#2f41a0]', 'hover:text-emerald-700');
      btnEditMembers.classList.replace('hover:bg-blue-50', 'hover:bg-emerald-50');
      editToolbar.classList.remove('hidden');
    } else {
      btnEditMembers.innerHTML = iconEdit;
      btnEditMembers.title = 'Edit Members';
      btnEditMembers.classList.replace('text-emerald-600', 'text-[#3C52C3]');
      btnEditMembers.classList.replace('hover:text-emerald-700', 'hover:text-[#2f41a0]');
      btnEditMembers.classList.replace('hover:bg-emerald-50', 'hover:bg-blue-50');
      editToolbar.classList.add('hidden');
    }
    
    renderMembers();
  });

  /* ============================================================
     MEMBER LIST CLICK HANDLER
     ============================================================ */
  memberList.addEventListener('click', (e) => {
    const card = e.target.closest('.member-card');
    if (!card) return;

    const userID = card.dataset.memberId;
    const connID = card.dataset.connId;

    // If in Edit Mode, toggle selection (Only allow selecting actual members, not self)
    if (isEditMode) {
      if (!connID) return; // Prevent selecting yourself

      if (selectedConnIDs.has(connID)) {
        selectedConnIDs.delete(connID);
      } else {
        selectedConnIDs.add(connID);
      }
      renderMembers();
      return;
    }

    // Normal Mode: View activity
    activeMemberID = parseInt(userID, 10);
    renderMembers();
    updateActiveHeader();
    renderActivity();
  });

  /* ============================================================
     PIN & DELETE ACTIONS
     ============================================================ */
  document.getElementById('btnPinSelected').addEventListener('click', async () => {
    if (selectedConnIDs.size === 0) {
      alert('Please select at least one family member to pin.');
      return;
    }

    try {
      const res = await fetch('familyCareActions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'pin', connectionIDs: Array.from(selectedConnIDs) })
      });
      const json = await res.json();
      if (json.ok) {
        window.location.reload(); // Reload to reflect new sort order
      } else {
        alert(json.message || 'Failed to pin.');
      }
    } catch (err) {
      alert('Network error.');
    }
  });

  // DELETE FLOW
  const deleteConfirmModal = document.getElementById('deleteConfirmModal');
  const btnConfirmDelete   = document.getElementById('btnConfirmDelete');
  const btnCancelDelete    = document.getElementById('btnCancelDelete');
  let deleteCountdown      = null;

  document.getElementById('btnDeleteSelected').addEventListener('click', () => {
    if (selectedConnIDs.size === 0) {
      alert('Please select at least one family member to delete.');
      return;
    }

    btnConfirmDelete.disabled = true;
    btnConfirmDelete.textContent = 'Wait 5s...';
    btnConfirmDelete.className = 'w-full rounded-xl bg-slate-300 py-3.5 text-sm font-bold text-slate-500 transition cursor-not-allowed';
    
    deleteConfirmModal.classList.remove('hidden');
    deleteConfirmModal.classList.add('flex');

    let seconds = 5;
    clearInterval(deleteCountdown);
    deleteCountdown = setInterval(() => {
      seconds--;
      if (seconds > 0) {
        btnConfirmDelete.textContent = `Wait ${seconds}s...`;
      } else {
        clearInterval(deleteCountdown);
        btnConfirmDelete.disabled = false;
        btnConfirmDelete.textContent = 'Yes, Remove Member';
        btnConfirmDelete.className = 'w-full rounded-xl bg-red-600 py-3.5 text-sm font-bold text-white hover:bg-red-700 active:scale-95 transition';
      }
    }, 1000);
  });

  function closeDeleteModal() {
    clearInterval(deleteCountdown);
    deleteConfirmModal.classList.add('hidden');
    deleteConfirmModal.classList.remove('flex');
  }

  btnCancelDelete.addEventListener('click', closeDeleteModal);

  btnConfirmDelete.addEventListener('click', async () => {
    btnConfirmDelete.disabled = true;
    btnConfirmDelete.textContent = 'Removing...';

    try {
      const res = await fetch('familyCareActions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', connectionIDs: Array.from(selectedConnIDs) })
      });
      const json = await res.json();
      
      if (json.ok) {
        window.location.reload(); // Reload to apply deletions cleanly
      } else {
        alert(json.message || 'Failed to delete.');
        closeDeleteModal();
        btnConfirmDelete.disabled = false;
      }
    } catch (err) {
      alert('Network error.');
      closeDeleteModal();
      btnConfirmDelete.disabled = false;
    }
  });

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
      <article class="appt-detail-trigger bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden
                      flex flex-col md:flex-row hover:ring-[#3C52C3]/40 hover:-translate-y-0.5 transition duration-200 cursor-pointer"
               data-appt-id="${a.appointmentID}">

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
     APPOINTMENT DETAIL MODAL LOGIC
     ============================================================ */
  const apptDetailModal = document.getElementById('apptDetailModal');
  const closeApptDetail = document.getElementById('closeApptDetail');
  
  const detUserName  = document.getElementById('detUserName');
  const detUserEmail = document.getElementById('detUserEmail');
  const detUserPhone = document.getElementById('detUserPhone');
  
  const detPatient   = document.getElementById('detPatient');
  const detAddress   = document.getElementById('detAddress');

  const detServiceName = document.getElementById('detServiceName');
  const detCategory    = document.getElementById('detCategory');
  const detStatus      = document.getElementById('detStatus');
  
  const detDate = document.getElementById('detDate');
  const detTime = document.getElementById('detTime');
  const detPrice = document.getElementById('detPrice');
  const detUnit = document.getElementById('detUnit');
  const detRef = document.getElementById('detRef');
  
  const detInstructionsWrapper = document.getElementById('detInstructionsWrapper');
  const detInstructions = document.getElementById('detInstructions');

  const detTimeline = document.getElementById('detTimeline');
  const detTimelineCount = document.getElementById('detTimelineCount');
  const detNurseContent = document.getElementById('detNurseContent');

  function openApptDetail(a) {
    const meta = statusMeta(a.appointmentStatus);

    detUserName.textContent  = `${a.userFirstName || ''} ${a.userLastName || ''}`.trim() || '—';
    detUserEmail.textContent = a.userEmail || '—';
    detUserPhone.textContent = a.userPhone || '—';
    
    detPatient.textContent = a.patientName || '—';
    detAddress.textContent = a.locationAddress || '—';

    detServiceName.textContent = a.serviceName || 'Service';
    detCategory.textContent    = a.category || 'General';
    
    detStatus.textContent = meta.label;
    detStatus.className   = `inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.1em] ring-1 shrink-0 ${colorMap[meta.color]}`;

    detDate.textContent  = fmtDate(a.startDateTime);
    detTime.textContent  = fmtTime(a.startDateTime);
    detPrice.innerHTML   = `RM${Number(a.price).toFixed(2)}`;
    detUnit.textContent  = `/${a.price_unit || 'visit'}`;
    detRef.textContent   = a.ref;

    if (a.specialInstructions && a.specialInstructions.trim() !== '') {
      detInstructionsWrapper.classList.remove('hidden');
      detInstructions.innerHTML = a.specialInstructions.replace(/\n/g, '<br>');
    } else {
      detInstructionsWrapper.classList.add('hidden');
    }

    detTimelineCount.textContent = `${a.timeline.length} update${a.timeline.length === 1 ? '' : 's'}`;
    detTimeline.innerHTML = '';
    
    if (a.timeline && a.timeline.length > 0) {
      const lastIdx = a.timeline.length - 1;
      a.timeline.forEach((t, i) => {
        const isLast = (i === lastIdx);
        const dotColor = statusMeta(t.status).color;
        const colorClass = colorMap[dotColor].split(' ')[0].replace('-100', '-500');
        
        const tDate = fmtDate(t.createdAt).replace(/^\w+, /, '');
        const tTime = fmtTime(t.createdAt);

        detTimeline.innerHTML += `
          <div class="relative ${isLast ? '' : 'pb-8'}">
            <span class="absolute -left-[26px] top-1.5 w-3.5 h-3.5 rounded-full
                         ${isLast ? `${colorClass} ring-4 ring-${dotColor}-500/20` : 'bg-white border-2 border-slate-300'}"></span>
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-slate-800 leading-snug">${esc(t.title || 'Update')}</p>
                ${t.message ? `<p class="text-sm text-slate-600 mt-1 leading-relaxed">${esc(t.message)}</p>` : ''}
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mt-1.5">
                  ${esc((t.createdBy || 'system').charAt(0).toUpperCase() + (t.createdBy || 'system').slice(1))}
                </p>
              </div>
              <div class="flex-shrink-0 text-left sm:text-right sm:pl-6">
                <p class="text-xs font-bold text-slate-700">${tDate}</p>
                <p class="text-xs text-slate-400">${tTime}</p>
              </div>
            </div>
          </div>
        `;
      });
    }

    if (a.nurseID) {
      const nurseImg = a.nursePhoto || `https://ui-avatars.com/api/?name=${encodeURIComponent(a.nurseFirstName + ' ' + a.nurseLastName)}&background=3C52C3&color=fff&size=200`;
      const ratingHtml = a.nurseRating ? `<p class="mt-2 text-xs font-semibold text-amber-500">★ ${Number(a.nurseRating).toFixed(1)} rating</p>` : '';
      
      detNurseContent.innerHTML = `
        <div class="flex flex-col items-center text-center">
          <img src="${esc(nurseImg)}" alt="Nurse"
               class="w-24 h-24 rounded-full object-cover shadow-md border-4 border-white bg-slate-100"
               onerror="this.src='https://ui-avatars.com/api/?name=Nurse&background=3C52C3&color=fff&size=200'">
          <h3 class="mt-4 text-lg font-extrabold text-slate-800">
            ${esc(a.nurseFirstName)} ${esc(a.nurseLastName)}
          </h3>
          <p class="mt-1 text-sm font-semibold text-[#3C52C3]">
            ${esc(a.nurseSpecialization || 'General Nurse')}
          </p>
          ${ratingHtml}
          <div class="w-full mt-6 pt-5 border-t border-slate-100 space-y-3 text-left">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 mb-1">Contact</p>
              <a href="tel:${esc(a.nursePhone)}" class="text-sm font-semibold text-slate-800 hover:text-[#3C52C3] transition break-all">
                ${esc(a.nursePhone || '—')}
              </a>
            </div>
            <div>
              <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 mb-1">Specialization</p>
              <p class="text-sm font-semibold text-slate-800">${esc(a.nurseSpecialization || '—')}</p>
            </div>
          </div>
        </div>
      `;
    } else {
      detNurseContent.innerHTML = `
        <div class="text-center py-10">
          <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/></svg>
          </div>
          <p class="text-sm font-bold text-slate-700">Awaiting assignment</p>
          <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
            A licensed nurse will accept your booking shortly.<br>You'll receive an email once assigned.
          </p>
        </div>
      `;
    }

    apptDetailModal.classList.remove('hidden');
    apptDetailModal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function closeDetailModal() {
    apptDetailModal.classList.add('hidden');
    apptDetailModal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  activityList.addEventListener('click', (e) => {
    const card = e.target.closest('.appt-detail-trigger');
    if (!card) return;
    
    const id = card.dataset.apptId;
    const appt = allAppts.find(a => String(a.appointmentID) === String(id));
    if (appt) openApptDetail(appt);
  });

  if (closeApptDetail) {
    closeApptDetail.addEventListener('click', closeDetailModal);
  }

  if (apptDetailModal) {
    apptDetailModal.addEventListener('click', (e) => {
      if (e.target === apptDetailModal) closeDetailModal();
    });
  }

  /* ============================================================
     OTHER MODALS (Create / Enter)
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
    if (!apptDetailModal.classList.contains('hidden')) closeDetailModal();
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