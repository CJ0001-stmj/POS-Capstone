// ============ Shared sidebar behavior (all pages) ============

// Mobile menu toggle
document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
});

// Submenu expand/collapse (e.g. Point of Sale > Stock Monitoring / Receipt History)
document.querySelectorAll('.has-submenu > .submenu-toggle').forEach((toggle) => {
    toggle.addEventListener('click', (e) => {
        e.preventDefault();
        const parentLi = toggle.closest('.has-submenu');
        const isOpen = parentLi.classList.contains('submenu-open');

        // Close any other open submenus so only one is expanded at a time.
        document.querySelectorAll('.has-submenu.submenu-open').forEach((li) => {
            if (li !== parentLi) li.classList.remove('submenu-open');
        });

        parentLi.classList.toggle('submenu-open', !isOpen);
    });
});
