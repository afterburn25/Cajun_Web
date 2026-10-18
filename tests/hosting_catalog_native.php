<?php
/** Disposable native offer/request integration. Synthetic catalog transport; no provider writes or payment. */
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test') exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/catalog_offers.php';
if(getenv('CWP_TEST_HOSTING_RECOVERY')==='1')require_once __DIR__.'/hosting_catalog_recovery_native.php';
if(getenv('CWP_TEST_HOSTING_RECOVERY_HTTP')==='1')require_once __DIR__.'/hosting_catalog_recovery_http.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes;

$private=__DIR__.'/../test-runtime/hosting-catalog-private';
@mkdir($private,0700,true);@mkdir($private.'/media',0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-catalog','storage'=>realpath($private),'key'=>str_repeat('synthetic-hosting-catalog-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hoci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$runtime=null;$password='Synthetic hosting catalog owner';$secret='SYNTHETIC_HOSTING_CATALOG_KEY';$calls=0;
$terms=['name'=>'Reviewed shared hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['One reviewed website'],'exclusions'=>['Website redesign'],'limits'=>['websites'=>1],'support'=>'Response within two business days'];
$input=['sku'=>'HOSTING-NATIVE','title'=>$terms['name'],'family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount'=>'25.00','setup'=>'0.00','cost'=>'9.87','provider_ref'=>'PRIVATE_PROVIDER_REFERENCE','summary'=>'Hosting with the reviewed service plan.','features'=>'PRIVATE_DRAFT_FEATURES','terms'=>'PRIVATE_DRAFT_TERMS','sort_order'=>'0'];
function hcn_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function hcn_deny(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;hcn_ok($name,true);return;}hcn_ok($name,false);}
function hcn_id(int $id):string {return str_pad(dechex($id),32,'0',STR_PAD_LEFT);}
function hcn_owner(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function hcn_client(int $id=1):void {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];}
/** Exact row-state digest for this disposable prefix, including audit, agreements, counters and source history. */
function hcn_state():string {
    $state=[];
    foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(table_name,5)=? ORDER BY table_name',['hoci_']) as $t){
        if(!preg_match('/\Ahoci_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unexpected fixture table name');
        $rows=array_map(static fn(array $r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$t['name'].'`'));sort($rows,SORT_STRING);$state[$t['name']]=$rows;
    }
    return hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
}
function hcn_ready():bool {return recurring_billing_locked(fn()=>hosting_catalog_ready());}
/** Test-only statement observation. Never retains SQL parameters, account data or credentials. */
final class HostingCatalogReadProbeStatement extends PDOStatement {
    protected function __construct() {}
    public function execute(?array $params=null): bool {
        if ($GLOBALS['hcn_probe_active']??false) {
            $GLOBALS['hcn_probe']['statements']++;
            if ($this->queryString==='SELECT value FROM '.table('settings').' WHERE name=?' && $params===['hosting_catalog_version']) $GLOBALS['hcn_probe']['history_validations']++;
            if (!preg_match('/\A(?:SELECT|SHOW)\b/',$this->queryString)) $GLOBALS['hcn_probe']['write_statements']++;
        }
        return parent::execute($params);
    }
}
function hcn_public_probe(int $page=1): array {
    $GLOBALS['hcn_probe']=['statements'=>0,'history_validations'=>0,'write_statements'=>0];
    $savedSession=$_SESSION;$_SESSION=[];
    $GLOBALS['hcn_probe_active']=true;
    try {return hosting_catalog_public($page);}
    finally {$_SESSION=$savedSession;$GLOBALS['hcn_probe_active']=false;$GLOBALS['hcn_public_read_probes'][]=$GLOBALS['hcn_probe'];}
}

function hcn_read(array $row,string $key,string $address,callable $authorize,string $operation):InventorySnapshot {
    global $calls,$secret;$authorize();
    if($key!==$secret||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unexpected synthetic catalog transport');
    $calls++;return PackageTypes::project(new TransportResponse(200,[],'[{"id":7001,"label":"Observed shared hosting"},{"id":7002,"label":"Different provider type"}]'),time(),'twenty-i|'.$row['id'].'|'.$operation);
}
/** Only a deliberately signalled audit failure counts; unrelated PDO failures remain failures. */
function hcn_fail_audit(string $action,callable $work):bool {
    if(!in_array($action,['hosting_catalog.publication','hosting_catalog.decision'],true))throw new LogicException('Unexpected audit target');
    query('SET @hoci_agreements_at_failure=NULL');
    db()->exec('CREATE TRIGGER hoci_audit_failure BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$action."' THEN SET @hoci_agreements_at_failure=(SELECT COUNT(*) FROM ".table('service_subscriptions')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic hosting audit failure'; END IF; END");
    try{$work();return false;}catch(PDOException $e){if(($e->errorInfo[0]??null)!=='45000'||($e->errorInfo[1]??null)!==1644||!str_contains($e->getMessage(),'Synthetic hosting audit failure'))throw $e;return true;}finally{db()->exec('DROP TRIGGER hoci_audit_failure');}
}
try {
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(table_name,5)=? LIMIT 1',['hoci_']))throw new RuntimeException('Disposable hoci_ prefix already exists; preserve the earlier run and use a clean test database.');
    if (!db()->setAttribute(PDO::ATTR_STATEMENT_CLASS,[HostingCatalogReadProbeStatement::class])) throw new RuntimeException('Read probe statement class is unavailable.');
    foreach(schema_statements('hoci_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','2');
    foreach([1,2] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Hosting staff '.$id,'hosting-staff'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat($id===1?'a':'b',32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[2,json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id){query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Hosting client '.$id,'hosting-client'.$id.'@example.invalid',str_repeat($id===1?'c':'d',32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,'Hosting project '.$id,'Synthetic project','[]',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);}
    hcn_owner();catalog_migrate($password);provider_catalog_import_prepare($password);provider_settings_migrate($password);
    $profile=provider_settings_save('',0,'Synthetic hosting catalog',true,$secret,$password);provider_inventory_migrate($password);provider_package_types_prepare($password);
    hcn_ok('Hosting owner records one complete direct catalog through synthetic transport',provider_inventory_refresh($profile,1,$password,static fn()=>'8.8.8.8','hcn_read',InventoryRead::PACKAGE_TYPES)==='complete'&&$calls===1);
    $preview=provider_catalog_import_preview(1,'package.types');$product=provider_catalog_import_create($preview['hash'],'7001',$input['sku'],'month',$password,'package.types');
    hcn_ok('Hosting offer starts from an actual saved unpriced imported draft',one('SELECT amount_cents FROM '.table('catalog_products').' WHERE id=?',[$product])['amount_cents']===null&&provider_catalog_import_record($product)['type_ref']==='7001');
    // Native fixture image seed, not an HTTP upload: the real remove wrapper and reference guard are exercised below.
    $media=hcn_id(900);$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQC6gAAAF2AOe0MgrYAAAAAElFTkSuQmCC',true);
    write_private_file($private.'/media/'.$media.'.php',CWP_GUARD.$png);$imageHash=hash_file('sha256',$private.'/media/'.$media.'.php');
    query('INSERT INTO '.table('media').' (id,filename,mime,bytes,width,height,original_name,created_at) VALUES (?,?,?,?,?,?,?,?)',[$media,$media.'.php','image/png',strlen($png),1,1,'synthetic.png',gmdate('Y-m-d H:i:s')]);
    query('UPDATE '.table('catalog_provider_imports').' SET media_id=?,media_revision=1 WHERE product_id=?',[$media,$product]);
    catalog_save($product,1,array_replace($input,['amount'=>'24.00']));service_plans_migrate($password);
    $plan=service_plans_create(hcn_id(1),$terms,$password);$plan=service_plans_change(hcn_id(1),$plan->revision(),hcn_id(2),'activate',[],$password);
    provider_plan_catalog_prepare($password);
    $configuration=['reseller_id'=>42,'type'=>'7002','label'=>'Synthetic owner-reviewed account','region'=>'Reviewed region','limits'=>['websites'=>1],'cost'=>['amount'=>'9.87','currency'=>'USD','period_months'=>1,'tax'=>'unknown','basis'=>'per-package','reference'=>'PRIVATE_COST_REFERENCE']];
    $mapping=provider_plan_catalog_save(hcn_id(10),hcn_id(1),$plan->revision(),$plan->fingerprint(),null,$profile,1,$configuration,7,true,$password);
    $before=hcn_state();hcn_ok('Hosting readiness is read-only before explicit preparation',!hcn_ready()&&hcn_state()===$before);
    hcn_ok('Unprepared anonymous hosting read validates history once and stays empty',hcn_public_probe()===['offers'=>[],'has_more'=>false,'unavailable'=>false]&&$GLOBALS['hcn_probe']['history_validations']===1&&$GLOBALS['hcn_probe']['write_statements']===0&&hcn_state()===$before);
    if(getenv('CWP_TEST_HOSTING_RECOVERY')==='1')hcr_phase('absent');
    if(getenv('CWP_TEST_HOSTING_RECOVERY_HTTP')==='1')hch_phase('absent');
    hcn_deny('Hosting preparation requires the current owner password',fn()=>hosting_catalog_prepare('wrong'));
    hcn_owner(2);hcn_deny('Delegated administrator cannot prepare hosting offer storage',fn()=>hosting_catalog_prepare($password));hcn_owner();
    hosting_catalog_prepare($password);$before=hcn_state();hosting_catalog_prepare($password);hcn_ok('Repeated hosting preparation preserves all database rows',hcn_ready()&&hcn_state()===$before);
    hcn_ok('Prepared empty hosting page validates history once without writes',hcn_public_probe()===['offers'=>[],'has_more'=>false,'unavailable'=>false]&&$GLOBALS['hcn_probe']['history_validations']===1&&$GLOBALS['hcn_probe']['write_statements']===0&&hcn_state()===$before);
    if(getenv('CWP_TEST_HOSTING_RECOVERY')==='1')hcr_phase('empty');
    if(getenv('CWP_TEST_HOSTING_RECOVERY_HTTP')==='1')hch_phase('empty');
    hcn_deny('Hosting publication rejects mismatched product and plan price',fn()=>hosting_catalog_preview(hcn_id(20),$product,hcn_id(1)));
    catalog_save($product,2,$input);hcn_deny('Hosting publication rejects a different mapped provider type',fn()=>hosting_catalog_preview(hcn_id(20),$product,hcn_id(1)));
    $configuration['type']='7001';$mapping=provider_plan_catalog_save(hcn_id(11),hcn_id(1),$plan->revision(),$plan->fingerprint(),$mapping['id'],$profile,1,$configuration,7,true,$password);
    $review=hosting_catalog_preview(hcn_id(20),$product,hcn_id(1));$before=hcn_state();
    hcn_owner(2);hcn_deny('Delegated administrator cannot publish a hosting offer',fn()=>hosting_catalog_publish(hcn_id(20),$product,hcn_id(1),$review['review_sha256'],$password));hcn_owner();
    hcn_deny('Hosting publication requires the current owner password',fn()=>hosting_catalog_publish(hcn_id(20),$product,hcn_id(1),$review['review_sha256'],'wrong'));
    $_GET['support']='';try{hcn_deny('Support context cannot publish a hosting offer',fn()=>hosting_catalog_publish(hcn_id(20),$product,hcn_id(1),$review['review_sha256'],$password));}finally{unset($_GET['support']);}
    hcn_deny('Hosting publication rejects a stale review fingerprint',fn()=>hosting_catalog_publish(hcn_id(20),$product,hcn_id(1),str_repeat('0',64),$password));
    hcn_ok('Rejected publication authority and stale review preserve all rows',hcn_state()===$before);
    hcn_ok('Publication audit failure rolls back publication counter and history',hcn_fail_audit('hosting_catalog.publication',fn()=>hosting_catalog_publish(hcn_id(20),$product,hcn_id(1),$review['review_sha256'],$password))&&hcn_state()===$before&&hcn_ready());
    $publication=hosting_catalog_publish(hcn_id(20),$product,hcn_id(1),$review['review_sha256'],$password);$before=hcn_state();
    hcn_ok('Exact publication replay retains one immutable publication',hosting_catalog_publish(hcn_id(20),$product,hcn_id(1),$review['review_sha256'],$password)->json()===$publication->json()&&hcn_state()===$before);
    if(getenv('CWP_TEST_HOSTING_RECOVERY')==='1')hcr_phase('published');
    if(getenv('CWP_TEST_HOSTING_RECOVERY_HTTP')==='1')hch_phase('published');
    $_SESSION=[];$public=hosting_catalog_public();$card=$publication->historicalProjection();
    hcn_ok('Anonymous hosting cards have exactly the closed safe projection',$public===['offers'=>[$card],'has_more'=>false,'unavailable'=>false]&&array_keys($card)===['publication_id','publication_sha256','product_id','product_revision','sku','title','summary','amount_cents','currency','cadence','inclusions','exclusions','limits','support','image_id']);
    $bytes=json_encode($public,JSON_THROW_ON_ERROR);hcn_ok('Hosting public cards expose plan terms without private import or provider values',!str_contains($bytes,$secret)&&!str_contains($bytes,$profile)&&!str_contains($bytes,'PRIVATE_')&&!str_contains($bytes,'credential_sha256')&&!str_contains($bytes,'source_key_fingerprint')&&$card['inclusions']===$terms['inclusions']&&$card['exclusions']===$terms['exclusions']&&$card['support']===$terms['support']);
    hcn_ok('Anonymous projection makes no transport or database writes',$calls===1&&hcn_state()===$before);
    hcn_ok('Nonempty public page validates hosting history exactly once',hcn_public_probe()===$public&&$GLOBALS['hcn_probe']['history_validations']===1&&$GLOBALS['hcn_probe']['write_statements']===0);
    hcn_ok('Next public call performs its own fresh history validation',hcn_public_probe()===$public&&$GLOBALS['hcn_probe']['history_validations']===1&&$GLOBALS['hcn_probe']['write_statements']===0);
    $savedMarker=one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_catalog_version']);
    query('DELETE FROM '.table('settings').' WHERE name=?',['hosting_catalog_version']);
    try {hcn_deny('Public call after a successful read refuses missing established marker',fn()=>hcn_public_probe());}
    finally {save_setting('hosting_catalog_version',$savedMarker['value']);}
    $savedCount=one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_catalog_publications_count']);
    save_setting('hosting_catalog_publications_count',(string)((int)$savedCount['value']+1));
    try {hcn_deny('Public call refuses a changed retained history count',fn()=>hcn_public_probe());}
    finally {save_setting('hosting_catalog_publications_count',$savedCount['value']);}
    $savedWitness=one('SELECT id,detail FROM '.table('audit').' WHERE action=?',['hosting_catalog.publication']);
    query('UPDATE '.table('audit').' SET detail=? WHERE id=?',[$savedWitness['detail'].' changed',$savedWitness['id']]);
    try {hcn_deny('Public call refuses changed publication audit witness despite equal counts',fn()=>hcn_public_probe());}
    finally {query('UPDATE '.table('audit').' SET detail=? WHERE id=?',[$savedWitness['detail'],$savedWitness['id']]);}
    query('UPDATE '.table('hosting_catalog_publications').' SET document_sha256=? WHERE id=?',[str_repeat('0',64),hcn_id(20)]);
    try {hcn_deny('Public call refuses corrupt retained publication bytes',fn()=>hcn_public_probe());}
    finally {query('UPDATE '.table('hosting_catalog_publications').' SET document_sha256=? WHERE id=?',[$publication->fingerprint(),hcn_id(20)]);}
    hcn_ok('Public integrity probes restore all rows and fresh safe projection',hcn_state()===$before&&hcn_public_probe()===$public&&$GLOBALS['hcn_probe']['history_validations']===1);
    hcn_deny('Direct product scope still requires owning lock and transaction',fn()=>hosting_catalog_read_scope([$product]));

    hcn_owner();hcn_deny('Imported hosting remains blocked from ordinary catalog publication',fn()=>catalog_save($product,3,array_replace($input,['status'=>'published'])));
    hcn_deny('Imported hosting remains blocked from ordinary invoice quotes',fn()=>catalog_quote($product,3,1));
    hcn_deny('Imported hosting remains blocked from ordinary catalog invoice creation',fn()=>catalog_issue(1,$product,3,1,'Synthetic instructions',str_repeat('e',64)));
    hcn_ok('Dedicated offer does not bypass catalog invoice or product family guards',catalog_public()===[]&&hcn_state()===$before);

    hcn_client();$before=hcn_state();hcn_deny('New hosting request requires exact publication fingerprint',fn()=>hosting_catalog_request(hcn_id(100),hcn_id(20),str_repeat('0',64),1));
    $_GET['support']='';try{hcn_deny('Support context cannot submit a hosting request',fn()=>hosting_catalog_request(hcn_id(100),hcn_id(20),$publication->fingerprint(),1));}finally{unset($_GET['support']);}
    hcn_ok('Request fingerprint and support refusals preserve every row',hcn_state()===$before);
    $request=hosting_catalog_request(hcn_id(101),hcn_id(20),$publication->fingerprint(),1);$before=hcn_state();
    hcn_ok('Exact client request replay writes no duplicate request',hosting_catalog_request(hcn_id(101),hcn_id(20),$publication->fingerprint(),1)->json()===$request->json()&&hcn_state()===$before);
    hcn_client(2);hcn_deny('Another client cannot replay a request under its own identity',fn()=>hosting_catalog_request(hcn_id(101),hcn_id(20),$publication->fingerprint(),2));
    hcn_deny('Another client session cannot claim the original client',fn()=>hosting_catalog_request(hcn_id(107),hcn_id(20),$publication->fingerprint(),1));
    hcn_deny('Another client cannot read the original request list',fn()=>hosting_catalog_client_requests(1));
    hcn_ok('Other client history excludes the original request',hosting_catalog_client_requests(2)===['requests'=>[],'has_more'=>false]&&hcn_state()===$before);
    $otherRequest=hosting_catalog_request(hcn_id(106),hcn_id(20),$publication->fingerprint(),2);
    hcn_client();$requests=[101=>$request];foreach([102,103,104,105] as $id)$requests[$id]=hosting_catalog_request(hcn_id($id),hcn_id(20),$publication->fingerprint(),1);
    query('UPDATE '.table('studio_clients').' SET password_hash=?,auth_version=? WHERE id=1',[password_hash('Synthetic rotated client password',PASSWORD_DEFAULT),str_repeat('f',32)]);$before=hcn_state();
    hcn_deny('Rotated client authentication refuses the stale session',fn()=>hosting_catalog_request(hcn_id(107),hcn_id(20),$publication->fingerprint(),1));
    hcn_client();$history=hosting_catalog_client_requests(1);hcn_ok('Current client can still read exact historical requests after password rotation',count($history['requests'])===5&&array_column($history['requests'],'offer')===array_fill(0,5,$card)&&hcn_state()===$before);
    hcn_owner(2);hcn_deny('Delegated administrator cannot convert a client hosting request',fn()=>hosting_catalog_convert(hcn_id(201),hcn_id(101),$request->fingerprint(),1,$password));hcn_owner();
    hcn_deny('Hosting conversion requires the current owner password',fn()=>hosting_catalog_convert(hcn_id(201),hcn_id(101),$request->fingerprint(),1,'wrong'));
    hcn_deny('Hosting conversion rejects another clients project',fn()=>hosting_catalog_convert(hcn_id(201),hcn_id(101),$request->fingerprint(),2,$password));
    hcn_deny('Hosting conversion rejects a stale request fingerprint',fn()=>hosting_catalog_convert(hcn_id(201),hcn_id(101),str_repeat('0',64),1,$password));
    $_GET['support']='';try{hcn_deny('Support context cannot convert a hosting request',fn()=>hosting_catalog_convert(hcn_id(201),hcn_id(101),$request->fingerprint(),1,$password));}finally{unset($_GET['support']);}
    hcn_ok('Denied conversions preserve all requests and agreements',hcn_state()===$before);
    $subscriptions=(int)query('SELECT COUNT(*) FROM '.table('service_subscriptions'))->fetchColumn();
    $failed=hcn_fail_audit('hosting_catalog.decision',fn()=>hosting_catalog_convert(hcn_id(201),hcn_id(101),$request->fingerprint(),1,$password));
    hcn_ok('Decision audit failure occurs after the agreement INSERT',$failed&&(int)query('SELECT @hoci_agreements_at_failure')->fetchColumn()===$subscriptions+1);
    hcn_ok('Decision audit failure atomically rolls back agreement link audit and counter',hcn_state()===$before&&hcn_ready());
    $agreement=hosting_catalog_convert(hcn_id(201),hcn_id(101),$request->fingerprint(),1,$password);$decision=one('SELECT * FROM '.table('hosting_catalog_decisions').' WHERE request_id=?',[hcn_id(101)]);
    hcn_ok('Owner conversion links the exact pending agreement and plan to its request',$decision['agreement_id']===$agreement->data()['id']&&(int)$decision['job_id']===1&&$agreement->data()['client_id']===1&&service_plans_json($agreement->data()['plan'])===$publication->data()['plan_json']&&$agreement->revision()===0&&$agreement->model()->state()==='pending-consent');
    $before=hcn_state();hcn_ok('Exact conversion replay returns the same agreement without writes',hosting_catalog_convert(hcn_id(201),hcn_id(101),$request->fingerprint(),1,$password)->json()===$agreement->json()&&hcn_state()===$before);
    hcn_deny('A second decision cannot convert the same request again',fn()=>hosting_catalog_convert(hcn_id(207),hcn_id(101),$request->fingerprint(),1,$password));
    hcn_deny('Linked agreement still requires client consent before activation',fn()=>service_plans_subscription_change($agreement->data()['id'],1,0,hcn_id(401),'activate',[],$password));
    hcn_ok('Duplicate conversion and missing consent leave the agreement unchanged',hcn_state()===$before);
    $second=hosting_catalog_convert(hcn_id(202),hcn_id(102),$requests[102]->fingerprint(),1,$password);
    hcn_ok('Two distinct requests for one project receive two distinct linked agreements',$second->data()['id']!==$agreement->data()['id']&&(int)query('SELECT COUNT(*) FROM '.table('hosting_catalog_decisions').' WHERE job_id=1 AND kind=?',['offered'])->fetchColumn()===2&&(int)query('SELECT COUNT(*) FROM '.table('service_subscriptions'))->fetchColumn()===2&&hcn_ready());
    hcn_client(2);$before=hcn_state();hcn_deny('Another client cannot withdraw the original pending request',fn()=>hosting_catalog_close_request(hcn_id(204),hcn_id(104),$requests[104]->fingerprint(),'No longer needed',2,null));hcn_ok('Cross-client request withdrawal writes nothing',hcn_state()===$before);
    hcn_client();hosting_catalog_close_request(hcn_id(204),hcn_id(104),$requests[104]->fingerprint(),'No longer needed',1,null);$before=hcn_state();hosting_catalog_close_request(hcn_id(204),hcn_id(104),$requests[104]->fingerprint(),'No longer needed',1,null);
    hcn_ok('Client pending request withdrawal is exact and replay safe',one('SELECT kind FROM '.table('hosting_catalog_decisions').' WHERE request_id=?',[hcn_id(104)])['kind']==='withdrawn'&&hcn_state()===$before);
    hcn_deny('Client withdrawal cannot cancel an offered agreement',fn()=>hosting_catalog_close_request(hcn_id(208),hcn_id(101),$request->fingerprint(),'Cancel agreement',1,null));
    hcn_owner();hosting_catalog_close_request(hcn_id(205),hcn_id(105),$requests[105]->fingerprint(),'Requires a separate review',null,$password);
    hcn_deny('Owner cannot convert a client-withdrawn request',fn()=>hosting_catalog_convert(hcn_id(209),hcn_id(104),$requests[104]->fingerprint(),1,$password));
    hcn_deny('Owner cannot convert a declined request',fn()=>hosting_catalog_convert(hcn_id(210),hcn_id(105),$requests[105]->fingerprint(),1,$password));
    hcn_ok('Closing pending requests does not create or change agreements',(int)query('SELECT COUNT(*) FROM '.table('service_subscriptions'))->fetchColumn()===2&&service_plans_validate(service_plans_row($agreement->data()['id'],true),true)->json()===$agreement->json());

    $before=hcn_state();$cipher=provider_settings_load($profile)['credentials'];
    query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[provider_settings_seal($profile,'live','SYNTHETIC_ROTATED_HOSTING_KEY'),$profile]);
    try{$_SESSION=[];hcn_ok('Public read rechecks changed credentials after earlier successful calls',hcn_public_probe()===['offers'=>[],'has_more'=>false,'unavailable'=>true]&&$GLOBALS['hcn_probe']['history_validations']===1);hcn_client();hcn_deny('Changed credentials refuse new requests against the old offer',fn()=>hosting_catalog_request(hcn_id(107),hcn_id(20),$publication->fingerprint(),1));hcn_ok('Changed credentials do not erase authenticated historical terms',count(hosting_catalog_client_requests(1)['requests'])===5);}
    finally{query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$cipher,$profile]);hcn_owner();}
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');try{$_SESSION=[];hcn_ok('Public read rechecks revoked reviewer after earlier successful calls',hcn_public_probe()===['offers'=>[],'has_more'=>false,'unavailable'=>true]&&$GLOBALS['hcn_probe']['history_validations']===1);hcn_client();hcn_deny('Revoked original owner authority refuses new hosting requests',fn()=>hosting_catalog_request(hcn_id(107),hcn_id(20),$publication->fingerprint(),1));}finally{query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');hcn_owner();}
    hcn_ok('Credential and owner refusal probes restore exact original database rows',hcn_state()===$before);
    hcn_owner(2);hcn_deny('Delegated administrator cannot withdraw an offer',fn()=>hosting_catalog_withdraw(hcn_id(30),hcn_id(20),$publication->fingerprint(),'Owner withdrew offer',$password));hcn_owner();
    hcn_deny('Offer withdrawal requires current owner password',fn()=>hosting_catalog_withdraw(hcn_id(30),hcn_id(20),$publication->fingerprint(),'Owner withdrew offer','wrong'));
    hosting_catalog_withdraw(hcn_id(30),hcn_id(20),$publication->fingerprint(),'Owner withdrew offer',$password);$before=hcn_state();hosting_catalog_withdraw(hcn_id(30),hcn_id(20),$publication->fingerprint(),'Owner withdrew offer',$password);
    hcn_ok('Offer withdrawal replay preserves exact history',hcn_state()===$before&&hosting_catalog_public()===['offers'=>[],'has_more'=>false,'unavailable'=>false]);
    hcn_ok('Empty withdrawn page still validates all retained history once',hcn_public_probe()===['offers'=>[],'has_more'=>false,'unavailable'=>false]&&$GLOBALS['hcn_probe']['history_validations']===1&&$GLOBALS['hcn_probe']['write_statements']===0&&hcn_state()===$before);
    if(getenv('CWP_TEST_HOSTING_RECOVERY')==='1')hcr_phase('withdrawn');
    if(getenv('CWP_TEST_HOSTING_RECOVERY_HTTP')==='1')hch_phase('withdrawn');
    hcn_client();hcn_deny('Withdrawn offer refuses a new client request',fn()=>hosting_catalog_request(hcn_id(107),hcn_id(20),$publication->fingerprint(),1));
    hcn_ok('Own historical request replay survives offer withdrawal',hosting_catalog_request(hcn_id(101),hcn_id(20),$publication->fingerprint(),1)->json()===$request->json());
    $history=hosting_catalog_client_requests(1);$states=array_column($history['requests'],'status','id');hcn_ok('Withdrawal retains exact client terms and prior request outcomes',count($history['requests'])===5&&array_column($history['requests'],'offer')===array_fill(0,5,$card)&&$states[hcn_id(101)]==='offered'&&$states[hcn_id(103)]==='pending'&&$states[hcn_id(104)]==='withdrawn'&&$states[hcn_id(105)]==='declined');
    hcn_owner();hcn_deny('Withdrawn offer refuses conversion of an existing pending request',fn()=>hosting_catalog_convert(hcn_id(203),hcn_id(103),$requests[103]->fingerprint(),1,$password));hcn_ok('Withdrawn offer refusals preserve all historical rows',hcn_state()===$before);
    $nextReview=hosting_catalog_preview(hcn_id(21),$product,hcn_id(1));$next=hosting_catalog_publish(hcn_id(21),$product,hcn_id(1),$nextReview['review_sha256'],$password);
    hcn_ok('A separately reviewed new publication can replace a withdrawn offer',hosting_catalog_public()['offers']===[$next->historicalProjection()]);
    provider_catalog_import_image($product,3,1,true,$password);
    hcn_ok('Removing the current product image preserves original publication media',provider_catalog_import_record($product)['media_id']===null&&$next->historicalProjection()['image_id']===$media&&staff_locked(fn()=>hosting_catalog_ready()&&hosting_catalog_image_used($media))&&one('SELECT id FROM '.table('media').' WHERE id=?',[$media])!==null&&hash_file('sha256',$private.'/media/'.$media.'.php')===$imageHash);
    hcn_ok('Image-reference guard does not protect an unrelated image',!staff_locked(fn()=>hosting_catalog_image_used(hcn_id(901))));
    hcn_client();hcn_deny('Changed current image invalidates the old public offer',fn()=>hosting_catalog_request(hcn_id(108),hcn_id(21),$next->fingerprint(),1));
    hcn_ok('Authenticated history preserves its original image after current removal',hosting_catalog_client_requests(1)['requests'][0]['offer']['image_id']===$media);
    hcn_owner();$currentReview=hosting_catalog_preview(hcn_id(22),$product,hcn_id(1));$current=hosting_catalog_publish(hcn_id(22),$product,hcn_id(1),$currentReview['review_sha256'],$password);
    catalog_save($product,3,array_replace($input,['summary'=>'Owner edited marketing summary.']));$before=hcn_state();hcn_client();
    hcn_deny('Product revision changes refuse an earlier publication request',fn()=>hosting_catalog_request(hcn_id(108),hcn_id(22),$current->fingerprint(),1));
    hcn_ok('Product edit removes stale public offers but preserves earlier request terms',hosting_catalog_public()['offers']===[]&&hosting_catalog_client_requests(1)['requests'][0]['offer']===$card&&hcn_state()===$before);
    hcn_owner();$marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_catalog_version']);query('DELETE FROM '.table('settings').' WHERE name=?',['hosting_catalog_version']);
    try{hcn_deny('Missing established hosting marker cannot silently recreate history',fn()=>hosting_catalog_prepare($password));}finally{save_setting('hosting_catalog_version',$marker['value']);}
    hcn_ok('Exact marker restoration preserves ready history and all row contents',hcn_ready()&&hcn_state()===$before);
    hcn_ok('Offer and request operations never create an invoice payment or provider write',$calls===1&&(int)query('SELECT COUNT(*) FROM '.table('studio_invoices'))->fetchColumn()===0&&(int)query('SELECT COUNT(*) FROM '.table('catalog_invoice_snapshots'))->fetchColumn()===0&&(int)query('SELECT COUNT(*) FROM '.table('service_subscriptions').' WHERE revision<>0')->fetchColumn()===0);
    $runtime=['php'=>PHP_VERSION,'pdo_driver'=>db()->getAttribute(PDO::ATTR_DRIVER_NAME),'database_version'=>(string)query('SELECT VERSION()')->fetchColumn(),'synthetic_catalog_transport_calls'=>$calls,'public_read_probes'=>$GLOBALS['hcn_public_read_probes']??[]];
} catch(Throwable $e) {$checks[]=['name'=>'Unexpected hosting catalog native failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$passed=!array_filter($checks,static fn(array $c):bool=>$c['passed']!==true);
file_put_contents(__DIR__.'/../qa/hosting-catalog-native-results.json',json_encode(['passed'=>$passed,'scope'=>'Disposable SQL integration using real local service wrappers and synthetic package-types transport; authenticated session fixtures, no browser, provider write, payment or fulfillment acceptance.','runtime'=>$runtime,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo ($passed?'PASS':'FAIL').': '.count($checks).' hosting catalog native checks'.PHP_EOL;exit($passed?0:1);
