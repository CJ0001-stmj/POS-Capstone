<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/promotion_engine.php';


// Idle session timeout — kill session after 10 min no activity
$SESSION_IDLE_LIMIT = 600; // seconds (10 min)
if (!empty($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $SESSION_IDLE_LIMIT) {
        $_SESSION = [];
        session_destroy();
        header('Location: /index.php?timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();


if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: /index.php');
    exit;
}

$userEmail = $_SESSION['user_email'];
$initials = strtoupper(substr($userEmail, 0, 1));
$pdo = get_db_connection();

$flash = null;

// ---------------------------------------------------------------
// Handle manual form actions (create / toggle / delete). The
// "Run Analytics Scan" button posts to promotions_api.php via fetch
// instead, so it can update the page without a full reload.
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_manual') {
        $name = trim((string)($_POST['name'] ?? ''));
        $scope = ($_POST['scope'] ?? 'storewide') === 'product' ? 'product' : 'storewide';
        $reason = in_array($_POST['reason'] ?? 'manual', ['manual', 'near_expiration', 'slow_selling', 'replaced_model'], true)
            ? $_POST['reason'] : 'manual';
        $discount = max(0, min(90, (float)($_POST['discount_percent'] ?? 0)));
        $startsAt = $_POST['starts_at'] ?: null;
        $endsAt = $_POST['ends_at'] ?: null;
        $productIds = array_map('intval', $_POST['product_ids'] ?? []);

        if ($name === '' || $discount <= 0) {
            $flash = ['type' => 'error', 'text' => 'Give the promotion a name and a discount percentage above 0.'];
        } elseif ($scope === 'product' && count($productIds) === 0) {
            $flash = ['type' => 'error', 'text' => 'Pick at least one product for a product-specific promotion.'];
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO promotions (name, scope, reason, auto_generated, discount_percent, starts_at, ends_at, is_active)
                 VALUES (:name, :scope, :reason, 0, :pct, :starts, :ends, 1)"
            );
            $ins->execute([
                'name' => $name, 'scope' => $scope, 'reason' => $reason, 'pct' => $discount,
                'starts' => $startsAt, 'ends' => $endsAt,
            ]);
            if ($scope === 'product') {
                $promoId = (int)$pdo->lastInsertId();
                $link = $pdo->prepare("INSERT IGNORE INTO promotion_products (promotion_id, product_id) VALUES (:p, :prod)");
                foreach ($productIds as $pid) {
                    $link->execute(['p' => $promoId, 'prod' => $pid]);
                }
            }
            $flash = ['type' => 'ok', 'text' => "Promotion \"{$name}\" created."];
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE promotions SET is_active = 1 - is_active WHERE id = :id")->execute(['id' => $id]);
        $flash = ['type' => 'ok', 'text' => 'Promotion status updated.'];
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Auto-generated promotions are owned by the scan - deactivate them
        // via toggle (or let the next scan retire them) instead of deleting.
        $stmt = $pdo->prepare("SELECT auto_generated FROM promotions WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if ((int)$stmt->fetchColumn() === 1) {
            $flash = ['type' => 'error', 'text' => 'Auto-generated promotions can be turned off, but are managed by the scan, not deleted.'];
        } else {
            $pdo->prepare("DELETE FROM promotions WHERE id = :id")->execute(['id' => $id]);
            $flash = ['type' => 'ok', 'text' => 'Promotion deleted.'];
        }
    }

    // PRG: redirect so a page refresh never resubmits the form.
    $_SESSION['promo_flash'] = $flash;
    header('Location: promotions.php');
    exit;
}

if (isset($_SESSION['promo_flash'])) {
    $flash = $_SESSION['promo_flash'];
    unset($_SESSION['promo_flash']);
}

// ---------------------------------------------------------------
// Data for the page
// ---------------------------------------------------------------
$preview = preview_auto_promotions($pdo);

$promotions = $pdo->query(
    "SELECT p.*, COUNT(pp.product_id) AS product_count
     FROM promotions p
     LEFT JOIN promotion_products pp ON pp.promotion_id = p.id
     GROUP BY p.id
     ORDER BY p.auto_generated DESC, p.is_active DESC, p.created_at DESC"
)->fetchAll();

$productList = $pdo->query(
    "SELECT id, name, sku, category_id FROM products WHERE is_active = 1 ORDER BY name"
)->fetchAll();

function peso2($n): string {
    return '₱' . number_format((float)$n, 2);
}
function pct1($n): string {
    $n = (float)$n;
    return ($n == floor($n) ? number_format($n, 0) : rtrim(rtrim(number_format($n, 2), '0'), '.')) . '%';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions - RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard.css">
    <link rel="stylesheet" href="/prm/promotions.css">
    <link rel="stylesheet" href="/../idle-timeout.css">
</head>
<body>
<div class="app-shell">

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-tags" style="margin-right:8px;"></i>Promotions &amp; Campaign Manager</h1>
                    <p>Let sales data flag what needs to move, then discount it automatically at checkout.</p>
                </div>
            </div>
            <div class="topbar-actions">
                <?php include __DIR__ . '/../notif-bell.php'; ?>
                <div class="user-chip">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="who">
                        <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                        <span>Staff</span>
                    </div>
                    <a href="/../logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content promo-content">

            <?php if ($flash): ?>
                <div class="promo-flash promo-flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['text']) ?></div>
            <?php endif; ?>

            <p class="section-label">Analytics-driven clearance scan</p>
            <div class="panel promo-scan-panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-magnifying-glass-chart"></i> What qualifies right now</h3>
                    <div class="panel-actions">
                        <button type="button" class="pos-btn-primary promo-scan-btn" id="runScanBtn">
                            <i class="fa-solid fa-bolt"></i> Run Analytics Scan
                        </button>
                    </div>
                </div>
                <p class="empty-note" style="padding:0 4px 10px;">
                    Each reason below is checked against live product &amp; sales data. Running the scan creates or
                    updates one auto-managed promotion per reason and links it to exactly the products that qualify
                    today - already-checked-out sales are never affected, and the POS applies these the moment the
                    scan runs.
                </p>
                <div class="promo-reason-grid">
                    <?php foreach ($preview as $reason => $info): ?>
                    <div class="promo-reason-card promo-reason-<?= $reason ?>">
                        <div class="promo-reason-top">
                            <span class="promo-reason-icon">
                                <i class="fa-solid <?= [
                                    'near_expiration' => 'fa-hourglass-half',
                                    'slow_selling' => 'fa-chart-line-down',
                                    'replaced_model' => 'fa-arrows-rotate',
                                ][$reason] ?>"></i>
                            </span>
                            <span class="promo-reason-count"><?= (int)$info['count'] ?></span>
                        </div>
                        <strong><?= htmlspecialchars($info['label']) ?></strong>
                        <span class="promo-reason-sub"><?= (int)$info['count'] ?> product<?= $info['count'] == 1 ? '' : 's' ?> qualify</span>
                        <label class="promo-reason-pct-label">
                            Discount %
                            <input type="number" class="promo-reason-pct" data-reason="<?= $reason ?>" min="0" max="90" step="0.5" value="<?= htmlspecialchars($info['default_percent']) ?>">
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="scanResult" class="promo-scan-result" style="display:none;"></div>
            </div>

            <p class="section-label">All promotions</p>
            <div class="panel">
                <?php if (empty($promotions)): ?>
                    <p class="empty-note">No promotions yet - run the analytics scan above, or create one manually below.</p>
                <?php else: ?>
                <table class="promo-table">
                    <thead>
                        <tr>
                            <th>Name</th><th>Reason</th><th>Scope</th><th>Discount</th>
                            <th>Window</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($promotions as $p): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($p['name']) ?>
                                <?php if ($p['auto_generated']): ?><span class="auto-chip" title="Managed by the analytics scan">AUTO</span><?php endif; ?>
                            </td>
                            <td><span class="reason-badge reason-<?= $p['reason'] ?>"><?= htmlspecialchars(PROMO_ENGINE_LABELS[$p['reason']] ?? 'Manual') ?></span></td>
                            <td><?= $p['scope'] === 'storewide' ? 'Storewide' : ((int)$p['product_count'] . ' product' . ($p['product_count'] == 1 ? '' : 's')) ?></td>
                            <td><?= pct1($p['discount_percent']) ?></td>
                            <td class="promo-window">
                                <?= $p['starts_at'] ? htmlspecialchars(date('M j, Y', strtotime($p['starts_at']))) : 'Any time' ?>
                                &rarr;
                                <?= $p['ends_at'] ? htmlspecialchars(date('M j, Y', strtotime($p['ends_at']))) : 'Open-ended' ?>
                            </td>
                            <td><span class="stock-badge <?= $p['is_active'] ? 'stock-active' : 'stock-critical' ?>"><?= $p['is_active'] ? 'Active' : 'Off' ?></span></td>
                            <td class="promo-row-actions">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="row-icon-btn" title="<?= $p['is_active'] ? 'Turn off' : 'Turn on' ?>">
                                        <i class="fa-solid <?= $p['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                    </button>
                                </form>
                                <?php if (!$p['auto_generated']): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this promotion?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="row-icon-btn row-icon-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <p class="section-label">Create a manual promotion</p>
            <div class="panel">
                <p class="empty-note" style="padding:0 4px 10px;">
                    For anything the analytics scan doesn't cover - a storewide holiday sale, a one-off bundle deal, etc.
                </p>
                <form method="post" class="promo-form" id="manualForm">
                    <input type="hidden" name="action" value="create_manual">
                    <div class="promo-form-grid">
                        <div>
                            <label class="pos-field-label">Promotion name</label>
                            <input type="text" name="name" class="pos-input" placeholder="e.g. Anniversary Sale" required>
                        </div>
                        <div>
                            <label class="pos-field-label">Reason</label>
                            <select name="reason" class="pos-select">
                                <option value="manual">Manual / Other</option>
                                <option value="near_expiration">Near Expiration</option>
                                <option value="slow_selling">Slow Selling</option>
                                <option value="replaced_model">Replaced by Newer Model</option>
                            </select>
                        </div>
                        <div>
                            <label class="pos-field-label">Discount %</label>
                            <input type="number" name="discount_percent" class="pos-input" min="0" max="90" step="0.5" placeholder="10" required>
                        </div>
                        <div>
                            <label class="pos-field-label">Scope</label>
                            <select name="scope" class="pos-select" id="scopeSelect">
                                <option value="storewide">Storewide (all products)</option>
                                <option value="product">Specific products</option>
                            </select>
                        </div>
                        <div>
                            <label class="pos-field-label">Starts (optional)</label>
                            <input type="date" name="starts_at" class="pos-input">
                        </div>
                        <div>
                            <label class="pos-field-label">Ends (optional)</label>
                            <input type="date" name="ends_at" class="pos-input">
                        </div>
                    </div>

                    <div id="productPickerWrap" style="display:none;">
                        <label class="pos-field-label">Products</label>
                        <input type="text" id="productPickerSearch" class="pos-input" placeholder="Filter products…" style="margin-bottom:8px;">
                        <div class="promo-product-picker" id="productPicker">
                            <?php foreach ($productList as $pr): ?>
                            <label class="promo-product-option" data-name="<?= htmlspecialchars(strtolower($pr['name'])) ?>">
                                <input type="checkbox" name="product_ids[]" value="<?= (int)$pr['id'] ?>">
                                <?= htmlspecialchars($pr['name']) ?> <span class="promo-product-sku"><?= htmlspecialchars($pr['sku']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="pos-btn-primary" style="margin-top:14px; max-width:240px;">
                        <i class="fa-solid fa-plus"></i> Create Promotion
                    </button>
                </form>
            </div>

        </main>
    </div>
</div>

<script src="/prm/promotions.js"></script>
<script src="/sidebar.js"></script>
<script src="/notif-bell.js"></script>
<script src="/..idle-timeout.js"></script>
</body>
</html>