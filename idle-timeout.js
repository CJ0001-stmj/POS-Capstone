// Idle session timeout — logs user out after inactivity, warns first
// with a styled modal (see idle-timeout.css). IDLE_LIMIT_MS must match
// $SESSION_IDLE_LIMIT in dashboard.php.
(function () {
    const IDLE_LIMIT_MS = 2 * 60 * 1000; // 10 min
    const WARN_BEFORE_MS = 15 * 1000;     // show modal 1 min before logout
    let idleTimer, warnTimer, countdownTimer;
    let overlay, countdownEl;

    function logout() {
        window.location.href = '/logout.php?timeout=1';
    }

    function buildModal() {
        overlay = document.createElement('div');
        overlay.className = 'idle-overlay';
        overlay.innerHTML = `
            <div class="idle-modal" role="alertdialog" aria-modal="true" aria-labelledby="idleTitle">
                <div class="idle-modal-icon"><i class="fa-solid fa-clock"></i></div>
                <h3 id="idleTitle">Still there?</h3>
                <p>You've been inactive. You'll be logged out in</p>
                <span class="idle-modal-countdown" id="idleCountdown">60s</span>
                <div class="idle-modal-actions">
                    <button type="button" class="idle-btn idle-btn-stay" id="idleStayBtn">Stay logged in</button>
                    <button type="button" class="idle-btn idle-btn-logout" id="idleLogoutBtn">Log out now</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        countdownEl = overlay.querySelector('#idleCountdown');
        overlay.querySelector('#idleStayBtn').addEventListener('click', () => {
            hideModal();
            resetTimers();
        });
        overlay.querySelector('#idleLogoutBtn').addEventListener('click', logout);
    }

    function showModal() {
        if (!overlay) buildModal();
        let secondsLeft = Math.round(WARN_BEFORE_MS / 1000);
        countdownEl.textContent = secondsLeft + 's';
        overlay.classList.add('open');
        countdownTimer = setInterval(() => {
            secondsLeft -= 1;
            countdownEl.textContent = Math.max(secondsLeft, 0) + 's';
            if (secondsLeft <= 0) clearInterval(countdownTimer);
        }, 1000);
    }

    function hideModal() {
        if (overlay) overlay.classList.remove('open');
        clearInterval(countdownTimer);
    }

    function resetTimers() {
        clearTimeout(idleTimer);
        clearTimeout(warnTimer);
        clearInterval(countdownTimer);
        warnTimer = setTimeout(showModal, IDLE_LIMIT_MS - WARN_BEFORE_MS);
        idleTimer = setTimeout(logout, IDLE_LIMIT_MS);
    }

    function onActivity() {
        // Ignore activity while the warning modal is up — only the modal
        // buttons should cancel it, so a stray mouse twitch doesn't dodge
        // the countdown the user is meant to see.
        if (overlay && overlay.classList.contains('open')) return;
        resetTimers();
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(evt =>
        document.addEventListener(evt, onActivity, { passive: true })
    );

    resetTimers();
})();