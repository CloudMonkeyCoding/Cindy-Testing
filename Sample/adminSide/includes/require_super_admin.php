<?php
require_once __DIR__ . '/require_admin_login.php';

if (empty($adminSession['is_super_admin'])) {
    http_response_code(403);
    echo 'Super admin privileges are required to access this page.';
    exit;
}

