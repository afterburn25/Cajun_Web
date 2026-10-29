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
$activeSupport=false;
if(function_exists('support_ready')&&function_exists('support_binding')&&support_ready()&&support_binding()!==''){
    $activeSupport=(bool)one('SELECT id FROM '.table('support_sessions').' WHERE binding_hash=? AND ended_at IS NULL LIMIT 1',
        [support_binding()]);
}
if(!$client||support_current()||$activeSupport||!software_commerce_ready()){http_response_code(403);exit;}
$id=$_GET['id']??null;$licenseId=$_GET['license']??null;
if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,18}$/D',$id)
    ||!is_string($licenseId)||!preg_match('/^[1-9][0-9]{0,9}$/D',$licenseId)){http_response_code(404);exit;}
$asset=one('SELECT a.* FROM '.table('software_assets').' a WHERE a.id=? AND a.is_published=1',[(int)$id]);
if(!$asset){http_response_code(404);exit;}
$entitlement=software_asset_entitlement_authorized((int)$client['id'],(int)$licenseId,$asset);
if(!$entitlement){http_response_code(404);exit;}
$file=software_asset_resolve_path((string)$asset['file_path']);
if(!$file||filesize($file)!==(int)$asset['byte_size']
    ||!hash_equals($asset['sha256'],hash_file('sha256',$file))){
    http_response_code(404);exit;
}
$filename=preg_replace('/[^a-zA-Z0-9._-]+/','-',(string)$asset['sku'].'-'.$asset['version']).'.zip';
query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',
    [$client['id'],'software.download','Asset '.$asset['id'].' license '.(int)$entitlement['id'].
        ' invoice '.(int)($entitlement['invoice_id']??0).' unit '.(int)($entitlement['unit_number']??0).' downloaded',
        gmdate('Y-m-d H:i:s')]);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($file));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
readfile($file);
exit;
