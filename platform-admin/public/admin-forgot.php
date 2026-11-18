<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';

if (current_admin()) redirect('index.php');
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $emailHash = hash('sha256', $email);
    $ipHash = hash('sha256', $ip);

    // Retain only a short window of hashed request metadata.
    db()->query("DELETE FROM admin_password_reset_requests WHERE requested_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
    $limit = db()->prepare('SELECT COUNT(*) AS total FROM admin_password_reset_requests WHERE (email_hash=? OR ip_hash=?) AND requested_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)');
    $limit->bind_param('ss', $emailHash, $ipHash);
    $limit->execute();
    $allowed = (int)$limit->get_result()->fetch_assoc()['total'] < 5;

    $record = db()->prepare('INSERT INTO admin_password_reset_requests (email_hash,ip_hash) VALUES (?,?)');
    $record->bind_param('ss', $emailHash, $ipHash);
    $record->execute();

    if (!$allowed) {
        error_log('SmartNow administrator password recovery request throttled. IP hash: '.$ipHash.'.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('SmartNow administrator password recovery received an invalid email format.');
    } else {
        $stmt = db()->prepare('SELECT id FROM admin_users WHERE email=? AND is_active=1 LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        if (!$admin) {
            error_log('SmartNow administrator password recovery: no active administrator matched the submitted email hash '.$emailHash.'.');
        } else {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $adminId = (int)$admin['id'];
            $save = db()->prepare('UPDATE admin_users SET password_reset_token_hash=?,password_reset_expires_at=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE id=? AND is_active=1');
            $save->bind_param('si', $tokenHash, $adminId);
            $save->execute();
            $url = admin_portal_url('admin-reset.php', $token);
            if (!$url) {
                error_log('SmartNow administrator password recovery stopped: reset URL could not be generated for admin ID '.$adminId.'.');
            } else {
                $mailAccepted = admin_send_mail($email, 'Reset your SmartNow administrator password', "A password reset was requested for your SmartNow administrator account.\n\nUse this one-time link within one hour:\n".$url."\n\nIf you did not request this change, you can ignore this message.");
                if (!$mailAccepted) {
                    error_log('SmartNow administrator password reset SMTP delivery failed for admin ID '.$adminId.'. See the PHPMailer SMTP diagnostic immediately before this entry.');
                } else {
                    error_log('SmartNow administrator password reset SMTP server accepted the message for admin ID '.$adminId.'. This confirms SMTP server acceptance, not final delivery to the recipient's inbox.');
                }
            }
        }
    }
    $sent = true;
}
page_header('Administrator password recovery');
?>
<div style="max-width:480px;margin:5vh auto">
<div class="eyebrow">Account recovery</div>
<h1 class="heading">Reset administrator password</h1>
<p class="sub">Enter the email address registered to your administrator account.</p>
<div class="panel">
<?php if ($sent): ?>
<div class="notice">If an active administrator account matches that email address, a password reset link has been sent. Check your inbox and spam folder.</div>
<?php else: ?>
<form method="post" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label for="email">Administrator email address</label>
<input id="email" name="email" type="email" maxlength="190" autocomplete="username" required>
<div style="margin-top:20px"><button class="btn" type="submit">Send password reset link</button></div>
</form>
<?php endif; ?>
<p style="margin:18px 0 0;font-size:14px"><a href="login.php">Back to administrator sign in</a></p>
</div></div>
<?php page_footer(); ?>
