<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/layout.php';
require_roles(['owner','administrator','content']);

$error = '';
$notice = '';
$editing = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $code = strtolower(trim((string)($_POST['plan_code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $summary = trim((string)($_POST['summary'] ?? ''));
        $features = trim((string)($_POST['features'] ?? ''));
        $priceInput = trim((string)($_POST['price'] ?? ''));
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'KES')));
        $interval = (string)($_POST['billing_interval'] ?? 'monthly');
        $published = isset($_POST['is_published']) ? 1 : 0;
        if ($productId < 1 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $code) || $name === '' || strlen($name) > 160 || $summary === '' || strlen($summary) > 500 || $features === '' || strlen($features) > 20000 || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $priceInput) || !preg_match('/^[A-Z]{3}$/', $currency) || !in_array($interval, ['monthly','quarterly','annual','one_time'], true)) {
            $error = 'Check the product, plan code, name, summary, features, price, currency and billing interval.';
        } else {
            $price = (float)$priceInput;
            try {
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE platform_subscription_plans SET product_id=?,plan_code=?,name=?,summary=?,features=?,price=?,currency=?,billing_interval=?,is_published=? WHERE id=?');
                    $stmt->bind_param('issssdssii', $productId, $code, $name, $summary, $features, $price, $currency, $interval, $published, $id);
                    $stmt->execute();
                    audit_event('plan.updated', 'subscription_plan', $id, ['published'=>(bool)$published]);
                } else {
                    $stmt = db()->prepare('INSERT INTO platform_subscription_plans (product_id,plan_code,name,summary,features,price,currency,billing_interval,is_published) VALUES (?,?,?,?,?,?,?,?,?)');
                    $stmt->bind_param('issssdssi', $productId, $code, $name, $summary, $features, $price, $currency, $interval, $published);
                    $stmt->execute();
                    $id = (int)db()->insert_id;
                    audit_event('plan.created', 'subscription_plan', $id, ['published'=>(bool)$published]);
                }
                redirect('plans.php?saved=1');
            } catch (mysqli_sql_exception $ex) {
                if ((int)$ex->getCode() === 1062) $error = 'That plan code is already used for this product.';
                else { error_log('Plan save failed: '.$ex->getMessage()); $error = 'The plan could not be saved.'; }
            }
        }
        $editing = ['id'=>$id,'product_id'=>$productId,'plan_code'=>$code,'name'=>$name,'summary'=>$summary,'features'=>$features,'price'=>$priceInput,'currency'=>$currency,'billing_interval'=>$interval,'is_published'=>$published];
    }
}
if (isset($_GET['edit']) && !$editing) {
    $id = (int)$_GET['edit'];
    $stmt = db()->prepare('SELECT id,product_id,plan_code,name,summary,features,price,currency,billing_interval,is_published FROM platform_subscription_plans WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc() ?: null;
}
if (isset($_GET['saved'])) $notice = 'Plan details saved.';
$products = db()->query('SELECT id,name FROM platform_products ORDER BY name');
$plans = db()->query('SELECT p.id,p.plan_code,p.name,p.summary,p.price,p.currency,p.billing_interval,p.is_published,pr.name AS product_name FROM platform_subscription_plans p INNER JOIN platform_products pr ON pr.id=p.product_id ORDER BY pr.name,p.name');
page_header('Subscription plans');
?>
<div class="eyebrow">Commercial catalogue</div><h1 class="heading">Subscription plans</h1><p class="sub">Enter only prices and terms you have approved. Publishing a plan makes it visible to customers as an enquiry option; it does not collect payment or activate a product licence.</p>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="panel"><h2><?= $editing ? 'Edit plan' : 'Create plan' ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
<div class="form-grid"><div><label for="product_id">Product</label><select id="product_id" name="product_id" required><option value="">Choose product</option><?php while ($product=$products->fetch_assoc()): ?><option value="<?= (int)$product['id'] ?>" <?= (int)($editing['product_id'] ?? 0)===(int)$product['id']?'selected':'' ?>><?= e($product['name']) ?></option><?php endwhile; ?></select></div>
<div><label for="plan_code">Plan code</label><input id="plan_code" name="plan_code" maxlength="100" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required value="<?= e($editing['plan_code'] ?? '') ?>"></div>
<div><label for="name">Plan name</label><input id="name" name="name" maxlength="160" required value="<?= e($editing['name'] ?? '') ?>"></div>
<div><label for="billing_interval">Billing interval</label><select id="billing_interval" name="billing_interval"><?php foreach (['monthly'=>'Monthly','quarterly'=>'Quarterly','annual'=>'Annual','one_time'=>'One time'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= (($editing['billing_interval'] ?? 'monthly')===$v)?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div><label for="price">Price</label><input id="price" name="price" inputmode="decimal" pattern="\d{1,10}(\.\d{1,2})?" required value="<?= e($editing['price'] ?? '') ?>"></div>
<div><label for="currency">Currency (ISO 4217)</label><input id="currency" name="currency" maxlength="3" pattern="[A-Za-z]{3}" required value="<?= e($editing['currency'] ?? 'KES') ?>"></div>
<div class="wide"><label for="summary">Short summary</label><input id="summary" name="summary" maxlength="500" required value="<?= e($editing['summary'] ?? '') ?>"></div>
<div class="wide"><label for="features">What's included (one item per line)</label><textarea id="features" name="features" rows="6" maxlength="20000" required><?= e($editing['features'] ?? '') ?></textarea></div>
<div><label><input type="checkbox" name="is_published" value="1" style="width:auto" <?= !empty($editing['is_published'])?'checked':'' ?>> Publish this plan</label></div></div>
<div class="actions" style="margin-top:18px"><button class="btn" type="submit">Save plan</button><?php if ($editing): ?><a class="btn secondary" href="plans.php">Cancel</a><?php endif; ?></div></form></div>
<div class="panel"><h2>Plans</h2><table><thead><tr><th>Product / Plan</th><th>Price</th><th>Interval</th><th>Status</th><th></th></tr></thead><tbody><?php if ($plans->num_rows): while ($plan=$plans->fetch_assoc()): ?><tr><td><strong><?= e($plan['product_name']) ?></strong><br><?= e($plan['name']) ?><br><span class="label"><?= e($plan['summary']) ?></span></td><td><?= e($plan['currency']) ?> <?= number_format((float)$plan['price'],2) ?></td><td><?= e(ucfirst(str_replace('_',' ',$plan['billing_interval']))) ?></td><td><?= (int)$plan['is_published'] ? 'Published' : 'Draft' ?></td><td><a class="btn secondary" href="plans.php?edit=<?= (int)$plan['id'] ?>">Edit</a></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="empty">No subscription plans have been configured.</td></tr><?php endif; ?></tbody></table></div>
<?php page_footer(); ?>
