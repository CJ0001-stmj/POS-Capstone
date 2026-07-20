<?php
// Add admin session check before deploy
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="recent-logins-' . date('Y-m-d-H-i-s') . '.csv"');

require_once __DIR__ . '/db.php';
$db = get_db_connection();

$hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
$stmt = $db->prepare(
    "SELECT email, ip_address, created_at, reason
     FROM login_audit
     WHERE success = 1 AND created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
     ORDER BY created_at DESC"
);
$stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
$stmt->execute();
$logins = $stmt->fetchAll();

$output = fopen('php://output', 'w');
fputcsv($output, ['Email', 'IP Address', 'Login Time', 'Notes']);

foreach ($logins as $login) {
    fputcsv($output, [
        $login['email'],
        $login['ip_address'] ?? '—',
        $login['created_at'],
        $login['reason'] ?? 'Normal login'
    ]);
}

fclose($output);
exit;