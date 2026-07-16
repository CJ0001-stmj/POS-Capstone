<?php
session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db.php';

$db = get_db_connection();

$range = strtolower(trim($_GET['range'] ?? 'monthly'));
if (!in_array($range, ['daily','weekly','monthly','yearly'], true)) {
    $range = 'monthly';
}

$metric = strtolower(trim($_GET['metric'] ?? 'profit'));
if (!in_array($metric, ['revenue','profit'], true)) {
    $metric = 'profit';
}

$topN = (int)($_GET['top'] ?? 8);
if ($topN <= 0 || $topN > 20) $topN = 8;

function bucket_label_and_group_sql(string $range): array {
    // Returns [label_sql, group_sql, order_sql]
    // label_sql is used in SELECT, group_sql in GROUP BY.
    switch ($range) {
        case 'daily':
            // YYYY-MM-DD
            return [
                "DATE(s.sale_date) AS bucket_label",
                "DATE(s.sale_date)",
                "bucket"
            ];
        case 'weekly':
            // ISO week-like: YEAR + week number (mode 1)
            return [
                "CONCAT(YEAR(s.sale_date), '-W', LPAD(WEEK(s.sale_date, 1), 2, '0')) AS bucket_label",
                "YEAR(s.sale_date), WEEK(s.sale_date, 1)",
                "bucket"
            ];
        case 'monthly':
            // YYYY-MM
            return [
                "DATE_FORMAT(s.sale_date, '%Y-%m') AS bucket_label",
                "DATE_FORMAT(s.sale_date, '%Y-%m')",
                "bucket"
            ];
        case 'yearly':
        default:
            // YYYY
            return [
                "YEAR(s.sale_date) AS bucket_label",
                "YEAR(s.sale_date)",
                "bucket"
            ];
    }
}

// Time window
$now = new DateTime('now');
$start = clone $now;

switch ($range) {
    case 'daily':
        $start->modify('-14 days');
        break;
    case 'weekly':
        $start->modify('-12 weeks');
        break;
    case 'yearly':
        $start->modify('-2 years');
        break;
    case 'monthly':
    default:
        $start->modify('-12 months');
        break;
}

$startStr = $start->format('Y-m-d H:i:s');
$endStr = $now->format('Y-m-d H:i:s');

[$labelSelectSql, $groupSql, $orderKey] = bucket_label_and_group_sql($range);

// Ensure group/order works consistently by selecting with an alias we can order.
// We'll order by actual bucket expression again in a derived query.
$timeSeriesSql = "
    SELECT bucket_label, sales, profit, tx_count, profit_margin
    FROM (
        SELECT 
            {$labelSelectSql},
            SUM(s.total_amount) AS sales,
            SUM(s.total_profit) AS profit,
            COUNT(*) AS tx_count,
            CASE WHEN SUM(s.total_amount) = 0 THEN 0 ELSE (SUM(s.total_profit) / SUM(s.total_amount)) END AS profit_margin
        FROM sales s
        WHERE s.sale_date >= :start AND s.sale_date <= :end
        GROUP BY {$groupSql}
    ) t
    ORDER BY bucket_label
";

$tsStmt = $db->prepare($timeSeriesSql);
$tsStmt->execute([':start' => $startStr, ':end' => $endStr]);
$rows = $tsStmt->fetchAll();

$labels = [];
$sales = [];
$profit = [];
$margins = [];
$txCounts = [];

foreach ($rows as $r) {
    $labels[] = $r['bucket_label'];
    $sales[] = (float)$r['sales'];
    $profit[] = (float)$r['profit'];
    $txCounts[] = (int)$r['tx_count'];
    $margins[] = (float)$r['profit_margin'];
}

// Business status (based on returned time window)
$totalSales = array_sum($sales);
$totalProfit = array_sum($profit);
$avgMargin = 0.0;
if ($totalSales > 0) {
    $avgMargin = $totalProfit / $totalSales;
}
$txTotal = array_sum($txCounts);

// Product ranking
$rankingMetricExpr = $metric === 'revenue' ? 'SUM(si.line_total)' : 'SUM(si.line_profit)';

// Use same time window
$rankingSql = "
    SELECT 
        p.id,
        p.name,
        p.sku,
        SUM(si.quantity) AS units_sold,
        SUM(si.line_total) AS revenue,
        SUM(si.line_profit) AS profit
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    WHERE s.status = 'completed'
      AND s.sale_date >= :start AND s.sale_date <= :end
    GROUP BY p.id, p.name, p.sku
    ORDER BY {$rankingMetricExpr} DESC
    LIMIT {$topN}
";


$rankStmt = $db->prepare($rankingSql);
$rankStmt->execute([':start' => $startStr, ':end' => $endStr]);
$ranking = $rankStmt->fetchAll();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'range' => $range,
    'metric' => $metric,
    'labels' => $labels,
    'series' => [
        'sales' => $sales,
        'profit' => $profit,
        'margin' => $margins,
        'tx_count' => $txCounts,
    ],
    'status' => [
        'total_sales' => (float)$totalSales,
        'total_profit' => (float)$totalProfit,
        'profit_margin' => (float)$avgMargin,
        'transaction_count' => (int)$txTotal,
    ],
    'ranking' => $ranking,
]);

