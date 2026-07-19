<?php
session_start();
require_once __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$userId    = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];
$userRole  = $_SESSION['user_role'] ?? 'cashier';
$initials  = strtoupper(substr($userEmail, 0, 1));
$canReview = in_array($userRole, ['admin', 'manager'], true);

$db = get_db_connection();

/* ---------------------------------------------------------------
 * Schema this page assumes (create if not already present):
 *
 * CREATE TABLE purchase_requests (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   request_no VARCHAR(30) NOT NULL,
 *   product_id INT NOT NULL,
 *   product_name VARCHAR(150) NOT NULL,
 *   sku VARCHAR(50) NOT NULL,
 *   supplier_name VARCHAR(150) NOT NULL,
 *   quantity_requested INT NOT NULL,
 *   notes TEXT NULL,
 *   status ENUM('pending','forwarded','fulfilled','declined') NOT NULL DEFAULT 'pending',
 *   requested_by_id INT NOT NULL,
 *   requested_by_email VARCHAR(150) NOT NULL,
 *   forwarded_by_email VARCHAR(150) NULL,
 *   forwarded_at DATETIME NULL,
 *   resolution_notes VARCHAR(255) NULL,
 *   resolved_at DATETIME NULL,
 *   supplier_notified_at DATETIME NULL,
 *   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
 * );
 *
 * stock_movements.reason also accepts 'purchase_request' alongside the
 * existing 'restock' / 'reservation' values used elsewhere.
 * ------------------------------------------------------------- */

// Cashiers only ever see their own requests. Admin/manager see everything,
// pending-first so the review queue surfaces what needs attention.
if ($canReview) {
    $stmt = $db->query(
        "SELECT * FROM purchase_requests
         ORDER BY FIELD(status, 'pending', 'approved', 'rejected'), created_at DESC"
    );
} else {
    $stmt = $db->prepare(
        "SELECT * FROM purchase_requests WHERE requested_by_id = :uid ORDER BY created_at DESC"
    );
    $stmt->execute([':uid' => $userId]);
}
$requests = $stmt->fetchAll();

$pendingCount   = 0;
$forwardedCount = 0;
$fulfilledCount = 0;
$declinedCount  = 0;
foreach ($requests as $r) {
    if ($r['status'] === 'pending') $pendingCount++;
    elseif ($r['status'] === 'forwarded') $forwardedCount++;
    elseif ($r['status'] === 'fulfilled') $fulfilledCount++;
    elseif ($r['status'] === 'declined') $declinedCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Requests - RAM-YUM STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="purchase-requests.css">
</head>
<body>
<div class="app-shell">

    <?php
    $__sidebarFile = ($userRole === 'cashier') ? 'sidebar-cashier.php' : 'sidebar.php';
    include __DIR__ . '/' . $__sidebarFile;
    ?>

    <div class="main-area">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greet">
                    <h1><i class="fa-solid fa-dolly" style="margin-right:8px;"></i>Purchase Requests</h1>
                    <p><?= $canReview
                        ? 'Validate supplier restock requests, send them to inventory, then relay the outcome back.'
                        : 'Track requests the supplier sent in, until inventory confirms.' ?></p>
                </div>
            </div>
            <div class="topbar-actions">
                <?php if ($userRole !== 'cashier') include __DIR__ . '/notif-bell.php'; ?>
                <div class="user-chip">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="who">
                        <strong><?= htmlspecialchars(explode('@', $userEmail)[0]) ?></strong>
                        <span><?= htmlspecialchars(ucfirst($userRole)) ?></span>
                    </div>
                    <a href="logout.php" class="logout-link" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>
        </header>

        <main class="content">

            <p class="section-label">Overview</p>
            <div class="kpi-strip">
                <div class="kpi-card stat-card">
                    <div class="kpi-top"><div class="kpi-icon"><i class="fa-solid fa-hourglass-half"></i></div></div>
                    <div class="kpi-value stat-value"><?= $pendingCount ?></div>
                    <div class="kpi-label">Awaiting Validation</div>
                </div>
                <div class="kpi-card stat-card">
                    <div class="kpi-top"><div class="kpi-icon"><i class="fa-solid fa-truck-ramp-box"></i></div></div>
                    <div class="kpi-value stat-value"><?= $forwardedCount ?></div>
                    <div class="kpi-label">Waiting On Inventory</div>
                </div>
                <div class="kpi-card stat-card">
                    <div class="kpi-top"><div class="kpi-icon"><i class="fa-solid fa-circle-check"></i></div></div>
                    <div class="kpi-value stat-value"><?= $fulfilledCount ?></div>
                    <div class="kpi-label">Fulfilled</div>
                </div>
                <div class="kpi-card stat-card">
                    <div class="kpi-top"><div class="kpi-icon"><i class="fa-solid fa-circle-xmark"></i></div></div>
                    <div class="kpi-value stat-value"><?= $declinedCount ?></div>
                    <div class="kpi-label">Declined</div>
                </div>
                <div class="kpi-card stat-card">
                    <div class="kpi-top"><div class="kpi-icon"><i class="fa-solid fa-list"></i></div></div>
                    <div class="kpi-value stat-value"><?= count($requests) ?></div>
                    <div class="kpi-label"><?= $canReview ? 'Total Requests' : 'Your Requests' ?></div>
                </div>
            </div>

            <!-- ============ VALIDATION QUEUE (admin/manager only) ============ -->
            <?php if ($canReview): ?>
            <p class="section-label">Validation Queue</p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-clipboard-check"></i> New supplier requests — check them, then send to inventory</h3>
                </div>
                <div class="table-scroll">
                <table class="pr-table" id="pendingTable">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Qty</th>
                            <th>Requested By</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $anyPending = false; foreach ($requests as $r): if ($r['status'] !== 'pending') continue; $anyPending = true; ?>
                        <tr class="pr-row" data-request-id="<?= (int)$r['id'] ?>" data-product-id="<?= (int)$r['product_id'] ?>" data-qty="<?= (int)$r['quantity_requested'] ?>">
                            <td><strong><?= htmlspecialchars($r['request_no']) ?></strong></td>
                            <td><?= htmlspecialchars($r['product_name']) ?><br><small style="color:var(--ram-muted);"><?= htmlspecialchars($r['sku']) ?></small></td>
                            <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                            <td><?= (int)$r['quantity_requested'] ?></td>
                            <td><?= htmlspecialchars(explode('@', $r['requested_by_email'])[0]) ?></td>
                            <td><?= htmlspecialchars((new DateTime($r['created_at']))->format('M j, g:i A')) ?></td>
                            <td class="pr-status-cell">
                                <span class="pr-badge pr-badge-pending"><i class="fa-solid fa-spinner fa-spin"></i> Awaiting Validation</span>
                            </td>
                            <td class="pr-row-actions">
                                <button type="button" class="pr-forward-btn" title="Validate &amp; send to inventory">
                                    <i class="fa-solid fa-paper-plane"></i> <span class="btn-label">Send to Inventory</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="pr-empty-row" id="pendingEmptyRow" style="<?= $anyPending ? 'display:none;' : '' ?>">
                        <td colspan="8" class="pr-empty-cell">Nothing waiting on validation right now.</td>
                    </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- ============ WAITING ON INVENTORY (read-only) ============ -->
            <p class="section-label">Waiting On Inventory</p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-truck-ramp-box"></i> Sent to inventory — no action here, just watching for their update</h3>
                </div>
                <div class="table-scroll">
                <table class="pr-table" id="forwardedTable">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Qty</th>
                            <th>Sent By</th>
                            <th>Sent At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $anyForwarded = false; foreach ($requests as $r): if ($r['status'] !== 'forwarded') continue; $anyForwarded = true; ?>
                        <tr class="pr-row-forwarded" data-request-id="<?= (int)$r['id'] ?>">
                            <td><strong><?= htmlspecialchars($r['request_no']) ?></strong></td>
                            <td><?= htmlspecialchars($r['product_name']) ?><br><small style="color:var(--ram-muted);"><?= htmlspecialchars($r['sku']) ?></small></td>
                            <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                            <td><?= (int)$r['quantity_requested'] ?></td>
                            <td><?= $r['forwarded_by_email'] ? htmlspecialchars(explode('@', $r['forwarded_by_email'])[0]) : '—' ?></td>
                            <td><?= $r['forwarded_at'] ? htmlspecialchars((new DateTime($r['forwarded_at']))->format('M j, g:i A')) : '—' ?></td>
                            <td class="pr-status-cell">
                                <span class="pr-badge pr-badge-forwarded"><i class="fa-solid fa-truck-ramp-box"></i> Waiting On Inventory</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="pr-empty-row" id="forwardedEmptyRow" style="<?= $anyForwarded ? 'display:none;' : '' ?>">
                        <td colspan="7" class="pr-empty-cell">Nothing out with inventory right now.</td>
                    </tr>
                    </tbody>
                </table>
                </div>
            </div>
            <?php else: ?>

            <!-- ============ VALIDATION QUEUE (cashier, read-only) ============ -->
            <p class="section-label">Validation Queue</p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-clipboard-check"></i> Your requests awaiting validation</h3>
                </div>
                <div class="table-scroll">
                <table class="pr-table" id="pendingTable">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Qty</th>
                            <th>Submitted</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $anyPending = false; foreach ($requests as $r): if ($r['status'] !== 'pending') continue; $anyPending = true; ?>
                        <tr class="pr-row" data-request-id="<?= (int)$r['id'] ?>">
                            <td><strong><?= htmlspecialchars($r['request_no']) ?></strong></td>
                            <td><?= htmlspecialchars($r['product_name']) ?><br><small style="color:var(--ram-muted);"><?= htmlspecialchars($r['sku']) ?></small></td>
                            <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                            <td><?= (int)$r['quantity_requested'] ?></td>
                            <td><?= htmlspecialchars((new DateTime($r['created_at']))->format('M j, g:i A')) ?></td>
                            <td class="pr-status-cell">
                                <span class="pr-badge pr-badge-pending"><i class="fa-solid fa-spinner fa-spin"></i> Awaiting Validation</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="pr-empty-row" id="pendingEmptyRow" style="<?= $anyPending ? 'display:none;' : '' ?>">
                        <td colspan="6" class="pr-empty-cell">Nothing waiting on validation right now.</td>
                    </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- ============ WAITING ON INVENTORY (cashier, read-only) ============ -->
            <p class="section-label">Waiting On Inventory</p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-truck-ramp-box"></i> Sent to inventory, waiting on their update</h3>
                </div>
                <div class="table-scroll">
                <table class="pr-table" id="forwardedTable">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Qty</th>
                            <th>Sent At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $anyForwarded = false; foreach ($requests as $r): if ($r['status'] !== 'forwarded') continue; $anyForwarded = true; ?>
                        <tr class="pr-row-forwarded" data-request-id="<?= (int)$r['id'] ?>">
                            <td><strong><?= htmlspecialchars($r['request_no']) ?></strong></td>
                            <td><?= htmlspecialchars($r['product_name']) ?><br><small style="color:var(--ram-muted);"><?= htmlspecialchars($r['sku']) ?></small></td>
                            <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                            <td><?= (int)$r['quantity_requested'] ?></td>
                            <td><?= $r['forwarded_at'] ? htmlspecialchars((new DateTime($r['forwarded_at']))->format('M j, g:i A')) : '—' ?></td>
                            <td class="pr-status-cell">
                                <span class="pr-badge pr-badge-forwarded"><i class="fa-solid fa-truck-ramp-box"></i> Waiting On Inventory</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="pr-empty-row" id="forwardedEmptyRow" style="<?= $anyForwarded ? 'display:none;' : '' ?>">
                        <td colspan="6" class="pr-empty-cell">Nothing out with inventory right now.</td>
                    </tr>
                    </tbody>
                </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============ HISTORY ============ -->
            <p class="section-label"><?= $canReview ? 'All Requests' : 'Your Request History' ?></p>
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa-solid fa-clock-rotate-left"></i> Full history</h3>
                </div>
                <div class="table-scroll">
                <table class="pr-table">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Qty</th>
                            <?php if ($canReview): ?><th>Requested By</th><?php endif; ?>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Supplier Notified</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="8" class="pr-empty-cell">No requests submitted yet.</td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr data-request-id="<?= (int)$r['id'] ?>" class="pr-hist-row pr-hist-status-<?= htmlspecialchars($r['status']) ?>">
                            <td><strong><?= htmlspecialchars($r['request_no']) ?></strong></td>
                            <td><?= htmlspecialchars($r['product_name']) ?></td>
                            <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                            <td><?= (int)$r['quantity_requested'] ?></td>
                            <?php if ($canReview): ?><td><?= htmlspecialchars(explode('@', $r['requested_by_email'])[0]) ?></td><?php endif; ?>
                            <td><?= htmlspecialchars((new DateTime($r['created_at']))->format('M j, Y g:i A')) ?></td>
                            <td class="pr-status-cell">
                                <?php if ($r['status'] === 'pending'): ?>
                                    <span class="pr-badge pr-badge-pending"><i class="fa-solid fa-spinner fa-spin"></i> Awaiting Validation</span>
                                <?php elseif ($r['status'] === 'forwarded'): ?>
                                    <span class="pr-badge pr-badge-forwarded"><i class="fa-solid fa-truck-ramp-box"></i> Waiting On Inventory</span>
                                <?php elseif ($r['status'] === 'fulfilled'): ?>
                                    <span class="pr-badge pr-badge-approved"><i class="fa-solid fa-check"></i> Fulfilled</span>
                                <?php else: ?>
                                    <span class="pr-badge pr-badge-rejected"><i class="fa-solid fa-xmark"></i> Declined</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['supplier_notified_at'] ? htmlspecialchars((new DateTime($r['supplier_notified_at']))->format('M j, g:i A')) : '—' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
window.RAMYUM_CAN_REVIEW = <?= $canReview ? 'true' : 'false' ?>;
</script>
<script src="purchase-requests.js"></script>
<script src="sidebar.js"></script>
<?php if ($canReview): ?><script src="notif-bell.js"></script><?php endif; ?>
</body>
</html>