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
        $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
        $stmt = db()->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES ('office_location', ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
        $stmt->bind_param('si', $officeLocation, $adminId);
        $stmt->execute();
        log_admin_audit('site_setting.updated', 'site_settings', 0, ['key' => 'office_location', 'value' => $officeLocation]);
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
    'plans' => (int)db()->query('SELECT COUNT(*) AS c FROM subscription_plans WHERE is_active = 1')->fetch_assoc()['c'],
    'customers' => (int)db()->query('SELECT COUNT(*) AS c FROM customers')->fetch_assoc()['c'],
    'open_tickets' => (int)db()->query("SELECT COUNT(*) AS c FROM support_tickets WHERE status IN ('open', 'in_progress')")->fetch_assoc()['c'],
    'new_enquiries' => (int)db()->query("SELECT COUNT(*) AS c FROM subscription_enquiries WHERE status = 'new'")->fetch_assoc()['c'],
    'published_articles' => (int)db()->query("SELECT COUNT(*) AS c FROM knowledge_articles WHERE status = 'published'")->fetch_assoc()['c'],
];
$recentTickets = db()->query('SELECT t.id, t.ticket_ref, t.subject, t.priority, t.status, t.created_at, c.full_name, c.email FROM support_tickets t LEFT JOIN customers c ON c.id = t.customer_id ORDER BY t.created_at DESC LIMIT 6')->fetch_all(MYSQLI_ASSOC);
$recentAudits = db()->query('SELECT a.action, a.entity_type, a.entity_id, a.detail, a.created_at, u.full_name FROM admin_audit_log a LEFT JOIN admin_users u ON u.id = a.admin_user_id ORDER BY a.created_at DESC LIMIT 8')->fetch_all(MYSQLI_ASSOC);
render_header('Overview', 'overview');
?>
<div class="grid">
  <div class="card"><div class="muted">Products</div><div class="kpi"><?= $counts['products'] ?></div></div>
  <div class="card"><div class="muted">Active Plans</div><div class="kpi"><?= $counts['plans'] ?></div></div>
  <div class="card"><div class="muted">Customers</div><div class="kpi"><?= $counts['customers'] ?></div></div>
  <div class="card"><div class="muted">Open Tickets</div><div class="kpi"><?= $counts['open_tickets'] ?></div></div>
  <div class="card"><div class="muted">New Enquiries</div><div class="kpi"><?= $counts['new_enquiries'] ?></div></div>
  <div class="card"><div class="muted">Published KB</div><div class="kpi"><?= $counts['published_articles'] ?></div></div>
</div>
<div class="card" style="margin-top:16px;">
  <h3>Website Office Location</h3>
  <?php if ($officeMessage): ?><p style="color:#0e7a70;font-weight:600;"><?= e($officeMessage) ?></p><?php endif; ?>
  <?php if ($officeError): ?><p style="color:#b42318;font-weight:600;"><?= e($officeError) ?></p><?php endif; ?>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_office_location">
    <input type="text" name="office_location" value="<?= e($currentOfficeLocation) ?>" maxlength="255" required style="flex:1;min-width:260px;padding:8px 12px;">
    <button type="submit" class="btn">Save Office Location</button>
  </form>
</div>
<div class="split">
  <div class="card"><h3>Recent Support Tickets</h3><?php if (!$recentTickets): ?><p class="muted">No support tickets logged yet.</p><?php else: ?><table><thead><tr><th>Ref</th><th>Subject</th><th>Customer</th><th>Status</th></tr></thead><tbody><?php foreach ($recentTickets as $ticket): ?><tr><td><a href="/tickets.php?id=<?= (int)$ticket['id'] ?>"><?= e($ticket['ticket_ref']) ?></a></td><td><?= e($ticket['subject']) ?></td><td><?= e($ticket['full_name'] ?: $ticket['email'] ?: 'Unassigned') ?></td><td><span class="badge"><?= e($ticket['status']) ?></span></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
  <div class="card"><h3>Recent Admin Audit Events</h3><?php if (!$recentAudits): ?><p class="muted">No audit events recorded yet.</p><?php else: ?><table><thead><tr><th>Action</th><th>By</th><th>Detail</th></tr></thead><tbody><?php foreach ($recentAudits as $audit): ?><tr><td><?= e($audit['action']) ?></td><td><?= e($audit['full_name'] ?: 'System') ?></td><td class="muted"><?= e(format_audit_detail($audit['detail'] ?? null)) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
</div>
<?php render_footer(); ?>
