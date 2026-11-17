<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('PHP 7.4 or later is required.');
}
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_SAPI !== 'cli' && !$isHttps) {
    http_response_code(400);
    exit('HTTPS is required.');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SMARTNOW_CUSTOMER');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function customer_db(): mysqli
{
    static $connection = null;
    if ($connection instanceof mysqli) return $connection;
    $host = getenv('SMARTNOW_DB_HOST');
    $name = getenv('SMARTNOW_DB_NAME');
    $user = getenv('SMARTNOW_DB_USER');
    $pass = getenv('SMARTNOW_DB_PASS');
    if (!$host || !$name || !$user) {
        error_log('SmartNow customer database environment is incomplete.');
        http_response_code(500);
        exit('The customer service is not configured.');
    }
    try {
        $connection = new mysqli($host, $user, $pass ?: '', $name);
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (Throwable $e) {
        error_log('SmartNow customer database connection failed: '.$e->getMessage());
        http_response_code(500);
        exit('The customer service is temporarily unavailable.');
    }
}
function customer_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function customer_csrf_token(): string
{
    if (empty($_SESSION['customer_csrf'])) $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['customer_csrf'];
}
function customer_verify_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!$token || !hash_equals(customer_csrf_token(), $token)) {
        http_response_code(419);
        exit('Your form session expired. Refresh the page and try again.');
    }
}
function customer_redirect(string $path): void
{
    header('Location: '.$path);
    exit;
}
function customer_send_mail(string $to, string $subject, string $body): bool
{
    $from = trim((string)getenv('SMARTNOW_MAIL_FROM'));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('SMARTNOW_MAIL_FROM is not configured with a valid email address.');
        return false;
    }
    $headers = "From: SmartNow Support <".$from.">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, $headers);
}
function customer_portal_url(string $path, string $token): ?string
{
    $base = rtrim((string)getenv('SMARTNOW_CUSTOMER_PORTAL_URL'), '/');
    if (!$base || !filter_var($base, FILTER_VALIDATE_URL) || strtolower((string)parse_url($base, PHP_URL_SCHEME)) !== 'https') return null;
    return $base.'/'.$path.'?token='.rawurlencode($token);
}
function customer_current(): ?array
{
    return isset($_SESSION['customer']) && is_array($_SESSION['customer']) ? $_SESSION['customer'] : null;
}
function require_customer(): void
{
    $session = customer_current();
    if (!$session || empty($session['account_id'])) customer_redirect('customer-login.php');
    $stmt = customer_db()->prepare('SELECT a.id,a.customer_id,a.email_verified_at,a.updated_at,c.email,c.contact_name,c.organisation_name,c.status FROM platform_customer_accounts a INNER JOIN platform_customers c ON c.id=a.customer_id WHERE a.id=? LIMIT 1');
    $accountId = (int)$session['account_id'];
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || !$row['email_verified_at'] || in_array($row['status'], ['suspended','closed'], true) || empty($session['account_updated_at']) || $session['account_updated_at'] !== $row['updated_at']) {
        $_SESSION = [];
        session_destroy();
        customer_redirect('customer-login.php?access=unavailable');
    }
    $_SESSION['customer'] = [
        'account_id'=>(int)$row['id'],
        'account_updated_at'=>$row['updated_at'],
        'customer_id'=>(int)$row['customer_id'],
        'email'=>$row['email'],
        'contact_name'=>$row['contact_name'],
        'organisation_name'=>$row['organisation_name']
    ];
}
