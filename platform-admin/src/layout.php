<?php
function page_header(string $title): void
{
    $admin = current_admin();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · SmartNow Administration</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#172033;background:#f4f6fa;font-synthesis:none}
*{box-sizing:border-box}body{margin:0}.shell{min-height:100vh;display:grid;grid-template-columns:245px 1fr}.side{background:#101a2d;color:#dbe4f4;padding:26px 18px}.brand{font-weight:750;font-size:18px;color:#fff;margin:0 0 30px}.side a{display:block;color:#c6d2e5;text-decoration:none;padding:11px 12px;border-radius:9px;margin:4px 0}.side a:hover{background:#22314b;color:white}.side .muted{font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#8292ad;margin:24px 12px 8px}.main{min-width:0}.top{height:72px;background:#fff;border-bottom:1px solid #e5e9f0;display:flex;align-items:center;justify-content:space-between;padding:0 32px}.content{padding:32px;max-width:1250px}.eyebrow{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.12em}.heading{font-size:28px;margin:7px 0 8px}.sub{color:#64748b;margin:0 0 26px}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.card,.panel{background:#fff;border:1px solid #e6eaf0;border-radius:13px;padding:20px;box-shadow:0 3px 12px rgba(16,24,40,.03)}.metric{font-size:30px;font-weight:750;margin-top:10px}.label{font-size:13px;color:#64748b}.panel{margin-top:20px}.panel h2{font-size:16px;margin:0 0 14px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 10px;border-bottom:1px solid #edf0f4;font-size:14px}th{font-size:12px;color:#64748b;font-weight:650}input,select,textarea{width:100%;padding:11px 12px;border:1px solid #cfd6e2;border-radius:8px;font:inherit;background:#fff}label{display:block;font-size:13px;font-weight:650;margin:15px 0 7px}.btn{display:inline-block;background:#2457d6;color:white;border:0;border-radius:8px;padding:10px 15px;font-weight:650;text-decoration:none;cursor:pointer}.btn.secondary{background:#eaf0ff;color:#2149ad}.btn.danger{background:#a92e39}.actions{display:flex;gap:10px;align-items:center}.notice{padding:12px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin:0 0 18px;color:#1e40af}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}.form-grid .wide{grid-column:1/-1}.empty{color:#64748b;padding:20px 0}@media(max-width:900px){.shell{grid-template-columns:1fr}.side{padding:14px;display:flex;gap:6px;align-items:center;overflow:auto}.brand{margin:0 15px 0 0;white-space:nowrap}.side .muted{display:none}.side a{white-space:nowrap}.top{padding:0 18px}.content{padding:20px}.cards{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.cards,.form-grid{grid-template-columns:1fr}.form-grid .wide{grid-column:auto}.heading{font-size:23px}}
</style>
</head>
<body>
<div class="shell">
<aside class="side">
<div class="brand">SmartNow <span style="color:#8ea9ff">/ Admin</span></div>
<?php if ($admin): ?>
<a href="index.php">Overview</a>
<?php if (in_array($admin['role'] ?? '', ['owner','administrator','content'], true)): ?><a href="products.php">Products</a><?php endif; ?>
<?php if (($admin['role'] ?? '') === 'owner'): ?><a href="administrators.php">Administrators</a><?php endif; ?>
<?php if (in_array($admin['role'] ?? '', ['owner','administrator','support'], true)): ?><a href="customers.php">Customers</a><a href="tickets.php">Support tickets</a><?php endif; ?>
<div class="muted">Not yet available</div>
<span style="font-size:12px;color:#8292ad;padding:0 12px">Subscriptions · Payments · Analytics · Knowledge base</span>
<?php endif; ?>
</aside>
<section class="main">
<header class="top"><strong><?= e($title) ?></strong><div class="actions"><?php if ($admin): ?><span style="font-size:13px;color:#64748b"><?= e($admin['display_name']) ?></span><form method="post" action="logout.php" style="margin:0"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn secondary" type="submit">Sign out</button></form><?php endif; ?></div></header>
<main class="content">
<?php
}

function page_footer(): void
{
    ?>
</main></section></div></body></html>
<?php
}
