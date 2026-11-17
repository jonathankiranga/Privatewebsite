<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_admin();

$metrics = [];
$queries = [
    'products' => 'SELECT COUNT(*) AS total FROM platform_products',
    'published' => "SELECT COUNT(*) AS total FROM platform_products WHERE status='published'",
    'customers' => 'SELECT COUNT(*) AS total FROM platform_customers',
    'open_tickets' => "SELECT COUNT(*) AS total FROM platform_support_tickets WHERE status IN ('open','in_progress','waiting_customer')"
];
foreach ($queries as $key => $sql) {
    $metrics[$key] = (int)db()->query($sql)->fetch_assoc()['total'];
}
$recent = db()->query('SELECT event_type,entity_type,entity_id,created_at FROM admin_audit_events ORDER BY id DESC LIMIT 8');
page_header('Overview');
?>
<div class="eyebrow">Platform operations</div><h1 class="heading">Administration overview</h1><p class="sub">Live totals from the SmartNow platform administration database.</p>
<div class="cards">
<div class="card"><div class="label">Products in catalogue</div><div class="metric"><?= $metrics['products'] ?></div></div>
<div class="card"><div class="label">Published products</div><div class="metric"><?= $metrics['published'] ?></div></div>
<div class="card"><div class="label">Customer records</div><div class="metric"><?= $metrics['customers'] ?></div></div>
<div class="card"><div class="label">Open support tickets</div><div class="metric"><?= $metrics['open_tickets'] ?></div></div>
</div>
<div class="panel"><div class="actions" style="justify-content:space-between"><h2>Recent administrator activity</h2><a class="btn secondary" href="products.php">Manage products</a></div>
<table><thead><tr><th>Event</th><th>Record</th><th>Date</th></tr></thead><tbody>
<?php if ($recent->num_rows): while ($row = $recent->fetch_assoc()): ?><tr><td><?= e($row['event_type']) ?></td><td><?= e($row['entity_type'] ?: '—') ?> <?= $row['entity_id'] ? '#'.(int)$row['entity_id'] : '' ?></td><td><?= e($row['created_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="3" class="empty">No recorded activity yet.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="notice" style="margin-top:20px">Customer accounts, billing, M-Pesa, support workflows and analytics are not enabled yet. The figures above are database counts, not live subscriber or revenue statistics.</div>
<?php page_footer(); ?>
