<?php
session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/db.php';

$userEmail = $_SESSION['user_email'];
$initials = strtoupper(substr($userEmail, 0, 1));
$db = get_db_connection();

// Categories
$categories = $db->query('SELECT id, name, icon FROM categories ORDER BY sort_order, name')->fetchAll();

// Active products, grouped by category for the grid + embedded as JSON for the cart/search logic
$products = $db->query(
    'SELECT id, category_id, sku, name, price, stock_qty, low_stock_threshold
     FROM products WHERE is_active = 1 ORDER BY name'
)->fetchAll();

// Low stock (at or below threshold)
$lowStock = $db->query(
    'SELECT id, name, sku, stock_qty, low_stock_threshold FROM products
     WHERE is_active = 1 AND stock_qty <= low_stock_threshold
     ORDER BY stock_qty ASC LIMIT 8'
)->fetchAll();

// Best sellers — last 30 days by units sold, falling back to all-time if quiet
$bestSellers = $db->query(
    "SELECT p.id, p.name, p.price, p.stock_qty, SUM(si.quantity) AS units_sold
     FROM sale_items si
     JOIN products p ON p.id = si.product_id
     JOIN sales s ON s.id = si.sale_id
     WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY p.id, p.name, p.price, p.stock_qty
     ORDER BY units_sold DESC
     LIMIT 6"
)->fetchAll();
if (count($bestSellers) === 0) {
    $bestSellers = $db->query(
        "SELECT p.id, p.name, p.price, p.stock_qty, SUM(si.quantity) AS units_sold
         FROM sale_items si
         JOIN products p ON p.id = si.product_id
         JOIN sales s ON s.id = si.sale_id
         WHERE s.status = 'completed'
         GROUP BY p.id, p.name, p.price, p.stock_qty
         ORDER BY units_sold DESC
         LIMIT 6"
    )->fetchAll();
}

$productsJson = json_encode($products, JSON_NUMERIC_CHECK);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="pos.css">
</head>
<body>
<div class="app-shell">

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="assets/logo.png" alt="RAM-YUM Logo">
            <div class="brand-text">
                <strong>RAM-YUM</strong>
                <span>Store Management</span>
            </div>
        </div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Overview</a></li>
            <li class="active"><a href="pos.php"><i class="fa-solid fa-cash-register"></i> Point of Sale</a></li>
            <li><a href="purchase-requests.php"><i class="fa-solid fa-dolly"></i> Purchase Requests</a></li>
            <li><a href="orders.php"><i class="fa-solid fa-bowl-food"></i> Orders &amp; Reservations</a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-line"></i> Sales Analytics</a></li>
            <li><a href="promotions.php"><i class="fa-solid fa-tags"></i> Promotions</a></li>
            <li><a href="login_audit.php"><i class="fa-solid fa-user-shield"></i> Audit &amp; Access</a></li>
        </ul>
        <div class="sidebar-foot">Logged in as<br><strong style="color:var(--ram-yellow)"><?= htmlspecialchars($userEmail) ?></strong></div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-cash-register" style="margin-right:8px;"></i>Point of Sale</h1>
                    <p>Ring up items, review the order, then checkout.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <div class="user-chip">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="who">
                        <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                        <span>Cashier</span>
                    </div>
                    <a href="logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content pos-content">

            <div class="pos-layout">

                <!-- ============ LEFT: catalog ============ -->
                <section class="pos-catalog">

                    <div class="pos-widgets">
                        <div class="pos-widget">
                            <div class="pos-widget-head">
                                <span><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</span>
                            </div>
                            <div class="pos-widget-body">
                                <?php if (count($lowStock) === 0): ?>
                                    <p class="pos-empty-note">Nothing running low right now.</p>
                                <?php else: foreach ($lowStock as $ls): ?>
                                    <div class="pos-low-row">
                                        <span class="name"><?= htmlspecialchars($ls['name']) ?></span>
                                        <span class="qty <?= $ls['stock_qty'] == 0 ? 'zero' : '' ?>"><?= (int)$ls['stock_qty'] ?> left</span>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>

                        <div class="pos-widget">
                            <div class="pos-widget-head">
                                <span><i class="fa-solid fa-fire"></i> Best Sellers</span>
                            </div>
                            <div class="pos-widget-body">
                                <?php if (count($bestSellers) === 0): ?>
                                    <p class="pos-empty-note">No sales recorded yet.</p>
                                <?php else: foreach ($bestSellers as $bs): ?>
                                    <div class="pos-best-row" data-add-id="<?= (int)$bs['id'] ?>">
                                        <span class="name"><?= htmlspecialchars($bs['name']) ?></span>
                                        <span class="sold"><?= (int)$bs['units_sold'] ?> sold</span>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="pos-search-row">
                        <div class="pos-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="productSearch" placeholder="Search products or SKU...">
                        </div>
                    </div>

                    <div class="pos-tabs" id="categoryTabs">
                        <button class="pos-tab active" data-category="all">
                            <i class="fa-solid fa-border-all"></i> All
                        </button>
                        <?php foreach ($categories as $cat): ?>
                        <button class="pos-tab" data-category="<?= (int)$cat['id'] ?>">
                            <i class="fa-solid <?= htmlspecialchars($cat['icon']) ?>"></i> <?= htmlspecialchars($cat['name']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="pos-product-grid" id="productGrid">
                        <?php foreach ($products as $p): $out = $p['stock_qty'] <= 0; ?>
                        <button type="button" class="pos-product-card <?= $out ? 'out' : '' ?>"
                                data-id="<?= (int)$p['id'] ?>"
                                data-category="<?= (int)$p['category_id'] ?>"
                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                data-sku="<?= htmlspecialchars($p['sku']) ?>"
                                <?= $out ? 'disabled' : '' ?>>
                            <span class="p-name"><?= htmlspecialchars($p['name']) ?></span>
                            <span class="p-sku"><?= htmlspecialchars($p['sku']) ?></span>
                            <span class="p-price">₱<?= number_format((float)$p['price'], 2) ?></span>
                            <span class="p-stock <?= $p['stock_qty'] <= $p['low_stock_threshold'] ? 'low' : '' ?>">
                                <?= $out ? 'Out of stock' : (int)$p['stock_qty'] . ' in stock' ?>
                            </span>
                        </button>
                        <?php endforeach; ?>
                        <p class="pos-empty-note" id="noResults" style="display:none;">No products match your search.</p>
                    </div>
                </section>

                <!-- ============ RIGHT: cart / order summary ============ -->
                <aside class="pos-cart">
                    <div class="pos-cart-head">
                        <h2><i class="fa-solid fa-receipt"></i> Current Order</h2>
                        <button type="button" class="pos-clear-btn" id="clearCartBtn" title="Clear order">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>

                    <div class="pos-cart-items" id="cartItems">
                        <p class="pos-empty-note" id="cartEmptyNote">Tap a product to add it to the order.</p>
                    </div>

                    <div class="pos-cart-summary">
                        <div class="row"><span>Subtotal</span><span id="sumSubtotal">₱0.00</span></div>
                        <div class="row total"><span>Total</span><span id="sumTotal">₱0.00</span></div>

                        <label class="pos-field-label">Payment method</label>
                        <select id="paymentMethod" class="pos-select">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="card">Card</option>
                        </select>

                        <label class="pos-field-label">Amount received</label>
                        <input type="number" id="amountReceived" class="pos-input" placeholder="0.00" min="0" step="0.01">

                        <div class="row change"><span>Change</span><span id="sumChange">₱0.00</span></div>

                        <button type="button" class="pos-checkout-btn" id="checkoutBtn" disabled>
                            <i class="fa-solid fa-circle-check"></i> Checkout
                        </button>
                    </div>
                </aside>

            </div>
        </main>
    </div>
</div>

<!-- ============ CONFIRM MODAL ============ -->
<div class="pos-modal-overlay" id="confirmOverlay">
    <div class="pos-modal">
        <h3><i class="fa-solid fa-clipboard-check"></i> Confirm This Sale</h3>
        <p class="pos-modal-sub">Double-check the order before it's finalized.</p>
        <div class="pos-modal-items" id="confirmItems"></div>
        <div class="pos-modal-totals">
            <div class="row"><span>Subtotal</span><span id="confirmSubtotal">₱0.00</span></div>
            <div class="row"><span>Total Due</span><span id="confirmTotal">₱0.00</span></div>
            <div class="row"><span>Amount Received</span><span id="confirmReceived">₱0.00</span></div>
            <div class="row change"><span>Change</span><span id="confirmChange">₱0.00</span></div>
        </div>
        <div class="pos-modal-actions">
            <button type="button" class="pos-btn-secondary" id="confirmBack">Go Back &amp; Edit</button>
            <button type="button" class="pos-btn-primary" id="confirmProceed">Confirm &amp; Complete Sale</button>
        </div>
    </div>
</div>

<!-- ============ LOADING OVERLAY ============ -->
<div class="pos-modal-overlay" id="loadingOverlay">
    <div class="pos-loading">
        <div class="pos-spinner"></div>
        <p>Processing transaction &amp; preparing receipt...</p>
    </div>
</div>

<!-- ============ RECEIPT MODAL ============ -->
<div class="pos-modal-overlay" id="receiptOverlay">
    <div class="pos-modal pos-receipt-modal">
        <div class="pos-receipt-check"><i class="fa-solid fa-circle-check"></i></div>
        <h3 style="text-align:center;">Sale Complete</h3>
        <div class="pos-receipt" id="receiptPaper"></div>
        <div class="pos-modal-actions">
            <button type="button" class="pos-btn-secondary" id="downloadReceiptBtn">
                <i class="fa-solid fa-download"></i> Download PDF
            </button>
            <button type="button" class="pos-btn-primary" id="newSaleBtn">
                <i class="fa-solid fa-plus"></i> New Sale
            </button>
        </div>
    </div>
</div>

<script>window.RAMYUM_PRODUCTS = <?= $productsJson ?: '[]' ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="pos.js"></script>
<script>
document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
});
</script>
</body>
</html>