<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    customer_verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = customer_db()->prepare('SELECT a.id FROM platform_customer_accounts a INNER JOIN platform_customers c ON c.id=a.customer_id WHERE c.email=? AND a.email_verified_at IS NULL LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        if ($account) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $id = (int)$account['id'];
            $stmt = customer_db()->prepare('UPDATE platform_customer_accounts SET verification_token_hash=?,verification_expires_at=DATE_ADD(NOW(),INTERVAL 24 HOUR) WHERE id=?');
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute();
            $url = customer_portal_url('customer-verify.php', $token);
            if ($url) customer_send_mail($email, 'Verify your SmartNow account', "Use this link to verify your email address (valid for 24 hours):\n".$url);
        }
    }
    $sent = true;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resend verification · SmartNow</title><style>body{font-family:system-ui;background:#f4f6fa;color:#172033}.panel{max-width:460px;margin:8vh auto;background:white;padding:28px;border:1px solid #e5e9f0;border-radius:14px}label{display:block;margin:16px 0 6px}input{width:100%;padding:12px;border:1px solid #cfd6e2;border-radius:8px}.btn{margin-top:20px;padding:12px 18px;background:#2457d6;color:white;border:0;border-radius:8px}a{color:#2457d6}</style></head><body><div class="panel"><h1>Resend verification</h1><?php if ($sent): ?><p>If an unverified account exists for that email, a new verification link has been sent.</p><?php else: ?><p>Enter the email address used to register.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><label>Email address</label><input name="email" type="email" required><button class="btn" type="submit">Send verification link</button></form><?php endif; ?><p><a href="customer-login.php">Back to sign in</a></p></div></body></html>