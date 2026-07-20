<?php
/**
 * Cashier / staff active status — shared by dashboard.php (initial
 * render) and cashier-status.php (the 30s poll dashboard.js calls so
 * the panel stays live without a full page reload).
 *
 * "Active" used to mean "logged in at some point today", which stayed
 * green all day even if someone clocked in at 8am and left by 9. This
 * now uses users.last_seen — a heartbeat timestamp refreshed by the
 * same idle-timeout check block every authenticated page already runs
 * (see dashboard.php) — so "active" means "still within the site's own
 * 10-minute idle window right now", the same cutoff that would log
 * them out. Falls back to login_audit's last successful login for any
 * account last_seen hasn't caught up on yet (e.g. right after the
 * last_seen column migration runs, before their next page load).
 */

const CASHIER_ACTIVE_WINDOW_SECONDS = 600; // matches $SESSION_IDLE_LIMIT site-wide

/**
 * Human "3m ago" / "2h ago" / "Jul 20" style relative label.
 */
function relative_time_label(DateTime $when, DateTime $now): string {
    $diff = $now->getTimestamp() - $when->getTimestamp();
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400 && $when->format('Y-m-d') === $now->format('Y-m-d')) {
        return floor($diff / 3600) . 'h ago';
    }
    return $when->format('M j, g:i A');
}

/**
 * Returns the cashier/staff status list, freshest-seen first.
 */
function build_cashier_status(PDO $pdo, int $limit = 8): array {
    $now = new DateTime();

    $stmt = $pdo->prepare(
        "SELECT u.email, u.role, u.last_seen, la.last_login
         FROM users u
         LEFT JOIN (
             SELECT email, MAX(created_at) AS last_login
             FROM login_audit
             WHERE success = 1
             GROUP BY email
         ) la ON la.email = u.email
         WHERE u.last_seen IS NOT NULL OR la.last_login IS NOT NULL
         ORDER BY GREATEST(COALESCE(u.last_seen, '1970-01-01'), COALESCE(la.last_login, '1970-01-01')) DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $cashiers = [];
    foreach ($stmt->fetchAll() as $row) {
        // Prefer the heartbeat (last_seen) — it reflects activity on
        // ANY page, not just the login moment — falling back to the
        // login timestamp for accounts that haven't hit a page with
        // the heartbeat yet.
        $lastSeenRaw = $row['last_seen'] ?: $row['last_login'];
        if (!$lastSeenRaw) continue;

        $lastSeen = new DateTime($lastSeenRaw);
        $secondsAgo = $now->getTimestamp() - $lastSeen->getTimestamp();
        $isActive = $secondsAgo <= CASHIER_ACTIVE_WINDOW_SECONDS;

        $cashiers[] = [
            'name'   => explode('@', $row['email'])[0],
            'role'   => ucfirst($row['role']),
            'status' => $isActive ? 'active' : 'offline',
            'note'   => ($isActive ? 'Active' : 'Last seen') . ' — ' . relative_time_label($lastSeen, $now),
        ];
    }
    return $cashiers;
}
