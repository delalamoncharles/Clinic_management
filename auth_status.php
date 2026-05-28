<?php
session_start();
require_once __DIR__ . '/config/security.php';

sendNoStoreHeaders();
header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
$authenticated = false;

if ($role === 'admin') {
    $authenticated = !empty($_SESSION['admin_id']);
} elseif ($role === 'user') {
    $authenticated = !empty($_SESSION['user_id']);
} else {
    $authenticated = !empty($_SESSION['admin_id']) || !empty($_SESSION['user_id']);
}

echo json_encode(['authenticated' => $authenticated]);
?>
