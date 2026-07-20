// ============ RAM-YUM Orders & Reservations — receivables queue ============
// Orders and reservations are created elsewhere (not this page). This
// page only lists what's pending and lets staff process it:
//   - "Process" -> collect cash, settle it, print a receipt.
//   - "Cancel"  -> restore stock, mark it cancelled.

function formatPeso(n) {
    const num = Number(n) || 0;
    return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

// ---------- tab switching ----------
const queueTabToggle = document.getElementById('queueTabToggle');
const panes = {
    orders: document.getElementById('pane-orders'),
    reservations: document.getElementById('pane-reservations'),
};

queueTabToggle.addEventListener('click', (e) => {
    const btn = e.target.closest('.type-btn');
    if (!btn) return;
    const tab = btn.dataset.tab;

    queueTabToggle.querySelectorAll('.type-btn').forEach((b) => {
        const isActive = b === btn;
        b.classList.toggle('active', isActive);
        b.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    Object.entries(panes).forEach(([name, pane]) => {
        if (pane) pane.style.display = name === tab ? '' : 'none';
    });
});

// ---------- product preview modal ----------
const viewOverlay = document.getElementById('viewOverlay');
const viewRefLabel = document.getElementById('viewRefLabel');
const viewItemsBody = document.getElementById('viewItemsBody');
const viewSubtotalDisplay = document.getElementById('viewSubtotalDisplay');
const viewDiscountRow = document.getElementById('viewDiscountRow');
const viewDiscountDisplay = document.getElementById('viewDiscountDisplay');
const viewPromoLabel = document.getElementById('viewPromoLabel');
const viewTotalDisplay = document.getElementById('viewTotalDisplay');
const viewCloseBtn = document.getElementById('viewCloseBtn');

function openViewModal(row) {
    const items = JSON.parse(row.dataset.items || '[]');
    const discount = Number(row.dataset.discount) || 0;
    const promo = row.dataset.promotion || '';

    viewRefLabel.textContent = row.dataset.ref ? `— ${row.dataset.ref}` : '';
    viewItemsBody.innerHTML = items.length
        ? items.map(it => `
            <tr>
                <td>${escapeHtml(it.product_name)}</td>
                <td>${Number(it.quantity)}</td>
                <td>${formatPeso(it.unit_price)}</td>
                <td>${formatPeso(it.line_total)}</td>
            </tr>`).join('')
        : `<tr class="empty-row"><td colspan="4">No product lines found for this.</td></tr>`;

    viewSubtotalDisplay.textContent = formatPeso(row.dataset.subtotal);
    if (discount > 0) {
        viewPromoLabel.textContent = promo ? promo : 'Discount';
        viewDiscountDisplay.textContent = '-' + formatPeso(discount);
        viewDiscountRow.style.display = 'flex';
    } else {
        viewDiscountRow.style.display = 'none';
    }
    viewTotalDisplay.textContent = formatPeso(row.dataset.total);
    viewOverlay.classList.add('show');
}
viewCloseBtn.addEventListener('click', () => viewOverlay.classList.remove('show'));

// ---------- cash payment modal ----------
const paymentOverlay = document.getElementById('paymentOverlay');
const paymentSubtotalDisplay = document.getElementById('paymentSubtotalDisplay');
const paymentDiscountRow = document.getElementById('paymentDiscountRow');
const paymentDiscountDisplay = document.getElementById('paymentDiscountDisplay');
const paymentPromoLabel = document.getElementById('paymentPromoLabel');
const paymentTotalDisplay = document.getElementById('paymentTotalDisplay');
const amountReceivedEl = document.getElementById('amountReceived');
const changeDueDisplay = document.getElementById('changeDueDisplay');
const paymentShortNote = document.getElementById('paymentShortNote');
const paymentBack = document.getElementById('paymentBack');
const paymentConfirm = document.getElementById('paymentConfirm');

const loadingOverlay = document.getElementById('loadingOverlay');
const loadingLabel = document.getElementById('loadingLabel');
const orderReceiptOverlay = document.getElementById('orderReceiptOverlay');
const orderReceiptPaper = document.getElementById('orderReceiptPaper');

let activeRow = null; // the .queue-row currently being processed
let activeTotal = 0;
let lastSale = null; // most recently completed sale, for the download button

function syncPaymentState() {
    const received = Number(amountReceivedEl.value) || 0;
    const change = Math.round((received - activeTotal) * 100) / 100;
    const short = received < activeTotal;

    changeDueDisplay.textContent = formatPeso(Math.max(0, change));
    paymentShortNote.style.display = (amountReceivedEl.value !== '' && short) ? 'block' : 'none';
    paymentConfirm.disabled = received <= 0 || short;
}
amountReceivedEl.addEventListener('input', syncPaymentState);

function openPaymentModal(row) {
    activeRow = row;
    activeTotal = Number(row.dataset.total) || 0;
    const discount = Number(row.dataset.discount) || 0;
    const promo = row.dataset.promotion || '';

    paymentSubtotalDisplay.textContent = formatPeso(row.dataset.subtotal);
    if (discount > 0) {
        paymentPromoLabel.textContent = promo ? promo : 'Discount';
        paymentDiscountDisplay.textContent = '-' + formatPeso(discount);
        paymentDiscountRow.style.display = 'flex';
    } else {
        paymentDiscountRow.style.display = 'none';
    }
    paymentTotalDisplay.textContent = formatPeso(activeTotal);
    amountReceivedEl.value = '';
    paymentShortNote.style.display = 'none';
    syncPaymentState();
    paymentOverlay.classList.add('show');
}

paymentBack.addEventListener('click', () => paymentOverlay.classList.remove('show'));

document.querySelectorAll('.queue-table').forEach((table) => {
    table.addEventListener('click', (e) => {
        const row = e.target.closest('.queue-row');
        if (!row) return;

        if (e.target.closest('.queue-view-btn')) {
            openViewModal(row);
        } else if (e.target.closest('.queue-process-btn')) {
            openPaymentModal(row);
        } else if (e.target.closest('.queue-cancel-btn')) {
            cancelRow(row);
        }
    });
});

async function callProcessEndpoint(payload) {
    const res = await fetch('orders-process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    return { ok: res.ok, data: await res.json() };
}

async function cancelRow(row) {
    if (!confirm('Cancel this and restore the stock that was set aside for it?')) return;

    row.querySelectorAll('button').forEach((b) => b.disabled = true);
    try {
        const { ok, data } = await callProcessEndpoint({
            type: row.dataset.type,
            id: row.dataset.id,
            action: 'cancel',
        });
        if (!ok || !data.ok) {
            alert(data.message || 'Could not cancel this.');
            row.querySelectorAll('button').forEach((b) => b.disabled = false);
            return;
        }
        removeRowAndUpdateCount(row);
    } catch (err) {
        alert("⚠️ Couldn't reach the server. Please try again.");
        row.querySelectorAll('button').forEach((b) => b.disabled = false);
    }
}

paymentConfirm.addEventListener('click', async () => {
    if (!activeRow) return;
    paymentOverlay.classList.remove('show');
    loadingLabel.textContent = 'Processing payment...';
    loadingOverlay.classList.add('show');

    try {
        const { ok, data } = await callProcessEndpoint({
            type: activeRow.dataset.type,
            id: activeRow.dataset.id,
            action: 'complete',
            amount_received: Number(amountReceivedEl.value) || 0,
        });
        loadingOverlay.classList.remove('show');

        if (!ok || !data.ok) {
            alert(`⚠️ ${data.message || 'Could not process this payment. Please try again.'}`);
            return;
        }

        lastSale = data.sale;
        renderReceipt(data.sale);
        orderReceiptOverlay.classList.add('show');
        removeRowAndUpdateCount(activeRow);
        activeRow = null;
    } catch (err) {
        loadingOverlay.classList.remove('show');
        alert("⚠️ Couldn't reach the server. Please try again.");
    }
});

function removeRowAndUpdateCount(row) {
    const type = row.dataset.type;
    const tab = type === 'order' ? 'orders' : 'reservations';
    const table = row.closest('table');
    row.remove();

    const remaining = table.querySelectorAll('.queue-row').length;
    if (remaining === 0) {
        const cell = type === 'order' ? 6 : 6;
        const tbody = table.querySelector('tbody');
        const emptyRow = document.createElement('tr');
        emptyRow.className = 'empty-row';
        emptyRow.innerHTML = `<td colspan="${cell}">Nothing waiting to be processed right now.</td>`;
        tbody.appendChild(emptyRow);
    }

    const countEl = queueTabToggle.querySelector(`.type-btn[data-tab="${tab}"] .q-count`);
    if (countEl) countEl.textContent = Math.max(0, Number(countEl.textContent) - 1);
}

function renderReceipt(sale) {
    const itemsHtml = sale.items.map(it => `
        <div class="r-line">
            <span>${escapeHtml(it.name)} x${it.quantity}</span>
            <span>${formatPeso(it.line_total)}</span>
        </div>
    `).join('');

    orderReceiptPaper.innerHTML = `
        <div class="r-center r-title">RAM-YUM STORE</div>
        <div class="r-center">Official Receipt</div>
        <hr>
        <div class="r-line"><span>Receipt No.</span><span>${escapeHtml(sale.receipt_no)}</span></div>
        <div class="r-line"><span>Date</span><span>${escapeHtml(sale.created_at)}</span></div>
        <div class="r-line"><span>Cashier</span><span>${escapeHtml(sale.cashier_email)}</span></div>
        <hr>
        ${itemsHtml}
        <hr>
        <div class="r-line"><span>Subtotal</span><span>${formatPeso(sale.subtotal)}</span></div>
        ${sale.discount > 0 ? `<div class="r-line"><span>${sale.promotion_name ? escapeHtml(sale.promotion_name) : 'Discount'}</span><span>-${formatPeso(sale.discount)}</span></div>` : ''}
        <div class="r-line r-total"><span>TOTAL</span><span>${formatPeso(sale.total)}</span></div>
        <div class="r-line"><span>Payment (Cash)</span><span>${formatPeso(sale.amount_received)}</span></div>
        <div class="r-line"><span>Change</span><span>${formatPeso(sale.change_due)}</span></div>
        <hr>
        <div class="r-center">Thank you for shopping at RAM-YUM!</div>
    `;
}

document.getElementById('printReceiptBtn').addEventListener('click', () => window.print());
document.getElementById('closeReceiptBtn').addEventListener('click', () => {
    orderReceiptOverlay.classList.remove('show');
});

function buildReceiptText(sale) {
    const lines = [];
    lines.push('RAM-YUM STORE');
    lines.push('Official Receipt');
    lines.push('----------------------------------------');
    lines.push(`Receipt No.: ${sale.receipt_no}`);
    lines.push(`Date:        ${sale.created_at}`);
    lines.push(`Cashier:     ${sale.cashier_email}`);
    lines.push('----------------------------------------');
    sale.items.forEach(it => {
        lines.push(`${it.name} x${it.quantity}`.padEnd(30) + formatPeso(it.line_total));
    });
    lines.push('----------------------------------------');
    lines.push('Subtotal'.padEnd(30) + formatPeso(sale.subtotal));
    if (sale.discount > 0) {
        lines.push((sale.promotion_name ? sale.promotion_name : 'Discount').padEnd(30) + '-' + formatPeso(sale.discount));
    }
    lines.push('TOTAL'.padEnd(30) + formatPeso(sale.total));
    lines.push('Payment (Cash)'.padEnd(30) + formatPeso(sale.amount_received));
    lines.push('Change'.padEnd(30) + formatPeso(sale.change_due));
    lines.push('----------------------------------------');
    lines.push('Thank you for shopping at RAM-YUM!');
    return lines.join('\n');
}

document.getElementById('downloadReceiptBtn').addEventListener('click', () => {
    if (!lastSale) return;
    const blob = new Blob([buildReceiptText(lastSale)], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `receipt-${lastSale.receipt_no}.txt`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
});