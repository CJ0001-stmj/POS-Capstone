<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

function log_attempt(PDO $db, string $email, bool $success, string $reason): void {
    $stmt = $db->prepare(
        'INSERT INTO login_audit (email, success, reason, ip_address, user_agent)
         VALUES (:email, :success, :reason, :ip, :ua)'
    );
    $stmt->execute([
        ':email'   => strtolower($email),
        ':success' => $success ? 1 : 0,
        ':reason'  => $reason,
        ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');

$db = get_db_connection();

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    log_attempt($db, $email, false, 'invalid_email_format');
    respond(400, ['ok' => false, 'message' => 'Please enter a valid email address.']);
}

$stmt = $db->prepare('SELECT id, email, password_hash, failed_attempts, locked_until FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => strtolower($email)]);
$user = $stmt->fetch();

if (!$user) {
    log_attempt($db, $email, false, 'user_not_found');
    // Same generic message as a wrong password, so we don't leak which emails exist.
    respond(401, ['ok' => false, 'message' => 'Invalid email or password.']);
}

if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    log_attempt($db, $email, false, 'account_locked');
    respond(423, [
        'ok' => false,
        'message' => 'Account temporarily locked. Try again after ' . $user['locked_until'] . '.',
    ]);
}

if (!password_verify($password, $user['password_hash'])) {
    $failedAttempts = $user['failed_attempts'] + 1;
    $lockedUntil = null;

    if ($failedAttempts >= MAX_FAILED_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
        $failedAttempts = 0;
    }

    $update = $db->prepare('UPDATE users SET failed_attempts = :attempts, locked_until = :locked WHERE id = :id');
    $update->execute([':attempts' => $failedAttempts, ':locked' => $lockedUntil, ':id' => $user['id']]);

    log_attempt($db, $email, false, 'invalid_password');
    respond(401, ['ok' => false, 'message' => 'Invalid email or password.']);
}

$reset = $db->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id');
$reset->execute([':id' => $user['id']]);

log_attempt($db, $email, true, 'success');

// Regenerate the session id on privilege change to guard against fixation.
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['logged_in_at'] = time();

respond(200, ['ok' => true, 'message' => 'Login successful.', 'email' => $user['email'], 'redirect' => 'dashboard.php']);