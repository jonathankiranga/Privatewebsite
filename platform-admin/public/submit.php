<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/public-bootstrap.php';
session_name('SMARTNOW_PUBLIC_FORM');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (empty($_SESSION['public_form_csrf'])) $_SESSION['public_form_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['public_form_csrf'];
$error = '';
$success = isset($_GET['sent']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!$token || !hash_equals($csrf, $token)) { http_response_code(419); exit('Your form session expired. Refresh the page and try again.'); }
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $success = true;
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $type = (string)($_POST['ticket_type'] ?? 'support');
        $subject = trim((string)($_POST['subject'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $consent = isset($_POST['privacy_consent']);
        if ($name === '' || strlen($name) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 ||
            !in_array($type, ['support','bug','feature_request'], true) || $subject === '' || strlen($subject) > 240 ||
            $description === '' || strlen($description) > 20000 || !$consent) {
            $error = 'Please complete the required fields, provide a valid email address and accept the privacy notice.';
        } else {
            $secret = (string)getenv('SMARTNOW_ANALYTICS_SECRET');
            if (strlen($secret) < 32) {
                $error = 'The submission service is not configured yet. Please try again later.';
            } else {
                $emailHash = hash_hmac('sha256', $email, $secret);
                $ipHash = hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $secret);
                $db = public_db();
                $rate = $db->prepare('SELECT COUNT(*) AS total FROM platform_public_submission_limits WHERE (email_hash=? OR ip_hash=?) AND created_at>DATE_SUB(NOW(),INTERVAL 1 DAY)');
                $rate->bind_param('ss', $emailHash, $ipHash);
                $rate->execute();
                $count = (int)$rate->get_result()->fetch_assoc()['total'];
                if ($count >= 5) {
                    $error = 'You have reached the daily submission limit. Please try again tomorrow or use your customer account.';
                } else {
                    try {
                        $db->begin_transaction();
                        $stmt = $db->prepare('INSERT INTO platform_public_submission_limits (email_hash,ip_hash) VALUES (?,?)');
                        $stmt->bind_param('ss', $emailHash, $ipHash);
                        $stmt->execute();
                        $priority = 'normal';
                        $status = 'open';
                        $stmt = $db->prepare('INSERT INTO platform_support_tickets (customer_id,ticket_type,requester_email,subject,description,priority,status) VALUES (NULL,?,?,?,?,?,?)');
                        $stmt->bind_param('ssssss', $type, $email, $subject, $description, $priority, $status);
                        $stmt->execute();
                        $ticketId = (int)$db->insert_id;
                        $authorType = 'customer';
                        $internal = 0;
                        $message = 'Submitted by: '.$name."\n\n".$description;
                        $stmt = $db->prepare('INSERT INTO platform_ticket_messages (ticket_id,author_type,message,is_internal) VALUES (?,?,?,?)');
                        $stmt->bind_param('issi', $ticketId, $authorType, $message, $internal);
                        $stmt->execute();
                        $db->commit();

                        $support = trim((string)getenv('SMARTNOW_SUPPORT_EMAIL'));
                        $from = trim((string)getenv('SMARTNOW_MAIL_FROM'));
                        if (filter_var($support, FILTER_VALIDATE_EMAIL) && filter_var($from, FILTER_VALIDATE_EMAIL)) {
                            $body = "A new public website submission (#".$ticketId.") was received.\n\nName: ".$name."\nEmail: ".$email."\nType: ".$type."\nSubject: ".$subject."\n\n".$description."\n\nReview it in the administration portal.";
                            if (!platform_send_mail($support, 'Website submission #'.$ticketId, $body, 'SmarterNow Website')) {
                                error_log('Website submission was saved, but its SMTP notification failed for ticket #'.$ticketId.'.');
                            }
                        } else error_log('Website submission saved but support email is not configured.');
                        unset($_SESSION['public_form_csrf']);
                        header('Location: submit.php?sent=1');
                        exit;
                    } catch (Throwable $e) {
                        $db->rollback();
                        error_log('Public website submission failed: '.$e->getMessage());
                        $error = 'Your submission could not be saved. Please try again.';
                    }
                }
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contact and submit feedback · SmartNow</title><meta name="description" content="Contact SmartNow Data Venture, report a software issue or suggest an improvement."><meta name="robots" content="noindex,follow"><style>*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#172033;font-family:Inter,system-ui,sans-serif}.wrap{max-width:760px;margin:5vh auto;padding:20px}.panel{background:#fff;border:1px solid #e5e9f0;border-radius:16px;padding:28px}a{color:#2457d6}label{display:block;margin:16px 0 6px;font-size:13px;font-weight:700}input,select,textarea{width:100%;padding:12px;border:1px solid #cfd6e2;border-radius:8px;font:inherit}textarea{min-height:150px;resize:vertical}.btn{margin-top:18px;padding:12px 18px;background:#2457d6;color:#fff;border:0;border-radius:8px;font-weight:700;cursor:pointer}.notice{padding:13px;border-radius:8px;background:#ecfdf5;border:1px solid #a7f3d0;margin-bottom:16px}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}.privacy{font-size:13px;color:#64748b;line-height:1.6}.hp{position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden}@media(max-width:600px){.wrap{padding:12px}.panel{padding:20px}}</style></head><body><main class="wrap"><p><a href="https://smarternowhosting.co.ke/">← SmartNow Data Venture</a></p><section class="panel"><h1>Contact and submit feedback</h1><p>Ask a question, report a problem or suggest an improvement. You do not need to create an account to send your first submission. We will use the email address you provide to respond.</p>
<?php if ($success): ?><div class="notice">Thank you. Your submission has been received and added to the support queue. The team can contact you at the email address you provided.</div><?php else: ?>
<?php if ($error): ?><div class="notice error"><?= public_e($error) ?></div><?php endif; ?>
<form method="post" action="submit.php"><input type="hidden" name="csrf_token" value="<?= public_e($csrf) ?>"><div class="hp" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<label for="name">Your name</label><input id="name" name="name" maxlength="160" autocomplete="name" required>
<label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required>
<label for="ticket_type">What is this about?</label><select id="ticket_type" name="ticket_type"><option value="support">I need help / have a question</option><option value="bug">Report a bug or problem</option><option value="feature_request">Suggest an improvement</option></select>
<label for="subject">Subject</label><input id="subject" name="subject" maxlength="240" required>
<label for="description">Details</label><textarea id="description" name="description" maxlength="20000" required placeholder="Describe what you were trying to do, what happened, and what you expected. Do not include passwords or payment-card details."></textarea>
<label class="privacy"><input type="checkbox" name="privacy_consent" value="1" required style="width:auto"> I agree that SmartNow may use the details I submit to handle this request and contact me about it. See the <a href="https://smarternowhosting.co.ke/privacy.html">privacy information</a>.</label>
<button class="btn" type="submit">Send submission</button></form><?php endif; ?></section></main></body></html>
