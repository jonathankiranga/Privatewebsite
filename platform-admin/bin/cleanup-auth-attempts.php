<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this command from the server terminal or a scheduled CLI job.\n");
    exit(1);
}
$admin = db()->query("DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$customer = db()->query("DELETE FROM platform_customer_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
fwrite(STDOUT, "Removed ".$admin->affected_rows." old administrator login attempts and ".$customer->affected_rows." old customer login attempts.\n");
