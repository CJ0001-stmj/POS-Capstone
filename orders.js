// ============ RAM-YUM Orders & Reservations ============
// Depends on:
//   window.RAMYUM_PRODUCTS  (injected by orders.php)
//   window.RAMYUM_PROMO     (injected by orders.php)
//
// Two transaction types share one cart:
//   - "order"       -> summarize, then hand the cart off to pos.php's
//                       payment section via sessionStorage + redirect.
//   - "reservation" -> submit straight to reservation_create.php, which
//                       deducts stock immediately and holds the items
//                       aside under the customer's name (no payment yet).

const PRODUCTS = window.RAMYUM_PRODUCTS || [];
const PRODUCTS_BY_ID = new Map(PRODUCTS.map(p => [Number(p.id), p]));
const RESERVATION_API_URL = 'reservation_create.php';
const HANDOFF_KEY = 'ramyum_pos_handoff';

const ACTIVE_PROMO = window.RAMYUM_PROMO || null;
const applyPromoToggleEl = document.getElementById('applyPromoToggle');

// cart: Map<productId, {id, name, sku, price, stock_qty, qty}>
const cart = new Map();
let transactionType = 'order'; // 'order' | 'reservation'

function promoApplied() {
    return !!ACTIVE_PROMO && (!applyPromoToggleEl || applyPromoToggleEl.checked);
}

function calcDiscount(subtotal) {
    if (!promoApplied() || subtotal <= 0) return 0;
    return Math.round(subtotal * (Number(ACTIVE_PROMO.discount_percent) / 100) * 100) / 100;
}

function formatPromoLabel() {
    if (!ACTIVE_PROMO) return 'Discount';
    const pct = Number(ACTIVE_PROMO.discount_percent);
    const pctStr = pct % 1 === 0 ? pct.toFixed(0) : pct.toString();
    return `${ACTIVE_PROMO.name} (-${pctStr}%)`;
}

// ---------- helpers ----------
function formatPeso(n) {
    const num = Number(n) || 0;
    return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function cartSubtotal() {
    let sum = 0;
    for (const line of cart.values()) sum += line.price * line.qty;
    return Math.round(sum * 100) / 100;
}

function cartItemCount() {
    let n = 0;
    for (const line of cart.values()) n += line.qty;
    return n;
}

function currentTotal() {
    const subtotal = cartSubtotal();
    const discount = calcDiscount(subtotal);
    return Math.round((subtotal - discount) * 100) / 100;
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

productGrid.addEventListener('click', (e) => {
    const card = e.target.closest('.pos-product-card');
    if (!card || card.disabled) return;
    addToCart(card.dataset.id, 1);
});

// ---------- transaction type toggle ----------
const typeToggle = document.getElementById('typeToggle');
const typeHelp = document.getElementById('typeHelp');
const reservationFields = document.getElementById('reservationFields');
const proceedBtnLabel = document.getElementById('proceedBtnLabel');

const TYPE_COPY = {
    order: {
        help: 'Ring up the items now and send this order straight to POS for payment.',
        btn: 'Proceed to Payment',
        icon: 'fa-arrow-right-to-bracket',
    },
    reservation: {
        help: 'Sets these items aside under the customer\'s name and deducts stock now. Payment is collected later.',
        btn: 'Confirm Reservation',
        icon: 'fa-clock',
    },
};

typeToggle.addEventListener('click', (e) => {
    const btn = e.target.closest('.type-btn');
    if (!btn) return;
    transactionType = btn.dataset.type;

    typeToggle.querySelectorAll('.type-btn').forEach(b => {
        const isActive = b === btn;
        b.classList.toggle('active', isActive);
        b.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    const copy = TYPE_COPY[transactionType];
    typeHelp.textContent = copy.help;
    proceedBtnLabel.textContent = copy.btn;
    proceedBtn.querySelector('i').className = `fa-solid ${copy.icon}`;
    reservationFields.style.display = transactionType === 'reservation' ? 'block' : 'none';

    syncProceedState();
});

// ---------- cart rendering ----------
const cartItemsEl = document.getElementById('cartItems');
const cartEmptyNote = document.getElementById('cartEmptyNote');
const sumItemCountEl = document.getElementById('sumItemCount');
const sumSubtotalEl = document.getElementById('sumSubtotal');
const sumDiscountRowEl = document.getElementById('sumDiscountRow');
const sumDiscountLabelEl = document.getElementById('sumDiscountLabel');
const sumDiscountEl = document.getElementById('sumDiscount');
const sumTotalEl = document.getElementById('sumTotal');
const proceedBtn = document.getElementById('proceedBtn');
const clearCartBtn = document.getElementById('clearCartBtn');
const customerNameEl = document.getElementById('customerName');

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
    const discount = calcDiscount(subtotal);
    const total = Math.round((subtotal - discount) * 100) / 100;

    sumItemCountEl.textContent = cartItemCount();
    sumSubtotalEl.textContent = formatPeso(subtotal);
    if (discount > 0) {
        sumDiscountLabelEl.textContent = formatPromoLabel();
        sumDiscountEl.textContent = '-' + formatPeso(discount);
        sumDiscountRowEl.style.display = 'flex';
    } else {
        sumDiscountRowEl.style.display = 'none';
    }
    sumTotalEl.textContent = formatPeso(total);

    syncProceedState();
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
    if (confirm('Clear this transaction?')) {
        cart.clear();
        renderCart();
    }
});

if (applyPromoToggleEl) {
    applyPromoToggleEl.addEventListener('change', renderCart);
}
if (customerNameEl) {
    customerNameEl.addEventListener('input', syncProceedState);
}

function syncProceedState() {
    const hasItems = cart.size > 0;
    if (transactionType === 'reservation') {
        const hasName = !!(customerNameEl && customerNameEl.value.trim());
        proceedBtn.disabled = !(hasItems && hasName);
    } else {
        proceedBtn.disabled = !hasItems;
    }
}

// ---------- confirm modal ----------
const confirmOverlay = document.getElementById('confirmOverlay');
const confirmTitle = document.getElementById('confirmTitle');
const confirmSub = document.getElementById('confirmSub');
const confirmItems = document.getElementById('confirmItems');
const confirmSubtotal = document.getElementById('confirmSubtotal');
const confirmDiscountRow = document.getElementById('confirmDiscountRow');
const confirmDiscountLabel = document.getElementById('confirmDiscountLabel');
const confirmDiscount = document.getElementById('confirmDiscount');
const confirmTotal = document.getElementById('confirmTotal');
const confirmReservationSummary = document.getElementById('confirmReservationSummary');
const confirmBack = document.getElementById('confirmBack');
const confirmProceed = document.getElementById('confirmProceed');

proceedBtn.addEventListener('click', () => {
    if (cart.size === 0) return;
    if (transactionType === 'reservation' && !customerNameEl.value.trim()) return;

    const subtotal = cartSubtotal();
    const discount = calcDiscount(subtotal);
    const total = currentTotal();

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

    confirmSubtotal.textContent = formatPeso(subtotal);
    if (discount > 0) {
        confirmDiscountLabel.textContent = formatPromoLabel();
        confirmDiscount.textContent = '-' + formatPeso(discount);
        confirmDiscountRow.style.display = 'flex';
    } else {
        confirmDiscountRow.style.display = 'none';
    }
    confirmTotal.textContent = formatPeso(total);

    if (transactionType === 'reservation') {
        confirmTitle.innerHTML = '<i class="fa-solid fa-clock"></i> Confirm This Reservation';
        confirmSub.textContent = 'Stock is deducted the moment you confirm — the items are held aside, payment comes later.';
        confirmProceed.textContent = 'Confirm Reservation';
        confirmReservationSummary.style.display = 'block';
        confirmReservationSummary.innerHTML = `
            <div class="r-row"><span>Customer</span><strong>${escapeHtml(customerNameEl.value.trim())}</strong></div>
            ${document.getElementById('customerContact').value.trim() ? `<div class="r-row"><span>Contact</span><strong>${escapeHtml(document.getElementById('customerContact').value.trim())}</strong></div>` : ''}
            ${document.getElementById('reservationNotes').value.trim() ? `<div class="r-row"><span>Notes</span><strong>${escapeHtml(document.getElementById('reservationNotes').value.trim())}</strong></div>` : ''}
        `;
    } else {
        confirmTitle.innerHTML = '<i class="fa-solid fa-clipboard-check"></i> Confirm This Order';
        confirmSub.textContent = 'Double-check the items before sending this to Point of Sale.';
        confirmProceed.textContent = 'Confirm & Send to POS';
        confirmReservationSummary.style.display = 'none';
    }

    confirmOverlay.classList.add('show');
});

confirmBack.addEventListener('click', () => confirmOverlay.classList.remove('show'));

// ---------- confirm: order -> handoff to POS ----------
function sendOrderToPos() {
    const payload = {
        items: Array.from(cart.values()).map(l => ({ product_id: l.id, quantity: l.qty })),
        apply_promo: promoApplied(),
    };
    sessionStorage.setItem(HANDOFF_KEY, JSON.stringify(payload));
    window.location.href = 'pos.php';
}

// ---------- confirm: reservation -> submit ----------
const loadingOverlay = document.getElementById('loadingOverlay');
const loadingLabel = document.getElementById('loadingLabel');
const reservationSuccessOverlay = document.getElementById('reservationSuccessOverlay');
const reservationPaper = document.getElementById('reservationPaper');

async function submitReservation() {
    confirmOverlay.classList.remove('show');
    loadingLabel.textContent = 'Setting the reservation aside...';
    loadingOverlay.classList.add('show');

    const payload = {
        cart: Array.from(cart.values()).map(l => ({ product_id: l.id, quantity: l.qty })),
        customer_name: customerNameEl.value.trim(),
        customer_contact: document.getElementById('customerContact').value.trim(),
        notes: document.getElementById('reservationNotes').value.trim(),
        apply_promo: promoApplied(),
    };

    try {
        const response = await fetch(RESERVATION_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        loadingOverlay.classList.remove('show');

        if (!response.ok || !data.ok) {
            alert(`⚠️ ${data.message || 'Reservation failed. Please try again.'}`);
            return;
        }

        renderReservationPaper(data.reservation);
        applyStockFromLines(data.reservation.items);
        reservationSuccessOverlay.classList.add('show');

        cart.clear();
        customerNameEl.value = '';
        document.getElementById('customerContact').value = '';
        document.getElementById('reservationNotes').value = '';
        renderCart();
    } catch (err) {
        loadingOverlay.classList.remove('show');
        alert("⚠️ Couldn't reach the server. Please try again.");
        console.error('Reservation request failed:', err);
    }
}

function renderReservationPaper(res) {
    const itemsHtml = res.items.map(it => `
        <div class="r-line">
            <span>${escapeHtml(it.name)} x${it.quantity}</span>
            <span>${formatPeso(it.line_total)}</span>
        </div>
    `).join('');

    reservationPaper.innerHTML = `
        <div class="r-center r-title">RAM-YUM STORE</div>
        <div class="r-center">Reservation Slip</div>
        <hr>
        <div class="r-line"><span>Reservation No.</span><span>${escapeHtml(res.reservation_no)}</span></div>
        <div class="r-line"><span>Date</span><span>${escapeHtml(res.created_at)}</span></div>
        <div class="r-line"><span>Customer</span><span>${escapeHtml(res.customer_name)}</span></div>
        ${res.customer_contact ? `<div class="r-line"><span>Contact</span><span>${escapeHtml(res.customer_contact)}</span></div>` : ''}
        <div class="r-line"><span>Staff</span><span>${escapeHtml(res.staff_email)}</span></div>
        <hr>
        ${itemsHtml}
        <hr>
        <div class="r-line"><span>Subtotal</span><span>${formatPeso(res.subtotal)}</span></div>
        <div class="r-line"><span>${res.promotion_name ? escapeHtml(res.promotion_name) : 'Discount'}</span><span>-${formatPeso(res.discount)}</span></div>
        <div class="r-line r-total"><span>TOTAL DUE AT PICKUP</span><span>${formatPeso(res.total)}</span></div>
        <hr>
        <div class="r-center">Stock has been set aside. Collect payment when the customer picks up.</div>
    `;
}

// Stock is authoritative on the server after a reservation - patch it into
// PRODUCTS_BY_ID and the visible cards, same pattern pos.js uses after a sale.
function applyStockFromLines(items) {
    for (const item of items) {
        const product = PRODUCTS_BY_ID.get(Number(item.product_id));
        if (!product) continue;
        const remaining = Math.max(0, Number(item.remaining_stock));
        product.stock_qty = remaining;

        const card = productGrid.querySelector(`.pos-product-card[data-id="${item.product_id}"]`);
        if (!card) continue;

        const stockEl = card.querySelector('.p-stock');
        const threshold = Number(product.low_stock_threshold);
        const isOut = remaining <= 0;

        card.classList.toggle('out', isOut);
        card.disabled = isOut;
        if (stockEl) {
            stockEl.textContent = isOut ? 'Out of stock' : `${remaining} in stock`;
            stockEl.classList.toggle('low', !isOut && !Number.isNaN(threshold) && remaining <= threshold);
        }
    }
}

confirmProceed.addEventListener('click', () => {
    if (transactionType === 'order') {
        confirmOverlay.classList.remove('show');
        loadingLabel.textContent = 'Sending order to POS...';
        loadingOverlay.classList.add('show');
        sendOrderToPos();
    } else {
        submitReservation();
    }
});

document.getElementById('newTransactionBtn').addEventListener('click', () => {
    reservationSuccessOverlay.classList.remove('show');
});

// ---------- initial paint ----------
renderCart();
