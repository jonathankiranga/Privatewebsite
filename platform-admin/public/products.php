<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator','content']);

$error = '';
$notice = '';
$editing = null;
function cms_video_url_is_valid(string $url): bool {
    if ($url === '') return true;
    if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return in_array($host, ['youtube.com','www.youtube.com','m.youtube.com','youtu.be','www.youtube-nocookie.com'], true);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $summary = trim((string)($_POST['summary'] ?? ''));
        $tagline = trim((string)($_POST['tagline'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $features = trim((string)($_POST['features'] ?? ''));
        $benefits = trim((string)($_POST['benefits'] ?? ''));
        $audience = trim((string)($_POST['target_audience'] ?? ''));
        $video = trim((string)($_POST['youtube_url'] ?? ''));
        $image = trim((string)($_POST['hero_image_url'] ?? ''));
        $metaTitle = trim((string)($_POST['meta_title'] ?? ''));
        $metaDescription = trim((string)($_POST['meta_description'] ?? ''));
        $url = trim((string)($_POST['public_url'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        $sort = (int)($_POST['sort_order'] ?? 0);
        $featured = isset($_POST['is_featured']) ? 1 : 0;
        $validImage = $image === '' || (filter_var($image, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($image, PHP_URL_SCHEME)), ['http','https'], true));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || $name === '' || strlen($name) > 160 ||
            $summary === '' || strlen($summary) > 500 || strlen($tagline) > 220 || strlen($audience) > 1000 ||
            strlen($metaTitle) > 190 || strlen($metaDescription) > 320 || strlen($image) > 500 || strlen($video) > 500 ||
            !$validImage || !cms_video_url_is_valid($video) ||
            ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true))) ||
            !in_array($status, ['draft','published','paused','retired'], true)) {
            $error = 'Check the product name, slug, summary, URLs, SEO fields and publication status.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE platform_products SET slug=?,name=?,summary=?,tagline=?,description=?,features=?,benefits=?,target_audience=?,youtube_url=?,hero_image_url=?,meta_title=?,meta_description=?,public_url=?,status=?,sort_order=?,is_featured=? WHERE id=?');
                    $stmt->bind_param('ssssssssssssssiii', $slug,$name,$summary,$tagline,$description,$features,$benefits,$audience,$video,$image,$metaTitle,$metaDescription,$url,$status,$sort,$featured,$id);
                    $stmt->execute();
                    audit_event('product.updated','platform_product',$id,['slug'=>$slug,'status'=>$status]);
                } else {
                    $stmt = db()->prepare('INSERT INTO platform_products (slug,name,summary,tagline,description,features,benefits,target_audience,youtube_url,hero_image_url,meta_title,meta_description,public_url,status,sort_order,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->bind_param('ssssssssssssssii', $slug,$name,$summary,$tagline,$description,$features,$benefits,$audience,$video,$image,$metaTitle,$metaDescription,$url,$status,$sort,$featured);
                    $stmt->execute();
                    $id = (int)db()->insert_id;
                    audit_event('product.created','platform_product',$id,['slug'=>$slug,'status'=>$status]);
                }
                redirect('products.php?saved=1');
            } catch (mysqli_sql_exception $ex) {
                if ((int)$ex->getCode() === 1062) $error = 'That product URL slug is already in use.';
                else { error_log('Product save failed: '.$ex->getMessage()); $error = 'The product could not be saved. Check the server log.'; }
            }
        }
        $editing = compact('id','slug','name','summary','tagline','description','features','benefits','audience','video','image','metaTitle','metaDescription','url','status','sort','featured');
        $editing = ['id'=>$id,'slug'=>$slug,'name'=>$name,'summary'=>$summary,'tagline'=>$tagline,'description'=>$description,'features'=>$features,'benefits'=>$benefits,'target_audience'=>$audience,'youtube_url'=>$video,'hero_image_url'=>$image,'meta_title'=>$metaTitle,'meta_description'=>$metaDescription,'public_url'=>$url,'status'=>$status,'sort_order'=>$sort,'is_featured'=>$featured];
    } elseif ($action === 'retire' && $id > 0) {
        $stmt=db()->prepare("UPDATE platform_products SET status='retired' WHERE id=?");
        $stmt->bind_param('i',$id); $stmt->execute();
        audit_event('product.retired','platform_product',$id);
        redirect('products.php?saved=1');
    } elseif ($action === 'status' && $id > 0) {
        $status=(string)($_POST['status'] ?? '');
        if (in_array($status,['draft','published','paused','retired'],true)) {
            $stmt=db()->prepare('UPDATE platform_products SET status=? WHERE id=?');
            $stmt->bind_param('si',$status,$id); $stmt->execute();
            audit_event('product.status_changed','platform_product',$id,['status'=>$status]);
            redirect('products.php?saved=1');
        }
    }
}
if (isset($_GET['edit']) && !$editing) {
    $id=(int)$_GET['edit'];
    $stmt=db()->prepare('SELECT * FROM platform_products WHERE id=?');
    $stmt->bind_param('i',$id); $stmt->execute();
    $editing=$stmt->get_result()->fetch_assoc() ?: null;
}
if (isset($_GET['saved'])) $notice='Product content has been saved. Published changes are available on the public website.';
$products=db()->query('SELECT id,slug,name,summary,status,updated_at FROM platform_products ORDER BY sort_order,name');
page_header('Website products');
?>
<div class="eyebrow">Website content management</div><h1 class="heading">Products</h1>
<p class="sub">Manage product pages, descriptions, features, images, SEO information and YouTube demonstrations. Retiring a product removes it from the public catalogue without deleting its proposal or enquiry history.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel">
<h2><?= $editing ? 'Edit product content' : 'Add a product' ?></h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
<div class="form-grid">
<div><label for="name">Product name</label><input id="name" name="name" maxlength="160" required value="<?= e($editing['name'] ?? '') ?>"></div>
<div><label for="slug">URL slug</label><input id="slug" name="slug" maxlength="100" pattern="[a-z0-9]+([-][a-z0-9]+)*" required value="<?= e($editing['slug'] ?? '') ?>" placeholder="smart-pos"></div>
<div class="wide"><label for="tagline">Headline / tagline</label><input id="tagline" name="tagline" maxlength="220" value="<?= e($editing['tagline'] ?? '') ?>"></div>
<div class="wide"><label for="summary">Short description (catalogue card)</label><textarea id="summary" name="summary" maxlength="500" rows="3" required><?= e($editing['summary'] ?? '') ?></textarea></div>
<div class="wide"><label for="description">Full product description</label><textarea id="description" name="description" rows="7"><?= e($editing['description'] ?? '') ?></textarea></div>
<div><label for="features">Features (one per line)</label><textarea id="features" name="features" rows="7"><?= e($editing['features'] ?? '') ?></textarea></div>
<div><label for="benefits">Business benefits (one per line)</label><textarea id="benefits" name="benefits" rows="7"><?= e($editing['benefits'] ?? '') ?></textarea></div>
<div class="wide"><label for="target_audience">Who should use this product?</label><textarea id="target_audience" name="target_audience" rows="2"><?= e($editing['target_audience'] ?? '') ?></textarea></div>
<div><label for="youtube_url">YouTube video URL</label><input id="youtube_url" name="youtube_url" type="url" maxlength="500" value="<?= e($editing['youtube_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=..."><small>Paste a normal YouTube or youtu.be link. The video will be embedded on the product page.</small></div>
<div><label for="hero_image_url">Product image URL</label><input id="hero_image_url" name="hero_image_url" type="url" maxlength="500" value="<?= e($editing['hero_image_url'] ?? '') ?>" placeholder="https://..."></div>
<div><label for="meta_title">SEO page title</label><input id="meta_title" name="meta_title" maxlength="190" value="<?= e($editing['meta_title'] ?? '') ?>"></div>
<div><label for="meta_description">SEO description</label><textarea id="meta_description" name="meta_description" maxlength="320" rows="2"><?= e($editing['meta_description'] ?? '') ?></textarea></div>
<div><label for="public_url">Public page URL (optional override)</label><input id="public_url" name="public_url" type="url" maxlength="500" value="<?= e($editing['public_url'] ?? '') ?>"></div>
<div><label for="status">Publication status</label><select id="status" name="status"><?php foreach (['draft'=>'Draft','published'=>'Published','paused'=>'Paused','retired'=>'Retired'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= (($editing['status'] ?? 'draft')===$v)?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div><label for="sort_order">Display order</label><input id="sort_order" name="sort_order" type="number" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></div>
<div><label><input type="checkbox" name="is_featured" value="1" style="width:auto" <?= !empty($editing['is_featured'])?'checked':'' ?>> Feature this product</label></div>
</div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Save product</button><?php if ($editing): ?><a class="btn secondary" href="products.php">Cancel</a><?php endif; ?></div>
</form></div>
<div class="panel"><h2>Live product catalogue</h2><table><thead><tr><th>Product</th><th>Slug</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
<?php if ($products->num_rows): while ($p=$products->fetch_assoc()): ?><tr><td><strong><?= e($p['name']) ?></strong><br><span class="label"><?= e($p['summary']) ?></span></td><td><?= e($p['slug']) ?></td><td><?= e(ucfirst($p['status'])) ?></td><td><?= e($p['updated_at']) ?></td><td><div class="actions"><a class="btn secondary" href="products.php?edit=<?= (int)$p['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Retire this product from the public catalogue? Its records will be retained.')" style="margin:0"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="retire"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger" type="submit">Remove</button></form></div></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="empty">No products have been added yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_footer(); ?>
