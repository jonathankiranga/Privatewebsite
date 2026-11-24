<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
require_customer();

function notify_support_team(int $ticketId, string $subject, string $message, string $customerEmail): void
{
    $from = trim((string)getenv('SMARTNOW_MAIL_FROM'));
    $to = trim((string)getenv('SMARTNOW_SUPPORT_EMAIL'));
    $base = rtrim((string)getenv('SMARTNOW_CUSTOMER_PORTAL_URL'), '/');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($base, FILTER_VALIDATE_URL) || strtolower((string)parse_url($base, PHP_URL_SCHEME)) !== 'https') {
        error_log('Support team notification skipped: mail configuration is incomplete.');
        return;
    }
    $body = "A customer has updated support request #".$ticketId." - ".$subject."\n\nCustomer: ".$customerEmail."\n\n".$message."\n\nOpen the request in the administration area:\n".$base.'/tickets.php?ticket='.$ticketId."\n";
    if (!customer_send_mail($to, 'Support request #'.$ticketId, $body)) {
        error_log('Support team SMTP notification failed for ticket #'.$ticketId);
    }
}

$customer = customer_current();
$customerId = (int)$customer['customer_id'];
$error = '';
$notice = '';
$ticketId = (int)($_GET['ticket'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    customer_verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $type = (string)($_POST['ticket_type'] ?? 'support');
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($type === 'product_request') {
            $requestedProduct = trim((string)($_POST['requested_product'] ?? ''));
            $businessNeed = trim((string)($_POST['business_need'] ?? ''));
            $requirements = trim((string)($_POST['requirements'] ?? ''));
            $intendedUsers = trim((string)($_POST['intended_users'] ?? ''));
            $budget = trim((string)($_POST['budget'] ?? ''));
            if ($requestedProduct === '' || strlen($requestedProduct) > 160 || $businessNeed === '' || strlen($businessNeed) > 5000 || strlen($requirements) > 10000 || strlen($intendedUsers) > 1000 || strlen($budget) > 120) {
                $error = 'Enter the proposed product name and business need. Check the field lengths.';
            } else {
                $subject = 'New product request: '.$requestedProduct;
                $description = "Proposed product: ".$requestedProduct."\n\nBusiness need:\n".$businessNeed."\n\nKey requirements:\n".($requirements !== '' ? $requirements : 'Not specified')."\n\nIntended users:\n".($intendedUsers !== '' ? $intendedUsers : 'Not specified')."\n\nIndicative budget:\n".($budget !== '' ? $budget : 'Not specified');
                $db = customer_db();
                try {
                    $contact = $db->prepare('SELECT contact_name,phone,organisation_name,email FROM platform_customers WHERE id=?');
                    $contact->bind_param('i', $customerId);
                    $contact->execute();
                    $profile = $contact->get_result()->fetch_assoc();
                    if (!$profile) throw new RuntimeException('Customer profile was not found.');
                    $name = trim((string)$profile['contact_name']);
                    if ($name === '') $name = (string)$profile['email'];
                    $email = (string)$profile['email'];
                    $phone = (string)($profile['phone'] ?? '');
                    $organisation = (string)($profile['organisation_name'] ?? '');
                    $source = 'customer_support';
                    $db->begin_transaction();
                    $stmt = $db->prepare("INSERT INTO platform_leads (customer_id,name,email,phone,organisation,product_id,source,subject,message,status,priority) VALUES (?,?,?,?,?,NULL,?,?,?,'new','normal')");
                    $stmt->bind_param('isssssss', $customerId, $name, $email, $phone, $organisation, $source, $subject, $description);
                    $stmt->execute();
                    $leadId = (int)$db->insert_id;
                    $activitySubject = 'New product request received through customer support';
                    $activityDetails = 'Submitted by existing customer '.$email.'.';
                    $stmt = $db->prepare("INSERT INTO platform_lead_activities (lead_id,activity_type,subject,details) VALUES (?,'note',?,?)");
                    $stmt->bind_param('iss', $leadId, $activitySubject, $activityDetails);
                    $stmt->execute();
                    $db->commit();
                    $supportEmail = trim((string)getenv('SMARTNOW_SUPPORT_EMAIL'));
                    if (filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
                        customer_send_mail($supportEmail, 'New product request lead #'.$leadId, "A customer has requested a new product.\n\nCustomer: ".$name."\nEmail: ".$email."\nOrganisation: ".$organisation."\n\n".$description."\n\nReview this lead in the administration portal.");
                    }
                    customer_redirect('customer-support.php?product_request=1');
                } catch (Throwable $ex) {
                    $db->rollback();
                    error_log('Customer product request capture failed: '.$ex->getMessage());
                    $error = 'Your product request could not be submitted. Please try again.';
                }
            }
        } elseif ($subject === '' || strlen($subject) > 240 || $description === '' || strlen($description) > 20000 || !in_array($type, ['support','bug','feature_request'], true) || ($type === 'bug' && $productId < 1)) {
            $error = 'Enter a subject and description, choose a valid request type and select the affected product for bug reports.';
        } else {
            $productIdForDb = null;
            if ($type === 'bug') {
                $productCheck = customer_db()->prepare("SELECT id FROM platform_products WHERE id=? AND status='published'");
                $productCheck->bind_param('i', $productId);
                $productCheck->execute();
                if (!$productCheck->get_result()->fetch_assoc()) $error = 'Choose a valid product from the available product list.';
                else $productIdForDb = $productId;
            }
            if ($error === '') {
                $db = customer_db();
                try {
                    $db->begin_transaction();
                    $priority = 'normal';
                    $status = 'open';
                    $email = (string)$customer['email'];
                    $stmt = $db->prepare('INSERT INTO platform_support_tickets (customer_id,ticket_type,requester_email,product_id,subject,description,priority,status) VALUES (?,?,?,?,?,?,?,?)');
                    $stmt->bind_param('ississss', $customerId, $type, $email, $productIdForDb, $subject, $description, $priority, $status);
                    $stmt->execute();
                    $newTicketId = (int)$db->insert_id;
                    $authorType = 'customer';
                    $internal = 0;
                    $stmt = $db->prepare('INSERT INTO platform_ticket_messages (ticket_id,author_type,message,is_internal) VALUES (?,?,?,?)');
                    $stmt->bind_param('issi', $newTicketId, $authorType, $description, $internal);
                    $stmt->execute();
                    $db->commit();
                    notify_support_team($newTicketId, $subject, $description, (string)$customer['email']);
                    customer_redirect('customer-support.php?ticket='.$newTicketId.'&created=1');
                } catch (Throwable $ex) {
                    $db->rollback();
                    error_log('Customer support ticket creation failed: '.$ex->getMessage());
                    $error = 'Your request could not be submitted. Please try again.';
                }
            }
        }
    } elseif ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim((string)($_POST['message'] ?? ''));
        if ($ticketId < 1 || $message === '' || strlen($message) > 20000) {
            $error = 'Enter a valid reply.';
        } else {
            $stmt = customer_db()->prepare('SELECT id FROM platform_support_tickets WHERE id=? AND customer_id=?');
            $stmt->bind_param('ii', $ticketId, $customerId);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $error = 'The support request could not be found.';
            } else {
                $db = customer_db();
                try {
                    $db->begin_transaction();
                    $authorType = 'customer';
                    $internal = 0;
                    $stmt = $db->prepare('INSERT INTO platform_ticket_messages (ticket_id,author_type,message,is_internal) VALUES (?,?,?,?)');
                    $stmt->bind_param('issi', $ticketId, $authorType, $message, $internal);
                    $stmt->execute();
                    $stmt = $db->prepare("UPDATE platform_support_tickets SET status=IF(status IN ('waiting_customer','resolved','closed'),'open',status) WHERE id=? AND customer_id=?");
                    $stmt->bind_param('ii', $ticketId, $customerId);
                    $stmt->execute();
                    $db->commit();
                    $ticketSubject = customer_db()->prepare('SELECT subject FROM platform_support_tickets WHERE id=?');
                    $ticketSubject->bind_param('i', $ticketId);
                    $ticketSubject->execute();
                    $subjectRow = $ticketSubject->get_result()->fetch_assoc();
                    notify_support_team($ticketId, $subjectRow ? $subjectRow['subject'] : 'Customer reply', $message, (string)$customer['email']);
                    customer_redirect('customer-support.php?ticket='.$ticketId.'&replied=1');
                } catch (Throwable $ex) {
                    $db->rollback();
                    error_log('Customer support reply failed: '.$ex->getMessage());
                    $error = 'Your reply could not be saved. Please try again.';
                }
            }
        }
    }
}
if (isset($_GET['created'])) $notice = 'Your support request has been submitted.';
if (isset($_GET['product_request'])) $notice = 'Thank you. Your new product request has been sent to our product and sales team.';
if (isset($_GET['replied'])) $notice = 'Your reply has been added.';
$selected = null;
$messages = null;
if ($ticketId > 0) {
    $stmt = customer_db()->prepare('SELECT t.id,t.subject,t.description,t.ticket_type,t.status,t.priority,t.created_at,t.updated_at,p.name AS product_name FROM platform_support_tickets t LEFT JOIN platform_products p ON p.id=t.product_id WHERE t.id=? AND t.customer_id=?');
    $stmt->bind_param('ii', $ticketId, $customerId);
    $stmt->execute();
    $selected = $stmt->get_result()->fetch_assoc() ?: null;
    if ($selected) {
        $stmt = customer_db()->prepare('SELECT author_type,message,created_at FROM platform_ticket_messages WHERE ticket_id=? AND is_internal=0 ORDER BY id ASC');
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $messages = $stmt->get_result();
    }
}
$stmt = customer_db()->prepare('SELECT id,subject,ticket_type,status,priority,created_at,updated_at FROM platform_support_tickets WHERE customer_id=? ORDER BY updated_at DESC LIMIT 100');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$tickets = $stmt->get_result();
$products = customer_db()->query("SELECT id,name FROM platform_products WHERE status='published' ORDER BY name");
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Support centre · SmartNow</title><style>*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#172033;font-family:Inter,system-ui,sans-serif}.wrap{max-width:1100px;margin:4vh auto;padding:20px}.panel{background:white;border:1px solid #e5e9f0;border-radius:14px;padding:22px;margin-bottom:18px}h1{margin:0 0 8px}.muted{color:#64748b;font-size:14px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}label{display:block;font-size:13px;font-weight:650;margin:14px 0 6px}input,select,textarea{width:100%;padding:11px;border:1px solid #cfd6e2;border-radius:8px;font:inherit}textarea{min-height:130px}.btn{display:inline-block;padding:10px 15px;background:#2457d6;color:white;border:0;border-radius:8px;text-decoration:none;font-weight:650;cursor:pointer;margin-top:14px}.secondary{background:#eaf0ff;color:#2149ad}.notice{padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin-bottom:14px}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #edf0f4;font-size:14px}th{color:#64748b;font-size:12px}.message{padding:14px 0;border-bottom:1px solid #edf0f4;white-space:pre-wrap}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.top a{color:#2457d6}@media(max-width:760px){.grid{grid-template-columns:1fr}.wrap{padding:12px}.panel{padding:16px}table{display:block;overflow:auto}}</style></head><body><div class="wrap">
<div class="top"><div><h1>Support centre</h1><div class="muted"><?= customer_e($customer['email']) ?></div></div><a href="customer-dashboard.php">My account</a></div>
<?php if ($notice): ?><div class="notice"><?= customer_e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= customer_e($error) ?></div><?php endif; ?>
<div class="grid"><section class="panel"><h2>Open a support request</h2><p class="muted">Report a problem, ask for help or suggest an improvement. Do not include passwords or payment-card details.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><input type="hidden" name="action" value="create">
<label for="ticket_type">What would you like to do?</label><select id="ticket_type" name="ticket_type"><option value="support">Ask a support question</option><option value="bug">Report a bug</option><option value="feature_request">Request an improvement</option><option value="product_request">Request a new product</option></select>
<div id="existing-product-fields" hidden><label for="product_id">Which product has the problem? *</label><select id="product_id" name="product_id"><option value="">Select the affected product</option><?php while ($p=$products->fetch_assoc()): ?><option value="<?= (int)$p['id'] ?>"><?= customer_e($p['name']) ?></option><?php endwhile; ?></select></div>
<div id="standard-fields"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="240"><label for="description">Describe your request</label><textarea id="description" name="description" maxlength="20000"></textarea></div>
<div id="new-product-fields" hidden><label for="requested_product">What product would you like us to develop? *</label><input id="requested_product" name="requested_product" maxlength="160"><label for="business_need">What business problem should it solve? *</label><textarea id="business_need" name="business_need" maxlength="5000"></textarea><label for="requirements">Key features or requirements</label><textarea id="requirements" name="requirements" maxlength="10000"></textarea><label for="intended_users">Who will use this product?</label><input id="intended_users" name="intended_users" maxlength="1000"><label for="budget">Indicative budget (optional)</label><input id="budget" name="budget" maxlength="120" placeholder="e.g. KES 100,000 or not yet decided"></div>
<button class="btn" type="submit">Submit request</button></form>
<script>
(function(){
 var type=document.getElementById('ticket_type'), product=document.getElementById('existing-product-fields'), standard=document.getElementById('standard-fields'), newProduct=document.getElementById('new-product-fields');
 var productSelect=document.getElementById('product_id'), subject=document.getElementById('subject'), description=document.getElementById('description'), requested=document.getElementById('requested_product'), need=document.getElementById('business_need');
 function update(){var bug=type.value==='bug', fresh=type.value==='product_request';product.hidden=!bug;standard.hidden=fresh;newProduct.hidden=!fresh;productSelect.required=bug;subject.required=!fresh;description.required=!fresh;requested.required=fresh;need.required=fresh;}
 type.addEventListener('change',update);update();
})();
</script></section>
<section class="panel"><h2>Your support requests</h2><table><thead><tr><th>Request</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php if ($tickets->num_rows): while ($t=$tickets->fetch_assoc()): ?><tr><td><a href="customer-support.php?ticket=<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> · <?= customer_e($t['subject']) ?></a><div class="muted"><?= customer_e(str_replace('_',' ',ucfirst($t['ticket_type']))) ?></div></td><td><?= customer_e(str_replace('_',' ',ucfirst($t['status']))) ?></td><td><?= customer_e($t['updated_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="3" class="muted">You have not submitted a support request yet.</td></tr><?php endif; ?></tbody></table></section></div>
<?php if ($selected): ?><section class="panel"><h2>#<?= (int)$selected['id'] ?> · <?= customer_e($selected['subject']) ?></h2><p class="muted">Status: <?= customer_e(str_replace('_',' ',ucfirst($selected['status']))) ?> · Priority: <?= customer_e(ucfirst($selected['priority'])) ?><?php if (!empty($selected['product_name'])): ?> · Product: <?= customer_e($selected['product_name']) ?><?php endif; ?></p>
<?php if ($messages && $messages->num_rows): while ($m=$messages->fetch_assoc()): ?><div class="message"><strong><?= customer_e(ucfirst($m['author_type'])) ?> · <?= customer_e($m['created_at']) ?></strong><p><?= customer_e($m['message']) ?></p></div><?php endwhile; endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= (int)$selected['id'] ?>"><label for="message">Add a reply</label><textarea id="message" name="message" maxlength="20000" required></textarea><button class="btn" type="submit">Send reply</button></form></section><?php elseif ($ticketId > 0): ?><div class="notice error">That support request was not found in your account.</div><?php endif; ?>
</div></body></html>
