<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/public-bootstrap.php';

$allowedOrigin = 'https://smarternowhosting.co.ke';
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== $allowedOrigin) { http_response_code(403); exit; }
header('Access-Control-Allow-Origin: '.$allowedOrigin);
header('Vary: Origin');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data) || ($data['event'] ?? '') !== 'page_view') { http_response_code(400); exit; }
$visitorId = (string)($data['visitor_id'] ?? '');
$page = (string)($data['page'] ?? '');
if (!preg_match('/^[a-f0-9]{32,64}$/', $visitorId) || $page === '' || strlen($page) > 500 ||
    $page[0] !== '/' || strpos($page, '//') === 0 || preg_match('/[\r\n]/', $page)) {
    http_response_code(400); exit;
}
$secret = (string)getenv('SMARTNOW_ANALYTICS_SECRET');
if (strlen($secret) < 32) {
    error_log('SMARTNOW_ANALYTICS_SECRET must contain at least 32 characters.');
    http_response_code(503); exit;
}
$visitorHash = hash_hmac('sha256', $visitorId, $secret);
$ipHash = hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $secret);
$referrerHost = null;
$referrer = (string)($data['referrer'] ?? '');
if ($referrer !== '') {
    $host = parse_url($referrer, PHP_URL_HOST);
    if (is_string($host) && $host !== '') $referrerHost = substr(strtolower($host), 0, 190);
}
$ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$device = 'desktop';
if (preg_match('/ipad|tablet|kindle|playbook/', $ua)) $device = 'tablet';
elseif (preg_match('/mobile|android|iphone|ipod/', $ua)) $device = 'mobile';
$browser = 'other';
if (strpos($ua, 'edg/') !== false) $browser = 'Edge';
elseif (strpos($ua, 'opr/') !== false) $browser = 'Opera';
elseif (strpos($ua, 'firefox/') !== false) $browser = 'Firefox';
elseif (strpos($ua, 'chrome/') !== false) $browser = 'Chrome';
elseif (strpos($ua, 'safari/') !== false) $browser = 'Safari';
if (strpos($ua, 'bot') !== false || strpos($ua, 'spider') !== false || strpos($ua, 'crawler') !== false) {
    http_response_code(204); exit;
}
try {
    $stmt = public_db()->prepare("INSERT INTO platform_visitor_events (visitor_hash,event_type,page_path,referrer_host,device_type,browser_family,ip_hash) VALUES (?,'page_view',?,?,?,?,?)");
    $stmt->bind_param('ssssss', $visitorHash, $page, $referrerHost, $device, $browser, $ipHash);
    $stmt->execute();
    http_response_code(204);
} catch (Throwable $e) {
    error_log('Visitor analytics insert failed: '.$e->getMessage());
    http_response_code(503);
}
