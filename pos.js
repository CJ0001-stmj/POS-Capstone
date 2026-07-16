// ============ RAM-YUM POS ============
// Depends on:
//   window.RAMYUM_PRODUCTS  (injected by pos.php - source of truth for price/stock)
//   jsPDF (loaded via CDN in pos.php) for the downloadable receipt

const PRODUCTS = window.RAMYUM_PRODUCTS || [];
const PRODUCTS_BY_ID = new Map(PRODUCTS.map(p => [Number(p.id), p]));
const CHECKOUT_API_URL = 'pos_checkout.php';

// cart: Map<productId, {id, name, sku, price, stock_qty, qty}>
const cart = new Map();
let lastReceipt = null;

// ---------- helpers ----------
function formatPeso(n) {
    const num = Number(n) || 0;
    return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function cartSubtotal() {
    let sum = 0;
    for (const line of cart.values()) sum += line.price * line.qty;
    return Math.round(sum * 100) / 100;
}

// ---------- product grid: search + category filter ----------
const productGrid = document.getElementById('productGrid');
const productSearch = document.getElementById('productSearch');
const categoryTabs = document.getElementById('categoryTabs');
const noResults = document.getElementById('noResults');
let activeCategory = 'all';

function applyGridFilter() {
    const term = (productSearch.value || '').trim().toLowerCase();
    let visibleCount = 0;

    productGrid.querySelectorAll('.pos-product-card').forEach(card => {
        const matchesCategory = activeCategory === 'all' || card.dataset.category === activeCategory;
        const name = (card.dataset.name || '').toLowerCase();
        const sku = (card.dataset.sku || '').toLowerCase();
        const matchesSearch = !term || name.includes(term) || sku.includes(term);
        const show = matchesCategory && matchesSearch;
        card.classList.toggle('hidden', !show);
        if (show) visibleCount++;
    });

    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
}

categoryTabs.addEventListener('click', (e) => {
    const tab = e.target.closest('.pos-tab');
    if (!tab) return;
    categoryTabs.querySelectorAll('.pos-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    activeCategory = tab.dataset.category;
    applyGridFilter();
});

productSearch.addEventListener('input', applyGridFilter);

// ---------- add to cart ----------
function addToCart(id, qtyToAdd = 1) {
    const product = PRODUCTS_BY_ID.get(Number(id));
    if (!product) return;
    if (Number(product.stock_qty) <= 0) return;

    const existing = cart.get(product.id);
    const currentQty = existing ? existing.qty : 0;
    const nextQty = Math.min(currentQty + qtyToAdd, Number(product.stock_qty));

    if (nextQty <= 0) return;

    cart.set(Number(product.id), {
        id: Number(product.id),
        name: product.name,
        sku: product.sku,
        price: Number(product.price),
        stock_qty: Number(product.stock_qty),
        qty: nextQty,
    });

    renderCart();
}

function setLineQty(id, qty) {
    const line = cart.get(Number(id));
    if (!line) return;
    if (qty <= 0) {
        cart.delete(Number(id));
    } else {
        line.qty = Math.min(qty, line.stock_qty);
        cart.set(Number(id), line);
    }
    renderCart();
}

function removeLine(id) {
    cart.delete(Number(id));
    renderCart();
}

// Product grid clicks (event delegation - grid is re-filtered, not rebuilt)
productGrid.addEventListener('click', (e) => {
    const card = e.target.closest('.pos-product-card');
    if (!card || card.disabled) return;
    addToCart(card.dataset.id, 1);
});

// Best-seller widget: click a row to quick-add
document.querySelectorAll('.pos-best-row[data-add-id]').forEach(row => {
    row.addEventListener('click', () => addToCart(row.dataset.addId, 1));
});

// ---------- cart rendering ----------
const cartItemsEl = document.getElementById('cartItems');
const cartEmptyNote = document.getElementById('cartEmptyNote');
const sumSubtotalEl = document.getElementById('sumSubtotal');
const sumTotalEl = document.getElementById('sumTotal');
const sumChangeEl = document.getElementById('sumChange');
const paymentMethodEl = document.getElementById('paymentMethod');
const amountReceivedEl = document.getElementById('amountReceived');
const checkoutBtn = document.getElementById('checkoutBtn');
const clearCartBtn = document.getElementById('clearCartBtn');

function renderCart() {
    cartItemsEl.innerHTML = '';

    if (cart.size === 0) {
        cartEmptyNote.style.display = 'block';
        cartItemsEl.appendChild(cartEmptyNote);
    } else {
        cartEmptyNote.style.display = 'none';
        for (const line of cart.values()) {
            const row = document.createElement('div');
            row.className = 'pos-cart-line';
            row.dataset.id = line.id;
            row.innerHTML = `
                <div class="line-info">
                    <div class="line-name">${escapeHtml(line.name)}</div>
                    <div class="line-price">${formatPeso(line.price)} each</div>
                </div>
                <div class="pos-qty-stepper">
                    <button type="button" class="qty-minus" aria-label="Decrease quantity">-</button>
                    <span>${line.qty}</span>
                    <button type="button" class="qty-plus" aria-label="Increase quantity">+</button>
                </div>
                <div class="line-total">${formatPeso(line.price * line.qty)}</div>
                <button type="button" class="line-remove" aria-label="Remove item"><i class="fa-solid fa-xmark"></i></button>
            `;
            cartItemsEl.appendChild(row);
        }
    }

    const subtotal = cartSubtotal();
    const total = subtotal; // no discount logic yet - matches server (discount = 0)
    sumSubtotalEl.textContent = formatPeso(subtotal);
    sumTotalEl.textContent = formatPeso(total);

    syncPaymentAndChange();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

cartItemsEl.addEventListener('click', (e) => {
    const row = e.target.closest('.pos-cart-line');
    if (!row) return;
    const id = row.dataset.id;
    const line = cart.get(Number(id));
    if (!line) return;

    if (e.target.closest('.qty-plus')) {
        setLineQty(id, line.qty + 1);
    } else if (e.target.closest('.qty-minus')) {
        setLineQty(id, line.qty - 1);
    } else if (e.target.closest('.line-remove')) {
        removeLine(id);
    }
});

clearCartBtn.addEventListener('click', () => {
    if (cart.size === 0) return;
    if (confirm('Clear the current order?')) {
        cart.clear();
        renderCart();
    }
});

// ---------- payment / change ----------
function currentTotal() {
    return cartSubtotal();
}

function syncPaymentAndChange() {
    const total = currentTotal();
    const isCash = paymentMethodEl.value === 'cash';

    if (!isCash) {
        amountReceivedEl.value = total.toFixed(2);
        amountReceivedEl.disabled = true;
    } else {
        amountReceivedEl.disabled = false;
    }

    const received = parseFloat(amountReceivedEl.value) || 0;
    const change = isCash ? Math.max(0, received - total) : 0;
    sumChangeEl.textContent = formatPeso(change);

    const hasItems = cart.size > 0;
    const enoughReceived = isCash ? received >= total && total > 0 : total > 0;
    checkoutBtn.disabled = !(hasItems && enoughReceived);
}

paymentMethodEl.addEventListener('change', syncPaymentAndChange);
amountReceivedEl.addEventListener('input', syncPaymentAndChange);

// ---------- checkout: confirm modal ----------
const confirmOverlay = document.getElementById('confirmOverlay');
const confirmItems = document.getElementById('confirmItems');
const confirmSubtotal = document.getElementById('confirmSubtotal');
const confirmTotal = document.getElementById('confirmTotal');
const confirmReceived = document.getElementById('confirmReceived');
const confirmChange = document.getElementById('confirmChange');
const confirmBack = document.getElementById('confirmBack');
const confirmProceed = document.getElementById('confirmProceed');

checkoutBtn.addEventListener('click', () => {
    if (cart.size === 0) return;

    const total = currentTotal();
    const received = paymentMethodEl.value === 'cash' ? (parseFloat(amountReceivedEl.value) || 0) : total;
    const change = paymentMethodEl.value === 'cash' ? Math.max(0, received - total) : 0;

    confirmItems.innerHTML = '';
    for (const line of cart.values()) {
        const row = document.createElement('div');
        row.className = 'm-row';
        row.innerHTML = `
            <span class="m-name">${escapeHtml(line.name)}<span class="m-qty">x${line.qty}</span></span>
            <span class="m-total">${formatPeso(line.price * line.qty)}</span>
        `;
        confirmItems.appendChild(row);
    }

    confirmSubtotal.textContent = formatPeso(total);
    confirmTotal.textContent = formatPeso(total);
    confirmReceived.textContent = formatPeso(received);
    confirmChange.textContent = formatPeso(change);

    confirmOverlay.classList.add('show');
});

confirmBack.addEventListener('click', () => confirmOverlay.classList.remove('show'));

// ---------- checkout: submit ----------
const loadingOverlay = document.getElementById('loadingOverlay');
const receiptOverlay = document.getElementById('receiptOverlay');
const receiptPaper = document.getElementById('receiptPaper');

confirmProceed.addEventListener('click', async () => {
    confirmOverlay.classList.remove('show');
    loadingOverlay.classList.add('show');

    const payload = {
        cart: Array.from(cart.values()).map(l => ({ product_id: l.id, quantity: l.qty })),
        amount_received: paymentMethodEl.value === 'cash' ? (parseFloat(amountReceivedEl.value) || 0) : currentTotal(),
        payment_method: paymentMethodEl.value,
    };

    try {
        const response = await fetch(CHECKOUT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();

        loadingOverlay.classList.remove('show');

        if (!response.ok || !data.ok) {
            alert(`⚠️ ${data.message || 'Checkout failed. Please try again.'}`);
            return;
        }

        lastReceipt = data.receipt;
        renderReceipt(lastReceipt);
        receiptOverlay.classList.add('show');

        // Reset the working order now that the sale is committed server-side.
        cart.clear();
        amountReceivedEl.value = '';
        paymentMethodEl.value = 'cash';
        renderCart();
    } catch (err) {
        loadingOverlay.classList.remove('show');
        alert("⚠️ Couldn't reach the server. Please try again.");
        console.error('Checkout request failed:', err);
    }
});

function renderReceipt(receipt) {
    const itemsHtml = receipt.items.map(it => `
        <div class="r-line">
            <span>${escapeHtml(it.name)} x${it.quantity}</span>
            <span>${formatPeso(it.line_total)}</span>
        </div>
    `).join('');

    receiptPaper.innerHTML = `
        <div class="r-center r-title">RAM-YUM STORE</div>
        <div class="r-center">Korean &amp; Japanese Store</div>
        <hr>
        <div class="r-line"><span>Receipt No.</span><span>${escapeHtml(receipt.receipt_no)}</span></div>
        <div class="r-line"><span>Date</span><span>${escapeHtml(receipt.created_at)}</span></div>
        <div class="r-line"><span>Cashier</span><span>${escapeHtml(receipt.cashier_email)}</span></div>
        <hr>
        ${itemsHtml}
        <hr>
        <div class="r-line"><span>Subtotal</span><span>${formatPeso(receipt.subtotal)}</span></div>
        <div class="r-line"><span>Discount</span><span>${formatPeso(receipt.discount)}</span></div>
        <div class="r-line r-total"><span>TOTAL</span><span>${formatPeso(receipt.total)}</span></div>
        <div class="r-line"><span>Payment (${escapeHtml(receipt.payment_method.toUpperCase())})</span><span>${formatPeso(receipt.amount_received)}</span></div>
        <div class="r-line"><span>Change</span><span>${formatPeso(receipt.change_due)}</span></div>
        <hr>
        <div class="r-center">Thank you for shopping with us! 🍜</div>
    `;
}

// ---------- download receipt as PDF ----------
document.getElementById('downloadReceiptBtn').addEventListener('click', () => {
    if (!lastReceipt || !window.jspdf) return;
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: [280, 500 + lastReceipt.items.length * 16] });

    let y = 30;
    const centerX = 140;
    doc.setFont('courier', 'bold');
    doc.setFontSize(14);
    doc.text('RAM-YUM STORE', centerX, y, { align: 'center' }); y += 16;
    doc.setFont('courier', 'normal');
    doc.setFontSize(9);
    doc.text('Korean & Japanese Store', centerX, y, { align: 'center' }); y += 18;

    doc.text('--------------------------------', centerX, y, { align: 'center' }); y += 14;
    doc.text(`Receipt No: ${lastReceipt.receipt_no}`, 20, y); y += 14;
    doc.text(`Date: ${lastReceipt.created_at}`, 20, y); y += 14;
    doc.text(`Cashier: ${lastReceipt.cashier_email}`, 20, y); y += 14;
    doc.text('--------------------------------', centerX, y, { align: 'center' }); y += 14;

    lastReceipt.items.forEach(it => {
        doc.text(`${it.name} x${it.quantity}`, 20, y);
        doc.text(formatPeso(it.line_total), 260, y, { align: 'right' });
        y += 14;
    });

    doc.text('--------------------------------', centerX, y, { align: 'center' }); y += 14;
    doc.text('Subtotal', 20, y); doc.text(formatPeso(lastReceipt.subtotal), 260, y, { align: 'right' }); y += 14;
    doc.text('Discount', 20, y); doc.text(formatPeso(lastReceipt.discount), 260, y, { align: 'right' }); y += 14;
    doc.setFont('courier', 'bold');
    doc.text('TOTAL', 20, y); doc.text(formatPeso(lastReceipt.total), 260, y, { align: 'right' }); y += 16;
    doc.setFont('courier', 'normal');
    doc.text(`Payment (${lastReceipt.payment_method.toUpperCase()})`, 20, y);
    doc.text(formatPeso(lastReceipt.amount_received), 260, y, { align: 'right' }); y += 14;
    doc.text('Change', 20, y); doc.text(formatPeso(lastReceipt.change_due), 260, y, { align: 'right' }); y += 18;

    doc.text('--------------------------------', centerX, y, { align: 'center' }); y += 16;
    doc.text('Thank you for shopping with us!', centerX, y, { align: 'center' });

    doc.save(`${lastReceipt.receipt_no}.pdf`);
});

document.getElementById('newSaleBtn').addEventListener('click', () => {
    receiptOverlay.classList.remove('show');
});

// ---------- initial paint ----------
renderCart();