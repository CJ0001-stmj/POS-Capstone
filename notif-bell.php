<?php
/**
 * Shared notification bell — include from any page's <header class="topbar">,
 * inside .topbar-actions, BEFORE the .user-chip div, e.g.:
 *
 *     <div class="topbar-actions">
 *         <?php include __DIR__ . '/notif-bell.php'; ?>
 *         <div class="user-chip">...</div>
 *     </div>
 *
 * Needs session_start() + db.php already required by the including page
 * (same convention as sidebar.php). Pulls its own PDO connection so it
 * doesn't care whether the page's local var is $pdo, $db, etc.
 * Requires notif-bell.js (loaded after sidebar.js) to wire up the click/tab
 * behavior, and dashboard.css (already loaded on every page) for styling.
 */

$__notifPdo = get_db_connection();

$stmt = $__notifPdo->prepare(
    "SELECT subject, submitted_by_email, status, created_at
     FROM staff_concerns
     WHERE status IN ('open', 'in_review')
     ORDER BY FIELD(status, 'open', 'in_review'), created_at DESC
     LIMIT 5"
);
$stmt->execute();
$__notifConcerns = $stmt->fetchAll();
$__openConcernCount = (int) $__notifPdo->query("SELECT COUNT(*) c FROM staff_concerns WHERE status = 'open'")->fetch()['c'];

$__notifTimeInOut = [];
$stmt = $__notifPdo->query(
    "SELECT u.email, u.role, la.last_login
     FROM users u
     JOIN (
         SELECT email, MAX(created_at) AS last_login
         FROM login_audit
         WHERE success = 1
         GROUP BY email
     ) la ON la.email = u.email
     ORDER BY la.last_login DESC
     LIMIT 5"
);
$__today = (new DateTime())->format('Y-m-d');
foreach ($stmt->fetchAll() as $row) {
    $lastLogin = new DateTime($row['last_login']);
    $__notifTimeInOut[] = [
        'name'   => explode('@', $row['email'])[0],
        'role'   => ucfirst($row['role']),
        'status' => $lastLogin->format('Y-m-d') === $__today ? 'active' : 'offline',
        'note'   => 'Last login ' . $lastLogin->format('M j, g:i A'),
    ];
}

$stmt = $__notifPdo->query(
    "SELECT si.product_name AS name, SUM(si.quantity) AS units, SUM(si.line_total) AS revenue
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     WHERE s.status = 'completed'
     GROUP BY si.product_name
     ORDER BY revenue DESC
     LIMIT 5"
);
$__notifTopProducts = [];
$__rank = 1;
foreach ($stmt->fetchAll() as $row) {
    $__notifTopProducts[] = [
        'rank'    => $__rank++,
        'name'    => $row['name'],
        'units'   => (int) $row['units'],
        'revenue' => '₱' . number_format((float) $row['revenue'], 0),
    ];
}

$__notifBadgeCount = $__openConcernCount;
?>
<div class="notif-wrap">
    <button class="icon-btn" id="notifBell" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
        <i class="fa-solid fa-bell"></i>
        <?php if ($__notifBadgeCount > 0): ?>
            <span class="notif-badge" id="notifBadge"><?= $__notifBadgeCount > 9 ? '9+' : (int) $__notifBadgeCount ?></span>
        <?php endif; ?>
    </button>
    <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-head">
            <strong>Notifications</strong>
        </div>
        <div class="notif-tabs" id="notifTabs">
            <button class="notif-tab active" data-tab="concerns">
                <i class="fa-solid fa-inbox"></i> Concerns
                <?php if ($__openConcernCount > 0): ?><span class="notif-tab-count"><?= (int) $__openConcernCount ?></span><?php endif; ?>
            </button>
            <button class="notif-tab" data-tab="timeinout"><i class="fa-solid fa-user-clock"></i> Time In/Out</button>
            <button class="notif-tab" data-tab="topproducts"><i class="fa-solid fa-ranking-star"></i> Top Products</button>
        </div>

        <div class="notif-list notif-pane" data-pane="concerns">
            <?php if (empty($__notifConcerns)): ?>
                <p class="notif-empty">No open concerns.</p>
            <?php else: foreach ($__notifConcerns as $nc): ?>
                <div class="notif-item <?= $nc['status'] === 'open' ? 'notif-item-unread' : '' ?>">
                    <p><?= htmlspecialchars($nc['subject']) ?></p>
                    <span>From <?= htmlspecialchars(explode('@', $nc['submitted_by_email'])[0]) ?> &middot; <?= htmlspecialchars((new DateTime($nc['created_at']))->format('M j, g:i A')) ?> &middot; <?= $nc['status'] === 'open' ? 'Open' : 'In review' ?></span>
                </div>
            <?php endforeach; endif; ?>
            <a href="staff-inbox.php" class="notif-view-all">View all in Inbox <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="notif-list notif-pane" data-pane="timeinout" style="display:none;">
            <?php if (empty($__notifTimeInOut)): ?>
                <p class="notif-empty">No staff logins recorded yet.</p>
            <?php else: foreach ($__notifTimeInOut as $ti): ?>
                <div class="notif-item">
                    <p><?= htmlspecialchars(ucfirst($ti['name'])) ?>
                        <span class="status-dot status-<?= $ti['status'] ?>" style="display:inline-block;width:8px;height:8px;border-radius:50%;margin-left:6px;"></span>
                    </p>
                    <span><?= htmlspecialchars($ti['role']) ?> &middot; <?= $ti['status'] === 'active' ? 'Timed in' : 'Timed out' ?> &middot; <?= htmlspecialchars($ti['note']) ?></span>
                </div>
            <?php endforeach; endif; ?>
            <a href="user-access-control.php" class="notif-view-all">View all in User Access <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="notif-list notif-pane" data-pane="topproducts" style="display:none;">
            <?php if (empty($__notifTopProducts)): ?>
                <p class="notif-empty">No completed sales on record yet.</p>
            <?php else: foreach ($__notifTopProducts as $tp): ?>
                <div class="notif-item">
                    <p>#<?= (int) $tp['rank'] ?> <?= htmlspecialchars($tp['name']) ?></p>
                    <span><?= (int) $tp['units'] ?> units sold &middot; <?= htmlspecialchars($tp['revenue']) ?> revenue</span>
                </div>
            <?php endforeach; endif; ?>
            <a href="analytics.php" class="notif-view-all">View all in Analytics <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
</div>
