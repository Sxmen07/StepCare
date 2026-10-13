// dashboard.js
document.addEventListener("DOMContentLoaded", function () {
    'use strict';

    // 1. Initialize Lucide Icons
    if (window.lucide) {
        lucide.createIcons();
    }

    // 2. Highlight Active Sidebar Link
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        // Check if the current URL matches the link's href
        if (currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('bg-indigo-500/10', 'text-indigo-600');
            link.classList.remove('text-slate-700');
        }
    });

    // 3. Optional: Log dashboard data to console
    if (window.dashboardData) {
        console.log("Dashboard Data Loaded:", window.dashboardData);
    }
});