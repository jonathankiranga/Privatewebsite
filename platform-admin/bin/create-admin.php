<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this command from the server terminal.\n");
}
$email = isset($argv[1]) ? strtolower(trim($argv[1])) : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php admin@example.com\n");
    exit(1);
}
$count = (int)db()->query('SELECT COUNT(*) AS total FROM admin_users')->fetch_assoc()['total'];
if ($count > 0) {
    fwrite(STDERR, "An administrator already exists. Create additional administrators through a separately authorised workflow.\n");
    exit(1);
}
if (!function_exists('readline')) {
    fwrite(STDERR, "PHP readline extension is required for secure password entry.\n");
    exit(1);
}
$name = trim((string)readline('Administrator display name: '));
if ($name === '') {
    fwrite(STDERR, "A display name is required.\n");
    exit(1);
}
$password = (string)readline('Password (minimum 14 characters): ');
if (strlen($password) < 14) {
    fwrite(STDERR, "Password must contain at least 14 characters.\n");
    exit(1);
}
$confirm = (string)readline('Confirm password: ');
if (!hash_equals($password, $confirm)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = db()->prepare("INSERT INTO admin_users (email,password_hash,display_name,role) VALUES (?,?,?,'owner')");
$stmt->bind_param('sss', $email, $hash, $name);
$stmt->execute();
fwrite(STDOUT, "Initial owner account created for {$email}. Delete shell history if your environment records command arguments.\n");
