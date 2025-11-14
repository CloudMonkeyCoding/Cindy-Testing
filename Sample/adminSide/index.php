<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
