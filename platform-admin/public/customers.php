<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator','support']);

$error = '';
$notice = '';
$canManageCustomers = in_array((current_admin()['role'] ?? ''), ['owner','administrator'], true);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_roles(['owner','administrator']);
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $contactName = trim((string)($_POST['contact_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $countryCode = strtoupper(trim((string)($_POST['country_code'] ?? '')));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $organisation = trim((string)($_POST['organisation_name'] ?? ''));
        $status = (string)($_POST['status'] ?? 'lead');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Enter a valid customer email address.';
        } elseif (strlen($organisation) > 190 || strlen($contactName) > 160 || strlen($phone) > 40 || ($countryCode !== '' && !preg_match('/^[A-Z]{2}$/', $countryCode)) || strlen($notes) > 20000 || !in_array($status, ['lead','active','suspended','closed'], true)) {
            $error = 'Check the organisation name and customer status.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE platform_customers SET email=?,contact_name=?,phone=?,country_code=?,organisation_name=?,notes=?,status=? WHERE id=?');
                    $stmt->bind_param('sssssssi', $email, $contactName, $phone, $countryCode, $organisation, $notes, $status, $id);
                    $stmt->execute();
                    audit_event('customer.updated', 'platform_customer', $id, ['status'=>$status]);
                } else {
                    $stmt = db()->prepare('INSERT INTO platform_customers (email,contact_name,phone,country_code,organisation_name,notes,status) VALUES (?,?,?,?,?,?,?)');
                    $stmt->bind_param('sssssss', $email, $contactName, $phone, $countryCode, $organisation, $notes, $status);
                    $stmt->execute();
                    $id = (int)db()->insert_id;
                    audit_event('customer.created', 'platform_customer', $id, ['status'=>$status]);
                }
                redirect('customers.php?saved=1');
            } catch (mysqli_sql_exception $ex) {
                if ((int)$ex->getCode() === 1062) $error = 'A customer record with this email already exists.';
                else { error_log('Customer save failed: '.$ex->getMessage()); $error = 'The customer could not be saved.'; }
            }
        }
    } elseif ($action === 'status' && $id > 0) {
        $status = (string)($_POST['status'] ?? '');
        if (in_array($status, ['lead','active','suspended','closed'], true)) {
            $stmt = db()->prepare('UPDATE platform_customers SET status=? WHERE id=?');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            audit_event('customer.status_changed', 'platform_customer', $id, ['status'=>$status]);
            redirect('customers.php?saved=1');
        }
    }
}
$editing = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = db()->prepare('SELECT id,email,contact_name,phone,country_code,organisation_name,notes,status FROM platform_customers WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc() ?: null;
}
if (isset($_GET['saved'])) $notice = 'Customer record saved.';
$search = trim((string)($_GET['q'] ?? ''));
if ($search !== '') {
    $like = '%'.$search.'%';
    $stmt = db()->prepare('SELECT id,email,contact_name,phone,country_code,organisation_name,notes,status,created_at FROM platform_customers WHERE email LIKE ? OR contact_name LIKE ? OR organisation_name LIKE ? ORDER BY created_at DESC LIMIT 200');
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $customers = $stmt->get_result();
} else {
    $customers = db()->query('SELECT id,email,contact_name,phone,country_code,organisation_name,notes,status,created_at FROM platform_customers ORDER BY created_at DESC LIMIT 200');
}
page_header('Customers');
?>
<div class="eyebrow">Customer records</div><h1 class="heading">Customers</h1><p class="sub">Maintain customer and organisation records. These records are not customer login accounts or subscriptions.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<?php if ($canManageCustomers): ?><div class="panel"><h2><?= $editing ? 'Edit customer record' : 'Add a customer record' ?></h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
<div class="form-grid"><div><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" required value="<?= e($editing['email'] ?? '') ?>"></div>
<div><label for="contact_name">Contact person</label><input id="contact_name" name="contact_name" maxlength="160" value="<?= e($editing['contact_name'] ?? '') ?>"></div>
<div><label for="phone">Phone number</label><input id="phone" name="phone" maxlength="40" value="<?= e($editing['phone'] ?? '') ?>"></div>
<div><label for="country_code">Country code (ISO 2-letter)</label><input id="country_code" name="country_code" maxlength="2" pattern="[A-Za-z]{2}" value="<?= e($editing['country_code'] ?? '') ?>"></div>
<div><label for="organisation_name">Organisation</label><input id="organisation_name" name="organisation_name" maxlength="190" value="<?= e($editing['organisation_name'] ?? '') ?>"></div>
<div><label for="status">Record status</label><select id="status" name="status"><?php foreach (['lead'=>'Lead','active'=>'Active','suspended'=>'Suspended','closed'=>'Closed'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= (($editing['status'] ?? 'lead')===$v)?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="wide"><label for="notes">Internal notes</label><textarea id="notes" name="notes" rows="3" maxlength="20000"><?= e($editing['notes'] ?? '') ?></textarea></div></div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Save customer</button><?php if ($editing): ?><a class="btn secondary" href="customers.php">Cancel</a><?php endif; ?></div></form></div><?php endif; ?>
<div class="panel"><div class="actions" style="justify-content:space-between"><h2>Customer directory</h2><form method="get" class="actions" style="margin:0"><input name="q" value="<?= e($search) ?>" placeholder="Search email or organisation" style="width:260px"><button class="btn secondary" type="submit">Search</button></form></div>
<table><thead><tr><th>Contact</th><th>Email / Phone</th><th>Organisation</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
<?php if ($customers->num_rows): while ($c=$customers->fetch_assoc()): ?><tr><td><?= e($c['contact_name'] ?: '—') ?></td><td><?= e($c['email']) ?><br><span class="label"><?= e($c['phone'] ?: '—') ?></span></td><td><?= e($c['organisation_name'] ?: '—') ?></td><td><?= e(ucfirst($c['status'])) ?></td><td><?= e($c['created_at']) ?></td><td><?php if ($canManageCustomers): ?><a class="btn secondary" href="customers.php?edit=<?= (int)$c['id'] ?>">Edit</a><?php endif; ?></td></tr><?php endwhile; else: ?><tr><td colspan="6" class="empty">No customer records found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_footer(); ?>
