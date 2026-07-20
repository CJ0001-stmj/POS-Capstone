<?php
/**
 * Admin Messages — one-way inbox: warnings/notices Admin & Access sends
 * to a staff member, pulled from `staff_warnings` (see
 * migration_staff_fields.sql + migration_notifications.sql). Separate
 * from staff-concern.php, which is the two-way staff <-> admin chat on
 * `staff_concerns` — this page is read-only for staff, admin-initiated
 * only. Opening this page marks every unread row as read, same as
 * opening the notif bell dropdown does via notifications.php.
 */
require_once __DIR__ . '/../db.php';

session_start();

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

// session_start() emits its own Cache-Control header (governed by
// session.cache_limiter in php.ini) which on some hosts defaults to a
// cacheable value. Force it back to no-store so a browser/proxy never
// serves a stale unread badge after messages were just read.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$userEmail = $_SESSION['user_email'] ?? null;
$userRole  = $_SESSION['user_role'] ?? 'cashier';
$userId    = $_SESSION['user_id'] ?? null;
$initials  = $userEmail ? strtoupper(substr($userEmail, 0, 1)) : '?';

if (!$userEmail) {
    header('Location: login.php');
    exit;
}

$pdo = get_db_connection();

// Mark every unread message as read the moment this page is opened —
// same semantics as the bell's mark_read action, so the badge count on
// every other page (dashboard-cashier.php, sidebar-cashier.php, etc.)
// clears in step with what's shown here.
$pdo->prepare('UPDATE staff_warnings SET read_at = NOW() WHERE user_id = :uid AND read_at IS NULL')
    ->execute([':uid' => $userId]);

// Full history, newest first. sent_by is nullable (fk_warnings_sent_by
// ON DELETE SET NULL) so LEFT JOIN in case the admin account was removed.
$stmt = $pdo->prepare(
    'SELECT sw.id, sw.message, sw.created_at, sw.read_at, u.email AS sent_by_email
     FROM staff_warnings sw
     LEFT JOIN users u ON u.id = sw.sent_by
     WHERE sw.user_id = :uid
     ORDER BY sw.created_at DESC'
);
$stmt->execute([':uid' => $userId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages — RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="/../dashboard.css">
    <link rel="stylesheet" href="/sc/staff-concern.css">
    <link rel="stylesheet" href="/../idle-timeout.css">
</head>
<body>
<div class="app-shell">

    <?php
    $__sidebarFile = (($_SESSION['user_role'] ?? '') === 'cashier') ? '/../sidebar-cashier.php' : '/../sidebar.php';
    include __DIR__ . '/' . $__sidebarFile;
    ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-bullhorn" style="margin-right:8px;"></i>Admin Messages</h1>
                    <p>Notices and warnings sent to you by Admin &amp; Access.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <?php if ($userRole !== 'cashier') include __DIR__ . '/../notif-bell.php'; ?>
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

        <main class="content concern-content">
            <section class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-inbox"></i> Your Messages</h3>
                </div>

                <?php if (empty($messages)): ?>
                    <p class="empty-note">No messages from Admin &amp; Access yet.</p>
                <?php else: ?>
                <div class="concern-thread-list">
                    <?php foreach ($messages as $m): ?>
                    <div class="concern-thread">
                        <div class="chat-bubble chat-bubble-admin">
                            <span class="chat-bubble-from"><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($m['sent_by_email'] ?? 'Admin & Access') ?></span>
                            <p><?= nl2br(htmlspecialchars($m['message'])) ?></p>
                            <time><?= htmlspecialchars((new DateTime($m['created_at']))->format('M j, Y g:i A')) ?></time>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script src="/../notif-bell.js"></script>
<script src="/../sidebar.js"></script>
<script src="/../idle-timeout.js"></script>
</body>
</html>