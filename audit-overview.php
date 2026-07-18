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

// Merge the last 20 events across three sources into one feed.
$feed = [];
foreach ($conn->query("SELECT email, success, reason, created_at FROM login_audit ORDER BY created_at DESC LIMIT 20") as $r) {
    $feed[] = [
        'type' => $r['success'] ? 'login_success' : 'login_failed',
        'label' => $r['success'] ? "Logged in: {$r['email']}" : "Failed login ({$r['reason']}): {$r['email']}",
        'time' => $r['created_at'],
    ];
}
foreach ($conn->query("SELECT receipt_no, cashier_email, total, created_at FROM sales WHERE status='completed' ORDER BY created_at DESC LIMIT 20") as $r) {
    $feed[] = [
        'type' => 'sale',
        'label' => "Sale {$r['receipt_no']} — ₱" . number_format($r['total'], 2) . " by " . ($r['cashier_email'] ?: 'unknown'),
        'time' => $r['created_at'],
    ];
}
foreach ($conn->query("SELECT subject, submitted_by_email, created_at FROM staff_concerns ORDER BY created_at DESC LIMIT 20") as $r) {
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
        <h2>Recent Activity</h2>
        <ul>
        <?php foreach ($feed as $f): ?>
            <li class="feed-item feed-<?= $f['type'] ?>">
                <i class="fa-solid <?= $iconFor[$f['type']] ?>"></i>
                <span><?= htmlspecialchars($f['label']) ?></span>
                <time><?= htmlspecialchars($f['time']) ?></time>
            </li>
        <?php endforeach; ?>
        <?php if (empty($feed)): ?>
            <li class="empty-row">No activity recorded yet.</li>
        <?php endif; ?>
        </ul>
    </section>
</main>
<script src="sidebar.js"></script>
<script src="notif-bell.js"></script>
</body>
</html>