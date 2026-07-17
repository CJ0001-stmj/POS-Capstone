<?php
session_start();
require_once __DIR__ . '/db.php';

// Guard: bounce anyone without a valid session back to the login screen.
if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$userEmail = $_SESSION['user_email'];
$initials = strtoupper(substr($userEmail, 0, 1));

$pdo = get_db_connection();

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

function peso($n): string {
    return '₱' . number_format((float)$n, 0);
}

/**
 * Total sales + profit (revenue minus each line's current product cost)
 * for completed sales within a date range.
 */
function sales_summary(PDO $pdo, string $start, string $end): array {
    $sql = "SELECT
                COALESCE(SUM(s.total), 0)               AS sales,
                COALESCE(SUM(ip.profit), 0)              AS profit,
                COUNT(DISTINCT s.id)                     AS trans_count
            FROM sales s
            LEFT JOIN (
                SELECT si.sale_id,
                       SUM((si.unit_price - COALESCE(p.cost, 0)) * si.quantity) AS profit
                FROM sale_items si
                LEFT JOIN products p ON p.id = si.product_id
                GROUP BY si.sale_id
            ) ip ON ip.sale_id = s.id
            WHERE s.status = 'completed'
              AND s.created_at BETWEEN :start AND :end";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['start' => $start, 'end' => $end]);
    return $stmt->fetch() ?: ['sales' => 0, 'profit' => 0, 'trans_count' => 0];
}

function pct_trend(float $current, float $previous): array {
    if ($previous <= 0) {
        return $current > 0 ? ['label' => 'New', 'dir' => 'up'] : ['label' => '—', 'dir' => 'flat'];
    }
    $change = (($current - $previous) / $previous) * 100;
    if (abs($change) < 0.05) {
        return ['label' => '0.0%', 'dir' => 'flat'];
    }
    $dir = $change >= 0 ? 'up' : 'down';
    return ['label' => ($change >= 0 ? '+' : '') . number_format($change, 1) . '%', 'dir' => $dir];
}

// ---------------------------------------------------------------
// Date ranges
// ---------------------------------------------------------------
$now             = new DateTime();
$todayStart      = (clone $now)->setTime(0, 0, 0);
$todayEnd        = (clone $now)->setTime(23, 59, 59);
$yesterdayStart  = (clone $todayStart)->modify('-1 day');
$yesterdayEnd    = (clone $todayEnd)->modify('-1 day');

$monthStart      = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
$dayOfMonth      = (int)$now->format('j');
$lastMonthStart  = (clone $monthStart)->modify('-1 month');
$lastMonthMtdEnd = (clone $lastMonthStart)->modify('+' . ($dayOfMonth - 1) . ' days')->setTime(23, 59, 59);

$fmt = 'Y-m-d H:i:s';

$today     = sales_summary($pdo, $todayStart->format($fmt), $todayEnd->format($fmt));
$yesterday = sales_summary($pdo, $yesterdayStart->format($fmt), $yesterdayEnd->format($fmt));
$month     = sales_summary($pdo, $monthStart->format($fmt), $now->format($fmt));
$lastMonth = sales_summary($pdo, $lastMonthStart->format($fmt), $lastMonthMtdEnd->format($fmt));

$todaySalesTrend  = pct_trend((float)$today['sales'], (float)$yesterday['sales']);
$todayProfitTrend = pct_trend((float)$today['profit'], (float)$yesterday['profit']);
$monthSalesTrend  = pct_trend((float)$month['sales'], (float)$lastMonth['sales']);
$monthProfitTrend = pct_trend((float)$month['profit'], (float)$lastMonth['profit']);

$kpis = [
    ['icon' => 'fa-cash-register', 'label' => "Today's Sales",    'value' => peso($today['sales']),    'trend' => $todaySalesTrend['label'],  'dir' => $todaySalesTrend['dir'],  'card' => 'today-card'],
    ['icon' => 'fa-sack-dollar',   'label' => "Today's Profit",   'value' => peso($today['profit']),   'trend' => $todayProfitTrend['label'], 'dir' => $todayProfitTrend['dir'], 'card' => 'profit-card'],
    ['icon' => 'fa-calendar-days', 'label' => 'This Month Sales', 'value' => peso($month['sales']),    'trend' => $monthSalesTrend['label'],  'dir' => $monthSalesTrend['dir'],  'card' => 'month-card'],
    ['icon' => 'fa-coins',         'label' => 'This Month Profit','value' => peso($month['profit']),   'trend' => $monthProfitTrend['label'], 'dir' => $monthProfitTrend['dir'], 'card' => 'month-profit-card'],
];

// ---------------------------------------------------------------
// Sales analytics — last 7 days (zero-filled for days with no sales)
// ---------------------------------------------------------------
$chartStart = (clone $todayStart)->modify('-6 days');
$stmt = $pdo->prepare(
    "SELECT DATE(s.created_at) AS d,
            SUM(s.total) AS sales,
            SUM(ip.profit) AS profit
     FROM sales s
     LEFT JOIN (
         SELECT si.sale_id,
                SUM((si.unit_price - COALESCE(p.cost, 0)) * si.quantity) AS profit
         FROM sale_items si
         LEFT JOIN products p ON p.id = si.product_id
         GROUP BY si.sale_id
     ) ip ON ip.sale_id = s.id
     WHERE s.status = 'completed' AND s.created_at >= :start
     GROUP BY DATE(s.created_at)"
);
$stmt->execute(['start' => $chartStart->format($fmt)]);
$byDay = [];
foreach ($stmt->fetchAll() as $row) {
    $byDay[$row['d']] = $row;
}

$salesChart = ['labels' => [], 'sales' => [], 'profit' => []];
for ($i = 6; $i >= 0; $i--) {
    $d = (clone $todayStart)->modify("-$i days");
    $key = $d->format('Y-m-d');
    $salesChart['labels'][] = $d->format('M j');
    $salesChart['sales'][]  = isset($byDay[$key]) ? round((float)$byDay[$key]['sales'], 2) : 0;
    $salesChart['profit'][] = isset($byDay[$key]) ? round((float)$byDay[$key]['profit'], 2) : 0;
}

// ---------------------------------------------------------------
// Product performance ranking — top sellers by revenue (all completed
// sales on record), with a trend arrow from the last 7 vs. prior 7 days
// ---------------------------------------------------------------
$stmt = $pdo->query(
    "SELECT si.product_name AS name,
            SUM(si.quantity) AS units,
            SUM(si.line_total) AS revenue
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     WHERE s.status = 'completed'
     GROUP BY si.product_name
     ORDER BY revenue DESC
     LIMIT 5"
);
$topProducts = $stmt->fetchAll();

$prior7Start = (clone $chartStart)->modify('-7 days');
$stmt = $pdo->prepare(
    "SELECT si.product_name AS name,
            SUM(CASE WHEN s.created_at >= :recentStart1 THEN si.quantity ELSE 0 END) AS recent_units,
            SUM(CASE WHEN s.created_at < :recentStart2 THEN si.quantity ELSE 0 END)  AS prior_units
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     WHERE s.status = 'completed' AND s.created_at >= :priorStart
     GROUP BY si.product_name"
);
$stmt->execute([
    'recentStart1' => $chartStart->format($fmt),
    'recentStart2' => $chartStart->format($fmt),
    'priorStart'   => $prior7Start->format($fmt),
]);
$trendByProduct = [];
foreach ($stmt->fetchAll() as $row) {
    $trendByProduct[$row['name']] = $row;
}

$productRanking = [];
$rank = 1;
foreach ($topProducts as $p) {
    $t = $trendByProduct[$p['name']] ?? ['recent_units' => 0, 'prior_units' => 0];
    if ($t['recent_units'] > $t['prior_units']) {
        $dir = 'up';
    } elseif ($t['recent_units'] < $t['prior_units']) {
        $dir = 'down';
    } else {
        $dir = 'flat';
    }
    $productRanking[] = [
        'rank'    => $rank++,
        'name'    => $p['name'],
        'units'   => (int)$p['units'],
        'revenue' => peso($p['revenue']),
        'dir'     => $dir,
    ];
}

// ---------------------------------------------------------------
// Cashier / staff active status — derived from login_audit. A user
// whose most recent successful login was today counts as active;
// otherwise offline. (No logout tracking exists yet, so this is a
// best-effort signal, not a live session list.)
// ---------------------------------------------------------------
$stmt = $pdo->query(
    "SELECT u.email, la.last_login
     FROM users u
     JOIN (
         SELECT email, MAX(created_at) AS last_login
         FROM login_audit
         WHERE success = 1
         GROUP BY email
     ) la ON la.email = u.email
     ORDER BY la.last_login DESC
     LIMIT 8"
);
$cashiers = [];
foreach ($stmt->fetchAll() as $row) {
    $lastLogin = new DateTime($row['last_login']);
    $isToday = $lastLogin->format('Y-m-d') === $now->format('Y-m-d');
    $cashiers[] = [
        'name'   => explode('@', $row['email'])[0],
        'role'   => 'Staff',
        'status' => $isToday ? 'active' : 'offline',
        'note'   => 'Last login ' . $lastLogin->format('M j, g:i A'),
    ];
}

// ---------------------------------------------------------------
// Low-stock products, straight from the products table
// ---------------------------------------------------------------
$stmt = $pdo->query(
    "SELECT name, stock_qty, low_stock_threshold
     FROM products
     WHERE is_active = 1 AND stock_qty <= low_stock_threshold
     ORDER BY stock_qty ASC"
);
$lowStock = [];
foreach ($stmt->fetchAll() as $row) {
    $critical = $row['stock_qty'] <= max(1, (int)round($row['low_stock_threshold'] / 2));
    $lowStock[] = [
        'name'    => $row['name'],
        'stock'   => (int)$row['stock_qty'],
        'reorder' => (int)$row['low_stock_threshold'],
        'status'  => $critical ? 'critical' : 'low',
    ];
}

// ---------------------------------------------------------------
// Today's transactions
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT s.receipt_no,
            s.created_at,
            s.total,
            GROUP_CONCAT(CONCAT(si.product_name, ' x', si.quantity) SEPARATOR ', ') AS items,
            SUM((si.unit_price - COALESCE(p.cost, 0)) * si.quantity) AS profit
     FROM sales s
     JOIN sale_items si ON si.sale_id = s.id
     LEFT JOIN products p ON p.id = si.product_id
     WHERE s.created_at BETWEEN :start AND :end AND s.status = 'completed'
     GROUP BY s.id
     ORDER BY s.created_at DESC"
);
$stmt->execute(['start' => $todayStart->format($fmt), 'end' => $todayEnd->format($fmt)]);
$transactions = [];
foreach ($stmt->fetchAll() as $row) {
    $transactions[] = [
        'id'      => $row['receipt_no'],
        'time'    => (new DateTime($row['created_at']))->format('M j, Y g:i A'),
        'items'   => $row['items'],
        'amount'  => peso($row['total']),
        'profit'  => peso($row['profit']),
    ];
}

$modules = [
    ['icon' => 'fa-cash-register', 'title' => 'Point of Sale & Transactions',    'href' => 'pos.php'],
    ['icon' => 'fa-dolly',         'title' => 'Purchase Request Management',     'href' => 'purchase-requests.php'],
    ['icon' => 'fa-bowl-food',     'title' => 'Customer Order & Reservation',    'href' => 'orders.php'],
    ['icon' => 'fa-chart-line',    'title' => 'Sales & Profitability Analytics', 'href' => 'analytics.php'],
    ['icon' => 'fa-tags',          'title' => 'Promotions & Campaign Manager',   'href' => 'promotions.php'],
    ['icon' => 'fa-user-shield',   'title' => 'Audit, Compliance & User Access', 'href' => 'login_audit.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RAM-YUM STORE</title>
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
                    <h1>Welcome back 🍜</h1>
                    <p id="current-date">Here's what's happening across the store today.</p>
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
            <p class="section-label">Today at a glance</p>
            <div class="kpi-strip">
                <?php foreach ($kpis as $k): ?>
                <div class="kpi-card stat-card <?= $k['card'] ?>">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid <?= $k['icon'] ?>"></i></div>
                        <span class="kpi-trend <?= $k['dir'] ?>"><?= htmlspecialchars($k['trend']) ?></span>
                    </div>
                    <div class="kpi-value stat-value"><?= htmlspecialchars($k['value']) ?></div>
                    <div class="kpi-label">
                        <?= htmlspecialchars($k['label']) ?>
                        <?php if ($k['card'] === 'today-card'): ?>
                            &middot; <span id="profit-margin">calculating…</span>
                        <?php elseif ($k['card'] === 'month-card'): ?>
                            &middot; <span id="month-margin">calculating…</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <p class="section-label">Sales analytics</p>
            <div class="panel chart-panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-chart-line"></i> Sales &amp; profit — last 7 days</h3>
                    <div class="chart-legend">
                        <span><i class="dot dot-sales"></i> Sales</span>
                        <span><i class="dot dot-profit"></i> Profit (dashed)</span>
                    </div>
                </div>
                <div class="chart-wrap">
                    <canvas id="salesChart" role="img"
                        aria-label="Line chart of daily sales and profit for the last 7 days, from <?= htmlspecialchars($salesChart['labels'][0]) ?> to <?= htmlspecialchars($salesChart['labels'][6]) ?>"
                    ><?php foreach ($salesChart['labels'] as $i => $lbl): ?><?= htmlspecialchars($lbl) ?>: <?= peso($salesChart['sales'][$i]) ?> sales, <?= peso($salesChart['profit'][$i]) ?> profit. <?php endforeach; ?></canvas>
                </div>
            </div>

            <div class="panel-row">
                <div class="panel">
                    <div class="panel-head">
                        <h3><i class="fa-solid fa-ranking-star"></i> Product performance ranking</h3>
                    </div>
                    <?php if (empty($productRanking)): ?>
                        <p class="empty-note">No completed sales on record yet.</p>
                    <?php else: ?>
                    <table class="ranking-table">
                        <thead>
                            <tr><th>#</th><th>Product</th><th>Units</th><th>Revenue</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($productRanking as $p): ?>
                            <tr>
                                <td class="rank-cell">
                                    <span class="rank-badge rank-<?= $p['rank'] <= 3 ? $p['rank'] : 'other' ?>"><?= $p['rank'] ?></span>
                                </td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= (int)$p['units'] ?></td>
                                <td><?= htmlspecialchars($p['revenue']) ?></td>
                                <td>
                                    <?php if ($p['dir'] === 'up'): ?>
                                        <i class="fa-solid fa-arrow-trend-up trend-up"></i>
                                    <?php elseif ($p['dir'] === 'down'): ?>
                                        <i class="fa-solid fa-arrow-trend-down trend-down"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-minus trend-flat"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h3><i class="fa-solid fa-user-clock"></i> Cashier active status</h3>
                    </div>
                    <?php if (empty($cashiers)): ?>
                        <p class="empty-note">No staff logins recorded yet.</p>
                    <?php else: ?>
                    <ul class="cashier-list">
                        <?php foreach ($cashiers as $c): ?>
                        <li>
                            <span class="cashier-avatar"><?= htmlspecialchars(strtoupper(substr($c['name'], 0, 1))) ?></span>
                            <div class="cashier-info">
                                <strong><?= htmlspecialchars($c['name']) ?></strong>
                                <span><?= htmlspecialchars($c['role']) ?></span>
                            </div>
                            <div class="cashier-status">
                                <span class="status-dot status-<?= $c['status'] ?>"></span>
                                <span class="status-text"><?= ucfirst($c['status']) ?></span>
                                <small><?= htmlspecialchars($c['note']) ?></small>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h3><i class="fa-solid fa-box-open"></i> Low-stock products</h3>
                    </div>
                    <?php if (empty($lowStock)): ?>
                        <p class="empty-note">Nothing below its reorder threshold right now.</p>
                    <?php else: ?>
                    <table class="low-stock-table">
                        <thead>
                            <tr><th>Product</th><th>Stock</th><th>Reorder at</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lowStock as $ls): ?>
                            <tr>
                                <td><?= htmlspecialchars($ls['name']) ?></td>
                                <td><?= (int)$ls['stock'] ?></td>
                                <td><?= (int)$ls['reorder'] ?></td>
                                <td><span class="stock-badge stock-<?= $ls['status'] ?>"><?= ucfirst($ls['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <p class="section-label">Daily transactions</p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-receipt"></i> Today's transactions</h3>
                    <div class="panel-actions">
                        <button class="action-btn" title="Export CSV"><i class="fa-solid fa-file-export"></i> Export</button>
                        <button class="action-btn" title="Print"><i class="fa-solid fa-print"></i> Print</button>
                        <button class="action-btn" title="Settings"><i class="fa-solid fa-gear"></i> Settings</button>
                    </div>
                </div>
                <table class="transactions-table">
                    <thead>
                        <tr><th>Transaction ID</th><th>Date &amp; Time</th><th>Items</th><th>Total Amount</th><th>Profit</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr class="empty-row"><td colspan="5">No transactions recorded yet today.</td></tr>
                    <?php else: foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['id']) ?></td>
                            <td><?= htmlspecialchars($t['time']) ?></td>
                            <td><?= htmlspecialchars($t['items']) ?></td>
                            <td><?= htmlspecialchars($t['amount']) ?></td>
                            <td><?= htmlspecialchars($t['profit']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="section-label">Store management modules</p>
            <div class="module-grid-compact">
                <?php foreach ($modules as $m): ?>
                <a class="module-chip" href="<?= htmlspecialchars($m['href']) ?>">
                    <span class="module-chip-icon"><i class="fa-solid <?= $m['icon'] ?>"></i></span>
                    <span><?= htmlspecialchars($m['title']) ?></span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<script>
window.SALES_CHART_DATA = <?= json_encode($salesChart, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="dashboard.js"></script>
<script src="sidebar.js"></script>
</body>
</html>