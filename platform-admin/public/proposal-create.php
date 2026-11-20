<?php
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/layout.php';
require_roles(['owner','administrator']);
$error='';$leadId=(int)($_GET['lead']??$_POST['lead_id']??0);$lead=null;
if($leadId>0){$st=db()->prepare('SELECT * FROM platform_leads WHERE id=?');$st->bind_param('i',$leadId);$st->execute();$lead=$st->get_result()->fetch_assoc();}
$products=db()->query("SELECT id,name,summary FROM platform_products WHERE status='published' ORDER BY name");
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $title=trim((string)($_POST['title']??''));$recipientName=trim((string)($_POST['recipient_name']??''));$recipientEmail=strtolower(trim((string)($_POST['recipient_email']??'')));$organisation=trim((string)($_POST['recipient_organisation']??''));$phone=trim((string)($_POST['recipient_phone']??''));$intro=trim((string)($_POST['introduction']??''));$scope=trim((string)($_POST['scope_of_work']??''));$assumptions=trim((string)($_POST['assumptions']??''));$terms=trim((string)($_POST['terms']??''));$currency=strtoupper(trim((string)($_POST['currency']??'KES')));$vat=(float)($_POST['vat_rate']??0);$discount=(float)($_POST['discount_amount']??0);$valid=trim((string)($_POST['valid_until']??''));$description=trim((string)($_POST['item_description']??''));$qty=(float)($_POST['quantity']??1);$price=(float)($_POST['unit_price']??0);
 if($title===''||strlen($title)>240||$recipientName===''||!filter_var($recipientEmail,FILTER_VALIDATE_EMAIL)||strlen($recipientName)>160||strlen($recipientEmail)>190||strlen($organisation)>190||strlen($phone)>60||strlen($currency)!==3||!preg_match('/^[A-Z]{3}$/',$currency)||$vat<0||$vat>100||$discount<0||$qty<=0||$qty>100000||$price<0||$description===''||strlen($description)>500||strlen($intro)>20000||strlen($scope)>20000||strlen($assumptions)>10000||strlen($terms)>10000||($valid!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$valid))){$error='Check recipient details, proposal title, currency, VAT, discount and at least one valid proposal item.';}
 else{
  $subtotal=round($qty*$price,2);$discount=min($discount,$subtotal);$taxable=max(0,$subtotal-$discount);$vatAmount=round($taxable*$vat/100,2);$total=round($taxable+$vatAmount,2);$token=bin2hex(random_bytes(32));$temporary='TMP-'.strtoupper(bin2hex(random_bytes(6)));$status='draft';$adminId=(int)current_admin()['id'];$leadDb=$leadId>0?$leadId:null;$customerId=$lead&&$lead['customer_id']?(int)$lead['customer_id']:null;$validDb=$valid!==''?$valid:null;
  $settings=db()->query('SELECT legal_name,trading_name,company_registration_no,kra_pin,vat_pin,address,city,country,phone,email,website,mpesa_till_number,mpesa_till_name FROM platform_invoice_settings WHERE id=1')->fetch_assoc();
  $companyJson=$settings?json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;
  try{
   db()->begin_transaction();
   $stmt=db()->prepare('INSERT INTO platform_proposals(lead_id,customer_id,proposal_number,public_token,title,recipient_name,recipient_email,recipient_organisation,recipient_phone,company_details_json,introduction,scope_of_work,assumptions,terms,currency,subtotal,discount_amount,vat_rate,vat_amount,total_amount,valid_until,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
   $stmt->bind_param('iisssssssssssssdddddssi',$leadDb,$customerId,$temporary,$token,$title,$recipientName,$recipientEmail,$organisation,$phone,$companyJson,$intro,$scope,$assumptions,$terms,$currency,$subtotal,$discount,$vat,$vatAmount,$total,$validDb,$status,$adminId);
   $stmt->execute();$proposalId=(int)db()->insert_id;$proposalNumber='SMD-PROP-'.date('Y').'-'.str_pad((string)$proposalId,5,'0',STR_PAD_LEFT);
   $u=db()->prepare('UPDATE platform_proposals SET proposal_number=? WHERE id=?');$u->bind_param('si',$proposalNumber,$proposalId);$u->execute();
   $productId=(int)($_POST['product_id']??0);$productDb=$productId>0?$productId:null;
   $item=db()->prepare('INSERT INTO platform_proposal_items(proposal_id,product_id,description,quantity,unit_price,line_total,sort_order) VALUES(?,?,?,?,?,?,1)');
   $item->bind_param('iisddd',$proposalId,$productDb,$description,$qty,$price,$subtotal);$item->execute();
   if($leadId>0){$u=db()->prepare("UPDATE platform_leads SET status='qualified' WHERE id=? AND status IN ('new','contacted')");$u->bind_param('i',$leadId);$u->execute();$adminId2=(int)current_admin()['id'];$detail='Proposal '.$proposalNumber.' created in draft status.';$a=db()->prepare("INSERT INTO platform_lead_activities(lead_id,admin_user_id,activity_type,subject,details) VALUES(?,?,'note','Proposal prepared',?)");$a->bind_param('iis',$leadId,$adminId2,$detail);$a->execute();}
   db()->commit();audit_event('proposal.created','proposal',$proposalId,['proposal_number'=>$proposalNumber]);redirect('proposal.php?id='.$proposalId);
  }catch(Throwable $e){db()->rollback();error_log('Proposal creation failed: '.$e->getMessage());$error='The proposal could not be created. Check the server log.';}
 }
}
page_header('Create proposal');
?>
<div class="eyebrow">Sales documents</div><h1 class="heading">Prepare systems proposal</h1><p class="sub">Create a proposal using your company details from Invoice settings. It can be reviewed, printed or emailed from the configured SmarterNow SMTP account.</p>
<?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<div class="panel"><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="lead_id" value="<?=$leadId?>">
<div class="form-grid"><div class="wide"><label>Proposal title</label><input name="title" maxlength="240" required value="<?=e($lead?'Systems proposal for '.$lead['organisation']:'Systems implementation proposal')?>"></div>
<div><label>Recipient name</label><input name="recipient_name" maxlength="160" required value="<?=e($lead['name']??'')?>"></div><div><label>Recipient email</label><input type="email" name="recipient_email" maxlength="190" required value="<?=e($lead['email']??'')?>"></div>
<div><label>Organisation</label><input name="recipient_organisation" maxlength="190" value="<?=e($lead['organisation']??'')?>"></div><div><label>Phone</label><input name="recipient_phone" maxlength="60" value="<?=e($lead['phone']??'')?>"></div>
<div><label>Related product</label><select name="product_id"><option value="0">Custom proposal</option><?php while($p=$products->fetch_assoc()):?><option value="<?=(int)$p['id']?>" <?=((int)($lead['product_id']??0)===(int)$p['id'])?'selected':''?>><?=e($p['name'])?></option><?php endwhile;?></select></div><div><label>Currency</label><input name="currency" maxlength="3" value="KES" required></div>
<div class="wide"><label>Introduction</label><textarea name="introduction" rows="4" placeholder="Thank the customer and summarise the business need."></textarea></div>
<div class="wide"><label>Scope of work / proposed solution</label><textarea name="scope_of_work" rows="6" placeholder="Describe the modules, implementation activities, configuration and deliverables."></textarea></div>
<div><label>Proposal item description</label><input name="item_description" maxlength="500" required placeholder="Software licence, implementation, training..."></div><div><label>Quantity</label><input name="quantity" type="number" min="0.01" step="0.01" value="1" required></div><div><label>Unit price</label><input name="unit_price" type="number" min="0" step="0.01" value="0" required></div><div><label>Discount amount</label><input name="discount_amount" type="number" min="0" step="0.01" value="0"></div><div><label>VAT rate (%)</label><input name="vat_rate" type="number" min="0" max="100" step="0.01" value="0"></div><div><label>Valid until</label><input name="valid_until" type="date"></div>
<div class="wide"><label>Assumptions / exclusions</label><textarea name="assumptions" rows="3"></textarea></div><div class="wide"><label>Commercial terms and payment schedule</label><textarea name="terms" rows="4" placeholder="Enter payment milestones, support period and other agreed terms."></textarea></div></div><button class="btn" type="submit">Create proposal</button></form></div>
<?php page_footer(); ?>
