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

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

function peso($n): string {
    return '₱' . number_format((float)$n, 0);
}

/**
 * Stock status for a product row (mirrors the low-stock logic already
 * used on the main dashboard, plus an explicit "out" state).
 */
function stock_status(int $qty, int $threshold): string {
    if ($qty <= 0) return 'out';
    if ($qty <= max(1, (int)round($threshold / 2))) return 'critical';
    if ($qty <= $threshold) return 'low';
    return 'ok';
}

function stock_status_label(string $status): string {
    return match ($status) {
        'out'      => 'Out of Stock',
        'critical' => 'Critical',
        'low'      => 'Low Stock',
        default    => 'In Stock',
    };
}

// ---------------------------------------------------------------
// Filters (period + product name search) — read from GET, all optional
// ---------------------------------------------------------------
$allowedRanges = ['today' => "Today", '7d' => 'Last 7 Days', '30d' => 'Last 30 Days', 'all' => 'All Time'];
$range = $_GET['range'] ?? '30d';
if (!array_key_exists($range, $allowedRanges)) {
    $range = '30d';
}
$q = trim((string)($_GET['q'] ?? ''));

$now  = new DateTime();
$fmt  = 'Y-m-d H:i:s';
$end  = (clone $now)->format($fmt);
switch ($range) {
    case 'today':
        $start = (clone $now)->setTime(0, 0, 0)->format($fmt);
        break;
    case '7d':
        $start = (clone $now)->modify('-6 days')->setTime(0, 0, 0)->format($fmt);
        break;
    case 'all':
        $start = '1970-01-01 00:00:00';
        break;
    case '30d':
    default:
        $start = (clone $now)->modify('-29 days')->setTime(0, 0, 0)->format($fmt);
        break;
}

// ---------------------------------------------------------------
// Purchased Products Overview — every active product, with units sold
// / revenue in the selected period pulled from completed POS sales
// (New Transaction) and receipts (sales/sale_items). Products with no
// sales in the period still show up with 0s so stock can be judged
// against real, current inventory.
// ---------------------------------------------------------------
$sql = "SELECT
            p.id, p.name, p.sku, p.stock_qty, p.low_stock_threshold, p.price,
            c.name AS category,
            COALESCE(sold.units, 0)      AS units_sold,
            COALESCE(sold.revenue, 0)    AS revenue,
            sold.last_sold
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN (
            SELECT si.product_id,
                   SUM(si.quantity)   AS units,
                   SUM(si.line_total) AS revenue,
                   MAX(s.created_at)  AS last_sold
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            WHERE s.status = 'completed' AND s.created_at >= :start
            GROUP BY si.product_id
        ) sold ON sold.product_id = p.id
        WHERE p.is_active = 1" . ($q !== '' ? " AND p.name LIKE :q" : "") . "
        ORDER BY units_sold DESC, p.name ASC";
$stmt = $pdo->prepare($sql);
$params = ['start' => $start];
if ($q !== '') {
    $params['q'] = '%' . $q . '%';
}
$stmt->execute($params);

$productRows = [];
$totalUnitsSold = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

foreach ($stmt->fetchAll() as $row) {
    $status = stock_status((int)$row['stock_qty'], (int)$row['low_stock_threshold']);
    if ($status === 'low' || $status === 'critical') $lowStockCount++;
    if ($status === 'out') $outOfStockCount++;
    $totalUnitsSold += (int)$row['units_sold'];

    $productRows[] = [
        'id'         => (int)$row['id'],
        'name'       => $row['name'],
        'sku'        => $row['sku'],
        'category'   => $row['category'],
        'units_sold' => (int)$row['units_sold'],
        'revenue'    => peso($row['revenue']),
        'stock'      => (int)$row['stock_qty'],
        'reorder'    => (int)$row['low_stock_threshold'],
        'status'     => $status,
        'last_sold'  => $row['last_sold'] ? (new DateTime($row['last_sold']))->format('M j, Y g:i A') : '—',
    ];
}

$totalActiveProducts = count($productRows);

// ---------------------------------------------------------------
// Recent restocks — from the stock_movements audit trail
// ---------------------------------------------------------------
$stmt = $pdo->query(
    "SELECT sm.change_qty, sm.created_at, p.name
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     WHERE sm.reason = 'restock'
     ORDER BY sm.created_at DESC
     LIMIT 8"
);
$recentRestocks = [];
foreach ($stmt->fetchAll() as $row) {
    $recentRestocks[] = [
        'name'    => $row['name'],
        'qty'     => (int)$row['change_qty'],
        'created' => (new DateTime($row['created_at']))->format('M j, Y g:i A'),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Monitoring - RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
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
                    <h1>Stock Monitoring</h1>
                    <p>Purchased products, pulled from completed POS transactions &amp; receipts.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
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
            <p class="section-label">Overview</p>
            <div class="kpi-strip">
                <div class="kpi-card stat-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                    </div>
                    <div class="kpi-value stat-value"><?= $totalActiveProducts ?></div>
                    <div class="kpi-label">Active Products</div>
                </div>
                <div class="kpi-card stat-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                    </div>
                    <div class="kpi-value stat-value"><?= $totalUnitsSold ?></div>
                    <div class="kpi-label">Units Sold (<?= htmlspecialchars($allowedRanges[$range]) ?>)</div>
                </div>
                <div class="kpi-card stat-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                    <div class="kpi-value stat-value"><?= $lowStockCount ?></div>
                    <div class="kpi-label">Low / Critical Stock</div>
                </div>
                <div class="kpi-card stat-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-ban"></i></div>
                    </div>
                    <div class="kpi-value stat-value"><?= $outOfStockCount ?></div>
                    <div class="kpi-label">Out of Stock</div>
                </div>
            </div>

            <p class="section-label">Purchased Products Overview</p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-receipt"></i> Sold via New Transaction (POS)</h3>
                </div>
                <form method="get" class="filter-bar">
                    <select name="range" class="filter-select" onchange="this.form.submit()">
                        <?php foreach ($allowedRanges as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $range === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="q" class="filter-input" placeholder="Search product name…" value="<?= htmlspecialchars($q) ?>">
                    <button type="submit" class="action-btn"><i class="fa-solid fa-filter"></i> Apply</button>
                    <?php if ($q !== '' || $range !== '30d'): ?>
                    <a href="stock-monitoring.php" class="action-btn"><i class="fa-solid fa-xmark"></i> Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($productRows)): ?>
                    <p class="empty-note">No products match this filter.</p>
                <?php else: ?>
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                            <th>Current Stock</th>
                            <th>Reorder At</th>
                            <th>Status</th>
                            <th>Last Sold</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($productRows as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small style="color:var(--ram-muted);"><?= htmlspecialchars($p['sku']) ?></small></td>
                            <td><?= htmlspecialchars($p['category']) ?></td>
                            <td><?= $p['units_sold'] ?></td>
                            <td><?= htmlspecialchars($p['revenue']) ?></td>
                            <td><?= $p['stock'] ?></td>
                            <td><?= $p['reorder'] ?></td>
                            <td><span class="stock-badge stock-<?= $p['status'] ?>"><?= stock_status_label($p['status']) ?></span></td>
                            <td><?= htmlspecialchars($p['last_sold']) ?></td>
                            <td><a class="table-link" href="receipt-history.php?product=<?= $p['id'] ?>" title="View receipts containing this product"><i class="fa-solid fa-clock-rotate-left"></i> Receipts</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <p class="section-label">Recent Restocks</p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-dolly"></i> Latest inventory replenishments</h3>
                </div>
                <?php if (empty($recentRestocks)): ?>
                    <p class="empty-note">No restocks recorded yet.</p>
                <?php else: ?>
                <table class="low-stock-table">
                    <thead>
                        <tr><th>Product</th><th>Quantity Added</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentRestocks as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td>+<?= $r['qty'] ?></td>
                            <td><?= htmlspecialchars($r['created']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script src="sidebar.js"></script>
</body>
</html>