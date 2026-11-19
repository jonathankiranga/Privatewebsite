<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator']);
$error='';
$settings=db()->query('SELECT * FROM platform_invoice_settings WHERE id=1')->fetch_assoc();
if(!$settings||trim($settings['legal_name'])===''||trim($settings['mpesa_till_number'])===''){page_header('Create invoice');echo '<div class="notice error">Complete your registered company details and M-Pesa Till number before issuing invoices. <a href="invoice-settings.php">Open invoice settings</a>.</div>';page_footer();exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$customerId=(int)($_POST['customer_id']??0);$invoiceDate=trim((string)($_POST['invoice_date']??date('Y-m-d')));$dueDate=trim((string)($_POST['due_date']??''));$vatInput=trim((string)($_POST['vat_rate']??$settings['default_vat_rate']));$notes=trim((string)($_POST['notes']??''));
 $descriptions=is_array($_POST['description']??null)?$_POST['description']:[];$quantities=is_array($_POST['quantity']??null)?$_POST['quantity']:[];$prices=is_array($_POST['unit_price']??null)?$_POST['unit_price']:[];$items=[];$subtotal=0.0;
 if($customerId<1||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$invoiceDate)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dueDate)||$dueDate<$invoiceDate||!preg_match('/^\d{1,2}(\.\d{1,2})?$/',$vatInput)||(float)$vatInput>100||strlen($notes)>5000)$error='Check the customer, invoice dates, VAT rate and notes.';
 if($error===''){for($i=0;$i<count($descriptions)&&$i<30;$i++){$desc=trim((string)$descriptions[$i]);$q=trim((string)($quantities[$i]??''));$p=trim((string)($prices[$i]??''));if($desc===''&&$q===''&&$p==='')continue;if($desc===''||strlen($desc)>500||!preg_match('/^\d{1,8}(\.\d{1,3})?$/',$q)||(float)$q<=0||!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$p)){$error='Each invoice line needs a description, positive quantity and valid unit price.';break;}$qty=(float)$q;$price=(float)$p;$line=round($qty*$price,2);$subtotal+=$line;$items[]=['description'=>$desc,'quantity'=>$qty,'unit_price'=>$price,'line_total'=>$line];}if(!$items&&$error==='')$error='Add at least one invoice line.';}
 if($error===''){$cs=db()->prepare("SELECT id,email,contact_name,phone,country_code,organisation_name FROM platform_customers WHERE id=? AND status <> 'closed' LIMIT 1");$cs->bind_param('i',$customerId);$cs->execute();$customer=$cs->get_result()->fetch_assoc();if(!$customer)$error='Select an existing customer that is not closed.';}
 if($error===''){
  $vatRate=(float)$vatInput;$vatAmount=round($subtotal*$vatRate/100,2);$total=round($subtotal+$vatAmount,2);
  $company=[];foreach(['legal_name','trading_name','company_registration_no','kra_pin','vat_pin','address','city','country','phone','email','website','mpesa_till_number','mpesa_till_name','mpesa_account_reference','payment_terms'] as $key)$company[$key]=$settings[$key];
  $customerSnapshot=['email'=>$customer['email'],'contact_name'=>$customer['contact_name'],'phone'=>$customer['phone'],'organisation_name'=>$customer['organisation_name']];
  $companyJson=json_encode($company,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$customerJson=json_encode($customerSnapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$adminId=(int)current_admin()['id'];$prefix=preg_replace('/[^A-Z0-9-]/','',(string)$settings['invoice_prefix']);
  db()->begin_transaction();
  try{
   $temporary='PENDING-'.bin2hex(random_bytes(6));$status='issued';$balance=$total;
   $stmt=db()->prepare("INSERT INTO platform_invoices(invoice_number,customer_id,invoice_date,due_date,currency,subtotal,vat_rate,vat_amount,total_amount,amount_paid,balance_due,status,notes,company_details_json,customer_details_json,created_by) VALUES(?,?,?,?, 'KES',?,?,?, ?,0,?,?,?,?,?,?)");
   $stmt->bind_param('sissdddddsssssi',$temporary,$customerId,$invoiceDate,$dueDate,$subtotal,$vatRate,$vatAmount,$total,$balance,$status,$notes,$companyJson,$customerJson,$adminId);
   $stmt->execute();$invoiceId=(int)db()->insert_id;$number=$prefix.'-'.date('Y',strtotime($invoiceDate)).'-'.str_pad((string)$invoiceId,6,'0',STR_PAD_LEFT);
   $up=db()->prepare('UPDATE platform_invoices SET invoice_number=? WHERE id=?');$up->bind_param('si',$number,$invoiceId);$up->execute();
   $li=db()->prepare('INSERT INTO platform_invoice_items(invoice_id,description,quantity,unit_price,line_total,sort_order) VALUES(?,?,?,?,?,?)');
   foreach($items as $index=>$item){$sort=$index+1;$li->bind_param('isdddi',$invoiceId,$item['description'],$item['quantity'],$item['unit_price'],$item['line_total'],$sort);$li->execute();}
   audit_event('invoice.created','invoice',$invoiceId,['invoice_number'=>$number,'total'=>$total,'currency'=>'KES']);db()->commit();redirect('invoice.php?id='.$invoiceId);
  }catch(Throwable $ex){db()->rollback();error_log('Invoice creation failed: '.$ex->getMessage());$error='The invoice could not be created. No invoice was saved.';}
 }
}
$customers=db()->query("SELECT id,email,contact_name,organisation_name FROM platform_customers WHERE status <> 'closed' ORDER BY organisation_name,contact_name,email");
page_header('Create invoice');
?>
<div class="eyebrow">Accounts receivable</div><h1 class="heading">Create invoice</h1><p class="sub">Issue a KES invoice to an existing customer. Your company and M-Pesa details are copied onto the invoice.</p>
<?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="panel"><h2>Invoice details</h2><div class="form-grid">
<div><label>Bill to customer *</label><select name="customer_id" required><option value="">Select customer</option><?php while($c=$customers->fetch_assoc()):?><option value="<?=(int)$c['id']?>" <?=(int)($_POST['customer_id']??0)===(int)$c['id']?'selected':''?>><?=e(($c['organisation_name']?:$c['contact_name']?:$c['email']).' · '.$c['email'])?></option><?php endwhile;?></select></div>
<div><label>VAT rate (%)</label><input name="vat_rate" inputmode="decimal" value="<?=e($_POST['vat_rate']??$settings['default_vat_rate'])?>"></div>
<div><label>Invoice date *</label><input type="date" name="invoice_date" required value="<?=e($_POST['invoice_date']??date('Y-m-d'))?>"></div>
<div><label>Due date *</label><input type="date" name="due_date" required value="<?=e($_POST['due_date']??date('Y-m-d',strtotime('+7 days')))?>"></div>
</div></div>
<div class="panel"><h2>Invoice items</h2><div style="overflow-x:auto"><table id="invoice-items"><thead><tr><th style="width:48%">Description</th><th>Quantity</th><th>Unit price (KES)</th><th></th></tr></thead><tbody>
<?php for($i=0;$i<4;$i++):?><tr><td><input name="description[]" maxlength="500" placeholder="Product or service"></td><td><input name="quantity[]" type="number" min="0.001" step="0.001" placeholder="1"></td><td><input name="unit_price[]" type="number" min="0" step="0.01" placeholder="0.00"></td><td><button type="button" class="btn secondary remove-line">Remove</button></td></tr><?php endfor;?>
</tbody></table></div><button type="button" class="btn secondary" id="add-line" style="margin-top:12px">Add line</button></div>
<div class="panel"><h2>Additional information</h2><label>Notes on invoice</label><textarea name="notes" rows="3" maxlength="5000"><?=e($_POST['notes']??'')?></textarea></div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Create and issue invoice</button><a class="btn secondary" href="invoices.php">Cancel</a></div></form>
<script>(function(){var body=document.querySelector('#invoice-items tbody');document.getElementById('add-line').addEventListener('click',function(){if(body.rows.length>=30){alert('A maximum of 30 invoice lines is allowed.');return;}var row=body.insertRow(-1);row.innerHTML='<td><input name="description[]" maxlength="500" placeholder="Product or service"></td><td><input name="quantity[]" type="number" min="0.001" step="0.001" placeholder="1"></td><td><input name="unit_price[]" type="number" min="0" step="0.01" placeholder="0.00"></td><td><button type="button" class="btn secondary remove-line">Remove</button></td>';});body.addEventListener('click',function(e){if(e.target.classList.contains('remove-line')&&body.rows.length>1)e.target.closest('tr').remove();});})();</script>
<?php page_footer(); ?>