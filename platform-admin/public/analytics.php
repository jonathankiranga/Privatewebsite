<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator']);
$range = (int)($_GET['days'] ?? 30);
if (!in_array($range, [7,30,90], true)) $range = 30;
$summary = db()->prepare("SELECT COUNT(*) AS page_views,COUNT(DISTINCT visitor_hash) AS visitors,COUNT(DISTINCT DATE(created_at)) AS active_days FROM platform_visitor_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)");
$summary->bind_param('i', $range); $summary->execute(); $totals = $summary->get_result()->fetch_assoc();
$pages = db()->prepare('SELECT page_path,COUNT(*) AS views,COUNT(DISTINCT visitor_hash) AS visitors FROM platform_visitor_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL ? DAY) GROUP BY page_path ORDER BY views DESC LIMIT 15');
$pages->bind_param('i', $range); $pages->execute(); $popular = $pages->get_result();
$sources = db()->prepare("SELECT COALESCE(NULLIF(referrer_host,''),'Direct / unknown') AS source,COUNT(*) AS visits FROM platform_visitor_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL ? DAY) GROUP BY source ORDER BY visits DESC LIMIT 15");
$sources->bind_param('i', $range); $sources->execute(); $referrers = $sources->get_result();
$devices = db()->prepare('SELECT device_type,COUNT(*) AS visits FROM platform_visitor_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL ? DAY) GROUP BY device_type ORDER BY visits DESC');
$devices->bind_param('i', $range); $devices->execute(); $deviceRows = $devices->get_result();
$recent = db()->query('SELECT page_path,device_type,browser_family,created_at FROM platform_visitor_events ORDER BY id DESC LIMIT 30');
page_header('Visitor analytics');
?>
<div class="eyebrow">Website performance</div><h1 class="heading">Visitor analytics</h1><p class="sub">Consent-based first-party page-view reporting. These are pseudonymous visitors, not verified identities or demographic profiles.</p>
<div class="actions" style="margin-bottom:18px"><a class="btn secondary" href="analytics.php?days=7">7 days</a><a class="btn secondary" href="analytics.php?days=30">30 days</a><a class="btn secondary" href="analytics.php?days=90">90 days</a><span class="label">Current range: <?= (int)$range ?> days</span></div>
<div class="cards"><div class="panel"><div class="label">Page views</div><div class="heading"><?= number_format((int)$totals['page_views']) ?></div></div><div class="panel"><div class="label">Pseudonymous visitors</div><div class="heading"><?= number_format((int)$totals['visitors']) ?></div></div><div class="panel"><div class="label">Days with activity</div><div class="heading"><?= number_format((int)$totals['active_days']) ?></div></div></div>
<div class="panel"><h2>Popular pages</h2><table><thead><tr><th>Page</th><th>Views</th><th>Visitors</th></tr></thead><tbody><?php if ($popular->num_rows): while($r=$popular->fetch_assoc()): ?><tr><td><?= e($r['page_path']) ?></td><td><?= number_format((int)$r['views']) ?></td><td><?= number_format((int)$r['visitors']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="3" class="empty">No consented visits recorded in this period.</td></tr><?php endif; ?></tbody></table></div>
<div class="cards"><div class="panel"><h2>Referral sources</h2><table><tbody><?php if ($referrers->num_rows): while($r=$referrers->fetch_assoc()): ?><tr><td><?= e($r['source']) ?></td><td><?= number_format((int)$r['visits']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="2" class="empty">No referral data yet.</td></tr><?php endif; ?></tbody></table></div><div class="panel"><h2>Devices</h2><table><tbody><?php if ($deviceRows->num_rows): while($r=$deviceRows->fetch_assoc()): ?><tr><td><?= e(ucfirst($r['device_type'])) ?></td><td><?= number_format((int)$r['visits']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="2" class="empty">No device data yet.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><h2>Recent page views</h2><p class="sub">The tracker does not collect names, email addresses, full IP addresses, exact locations, ages or genders.</p><table><thead><tr><th>Page</th><th>Device</th><th>Browser</th><th>Time</th></tr></thead><tbody><?php if ($recent->num_rows): while($r=$recent->fetch_assoc()): ?><tr><td><?= e($r['page_path']) ?></td><td><?= e(ucfirst($r['device_type'])) ?></td><td><?= e($r['browser_family']) ?></td><td><?= e($r['created_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="4" class="empty">No visitor activity recorded yet.</td></tr><?php endif; ?></tbody></table></div>
<?php page_footer(); ?>
