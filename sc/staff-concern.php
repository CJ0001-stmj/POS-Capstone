<?php
/**
 * Staff Concerns — cashier/staff-facing "chat" with Admin & Access.
 * Any logged-in user can send a concern here; it lands in the exact same
 * `staff_concerns` table staff-inbox.php triages, so nothing extra to
 * sync — submit here, it shows up there immediately. When an admin sets
 * resolution_notes on it, that reply renders back here as the admin's
 * side of the thread.
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

$pdo = get_db_connection();

$flash = null;

// Handle new concern submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $otherText = trim($_POST['subject_other'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $subject = $category === 'Other'
        ? ($otherText !== '' ? 'Other — ' . $otherText : 'Other')
        : $category;

    if ($category === '' || $message === '') {
        $flash = ['type' => 'error', 'text' => 'Please choose a category and fill in a message.'];
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO staff_concerns (submitted_by, submitted_by_email, subject, message, status)
             VALUES (:uid, :email, :subject, :message, "open")'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':email'   => $userEmail,
            ':subject' => $subject,
            ':message' => $message,
        ]);
        header('Location: staff-concern.php?sent=1');
        exit;
    }
}
if (isset($_GET['sent'])) {
    $flash = ['type' => 'ok', 'text' => 'Sent to Admin & Access — you\'ll see their reply here once triaged.'];
}

// This user's own concerns, newest first — each row is one "thread"
// (their message, plus the admin's resolution_notes reply if resolved).
$stmt = $pdo->prepare(
    'SELECT id, subject, message, status, resolution_notes, created_at, resolved_at
     FROM staff_concerns
     WHERE submitted_by = :uid
     ORDER BY created_at DESC'
);
$stmt->execute([':uid' => $userId]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$openCount = 0;
foreach ($threads as $t) {
    if ($t['status'] !== 'resolved') $openCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concerns — RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard.css">
    <link rel="stylesheet" href="/sc/staff-concern.css">
    <link rel="stylesheet" href="/../idle-timeout.css">
</head>
<body>
<div class="app-shell">

    <?php
    $__sidebarFile = ($userRole === 'cashier') ? '../sidebar-cashier.php' : '../sidebar.php';
    include __DIR__ . '/' . $__sidebarFile;
    ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-comments" style="margin-right:8px;"></i>Concerns</h1>
                    <p>Message Admin &amp; Access directly about anything you need help with.</p>
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
                    <a href="/logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content concern-content">

            <?php if ($flash): ?>
            <div class="promo-flash promo-flash-<?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash['text']) ?></div>
            <?php endif; ?>

            <div class="concern-layout">

                <!-- ============ new message ============ -->
                <section class="panel concern-compose">
                    <div class="panel-head">
                        <h3><i class="fa-solid fa-paper-plane"></i> New Message</h3>
                    </div>
                    <form method="post" class="concern-form-new">
                        <label class="pos-field-label">Category</label>
                        <select name="category" id="concernCategory" class="pos-select" required>
                            <option value="" disabled selected>Choose a category...</option>
                            <option value="Equipment / POS Issue">Equipment / POS Issue</option>
                            <option value="Inventory / Stock">Inventory / Stock</option>
                            <option value="Schedule / Shift">Schedule / Shift</option>
                            <option value="Payroll / Pay">Payroll / Pay</option>
                            <option value="Customer Complaint">Customer Complaint</option>
                            <option value="Coworker / Conduct">Coworker / Conduct</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" name="subject_other" id="concernSubjectOther" class="pos-input" placeholder="Briefly describe the category" maxlength="150" style="display:none; margin-top:10px;">
                        <label class="pos-field-label" style="margin-top:12px;">Message</label>
                        <textarea name="message" class="pos-input" rows="4" placeholder="Describe the issue or question..." required></textarea>
                        <button type="submit" class="pos-btn-primary concern-send-btn">
                            <i class="fa-solid fa-paper-plane"></i> Send to Admin
                        </button>
                    </form>
                </section>

                <!-- ============ thread history ============ -->
                <section class="panel concern-threads">
                    <div class="panel-head">
                        <h3><i class="fa-solid fa-inbox"></i> Your Messages <?php if ($openCount): ?><span class="badge-count"><?= $openCount ?> pending</span><?php endif; ?></h3>
                    </div>

                    <?php if (empty($threads)): ?>
                        <p class="empty-note">You haven't sent any messages yet — use the form to reach out to Admin &amp; Access.</p>
                    <?php else: ?>
                    <div class="concern-thread-list">
                        <?php foreach ($threads as $t): ?>
                        <div class="concern-thread">
                            <div class="concern-thread-head">
                                <strong><?= htmlspecialchars($t['subject']) ?></strong>
                                <span class="status-badge status-<?= $t['status'] === 'in_review' ? 'reserved' : ($t['status'] === 'resolved' ? 'fulfilled' : 'cancelled') ?>">
                                    <?= $t['status'] === 'in_review' ? 'In Review' : ucfirst($t['status']) ?>
                                </span>
                            </div>

                            <div class="chat-bubble chat-bubble-me">
                                <p><?= nl2br(htmlspecialchars($t['message'])) ?></p>
                                <time><?= htmlspecialchars((new DateTime($t['created_at']))->format('M j, Y g:i A')) ?></time>
                            </div>

                            <?php if ($t['resolution_notes']): ?>
                            <div class="chat-bubble chat-bubble-admin">
                                <span class="chat-bubble-from"><i class="fa-solid fa-user-shield"></i> Admin &amp; Access</span>
                                <p><?= nl2br(htmlspecialchars($t['resolution_notes'])) ?></p>
                                <time><?= $t['resolved_at'] ? htmlspecialchars((new DateTime($t['resolved_at']))->format('M j, Y g:i A')) : '' ?></time>
                            </div>
                            <?php elseif ($t['status'] !== 'resolved'): ?>
                            <p class="concern-waiting"><i class="fa-solid fa-hourglass-half"></i> Waiting on a reply...</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

            </div>
        </main>
    </div>
</div>

<script>
document.getElementById('concernCategory').addEventListener('change', (e) => {
    const otherField = document.getElementById('concernSubjectOther');
    const isOther = e.target.value === 'Other';
    otherField.style.display = isOther ? 'block' : 'none';
    otherField.required = isOther;
});
</script>
<script src="/notif-bell.js"></script>
<script src="/sidebar.js"></script>
<script src="/../idle-timeout.js"></script>
</body>
</html>