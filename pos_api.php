<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/pos_data.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in again.']);
    exit;
}

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

$db = get_db_connection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ---------------------------------------------------------------
// action=refresh  -> current products / low-stock / best-sellers.
// Used to resync the screen after a checkout without a full reload.
// ---------------------------------------------------------------
if ($action === 'refresh') {
    respond(200, ['ok' => true] + pos_get_bootstrap_data($db));
}

// ---------------------------------------------------------------
// action=checkout -> validate cart against live stock, write the
// transaction + line items + stock movements atomically, and
// decrement stock. Prices are always re-read from the database -
// the client's cart is treated as a list of {product_id, quantity}
// only, never trusted for pricing.
// ---------------------------------------------------------------
if ($action === 'checkout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $cartItems = $input['items'] ?? [];
    $amountReceived = (float)($input['amount_received'] ?? 0);
    $paymentMethod = in_array($input['payment_method'] ?? 'cash', ['cash', 'gcash', 'card'], true)
        ? $input['payment_method']
        : 'cash';

    if (!is_array($cartItems) || count($cartItems) === 0) {
        respond(400, ['ok' => false, 'message' => 'Cart is empty.']);
    }

    // Normalize + de-dupe requested quantities per product id.
    $requested = []; // product_id => qty
    foreach ($cartItems as $row) {
        $pid = (int)($row['product_id'] ?? 0);
        $qty = (int)($row['quantity'] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $requested[$pid] = ($requested[$pid] ?? 0) + $qty;
    }

    if (empty($requested)) {
        respond(400, ['ok' => false, 'message' => 'Cart has no valid items.']);
    }

    try {
        $db->beginTransaction();

        // Lock the rows we're about to sell so two simultaneous
        // checkouts on the same register can't oversell the same stock.
        $ids = array_keys($requested);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $lockStmt = $db->prepare(
            "SELECT id, name, price, stock_qty FROM products WHERE id IN ($placeholders) FOR UPDATE"
        );
        $lockStmt->execute($ids);
        $products = [];
        foreach ($lockStmt->fetchAll() as $p) {
            $products[$p['id']] = $p;
        }

        $lineItems = [];
        $subtotal = 0.0;
        $insufficient = [];

        foreach ($requested as $pid => $qty) {
            if (!isset($products[$pid])) {
                $insufficient[] = "Item #$pid is no longer available.";
                continue;
            }
            $p = $products[$pid];
            if ($p['stock_qty'] < $qty) {
                $insufficient[] = "{$p['name']} - only {$p['stock_qty']} left in stock.";
                continue;
            }
            $lineTotal = round($p['price'] * $qty, 2);
            $subtotal += $lineTotal;
            $lineItems[] = [
                'product_id' => $pid,
                'name'       => $p['name'],
                'unit_price' => $p['price'],
                'quantity'   => $qty,
                'line_total' => $lineTotal,
            ];
        }

        if (!empty($insufficient)) {
            $db->rollBack();
            respond(409, ['ok' => false, 'message' => implode(' ', $insufficient)]);
        }

        $subtotal = round($subtotal, 2);
        $totalAmount = $subtotal; // hook discounts/tax in here later if needed
        $changeDue = round($amountReceived - $totalAmount, 2);

        if ($amountReceived <= 0 || $changeDue < 0) {
            $db->rollBack();
            respond(400, ['ok' => false, 'message' => 'Amount received is less than the total due.']);
        }

        $itemCount = array_sum(array_column($lineItems, 'quantity'));
        $transactionCode = pos_generate_transaction_code($db);
        $cashierEmail = $_SESSION['user_email'] ?? null;

        $txStmt = $db->prepare(
            'INSERT INTO transactions
                (transaction_code, cashier_email, subtotal, discount_total, total_amount, amount_received, change_due, payment_method, item_count)
             VALUES (:code, :email, :subtotal, 0, :total, :received, :change, :method, :count)'
        );
        $txStmt->execute([
            ':code'     => $transactionCode,
            ':email'    => $cashierEmail,
            ':subtotal' => $subtotal,
            ':total'    => $totalAmount,
            ':received' => $amountReceived,
            ':change'   => $changeDue,
            ':method'   => $paymentMethod,
            ':count'    => $itemCount,
        ]);
        $transactionId = (int)$db->lastInsertId();

        $itemStmt = $db->prepare(
            'INSERT INTO transaction_items (transaction_id, product_id, product_name, unit_price, quantity, line_total)
             VALUES (:tid, :pid, :name, :price, :qty, :total)'
        );
        $stockStmt = $db->prepare('UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :id');
        $moveStmt = $db->prepare(
            'INSERT INTO stock_movements (product_id, change_qty, reason, reference_id)
             VALUES (:pid, :change, "sale", :ref)'
        );

        foreach ($lineItems as $li) {
            $itemStmt->execute([
                ':tid'   => $transactionId,
                ':pid'   => $li['product_id'],
                ':name'  => $li['name'],
                ':price' => $li['unit_price'],
                ':qty'   => $li['quantity'],
                ':total' => $li['line_total'],
            ]);
            $stockStmt->execute([':qty' => $li['quantity'], ':id' => $li['product_id']]);
            $moveStmt->execute([':pid' => $li['product_id'], ':change' => -$li['quantity'], ':ref' => $transactionId]);
        }

        $db->commit();

        respond(200, [
            'ok' => true,
            'message' => 'Transaction recorded.',
            'receipt' => [
                'transaction_code' => $transactionCode,
                'cashier_email'    => $cashierEmail,
                'items'            => $lineItems,
                'subtotal'         => $subtotal,
                'total_amount'     => $totalAmount,
                'amount_received'  => $amountReceived,
                'change_due'       => $changeDue,
                'payment_method'   => $paymentMethod,
                'created_at'       => date('Y-m-d H:i:s'),
            ],
        ] + pos_get_bootstrap_data($db));

    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        respond(500, ['ok' => false, 'message' => 'Checkout failed. Please try again.']);
    }
}

// ---------------------------------------------------------------
// action=fetch_transactions -> Get recent transaction reports
// featuring full logs of items, quantities, cash, and change.
// ---------------------------------------------------------------
if ($action === 'fetch_transactions') {
    try {
        $stmt = $db->query(
            'SELECT 
                t.transaction_code, 
                t.amount_received, 
                t.change_due, 
                t.payment_method, 
                t.total_amount, 
                t.created_at,
                GROUP_CONCAT(CONCAT(ti.product_name, " (", ti.quantity, ")") SEPARATOR ", ") AS item_details
             FROM transactions t
             JOIN transaction_items ti ON t.id = ti.transaction_id
             GROUP BY t.id
             ORDER BY t.created_at DESC
             LIMIT 20'
        );
        $transactions = $stmt->fetchAll();
        
        respond(200, ['ok' => true, 'transactions' => $transactions]);
    } catch (Throwable $e) {
        respond(500, ['ok' => false, 'message' => 'Failed to fetch reports.']);
    }
}

respond(400, ['ok' => false, 'message' => 'Unknown action.']);