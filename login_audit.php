<?php
// Not authenticated in this starter - add an admin session/API-key check
// before exposing this publicly.
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$db = get_db_connection();

$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;

$stmt = $db->prepare('SELECT email, success, reason, ip_address, user_agent, created_at
                       FROM login_audit ORDER BY created_at DESC LIMIT :limit');
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$entries = $stmt->fetchAll();

echo json_encode(['ok' => true, 'count' => count($entries), 'entries' => $entries]);
