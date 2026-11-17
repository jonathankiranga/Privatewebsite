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
fwrite(STDOUT, 'Administrator display name: ');
$name = trim((string)fgets(STDIN));
if ($name === '') {
    fwrite(STDERR, "A display name is required.\n");
    exit(1);
}
fwrite(STDOUT, 'Password (minimum 14 characters): ');
if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) shell_exec('stty -echo');
$password = trim((string)fgets(STDIN));
if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) { shell_exec('stty echo'); fwrite(STDOUT, "\\n"); }
if (strlen($password) < 14) {
    fwrite(STDERR, "Password must contain at least 14 characters.\n");
    exit(1);
}
fwrite(STDOUT, 'Confirm password: ');
if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) shell_exec('stty -echo');
$confirm = trim((string)fgets(STDIN));
if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) { shell_exec('stty echo'); fwrite(STDOUT, "\\n"); }
if (!hash_equals($password, $confirm)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = db()->prepare("INSERT INTO admin_users (email,password_hash,display_name,role) VALUES (?,?,?,'owner')");
$stmt->bind_param('sss', $email, $hash, $name);
$stmt->execute();
fwrite(STDOUT, "Initial owner account created for {$email}. Delete shell history if your environment records command arguments.\n");
