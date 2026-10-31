<?php
/** Isolated Linux SQL checkout: current-clock confirmation and synthetic local receipt; no charge/provider write. */
declare(strict_types=1);
if (PHP_SAPI!=='cli' || PHP_OS_FAMILY!=='Linux' || getenv('CWP_TEST_HOSTING_CHECKOUT')!=='1' || getenv('CWP_TEST_DATABASE')!=='cwp_test' || (getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1') exit(2);
$port=filter_var(getenv('CWP_TEST_PORT')?:'33306',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);if($port===false)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/catalog_offers.php';
require_once __DIR__.'/../site/app/hosting/catalog_checkout.php';
require_once __DIR__.'/../site/app/hosting/order_link_views.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes;

$checks=[];$stage='preflight';$calls=0;$reportPathReady=false;$password='Synthetic native checkout owner';$secret='SYNTHETIC_CHECKOUT_PROVIDER_KEY';
$runtime=__DIR__.'/../test-runtime';$private=$runtime.'/hosting-checkout-private';$qa=__DIR__.'/../qa';
if(file_exists($qa.'/hosting-checkout-native-results.json')||is_link($qa.'/hosting-checkout-native-results.json'))exit(2);
function hcc_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException('Named checkout assertion failed.');}
function hcc_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;hcc_ok($name,true);return;}hcc_ok($name,false);}
function hcc_id(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function hcc_owner():void {$u=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];}
function hcc_client(int $id=1):void {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];}
function hcc_count(string $name):int {return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function hcc_ready():bool{return hosting_checkout_locked(static fn():bool=>hosting_checkout_ready());}
function hcc_state():string {
    $data=[];
    foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,4)=BINARY ? ORDER BY TABLE_NAME',['hcc_']) as $t){
        if(!preg_match('/\Ahcc_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unknown fixture table.');
        $rows=array_map(static fn(array $r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM '.chr(96).$t['name'].chr(96)));sort($rows,SORT_STRING);$data[$t['name']]=$rows;
    }
    return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
}
/** Only exact retained fixture rows are restored, never a newly invented business history. */
function hcc_restore(string $name,array $row):void {
    if(!in_array($name,['settings','audit','hosting_checkout_results','recurring_billing_invoices'],true))throw new LogicException('Unknown restore table.');
    foreach(array_keys($row) as $field)if(!preg_match('/\A[a-z_][a-z0-9_]*\z/',$field))throw new LogicException('Unknown restore field.');
    query('INSERT INTO '.table($name).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
}
function hcc_read(array $row,string $key,string $address,callable $authorize,string $operation):InventorySnapshot {
    global $calls,$secret;$authorize();
    if($key!==$secret || $address!=='8.8.8.8' || $operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unexpected synthetic transport');
    $calls++;return PackageTypes::project(new TransportResponse(200,[],'[{"id":7001,"label":"Synthetic checkout hosting"}]'),time(),'twenty-i|'.$row['id'].'|'.$operation);
}
function hcc_review(int $n,HostingCatalogPublication $pub,array $project=['mode'=>'new-hosting']):array {
    return hosting_checkout_preview(hcc_id($n),$pub->data()['id'],$pub->fingerprint(),1,$project);
}
function hcc_policy(int $ttl=900):array {
    return ['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today',
        'requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>'UTC','alignment'=>'day-of-month','review_ttl_seconds'=>$ttl];
}
function hcc_publish(int $id,int $product,string $plan,array $policy):HostingCatalogPublication {
    global $password;hcc_owner();$review=hosting_catalog_preview(hcc_id($id),$product,$plan,$policy);
    return hosting_catalog_publish(hcc_id($id),$product,$plan,$review['review_sha256'],$password,$policy);
}
function hcc_paths(string $path):void {
    $p=$path;while(true){if(is_link($p))throw new RuntimeException('Fixture path has a link ancestor.');$parent=dirname($p);if($parent===$p)break;$p=$parent;}
}
try {
    hcc_paths($runtime);hcc_paths($qa);hcc_paths($private);
    if(file_exists($private)||is_link($private))throw new RuntimeException('Preserve existing checkout fixture storage; use a clean runtime.');
    if(!is_dir($runtime)&&!mkdir($runtime,0700,true))throw new RuntimeException('Cannot create runtime.');
    if(!mkdir($private,0700)||(!is_dir($qa)&&!mkdir($qa,0700,true)))throw new RuntimeException('Cannot create isolated fixture paths.');
    $reportPathReady=true;
    $GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-checkout','storage'=>realpath($private),'key'=>str_repeat('synthetic-checkout-local-',4),
        'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hcc_']];
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,4)=BINARY ? LIMIT 1',['hcc_']))throw new RuntimeException('Preserve existing hcc_ data; use a clean test database.');
    if((new ReflectionFunction('hosting_catalog_preview'))->getNumberOfParameters()!==4 || (new ReflectionFunction('hosting_catalog_publish'))->getNumberOfParameters()!==6)throw new RuntimeException('Reviewed checkout publication policy API is not integrated.');
    hcc_ok('Explicit Linux disposable database and empty hcc namespace are selected',config()['db']['name']==='cwp_test'&&config()['db']['prefix']==='hcc_'&&PHP_OS_FAMILY==='Linux');
    $stage='seed';foreach(schema_statements('hcc_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','2');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Checkout owner','checkout-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('a',32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);
    foreach([1,2] as $id){query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Checkout client '.$id,'checkout-client'.$id.'@example.invalid',password_hash('Synthetic checkout client',PASSWORD_DEFAULT),str_repeat($id===1?'c':'d',32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,'Existing project '.$id,'Synthetic native project','',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);}
    hcc_owner();catalog_migrate($password);provider_catalog_import_prepare($password);provider_settings_migrate($password);
    $profile=provider_settings_save('',0,'Synthetic checkout connection',true,$secret,$password);provider_inventory_migrate($password);provider_package_types_prepare($password);
    hcc_ok('Provider fixture retains one real saved observation through closed synthetic transport',provider_inventory_refresh($profile,1,$password,static fn()=>'8.8.8.8','hcc_read',InventoryRead::PACKAGE_TYPES)==='complete'&&$calls===1);
    $preview=provider_catalog_import_preview(1,'package.types');$product=provider_catalog_import_create($preview['hash'],'7001','HOSTING-CHECKOUT','month',$password,'package.types');
    catalog_save($product,1,['sku'=>'HOSTING-CHECKOUT','title'=>'Reviewed checkout hosting','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount'=>'25.00','setup'=>'0.00','cost'=>'9.87','provider_ref'=>'PRIVATE_PROVIDER_REFERENCE','summary'=>'One reviewed hosting service.','features'=>'PRIVATE_DRAFT_FEATURES','terms'=>'PRIVATE_DRAFT_TERMS','sort_order'=>'0']);
    service_plans_migrate($password);$terms=['name'=>'Reviewed checkout hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['One reviewed website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during stated hours'];
    $plan=service_plans_create(hcc_id(1),$terms,$password);$plan=service_plans_change(hcc_id(1),$plan->revision(),hcc_id(2),'activate',[],$password);
    provider_plan_catalog_prepare($password);$config=['reseller_id'=>42,'type'=>'7001','label'=>'Synthetic owner account','region'=>'Reviewed region','limits'=>['websites'=>1],'cost'=>['amount'=>'9.87','currency'=>'USD','period_months'=>1,'tax'=>'unknown','basis'=>'per-package','reference'=>'PRIVATE_COST_REFERENCE']];
    provider_plan_catalog_save(hcc_id(10),hcc_id(1),$plan->revision(),$plan->fingerprint(),null,$profile,1,$config,7,true,$password);
    hosting_catalog_prepare($password);payments_migrate($password);attempts_migrate($password);ledger_migrate($password);service_plans_checkout_migrate($password);recurring_billing_migrate($password);recurring_billing_checkout_migrate($password);
    $stage='setup';$before=hcc_state();hcc_ok('Unprepared checkout readiness is a no-write false result',!hcc_ready()&&hcc_state()===$before);
    db()->exec('CREATE TABLE hcc_hosting_checkout_unknown (id INT PRIMARY KEY) ENGINE=InnoDB');$before=hcc_state();hcc_deny('Unknown unprepared checkout namespace refuses setup',fn()=>hosting_checkout_prepare($password));hcc_ok('Unknown namespace refusal preserves rows',hcc_state()===$before);db()->exec('DROP TABLE hcc_hosting_checkout_unknown');
    $sessionBeforeDenial=$_SESSION; $_SESSION['last_activity']=time()-1801;
    try { hcc_deny('Checkout setup requires current owner password — invalid current session',fn()=>hosting_checkout_prepare('')); } finally { $_SESSION=$sessionBeforeDenial; }
    db()->exec(hosting_checkout_schema('hcc_')['hosting_checkout_reviews']);hosting_checkout_prepare($password);hcc_ok('Recognized empty interrupted initial CREATE resumes explicitly',hcc_ready());
    $before=hcc_state();hosting_checkout_prepare($password);hcc_ok('Repeated setup preserves all rows',hcc_state()===$before);
    save_setting('hosting_checkout_version','unknown');$before=hcc_state();hcc_deny('Unknown marker refuses readiness',fn()=>hcc_ready());hcc_deny('Unknown marker refuses setup without adoption',fn()=>hosting_checkout_prepare($password));hcc_ok('Unknown marker refusals preserve rows',hcc_state()===$before);save_setting('hosting_checkout_version','1');
    db()->exec('RENAME TABLE hcc_hosting_checkout_results TO hcc_retained_results');$before=hcc_state();hcc_deny('Missing established result table refuses recreation',fn()=>hosting_checkout_prepare($password));hcc_ok('Missing table remains missing after setup refusal',!hosting_checkout_exists('hosting_checkout_results')&&hcc_state()===$before);db()->exec('RENAME TABLE hcc_retained_results TO hcc_hosting_checkout_results');
    hcc_ok('Exact original schema restoration returns ready',hcc_ready());
    $stage='publication';$pub=hcc_publish(20,$product,hcc_id(1),hcc_policy());hcc_ok('Real current owner publication retains checkout policy and exact terms',$pub->checkoutPolicy()===hcc_policy());
    hcc_client();$review=hcc_review(30,$pub,['mode'=>'existing','id'=>1]);$before=hcc_state();$same=hcc_review(30,$pub,['mode'=>'existing','id'=>1]);hcc_ok('Exact preview retry retains one immutable review',$same===$review&&hcc_state()===$before);
    hcc_ok('Client review projection withholds private provider and document fields',!str_contains(service_plans_json($review),'PRIVATE_')&&!str_contains(service_plans_json($review),'plan_json')&&!str_contains(service_plans_json($review),'mapping_json'));
    hcc_deny('Review request identity cannot change project instruction',fn()=>hcc_review(30,$pub,['mode'=>'new-hosting']));
    hcc_deny('Foreign existing project refuses preview',fn()=>hcc_review(31,$pub,['mode'=>'existing','id'=>2]));
    hcc_deny('Unchecked confirmation refuses first invoice',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,false));
    hcc_deny('Changed review hash refuses first invoice',fn()=>hosting_checkout_confirm(hcc_id(30),str_repeat('0',64),1,true));
    query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=1');$before=hcc_state();hcc_deny('Project ownership changed after review refuses confirmation',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));hcc_ok('Changed project ownership refusal creates no financial rows',hcc_state()===$before);query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=1');
    $stage='first-confirm';$jobs=hcc_count('studio_jobs');$started=time();$result=hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true);$finished=time();
    hcc_ok('Actual client confirmation immediately creates one unpaid invoice',$result['invoice_status']==='issued'&&$result['amount_cents']===2500&&$result['currency']==='USD'&&hcc_count('studio_invoices')===1&&!$result['automatic_charge']&&!$result['provider_activation']);
    hcc_ok('Existing project selection creates no replacement project',$result['project_id']===1&&hcc_count('studio_jobs')===$jobs);
    $s=service_plans_row($result['agreement_id'],true);$b=recurring_billing_row($result['schedule_id']);$map=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE invoice_id=?',[$result['invoice_id']]);
    $events=all('SELECT * FROM '.table('service_subscription_events').' WHERE subscription_id=? ORDER BY revision',[$result['agreement_id']]);$at=$result['accepted_at'];
    hcc_ok('Current-time confirmation records a single accepted epoch',$at>=$started&&$at<=$finished&&(int)$s['created_epoch']===$at&&(int)$b['created_epoch']===$at&&(int)$map['issued_epoch']===$at&&array_map('intval',array_column($events,'recorded_epoch'))===[$at,$at]);
    hcc_ok('Agreement rows distinguish client consent from automatic effect',$s['created_by']===null&&(int)$s['created_by_client']===1&&$events[0]['actor_staff_id']===null&&(int)$events[0]['actor_client_id']===1&&$events[0]['actor_system']===null&&$events[1]['actor_staff_id']===null&&$events[1]['actor_client_id']===null&&$events[1]['actor_system']==='hosting-checkout');
    hcc_ok('Exact native complete graph passes historical validation',hosting_checkout_result(hcc_id(30),1)['invoice_id']===$result['invoice_id']&&hcc_ready());
    $before=hcc_state();$replay=hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true);hcc_ok('Completed exact retry returns original graph without writes',$replay['replayed']&&$replay['invoice_id']===$result['invoice_id']&&$replay['agreement_id']===$result['agreement_id']&&hcc_state()===$before);
    hcc_client(2);hcc_deny('Other current client cannot read completed checkout',fn()=>hosting_checkout_result(hcc_id(30),2));hcc_deny('Other current client cannot replay completed checkout',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],2,true));hcc_client();
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;$before=hcc_state();hcc_deny('Established contact session key is refused before normalization '.$key,fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));hcc_ok('Contact key remains present and commercial rows unchanged '.$key,array_key_exists($key,$_SESSION)&&hcc_state()===$before);unset($_SESSION[$key]);}
    $savedSession=$_SESSION;query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[str_repeat('e',32)]);$_SESSION=$savedSession;hcc_deny('Revoked primary-client authentication cannot replay checkout',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));hcc_client();
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');$before=hcc_state();hcc_deny('Inactive primary client cannot replay completed checkout',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));hcc_ok('Inactive client refusal preserves retained commercial rows',hcc_state()===$before);query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');hcc_client();
    hcc_owner();support_migrate($password);support_policy(1,true,$password);$supportId=support_start(1);$GLOBALS['cwp_support']=support_resolve($supportId);
    hcc_ok('Real owner support session resolves before checkout refusal',is_array($GLOBALS['cwp_support'])&&$GLOBALS['cwp_support']['session']['id']===$supportId&&(int)$GLOBALS['cwp_support']['client']['id']===1);
    hcc_deny('Genuine owner support context cannot impersonate checkout client',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));support_end($supportId);hcc_client();
    $stage='new-project-rollback';$next=hcc_review(40,$pub);$before=hcc_state();$priorInvoices=hcc_count('studio_invoices');$priorJobs=hcc_count('studio_jobs');
    query('SET @hcc_invoice_count=NULL');query('SET @hcc_project_count=NULL');
    db()->exec("CREATE TRIGGER hcc_checkout_audit_failure BEFORE INSERT ON ".table('audit')." FOR EACH ROW BEGIN IF NEW.action='hosting_checkout.completed' THEN SET @hcc_invoice_count=(SELECT COUNT(*) FROM ".table('studio_invoices')."); SET @hcc_project_count=(SELECT COUNT(*) FROM ".table('studio_jobs')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic checkout late audit failure'; END IF; END");
    $failed=false;try{hosting_checkout_confirm(hcc_id(40),$next['review_sha256'],1,true);}catch(PDOException $e){if(($e->errorInfo[0]??null)!=='45000'||($e->errorInfo[1]??null)!==1644||!str_contains($e->getMessage(),'Synthetic checkout late audit failure'))throw $e;$failed=true;}finally{db()->exec('DROP TRIGGER hcc_checkout_audit_failure');}
    $observed=one('SELECT @hcc_invoice_count AS invoices,@hcc_project_count AS projects');
    hcc_ok('Real trigger observes invoice and dedicated project before late audit failure',$failed&&(int)$observed['invoices']===$priorInvoices+1&&(int)$observed['projects']===$priorJobs+1);
    hcc_ok('Owning native transaction rolls every checkout row back',hcc_state()===$before);
    $new=hosting_checkout_confirm(hcc_id(40),$next['review_sha256'],1,true);$job=one('SELECT * FROM '.table('studio_jobs').' WHERE id=?',[$new['project_id']]);
    hcc_ok('Fresh retry after complete rollback creates one dedicated project and invoice',hcc_count('studio_jobs')===$priorJobs+1&&hcc_count('studio_invoices')===$priorInvoices+1&&(int)$job['client_id']===1&&$job['milestones']==='');
    $before=hcc_state();hosting_checkout_confirm(hcc_id(40),$next['review_sha256'],1,true);hcc_ok('Dedicated project is never duplicated by completed retry',hcc_state()===$before);
    $stage='history-refusal';
    $saved=one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=?',[hcc_id(30)]);query('DELETE FROM '.table('hosting_checkout_results').' WHERE id=?',[hcc_id(30)]);$damaged=hcc_state();
    hcc_deny('Lost completed result refuses reconstruction',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));hcc_ok('Lost-result retry does not recreate financial history',hcc_state()===$damaged);hcc_restore('hosting_checkout_results',$saved);
    $saved=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE invoice_id=?',[$result['invoice_id']]);
    query('SET FOREIGN_KEY_CHECKS=0');try{query('DELETE FROM '.table('recurring_billing_invoices').' WHERE id=?',[$saved['id']]);}finally{query('SET FOREIGN_KEY_CHECKS=1');}
    $damaged=hcc_state();hcc_deny('Missing first-invoice map refuses completed replay',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));hcc_ok('Lost-map retry never reissues first invoice',hcc_state()===$damaged);hcc_restore('recurring_billing_invoices',$saved);
    $origin=one('SELECT * FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['recurring_billing.checkout_issued','% invoice '.$result['invoice_id']]);if(!$origin)throw new RuntimeException('Expected exact origin missing before deliberate corruption.');
    query('DELETE FROM '.table('audit').' WHERE id=?',[$origin['id']]);$damaged=hcc_state();hcc_deny('Missing independent client invoice origin refuses replay',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));hcc_ok('Missing-origin refusal leaves damaged rows for restoration',hcc_state()===$damaged);hcc_restore('audit',$origin);
    $count=one('SELECT * FROM '.table('settings').' WHERE name=?',['hosting_checkout_results_count']);query('DELETE FROM '.table('settings').' WHERE name=?',['hosting_checkout_results_count']);hcc_deny('Missing retained count refuses replay',fn()=>hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true));hcc_restore('settings',$count);unset($GLOBALS['cwp_settings']);
    $hash=one('SELECT document_sha256 FROM '.table('hosting_checkout_reviews').' WHERE id=?',[hcc_id(30)])['document_sha256'];query('UPDATE '.table('hosting_checkout_reviews').' SET document_sha256=? WHERE id=?',[str_repeat('0',64),hcc_id(30)]);hcc_deny('Changed retained review fingerprint refuses historical access',fn()=>hosting_checkout_result(hcc_id(30),1));query('UPDATE '.table('hosting_checkout_reviews').' SET document_sha256=? WHERE id=?',[$hash,hcc_id(30)]);
    hcc_ok('Restoring exact retained bytes returns complete history ready',hcc_ready()&&hosting_checkout_result(hcc_id(30),1)['invoice_id']===$result['invoice_id']);
    hcc_ok('Invoice confirmation alone records no receipt card request or provider write',hcc_count('invoice_payments')===0&&hcc_count('payment_requests')===0&&$calls===1);
    $stage='local-receipt-policy';hcc_owner();service_orders_migrate($password);hosting_order_links_prepare($password);
    $agreement=service_plans_validate(service_plans_row($result['agreement_id'],true),true);$invoice=ledger_invoice($result['invoice_id']);$otherInvoice=ledger_invoice($new['invoice_id']);$before=hcc_state();
    hcc_deny('Checkout order cannot substitute another same-client invoice',fn()=>service_orders_offer(hcc_id(72),$result['agreement_id'],1,$agreement->revision(),$agreement->fingerprint(),$new['invoice_id'],ledger_invoice_hash($otherInvoice),$password));hcc_ok('Wrong invoice refusal preserves exact checkout and payment rows',hcc_state()===$before);
    $order=service_orders_offer(hcc_id(70),$result['agreement_id'],1,$agreement->revision(),$agreement->fingerprint(),$result['invoice_id'],ledger_invoice_hash($invoice),$password);
    hcc_client();$order=service_orders_decide(hcc_id(70),1,0,hcc_id(71),'accept',['domain_name'=>'checkout.example','review_sha256'=>$order->fingerprint()],true);hcc_owner();
    $unpaid=recurring_billing_locked(fn()=>service_orders_assess($order,time()));hcc_ok('Accepted unpaid checkout remains commercially ineligible',!$unpaid['eligible']&&$unpaid['basis']===null&&!$unpaid['payment']['payment_evidence_ready']&&$unpaid['manual']===null);
    $mapping=recurring_billing_locked(fn()=>provider_plan_catalog_lookup(hcc_id(10)));$package=provider_plan_catalog_package($mapping['configuration'],hcc_id(70));$before=hcc_state();
    $manual=['reason'=>'Synthetic no-receipt bypass must refuse','expires_at'=>time()+3600];
    hcc_deny('Checkout manual-payment bypass refuses while unpaid',fn()=>service_orders_change(hcc_id(70),1,$order->revision(),hcc_id(73),'allow_manual',$manual,$password));
    hcc_deny('Unpaid checkout cannot prepare even its exact original mapping',fn()=>hosting_order_links_create(hcc_id(70),1,$order->revision(),$order->fingerprint(),hcc_id(80),$package,$profile,1,42,$password,$mapping['id']));hcc_ok('Unpaid and manual-policy refusals leave no service or altered history',hcc_state()===$before&&hcc_count('hosting_records')===0);
    $receiptArgs=[$result['invoice_id'],2500,'bank','Synthetic verified native checkout receipt',gmdate('Y-m-d'),hash('sha256','native-checkout-receipt'),ledger_review_hash($invoice)];
    $receipt=ledger_receive(...$receiptArgs);$before=hcc_state();hcc_ok('Exact synthetic verified-receipt retry is inert',ledger_receive(...$receiptArgs)===$receipt&&hcc_state()===$before);
    $paid=recurring_billing_locked(fn()=>service_orders_assess($order,time()));hcc_ok('Actual local receipt enables only the exact checkout invoice',$paid['eligible']&&$paid['basis']==='verified-payment'&&$paid['payment']['payment_evidence_ready']&&$paid['manual']===null&&ledger_invoice($result['invoice_id'])['status']==='paid'&&ledger_invoice($new['invoice_id'])['status']==='issued');
    $before=hcc_state();hcc_deny('Checkout manual-payment bypass refuses even after verified receipt',fn()=>service_orders_change(hcc_id(70),1,$order->revision(),hcc_id(74),'allow_manual',$manual,$password));hcc_deny('Paid checkout cannot bypass its publication mapping with manual mapping',fn()=>hosting_order_links_create(hcc_id(70),1,$order->revision(),$order->fingerprint(),hcc_id(80),$package,$profile,1,42,$password,null));hcc_ok('Paid bypass refusals preserve all rows',hcc_state()===$before);
    $link=hosting_order_links_create(hcc_id(70),1,$order->revision(),$order->fingerprint(),hcc_id(80),$package,$profile,1,42,$password,$mapping['id']);
    hcc_ok('Paid local preparation retains original invoice mapping and verified-payment basis',$order->data()['invoice']['id']===$result['invoice_id']&&$link['binding']['catalog']===['id'=>$mapping['id'],'sha256'=>$mapping['sha256']]&&$link['binding']['commercial']['basis']==='verified-payment'&&$link['binding']['commercial']['manual_event_id']===null);
    $before=hcc_state();$linked=hosting_order_links_create(hcc_id(70),1,$order->revision(),$order->fingerprint(),hcc_id(80),$package,$profile,1,42,$password,$mapping['id']);hcc_ok('Exact local preparation replay retains one hosting account',$linked['binding']===$link['binding']&&hcc_state()===$before&&hcc_count('hosting_records')===1);
    $saved=one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=?',[hcc_id(30)]);query('DELETE FROM '.table('hosting_checkout_results').' WHERE id=?',[hcc_id(30)]);$damaged=hcc_state();hcc_deny('Lost checkout result blocks even retained paid order access',fn()=>hosting_order_links_review(hcc_id(70),1));hcc_ok('Paid lost-graph refusal never recreates checkout rows',hcc_state()===$damaged);hcc_restore('hosting_checkout_results',$saved);
    $prepared=hosting_order_links_review(hcc_id(70),1);hcc_ok('Restored paid history remains local preparation without dispatch authority',$prepared['commercial']['eligible']&&$prepared['commercial']['basis']==='verified-payment'&&!$prepared['dispatch_authorized']&&!$prepared['provider_entitlement_verified']);hcc_client();
    $stage='stale-source';$stale=hcc_review(50,$pub);hcc_owner();$short=hcc_publish(21,$product,hcc_id(1),hcc_policy(5));hcc_client();
    hcc_deny('Superseded publication refuses an uncompleted review',fn()=>hosting_checkout_confirm(hcc_id(50),$stale['review_sha256'],1,true));
    $expires=hcc_review(51,$short);$deadline=microtime(true)+7;while(time()<$expires['expires_at']&&microtime(true)<$deadline)usleep(50000);
    hcc_ok('Expiry negative observes real elapsed wall clock without date shifts',time()>=$expires['expires_at']);hcc_deny('Actually expired retained review refuses confirmation',fn()=>hosting_checkout_confirm(hcc_id(51),$expires['review_sha256'],1,true));hcc_deny('Expired request ID cannot be silently refreshed',fn()=>hcc_review(51,$short));
    $fresh=hcc_review(52,$short);hcc_ok('A new request ID obtains a fresh review',$fresh['request_id']!==$expires['request_id']&&$fresh['review_sha256']!==$expires['review_sha256']);
    $long=hcc_publish(22,$product,hcc_id(1),hcc_policy());hcc_client();$connectionReview=hcc_review(53,$long);
    hcc_owner();provider_settings_state($profile,1,'disable',$password);hcc_client();$before=hcc_state();hcc_deny('Changed current provider connection refuses new confirmation',fn()=>hosting_checkout_confirm(hcc_id(53),$connectionReview['review_sha256'],1,true));hcc_ok('Changed connection refusal creates no commercial rows',hcc_state()===$before);
    $before=hcc_state();$historical=hosting_checkout_confirm(hcc_id(30),$review['review_sha256'],1,true);hcc_ok('Completed owned retry survives current offer and connection unavailability',$historical['replayed']&&$historical['invoice_id']===$result['invoice_id']&&hcc_state()===$before);
    hcc_ok('Only one local synthetic receipt exists with no card request attempt or provider write',hcc_count('invoice_payments')===1&&hcc_count('payment_requests')===0&&hcc_count('payment_attempts')===0&&$calls===1);
    $stage='complete';$report=['passed'=>true,'checks'=>$checks,'scope'=>'Native Linux SQL/current-client/current-clock first unpaid invoice, followed by local synthetic verified-receipt policy and local preparation; no HTTP, charge, provider write or provisioning','runtime'=>['php'=>PHP_VERSION,'database'=>(string)query('SELECT VERSION()')->fetchColumn()]];
}catch(Throwable $e){$report=['passed'=>false,'checks'=>$checks,'scope'=>'Native checkout fixture stopped; no acceptance beyond named outcomes','stage'=>$stage,'error'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null]];}
$bytes=json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";if($reportPathReady&&is_dir($qa)&&!is_link($qa))file_put_contents($qa.'/hosting-checkout-native-results.json',$bytes);echo $bytes;if(!$report['passed'])exit(1);
