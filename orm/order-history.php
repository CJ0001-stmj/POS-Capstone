<?php
session_start();
require_once __DIR__ . '/../db.php';


// Idle session timeout — kill session after 10 min no activity
$SESSION_IDLE_LIMIT = 600; // seconds (10 min)
if (!empty($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $SESSION_IDLE_LIMIT) {
        $_SESSION = [];
        session_destroy();
        header('Location: /index.php?timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$userEmail = $_SESSION['user_email'];
$userRole  = $_SESSION['user_role'] ?? 'cashier';
$pdo       = get_db_connection();

function peso($n): string {
    return '₱' . number_format((float) $n, 2);
}

// ---------------------------------------------------------------
// Filters (each side of the page filters independently)
// ---------------------------------------------------------------
$resStatus = $_GET['res_status'] ?? 'all';                 // all|reserved|fulfilled|cancelled
$resQuery  = trim($_GET['res_q'] ?? '');

$activeTab = ($_GET['tab'] ?? 'reservations') === 'orders' ? 'orders' : 'reservations';

$ordFrom = $_GET['ord_from'] ?? date('Y-m-d', strtotime('-30 days'));
$ordTo   = $_GET['ord_to'] ?? date('Y-m-d');
$ordQuery = trim($_GET['ord_q'] ?? '');

// ---------------------------------------------------------------
// Reserved purchases (from the reservations module)
// ---------------------------------------------------------------
$resSql = "SELECT r.id, r.reservation_no, r.customer_name, r.customer_contact, r.notes,
                  r.staff_email, r.subtotal, r.discount, r.promotion_name, r.total,
                  r.item_count, r.status, r.created_at, r.fulfilled_at, r.cancelled_at,
                  MAX(sl.id) AS sale_id, MAX(sl.receipt_no) AS sale_receipt_no,
                  MAX(sl.created_at) AS sale_created_at, MAX(sl.total) AS sale_total,
                  GROUP_CONCAT(CONCAT(ri.product_name, ' x', ri.quantity) SEPARATOR ', ') AS items
           FROM reservations r
           LEFT JOIN reservation_items ri ON ri.reservation_id = r.id
           LEFT JOIN sales sl ON sl.id = r.fulfilled_sale_id
           WHERE 1=1";
$resParams = [];
if ($resStatus !== 'all') {
    $resSql .= " AND r.status = :status";
    $resParams[':status'] = $resStatus;
}
if ($resQuery !== '') {
    $resSql .= " AND (r.reservation_no LIKE :q OR r.customer_name LIKE :q)";
    $resParams[':q'] = '%' . $resQuery . '%';
}
$resSql .= " GROUP BY r.id ORDER BY r.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($resSql);
$stmt->execute($resParams);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Full line items per reservation, for the "view full details" modal.
$resItemsByRes = [];
$resIds = array_column($reservations, 'id');
if ($resIds) {
    $ph = implode(',', array_fill(0, count($resIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT reservation_id, product_id, product_name, unit_price, quantity, discount_amount, line_total, promotion_name
         FROM reservation_items WHERE reservation_id IN ($ph) ORDER BY id"
    );
    $stmt->execute($resIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $resItemsByRes[(int) $row['reservation_id']][] = $row;
    }
}

// Line items for the transaction created when a reservation got processed
// (fetched independently of the Orders tab's own date filter, so the
// created transaction always shows even if it falls outside that range).
$resSaleItemsBySale = [];
$resSaleIds = array_values(array_unique(array_filter(array_column($reservations, 'sale_id'))));
if ($resSaleIds) {
    $ph = implode(',', array_fill(0, count($resSaleIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT sale_id, product_name, unit_price, quantity, line_total
         FROM sale_items WHERE sale_id IN ($ph) ORDER BY id"
    );
    $stmt->execute($resSaleIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $resSaleItemsBySale[(int) $row['sale_id']][] = $row;
    }
}

$resCounts = ['all' => 0, 'reserved' => 0, 'fulfilled' => 0, 'cancelled' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM reservations GROUP BY status") as $row) {
    $resCounts[$row['status']] = (int) $row['c'];
    $resCounts['all'] += (int) $row['c'];
}

// ---------------------------------------------------------------
// Order purchase history (completed sales rung up at the counter)
// ---------------------------------------------------------------
$ordSql = "SELECT s.id, s.receipt_no, s.created_at, s.total,
                  GROUP_CONCAT(CONCAT(si.product_name, ' x', si.quantity) SEPARATOR ', ') AS items
           FROM sales s
           JOIN sale_items si ON si.sale_id = s.id
           WHERE s.status = 'completed'
             AND s.created_at BETWEEN :from AND :to";
$ordParams = [
    ':from' => $ordFrom . ' 00:00:00',
    ':to'   => $ordTo . ' 23:59:59',
];
if ($ordQuery !== '') {
    $ordSql .= " AND s.receipt_no LIKE :q";
    $ordParams[':q'] = '%' . $ordQuery . '%';
}
$ordSql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($ordSql);
$stmt->execute($ordParams);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Full line items per sale, for the "view full details" modal.
$ordItemsBySale = [];
$ordIds = array_column($orders, 'id');
if ($ordIds) {
    $ph = implode(',', array_fill(0, count($ordIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT sale_id, product_name, unit_price, quantity, line_total
         FROM sale_items WHERE sale_id IN ($ph) ORDER BY id"
    );
    $stmt->execute($ordIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ordItemsBySale[(int) $row['sale_id']][] = $row;
    }
}

$ordTotalSum = 0.0;
foreach ($orders as $o) {
    $ordTotalSum += (float) $o['total'];
}

// ---------------------------------------------------------------
// Build the JSON payloads the JS modal reads from (no extra fetches
// needed — full detail ships with the page).
// ---------------------------------------------------------------
$resDetails = [];
foreach ($reservations as $r) {
    $resDetails['res-' . $r['id']] = [
        'kind'             => 'reservation',
        'reservation_no'   => $r['reservation_no'],
        'status'           => $r['status'],
        'customer_name'    => $r['customer_name'],
        'customer_contact' => $r['customer_contact'],
        'notes'            => $r['notes'],
        'staff_email'      => $r['staff_email'],
        'created_at'       => (new DateTime($r['created_at']))->format('M j, Y g:i A'),
        'fulfilled_at'     => $r['fulfilled_at'] ? (new DateTime($r['fulfilled_at']))->format('M j, Y g:i A') : null,
        'cancelled_at'     => $r['cancelled_at'] ? (new DateTime($r['cancelled_at']))->format('M j, Y g:i A') : null,
        'subtotal'         => (float) $r['subtotal'],
        'discount'         => (float) $r['discount'],
        'promotion_name'   => $r['promotion_name'],
        'total'            => (float) $r['total'],
        'items'            => array_map(function ($it) {
            return [
                'product_id' => (int) $it['product_id'],
                'name'       => $it['product_name'],
                'unit_price' => (float) $it['unit_price'],
                'quantity'   => (int) $it['quantity'],
                'discount'   => (float) $it['discount_amount'],
                'line_total' => (float) $it['line_total'],
                'promotion'  => $it['promotion_name'],
            ];
        }, $resItemsByRes[(int) $r['id']] ?? []),
        'sale' => $r['sale_id'] ? [
            'sale_id'    => (int) $r['sale_id'],
            'receipt_no' => $r['sale_receipt_no'],
            'created_at' => (new DateTime($r['sale_created_at']))->format('M j, Y g:i A'),
            'total'      => (float) $r['sale_total'],
            'items'      => array_map(function ($it) {
                return [
                    'name'       => $it['product_name'],
                    'unit_price' => (float) $it['unit_price'],
                    'quantity'   => (int) $it['quantity'],
                    'line_total' => (float) $it['line_total'],
                ];
            }, $resSaleItemsBySale[(int) $r['sale_id']] ?? []),
        ] : null,
    ];
}

$ordDetails = [];
foreach ($orders as $o) {
    $ordDetails['ord-' . $o['id']] = [
        'kind'         => 'order',
        'receipt_no'   => $o['receipt_no'],
        'created_at'   => (new DateTime($o['created_at']))->format('M j, Y g:i A'),
        'total'        => (float) $o['total'],
        'items'        => array_map(function ($it) {
            return [
                'name'       => $it['product_name'],
                'unit_price' => (float) $it['unit_price'],
                'quantity'   => (int) $it['quantity'],
                'line_total' => (float) $it['line_total'],
            ];
        }, $ordItemsBySale[(int) $o['id']] ?? []),
    ];
}

$allDetails = $resDetails + $ordDetails;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="/../dashboard.css">
    <link rel="stylesheet" href="/orm/orders.css">
    <link rel="stylesheet" href="/orm/order-history.css">
    <link rel="stylesheet" href="/../idle-timeout.css">
</head>
<body>
<div class="app-shell">

    <?php
    $__sidebarFile = ($userRole === 'cashier') ? '/../sidebar-cashier.php' : '/../sidebar.php';
    include __DIR__ . '/' . $__sidebarFile;
    ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-clock-rotate-left"></i> Purchase History</h1>
                    <p>Reserved purchases and order purchase history for Orders &amp; Reservations.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <?php if ($userRole !== 'cashier') include __DIR__ . '/../notif-bell.php'; ?>
                <div class="user-chip">
                    <div class="avatar"><?= htmlspecialchars(strtoupper(substr($userEmail, 0, 1))) ?></div>
                    <div class="who">
                        <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                        <span>Staff</span>
                    </div>
                    <a href="/../logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content">

            <div class="type-toggle" id="historyTabToggle" role="tablist">
                <button class="type-btn <?= $activeTab === 'reservations' ? 'active' : '' ?>" data-tab="reservations" role="tab" aria-selected="<?= $activeTab === 'reservations' ? 'true' : 'false' ?>">
                    <i class="fa-solid fa-clock"></i> Reserved Purchases
                </button>
                <button class="type-btn <?= $activeTab === 'orders' ? 'active' : '' ?>" data-tab="orders" role="tab" aria-selected="<?= $activeTab === 'orders' ? 'true' : 'false' ?>">
                    <i class="fa-solid fa-receipt"></i> Order Purchase History
                </button>
            </div>

            <!-- ===================== Reserved purchases ===================== -->
            <section class="history-pane" id="pane-reservations" <?= $activeTab === 'reservations' ? '' : 'style="display:none;"' ?>>
                <div class="panel history-panel">
                    <div class="history-panel-head">
                        <h3><i class="fa-solid fa-clock"></i> Reserved Purchases</h3>
                        <form class="history-filters" method="get">
                            <input type="hidden" name="tab" value="reservations">
                            <input type="text" name="res_q" class="pos-input" placeholder="Search reservation # or customer"
                                   value="<?= htmlspecialchars($resQuery) ?>">
                            <select name="res_status" class="pos-input">
                                <option value="all"       <?= $resStatus === 'all' ? 'selected' : '' ?>>All statuses (<?= $resCounts['all'] ?>)</option>
                                <option value="reserved"  <?= $resStatus === 'reserved' ? 'selected' : '' ?>>Reserved (<?= $resCounts['reserved'] ?>)</option>
                                <option value="fulfilled" <?= $resStatus === 'fulfilled' ? 'selected' : '' ?>>Fulfilled (<?= $resCounts['fulfilled'] ?>)</option>
                                <option value="cancelled" <?= $resStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled (<?= $resCounts['cancelled'] ?>)</option>
                            </select>
                            <button type="submit" class="hist-btn"><i class="fa-solid fa-filter"></i> Filter</button>
                        </form>
                    </div>

                    <div class="table-scroll">
                        <table class="transactions-table history-table">
                            <thead>
                                <tr>
                                    <th>Reservation No.</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr class="empty-row"><td colspan="7">No reservations match these filters.</td></tr>
                            <?php else: foreach ($reservations as $r): ?>
                                <tr class="hist-row" data-detail-key="res-<?= (int) $r['id'] ?>" onclick="openHistModal('res-<?= (int) $r['id'] ?>')">
                                    <td><?= htmlspecialchars($r['reservation_no']) ?></td>
                                    <td><?= htmlspecialchars($r['customer_name']) ?></td>
                                    <td><?= htmlspecialchars((new DateTime($r['created_at']))->format('M j, Y g:i A')) ?></td>
                                    <td class="hist-items-cell"><?= htmlspecialchars($r['items'] ?? '') ?></td>
                                    <td><?= peso($r['total']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($r['status']) ?>"><?= ucfirst(htmlspecialchars($r['status'])) ?></span>
                                        <?php if ($r['status'] === 'fulfilled' && !empty($r['sale_receipt_no'])): ?>
                                        <div class="hist-receipt-tag"><i class="fa-solid fa-receipt"></i> <?= htmlspecialchars($r['sale_receipt_no']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><button type="button" class="view-btn" data-detail-key="res-<?= (int) $r['id'] ?>" onclick="event.stopPropagation(); openHistModal('res-<?= (int) $r['id'] ?>')"><i class="fa-solid fa-eye"></i> View</button></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===================== Order purchase history ===================== -->
            <section class="history-pane" id="pane-orders" <?= $activeTab === 'orders' ? '' : 'style="display:none;"' ?>>
                <div class="panel history-panel">
                    <div class="history-panel-head">
                        <h3><i class="fa-solid fa-receipt"></i> Order Purchase History</h3>
                        <form class="history-filters" method="get">
                            <input type="hidden" name="tab" value="orders">
                            <input type="text" name="ord_q" class="pos-input" placeholder="Search receipt #"
                                   value="<?= htmlspecialchars($ordQuery) ?>">
                            <input type="date" name="ord_from" class="pos-input" value="<?= htmlspecialchars($ordFrom) ?>">
                            <input type="date" name="ord_to" class="pos-input" value="<?= htmlspecialchars($ordTo) ?>">
                            <button type="submit" class="hist-btn"><i class="fa-solid fa-filter"></i> Filter</button>
                        </form>
                    </div>

                    <div class="hist-summary">
                        <span><?= count($orders) ?> transaction<?= count($orders) === 1 ? '' : 's' ?></span>
                        <strong><?= peso($ordTotalSum) ?> total</strong>
                    </div>

                    <div class="table-scroll">
                        <table class="transactions-table history-table">
                            <thead>
                                <tr>
                                    <th>Receipt No.</th>
                                    <th>Date &amp; Time</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($orders)): ?>
                                <tr class="empty-row"><td colspan="5">No completed orders in this date range.</td></tr>
                            <?php else: foreach ($orders as $o): ?>
                                <tr class="hist-row" data-detail-key="ord-<?= (int) $o['id'] ?>" onclick="openHistModal('ord-<?= (int) $o['id'] ?>')">
                                    <td><?= htmlspecialchars($o['receipt_no']) ?></td>
                                    <td><?= htmlspecialchars((new DateTime($o['created_at']))->format('M j, Y g:i A')) ?></td>
                                    <td class="hist-items-cell"><?= htmlspecialchars($o['items'] ?? '') ?></td>
                                    <td><?= peso($o['total']) ?></td>
                                    <td><button type="button" class="view-btn" data-detail-key="ord-<?= (int) $o['id'] ?>" onclick="event.stopPropagation(); openHistModal('ord-<?= (int) $o['id'] ?>')"><i class="fa-solid fa-eye"></i> View</button></td>
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

<!-- ===================== Full-detail modal (shared) ===================== -->
<div class="hist-modal-overlay" id="histModalOverlay">
    <div class="hist-modal">
        <button type="button" class="hist-modal-close" id="histModalClose" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        <div class="hist-modal-head">
            <h3 id="histModalTitle"></h3>
            <span class="status-badge" id="histModalStatus" style="display:none;"></span>
        </div>
        <div class="hist-modal-meta" id="histModalMeta"></div>
        <div class="hist-modal-items" id="histModalItems"></div>
        <div class="hist-modal-totals" id="histModalTotals"></div>
    </div>
</div>

<script>
    window.RAMYUM_HISTORY_DETAILS = <?= json_encode($allDetails, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/orm/order-history.js"></script>
<script src="/../sidebar.js"></script>
<script src="/../notif-bell.js"></script>
<script src="/../idle-timeout.js"></script>
</body>
</html>