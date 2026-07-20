<?php
/**
 * Audit & Access > Inbox
 * Admin/manager triage view for concerns submitted via staff_concerns.
 * Handles a POST to mark a concern in_review/resolved with notes.
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

$userEmail = $_SESSION['user_email'] ?? null;
$userRole  = $_SESSION['user_role'] ?? 'cashier';
$userId    = $_SESSION['user_id'] ?? null;
$initials  = $userEmail ? strtoupper(substr($userEmail, 0, 1)) : '?';

if (!$userEmail) {
    header('Location: /login.php');
    exit;
}
if (!in_array($userRole, ['admin', 'manager'], true)) {
    http_response_code(403);
    die('You do not have permission to view this page.');
}

// Handle triage action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['concern_id'])) {
    $concernId = (int)$_POST['concern_id'];
    $newStatus = in_array($_POST['status'] ?? '', ['open', 'in_review', 'resolved'], true) ? $_POST['status'] : 'open';
    $notes = trim($_POST['resolution_notes'] ?? '');

    $stmt = $conn->prepare("
        UPDATE staff_concerns
        SET status = ?, resolution_notes = ?, resolved_by = ?,
            resolved_at = CASE WHEN ? = 'resolved' THEN NOW() ELSE resolved_at END
        WHERE id = ?
    ");
    $stmt->bind_param('ssiii', $newStatus, $notes, $userId, $newStatus, $concernId);
    $stmt->execute();
    $stmt->close();

    header('Location: staff-inbox.php');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$where = '';
if (in_array($statusFilter, ['open', 'in_review', 'resolved'], true)) {
    $where = "WHERE status = '" . $conn->real_escape_string($statusFilter) . "'";
}

$concerns = $conn->query("SELECT * FROM staff_concerns $where ORDER BY FIELD(status,'open','in_review','resolved'), created_at DESC")
    ->fetch_all(MYSQLI_ASSOC);

$openCount = $conn->query("SELECT COUNT(*) c FROM staff_concerns WHERE status = 'open'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inbox — RAM-YUM</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/dashboard.css">
<link rel="stylesheet" href="/aac/audit-access.css">
<link rel="stylesheet" href="/../idle-timeout.css">
</head>
<body>
<?php include __DIR__ . '/../sidebar.php'; ?>

<main class="main-content">
    <header class="topbar">
        <div style="display:flex; align-items:center; gap:14px;">
            <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-greet">
                <h1>Inbox</h1>
                <p>Concerns and reports submitted by staff.</p>
            </div>
        </div>
        <div class="topbar-actions">
            <?php include __DIR__ . '/../notif-bell.php'; ?>
            <div class="user-chip">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="who">
                    <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                    <span><?= htmlspecialchars(ucfirst($userRole)) ?></span>
                </div>
                <a href="/../logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </header>

    <div class="page-header">
        <h1><i class="fa-solid fa-inbox"></i> Inbox <?php if ($openCount): ?><span class="badge-count"><?= (int)$openCount ?> open</span><?php endif; ?></h1>
        <p class="subtitle">Concerns and reports submitted by staff.</p>
    </div>

    <div class="filters-bar">
        <a href="staff-inbox.php" class="<?= $statusFilter === '' ? 'active' : '' ?>">All</a>
        <a href="staff-inbox.php?status=open" class="<?= $statusFilter === 'open' ? 'active' : '' ?>">Open</a>
        <a href="staff-inbox.php?status=in_review" class="<?= $statusFilter === 'in_review' ? 'active' : '' ?>">In Review</a>
        <a href="staff-inbox.php?status=resolved" class="<?= $statusFilter === 'resolved' ? 'active' : '' ?>">Resolved</a>
    </div>

    <div class="concern-list">
    <?php foreach ($concerns as $c): ?>
        <div class="concern-card status-<?= htmlspecialchars($c['status']) ?>">
            <div class="concern-head">
                <strong><?= htmlspecialchars($c['subject']) ?></strong>
                <span class="status-pill status-<?= htmlspecialchars($c['status']) ?>"><?= ucwords(str_replace('_',' ',$c['status'])) ?></span>
            </div>
            <p class="concern-meta">From <?= htmlspecialchars($c['submitted_by_email']) ?> — <?= htmlspecialchars($c['created_at']) ?></p>
            <p class="concern-body"><?= nl2br(htmlspecialchars($c['message'])) ?></p>
            <?php if ($c['resolution_notes']): ?>
                <p class="concern-resolution"><em>Resolution:</em> <?= htmlspecialchars($c['resolution_notes']) ?></p>
            <?php endif; ?>

            <form method="post" class="concern-form">
                <input type="hidden" name="concern_id" value="<?= (int)$c['id'] ?>">
                <button type="button" class="suggest-reply-btn"
                        data-subject="<?= htmlspecialchars($c['subject']) ?>"
                        data-message="<?= htmlspecialchars($c['message']) ?>"
                        title="Fill in a suggested reply based on this concern">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Suggest Reply
                </button>
                <select name="status">
                    <option value="open" <?= $c['status']==='open'?'selected':'' ?>>Open</option>
                    <option value="in_review" <?= $c['status']==='in_review'?'selected':'' ?>>In Review</option>
                    <option value="resolved" <?= $c['status']==='resolved'?'selected':'' ?>>Resolved</option>
                </select>
                <input type="text" name="resolution_notes" class="resolution-notes-input" placeholder="Resolution notes..." value="<?= htmlspecialchars($c['resolution_notes'] ?? '') ?>">
                <button type="submit"><i class="fa-solid fa-check"></i> Update</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if (empty($concerns)): ?>
        <p class="empty-row">No concerns to show.</p>
    <?php endif; ?>
    </div>
</main>
<script src="/sidebar.js"></script>
<script src="/notif-bell.js"></script>
<script src="/si/staff-inbox.js"></script>
<script src="/../idle-timeout.js"></script>
</body>
</html>