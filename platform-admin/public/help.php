<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';
$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$categories = customer_db()->query("SELECT DISTINCT category FROM platform_kb_articles WHERE status='published' ORDER BY category");
if ($q !== '' && $category !== '') {
    $like = '%'.$q.'%';
    $stmt = customer_db()->prepare("SELECT slug,title,summary,category,updated_at FROM platform_kb_articles WHERE status='published' AND category=? AND (title LIKE ? OR summary LIKE ? OR body LIKE ?) ORDER BY updated_at DESC LIMIT 100");
    $stmt->bind_param('ssss', $category, $like, $like, $like);
    $stmt->execute();
    $articles = $stmt->get_result();
} elseif ($q !== '') {
    $like = '%'.$q.'%';
    $stmt = customer_db()->prepare("SELECT slug,title,summary,category,updated_at FROM platform_kb_articles WHERE status='published' AND (title LIKE ? OR summary LIKE ? OR body LIKE ?) ORDER BY updated_at DESC LIMIT 100");
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $articles = $stmt->get_result();
} elseif ($category !== '') {
    $stmt = customer_db()->prepare("SELECT slug,title,summary,category,updated_at FROM platform_kb_articles WHERE status='published' AND category=? ORDER BY updated_at DESC LIMIT 100");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $articles = $stmt->get_result();
} else {
    $articles = customer_db()->query("SELECT slug,title,summary,category,updated_at FROM platform_kb_articles WHERE status='published' ORDER BY category,title LIMIT 100");
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Help centre · SmartNow Data Venture</title><meta name="description" content="Search SmartNow product help, guides and troubleshooting articles."><link rel="canonical" href="https://cpanel.smarternowhosting.co.ke/help.php"><style>*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#172033;font-family:Inter,system-ui,sans-serif}.wrap{max-width:1000px;margin:5vh auto;padding:20px}.head{background:#12223a;color:white;padding:30px;border-radius:16px}.head p{color:#c4cfde}.search{display:grid;grid-template-columns:1fr 220px auto;gap:10px;margin-top:22px}input,select{padding:12px;border:1px solid #cfd6e2;border-radius:8px;font:inherit;width:100%}.btn{padding:12px 18px;background:#2864e8;color:white;border:0;border-radius:8px;font-weight:700;text-decoration:none;cursor:pointer}.articles{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:22px}.article{display:block;background:white;border:1px solid #e5e9f0;border-radius:13px;padding:20px}.article h2{font-size:18px;margin:8px 0}.article p{color:#64748b;margin:0}.category{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#2864e8;font-weight:800}.nav{display:flex;justify-content:space-between;margin-bottom:18px}.nav a{color:#2864e8}.empty{padding:24px;background:white;border-radius:12px;color:#64748b;margin-top:20px}@media(max-width:700px){.search,.articles{grid-template-columns:1fr}.wrap{padding:12px}.head{padding:22px}}</style></head><body><div class="wrap"><div class="nav"><strong>SmartNow Help Centre</strong><a href="customer-login.php">Customer portal sign in</a></div><header class="head"><h1>How can we help?</h1><p>Search published product guides, support instructions and troubleshooting articles.</p><form class="search" method="get"><input name="q" value="<?= customer_e($q) ?>" placeholder="Search help articles"><select name="category"><option value="">All categories</option><?php while ($cat=$categories->fetch_assoc()): ?><option value="<?= customer_e($cat['category']) ?>" <?= $category===$cat['category']?'selected':'' ?>><?= customer_e($cat['category']) ?></option><?php endwhile; ?></select><button class="btn" type="submit">Search</button></form></header>
<?php if ($articles->num_rows): ?><div class="articles"><?php while ($a=$articles->fetch_assoc()): ?><a class="article" href="article.php?slug=<?= rawurlencode($a['slug']) ?>"><span class="category"><?= customer_e($a['category']) ?></span><h2><?= customer_e($a['title']) ?></h2><p><?= customer_e($a['summary']) ?></p></a><?php endwhile; ?></div><?php else: ?><div class="empty">No published articles match that search. You can <a href="customer-register.php">create a customer account</a> and contact support for assistance.</div><?php endif; ?>
</div></body></html>