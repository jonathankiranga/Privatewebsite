<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$done = false;
$valid = false;
$adminId = 0;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT id FROM admin_users WHERE password_reset_token_hash=? AND password_reset_expires_at>NOW() AND is_active=1 LIMIT 1');
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    if ($admin) {
        $valid = true;
        $adminId = (int)$admin['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!$valid) {
        $error = 'This password reset link is invalid or has expired. Request a new link.';
    } elseif (strlen($password) < 14) {
        $error = 'Use a password with at least 14 characters.';
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'The passwords do not match.';
    } else {
        $tokenHash = hash('sha256', $token);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE admin_users SET password_hash=?,password_reset_token_hash=NULL,password_reset_expires_at=NULL,failed_attempts=0,locked_until=NULL,auth_version=auth_version+1 WHERE id=? AND password_reset_token_hash=? AND password_reset_expires_at>NOW() AND is_active=1');
        $stmt->bind_param('sis', $passwordHash, $adminId, $tokenHash);
        $stmt->execute();
        $done = $stmt->affected_rows === 1;
        $valid = false;
        if ($done) {
            $event = 'admin.password_reset';
            $entity = 'admin_user';
            $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
            $audit = db()->prepare('INSERT INTO admin_audit_events (admin_user_id,event_type,entity_type,entity_id,ip_hash) VALUES (?,?,?,?,?)');
            $audit->bind_param('issis', $adminId, $event, $entity, $adminId, $ipHash);
            $audit->execute();
        } else {
            $error = 'This password reset link is invalid or has expired. Request a new link.';
        }
    }
}
page_header('Choose a new administrator password');
?>
<div style="max-width:480px;margin:5vh auto">
<div class="eyebrow">Account recovery</div>
<h1 class="heading">Choose a new password</h1>
<div class="panel">
<?php if ($done): ?>
<div class="notice">Your administrator password has been changed. Other active administrator sessions have been invalidated.</div>
<p><a href="login.php">Continue to administrator sign in</a></p>
<?php else: ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<?php if ($valid): ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="token" value="<?= e($token) ?>">
<label for="password">New password (at least 14 characters)</label>
<input id="password" name="password" type="password" minlength="14" autocomplete="new-password" required>
<label for="confirm_password">Confirm new password</label>
<input id="confirm_password" name="confirm_password" type="password" minlength="14" autocomplete="new-password" required>
<div style="margin-top:20px"><button class="btn" type="submit">Change administrator password</button></div>
</form>
<?php else: ?>
<p>This reset link is invalid or has expired. <a href="admin-forgot.php">Request another reset link</a>.</p>
<?php endif; ?>
<?php endif; ?>
</div></div>
<?php page_footer(); ?>
