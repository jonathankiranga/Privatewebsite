<?php
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/layout.php';
require_roles(['owner','administrator']);
$id=(int)($_GET['id']??0);$error='';$notice='';
if($id<1){http_response_code(400);exit('Invalid proposal.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=(string)($_POST['action']??'');
 if($action==='send'){
  $st=db()->prepare('SELECT * FROM platform_proposals WHERE id=?');$st->bind_param('i',$id);$st->execute();$p=$st->get_result()->fetch_assoc();
  if(!$p){http_response_code(404);exit('Proposal not found.');}
  elseif(!in_array($p['status'],['draft','sent'],true)){$error='Only draft or previously sent proposals can be emailed again.';}
  elseif(!filter_var($p['recipient_email'],FILTER_VALIDATE_EMAIL)){$error='A valid recipient email is required before sending.';}
  else{
   $publicUrl='https://smarternowhosting.co.ke/proposal-view.php?token='.rawurlencode($p['public_token']);
   $body="Dear ".$p['recipient_name'].",\n\nPlease find your SmarterNow systems proposal: ".$p['proposal_number']." - ".$p['title'].".\n\nProposal total: ".$p['currency'].' '.number_format((float)$p['total_amount'],2)."\nValid until: ".($p['valid_until']?:'As agreed')."\n\nView the proposal and respond securely here:\n".$publicUrl."\n\nRegards,\nSmarterNow Data Venture";
   $sent=platform_send_mail($p['recipient_email'],'Systems proposal '.$p['proposal_number'].' - '.$p['title'],$body,'SmarterNow Proposals');
   $adminId=(int)current_admin()['id'];$smtp=$sent?1:0;$recipient=$p['recipient_email'];$subject='Systems proposal '.$p['proposal_number'].' - '.$p['title'];
   $log=db()->prepare('INSERT INTO platform_proposal_email_log(proposal_id,recipient_email,subject,sent_by,smtp_accepted) VALUES(?,?,?,?,?)');$log->bind_param('issii',$id,$recipient,$subject,$adminId,$smtp);$log->execute();
   if($sent){
    $u=db()->prepare("UPDATE platform_proposals SET status='sent',sent_at=NOW() WHERE id=? AND status IN ('draft','sent')");$u->bind_param('i',$id);$u->execute();
    if(!empty($p['lead_id'])){$leadId=(int)$p['lead_id'];$u=db()->prepare("UPDATE platform_leads SET status='proposal_sent' WHERE id=? AND status NOT IN ('won','lost')");$u->bind_param('i',$leadId);$u->execute();$detail='Proposal '.$p['proposal_number'].' sent to '.$recipient.'. SMTP server accepted the message.';$a=db()->prepare("INSERT INTO platform_lead_activities(lead_id,admin_user_id,activity_type,subject,details) VALUES(?,?,'email','Proposal emailed',?)");$a->bind_param('iis',$leadId,$adminId,$detail);$a->execute();}
    audit_event('proposal.emailed','proposal',$id,['recipient_hash'=>hash('sha256',strtolower($recipient))]);$notice='The SMTP server accepted the proposal email. This confirms server acceptance, not final inbox delivery.';
   }else{$error='The SMTP server did not accept the email. Check the SMTP configuration and server log. The proposal remains available to print or send again.';}
  }
 }
}
$st=db()->prepare('SELECT * FROM platform_proposals WHERE id=?');$st->bind_param('i',$id);$st->execute();$p=$st->get_result()->fetch_assoc();if(!$p){http_response_code(404);exit('Proposal not found.');}
$st=db()->prepare('SELECT * FROM platform_proposal_items WHERE proposal_id=? ORDER BY sort_order,id');$st->bind_param('i',$id);$st->execute();$items=$st->get_result();
$company=json_decode((string)$p['company_details_json'],true)?:[];
page_header('Proposal '.$p['proposal_number']);
?>
<div class="eyebrow">Sales documents</div><h1 class="heading"><?=e($p['proposal_number'])?></h1><p class="sub"><?=e($p['title'])?> · <?=e(ucfirst($p['status']))?></p>
<?php if($notice):?><div class="notice"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<div class="actions"><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="send"><button class="btn" type="submit" onclick="return confirm('Send this proposal to <?=e($p['recipient_email'])?> using the configured SmarterNow SMTP account?')">Send proposal by email</button></form><button class="btn secondary" type="button" onclick="window.print()">Print / Save PDF</button><a class="btn secondary" href="proposal-create.php?lead=<?=(int)$p['lead_id']?>">Create another</a></div>
<div class="panel" id="proposal-print" style="max-width:900px;margin-top:20px">
<div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap"><div><h2><?=e($company['trading_name']?:($company['legal_name']??'SmarterNow Data Venture'))?></h2><p><?=e($company['legal_name']??'')?> <?=e($company['company_registration_no']??'')?><br><?=e($company['kra_pin']??'')?> <?=e($company['vat_pin']??'')?><br><?=e($company['address']??'')?> <?=e($company['city']??'')?> <?=e($company['country']??'Kenya')?><br><?=e($company['phone']??'')?> · <?=e($company['email']??'')?></p></div><div style="text-align:right"><strong>PROPOSAL</strong><h2><?=e($p['proposal_number'])?></h2><p>Date: <?=e(date('d M Y',strtotime($p['created_at'])))?><br>Valid until: <?=e($p['valid_until']?:'As agreed')?></p></div></div>
<hr><h2><?=e($p['title'])?></h2><p><strong>Prepared for:</strong><br><?=e($p['recipient_name'])?><br><?=e($p['recipient_organisation'])?><br><?=e($p['recipient_email'])?> · <?=e($p['recipient_phone'])?></p>
<?php if($p['introduction']):?><h3>Introduction</h3><p><?=nl2br(e($p['introduction']))?></p><?php endif;?><?php if($p['scope_of_work']):?><h3>Proposed solution and scope of work</h3><p><?=nl2br(e($p['scope_of_work']))?></p><?php endif;?>
<h3>Commercial proposal</h3><table><thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead><tbody><?php while($it=$items->fetch_assoc()):?><tr><td><?=e($it['description'])?></td><td><?=number_format((float)$it['quantity'],2)?></td><td><?=e($p['currency'])?> <?=number_format((float)$it['unit_price'],2)?></td><td><?=e($p['currency'])?> <?=number_format((float)$it['line_total'],2)?></td></tr><?php endwhile;?></tbody></table>
<div style="max-width:360px;margin-left:auto"><p>Subtotal: <?=e($p['currency'])?> <?=number_format((float)$p['subtotal'],2)?><br>Discount: <?=e($p['currency'])?> <?=number_format((float)$p['discount_amount'],2)?><br>VAT (<?=number_format((float)$p['vat_rate'],2)?>%): <?=e($p['currency'])?> <?=number_format((float)$p['vat_amount'],2)?></p><h2>Total: <?=e($p['currency'])?> <?=number_format((float)$p['total_amount'],2)?></h2></div>
<?php if($p['assumptions']):?><h3>Assumptions and exclusions</h3><p><?=nl2br(e($p['assumptions']))?></p><?php endif;?><?php if($p['terms']):?><h3>Commercial terms</h3><p><?=nl2br(e($p['terms']))?></p><?php endif;?>
<p style="margin-top:40px">We appreciate the opportunity to submit this proposal. Please use the secure proposal link sent by email to accept or decline this offer.</p><p><strong>SmarterNow Data Venture</strong></p>
</div>
<style>@media print{body{background:#fff}.side,.top,.eyebrow,.sub,.actions{display:none!important}.shell{display:block}.content{padding:0;max-width:none}.panel{border:0;box-shadow:none}#proposal-print{max-width:none!important;margin:0!important}}</style>
<?php page_footer(); ?>
