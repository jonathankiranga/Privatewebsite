<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
customer_verify_csrf();
if (!empty($_COOKIE['SMARTNOW_CUSTOMER_REMEMBER']) && preg_match('/^[a-f0-9]{64}$/', (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER'])) {
    $rememberHash = hash('sha256', (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER']);
    $revoke = customer_db()->prepare('DELETE FROM platform_customer_remember_tokens WHERE token_hash=?');
    $revoke->bind_param('s', $rememberHash);
    $revoke->execute();
}
setcookie('SMARTNOW_CUSTOMER_REMEMBER', '', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
}
session_destroy();
customer_redirect('customer-login.php');
