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

  // 2. Render Timeline
  function renderTimeline(data) {
    const container = document.getElementById('timeline-container');
    
    if (data.length === 0) {
      container.innerHTML = '<p class="text-sm text-slate-500 italic">No activity logged yet.</p>';
      return;
    }

    container.innerHTML = data.map(t => {
      const d = new Date(t.createdAt.replace(' ', 'T'));
      const dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      const timeStr = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
      
      let dotColor = 'bg-slate-300';
      if (t.status === 'completed') dotColor = 'bg-emerald-500';
      else if (t.status === 'in_progress') dotColor = 'bg-violet-500';
      else if (t.status === 'arrived') dotColor = 'bg-indigo-500';
      else if (t.status === 'on_the_way') dotColor = 'bg-blue-500';

      return `
        <div class="relative">
          <span class="absolute -left-6 top-1 w-3 h-3 rounded-full ${dotColor} ring-4 ring-white"></span>
          <div>
            <div class="flex items-center justify-between mb-1">
              <p class="text-sm font-bold text-slate-800">${t.title}</p>
              <p class="text-[11px] font-semibold text-slate-400">${dateStr} at ${timeStr}</p>
            </div>
            <p class="text-sm text-slate-600 leading-relaxed">${t.message}</p>
          </div>
        </div>
      `;
    }).join('');
    
    if (window.lucide) lucide.createIcons();
  }

  renderTimeline(timeline);
});

// ==========================================
// 3. AJAX Actions
// ==========================================

// Update Activity Status
async function updateStatus(status, defaultMessage) {
  const apptID = window.appointmentData.appointmentID;
  
  const payload = {
    appointmentID: apptID,
    status: status,
    title: defaultMessage,
    message: defaultMessage
  };

  try {
    const res = await fetch('updateActivity.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    
    if (data.success) {
      // Reload page to reflect new timeline and top status
      window.location.reload();
    } else {
      alert(data.message || 'Error updating status');
    }
  } catch (err) {
    alert('Network error');
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