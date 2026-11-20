<?php
declare(strict_types=1);
require __DIR__.'/platform-admin/src/public-bootstrap.php';
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=900');
$rows=public_db()->query("SELECT slug,updated_at FROM platform_products WHERE status='published' ORDER BY sort_order,name");
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
while($p=$rows->fetch_assoc()){
 $loc='https://smarternowhosting.co.ke/product.php?slug='.rawurlencode($p['slug']);
 echo '<url><loc>'.htmlspecialchars($loc,ENT_XML1|ENT_QUOTES,'UTF-8').'</loc><lastmod>'.htmlspecialchars(date('Y-m-d',strtotime($p['updated_at'])),ENT_XML1|ENT_QUOTES,'UTF-8').'</lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>'."\n";
}
echo '</urlset>';
