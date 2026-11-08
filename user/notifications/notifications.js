document.addEventListener('DOMContentLoaded', function() {
    const listContainer = document.getElementById('fullNotificationList');
    
    // Auto-refresh every 15 seconds
    setInterval(() => {
        fetch('fetch_notifications.php')
            .then(res => res.text())
            .then(html => {
                // Only update if the user isn't interacting to prevent jarring UI shifts
                listContainer.innerHTML = html;
            });
    }, 15000);

    // Rest of your toggle / filter logic remains the same...
    const filterTabs = document.querySelectorAll('.filter-tab');
    const notifItems = document.querySelectorAll('.notif-item');

    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            filterTabs.forEach(t => {
                t.classList.remove('bg-[#3C52C3]', 'text-white');
                t.classList.add('text-slate-600', 'hover:bg-slate-200');
            });
            this.classList.remove('text-slate-600', 'hover:bg-slate-200');
            this.classList.add('bg-[#3C52C3]', 'text-white');

            const filter = this.dataset.filter;
            document.querySelectorAll('.notif-item').forEach(item => {
                const status = item.dataset.status;
                if (filter === 'all' || status === filter) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        });
    });
});

function markAsReadBeforeNav(id) {
    fetch('/StepCare/user/headerNfooter/header/markNotification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&status=1`
    }).catch(err => console.error(err));
}