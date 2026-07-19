<?php
/**
 * Walk-in order checkout — cash only.
 *
 * Called from orders.js when transactionType === 'order'. Unlike a
 * reservation (stock set aside, payment later), this finalizes the sale
 * immediately: validates stock, deducts it, records the sale + line
 * items, and hands back everything orders.js needs to render a receipt.
 * No other payment method is accepted here — walk-ins pay cash on the
 * spot; card/GCash stay on pos.php's own checkout.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/promotion_engine.php';

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
$items = is_array($input['items'] ?? null) ? $input['items'] : [];
$applyPromo = !empty($input['apply_promo']);
$amountReceived = round((float) ($input['amount_received'] ?? 0), 2);

if (empty($items)) {
    respond(400, ['ok' => false, 'message' => 'Add at least one item before checking out.']);
}

$db = get_db_connection();

try {
    $db->beginTransaction();

    // Lock every product row this sale touches so stock can't drift out
    // from under us if two cashiers ring up the same item at once.
    $lines = [];
    $subtotal = 0.0;

    foreach ($items as $line) {
        $productId = (int) ($line['product_id'] ?? 0);
        $qty       = (int) ($line['quantity'] ?? 0);
        if ($productId <= 0 || $qty <= 0) continue;

        $stmt = $db->prepare('SELECT id, name, price, stock_qty FROM products WHERE id = :id AND is_active = 1 FOR UPDATE');
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();
        if (!$product) {
            $db->rollBack();
            respond(404, ['ok' => false, 'message' => 'One of the items in this order is no longer available.']);
        }
        if ($qty > (int) $product['stock_qty']) {
            $db->rollBack();
            respond(409, ['ok' => false, 'message' => "Not enough stock for {$product['name']} — only {$product['stock_qty']} left."]);
        }

        $lineTotal = round((float) $product['price'] * $qty, 2);
        $subtotal += $lineTotal;

        $lines[] = [
            'product_id' => (int) $product['id'],
            'name'       => $product['name'],
            'unit_price' => (float) $product['price'],
            'quantity'   => $qty,
            'line_total' => $lineTotal,
            'stock_qty'  => (int) $product['stock_qty'],
        ];
    }

    if (empty($lines)) {
        $db->rollBack();
        respond(400, ['ok' => false, 'message' => 'Add at least one item before checking out.']);
    }

    $subtotal = round($subtotal, 2);

    $promo = $applyPromo ? active_storewide_promotion($db) : null;
    $discount = $promo ? round($subtotal * ((float) $promo['discount_percent'] / 100), 2) : 0.0;
    $total = round($subtotal - $discount, 2);

    if ($amountReceived < $total) {
        $db->rollBack();
        respond(400, ['ok' => false, 'message' => 'Amount received is less than the total due.']);
    }
    $changeDue = round($amountReceived - $total, 2);

    $itemCount = array_sum(array_column($lines, 'quantity'));

    $insert = $db->prepare(
        'INSERT INTO sales
            (receipt_no, cashier_id, cashier_email, subtotal, discount, promotion_id, promotion_name,
             total, amount_received, change_due, payment_method, item_count, status)
         VALUES
            (:receipt_no, :cashier_id, :cashier_email, :subtotal, :discount, :promotion_id, :promotion_name,
             :total, :amount_received, :change_due, "cash", :item_count, "completed")'
    );
    $insert->execute([
        ':receipt_no'      => 'PENDING',
        ':cashier_id'      => $_SESSION['user_id'],
        ':cashier_email'   => $_SESSION['user_email'],
        ':subtotal'        => $subtotal,
        ':discount'        => $discount,
        ':promotion_id'    => $promo['id'] ?? null,
        ':promotion_name'  => $promo['name'] ?? null,
        ':total'           => $total,
        ':amount_received' => $amountReceived,
        ':change_due'      => $changeDue,
        ':item_count'      => $itemCount,
    ]);

    $saleId = (int) $db->lastInsertId();
    $receiptNo = 'RY' . date('Ymd') . '-' . str_pad((string) $saleId, 5, '0', STR_PAD_LEFT);
    $db->prepare('UPDATE sales SET receipt_no = :r WHERE id = :id')
       ->execute([':r' => $receiptNo, ':id' => $saleId]);

    $itemInsert = $db->prepare(
        'INSERT INTO sale_items (sale_id, product_id, product_name, unit_price, quantity, line_total)
         VALUES (:sale_id, :product_id, :product_name, :unit_price, :quantity, :line_total)'
    );
    $stockUpdate = $db->prepare('UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :id');
    $movementInsert = $db->prepare(
        'INSERT INTO stock_movements (product_id, change_qty, reason, reference_id)
         VALUES (:product_id, :change_qty, "sale", :reference_id)'
    );

    $responseItems = [];
    foreach ($lines as $line) {
        $itemInsert->execute([
            ':sale_id'      => $saleId,
            ':product_id'   => $line['product_id'],
            ':product_name' => $line['name'],
            ':unit_price'   => $line['unit_price'],
            ':quantity'     => $line['quantity'],
            ':line_total'   => $line['line_total'],
        ]);
        $stockUpdate->execute([':qty' => $line['quantity'], ':id' => $line['product_id']]);
        $movementInsert->execute([
            ':product_id'   => $line['product_id'],
            ':change_qty'   => -$line['quantity'],
            ':reference_id' => $saleId,
        ]);

        $responseItems[] = [
            'product_id'      => $line['product_id'],
            'name'            => $line['name'],
            'unit_price'      => $line['unit_price'],
            'quantity'        => $line['quantity'],
            'line_total'      => $line['line_total'],
            'remaining_stock' => $line['stock_qty'] - $line['quantity'],
        ];
    }

    $db->commit();

    respond(200, [
        'ok'   => true,
        'sale' => [
            'id'              => $saleId,
            'receipt_no'      => $receiptNo,
            'cashier_email'   => $_SESSION['user_email'],
            'subtotal'        => $subtotal,
            'discount'        => $discount,
            'promotion_name'  => $promo['name'] ?? null,
            'total'           => $total,
            'amount_received' => $amountReceived,
            'change_due'      => $changeDue,
            'payment_method'  => 'cash',
            'item_count'      => $itemCount,
            'created_at'      => (new DateTime())->format('M j, Y g:i A'),
            'items'           => $responseItems,
        ],
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(500, ['ok' => false, 'message' => 'Could not process this payment. Please try again.']);
}