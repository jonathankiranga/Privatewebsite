<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
require_customer();
$customer = customer_current();
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    customer_verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'profile') {
        $name = trim((string)($_POST['contact_name'] ?? ''));
        $organisation = trim((string)($_POST['organisation_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $country = strtoupper(trim((string)($_POST['country_code'] ?? '')));
        if ($name === '' || strlen($name) > 160 || strlen($organisation) > 190 || strlen($phone) > 40 || ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country))) {
            $error = 'Check the contact name, organisation, phone number and country code.';
        } else {
            $stmt = customer_db()->prepare('UPDATE platform_customers SET contact_name=?,organisation_name=?,phone=?,country_code=? WHERE id=?');
            $stmt->bind_param('ssssi', $name, $organisation, $phone, $country, $customer['customer_id']);
            $stmt->execute();
            $notice = 'Your profile has been updated.';
        }
    } elseif ($action === 'password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $stmt = customer_db()->prepare('SELECT password_hash FROM platform_customer_accounts WHERE id=?');
        $stmt->bind_param('i', $customer['account_id']);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 14) {
            $error = 'Use a new password with at least 14 characters.';
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $error = 'The new passwords do not match.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = customer_db()->prepare('UPDATE platform_customer_accounts SET password_hash=? WHERE id=?');
            $stmt->bind_param('si', $hash, $customer['account_id']);
            $stmt->execute();
            $stmt = customer_db()->prepare('SELECT updated_at FROM platform_customer_accounts WHERE id=?');
            $stmt->bind_param('i', $customer['account_id']);
            $stmt->execute();
            $version = $stmt->get_result()->fetch_assoc()['updated_at'];
            session_regenerate_id(true);
            $_SESSION['customer']['account_updated_at'] = $version;
            $notice = 'Your password has been changed. Other active sessions will be required to sign in again.';
        }
    }
}
$stmt = customer_db()->prepare('SELECT email,contact_name,organisation_name,phone,country_code FROM platform_customers WHERE id=?');
$stmt->bind_param('i', $customer['customer_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account profile · SmartNow</title><style>*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#172033;font-family:system-ui,sans-serif}.wrap{max-width:760px;margin:5vh auto;padding:20px}.panel{background:white;border:1px solid #e5e9f0;border-radius:14px;padding:24px;margin-bottom:18px}label{display:block;font-size:13px;font-weight:650;margin:14px 0 6px}input{width:100%;padding:11px;border:1px solid #cfd6e2;border-radius:8px;font:inherit}.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.btn{margin-top:18px;padding:11px 16px;background:#2457d6;color:white;border:0;border-radius:8px;font-weight:700}.notice{padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin-bottom:14px}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}a{color:#2457d6}@media(max-width:560px){.grid{grid-template-columns:1fr}.wrap{padding:12px}.panel{padding:18px}}</style></head><body><div class="wrap"><p><a href="customer-dashboard.php">← My account</a></p><h1>Account profile</h1><?php if ($notice): ?><div class="notice"><?= customer_e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= customer_e($error) ?></div><?php endif; ?>
<section class="panel"><h2>Contact details</h2><p>Email address: <strong><?= customer_e($profile['email']) ?></strong> <span style="color:#64748b">(verified; contact support to change it)</span></p><form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><input type="hidden" name="action" value="profile"><div class="grid"><div><label for="contact_name">Your name</label><input id="contact_name" name="contact_name" maxlength="160" required value="<?= customer_e($profile['contact_name']) ?>"></div><div><label for="organisation_name">Organisation</label><input id="organisation_name" name="organisation_name" maxlength="190" value="<?= customer_e($profile['organisation_name']) ?>"></div><div><label for="phone">Phone number</label><input id="phone" name="phone" maxlength="40" value="<?= customer_e($profile['phone']) ?>"></div><div><label for="country_code">Country code (ISO 2-letter)</label><input id="country_code" name="country_code" maxlength="2" pattern="[A-Za-z]{2}" value="<?= customer_e($profile['country_code']) ?>"></div></div><button class="btn" type="submit">Save contact details</button></form></section>
<section class="panel"><h2>Change password</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><input type="hidden" name="action" value="password"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required><div class="grid"><div><label for="new_password">New password (14+ characters)</label><input id="new_password" name="new_password" type="password" minlength="14" autocomplete="new-password" required></div><div><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" minlength="14" autocomplete="new-password" required></div></div><button class="btn" type="submit">Change password</button></form></section></div></body></html>
