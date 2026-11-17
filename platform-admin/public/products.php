<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_admin();

$error = '';
$notice = '';
$editing = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $summary = trim((string)($_POST['summary'] ?? ''));
        $url = trim((string)($_POST['public_url'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || $name === '' || strlen($name) > 160 || $summary === '' || strlen($summary) > 500) {
            $error = 'Enter a valid product name, URL slug and summary (maximum 500 characters).';
        } elseif ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true))) {
            $error = 'The public URL must be a valid HTTP or HTTPS address.';
        } elseif (!in_array($status, ['draft','published','paused','retired'], true)) {
            $error = 'Choose a valid publication status.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE platform_products SET slug=?,name=?,summary=?,public_url=?,status=? WHERE id=?');
                    $stmt->bind_param('sssssi', $slug, $name, $summary, $url, $status, $id);
                    $stmt->execute();
                    audit_event('product.updated', 'platform_product', $id, ['slug'=>$slug,'status'=>$status]);
                } else {
                    $stmt = db()->prepare('INSERT INTO platform_products (slug,name,summary,public_url,status) VALUES (?,?,?,?,?)');
                    $stmt->bind_param('sssss', $slug, $name, $summary, $url, $status);
                    $stmt->execute();
                    $id = (int)db()->insert_id;
                    audit_event('product.created', 'platform_product', $id, ['slug'=>$slug,'status'=>$status]);
                }
                redirect('products.php?saved=1');
            } catch (mysqli_sql_exception $ex) {
                if ((int)$ex->getCode() === 1062) {
                    $error = 'That product URL slug is already in use.';
                } else {
                    error_log('Product save failed: '.$ex->getMessage());
                    $error = 'The product could not be saved. Check the server log.';
                }
            }
        }
        $editing = ['id'=>$id,'slug'=>$slug,'name'=>$name,'summary'=>$summary,'public_url'=>$url,'status'=>$status];
    } elseif ($action === 'status' && $id > 0) {
        $status = (string)($_POST['status'] ?? '');
        if (in_array($status, ['draft','published','paused','retired'], true)) {
            $stmt = db()->prepare('UPDATE platform_products SET status=? WHERE id=?');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            audit_event('product.status_changed', 'platform_product', $id, ['status'=>$status]);
            redirect('products.php?saved=1');
        }
    }
}
if (isset($_GET['edit']) && !$editing) {
    $id = (int)$_GET['edit'];
    $stmt = db()->prepare('SELECT id,slug,name,summary,public_url,status FROM platform_products WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc() ?: null;
}
if (isset($_GET['saved'])) $notice = 'Product changes have been saved.';
$products = db()->query('SELECT id,slug,name,summary,public_url,status,updated_at FROM platform_products ORDER BY name ASC');
page_header('Products');
?>
<div class="eyebrow">Catalogue management</div><h1 class="heading">Products</h1><p class="sub">Manage the products shown in the SmartNow platform catalogue. Publishing here does not automatically change the public marketing website.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel">
<h2><?= $editing ? 'Edit product' : 'Add a product' ?></h2>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
<div class="form-grid">
<div><label for="name">Product name</label><input id="name" name="name" maxlength="160" required value="<?= e($editing['name'] ?? '') ?>"></div>
<div><label for="slug">URL slug</label><input id="slug" name="slug" maxlength="100" pattern="[a-z0-9]+([-][a-z0-9]+)*" required value="<?= e($editing['slug'] ?? '') ?>" placeholder="school-management"></div>
<div class="wide"><label for="summary">Short description</label><textarea id="summary" name="summary" maxlength="500" rows="3" required><?= e($editing['summary'] ?? '') ?></textarea></div>
<div><label for="public_url">Public product URL (optional)</label><input id="public_url" name="public_url" type="url" maxlength="500" value="<?= e($editing['public_url'] ?? '') ?>" placeholder="https://..."></div>
<div><label for="status">Status</label><select id="status" name="status"><?php foreach (['draft'=>'Draft','published'=>'Published','paused'=>'Paused','retired'=>'Retired'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= (($editing['status'] ?? 'draft')===$value)?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
</div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Save product</button><?php if ($editing): ?><a class="btn secondary" href="products.php">Cancel</a><?php endif; ?></div>
</form></div>
<div class="panel"><h2>Product catalogue</h2><table><thead><tr><th>Product</th><th>Slug</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
<?php if ($products->num_rows): while ($p=$products->fetch_assoc()): ?><tr><td><strong><?= e($p['name']) ?></strong><br><span class="label"><?= e($p['summary']) ?></span></td><td><?= e($p['slug']) ?></td><td><?= e(ucfirst($p['status'])) ?></td><td><?= e($p['updated_at']) ?></td><td><a class="btn secondary" href="products.php?edit=<?= (int)$p['id'] ?>">Edit</a></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="empty">No products have been added to this database yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_footer(); ?>
