<?php
/**
 * Processes a queued order or reservation from orders.php.
 *
 * "complete" = collect cash payment now, write a real `sales` row, mark
 * the source row done. "cancel" = restore the stock that was deducted
 * when the order/reservation was created, mark it cancelled. This file
 * never creates new orders/reservations - those land in the source
 * tables from elsewhere; this only resolves rows that are already there.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

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

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$type   = $input['type'] ?? '';
$id     = (int) ($input['id'] ?? 0);
$action = $input['action'] ?? '';
$amountReceived = round((float) ($input['amount_received'] ?? 0), 2);

if (!in_array($type, ['order', 'reservation'], true)) {
    respond(400, ['ok' => false, 'message' => 'Unknown queue type.']);
}
if ($id <= 0) {
    respond(400, ['ok' => false, 'message' => 'Missing id.']);
}
if (!in_array($action, ['complete', 'cancel'], true)) {
    respond(400, ['ok' => false, 'message' => 'Unknown action.']);
}

$db = get_db_connection();

try {
    $db->beginTransaction();

    if ($type === 'order') {
        $stmt = $db->prepare('SELECT * FROM pending_orders WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) { $db->rollBack(); respond(404, ['ok' => false, 'message' => 'Order not found.']); }
        if ($row['status'] !== 'pending') { $db->rollBack(); respond(409, ['ok' => false, 'message' => 'This order was already processed.']); }

        $stmt = $db->prepare('SELECT * FROM pending_order_items WHERE pending_order_id = :id');
        $stmt->execute([':id' => $id]);
        $lines = $stmt->fetchAll();

        if ($action === 'cancel') {
            foreach ($lines as $line) {
                if (!$line['product_id']) continue;
                $db->prepare('UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :pid')
                   ->execute([':qty' => $line['quantity'], ':pid' => $line['product_id']]);
                $db->prepare('INSERT INTO stock_movements (product_id, change_qty, reason, reference_id) VALUES (:pid, :qty, "void", :ref)')
                   ->execute([':pid' => $line['product_id'], ':qty' => $line['quantity'], ':ref' => $id]);
            }
            $db->prepare('UPDATE pending_orders SET status = "cancelled", processed_by_email = :email, processed_at = NOW() WHERE id = :id')
               ->execute([':email' => $_SESSION['user_email'], ':id' => $id]);
            $db->commit();
            respond(200, ['ok' => true, 'type' => 'order', 'id' => $id, 'status' => 'cancelled']);
        }

        if ($amountReceived < (float) $row['total']) {
            $db->rollBack();
            respond(400, ['ok' => false, 'message' => 'Amount received is less than the total due.']);
        }
        $changeDue = round($amountReceived - (float) $row['total'], 2);

        $saleId = insert_sale_from_lines($db, $lines, (float) $row['subtotal'], (float) $row['discount'],
            $row['promotion_name'], (float) $row['total'], $amountReceived, $changeDue, (int) $row['item_count']);

        $db->prepare('UPDATE pending_orders SET status = "completed", processed_by_email = :email, processed_at = NOW(), sale_id = :sale_id WHERE id = :id')
           ->execute([':email' => $_SESSION['user_email'], ':sale_id' => $saleId, ':id' => $id]);

        $db->commit();
        respond(200, ['ok' => true, 'sale' => build_receipt($db, $saleId)]);
    }

    // ---------- reservation ----------
    $stmt = $db->prepare('SELECT * FROM reservations WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) { $db->rollBack(); respond(404, ['ok' => false, 'message' => 'Reservation not found.']); }
    if ($row['status'] !== 'reserved') { $db->rollBack(); respond(409, ['ok' => false, 'message' => 'This reservation was already resolved.']); }

    $stmt = $db->prepare('SELECT * FROM reservation_items WHERE reservation_id = :id');
    $stmt->execute([':id' => $id]);
    $lines = $stmt->fetchAll();

    if ($action === 'cancel') {
        foreach ($lines as $line) {
            if (!$line['product_id']) continue;
            $db->prepare('UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :pid')
               ->execute([':qty' => $line['quantity'], ':pid' => $line['product_id']]);
            $db->prepare('INSERT INTO stock_movements (product_id, change_qty, reason, reference_id) VALUES (:pid, :qty, "void", :ref)')
               ->execute([':pid' => $line['product_id'], ':qty' => $line['quantity'], ':ref' => $id]);
        }
        $db->prepare('UPDATE reservations SET status = "cancelled", cancelled_at = NOW() WHERE id = :id')
           ->execute([':id' => $id]);
        $db->commit();
        respond(200, ['ok' => true, 'type' => 'reservation', 'id' => $id, 'status' => 'cancelled']);
    }

    if ($amountReceived < (float) $row['total']) {
        $db->rollBack();
        respond(400, ['ok' => false, 'message' => 'Amount received is less than the total due.']);
    }
    $changeDue = round($amountReceived - (float) $row['total'], 2);

    // reservation_items uses product_name/unit_price/quantity/line_total same shape as pending_order_items
    $saleId = insert_sale_from_lines($db, $lines, (float) $row['subtotal'], (float) $row['discount'],
        $row['promotion_name'], (float) $row['total'], $amountReceived, $changeDue, (int) $row['item_count']);

    $db->prepare('UPDATE reservations SET status = "fulfilled", fulfilled_at = NOW(), fulfilled_sale_id = :sale_id WHERE id = :id')
       ->execute([':sale_id' => $saleId, ':id' => $id]);

    $db->commit();
    respond(200, ['ok' => true, 'sale' => build_receipt($db, $saleId)]);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(500, ['ok' => false, 'message' => 'Could not process this. Please try again.']);
}

function insert_sale_from_lines(PDO $db, array $lines, float $subtotal, float $discount, ?string $promoName,
                                 float $total, float $amountReceived, float $changeDue, int $itemCount): int {
    $insert = $db->prepare(
        'INSERT INTO sales
            (receipt_no, cashier_id, cashier_email, subtotal, discount, promotion_name,
             total, amount_received, change_due, payment_method, item_count, status)
         VALUES
            (:receipt_no, :cashier_id, :cashier_email, :subtotal, :discount, :promotion_name,
             :total, :amount_received, :change_due, "cash", :item_count, "completed")'
    );
    $insert->execute([
        ':receipt_no'      => 'PENDING',
        ':cashier_id'      => $_SESSION['user_id'],
        ':cashier_email'   => $_SESSION['user_email'],
        ':subtotal'        => $subtotal,
        ':discount'        => $discount,
        ':promotion_name'  => $promoName,
        ':total'           => $total,
        ':amount_received' => $amountReceived,
        ':change_due'      => $changeDue,
        ':item_count'      => $itemCount,
    ]);
    $saleId = (int) $db->lastInsertId();
    $receiptNo = 'RY' . date('Ymd') . '-' . str_pad((string) $saleId, 5, '0', STR_PAD_LEFT);
    $db->prepare('UPDATE sales SET receipt_no = :r WHERE id = :id')->execute([':r' => $receiptNo, ':id' => $saleId]);

    $itemInsert = $db->prepare(
        'INSERT INTO sale_items (sale_id, product_id, product_name, unit_price, quantity, line_total)
         VALUES (:sale_id, :product_id, :product_name, :unit_price, :quantity, :line_total)'
    );
    foreach ($lines as $line) {
        $itemInsert->execute([
            ':sale_id'      => $saleId,
            ':product_id'   => $line['product_id'],
            ':product_name' => $line['product_name'],
            ':unit_price'   => $line['unit_price'],
            ':quantity'     => $line['quantity'],
            ':line_total'   => $line['line_total'],
        ]);
    }
    return $saleId;
}

function build_receipt(PDO $db, int $saleId): array {
    $stmt = $db->prepare('SELECT * FROM sales WHERE id = :id');
    $stmt->execute([':id' => $saleId]);
    $sale = $stmt->fetch();

    $stmt = $db->prepare('SELECT product_name, unit_price, quantity, line_total FROM sale_items WHERE sale_id = :id');
    $stmt->execute([':id' => $saleId]);
    $items = array_map(function ($it) {
        return [
            'name'       => $it['product_name'],
            'unit_price' => (float) $it['unit_price'],
            'quantity'   => (int) $it['quantity'],
            'line_total' => (float) $it['line_total'],
        ];
    }, $stmt->fetchAll());

    return [
        'receipt_no'      => $sale['receipt_no'],
        'cashier_email'   => $sale['cashier_email'],
        'subtotal'        => (float) $sale['subtotal'],
        'discount'        => (float) $sale['discount'],
        'promotion_name'  => $sale['promotion_name'],
        'total'           => (float) $sale['total'],
        'amount_received' => (float) $sale['amount_received'],
        'change_due'      => (float) $sale['change_due'],
        'created_at'      => (new DateTime($sale['created_at']))->format('M j, Y g:i A'),
        'items'           => $items,
    ];
}