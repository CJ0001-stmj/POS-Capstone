<?php
// Run this ONCE from the command line to fill in demo sales history so the
// dashboard has something to chart across day/week/month/year views:
//   php seed_sales.php
//
// Safe to re-run: it always wipes and regenerates sales + sale_items,
// but leaves products and users alone.

require_once __DIR__ . '/db.php';

$db = get_db_connection();

$products = $db->query('SELECT id, cost_price, sell_price FROM products WHERE active = 1')->fetchAll();
if (!$products) {
    echo "No products found - run dashboard_schema.sql first.\n";
    exit(1);
}

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
$db->exec('TRUNCATE TABLE sale_items');
$db->exec('TRUNCATE TABLE sales');
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$insertSale = $db->prepare(
    'INSERT INTO sales (sale_date, total_amount, total_cost, total_profit)
     VALUES (:date, :amount, :cost, :profit)'
);
$insertItem = $db->prepare(
    'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, unit_cost, line_total, line_profit)
     VALUES (:sale_id, :product_id, :qty, :price, :cost, :line_total, :line_profit)'
);

// 14 months of history so "yearly" and "monthly" views both have
// something meaningful to show, with a mild weekend + growth trend.
$days = 14 * 30;
$startDate = (new DateTime())->modify("-$days days");
$saleCount = 0;

for ($d = 0; $d < $days; $d++) {
    $date = (clone $startDate)->modify("+$d days");
    $dayOfWeek = (int)$date->format('N'); // 1=Mon .. 7=Sun
    $isWeekend = $dayOfWeek >= 6;

    // Gentle upward trend over the 14 months + weekend bump.
    $growth = 1 + ($d / $days) * 0.6;
    $baseTransactions = $isWeekend ? rand(14, 22) : rand(6, 14);
    $transactions = max(1, (int)round($baseTransactions * $growth));

    for ($t = 0; $t < $transactions; $t++) {
        $hour = $isWeekend ? rand(10, 20) : rand(9, 21);
        $saleDateTime = (clone $date)->setTime($hour, rand(0, 59), rand(0, 59));

        $itemsInSale = rand(1, 4);
        $chosenProducts = (array)array_rand($products, min($itemsInSale, count($products)));

        $totalAmount = 0;
        $totalCost = 0;
        $lineData = [];

        foreach ($chosenProducts as $idx) {
            $product = $products[$idx];
            $qty = rand(1, 3);
            $price = (float)$product['sell_price'];
            $cost = (float)$product['cost_price'];
            $lineTotal = round($price * $qty, 2);
            $lineProfit = round(($price - $cost) * $qty, 2);

            $totalAmount += $lineTotal;
            $totalCost += $cost * $qty;

            $lineData[] = [
                'product_id'  => $product['id'],
                'qty'         => $qty,
                'price'       => $price,
                'cost'        => $cost,
                'line_total'  => $lineTotal,
                'line_profit' => $lineProfit,
            ];
        }

        $totalProfit = round($totalAmount - $totalCost, 2);

        $insertSale->execute([
            ':date'   => $saleDateTime->format('Y-m-d H:i:s'),
            ':amount' => round($totalAmount, 2),
            ':cost'   => round($totalCost, 2),
            ':profit' => $totalProfit,
        ]);
        $saleId = (int)$db->lastInsertId();
        $saleCount++;

        foreach ($lineData as $line) {
            $insertItem->execute([
                ':sale_id'     => $saleId,
                ':product_id'  => $line['product_id'],
                ':qty'         => $line['qty'],
                ':price'       => $line['price'],
                ':cost'        => $line['cost'],
                ':line_total'  => $line['line_total'],
                ':line_profit' => $line['line_profit'],
            ]);
        }
    }
}

echo "Seeded $saleCount demo transactions across $days days.\n";
