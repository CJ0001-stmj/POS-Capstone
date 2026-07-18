<?php
session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/promotion_engine.php';

$userEmail = $_SESSION['user_email'];
$userRole  = $_SESSION['user_role'] ?? 'cashier';
$initials  = strtoupper(substr($userEmail, 0, 1));
$db = get_db_connection();

// Categories
$categories = $db->query('SELECT id, name, icon FROM categories ORDER BY sort_order, name')->fetchAll();

// Active products, same shape pos.php uses (product grid + cart both read
// this JSON), including any live clearance promo per product.
$products = $db->query(
    'SELECT id, category_id, sku, name, price, stock_qty, low_stock_threshold
     FROM products WHERE is_active = 1 ORDER BY name'
)->fetchAll();

$productPromos = all_product_promotions($db);
foreach ($products as &$p) {
    $promo = $productPromos[(int) $p['id']] ?? null;
    $p['promo_id'] = $promo['promotion_id'] ?? null;
    $p['promo_name'] = $promo['name'] ?? null;
    $p['promo_reason'] = $promo['reason'] ?? null;
    $p['promo_reason_label'] = $promo ? (PROMO_ENGINE_LABELS[$promo['reason']] ?? 'Promo') : null;
    $p['promo_discount_percent'] = $promo['discount_percent'] ?? null;
}
unset($p);

$activePromo = active_storewide_promotion($db);

$productsJson = json_encode($products, JSON_NUMERIC_CHECK);
$promoJson = json_encode($activePromo ?: null, JSON_NUMERIC_CHECK);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders & Reservations - RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="pos.css">
    <link rel="stylesheet" href="orders.css">
</head>
<body>
<div class="app-shell">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-bowl-food" style="margin-right:8px;"></i>Orders & Reservations</h1>
                    <p>Build a walk-in customer's order, then send it to Point of Sale — or set items aside as a reservation.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <?php include __DIR__ . '/notif-bell.php'; ?>
                <div class="user-chip">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="who">
                        <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                        <span><?= htmlspecialchars(ucfirst($userRole)) ?></span>
                    </div>
                    <a href="logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content pos-content">

            <div class="pos-layout">

                <!-- ============ LEFT: catalog ============ -->
                <section class="pos-catalog">

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
                        <?php foreach ($products as $p): $out = $p['stock_qty'] <= 0; $hasPromo = $p['promo_discount_percent'] !== null; ?>
                        <button type="button" class="pos-product-card <?= $out ? 'out' : '' ?> <?= $hasPromo ? 'has-promo promo-' . htmlspecialchars($p['promo_reason']) : '' ?>"
                                data-id="<?= (int)$p['id'] ?>"
                                data-category="<?= (int)$p['category_id'] ?>"
                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                data-sku="<?= htmlspecialchars($p['sku']) ?>"
                                <?= $out ? 'disabled' : '' ?>>
                            <?php if ($hasPromo): ?>
                                <span class="p-promo-badge"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($p['promo_reason_label']) ?></span>
                            <?php endif; ?>
                            <span class="p-name"><?= htmlspecialchars($p['name']) ?></span>
                            <span class="p-sku"><?= htmlspecialchars($p['sku']) ?></span>
                            <?php if ($hasPromo):
                                $discounted = (float)$p['price'] * (1 - (float)$p['promo_discount_percent'] / 100);
                            ?>
                                <span class="p-price">
                                    <span class="p-price-strike">₱<?= number_format((float)$p['price'], 2) ?></span>
                                    ₱<?= number_format($discounted, 2) ?>
                                </span>
                            <?php else: ?>
                                <span class="p-price">₱<?= number_format((float)$p['price'], 2) ?></span>
                            <?php endif; ?>
                            <span class="p-stock <?= $p['stock_qty'] <= $p['low_stock_threshold'] ? 'low' : '' ?>">
                                <?= $out ? 'Out of stock' : (int)$p['stock_qty'] . ' in stock' ?>
                            </span>
                        </button>
                        <?php endforeach; ?>
                        <p class="pos-empty-note" id="noResults" style="display:none;">No products match your search.</p>
                    </div>
                </section>

                <!-- ============ RIGHT: walk-in transaction container ============ -->
                <aside class="pos-cart">
                    <div class="pos-cart-head">
                        <h2><i class="fa-solid fa-basket-shopping"></i> Walk-in Transaction</h2>
                        <button type="button" class="pos-clear-btn" id="clearCartBtn" title="Clear">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>

                    <!-- Order vs. Reservation -->
                    <div class="type-toggle" id="typeToggle" role="tablist">
                        <button type="button" class="type-btn active" data-type="order" role="tab" aria-selected="true">
                            <i class="fa-solid fa-cart-shopping"></i> Order
                        </button>
                        <button type="button" class="type-btn" data-type="reservation" role="tab" aria-selected="false">
                            <i class="fa-solid fa-clock"></i> Reservation
                        </button>
                    </div>
                    <p class="type-help" id="typeHelp">Ring up the items now and send this order straight to POS for payment.</p>

                    <?php if ($activePromo): ?>
                    <label class="pos-promo-banner" for="applyPromoToggle">
                        <input type="checkbox" id="applyPromoToggle" checked>
                        <i class="fa-solid fa-tag"></i>
                        <span>Apply <?= htmlspecialchars($activePromo['name']) ?> — <?= rtrim(rtrim(number_format((float)$activePromo['discount_percent'], 2), '0'), '.') ?>% off</span>
                    </label>
                    <?php endif; ?>

                    <!-- Reservation-only customer details -->
                    <div class="reservation-fields" id="reservationFields" style="display:none;">
                        <label class="pos-field-label">Customer name *</label>
                        <input type="text" id="customerName" class="pos-input" placeholder="Juan Dela Cruz">
                        <label class="pos-field-label">Contact number (optional)</label>
                        <input type="text" id="customerContact" class="pos-input" placeholder="09XX XXX XXXX">
                        <label class="pos-field-label">Notes (optional)</label>
                        <input type="text" id="reservationNotes" class="pos-input" placeholder="Pickup time, special request...">
                    </div>

                    <div class="pos-cart-items" id="cartItems">
                        <p class="pos-empty-note" id="cartEmptyNote">Tap a product to add it to this transaction.</p>
                    </div>

                    <div class="pos-cart-summary">
                        <div class="row"><span>Items</span><span id="sumItemCount">0</span></div>
                        <div class="row"><span>Subtotal</span><span id="sumSubtotal">₱0.00</span></div>
                        <div class="row discount" id="sumDiscountRow" style="display:none;">
                            <span id="sumDiscountLabel">Discount</span><span id="sumDiscount">-₱0.00</span>
                        </div>
                        <div class="row total"><span>Total Amount</span><span id="sumTotal">₱0.00</span></div>

                        <button type="button" class="pos-checkout-btn" id="proceedBtn" disabled>
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> <span id="proceedBtnLabel">Proceed to Payment</span>
                        </button>
                    </div>
                </aside>

            </div>
        </main>
    </div>
</div>

<!-- ============ CONFIRM MODAL (order or reservation, same shell) ============ -->
<div class="pos-modal-overlay" id="confirmOverlay">
    <div class="pos-modal">
        <h3 id="confirmTitle"><i class="fa-solid fa-clipboard-check"></i> Confirm This Order</h3>
        <p class="pos-modal-sub" id="confirmSub">Double-check the items before sending this to Point of Sale.</p>
        <div class="pos-modal-items" id="confirmItems"></div>
        <div class="pos-modal-totals">
            <div class="row"><span>Subtotal</span><span id="confirmSubtotal">₱0.00</span></div>
            <div class="row discount" id="confirmDiscountRow" style="display:none;">
                <span id="confirmDiscountLabel">Discount</span><span id="confirmDiscount">-₱0.00</span>
            </div>
            <div class="row"><span>Total Amount</span><span id="confirmTotal">₱0.00</span></div>
        </div>
        <div id="confirmReservationSummary" class="confirm-reservation-summary" style="display:none;"></div>
        <div class="pos-modal-actions">
            <button type="button" class="pos-btn-secondary" id="confirmBack">Go Back &amp; Edit</button>
            <button type="button" class="pos-btn-primary" id="confirmProceed">Confirm</button>
        </div>
    </div>
</div>

<!-- ============ LOADING OVERLAY ============ -->
<div class="pos-modal-overlay" id="loadingOverlay">
    <div class="pos-loading">
        <div class="pos-spinner"></div>
        <p id="loadingLabel">Setting the reservation aside...</p>
    </div>
</div>

<!-- ============ RESERVATION SUCCESS MODAL ============ -->
<div class="pos-modal-overlay" id="reservationSuccessOverlay">
    <div class="pos-modal pos-receipt-modal">
        <div class="pos-receipt-check"><i class="fa-solid fa-circle-check"></i></div>
        <h3 style="text-align:center;">Reservation Confirmed</h3>
        <div class="pos-receipt" id="reservationPaper"></div>
        <div class="pos-modal-actions">
            <button type="button" class="pos-btn-primary" id="newTransactionBtn">
                <i class="fa-solid fa-plus"></i> New Transaction
            </button>
        </div>
    </div>
</div>

<script>
window.RAMYUM_PRODUCTS = <?= $productsJson ?: '[]' ?>;
window.RAMYUM_PROMO = <?= $promoJson ?: 'null' ?>;
</script>
<script src="orders.js"></script>
<script src="sidebar.js"></script>
<script src="notif-bell.js"></script>
</body>
</html>