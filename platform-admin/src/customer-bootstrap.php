<?php
declare(strict_types=1);

require_once __DIR__ . '/load-config.php';
require_once __DIR__ . '/mailer.php';

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('PHP 7.4 or later is required.');
}
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_SAPI !== 'cli' && !$isHttps) {
    http_response_code(400);
    exit('HTTPS is required.');
}
/*
 * Keep customer authentication isolated to its own HTTPS-only session cookie.
 * Explicit INI settings override shared-hosting PHP defaults.
 */
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_path', '/');
// PHP's default session garbage-collection lifetime is commonly 24 minutes.
// Customer sessions may remain server-side for 30 days; the remember token below
// is the durable credential and is independently validated against the database.
ini_set('session.gc_maxlifetime', '2592000');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SMARTNOW_CUSTOMER');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if (!session_start()) {
        error_log('SmartNow customer session_start() failed. Check PHP session.save_path and directory permissions.');
        http_response_code(500);
        exit('The customer session could not be started. Please try again later.');
    }
} elseif (session_name() !== 'SMARTNOW_CUSTOMER') {
    error_log('SmartNow customer bootstrap found an already-active PHP session with an unexpected name.');
    http_response_code(500);
    exit('The customer session is not configured correctly.');
}

if (session_id() === '') {
    error_log('SmartNow customer bootstrap has no active PHP session ID.');
    http_response_code(500);
    exit('The customer session could not be started. Please try again later.');
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
    return platform_send_mail($to, $subject, $body, 'SmarterNow Support');
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
function customer_issue_remember_token(int $accountId): void
{
    $db = customer_db();
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $stmt = $db->prepare('INSERT INTO platform_customer_remember_tokens (account_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY))');
    $stmt->bind_param('is', $accountId, $hash);
    $stmt->execute();
    setcookie('SMARTNOW_CUSTOMER_REMEMBER', $raw, [
        'expires' => time() + 2592000,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
function customer_establish_login(array $account, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['customer'] = [
        'account_id'=>(int)$account['id'],
        'account_updated_at'=>$account['updated_at'],
        'customer_id'=>(int)$account['customer_id'],
        'email'=>$account['email'],
        'contact_name'=>$account['contact_name'],
        'organisation_name'=>$account['organisation_name']
    ];
    $_SESSION['customer_last_activity'] = time();
    if ($remember) {
        if (!empty($_COOKIE['SMARTNOW_CUSTOMER_REMEMBER']) && preg_match('/^[a-f0-9]{64}$/', (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER'])) {
            $oldHash = hash('sha256', (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER']);
            $revoke = customer_db()->prepare('DELETE FROM platform_customer_remember_tokens WHERE token_hash=?');
            $revoke->bind_param('s', $oldHash);
            $revoke->execute();
        }
        customer_issue_remember_token((int)$account['id']);
    } else {
        if (!empty($_COOKIE['SMARTNOW_CUSTOMER_REMEMBER']) && preg_match('/^[a-f0-9]{64}$/', (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER'])) {
            $oldHash = hash('sha256', (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER']);
            $revoke = customer_db()->prepare('DELETE FROM platform_customer_remember_tokens WHERE token_hash=?');
            $revoke->bind_param('s', $oldHash);
            $revoke->execute();
        }
        setcookie('SMARTNOW_CUSTOMER_REMEMBER', '', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    }
}
function customer_restore_remembered_login(): void
{
    if (customer_current() || empty($_COOKIE['SMARTNOW_CUSTOMER_REMEMBER'])) return;
    $raw = (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER'];
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) return;
    $hash = hash('sha256', $raw);
    $stmt = customer_db()->prepare('SELECT t.id AS token_id,a.id,a.customer_id,a.email_verified_at,a.updated_at,c.email,c.contact_name,c.organisation_name,c.status FROM platform_customer_remember_tokens t INNER JOIN platform_customer_accounts a ON a.id=t.account_id INNER JOIN platform_customers c ON c.id=a.customer_id WHERE t.token_hash=? AND t.expires_at>UTC_TIMESTAMP() LIMIT 1');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    if (!$account || !$account['email_verified_at'] || in_array($account['status'], ['suspended','closed'], true)) {
        setcookie('SMARTNOW_CUSTOMER_REMEMBER', '', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        return;
    }
    $delete = customer_db()->prepare('DELETE FROM platform_customer_remember_tokens WHERE id=?');
    $delete->bind_param('i', $account['token_id']);
    $delete->execute();
    customer_establish_login($account, true);
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
        if (!empty($_COOKIE['SMARTNOW_CUSTOMER_REMEMBER']) && preg_match('/^[a-f0-9]{64}$/', (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER'])) {
            $rememberHash = hash('sha256', (string)$_COOKIE['SMARTNOW_CUSTOMER_REMEMBER']);
            $revoke = customer_db()->prepare('DELETE FROM platform_customer_remember_tokens WHERE token_hash=?');
            $revoke->bind_param('s', $rememberHash);
            $revoke->execute();
        }
        setcookie('SMARTNOW_CUSTOMER_REMEMBER', '', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        $_SESSION = [];
        session_destroy();
        customer_redirect('customer-login.php?access=unavailable');
    }
    $_SESSION['customer_last_activity'] = time();
    $_SESSION['customer'] = [
        'account_id'=>(int)$row['id'],
        'account_updated_at'=>$row['updated_at'],
        'customer_id'=>(int)$row['customer_id'],
        'email'=>$row['email'],
        'contact_name'=>$row['contact_name'],
        'organisation_name'=>$row['organisation_name']
    ];
}

// Restore a validated customer login after the browser session cookie is gone.
customer_restore_remembered_login();
