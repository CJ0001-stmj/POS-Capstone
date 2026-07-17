// ============ Receipt History: expandable receipt detail rows ============
function toggleReceiptRow(row) {
    const targetId = row.getAttribute('data-target');
    const detailRow = document.getElementById(targetId);
    if (!detailRow) return;

    const isOpen = detailRow.style.display !== 'none';
    detailRow.style.display = isOpen ? 'none' : 'table-row';
    row.classList.toggle('receipt-row-open', !isOpen);
}

document.querySelectorAll('.receipt-row').forEach((row) => {
    row.addEventListener('click', () => toggleReceiptRow(row));
});

// Landed here via a "View in Receipt History" link right after checkout —
// open that specific receipt automatically and scroll it into view.
if (window.RAMYUM_AUTO_OPEN_RECEIPT) {
    const match = document.querySelector(`.receipt-row[data-receipt="${CSS.escape(window.RAMYUM_AUTO_OPEN_RECEIPT)}"]`);
    if (match) {
        toggleReceiptRow(match);
        match.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}