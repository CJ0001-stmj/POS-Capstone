<?php
/**
 * Cashier-only sidebar navigator.
 *
 * Separate component from sidebar.php on purpose — sidebar.php owns the
 * full admin/manager nav (Analytics, Promotions, Audit & Access, etc.)
 * and derives the cashier menu by filtering that array down. That means
 * every time an admin-only item gets added to sidebar.php, this file's
 * output is on the hook for staying correct too. Keeping cashier's own
 * fixed module list here instead means dashboard-cashier.php's nav is
 * self-contained and can't drift just because the admin sidebar changed.
 *
 * Include this from dashboard-cashier.php AFTER session_start() and after
 * $userEmail has been set, same convention as sidebar.php:
 *
 *     <?php include __DIR__ . '/sidebar-cashier.php'; ?>
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Fixed set — everything a cashier actually touches day to day. No
// filtering, no shared array with the admin side.
$cashierNavItems = [
    [
        'label' => 'Overview',
        'href'  => 'dashboard.php',
        'icon'  => 'fa-gauge-high',
        'match' => ['dashboard.php'],
    ],
    [
        'label' => 'Point of Sale',
        'href'  => 'pos.php',
        'icon'  => 'fa-cash-register',
        'match' => ['pos.php', 'stock-monitoring.php', 'receipt-history.php'],
        'children' => [
            ['label' => 'New Transaction',   'href' => 'pos.php',                'icon' => 'fa-cash-register'],
            ['label' => 'Stock Monitoring',  'href' => 'stock-monitoring.php',   'icon' => 'fa-boxes-stacked'],
            ['label' => 'Receipt History',   'href' => 'receipt-history.php',    'icon' => 'fa-clock-rotate-left'],
        ],
    ],
    [
        'label' => 'Purchase Requests',
        'href'  => 'purchase-requests.php',
        'icon'  => 'fa-dolly',
        'match' => ['purchase-requests.php'],
    ],
    [
        'label' => 'Orders & Reservations',
        'href'  => 'orders.php',
        'icon'  => 'fa-bowl-food',
        'match' => ['orders.php', 'order-history.php'],
        'children' => [
            ['label' => 'New Transaction',   'href' => 'orders.php',        'icon' => 'fa-bowl-food'],
            ['label' => 'Purchase History',  'href' => 'order-history.php', 'icon' => 'fa-clock-rotate-left'],
        ],
    ],
    [
        'label' => 'Concerns',
        'href'  => 'staff-concern.php',
        'icon'  => 'fa-comments',
        'match' => ['staff-concern.php'],
    ],
    [
        'label' => 'Admin Messages',
        'href'  => 'admin-messages.php',
        'icon'  => 'fa-bullhorn',
        'match' => ['admin-messages.php'],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="assets/logo.png" alt="RAM-YUM Logo">
        <div class="brand-text">
            <strong>RAM-YUM</strong>
            <span>Store Management</span>
        </div>
    </div>
    <ul class="sidebar-nav">
        <?php foreach ($cashierNavItems as $item): ?>
            <?php
            $isActive = in_array($currentPage, $item['match'], true);
            $hasChildren = !empty($item['children']);
            ?>
            <?php if ($hasChildren): ?>
            <li class="has-submenu<?= $isActive ? ' submenu-open active' : '' ?>">
                <a href="<?= htmlspecialchars($item['href']) ?>" class="submenu-toggle">
                    <i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                    <i class="fa-solid fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <?php foreach ($item['children'] as $child): ?>
                    <li class="<?= $currentPage === $child['href'] ? 'active' : '' ?>">
                        <a href="<?= htmlspecialchars($child['href']) ?>">
                            <i class="fa-solid <?= htmlspecialchars($child['icon']) ?>"></i> <?= htmlspecialchars($child['label']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <?php else: ?>
            <li class="<?= $isActive ? 'active' : '' ?>">
                <a href="<?= htmlspecialchars($item['href']) ?>">
                    <i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                </a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar-foot">Logged in as<br><strong style="color:var(--ram-yellow)"><?= htmlspecialchars($userEmail ?? ($_SESSION['user_email'] ?? '')) ?></strong></div>
</aside>