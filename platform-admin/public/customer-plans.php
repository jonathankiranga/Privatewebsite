<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
require_customer();
$customer = customer_current();
$customerId = (int)$customer['customer_id'];
$error = '';
$notice = '';
if (isset($_GET['requested'])) $notice = 'Your plan enquiry has been sent. This is not a paid subscription and does not activate a product licence.';
if (isset($_GET['existing'])) $notice = 'You already have an open enquiry for that plan. View its status below.';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    customer_verify_csrf();
    $planId = (int)($_POST['plan_id'] ?? 0);
    $message = trim((string)($_POST['message'] ?? ''));
    if ($planId < 1 || strlen($message) > 5000) {
        $error = 'Choose a valid plan and keep your message within 5,000 characters.';
    } else {
        $stmt = customer_db()->prepare('SELECT id,name FROM platform_subscription_plans WHERE id=? AND is_published=1');
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $plan = $stmt->get_result()->fetch_assoc();
        if (!$plan) {
            $error = 'That plan is no longer available.';
        } else {
            $stmt = customer_db()->prepare("SELECT id FROM platform_subscription_enquiries WHERE customer_id=? AND plan_id=? AND status IN ('new','contacted','quoted','quote_accepted') LIMIT 1");
            $stmt->bind_param('ii', $customerId, $planId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                customer_redirect('customer-plans.php?existing=1');
            }
            $stmt = customer_db()->prepare('INSERT INTO platform_subscription_enquiries (customer_id,plan_id,request_message) VALUES (?,?,?)');
            $stmt->bind_param('iis', $customerId, $planId, $message);
            $stmt->execute();
            $enquiryId = (int)customer_db()->insert_id;
            $from = trim((string)getenv('SMARTNOW_MAIL_FROM'));
            $to = trim((string)getenv('SMARTNOW_SUPPORT_EMAIL'));
            $base = rtrim((string)getenv('SMARTNOW_CUSTOMER_PORTAL_URL'), '/');
            if (filter_var($from, FILTER_VALIDATE_EMAIL) && filter_var($to, FILTER_VALIDATE_EMAIL) && filter_var($base, FILTER_VALIDATE_URL) && strtolower((string)parse_url($base, PHP_URL_SCHEME)) === 'https') {
                $body = "A customer has requested plan information.\n\nEnquiry #".$enquiryId."\nCustomer: ".$customer['email']."\nPlan: ".$plan['name']."\nMessage: ".$message."\n\nReview it in the administration area:\n".$base.'/enquiries.php';
                $headers = "From: SmartNow Support <".$from.">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                if (!mail($to, '=?UTF-8?B?'.base64_encode('SmartNow plan enquiry #'.$enquiryId).'?=', $body, $headers)) error_log('Plan enquiry notification failed for #'.$enquiryId);
            }
            customer_redirect('customer-plans.php?requested=1');
        }
    }
}
$plans = customer_db()->query("SELECT p.id,p.name,p.summary,p.features,p.price,p.currency,p.billing_interval,pr.name AS product_name FROM platform_subscription_plans p INNER JOIN platform_products pr ON pr.id=p.product_id WHERE p.is_published=1 AND pr.status='published' ORDER BY pr.name,p.price");
$stmt = customer_db()->prepare('SELECT e.id,e.status,e.created_at,p.name AS plan_name,pr.name AS product_name FROM platform_subscription_enquiries e INNER JOIN platform_subscription_plans p ON p.id=e.plan_id INNER JOIN platform_products pr ON pr.id=p.product_id WHERE e.customer_id=? ORDER BY e.created_at DESC LIMIT 50');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$enquiries = $stmt->get_result();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Plans and subscriptions · SmartNow</title><style>*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#172033;font-family:Inter,system-ui,sans-serif}.wrap{max-width:1100px;margin:4vh auto;padding:20px}.top{display:flex;justify-content:space-between;margin-bottom:18px}.top a{color:#2864e8}.intro,.plan,.panel{background:white;border:1px solid #e5e9f0;border-radius:14px;padding:22px;margin-bottom:16px}.intro{background:#12223a;color:white}.intro p{color:#c4cfde}.plans{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.plan h2{margin:8px 0}.muted{color:#64748b;font-size:14px}.price{font-size:25px;font-weight:800;margin:16px 0}.tag{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#2864e8;font-weight:800}.features{white-space:pre-wrap;line-height:1.7;color:#4d5b70}.btn{padding:11px 16px;background:#2864e8;color:white;border:0;border-radius:8px;font-weight:700;cursor:pointer;margin-top:12px}.notice{padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin:14px 0}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}textarea{width:100%;min-height:75px;padding:10px;border:1px solid #cfd6e2;border-radius:8px;font:inherit}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #edf0f4;font-size:14px}th{color:#64748b;font-size:12px}@media(max-width:720px){.plans{grid-template-columns:1fr}.wrap{padding:12px}.top{gap:12px;flex-wrap:wrap}table{display:block;overflow:auto}}</style></head><body><div class="wrap"><div class="top"><strong>SmartNow customer portal</strong><a href="customer-dashboard.php">← My account</a></div>
<section class="intro"><h1>Plans and product enquiries</h1><p>Review published plan information and ask the SmartNow team to contact you. Sending an enquiry does not create a subscription, take payment or activate product access.</p></section>
<?php if ($notice): ?><div class="notice"><?= customer_e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= customer_e($error) ?></div><?php endif; ?>
<?php if ($plans->num_rows): ?><div class="plans"><?php while ($plan=$plans->fetch_assoc()): ?><article class="plan"><span class="tag"><?= customer_e($plan['product_name']) ?></span><h2><?= customer_e($plan['name']) ?></h2><p class="muted"><?= customer_e($plan['summary']) ?></p><div class="price"><?= customer_e($plan['currency']) ?> <?= number_format((float)$plan['price'],2) ?> <span class="muted">/ <?= customer_e(str_replace('_',' ',$plan['billing_interval'])) ?></span></div><div class="features"><?= customer_e($plan['features']) ?></div><form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>"><label for="message-<?= (int)$plan['id'] ?>">Message (optional)</label><textarea id="message-<?= (int)$plan['id'] ?>" name="message" maxlength="5000" placeholder="Tell us about your organisation or deployment requirements."></textarea><button class="btn" type="submit">Request plan details</button></form></article><?php endwhile; ?></div><?php else: ?><div class="panel">No plans have been published yet. Please check again later or submit a support request.</div><?php endif; ?>
<section class="panel"><h2>Your plan enquiries</h2><table><thead><tr><th>Product / Plan</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php if ($enquiries->num_rows): while ($e=$enquiries->fetch_assoc()): ?><tr><td><?= customer_e($e['product_name']) ?> · <?= customer_e($e['plan_name']) ?></td><td><?= customer_e(str_replace('_',' ',ucfirst($e['status']))) ?></td><td><?= customer_e($e['created_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="3" class="muted">You have not submitted a plan enquiry yet.</td></tr><?php endif; ?></tbody></table></section>
</div></body></html>
