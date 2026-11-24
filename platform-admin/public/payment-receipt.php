<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator']);
$paymentId=(int)($_GET['payment_id']??0);
if($paymentId<1){http_response_code(404);exit('Receipt not found.');}
$stmt=db()->prepare('SELECT p.id,p.invoice_id,p.receipt_number,p.payment_date,p.amount,p.method,p.reference,p.notes,i.invoice_number,i.total_amount,i.company_details_json,i.customer_details_json FROM platform_invoice_payments p INNER JOIN platform_invoices i ON i.id=p.invoice_id WHERE p.id=? LIMIT 1');
$stmt->bind_param('i',$paymentId);$stmt->execute();$payment=$stmt->get_result()->fetch_assoc();
if(!$payment){http_response_code(404);exit('Receipt not found.');}
$company=json_decode($payment['company_details_json'],true)?:[];$customer=json_decode($payment['customer_details_json'],true)?:[];
$paidStmt=db()->prepare('SELECT COALESCE(SUM(amount),0) AS paid_to_date FROM platform_invoice_payments WHERE invoice_id=(SELECT invoice_id FROM platform_invoice_payments WHERE id=?) AND id<=?');
$paidStmt->bind_param('ii',$paymentId,$paymentId);$paidStmt->execute();$paidToDate=(float)$paidStmt->get_result()->fetch_assoc()['paid_to_date'];
$balance=max(0,round((float)$payment['total_amount']-$paidToDate,2));
page_header('Receipt '.$payment['receipt_number']);
?>
<style>
.receipt-actions{max-width:760px;margin:20px auto;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
.receipt-sheet{max-width:760px;margin:0 auto 30px;background:#fff;border:1px solid #e5e9f0;border-radius:12px;padding:42px;box-shadow:0 8px 28px rgba(16,24,40,.06)}
.receipt-top{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #172033;padding-bottom:22px}
.receipt-title{text-align:right}.receipt-title h1{font-size:28px;margin:0 0 8px}.receipt-number{font-weight:800;color:#2457d6}
.receipt-parties{display:grid;grid-template-columns:1fr 1fr;gap:25px;margin:28px 0}
.receipt-label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#64748b;font-weight:700;margin-bottom:7px}
.receipt-row{display:flex;justify-content:space-between;gap:20px;padding:12px 0;border-bottom:1px solid #edf0f4}
.receipt-row strong{text-align:right}.receipt-amount{font-size:22px;font-weight:800}
.receipt-balance{margin-top:24px;padding:17px;background:#f2f6fc;border-radius:9px;display:flex;justify-content:space-between;gap:15px}
@media print{body{background:#fff!important}.side,.top,.receipt-actions{display:none!important}.shell,.main{display:block!important}.content{padding:0!important;max-width:none!important}.receipt-sheet{border:0;border-radius:0;box-shadow:none;margin:0 auto;padding:12mm;max-width:none}}
@media(max-width:600px){.receipt-sheet{padding:20px}.receipt-top{flex-direction:column}.receipt-title{text-align:left}.receipt-parties{grid-template-columns:1fr}}
</style>
<div class="receipt-actions"><a class="btn secondary" href="invoice.php?id=<?=(int)$payment['invoice_id']?>">Back to invoice</a><button class="btn" type="button" onclick="window.print()">Print / Save PDF</button></div>
<article class="receipt-sheet">
 <div class="receipt-top"><div><div style="font-size:22px;font-weight:800"><?=e(($company['trading_name']??'')?:($company['legal_name']??'Company'))?></div><?php if(!empty($company['legal_name'])&&!empty($company['trading_name'])):?><div><?=e($company['legal_name'])?></div><?php endif;?><div><?=nl2br(e($company['address']??''))?></div><div><?=e(trim(($company['city']??'').' '.($company['country']??'')))?></div><div><?=e($company['phone']??'')?><?=!empty($company['email'])?' · '.e($company['email']):''?></div><?php if(!empty($company['kra_pin'])):?><div>KRA PIN: <?=e($company['kra_pin'])?></div><?php endif;?></div><div class="receipt-title"><h1>PAYMENT RECEIPT</h1><div class="receipt-number"><?=e($payment['receipt_number'])?></div><div>Date: <?=e($payment['payment_date'])?></div><div>Invoice: <?=e($payment['invoice_number'])?></div></div></div>
 <div class="receipt-parties"><div><div class="receipt-label">Received from</div><strong><?=e(($customer['organisation_name']??'')?:($customer['contact_name']??'Customer'))?></strong><div><?=e($customer['contact_name']??'')?></div><div><?=e($customer['email']??'')?></div><div><?=e($customer['phone']??'')?></div></div><div><div class="receipt-label">Payment details</div><div>Method: <strong><?=e(strtoupper($payment['method']))?></strong></div><div>Reference: <strong><?=e($payment['reference']?:'—')?></strong></div></div></div>
 <div class="receipt-row"><span>Amount received</span><strong class="receipt-amount">KES <?=number_format((float)$payment['amount'],2)?></strong></div>
 <div class="receipt-row"><span>Invoice total</span><strong>KES <?=number_format((float)$payment['total_amount'],2)?></strong></div>
 <div class="receipt-row"><span>Total received to date</span><strong>KES <?=number_format($paidToDate,2)?></strong></div>
 <div class="receipt-balance"><strong>Outstanding balance after this payment</strong><strong>KES <?=number_format($balance,2)?></strong></div>
 <?php if(!empty($payment['notes'])):?><p style="margin-top:24px"><strong>Payment notes:</strong><br><?=nl2br(e($payment['notes']))?></p><?php endif;?>
 <p style="text-align:center;color:#64748b;font-size:12px;margin-top:38px">This receipt confirms payment received against the invoice shown above. Thank you for your business.</p>
</article>
<?php page_footer(); ?>
