// user/notifications/notifications.js

document.addEventListener('DOMContentLoaded', function() {
    
    // ==========================================
    // 1. FILTER LOGIC
    // ==========================================
    const filterTabs = document.querySelectorAll('.filter-tab');
    const notifItems = document.querySelectorAll('.notif-item');

    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab styling
            filterTabs.forEach(t => {
                t.classList.remove('bg-[#3C52C3]', 'text-white');
                t.classList.add('text-slate-600', 'hover:bg-slate-200');
            });
            this.classList.remove('text-slate-600', 'hover:bg-slate-200');
            this.classList.add('bg-[#3C52C3]', 'text-white');

            const filter = this.dataset.filter;

            // Show/Hide items
            notifItems.forEach(item => {
                const status = item.dataset.status;
                if (filter === 'all' || status === filter) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        });
    });

    // ==========================================
    // 2. TOGGLE READ/UNREAD LOGIC
    // ==========================================
    const markAllBtn = document.getElementById('markAllReadBtn');

    function updateNotificationStatus(id, status, callback) {
        fetch('/StepCare/user/headerNfooter/header/markNotification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&status=${status}`
        })
        .then(res => res.json())
        .then(data => { if (data.success && callback) callback(); })
        .catch(err => console.error('Error:', err));
    }

    document.querySelectorAll('.notif-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const currentVal = parseInt(this.dataset.isread);
            const newVal = currentVal === 1 ? 0 : 1;

            updateNotificationStatus(id, newVal, () => {
                this.dataset.isread = newVal;
                const row = document.getElementById(`notif-row-${id}`);
                const unreadDot = row.querySelector('.unread-dot');
                const readDot = row.querySelector('.read-dot');

                if (newVal === 1) {
                    row.dataset.status = 'read';
                    row.classList.remove('bg-blue-50/50', 'border-blue-100');
                    row.classList.add('bg-white');
                    unreadDot.classList.add('hidden');
                    readDot.classList.remove('hidden');
                    this.title = 'Mark as unread';
                    this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98v7.491z" /></svg>';
                } else {
                    row.dataset.status = 'unread';
                    row.classList.remove('bg-white');
                    row.classList.add('bg-blue-50/50', 'border-blue-100');
                    unreadDot.classList.remove('hidden');
                    readDot.classList.add('hidden');
                    this.title = 'Mark as read';
                    this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>';
                }
            });
        });
    });

    // ==========================================
    // 3. MARK ALL READ LOGIC
    // ==========================================
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fetch('/StepCare/user/headerNfooter/header/markNotification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_all=1'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notif-item').forEach(row => {
                        row.dataset.status = 'read';
                        row.classList.remove('bg-blue-50/50', 'border-blue-100');
                        row.classList.add('bg-white');
                        row.querySelector('.unread-dot').classList.add('hidden');
                        row.querySelector('.read-dot').classList.remove('hidden');
                    });
                    document.querySelectorAll('.notif-toggle-btn').forEach(btn => {
                        btn.dataset.isread = 1;
                        btn.title = 'Mark as unread';
                        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98v7.491z" /></svg>';
                    });
                    markAllBtn.remove();
                }
            });
        });
    }
});

// Helper function for clicking the main link (marks as read without waiting)
function markAsReadBeforeNav(id) {
    fetch('/StepCare/user/headerNfooter/header/markNotification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&status=1`
    }).catch(err => console.error(err));
}