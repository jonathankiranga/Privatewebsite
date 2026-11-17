<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator','content']);

$error = '';
$notice = '';
$editing = null;
$admin = current_admin();
$adminId = (int)$admin['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $title = trim((string)($_POST['title'] ?? ''));
        $summary = trim((string)($_POST['summary'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || $title === '' || strlen($title) > 220 || $summary === '' || strlen($summary) > 500 || $category === '' || strlen($category) > 100 || $body === '' || strlen($body) > 100000) {
            $error = 'Complete the article fields. Use a valid URL slug and stay within the field limits.';
        } elseif (!in_array($status, ['draft','published','archived'], true)) {
            $error = 'Choose a valid article status.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = db()->prepare("UPDATE platform_kb_articles SET slug=?,title=?,summary=?,category=?,body=?,status=?,updated_by_admin_id=?,published_at=IF(?='published',COALESCE(published_at,NOW()),NULL) WHERE id=?");
                    $stmt->bind_param('ssssssisi', $slug, $title, $summary, $category, $body, $status, $adminId, $status, $id);
                    $stmt->execute();
                    audit_event('kb_article.updated', 'kb_article', $id, ['status'=>$status]);
                } else {
                    $stmt = db()->prepare("INSERT INTO platform_kb_articles (slug,title,summary,category,body,status,created_by_admin_id,updated_by_admin_id,published_at) VALUES (?,?,?,?,?,?,?,?,IF(?='published',NOW(),NULL))");
                    $stmt->bind_param('ssssssiii', $slug, $title, $summary, $category, $body, $status, $adminId, $adminId, $status);
                    $stmt->execute();
                    $id = (int)db()->insert_id;
                    audit_event('kb_article.created', 'kb_article', $id, ['status'=>$status]);
                }
                redirect('knowledge-base.php?saved=1');
            } catch (mysqli_sql_exception $ex) {
                if ((int)$ex->getCode() === 1062) $error = 'That article URL slug is already in use.';
                else { error_log('Knowledge base save failed: '.$ex->getMessage()); $error = 'The article could not be saved.'; }
            }
        }
        $editing = ['id'=>$id,'slug'=>$slug,'title'=>$title,'summary'=>$summary,'category'=>$category,'body'=>$body,'status'=>$status];
    }
}
if (isset($_GET['edit']) && !$editing) {
    $id = (int)$_GET['edit'];
    $stmt = db()->prepare('SELECT id,slug,title,summary,category,body,status FROM platform_kb_articles WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc() ?: null;
}
if (isset($_GET['saved'])) $notice = 'Knowledge base article saved.';
$articles = db()->query('SELECT id,slug,title,category,status,updated_at FROM platform_kb_articles ORDER BY updated_at DESC LIMIT 300');
page_header('Knowledge base');
?>
<div class="eyebrow">Customer education</div><h1 class="heading">Knowledge base</h1><p class="sub">Write and publish accurate help articles, product guidance and troubleshooting instructions. Drafts are not visible to customers.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel"><h2><?= $editing ? 'Edit article' : 'Create article' ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
<div class="form-grid"><div><label for="title">Article title</label><input id="title" name="title" maxlength="220" required value="<?= e($editing['title'] ?? '') ?>"></div><div><label for="slug">URL slug</label><input id="slug" name="slug" maxlength="180" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= e($editing['slug'] ?? '') ?>"></div><div><label for="category">Category</label><input id="category" name="category" maxlength="100" required value="<?= e($editing['category'] ?? '') ?>"></div><div><label for="status">Publication status</label><select id="status" name="status"><?php foreach (['draft'=>'Draft','published'=>'Published','archived'=>'Archived'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= (($editing['status'] ?? 'draft')===$v)?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="wide"><label for="summary">Short summary</label><input id="summary" name="summary" maxlength="500" required value="<?= e($editing['summary'] ?? '') ?>"></div><div class="wide"><label for="body">Article content (plain text)</label><textarea id="body" name="body" rows="12" maxlength="100000" required><?= e($editing['body'] ?? '') ?></textarea><p class="label">Content is rendered as escaped plain text. HTML and scripts are not executed.</p></div></div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Save article</button><?php if ($editing): ?><a class="btn secondary" href="knowledge-base.php">Cancel</a><?php endif; ?><a class="btn secondary" href="https://cpanel.smarternowhosting.co.ke/help.php" target="_blank" rel="noopener">View customer help</a></div></form></div>
<div class="panel"><h2>Articles</h2><table><thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody><?php if ($articles->num_rows): while ($a=$articles->fetch_assoc()): ?><tr><td><?= e($a['title']) ?><br><span class="label"><?= e($a['slug']) ?></span></td><td><?= e($a['category']) ?></td><td><?= e(ucfirst($a['status'])) ?></td><td><?= e($a['updated_at']) ?></td><td><a class="btn secondary" href="knowledge-base.php?edit=<?= (int)$a['id'] ?>">Edit</a></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="empty">No help articles have been created yet.</td></tr><?php endif; ?></tbody></table></div>
<?php page_footer(); ?>
