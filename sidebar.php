<?php
/**
 * Shared sidebar navigator.
 *
 * Include this from any page AFTER session_start() and after $userEmail
 * has been set, e.g.:
 *
 *     require_once __DIR__ . '/db.php';
 *     $userEmail = $_SESSION['user_email'];
 *     ...
 *     <?php include __DIR__ . '/sidebar.php'; ?>
 *
 * One nav definition lives here so every page (dashboard, POS, promotions,
 * analytics, etc.) renders the exact same markup — no more copy-pasting
 * the <aside> block (and its submenu logic) into each page separately.
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Each entry is either a plain link, or a link with a "children" submenu.
// "match" lists which basenames count as "this item is the active one" —
// for parent items with children, that includes the children's own pages.
$navItems = [
    [
        'label' => 'Overview',
        'href'  => '/dashboard.php',
        'icon'  => 'fa-gauge-high',
        'match' => ['dashboard.php'],
    ],
    [
        'label' => 'Point of Sale',
        'href'  => '/pos/pos.php',
        'icon'  => 'fa-cash-register',
        'match' => ['pos.php', 'stock-monitoring.php', 'receipt-history.php'],
        'children' => [
            ['label' => 'New Transaction',   'href' => '/pos/pos.php',                'icon' => 'fa-cash-register'],
            ['label' => 'Stock Monitoring',  'href' => '/pos/stock-monitoring.php',   'icon' => 'fa-boxes-stacked'],
            ['label' => 'Receipt History',   'href' => '/pos/receipt-history.php',    'icon' => 'fa-clock-rotate-left'],
        ],
    ],
    [
        'label' => 'Purchase Requests',
        'href'  => '/pr/purchase-requests.php',
        'icon'  => 'fa-dolly',
        'match' => ['purchase-requests.php'],
    ],
    [
        'label' => 'Orders & Reservations',
        'href'  => '/orm/orders.php',
        'icon'  => 'fa-bowl-food',
        'match' => ['orders.php', 'order-history.php'],
        'children' => [
            ['label' => 'New Transaction',   'href' => '/orm/orders.php',        'icon' => 'fa-bowl-food'],
            ['label' => 'Purchase History',  'href' => '/orm/order-history.php', 'icon' => 'fa-clock-rotate-left'],
        ],
    ],
    [
        'label' => 'Sales Analytics',
        'href'  => '/spas/analytics.php',
        'icon'  => 'fa-chart-line',
        'match' => ['analytics.php'],
    ],
    [
        'label' => 'Promotions',
        'href'  => '/prm/promotions.php',
        'icon'  => 'fa-tags',
        'match' => ['promotions.php'],
    ],
    [
        'label' => 'Audit & Access',
        'href'  => '/aac/audit-overview.php',
        'icon'  => 'fa-user-shield',
        'match' => ['audit-overview.php', 'user-access-control.php', 'staff-inbox.php'],
        'children' => [
            ['label' => 'Overview',             'href' => '/aac/audit-overview.php',      'icon' => 'fa-chart-pie'],
            ['label' => 'User Access Control',  'href' => '/aac/user-access-control.php', 'icon' => 'fa-user-lock'],
            ['label' => 'Inbox',                'href' => '/si/staff-inbox.php',          'icon' => 'fa-inbox'],
        ],
    ],
];

// Cashiers only get the modules they actually work in day to day —
// Overview (this dashboard), Point of Sale, Purchase Requests, and
// Orders & Reservations. Analytics/Promotions/Audit stay admin+manager only.
$userRole = $_SESSION['user_role'] ?? 'cashier';
if ($userRole === 'cashier') {
    $cashierHrefs = ['/dashboard.php', '/pos/pos.php', '/pr/purchase-requests.php', '/orm/orders.php'];
    $navItems = array_values(array_filter($navItems, function ($item) use ($cashierHrefs) {
        return in_array($item['href'], $cashierHrefs, true);
    }));
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="/assets/logo.png" alt="RAM-YUM Logo">
        <div class="brand-text">
            <strong>RAM-YUM</strong>
            <span>Store Management</span>
        </div>
    </div>
    <ul class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
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
                    <li class="<?= $currentPage === basename($child['href']) ? 'active' : '' ?>">
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