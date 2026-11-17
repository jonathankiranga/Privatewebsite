<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator','support']);

$error = '';
$notice = '';
$ticketId = (int)($_GET['ticket'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if ($ticketId < 1) {
        $error = 'Invalid ticket reference.';
    } elseif ($action === 'update') {
        $status = (string)($_POST['status'] ?? '');
        $priority = (string)($_POST['priority'] ?? '');
        $type = (string)($_POST['ticket_type'] ?? '');
        $assignedAdminInput = trim((string)($_POST['assigned_admin_id'] ?? ''));
        $assignedAdminId = $assignedAdminInput === '' ? null : (int)$assignedAdminInput;
        if ($assignedAdminInput !== '' && (!ctype_digit($assignedAdminInput) || (int)$assignedAdminInput < 1)) {
            $error = 'Choose a valid administrator assignment.';
        } elseif (!in_array($status, ['open','in_progress','waiting_customer','resolved','closed'], true) ||
            !in_array($priority, ['low','normal','high','urgent'], true) ||
            !in_array($type, ['support','bug','feature_request'], true)) {
            $error = 'Choose valid ticket status, priority and type.';
        } else {
            if ($assignedAdminId !== null) {
                $checkAdmin = db()->prepare('SELECT id FROM admin_users WHERE id=? AND is_active=1');
                $checkAdmin->bind_param('i', $assignedAdminId);
                $checkAdmin->execute();
                if (!$checkAdmin->get_result()->fetch_assoc()) {
                    $error = 'The selected administrator is not active.';
                }
            }
            if ($error === '') {
            $stmt = db()->prepare('UPDATE platform_support_tickets SET status=?,priority=?,ticket_type=?,assigned_admin_id=? WHERE id=?');
            $stmt->bind_param('ssssi', $status, $priority, $type, $assignedAdminId, $ticketId);
            $stmt->execute();
            audit_event('ticket.updated', 'support_ticket', $ticketId, ['status'=>$status,'priority'=>$priority,'type'=>$type,'assigned_admin_id'=>$assignedAdminId]);
            redirect('tickets.php?ticket='.$ticketId.'&saved=1');
            }
        }
    } elseif ($action === 'reply') {
        $message = trim((string)($_POST['message'] ?? ''));
        $internal = isset($_POST['is_internal']) ? 1 : 0;
        if ($message === '' || strlen($message) > 20000) {
            $error = 'Enter a reply of up to 20,000 characters.';
        } else {
            $exists = db()->prepare('SELECT id FROM platform_support_tickets WHERE id=?');
            $exists->bind_param('i', $ticketId);
            $exists->execute();
            if (!$exists->get_result()->fetch_assoc()) {
                $error = 'The ticket could not be found.';
            } else {
                $admin = current_admin();
                $adminId = (int)$admin['id'];
                $authorType = 'admin';
                $stmt = db()->prepare('INSERT INTO platform_ticket_messages (ticket_id,author_type,author_admin_id,message,is_internal) VALUES (?,?,?,?,?)');
                $stmt->bind_param('isisi', $ticketId, $authorType, $adminId, $message, $internal);
                $stmt->execute();
                audit_event('ticket.reply_added', 'support_ticket', $ticketId, ['internal'=>(bool)$internal]);
                redirect('tickets.php?ticket='.$ticketId.'&replied=1');
            }
        }
    }
}
if (isset($_GET['saved'])) $notice = 'Ticket details updated.';
if (isset($_GET['replied'])) $notice = 'Reply added to the ticket history.';
$selected = null;
$messages = null;
$activeAdmins = db()->query('SELECT id,display_name FROM admin_users WHERE is_active=1 ORDER BY display_name');
if ($ticketId > 0) {
    $stmt = db()->prepare('SELECT t.*,c.email AS customer_email,c.organisation_name,a.display_name AS assigned_admin_name FROM platform_support_tickets t LEFT JOIN platform_customers c ON c.id=t.customer_id LEFT JOIN admin_users a ON a.id=t.assigned_admin_id WHERE t.id=?');
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $selected = $stmt->get_result()->fetch_assoc() ?: null;
    if ($selected) {
        $stmt = db()->prepare('SELECT m.*,a.display_name FROM platform_ticket_messages m LEFT JOIN admin_users a ON a.id=m.author_admin_id WHERE m.ticket_id=? ORDER BY m.id ASC');
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $messages = $stmt->get_result();
    }
}
$statusFilter = (string)($_GET['status'] ?? '');
$validFilters = ['open','in_progress','waiting_customer','resolved','closed'];
if (in_array($statusFilter, $validFilters, true)) {
    $stmt = db()->prepare('SELECT t.id,t.subject,t.ticket_type,t.status,t.priority,t.created_at,COALESCE(c.email,t.requester_email) AS requester,a.display_name AS assigned_admin_name FROM platform_support_tickets t LEFT JOIN platform_customers c ON c.id=t.customer_id LEFT JOIN admin_users a ON a.id=t.assigned_admin_id WHERE t.status=? ORDER BY FIELD(t.priority,"urgent","high","normal","low"),t.updated_at DESC LIMIT 200');
    $stmt->bind_param('s', $statusFilter);
    $stmt->execute();
    $tickets = $stmt->get_result();
} else {
    $tickets = db()->query('SELECT t.id,t.subject,t.ticket_type,t.status,t.priority,t.created_at,COALESCE(c.email,t.requester_email) AS requester,a.display_name AS assigned_admin_name FROM platform_support_tickets t LEFT JOIN platform_customers c ON c.id=t.customer_id LEFT JOIN admin_users a ON a.id=t.assigned_admin_id ORDER BY FIELD(t.priority,"urgent","high","normal","low"),t.updated_at DESC LIMIT 200');
}
page_header('Support tickets');
?>
<div class="eyebrow">Customer service</div><h1 class="heading">Support tickets</h1><p class="sub">Review support requests, bug reports and feature suggestions. Replies are recorded in each ticket's history.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel"><div class="actions" style="justify-content:space-between"><h2>Ticket queue</h2><form method="get" class="actions" style="margin:0"><select name="status" style="width:190px"><option value="">All statuses</option><?php foreach (['open'=>'Open','in_progress'=>'In progress','waiting_customer'=>'Waiting for customer','resolved'=>'Resolved','closed'=>'Closed'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= $statusFilter===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn secondary">Filter</button></form></div>
<table><thead><tr><th>Ticket</th><th>Requester</th><th>Type</th><th>Priority</th><th>Status</th><th>Assigned to</th><th>Created</th></tr></thead><tbody>
<?php if ($tickets->num_rows): while ($t=$tickets->fetch_assoc()): ?><tr><td><a href="tickets.php?ticket=<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> · <?= e($t['subject']) ?></a></td><td><?= e($t['requester'] ?: 'Unlinked requester') ?></td><td><?= e(str_replace('_',' ',ucfirst($t['ticket_type']))) ?></td><td><?= e(ucfirst($t['priority'])) ?></td><td><?= e(str_replace('_',' ',ucfirst($t['status']))) ?></td><td><?= e($t['assigned_admin_name'] ?: 'Unassigned') ?></td><td><?= e($t['created_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="7" class="empty">No tickets found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($selected): ?>
<div class="panel"><div class="actions" style="justify-content:space-between"><h2>Ticket #<?= (int)$selected['id'] ?> — <?= e($selected['subject']) ?></h2><a class="btn secondary" href="tickets.php">Close detail</a></div>
<p class="label">Requester: <?= e($selected['customer_email'] ?: $selected['requester_email'] ?: 'Not supplied') ?> · Organisation: <?= e($selected['organisation_name'] ?: '—') ?></p>
<p><?= nl2br(e($selected['description'] ?: 'No original description was recorded.')) ?></p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="ticket_id" value="<?= (int)$selected['id'] ?>">
<div class="form-grid"><div><label>Status</label><select name="status"><?php foreach (['open'=>'Open','in_progress'=>'In progress','waiting_customer'=>'Waiting for customer','resolved'=>'Resolved','closed'=>'Closed'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= $selected['status']===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div><label>Priority</label><select name="priority"><?php foreach (['low','normal','high','urgent'] as $v): ?><option value="<?= e($v) ?>" <?= $selected['priority']===$v?'selected':'' ?>><?= e(ucfirst($v)) ?></option><?php endforeach; ?></select></div>
<div><label>Type</label><select name="ticket_type"><?php foreach (['support'=>'Support','bug'=>'Bug report','feature_request'=>'Feature suggestion'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= $selected['ticket_type']===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div><label>Assigned administrator</label><select name="assigned_admin_id"><option value="">Unassigned</option><?php while ($adminOption = $activeAdmins->fetch_assoc()): ?><option value="<?= (int)$adminOption['id'] ?>" <?= (int)($selected['assigned_admin_id'] ?? 0)===(int)$adminOption['id']?'selected':'' ?>><?= e($adminOption['display_name']) ?></option><?php endwhile; ?></select></div></div>
<div style="margin-top:16px"><button class="btn" type="submit">Update ticket</button></div></form>
</div>
<div class="panel"><h2>Conversation history</h2>
<?php if ($messages && $messages->num_rows): while ($m=$messages->fetch_assoc()): ?><div style="padding:14px 0;border-bottom:1px solid #edf0f4"><div class="actions"><strong><?= e($m['author_type']==='admin' ? ($m['display_name'] ?: 'Administrator') : ucfirst($m['author_type'])) ?></strong><span class="label"><?= e($m['created_at']) ?></span><?php if ((int)$m['is_internal']): ?><span class="label">Internal note</span><?php endif; ?></div><p><?= nl2br(e($m['message'])) ?></p></div><?php endwhile; else: ?><p class="empty">No replies or notes have been recorded yet.</p><?php endif; ?>
<form method="post" style="margin-top:20px"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= (int)$selected['id'] ?>"><label for="message">Add reply or internal note</label><textarea id="message" name="message" rows="5" maxlength="20000" required></textarea><label style="display:flex;align-items:center;gap:8px;font-weight:500"><input type="checkbox" name="is_internal" value="1" style="width:auto"> Internal note (not visible to customer)</label><button class="btn" type="submit" style="margin-top:12px">Add to history</button></form>
</div>
<?php elseif ($ticketId > 0): ?><div class="notice error">That ticket does not exist.</div><?php endif; ?>
<?php page_footer(); ?>
