<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$done = false;
$valid = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $hash = hash('sha256', $token);
    $stmt = customer_db()->prepare('SELECT id FROM platform_customer_accounts WHERE password_reset_token_hash=? AND password_reset_expires_at>NOW() AND email_verified_at IS NOT NULL LIMIT 1');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $valid = (bool)$stmt->get_result()->fetch_assoc();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    customer_verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!$valid) $error = 'This password reset link is invalid or has expired.';
    elseif (strlen($password) < 14) $error = 'Use a password with at least 14 characters.';
    elseif (!hash_equals($password, $confirm)) $error = 'The passwords do not match.';
    else {
        $hash = hash('sha256', $token);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = customer_db()->prepare('UPDATE platform_customer_accounts SET password_hash=?,password_reset_token_hash=NULL,password_reset_expires_at=NULL WHERE password_reset_token_hash=? AND password_reset_expires_at>NOW()');
        $stmt->bind_param('ss', $passwordHash, $hash);
        $stmt->execute();
        $done = $stmt->affected_rows === 1;
        $valid = false;
        if (!$done) $error = 'This password reset link is invalid or has expired.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Choose a new password · SmartNow</title><style>body{font-family:system-ui;background:#f4f6fa;color:#172033}.panel{max-width:460px;margin:8vh auto;background:white;padding:28px;border:1px solid #e5e9f0;border-radius:14px}label{display:block;margin:16px 0 6px}input{width:100%;padding:12px;border:1px solid #cfd6e2;border-radius:8px}.btn{margin-top:20px;padding:12px 18px;background:#2457d6;color:white;border:0;border-radius:8px}.notice{padding:12px;background:#fff1f2;border:1px solid #fecdd3;border-radius:8px}a{color:#2457d6}</style></head><body><div class="panel"><h1>Choose a new password</h1><?php if ($done): ?><p>Your password has been changed. <a href="customer-login.php">Sign in</a>.</p><?php else: ?><?php if ($error): ?><div class="notice"><?= customer_e($error) ?></div><?php endif; ?><?php if ($valid): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><input type="hidden" name="token" value="<?= customer_e($token) ?>"><label>New password (14+ characters)</label><input type="password" name="password" minlength="14" autocomplete="new-password" required><label>Confirm new password</label><input type="password" name="confirm_password" minlength="14" autocomplete="new-password" required><button class="btn" type="submit">Change password</button></form><?php else: ?><p>Request a new reset link from the <a href="customer-forgot.php">password reset page</a>.</p><?php endif; ?><?php endif; ?></div></body></html>