<?php
session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/db.php';

$userEmail = $_SESSION['user_email'];
$userRole  = $_SESSION['user_role'] ?? 'cashier';
$initials  = strtoupper(substr($userEmail, 0, 1));
$db = get_db_connection();

function peso($n): string {
    return '₱' . number_format((float) $n, 2);
}

// Orders land in `pending_orders` from wherever customers place them
// (not this app). This page only processes what's already there.
$pendingOrders = $db->query(
    "SELECT po.*,
            (SELECT GROUP_CONCAT(CONCAT(product_name, ' x', quantity) SEPARATOR ', ')
             FROM pending_order_items WHERE pending_order_id = po.id) AS items_summary
     FROM pending_orders po
     WHERE po.status = 'pending'
     ORDER BY po.created_at ASC"
)->fetchAll();

// Reservations already deduct stock on creation elsewhere - this page
// just surfaces the ones still waiting to be picked up / paid for.
$reservations = $db->query(
    "SELECT r.*,
            (SELECT GROUP_CONCAT(CONCAT(product_name, ' x', quantity) SEPARATOR ', ')
             FROM reservation_items WHERE reservation_id = r.id) AS items_summary
     FROM reservations r
     WHERE r.status = 'reserved'
     ORDER BY r.created_at ASC"
)->fetchAll();
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

    <?php
    $__sidebarFile = ($userRole === 'cashier') ? 'sidebar-cashier.php' : 'sidebar.php';
    include __DIR__ . '/' . $__sidebarFile;
    ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-bowl-food" style="margin-right:8px;"></i>Orders & Reservations</h1>
                    <p>Process orders and reservations that have come in — collect cash and settle them.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <?php if ($userRole !== 'cashier') include __DIR__ . '/notif-bell.php'; ?>
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

        <main class="content">

            <div class="type-toggle" id="queueTabToggle" role="tablist">
                <button type="button" class="type-btn active" data-tab="orders" role="tab" aria-selected="true">
                    <i class="fa-solid fa-cart-shopping"></i> Pending Orders <span class="q-count"><?= count($pendingOrders) ?></span>
                </button>
                <button type="button" class="type-btn" data-tab="reservations" role="tab" aria-selected="false">
                    <i class="fa-solid fa-clock"></i> Reservations <span class="q-count"><?= count($reservations) ?></span>
                </button>
            </div>

            <!-- ============ PENDING ORDERS ============ -->
            <section class="queue-pane" id="pane-orders">
                <div class="panel history-panel">
                    <div class="history-panel-head">
                        <h3><i class="fa-solid fa-cart-shopping"></i> Pending Orders</h3>
                    </div>
                    <div class="table-scroll">
                    <table class="transactions-table queue-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Submitted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pendingOrders)): ?>
                            <tr class="empty-row"><td colspan="6">Nothing waiting to be processed right now.</td></tr>
                        <?php else: foreach ($pendingOrders as $o): ?>
                            <tr class="queue-row" data-type="order" data-id="<?= (int)$o['id'] ?>" data-total="<?= (float)$o['total'] ?>">
                                <td><strong><?= htmlspecialchars($o['order_no']) ?></strong></td>
                                <td><?= htmlspecialchars($o['customer_name'] ?: '—') ?></td>
                                <td class="hist-items-cell"><?= htmlspecialchars($o['items_summary'] ?? '') ?></td>
                                <td><?= peso($o['total']) ?></td>
                                <td><?= htmlspecialchars((new DateTime($o['created_at']))->format('M j, g:i A')) ?></td>
                                <td class="pr-row-actions">
                                    <button type="button" class="pos-btn-primary queue-process-btn" style="padding:7px 13px; font-size:0.78rem;"><i class="fa-solid fa-cash-register"></i> Process</button>
                                    <button type="button" class="pos-btn-secondary queue-cancel-btn" style="padding:7px 13px; font-size:0.78rem;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </section>

            <!-- ============ RESERVATIONS ============ -->
            <section class="queue-pane" id="pane-reservations" style="display:none;">
                <div class="panel history-panel">
                    <div class="history-panel-head">
                        <h3><i class="fa-solid fa-clock"></i> Reservations Awaiting Pickup</h3>
                    </div>
                    <div class="table-scroll">
                    <table class="transactions-table queue-table">
                        <thead>
                            <tr>
                                <th>Reservation #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Reserved</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr class="empty-row"><td colspan="6">No reservations waiting on pickup right now.</td></tr>
                        <?php else: foreach ($reservations as $r): ?>
                            <tr class="queue-row" data-type="reservation" data-id="<?= (int)$r['id'] ?>" data-total="<?= (float)$r['total'] ?>">
                                <td><strong><?= htmlspecialchars($r['reservation_no']) ?></strong></td>
                                <td><?= htmlspecialchars($r['customer_name']) ?></td>
                                <td class="hist-items-cell"><?= htmlspecialchars($r['items_summary'] ?? '') ?></td>
                                <td><?= peso($r['total']) ?></td>
                                <td><?= htmlspecialchars((new DateTime($r['created_at']))->format('M j, g:i A')) ?></td>
                                <td class="pr-row-actions">
                                    <button type="button" class="pos-btn-primary queue-process-btn" style="padding:7px 13px; font-size:0.78rem;"><i class="fa-solid fa-cash-register"></i> Process</button>
                                    <button type="button" class="pos-btn-secondary queue-cancel-btn" style="padding:7px 13px; font-size:0.78rem;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </section>

        </main>
    </div>
</div>

<!-- ============ CASH PAYMENT MODAL ============ -->
<div class="pos-modal-overlay" id="paymentOverlay">
    <div class="pos-modal">
        <h3><i class="fa-solid fa-cash-register"></i> Collect Payment</h3>
        <p class="pos-modal-sub">Cash only — enter the amount received to settle this.</p>
        <div class="confirm-reservation-summary">
            <div class="r-row"><span>Total due</span><strong id="paymentTotalDisplay">₱0.00</strong></div>
        </div>
        <label class="pos-field-label" style="margin-top:12px;">Amount received</label>
        <input type="number" id="amountReceived" class="pos-input" placeholder="0.00" min="0" step="0.01" inputmode="decimal">
        <div class="confirm-reservation-summary">
            <div class="r-row"><span>Change</span><strong id="changeDueDisplay">₱0.00</strong></div>
        </div>
        <p class="payment-short-note" id="paymentShortNote" style="display:none;">Amount received is less than the total due.</p>
        <div class="pos-modal-actions">
            <button type="button" class="pos-btn-secondary" id="paymentBack">Go Back</button>
            <button type="button" class="pos-btn-primary" id="paymentConfirm" disabled>Confirm Payment</button>
        </div>
    </div>
</div>

<!-- ============ LOADING OVERLAY ============ -->
<div class="pos-modal-overlay" id="loadingOverlay">
    <div class="pos-loading">
        <div class="pos-spinner"></div>
        <p id="loadingLabel">Processing...</p>
    </div>
</div>

<!-- ============ RECEIPT MODAL ============ -->
<div class="pos-modal-overlay" id="orderReceiptOverlay">
    <div class="pos-modal pos-receipt-modal">
        <div class="pos-receipt-check"><i class="fa-solid fa-circle-check"></i></div>
        <h3 style="text-align:center;">Payment Received</h3>
        <div class="pos-receipt" id="orderReceiptPaper"></div>
        <div class="pos-modal-actions">
            <button type="button" class="pos-btn-secondary" id="printReceiptBtn"><i class="fa-solid fa-print"></i> Print</button>
            <button type="button" class="pos-btn-primary" id="closeReceiptBtn"><i class="fa-solid fa-check"></i> Done</button>
        </div>
    </div>
</div>

<script src="orders.js"></script>
<script src="sidebar.js"></script>
<?php if ($userRole !== 'cashier'): ?><script src="notif-bell.js"></script><?php endif; ?>
</body>
</html>