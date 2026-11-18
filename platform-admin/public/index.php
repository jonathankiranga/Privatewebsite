<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

require_admin();

$officeMessage = null;
$officeError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_office_location') {
    verify_csrf();
    $officeLocation = trim((string)($_POST['office_location'] ?? ''));
    if ($officeLocation === '' || mb_strlen($officeLocation) > 255) {
        $officeError = 'Please enter a valid office location (1 to 255 characters).';
    } else {
        $admin = current_admin();
        $adminId = (int)($admin['id'] ?? 0);
        $stmt = db()->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES ('office_location', ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
        $stmt->bind_param('si', $officeLocation, $adminId);
        $stmt->execute();
        audit_event('site_setting.updated', 'site_settings', 0, ['key' => 'office_location', 'value' => $officeLocation]);
        $officeMessage = 'Office location updated on the website.';
    }
}

$currentOfficeLocation = 'Kerugoya, Kirinyaga County, Kenya';
try {
    $locRes = db()->query("SELECT setting_value FROM site_settings WHERE setting_key = 'office_location' LIMIT 1");
    if ($locRes && ($locRow = $locRes->fetch_assoc()) && trim((string)$locRow['setting_value']) !== '') {
        $currentOfficeLocation = trim((string)$locRow['setting_value']);
    }
} catch (Throwable $e) {
    // Table may not exist yet before running 007_office_location.sql
}

$counts = [
    'products' => (int)db()->query('SELECT COUNT(*) AS c FROM platform_products')->fetch_assoc()['c'],
    'plans' => (int)db()->query('SELECT COUNT(*) AS c FROM platform_subscription_plans WHERE is_published = 1')->fetch_assoc()['c'], 
    'customers' => (int)db()->query('SELECT COUNT(*) AS c FROM platform_customers')->fetch_assoc()['c'], 
    'open_tickets' => (int)db()->query("SELECT COUNT(*) AS c FROM platform_support_tickets WHERE status IN ('open', 'in_progress')")->fetch_assoc()['c'], 
    'new_enquiries' => (int)db()->query("SELECT COUNT(*) AS c FROM platform_subscription_enquiries WHERE status = 'new'")->fetch_assoc()['c'], 
    'published_articles' => (int)db()->query("SELECT COUNT(*) AS c FROM platform_kb_articles WHERE status = 'published'")->fetch_assoc()['c'], 
];
$recentTickets = db()->query('SELECT t.id, t.subject, t.priority, t.status, t.created_at, c.contact_name AS full_name, c.email FROM platform_support_tickets t LEFT JOIN platform_customers c ON c.id = t.customer_id ORDER BY t.created_at DESC LIMIT 6')->fetch_all(MYSQLI_ASSOC);
$recentAudits = db()->query('SELECT a.event_type AS action, a.entity_type, a.entity_id, a.details_json AS detail, a.created_at, u.display_name AS full_name FROM admin_audit_events a LEFT JOIN admin_users u ON u.id = a.admin_user_id ORDER BY a.created_at DESC LIMIT 8')->fetch_all(MYSQLI_ASSOC);
page_header('Overview');
?>
<div class="cards">
  <div class="card"><div class="muted">Products</div><div class="kpi"><?= $counts['products'] ?></div></div>
  <div class="card"><div class="muted">Active Plans</div><div class="kpi"><?= $counts['plans'] ?></div></div>
  <div class="card"><div class="muted">Customers</div><div class="kpi"><?= $counts['customers'] ?></div></div>
  <div class="card"><div class="muted">Open Tickets</div><div class="kpi"><?= $counts['open_tickets'] ?></div></div>
  <div class="card"><div class="muted">New Enquiries</div><div class="kpi"><?= $counts['new_enquiries'] ?></div></div>
  <div class="card"><div class="muted">Published KB</div><div class="kpi"><?= $counts['published_articles'] ?></div></div>
</div>
<div class="panel">
  <h3>Website Office Location</h3>
  <?php if ($officeMessage): ?><p style="color:#0e7a70;font-weight:600;"><?= e($officeMessage) ?></p><?php endif; ?>
  <?php if ($officeError): ?><p style="color:#b42318;font-weight:600;"><?= e($officeError) ?></p><?php endif; ?>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_office_location">
    <input type="text" name="office_location" value="<?= e($currentOfficeLocation) ?>" maxlength="255" required style="flex:1;min-width:260px;padding:8px 12px;">
    <button type="submit" class="btn">Save Office Location</button>
  </form>
</div>
<div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:20px;">
  <div class="card"><h3>Recent Support Tickets</h3><?php if (!$recentTickets): ?><p class="muted">No support tickets logged yet.</p><?php else: ?><table><thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Status</th></tr></thead><tbody><?php foreach ($recentTickets as $ticket): ?><tr><td><a href="tickets.php?id=<?= (int)$ticket['id'] ?>">#<?= (int)$ticket['id'] ?></a></td><td><?= e($ticket['subject']) ?></td><td><?= e($ticket['full_name'] ?: $ticket['email'] ?: 'Unassigned') ?></td><td><span class="badge"><?= e($ticket['status']) ?></span></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
  <div class="card"><h3>Recent Admin Audit Events</h3><?php if (!$recentAudits): ?><p class="muted">No audit events recorded yet.</p><?php else: ?><table><thead><tr><th>Action</th><th>By</th><th>Detail</th></tr></thead><tbody><?php foreach ($recentAudits as $audit): ?><tr><td><?= e($audit['action']) ?></td><td><?= e($audit['full_name'] ?: 'System') ?></td><td class="muted"><?= e($audit['detail'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
</div>
<?php page_footer(); ?>
