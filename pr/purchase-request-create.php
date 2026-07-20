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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$productId    = (int) ($input['product_id'] ?? 0);
$supplierName = trim($input['supplier_name'] ?? '');
$quantity     = (int) ($input['quantity'] ?? 0);
$notes        = trim($input['notes'] ?? '');

if ($productId <= 0) {
    respond(400, ['ok' => false, 'message' => 'Pick a product to restock.']);
}
if ($supplierName === '') {
    respond(400, ['ok' => false, 'message' => 'Supplier name is required.']);
}
if ($quantity <= 0) {
    respond(400, ['ok' => false, 'message' => 'Quantity must be at least 1.']);
}

$db = get_db_connection();

$stmt = $db->prepare('SELECT id, sku, name FROM products WHERE id = :id AND is_active = 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();
if (!$product) {
    respond(404, ['ok' => false, 'message' => 'That product is no longer available.']);
}

try {
    $db->beginTransaction();

    $insert = $db->prepare(
        'INSERT INTO purchase_requests
            (request_no, product_id, product_name, sku, supplier_name, quantity_requested,
             notes, status, requested_by_id, requested_by_email)
         VALUES
            (:request_no, :product_id, :product_name, :sku, :supplier_name, :quantity,
             :notes, "pending", :requested_by_id, :requested_by_email)'
    );
    $insert->execute([
        ':request_no'         => 'PENDING',
        ':product_id'         => $product['id'],
        ':product_name'       => $product['name'],
        ':sku'                => $product['sku'],
        ':supplier_name'      => $supplierName,
        ':quantity'           => $quantity,
        ':notes'              => $notes !== '' ? $notes : null,
        ':requested_by_id'    => $_SESSION['user_id'],
        ':requested_by_email' => $_SESSION['user_email'],
    ]);

    $requestId = (int) $db->lastInsertId();
    $requestNo = 'PR' . date('Ymd') . '-' . str_pad((string) $requestId, 5, '0', STR_PAD_LEFT);

    $db->prepare('UPDATE purchase_requests SET request_no = :r WHERE id = :id')
       ->execute([':r' => $requestNo, ':id' => $requestId]);

    $db->commit();

    respond(200, [
        'ok' => true,
        'request' => [
            'id'             => $requestId,
            'request_no'     => $requestNo,
            'product_id'     => $product['id'],
            'product_name'   => $product['name'],
            'sku'            => $product['sku'],
            'supplier_name'  => $supplierName,
            'quantity'       => $quantity,
            'status'         => 'pending',
            'requested_by'   => $_SESSION['user_email'],
            'created_at'     => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(500, ['ok' => false, 'message' => 'Could not submit the request. Please try again.']);
}
