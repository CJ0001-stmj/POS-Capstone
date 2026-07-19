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

// ---------- cash payment modal ----------
const paymentOverlay = document.getElementById('paymentOverlay');
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

        if (e.target.closest('.queue-process-btn')) {
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