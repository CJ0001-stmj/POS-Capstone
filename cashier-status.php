<?php
/**
 * Polled by dashboard.js every 30s so "Cashier active status" stays
 * live without a full page reload. Same data/logic dashboard.php uses
 * on first paint — see cashier-status-lib.php.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cashier-status-lib.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not logged in.']);
    exit;
}
$userRole = $_SESSION['user_role'] ?? 'cashier';
if (!in_array($userRole, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not permitted.']);
    exit;
}

$pdo = get_db_connection();
echo json_encode(['ok' => true, 'cashiers' => build_cashier_status($pdo)]);
