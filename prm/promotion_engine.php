<?php
/**
 * Promotion Engine
 * -----------------
 * Analytics-driven markdown engine. Scans product/sales data for four
 * clearance reasons and keeps one auto-generated, per-product promotion
 * row in sync for each:
 *
 *   - near_expiration : products.expiry_date is coming up soon
 *   - slow_selling      : no sales at all in the lookback window
 *   - replaced_model    : products.is_superseded = 1
 *
 * Nothing here touches the POS directly - pos_checkout.php and pos.php
 * just read whatever active promotions/promotion_products rows exist,
 * the same way they already read the storewide promo. Running the scan
 * (apply_auto_promotions) is what turns analytics into a live discount.
 */

// Default discount applied by each reason. Admins can override these
// per-scan from the Promotions page.
const PROMO_ENGINE_DEFAULTS = [
    'near_expiration' => 30.0,
    'slow_selling'    => 15.0,
    'replaced_model'  => 25.0,
];

const PROMO_ENGINE_LABELS = [
    'near_expiration' => 'Near Expiration',
    'slow_selling'    => 'Slow Selling',
    'replaced_model'  => 'Replaced by Newer Model',
];

const PROMO_ENGINE_REASONS = ['near_expiration', 'slow_selling', 'replaced_model'];

/**
 * Products expiring within $daysAhead days (and not already expired).
 */
function detect_near_expiration(PDO $pdo, int $daysAhead = 14): array {
    $stmt = $pdo->prepare(
        "SELECT id, name, sku, expiry_date, stock_qty
         FROM products
         WHERE is_active = 1
           AND expiry_date IS NOT NULL
           AND expiry_date >= CURDATE()
           AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
           AND stock_qty > 0"
    );
    $stmt->execute(['days' => $daysAhead]);
    return $stmt->fetchAll();
}

/**
 * Products with zero completed sales in the last $days days, that have
 * been around long enough to judge (created more than $minAgeDays ago)
 * and still have stock worth moving.
 *
 * This is intentionally a simple, explainable heuristic (no sales at all
 * in the window) rather than a statistical velocity model - straightforward
 * to reason about from the Promotions page, and easy to tune later.
 */
function detect_slow_selling(PDO $pdo, int $days = 30, int $minAgeDays = 14): array {
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.sku, p.stock_qty, p.created_at,
                COALESCE(SUM(CASE WHEN s.created_at >= :since THEN si.quantity ELSE 0 END), 0) AS units_recent
         FROM products p
         LEFT JOIN sale_items si ON si.product_id = p.id
         LEFT JOIN sales s ON s.id = si.sale_id AND s.status = 'completed'
         WHERE p.is_active = 1
           AND p.stock_qty > 0
           AND p.created_at <= DATE_SUB(NOW(), INTERVAL :minAge DAY)
         GROUP BY p.id, p.name, p.sku, p.stock_qty, p.created_at
         HAVING units_recent = 0"
    );
    $stmt->execute(['since' => date('Y-m-d H:i:s', strtotime("-{$days} days")), 'minAge' => $minAgeDays]);
    return $stmt->fetchAll();
}

/**
 * Products manually flagged as superseded by a newer model/version.
 */
function detect_replaced_model(PDO $pdo): array {
    return $pdo->query(
        "SELECT id, name, sku, stock_qty FROM products
         WHERE is_active = 1 AND is_superseded = 1 AND stock_qty > 0"
    )->fetchAll();
}

function run_detector(PDO $pdo, string $reason): array {
    switch ($reason) {
        case 'near_expiration': return detect_near_expiration($pdo);
        case 'slow_selling':    return detect_slow_selling($pdo);
        case 'replaced_model':  return detect_replaced_model($pdo);
        default: return [];
    }
}

/**
 * Returns candidate counts/products for every reason without writing
 * anything - used for the "preview before you scan" panel.
 */
function preview_auto_promotions(PDO $pdo): array {
    $preview = [];
    foreach (PROMO_ENGINE_REASONS as $reason) {
        $products = run_detector($pdo, $reason);
        $preview[$reason] = [
            'label'    => PROMO_ENGINE_LABELS[$reason],
            'count'    => count($products),
            'products' => $products,
            'default_percent' => PROMO_ENGINE_DEFAULTS[$reason],
        ];
    }
    return $preview;
}

/**
 * Runs all four detectors and syncs one auto-generated, scope='product'
 * promotion per reason so it matches exactly what qualifies right now:
 *   - creates the promotion row the first time a reason has candidates
 *   - adds/removes promotion_products links to match the current candidate set
 *   - deactivates (but keeps, for history) the promotion when a reason has
 *     no more qualifying products
 *
 * $percentOverrides: optional ['reason' => percent] to use instead of
 * PROMO_ENGINE_DEFAULTS for this run.
 *
 * Returns a per-reason summary for the UI to display after running.
 */
function apply_auto_promotions(PDO $pdo, array $percentOverrides = []): array {
    $summary = [];

    foreach (PROMO_ENGINE_REASONS as $reason) {
        $candidates = run_detector($pdo, $reason);
        $candidateIds = array_map(fn($p) => (int) $p['id'], $candidates);
        $percent = isset($percentOverrides[$reason]) && $percentOverrides[$reason] !== ''
            ? (float) $percentOverrides[$reason]
            : PROMO_ENGINE_DEFAULTS[$reason];
        $percent = max(0.0, min(90.0, $percent));

        // Find (or prepare to create) the single auto-generated promotion
        // this engine owns for this reason.
        $stmt = $pdo->prepare(
            "SELECT id FROM promotions WHERE reason = :reason AND auto_generated = 1 LIMIT 1"
        );
        $stmt->execute(['reason' => $reason]);
        $promoId = $stmt->fetchColumn();

        if (count($candidateIds) === 0) {
            // Nothing currently qualifies - switch the promo off (if it
            // exists) and clear its product links. The row itself is kept
            // so its history stays attached to any past sales.
            if ($promoId) {
                $pdo->prepare("UPDATE promotions SET is_active = 0 WHERE id = :id")->execute(['id' => $promoId]);
                $pdo->prepare("DELETE FROM promotion_products WHERE promotion_id = :id")->execute(['id' => $promoId]);
            }
            $summary[$reason] = ['label' => PROMO_ENGINE_LABELS[$reason], 'count' => 0, 'percent' => $percent, 'promotion_id' => $promoId ?: null];
            continue;
        }

        if (!$promoId) {
            $name = PROMO_ENGINE_LABELS[$reason] . ' Clearance';
            $ins = $pdo->prepare(
                "INSERT INTO promotions (name, scope, reason, auto_generated, discount_percent, notes, is_active)
                 VALUES (:name, 'product', :reason, 1, :pct, :notes, 1)"
            );
            $ins->execute([
                'name'  => $name,
                'reason' => $reason,
                'pct'   => $percent,
                'notes' => 'Auto-maintained by the promotion engine scan.',
            ]);
            $promoId = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare(
                "UPDATE promotions SET discount_percent = :pct, is_active = 1 WHERE id = :id"
            )->execute(['pct' => $percent, 'id' => $promoId]);
        }

        // Sync promotion_products to exactly the current candidate set.
        $existing = $pdo->prepare("SELECT product_id FROM promotion_products WHERE promotion_id = :id");
        $existing->execute(['id' => $promoId]);
        $existingIds = array_map('intval', $existing->fetchAll(PDO::FETCH_COLUMN));

        $toAdd = array_diff($candidateIds, $existingIds);
        $toRemove = array_diff($existingIds, $candidateIds);

        if ($toAdd) {
            $ins = $pdo->prepare("INSERT IGNORE INTO promotion_products (promotion_id, product_id) VALUES (:promo, :prod)");
            foreach ($toAdd as $pid) {
                $ins->execute(['promo' => $promoId, 'prod' => $pid]);
            }
        }
        if ($toRemove) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $del = $pdo->prepare("DELETE FROM promotion_products WHERE promotion_id = ? AND product_id IN ($placeholders)");
            $del->execute(array_merge([$promoId], array_values($toRemove)));
        }

        $summary[$reason] = [
            'label' => PROMO_ENGINE_LABELS[$reason],
            'count' => count($candidateIds),
            'percent' => $percent,
            'promotion_id' => $promoId,
        ];
    }

    return $summary;
}

/**
 * The single best active promotion for a specific product right now,
 * considering only scope='product' promotions linked via promotion_products.
 * "Best" = highest discount_percent among currently-live ones.
 * Returns null if none apply.
 */
function best_product_promotion(PDO $pdo, int $productId): ?array {
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.reason, p.discount_percent
         FROM promotions p
         JOIN promotion_products pp ON pp.promotion_id = p.id
         WHERE pp.product_id = :pid
           AND p.scope = 'product'
           AND p.is_active = 1
           AND (p.starts_at IS NULL OR p.starts_at <= NOW())
           AND (p.ends_at IS NULL OR p.ends_at >= NOW())
         ORDER BY p.discount_percent DESC
         LIMIT 1"
    );
    $stmt->execute(['pid' => $productId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Best active product-specific promotion for every active product, keyed
 * by product_id. One query instead of N so pos.php can embed it cheaply.
 */
function all_product_promotions(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT pp.product_id, p.id, p.name, p.reason, p.discount_percent
         FROM promotions p
         JOIN promotion_products pp ON pp.promotion_id = p.id
         WHERE p.scope = 'product'
           AND p.is_active = 1
           AND (p.starts_at IS NULL OR p.starts_at <= NOW())
           AND (p.ends_at IS NULL OR p.ends_at >= NOW())
         ORDER BY p.discount_percent DESC"
    )->fetchAll();

    $byProduct = [];
    foreach ($rows as $row) {
        $pid = (int) $row['product_id'];
        // First row per product is already the highest percent thanks to ORDER BY.
        if (!isset($byProduct[$pid])) {
            $byProduct[$pid] = [
                'promotion_id'     => (int) $row['id'],
                'name'             => $row['name'],
                'reason'           => $row['reason'],
                'discount_percent' => (float) $row['discount_percent'],
            ];
        }
    }
    return $byProduct;
}

/**
 * The current storewide promo, same rule pos.php/pos_checkout.php already
 * used before per-product promos existed.
 */
function active_storewide_promotion(PDO $pdo): ?array {
    $row = $pdo->query(
        "SELECT id, name, discount_percent FROM promotions
         WHERE is_active = 1 AND scope = 'storewide'
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at IS NULL OR ends_at >= NOW())
         ORDER BY discount_percent DESC LIMIT 1"
    )->fetch();
    return $row ?: null;
}