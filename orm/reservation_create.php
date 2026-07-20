<?php
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
$cart = $input['cart'] ?? [];
$customerName = trim($input['customer_name'] ?? '');
$customerContact = trim($input['customer_contact'] ?? '');
$notes = trim($input['notes'] ?? '');
$applyStorewidePromo = filter_var($input['apply_promo'] ?? true, FILTER_VALIDATE_BOOLEAN);

if ($customerName === '') {
    respond(400, ['ok' => false, 'message' => 'Customer name is required for a reservation.']);
}
if (!is_array($cart) || count($cart) === 0) {
    respond(400, ['ok' => false, 'message' => 'Reservation has no items.']);
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
    respond(400, ['ok' => false, 'message' => 'Reservation has no valid items.']);
}

$db = get_db_connection();

try {
    $db->beginTransaction();

    // Lock and re-fetch real rows - same reasoning as pos_checkout.php:
    // price/stock can never be spoofed by the client, and a reservation
    // can't oversell stock that a live sale is also fighting over.
    $ids = array_keys($requested);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT id, name, price, stock_qty FROM products
         WHERE id IN ($placeholders) AND is_active = 1 FOR UPDATE"
    );
    $stmt->execute($ids);
    $productsById = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $productsById[(int) $p['id']] = $p;
    }

    $storewidePromo = $applyStorewidePromo ? active_storewide_promotion($db) : null;

    $subtotal = 0.0;
    $totalDiscount = 0.0;
    $itemCount = 0;
    $lines = [];
    $promoNamesUsed = [];

    foreach ($requested as $pid => $qty) {
        if (!isset($productsById[$pid])) {
            $db->rollBack();
            respond(400, ['ok' => false, 'message' => 'One of the items is no longer available.']);
        }
        $p = $productsById[$pid];
        if ($qty > (int) $p['stock_qty']) {
            $db->rollBack();
            respond(409, ['ok' => false, 'message' => "Not enough stock for \"{$p['name']}\" (only {$p['stock_qty']} left)."]);
        }
        $unitPrice = (float) $p['price'];
        $lineSubtotal = round($unitPrice * $qty, 2);

        $productPromo = best_product_promotion($db, $pid);
        $candidatePct = 0.0;
        $candidateId = null;
        $candidateName = null;
        if ($productPromo && (float) $productPromo['discount_percent'] > $candidatePct) {
            $candidatePct = (float) $productPromo['discount_percent'];
            $candidateId = (int) $productPromo['id'];
            $candidateName = $productPromo['name'];
        }
        if ($storewidePromo && (float) $storewidePromo['discount_percent'] > $candidatePct) {
            $candidatePct = (float) $storewidePromo['discount_percent'];
            $candidateId = (int) $storewidePromo['id'];
            $candidateName = $storewidePromo['name'];
        }

        $lineDiscount = $candidatePct > 0 ? round($lineSubtotal * ($candidatePct / 100), 2) : 0.00;
        $lineTotal = round($lineSubtotal - $lineDiscount, 2);

        $subtotal += $lineSubtotal;
        $totalDiscount += $lineDiscount;
        $itemCount += $qty;
        if ($candidateName) {
            $promoNamesUsed[$candidateName] = true;
        }

        $lines[] = [
            'product_id' => $pid,
            'name' => $p['name'],
            'unit_price' => $unitPrice,
            'quantity' => $qty,
            'discount_amount' => $lineDiscount,
            'line_total' => $lineTotal,
            'promotion_id' => $candidateId,
            'promotion_name' => $candidateName,
            'remaining_stock' => (int) $p['stock_qty'] - $qty,
        ];
    }

    $subtotal = round($subtotal, 2);
    $discount = round($totalDiscount, 2);
    $total = round($subtotal - $discount, 2);

    $promotionId = null;
    $promotionName = null;
    if (count($promoNamesUsed) === 1) {
        $promotionName = array_key_first($promoNamesUsed);
        foreach ($lines as $l) {
            if ($l['promotion_name'] === $promotionName) {
                $promotionId = $l['promotion_id'];
                break;
            }
        }
    } elseif (count($promoNamesUsed) > 1) {
        $promotionName = 'Multiple promotions';
    }

    $insertReservation = $db->prepare(
        'INSERT INTO reservations (reservation_no, customer_name, customer_contact, notes, staff_id, staff_email, subtotal, discount, promotion_id, promotion_name, total, item_count, status)
         VALUES (:reservation_no, :customer_name, :customer_contact, :notes, :staff_id, :staff_email, :subtotal, :discount, :promotion_id, :promotion_name, :total, :item_count, "reserved")'
    );
    $insertReservation->execute([
        ':reservation_no' => 'PENDING',
        ':customer_name' => $customerName,
        ':customer_contact' => $customerContact !== '' ? $customerContact : null,
        ':notes' => $notes !== '' ? $notes : null,
        ':staff_id' => $_SESSION['user_id'],
        ':staff_email' => $_SESSION['user_email'],
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':promotion_id' => $promotionId,
        ':promotion_name' => $promotionName,
        ':total' => $total,
        ':item_count' => $itemCount,
    ]);

    $reservationId = (int) $db->lastInsertId();
    $reservationNo = 'RSV' . date('Ymd') . '-' . str_pad((string) $reservationId, 5, '0', STR_PAD_LEFT);

    $db->prepare('UPDATE reservations SET reservation_no = :r WHERE id = :id')
       ->execute([':r' => $reservationNo, ':id' => $reservationId]);

    $insertItem = $db->prepare(
        'INSERT INTO reservation_items (reservation_id, product_id, promotion_id, product_name, unit_price, promotion_name, quantity, discount_amount, line_total)
         VALUES (:reservation_id, :product_id, :promotion_id, :product_name, :unit_price, :promotion_name, :quantity, :discount_amount, :line_total)'
    );
    // Reservation deducts stock the moment it's confirmed - the items are
    // spoken for even though no payment has changed hands yet.
    $updateStock = $db->prepare('UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :id');
    $insertMovement = $db->prepare(
        'INSERT INTO stock_movements (product_id, change_qty, reason, reference_id)
         VALUES (:product_id, :change_qty, "reservation", :reference_id)'
    );

    foreach ($lines as $line) {
        $insertItem->execute([
            ':reservation_id' => $reservationId,
            ':product_id' => $line['product_id'],
            ':promotion_id' => $line['promotion_id'],
            ':product_name' => $line['name'],
            ':unit_price' => $line['unit_price'],
            ':promotion_name' => $line['promotion_name'],
            ':quantity' => $line['quantity'],
            ':discount_amount' => $line['discount_amount'],
            ':line_total' => $line['line_total'],
        ]);
        $updateStock->execute([':qty' => $line['quantity'], ':id' => $line['product_id']]);
        $insertMovement->execute([
            ':product_id' => $line['product_id'],
            ':change_qty' => -$line['quantity'],
            ':reference_id' => $reservationId,
        ]);
    }

    $db->commit();

    respond(200, [
        'ok' => true,
        'reservation' => [
            'reservation_no' => $reservationNo,
            'customer_name' => $customerName,
            'customer_contact' => $customerContact,
            'notes' => $notes,
            'staff_email' => $_SESSION['user_email'],
            'created_at' => date('Y-m-d H:i:s'),
            'items' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'promotion_name' => $promotionName,
            'total' => $total,
            'item_count' => $itemCount,
        ],
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(500, ['ok' => false, 'message' => 'Reservation failed. Please try again.']);
}
