<?php
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/layout.php';
require_roles(['owner','administrator']);
$error='';
$leadId=(int)($_GET['lead']??$_POST['lead_id']??0);
$lead=null;
if($leadId>0){
    $st=db()->prepare('SELECT * FROM platform_leads WHERE id=?');
    $st->bind_param('i',$leadId);
    $st->execute();
    $lead=$st->get_result()->fetch_assoc();
    if(!$lead){http_response_code(404);exit('Lead not found.');}
}
$products=db()->query("SELECT id,name FROM platform_products WHERE status='published' ORDER BY name");
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $title=trim((string)($_POST['title']??''));
    $recipientName=trim((string)($_POST['recipient_name']??''));
    $recipientEmail=strtolower(trim((string)($_POST['recipient_email']??'')));
    $organisation=trim((string)($_POST['recipient_organisation']??''));
    $phone=trim((string)($_POST['recipient_phone']??''));
    $intro=trim((string)($_POST['introduction']??''));
    $scope=trim((string)($_POST['scope_of_work']??''));
    $assumptions=trim((string)($_POST['assumptions']??''));
    $terms=trim((string)($_POST['terms']??''));
    $currency=strtoupper(trim((string)($_POST['currency']??'KES')));
    $vatInput=trim((string)($_POST['vat_rate']??'0'));
    $discountInput=trim((string)($_POST['discount_amount']??'0'));
    $vat=(float)$vatInput;
    $discount=(float)$discountInput;
    $valid=trim((string)($_POST['valid_until']??''));
    $productId=(int)($_POST['product_id']??0);
    $descriptions=is_array($_POST['item_description']??null)?$_POST['item_description']:[];
    $quantities=is_array($_POST['quantity']??null)?$_POST['quantity']:[];
    $prices=is_array($_POST['unit_price']??null)?$_POST['unit_price']:[];
    $lineItems=[];
    $subtotal=0.0;
    if(count($descriptions)>30){$error='A proposal can contain a maximum of 30 line items.';}
    for($i=0;$i<min(count($descriptions),30);$i++){
        $desc=trim((string)$descriptions[$i]);
        $qtyInput=trim((string)($quantities[$i]??''));
        $priceInput=trim((string)($prices[$i]??''));
        if($desc===''&&$qtyInput===''&&$priceInput===''){continue;}
        if($desc===''||strlen($desc)>500||!preg_match('/^\d{1,8}(\.\d{1,3})?$/',$qtyInput)||!preg_match('/^\d{1,12}(\.\d{1,2})?$/',$priceInput)||(float)$qtyInput<=0){
            $error='Each proposal line needs a description, a valid quantity greater than zero and a valid non-negative unit price.';
            break;
        }
        $qty=(float)$qtyInput;
        $price=(float)$priceInput;
        $lineTotal=round($qty*$price,2);
        $lineItems[]=['description'=>$desc,'quantity'=>$qty,'unit_price'=>$price,'line_total'=>$lineTotal];
        $subtotal+=$lineTotal;
    }
    if($error===''&&($title===''||strlen($title)>240||$recipientName===''||!filter_var($recipientEmail,FILTER_VALIDATE_EMAIL)||strlen($recipientName)>160||strlen($recipientEmail)>190||strlen($organisation)>190||strlen($phone)>60||strlen($currency)!==3||!preg_match('/^[A-Z]{3}$/',$currency)||!preg_match('/^\d{1,3}(\.\d{1,2})?$/',$vatInput)||$vat<0||$vat>100||!preg_match('/^\d{1,12}(\.\d{1,2})?$/',$discountInput)||$discount<0||count($lineItems)<1||strlen($intro)>20000||strlen($scope)>20000||strlen($assumptions)>10000||strlen($terms)>10000||($valid!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$valid)))){
        $error='Check the recipient details, proposal title, currency, VAT, discount and line items.';
    }
    if($error===''){
        $subtotal=round($subtotal,2);
        $discount=min($discount,$subtotal);
        $taxable=max(0,$subtotal-$discount);
        $vatAmount=round($taxable*$vat/100,2);
        $total=round($taxable+$vatAmount,2);
        $token=bin2hex(random_bytes(32));
        $temporary='TMP-'.strtoupper(bin2hex(random_bytes(6)));
        $status='draft';
        $adminId=(int)current_admin()['id'];
        $leadDb=$leadId>0?$leadId:null;
        $customerId=$lead&&$lead['customer_id']?(int)$lead['customer_id']:null;
        $validDb=$valid!==''?$valid:null;
        $productDb=$productId>0?$productId:null;
        $settings=db()->query('SELECT legal_name,trading_name,company_registration_no,kra_pin,vat_pin,address,city,country,phone,email,website,mpesa_till_number,mpesa_till_name FROM platform_invoice_settings WHERE id=1')->fetch_assoc();
        $companyJson=$settings?json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;
        try{
            db()->begin_transaction();
            $stmt=db()->prepare('INSERT INTO platform_proposals(lead_id,customer_id,proposal_number,public_token,title,recipient_name,recipient_email,recipient_organisation,recipient_phone,company_details_json,introduction,scope_of_work,assumptions,terms,currency,subtotal,discount_amount,vat_rate,vat_amount,total_amount,valid_until,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iisssssssssssssdddddssi',$leadDb,$customerId,$temporary,$token,$title,$recipientName,$recipientEmail,$organisation,$phone,$companyJson,$intro,$scope,$assumptions,$terms,$currency,$subtotal,$discount,$vat,$vatAmount,$total,$validDb,$status,$adminId);
            $stmt->execute();
            $proposalId=(int)db()->insert_id;
            $proposalNumber='SMD-PROP-'.date('Y').'-'.str_pad((string)$proposalId,5,'0',STR_PAD_LEFT);
            $u=db()->prepare('UPDATE platform_proposals SET proposal_number=? WHERE id=?');
            $u->bind_param('si',$proposalNumber,$proposalId);
            $u->execute();
            $item=db()->prepare('INSERT INTO platform_proposal_items(proposal_id,product_id,description,quantity,unit_price,line_total,sort_order) VALUES(?,?,?,?,?,?,?)');
            foreach($lineItems as $index=>$line){
                $sortOrder=$index+1;
                $item->bind_param('iisdddi',$proposalId,$productDb,$line['description'],$line['quantity'],$line['unit_price'],$line['line_total'],$sortOrder);
                $item->execute();
            }
            if($leadId>0){
                $u=db()->prepare("UPDATE platform_leads SET status='qualified' WHERE id=? AND status IN ('new','contacted')");
                $u->bind_param('i',$leadId);
                $u->execute();
                $detail='Proposal '.$proposalNumber.' created in draft status.';
                $a=db()->prepare("INSERT INTO platform_lead_activities(lead_id,admin_user_id,activity_type,subject,details) VALUES(?,?,'note','Proposal prepared',?)");
                $a->bind_param('iis',$leadId,$adminId,$detail);
                $a->execute();
            }
            db()->commit();
            audit_event('proposal.created','proposal',$proposalId,['proposal_number'=>$proposalNumber]);
            redirect('proposal.php?id='.$proposalId);
        }catch(Throwable $e){
            db()->rollback();
            error_log('Proposal creation failed: '.$e->getMessage());
            $error='The proposal could not be created. Check the server log.';
        }
    }
}
page_header('Create proposal');
?>
<div class="eyebrow">Sales documents</div><h1 class="heading">Prepare systems proposal</h1><p class="sub">Create a customer-specific proposal with multiple priced items. Company details are copied from Billing → Invoice settings. You can review, print or email the proposal from your configured SmarterNow SMTP mailbox.</p>
<?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<div class="panel"><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="lead_id" value="<?=$leadId?>">
<div class="form-grid"><div class="wide"><label>Proposal title</label><input name="title" maxlength="240" required value="<?=e($lead?'Systems proposal for '.$lead['organisation']:'Systems implementation proposal')?>"></div>
<div><label>Recipient name</label><input name="recipient_name" maxlength="160" required value="<?=e($lead['name']??'')?>"></div><div><label>Recipient email</label><input type="email" name="recipient_email" maxlength="190" required value="<?=e($lead['email']??'')?>"></div>
<div><label>Organisation</label><input name="recipient_organisation" maxlength="190" value="<?=e($lead['organisation']??'')?>"></div><div><label>Phone</label><input name="recipient_phone" maxlength="60" value="<?=e($lead['phone']??'')?>"></div>
<div><label>Related product</label><select name="product_id"><option value="0">Custom proposal</option><?php while($p=$products->fetch_assoc()):?><option value="<?=(int)$p['id']?>" <?=((int)($lead['product_id']??0)===(int)$p['id'])?'selected':''?>><?=e($p['name'])?></option><?php endwhile;?></select></div><div><label>Currency</label><input name="currency" maxlength="3" value="KES" required></div>
<div class="wide"><label>Introduction</label><textarea name="introduction" rows="4" placeholder="Thank the customer and summarise the business need."></textarea></div>
<div class="wide"><label>Scope of work / proposed solution</label><textarea name="scope_of_work" rows="6" placeholder="Describe the modules, implementation activities, configuration and deliverables."></textarea></div>
<div class="wide"><label>Commercial line items</label><div id="proposal-items"><div class="proposal-item" style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin:8px 0"><label>Description</label><input name="item_description[]" maxlength="500" required placeholder="Software licence, implementation, training..."><div class="form-grid"><div><label>Quantity</label><input name="quantity[]" type="number" min="0.001" step="0.001" value="1" required></div><div><label>Unit price</label><input name="unit_price[]" type="number" min="0" step="0.01" value="0" required></div></div></div></div><button class="btn secondary" type="button" id="add-proposal-item">Add another line</button></div>
<div><label>Discount amount</label><input name="discount_amount" type="number" min="0" step="0.01" value="0"></div><div><label>VAT rate (%)</label><input name="vat_rate" type="number" min="0" max="100" step="0.01" value="0"></div><div><label>Valid until</label><input name="valid_until" type="date"></div>
<div class="wide"><label>Assumptions / exclusions</label><textarea name="assumptions" rows="3"></textarea></div><div class="wide"><label>Commercial terms and payment schedule</label><textarea name="terms" rows="4" placeholder="Enter payment milestones, support period and other agreed terms."></textarea></div></div><button class="btn" type="submit">Create proposal</button></form></div>
<script>
(function(){var wrap=document.getElementById('proposal-items');var add=document.getElementById('add-proposal-item');if(!wrap||!add)return;add.addEventListener('click',function(){if(wrap.querySelectorAll('.proposal-item').length>=30){alert('A proposal can contain up to 30 line items.');return;}var row=document.createElement('div');row.className='proposal-item';row.style.cssText='border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin:8px 0';row.innerHTML='<label>Description</label><input name="item_description[]" maxlength="500" required><div class="form-grid"><div><label>Quantity</label><input name="quantity[]" type="number" min="0.001" step="0.001" value="1" required></div><div><label>Unit price</label><input name="unit_price[]" type="number" min="0" step="0.01" value="0" required></div></div><button class="btn danger remove-proposal-item" type="button">Remove line</button>';row.querySelector('.remove-proposal-item').addEventListener('click',function(){row.remove();});wrap.appendChild(row);});})();
</script>
<?php page_footer(); ?>
