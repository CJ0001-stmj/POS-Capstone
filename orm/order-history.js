// ============ Purchase History (Reservations + Orders) ============

// ---------- tab switching ----------
const historyTabToggle = document.getElementById('historyTabToggle');
const panes = {
    reservations: document.getElementById('pane-reservations'),
    orders: document.getElementById('pane-orders'),
};

historyTabToggle?.addEventListener('click', (e) => {
    const btn = e.target.closest('.type-btn');
    if (!btn) return;
    const tab = btn.dataset.tab;

    historyTabToggle.querySelectorAll('.type-btn').forEach((b) => {
        const isActive = b === btn;
        b.classList.toggle('active', isActive);
        b.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    Object.entries(panes).forEach(([name, pane]) => {
        if (pane) pane.style.display = name === tab ? '' : 'none';
    });

    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    history.replaceState(null, '', url);
});

// ---------- full-detail modal ----------
const DETAILS = window.RAMYUM_HISTORY_DETAILS || {};

function formatPeso(n) {
    const num = Number(n) || 0;
    return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

const histModalOverlay = document.getElementById('histModalOverlay');
const histModalTitle = document.getElementById('histModalTitle');
const histModalStatus = document.getElementById('histModalStatus');
const histModalMeta = document.getElementById('histModalMeta');
const histModalItems = document.getElementById('histModalItems');
const histModalTotals = document.getElementById('histModalTotals');
const histModalClose = document.getElementById('histModalClose');

function openHistModal(key) {
    const d = DETAILS[key];
    if (!d) return;

    const itemsHtml = (d.items || []).map((it) => `
        <div class="m-row">
            <span class="m-name">${escapeHtml(it.name)}<span class="m-qty">x${it.quantity}</span></span>
            <span class="m-unit">${formatPeso(it.unit_price)} ea</span>
            <span class="m-total">${formatPeso(it.line_total)}</span>
        </div>
    `).join('') || '<p class="hist-empty-note">No line items on record.</p>';

    if (d.kind === 'reservation') {
        histModalTitle.innerHTML = `<i class="fa-solid fa-clock"></i> Reservation ${escapeHtml(d.reservation_no)}`;
        histModalStatus.textContent = d.status.charAt(0).toUpperCase() + d.status.slice(1);
        histModalStatus.className = `status-badge status-${d.status}`;
        histModalStatus.style.display = 'inline-block';

        const metaRows = [
            ['Customer', d.customer_name],
            ['Contact', d.customer_contact],
            ['Notes', d.notes],
            ['Staff', d.staff_email],
            ['Reserved on', d.created_at],
            ['Fulfilled', d.fulfilled_at],
            ['Cancelled', d.cancelled_at],
        ].filter(([, v]) => v);
        histModalMeta.innerHTML = metaRows.map(([label, val]) => `
            <div class="r-row"><span>${escapeHtml(label)}</span><strong>${escapeHtml(val)}</strong></div>
        `).join('');

        histModalItems.innerHTML = itemsHtml;

        let totalsHtml = `<div class="r-row"><span>Subtotal</span><strong>${formatPeso(d.subtotal)}</strong></div>`;
        if (d.discount > 0) {
            totalsHtml += `<div class="r-row"><span>${escapeHtml(d.promotion_name || 'Discount')}</span><strong>-${formatPeso(d.discount)}</strong></div>`;
        }
        totalsHtml += `<div class="r-row r-total"><span>Total</span><strong>${formatPeso(d.total)}</strong></div>`;

        if (d.sale) {
            const saleItemsHtml = (d.sale.items || []).map((it) => `
                <div class="m-row">
                    <span class="m-name">${escapeHtml(it.name)}<span class="m-qty">x${it.quantity}</span></span>
                    <span class="m-unit">${formatPeso(it.unit_price)} ea</span>
                    <span class="m-total">${formatPeso(it.line_total)}</span>
                </div>
            `).join('') || '<p class="hist-empty-note">No line items on record.</p>';

            totalsHtml += `
                <div class="hist-modal-sale">
                    <div class="hist-modal-sale-head">
                        <span><i class="fa-solid fa-receipt"></i> Transaction Created</span>
                        <span class="hist-sale-receipt">${escapeHtml(d.sale.receipt_no)}</span>
                    </div>
                    <div class="r-row"><span>Date &amp; time</span><strong>${escapeHtml(d.sale.created_at)}</strong></div>
                    <div class="hist-modal-items">${saleItemsHtml}</div>
                    <div class="r-row r-total"><span>Amount collected</span><strong>${formatPeso(d.sale.total)}</strong></div>
                </div>
            `;
        }

        histModalTotals.innerHTML = totalsHtml;
    } else {
        histModalTitle.innerHTML = `<i class="fa-solid fa-receipt"></i> Receipt ${escapeHtml(d.receipt_no)}`;
        histModalStatus.style.display = 'none';

        histModalMeta.innerHTML = `<div class="r-row"><span>Date &amp; time</span><strong>${escapeHtml(d.created_at)}</strong></div>`;
        histModalItems.innerHTML = itemsHtml;
        histModalTotals.innerHTML = `<div class="r-row r-total"><span>Total</span><strong>${formatPeso(d.total)}</strong></div>`;
    }

    histModalOverlay.classList.add('show');
}

function closeHistModal() {
    histModalOverlay.classList.remove('show');
}

document.body.addEventListener('click', (e) => {
    const trigger = e.target.closest('.hist-row, .view-btn');
    if (!trigger) return;
    openHistModal(trigger.dataset.detailKey);
});

histModalClose?.addEventListener('click', closeHistModal);
histModalOverlay?.addEventListener('click', (e) => {
    if (e.target === histModalOverlay) closeHistModal();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeHistModal();
});