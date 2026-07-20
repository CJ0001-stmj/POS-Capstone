<?php
session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/../db.php';

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

// NOTE: this schema has no per-line profit snapshot, so profit is always
// derived as (unit_price - product.cost) * quantity, using each product's
// *current* cost. If a product's cost changes later, historical profit
// figures computed this way will shift too — same approach used in
// dashboard.php's KPI/chart queries, for consistency.

function bucket_label_and_group_sql(string $range): array {
    // Returns [label_sql, group_sql]. label_sql is wrapped in MIN() by the
    // caller so this stays valid under strict ONLY_FULL_GROUP_BY SQL mode.
    switch ($range) {
        case 'daily':
            return [
                "DATE_FORMAT(s.created_at, '%Y-%m-%d')",
                "DATE(s.created_at)",
            ];
        case 'weekly':
            return [
                "CONCAT(YEAR(s.created_at), '-W', LPAD(WEEK(s.created_at, 1), 2, '0'))",
                "YEAR(s.created_at), WEEK(s.created_at, 1)",
            ];
        case 'monthly':
            return [
                "DATE_FORMAT(s.created_at, '%Y-%m')",
                "DATE_FORMAT(s.created_at, '%Y-%m')",
            ];
        case 'yearly':
        default:
            return [
                "YEAR(s.created_at)",
                "YEAR(s.created_at)",
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

[$labelSql, $groupSql] = bucket_label_and_group_sql($range);

// Per-sale profit, derived from sale_items joined to each product's
// current cost, then rolled up by time bucket alongside each sale's total.
$timeSeriesSql = "
    SELECT bucket_label, sales, profit, tx_count,
           CASE WHEN sales = 0 THEN 0 ELSE (profit / sales) END AS profit_margin
    FROM (
        SELECT
            MIN({$labelSql}) AS bucket_label,
            SUM(s.total) AS sales,
            SUM(COALESCE(ip.profit, 0)) AS profit,
            COUNT(DISTINCT s.id) AS tx_count
        FROM sales s
        LEFT JOIN (
            SELECT si.sale_id,
                   SUM((si.unit_price - COALESCE(p.cost, 0)) * si.quantity) AS profit
            FROM sale_items si
            LEFT JOIN products p ON p.id = si.product_id
            GROUP BY si.sale_id
        ) ip ON ip.sale_id = s.id
        WHERE s.status = 'completed'
          AND s.created_at >= :start AND s.created_at <= :end
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

// Product ranking — grouped by the snapshotted product_name (so it still
// ranks correctly even if a product was later renamed or deleted), with
// revenue/profit computed over the same time window.
$orderExpr = $metric === 'revenue' ? 'revenue' : 'profit';

$rankingSql = "
    SELECT
        si.product_id AS id,
        si.product_name AS name,
        COALESCE(p.sku, '—') AS sku,
        SUM(si.quantity) AS units_sold,
        SUM(si.line_total) AS revenue,
        SUM((si.unit_price - COALESCE(p.cost, 0)) * si.quantity) AS profit
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    LEFT JOIN products p ON p.id = si.product_id
    WHERE s.status = 'completed'
      AND s.created_at >= :start AND s.created_at <= :end
    GROUP BY si.product_id, si.product_name, p.sku
    ORDER BY {$orderExpr} DESC
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