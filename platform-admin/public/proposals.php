<?php
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/layout.php';
require_roles(['owner','administrator']);
$filter=(string)($_GET['status']??'');$allowed=['draft','sent','accepted','rejected','expired','withdrawn'];
if(in_array($filter,$allowed,true)){$st=db()->prepare('SELECT id,proposal_number,title,recipient_name,recipient_email,currency,total_amount,status,created_at FROM platform_proposals WHERE status=? ORDER BY created_at DESC LIMIT 300');$st->bind_param('s',$filter);$st->execute();$rows=$st->get_result();}
else{$rows=db()->query('SELECT id,proposal_number,title,recipient_name,recipient_email,currency,total_amount,status,created_at FROM platform_proposals ORDER BY created_at DESC LIMIT 300');}
page_header('Systems proposals');
?>
<div class="eyebrow">Sales documents</div><h1 class="heading">Systems proposals</h1><p class="sub">Prepare customer-specific proposals, send them from your configured company email, print to PDF and track customer decisions.</p><div class="actions"><a class="btn" href="proposal-create.php">Create proposal</a><?php foreach($allowed as $s):?><a class="btn secondary" href="proposals.php?status=<?=e($s)?>"><?=e(ucfirst($s))?></a><?php endforeach;?><a class="btn secondary" href="proposals.php">All</a></div>
<div class="panel"><table><thead><tr><th>Proposal</th><th>Recipient</th><th>Amount</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody><?php if($rows->num_rows):while($p=$rows->fetch_assoc()):?><tr><td><strong><?=e($p['proposal_number'])?></strong><br><?=e($p['title'])?></td><td><?=e($p['recipient_name'])?><br><span class="label"><?=e($p['recipient_email'])?></span></td><td><?=e($p['currency'])?> <?=number_format((float)$p['total_amount'],2)?></td><td><?=e(ucfirst($p['status']))?></td><td><?=e($p['created_at'])?></td><td><a class="btn secondary" href="proposal.php?id=<?=(int)$p['id']?>">Open</a></td></tr><?php endwhile;else:?><tr><td colspan="6" class="empty">No proposals have been created.</td></tr><?php endif;?></tbody></table></div>
<?php page_footer(); ?>
