<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    respond(401, ['ok' => false, 'message' => 'Please log in again.']);
}
$userRole = $_SESSION['user_role'] ?? 'cashier';
if (!in_array($userRole, ['admin', 'manager'], true)) {
    respond(403, ['ok' => false, 'message' => 'You do not have permission to review requests.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$requestId = (int) ($input['request_id'] ?? 0);
$action    = $input['action'] ?? '';
$reviewNotes = trim($input['review_notes'] ?? '');

if ($requestId <= 0) {
    respond(400, ['ok' => false, 'message' => 'Missing request id.']);
}
if (!in_array($action, ['approve', 'reject'], true)) {
    respond(400, ['ok' => false, 'message' => 'Unknown action.']);
}

$db = get_db_connection();

try {
    $db->beginTransaction();

    // Lock the request row so two reviewers can't resolve the same
    // request twice at the same time.
    $stmt = $db->prepare('SELECT * FROM purchase_requests WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        $db->rollBack();
        respond(404, ['ok' => false, 'message' => 'Request not found.']);
    }
    if ($request['status'] !== 'pending') {
        $db->rollBack();
        respond(409, ['ok' => false, 'message' => 'This request was already reviewed.']);
    }

    if ($action === 'approve') {
        // Lock the product row too - the stock update and the request's
        // resolution need to land together or not at all.
        $stmt = $db->prepare('SELECT id, stock_qty FROM products WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $request['product_id']]);
        $product = $stmt->fetch();
        if (!$product) {
            $db->rollBack();
            respond(404, ['ok' => false, 'message' => 'The product on this request no longer exists.']);
        }

        $qty = (int) $request['quantity_requested'];

        $db->prepare('UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :id')
           ->execute([':qty' => $qty, ':id' => $product['id']]);

        $db->prepare(
            'INSERT INTO stock_movements (product_id, change_qty, reason, reference_id)
             VALUES (:product_id, :change_qty, "purchase_request", :reference_id)'
        )->execute([
            ':product_id'   => $product['id'],
            ':change_qty'   => $qty,
            ':reference_id' => $requestId,
        ]);

        $newStatus = 'approved';
    } else {
        $newStatus = 'rejected';
    }

    $db->prepare(
        'UPDATE purchase_requests
         SET status = :status, reviewed_by_email = :reviewed_by, review_notes = :notes, reviewed_at = NOW()
         WHERE id = :id'
    )->execute([
        ':status'      => $newStatus,
        ':reviewed_by' => $_SESSION['user_email'],
        ':notes'       => $reviewNotes !== '' ? $reviewNotes : null,
        ':id'          => $requestId,
    ]);

    $db->commit();

    respond(200, [
        'ok' => true,
        'request_id'   => $requestId,
        'status'       => $newStatus,
        'reviewed_by'  => $_SESSION['user_email'],
        'new_stock'    => $action === 'approve' ? ((int)$product['stock_qty'] + (int)$request['quantity_requested']) : null,
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(500, ['ok' => false, 'message' => 'Could not process this request. Please try again.']);
}
