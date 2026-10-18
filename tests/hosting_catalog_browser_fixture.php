<?php
/** CLI-only continuation of the successful disposable hofc_ native fixture. Never shipped. */
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test') exit(2);
$operation=$argv[1]??'';
if (!in_array($operation,['prepare','state','edit-summary','damage-marker','restore-marker'],true)) exit(2);
// Retain a useful bounded location/code without arguments, SQL, configuration or private values.
set_exception_handler(static function(Throwable $error)use($operation):never {
    $message=match(true) {
        $error instanceof PDOException=>'Disposable database operation failed.',
        $error instanceof ErrorException=>'Disposable fixture emitted a PHP diagnostic.',
        $error instanceof Error=>'Disposable fixture bootstrap or call failed.',
        default=>'Disposable fixture precondition or application guard failed.',
    };
    $class=get_class($error);$file=basename($error->getFile());
    $safe=['operation'=>$operation,'class'=>preg_match('/\A[A-Za-z_\\\\][A-Za-z0-9_\\\\]{0,119}\z/',$class)?$class:'Throwable',
        'message'=>$message,'file'=>preg_match('/\A[A-Za-z0-9_.-]{1,120}\z/',$file)?$file:'unknown.php','line'=>$error->getLine()];
    if ($error instanceof PDOException) {
        $state=$error->errorInfo[0]??'';
        $safe['sqlstate']=is_string($state)&&preg_match('/\A[A-Z0-9]{5}\z/',$state)?$state:null;
        $driver=$error->errorInfo[1]??null;$safe['driver_code']=is_int($driver)?$driver:null;
    }
    fwrite(STDERR,json_encode($safe,JSON_THROW_ON_ERROR).PHP_EOL);exit(1);
});
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool {
    if (!(error_reporting()&$severity)) return false;
    throw new ErrorException('Fixture PHP diagnostic',0,$severity,$file,$line);
});
$root=dirname(__DIR__);$site=$root.'/test-runtime/hosting-catalog-web/hosting-catalog';
$private=realpath($root.'/test-runtime/hosting-catalog-private');
if (!$private||!is_file($private.'/provider-master.key')) throw new RuntimeException('Successful native seed required.');
$native=json_decode(file_get_contents($root.'/qa/hosting-catalog-native-results.json'),true,64,JSON_THROW_ON_ERROR);
if (($native['passed']??null)!==true||!($native['checks']??[])||array_filter($native['checks'],static fn(array $c):bool=>($c['passed']??null)!==true)) throw new RuntimeException('Successful native checks required.');
define('CWP_BOOT',true);define('CWP_ROOT',$site);
require $site.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-catalog','storage'=>$private,'key'=>str_repeat('synthetic-hosting-catalog-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hofc_']];
require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
start_session();
$owner=one('SELECT * FROM '.table('users').' WHERE id=1');
if (!$owner||$owner['email']!=='hosting-staff1@example.invalid') throw new RuntimeException('Unexpected fixture owner.');
$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$product=one('SELECT * FROM '.table('catalog_products').' WHERE sku=?',['HOSTING-NATIVE']);
if (!$product||$product['family']!=='hosting'||$product['status']!=='draft') throw new RuntimeException('Expected imported draft absent.');
$markerFile=$private.'/hosting-browser-marker.json';$prepared=$private.'/hosting-browser-prepared';
if ($operation==='prepare') {
    if (is_file($prepared)||file_exists($site.'/config.php')) throw new RuntimeException('Browser fixture already prepared; preserve the prior run.');
    recurring_billing_locked(function():void {
        if (!hosting_catalog_ready()) throw new RuntimeException('Native history not ready.');
        foreach ([1,2] as $id) {
            $row=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
            if (!$row||$row['email']!=='hosting-client'.$id.'@example.invalid') throw new RuntimeException('Unexpected fixture client.');
            // Synthetic account setup only. Authentication itself occurs through the real HTTP login.
            query('UPDATE '.table('studio_clients').' SET password_hash=? WHERE id=?',[password_hash('Synthetic hosting browser client',PASSWORD_DEFAULT),$id]);
        }
    });
    if (file_put_contents($site.'/config.php','<?php return '.var_export(config(),true).';')===false||!chmod($site.'/config.php',0600)) throw new RuntimeException('Cannot protect installed config.');
    if (file_put_contents($site.'/.installed.php',CWP_GUARD.'synthetic installed guard')===false||file_put_contents($prepared,'prepared',LOCK_EX)===false) throw new RuntimeException('Cannot retain fixture preparation.');
    echo json_encode(['prepared'=>true,'product_id'=>(int)$product['id'],'plan_id'=>str_pad('1',32,'0',STR_PAD_LEFT),'prefix'=>'hofc_'],JSON_THROW_ON_ERROR);exit;
}
if (!is_file($prepared)) throw new RuntimeException('Prepare browser fixture first.');
if ($operation==='edit-summary') {
    // A real catalog revision invalidates the prior review; never corrupt its original provenance.
    catalog_save((int)$product['id'],(int)$product['revision'],[
        'sku'=>'HOSTING-NATIVE','title'=>'Reviewed shared hosting','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD',
        'amount'=>'25.00','setup'=>'0.00','cost'=>'9.87','provider_ref'=>'PRIVATE_PROVIDER_REFERENCE',
        'summary'=>'Browser owner edited the saved summary.','features'=>'PRIVATE_DRAFT_FEATURES','terms'=>'PRIVATE_DRAFT_TERMS','sort_order'=>'0',
    ]);
    echo json_encode(['changed'=>true],JSON_THROW_ON_ERROR);exit;
}
if ($operation==='damage-marker') {
    if (file_exists($markerFile)) throw new RuntimeException('Previous recovery probe remains.');
    recurring_billing_locked(function()use($markerFile):void {
        if (!hosting_catalog_ready()) throw new RuntimeException('Ready original history required.');
        $row=one('SELECT * FROM '.table('settings').' WHERE name=?',['hosting_catalog_version']);
        if (!$row||file_put_contents($markerFile,json_encode($row,JSON_THROW_ON_ERROR),LOCK_EX)===false||!chmod($markerFile,0600)) throw new RuntimeException('Cannot retain original marker.');
        query('DELETE FROM '.table('settings').' WHERE name=?',['hosting_catalog_version']);
    });
    echo json_encode(['damaged'=>true],JSON_THROW_ON_ERROR);exit;
}
if ($operation==='restore-marker') {
    $row=json_decode(file_get_contents($markerFile),true,8,JSON_THROW_ON_ERROR);
    if (($row['name']??null)!=='hosting_catalog_version'||($row['value']??null)!=='1') throw new RuntimeException('Unexpected retained marker.');
    recurring_billing_locked(function()use($row):void {
        if (one('SELECT name FROM '.table('settings').' WHERE name=?',[$row['name']])) throw new RuntimeException('Marker already exists.');
        save_setting($row['name'],$row['value']);
        if (!hosting_catalog_ready()) throw new RuntimeException('Original history still needs recovery.');
    });
    unlink($markerFile);echo json_encode(['restored'=>true],JSON_THROW_ON_ERROR);exit;
}
$result=recurring_billing_locked(function():array {
    $tables=['catalog_products','catalog_revisions','catalog_provider_imports','media','catalog_invoice_snapshots','studio_invoices','payment_gateways','payment_requests',
        'service_plans','service_plan_events','service_subscriptions','service_subscription_events',
        'hosting_catalog_publications','hosting_catalog_withdrawals','hosting_catalog_requests','hosting_catalog_decisions'];
    foreach (all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(table_name,14)=? ORDER BY table_name',['hofc_provider_']) as $t) {
        if (!preg_match('/\Ahofc_provider_[a-z0-9_]+\z/',$t['name'])) throw new LogicException('Unexpected provider fixture table.');
        $tables[]=substr($t['name'],5);
    }
    $rows=[];$counts=[];
    foreach ($tables as $name) {
        $list=array_map(static fn(array $r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM '.table($name)));
        sort($list,SORT_STRING);$rows[$name]=$list;$counts[$name]=count($list);
    }
    $rows['hosting_settings']=all('SELECT * FROM '.table('settings').' WHERE name LIKE ? ORDER BY name',['hosting_catalog%']);
    $rows['hosting_audit']=all('SELECT * FROM '.table('audit').' WHERE action LIKE ? ORDER BY id',['hosting_catalog.%']);
    $agreements=[];
    foreach (all('SELECT * FROM '.table('service_subscriptions').' ORDER BY id') as $row) {
        $doc=service_plans_validate($row,true);
        $agreements[$row['id']]=['client_id'=>(int)$row['client_id'],'revision'=>$doc->revision(),'state'=>$doc->model()->state(),'consented'=>$doc->model()->consent()!==null,'plan_json'=>service_plans_json($doc->data()['plan'])];
    }
    return ['sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)),'counts'=>$counts,'ready'=>hosting_catalog_ready(),
        'requests'=>all('SELECT id,publication_id,client_id,document_sha256 FROM '.table('hosting_catalog_requests').' ORDER BY id'),
        'images'=>all('SELECT product_id,media_id,media_revision FROM '.table('catalog_provider_imports').' ORDER BY product_id'),
        'decisions'=>all('SELECT id,request_id,kind,agreement_id,job_id FROM '.table('hosting_catalog_decisions').' ORDER BY id'),
        'publications'=>all('SELECT id,product_id,document_sha256,document_json FROM '.table('hosting_catalog_publications').' ORDER BY seq'),
        'agreements'=>$agreements,'provider_key_sha256'=>hash_file('sha256',config()['storage'].'/provider-master.key')];
});
echo json_encode($result,JSON_THROW_ON_ERROR);
