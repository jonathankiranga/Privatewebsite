<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator']);

function notify_plan_enquiry_customer(string $email, int $enquiryId, string $status): void
{
    $from = trim((string)getenv('SMARTNOW_MAIL_FROM'));
    $base = rtrim((string)getenv('SMARTNOW_CUSTOMER_PORTAL_URL'), '/');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($base, FILTER_VALIDATE_URL) || strtolower((string)parse_url($base, PHP_URL_SCHEME)) !== 'https') return;
    $body = "There is an update to your SmartNow plan enquiry #".$enquiryId.": ".str_replace('_',' ',$status).".\n\nSign in to view your plan enquiry:\n".$base.'/customer-plans.php';
    $headers = "From: SmartNow Support <".$from.">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    if (!mail($email, '=?UTF-8?B?'.base64_encode('SmartNow plan enquiry update').'?=', $body, $headers)) error_log('Plan enquiry status email failed for #'.$enquiryId);
}

$error = '';
$notice = '';
$enquiryId = (int)($_GET['enquiry'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $enquiryId = (int)($_POST['enquiry_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $notes = trim((string)($_POST['admin_notes'] ?? ''));
    $assignedInput = trim((string)($_POST['assigned_admin_id'] ?? ''));
    $assignedId = $assignedInput === '' ? null : (int)$assignedInput;
    if ($enquiryId < 1 || !in_array($status, ['new','contacted','quoted','quote_accepted','declined','closed'], true) || strlen($notes) > 20000 || ($assignedInput !== '' && (!ctype_digit($assignedInput) || (int)$assignedInput < 1))) {
        $error = 'Check the enquiry, status, notes and assignment.';
    } else {
        if ($assignedId !== null) {
            $check = db()->prepare('SELECT id FROM admin_users WHERE id=? AND is_active=1');
            $check->bind_param('i', $assignedId);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) $error = 'The selected administrator is not active.';
        }
        if ($error === '') {
            $before = db()->prepare('SELECT e.status,c.email FROM platform_subscription_enquiries e INNER JOIN platform_customers c ON c.id=e.customer_id WHERE e.id=?');
            $before->bind_param('i', $enquiryId);
            $before->execute();
            $previous = $before->get_result()->fetch_assoc();
            if (!$previous) {
                $error = 'The enquiry could not be found.';
            } else {
                $stmt = db()->prepare('UPDATE platform_subscription_enquiries SET status=?,admin_notes=?,assigned_admin_id=? WHERE id=?');
                $stmt->bind_param('sssi', $status, $notes, $assignedId, $enquiryId);
                $stmt->execute();
                audit_event('plan_enquiry.updated', 'subscription_enquiry', $enquiryId, ['status'=>$status,'assigned_admin_id'=>$assignedId]);
                if ($previous['status'] !== $status) notify_plan_enquiry_customer($previous['email'], $enquiryId, $status);
                redirect('enquiries.php?enquiry='.$enquiryId.'&saved=1');
            }
        }
    }
}
if (isset($_GET['saved'])) $notice = 'Enquiry details saved.';
$selected = null;
$activeAdmins = db()->query('SELECT id,display_name FROM admin_users WHERE is_active=1 ORDER BY display_name');
if ($enquiryId > 0) {
    $stmt = db()->prepare('SELECT e.*,c.email,c.contact_name,c.organisation_name,p.name AS plan_name,p.price,p.currency,p.billing_interval,pr.name AS product_name,a.display_name AS assigned_admin_name FROM platform_subscription_enquiries e INNER JOIN platform_customers c ON c.id=e.customer_id INNER JOIN platform_subscription_plans p ON p.id=e.plan_id INNER JOIN platform_products pr ON pr.id=p.product_id LEFT JOIN admin_users a ON a.id=e.assigned_admin_id WHERE e.id=?');
    $stmt->bind_param('i', $enquiryId);
    $stmt->execute();
    $selected = $stmt->get_result()->fetch_assoc() ?: null;
}
$statusFilter = (string)($_GET['status'] ?? '');
$valid = ['new','contacted','quoted','quote_accepted','declined','closed'];
if (in_array($statusFilter, $valid, true)) {
    $stmt = db()->prepare('SELECT e.id,e.status,e.created_at,c.email,p.name AS plan_name,pr.name AS product_name,a.display_name AS assigned_admin_name FROM platform_subscription_enquiries e INNER JOIN platform_customers c ON c.id=e.customer_id INNER JOIN platform_subscription_plans p ON p.id=e.plan_id INNER JOIN platform_products pr ON pr.id=p.product_id LEFT JOIN admin_users a ON a.id=e.assigned_admin_id WHERE e.status=? ORDER BY e.updated_at DESC LIMIT 250');
    $stmt->bind_param('s', $statusFilter);
    $stmt->execute();
    $enquiries = $stmt->get_result();
} else {
    $enquiries = db()->query('SELECT e.id,e.status,e.created_at,c.email,p.name AS plan_name,pr.name AS product_name,a.display_name AS assigned_admin_name FROM platform_subscription_enquiries e INNER JOIN platform_customers c ON c.id=e.customer_id INNER JOIN platform_subscription_plans p ON p.id=e.plan_id INNER JOIN platform_products pr ON pr.id=p.product_id LEFT JOIN admin_users a ON a.id=e.assigned_admin_id ORDER BY e.updated_at DESC LIMIT 250');
}
page_header('Plan enquiries');
?>
<div class="eyebrow">Sales pipeline</div><h1 class="heading">Plan enquiries</h1><p class="sub">Follow up customer interest in published plans. An enquiry is not a subscription, payment or licence activation.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel"><div class="actions" style="justify-content:space-between"><h2>Enquiry queue</h2><form method="get" class="actions" style="margin:0"><select name="status" style="width:200px"><option value="">All statuses</option><?php foreach (['new'=>'New','contacted'=>'Contacted','quoted'=>'Quoted','quote_accepted'=>'Quote accepted','declined'=>'Declined','closed'=>'Closed'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= $statusFilter===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn secondary">Filter</button></form></div>
<table><thead><tr><th>Customer</th><th>Product / Plan</th><th>Status</th><th>Assigned</th><th>Submitted</th></tr></thead><tbody><?php if ($enquiries->num_rows): while ($e=$enquiries->fetch_assoc()): ?><tr><td><?= e($e['email']) ?></td><td><a href="enquiries.php?enquiry=<?= (int)$e['id'] ?>"><?= e($e['product_name']) ?> · <?= e($e['plan_name']) ?></a></td><td><?= e(str_replace('_',' ',ucfirst($e['status']))) ?></td><td><?= e($e['assigned_admin_name'] ?: 'Unassigned') ?></td><td><?= e($e['created_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="empty">No plan enquiries found.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($selected): ?><div class="panel"><h2>Enquiry #<?= (int)$selected['id'] ?></h2><p><strong><?= e($selected['contact_name'] ?: $selected['email']) ?></strong> · <?= e($selected['email']) ?> · <?= e($selected['organisation_name'] ?: 'No organisation supplied') ?></p><p><strong><?= e($selected['product_name']) ?> — <?= e($selected['plan_name']) ?></strong></p><p><?= e($selected['currency']) ?> <?= number_format((float)$selected['price'],2) ?> / <?= e(str_replace('_',' ',$selected['billing_interval'])) ?></p><p><?= nl2br(e($selected['request_message'] ?: 'No additional message supplied.')) ?></p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="enquiry_id" value="<?= (int)$selected['id'] ?>"><div class="form-grid"><div><label>Status</label><select name="status"><?php foreach (['new'=>'New','contacted'=>'Contacted','quoted'=>'Quoted','quote_accepted'=>'Quote accepted (not activated)','declined'=>'Declined','closed'=>'Closed'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= $selected['status']===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div><label>Assigned administrator</label><select name="assigned_admin_id"><option value="">Unassigned</option><?php while ($a=$activeAdmins->fetch_assoc()): ?><option value="<?= (int)$a['id'] ?>" <?= (int)($selected['assigned_admin_id'] ?? 0)===(int)$a['id']?'selected':'' ?>><?= e($a['display_name']) ?></option><?php endwhile; ?></select></div><div class="wide"><label>Internal notes</label><textarea name="admin_notes" rows="5" maxlength="20000"><?= e($selected['admin_notes'] ?? '') ?></textarea></div></div><button class="btn" type="submit">Save enquiry</button></form>
<p class="notice" style="margin-top:18px">The quote acceptance status records customer interest only. Payment confirmation, subscriptions and application access provisioning are not implemented.</p></div><?php elseif ($enquiryId > 0): ?><div class="notice error">That enquiry does not exist.</div><?php endif; ?>
<?php page_footer(); ?>
