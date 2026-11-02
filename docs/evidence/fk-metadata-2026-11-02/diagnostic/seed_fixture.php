<?php
declare(strict_types=1);
/* Synthetic diagnostic seed only. Calls the frozen corrected guards and migrations. */
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$phase=$argv[1]??'';if(!in_array($phase,['prepare','finish','verify'],true))exit(2);
$runtime=dirname(__DIR__);$root=$runtime.'/web/existing';$private=$runtime.'/private';
set_exception_handler(static function(Throwable $e)use($phase):never{
    $safe=['phase'=>$phase,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'message'=>$e instanceof PDOException?'Database precondition failed':$e->getMessage()];
    if($e instanceof PDOException){$safe['sqlstate']=$e->errorInfo[0]??null;$safe['driver_code']=$e->errorInfo[1]??null;}
    fwrite(STDERR,json_encode($safe,JSON_THROW_ON_ERROR)."\n");exit(1);
});
define('CWP_BOOT',true);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';$cfg=config();
if($cfg['db']['host']!=='127.0.0.1'||$cfg['db']['port']!==33316||$cfg['db']['name']!=='cwp_test'||$cfg['db']['prefix']!=='ci_'||$cfg['site_url']!=='http://127.0.0.1:8092/existing')throw new RuntimeException('Unexpected diagnostic configuration');
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8092','SCRIPT_NAME'=>'/existing/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$runtime.'/web';
ini_set('session.save_path',$runtime.'/sessions');start_session();
require_once CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/app/hosting/order_views.php';
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog.php';
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment_views.php';
function diag_owner():void {
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[1]);if(!$u)throw new RuntimeException('Missing synthetic owner');
    $_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];
}
function diag_client():void {
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[1]);
    $_SESSION=['client_id'=>1,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];
}
function diag_write(string $path,array $data):void {if(file_exists($path))throw new RuntimeException('Refusing to overwrite retained diagnostic state');file_put_contents($path,json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");chmod($path,0600);}
function diag_check(bool $condition,string $name):void {if(!$condition)throw new RuntimeException($name);}
if($phase==='prepare'){
    diag_check((int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()===0,'Only an empty owned diagnostic database can be seeded');
    foreach(schema_statements('ci_') as $sql)db()->exec($sql);
    foreach(default_settings() as $name=>$value)save_setting($name,$value);
    save_setting('support_schema_version','1');
    $input=json_decode(file_get_contents($private.'/fixture-input.json'),true,16,JSON_THROW_ON_ERROR);
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Synthetic catalog owner','catalog-owner@example.invalid',password_hash($input['owner_password'],PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Synthetic catalog client','catalog-client@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[1,'Synthetic catalog project','Retained scope','design','','2020-01-01 00:00:00','2020-01-01 00:00:00']);
    diag_owner();payments_migrate('');ledger_migrate('');service_plans_migrate('');recurring_billing_migrate('');service_orders_migrate('');provider_settings_migrate('');hosting_order_links_prepare('');provider_plan_catalog_prepare('');
    $safe=['phase'=>'prepared','owner_id'=>1,'client_id'=>1,'job_id'=>1,'service_plans_ready'=>service_plans_ready(),'recurring_ready'=>recurring_billing_ready(),'hosting_links_ready'=>hosting_storage_ready('order_links'),'fulfillment_ready'=>hosting_storage_ready('fulfillment'),'table_count'=>(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()];
    diag_check($safe['service_plans_ready']&&$safe['recurring_ready']&&$safe['hosting_links_ready']&&!$safe['fulfillment_ready'],'Prepared state differs from target boundaries');
    diag_write($private.'/prepared.json',$safe);echo json_encode($safe,JSON_THROW_ON_ERROR)."\n";exit;
}
diag_owner();
if($phase==='finish'){
    diag_check(!file_exists($private.'/fixture-state.json'),'Finish already ran');
    $historical=json_decode(file_get_contents($private.'/historical.json'),true,8,JSON_THROW_ON_ERROR);
    foreach(['schedule_id','source_id'] as $key)diag_check(is_string($historical[$key]??null)&&preg_match('/\A[a-f0-9]{32}\z/',$historical[$key])===1,'Invalid historical fixture output');
    $schedule=recurring_billing_get($historical['schedule_id'],1);
    $issued=recurring_billing_issue($historical['schedule_id'],1,$schedule->revision(),$schedule->fingerprint(),0,bin2hex(random_bytes(16)));$invoice=(int)$issued['invoice_id'];
    $source=recurring_billing_locked(fn()=>service_plans_validate(service_plans_row($historical['source_id'],true,true),true));
    $order=service_orders_offer(bin2hex(random_bytes(16)),$historical['source_id'],1,$source->revision(),$source->fingerprint(),$invoice,ledger_invoice_hash(ledger_invoice($invoice)),'');
    diag_client();$order=service_orders_decide($order->data()['id'],1,$order->revision(),bin2hex(random_bytes(16)),'accept',['domain_name'=>'paid-fulfillment.example','review_sha256'=>$order->fingerprint()],true);diag_owner();
    ledger_receive($invoice,2500,'bank','Synthetic fulfillment receipt paid-fulfillment',gmdate('Y-m-d'),bin2hex(random_bytes(32)),ledger_review_hash(ledger_invoice($invoice)));
    $profileId=provider_settings_save('',0,'Synthetic diagnostic provider',true,'SYNTHETIC_DIAGNOSTIC_PROVIDER_CREDENTIAL','');$profile=provider_settings_load($profileId);
    $plan=ServicePlanDocument::fromJson(service_plans_json($source->data()['plan']));
    $configuration=provider_plan_catalog_config(['reseller_id'=>42,'type'=>'811','label'=>'Reviewed <catalog package>','region'=>'Reviewed fixture location','limits'=>['websites'=>1],'cost'=>['amount'=>'12.5000','currency'=>'GBP','period_months'=>12,'tax'=>'excluded','basis'=>'shared-account','reference'=>'Synthetic account reference']]);
    $mapping=provider_plan_catalog_save(bin2hex(random_bytes(16)),$plan->data()['id'],$plan->revision(),$plan->fingerprint(),null,$profileId,(int)$profile['revision'],$configuration,7,true,'');
    $assessment=recurring_billing_locked(fn()=>service_orders_assess($order,time()));
    recurring_billing_locked(fn()=>provider_plan_catalog_order($mapping,$order,true));
    $balance=ledger_balance(ledger_invoice($invoice));
    diag_check($order->state()['phase']==='accepted'&&$assessment['eligible']&&$assessment['basis']==='verified-payment'&&$balance['paid_cents']===2500,'Paid accepted order precondition differs');
    diag_check(!one('SELECT id FROM '.table('hosting_order_links').' WHERE id=?',[$order->data()['id']])&&!hosting_storage_ready('fulfillment'),'Target must begin without a mapped record or fulfillment setup');
    $state=['schedule_id'=>$historical['schedule_id'],'source_id'=>$historical['source_id'],'order_id'=>$order->data()['id'],'invoice_id'=>$invoice,'catalog_id'=>$mapping['id'],'catalog_sha256'=>$mapping['sha256'],'staff_url'=>'http://127.0.0.1:8092/existing/admin/index.php?view=service-agreement&record='.$historical['source_id']];
    diag_write($private.'/fixture-state.json',$state);
    $safe=['phase'=>'seeded','source_cadence'=>'monthly','invoice_cents'=>2500,'invoice_period'=>0,'order_phase'=>$order->state()['phase'],'commercial_basis'=>$assessment['basis'],'catalog_ready'=>true,'hosting_link_exists'=>false,'fulfillment_prepared'=>false,'table_count'=>(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn(),'client_count'=>(int)query('SELECT COUNT(*) FROM '.table('studio_clients'))->fetchColumn(),'order_count'=>(int)query('SELECT COUNT(*) FROM '.table('service_orders'))->fetchColumn(),'catalog_count'=>(int)query('SELECT COUNT(*) FROM '.table('provider_plan_catalog'))->fetchColumn()];
    diag_write($runtime.'/evidence/seed-state.json',$safe);echo json_encode($safe,JSON_THROW_ON_ERROR)."\n";exit;
}
$state=json_decode(file_get_contents($private.'/fixture-state.json'),true,8,JSON_THROW_ON_ERROR);
$order=recurring_billing_locked(fn()=>service_orders_validate(service_orders_row($state['order_id'])));
$link=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$state['order_id']]);
$valid=null;if($link)$valid=recurring_billing_locked(fn()=>hosting_order_links_validate($link,$order));
$safe=['hosting_link_exists'=>$link!==null,'exact_catalog_provenance'=>$valid!==null&&$valid['binding']['format']===2&&$valid['binding']['catalog']===['id'=>$state['catalog_id'],'sha256'=>$state['catalog_sha256']],'invoice_cents'=>2500,'paid_cents'=>ledger_balance(ledger_invoice((int)$state['invoice_id']))['paid_cents'],'order_phase'=>$order->state()['phase'],'fulfillment_prepared'=>hosting_storage_ready('fulfillment')];
echo json_encode($safe,JSON_THROW_ON_ERROR)."\n";
