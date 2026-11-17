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
    $body = "A customer has updated support request #".$ticketId." - ".$subject."\\n\\nCustomer: ".$customerEmail."\\n\\n".$message."\\n\\nOpen the request in the administration area:\\n".$base.'/tickets.php?ticket='.$ticketId."\\n";
    $headers = "From: SmartNow Support <".$from.">\\r\\nContent-Type: text/plain; charset=UTF-8\\r\\n";
    if (!mail($to, '=?UTF-8?B?'.base64_encode('Support request #'.$ticketId).'?=', $body, $headers)) {
        error_log('Support team notification email could not be sent for ticket #'.$ticketId);
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
        if ($subject === '' || strlen($subject) > 240 || $description === '' || strlen($description) > 20000 || !in_array($type, ['support','bug','feature_request'], true)) {
            $error = 'Enter a subject and description, and choose a valid request type.';
        } else {
            $db = customer_db();
            try {
                $db->begin_transaction();
                $priority = 'normal';
                $status = 'open';
                $email = (string)$customer['email'];
                $stmt = $db->prepare('INSERT INTO platform_support_tickets (customer_id,ticket_type,requester_email,subject,description,priority,status) VALUES (?,?,?,?,?,?,?)');
                $stmt->bind_param('issssss', $customerId, $type, $email, $subject, $description, $priority, $status);
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
                    $stmt = $db->prepare("UPDATE platform_support_tickets SET status=IF(status='waiting_customer','open',status) WHERE id=? AND customer_id=?");
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
if (isset($_GET['replied'])) $notice = 'Your reply has been added.';
$selected = null;
$messages = null;
if ($ticketId > 0) {
    $stmt = customer_db()->prepare('SELECT id,subject,description,ticket_type,status,priority,created_at,updated_at FROM platform_support_tickets WHERE id=? AND customer_id=?');
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
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Support centre · SmartNow</title><style>*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#172033;font-family:Inter,system-ui,sans-serif}.wrap{max-width:1100px;margin:4vh auto;padding:20px}.panel{background:white;border:1px solid #e5e9f0;border-radius:14px;padding:22px;margin-bottom:18px}h1{margin:0 0 8px}.muted{color:#64748b;font-size:14px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}label{display:block;font-size:13px;font-weight:650;margin:14px 0 6px}input,select,textarea{width:100%;padding:11px;border:1px solid #cfd6e2;border-radius:8px;font:inherit}textarea{min-height:130px}.btn{display:inline-block;padding:10px 15px;background:#2457d6;color:white;border:0;border-radius:8px;text-decoration:none;font-weight:650;cursor:pointer;margin-top:14px}.secondary{background:#eaf0ff;color:#2149ad}.notice{padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin-bottom:14px}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #edf0f4;font-size:14px}th{color:#64748b;font-size:12px}.message{padding:14px 0;border-bottom:1px solid #edf0f4;white-space:pre-wrap}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.top a{color:#2457d6}@media(max-width:760px){.grid{grid-template-columns:1fr}.wrap{padding:12px}.panel{padding:16px}table{display:block;overflow:auto}}</style></head><body><div class="wrap">
<div class="top"><div><h1>Support centre</h1><div class="muted"><?= customer_e($customer['email']) ?></div></div><a href="customer-dashboard.php">My account</a></div>
<?php if ($notice): ?><div class="notice"><?= customer_e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= customer_e($error) ?></div><?php endif; ?>
<div class="grid"><section class="panel"><h2>Open a support request</h2><p class="muted">Report a problem, ask for help or suggest an improvement. Do not include passwords or payment-card details.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><input type="hidden" name="action" value="create">
<label for="ticket_type">Request type</label><select id="ticket_type" name="ticket_type"><option value="support">Support question</option><option value="bug">Report a bug</option><option value="feature_request">Suggest an improvement</option></select>
<label for="subject">Subject</label><input id="subject" name="subject" maxlength="240" required>
<label for="description">Describe your request</label><textarea id="description" name="description" maxlength="20000" required></textarea>
<button class="btn" type="submit">Submit request</button></form></section>
<section class="panel"><h2>Your support requests</h2><table><thead><tr><th>Request</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php if ($tickets->num_rows): while ($t=$tickets->fetch_assoc()): ?><tr><td><a href="customer-support.php?ticket=<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> · <?= customer_e($t['subject']) ?></a><div class="muted"><?= customer_e(str_replace('_',' ',ucfirst($t['ticket_type']))) ?></div></td><td><?= customer_e(str_replace('_',' ',ucfirst($t['status']))) ?></td><td><?= customer_e($t['updated_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="3" class="muted">You have not submitted a support request yet.</td></tr><?php endif; ?></tbody></table></section></div>
<?php if ($selected): ?><section class="panel"><h2>#<?= (int)$selected['id'] ?> · <?= customer_e($selected['subject']) ?></h2><p class="muted">Status: <?= customer_e(str_replace('_',' ',ucfirst($selected['status']))) ?> · Priority: <?= customer_e(ucfirst($selected['priority'])) ?></p>
<?php if ($messages && $messages->num_rows): while ($m=$messages->fetch_assoc()): ?><div class="message"><strong><?= customer_e(ucfirst($m['author_type'])) ?> · <?= customer_e($m['created_at']) ?></strong><p><?= customer_e($m['message']) ?></p></div><?php endwhile; endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= customer_e(customer_csrf_token()) ?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= (int)$selected['id'] ?>"><label for="message">Add a reply</label><textarea id="message" name="message" maxlength="20000" required></textarea><button class="btn" type="submit">Send reply</button></form></section><?php elseif ($ticketId > 0): ?><div class="notice error">That support request was not found in your account.</div><?php endif; ?>
</div></body></html>
