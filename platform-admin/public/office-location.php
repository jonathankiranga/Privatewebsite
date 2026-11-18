<?php
declare(strict_types=1);

require __DIR__ . '/../src/public-bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://smarternowhosting.co.ke');
header('Cache-Control: public, max-age=60');

$location = 'Kerugoya, Kirinyaga County, Kenya';
try {
    $stmt = public_db()->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'office_location' LIMIT 1");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && trim((string)$row['setting_value']) !== '') {
        $location = trim((string)$row['setting_value']);
    }
} catch (Throwable $e) {
    error_log('Office location lookup failed: ' . $e->getMessage());
}

echo json_encode(['office_location' => $location], JSON_UNESCAPED_UNICODE);
