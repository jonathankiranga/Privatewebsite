<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator','support']);

$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_roles(['owner','administrator']);
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $organisation = trim((string)($_POST['organisation_name'] ?? ''));
        $status = (string)($_POST['status'] ?? 'lead');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Enter a valid customer email address.';
        } elseif (strlen($organisation) > 190 || !in_array($status, ['lead','active','suspended','closed'], true)) {
            $error = 'Check the organisation name and customer status.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE platform_customers SET email=?,organisation_name=?,status=? WHERE id=?');
                    $stmt->bind_param('sssi', $email, $organisation, $status, $id);
                    $stmt->execute();
                    audit_event('customer.updated', 'platform_customer', $id, ['status'=>$status]);
                } else {
                    $stmt = db()->prepare('INSERT INTO platform_customers (email,organisation_name,status) VALUES (?,?,?)');
                    $stmt->bind_param('sss', $email, $organisation, $status);
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
    $stmt = db()->prepare('SELECT id,email,organisation_name,status FROM platform_customers WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc() ?: null;
}
if (isset($_GET['saved'])) $notice = 'Customer record saved.';
$search = trim((string)($_GET['q'] ?? ''));
if ($search !== '') {
    $like = '%'.$search.'%';
    $stmt = db()->prepare('SELECT id,email,organisation_name,status,created_at FROM platform_customers WHERE email LIKE ? OR organisation_name LIKE ? ORDER BY created_at DESC LIMIT 200');
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $customers = $stmt->get_result();
} else {
    $customers = db()->query('SELECT id,email,organisation_name,status,created_at FROM platform_customers ORDER BY created_at DESC LIMIT 200');
}
page_header('Customers');
?>
<div class="eyebrow">Customer records</div><h1 class="heading">Customers</h1><p class="sub">Maintain customer and organisation records. These records are not customer login accounts or subscriptions.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel"><h2><?= $editing ? 'Edit customer record' : 'Add customer record' ?></h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
<div class="form-grid"><div><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" required value="<?= e($editing['email'] ?? '') ?>"></div>
<div><label for="organisation_name">Organisation</label><input id="organisation_name" name="organisation_name" maxlength="190" value="<?= e($editing['organisation_name'] ?? '') ?>"></div>
<div><label for="status">Record status</label><select id="status" name="status"><?php foreach (['lead'=>'Lead','active'=>'Active','suspended'=>'Suspended','closed'=>'Closed'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= (($editing['status'] ?? 'lead')===$v)?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Save customer</button><?php if ($editing): ?><a class="btn secondary" href="customers.php">Cancel</a><?php endif; ?></div></form></div>
<div class="panel"><div class="actions" style="justify-content:space-between"><h2>Customer directory</h2><form method="get" class="actions" style="margin:0"><input name="q" value="<?= e($search) ?>" placeholder="Search email or organisation" style="width:260px"><button class="btn secondary" type="submit">Search</button></form></div>
<table><thead><tr><th>Email</th><th>Organisation</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
<?php if ($customers->num_rows): while ($c=$customers->fetch_assoc()): ?><tr><td><?= e($c['email']) ?></td><td><?= e($c['organisation_name'] ?: '—') ?></td><td><?= e(ucfirst($c['status'])) ?></td><td><?= e($c['created_at']) ?></td><td><a class="btn secondary" href="customers.php?edit=<?= (int)$c['id'] ?>">Edit</a></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="empty">No customer records found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_footer(); ?>
