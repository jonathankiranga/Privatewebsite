<?php
declare(strict_types=1);

require_once __DIR__ . '/load-config.php';

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('PHP 7.4 or later is required.');
}

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$secureCookie = getenv('SMARTNOW_SESSION_SECURE') === '1' || $isHttps;
if (PHP_SAPI !== 'cli' && !$isHttps) {
    http_response_code(400);
    exit('HTTPS is required.');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SMARTNOW_ADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli
{
    static $connection = null;
    if ($connection instanceof mysqli) {
        return $connection;
    }
    $host = getenv('SMARTNOW_DB_HOST');
    $name = getenv('SMARTNOW_DB_NAME');
    $user = getenv('SMARTNOW_DB_USER');
    $pass = getenv('SMARTNOW_DB_PASS');
    if (!$host || !$name || !$user) {
        error_log('SmartNow admin database environment is incomplete.');
        http_response_code(500);
        exit('The administration service is not configured. Contact the system administrator.');
    }
    try {
        $connection = new mysqli($host, $user, $pass ?: '', $name);
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (Throwable $e) {
        error_log('SmartNow admin database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('The administration service is temporarily unavailable.');
    }
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $provided = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if (!$provided || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('Your form session has expired. Go back, refresh the page and try again.');
    }
}

function admin_send_mail(string $to, string $subject, string $body): bool
{
    $from = trim((string)getenv('SMARTNOW_MAIL_FROM'));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('SMARTNOW_MAIL_FROM is not configured with a valid email address.');
        return false;
    }
    $headers = "From: SmartNow Administration <".$from.">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, $headers);
}

function admin_portal_url(string $path, string $token): ?string
{
    $base = rtrim((string)getenv('SMARTNOW_ADMIN_PORTAL_URL'), '/');
    if (!$base || !filter_var($base, FILTER_VALIDATE_URL) || strtolower((string)parse_url($base, PHP_URL_SCHEME)) !== 'https') {
        error_log('SMARTNOW_ADMIN_PORTAL_URL is not configured as a valid HTTPS URL.');
        return null;
    }
    return $base.'/'.$path.'?token='.rawurlencode($token);
}

function current_admin(): ?array
{
    return isset($_SESSION['admin']) && is_array($_SESSION['admin']) ? $_SESSION['admin'] : null;
}

function require_admin(): void
{
    $sessionAdmin = current_admin();
    if (!$sessionAdmin || empty($sessionAdmin['id'])) {
        header('Location: login.php');
        exit;
    }

    $stmt = db()->prepare('SELECT id,email,display_name,role,is_active,auth_version FROM admin_users WHERE id=? LIMIT 1');
    $adminId = (int)$sessionAdmin['id'];
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $freshAdmin = $stmt->get_result()->fetch_assoc();

    if (!$freshAdmin || (int)$freshAdmin['is_active'] !== 1 || !isset($sessionAdmin['auth_version']) || (int)$sessionAdmin['auth_version'] !== (int)$freshAdmin['auth_version']) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['admin'] = [
        'id' => (int)$freshAdmin['id'],
        'email' => $freshAdmin['email'],
        'display_name' => $freshAdmin['display_name'],
        'role' => $freshAdmin['role'],
        'auth_version' => (int)$freshAdmin['auth_version']
    ];
}

function require_roles(array $roles): void
{
    require_admin();
    $admin = current_admin();
    if (!$admin || !in_array((string)($admin['role'] ?? ''), $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this area.');
    }
}


function audit_event(string $event, ?string $entityType = null, ?int $entityId = null, array $details = []): void
{
    $admin = current_admin();
    $json = $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null;
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $stmt = db()->prepare('INSERT INTO admin_audit_events (admin_user_id,event_type,entity_type,entity_id,details_json,ip_hash) VALUES (?,?,?,?,?,?)');
    $adminId = $admin ? (int)$admin['id'] : null;
    $stmt->bind_param('ississ', $adminId, $event, $entityType, $entityId, $json, $ipHash);
    $stmt->execute();
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}
