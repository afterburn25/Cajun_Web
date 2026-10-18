<?php
/** Disposable SQL migration/retained-history test. No provider, vault or private-file fixture. */
declare(strict_types=1);
$database=(string)getenv('CWP_TEST_DATABASE');$port=(int)getenv('CWP_TEST_PORT');
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_CHECKOUT_STORAGE')!=='1'||!in_array($database,['cwp_checkout_test','cwp_checkout_retry1'],true)||getenv('CWP_TEST_HOST')!=='127.0.0.1'||$port!==43319)exit(2);
$site=realpath((string)(getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site'));
if(!$site||!is_file($site.'/app/plans/records.php'))exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$site);
require CWP_CODE_ROOT.'/includes/bootstrap.php';
require CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/app/plans/records.php';
require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout_document.php';
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1','storage'=>'','key'=>'','db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>$database,'user'=>(string)getenv('CWP_TEST_USER'),'pass'=>(string)getenv('CWP_TEST_PASSWORD'),'prefix'=>'cscp_main_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SESSION=[];
$checks=[];$password='Synthetic checkout migration owner';
$terms=['name'=>'Reviewed shared hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['One reviewed website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during stated business hours'];
function scn_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function scn_bad(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;scn_ok($name,true);return;}scn_ok($name,false);}
function scn_id(int $n):string {return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function scn_owner(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function scn_client(int $id=9):void {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];}
function scn_marker():string {return one('SELECT value FROM '.table('settings').' WHERE name=?',['service_plans_checkout_schema_version'])['value']??'';}
function scn_setup(string $prefix):void {
    global $password;
    if(!in_array($prefix,['cscp_main_','cscp_start_','cscp_partial_','cscp_reverse_','cscp_unknown_','cscp_missing_'],true))throw new LogicException('Unknown owned fixture namespace.');
    $GLOBALS['cwp_config']['db']['prefix']=$prefix;unset($GLOBALS['cwp_settings']);
    $sql=schema_statements($prefix);
    foreach(['settings','users','audit','studio_clients','staff_profiles'] as $name)db()->exec($sql[$name]);
    foreach([1,2] as $id)query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$id,'Migration operator '.$id,'migration'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat((string)$id,32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (2,?,1,?)',['administrator',json_encode(array_keys(staff_permissions()),JSON_THROW_ON_ERROR)]);
    foreach([9,10] as $id)query('INSERT INTO '.table('studio_clients').' (id,name,email,auth_version,created_at) VALUES (?,?,?,?,?)',[$id,'Migration client '.$id,'migration-client'.$id.'@example.invalid',str_repeat('a',32),gmdate('Y-m-d H:i:s')]);
    save_setting('studio_schema_version','2');scn_owner();service_plans_migrate($password);
}
function scn_rows():array {
    $rows=[];foreach(['service_plans','service_plan_events','service_subscriptions','service_subscription_events','audit'] as $name)$rows[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');return $rows;
}
function scn_retained(array $old):bool {
    $now=scn_rows();foreach($old as $name=>$rows){$indexed=array_column($now[$name],null,'id');foreach($rows as $row){$current=$indexed[$row['id']]??null;if(!$current||array_intersect_key($current,$row)!==$row)return false;}}return true;
}
function scn_insert(string $name,array $row):void {
    if(!in_array($name,['service_subscriptions','service_subscription_events'],true))throw new LogicException('Unknown synthetic history table.');
    $fields=array_keys($row);foreach($fields as $field)if(!preg_match('/\A[a-z_][a-z0-9_]*\z/',$field))throw new LogicException('Unknown fixture field.');
    query('INSERT INTO '.table($name).' ('.implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($row));
}
function scn_update(string $name,string $id,array $changes):void {
    $allowed=match($name){'service_subscriptions'=>['created_by','created_by_client'],'service_subscription_events'=>['actor_system','actor_staff_id','actor_client_id','after_sha256'],default=>throw new LogicException('Unknown history table.')};
    if(!$changes||array_diff(array_keys($changes),$allowed))throw new LogicException('Unknown synthetic mutation.');
    query('UPDATE '.table($name).' SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($changes))).' WHERE id=?',[...array_values($changes),$id]);
}
/** Synthetic native historical row only; this is not the authorized checkout creation writer. */
function scn_seed_checkout(ServiceSubscriptionDocument $doc,ServicePlanDocument $plan):void {
    $d=$doc->data();$json=$doc->json();
    scn_insert('service_subscriptions',['id'=>$d['id'],'plan_id'=>$plan->data()['id'],'plan_revision'=>$plan->revision(),'plan_sha256'=>$plan->fingerprint(),'client_id'=>$d['client_id'],'created_by'=>null,'created_epoch'=>$d['created_at'],'initial_json'=>$json,'journal_json'=>$json,'journal_sha256'=>$doc->fingerprint(),'revision'=>2,'created_by_client'=>$d['created_by_client']]);
    $prefix=$d;$prefix['events']=[];$before=hash('sha256',service_plans_json($prefix));
    foreach($d['events'] as $i=>$event){
        $prefix['events'][]=$event;$after=hash('sha256',service_plans_json($prefix));
        scn_insert('service_subscription_events',['id'=>$event['id'],'subscription_id'=>$d['id'],'actor_staff_id'=>null,'actor_client_id'=>$i===0?$d['client_id']:null,'revision'=>$i+1,'recorded_epoch'=>$event['at'],'input_json'=>service_plans_json(['revision'=>$i,'command'=>$event['command'],'arguments'=>$event['arguments']]),'before_sha256'=>$before,'after_sha256'=>$after,'actor_system'=>$i===1?'hosting-checkout':null]);
        $before=$after;
    }
}

function scn_document_fixture(int $at,ServicePlanDocument $plan):array {
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
    scn_ok('Fixture connects only to the explicitly owned database and port',(int)query('SELECT @@port')->fetchColumn()===$port&&query('SELECT DATABASE()')->fetchColumn()===$database);
    scn_ok('Owned checkout table namespace is initially empty',!all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,5)=?',['cscp_']));
    scn_setup('cscp_main_');scn_ok('Legacy setup leaves checkout unprepared',service_plans_ready()&&!service_plans_checkout_ready()&&scn_marker()===''&&service_plans_checkout_schema_stage()===0);
    $p=service_plans_create(scn_id(1),$terms,$password);$p=service_plans_change(scn_id(1),0,scn_id(2),'activate',[],$password);
    $s=service_plans_offer(scn_id(3),9,$p->data()['id'],$p->revision(),$p->fingerprint(),$password);
    scn_client();$s=service_plans_client_change(scn_id(3),9,0,scn_id(4),'consent',['terms_hash'=>$p->model()->termsHash()],true);scn_owner();
    $s=service_plans_subscription_change(scn_id(3),9,1,scn_id(5),'activate',[],$password);$legacyJson=$s->json();$old=scn_rows();
    scn_bad('Wrong owner password cannot begin extension',fn()=>service_plans_checkout_migrate('wrong'));
    $auth=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('f',32)]);scn_bad('Stale owner authorization cannot prepare extension',fn()=>service_plans_checkout_migrate($password));query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$auth]);scn_owner();
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');scn_bad('Disabled owner cannot prepare extension',fn()=>service_plans_checkout_migrate($password));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');scn_owner();
    scn_owner(2);scn_bad('Delegated administrator cannot prepare extension',fn()=>service_plans_checkout_migrate($password));scn_owner();
    $_GET['support']='';scn_bad('Support selector cannot prepare extension',fn()=>service_plans_checkout_migrate($password));unset($_GET['support']);
    db()->beginTransaction();scn_bad('Inherited transaction is refused before migration',fn()=>service_plans_checkout_migrate($password));scn_ok('Inherited transaction remains caller owned',db()->inTransaction());db()->rollBack();
    scn_ok('Denied setup preserves all original records and audits',scn_rows()===$old&&scn_marker()===''&&service_plans_checkout_schema_stage()===0);
    $event=one('SELECT * FROM '.table('service_subscription_events').' WHERE id=?',[scn_id(4)]);query('DELETE FROM '.table('service_subscription_events').' WHERE id=?',[scn_id(4)]);
    scn_bad('Lost original consent prevents any checkout ALTER',fn()=>service_plans_checkout_migrate($password));scn_insert('service_subscription_events',$event);
    scn_ok('Failed history preflight leaves original schema unchanged',scn_marker()===''&&service_plans_checkout_schema_stage()===0&&scn_rows()===$old);
    db()->exec("CREATE TRIGGER cscp_main_fail BEFORE INSERT ON ".table('audit')." FOR EACH ROW BEGIN IF NEW.action='service_plans.checkout_setup' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic checkout audit refusal'; END IF; END");
    $failed=false;try{service_plans_checkout_migrate($password);}catch(PDOException $e){$failed=$e->getCode()==='45000';}finally{db()->exec('DROP TRIGGER cscp_main_fail');}
    scn_ok('Final audit failure preserves resumable DDL but does not enable checkout',$failed&&scn_marker()==='preparing-1'&&service_plans_checkout_schema_stage()===2);
    scn_bad('Preparing marker blocks legacy readiness too',fn()=>service_plans_ready());
    scn_ok('DDL preserves all old document event and audit values',scn_retained($old));
    service_plans_checkout_migrate($password);scn_ok('Explicit retry resumes recognized completed DDL and enables extension',service_plans_ready()&&service_plans_checkout_ready()&&scn_marker()==='1');
    scn_ok('Original marker and every original record remain intact',setting('service_plans_schema_version')==='1'&&scn_retained($old)&&service_plans_validate(service_plans_row(scn_id(3),true),true)->json()===$legacyJson);
    service_plans_migrate($password);scn_ok('Original owner setup verifies prepared extension without resetting it',service_plans_checkout_ready()&&scn_retained($old));
    $new=service_plans_offer(scn_id(6),9,$p->data()['id'],$p->revision(),$p->fingerprint(),$password);scn_client();
    $new=service_plans_client_change(scn_id(6),9,0,scn_id(7),'consent',['terms_hash'=>$p->model()->termsHash()],true);scn_owner();
    $new=service_plans_subscription_change(scn_id(6),9,1,scn_id(8),'activate',[],$password);
    scn_ok('Legacy lifecycle still uses only original staff and client actor columns',$new->data()['format']===1&&one('SELECT created_by_client FROM '.table('service_subscriptions').' WHERE id=?',[scn_id(6)])['created_by_client']===null&&one('SELECT actor_system FROM '.table('service_subscription_events').' WHERE id=?',[scn_id(8)])['actor_system']===null);
    foreach(['2','unexpected'] as $v){save_setting('service_plans_checkout_schema_version',$v);scn_bad('Unknown extension marker blocks readiness '.$v,fn()=>service_plans_ready());scn_bad('Unknown marker refuses migration '.$v,fn()=>service_plans_checkout_migrate($password));}
    save_setting('service_plans_checkout_schema_version','');scn_bad('Unmarked extended schema is not silently adopted by readiness',fn()=>service_plans_ready());scn_bad('Unmarked extended schema is not silently adopted by migration',fn()=>service_plans_checkout_migrate($password));save_setting('service_plans_checkout_schema_version','1');
    db()->exec('ALTER TABLE '.table('service_subscription_events').' ADD unexpected_flag INT NULL');scn_bad('Unknown extension column refuses readiness',fn()=>service_plans_ready());db()->exec('ALTER TABLE '.table('service_subscription_events').' DROP COLUMN unexpected_flag');
    db()->exec('ALTER TABLE '.table('service_subscription_events').' MODIFY actor_system VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NULL');scn_bad('Case folding system identity refuses readiness',fn()=>service_plans_ready());db()->exec('ALTER TABLE '.table('service_subscription_events').' MODIFY actor_system VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL');
    db()->exec('ALTER TABLE '.table('service_subscriptions').' ADD INDEX unknown_extension(created_epoch)');scn_bad('Unknown extension index refuses readiness',fn()=>service_plans_ready());db()->exec('ALTER TABLE '.table('service_subscriptions').' DROP INDEX unknown_extension');
    $f=scn_document_fixture(time(),$p);$r=HostingCheckoutReview::create(scn_id(20),$f['pub'],9,['mode'=>'new-hosting'],$f['at'],$f['c']);
    $consent=HostingCheckoutConsent::create($r,$r->fingerprint(),true,$f['pub'],$f['c'],9,null,null,$f['at']);
    $checkout=ServiceSubscriptionDocument::createCheckout(scn_id(21),$consent,$f['pub'],51,scn_id(22),scn_id(23));
    scn_seed_checkout($checkout,$p);$row=service_plans_row(scn_id(21),true);
    scn_ok('Historical format2 accepts completed two-event initial document',service_plans_validate($row,true)->json()===$checkout->json()&&(int)$row['revision']===2&&$row['initial_json']===$checkout->json()&&$row['created_by']===null&&(int)$row['created_by_client']===9);
    $effect=one('SELECT * FROM '.table('service_subscription_events').' WHERE id=?',[scn_id(23)]);
    scn_ok('Automatic effect has no fictitious staff or client actor',$effect['actor_staff_id']===null&&$effect['actor_client_id']===null&&$effect['actor_system']==='hosting-checkout');
    $raw=$checkout->data();$raw['events']=[];scn_bad('Empty format2 transition witness is not a standalone document',fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($raw)));
    $first=one('SELECT * FROM '.table('service_subscription_events').' WHERE id=?',[scn_id(22)]);
    scn_ok('First transition hash binds raw canonical empty prefix',hash_equals(hash('sha256',service_plans_json($raw)),$first['before_sha256']));
    $raw['events'][]=$checkout->data()['events'][0];scn_bad('One-event format2 witness is not a standalone document',fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($raw)));
    scn_ok('Second transition hashes bind one-event prefix and completed document',hash_equals(hash('sha256',service_plans_json($raw)),$effect['before_sha256'])&&hash_equals($checkout->fingerprint(),$effect['after_sha256']));
    foreach([['actor_system'=>null,'actor_staff_id'=>1,'actor_client_id'=>null],['actor_system'=>null,'actor_staff_id'=>null,'actor_client_id'=>9],['actor_system'=>'hosting-checkout','actor_staff_id'=>1,'actor_client_id'=>null],['actor_system'=>'other','actor_staff_id'=>null,'actor_client_id'=>null],['actor_system'=>null,'actor_staff_id'=>null,'actor_client_id'=>null]] as $i=>$change){
        scn_update('service_subscription_events',scn_id(23),$change);scn_bad('Invalid system attribution refused '.$i,fn()=>service_plans_validate(service_plans_row(scn_id(21),true),true));scn_update('service_subscription_events',scn_id(23),['actor_system'=>'hosting-checkout','actor_staff_id'=>null,'actor_client_id'=>null]);
    }
    scn_update('service_subscriptions',scn_id(21),['created_by'=>1]);scn_bad('Client checkout cannot acquire a fictitious staff creator',fn()=>service_plans_validate(service_plans_row(scn_id(21),true),true));scn_update('service_subscriptions',scn_id(21),['created_by'=>null]);
    scn_update('service_subscriptions',scn_id(21),['created_by_client'=>10]);scn_bad('Checkout creator must be the actual retained consent client',fn()=>service_plans_validate(service_plans_row(scn_id(21),true),true));scn_update('service_subscriptions',scn_id(21),['created_by_client'=>9]);
    scn_update('service_subscriptions',scn_id(3),['created_by_client'=>9]);scn_bad('Legacy subscription cannot gain a second creator',fn()=>service_plans_validate(service_plans_row(scn_id(3),true),true));scn_update('service_subscriptions',scn_id(3),['created_by_client'=>null]);
    scn_update('service_subscription_events',scn_id(5),['actor_system'=>'hosting-checkout']);scn_bad('Legacy activation cannot be relabeled as a checkout effect',fn()=>service_plans_validate(service_plans_row(scn_id(3),true),true));scn_update('service_subscription_events',scn_id(5),['actor_system'=>null]);
    scn_update('service_subscription_events',scn_id(22),['after_sha256'=>str_repeat('0',64)]);scn_bad('Changed first transition witness refuses history',fn()=>service_plans_validate(service_plans_row(scn_id(21),true),true));scn_update('service_subscription_events',scn_id(22),['after_sha256'=>$first['after_sha256']]);
    query('DELETE FROM '.table('service_subscription_events').' WHERE id=?',[scn_id(23)]);scn_bad('Missing fixed system effect refuses history',fn()=>service_plans_validate(service_plans_row(scn_id(21),true),true));scn_insert('service_subscription_events',$effect);
    save_setting('service_plans_checkout_schema_version','preparing-1');scn_bad('New-format rows cannot be adopted while extension is preparing',fn()=>service_plans_checkout_migrate($password));save_setting('service_plans_checkout_schema_version','1');
    scn_owner();$later=service_plans_subscription_change(scn_id(21),9,2,scn_id(24),'pause',[],$password);
    $later=service_plans_subscription_change(scn_id(21),9,3,scn_id(25),'resume',[],$password);scn_client();
    $later=service_plans_client_change(scn_id(21),9,4,scn_id(26),'cancel',['reason'=>'End retained checkout service'],true);scn_owner();
    scn_ok('Existing guarded later lifecycle preserves completed creation and client consent',$later->model()->state()==='cancelled'&&service_plans_row(scn_id(21),true)['initial_json']===$checkout->json()&&$later->data()['checkout']===$checkout->data()['checkout']);
    scn_client();scn_bad('Old client writer cannot introduce automatic activation',fn()=>service_plans_client_change(scn_id(21),9,5,scn_id(27),'checkout_activate',[],true));scn_owner();
    service_plans_checkout_migrate($password);scn_ok('Prepared migration rechecks valid historical format2 without rewriting it',service_plans_validate(service_plans_row(scn_id(21),true),true)->json()===$later->json());
    scn_setup('cscp_start_');save_setting('service_plans_checkout_schema_version','preparing-1');scn_bad('Pending marker refuses readiness before any ALTER',fn()=>service_plans_ready());service_plans_checkout_migrate($password);scn_ok('Recognized pre-DDL interruption resumes safely',service_plans_checkout_ready());
    scn_setup('cscp_partial_');save_setting('service_plans_checkout_schema_version','preparing-1');db()->exec(service_plans_checkout_steps()[0]);scn_bad('Recognized middle step refuses application readiness',fn()=>service_plans_ready());service_plans_checkout_migrate($password);scn_ok('Recognized middle DDL interruption resumes safely',service_plans_checkout_ready());
    scn_setup('cscp_reverse_');save_setting('service_plans_checkout_schema_version','preparing-1');db()->exec(service_plans_checkout_steps()[1]);scn_bad('Reverse DDL order cannot be resumed',fn()=>service_plans_checkout_migrate($password));
    scn_setup('cscp_unknown_');save_setting('service_plans_checkout_schema_version','preparing-1');db()->exec('ALTER TABLE '.table('service_subscriptions').' ADD created_by_client INT UNSIGNED NULL AFTER revision');scn_bad('Partially added creator column without exact FK and index refuses',fn()=>service_plans_checkout_migrate($password));
    scn_setup('cscp_missing_');db()->exec('RENAME TABLE '.table('service_subscription_events').' TO cscp_missing_preserved_events');scn_bad('Established missing table is never recreated by checkout migration',fn()=>service_plans_checkout_migrate($password));scn_ok('Missing original table remains missing after refusal',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['cscp_missing_service_subscription_events']));
    $report=['passed'=>true,'php'=>PHP_VERSION,'database_version'=>query('SELECT VERSION()')->fetchColumn(),'port'=>$port,'scope'=>'Actual disposable migration, original legacy wrappers and synthetic format2 historical storage only. No authenticated checkout creation, retained publication/consent audit integration, project creation, invoice, payment, provider, filesystem or HTTP acceptance.','checks'=>$checks];
}catch(Throwable $e){$report=['passed'=>false,'php'=>PHP_VERSION,'checks'=>$checks,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'code'=>(string)$e->getCode(),'message'=>$e instanceof PDOException?'Database error; original SQL fixture did not complete.':$e->getMessage()]];}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
exit($report['passed']?0:1);
