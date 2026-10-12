document.addEventListener('DOMContentLoaded', function () {
    const toggle   = document.getElementById('profileToggle');
    const dropdown = document.getElementById('profileDropdown');
    const menu     = document.getElementById('profileMenu');

    // User is not logged in → these elements don't exist → do nothing.
    if (!toggle || !dropdown) return;

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();                       // don't bubble to document
        dropdown.classList.toggle('hidden');
    });

    // Close when clicking outside the menu
    document.addEventListener('click', function (e) {
        if (menu && !menu.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') dropdown.classList.add('hidden');
    });
});