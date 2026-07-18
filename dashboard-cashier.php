<?php
/**
 * Cashier shift dashboard. Included (not requested directly) from
 * dashboard.php when $_SESSION['user_role'] === 'cashier'. Expects
 * $pdo, $userEmail, $userRole, $initials, and peso() to already be
 * in scope from dashboard.php.
 */


// If this file is included without $pdo in scope, fall back to using
// the shared DB helper.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/db.php';
    $pdo = get_db_connection();
}

if (!function_exists('peso')) {
    function peso($n): string {
        return '₱' . number_format((float)$n, 0);
    }
}

// Ensure session data exists (dashboard.php usually starts it).
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Derive commonly expected session-backed variables (dashboard.php normally
// injects these into scope, but we make the file resilient).
$userEmail = $userEmail ?? ($_SESSION['user_email'] ?? '');
$userRole  = $userRole  ?? ($_SESSION['user_role'] ?? 'cashier');
$initials  = $initials  ?? (isset($userEmail[0]) ? strtoupper(substr($userEmail, 0, 1)) : '');


$now = new DateTime();

// "Time in" = when this session's login happened (set by login.php on
// successful auth). Falls back to now if somehow missing.
$shiftStart = isset($_SESSION['logged_in_at'])
    ? (new DateTime())->setTimestamp((int)$_SESSION['logged_in_at'])
    : clone $now;


$fmt = 'Y-m-d H:i:s';

// Real-time sales rung up by THIS cashier since they clocked in.
// Assumes `sales.cashier_id` references `users.id` — rename below if
// your schema calls it something else (e.g. `user_id`).
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(s.total), 0) AS sales, COUNT(s.id) AS trans_count
     FROM sales s
     WHERE s.status = 'completed'
       AND s.cashier_id = :uid
       AND s.created_at BETWEEN :start AND :end"
);
$stmt->execute([
    ':uid'   => $_SESSION['user_id'],
    ':start' => $shiftStart->format($fmt),
    ':end'   => $now->format($fmt),
]);
$shift = $stmt->fetch() ?: ['sales' => 0, 'trans_count' => 0];

$roleLabel = ucfirst($userRole);

$modules = [
    ['icon' => 'fa-cash-register', 'title' => 'Point of Sale & Transactions', 'href' => 'pos.php'],
    ['icon' => 'fa-dolly',         'title' => 'Purchase Request Management',  'href' => 'purchase-requests.php'],
    ['icon' => 'fa-bowl-food',     'title' => 'Customer Order & Reservation', 'href' => 'orders.php'],
    ['icon' => 'fa-bullhorn',      'title' => 'Admin Messages',               'href' => 'admin-messages.php'],
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

    <?php include __DIR__ . '/sidebar-cashier.php'; ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1>Welcome back 🍜</h1>
                    <p id="current-date">Here's your shift so far.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <div class="user-chip">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="who">
                        <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                        <span><?= htmlspecialchars($roleLabel) ?></span>
                    </div>
                    <a href="logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content">
            <p class="section-label">Your shift</p>
            <div class="kpi-strip">
                <div class="kpi-card stat-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="kpi-value"><?= htmlspecialchars($shiftStart->format('g:i A')) ?></div>
                    <div class="kpi-label">Time in &middot; <?= htmlspecialchars($shiftStart->format('M j')) ?></div>
                </div>
                <div class="kpi-card stat-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-id-badge"></i></div>
                    </div>
                    <div class="kpi-value"><?= htmlspecialchars($roleLabel) ?></div>
                    <div class="kpi-label">Role</div>
                </div>
                <div class="kpi-card stat-card shift-sales-card">
                    <div class="kpi-top">
                        <div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                        <span class="kpi-trend flat" id="shift-sales-live" title="Refreshes automatically"><i class="fa-solid fa-rotate"></i> live</span>
                    </div>
                    <div class="kpi-value" id="shift-sales-value"><?= peso($shift['sales']) ?></div>
                    <div class="kpi-label">Sales this shift &middot; <span id="shift-trans-count"><?= (int)$shift['trans_count'] ?></span> transactions</div>
                </div>
            </div>

            <p class="section-label">Your modules</p>
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
// Poll shift sales every 30s so the number stays "real-time" without a
// full page reload while the cashier is mid-shift.
async function refreshShiftSales() {
    try {
        const res = await fetch('shift-sales.php', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.ok) return;
        document.getElementById('shift-sales-value').textContent = data.sales_formatted;
        document.getElementById('shift-trans-count').textContent = data.trans_count;
    } catch (err) {
        console.error('Shift sales refresh failed:', err);
    }
}
setInterval(refreshShiftSales, 30000);
</script>
<script src="sidebar.js"></script>
</body>
</html>