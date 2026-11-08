document.addEventListener("DOMContentLoaded", () => {
  if (window.lucide) lucide.createIcons();

  const notifications = window.notificationsData || [];
  const listContainer = document.getElementById('notificationList');
  const markAllBtn    = document.getElementById('markAllReadBtn');

  // Helper for auto-refresh topbar
  function checkUnreadCount() {
    fetch('../../headerNfooter/header/notification/get_unread_count.php')
      .then(res => res.json())
      .then(data => {
        // Assuming you have a badge element in your header.php, e.g., id="notificationBadge"
        const badge = document.getElementById('notificationBadge');
        if (badge) {
          if (data.unread > 0) {
            badge.textContent = data.unread;
            badge.classList.remove('hidden');
          } else {
            badge.classList.add('hidden');
          }
        }
      }).catch(e => console.error(e));
  }

  // Poll every 15 seconds
  setInterval(checkUnreadCount, 15000);

  // Time ago formatter
  function timeAgo(dateString) {
    const date = new Date(dateString.replace(' ', 'T'));
    const seconds = Math.floor((new Date() - date) / 1000);
    let interval = Math.floor(seconds / 31536000); if (interval > 1) return interval + " years ago";
    interval = Math.floor(seconds / 2592000); if (interval > 1) return interval + " months ago";
    interval = Math.floor(seconds / 86400); if (interval > 1) return interval + " days ago";
    interval = Math.floor(seconds / 3600); if (interval > 1) return interval + " hours ago";
    interval = Math.floor(seconds / 60); if (interval > 1) return interval + " mins ago";
    return "Just now";
  }

  function renderNotifications() {
    let unreadCount = 0;

    if (notifications.length === 0) {
      listContainer.innerHTML = `
        <div class="bg-white rounded-3xl border border-neutral-200 p-12 text-center shadow-sm">
          <h3 class="text-lg font-bold text-slate-800 mb-1">You're all caught up!</h3>
        </div>
      `;
      markAllBtn.classList.add('hidden');
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
              <h4 class="text-base font-bold text-slate-900 ${isRead ? '' : 'group-hover:text-[#3C52C3]'} transition">${n.title}</h4>
              <p class="text-sm text-slate-600 mt-1 leading-relaxed">${n.message}</p>
              <div class="flex items-center gap-3 mt-3">
                <span class="text-xs font-semibold text-slate-400">${timeAgo(n.createdAt)}</span>
                ${url !== '#' ? `<a href="${url}" onclick="markAsRead(${n.notificationID})" class="text-xs font-bold text-[#3C52C3] hover:underline">View Details</a>` : ''}
              </div>
            </div>
            <button type="button" onclick="toggleRead(${n.notificationID}, ${isRead})" class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-slate-400 hover:text-[#3C52C3] hover:bg-slate-100">
              <i data-lucide="${isRead ? 'mail-open' : 'mail'}" class="w-5 h-5"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');

    markAllBtn.classList.toggle('hidden', unreadCount === 0);
    if (window.lucide) lucide.createIcons();
  }

  window.toggleRead = function(id, currentIsRead) {
    const newStatus = currentIsRead ? 0 : 1;
    fetch('../../headerNfooter/header/markNotification.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}&status=${newStatus}`
    }).then(res => res.json()).then(data => {
      if (data.success) window.location.reload(); 
    });
  };

  window.markAsRead = function(id) {
    fetch('../../headerNfooter/header/markNotification.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}&status=1`
    }).catch(e => console.error(e));
  };

  if (markAllBtn) {
    markAllBtn.addEventListener('click', () => {
      fetch('../../headerNfooter/header/markNotification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_all=1'
      }).then(res => res.json()).then(data => {
        if (data.success) window.location.reload();
      });
    });
  }

  renderNotifications();
});