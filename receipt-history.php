<?php
session_start();
require_once __DIR__ . '/db.php';

// session_start() emits its own Cache-Control header (governed by
// session.cache_limiter in php.ini) which on some hosts defaults to a
// cacheable value. Force it back to no-store so a browser/proxy never
// serves a stale copy of this page right after a checkout.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Guard: bounce anyone without a valid session back to the login screen.
if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$userEmail = $_SESSION['user_email'];
$initials  = strtoupper(substr($userEmail, 0, 1));

$pdo = get_db_connection();

function peso($n): string {
    return '₱' . number_format((float)$n, 0);
}

// ---------------------------------------------------------------
// Filters
// ---------------------------------------------------------------
$allowedRanges = ['today' => 'Today', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days', 'all' => 'All Time'];
$range = $_GET['range'] ?? 'all';
if (!array_key_exists($range, $allowedRanges)) {
    $range = 'all';
}
$search        = trim((string)($_GET['search'] ?? ''));
$exactReceipt  = trim((string)($_GET['receipt'] ?? ''));
$productId     = isset($_GET['product']) ? (int)$_GET['product'] : 0;
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 15;

// Landing here from a just-completed POS sale: never let the date range
// filter hide the receipt the cashier was just linked to.
if ($exactReceipt !== '') {
    $range = 'all';
}

// Filtered-from-Stock-Monitoring banner needs the product name
$filteredProductName = null;
if ($productId > 0) {
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = :id");
    $stmt->execute(['id' => $productId]);
    $filteredProductName = $stmt->fetchColumn() ?: null;
    if ($filteredProductName === null) {
        $productId = 0; // ignore an invalid product id
    }
}

$now = new DateTime();
$fmt = 'Y-m-d H:i:s';
$end = (clone $now)->format($fmt);
switch ($range) {
    case 'today':
        $start = (clone $now)->setTime(0, 0, 0)->format($fmt);
        break;
    case '7d':
        $start = (clone $now)->modify('-6 days')->setTime(0, 0, 0)->format($fmt);
        break;
    case '30d':
        $start = (clone $now)->modify('-29 days')->setTime(0, 0, 0)->format($fmt);
        break;
    case 'all':
    default:
        $start = '1970-01-01 00:00:00';
        break;
}

// ---------------------------------------------------------------
// Build WHERE clause shared by the count query and the page query
// ---------------------------------------------------------------
$where  = "WHERE s.created_at >= :start";
$params = ['start' => $start];

if ($exactReceipt !== '') {
    $where .= " AND s.receipt_no = :exact_receipt";
    $params['exact_receipt'] = $exactReceipt;
} elseif ($search !== '') {
    $where .= " AND s.receipt_no LIKE :search";
    $params['search'] = '%' . $search . '%';
}
if ($productId > 0) {
    $where .= " AND EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND si.product_id = :pid)";
    $params['pid'] = $productId;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM sales s $where");
$countStmt->execute($params);
$totalReceipts = (int)$countStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($totalReceipts / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

$sql = "SELECT s.id, s.receipt_no, s.cashier_email, s.created_at, s.item_count,
               s.subtotal, s.discount, s.promotion_name, s.total, s.payment_method, s.status
        FROM sales s
        $where
        ORDER BY s.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Pull line items for every receipt on this page in one query
$lineItemsBySale = [];
if (!empty($sales)) {
    $saleIds = array_column($sales, 'id');
    $in = implode(',', array_fill(0, count($saleIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT sale_id, product_name, unit_price, quantity, line_total
         FROM sale_items
         WHERE sale_id IN ($in)
         ORDER BY id ASC"
    );
    $stmt->execute($saleIds);
    foreach ($stmt->fetchAll() as $row) {
        $lineItemsBySale[$row['sale_id']][] = $row;
    }
}

function build_query(array $overrides, array $current): string {
    $merged = array_merge($current, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return htmlspecialchars('receipt-history.php?' . http_build_query($merged));
}
$currentParams = ['range' => $range, 'search' => $search, 'product' => $productId ?: null, 'receipt' => $exactReceipt ?: null];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt History - RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
<div class="app-shell">

    <?php
    $__sidebarFile = (($_SESSION['user_role'] ?? '') === 'cashier') ? 'sidebar-cashier.php' : 'sidebar.php';
    include __DIR__ . '/' . $__sidebarFile;
    ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1>Receipt History</h1>
                    <p>Digital receipt directory — review exactly what each transaction purchased.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <?php if (($_SESSION['user_role'] ?? '') !== 'cashier') include __DIR__ . '/notif-bell.php'; ?>
                <div class="user-chip">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="who">
                        <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                        <span>Staff</span>
                    </div>
                    <a href="logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content">
            <?php if ($exactReceipt !== ''): ?>
            <div class="filter-banner">
                <span><i class="fa-solid fa-receipt"></i> Showing receipt <strong><?= htmlspecialchars($exactReceipt) ?></strong> from your last checkout</span>
                <a href="<?= build_query(['receipt' => null], $currentParams) ?>">Show all receipts <i class="fa-solid fa-xmark"></i></a>
            </div>
            <?php elseif ($filteredProductName !== null): ?>
            <div class="filter-banner">
                <span><i class="fa-solid fa-filter"></i> Showing receipts that include: <strong><?= htmlspecialchars($filteredProductName) ?></strong></span>
                <a href="<?= build_query(['product' => null], $currentParams) ?>">Clear <i class="fa-solid fa-xmark"></i></a>
            </div>
            <?php endif; ?>

            <p class="section-label">All Receipts</p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-receipt"></i> <?= $totalReceipts ?> receipt<?= $totalReceipts === 1 ? '' : 's' ?> on record</h3>
                </div>

                <form method="get" class="filter-bar">
                    <?php if ($productId > 0): ?><input type="hidden" name="product" value="<?= $productId ?>"><?php endif; ?>
                    <select name="range" class="filter-select" onchange="this.form.submit()">
                        <?php foreach ($allowedRanges as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $range === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" class="filter-input" placeholder="Search receipt no…" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="action-btn"><i class="fa-solid fa-filter"></i> Apply</button>
                    <?php if ($search !== '' || $range !== 'all' || $productId > 0): ?>
                    <a href="receipt-history.php" class="action-btn"><i class="fa-solid fa-xmark"></i> Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($sales)): ?>
                    <p class="empty-note">No receipts match this filter.</p>
                <?php else: ?>
                <table class="transactions-table receipt-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Receipt No.</th>
                            <th>Date &amp; Time</th>
                            <th>Cashier</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sales as $s): ?>
                        <tr class="receipt-row" data-target="receipt-<?= $s['id'] ?>" data-receipt="<?= htmlspecialchars($s['receipt_no']) ?>">
                            <td><i class="fa-solid fa-chevron-right receipt-caret"></i></td>
                            <td><strong><?= htmlspecialchars($s['receipt_no']) ?></strong></td>
                            <td><?= htmlspecialchars((new DateTime($s['created_at']))->format('M j, Y g:i A')) ?></td>
                            <td><?= htmlspecialchars($s['cashier_email'] ?? '—') ?></td>
                            <td><?= (int)$s['item_count'] ?></td>
                            <td><?= peso($s['total']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($s['payment_method'])) ?></td>
                            <td><span class="stock-badge <?= $s['status'] === 'voided' ? 'stock-critical' : 'stock-ok' ?>"><?= ucfirst($s['status']) ?></span></td>
                        </tr>
                        <tr class="receipt-detail-row" id="receipt-<?= $s['id'] ?>" style="display:none;">
                            <td colspan="8">
                                <div class="receipt-detail">
                                    <div class="receipt-detail-lines">
                                        <?php foreach ($lineItemsBySale[$s['id']] ?? [] as $li): ?>
                                        <div class="receipt-line">
                                            <span><?= htmlspecialchars($li['product_name']) ?> &times; <?= (int)$li['quantity'] ?></span>
                                            <span><?= peso($li['line_total']) ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="receipt-detail-summary">
                                        <div><span>Subtotal</span><span><?= peso($s['subtotal']) ?></span></div>
                                        <?php if ((float)$s['discount'] > 0): ?>
                                        <div><span>Discount<?= $s['promotion_name'] ? ' (' . htmlspecialchars($s['promotion_name']) . ')' : '' ?></span><span>-<?= peso($s['discount']) ?></span></div>
                                        <?php endif; ?>
                                        <div class="receipt-detail-total"><span>Total</span><span><?= peso($s['total']) ?></span></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= build_query(['page' => $page - 1], $currentParams) ?>" class="action-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <span class="pagination-label">Page <?= $page ?> of <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= build_query(['page' => $page + 1], $currentParams) ?>" class="action-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
window.RAMYUM_AUTO_OPEN_RECEIPT = <?= json_encode($exactReceipt !== '' ? $exactReceipt : null) ?>;
</script>
<script src="sidebar.js"></script>
<script src="notif-bell.js"></script>
<script src="receipt-history.js"></script>
</body>
</html>