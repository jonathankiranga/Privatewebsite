<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
if (customer_current()) customer_redirect('customer-dashboard.php');
$error = '';
if (isset($_GET['verified'])) $error = 'Your email is verified. Sign in to continue.';
if (isset($_GET['access'])) $error = 'This account is unavailable. Contact SmartNow support.';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    customer_verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $emailHash = hash('sha256', $email);
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $limit = customer_db()->prepare('SELECT COUNT(*) AS attempts FROM platform_customer_login_attempts WHERE (email_hash=? OR ip_hash=?) AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
    $limit->bind_param('ss', $emailHash, $ipHash);
    $limit->execute();
    $attemptCount = (int)$limit->get_result()->fetch_assoc()['attempts'];
    $stmt = customer_db()->prepare('SELECT a.id,a.customer_id,a.password_hash,a.email_verified_at,a.updated_at,c.email,c.contact_name,c.organisation_name,c.status FROM platform_customer_accounts a INNER JOIN platform_customers c ON c.id=a.customer_id WHERE c.email=? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $validLogin = $attemptCount < 10 && $account && password_verify($password, $account['password_hash']) && $account['email_verified_at'] && !in_array($account['status'], ['suspended','closed'], true)) {
        session_regenerate_id(true);
        $_SESSION['customer'] = ['account_id'=>(int)$account['id'],'account_updated_at'=>$account['updated_at'],'customer_id'=>(int)$account['customer_id'],'email'=>$account['email'],'contact_name'=>$account['contact_name'],'organisation_name'=>$account['organisation_name']];
        $id = (int)$account['id'];
        $stmt = customer_db()->prepare('UPDATE platform_customer_accounts SET last_login_at=NOW() WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        customer_redirect('customer-dashboard.php');
    }
    $success = ($validLogin && $account['email_verified_at'] && !in_array($account['status'], ['suspended','closed'], true)) ? 1 : 0;
    $log = customer_db()->prepare('INSERT INTO platform_customer_login_attempts (email_hash,ip_hash,was_successful) VALUES (?,?,?)');
    $log->bind_param('ssi', $emailHash, $ipHash, $success);
    $log->execute();
    $error = $attemptCount >= 10 ? 'Too many sign-in attempts. Wait 15 minutes before trying again.' : 'Sign-in details are incorrect, the email is not verified, or the account is unavailable. You can request a verification email or reset your password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customer sign in · SmartNow</title><style>*{box-sizing:border-box}body{font-family:system-ui;background:#f4f6fa;color:#172033;margin:0}.panel{max-width:460px;margin:8vh auto;background:white;padding:28px;border:1px solid #e5e9f0;border-radius:14px}label{display:block;margin:16px 0 6px;font-size:13px;font-weight:650}input{width:100%;padding:12px;border:1px solid #cfd6e2;border-radius:8px;font:inherit}.btn{margin-top:20px;padding:12px 18px;background:#2457d6;color:white;border:0;border-radius:8px;font-weight:700}.notice{padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px}.links{margin-top:18px;font-size:14px}a{color:#2457d6}</style></head><body><div class="panel"><h1>Customer sign in</h1><p>Access your SmartNow customer account.</p><?php if ($error): ?><div class="notice"><?= customer_e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" required><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button class="btn" type="submit">Sign in</button></form><div class="links"><a href="customer-register.php">Create account</a> · <a href="customer-forgot.php">Forgot password?</a> · <a href="customer-resend-verification.php">Resend verification</a></div></div></body></html>