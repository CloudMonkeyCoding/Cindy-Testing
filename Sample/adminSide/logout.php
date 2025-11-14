<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_unset();
session_destroy();

session_start();
session_regenerate_id(true);

$_SESSION['admin_timeout_message'] = 'You have been signed out successfully.';

header('Location: login.php');
exit;
