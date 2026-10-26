<?php
/** Authorized versioned software ZIP downloads; never expose storage paths. */
declare(strict_types=1);
define('CWP_BOOT',true);
require __DIR__.'/../includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_asset_access.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';
boot();
$client=studio_client();
if(!$client||support_current()||!software_commerce_ready()){http_response_code(403);exit;}
$id=$_GET['id']??null;
if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,18}$/D',$id)){http_response_code(404);exit;}
$asset=one('SELECT a.* FROM '.table('software_assets').' a WHERE a.id=? AND a.is_published=1',[(int)$id]);
if(!$asset){http_response_code(404);exit;}
if(!software_asset_customer_authorized((int)$client['id'],(string)$asset['sku'])){http_response_code(404);exit;}
$file=software_asset_resolve_path((string)$asset['file_path']);
if(!$file||filesize($file)!==(int)$asset['byte_size']
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
