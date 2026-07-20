<?php
session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: /index.php');
    exit;
}
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


$userEmail = $_SESSION['user_email'];
$initials  = strtoupper(substr($userEmail, 0, 1));
$db = get_db_connection();

/* ------------------------------------------------------------------ *
 *  BUSINESS STATUS — headline KPIs
 * ------------------------------------------------------------------ */

// All-time revenue / profit (profit approximated using CURRENT product
// cost, since sale_items only snapshots the sale price, not cost-at-time).
$totals = $db->query(
    "SELECT
        COALESCE(SUM(si.line_total), 0) AS revenue,
        COALESCE(SUM(si.quantity * COALESCE(p.cost, 0)), 0) AS cogs,
        COUNT(DISTINCT s.id) AS txn_count,
        COALESCE(SUM(si.quantity), 0) AS units_sold
     FROM sales s
     JOIN sale_items si ON si.sale_id = s.id
     LEFT JOIN products p ON p.id = si.product_id
     WHERE s.status = 'completed'"
)->fetch();

$allTimeRevenue = (float) $totals['revenue'];
$allTimeProfit  = $allTimeRevenue - (float) $totals['cogs'];
$allTimeMargin  = $allTimeRevenue > 0 ? ($allTimeProfit / $allTimeRevenue) * 100 : 0;
$allTimeTxns    = (int) $totals['txn_count'];
$avgTicket      = $allTimeTxns > 0 ? $allTimeRevenue / $allTimeTxns : 0;

// This month vs last month, for a growth indicator
function periodTotals(PDO $db, string $startExpr, string $endExpr): array {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(si.line_total), 0) AS revenue,
                COALESCE(SUM(si.quantity * COALESCE(p.cost, 0)), 0) AS cogs
         FROM sales s
         JOIN sale_items si ON si.sale_id = s.id
         LEFT JOIN products p ON p.id = si.product_id
         WHERE s.status = 'completed' AND s.created_at >= $startExpr AND s.created_at < $endExpr"
    );
    $stmt->execute();
    $row = $stmt->fetch();
    $rev = (float) $row['revenue'];
    $cogs = (float) $row['cogs'];
    return ['revenue' => $rev, 'profit' => $rev - $cogs];
}

$thisMonth = periodTotals($db, "DATE_FORMAT(NOW(), '%Y-%m-01')", "DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')");
$lastMonth = periodTotals($db, "DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')", "DATE_FORMAT(NOW(), '%Y-%m-01')");

$revenueGrowth = $lastMonth['revenue'] > 0
    ? (($thisMonth['revenue'] - $lastMonth['revenue']) / $lastMonth['revenue']) * 100
    : ($thisMonth['revenue'] > 0 ? 100 : 0);

// Stock health snapshot
$stockHealth = $db->query(
    "SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN stock_qty <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN stock_qty > 0 AND stock_qty <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN stock_qty > low_stock_threshold THEN 1 ELSE 0 END) AS healthy_stock
     FROM products WHERE is_active = 1"
)->fetch();

// Category mix by revenue (all-time)
$categoryMix = $db->query(
    "SELECT c.name, COALESCE(SUM(si.line_total), 0) AS revenue
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     LEFT JOIN sale_items si ON si.product_id = p.id
     LEFT JOIN sales s ON s.id = si.sale_id AND s.status = 'completed'
     GROUP BY c.id, c.name
     ORDER BY revenue DESC"
)->fetchAll();

/* ------------------------------------------------------------------ *
 *  PRODUCT RANKING — by revenue, units sold, and profit (all-time)
 * ------------------------------------------------------------------ */
$rankingBase = "SELECT p.id, p.name, p.sku, c.name AS category,
                       SUM(si.quantity) AS units_sold,
                       SUM(si.line_total) AS revenue,
                       SUM(si.quantity * COALESCE(p.cost, 0)) AS cogs,
                       (SUM(si.line_total) - SUM(si.quantity * COALESCE(p.cost, 0))) AS profit
                FROM sale_items si
                JOIN sales s ON s.id = si.sale_id AND s.status = 'completed'
                JOIN products p ON p.id = si.product_id
                LEFT JOIN categories c ON c.id = p.category_id
                GROUP BY p.id, p.name, p.sku, c.name";

$rankByRevenue = $db->query("$rankingBase ORDER BY revenue DESC LIMIT 10")->fetchAll();
$rankByUnits   = $db->query("$rankingBase ORDER BY units_sold DESC LIMIT 10")->fetchAll();
$rankByProfit  = $db->query("$rankingBase ORDER BY profit DESC LIMIT 10")->fetchAll();

// Slow movers: active products with zero or very low units sold in last 30 days
$slowMovers = $db->query(
    "SELECT p.id, p.name, p.sku, p.stock_qty,
            COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END), 0) AS units_last_30d
     FROM products p
     LEFT JOIN sale_items si ON si.product_id = p.id
     LEFT JOIN sales s ON s.id = si.sale_id AND s.status = 'completed'
     WHERE p.is_active = 1
     GROUP BY p.id, p.name, p.sku, p.stock_qty
     HAVING units_last_30d <= 1
     ORDER BY units_last_30d ASC, p.stock_qty DESC
     LIMIT 8"
)->fetchAll();

/* ------------------------------------------------------------------ *
 *  TIME-SERIES PERFORMANCE — daily / weekly / monthly / yearly
 * ------------------------------------------------------------------ */
function seriesQuery(PDO $db, string $groupExpr, string $labelExpr, string $sinceExpr, string $orderExpr): array {
    $sql = "SELECT MIN($labelExpr) AS label,
                   COALESCE(SUM(si.line_total), 0) AS revenue,
                   COALESCE(SUM(si.line_total) - SUM(si.quantity * COALESCE(p.cost, 0)), 0) AS profit,
                   COUNT(DISTINCT s.id) AS txns
            FROM sales s
            JOIN sale_items si ON si.sale_id = s.id
            LEFT JOIN products p ON p.id = si.product_id
            WHERE s.status = 'completed' $sinceExpr
            GROUP BY $groupExpr
            ORDER BY $orderExpr";
    return $db->query($sql)->fetchAll();
}

$dailySeries = seriesQuery(
    $db,
    "DATE(s.created_at)",
    "DATE_FORMAT(s.created_at, '%b %d')",
    "AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)",
    "DATE(s.created_at) ASC"
);

$weeklySeries = seriesQuery(
    $db,
    "YEARWEEK(s.created_at, 1)",
    "CONCAT('Wk ', DATE_FORMAT(DATE_SUB(s.created_at, INTERVAL WEEKDAY(s.created_at) DAY), '%b %d'))",
    "AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)",
    "YEARWEEK(s.created_at, 1) ASC"
);

$monthlySeries = seriesQuery(
    $db,
    "DATE_FORMAT(s.created_at, '%Y-%m')",
    "DATE_FORMAT(s.created_at, '%b %Y')",
    "AND s.created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)",
    "DATE_FORMAT(s.created_at, '%Y-%m') ASC"
);

$yearlySeries = seriesQuery(
    $db,
    "YEAR(s.created_at)",
    "YEAR(s.created_at)",
    "",
    "YEAR(s.created_at) ASC"
);

$seriesPayload = [
    'daily'   => $dailySeries,
    'weekly'  => $weeklySeries,
    'monthly' => $monthlySeries,
    'yearly'  => $yearlySeries,
];

$analyticsData = [
    'series' => $seriesPayload,
    'categoryMix' => $categoryMix,
];
$analyticsJson = json_encode($analyticsData, JSON_NUMERIC_CHECK);

function pesos($n) { return '₱' . number_format((float) $n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales &amp; Profitability Analytics - RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard.css">
    <link rel="stylesheet" href="/spas/analytics.css">
    <link rel="stylesheet" href="/../idle-timeout.css">
</head>
<body>
<div class="app-shell">

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-chart-line" style="margin-right:8px;"></i>Sales &amp; Profitability Analytics</h1>
                    <p>Product ranking, business health, and performance over time.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <?php include __DIR__ . '/../notif-bell.php'; ?>
                <div class="user-chip">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="who">
                        <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                        <span>Staff</span>
                    </div>
                    <a href="/../logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content">

            <!-- ============ BUSINESS STATUS ============ -->
            <p class="section-label">Business status</p>
            <div class="kpi-strip">
                <div class="kpi-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                        <span class="kpi-trend <?= $revenueGrowth >= 0 ? 'up' : 'down' ?>">
                            <?= $revenueGrowth >= 0 ? '+' : '' ?><?= number_format($revenueGrowth, 1) ?>% MoM
                        </span>
                    </div>
                    <div class="kpi-value"><?= pesos($allTimeRevenue) ?></div>
                    <div class="kpi-label">All-time revenue</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-coins"></i></div>
                        <span class="kpi-trend up"><?= number_format($allTimeMargin, 1) ?>% margin</span>
                    </div>
                    <div class="kpi-value"><?= pesos($allTimeProfit) ?></div>
                    <div class="kpi-label">All-time profit</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-receipt"></i></div>
                        <span class="kpi-trend up"><?= (int) $allTimeTxns ?> total</span>
                    </div>
                    <div class="kpi-value"><?= pesos($avgTicket) ?></div>
                    <div class="kpi-label">Average ticket size</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-box-open"></i></div>
                        <span class="kpi-trend <?= (int) $stockHealth['low_stock'] + (int) $stockHealth['out_of_stock'] > 0 ? 'down' : 'up' ?>">
                            <?= (int) $stockHealth['low_stock'] + (int) $stockHealth['out_of_stock'] ?> to watch
                        </span>
                    </div>
                    <div class="kpi-value"><?= (int) $stockHealth['total_products'] ?></div>
                    <div class="kpi-label">Active products</div>
                </div>
            </div>

            <div class="an-status-grid">
                <div class="an-panel an-stock-health">
                    <h3><i class="fa-solid fa-heart-pulse"></i> Stock health</h3>
                    <div class="an-stock-bar">
                        <?php
                        $tot = max(1, (int) $stockHealth['total_products']);
                        $healthyPct = round(((int) $stockHealth['healthy_stock'] / $tot) * 100);
                        $lowPct = round(((int) $stockHealth['low_stock'] / $tot) * 100);
                        $outPct = 100 - $healthyPct - $lowPct;
                        ?>
                        <span class="seg healthy" style="width: <?= $healthyPct ?>%"></span>
                        <span class="seg low" style="width: <?= $lowPct ?>%"></span>
                        <span class="seg out" style="width: <?= max(0, $outPct) ?>%"></span>
                    </div>
                    <div class="an-stock-legend">
                        <span><i class="dot healthy"></i> Healthy (<?= (int) $stockHealth['healthy_stock'] ?>)</span>
                        <span><i class="dot low"></i> Low stock (<?= (int) $stockHealth['low_stock'] ?>)</span>
                        <span><i class="dot out"></i> Out of stock (<?= (int) $stockHealth['out_of_stock'] ?>)</span>
                    </div>

                    <h3 class="an-subhead"><i class="fa-solid fa-eye-slash"></i> Slow movers (last 30 days)</h3>
                    <?php if (count($slowMovers) === 0): ?>
                        <p class="pos-empty-note">Everything is selling — nothing sitting idle.</p>
                    <?php else: ?>
                    <div class="an-list">
                        <?php foreach ($slowMovers as $sm): ?>
                        <div class="an-list-row">
                            <span class="name"><?= htmlspecialchars($sm['name']) ?></span>
                            <span class="meta"><?= (int) $sm['units_last_30d'] ?> sold · <?= (int) $sm['stock_qty'] ?> in stock</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="an-panel an-category-mix">
                    <h3><i class="fa-solid fa-chart-pie"></i> Revenue by category</h3>
                    <div class="an-chart-wrap an-chart-big">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- ============ PERFORMANCE OVER TIME ============ -->
            <p class="section-label">Sales &amp; profit performance</p>
            <div class="an-panel an-performance">
                <div class="an-panel-head">
                    <div class="an-tabs" id="periodTabs">
                        <button class="an-tab active" data-period="daily">Daily</button>
                        <button class="an-tab" data-period="weekly">Weekly</button>
                        <button class="an-tab" data-period="monthly">Monthly</button>
                        <button class="an-tab" data-period="yearly">Yearly</button>
                    </div>
                    <div class="an-legend-inline">
                        <span><i class="dot revenue"></i> Revenue</span>
                        <span><i class="dot profit"></i> Profit</span>
                    </div>
                </div>
                <div class="an-chart-wrap">
                    <canvas id="performanceChart"></canvas>
                </div>
                <div class="an-period-summary" id="periodSummary"></div>
            </div>

            <!-- ============ PRODUCT RANKING ============ -->
            <p class="section-label">Product ranking</p>
            <div class="an-panel an-ranking">
                <div class="an-tabs" id="rankTabs">
                    <button class="an-tab active" data-rank="revenue">By Revenue</button>
                    <button class="an-tab" data-rank="units">By Units Sold</button>
                    <button class="an-tab" data-rank="profit">By Profit</button>
                </div>

                <div class="an-rank-table-wrap">
                    <table class="an-rank-table" data-rank-panel="revenue">
                        <thead>
                            <tr><th>#</th><th>Product</th><th>Category</th><th>Units sold</th><th>Revenue</th><th>Profit</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rankByRevenue as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><span class="rt-name"><?= htmlspecialchars($r['name']) ?></span><span class="rt-sku"><?= htmlspecialchars($r['sku']) ?></span></td>
                                <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                                <td><?= (int) $r['units_sold'] ?></td>
                                <td><?= pesos($r['revenue']) ?></td>
                                <td><?= pesos($r['profit']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($rankByRevenue) === 0): ?>
                            <tr><td colspan="6" class="an-empty-cell">No sales recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <table class="an-rank-table hidden" data-rank-panel="units">
                        <thead>
                            <tr><th>#</th><th>Product</th><th>Category</th><th>Units sold</th><th>Revenue</th><th>Profit</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rankByUnits as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><span class="rt-name"><?= htmlspecialchars($r['name']) ?></span><span class="rt-sku"><?= htmlspecialchars($r['sku']) ?></span></td>
                                <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                                <td><?= (int) $r['units_sold'] ?></td>
                                <td><?= pesos($r['revenue']) ?></td>
                                <td><?= pesos($r['profit']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($rankByUnits) === 0): ?>
                            <tr><td colspan="6" class="an-empty-cell">No sales recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <table class="an-rank-table hidden" data-rank-panel="profit">
                        <thead>
                            <tr><th>#</th><th>Product</th><th>Category</th><th>Units sold</th><th>Revenue</th><th>Profit</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rankByProfit as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><span class="rt-name"><?= htmlspecialchars($r['name']) ?></span><span class="rt-sku"><?= htmlspecialchars($r['sku']) ?></span></td>
                                <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                                <td><?= (int) $r['units_sold'] ?></td>
                                <td><?= pesos($r['revenue']) ?></td>
                                <td><?= pesos($r['profit']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($rankByProfit) === 0): ?>
                            <tr><td colspan="6" class="an-empty-cell">No sales recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<script>window.RAMYUM_ANALYTICS = <?= $analyticsJson ?: '{}' ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="/spas/analytics.js"></script>
<script src="/sidebar.js"></script>
<script src="/notif-bell.js"></script>
<script src="/../idle-timeout.js"></script>
</body>
</html>