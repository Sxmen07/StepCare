document.addEventListener("DOMContentLoaded", () => {
  if (window.lucide) lucide.createIcons();

  const currentService = window.currentServiceData;
  const upcomingSchedules = window.upcomingSchedulesData;

  const activeContainer = document.getElementById('active-service-container');
  const upcomingContainer = document.getElementById('upcoming-schedules-container');

  // Format Helpers
  const formatDate = (dtStr) => {
    const d = new Date(dtStr.replace(' ', 'T'));
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
  };
  const formatTime = (dtStr) => {
    const d = new Date(dtStr.replace(' ', 'T'));
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  };

  // ==========================================
  // 1. Render Active/Current Service
  // ==========================================
  if (!currentService) {
    activeContainer.innerHTML = `
      <div class="bg-white rounded-3xl p-10 flex flex-col items-center justify-center text-center shadow-sm border border-neutral-200/60 min-h-[250px]">
        <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center mb-4">
          <i data-lucide="coffee" class="w-8 h-8 text-slate-400"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-800">No active services right now</h3>
        <p class="text-sm text-slate-500 mt-1">You have no confirmed appointments scheduled for today.</p>
      </div>
    `;
    if (window.lucide) lucide.createIcons();
  } else {
    // Determine status color
    const isInProgress = currentService.appointmentStatus === 'in_progress';
    const statusBadge = isInProgress 
      ? `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold uppercase tracking-wider border border-indigo-200"><span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span> In Progress</span>`
      : `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-bold uppercase tracking-wider border border-blue-200"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Confirmed</span>`;

    activeContainer.innerHTML = `
      <div class="bg-white rounded-[2rem] shadow-sm border border-neutral-200/80 overflow-hidden relative group transition-all duration-300 hover:shadow-md">
        
        <!-- Top accent bar -->
        <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-[#3C52C3] to-[#10B981]"></div>

        <div class="grid grid-cols-1 lg:grid-cols-12">
          
          <!-- LEFT: User Information (5 cols) -->
          <div class="lg:col-span-5 p-8 lg:pr-10 flex flex-col justify-center">
            
            <div class="flex items-center gap-4 mb-6">
              <div class="w-14 h-14 rounded-full bg-[#3C52C3]/10 flex items-center justify-center text-[#3C52C3] font-black text-xl">
                ${currentService.firstName.charAt(0)}
              </div>
              <div>
                <div class="flex items-center gap-3 mb-1">
                  <h2 class="text-2xl font-black text-slate-900">${currentService.firstName} ${currentService.lastName}</h2>
                  ${statusBadge}
                </div>
                <p class="text-sm font-semibold text-slate-500 capitalize">${currentService.gender || 'Not specified'}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="bg-neutral-50 rounded-xl p-4 border border-neutral-100">
                <div class="flex items-center gap-1.5 text-xs font-bold text-neutral-400 uppercase tracking-wider mb-1">
                  <i data-lucide="phone" class="w-3.5 h-3.5"></i> Phone
                </div>
                <p class="text-sm font-bold text-slate-800">${currentService.phoneNumber || 'N/A'}</p>
              </div>
              
              <div class="bg-rose-50/50 rounded-xl p-4 border border-rose-100/50">
                <div class="flex items-center gap-1.5 text-xs font-bold text-rose-400 uppercase tracking-wider mb-1">
                  <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i> Emergency
                </div>
                <p class="text-sm font-bold text-slate-800">${currentService.emergencyContactName || 'N/A'}</p>
                <p class="text-xs font-semibold text-rose-600 mt-0.5">${currentService.emergencyContactPhone || 'N/A'}</p>
              </div>
            </div>
          </div>

          <!-- Vertical Divider -->
          <div class="hidden lg:block col-span-1 w-px bg-neutral-100 my-8"></div>
          <div class="lg:hidden h-px bg-neutral-100 mx-8"></div>

          <!-- RIGHT: Service Information (6 cols) -->
          <div class="lg:col-span-6 p-8 lg:pl-10 flex flex-col justify-between">
            
            <div>
              <div class="flex justify-between items-start mb-4">
                <div>
                  <p class="text-xs font-bold text-[#3C52C3] uppercase tracking-wider mb-1">${currentService.category}</p>
                  <h3 class="text-xl font-extrabold text-slate-800">${currentService.serviceName}</h3>
                </div>
              </div>

              <div class="flex items-start gap-3 mt-6">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-[#10B981] shrink-0 mt-1">
                  <i data-lucide="map-pin" class="w-5 h-5"></i>
                </div>
                <div>
                  <p class="text-sm font-bold text-slate-800">${currentService.locationAddress}</p>
                  <div class="flex items-center gap-4 mt-2">
                    <p class="text-sm font-semibold text-slate-600 flex items-center gap-1.5">
                      <i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i>
                      ${formatDate(currentService.startDateTime)}
                    </p>
                    <p class="text-sm font-semibold text-slate-600 flex items-center gap-1.5">
                      <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                      ${formatTime(currentService.startDateTime)} - ${formatTime(currentService.endDateTime)}
                    </p>
                  </div>
                </div>
              </div>

              ${currentService.specialInstructions ? `
                <div class="mt-6 bg-amber-50 rounded-xl p-4 border border-amber-100">
                  <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">Special Instructions</p>
                  <p class="text-sm text-amber-900 font-medium leading-relaxed">${currentService.specialInstructions}</p>
                </div>
              ` : ''}
            </div>

            <div class="mt-8 flex justify-end">
              <button class="flex items-center gap-2 bg-slate-900 hover:bg-black text-white px-6 py-3 rounded-xl font-bold text-sm transition-all shadow-md active:scale-95 cursor-pointer">
                Start Session <i data-lucide="arrow-right" class="w-4 h-4"></i>
              </button>
            </div>

          </div>
        </div>
      </div>
    `;
    if (window.lucide) lucide.createIcons();
  }

  // ==========================================
  // 2. Render Upcoming Schedules
  // ==========================================
  if (upcomingSchedules.length === 0) {
    upcomingContainer.innerHTML = `
      <div class="bg-white rounded-2xl p-8 border border-neutral-200/60 flex items-center justify-center">
        <p class="text-sm text-neutral-500 font-medium">No upcoming schedules found.</p>
      </div>
    `;
  } else {
    let html = `
      <div class="flex items-center justify-between px-2 mb-2">
        <h3 class="text-lg font-bold text-slate-800">Upcoming Schedules</h3>
        <span class="text-xs font-semibold text-slate-400">${upcomingSchedules.length} appointments</span>
      </div>
      <div class="flex flex-col gap-3">
    `;

    upcomingSchedules.forEach((appt, index) => {
      const dateObj = new Date(appt.startDateTime.replace(' ', 'T'));
      const month = dateObj.toLocaleString('en-US', { month: 'short' });
      const day = dateObj.getDate();
      
      const statusColor = appt.appointmentStatus === 'confirmed' 
        ? 'bg-blue-50 text-blue-700 border-blue-200' 
        : 'bg-amber-50 text-amber-700 border-amber-200';
      
      const statusLabel = appt.appointmentStatus === 'confirmed' ? 'Confirmed' : 'Pending';

      html += `
        <div class="schedule-item bg-white rounded-2xl p-4 flex flex-col md:flex-row items-center gap-5 border border-neutral-200/80 hover:border-[#3C52C3]/30 hover:shadow-md transition-all duration-300 group cursor-pointer" style="animation-delay: ${index * 0.05}s">
          
          <!-- Date Badge -->
          <div class="w-16 h-16 rounded-xl bg-slate-50 border border-slate-100 flex flex-col items-center justify-center shrink-0 group-hover:bg-[#3C52C3]/5 group-hover:border-[#3C52C3]/20 transition-colors">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${month}</span>
            <span class="text-xl font-black text-slate-800 leading-none mt-0.5">${day}</span>
          </div>

          <!-- Service & Patient Info -->
          <div class="flex-1 min-w-0 text-center md:text-left">
            <h4 class="text-base font-bold text-slate-900 truncate">${appt.serviceName}</h4>
            <div class="flex items-center justify-center md:justify-start gap-3 mt-1 text-xs font-medium text-slate-500">
              <span class="flex items-center gap-1"><i data-lucide="user" class="w-3.5 h-3.5"></i> ${appt.firstName} ${appt.lastName}</span>
              <span class="w-1 h-1 rounded-full bg-slate-300"></span>
              <span class="flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i> ${formatTime(appt.startDateTime)}</span>
            </div>
          </div>

          <!-- Status Badge -->
          <div class="shrink-0">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider border ${statusColor}">
              ${statusLabel}
            </span>
          </div>

        </div>
      `;
    });

    html += `</div>`;
    upcomingContainer.innerHTML = html;
    if (window.lucide) lucide.createIcons();
  }
});