<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator']);
$error=''; $notice='';
$fields=['legal_name','trading_name','company_registration_no','kra_pin','vat_pin','address','city','country','phone','email','website','mpesa_till_number','mpesa_till_name','mpesa_account_reference','payment_terms','invoice_prefix','default_vat_rate'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $d=[]; foreach($fields as $f)$d[$f]=trim((string)($_POST[$f]??'')); $d['invoice_prefix']=strtoupper($d['invoice_prefix']);
 if($d['legal_name']===''||strlen($d['legal_name'])>190||strlen($d['trading_name'])>190||strlen($d['company_registration_no'])>100||strlen($d['kra_pin'])>40||strlen($d['vat_pin'])>40||strlen($d['address'])>4000||strlen($d['city'])>120||strlen($d['country'])>100||strlen($d['phone'])>60||($d['email']!==''&&!filter_var($d['email'],FILTER_VALIDATE_EMAIL))||strlen($d['email'])>190||($d['website']!==''&&!filter_var($d['website'],FILTER_VALIDATE_URL))||strlen($d['website'])>255||strlen($d['mpesa_till_number'])>40||strlen($d['mpesa_till_name'])>190||strlen($d['mpesa_account_reference'])>100||strlen($d['payment_terms'])>500||!preg_match('/^[A-Z0-9-]{2,20}$/',$d['invoice_prefix'])||!preg_match('/^\d{1,2}(\.\d{1,2})?$/',$d['default_vat_rate'])||(float)$d['default_vat_rate']>100){
  $error='Check the company details, email, website, invoice prefix and VAT rate.';
 }else{
  $vat=(float)$d['default_vat_rate']; $adminId=(int)current_admin()['id'];
  $stmt=db()->prepare('UPDATE platform_invoice_settings SET legal_name=?,trading_name=?,company_registration_no=?,kra_pin=?,vat_pin=?,address=?,city=?,country=?,phone=?,email=?,website=?,mpesa_till_number=?,mpesa_till_name=?,mpesa_account_reference=?,payment_terms=?,invoice_prefix=?,default_vat_rate=?,updated_by=? WHERE id=1');
  $stmt->bind_param('ssssssssssssssssdi',$d['legal_name'],$d['trading_name'],$d['company_registration_no'],$d['kra_pin'],$d['vat_pin'],$d['address'],$d['city'],$d['country'],$d['phone'],$d['email'],$d['website'],$d['mpesa_till_number'],$d['mpesa_till_name'],$d['mpesa_account_reference'],$d['payment_terms'],$d['invoice_prefix'],$vat,$adminId);
  $stmt->execute(); audit_event('invoice_settings.updated','invoice_settings',1,['till_configured'=>$d['mpesa_till_number']!=='']); redirect('invoice-settings.php?saved=1');
 }
}else{$res=db()->query('SELECT '.implode(',',$fields).' FROM platform_invoice_settings WHERE id=1');$d=$res->fetch_assoc()?:[];}
if(isset($_GET['saved']))$notice='Company and invoice settings saved.';
page_header('Invoice settings');
?>
<div class="eyebrow">Billing configuration</div><h1 class="heading">Company &amp; invoice settings</h1><p class="sub">These details are printed on new invoices. Previously issued invoices retain the original details.</p>
<?php if($notice):?><div class="notice"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="panel"><h2>Official company details</h2><div class="form-grid">
<div><label>Registered legal name *</label><input name="legal_name" maxlength="190" required value="<?=e($d['legal_name']??'')?>"></div><div><label>Trading name</label><input name="trading_name" maxlength="190" value="<?=e($d['trading_name']??'')?>"></div>
<div><label>Company registration number</label><input name="company_registration_no" maxlength="100" value="<?=e($d['company_registration_no']??'')?>"></div><div><label>KRA PIN</label><input name="kra_pin" maxlength="40" value="<?=e($d['kra_pin']??'')?>"></div>
<div><label>VAT PIN (if registered)</label><input name="vat_pin" maxlength="40" value="<?=e($d['vat_pin']??'')?>"></div><div><label>Contact phone</label><input name="phone" maxlength="60" value="<?=e($d['phone']??'')?>"></div>
<div><label>Business email</label><input name="email" type="email" maxlength="190" value="<?=e($d['email']??'')?>"></div><div><label>Website</label><input name="website" type="url" maxlength="255" value="<?=e($d['website']??'')?>"></div>
<div class="wide"><label>Physical / postal address</label><textarea name="address" rows="3" maxlength="4000"><?=e($d['address']??'')?></textarea></div>
<div><label>City / County</label><input name="city" maxlength="120" value="<?=e($d['city']??'')?>"></div><div><label>Country</label><input name="country" maxlength="100" value="<?=e($d['country']??'Kenya')?>"></div>
</div></div>
<div class="panel"><h2>M-Pesa and payment instructions</h2><div class="form-grid">
<div><label>M-Pesa Buy Goods Till number</label><input name="mpesa_till_number" maxlength="40" required value="<?=e($d['mpesa_till_number']??'')?>" placeholder="Your official Till number"></div>
<div><label>Till / business display name</label><input name="mpesa_till_name" maxlength="190" value="<?=e($d['mpesa_till_name']??'')?>"></div>
<div><label>Account / payment reference</label><input name="mpesa_account_reference" maxlength="100" value="<?=e($d['mpesa_account_reference']??'invoice_number')?>" placeholder="Use invoice_number for invoice-specific references"></div>
<div><label>Default VAT rate (%)</label><input name="default_vat_rate" inputmode="decimal" value="<?=e($d['default_vat_rate']??'0.00')?>"></div>
<div><label>Invoice number prefix</label><input name="invoice_prefix" maxlength="20" pattern="[A-Za-z0-9-]{2,20}" required value="<?=e($d['invoice_prefix']??'INV')?>"></div>
<div class="wide"><label>Payment terms / instructions</label><textarea name="payment_terms" rows="3" maxlength="500"><?=e($d['payment_terms']??'Payment due within 7 days.')?></textarea></div>
</div><p class="label">The invoice displays the Till number and instructions. It does not initiate M-Pesa STK pushes or automatically verify payments.</p></div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Save invoice settings</button><a class="btn secondary" href="invoices.php">Back to invoices</a></div></form>
<?php page_footer(); ?>