<?php
/** Disposable recurring migration/history fixture; synthetic v2 rows are not a checkout writer. */
declare(strict_types=1);
$database=(string)getenv('CWP_TEST_DATABASE');$port=(int)getenv('CWP_TEST_PORT');
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_RECURRING_CHECKOUT_STORAGE')!=='1'||!in_array($database,['cwp_checkout_test','cwp_checkout_recurring_retry1'],true)||getenv('CWP_TEST_HOST')!=='127.0.0.1'||$port!==43319)exit(2);
$site=realpath((string)(getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site'));
if(!$site||!is_file($site.'/app/billing/recurring_billing_invoices.php'))exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$site);
require CWP_CODE_ROOT.'/includes/bootstrap.php';require CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout_billing_document.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1','storage'=>'','key'=>'','db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>$database,'user'=>(string)getenv('CWP_TEST_USER'),'pass'=>(string)getenv('CWP_TEST_PASSWORD'),'prefix'=>'crbc_main_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SESSION=[];
$checks=[];$password='Synthetic recurring checkout migration owner';
$planTerms=['name'=>'Reviewed shared hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['One reviewed website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during stated business hours'];
function rcn_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function rcn_bad(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|RuntimeException|LogicException|JsonException $e){if($e instanceof PDOException)throw $e;rcn_ok($name,true);return;}rcn_ok($name,false);}
function rcn_id(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function rcn_owner(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_support']);}
function rcn_marker():string{return one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_checkout_schema_version'])['value']??'';}
function rcn_setup(string $prefix):void {
    global $password;
    if(!in_array($prefix,['crbc_main_','crbc_start_','crbc_partial_','crbc_reverse_','crbc_unknown_','crbc_missing_'],true))throw new LogicException('Unknown owned fixture namespace.');
    $GLOBALS['cwp_config']['db']['prefix']=$prefix;unset($GLOBALS['cwp_settings']);
    $sql=schema_statements($prefix);
    foreach(['settings','users','audit','studio_clients','staff_profiles','studio_jobs','studio_invoices','invoice_payments'] as $name)db()->exec($sql[$name]);
    foreach([1,2] as $id)query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$id,'Recurring operator '.$id,'recurring-operator'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat((string)$id,32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (2,?,1,?)',['administrator',json_encode(array_keys(staff_permissions()),JSON_THROW_ON_ERROR)]);
    foreach([1,2] as $id){query('INSERT INTO '.table('studio_clients').' (id,name,email,auth_version,created_at) VALUES (?,?,?,?,?)',[$id,'Recurring client '.$id,'recurring-client'.$id.'@example.invalid',str_repeat('a',32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('studio_jobs').' (id,client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$id,'Existing project '.$id,'Retained scope','design','','2020-01-01 00:00:00','2020-01-01 00:00:00']);}
    foreach(['studio_schema_version'=>'2','payments_schema_version'=>'1','invoice_ledger_schema_version'=>'1'] as $k=>$v)save_setting($k,$v);
    rcn_owner();service_plans_migrate($password);service_plans_checkout_migrate($password);recurring_billing_migrate($password);
}
function rcn_insert(string $name,array $row):void {
    if(!in_array($name,['service_subscriptions','service_subscription_events','recurring_billing_events','recurring_billing_invoices','audit'],true))throw new LogicException('Unknown synthetic history table.');
    $fields=array_keys($row);foreach($fields as $field)if(!preg_match('/\A[a-z_][a-z0-9_]*\z/',$field))throw new LogicException('Unknown fixture column.');
    query('INSERT INTO '.table($name).' ('.implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($row));
}
function rcn_rows():array {$out=[];foreach(['service_plans','service_plan_events','service_subscriptions','service_subscription_events','recurring_billing_sources','recurring_billing_schedules','recurring_billing_events','recurring_billing_invoices','studio_invoices','audit'] as $n)$out[$n]=all('SELECT * FROM '.table($n).' ORDER BY id');return $out;}
function rcn_retained(array $old):bool {$now=rcn_rows();foreach($old as $name=>$rows){$indexed=array_column($now[$name],null,'id');foreach($rows as $row)if(!isset($indexed[$row['id']])||array_intersect_key($indexed[$row['id']],$row)!==$row)return false;}return true;}
function rcn_read(string $id):RecurringBillingSchedule{return recurring_billing_get($id,1);}
/** Raw synthetic native storage graph only; caller inserts all records before running public readers. */
function rcn_seed_schedule(RecurringBillingSchedule $d):array {
    $t=$d->data()['terms'];$id=$t['id'];$at=$d->data()['created_at'];$event=$d->data()['events'][0];
    query('INSERT INTO '.table('recurring_billing_sources').' (id,latest_id,latest_sha256,generations) VALUES (?,?,?,1)',[$t['source_subscription']['id'],$id,$d->fingerprint()]);
    query('INSERT INTO '.table('recurring_billing_schedules').' (id,subscription_id,generation,client_id,job_id,created_by,created_epoch,previous_id,previous_sha256,initial_json,journal_json,journal_sha256,revision,created_by_client) VALUES (?,?,1,?,?,NULL,?,NULL,NULL,?,?,?,1,?)',[$id,$t['source_subscription']['id'],$t['client_id'],$t['project_id'],$at,$d->json(),$d->json(),$d->fingerprint(),$t['client_id']]);
    $raw=$d->data();$raw['events']=[];
    rcn_insert('recurring_billing_events',['id'=>$event['id'],'schedule_id'=>$id,'actor_staff_id'=>null,'actor_client_id'=>$t['client_id'],'revision'=>1,'recorded_epoch'=>$at,'input_json'=>recurring_billing_json(['revision'=>0,'command'=>$event['command'],'arguments'=>$event['arguments']]),'before_sha256'=>hash('sha256',recurring_billing_json($raw)),'after_sha256'=>$d->fingerprint()]);
    $period=$d->assessment(0,$at)['period'];
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',[$t['project_id'],'RI-'.str_repeat('A',24),$t['title'],$t['amount_cents'],$t['currency'],recurring_billing_invoice_instructions($id,$period),gmdate('Y-m-d H:i:s',$at)]);
    $invoice=(int)db()->lastInsertId();$snapshot=recurring_billing_json(['period'=>$period,'invoice'=>recurring_billing_invoice_snapshot(one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$invoice]))]);
    $map=['id'=>rcn_id(900),'schedule_id'=>$id,'period_index'=>0,'invoice_id'=>$invoice,'staff_id'=>null,'issued_epoch'=>$at,'schedule_revision'=>1,'schedule_sha256'=>$d->fingerprint(),'request_sha256'=>recurring_billing_invoice_input($id,$t['client_id'],1,$d->fingerprint(),0,'client:'.$t['client_id']),'snapshot_json'=>$snapshot,'snapshot_sha256'=>hash('sha256',$snapshot),'client_id'=>$t['client_id']];
    rcn_insert('recurring_billing_invoices',$map);$detail=recurring_billing_checkout_invoice_detail($map,$d);
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (0,?,?,?)',['recurring_billing.checkout_issued',$detail,gmdate('Y-m-d H:i:s',$at)]);
    $auditId=(int)db()->lastInsertId(); // Capture before any intervening SELECT can change PDO's last-insert state.
    $savedMap=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE id=?',[$map['id']]);
    $savedAudit=$auditId>0?one('SELECT * FROM '.table('audit').' WHERE id=?',[$auditId]):null;
    if(!$savedMap||!$savedAudit)throw new RuntimeException('Synthetic checkout seed could not reload its inserted invoice map and audit origin.');
    return ['map'=>$savedMap,'audit'=>$savedAudit];
}

function rcn_fields(int $id,int $source=100):array {$s=service_plans_validate(service_plans_row(rcn_id($source),true),true);return ['format'=>1,'id'=>rcn_id($id),'client_id'=>1,'project_id'=>1,'source_subscription'=>['id'=>rcn_id($source),'revision'=>$s->revision(),'sha256'=>$s->fingerprint()],'title'=>'Agreed recurring care','terms'=>'Explicit whole-period invoice terms. No automatic payment.','currency'=>'USD','amount_cents'=>$s->model()->agreedPrice,'cadence'=>'monthly','anchor'=>gmdate('Y-m-d',time()+4*86400),'alignment'=>'day-of-month','timezone'=>'UTC','due_after_days'=>14,'collection'=>'invoice-only'];}

function rcn_historical_source(int $id,int $price=1001):void {
    $at=1570000000;$terms=['name'=>'Care','cadence'=>'monthly','price'=>$price,'currency'=>'USD','inclusions'=>['Care'],'exclusions'=>[],'limits'=>[],'support'=>'Business hours'];
    $p0=ServicePlanDocument::create(rcn_id($id+1),1,$at,$terms);$p=$p0->append(rcn_id($id+2),'activate',1,$at+1,[]);
    query('INSERT INTO '.table('service_plans').' (id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,1,?,?,?,?,1)',[rcn_id($id+1),$at,$p0->json(),$p->json(),$p->fingerprint()]);
    query('INSERT INTO '.table('service_plan_events').' (id,plan_id,actor_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,1,1,?,?,?,?)',[rcn_id($id+2),rcn_id($id+1),$at+1,service_plans_json(['revision'=>0,'command'=>'activate','arguments'=>[]]),$p0->fingerprint(),$p->fingerprint()]);
    $s0=ServiceSubscriptionDocument::create(rcn_id($id),1,1,$at+2,$p);$s1=$s0->append(rcn_id($id+3),'consent','client:1',$at+3,['terms_hash'=>$p->model()->termsHash()]);$s=$s1->append(rcn_id($id+4),'activate','staff:1',$at+4,[]);
    query('INSERT INTO '.table('service_subscriptions').' (id,plan_id,plan_revision,plan_sha256,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,1,?,1,1,?,?,?,?,2)',[rcn_id($id),rcn_id($id+1),$p->fingerprint(),$at+2,$s0->json(),$s->json(),$s->fingerprint()]);
    foreach([[$s0,$s1,null,1],[$s1,$s,1,null]] as [$before,$after,$staff,$client]){$e=$after->data()['events'][array_key_last($after->data()['events'])];query('INSERT INTO '.table('service_subscription_events').' (id,subscription_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$e['id'],rcn_id($id),$staff,$client,$after->revision(),$e['at'],service_plans_json(['revision'=>$before->revision(),'command'=>$e['command'],'arguments'=>$e['arguments']]),$before->fingerprint(),$after->fingerprint()]);}
}

function rcn_historical_billing(int $id,int $source):RecurringBillingSchedule {
    $f=rcn_fields($id,$source);$f['anchor']='2020-02-01';$t=RecurringBillingTerms::fromArray($f);$at=1577836800;$d0=RecurringBillingSchedule::create($t,1,$at);$d1=$d0->append(rcn_id($id+1),'accept','client:1',$at+86400,['terms_sha256'=>$t->fingerprint()]);$d=$d1->append(rcn_id($id+2),'activate','staff:1',$at+172800,['period_index'=>0]);
    query('INSERT INTO '.table('recurring_billing_sources').' (id,latest_id,latest_sha256,generations) VALUES (?,?,?,1)',[rcn_id($source),rcn_id($id),$d->fingerprint()]);
    query('INSERT INTO '.table('recurring_billing_schedules').' (id,subscription_id,generation,client_id,job_id,created_by,created_epoch,previous_id,previous_sha256,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,1,1,1,1,?,NULL,NULL,?,?,?,2)',[rcn_id($id),rcn_id($source),$at,$d0->json(),$d->json(),$d->fingerprint()]);
    foreach([[$d0,$d1,null,1],[$d1,$d,1,null]] as [$before,$after,$staff,$client]){$e=$after->data()['events'][array_key_last($after->data()['events'])];query('INSERT INTO '.table('recurring_billing_events').' (id,schedule_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$e['id'],rcn_id($id),$staff,$client,$after->revision(),$e['at'],recurring_billing_json(['revision'=>$before->revision(),'command'=>$e['command'],'arguments'=>$e['arguments']]),$before->fingerprint(),$after->fingerprint()]);}
    return $d;
}

function rcn_seed_checkout(ServiceSubscriptionDocument $doc,ServicePlanDocument $plan):void {
    $d=$doc->data();$json=$doc->json();
    rcn_insert('service_subscriptions',['id'=>$d['id'],'plan_id'=>$plan->data()['id'],'plan_revision'=>$plan->revision(),'plan_sha256'=>$plan->fingerprint(),'client_id'=>$d['client_id'],'created_by'=>null,'created_epoch'=>$d['created_at'],'initial_json'=>$json,'journal_json'=>$json,'journal_sha256'=>$doc->fingerprint(),'revision'=>2,'created_by_client'=>$d['created_by_client']]);
    $prefix=$d;$prefix['events']=[];$before=hash('sha256',service_plans_json($prefix));
    foreach($d['events'] as $i=>$event){
        $prefix['events'][]=$event;$after=hash('sha256',service_plans_json($prefix));
        rcn_insert('service_subscription_events',['id'=>$event['id'],'subscription_id'=>$d['id'],'actor_staff_id'=>null,'actor_client_id'=>$i===0?$d['client_id']:null,'revision'=>$i+1,'recorded_epoch'=>$event['at'],'input_json'=>service_plans_json(['revision'=>$i,'command'=>$event['command'],'arguments'=>$event['arguments']]),'before_sha256'=>$before,'after_sha256'=>$after,'actor_system'=>$i===1?'hosting-checkout':null]);
        $before=$after;
    }
}

function rcn_document_fixture(int $at,ServicePlanDocument $plan):array {
    $cadence='monthly';$timezone='UTC';$alignment='day-of-month';$ttl=900;$mappingExpiry=null;$large=false;
    $terms=['name'=>'Reviewed shared hosting','cadence'=>$cadence,'price'=>2500,'currency'=>'USD','inclusions'=>['One reviewed website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during stated business hours'];
    if($large){$terms['inclusions']=array_fill(0,32,str_repeat('x',1000));$terms['exclusions']=array_fill(0,32,str_repeat('y',1000));}
    $p=['id'=>42,'revision'=>3,'sku'=>'HOST-811','title'=>$terms['name'],'family'=>'hosting','status'=>'draft','term'=>$cadence==='monthly'?'month':'year','currency'=>'USD','amount_cents'=>2500,'setup_cents'=>0,'summary'=>'Reviewed hosting for one website.','features'=>'PRIVATE_DRAFT_FEATURES','terms'=>'PRIVATE_DRAFT_TERMS','media_id'=>str_repeat('e',32)];
    $i=['id'=>7,'product_id'=>42,'profile_id'=>str_repeat('a',32),'source_revision'=>1,'refresh_id'=>str_repeat('2',32),'source_key_fingerprint'=>hash('sha256','private inventory HMAC'),'type_ref'=>'811','type_name'=>'Original observed label','source_hash'=>''];
    $i['source_hash']=hash('sha256',json_encode([$i['profile_id'],$i['source_revision'],$i['refresh_id'],$i['source_key_fingerprint'],$i['type_ref'],$i['type_name']],JSON_THROW_ON_ERROR));
    $o=['refresh_id'=>str_repeat('f',32),'profile_id'=>$i['profile_id'],'profile_revision'=>3,'key_fingerprint'=>$i['source_key_fingerprint'],'observed_epoch'=>$at,'types'=>[['id'=>'811','label'=>'Current observed type']]];
    $m=['format'=>1,'id'=>str_repeat('d',32),'plan_id'=>$plan->data()['id'],'revision'=>1,'previous_id'=>null,'previous_sha256'=>null,'plan_revision'=>$plan->revision(),'plan_sha256'=>$plan->fingerprint(),'profile_id'=>$o['profile_id'],'profile_revision'=>3,'credential_sha256'=>hash('sha256','private ciphertext'),'created_by'=>1,'owner_auth'=>'PRIVATE_MAPPING_OWNER_AUTH','created_at'=>$at,'expires_at'=>$mappingExpiry??$at+86400*7,'enabled'=>true,'configuration'=>['reseller_id'=>42,'type'=>'811','label'=>'PRIVATE_PROVIDER_LABEL','region'=>'PRIVATE_REGION','limits'=>['websites'=>1],'cost'=>['amount'=>'7.5000','currency'=>'GBP','period_months'=>12,'tax'=>'unknown','basis'=>'shared-account','reference'=>'PRIVATE_WHOLESALE_REFERENCE']],'evidence'=>'owner-account-review'];
    $c=['publication'=>['id'=>str_repeat('1',32),'sha256'=>null,'enabled'=>true],'product'=>['id'=>42,'revision'=>3,'sha256'=>hash('sha256',service_plans_json($p))],
        'plan'=>['id'=>$m['plan_id'],'revision'=>$plan->revision(),'sha256'=>$plan->fingerprint(),'status'=>'active'],'mapping'=>['id'=>$m['id'],'sha256'=>hash('sha256',service_plans_json($m))],
        'profile'=>['id'=>$o['profile_id'],'revision'=>3,'enabled'=>true,'credential_sha256'=>$m['credential_sha256'],'key_fingerprint'=>$o['key_fingerprint']],
        'catalog'=>['refresh_id'=>$o['refresh_id'],'state'=>'complete','profile_id'=>$o['profile_id'],'profile_revision'=>3,'key_fingerprint'=>$o['key_fingerprint'],'observed_epoch'=>$o['observed_epoch'],'types_sha256'=>hosting_catalog_types_hash($o['types'])],
        'publisher'=>['id'=>1,'role'=>'owner','enabled'=>true,'auth_version'=>'PRIVATE_PUBLISHER_AUTH'],'mapping_reviewer'=>['id'=>1,'role'=>'owner','enabled'=>true,'auth_version'=>$m['owner_auth']],'support_session'=>false];
    $policy=['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>$timezone,'alignment'=>$alignment,'review_ttl_seconds'=>$ttl];
    $legacy=HostingCatalogPublication::create(str_repeat('1',32),$at,$p,$i,$o,$plan,service_plans_json($m),$c);
    $pub=HostingCatalogPublication::createCheckout(str_repeat('1',32),$at,$p,$i,$o,$plan,service_plans_json($m),$c,$policy);$c['publication']['sha256']=$pub->fingerprint();
    return compact('at','terms','plan','p','i','o','m','c','policy','legacy','pub');
}
try {
    rcn_ok('Fixture connects only to the explicitly owned database and port',(int)query('SELECT @@port')->fetchColumn()===$port&&query('SELECT DATABASE()')->fetchColumn()===$database);
    rcn_ok('Separate recurring checkout namespace is initially empty',!all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=?',['crbc_']));
    rcn_setup('crbc_main_');rcn_ok('Legacy setup does not enable recurring checkout',recurring_billing_ready()&&!recurring_billing_checkout_ready()&&rcn_marker()===''&&recurring_billing_checkout_schema_stage()===0);
    rcn_historical_source(100);$legacy=rcn_historical_billing(300,100);$issued=recurring_billing_issue(rcn_id(300),1,$legacy->revision(),$legacy->fingerprint(),0,rcn_id(400));
    $old=rcn_rows();$oldOrigin=recurring_billing_invoice_origin($issued['invoice_id']);
    rcn_ok('Existing staff issuance retains exact legacy origin shape',array_keys($oldOrigin)===['staff_id','schedule_id','period_index','created_at']&&$oldOrigin['staff_id']===1&&$oldOrigin['schedule_id']===rcn_id(300)&&$oldOrigin['period_index']===0);
    rcn_bad('Wrong owner password cannot begin extension',fn()=>recurring_billing_checkout_migrate('wrong'));
    rcn_owner(2);rcn_bad('Delegated financial administrator cannot prepare extension',fn()=>recurring_billing_checkout_migrate($password));rcn_owner();
    $auth=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('f',32)]);rcn_bad('Stale owner session cannot prepare extension',fn()=>recurring_billing_checkout_migrate($password));query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$auth]);rcn_owner();
    $_GET['support']='';rcn_bad('Support selector cannot prepare extension',fn()=>recurring_billing_checkout_migrate($password));unset($_GET['support']);
    db()->beginTransaction();rcn_bad('Inherited transaction is refused before migration',fn()=>recurring_billing_checkout_migrate($password));rcn_ok('Inherited transaction remains caller owned',db()->inTransaction());db()->rollBack();
    rcn_ok('Denied setup preserves original rows and empty marker',rcn_rows()===$old&&rcn_marker()===''&&recurring_billing_checkout_schema_stage()===0);
    $event=one('SELECT * FROM '.table('recurring_billing_events').' WHERE id=?',[rcn_id(301)]);query('DELETE FROM '.table('recurring_billing_events').' WHERE id=?',[rcn_id(301)]);
    rcn_bad('Lost original consent refuses migration before any ALTER',fn()=>recurring_billing_checkout_migrate($password));rcn_insert('recurring_billing_events',$event);
    rcn_ok('History preflight preserves schema and original restored rows',rcn_marker()===''&&recurring_billing_checkout_schema_stage()===0&&rcn_rows()===$old);
    $oldAudit=one('SELECT * FROM '.table('audit').' WHERE action=?',['recurring_billing.issued']);query('DELETE FROM '.table('audit').' WHERE id=?',[$oldAudit['id']]);
    rcn_bad('Missing independent legacy invoice origin blocks extension',fn()=>recurring_billing_checkout_migrate($password));rcn_insert('audit',$oldAudit);
    db()->exec("CREATE TRIGGER crbc_main_fail BEFORE INSERT ON ".table('audit')." FOR EACH ROW BEGIN IF NEW.action='recurring_billing.checkout_setup' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic recurring checkout audit refusal'; END IF; END");
    $failed=false;try{recurring_billing_checkout_migrate($password);}catch(PDOException $e){$failed=$e->getCode()==='45000';}finally{db()->exec('DROP TRIGGER crbc_main_fail');}
    rcn_ok('Audit failure retains recognized completed DDL but not active marker',$failed&&rcn_marker()==='preparing-1'&&recurring_billing_checkout_schema_stage()===2);
    rcn_bad('Preparing state blocks application readiness',fn()=>recurring_billing_ready());rcn_ok('Migration preserves all original fields and document bytes',rcn_retained($old));
    recurring_billing_checkout_migrate($password);rcn_ok('Explicit retry completes the known interrupted migration',recurring_billing_checkout_ready()&&rcn_marker()==='1');
    rcn_ok('Original marker legacy invoice and legacy documents remain exact',setting('recurring_billing_schema_version')==='1'&&rcn_retained($old)&&rcn_read(rcn_id(300))->json()===$legacy->json()&&recurring_billing_invoice_origin($issued['invoice_id'])===$oldOrigin);
    recurring_billing_migrate($password);rcn_ok('Original setup entry preserves and verifies extension',recurring_billing_checkout_ready()&&rcn_retained($old));
    $legacyRetry=recurring_billing_issue(rcn_id(300),1,$legacy->revision(),$legacy->fingerprint(),0,rcn_id(400));rcn_ok('Legacy staff replay after migration returns its original invoice',$legacyRetry['replayed']&&$legacyRetry['invoice_id']===$issued['invoice_id']);
    $next=recurring_billing_issue(rcn_id(300),1,$legacy->revision(),$legacy->fingerprint(),1,rcn_id(401));rcn_ok('New legacy issuance still has only its real staff actor',!$next['replayed']&&one('SELECT client_id FROM '.table('recurring_billing_invoices').' WHERE id=?',[rcn_id(401)])['client_id']===null);
    foreach(['future','2'] as $v){save_setting('recurring_billing_checkout_schema_version',$v);rcn_bad('Unknown marker refuses readiness '.$v,fn()=>recurring_billing_ready());rcn_bad('Unknown marker refuses migration '.$v,fn()=>recurring_billing_checkout_migrate($password));}save_setting('recurring_billing_checkout_schema_version','');
    rcn_bad('Unmarked extended schema refuses readiness',fn()=>recurring_billing_ready());rcn_bad('Unmarked extended schema refuses adoption',fn()=>recurring_billing_checkout_migrate($password));save_setting('recurring_billing_checkout_schema_version','1');
    db()->exec('ALTER TABLE '.table('recurring_billing_schedules').' ADD unknown_flag INT NULL');rcn_bad('Unknown column refuses readiness',fn()=>recurring_billing_ready());db()->exec('ALTER TABLE '.table('recurring_billing_schedules').' DROP COLUMN unknown_flag');
    db()->exec('ALTER TABLE '.table('recurring_billing_invoices').' ADD INDEX unknown_extension(issued_epoch)');rcn_bad('Unknown index refuses readiness',fn()=>recurring_billing_ready());db()->exec('ALTER TABLE '.table('recurring_billing_invoices').' DROP INDEX unknown_extension');
    $p=service_plans_create(rcn_id(600),$planTerms,$password);$p=service_plans_change(rcn_id(600),0,rcn_id(601),'activate',[],$password);
    $f=rcn_document_fixture(time(),$p);$review=HostingCheckoutReview::create(rcn_id(700),$f['pub'],1,['mode'=>'existing','id'=>1],$f['at'],$f['c']);
    $consent=HostingCheckoutConsent::create($review,$review->fingerprint(),true,$f['pub'],$f['c'],1,1,1,$f['at']);
    $subscription=ServiceSubscriptionDocument::createCheckout(rcn_id(710),$consent,$f['pub'],1,rcn_id(711),rcn_id(712));
    rcn_seed_checkout($subscription,$p);$terms=hosting_checkout_billing_terms(rcn_id(800),$subscription);$schedule=RecurringBillingSchedule::createCheckout($terms,rcn_id(801));
    $seed=staff_locked(fn()=>staff_transaction(fn()=>rcn_seed_schedule($schedule)));$map=$seed['map'];$audit=$seed['audit'];$invoice=ledger_invoice((int)$map['invoice_id']);
    rcn_ok('Complete synthetic format2 graph passes real native history reader',rcn_read(rcn_id(800))->json()===$schedule->json()&&recurring_billing_checkout_ready());
    $row=recurring_billing_row(rcn_id(800));$first=one('SELECT * FROM '.table('recurring_billing_events').' WHERE id=?',[rcn_id(801)]);$raw=$schedule->data();$raw['events']=[];
    rcn_ok('Initial document is completed revision one with actual client attribution',$row['initial_json']===$schedule->json()&&(int)$row['revision']===1&&$row['created_by']===null&&(int)$row['created_by_client']===1&&$first['actor_staff_id']===null&&(int)$first['actor_client_id']===1);
    rcn_bad('Raw empty event witness is never a valid format2 document',fn()=>RecurringBillingSchedule::fromJson(recurring_billing_json($raw)));
    rcn_ok('Initial event binds raw zero prefix and completed schedule',hash('sha256',recurring_billing_json($raw))===$first['before_sha256']&&$schedule->fingerprint()===$first['after_sha256']);
    $origin=recurring_billing_invoice_origin((int)$map['invoice_id']);
    rcn_ok('Client invoice origin exactly roundtrips all named accepted facts',$origin===(recurring_billing_checkout_origin_facts($map,$schedule)+['created_at'=>gmdate('Y-m-d H:i:s',(int)$map['issued_epoch'])])&&strlen($audit['detail'])<=400&&$audit['detail']===recurring_billing_checkout_invoice_detail($map,$schedule));
    rcn_ok('First invoice is an exact existing-ledger obligation at accepted instant',ledger_balance($invoice)['due_cents']===2500&&(int)$map['issued_epoch']===$consent->data()['accepted_at']&&$map['staff_id']===null&&(int)$map['client_id']===1);
    rcn_bad('Legacy offer path cannot create checkout billing',fn()=>recurring_billing_offer($terms->data()));
    rcn_bad('Staff cannot adopt client issuance identity',fn()=>recurring_billing_issue(rcn_id(800),1,1,$schedule->fingerprint(),0,rcn_id(900)));
    rcn_bad('Staff cannot generate another checkout first invoice',fn()=>recurring_billing_issue(rcn_id(800),1,1,$schedule->fingerprint(),0,rcn_id(901)));
    rcn_bad('Future second period remains unavailable now',fn()=>recurring_billing_issue(rcn_id(800),1,1,$schedule->fingerprint(),1,rcn_id(902)));
    query('UPDATE '.table('recurring_billing_schedules').' SET created_by=1 WHERE id=?',[rcn_id(800)]);rcn_bad('Checkout cannot add fictitious staff creator',fn()=>rcn_read(rcn_id(800)));query('UPDATE '.table('recurring_billing_schedules').' SET created_by=NULL WHERE id=?',[rcn_id(800)]);
    query('UPDATE '.table('recurring_billing_schedules').' SET created_by_client=2 WHERE id=?',[rcn_id(800)]);rcn_bad('Different client creator refuses native history',fn()=>rcn_read(rcn_id(800)));query('UPDATE '.table('recurring_billing_schedules').' SET created_by_client=1 WHERE id=?',[rcn_id(800)]);
    query('UPDATE '.table('recurring_billing_events').' SET actor_staff_id=1 WHERE id=?',[rcn_id(801)]);rcn_bad('Fixed client event refuses second human actor',fn()=>rcn_read(rcn_id(800)));query('UPDATE '.table('recurring_billing_events').' SET actor_staff_id=NULL WHERE id=?',[rcn_id(801)]);
    query('UPDATE '.table('recurring_billing_invoices').' SET staff_id=1 WHERE id=?',[rcn_id(900)]);rcn_bad('Invoice cannot acquire fictitious staff attribution',fn()=>rcn_read(rcn_id(800)));query('UPDATE '.table('recurring_billing_invoices').' SET staff_id=NULL WHERE id=?',[rcn_id(900)]);
    query('UPDATE '.table('recurring_billing_invoices').' SET client_id=2 WHERE id=?',[rcn_id(900)]);rcn_bad('Invoice client must match retained consent',fn()=>rcn_read(rcn_id(800)));query('UPDATE '.table('recurring_billing_invoices').' SET client_id=1 WHERE id=?',[rcn_id(900)]);
    query('UPDATE '.table('audit').' SET detail=? WHERE id=?',[str_replace($origin['request_input_sha256'],str_repeat('0',64),$audit['detail']),$audit['id']]);rcn_bad('Changed independent input binding refuses obligation',fn()=>rcn_read(rcn_id(800)));query('UPDATE '.table('audit').' SET detail=? WHERE id=?',[$audit['detail'],$audit['id']]);
    query('DELETE FROM '.table('audit').' WHERE id=?',[$audit['id']]);rcn_bad('Lost independent client origin blocks invoice reader',fn()=>recurring_billing_invoice_guard($invoice));rcn_bad('Lost origin also blocks explicit ready migration',fn()=>recurring_billing_checkout_migrate($password));rcn_insert('audit',$audit);
    $duplicate=$audit;unset($duplicate['id']);rcn_insert('audit',$duplicate);$duplicateId=(int)db()->lastInsertId();rcn_bad('Conflicting duplicate origin refuses exact replay',fn()=>rcn_read(rcn_id(800)));query('DELETE FROM '.table('audit').' WHERE id=?',[$duplicateId]);
    query('DELETE FROM '.table('recurring_billing_invoices').' WHERE id=?',[rcn_id(900)]);
    rcn_bad('Completed schedule cannot omit its first obligation',fn()=>rcn_read(rcn_id(800)));
    rcn_bad('Lost map plus renamed display reference still has independent origin',fn()=>recurring_billing_invoice_guard(array_replace($invoice,['reference'=>'Unrelated display reference'])));
    rcn_bad('Staff cannot regenerate missing checkout period zero',fn()=>recurring_billing_issue(rcn_id(800),1,1,$schedule->fingerprint(),0,rcn_id(903)));
    rcn_ok('Missing map remains discoverable by obligation origin',recurring_billing_invoice_obligation_origin(rcn_id(800),0));rcn_insert('recurring_billing_invoices',$map);
    query('UPDATE '.table('recurring_billing_schedules').' SET initial_json=? WHERE id=?',[recurring_billing_json($raw),rcn_id(800)]);rcn_bad('Stored raw skeleton cannot replace completed initial document',fn()=>rcn_read(rcn_id(800)));query('UPDATE '.table('recurring_billing_schedules').' SET initial_json=? WHERE id=?',[$schedule->json(),rcn_id(800)]);
    save_setting('recurring_billing_checkout_schema_version','preparing-1');rcn_bad('Preparing migration cannot adopt preexisting format2 graph',fn()=>recurring_billing_checkout_migrate($password));save_setting('recurring_billing_checkout_schema_version','1');
    $later=recurring_billing_change(rcn_id(800),1,1,rcn_id(810),'pause',['period_index'=>1,'reason'=>'Pause next billing period']);
    $later=recurring_billing_change(rcn_id(800),1,2,rcn_id(811),'resume',['period_index'=>2,'reason'=>'Resume after planned pause']);
    $subLater=service_plans_subscription_change(rcn_id(710),1,2,rcn_id(713),'pause',[],$password);
    rcn_ok('Later schedule and subscription events retain original first obligation',rcn_read(rcn_id(800))->json()===$later->json()&&ledger_balance(ledger_invoice((int)$map['invoice_id']))['due_cents']===2500&&recurring_billing_invoice_origin((int)$map['invoice_id'])===$origin&&recurring_billing_row(rcn_id(800))['initial_json']===$schedule->json());
    recurring_billing_checkout_migrate($password);rcn_ok('Repeated preparation validates both formats without rewriting history',recurring_billing_checkout_ready()&&rcn_retained($old));
    foreach([['crbc_start_',null],['crbc_partial_',0],['crbc_reverse_',1]] as [$namespace,$step]){
        rcn_setup($namespace);save_setting('recurring_billing_checkout_schema_version','preparing-1');if($step!==null)db()->exec(recurring_billing_checkout_steps()[$step]);
        rcn_bad('Partial application readiness refuses '.$namespace,fn()=>recurring_billing_ready());
        if($step===1)rcn_bad('Reversed DDL cannot be resumed',fn()=>recurring_billing_checkout_migrate($password));else{recurring_billing_checkout_migrate($password);rcn_ok('Recognized DDL interruption resumes '.$namespace,recurring_billing_checkout_ready());}
    }
    rcn_setup('crbc_unknown_');save_setting('recurring_billing_checkout_schema_version','preparing-1');db()->exec('ALTER TABLE '.table('recurring_billing_schedules').' ADD created_by_client INT UNSIGNED NULL AFTER revision');rcn_bad('Incomplete column without reviewed FK and index refuses adoption',fn()=>recurring_billing_checkout_migrate($password));
    rcn_setup('crbc_missing_');db()->exec('RENAME TABLE '.table('recurring_billing_events').' TO crbc_missing_preserved_events');rcn_bad('Missing established original table is not recreated',fn()=>recurring_billing_checkout_migrate($password));rcn_ok('Refusal preserves missing table state',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['crbc_missing_recurring_billing_events']));
    $report=['passed'=>true,'php'=>PHP_VERSION,'database_version'=>query('SELECT VERSION()')->fetchColumn(),'port'=>$port,'scope'=>'Disposable native migration, real legacy wrappers and synthetic v2 retained-history storage only. No atomic client checkout writer, independent hosting checkout request store, payment, provider, HTTP or purchase acceptance. No clock adjustment.','checks'=>$checks];
}catch(Throwable $e){$report=['passed'=>false,'php'=>PHP_VERSION,'checks'=>$checks,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'code'=>(string)$e->getCode(),'message'=>$e instanceof PDOException?'Database error; original SQL fixture did not complete.':$e->getMessage()]];}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;exit($report['passed']?0:1);
