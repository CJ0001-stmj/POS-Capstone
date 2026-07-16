<?php
// Run this ONCE from the command line to create a demo login, then delete
// the file (or at least remove it from anywhere publicly reachable):
//   php seed_user.php

require_once __DIR__ . '/db.php';

$email = 'cj@gmail.com';
$password = 'cj123';

$db = get_db_connection();

$check = $db->prepare('SELECT id FROM users WHERE email = :email');
$check->execute([':email' => $email]);

if ($check->fetch()) {
    echo "User $email already exists - nothing to do.\n";
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);

$insert = $db->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :hash)');
$insert->execute([':email' => $email, ':hash' => $hash]);

echo "Created demo user: $email / $password\n";
