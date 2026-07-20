<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';



if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not logged in.']);
    exit;
}

function peso($n): string {
    return '₱' . number_format((float)$n, 0);
}

$pdo = get_db_connection();

$shiftStart = isset($_SESSION['logged_in_at'])
    ? (new DateTime())->setTimestamp($_SESSION['logged_in_at'])
    : new DateTime();
$now = new DateTime();
$fmt = 'Y-m-d H:i:s';

// Same assumption as dashboard-cashier.php: sales.cashier_id -> users.id.
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

echo json_encode([
    'ok'              => true,
    'sales'           => (float)$shift['sales'],
    'sales_formatted' => peso($shift['sales']),
    'trans_count'     => (int)$shift['trans_count'],
]);
