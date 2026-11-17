<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this command from the server terminal or a scheduled CLI job.\n");
    exit(1);
}
$connection = db();
$connection->query("DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$adminDeleted = $connection->affected_rows;
$connection->query("DELETE FROM platform_customer_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$customerDeleted = $connection->affected_rows;
fwrite(STDOUT, "Removed ".$adminDeleted." old administrator login attempts and ".$customerDeleted." old customer login attempts.\n");
