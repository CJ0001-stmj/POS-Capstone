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
$cart = $input['cart'] ?? [];
$amountReceived = (float) ($input['amount_received'] ?? 0);
$paymentMethod = in_array($input['payment_method'] ?? '', ['cash', 'gcash', 'card'], true)
    ? $input['payment_method']
    : 'cash';

if (!is_array($cart) || count($cart) === 0) {
    respond(400, ['ok' => false, 'message' => 'Cart is empty.']);
}

// Normalize + de-duplicate incoming lines: [{product_id, quantity}, ...]
$requested = [];
foreach ($cart as $line) {
    $pid = (int) ($line['product_id'] ?? 0);
    $qty = (int) ($line['quantity'] ?? 0);
    if ($pid <= 0 || $qty <= 0) {
        continue;
    }
    $requested[$pid] = ($requested[$pid] ?? 0) + $qty;
}

if (count($requested) === 0) {
    respond(400, ['ok' => false, 'message' => 'Cart has no valid items.']);
}

$db = get_db_connection();

try {
    $db->beginTransaction();

    // Lock and re-fetch the real rows so price/stock can never be spoofed
    // by the client, and two cashiers can't oversell the same last unit.
    $ids = array_keys($requested);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT id, name, price, stock_qty FROM products
         WHERE id IN ($placeholders) AND is_active = 1 FOR UPDATE"
    );
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $productsById = [];
    foreach ($products as $p) {
        $productsById[(int) $p['id']] = $p;
    }

    $subtotal = 0.0;
    $itemCount = 0;
    $lines = [];

    foreach ($requested as $pid => $qty) {
        if (!isset($productsById[$pid])) {
            $db->rollBack();
            respond(400, ['ok' => false, 'message' => "One of the items is no longer available."]);
        }
        $p = $productsById[$pid];
        if ($qty > (int) $p['stock_qty']) {
            $db->rollBack();
            respond(409, ['ok' => false, 'message' => "Not enough stock for \"{$p['name']}\" (only {$p['stock_qty']} left)."]);
        }
        $unitPrice = (float) $p['price'];
        $lineTotal = round($unitPrice * $qty, 2);
        $subtotal += $lineTotal;
        $itemCount += $qty;
        $lines[] = [
            'product_id' => $pid,
            'name' => $p['name'],
            'unit_price' => $unitPrice,
            'quantity' => $qty,
            'line_total' => $lineTotal,
        ];
    }

    $subtotal = round($subtotal, 2);
    $discount = 0.00;
    $total = round($subtotal - $discount, 2);

    if ($paymentMethod !== 'cash') {
        // Non-cash tenders settle exactly; no change is generated.
        $amountReceived = $total;
    }

    if ($amountReceived < $total) {
        $db->rollBack();
        respond(400, ['ok' => false, 'message' => 'Amount received is less than the total due.']);
    }

    $changeDue = round($amountReceived - $total, 2);

    $insertSale = $db->prepare(
        'INSERT INTO sales (receipt_no, cashier_id, cashier_email, subtotal, discount, total, amount_received, change_due, payment_method, item_count, status)
         VALUES (:receipt_no, :cashier_id, :cashier_email, :subtotal, :discount, :total, :amount_received, :change_due, :payment_method, :item_count, "completed")'
    );
    // Placeholder receipt number first, patched with the real sale id right after insert.
    $insertSale->execute([
        ':receipt_no' => 'PENDING',
        ':cashier_id' => $_SESSION['user_id'],
        ':cashier_email' => $_SESSION['user_email'],
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':total' => $total,
        ':amount_received' => $amountReceived,
        ':change_due' => $changeDue,
        ':payment_method' => $paymentMethod,
        ':item_count' => $itemCount,
    ]);

    $saleId = (int) $db->lastInsertId();
    $receiptNo = 'RY' . date('Ymd') . '-' . str_pad((string) $saleId, 5, '0', STR_PAD_LEFT);

    $db->prepare('UPDATE sales SET receipt_no = :r WHERE id = :id')
       ->execute([':r' => $receiptNo, ':id' => $saleId]);

    $insertItem = $db->prepare(
        'INSERT INTO sale_items (sale_id, product_id, product_name, unit_price, quantity, line_total)
         VALUES (:sale_id, :product_id, :product_name, :unit_price, :quantity, :line_total)'
    );
    $updateStock = $db->prepare('UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :id');
    $insertMovement = $db->prepare(
        'INSERT INTO stock_movements (product_id, change_qty, reason, reference_id)
         VALUES (:product_id, :change_qty, "sale", :reference_id)'
    );

    foreach ($lines as $line) {
        $insertItem->execute([
            ':sale_id' => $saleId,
            ':product_id' => $line['product_id'],
            ':product_name' => $line['name'],
            ':unit_price' => $line['unit_price'],
            ':quantity' => $line['quantity'],
            ':line_total' => $line['line_total'],
        ]);
        $updateStock->execute([':qty' => $line['quantity'], ':id' => $line['product_id']]);
        $insertMovement->execute([
            ':product_id' => $line['product_id'],
            ':change_qty' => -$line['quantity'],
            ':reference_id' => $saleId,
        ]);
    }

    $db->commit();

    respond(200, [
        'ok' => true,
        'receipt' => [
            'receipt_no' => $receiptNo,
            'cashier_email' => $_SESSION['user_email'],
            'created_at' => date('Y-m-d H:i:s'),
            'items' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'amount_received' => $amountReceived,
            'change_due' => $changeDue,
            'payment_method' => $paymentMethod,
            'item_count' => $itemCount,
        ],
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(500, ['ok' => false, 'message' => 'Checkout failed. Please try again.']);
}