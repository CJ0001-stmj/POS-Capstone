<?php
/**
 * Audit & Access > User Access Control
 *
 * Admin/manager-only view of every staff account: role, last login,
 * recent failed attempts, lock status, sales tied to that user, and
 * performance status. Admins can create, edit, and delete accounts,
 * and drill into a user's login activity (time in / time out) via
 * a modal.
 */
require_once __DIR__ . '/db.php';
session_start();

$userEmail = $_SESSION['user_email'] ?? null;
$userRole  = $_SESSION['user_role'] ?? 'cashier';
$initials  = $userEmail ? strtoupper(substr($userEmail, 0, 1)) : '?';

if (!$userEmail) {
    header('Location: login.php');
    exit;
}
if (!in_array($userRole, ['admin', 'manager'], true)) {
    http_response_code(403);
    die('You do not have permission to view this page.');
}

// --------------------------------------------------------------
// AJAX: activity log for one user (time in / time out pairing)
// --------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'activity' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT email, full_name, shift_start, shift_end FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if (!$u) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Required shift length in minutes (same-day shift assumed — an
    // overnight shift like 22:00-06:00 would need this tweaked, since
    // shift_end - shift_start would come out negative).
    $requiredMinutes = null;
    if ($u['shift_start'] && $u['shift_end']) {
        $diff = (strtotime($u['shift_end']) - strtotime($u['shift_start'])) / 60;
        $requiredMinutes = $diff > 0 ? (int)$diff : null;
    }

    $stmt = $conn->prepare("SELECT success, reason, ip_address, created_at FROM login_audit WHERE email = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param('s', $u['email']);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // No session table exists yet, so "time out" is approximated as the
    // timestamp of whatever login_audit row comes right after a success
    // row (chronologically). Most recent success row has no time_out yet.
    $sessions = [];
    for ($i = 0; $i < count($logs); $i++) {
        if ((int)$logs[$i]['success'] === 1) {
            $timeIn  = $logs[$i]['created_at'];
            $timeOut = $i > 0 ? $logs[$i - 1]['created_at'] : null;

            $durationMinutes = null;
            $shortfall = false;
            if ($timeOut) {
                $durationMinutes = (int)round((strtotime($timeOut) - strtotime($timeIn)) / 60);
                if ($requiredMinutes !== null && $durationMinutes < $requiredMinutes) {
                    $shortfall = true;
                }
            }

            $sessions[] = [
                'time_in'  => $timeIn,
                'time_out' => $timeOut,
                'ip'       => $logs[$i]['ip_address'],
                'duration_minutes' => $durationMinutes,
                'shortfall' => $shortfall,
            ];
        }
    }

    $stmt = $conn->prepare("
        SELECT sw.message, sw.created_at, admin.email AS sent_by_email
        FROM staff_warnings sw
        LEFT JOIN users admin ON admin.id = sw.sent_by
        WHERE sw.user_id = ?
        ORDER BY sw.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $warnings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'email' => $u['email'],
        'full_name' => $u['full_name'],
        'shift_start' => $u['shift_start'],
        'shift_end' => $u['shift_end'],
        'required_minutes' => $requiredMinutes,
        'sessions' => $sessions,
        'warnings' => $warnings,
    ]);
    exit;
}

// --------------------------------------------------------------
// AJAX: send a warning to a staff member (admin only)
// --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'send_warning') {
    header('Content-Type: application/json');

    if ($userRole !== 'admin') {
        echo json_encode(['ok' => false, 'error' => 'Only admins can send warnings.']);
        exit;
    }

    $targetId = (int)($_POST['user_id'] ?? 0);
    $message  = trim($_POST['message'] ?? '');
    $sentBy   = $_SESSION['user_id'] ?? null;

    if ($targetId <= 0 || $message === '') {
        echo json_encode(['ok' => false, 'error' => 'A message is required.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO staff_warnings (user_id, message, sent_by) VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $targetId, $message, $sentBy);

    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'created_at' => date('Y-m-d H:i:s')]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Could not save warning.']);
    }
    exit;
}

// --------------------------------------------------------------
// Form actions: create / update / delete (admin only)
// --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($userRole !== 'admin') {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Only admins can manage accounts.'];
        header('Location: user-access-control.php');
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'create_user') {
        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $role       = $_POST['role'] ?? 'cashier';
        $fullName   = trim($_POST['full_name'] ?? '');
        $birthDate  = trim($_POST['birth_date'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $shiftStart = trim($_POST['shift_start'] ?? '');
        $shiftEnd   = trim($_POST['shift_end'] ?? '');

        $ageError = null;
        if ($birthDate !== '') {
            $age = calculate_age($birthDate);
            if ($age === null) {
                $ageError = 'Invalid birthdate.';
            } elseif ($age < 15 || $age > 100) {
                $ageError = 'Birthdate gives an age outside 15-100 — double check it.';
            }
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || !in_array($role, ['admin', 'manager', 'cashier'], true)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid email, password (min 6 chars), or role.'];
        } elseif ($fullName === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Full name is required.'];
        } elseif ($ageError) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => $ageError];
        } elseif ($shiftStart !== '' && $shiftEnd !== '' && $shiftStart === $shiftEnd) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Shift start and end can\'t be the same time.'];
        } else {
            $hash        = password_hash($password, PASSWORD_BCRYPT);
            $birthDateV  = $birthDate !== '' ? $birthDate : null;
            $phoneV      = $phone !== '' ? $phone : null;
            $addressV    = $address !== '' ? $address : null;
            $shiftStartV = $shiftStart !== '' ? $shiftStart : null;
            $shiftEndV   = $shiftEnd !== '' ? $shiftEnd : null;

            $stmt = $conn->prepare("
                INSERT INTO users (email, password_hash, role, full_name, birth_date, phone, address, shift_start, shift_end)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'sssssssss',
                $email, $hash, $role, $fullName, $birthDateV, $phoneV, $addressV, $shiftStartV, $shiftEndV
            );
            $_SESSION['flash'] = $stmt->execute()
                ? ['type' => 'success', 'msg' => "Account $email created."]
                : ['type' => 'error', 'msg' => 'Email already exists or insert failed.'];
        }
    }

    if ($action === 'update_user') {
        $id         = (int)($_POST['user_id'] ?? 0);
        $email      = trim($_POST['email'] ?? '');
        $role       = $_POST['role'] ?? 'cashier';
        $password   = $_POST['password'] ?? '';
        $unlock     = isset($_POST['unlock']);
        $fullName   = trim($_POST['full_name'] ?? '');
        $birthDate  = trim($_POST['birth_date'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $shiftStart = trim($_POST['shift_start'] ?? '');
        $shiftEnd   = trim($_POST['shift_end'] ?? '');

        $ageError = null;
        if ($birthDate !== '') {
            $age = calculate_age($birthDate);
            if ($age === null) {
                $ageError = 'Invalid birthdate.';
            } elseif ($age < 15 || $age > 100) {
                $ageError = 'Birthdate gives an age outside 15-100 — double check it.';
            }
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['admin', 'manager', 'cashier'], true)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid email or role.'];
        } elseif ($fullName === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Full name is required.'];
        } elseif ($ageError) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => $ageError];
        } elseif ($password !== '' && strlen($password) < 6) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Password must be at least 6 characters.'];
        } else {
            $birthDateV  = $birthDate !== '' ? $birthDate : null;
            $phoneV      = $phone !== '' ? $phone : null;
            $addressV    = $address !== '' ? $address : null;
            $shiftStartV = $shiftStart !== '' ? $shiftStart : null;
            $shiftEndV   = $shiftEnd !== '' ? $shiftEnd : null;

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $sql  = "UPDATE users SET email = ?, role = ?, password_hash = ?, full_name = ?, birth_date = ?, phone = ?, address = ?, shift_start = ?, shift_end = ?"
                      . ($unlock ? ", failed_attempts = 0, locked_until = NULL" : "") . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    'sssssssssi',
                    $email, $role, $hash, $fullName, $birthDateV, $phoneV, $addressV, $shiftStartV, $shiftEndV, $id
                );
            } else {
                $sql  = "UPDATE users SET email = ?, role = ?, full_name = ?, birth_date = ?, phone = ?, address = ?, shift_start = ?, shift_end = ?"
                      . ($unlock ? ", failed_attempts = 0, locked_until = NULL" : "") . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    'ssssssssi',
                    $email, $role, $fullName, $birthDateV, $phoneV, $addressV, $shiftStartV, $shiftEndV, $id
                );
            }
            $_SESSION['flash'] = $stmt->execute()
                ? ['type' => 'success', 'msg' => 'Account updated.']
                : ['type' => 'error', 'msg' => 'Update failed (email may already be in use).'];
        }
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['user_id'] ?? 0);

        $stmt = $conn->prepare("SELECT email, role FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();

        if (!$target) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Account not found.'];
        } elseif ($target['email'] === $userEmail) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'You cannot delete your own account.'];
        } else {
            $blockDelete = false;
            if ($target['role'] === 'admin') {
                $count = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'];
                if ($count <= 1) {
                    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Cannot delete the last remaining admin.'];
                    $blockDelete = true;
                }
            }
            if (!$blockDelete) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Account {$target['email']} deleted."];
            }
        }
    }

    header('Location: user-access-control.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// --------------------------------------------------------------
// Main listing query
// --------------------------------------------------------------
$sql = "
    SELECT
        u.id,
        u.email,
        u.role,
        u.full_name,
        u.birth_date,
        u.phone,
        u.address,
        u.shift_start,
        u.shift_end,
        u.failed_attempts,
        u.locked_until,
        u.created_at,
        (SELECT MAX(created_at) FROM login_audit la WHERE la.email = u.email AND la.success = 1) AS last_login,
        (SELECT COUNT(*) FROM login_audit la2 WHERE la2.email = u.email AND la2.success = 0
            AND la2.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS failed_last_24h,
        COALESCE(s.sales_count, 0) AS sales_count,
        COALESCE(s.total_sales, 0) AS total_sales,
        s.last_sale
    FROM users u
    LEFT JOIN (
        SELECT
            COALESCE(cashier_id, 0) AS cid,
            cashier_email,
            COUNT(*) AS sales_count,
            SUM(total) AS total_sales,
            MAX(created_at) AS last_sale
        FROM sales
        WHERE status = 'completed'
        GROUP BY COALESCE(cashier_id, 0), cashier_email
    ) s ON (s.cid = u.id OR (s.cid = 0 AND s.cashier_email = u.email))
    GROUP BY u.id
    ORDER BY FIELD(u.role, 'admin', 'manager', 'cashier'), u.email
";
$result = $conn->query($sql);
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

/**
 * Performance tiering off total sales value. Thresholds are placeholders —
 * swap in whatever numbers fit your store's actual sales volume.
 */
function performanceTier(float $totalSales): array {
    if ($totalSales <= 0)        return ['label' => 'No Sales', 'class' => 'perf-none'];
    if ($totalSales < 10000)     return ['label' => 'Low',      'class' => 'perf-low'];
    if ($totalSales < 50000)     return ['label' => 'Good',     'class' => 'perf-good'];
    return ['label' => 'Top', 'class' => 'perf-top'];
}

/**
 * Age from a birthdate, calculated fresh every time so it's never stale.
 * Returns null on empty/invalid input.
 */
function calculate_age(?string $birthDate): ?int {
    if (!$birthDate) return null;
    try {
        $bd = new DateTime($birthDate);
        $now = new DateTime();
        if ($bd > $now) return null;
        return $bd->diff($now)->y;
    } catch (Exception $e) {
        return null;
    }
}

/** "8:00 AM – 4:00 PM" from two TIME strings, or "—" if either is missing. */
function shift_label(?string $start, ?string $end): string {
    if (!$start || !$end) return '—';
    try {
        return (new DateTime($start))->format('g:i A') . ' – ' . (new DateTime($end))->format('g:i A');
    } catch (Exception $e) {
        return '—';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Access Control — RAM-YUM</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="audit-access.css">
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <header class="topbar">
        <div style="display:flex; align-items:center; gap:14px;">
            <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-greet">
                <h1>User Access Control</h1>
                <p>Every staff account, their access status, activity, and performance.</p>
            </div>
        </div>
        <div class="topbar-actions">
            <?php include __DIR__ . '/notif-bell.php'; ?>
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

    <div class="page-header" style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px;">
        <div>
            <h1><i class="fa-solid fa-user-lock"></i> User Access Control</h1>
            <p class="subtitle">Every staff account, their access status, activity, and performance.</p>
        </div>
        <?php if ($userRole === 'admin'): ?>
        <button class="btn-primary" onclick="openModal('addUserModal')"><i class="fa-solid fa-user-plus"></i> Add Account</button>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="flash-msg flash-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="filters-bar">
        <input type="text" id="staffSearch" placeholder="Search by email...">
        <select id="roleFilter">
            <option value="">All roles</option>
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="cashier">Cashier</option>
        </select>
    </div>

    <div class="table-responsive">
    <table class="data-table" id="staffTable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Age</th>
                <th>Shift</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Failed (24h)</th>
                <th># Sales</th>
                <th>Total Sold</th>
                <th>Performance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $isLocked = $r['locked_until'] && strtotime($r['locked_until']) > time();
            $isActiveNow = $r['last_login'] && (time() - strtotime($r['last_login']) <= 1800);
            $statusLabel = $isLocked ? 'Locked' : ($isActiveNow ? 'Active now' : 'Offline');
            $statusClass = $isLocked ? 'status-locked' : ($isActiveNow ? 'status-active' : 'status-offline');
            $perf = performanceTier((float)$r['total_sales']);
            ?>
            <?php
            $age = calculate_age($r['birth_date']);
            ?>
            <tr data-role="<?= htmlspecialchars($r['role']) ?>" data-email="<?= htmlspecialchars(strtolower($r['email'])) ?>">
                <td><?= $r['full_name'] ? htmlspecialchars($r['full_name']) : '<span class="empty-note">—</span>' ?></td>
                <td><?= htmlspecialchars($r['email']) ?></td>
                <td><?= $age !== null ? $age : '—' ?></td>
                <td><?= htmlspecialchars(shift_label($r['shift_start'], $r['shift_end'])) ?></td>
                <td><span class="role-pill role-<?= htmlspecialchars($r['role']) ?>"><?= htmlspecialchars(ucfirst($r['role'])) ?></span></td>
                <td><span class="status-pill <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                <td><?= $r['last_login'] ? htmlspecialchars($r['last_login']) : '—' ?></td>
                <td><?= (int)$r['failed_last_24h'] ?></td>
                <td><?= (int)$r['sales_count'] ?></td>
                <td>₱<?= number_format((float)$r['total_sales'], 2) ?></td>
                <td><span class="perf-pill <?= $perf['class'] ?>"><?= $perf['label'] ?></span></td>
                <td class="action-cell">
                    <button class="icon-btn" title="View Activity"
                        onclick="openActivityModal(<?= (int)$r['id'] ?>, '<?= htmlspecialchars($r['email'], ENT_QUOTES) ?>')">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </button>
                    <?php if ($userRole === 'admin'): ?>
                    <button class="icon-btn" title="Edit"
                        onclick='openEditModal(<?= json_encode([
                            "id" => (int)$r["id"],
                            "email" => $r["email"],
                            "role" => $r["role"],
                            "locked" => $isLocked,
                            "full_name" => $r["full_name"],
                            "birth_date" => $r["birth_date"],
                            "phone" => $r["phone"],
                            "address" => $r["address"],
                            "shift_start" => $r["shift_start"],
                            "shift_end" => $r["shift_end"],
                        ]) ?>)'>
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete account <?= htmlspecialchars($r['email'], ENT_QUOTES) ?>? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <tr><td colspan="12" class="empty-row">No staff accounts found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</main>

<!-- Add Account Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Add Account</h2>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <label>Full Name
                <input type="text" name="full_name" required>
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Password
                <input type="password" name="password" minlength="6" required>
            </label>
            <label>Birthdate <span class="hint" id="addAgeHint"></span>
                <input type="date" name="birth_date" id="addBirthDate">
            </label>
            <label>Phone Number
                <input type="tel" name="phone">
            </label>
            <label>Address
                <input type="text" name="address">
            </label>
            <div class="field-row">
                <label>Shift Start
                    <input type="time" name="shift_start">
                </label>
                <label>Shift End
                    <input type="time" name="shift_end">
                </label>
            </div>
            <label>Role
                <select name="role">
                    <option value="cashier">Cashier</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Account Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Edit Account</h2>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="editUserId">
            <label>Full Name
                <input type="text" name="full_name" id="editUserFullName" required>
            </label>
            <label>Email
                <input type="email" name="email" id="editUserEmail" required>
            </label>
            <label>New Password <span class="hint">(leave blank to keep current)</span>
                <input type="password" name="password" id="editUserPassword" minlength="6">
            </label>
            <label>Birthdate <span class="hint" id="editAgeHint"></span>
                <input type="date" name="birth_date" id="editUserBirthDate">
            </label>
            <label>Phone Number
                <input type="tel" name="phone" id="editUserPhone">
            </label>
            <label>Address
                <input type="text" name="address" id="editUserAddress">
            </label>
            <div class="field-row">
                <label>Shift Start
                    <input type="time" name="shift_start" id="editUserShiftStart">
                </label>
                <label>Shift End
                    <input type="time" name="shift_end" id="editUserShiftEnd">
                </label>
            </div>
            <label>Role
                <select name="role" id="editUserRole">
                    <option value="cashier">Cashier</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <label class="checkbox-label" id="editUnlockWrap" style="display:none;">
                <input type="checkbox" name="unlock" id="editUserUnlock"> Unlock account / reset failed attempts
            </label>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Activity Modal -->
<div class="modal-overlay" id="activityModal">
    <div class="modal modal-wide">
        <div class="modal-header">
            <h2>Activity — <span id="activityEmail"></span></h2>
            <button class="modal-close" onclick="closeModal('activityModal')">&times;</button>
        </div>
        <div id="activityShiftInfo" class="activity-shift-info"></div>
        <div id="activityBody" class="activity-body">
            <p class="empty-row">Loading...</p>
        </div>
        <?php if ($userRole === 'admin'): ?>
        <div class="warning-section">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Send Warning</h3>
            <div id="warningHistory" class="warning-list"></div>
            <textarea id="warningMessage" class="warning-textarea" placeholder="e.g. Shift on July 15 fell short by 2 hours — please clock in on time."></textarea>
            <div class="modal-actions">
                <span id="warningStatus" class="warning-status"></span>
                <button type="button" class="btn-warning" id="sendWarningBtn" onclick="sendWarning()">
                    <i class="fa-solid fa-paper-plane"></i> Send Warning
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="sidebar.js"></script>
<script src="notif-bell.js"></script>
<script>
const searchBox  = document.getElementById('staffSearch');
const roleFilter = document.getElementById('roleFilter');
const rows = document.querySelectorAll('#staffTable tbody tr');

function applyFilters() {
    const q = searchBox.value.trim().toLowerCase();
    const role = roleFilter.value;
    rows.forEach(row => {
        const matchesSearch = !q || row.dataset.email?.includes(q);
        const matchesRole = !role || row.dataset.role === role;
        row.style.display = (matchesSearch && matchesRole) ? '' : 'none';
    });
}
searchBox?.addEventListener('input', applyFilters);
roleFilter?.addEventListener('change', applyFilters);

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Age preview under the birthdate field — purely informational, the
// real age is calculated server-side from birth_date at render time.
function agePreview(dateStr) {
    if (!dateStr) return '';
    const bd = new Date(dateStr);
    if (isNaN(bd)) return '';
    const now = new Date();
    let age = now.getFullYear() - bd.getFullYear();
    const beforeBirthday = (now.getMonth() < bd.getMonth()) ||
        (now.getMonth() === bd.getMonth() && now.getDate() < bd.getDate());
    if (beforeBirthday) age--;
    return age >= 0 ? `(age ${age})` : '';
}
document.getElementById('addBirthDate')?.addEventListener('input', (e) => {
    document.getElementById('addAgeHint').textContent = agePreview(e.target.value);
});
document.getElementById('editUserBirthDate')?.addEventListener('input', (e) => {
    document.getElementById('editAgeHint').textContent = agePreview(e.target.value);
});

function openEditModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserFullName').value = user.full_name || '';
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserRole').value = user.role;
    document.getElementById('editUserPassword').value = '';
    document.getElementById('editUserBirthDate').value = user.birth_date || '';
    document.getElementById('editAgeHint').textContent = agePreview(user.birth_date || '');
    document.getElementById('editUserPhone').value = user.phone || '';
    document.getElementById('editUserAddress').value = user.address || '';
    document.getElementById('editUserShiftStart').value = user.shift_start ? user.shift_start.slice(0, 5) : '';
    document.getElementById('editUserShiftEnd').value = user.shift_end ? user.shift_end.slice(0, 5) : '';
    document.getElementById('editUserUnlock').checked = false;
    document.getElementById('editUnlockWrap').style.display = user.locked ? 'block' : 'none';
    openModal('editUserModal');
}

let currentActivityUserId = null;

function formatDuration(minutes) {
    if (minutes === null || minutes === undefined) return '—';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h ${m}m`;
}

function renderWarningHistory(warnings) {
    const el = document.getElementById('warningHistory');
    if (!el) return;
    if (!warnings || warnings.length === 0) {
        el.innerHTML = '<p class="empty-note">No warnings sent yet.</p>';
        return;
    }
    el.innerHTML = warnings.map(w => `
        <div class="warning-item">
            <p>${w.message}</p>
            <span>${w.sent_by_email ?? 'Unknown admin'} — ${w.created_at}</span>
        </div>
    `).join('');
}

function openActivityModal(id, email) {
    currentActivityUserId = id;
    document.getElementById('activityEmail').textContent = email;
    document.getElementById('activityBody').innerHTML = '<p class="empty-row">Loading...</p>';
    document.getElementById('activityShiftInfo').innerHTML = '';
    const warningStatus = document.getElementById('warningStatus');
    if (warningStatus) warningStatus.textContent = '';
    const warningMessage = document.getElementById('warningMessage');
    if (warningMessage) warningMessage.value = '';
    openModal('activityModal');

    fetch(`user-access-control.php?ajax=activity&id=${id}`)
        .then(res => res.json())
        .then(data => {
            const body = document.getElementById('activityBody');
            const shiftInfo = document.getElementById('activityShiftInfo');

            if (data.shift_start && data.shift_end) {
                shiftInfo.innerHTML = `<i class="fa-solid fa-clock"></i> Assigned shift: <strong>${formatDuration(data.required_minutes)}</strong> per day`;
            } else {
                shiftInfo.innerHTML = `<i class="fa-solid fa-clock"></i> No shift assigned yet — edit the account to set one.`;
            }

            renderWarningHistory(data.warnings);

            if (data.error || !data.sessions || data.sessions.length === 0) {
                body.innerHTML = '<p class="empty-row">No login activity found.</p>';
                return;
            }
            let html = '<table class="data-table"><thead><tr><th>Time In</th><th>Time Out</th><th>Duration</th><th>IP</th></tr></thead><tbody>';
            data.sessions.forEach(s => {
                const rowClass = s.shortfall ? 'session-short' : '';
                const durationCell = s.time_out
                    ? `${formatDuration(s.duration_minutes)}${s.shortfall ? ' <span class="shortfall-badge">Short</span>' : ''}`
                    : '<em>Ongoing / most recent</em>';
                html += `<tr class="${rowClass}"><td>${s.time_in}</td><td>${s.time_out ?? '<em>Ongoing / most recent</em>'}</td><td>${durationCell}</td><td>${s.ip ?? '—'}</td></tr>`;
            });
            html += '</tbody></table>';
            body.innerHTML = html;
        })
        .catch(() => {
            document.getElementById('activityBody').innerHTML = '<p class="empty-row">Failed to load activity.</p>';
        });
}

function sendWarning() {
    const message = document.getElementById('warningMessage').value.trim();
    const status  = document.getElementById('warningStatus');
    if (!message) {
        status.textContent = 'Write a message first.';
        status.className = 'warning-status warning-status-error';
        return;
    }
    if (!currentActivityUserId) return;

    const btn = document.getElementById('sendWarningBtn');
    btn.disabled = true;
    status.textContent = 'Sending...';
    status.className = 'warning-status';

    const body = new URLSearchParams({
        ajax_action: 'send_warning',
        user_id: currentActivityUserId,
        message: message,
    });

    fetch('user-access-control.php', { method: 'POST', body })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            if (data.ok) {
                document.getElementById('warningMessage').value = '';
                // Refresh just the warning history, so the sessions table
                // doesn't reload and the status message isn't wiped out.
                fetch(`user-access-control.php?ajax=activity&id=${currentActivityUserId}`)
                    .then(r => r.json())
                    .then(d => renderWarningHistory(d.warnings));
                status.textContent = 'Warning sent.';
                status.className = 'warning-status warning-status-ok';
            } else {
                status.textContent = data.error || 'Could not send warning.';
                status.className = 'warning-status warning-status-error';
            }
        })
        .catch(() => {
            btn.disabled = false;
            status.textContent = 'Could not send warning.';
            status.className = 'warning-status warning-status-error';
        });
}

// Close modal on overlay click (not inner box)
document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('open'); });
});
</script>
</body>
</html>