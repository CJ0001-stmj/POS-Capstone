
const searchBox  = document.getElementById('staffSearch');
const roleFilter = document.getElementById('roleFilter');
const rows = document.querySelectorAll('#staffTable tbody tr');

function applyFilters() {
    const q = searchBox.value.trim().toLowerCase();
    const role = roleFilter.value;
    rows.forEach(row => {
        const matchesSearch = !q || row.dataset.email?.includes(q);
        const matchesRole = !role || row.dataset.role === role;
        row.style.display = (matchesSearch && matchesRole) ? '' : 'none';
    });
}
searchBox?.addEventListener('input', applyFilters);
roleFilter?.addEventListener('change', applyFilters);

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Age preview under the birthdate field — purely informational, the
// real age is calculated server-side from birth_date at render time.
function agePreview(dateStr) {
    if (!dateStr) return '';
    const bd = new Date(dateStr);
    if (isNaN(bd)) return '';
    const now = new Date();
    let age = now.getFullYear() - bd.getFullYear();
    const beforeBirthday = (now.getMonth() < bd.getMonth()) ||
        (now.getMonth() === bd.getMonth() && now.getDate() < bd.getDate());
    if (beforeBirthday) age--;
    return age >= 0 ? `(age ${age})` : '';
}
document.getElementById('addBirthDate')?.addEventListener('input', (e) => {
    document.getElementById('addAgeHint').textContent = agePreview(e.target.value);
});
document.getElementById('editUserBirthDate')?.addEventListener('input', (e) => {
    document.getElementById('editAgeHint').textContent = agePreview(e.target.value);
});

function openEditModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserFullName').value = user.full_name || '';
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserRole').value = user.role;
    document.getElementById('editUserPassword').value = '';
    document.getElementById('editUserBirthDate').value = user.birth_date || '';
    document.getElementById('editAgeHint').textContent = agePreview(user.birth_date || '');
    document.getElementById('editUserPhone').value = user.phone || '';
    document.getElementById('editUserAddress').value = user.address || '';
    document.getElementById('editUserShiftStart').value = user.shift_start ? user.shift_start.slice(0, 5) : '';
    document.getElementById('editUserShiftEnd').value = user.shift_end ? user.shift_end.slice(0, 5) : '';
    document.getElementById('editUserUnlock').checked = false;
    document.getElementById('editUnlockWrap').style.display = user.locked ? 'block' : 'none';
    openModal('editUserModal');
}

let currentActivityUserId = null;

function formatDuration(minutes) {
    if (minutes === null || minutes === undefined) return '—';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h ${m}m`;
}

function renderWarningHistory(warnings) {
    const el = document.getElementById('warningHistory');
    if (!el) return;
    if (!warnings || warnings.length === 0) {
        el.innerHTML = '<p class="empty-note">No warnings sent yet.</p>';
        return;
    }
    el.innerHTML = warnings.map(w => `
        <div class="warning-item">
            <p>${w.message}</p>
            <span>${w.sent_by_email ?? 'Unknown admin'} — ${w.created_at}</span>
        </div>
    `).join('');
}

function openActivityModal(id, email) {
    currentActivityUserId = id;
    document.getElementById('activityEmail').textContent = email;
    document.getElementById('activityBody').innerHTML = '<p class="empty-row">Loading...</p>';
    document.getElementById('activityShiftInfo').innerHTML = '';
    const warningStatus = document.getElementById('warningStatus');
    if (warningStatus) warningStatus.textContent = '';
    const warningMessage = document.getElementById('warningMessage');
    if (warningMessage) warningMessage.value = '';
    openModal('activityModal');

    fetch(`user-access-control.php?ajax=activity&id=${id}`)
        .then(res => res.json())
        .then(data => {
            const body = document.getElementById('activityBody');
            const shiftInfo = document.getElementById('activityShiftInfo');

            if (data.shift_start && data.shift_end) {
                shiftInfo.innerHTML = `<i class="fa-solid fa-clock"></i> Assigned shift: <strong>${formatDuration(data.required_minutes)}</strong> per day`;
            } else {
                shiftInfo.innerHTML = `<i class="fa-solid fa-clock"></i> No shift assigned yet — edit the account to set one.`;
            }

            renderWarningHistory(data.warnings);

            if (data.error || !data.sessions || data.sessions.length === 0) {
                body.innerHTML = '<p class="empty-row">No login activity found.</p>';
                return;
            }
            let html = '<table class="data-table"><thead><tr><th>Time In</th><th>Time Out</th><th>Duration</th><th>IP</th></tr></thead><tbody>';
            data.sessions.forEach(s => {
                const rowClass = s.shortfall ? 'session-short' : '';
                const durationCell = s.time_out
                    ? `${formatDuration(s.duration_minutes)}${s.shortfall ? ' <span class="shortfall-badge">Short</span>' : ''}`
                    : '<em>Ongoing / most recent</em>';
                html += `<tr class="${rowClass}"><td>${s.time_in}</td><td>${s.time_out ?? '<em>Ongoing / most recent</em>'}</td><td>${durationCell}</td><td>${s.ip ?? '—'}</td></tr>`;
            });
            html += '</tbody></table>';
            body.innerHTML = html;
        })
        .catch(() => {
            document.getElementById('activityBody').innerHTML = '<p class="empty-row">Failed to load activity.</p>';
        });
}

function sendWarning() {
    const message = document.getElementById('warningMessage').value.trim();
    const status  = document.getElementById('warningStatus');
    if (!message) {
        status.textContent = 'Write a message first.';
        status.className = 'warning-status warning-status-error';
        return;
    }
    if (!currentActivityUserId) return;

    const btn = document.getElementById('sendWarningBtn');
    btn.disabled = true;
    status.textContent = 'Sending...';
    status.className = 'warning-status';

    const body = new URLSearchParams({
        ajax_action: 'send_warning',
        user_id: currentActivityUserId,
        message: message,
    });

    fetch('user-access-control.php', { method: 'POST', body })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            if (data.ok) {
                document.getElementById('warningMessage').value = '';
                // Refresh just the warning history, so the sessions table
                // doesn't reload and the status message isn't wiped out.
                fetch(`user-access-control.php?ajax=activity&id=${currentActivityUserId}`)
                    .then(r => r.json())
                    .then(d => renderWarningHistory(d.warnings));
                status.textContent = 'Warning sent.';
                status.className = 'warning-status warning-status-ok';
            } else {
                status.textContent = data.error || 'Could not send warning.';
                status.className = 'warning-status warning-status-error';
            }
        })
        .catch(() => {
            btn.disabled = false;
            status.textContent = 'Could not send warning.';
            status.className = 'warning-status warning-status-error';
        });
}

// Close modal on overlay click (not inner box)
document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('open'); });
});