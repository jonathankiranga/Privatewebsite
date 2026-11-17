<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';

if (current_admin()) {
    redirect('index.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $emailHash = hash('sha256', $email);
    $stmt = db()->prepare('SELECT COUNT(*) AS attempts FROM admin_login_attempts WHERE was_successful=0 AND (email_hash=? OR ip_hash=?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $stmt->bind_param('ss', $emailHash, $ipHash);
    $stmt->execute();
    $attempts = (int)$stmt->get_result()->fetch_assoc()['attempts'];
    if ($attempts >= 10) {
        $error = 'Too many attempts. Wait 15 minutes before trying again.';
    } else {
        $stmt = db()->prepare('SELECT id,email,password_hash,display_name,role,is_active,locked_until FROM admin_users WHERE email=? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $valid = $user && (int)$user['is_active'] === 1 && (!$user['locked_until'] || strtotime($user['locked_until']) <= time()) && password_verify($password, $user['password_hash']);
        $success = $valid ? 1 : 0;
        $log = db()->prepare('INSERT INTO admin_login_attempts (email_hash,ip_hash,was_successful) VALUES (?,?,?)');
        $log->bind_param('ssi', $emailHash, $ipHash, $success);
        $log->execute();
        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin'] = ['id'=>(int)$user['id'],'email'=>$user['email'],'display_name'=>$user['display_name'],'role'=>$user['role']];
            $reset = db()->prepare('UPDATE admin_users SET failed_attempts=0,locked_until=NULL,last_login_at=NOW() WHERE id=?');
            $reset->bind_param('i', $user['id']);
            $reset->execute();
            audit_event('admin.login', 'admin_user', (int)$user['id']);
            redirect('index.php');
        }
        if ($user) {
            $fail = db()->prepare('UPDATE admin_users SET failed_attempts=failed_attempts+1, locked_until=IF(failed_attempts+1>=8,DATE_ADD(NOW(),INTERVAL 15 MINUTE),locked_until) WHERE id=?');
            $fail->bind_param('i', $user['id']);
            $fail->execute();
        }
        $error = 'The email or password is incorrect, or the account is unavailable.';
    }
}
page_header('Administrator sign in');
?>
<div style="max-width:440px;margin:5vh auto">
<div class="eyebrow">Restricted access</div><h1 class="heading">Sign in to SmartNow</h1><p class="sub">This area is for authorised platform administrators.</p>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel">
<form method="post" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" autocomplete="username" required>
<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<div style="margin-top:22px"><button class="btn" type="submit">Sign in</button></div>
</form>
</div></div>
<?php page_footer(); ?>
