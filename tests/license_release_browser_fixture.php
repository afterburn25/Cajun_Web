<?php
/** Disposable packaged-site licensing HTTP setup. No Website_Core or provider execution. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||count($argv)!==2)exit(2);
$root=realpath(__DIR__.'/../test-runtime/web/license-release');
if(!$root||!is_file($root.'/includes/bootstrap.php'))exit(2);
define('CWP_BOOT',true);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';
if(config()['db']['name']!=='cwp_test')exit(2);
$GLOBALS['cwp_config']['db']['prefix']='licweb_';
$private=__DIR__.'/../test-runtime/license-release-private';
$GLOBALS['cwp_config']['storage']=$private;
$GLOBALS['cwp_config']['site_url']='http://127.0.0.1:8091/license-release';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
$operation=$argv[1];$now=gmdate('Y-m-d H:i:s');
if($operation==='seed'){
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['licweb_settings']))throw new RuntimeException('Licensing HTTP fixture must start absent.');
    require_once CWP_CODE_ROOT.'/database/schema/base.php';require __DIR__.'/fixtures/license_pre_master_schema.php';
    foreach(schema_statements('licweb_') as $name=>$sql)if(!in_array($name,['website_licenses','license_domains','license_bundles'],true))db()->exec($sql);
    foreach(license_pre_master_schema('licweb_') as $sql)db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    save_setting('catalog_schema_version','1');
    foreach([['Release licensing owner','license-owner@example.invalid'],['Release website administrator','license-staff@example.invalid']] as [$name,$email])query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',[$name,$email,password_hash('Synthetic licensing HTTP passphrase 2026',PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,?)",[json_encode(['website.manage','clients.manage'])]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$id,'Licensing HTTP client '.$id,'license-client'.$id.'@example.invalid',password_hash('Synthetic licensing client passphrase 2026',PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?)',['Licensing release project','Isolated licensing HTTP acceptance','Original purchase records',$now,$now]);
    foreach([899,900] as $id){
        query('INSERT INTO '.table('studio_invoices').' (id,job_id,reference,title,amount_cents,currency,status,instructions,created_at,paid_at) VALUES (?,1,?,?,50000,?,?,?, ?,?)',[$id,'LWEB-'.$id,'Original licensed website','USD','paid','Synthetic retained purchase',$now,$now]);
        query('INSERT INTO '.table('catalog_invoice_snapshots').' (invoice_id,job_id,client_id,actor_id,request_key,request_hash,product_id,revision,quantity,snapshot,created_at) VALUES (?,1,1,1,?,?,1,1,1,?,?)',[$id,hash('sha256','lweb-'.$id),hash('sha256','lweb-h-'.$id),json_encode(['sku'=>'WEBSITE-RESTAURANT','title'=>'Original licensed website']),$now]);
    }
    if(is_dir($private))throw new RuntimeException('Private fixture must start absent.');
    mkdir($private,0700,true);mkdir($private.'/media',0700);mkdir($private.'/bundles',0700);
    $zip=new ZipArchive();if($zip->open($private.'/bundles/synthetic.zip',ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Synthetic ZIP setup failed.');
    $zip->addFromString('index.html','<!doctype html><title>Synthetic licensing download</title><p>No Website Core is included or executed.</p>');$zip->close();
    foreach([[1,1,0,'1111-1111-1111-1111'],[2,1,1,'2222-2222-2222-2222'],[3,2,1,'3333-3333-3333-3333']] as [$id,$client,$active,$key]){
        query('INSERT INTO '.table('website_licenses').' (id,license_key,customer_id,product_id,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$id,$key,$client,'WEBSITE-RESTAURANT',$active,$now,$now]);
        query('INSERT INTO '.table('license_bundles').' (license_id,file_path,title,created_at) VALUES (?,?,?,?)',[$id,$private.'/bundles/synthetic.zip','Synthetic ordinary bundle '.$id,$now]);
    }
    query('INSERT INTO '.table('license_domains').' (license_id,domain,is_active,registered_at) VALUES (1,?,1,?)',['retained-license.example',$now]);
    license_audit(1,'fixture','Retained licensing HTTP history');
    file_put_contents($root.'/config.php','<?php return '.var_export(config(),true).';');
    file_put_contents($root.'/.installed.php',CWP_GUARD.'Synthetic installed fixture');
    echo json_encode(['seeded'=>true,'prefix'=>'licweb_','old_invoice_max'=>900],JSON_THROW_ON_ERROR);
}elseif($operation==='master-bundle'){
    $master=one('SELECT id FROM '.table('website_licenses').' WHERE customer_id=1 AND is_master=1');if(!$master)throw new RuntimeException('Create the master through its actual owner form first.');
    $path=$private.'/bundles/synthetic.zip';if(!is_file($path))exit(2);
    query('INSERT INTO '.table('license_bundles').' (license_id,file_path,title,sha256,created_at) VALUES (?,?,?,?,?)',[$master['id'],$path,'Synthetic historical master bundle',hash_file('sha256',$path),$now]);
    echo json_encode(['bundle_id'=>(int)db()->lastInsertId()],JSON_THROW_ON_ERROR);
}elseif($operation==='old-fulfillment'){
    require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
    require_once CWP_CODE_ROOT.'/app/licenses/fulfill.php';
    try{license_fulfill_invoice(900);echo json_encode(['refused'=>false]);}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo json_encode(['refused'=>true,'reason'=>$e->getMessage()],JSON_THROW_ON_ERROR);}
}else exit(2);
