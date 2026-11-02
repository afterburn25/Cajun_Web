<?php
/** Isolated published-1.25 upgrade continuation. No HTTP route or provider transport. */
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||count($argv)!==4) exit(2);
$mode=$argv[2];
if (!in_array($mode,['state','plan-original','plan-positive'],true)) exit(2);
set_exception_handler(static function(Throwable $e)use($mode):never {
    $safe=['operation'=>$mode,'class'=>get_class($e),'message'=>'Isolated hosting upgrade fixture failed.','file'=>basename($e->getFile()),'line'=>$e->getLine()];
    if ($e instanceof PDOException) {$s=$e->errorInfo[0]??null;$safe['sqlstate']=is_string($s)&&preg_match('/\A[A-Z0-9]{5}\z/',$s)?$s:null;}
    fwrite(STDERR,json_encode($safe,JSON_THROW_ON_ERROR).PHP_EOL);exit(1);
});
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool {
    if (!(error_reporting()&$severity)) return false;
    throw new ErrorException('Fixture PHP diagnostic',0,$severity,$file,$line);
});
$root=realpath($argv[1]);$runtime=realpath(__DIR__.'/../test-runtime/release126-upgrade');
if (!$root||!$runtime||is_link($argv[1])||!str_starts_with($root,$runtime.DIRECTORY_SEPARATOR)) exit(2);
if (!preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/',$argv[3])||trim(file_get_contents(__DIR__.'/../VERSION'))!==$argv[3]) exit(2);
foreach (['app/hosting/catalog_offers.php','app/hosting/catalog_offers_storage.php','app/hosting/catalog_recovery.php','database/migrations/hosting_catalog_migrate.php'] as $path) {
    if (!is_file($root.'/'.$path)||is_link($root.'/'.$path)||!hash_equals(hash_file('sha256',__DIR__.'/../site/'.$path),hash_file('sha256',$root.'/'.$path))) throw new RuntimeException('Expected exact installed candidate code.');
}
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
$prefix=config()['db']['prefix'];
if (CWP_VERSION!==$argv[3]||config()['db']['name']!=='cwp_test'||!in_array($prefix,['r126_ron_','r126_son_','r126_roff_','r126_soff_'],true)) exit(2);
require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog.php';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root;start_session();
$owner=one('SELECT * FROM '.table('users').' WHERE id=1');
if (!$owner||$owner['email']!=='organized@example.invalid') throw new RuntimeException('Original fixture owner missing.');
$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$password='Synthetic organized upgrade passphrase';

if ($mode!=='state') {
    // Only real commercial wrappers create these records. No fixture INSERT or journal fabrication.
    $original=$mode==='plan-original';$sku=$original?'ORIGINAL-125-HOST':'NEW-126-CATALOG-ONLY';
    $product=one('SELECT * FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
    if (!$product||$product['family']!=='hosting'||$product['status']!=='draft'||(int)$product['amount_cents']<1) throw new RuntimeException('Price the actual imported draft first.');
    $import=provider_catalog_import_record((int)$product['id']);
    if (!$import||$import['type_ref']!==($original?'9':'901')) throw new RuntimeException('Unexpected original import.');
    $planId=substr(hash('sha256',$prefix.'|hosting-upgrade|'.$mode),0,32);
    if (one('SELECT id FROM '.table('service_plans').' WHERE id=?',[$planId])) throw new RuntimeException('Never overwrite earlier fixture plan evidence.');
    $terms=['name'=>$product['title'],'cadence'=>['month'=>'monthly','year'=>'annually'][$product['term']],
        'price'=>(int)$product['amount_cents'],'currency'=>'USD','inclusions'=>['One owner-reviewed website'],
        'exclusions'=>['Website redesign'],'limits'=>['websites'=>1],'support'=>'Response within two business days'];
    $plan=service_plans_create($planId,$terms,$password);
    $plan=service_plans_change($planId,$plan->revision(),substr(hash('sha256',$planId.'|activate'),0,32),'activate',[],$password);
    provider_plan_catalog_prepare($password);
    $profile=provider_settings_profile();if (!$profile) throw new RuntimeException('Original provider context missing.');
    $configuration=['reseller_id'=>42,'type'=>$import['type_ref'],'label'=>'Synthetic saved upgrade account','region'=>'Synthetic reviewed region',
        'limits'=>['websites'=>1],'cost'=>null];
    $mapping=provider_plan_catalog_save(substr(hash('sha256',$planId.'|mapping'),0,32),$planId,$plan->revision(),$plan->fingerprint(),null,
        $profile['id'],(int)$profile['revision'],$configuration,7,true,$password);
    echo json_encode(['product_id'=>(int)$product['id'],'plan_id'=>$planId,'plan_sha256'=>$plan->fingerprint(),
        'mapping_id'=>$mapping['id'],'type_ref'=>$import['type_ref'],'live_requests'=>0],JSON_THROW_ON_ERROR);exit;
}

$result=recurring_billing_locked(static function()use($prefix):array {
    $ready=hosting_catalog_ready();$namespace=$prefix.'hosting_catalog_';
    $tables=array_column(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen($namespace),$namespace]),'TABLE_NAME');
    $tables=array_map(static fn($n)=>substr($n,strlen($prefix)),$tables);
    $settings=all('SELECT name,value FROM '.table('settings').' WHERE BINARY LEFT(name,?)=BINARY ? ORDER BY name',[strlen('hosting_catalog'),'hosting_catalog']);
    $audit=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY LEFT(action,?)=BINARY ?',[strlen('hosting_catalog.'),'hosting_catalog.'])->fetchColumn();
    $publications=[];$requests=[];$decisions=[];$agreements=[];$counts=[];
    foreach ($tables as $name) {
        if (!in_array($name,hosting_catalog_tables(),true)) throw new RuntimeException('Unknown hosting table.');
        $counts[$name]=(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();
    }
    if ($ready) {
        foreach (all('SELECT * FROM '.table('hosting_catalog_publications').' ORDER BY seq') as $row) {
            $p=hosting_catalog_publication($row['id']);$d=$p->data();
            $publications[]=['id'=>$row['id'],'sha256'=>$p->fingerprint(),'product_id'=>(int)$row['product_id'],
                'product_revision'=>(int)$row['product_revision'],'media_id'=>$row['media_id'],
                'plan_sha256'=>hash('sha256',$d['plan_json']),'mapping_id'=>$row['mapping_id'],
                'import_type'=>$d['import']['type_ref'],'observation_type'=>$d['observation']['selected']['id']];
        }
        $requests=all('SELECT id,publication_id,client_id,document_sha256 FROM '.table('hosting_catalog_requests').' ORDER BY id');
        $decisions=all('SELECT id,request_id,kind,agreement_id,job_id FROM '.table('hosting_catalog_decisions').' ORDER BY id');
        foreach ($decisions as $decision) if ($decision['agreement_id']!==null) {
            $row=service_plans_row($decision['agreement_id'],true);$d=service_plans_validate($row,true);
            $agreements[$decision['agreement_id']]=['client_id'=>(int)$row['client_id'],'revision'=>$d->revision(),
                'state'=>$d->model()->state(),'consented'=>$d->model()->consent()!==null,
                'plan_sha256'=>hash('sha256',service_plans_json($d->data()['plan']))];
        }
    }
    $private=realpath(config()['storage']);
    if (!$private||is_link(config()['storage'])) throw new RuntimeException('Original private store unavailable.');
    return ['ready'=>$ready,'tables'=>$tables,'settings'=>$settings,'audit_count'=>$audit,'counts'=>$counts,
        'publications'=>$publications,'requests'=>$requests,'decisions'=>$decisions,'agreements'=>(object)$agreements,
        // Invocation-private location only: the Python caller never copies this field to QA.
        'private_path'=>$private];
});
echo json_encode($result,JSON_THROW_ON_ERROR);
