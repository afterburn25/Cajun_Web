<?php
/** Authorized versioned software ZIP downloads; never expose storage paths. */
declare(strict_types=1);
define('CWP_BOOT',true);
require __DIR__.'/../includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
boot();
$client=studio_client();
if(!$client||support_current()||!software_commerce_ready()){http_response_code(403);exit;}
$id=$_GET['id']??null;
if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,18}$/D',$id)){http_response_code(404);exit;}
$asset=one('SELECT a.* FROM '.table('software_assets').' a WHERE a.id=? AND a.is_published=1',[(int)$id]);
if(!$asset){http_response_code(404);exit;}
$licenses=all('SELECT l.* FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.customer_id=? AND l.product_id=? AND l.is_active=1 AND l.is_master=0 LIMIT 100',
    [$client['id'],$asset['sku']]);
$allowed=false;
foreach($licenses as $license){
    $domain=one('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1 ORDER BY id LIMIT 1',[$license['id']]);
    if($domain&&software_grant_verdict($license,$domain['domain'])){$allowed=true;break;}
}
if(!$allowed){http_response_code(404);exit;}
$storage=realpath(config()['storage']??'');
$file=realpath($asset['file_path']);
if(!$storage||!$file||!str_starts_with(str_replace('\\','/',$file),rtrim(str_replace('\\','/',$storage),'/').'/')
    ||!is_file($file)||filesize($file)!==(int)$asset['byte_size']
    ||!hash_equals($asset['sha256'],hash_file('sha256',$file))){
    http_response_code(404);exit;
}
$filename=preg_replace('/[^a-zA-Z0-9._-]+/','-',(string)$asset['sku'].'-'.$asset['version']).'.zip';
query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',
    [$client['id'],'software.download','Asset '.$asset['id'].' downloaded',gmdate('Y-m-d H:i:s')]);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($file));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
readfile($file);
exit;
