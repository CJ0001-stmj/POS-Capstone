// Notification bell — open/close dropdown, switch between concern /
// time-in-out / top-products tabs. Pairs with notif-bell.php (markup) and
// the .notif-* rules already in dashboard.css.
function initNotifBell() {
    const bell = document.getElementById('notifBell');
    const dropdown = document.getElementById('notifDropdown');
    if (!bell || !dropdown) return;

    bell.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        bell.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== bell) {
            dropdown.classList.remove('open');
            bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.querySelectorAll('.notif-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const target = tab.dataset.tab;
            document.querySelectorAll('.notif-pane').forEach((pane) => {
                pane.style.display = pane.dataset.pane === target ? '' : 'none';
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', initNotifBell);
