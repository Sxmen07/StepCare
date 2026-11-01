document.addEventListener("DOMContentLoaded", () => {
  if (window.lucide) lucide.createIcons();

  const stats = window.statsData;
  const currentService = window.currentServiceData;
  const upcomingSchedules = window.upcomingSchedulesData;

  const summaryContainer = document.getElementById('summary-cards-container');
  const activeContainer = document.getElementById('active-service-container');

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
  // 0. Render Summary Cards
  // ==========================================
  summaryContainer.innerHTML = `
    <!-- Today -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
      <div>
        <p class="text-xs font-bold text-slate-500 mb-1">Today's Appointments</p>
        <h3 class="text-4xl font-black text-slate-900">${stats.today || 0}</h3>
      </div>
      <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
        <i data-lucide="calendar-days" class="w-7 h-7"></i>
      </div>
    </div>

    <!-- Upcoming -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
      <div>
        <p class="text-xs font-bold text-slate-500 mb-1">Upcoming</p>
        <h3 class="text-4xl font-black text-slate-900">${stats.upcoming || 0}</h3>
      </div>
      <div class="w-14 h-14 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500">
        <i data-lucide="clock" class="w-7 h-7"></i>
      </div>
    </div>

    <!-- Completed -->
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-neutral-200/80 flex items-center justify-between">
      <div>
        <p class="text-xs font-bold text-slate-500 mb-1">Completed</p>
        <h3 class="text-4xl font-black text-slate-900">${stats.completed || 0}</h3>
      </div>
      <div class="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center text-[#10B981]">
        <i data-lucide="check-circle" class="w-7 h-7"></i>
      </div>
    </div>
  `;
  if (window.lucide) lucide.createIcons();

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
              <button onclick="window.location.href='appointmentDetails/appointmentDetails.php?id=${currentService.appointmentID}'" 
                      class="flex items-center gap-2 bg-slate-900 hover:bg-black text-white px-6 py-3 rounded-xl font-bold text-sm transition-all shadow-md active:scale-95 cursor-pointer">
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
  // 2. Calendar & Schedule Logic
  // ==========================================
  
  const calendarData = [...upcomingSchedules];
  if (currentService) calendarData.push(currentService);

  const apptsByDate = {};
  calendarData.forEach(appt => {
    const dateKey = appt.startDateTime.split(' ')[0];
    if (!apptsByDate[dateKey]) apptsByDate[dateKey] = [];
    apptsByDate[dateKey].push(appt);
  });

  let currentCalDate = new Date(); 
  let selectedDateObj = new Date(); 

  const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
  
  const calMonthYear = document.getElementById('cal-month-year');
  const calendarDays = document.getElementById('calendar-days');
  const selectedDateTitle = document.getElementById('selected-date-title');
  const dayApptCount = document.getElementById('day-appt-count');
  const dayAppointmentsList = document.getElementById('day-appointments-list');

  const toYYYYMMDD = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  function renderCalendar(direction = null) {
    calMonthYear.textContent = `${monthNames[currentCalDate.getMonth()]} ${currentCalDate.getFullYear()}`;
    calendarDays.innerHTML = '';

    const year = currentCalDate.getFullYear();
    const month = currentCalDate.getMonth();
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    for (let i = 0; i < firstDay; i++) {
      calendarDays.innerHTML += `<div></div>`;
    }

    const todayStr = toYYYYMMDD(new Date());
    const selectedStr = toYYYYMMDD(selectedDateObj);

    for (let i = 1; i <= daysInMonth; i++) {
      const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
      const hasAppt = apptsByDate[dateStr] && apptsByDate[dateStr].length > 0;
      
      let baseClass = "w-9 h-9 mx-auto flex flex-col items-center justify-center rounded-full text-sm font-semibold cursor-pointer transition-colors relative";
      let activeClass = "";
      
      if (dateStr === selectedStr) {
        activeClass = "bg-[#3C52C3] text-white shadow-md";
      } else if (dateStr === todayStr) {
        activeClass = "bg-[#3C52C3]/10 text-[#3C52C3]";
      } else {
        activeClass = "text-slate-700 hover:bg-slate-100";
      }

      let dotHtml = '';
      if (hasAppt) {
        dotHtml = `<span class="absolute bottom-1 w-1 h-1 rounded-full ${dateStr === selectedStr ? 'bg-white' : 'bg-[#3C52C3]'}"></span>`;
      }

      calendarDays.innerHTML += `
        <div class="${baseClass} ${activeClass}" onclick="selectDate('${dateStr}')">
          ${i}
          ${dotHtml}
        </div>
      `;
    }

    // ★ 添加动画效果
    if (direction) {
      calendarDays.classList.remove('calendar-anim-left', 'calendar-anim-right');
      void calendarDays.offsetWidth; // 强制重绘
      if (direction === 'left') calendarDays.classList.add('calendar-anim-left');
      if (direction === 'right') calendarDays.classList.add('calendar-anim-right');
    }
  }

  function renderDayAppointments() {
    const dateStr = toYYYYMMDD(selectedDateObj);
    const todayStr = toYYYYMMDD(new Date());
    
    if (dateStr === todayStr) {
      selectedDateTitle.textContent = "Today's Schedule";
    } else {
      selectedDateTitle.textContent = selectedDateObj.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
    }

    const appts = apptsByDate[dateStr] || [];
    dayApptCount.textContent = `${appts.length} appointment${appts.length === 1 ? '' : 's'}`;

    if (appts.length === 0) {
      dayAppointmentsList.innerHTML = `
        <div class="py-12 text-center flex flex-col items-center">
          <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center mb-3">
            <i data-lucide="calendar-x" class="w-8 h-8 text-slate-300"></i>
          </div>
          <p class="text-sm font-semibold text-slate-600">No appointments scheduled</p>
          <p class="text-xs text-slate-400 mt-1">Enjoy your free time!</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    dayAppointmentsList.innerHTML = appts.map((appt, index) => {
      const isConfirmed = appt.appointmentStatus === 'confirmed';
      const statusColor = isConfirmed ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-amber-50 text-amber-700 border-amber-200';
      const statusLabel = isConfirmed ? 'Confirmed' : 'Pending';

      return `
        <div onclick="window.location.href='appointmentDetails/appointmentDetails.php?id=${appt.appointmentID}'" 
             class="schedule-item bg-white rounded-2xl p-5 flex items-center gap-5 border border-neutral-200/80 hover:border-[#3C52C3]/40 hover:shadow-md transition-all duration-300 group cursor-pointer" style="animation-delay: ${index * 0.05}s">
          
          <!-- Time (Fixed Width, No Box) -->
          <div class="w-20 shrink-0 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Time</span>
            <span class="text-sm font-extrabold text-slate-800 whitespace-nowrap">${formatTime(appt.startDateTime)}</span>
          </div>

          <!-- Vertical Divider -->
          <div class="w-px h-10 bg-slate-200 shrink-0"></div>

          <!-- Details -->
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-bold text-slate-900 truncate">${appt.serviceName}</h4>
            <div class="flex items-center gap-3 mt-1.5 text-xs font-medium text-slate-500">
              <span class="flex items-center gap-1.5"><i data-lucide="user" class="w-3.5 h-3.5"></i> ${appt.firstName} ${appt.lastName}</span>
            </div>
          </div>

          <!-- Status -->
          <div class="shrink-0 pr-1">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider border ${statusColor}">
              ${statusLabel}
            </span>
          </div>
        </div>
      `;
    }).join('');
    
    if (window.lucide) lucide.createIcons();
  }

  window.selectDate = function(dateStr) {
    selectedDateObj = new Date(dateStr + 'T00:00:00'); 
    renderCalendar(); // 点击日期时不触发切换月份的滑动动画，保持平滑更新
    renderDayAppointments();
  };

  document.getElementById('cal-prev').addEventListener('click', () => {
    currentCalDate.setMonth(currentCalDate.getMonth() - 1);
    renderCalendar('left'); // 向左滑动
  });

  document.getElementById('cal-next').addEventListener('click', () => {
    currentCalDate.setMonth(currentCalDate.getMonth() + 1);
    renderCalendar('right'); // 向右滑动
  });

  renderCalendar();
  renderDayAppointments();
});