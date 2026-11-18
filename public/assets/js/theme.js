// theme.js – No page reload, uses localStorage, falls back to system preference

// IIFE: Immediately set theme to avoid FOUC (Flash of Unstyled Content)
(function () {
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = saved === 'dark' || (!saved && prefersDark);

    // Apply class to html (for scrollbar) and body (for CSS variables)
    document.documentElement.classList.add(isDark ? 'dark-mode' : 'light-mode');
    if (document.body) {
        document.body.classList.add(isDark ? 'dark-mode' : 'light-mode');
    } else {
        // If body not ready, wait for it
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add(isDark ? 'dark-mode' : 'light-mode');
        });
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('theme-toggle');
    if (!toggleBtn) return;

    // Set initial icon
    const isDark = document.documentElement.classList.contains('dark-mode');
    const icon = toggleBtn.querySelector('i');
    if (icon) {
        if (isDark) {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        } else {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
    }

    toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        // Toggle on both html and body
        document.documentElement.classList.toggle('dark-mode');
        document.documentElement.classList.toggle('light-mode');
        if (document.body) {
            document.body.classList.toggle('dark-mode');
            document.body.classList.toggle('light-mode');
        }
        const isDarkNow = document.documentElement.classList.contains('dark-mode');
        localStorage.setItem('theme', isDarkNow ? 'dark' : 'light');
        // Update icon
        const icon = toggleBtn.querySelector('i');
        if (icon) {
            if (isDarkNow) {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            } else {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            }
        }
    });
});