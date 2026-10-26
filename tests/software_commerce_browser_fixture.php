<?php
/** Disposable installed-site software-commerce browser fixture. No gateway or mail traffic. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||count($argv)!==3)exit(2);
$root=realpath(__DIR__.'/../test-runtime/web/existing');
if(!$root||!is_file($root.'/includes/bootstrap.php')||!is_file($root.'/config.php'))exit(2);
define('CWP_BOOT',true);
require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
if(config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='ci_')exit(2);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/existing/index.php','REQUEST_METHOD'=>'GET'];
$operation=$argv[1];
$invoice=(int)$argv[2];
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';

if(in_array($operation,['sandbox-on','sandbox-off'],true)){
    require_once CWP_CODE_ROOT.'/app/billing/payments.php';
    if(!software_commerce_ready()||!payments_ready())throw new RuntimeException('Synthetic payment-page fixture requires prepared commerce and payments.');
    if($operation==='sandbox-off'){
        save_setting('software_gateway_mode','disabled');
        echo json_encode(['mode'=>'disabled'],JSON_THROW_ON_ERROR);
        exit;
    }
    $_SERVER['DOCUMENT_ROOT']=$root;
    start_session();
    $actor=one('SELECT auth_version FROM '.table('users').' WHERE id=1');
    if(!$actor)throw new RuntimeException('Synthetic owner is unavailable.');
    $_SESSION=['uid'=>1,'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $gateway=payments_gateway('sandbox');
    payments_save('sandbox',(int)$gateway['revision'],[
        'api_login_id'=>'SyntheticBrowser',
        'transaction_key'=>'1234567890ABCDEF',
        'signature_key'=>str_repeat('ab',64),
        'public_client_key'=>'Synthetic_Browser_Client_Key_2026',
    ],'Synthetic owner passphrase 2026');
    save_setting('software_gateway_mode','sandbox');
    echo json_encode(['mode'=>'sandbox'],JSON_THROW_ON_ERROR);
    exit;
}
if($operation!=='fulfill-asset'||$invoice<1)exit(2);

require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_fulfill.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';

$order=one('SELECT * FROM '.table('software_orders').' WHERE invoice_id=?',[$invoice]);
if(!$order)throw new RuntimeException('Synthetic browser software order not found.');
$i=ledger_invoice($invoice);
$balance=ledger_balance($i);
if($i['status']==='issued'){
    if($balance['paid_cents']!==0||$balance['due_cents']!==(int)$i['amount_cents'])throw new RuntimeException('Unexpected browser invoice balance.');
    db()->beginTransaction();
    try{
        ledger_insert($i,0,'gateway',(int)$i['amount_cents'],'authorize_net',
            'Synthetic browser acceptance payment; no gateway contacted',gmdate('Y-m-d'),null,null,
            hash('sha256','software-browser|'.$invoice));
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
            ['paid','Synthetic browser acceptance only',gmdate('Y-m-d H:i:s'),$invoice]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
$license=software_fulfill_paid_order($invoice);
if(!$license)$license=one('SELECT l.* FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE e.invoice_id=? ORDER BY e.unit_number LIMIT 1',[$invoice]);
if(!$license)throw new RuntimeException('Synthetic browser fulfillment produced no entitlement.');

$storage=software_asset_private_root();
$dir=$storage.DIRECTORY_SEPARATOR.'software-browser';
if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Synthetic asset directory unavailable.');
$file=$dir.DIRECTORY_SEPARATOR.'software-http-browser.zip';
if(!is_file($file)){
    $zip=new ZipArchive();
    if($zip->open($file,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Synthetic asset ZIP creation failed.');
    $zip->addFromString('README.txt',"Synthetic CajunWebPro software browser acceptance asset.\nNo production package or gateway data.\n");
    $zip->close();
}
$sha=hash_file('sha256',$file);$bytes=filesize($file);
if(!is_string($sha)||$bytes===false)throw new RuntimeException('Synthetic asset could not be verified.');
$version='browser-acceptance-1';
$asset=one('SELECT id FROM '.table('software_assets').' WHERE sku=? AND version=?',[$order['sku'],$version]);
if(!$asset){
    query('INSERT INTO '.table('software_assets').' (sku,version,title,file_path,sha256,byte_size,is_published,created_at) VALUES (?,?,?,?,?,?,1,?)',
        [$order['sku'],$version,'Synthetic browser acceptance download',
         software_asset_reference($storage,realpath($file)),$sha,$bytes,gmdate('Y-m-d H:i:s')]);
    $asset=['id'=>(int)db()->lastInsertId()];
}
echo json_encode([
    'invoice_id'=>$invoice,
    'asset_id'=>(int)$asset['id'],
    'license_id'=>(int)$license['id'],
    'sha256'=>$sha,
    'bytes'=>(int)$bytes,
    'domain'=>$order['domain'],
],JSON_THROW_ON_ERROR);
