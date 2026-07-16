/* ============================================================
   POS module front-end logic.
   Reads the bootstrap JSON rendered by pos.php (#posBootstrapData),
   renders categories / product grid / insights / cart, and talks
   to pos_api.php for refresh + checkout.
   ============================================================ */
(function () {
    'use strict';

    // ---------------------------------------------------------
    // State
    // ---------------------------------------------------------
    const state = {
        categories: [],
        products: [],
        lowStock: [],
        bestSellers: [],
        cart: new Map(),          // product_id -> { product, qty }
        activeCategory: 'all',
        searchTerm: '',
        paymentMethod: 'cash',
        lastReceipt: null,
    };

    const money = (n) => '₱' + Number(n || 0).toFixed(2);

    // ---------------------------------------------------------
    // Bootstrap
    // ---------------------------------------------------------
    function loadBootstrap(data) {
        state.categories = data.categories || [];
        state.products = data.products || [];
        state.lowStock = data.lowStock || [];
        state.bestSellers = data.bestSellers || [];
    }

    function init() {
        const el = document.getElementById('posBootstrapData');
        let data = {};
        try {
            data = JSON.parse(el ? el.textContent : '{}') || {};
        } catch (e) {
            data = {};
        }
        loadBootstrap(data);

        renderCategoryTabs();
        renderProductGrid();
        renderInsights();
        renderCart();
        renderQuickCash();

        wireEvents();

        // 1. Dropdown Toggle Function
window.toggleTransactionDropdown = function() {
    const content = document.getElementById('transactionDropdownContent');
    const icon = document.getElementById('txReportToggleIcon').querySelector('i');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.className = 'fa-solid fa-chevron-up';
        fetchTransactionReports(); // Load data dynamically when opened
    } else {
        content.style.display = 'none';
        icon.className = 'fa-solid fa-chevron-down';
    }
};

// 2. Async Data Loader
async function fetchTransactionReports() {
    const tbody = document.getElementById('txReportTableBody');
    if (!tbody) return;
    
    try {
        const res = await fetch('pos_api.php?action=fetch_transactions');
        const data = await res.json();
        
        if (!data.ok || !data.transactions || data.transactions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">No recent transactions found.</td></tr>`;
            return;
        }
        
        tbody.innerHTML = data.transactions.map(tx => {
            const methodBadge = tx.payment_method === 'cash' 
                ? '<span class="badge bg-success text-white">Cash</span>' 
                : tx.payment_method === 'gcash' 
                    ? '<span class="badge bg-primary text-white">GCash (E-Wallet)</span>' 
                    : '<span class="badge bg-secondary text-white">Card</span>';
                    
            return `
                <tr style="border-bottom: 1px solid rgba(117,11,29,0.08);">
                    <td class="fw-bold">${tx.created_at}</td>
                    <td style="color: var(--ram-red); font-weight: 700;">${tx.transaction_code}</td>
                    <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(tx.item_details)}">
                        ${escapeHtml(tx.item_details)}
                    </td>
                    <td>${methodBadge}</td>
                    <td class="fw-bold text-dark">₱${Number(tx.amount_received).toFixed(2)}</td>
                    <td class="text-muted">₱${Number(tx.change_due).toFixed(2)}</td>
                    <td class="fw-bold" style="color: var(--ram-red);">₱${Number(tx.total_amount).toFixed(2)}</td>
                </tr>
            `;
        }).join('');
        
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">Error fetching records.</td></tr>`;
    }
}

// 3. Hook the live-refresh system
// Inside your existing 'performCheckout()' function success pathway, add a call to update reports live if it's currently visible:
// if (document.getElementById('transactionDropdownContent').style.display === 'block') { fetchTransactionReports(); }
    }

    // ---------------------------------------------------------
    // Category tabs
    // ---------------------------------------------------------
    function renderCategoryTabs() {
        const wrap = document.getElementById('categoryTabs');
        if (!wrap) return;

        // Keep the existing "All" tab (already in the markup), drop the rest, rebuild.
        wrap.querySelectorAll('.cat-tab:not([data-category="all"])').forEach((n) => n.remove());

        state.categories.forEach((cat) => {
            const btn = document.createElement('button');
            btn.className = 'cat-tab';
            btn.dataset.category = String(cat.id);
            btn.innerHTML = `<i class="fa-solid ${escapeAttr(cat.icon || 'fa-tag')}"></i> ${escapeHtml(cat.name)}`;
            wrap.appendChild(btn);
        });
    }

    // ---------------------------------------------------------
    // Product grid
    // ---------------------------------------------------------
    function renderProductGrid() {
        const grid = document.getElementById('productGrid');
        if (!grid) return;

        const term = state.searchTerm.trim().toLowerCase();
        const filtered = state.products.filter((p) => {
            const matchesCategory = state.activeCategory === 'all' || String(p.category_id) === String(state.activeCategory);
            if (!matchesCategory) return false;
            if (!term) return true;
            return (
                p.name.toLowerCase().includes(term) ||
                (p.sku || '').toLowerCase().includes(term)
            );
        });

        if (filtered.length === 0) {
            grid.innerHTML = '<p style="color:var(--ram-muted); font-size:0.85rem; padding:20px 4px;">No products match your search.</p>';
            return;
        }

        const bestSellerIds = new Set(state.bestSellers.map((b) => Number(b.id)));

        grid.innerHTML = filtered.map((p) => {
            const stockQty = Number(p.stock_qty);
            const threshold = Number(p.low_stock_threshold);
            const outOfStock = stockQty <= 0;
            const isLow = !outOfStock && stockQty <= threshold;
            const isBest = bestSellerIds.has(Number(p.id));

            let badge = '';
            if (outOfStock) {
                badge = '<span class="product-badge low">OUT</span>';
            } else if (isLow) {
                badge = '<span class="product-badge low">LOW</span>';
            } else if (isBest) {
                badge = '<span class="product-badge best">BEST</span>';
            }

            return `
                <button type="button" class="product-card${outOfStock ? ' out-of-stock' : ''}" data-id="${p.id}" ${outOfStock ? 'disabled' : ''}>
                    ${badge}
                    <div class="product-sku">${escapeHtml(p.sku)}</div>
                    <div class="product-name">${escapeHtml(p.name)}</div>
                    <div class="product-foot">
                        <div class="product-price">${money(p.price)}</div>
                        <div class="product-stock${isLow ? ' low' : ''}">${outOfStock ? 'Out of stock' : stockQty + ' left'}</div>
                    </div>
                </button>
            `;
        }).join('');
    }

    // ---------------------------------------------------------
    // Insights (low stock + best sellers)
    // ---------------------------------------------------------
    function renderInsights() {
        const countEl = document.getElementById('lowStockCount');
        const lowList = document.getElementById('lowStockList');
        const bestList = document.getElementById('bestSellerList');

        if (countEl) countEl.textContent = String(state.lowStock.length);

        if (lowList) {
            if (state.lowStock.length === 0) {
                lowList.innerHTML = '<li class="insight-empty">All items are well stocked.</li>';
            } else {
                lowList.innerHTML = state.lowStock.map((item) => {
                    const critical = Number(item.stock_qty) <= 0 || Number(item.stock_qty) <= Number(item.low_stock_threshold) / 2;
                    return `
                        <li class="${critical ? 'critical' : ''}">
                            <span>${escapeHtml(item.name)}</span>
                            <span class="tag">${item.stock_qty} left</span>
                        </li>
                    `;
                }).join('');
            }
        }

        if (bestList) {
            if (state.bestSellers.length === 0) {
                bestList.innerHTML = '<li class="insight-empty">No sales recorded yet.</li>';
            } else {
                bestList.innerHTML = state.bestSellers.map((item, idx) => `
                    <li>
                        <span><span class="rank">${idx + 1}</span>${escapeHtml(item.name)}</span>
                        <span class="tag">${item.units_sold} sold</span>
                    </li>
                `).join('');
            }
        }
    }

    // ---------------------------------------------------------
    // Cart
    // ---------------------------------------------------------
    function addToCart(productId) {
        const product = state.products.find((p) => Number(p.id) === Number(productId));
        if (!product) return;
        if (Number(product.stock_qty) <= 0) return;

        const existing = state.cart.get(productId);
        const currentQty = existing ? existing.qty : 0;
        if (currentQty >= Number(product.stock_qty)) return; // can't exceed live stock

        state.cart.set(productId, { product, qty: currentQty + 1 });
        renderCart();
    }

    function changeQty(productId, delta) {
        const entry = state.cart.get(productId);
        if (!entry) return;
        const newQty = entry.qty + delta;
        if (newQty <= 0) {
            state.cart.delete(productId);
        } else if (newQty > Number(entry.product.stock_qty)) {
            return; // don't allow exceeding stock
        } else {
            entry.qty = newQty;
        }
        renderCart();
    }

    function removeFromCart(productId) {
        state.cart.delete(productId);
        renderCart();
    }

    function clearCart() {
        state.cart.clear();
        renderCart();
    }

    function cartTotal() {
        let total = 0;
        state.cart.forEach(({ product, qty }) => {
            total += Number(product.price) * qty;
        });
        return round2(total);
    }

    function cartItemCount() {
        let count = 0;
        state.cart.forEach(({ qty }) => (count += qty));
        return count;
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    function renderCart() {
        const itemsWrap = document.getElementById('cartItems');
        const emptyState = document.getElementById('cartEmptyState');
        const itemCountEl = document.getElementById('summaryItemCount');
        const totalEl = document.getElementById('summaryTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');

        if (itemsWrap) {
            if (state.cart.size === 0) {
                itemsWrap.innerHTML = `
                    <div class="cart-empty" id="cartEmptyState">
                        <i class="fa-solid fa-basket-shopping"></i>
                        <p>Tap a product to start an order.</p>
                    </div>
                `;
            } else {
                itemsWrap.innerHTML = Array.from(state.cart.entries()).map(([id, { product, qty }]) => {
                    const lineTotal = round2(Number(product.price) * qty);
                    return `
                        <div class="cart-line" data-id="${id}">
                            <div class="cart-line-info">
                                <div class="cart-line-name">${escapeHtml(product.name)}</div>
                                <div class="cart-line-price">${money(product.price)} each</div>
                            </div>
                            <div class="cart-qty">
                                <button type="button" class="qty-btn" data-action="dec" data-id="${id}">-</button>
                                <span class="qty-val">${qty}</span>
                                <button type="button" class="qty-btn" data-action="inc" data-id="${id}">+</button>
                            </div>
                            <div class="cart-line-total">${money(lineTotal)}</div>
                            <button type="button" class="cart-line-remove" data-action="remove" data-id="${id}" title="Remove">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    `;
                }).join('');
            }
        }

        const total = cartTotal();
        if (itemCountEl) itemCountEl.textContent = String(cartItemCount());
        if (totalEl) totalEl.textContent = money(total);

        renderQuickCash();
        updateChangeDue();

        if (checkoutBtn) {
            const amountReceived = getAmountReceived();
            const hasItems = state.cart.size > 0;
            const enoughCash = amountReceived >= total && total > 0;
            checkoutBtn.disabled = !(hasItems && enoughCash);
        }
    }

    function getAmountReceived() {
        const input = document.getElementById('amountReceived');
        const val = input ? parseFloat(input.value) : 0;
        return isNaN(val) ? 0 : val;
    }

    function updateChangeDue() {
        const changeEl = document.getElementById('summaryChange');
        if (!changeEl) return;
        const total = cartTotal();
        const received = getAmountReceived();
        const change = round2(received - total);
        changeEl.textContent = money(change);
        const row = changeEl.closest('.summary-row');
        if (row) {
            if (change < 0) {
                row.classList.add('insufficient');
            } else {
                row.classList.remove('insufficient');
            }
        }
    }

    function renderQuickCash() {
        const wrap = document.getElementById('quickCash');
        if (!wrap) return;
        const total = cartTotal();

        if (total <= 0) {
            wrap.innerHTML = '';
            return;
        }

        const denominations = [20, 50, 100, 200, 500, 1000];
        const suggestions = new Set();
        suggestions.add(round2(total)); // exact amount

        denominations.forEach((d) => {
            if (d >= total) suggestions.add(d);
        });

        // Also add "round up to next denomination above total" if none matched closely
        const nextRoundUp = Math.ceil(total / 100) * 100;
        if (nextRoundUp > total) suggestions.add(nextRoundUp);

        const values = Array.from(suggestions).sort((a, b) => a - b).slice(0, 5);

        wrap.innerHTML = values.map((v) => `
            <button type="button" data-amount="${v}">${money(v)}</button>
        `).join('');
    }

    // ---------------------------------------------------------
    // Confirm modal
    // ---------------------------------------------------------
    function openConfirmModal() {
        const modal = document.getElementById('confirmModal');
        const itemsWrap = document.getElementById('confirmItems');
        const totalEl = document.getElementById('confirmTotal');
        const receivedEl = document.getElementById('confirmReceived');
        const changeEl = document.getElementById('confirmChange');
        const methodEl = document.getElementById('confirmMethod');

        if (itemsWrap) {
            itemsWrap.innerHTML = Array.from(state.cart.values()).map(({ product, qty }) => `
                <div class="confirm-item-row">
                    <span class="name">${escapeHtml(product.name)} <span class="meta">x${qty}</span></span>
                    <span>${money(round2(Number(product.price) * qty))}</span>
                </div>
            `).join('');
        }

        const total = cartTotal();
        const received = getAmountReceived();
        const change = round2(received - total);

        if (totalEl) totalEl.textContent = money(total);
        if (receivedEl) receivedEl.textContent = money(received);
        if (changeEl) changeEl.textContent = money(change);
        if (methodEl) methodEl.textContent = methodLabel(state.paymentMethod);

        if (modal) modal.classList.add('show');
    }

    function closeConfirmModal() {
        const modal = document.getElementById('confirmModal');
        if (modal) modal.classList.remove('show');
    }

    function methodLabel(method) {
        return { cash: 'Cash', gcash: 'GCash', card: 'Card' }[method] || 'Cash';
    }

    // ---------------------------------------------------------
    // Loading overlay
    // ---------------------------------------------------------
    function showLoading(text) {
        const overlay = document.getElementById('loadingOverlay');
        const textEl = document.getElementById('loadingText');
        if (textEl) textEl.textContent = text || 'Processing transaction...';
        if (overlay) overlay.classList.add('show');
    }

    function hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.classList.remove('show');
    }

    // ---------------------------------------------------------
    // Receipt modal
    // ---------------------------------------------------------
    function openReceiptModal(receipt) {
        state.lastReceipt = receipt;

        const codeLine = document.getElementById('receiptCodeLine');
        const totalsBox = document.getElementById('receiptTotalsBox');
        const modal = document.getElementById('receiptModal');

        if (codeLine) codeLine.textContent = `Reference: ${receipt.transaction_code}`;
        if (totalsBox) {
            totalsBox.innerHTML = `
                <div><span>Total Amount</span><strong>${money(receipt.total_amount)}</strong></div>
                <div><span>Amount Received</span><strong>${money(receipt.amount_received)}</strong></div>
                <div><span>Change</span><strong>${money(receipt.change_due)}</strong></div>
                <div><span>Payment Method</span><strong>${methodLabel(receipt.payment_method)}</strong></div>
            `;
        }
        if (modal) modal.classList.add('show');
    }

    function closeReceiptModal() {
        const modal = document.getElementById('receiptModal');
        if (modal) modal.classList.remove('show');
    }

    function downloadReceiptPdf() {
        const receipt = state.lastReceipt;
        if (!receipt || !window.jspdf) return;

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ unit: 'pt', format: [280, 500 + receipt.items.length * 16] });

        let y = 30;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);
        doc.text('RAM-YUM STORE', 140, y, { align: 'center' });
        y += 18;
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.text('Official Receipt', 140, y, { align: 'center' });
        y += 20;

        doc.text(`Ref: ${receipt.transaction_code}`, 20, y); y += 14;
        doc.text(`Date: ${receipt.created_at}`, 20, y); y += 14;
        if (receipt.cashier_email) {
            doc.text(`Cashier: ${receipt.cashier_email}`, 20, y); y += 14;
        }
        y += 6;
        doc.text('----------------------------------------', 20, y); y += 14;

        receipt.items.forEach((item) => {
            doc.text(`${item.name}`, 20, y); y += 12;
            doc.text(`  ${item.quantity} x ${money(item.unit_price)}`, 20, y);
            doc.text(`${money(item.line_total)}`, 240, y, { align: 'right' });
            y += 16;
        });

        doc.text('----------------------------------------', 20, y); y += 14;
        doc.text('Subtotal', 20, y); doc.text(money(receipt.subtotal), 240, y, { align: 'right' }); y += 14;
        doc.setFont('helvetica', 'bold');
        doc.text('Total', 20, y); doc.text(money(receipt.total_amount), 240, y, { align: 'right' }); y += 14;
        doc.setFont('helvetica', 'normal');
        doc.text('Amount Received', 20, y); doc.text(money(receipt.amount_received), 240, y, { align: 'right' }); y += 14;
        doc.text('Change', 20, y); doc.text(money(receipt.change_due), 240, y, { align: 'right' }); y += 14;
        doc.text('Payment Method', 20, y); doc.text(methodLabel(receipt.payment_method), 240, y, { align: 'right' }); y += 20;

        doc.setFontSize(8);
        doc.text('Thank you for shopping with us!', 140, y, { align: 'center' });

        doc.save(`${receipt.transaction_code}.pdf`);
    }

    // ---------------------------------------------------------
    // Checkout
    // ---------------------------------------------------------
    async function performCheckout() {
        const items = Array.from(state.cart.entries()).map(([id, { qty }]) => ({
            product_id: Number(id),
            quantity: qty,
        }));
        const amountReceived = getAmountReceived();

        closeConfirmModal();
        showLoading('Processing transaction...');

        try {
            const res = await fetch('pos_api.php?action=checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    items,
                    amount_received: amountReceived,
                    payment_method: state.paymentMethod,
                }),
            });
            const data = await res.json();
            hideLoading();

            if (!data.ok) {
                alert(data.message || 'Checkout failed. Please try again.');
                // Refresh in case stock changed under us.
                refreshFromServer();
                return;
            }

            // Sync fresh product/insight data returned alongside the receipt.
            loadBootstrap(data);
            state.cart.clear();

            const amountInput = document.getElementById('amountReceived');
            if (amountInput) amountInput.value = '';

            renderCategoryTabs();
            renderProductGrid();
            renderInsights();
            renderCart();

            openReceiptModal(data.receipt);
        } catch (err) {
            hideLoading();
            alert('Something went wrong while checking out. Please try again.');
        }
    }

    async function refreshFromServer() {
        try {
            const res = await fetch('pos_api.php?action=refresh');
            const data = await res.json();
            if (data.ok) {
                loadBootstrap(data);
                renderCategoryTabs();
                renderProductGrid();
                renderInsights();
            }
        } catch (err) {
            // Silent fail; user still has the last known state.
        }
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------
    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }
    function escapeAttr(str) {
        return escapeHtml(str);
    }

    // ---------------------------------------------------------
    // Events
    // ---------------------------------------------------------
    function wireEvents() {
        // Category tabs
        document.getElementById('categoryTabs')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.cat-tab');
            if (!btn) return;
            document.querySelectorAll('.cat-tab').forEach((t) => t.classList.remove('active'));
            btn.classList.add('active');
            state.activeCategory = btn.dataset.category;
            renderProductGrid();
        });

        // Product search
        document.getElementById('productSearch')?.addEventListener('input', (e) => {
            state.searchTerm = e.target.value;
            renderProductGrid();
        });

        // Product grid clicks
        document.getElementById('productGrid')?.addEventListener('click', (e) => {
            const card = e.target.closest('.product-card');
            if (!card || card.disabled) return;
            addToCart(Number(card.dataset.id));
        });

        // Cart interactions (qty +/-, remove)
        document.getElementById('cartItems')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const id = Number(btn.dataset.id);
            if (btn.dataset.action === 'inc') changeQty(id, 1);
            if (btn.dataset.action === 'dec') changeQty(id, -1);
            if (btn.dataset.action === 'remove') removeFromCart(id);
        });

        // Clear cart
        document.getElementById('clearCartBtn')?.addEventListener('click', () => {
            if (state.cart.size === 0) return;
            if (confirm('Clear the current order?')) clearCart();
        });

        // Amount received
        document.getElementById('amountReceived')?.addEventListener('input', () => {
            updateChangeDue();
            renderCartCheckoutState();
        });

        // Quick cash buttons
        document.getElementById('quickCash')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-amount]');
            if (!btn) return;
            const input = document.getElementById('amountReceived');
            if (input) input.value = btn.dataset.amount;
            updateChangeDue();
            renderCartCheckoutState();
        });

        // Payment method
        document.getElementById('paymentMethods')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.pm-btn');
            if (!btn) return;
            document.querySelectorAll('.pm-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            state.paymentMethod = btn.dataset.method;
        });

        // Checkout button -> open confirm modal
        document.getElementById('checkoutBtn')?.addEventListener('click', () => {
            if (state.cart.size === 0) return;
            openConfirmModal();
        });

        // Confirm modal controls
        document.getElementById('confirmModalClose')?.addEventListener('click', closeConfirmModal);
        document.getElementById('confirmBackBtn')?.addEventListener('click', closeConfirmModal);
        document.getElementById('confirmProceedBtn')?.addEventListener('click', performCheckout);

        // Receipt modal controls
        document.getElementById('receiptModalClose')?.addEventListener('click', closeReceiptModal);
        document.getElementById('newOrderBtn')?.addEventListener('click', closeReceiptModal);
        document.getElementById('downloadReceiptBtn')?.addEventListener('click', downloadReceiptPdf);

        // Close modals when clicking the dark overlay itself
        document.querySelectorAll('.pos-modal-overlay').forEach((overlay) => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('show');
            });
        });
    }

    function renderCartCheckoutState() {
        const checkoutBtn = document.getElementById('checkoutBtn');
        if (!checkoutBtn) return;
        const total = cartTotal();
        const amountReceived = getAmountReceived();
        const hasItems = state.cart.size > 0;
        const enoughCash = amountReceived >= total && total > 0;
        checkoutBtn.disabled = !(hasItems && enoughCash);
    }

    // ---------------------------------------------------------
    // Boot
    // ---------------------------------------------------------
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/**
 * Switch View: Show Transaction Reports Module, Hide POS Register
 */
window.switchToTxReportModule = function() {
    // 1. Manage Active Class in Sidebar
    document.querySelectorAll('.sidebar-nav .nav-item').forEach(el => el.classList.remove('active'));
    const sidebarBtn = document.getElementById('sidebarTxReportBtn');
    if (sidebarBtn) sidebarBtn.classList.add('active');

    // 2. Hide POS Grid Components (Adjust selectors to match your active checkout layouts)
    const posLayout = document.querySelector('.pos-layout');
    const posInsights = document.querySelector('.pos-insights');
    if (posLayout) posLayout.classList.add('d-none');
    if (posInsights) posInsights.classList.add('d-none');

    // 3. Reveal Report Module Container
    const reportModule = document.getElementById('transactionReportModule');
    if (reportModule) {
        reportModule.classList.remove('d-none');
        // Fetch fresh transactional analytics
        loadModuleTransactions();
    }
};

/**
 * Switch View: Return back to standard Selling Registry view
 */
window.switchToPOSRegister = function() {
    // 1. Highlight standard POS in Sidebar
    document.querySelectorAll('.sidebar-nav .nav-item').forEach(el => el.classList.remove('active'));
    const posSidebarBtn = document.querySelector('.sidebar-nav .nav-item:first-child');
    if (posSidebarBtn) posSidebarBtn.classList.add('active');

    // 2. Toggle Displays
    const reportModule = document.getElementById('transactionReportModule');
    const posLayout = document.querySelector('.pos-layout');
    const posInsights = document.querySelector('.pos-insights');

    if (reportModule) reportModule.classList.add('d-none');
    if (posLayout) posLayout.classList.remove('d-none');
    if (posInsights) posInsights.classList.remove('d-none');
};

/**
 * Fetch data and populate Module Overview Table and Metrics
 */
async function loadModuleTransactions() {
    const tbody = document.getElementById('moduleTxTableBody');
    if (!tbody) return;

    try {
        const res = await fetch('pos_api.php?action=fetch_transactions');
        const data = await res.json();

        if (!data.ok || !data.transactions || data.transactions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-5">No transactional records found.</td></tr>`;
            return;
        }

        // Initialize Metrics
        let totalVol = 0;
        let cashVol = 0;
        let eWalletVol = 0;

        // Render Table Body
        tbody.innerHTML = data.transactions.map(tx => {
            const amount = parseFloat(tx.total_amount) || 0;
            const received = parseFloat(tx.amount_received) || 0;
            const change = parseFloat(tx.change_due) || 0;
            const isCash = tx.payment_method === 'cash';

            // Accumulate financial stats
            totalVol += amount;
            if (isCash) cashVol += amount;
            else eWalletVol += amount;

            const badgeHTML = isCash 
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Cash</span>' 
                : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">E-Wallet (GCash)</span>';

            const dateStr = new Date(tx.created_at).toLocaleString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            return `
                <tr class="tx-row">
                    <td class="ps-3 text-muted">${dateStr}</td>
                    <td class="fw-bold text-dark tx-ref-code">${tx.transaction_code}</td>
                    <td style="max-width: 320px;" class="text-truncate" title="${escapeHtml(tx.item_details)}">
                        ${escapeHtml(tx.item_details)}
                    </td>
                    <td>${badgeHTML}</td>
                    <td class="fw-bold">₱${received.toFixed(2)}</td>
                    <td class="text-muted">₱${change.toFixed(2)}</td>
                    <td class="pe-3 fw-bold text-end" style="color: var(--ram-red);">₱${amount.toFixed(2)}</td>
                </tr>
            `;
        }).join('');

        // Apply dynamic metric counts
        document.getElementById('modTotalVolume').innerText = `₱${totalVol.toFixed(2)}`;
        document.getElementById('modCashTotal').innerText = `₱${cashVol.toFixed(2)}`;
        document.getElementById('modEWalletTotal').innerText = `₱${eWalletVol.toFixed(2)}`;

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-5">Error parsing reporting systems data.</td></tr>`;
    }
}

/**
 * Quick Filter / Live Search Method for Module Tables
 */
window.filterModuleTransactions = function() {
    const input = document.getElementById('moduleTxSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#moduleTxTableBody .tx-row');

    rows.forEach(row => {
        const refCode = row.querySelector('.tx-ref-code').textContent.toLowerCase();
        if (refCode.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
};

// Helper utility to clean output
function escapeHtml(text) {
    if(!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}