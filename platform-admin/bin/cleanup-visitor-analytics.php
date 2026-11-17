<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}
$result = db()->query("DELETE FROM platform_visitor_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
$events = $result->affected_rows;
$result = db()->query("DELETE FROM platform_public_submission_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$limits = $result->affected_rows;
echo "Deleted ".$events." visitor events older than 180 days and ".$limits." public-form rate-limit records older than 30 days.\n";
