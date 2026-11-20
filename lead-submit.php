<?php
declare(strict_types=1);
require __DIR__ . '/platform-admin/src/public-bootstrap.php';
session_name('SMARTNOW_LEAD_FORM');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['lead_csrf'])) $_SESSION['lead_csrf']=bin2hex(random_bytes(32));
$error=''; $success=isset($_GET['sent']); $productId=(int)($_GET['product'] ?? $_POST['product_id'] ?? 0); $product=null;
if($productId>0){$st=public_db()->prepare("SELECT id,name FROM platform_products WHERE id=? AND status='published'");$st->bind_param('i',$productId);$st->execute();$product=$st->get_result()->fetch_assoc();}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals((string)$_SESSION['lead_csrf'],(string)($_POST['csrf_token']??''))){http_response_code(419);exit('Form session expired. Refresh and try again.');}
 if(trim((string)($_POST['website']??''))!==''){$success=true;}
 else{
  $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$phone=trim((string)($_POST['phone']??''));$org=trim((string)($_POST['organisation']??''));$subject=trim((string)($_POST['subject']??''));$message=trim((string)($_POST['message']??''));$productId=(int)($_POST['product_id']??0);
  if($name===''||strlen($name)>160||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||strlen($phone)>60||strlen($org)>190||$subject===''||strlen($subject)>240||strlen($message)>10000||!isset($_POST['consent'])){$error='Complete the required fields and accept the contact consent.';}
  else{
   $productIdForDb=$productId>0?$productId:null;$source='website';$status='new';$priority='normal';
   $db=public_db();
   $secret=(string)getenv('SMARTNOW_ANALYTICS_SECRET');
   if(strlen($secret)<32){$error='The enquiry service is not configured yet. Please try again later.';}
   else{
    $emailHash=hash_hmac('sha256',$email,$secret);$ipHash=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),$secret);
    $rate=$db->prepare('SELECT COUNT(*) AS total FROM platform_public_submission_limits WHERE (email_hash=? OR ip_hash=?) AND created_at>DATE_SUB(NOW(),INTERVAL 1 DAY)');
    $rate->bind_param('ss',$emailHash,$ipHash);$rate->execute();$count=(int)$rate->get_result()->fetch_assoc()['total'];
    if($count>=5){$error='You have reached the daily enquiry limit. Please contact our team directly.';}
    else{
   try{
    $db->begin_transaction();
    $limit=$db->prepare('INSERT INTO platform_public_submission_limits(email_hash,ip_hash) VALUES(?,?)');$limit->bind_param('ss',$emailHash,$ipHash);$limit->execute();
    $stmt=$db->prepare("INSERT INTO platform_leads(name,email,phone,organisation,product_id,source,subject,message,status,priority) VALUES(?,?,?,?,?,?,?,?,'new','normal')");
    $stmt->bind_param('ssssisss',$name,$email,$phone,$org,$productIdForDb,$source,$subject,$message);$stmt->execute();$leadId=(int)$db->insert_id;
    $stmt=$db->prepare("INSERT INTO platform_lead_activities(lead_id,activity_type,subject,details) VALUES(?,'note','Website enquiry received',?)");
    $detail='Website form submission from '.$name.' ('.$email.').';$stmt->bind_param('is',$leadId,$detail);$stmt->execute();$db->commit();
    $support=trim((string)getenv('SMARTNOW_SUPPORT_EMAIL'));
    if(filter_var($support,FILTER_VALIDATE_EMAIL)){platform_send_mail($support,'New sales lead #'.$leadId.' - '.$subject,"A new website lead has been received.\n\nName: $name\nEmail: $email\nPhone: $phone\nOrganisation: $org\nSubject: $subject\nProduct ID: $productId\n\n$message\n\nOpen the admin portal and select Leads to follow up.",'SmarterNow Website');}
    unset($_SESSION['lead_csrf']);header('Location: lead-submit.php?sent=1');exit;
   }catch(Throwable $e){$db->rollback();error_log('Website lead capture failed: '.$e->getMessage());$error='Your request could not be submitted. Please try again later.';}
    }
   }
  }
 }
}
?><!doctype html><html lang="en-KE"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Request a systems proposal · SmarterNow</title><style>*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172033;font:16px/1.6 Inter,system-ui,sans-serif}.wrap{max-width:760px;margin:5vh auto;padding:18px}.panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px}label{display:block;font-size:13px;font-weight:700;margin:15px 0 6px}input,textarea{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit}textarea{min-height:130px}.btn{padding:12px 20px;background:#2457d6;color:#fff;border:0;border-radius:8px;font-weight:800;margin-top:18px;cursor:pointer}.notice{padding:12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}.hp{position:absolute;left:-10000px}</style></head><body><main class="wrap"><p><a href="https://smarternowhosting.co.ke/">← SmarterNow Data Venture</a></p><section class="panel"><h1>Request a systems proposal</h1><?php if($success): ?><div class="notice">Thank you. Your enquiry has been received. Our team will contact you using the email or phone number you provided.</div><?php else: ?><?php if($error): ?><div class="notice error"><?= public_e($error) ?></div><?php endif; ?><p><?php if($product): ?>Enquiry about: <strong><?= public_e($product['name']) ?></strong><?php else: ?>Tell us which system or business process you would like us to address.<?php endif; ?></p><form method="post"><input type="hidden" name="csrf_token" value="<?= public_e($_SESSION['lead_csrf']) ?>"><input type="hidden" name="product_id" value="<?= (int)$productId ?>"><div class="hp"><label>Website</label><input name="website" tabindex="-1" autocomplete="off"></div><label>Your name *</label><input name="name" maxlength="160" required><label>Work email *</label><input type="email" name="email" maxlength="190" required><label>Phone / WhatsApp</label><input name="phone" maxlength="60"><label>Organisation</label><input name="organisation" maxlength="190"><label>What would you like a proposal for? *</label><input name="subject" maxlength="240" required value="<?= $product?'Proposal for '.public_e($product['name']):'' ?>"><label>Describe your requirements *</label><textarea name="message" maxlength="10000" required placeholder="Tell us about your organisation, number of users or branches, current process and what you need the system to do."></textarea><label><input type="checkbox" name="consent" value="1" required style="width:auto"> I agree that SmarterNow may use these details to respond to this enquiry.</label><button class="btn" type="submit">Send proposal request</button></form><?php endif; ?></section></main></body></html>