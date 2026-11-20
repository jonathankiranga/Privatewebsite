<?php
declare(strict_types=1);
require __DIR__ . '/platform-admin/src/public-bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=120');
$result=public_db()->query("SELECT slug,name,summary,tagline,hero_image_url,is_featured FROM platform_products WHERE status='published' ORDER BY is_featured DESC,sort_order ASC,name ASC");
$items=[];
while($row=$result->fetch_assoc()){
 $items[]=['slug'=>$row['slug'],'name'=>$row['name'],'summary'=>$row['summary'],'tagline'=>$row['tagline'],'image'=>$row['hero_image_url'],'featured'=>(bool)$row['is_featured'],'url'=>'/product.php?slug='.rawurlencode($row['slug'])];
}
echo json_encode(['products'=>$items],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
