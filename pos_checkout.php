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
$amountReceived = (float) ($input['amount_received'] ?? 0);
$paymentMethod = in_array($input['payment_method'] ?? '', ['cash', 'gcash', 'card'], true)
    ? $input['payment_method']
    : 'cash';
// Cashier can choose to skip an active storewide promo (e.g. it doesn't
// apply to this customer/order), but this can never turn a discount ON
// that isn't actually live - see the lookups below. Auto-clearance
// promotions (near-expiration, slow-selling, etc.) are never optional -
// they always apply when a product qualifies, same as a marked-down price
// tag would in a physical store.
$applyStorewidePromo = filter_var($input['apply_promo'] ?? true, FILTER_VALIDATE_BOOLEAN);

// If this checkout is fulfilling a reservation (cashier clicked "Process"
// on a held reservation), the stock for these lines was already deducted
// when the reservation was created — we must NOT deduct it again here.
// We still create a normal sale/receipt so the cashier collects payment.
$reservationId = isset($input['reservation_id']) ? (int) $input['reservation_id'] : null;

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

    // If fulfilling a reservation, lock it now and make sure it's still
    // sitting there waiting (not already fulfilled/cancelled by someone
    // else in the meantime).
    if ($reservationId) {
        $stmt = $db->prepare('SELECT id, status FROM reservations WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reservation) {
            $db->rollBack();
            respond(404, ['ok' => false, 'message' => 'Reservation not found.']);
        }
        if ($reservation['status'] !== 'reserved') {
            $db->rollBack();
            respond(409, ['ok' => false, 'message' => 'This reservation was already ' . $reservation['status'] . '.']);
        }
    }

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

    // Storewide promo (optional, cashier-toggleable) - resolved here, not
    // trusted from the client, same as before.
    $storewidePromo = $applyStorewidePromo ? active_storewide_promotion($db) : null;

    $subtotal = 0.0;
    $totalDiscount = 0.0;
    $itemCount = 0;
    $lines = [];
    $promoNamesUsed = [];

    foreach ($requested as $pid => $qty) {
        if (!isset($productsById[$pid])) {
            $db->rollBack();
            respond(400, ['ok' => false, 'message' => "One of the items is no longer available."]);
        }
        $p = $productsById[$pid];
        if (!$reservationId && $qty > (int) $p['stock_qty']) {
            $db->rollBack();
            respond(409, ['ok' => false, 'message' => "Not enough stock for \"{$p['name']}\" (only {$p['stock_qty']} left)."]);
        }
        $unitPrice = (float) $p['price'];
        $lineSubtotal = round($unitPrice * $qty, 2);

        // Resolve the discount for this specific line: a per-product
        // clearance promotion (near-expiration, slow-selling, out-of-season,
        // replaced-model) always wins if it beats the storewide sale;
        // these never stack, the customer gets whichever markdown is bigger.
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
            'remaining_stock' => $reservationId ? (int) $p['stock_qty'] : (int) $p['stock_qty'] - $qty,
        ];
    }

    $subtotal = round($subtotal, 2);
    $discount = round($totalDiscount, 2);
    $total = round($subtotal - $discount, 2);

    // Sale-level promotion label: if every discounted line shared the same
    // promotion, show that name on the receipt; if different clearance
    // reasons applied to different lines, say so rather than picking one
    // arbitrarily. sale_items still has the accurate per-line record either way.
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
        'INSERT INTO sales (receipt_no, cashier_id, cashier_email, subtotal, discount, promotion_id, promotion_name, total, amount_received, change_due, payment_method, item_count, status)
         VALUES (:receipt_no, :cashier_id, :cashier_email, :subtotal, :discount, :promotion_id, :promotion_name, :total, :amount_received, :change_due, :payment_method, :item_count, "completed")'
    );
    // Placeholder receipt number first, patched with the real sale id right after insert.
    $insertSale->execute([
        ':receipt_no' => 'PENDING',
        ':cashier_id' => $_SESSION['user_id'],
        ':cashier_email' => $_SESSION['user_email'],
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':promotion_id' => $promotionId,
        ':promotion_name' => $promotionName,
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
        'INSERT INTO sale_items (sale_id, product_id, promotion_id, product_name, unit_price, promotion_name, quantity, discount_amount, line_total)
         VALUES (:sale_id, :product_id, :promotion_id, :product_name, :unit_price, :promotion_name, :quantity, :discount_amount, :line_total)'
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
            ':promotion_id' => $line['promotion_id'],
            ':product_name' => $line['name'],
            ':unit_price' => $line['unit_price'],
            ':promotion_name' => $line['promotion_name'],
            ':quantity' => $line['quantity'],
            ':discount_amount' => $line['discount_amount'],
            ':line_total' => $line['line_total'],
        ]);
        if (!$reservationId) {
            $updateStock->execute([':qty' => $line['quantity'], ':id' => $line['product_id']]);
            $insertMovement->execute([
                ':product_id' => $line['product_id'],
                ':change_qty' => -$line['quantity'],
                ':reference_id' => $saleId,
            ]);
        }
    }

    // Fulfilling a reservation: mark it settled and link it to the sale
    // that just got created, so receipt-history/order-history can trace
    // back to it. Guard the WHERE on status so a double-submit can't
    // fulfill the same reservation twice.
    if ($reservationId) {
        $fulfilled = $db->prepare(
            "UPDATE reservations SET status = 'fulfilled', fulfilled_at = NOW(), fulfilled_sale_id = :sale_id
             WHERE id = :id AND status = 'reserved'"
        );
        $fulfilled->execute([':sale_id' => $saleId, ':id' => $reservationId]);
        if ($fulfilled->rowCount() !== 1) {
            $db->rollBack();
            respond(409, ['ok' => false, 'message' => 'This reservation was already processed elsewhere.']);
        }
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
            'promotion_name' => $promotionName,
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