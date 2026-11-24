<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator']);
$invoiceId=(int)($_GET['id']??$_POST['invoice_id']??0);if($invoiceId<1){http_response_code(404);exit('Invoice not found.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=(string)($_POST['action']??'');
 if($action==='payment'){
  $amountInput=trim((string)($_POST['amount']??''));$paymentDate=trim((string)($_POST['payment_date']??date('Y-m-d')));$method=(string)($_POST['method']??'mpesa');$reference=trim((string)($_POST['reference']??''));$notes=trim((string)($_POST['payment_notes']??''));
  if(!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$amountInput)||(float)$amountInput<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$paymentDate)||!in_array($method,['mpesa','bank','cash','other'],true)||strlen($reference)>120||strlen($notes)>500)$error='Enter a valid payment amount, date, method and reference.';
  else{
   $amount=(float)$amountInput;db()->begin_transaction();
   try{$lock=db()->prepare('SELECT total_amount,amount_paid,balance_due,status FROM platform_invoices WHERE id=? FOR UPDATE');$lock->bind_param('i',$invoiceId);$lock->execute();$cur=$lock->get_result()->fetch_assoc();
    if(!$cur||$cur['status']==='void')throw new RuntimeException('Payments cannot be recorded against a missing or void invoice.');
    if($amount>(float)$cur['balance_due']+0.001)throw new RuntimeException('The payment exceeds the outstanding balance.');
    $adminId=(int)current_admin()['id'];$p=db()->prepare('INSERT INTO platform_invoice_payments(invoice_id,payment_date,amount,method,reference,notes,recorded_by) VALUES(?,?,?,?,?,?,?)');$p->bind_param('isdsssi',$invoiceId,$paymentDate,$amount,$method,$reference,$notes,$adminId);$p->execute();
    $paid=round((float)$cur['amount_paid']+$amount,2);$balance=round((float)$cur['total_amount']-$paid,2);$status=$balance<=0?'paid':'part_paid';$u=db()->prepare('UPDATE platform_invoices SET amount_paid=?,balance_due=?,status=? WHERE id=?');$u->bind_param('ddsi',$paid,$balance,$status,$invoiceId);$u->execute();
    audit_event('invoice.payment_recorded','invoice',$invoiceId,['amount'=>$amount,'method'=>$method,'reference'=>$reference]);db()->commit();redirect('invoice.php?id='.$invoiceId.'&payment_saved=1');
   }catch(Throwable $ex){db()->rollback();$error=$ex instanceof RuntimeException?$ex->getMessage():'The payment could not be recorded.';if(!($ex instanceof RuntimeException))error_log('Invoice payment failed: '.$ex->getMessage());}
  }
 }elseif($action==='email_receipt'){
  $paymentId=(int)($_POST['payment_id']??0);$recipient=trim((string)($_POST['recipient_email']??''));
  if($paymentId<1||!filter_var($recipient,FILTER_VALIDATE_EMAIL)||strlen($recipient)>190)$error='Enter a valid customer email address.';
  else{
   $receipt=db()->prepare('SELECT p.id,p.receipt_number,p.payment_date,p.amount,p.method,p.reference,p.notes,i.invoice_number,i.total_amount,i.company_details_json,i.customer_details_json FROM platform_invoice_payments p INNER JOIN platform_invoices i ON i.id=p.invoice_id WHERE p.id=? AND p.invoice_id=? LIMIT 1');
   $receipt->bind_param('ii',$paymentId,$invoiceId);$receipt->execute();$payment=$receipt->get_result()->fetch_assoc();
   if(!$payment)$error='The payment receipt could not be found for this invoice.';
   else{
    $paidStmt=db()->prepare('SELECT COALESCE(SUM(amount),0) AS paid_to_date FROM platform_invoice_payments WHERE invoice_id=? AND id<=?');$paidStmt->bind_param('ii',$invoiceId,$paymentId);$paidStmt->execute();$paidToDate=(float)$paidStmt->get_result()->fetch_assoc()['paid_to_date'];
    $companyDetails=json_decode($payment['company_details_json'],true)?:[];$customerDetails=json_decode($payment['customer_details_json'],true)?:[];
    $receiptBalance=max(0,round((float)$payment['total_amount']-$paidToDate,2));
    $receiptSubject='Payment receipt '.$payment['receipt_number'].' · '.$payment['invoice_number'];
    $receiptBody="PAYMENT RECEIPT\\n".
     (($companyDetails['trading_name']??'')?:($companyDetails['legal_name']??'Company'))."\\n".
     "Receipt number: ".$payment['receipt_number']."\\n".
     "Invoice number: ".$payment['invoice_number']."\\n".
     "Customer: ".(($customerDetails['organisation_name']??'')?:($customerDetails['contact_name']??'Customer'))."\\n".
     "Payment date: ".$payment['payment_date']."\\n".
     "Payment method: ".strtoupper($payment['method'])."\\n".
     "Transaction reference: ".(($payment['reference']??'')?:'—')."\\n".
     "Amount received: KES ".number_format((float)$payment['amount'],2)."\\n".
     "Invoice total: KES ".number_format((float)$payment['total_amount'],2)."\\n".
     "Total received to date: KES ".number_format($paidToDate,2)."\\n".
     "Balance remaining after this payment: KES ".number_format($receiptBalance,2)."\\n".
     "Notes: ".(($payment['notes']??'')?:'—')."\\n\\n".
     "Thank you for your payment.\\n".
     (($companyDetails['email']??'')?'Contact: '.$companyDetails['email']:'');
    $sent=admin_send_mail($recipient,$receiptSubject,$receiptBody);
    $log=db()->prepare('INSERT INTO platform_invoice_receipt_email_log(payment_id,recipient_email,subject,sent_by,smtp_accepted) VALUES(?,?,?,?,?)');
    $adminId=(int)current_admin()['id'];$accepted=$sent?1:0;$log->bind_param('issii',$paymentId,$recipient,$receiptSubject,$adminId,$accepted);$log->execute();
    audit_event('invoice.receipt_email_attempted','invoice_payment',$paymentId,['receipt_number'=>$payment['receipt_number'],'recipient_hash'=>hash('sha256',strtolower($recipient)),'smtp_accepted'=>$accepted]);
    redirect('invoice.php?id='.$invoiceId.'&receipt_email='.($sent?'sent':'failed'));
   }
  }
 }elseif($action==='void'){
  $reason=trim((string)($_POST['void_reason']??''));$st=db()->prepare('SELECT status,amount_paid FROM platform_invoices WHERE id=?');$st->bind_param('i',$invoiceId);$st->execute();$cur=$st->get_result()->fetch_assoc();
  if(!$cur||$cur['status']==='void'||(float)$cur['amount_paid']>0||strlen($reason)<5||strlen($reason)>500)$error='Only unpaid invoices can be voided. Enter a reason of at least 5 characters.';
  else{$u=db()->prepare("UPDATE platform_invoices SET status='void',notes=CONCAT(COALESCE(notes,''),CASE WHEN COALESCE(notes,'')='' THEN '' ELSE '\\n' END,'VOID: ',?) WHERE id=?");$u->bind_param('si',$reason,$invoiceId);$u->execute();audit_event('invoice.voided','invoice',$invoiceId,['reason'=>$reason]);redirect('invoice.php?id='.$invoiceId.'&voided=1');}
 }
}
$st=db()->prepare('SELECT * FROM platform_invoices WHERE id=?');$st->bind_param('i',$invoiceId);$st->execute();$invoice=$st->get_result()->fetch_assoc();if(!$invoice){http_response_code(404);exit('Invoice not found.');}
$company=json_decode($invoice['company_details_json'],true)?:[];$customer=json_decode($invoice['customer_details_json'],true)?:[];
$ls=db()->prepare('SELECT description,quantity,unit_price,line_total FROM platform_invoice_items WHERE invoice_id=? ORDER BY sort_order,id');$ls->bind_param('i',$invoiceId);$ls->execute();$lines=$ls->get_result();
$ps=db()->prepare('SELECT id,receipt_number,payment_date,amount,method,reference,notes FROM platform_invoice_payments WHERE invoice_id=? ORDER BY payment_date,id');$ps->bind_param('i',$invoiceId);$ps->execute();$payments=$ps->get_result();
$status=$invoice['status'];if(!in_array($status,['paid','void'],true)&&(float)$invoice['balance_due']>0&&$invoice['due_date']<date('Y-m-d'))$status='overdue';
page_header('Invoice '.$invoice['invoice_number']);
?>
<style>
.invoice-sheet{background:#fff;border:1px solid #e5e9f0;border-radius:12px;padding:38px;max-width:900px;margin:20px auto}.invoice-head{display:flex;justify-content:space-between;gap:25px;border-bottom:2px solid #172033;padding-bottom:24px}.invoice-brand{font-size:23px;font-weight:800}.invoice-meta{text-align:right}.invoice-meta h1{font-size:30px;margin:0 0 8px;letter-spacing:.04em}.invoice-columns{display:grid;grid-template-columns:1fr 1fr;gap:25px;margin:26px 0}.invoice-label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#64748b;font-weight:700}.invoice-total{margin-left:auto;width:min(100%,330px)}.invoice-total div{display:flex;justify-content:space-between;padding:7px 0}.invoice-total .grand{border-top:2px solid #172033;font-size:19px;font-weight:800;margin-top:8px;padding-top:12px}.invoice-pay{background:#f2f6fc;border:1px solid #dce5f1;border-radius:10px;padding:18px;margin-top:25px}.invoice-pay h3{margin:0 0 8px}.invoice-sheet table th,.invoice-sheet table td{padding:11px 8px}.invoice-sheet table td:last-child,.invoice-sheet table th:last-child{text-align:right}.invoice-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin:18px auto;max-width:900px}
@media print{body{background:#fff!important}.side,.top,.invoice-actions,.no-print{display:none!important}.shell,.main{display:block!important}.content{padding:0!important;max-width:none!important}.invoice-sheet{border:0;border-radius:0;margin:0;padding:12mm;max-width:none;box-shadow:none}.invoice-sheet a{color:inherit;text-decoration:none}}
@media(max-width:650px){.invoice-sheet{padding:18px}.invoice-head{flex-direction:column}.invoice-meta{text-align:left}.invoice-columns{grid-template-columns:1fr}}
</style>
<?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?><?php if(isset($_GET['payment_saved'])):?><div class="notice">Payment recorded and invoice balance updated.</div><?php endif;?><?php if(isset($_GET['voided'])):?><div class="notice">Invoice marked as void.</div><?php endif;?><?php if(($_GET['receipt_email']??'')==='sent'):?><div class="notice">Receipt email was accepted by the configured mail server.</div><?php elseif(($_GET['receipt_email']??'')==='failed'):?><div class="notice error">The receipt email could not be sent. Check SMTP configuration and the server error log.</div><?php endif;?>
<div class="invoice-actions"><a class="btn secondary" href="invoices.php">Back to invoices</a><button class="btn" type="button" onclick="window.print()">Print / Save PDF</button></div>
<article class="invoice-sheet">
<div class="invoice-head"><div><div class="invoice-brand"><?=e(!empty($company['trading_name'])?$company['trading_name']:($company['legal_name']??'Company'))?></div><?php if(!empty($company['trading_name'])&&!empty($company['legal_name'])):?><div><?=e($company['legal_name'])?></div><?php endif;?><div><?=nl2br(e($company['address']??''))?></div><div><?=e(trim(($company['city']??'').' '.($company['country']??'')))?></div><div><?=e($company['phone']??'')?><?=!empty($company['email'])?' · '.e($company['email']):''?></div><?php if(!empty($company['website'])):?><div><?=e($company['website'])?></div><?php endif;?></div>
<div class="invoice-meta"><h1>INVOICE</h1><strong><?=e($invoice['invoice_number'])?></strong><div class="label" style="margin-top:8px">Status: <?=e(ucfirst(str_replace('_',' ',$status)))?></div><div>Issued: <?=e($invoice['invoice_date'])?></div><div>Due: <?=e($invoice['due_date'])?></div></div></div>
<div class="invoice-columns"><div><div class="invoice-label">Bill to</div><strong><?=e($customer['organisation_name']?:$customer['contact_name']?:'Customer')?></strong><div><?=e($customer['contact_name']??'')?></div><div><?=e($customer['email']??'')?></div><div><?=e($customer['phone']??'')?></div></div><div><div class="invoice-label">Company registration &amp; tax</div><div>Registration: <?=e($company['company_registration_no']??'—')?></div><div>KRA PIN: <?=e($company['kra_pin']??'—')?></div><div>VAT PIN: <?=e($company['vat_pin']??'—')?></div></div></div>
<table><thead><tr><th>Description</th><th>Qty</th><th>Unit price (KES)</th><th>Amount (KES)</th></tr></thead><tbody><?php while($line=$lines->fetch_assoc()):?><tr><td><?=nl2br(e($line['description']))?></td><td><?=number_format((float)$line['quantity'],3)?></td><td><?=number_format((float)$line['unit_price'],2)?></td><td><?=number_format((float)$line['line_total'],2)?></td></tr><?php endwhile;?></tbody></table>
<div class="invoice-total"><div><span>Subtotal</span><strong>KES <?=number_format((float)$invoice['subtotal'],2)?></strong></div><div><span>VAT (<?=number_format((float)$invoice['vat_rate'],2)?>%)</span><strong>KES <?=number_format((float)$invoice['vat_amount'],2)?></strong></div><div class="grand"><span>Total</span><span>KES <?=number_format((float)$invoice['total_amount'],2)?></span></div><div><span>Amount paid</span><strong>KES <?=number_format((float)$invoice['amount_paid'],2)?></strong></div><div><span>Balance due</span><strong>KES <?=number_format((float)$invoice['balance_due'],2)?></strong></div></div>
<?php if(!empty($company['mpesa_till_number'])||!empty($company['payment_terms'])):?><div class="invoice-pay"><h3>Payment instructions</h3><?php if(!empty($company['mpesa_till_number'])):?><p><strong>M-Pesa Buy Goods Till:</strong> <?=e($company['mpesa_till_number'])?><?=!empty($company['mpesa_till_name'])?' · '.e($company['mpesa_till_name']):''?></p><?php endif;?><?php if(!empty($company['mpesa_account_reference'])):?><p><strong>Account / reference:</strong> <?=e($company['mpesa_account_reference']==='invoice_number'?$invoice['invoice_number']:$company['mpesa_account_reference'])?></p><?php endif;?><?php if(!empty($company['payment_terms'])):?><p><?=nl2br(e($company['payment_terms']))?></p><?php endif;?></div><?php endif;?>
<?php if(!empty($invoice['notes'])):?><p style="margin-top:22px"><strong>Notes:</strong><br><?=nl2br(e($invoice['notes']))?></p><?php endif;?><p style="text-align:center;color:#64748b;font-size:12px;margin-top:35px">Thank you for your business.</p></article>
<div class="panel no-print" style="max-width:900px;margin:20px auto"><h2>Record a payment</h2>
<?php if($invoice['status']==='void'||(float)$invoice['balance_due']<=0):?><p class="empty">No payment can be recorded. This invoice is fully paid or void.</p><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=$invoiceId?>"><input type="hidden" name="action" value="payment"><div class="form-grid"><div><label>Amount (KES)</label><input name="amount" type="number" min="0.01" max="<?=e(number_format((float)$invoice['balance_due'],2,'.',''))?>" step="0.01" required></div><div><label>Payment date</label><input name="payment_date" type="date" value="<?=e(date('Y-m-d'))?>" required></div><div><label>Method</label><select name="method"><option value="mpesa">M-Pesa</option><option value="bank">Bank transfer</option><option value="cash">Cash</option><option value="other">Other</option></select></div><div><label>Transaction / receipt reference</label><input name="reference" maxlength="120" placeholder="M-Pesa receipt code"></div><div class="wide"><label>Notes</label><input name="payment_notes" maxlength="500"></div></div><button class="btn" type="submit" style="margin-top:16px">Record payment</button></form><?php endif;?>
<h2 style="margin-top:28px">Payment history &amp; receipts</h2><div style="overflow-x:auto"><table><thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Receipt actions</th></tr></thead><tbody><?php if($payments->num_rows):while($p=$payments->fetch_assoc()):?><tr><td><strong><?=e($p['receipt_number'])?></strong></td><td><?=e($p['payment_date'])?></td><td><?=e(strtoupper($p['method']))?></td><td><?=e($p['reference']?:'—')?></td><td>KES <?=number_format((float)$p['amount'],2)?></td><td><div class="actions" style="flex-wrap:wrap"><a class="btn secondary" href="payment-receipt.php?payment_id=<?=(int)$p['id']?>" target="_blank" rel="noopener">Print / PDF</a><form method="post" class="actions" style="gap:6px;align-items:center"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=$invoiceId?>"><input type="hidden" name="action" value="email_receipt"><input type="hidden" name="payment_id" value="<?=(int)$p['id']?>"><input type="email" name="recipient_email" value="<?=e($customer['email']??'')?>" required maxlength="190" aria-label="Receipt recipient email" style="width:190px;padding:8px"><button class="btn" type="submit">Email receipt</button></form></div></td></tr><?php endwhile;else:?><tr><td colspan="6" class="empty">No payments recorded.</td></tr><?php endif;?></tbody></table></div>
<?php if($invoice['status']!=='void'&&(float)$invoice['amount_paid']<=0):?><form method="post" style="margin-top:24px" onsubmit="return confirm('Void this invoice? This action cannot be reversed.');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=$invoiceId?>"><input type="hidden" name="action" value="void"><label>Reason for voiding</label><input name="void_reason" maxlength="500" minlength="5" required><button class="btn danger" type="submit" style="margin-top:10px">Void unpaid invoice</button></form><?php endif;?>
</div>
<?php page_footer(); ?>