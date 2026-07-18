<?php
/**
 * Notifications endpoint for the topbar bell — one shared file every
 * page's bell can call, so it's not duplicated per-page.
 *
 * GET  -> { ok, notifications: [...], unread_count }   (latest 20 for the logged-in user)
 * POST action=mark_read -> marks all of the logged-in user's unread rows read
 *
 * Currently only staff_warnings feeds this (admin -> cashier/staff
 * warnings). Add more notification sources later by UNIONing them in
 * below and giving each a `type`.
 */
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $stmt = $conn->prepare("UPDATE staff_warnings SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    echo json_encode(['ok' => true]);
    exit;
}

$stmt = $conn->prepare("
    SELECT sw.id, sw.message, sw.created_at, sw.read_at, admin.email AS sent_by_email
    FROM staff_warnings sw
    LEFT JOIN users admin ON admin.id = sw.sent_by
    WHERE sw.user_id = ?
    ORDER BY sw.created_at DESC
    LIMIT 20
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unreadCount = 0;
foreach ($notifications as $n) {
    if ($n['read_at'] === null) {
        $unreadCount++;
    }
}

echo json_encode([
    'ok' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount,
]);
exit;
