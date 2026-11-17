<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
$token = trim((string)($_GET['token'] ?? ''));
$verified = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $hash = hash('sha256', $token);
    $stmt = customer_db()->prepare('SELECT id FROM platform_customer_accounts WHERE verification_token_hash=? AND verification_expires_at>NOW() AND email_verified_at IS NULL LIMIT 1');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    if ($account) {
        $id = (int)$account['id'];
        $stmt = customer_db()->prepare('UPDATE platform_customer_accounts SET email_verified_at=NOW(),verification_token_hash=NULL,verification_expires_at=NULL WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $verified = true;
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Email verification · SmartNow</title><style>body{font-family:system-ui;background:#f4f6fa;color:#172033;margin:0}.panel{max-width:520px;margin:10vh auto;background:white;padding:28px;border:1px solid #e5e9f0;border-radius:14px}a{color:#2457d6}</style></head><body><div class="panel"><h1><?= $verified ? 'Email verified' : 'Verification link unavailable' ?></h1><p><?= $verified ? 'Your email address is verified. You can now sign in to your SmartNow customer account.' : 'This link is invalid, expired or has already been used. Request a new verification email.' ?></p><p><a href="customer-login.php">Continue to sign in</a> · <a href="customer-resend-verification.php">Resend verification</a></p></div></body></html>