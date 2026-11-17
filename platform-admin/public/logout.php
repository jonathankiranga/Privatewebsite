<?php
require dirname(__DIR__) . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
$admin = current_admin();
if ($admin) {
    audit_event('admin.logout', 'admin_user', (int)$admin['id']);
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
}
session_destroy();
redirect('login.php');
