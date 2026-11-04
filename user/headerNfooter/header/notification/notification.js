// StepCare/headerNfooter/header/notification/notification.js
document.addEventListener("DOMContentLoaded", () => {
  if (window.lucide) lucide.createIcons();

  const notifications = window.notificationsData || [];
  const listContainer = document.getElementById('notificationList');
  const markAllBtn    = document.getElementById('markAllReadBtn');

  // Time ago formatter
  function timeAgo(dateString) {
    const date = new Date(dateString.replace(' ', 'T'));
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval > 1) return interval + " years ago";
    interval = Math.floor(seconds / 2592000);
    if (interval > 1) return interval + " months ago";
    interval = Math.floor(seconds / 86400);
    if (interval > 1) return interval + " days ago";
    interval = Math.floor(seconds / 3600);
    if (interval > 1) return interval + " hours ago";
    interval = Math.floor(seconds / 60);
    if (interval > 1) return interval + " mins ago";
    return "Just now";
  }

  function renderNotifications() {
    let unreadCount = 0;

    if (notifications.length === 0) {
      listContainer.innerHTML = `
        <div class="bg-white rounded-3xl border border-neutral-200 p-12 text-center shadow-sm">
          <div class="w-20 h-20 mx-auto bg-slate-50 rounded-full flex items-center justify-center mb-4">
            <i data-lucide="bell-off" class="w-10 h-10 text-slate-300"></i>
          </div>
          <h3 class="text-lg font-bold text-slate-800 mb-1">You're all caught up!</h3>
          <p class="text-slate-500">You have no notifications right now.</p>
        </div>
      `;
      markAllBtn.classList.add('hidden');
      if (window.lucide) lucide.createIcons();
      return;
    }

    listContainer.innerHTML = notifications.map(n => {
      const isRead = parseInt(n.isRead) === 1;
      if (!isRead) unreadCount++;

      const bgClass = isRead ? 'bg-white' : 'bg-blue-50/50 border-blue-100';
      const dotHtml = isRead ? '' : `<span class="absolute left-4 top-6 w-2.5 h-2.5 bg-[#3C52C3] rounded-full ring-4 ring-white"></span>`;
      const url = n.linkURL || '#';

      return `
        <div class="relative ${bgClass} border border-neutral-200 rounded-2xl p-5 pl-10 shadow-sm hover:shadow-md transition group" id="notif-${n.notificationID}">
          ${dotHtml}
          
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <h4 class="text-base font-bold text-slate-900 ${isRead ? '' : 'group-hover:text-[#3C52C3]'} transition">
                ${n.title}
              </h4>
              <p class="text-sm text-slate-600 mt-1 leading-relaxed">
                ${n.message}
              </p>
              <div class="flex items-center gap-3 mt-3">
                <span class="text-xs font-semibold text-slate-400">${timeAgo(n.createdAt)}</span>
                
                ${url !== '#' ? `
                  <a href="${url}" onclick="markAsRead(${n.notificationID})" class="text-xs font-bold text-[#3C52C3] hover:underline flex items-center gap-1">
                    View Details <i data-lucide="arrow-right" class="w-3 h-3"></i>
                  </a>
                ` : ''}
              </div>
            </div>

            <button type="button" onclick="toggleRead(${n.notificationID}, ${isRead})" class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-slate-400 hover:text-[#3C52C3] hover:bg-slate-100 transition" title="${isRead ? 'Mark as unread' : 'Mark as read'}">
              ${isRead 
                ? `<i data-lucide="mail-open" class="w-5 h-5"></i>` 
                : `<i data-lucide="mail" class="w-5 h-5"></i>`}
            </button>
          </div>
        </div>
      `;
    }).join('');

    if (unreadCount > 0) {
      markAllBtn.classList.remove('hidden');
    } else {
      markAllBtn.classList.add('hidden');
    }

    if (window.lucide) lucide.createIcons();
  }

  // Expose functions to window for inline onclick
  window.toggleRead = function(id, currentIsRead) {
    const newStatus = currentIsRead ? 0 : 1;
    fetch('../../../user/headerNfooter/header/markNotification.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}&status=${newStatus}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        window.location.reload(); // Quickest way to refresh the UI state
      }
    });
  };

  window.markAsRead = function(id) {
    // Silently marks as read before navigating
    fetch('../../../user/headerNfooter/header/markNotification.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}&status=1`
    }).catch(e => console.error(e));
  };

  if (markAllBtn) {
    markAllBtn.addEventListener('click', () => {
      fetch('../../../user/headerNfooter/header/markNotification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_all=1'
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) window.location.reload();
      });
    });
  }

  renderNotifications();
});