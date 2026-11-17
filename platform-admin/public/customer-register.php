<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
if (customer_current()) customer_redirect('customer-dashboard.php');
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    customer_verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $name = trim((string)($_POST['contact_name'] ?? ''));
    $organisation = trim((string)($_POST['organisation_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $country = strtoupper(trim((string)($_POST['country_code'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || $name === '' || strlen($name) > 160 || strlen($organisation) > 190 || strlen($phone) > 40 || ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country))) {
        $error = 'Check your email address and contact details.';
    } elseif (strlen($password) < 14) {
        $error = 'Use a password with at least 14 characters.';
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'The passwords do not match.';
    } elseif (!customer_portal_url('customer-verify.php', 'configuration-check') || !getenv('SMARTNOW_MAIL_FROM')) {
        $error = 'Account verification email is not configured yet. Please contact support.';
    } else {
        $db = customer_db();
        try {
            $db->begin_transaction();
            $stmt = $db->prepare('SELECT id,status FROM platform_customers WHERE email=? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $customer = $stmt->get_result()->fetch_assoc();
            if ($customer && in_array($customer['status'], ['suspended','closed'], true)) {
                throw new RuntimeException('This customer record is not eligible for account registration. Contact support.');
            }
            if ($customer) {
                $customerId = (int)$customer['id'];
            } else {
                $status = 'lead';
                $stmt = $db->prepare('INSERT INTO platform_customers (email,contact_name,phone,country_code,organisation_name,status) VALUES (?,?,?,?,?,?)');
                $stmt->bind_param('ssssss', $email, $name, $phone, $country, $organisation, $status);
                $stmt->execute();
                $customerId = (int)$db->insert_id;
            }
            $stmt = $db->prepare('SELECT id FROM platform_customer_accounts WHERE customer_id=? LIMIT 1');
            $stmt->bind_param('i', $customerId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('An account already exists for this email. Sign in or request a password reset.');
            }
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO platform_customer_accounts (customer_id,password_hash,verification_token_hash,verification_expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))');
            $stmt->bind_param('iss', $customerId, $passwordHash, $tokenHash);
            $stmt->execute();
            $db->commit();
            $verifyUrl = customer_portal_url('customer-verify.php', $token);
            $sent = $verifyUrl && customer_send_mail($email, 'Verify your SmartNow account', "Hello ".$name.",\n\nVerify your SmartNow customer account using this link (valid for 24 hours):\n".$verifyUrl."\n\nIf you did not create this account, you can ignore this email.");
            if ($sent) {
                $notice = 'Your account has been created. Check your email for the verification link before signing in.';
            } else {
                $notice = 'Your account record was created, but the verification email could not be sent. Use the resend verification page or contact support.';
            }
        } catch (RuntimeException $ex) {
            $db->rollback();
            $error = $ex->getMessage();
        } catch (mysqli_sql_exception $ex) {
            $db->rollback();
            error_log('Customer registration failed: '.$ex->getMessage());
            $error = 'We could not complete registration. If you already have an account, sign in or reset your password.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create customer account · SmartNow</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#172033;font-family:Inter,system-ui,sans-serif}.wrap{max-width:680px;margin:5vh auto;padding:24px}.panel{background:#fff;border:1px solid #e5e9f0;border-radius:16px;padding:28px;box-shadow:0 8px 30px rgba(16,24,40,.05)}h1{margin:0 0 8px;font-size:28px}.sub{color:#64748b;margin:0 0 24px}label{display:block;font-size:13px;font-weight:650;margin:14px 0 6px}input{width:100%;padding:12px;border:1px solid #cfd6e2;border-radius:8px;font:inherit}.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.btn{margin-top:22px;padding:12px 18px;background:#2457d6;color:white;border:0;border-radius:8px;font-weight:700;cursor:pointer}.notice{padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin:14px 0;color:#1e40af}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}.links{margin-top:20px;color:#64748b;font-size:14px}.links a{color:#2457d6}@media(max-width:560px){.grid{grid-template-columns:1fr}.wrap{padding:14px}.panel{padding:20px}}
</style></head><body><div class="wrap"><div class="panel"><h1>Create your SmartNow account</h1><p class="sub">Use one account to manage your SmartNow customer relationship and support requests.</p>
<?php if ($error): ?><div class="notice error"><?= customer_e($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="notice"><?= customer_e($notice) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>">
<div class="grid"><div><label for="contact_name">Your name</label><input id="contact_name" name="contact_name" maxlength="160" required></div><div><label for="organisation_name">Organisation</label><input id="organisation_name" name="organisation_name" maxlength="190"></div>
<div><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required></div><div><label for="phone">Phone number</label><input id="phone" name="phone" maxlength="40" autocomplete="tel"></div>
<div><label for="country_code">Country code (ISO 2-letter)</label><input id="country_code" name="country_code" maxlength="2" pattern="[A-Za-z]{2}" placeholder="KE"></div></div>
<div class="grid"><div><label for="password">Password (14+ characters)</label><input id="password" name="password" type="password" minlength="14" autocomplete="new-password" required></div><div><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="14" autocomplete="new-password" required></div></div>
<button class="btn" type="submit">Create account</button></form>
<div class="links">Already registered? <a href="customer-login.php">Sign in</a> · <a href="customer-resend-verification.php">Resend verification</a></div></div></div></body></html>
