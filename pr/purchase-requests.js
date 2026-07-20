// ============ Purchase Requests ============

document.addEventListener('DOMContentLoaded', () => {

    // ---------- Review queue: approve / reject ----------
    document.querySelectorAll('.pr-approve-btn').forEach((btn) => {
        btn.addEventListener('click', () => handleReview(btn, 'approve'));
    });
    document.querySelectorAll('.pr-reject-btn').forEach((btn) => {
        btn.addEventListener('click', () => handleReview(btn, 'reject'));
    });

    async function handleReview(btn, action) {
        const row = btn.closest('.pr-row');
        if (!row || row.classList.contains('processing')) return;

        const requestId = row.dataset.requestId;
        const approveBtn = row.querySelector('.pr-approve-btn');
        const rejectBtn = row.querySelector('.pr-reject-btn');

        // Inline processing indicator: buttons disable, spinner replaces
        // their icon, while we wait on inventory's response.
        row.classList.add('processing');
        approveBtn.disabled = true;
        rejectBtn.disabled = true;

        try {
            const res = await fetch('purchase-request-action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ request_id: requestId, action }),
            });
            const data = await res.json();

            row.classList.remove('processing');

            if (!data.ok) {
                approveBtn.disabled = false;
                rejectBtn.disabled = false;
                alert(data.message || 'Could not process this request.');
                return;
            }

            applyResolvedStatus(requestId, data.status, data.reviewed_by);
        } catch (err) {
            row.classList.remove('processing');
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
            alert('Network error — please try again.');
        }
    }

    function applyResolvedStatus(requestId, status, reviewedBy) {
        // Pull the row out of the pending queue entirely - it's resolved.
        const pendingRow = document.querySelector(`#pendingTable .pr-row[data-request-id="${requestId}"]`);
        if (pendingRow) {
            pendingRow.remove();
            const remaining = document.querySelectorAll('#pendingTable .pr-row').length;
            const emptyRow = document.getElementById('pendingEmptyRow');
            if (emptyRow) emptyRow.style.display = remaining === 0 ? '' : 'none';
        }

        // Update the matching row in the full history table.
        const histRow = document.querySelector(`#historyTableBody .pr-hist-row[data-request-id="${requestId}"]`);
        if (histRow) {
            histRow.classList.remove('pr-hist-status-pending');
            histRow.classList.add(`pr-hist-status-${status}`);
            const badgeCell = histRow.querySelector('.pr-status-cell');
            if (badgeCell) {
                badgeCell.innerHTML = status === 'approved'
                    ? '<span class="pr-badge pr-badge-approved"><i class="fa-solid fa-check"></i> Approved</span>'
                    : '<span class="pr-badge pr-badge-rejected"><i class="fa-solid fa-xmark"></i> Rejected</span>';
            }
            const cells = histRow.querySelectorAll('td');
            const reviewedCell = cells[cells.length - 1];
            if (reviewedCell) reviewedCell.textContent = reviewedBy.split('@')[0];
        }
    }
});