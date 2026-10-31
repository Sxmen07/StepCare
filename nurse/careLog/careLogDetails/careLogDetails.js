// nurse/careLogDetails/careLogDetails.js
document.addEventListener("DOMContentLoaded", () => {
  if (window.lucide) lucide.createIcons();

  const appt = window.appointmentData;
  const timeline = window.timelineData;

  // 1. Populate User & Appt Info
  document.getElementById('header-user-name').textContent = `${appt.firstName} ${appt.lastName}`;
  document.getElementById('header-service-name').textContent = appt.serviceName;

  document.getElementById('det-phone').textContent = appt.phoneNumber || 'N/A';
  document.getElementById('det-email').textContent = appt.email || 'N/A';
  document.getElementById('det-emg-name').textContent = appt.emergencyContactName || 'N/A';
  document.getElementById('det-emg-phone').textContent = appt.emergencyContactPhone || 'N/A';
  document.getElementById('det-address').textContent = appt.locationAddress || 'N/A';
  
  if (appt.specialInstructions) {
    document.getElementById('det-instructions').textContent = appt.specialInstructions;
  }
  
  // Populate Notes
  document.getElementById('nurseNotes').value = appt.nurseNotes || '';

  // 2. Setup Action Button Logic (Sequential State Machine)
  const actionBtn = document.getElementById('actionBtn');
  let latestStatus = 'confirmed'; // Default starting point
  
  if (timeline.length > 0) {
    latestStatus = timeline[timeline.length - 1].status;
  }

  let nextAction = null;
  let btnText = '';

  if (latestStatus === 'confirmed' || latestStatus === 'pending') {
    nextAction = 'on_the_way';
    btnText = 'On The Way';
    actionBtn.className = 'px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-sm active:scale-95 text-white bg-blue-600 hover:bg-blue-700';
  } else if (latestStatus === 'on_the_way') {
    nextAction = 'arrived';
    btnText = 'Arrived';
    actionBtn.className = 'px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-sm active:scale-95 text-white bg-indigo-600 hover:bg-indigo-700';
  } else if (latestStatus === 'arrived') {
    nextAction = 'in_progress';
    btnText = 'Start Service';
    actionBtn.className = 'px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-sm active:scale-95 text-white bg-violet-600 hover:bg-violet-700';
  } else if (latestStatus === 'in_progress') {
    nextAction = 'completed';
    btnText = 'Complete Service';
    actionBtn.className = 'px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-sm active:scale-95 text-white bg-emerald-600 hover:bg-emerald-700';
  }

  if (nextAction) {
    actionBtn.classList.remove('hidden');
    actionBtn.textContent = btnText;
    actionBtn.onclick = () => updateStatus(nextAction, btnText);
  } else {
    actionBtn.classList.remove('hidden');
    actionBtn.textContent = 'Completed';
    actionBtn.className = 'px-5 py-2.5 rounded-xl font-bold text-sm bg-slate-200 text-slate-500 cursor-not-allowed';
    actionBtn.disabled = true;
  }

  // 3. Render Timeline
  function renderTimeline(data) {
    const container = document.getElementById('timeline-container');
    document.getElementById('update-count').textContent = `${data.length} update${data.length === 1 ? '' : 's'}`;
    
    if (data.length === 0) {
      container.innerHTML = '<p class="text-sm text-slate-500 italic pb-4">No activity logged yet.</p>';
      return;
    }

    container.innerHTML = data.map((t, index) => {
      const d = new Date(t.createdAt.replace(' ', 'T'));
      const dateStr = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); // e.g., 31 Oct 2026
      const timeStr = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }); // e.g., 10:53 PM
      
      // Determine dot color (active vs past)
      const isLast = index === data.length - 1;
      
      let dotColor = 'bg-slate-300'; // Default past
      if (isLast) {
        if (t.status === 'completed') dotColor = 'bg-emerald-500 ring-emerald-100';
        else if (t.status === 'in_progress') dotColor = 'bg-violet-500 ring-violet-100';
        else if (t.status === 'arrived') dotColor = 'bg-indigo-500 ring-indigo-100';
        else if (t.status === 'on_the_way') dotColor = 'bg-blue-500 ring-blue-100';
        else dotColor = 'bg-[#3C52C3] ring-indigo-100';
      }

      // Hide connecting line for the last item
      const lineHtml = !isLast ? `<div class="absolute left-[7px] top-6 bottom-[-24px] w-px bg-slate-200"></div>` : '';

      return `
        <div class="relative pl-8">
          ${lineHtml}
          <span class="absolute left-0 top-1.5 w-3.5 h-3.5 rounded-full ${dotColor} ring-4 ring-white z-10"></span>
          
          <div class="flex items-start justify-between gap-4 mb-1">
            <p class="text-sm font-bold text-slate-900">${t.title}</p>
            <div class="text-right shrink-0">
              <p class="text-xs font-bold text-slate-800">${dateStr}</p>
              <p class="text-[11px] text-slate-400">${timeStr}</p>
            </div>
          </div>
          
          <p class="text-sm text-slate-600 leading-relaxed mb-1.5">${t.message}</p>
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">${t.createdBy}</p>
        </div>
      `;
    }).join('');
  }

  renderTimeline(timeline);
});

// ==========================================
// 4. AJAX Actions
// ==========================================

async function updateStatus(status, title) {
  const apptID = window.appointmentData.appointmentID;
  const btn = document.getElementById('actionBtn');
  
  // Save original state
  const origText = btn.textContent;
  const origClass = btn.className;
  
  btn.textContent = 'Updating...';
  btn.disabled = true;
  
  const payload = {
    appointmentID: apptID,
    status: status,
    title: title,
    message: title // Using the button text as the message
  };

  try {
    const res = await fetch('updateActivity.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    
    if (data.success) {
      window.location.reload();
    } else {
      alert(data.message || 'Error updating status');
      btn.textContent = origText;
      btn.className = origClass;
      btn.disabled = false;
    }
  } catch (err) {
    alert('Network error');
    btn.textContent = origText;
    btn.className = origClass;
    btn.disabled = false;
  }
}

// Save Notes
async function saveNotes() {
  const apptID = window.appointmentData.appointmentID;
  const notes = document.getElementById('nurseNotes').value;
  const btn = document.getElementById('saveNotesBtn');

  const origHTML = btn.innerHTML;
  btn.innerHTML = 'Saving...';
  btn.disabled = true;

  try {
    const formData = new FormData();
    formData.append('appointmentID', apptID);
    formData.append('notes', notes);

    const res = await fetch('updateNotes.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    
    if (data.success) {
      btn.innerHTML = 'Saved!';
      setTimeout(() => { btn.innerHTML = origHTML; btn.disabled = false; }, 2000);
    } else {
      alert(data.message || 'Error saving notes');
      btn.innerHTML = origHTML;
      btn.disabled = false;
    }
  } catch (err) {
    alert('Network error');
    btn.innerHTML = origHTML;
    btn.disabled = false;
  }
}