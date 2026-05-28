<?php
session_start();
require_once __DIR__ . '/security.php';

/* Prevent browser cache */
sendNoStoreHeaders();

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin() {

    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function getCurrentUser() {
    return $_SESSION['username'] ?? '';
}
?>
