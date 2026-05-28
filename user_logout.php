<?php
session_start();
require_once __DIR__ . '/config/security.php';

sendNoStoreHeaders();
destroyCurrentSession();

header('Location: login.php?logged_out=1');
exit();
?>
