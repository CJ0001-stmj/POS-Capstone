<?php
session_start();
$timeout = isset($_GET['timeout']) ? '?timeout=1' : '';
$_SESSION = [];
session_destroy();
header('Location: index.php' . $timeout);
exit;