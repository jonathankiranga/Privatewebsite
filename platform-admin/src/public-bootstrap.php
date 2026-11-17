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
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function public_db(): mysqli
{
    static $connection = null;
    if ($connection instanceof mysqli) return $connection;
    $host = getenv('SMARTNOW_DB_HOST');
    $name = getenv('SMARTNOW_DB_NAME');
    $user = getenv('SMARTNOW_DB_USER');
    $pass = getenv('SMARTNOW_DB_PASS');
    if (!$host || !$name || !$user) {
        error_log('SmartNow public help database environment is incomplete.');
        http_response_code(500);
        exit('The help centre is temporarily unavailable.');
    }
    try {
        $connection = new mysqli($host, $user, $pass ?: '', $name);
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (Throwable $e) {
        error_log('SmartNow public help database connection failed: '.$e->getMessage());
        http_response_code(500);
        exit('The help centre is temporarily unavailable.');
    }
}
function public_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
