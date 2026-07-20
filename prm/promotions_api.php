<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/promotion_engine.php';

function api_respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    api_respond(401, ['ok' => false, 'message' => 'Please log in again.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = get_db_connection();

if ($action === 'scan') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $overrides = [];
    if (isset($input['percents']) && is_array($input['percents'])) {
        foreach (PROMO_ENGINE_REASONS as $reason) {
            if (isset($input['percents'][$reason]) && $input['percents'][$reason] !== '') {
                $overrides[$reason] = (float) $input['percents'][$reason];
            }
        }
    }

    $summary = apply_auto_promotions($pdo, $overrides);
    api_respond(200, ['ok' => true, 'summary' => $summary]);
}

if ($action === 'preview') {
    $preview = preview_auto_promotions($pdo);
    // Trim raw product rows out of the JSON payload - the page already
    // has counts server-side; this endpoint is only for a lightweight refresh.
    foreach ($preview as &$info) {
        $info['products'] = array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name']], $info['products']);
    }
    api_respond(200, ['ok' => true, 'preview' => $preview]);
}

api_respond(400, ['ok' => false, 'message' => 'Unknown action.']);
