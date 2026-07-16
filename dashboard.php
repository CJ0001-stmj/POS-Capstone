<?php
session_start();

// Guard: bounce anyone without a valid session back to the login screen.
if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$userEmail = $_SESSION['user_email'];
$initials = strtoupper(substr($userEmail, 0, 1));

// ---------------------------------------------------------------
// Mock data placeholders. Wire each of these up to real queries
// (transactions, products, staff_sessions, purchase_requests, etc.)
// as those tables come online — the layout below is already built
// to take them.
// ---------------------------------------------------------------

// KPI strip — today + this month, sales + profit (feeds the margin
// calculation in dashboard.js)
$kpis = [
    ['icon' => 'fa-cash-register', 'label' => "Today's Sales",   'value' => '₱18,420',  'trend' => '+8.2%', 'dir' => 'up',   'card' => 'today-card'],
    ['icon' => 'fa-sack-dollar',   'label' => "Today's Profit",   'value' => '₱6,760',   'trend' => '+5.1%', 'dir' => 'up',   'card' => 'profit-card'],
    ['icon' => 'fa-calendar-days', 'label' => 'This Month Sales', 'value' => '₱214,300', 'trend' => '+12%',  'dir' => 'up',   'card' => 'month-card'],
    ['icon' => 'fa-coins',         'label' => 'This Month Profit','value' => '₱78,110',  'trend' => '+9.4%', 'dir' => 'up',   'card' => 'month-profit-card'],
];

// Sales analytics — last 7 days, sales vs. profit
$salesChart = [
    'labels'  => ['Jul 11', 'Jul 12', 'Jul 13', 'Jul 14', 'Jul 15', 'Jul 16', 'Jul 17'],
    'sales'   => [15200, 16800, 14950, 19100, 21300, 24600, 18420],
    'profit'  => [5300,  5900,  5100,  6700,  7600,  8900,  6760],
];

// Product performance ranking
$productRanking = [
    ['rank' => 1, 'name' => 'Kimchi Ramen',        'units' => 214, 'revenue' => '₱32,100', 'dir' => 'up'],
    ['rank' => 2, 'name' => 'Bulgogi Rice Bowl',    'units' => 178, 'revenue' => '₱26,700', 'dir' => 'up'],
    ['rank' => 3, 'name' => 'Tteokbokki',           'units' => 156, 'revenue' => '₱18,720', 'dir' => 'down'],
    ['rank' => 4, 'name' => 'Iced Yakult Soda',     'units' => 142, 'revenue' => '₱9,940',  'dir' => 'up'],
    ['rank' => 5, 'name' => 'Gyoza (6pc)',          'units' => 121, 'revenue' => '₱10,890', 'dir' => 'down'],
];

// Cashier / staff active status
$cashiers = [
    ['name' => 'Mika Santos',   'role' => 'Cashier — AM shift', 'status' => 'active',  'note' => 'On register 1'],
    ['name' => 'Jordan Reyes',  'role' => 'Cashier — AM shift', 'status' => 'active',  'note' => 'On register 2'],
    ['name' => 'Bea Villareal', 'role' => 'Floor staff',        'status' => 'break',   'note' => 'Back in 10 min'],
    ['name' => 'Carlo Dizon',   'role' => 'Cashier — PM shift', 'status' => 'offline', 'note' => 'Shift starts 4:00 PM'],
];

// Low-stock products
$lowStock = [
    ['name' => 'Gochujang Paste 500g', 'stock' => 3,  'reorder' => 10, 'status' => 'critical'],
    ['name' => 'Nori Sheets (pack)',   'stock' => 6,  'reorder' => 12, 'status' => 'low'],
    ['name' => 'Rice Cake (tteok)',    'stock' => 5,  'reorder' => 15, 'status' => 'critical'],
    ['name' => 'Yakult 5-pack',        'stock' => 9,  'reorder' => 20, 'status' => 'low'],
    ['name' => 'Sesame Oil 1L',        'stock' => 2,  'reorder' => 8,  'status' => 'critical'],
    ['name' => 'Disposable Chopsticks','stock' => 14, 'reorder' => 30, 'status' => 'low'],
];

// Today's transactions (columns match the CSV export in dashboard.js)
$transactions = [
    ['id' => 'TX-10482', 'time' => 'Jul 17, 2026 1:42 PM', 'items' => 'Kimchi Ramen x2, Iced Tea',   'amount' => '₱620', 'profit' => '₱210'],
    ['id' => 'TX-10481', 'time' => 'Jul 17, 2026 1:35 PM', 'items' => 'Bulgogi Rice Bowl',            'amount' => '₱280', 'profit' => '₱95'],
    ['id' => 'TX-10480', 'time' => 'Jul 17, 2026 1:20 PM', 'items' => 'Gyoza (6pc), Yakult Soda',     'amount' => '₱310', 'profit' => '₱118'],
    ['id' => 'TX-10479', 'time' => 'Jul 17, 2026 1:04 PM', 'items' => 'Tteokbokki x2',                'amount' => '₱480', 'profit' => '₱150'],
    ['id' => 'TX-10478', 'time' => 'Jul 17, 2026 12:51 PM','items' => 'Kimchi Ramen, Gyoza (6pc)',    'amount' => '₱330', 'profit' => '₱112'],
];

$modules = [
    ['code' => 'M1', 'icon' => 'fa-cash-register',  'title' => 'Point of Sale & Transactions',      'href' => 'pos.php'],
    ['code' => 'M2', 'icon' => 'fa-dolly',          'title' => 'Purchase Request Management',       'href' => 'purchase-requests.php'],
    ['code' => 'M3', 'icon' => 'fa-bowl-food',      'title' => 'Customer Order & Reservation',      'href' => 'orders.php'],
    ['code' => 'M4', 'icon' => 'fa-chart-line',     'title' => 'Sales & Profitability Analytics',   'href' => 'analytics.php'],
    ['code' => 'M5', 'icon' => 'fa-tags',           'title' => 'Promotions & Campaign Manager',     'href' => 'promotions.php'],
    ['code' => 'M6', 'icon' => 'fa-user-shield',    'title' => 'Audit, Compliance & User Access',   'href' => 'login_audit.php'],
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

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="assets/logo.png" alt="RAM-YUM Logo">
            <div class="brand-text">
                <strong>RAM-YUM</strong>
                <span>Store Management</span>
            </div>
        </div>
        <ul class="sidebar-nav">
            <li class="active"><a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Overview</a></li>
            <li><a href="pos.php"><i class="fa-solid fa-cash-register"></i> Point of Sale</a></li>
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
                        <span><i class="dot dot-profit"></i> Profit</span>
                    </div>
                </div>
                <div class="chart-wrap">
                    <canvas id="salesChart" height="90"></canvas>
                </div>
            </div>

            <div class="panel-row">
                <div class="panel">
                    <div class="panel-head">
                        <h3><i class="fa-solid fa-ranking-star"></i> Product performance ranking</h3>
                    </div>
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
                                <td><i class="fa-solid <?= $p['dir'] === 'up' ? 'fa-arrow-trend-up trend-up' : 'fa-arrow-trend-down trend-down' ?>"></i></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h3><i class="fa-solid fa-user-clock"></i> Cashier active status</h3>
                    </div>
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
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h3><i class="fa-solid fa-box-open"></i> Low-stock products</h3>
                    </div>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script src="dashboard.js"></script>
<script>
document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
});
</script>
</body>
</html>