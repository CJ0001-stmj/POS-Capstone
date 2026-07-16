<?php
// Shared read helpers for the POS module. Included by both pos.php
// (server-rendered initial load) and pos_api.php (AJAX refresh after
// checkout), so the two never drift out of sync with each other.

require_once __DIR__ . '/db.php';

const LOW_STOCK_LIMIT = 12;      // rows shown in the Low Stock panel
const BEST_SELLER_LIMIT = 5;     // rows shown in the Best Sellers panel
const BEST_SELLER_WINDOW_DAYS = 30;

function pos_get_categories(PDO $db): array {
    $stmt = $db->query('SELECT id, name, icon FROM categories ORDER BY sort_order ASC, name ASC');
    return $stmt->fetchAll();
}

function pos_get_products(PDO $db): array {
    $stmt = $db->query(
        'SELECT id, category_id, sku, name, price, stock_qty, low_stock_threshold, image
         FROM products
         WHERE is_active = 1
         ORDER BY name ASC'
    );
    return $stmt->fetchAll();
}

function pos_get_low_stock(PDO $db): array {
    $stmt = $db->prepare(
        'SELECT id, name, stock_qty, low_stock_threshold
         FROM products
         WHERE is_active = 1 AND stock_qty <= low_stock_threshold
         ORDER BY stock_qty ASC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', LOW_STOCK_LIMIT, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function pos_get_best_sellers(PDO $db): array {
    $stmt = $db->prepare(
        'SELECT p.id, p.name, p.price, SUM(ti.quantity) AS units_sold
         FROM transaction_items ti
         JOIN transactions t ON t.id = ti.transaction_id
         JOIN products p ON p.id = ti.product_id
         WHERE t.created_at >= (NOW() - INTERVAL :days DAY)
         GROUP BY p.id, p.name, p.price
         ORDER BY units_sold DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':days', BEST_SELLER_WINDOW_DAYS, PDO::PARAM_INT);
    $stmt->bindValue(':limit', BEST_SELLER_LIMIT, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Bundles everything the POS screen needs into one array, so both the
// SSR page and the AJAX refresh endpoint build identical payloads.
function pos_get_bootstrap_data(PDO $db): array {
    return [
        'categories'   => pos_get_categories($db),
        'products'     => pos_get_products($db),
        'lowStock'     => pos_get_low_stock($db),
        'bestSellers'  => pos_get_best_sellers($db),
    ];
}

function pos_generate_transaction_code(PDO $db): string {
    $prefix = 'RY-' . date('Ymd') . '-';
    // Count today's transactions to build a stable-looking running number.
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM transactions WHERE transaction_code LIKE :p");
    $stmt->execute([':p' => $prefix . '%']);
    $count = (int)($stmt->fetch()['c'] ?? 0);
    return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
}