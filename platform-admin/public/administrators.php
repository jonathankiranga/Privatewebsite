<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner']);

$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $name = trim((string)($_POST['display_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'administrator');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || $name === '' || strlen($name) > 120) {
            $error = 'Enter a valid email address and display name.';
        } elseif (strlen($password) < 14) {
            $error = 'Administrator passwords must contain at least 14 characters.';
        } elseif (!in_array($role, ['owner','administrator','support','content'], true)) {
            $error = 'Choose a valid administrator role.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('INSERT INTO admin_users (email,password_hash,display_name,role) VALUES (?,?,?,?)');
                $stmt->bind_param('ssss', $email, $hash, $name, $role);
                $stmt->execute();
                $newId = (int)db()->insert_id;
                audit_event('admin.created', 'admin_user', $newId, ['role'=>$role]);
                redirect('administrators.php?saved=1');
            } catch (mysqli_sql_exception $ex) {
                if ((int)$ex->getCode() === 1062) $error = 'An administrator with this email already exists.';
                else { error_log('Administrator creation failed: '.$ex->getMessage()); $error = 'The administrator could not be created.'; }
            }
        }
    } elseif ($action === 'status') {
        $targetId = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        $current = current_admin();
        if ($targetId < 1 || (int)$current['id'] === $targetId && !$active) {
            $error = 'You cannot deactivate your own active session.';
        } else {
            $stmt = db()->prepare('SELECT role,is_active FROM admin_users WHERE id=?');
            $stmt->bind_param('i', $targetId);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            if (!$target) {
                $error = 'Administrator not found.';
            } elseif (!$active && $target['role'] === 'owner' && (int)$target['is_active'] === 1) {
                $owners = (int)db()->query("SELECT COUNT(*) AS total FROM admin_users WHERE role='owner' AND is_active=1")->fetch_assoc()['total'];
                if ($owners <= 1) $error = 'The last active owner cannot be deactivated.';
            }
            if ($error === '') {
                $stmt = db()->prepare('UPDATE admin_users SET is_active=? WHERE id=?');
                $stmt->bind_param('ii', $active, $targetId);
                $stmt->execute();
                audit_event('admin.status_changed', 'admin_user', $targetId, ['is_active'=>(bool)$active]);
                redirect('administrators.php?saved=1');
            }
        }
    }
}
if (isset($_GET['saved'])) $notice = 'Administrator changes have been saved.';
$admins = db()->query('SELECT id,email,display_name,role,is_active,last_login_at,created_at FROM admin_users ORDER BY role,email');
page_header('Administrators');
?>
<div class="eyebrow">Access control</div><h1 class="heading">Administrators</h1><p class="sub">Create administrator accounts and control their access to the platform back office.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel"><h2>Create administrator</h2>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
<div class="form-grid">
<div><label for="display_name">Display name</label><input id="display_name" name="display_name" maxlength="120" required></div>
<div><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" required></div>
<div><label for="password">Temporary password</label><input id="password" name="password" type="password" minlength="14" autocomplete="new-password" required></div>
<div><label for="role">Role</label><select id="role" name="role"><option value="administrator">Administrator</option><option value="support">Support</option><option value="content">Content</option><option value="owner">Owner</option></select></div>
</div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Create administrator</button></div>
</form></div>
<div class="panel"><h2>Administrator accounts</h2>
<table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Action</th></tr></thead><tbody>
<?php if ($admins->num_rows): while ($a=$admins->fetch_assoc()): ?>
<tr><td><?= e($a['display_name']) ?></td><td><?= e($a['email']) ?></td><td><?= e(ucfirst($a['role'])) ?></td><td><?= (int)$a['is_active'] ? 'Active' : 'Inactive' ?></td><td><?= e($a['last_login_at'] ?: 'Never') ?></td><td>
<?php if ((int)$a['id'] !== (int)current_admin()['id']): ?><form method="post" style="margin:0"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="is_active" value="<?= (int)$a['is_active'] ? 0 : 1 ?>"><button class="btn secondary" type="submit"><?= (int)$a['is_active'] ? 'Deactivate' : 'Activate' ?></button></form><?php else: ?><span class="label">Current account</span><?php endif; ?>
</td></tr>
<?php endwhile; else: ?><tr><td colspan="6" class="empty">No administrator accounts found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_footer(); ?>
