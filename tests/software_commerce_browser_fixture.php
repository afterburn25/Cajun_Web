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

if(in_array($operation,['commerce-marker-off','commerce-marker-on'],true)){
    save_setting('software_commerce_version',$operation==='commerce-marker-on'?'3':'');
    echo json_encode(['software_commerce_version'=>(string)setting('software_commerce_version')],JSON_THROW_ON_ERROR);
    exit;
}
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
if($operation==='mail-off'){
    save_setting('email_mode','disabled');
    echo json_encode(['email_mode'=>'disabled'],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='legacy-bundle'){
    if($invoice<1)exit(2);
    require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';
    $entitlement=one('SELECT e.license_id,o.sku FROM '.table('software_entitlements').' e JOIN '.table('software_orders').' o ON o.invoice_id=e.invoice_id WHERE e.invoice_id=? ORDER BY e.unit_number LIMIT 1',[$invoice]);
    if(!$entitlement)throw new RuntimeException('Synthetic legacy-bundle fixture requires fulfilled software.');
    $asset=one('SELECT file_path,sha256 FROM '.table('software_assets').' WHERE sku=? ORDER BY id DESC LIMIT 1',[$entitlement['sku']]);
    if(!$asset)throw new RuntimeException('Synthetic legacy-bundle fixture requires a software asset.');
    $file=software_asset_resolve_path((string)$asset['file_path']);
    if(!$file)throw new RuntimeException('Synthetic legacy-bundle fixture asset path is unavailable.');
    query('INSERT INTO '.table('license_bundles').' (license_id,file_path,title,sha256,created_at) VALUES (?,?,?,?,?)',
        [(int)$entitlement['license_id'],$file,'Stale legacy software bundle',(string)$asset['sha256'],gmdate('Y-m-d H:i:s')]);
    echo json_encode(['bundle_id'=>(int)db()->lastInsertId(),'license_id'=>(int)$entitlement['license_id']],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='reconcile-review'){
    if($invoice<1)exit(2);
    require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
    $order=one('SELECT client_id,billing_term,created_at FROM '.table('software_orders').' WHERE invoice_id=?',[$invoice]);
    $i=ledger_invoice($invoice);
    if(!$order||$order['billing_term']!=='once'||$i['status']!=='issued')throw new RuntimeException('Synthetic reconcile fixture requires an issued one-time software order.');
    $now=gmdate('Y-m-d H:i:s');
    $request=hash('sha256','browser-uncertain-'.$invoice);
    query('INSERT INTO '.table('software_charges').' (invoice_id,client_id,amount_cents,status,request_key,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),request_key=VALUES(request_key),transaction_id=VALUES(transaction_id),updated_at=VALUES(updated_at)',
        [$invoice,(int)$order['client_id'],(int)$i['amount_cents'],'uncertain',$request,'650'.str_pad((string)$invoice,7,'0',STR_PAD_LEFT),$now,$now]);
    query('INSERT INTO '.table('software_charge_attempts').' (invoice_id,request_key,amount_cents,status,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),transaction_id=VALUES(transaction_id),updated_at=VALUES(updated_at)',
        [$invoice,$request,(int)$i['amount_cents'],'uncertain','650'.str_pad((string)$invoice,7,'0',STR_PAD_LEFT),$now,$now]);
    echo json_encode(['transaction_id'=>'650'.str_pad((string)$invoice,7,'0',STR_PAD_LEFT)],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='subscription-charge-review'){
    if($invoice<1)exit(2);
    $sub=one('SELECT id,status,gateway_subscription_id FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice]);
    if(!$sub)throw new RuntimeException('Synthetic recurring charge review requires a local subscription row.');
    $gatewayId='777'.str_pad((string)$invoice,7,'0',STR_PAD_LEFT);
    query('UPDATE '.table('software_subscriptions').' SET gateway_subscription_id=?,status=? WHERE id=?',
        [$gatewayId,'pending_first_payment',$sub['id']]);
    echo json_encode(['gateway_subscription_id'=>$gatewayId,'status'=>'pending_first_payment'],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='subscription-review'){
    if($invoice<1)exit(2);
    require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
    $order=one('SELECT client_id,billing_term,created_at FROM '.table('software_orders').' WHERE invoice_id=?',[$invoice]);
    $i=ledger_invoice($invoice);
    if(!$order||!in_array($order['billing_term'],['month','year'],true)||$i['status']!=='issued')throw new RuntimeException('Synthetic subscription-review fixture requires an issued recurring software order.');
    $months=$order['billing_term']==='year'?12:1;$now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),gateway_subscription_id=NULL',
        [$invoice,(int)$order['client_id'],$months,(int)$i['amount_cents'],'review',$order['created_at'],$now]);
    echo json_encode(['status'=>'review'],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='payment-review'){
    if($invoice<1)exit(2);
    require_once CWP_CODE_ROOT.'/app/billing/software_payment_review.php';
    $order=one('SELECT client_id FROM '.table('software_orders').' WHERE invoice_id=?',[$invoice]);
    $paid=one('SELECT amount_cents,status FROM '.table('studio_invoices').' WHERE id=?',[$invoice]);
    if(!$order||!$paid||$paid['status']!=='paid')throw new RuntimeException('Synthetic payment review requires a paid software invoice.');
    $original='61'.str_pad((string)$invoice,8,'0',STR_PAD_LEFT);
    $event='71'.str_pad((string)$invoice,8,'0',STR_PAD_LEFT);
    $charge=one('SELECT id,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice]);
    if(!$charge){
        $stamp=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('software_charges').' (invoice_id,client_id,amount_cents,status,request_key,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',
            [$invoice,(int)$order['client_id'],(int)$paid['amount_cents'],'paid',hash('sha256','browser-review-'.$invoice),$original,$stamp,$stamp]);
    }elseif((string)($charge['transaction_id']??'')!==$original){
        throw new RuntimeException('Synthetic payment review found an unexpected transaction identity.');
    }
    $state=software_payment_reversal_apply([
        'kind'=>'refund','transaction_id'=>$event,'original_transaction_id'=>$original,'amount_cents'=>500
    ]);
    echo json_encode(['original_transaction_id'=>$original,'reversal_transaction_id'=>$event,'state'=>$state],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='live-trial'){
    if($invoice<1)exit(2);
    require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
    $product=one('SELECT sku,family FROM '.table('catalog_products').' WHERE id=?',[$invoice]);
    if(!$product||$product['family']!=='software')throw new RuntimeException('Synthetic live-trial fixture requires a software product.');
    db()->beginTransaction();
    try{
        $trial=software_grant_create(1,(string)$product['sku'],'trial','Synthetic Buyer',
            'software-http.example',gmdate('Y-m-d H:i:s',time()+86400*7),1);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    $row=one('SELECT l.license_key,l.created_at,e.created_at AS entitlement_created,e.issued_by FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.id=?',[(int)$trial['id']]);
    echo json_encode(['license_key'=>$trial['key'],'license_id'=>(int)$trial['id'],
        'created_at'=>$row['created_at'],'entitlement_created'=>$row['entitlement_created'],'issued_by'=>(int)$row['issued_by']],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='expired-trial'){
    if($invoice<1)exit(2);
    require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
    $product=one('SELECT sku,family FROM '.table('catalog_products').' WHERE id=?',[$invoice]);
    if(!$product||$product['family']!=='software')throw new RuntimeException('Synthetic expired-trial fixture requires a software product.');
    db()->beginTransaction();
    try{
        $trial=software_grant_create(1,(string)$product['sku'],'trial','Synthetic Expired Trial',
            'expired-trial.example',gmdate('Y-m-d H:i:s',time()+86400),1);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    query('UPDATE '.table('software_entitlements').' SET expires_at=? WHERE license_id=?',
        [gmdate('Y-m-d H:i:s',time()-86400),(int)$trial['id']]);
    echo json_encode(['license_key'=>$trial['key'],'license_id'=>(int)$trial['id'],'sku'=>(string)$product['sku']],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='orphan-license'){
    if($invoice<1)exit(2);
    require_once CWP_CODE_ROOT.'/app/licenses/service.php';
    $product=one('SELECT sku,family FROM '.table('catalog_products').' WHERE id=?',[$invoice]);
    if(!$product||$product['family']!=='software')throw new RuntimeException('Synthetic orphan fixture requires a software product.');
    $key=license_create(1,(string)$product['sku'],1,false,null);
    $license=one('SELECT id FROM '.table('website_licenses').' WHERE license_key=?',[$key]);
    if(!$license)throw new RuntimeException('Synthetic orphan license was not created.');
    query('INSERT INTO '.table('license_domains').' (license_id,domain,is_active,registered_at) VALUES (?,?,1,?)',
        [(int)$license['id'],'orphan-software.example',gmdate('Y-m-d H:i:s')]);
    echo json_encode(['license_key'=>$key,'license_id'=>(int)$license['id']],JSON_THROW_ON_ERROR);
    exit;
}
if($operation!=='fulfill-asset'||$invoice<1)exit(2);

require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_fulfill.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_usage.php';

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
software_usage_record((int)$license['id'],(string)$order['domain']);

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
$asset=one('SELECT id,released_at,created_at FROM '.table('software_assets').' WHERE sku=? AND version=?',[$order['sku'],$version]);
if(!$asset){
    $created=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('software_assets').' (sku,version,title,file_path,sha256,byte_size,is_published,released_at,created_at) VALUES (?,?,?,?,?,?,1,?,?)',
        [$order['sku'],$version,'Synthetic browser acceptance download',
         software_asset_reference($storage,realpath($file)),$sha,$bytes,$created,$created]);
    $asset=['id'=>(int)db()->lastInsertId(),'released_at'=>$created,'created_at'=>$created];
}
echo json_encode([
    'invoice_id'=>$invoice,
    'asset_id'=>(int)$asset['id'],
    'asset_released_at'=>(string)$asset['released_at'],
    'asset_created_at'=>(string)$asset['created_at'],
    'version'=>$version,
    'license_id'=>(int)$license['id'],
    'license_key'=>(string)$license['license_key'],
    'sha256'=>$sha,
    'bytes'=>(int)$bytes,
    'domain'=>$order['domain'],
],JSON_THROW_ON_ERROR);
