<?php
// Database connection settings. Adjust to match your local/production MySQL.
// In a real deployment, load these from environment variables instead of
// hardcoding credentials in a file that sits in your web root.

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mms');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function get_db_connection(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Database connection failed.']);
        exit;
    }
}

// mysqli connection, for pages using $conn->query(...) style (audit-overview.php,
// user-access-control.php, etc). Kept alongside get_db_connection() so PDO-based
// pages (shift-sales.php) keep working too.
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    die('Database connection failed.');
}
$conn->set_charset(DB_CHARSET);