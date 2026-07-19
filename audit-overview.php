<?php
/**
 * Audit & Access > Overview
 * High-level pulse of the whole system: today's sales, logins, failed
 * attempts, locked accounts, open staff concerns, and a merged recent
 * activity feed pulled from login_audit + sales + staff_concerns.
 */
require_once __DIR__ . '/db.php';
session_start();

$userEmail = $_SESSION['user_email'] ?? null;
$userRole  = $_SESSION['user_role'] ?? 'cashier';
$initials  = $userEmail ? strtoupper(substr($userEmail, 0, 1)) : '?';

if (!$userEmail) {
    header('Location: login.php');
    exit;
}
if (!in_array($userRole, ['admin', 'manager'], true)) {
    http_response_code(403);
    die('You do not have permission to view this page.');
}

$salesToday    = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM sales WHERE status='completed' AND DATE(created_at) = CURDATE()")->fetch_assoc();
$loginsToday   = $conn->query("SELECT COUNT(*) c FROM login_audit WHERE success = 1 AND DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
$failedToday   = $conn->query("SELECT COUNT(*) c FROM login_audit WHERE success = 0 AND DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
$lockedNow     = $conn->query("SELECT COUNT(*) c FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()")->fetch_assoc()['c'];
$openConcerns  = $conn->query("SELECT COUNT(*) c FROM staff_concerns WHERE status = 'open'")->fetch_assoc()['c'];

// Distinct users seen across the three feed sources, for the filter
// dropdown - union so it covers cashiers who never filed a concern etc.
$feedUsers = [];
$userRes = $conn->query(
    "SELECT email FROM (
        SELECT email FROM login_audit WHERE email IS NOT NULL AND email <> ''
        UNION
        SELECT cashier_email AS email FROM sales WHERE cashier_email IS NOT NULL AND cashier_email <> ''
        UNION
        SELECT submitted_by_email AS email FROM staff_concerns WHERE submitted_by_email IS NOT NULL AND submitted_by_email <> ''
     ) AS u ORDER BY email ASC"
);
foreach ($userRes as $r) {
    $feedUsers[] = $r['email'];
}

// Selected filter - empty/"all" means unfiltered.
$selectedUser = $_GET['user'] ?? '';
$selectedUser = in_array($selectedUser, $feedUsers, true) ? $selectedUser : '';

// Merge the last 20 events per source into one feed, filtered to the
// selected user when one is chosen. Pull a slightly wider window (40)
// when filtering since a chatty "all users" query would otherwise crowd
// one person's rows out of a plain LIMIT 20.
$feedLimit = $selectedUser ? 40 : 20;

if ($selectedUser) {
    $stmt = $conn->prepare("SELECT email, ip_address, success, reason, created_at FROM login_audit WHERE email = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param('si', $selectedUser, $feedLimit);
    $stmt->execute();
    $loginRows = $stmt->get_result();
} else {
    $loginRows = $conn->query("SELECT email, ip_address, success, reason, created_at FROM login_audit ORDER BY created_at DESC LIMIT {$feedLimit}");
}

$feed = [];
foreach ($loginRows as $r) {
    $ipSuffix = $r['ip_address'] ? " — {$r['ip_address']}" : '';
    $feed[] = [
        'type' => $r['success'] ? 'login_success' : 'login_failed',
        'label' => ($r['success'] ? "Logged in: {$r['email']}" : "Failed login ({$r['reason']}): {$r['email']}") . $ipSuffix,
        'time' => $r['created_at'],
    ];
}

if ($selectedUser) {
    $stmt = $conn->prepare("SELECT receipt_no, cashier_email, total, created_at FROM sales WHERE status='completed' AND cashier_email = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param('si', $selectedUser, $feedLimit);
    $stmt->execute();
    $saleRows = $stmt->get_result();
} else {
    $saleRows = $conn->query("SELECT receipt_no, cashier_email, total, created_at FROM sales WHERE status='completed' ORDER BY created_at DESC LIMIT {$feedLimit}");
}
foreach ($saleRows as $r) {
    $feed[] = [
        'type' => 'sale',
        'label' => "Sale {$r['receipt_no']} — ₱" . number_format($r['total'], 2) . " by " . ($r['cashier_email'] ?: 'unknown'),
        'time' => $r['created_at'],
    ];
}

if ($selectedUser) {
    $stmt = $conn->prepare("SELECT subject, submitted_by_email, created_at FROM staff_concerns WHERE submitted_by_email = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param('si', $selectedUser, $feedLimit);
    $stmt->execute();
    $concernRows = $stmt->get_result();
} else {
    $concernRows = $conn->query("SELECT subject, submitted_by_email, created_at FROM staff_concerns ORDER BY created_at DESC LIMIT {$feedLimit}");
}
foreach ($concernRows as $r) {
    $feed[] = [
        'type' => 'concern',
        'label' => "New concern: \"{$r['subject']}\" from {$r['submitted_by_email']}",
        'time' => $r['created_at'],
    ];
}

usort($feed, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
$feed = array_slice($feed, 0, 25);

$iconFor = [
    'login_success' => 'fa-right-to-bracket',
    'login_failed'  => 'fa-triangle-exclamation',
    'sale'          => 'fa-cash-register',
    'concern'       => 'fa-inbox',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Overview — RAM-YUM</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="audit-access.css">
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <header class="topbar">
        <div style="display:flex; align-items:center; gap:14px;">
            <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-greet">
                <h1>Audit & Access</h1>
                <p>A single view of today's activity across the whole system.</p>
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

    <div class="page-header">
        <h1><i class="fa-solid fa-chart-pie"></i> Audit & Access Overview</h1>
        <p class="subtitle">A single view of today's activity across the whole system.</p>
    </div>

    <div class="stat-cards">
        <div class="stat-card">
            <span class="stat-value"><?= (int)$salesToday['c'] ?></span>
            <span class="stat-label">Sales Today</span>
            <span class="stat-sub">₱<?= number_format((float)$salesToday['t'], 2) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?= (int)$loginsToday ?></span>
            <span class="stat-label">Logins Today</span>
        </div>
        <div class="stat-card <?= $failedToday > 0 ? 'stat-warn' : '' ?>">
            <span class="stat-value"><?= (int)$failedToday ?></span>
            <span class="stat-label">Failed Logins Today</span>
        </div>
        <div class="stat-card <?= $lockedNow > 0 ? 'stat-danger' : '' ?>">
            <span class="stat-value"><?= (int)$lockedNow ?></span>
            <span class="stat-label">Accounts Locked Now</span>
        </div>
        <div class="stat-card <?= $openConcerns > 0 ? 'stat-warn' : '' ?>">
            <span class="stat-value"><?= (int)$openConcerns ?></span>
            <span class="stat-label">Open Staff Concerns</span>
        </div>
    </div>

    <section class="activity-feed">
        <div class="activity-feed-head">
            <h2>Recent Activity</h2>
            <form method="get" class="user-filter-form" id="userFilterForm">
                <label for="userFilterSelect" class="user-filter-label">
                    <i class="fa-solid fa-user"></i> Filter by user
                </label>
                <select name="user" id="userFilterSelect" class="pos-select" onchange="document.getElementById('userFilterForm').submit()">
                    <option value="">All users</option>
                    <?php foreach ($feedUsers as $email): ?>
                        <option value="<?= htmlspecialchars($email) ?>" <?= $selectedUser === $email ? 'selected' : '' ?>>
                            <?= htmlspecialchars($email) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selectedUser): ?>
                    <a href="audit-overview.php" class="user-filter-clear"><i class="fa-solid fa-xmark"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <ul>
        <?php foreach ($feed as $f): ?>
            <li class="feed-item feed-<?= $f['type'] ?>">
                <i class="fa-solid <?= $iconFor[$f['type']] ?>"></i>
                <span><?= htmlspecialchars($f['label']) ?></span>
                <time><?= htmlspecialchars($f['time']) ?></time>
            </li>
        <?php endforeach; ?>
        <?php if (empty($feed)): ?>
            <li class="empty-row"><?= $selectedUser ? 'No activity recorded for this user yet.' : 'No activity recorded yet.' ?></li>
        <?php endif; ?>
        </ul>
    </section>
</main>
<script src="sidebar.js"></script>
<script src="notif-bell.js"></script>
</body>
</html>