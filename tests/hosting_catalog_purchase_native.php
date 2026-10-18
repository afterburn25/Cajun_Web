<?php
/** Disposable joined SQL purchase. Prepare uses an isolated process clock; due uses real time. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOSTING_PURCHASE')!=='1')exit(2);
$phase=$argv[1]??'';
if(!in_array($phase,['prepare','due','portal-own','portal-other'],true))exit(2);
$fullMode=getenv('CWP_TEST_HOSTING_PURCHASE_FULL_SUITE');if(!in_array($fullMode,[false,'','1'],true))throw new RuntimeException('Unknown purchase output mode');$fullSuite=$fullMode==='1';
$portText=getenv('CWP_TEST_PORT');if(!is_string($portText)||!preg_match('/\A[1-9][0-9]{0,4}\z/',$portText)||(int)$portText>65535)throw new RuntimeException('Assigned disposable database port required');$purchasePort=(int)$portText;
if($fullSuite){$version=getenv('CWP_PHP_VERSION');if(!in_array($version,['8.2','8.3','8.4'],true)||getenv('PHP_BINARY')!=='/usr/bin/php'.$version||PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION!==$version||realpath(PHP_BINARY)!==realpath((string)getenv('PHP_BINARY')))throw new RuntimeException('Selected supported PHP runtime required');}
elseif($purchasePort!==33306)throw new RuntimeException('Original focused database port required');
$root=realpath(__DIR__.'/..');$runtime=$root.'/test-runtime/hosting-purchase';$qa=$root.($fullSuite?'/qa':'/qa/hosting-purchase');
foreach([$runtime,$qa] as $path){if(!is_dir($path)||realpath($path)!==$path)throw new RuntimeException('Use the isolated purchase runner.');for($p=$path;$p!==dirname($p);$p=dirname($p))if(is_link($p))throw new RuntimeException('Purchase fixture paths must not contain links.');}
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';
require_once CWP_CODE_ROOT.'/app/hosting/order_link_views.php';
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment_views.php';
require_once CWP_CODE_ROOT.'/includes/layout/public.php';
require_once CWP_CODE_ROOT.'/admin/controllers/support.php';
require_once CWP_CODE_ROOT.'/includes/layout/provider_services.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,ProviderCredential,ProviderGateway};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\{InventoryReader,PackageTypes};
use CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms;

const HPC_PASSWORD='Synthetic joined hosting owner';
const HPC_CLIENT_PASSWORD='Synthetic joined hosting client';
const HPC_SECRET='SYNTHETIC_JOINED_HOSTING_KEY';
const HPC_DOMAIN='purchase.example';
const HPC_PACKAGE=876001;
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-purchase','storage'=>$runtime.'/private','key'=>str_repeat('synthetic-joined-hosting-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hpci_']];
if(config()['db']['host']!=='127.0.0.1'||config()['db']['port']!==$purchasePort)throw new RuntimeException('Use the selected disposable database endpoint.');
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=CWP_CODE_ROOT;ini_set('session.save_path',$runtime.'/sessions');start_session();
date_default_timezone_set('UTC');
$checks=[];$step='clock';$result=['passed'=>false,'phase'=>$phase,'simulated_application_clock'=>$phase==='prepare','checks'=>[]];
/** Counts executed prepared statements without retaining SQL or parameters. No performance threshold. */
final class HostingPurchaseStatement extends PDOStatement {
    protected function __construct(){}
    public function execute(?array $params=null):bool {if($GLOBALS['hp_measuring']??false)++$GLOBALS['hp_statement_count'];return parent::execute($params);}
}
function hp_measure(string $name,callable $work):mixed {
    global $result;$GLOBALS['hp_statement_count']=0;$GLOBALS['hp_measuring']=true;$start=hrtime(true);$completed=false;
    try{$value=$work();$completed=true;return $value;}
    finally{$GLOBALS['hp_measuring']=false;$result['measurements'][$name]=['completed'=>$completed,'prepared_statement_executions'=>$GLOBALS['hp_statement_count'],'elapsed_ms'=>(hrtime(true)-$start)/1000000,'scope'=>'Whole wrapper elapsed time; execute count excludes transaction API calls and direct PDO exec. No SQL or parameters retained.'];}
}
function hp_id(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function hp_ok(string $name,bool $yes):void{global $checks;$checks[]=['name'=>$name,'passed'=>$yes];if(!$yes)throw new RuntimeException($name);}
function hp_deny(string $name,callable $f):void{try{$f();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;hp_ok($name,true);return;}hp_ok($name,false);}
function hp_owner():void{$u=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_support']);}
function hp_client(int $id=1):void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];$_GET=[];unset($GLOBALS['cwp_support']);}
function hp_state():string{
    $data=[];
    foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=? ORDER BY TABLE_NAME',['hpci_']) as $t){
        if(!preg_match('/\Ahpci_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unexpected purchase table');
        $rows=array_map(static fn(array $r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$t['name'].'`'));sort($rows,SORT_STRING);$data[$t['name']]=$rows;
    }
    return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
}
function hp_failure(string $action,callable $f):bool{
    if(!in_array($action,['recurring_billing.issued','payments.ledger.manual','hosting_records.fulfillment_approved','provider.service_linked'],true))throw new LogicException('Unexpected purchase audit target');
    db()->exec('CREATE TRIGGER hpci_purchase_failure BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic joined purchase audit failure'; END IF; END");
    try{$f();return false;}catch(PDOException $e){if(($e->errorInfo[0]??null)!=='45000'||($e->errorInfo[1]??null)!==1644||!str_contains($e->getMessage(),'Synthetic joined purchase audit failure'))throw $e;return true;}finally{db()->exec('DROP TRIGGER hpci_purchase_failure');}
}
function hp_catalog(array $p,string $secret,string $address,callable $authorize,string $operation):InventorySnapshot{
    $authorize();if($secret!==HPC_SECRET||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unexpected fixed catalog seam');
    $GLOBALS['hp_catalog_calls']=($GLOBALS['hp_catalog_calls']??0)+1;
    return PackageTypes::project(new TransportResponse(200,[],'[{"id":7001,"label":"Joined hosting type"}]'),time(),'twenty-i|'.$p['id'].'|'.$operation);
}
/** No sockets, cURL, DNS, secrets or arbitrary response callbacks. Actual gateway and reader call this transport. */
final class HostingPurchaseTransport implements HttpTransport{
    public static array $calls=[];
    public function __construct(private Closure $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        ($this->authorize)();
        if($request->method==='POST'&&$request->url==='https://api.20i.com/reseller/42/addWeb'){
            $body=json_decode($request->body,true,8,JSON_THROW_ON_ERROR);
            if($body!==['type'=>'7001','domain_name'=>HPC_DOMAIN])throw new LogicException('Unexpected joined purchase request body');
            self::$calls[]=['method'=>'POST','operation'=>'packages.create'];return new TransportResponse(200,[],'{"result":876001}');
        }
        if($request->method==='GET'&&$request->url==='https://api.20i.com/package'&&$request->body===null){
            self::$calls[]=['method'=>'GET','operation'=>'packages.list'];
            return new TransportResponse(200,[],json_encode([['id'=>HPC_PACKAGE,'enabled'=>true,'typeRef'=>7001,'name'=>HPC_DOMAIN,'names'=>[HPC_DOMAIN],'created'=>'2020-01-15T16:30:30+00:00']],JSON_THROW_ON_ERROR));
        }
        throw new LogicException('Unexpected provider method or fixed route');
    }
}
function hp_perform(array $p,string $secret,string $address,callable $authorize,\CajunWebPro\Foundation\Provider\ProviderOperation $operation):\CajunWebPro\Foundation\Provider\OperationResult{
    if($secret!==HPC_SECRET||$address!=='8.8.8.8')throw new LogicException('Unexpected synthetic fulfillment context');
    $credential=new ProviderCredential('twenty-i',$p['id'].':'.(int)$p['revision'].':'.provider_operations_credential_fingerprint($secret),static function()use($authorize,$secret):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new HostingPurchaseTransport(Closure::fromCallable($authorize)),new ProviderOperationLedger()))->perform($credential,$operation);
}
function hp_read(array $p,string $secret,string $address,callable $authorize,string $operation):InventorySnapshot{
    if($secret!==HPC_SECRET||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGES_LIST)throw new LogicException('Unexpected synthetic confirmation context');
    $credential=new ProviderCredential('twenty-i',$p['id'],static function()use($authorize,$secret):string{$authorize();return $secret;});
    return InventoryReader::read(new HostingPurchaseTransport(Closure::fromCallable($authorize)),$credential,$operation,0,time());
}
function hp_write(string $name,array $value):void{global $qa,$fullSuite;if($fullSuite)$name='hosting-purchase-'.$name;$f=fopen($qa.'/'.$name,'x');if(!$f)throw new RuntimeException('Purchase evidence already exists');try{fwrite($f,json_encode($value,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");}finally{fclose($f);}}

try{
    db()->setAttribute(PDO::ATTR_STATEMENT_CLASS,[HostingPurchaseStatement::class,[]]);
    $parent=(int)getenv('CWP_PURCHASE_PARENT_EPOCH');$offset=$phase==='prepare'?172800:0;
    hp_ok('Application clock matches its declared isolated phase',abs(time()-($parent-$offset))<120&&abs((new DateTimeImmutable('now',new DateTimeZone('UTC')))->getTimestamp()-time())<=1&&abs((int)floor(microtime(true))-time())<=1);
    $mono=hrtime(true);usleep(20000);hp_ok('Process monotonic clock still advances',hrtime(true)>$mono+1000000&&hrtime(true)<$mono+1000000000);
    $server=(int)query('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP())')->fetchColumn();
    hp_ok('Database server remains on the unfaked parent clock',abs($server-$parent)<120&&($phase!=='prepare'||$server-time()>172600));
    $result['clock']=['php_epoch'=>time(),'parent_epoch'=>$parent,'database_epoch'=>$server,'offset_seconds'=>$offset,'php'=>PHP_VERSION,'database_version'=>(string)query('SELECT VERSION()')->fetchColumn()];
    if(str_starts_with($phase,'portal-')){
        $state=json_decode(file_get_contents($runtime.'/state.json'),true,16,JSON_THROW_ON_ERROR);$client=$phase==='portal-own'?1:2;hp_client($client);
        $before=hp_state();$level=ob_get_level();ob_start();
        register_shutdown_function(static function()use($client,$before,$level,$phase,&$result,&$checks):void{
            $html='';while(ob_get_level()>$level)$html=ob_get_clean().$html;
            try{
                hp_ok('Actual Hosting renderer completed without a fatal PHP error',error_get_last()===null||!in_array(error_get_last()['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true));
                hp_ok('Portal current client is authenticated as selected fixture identity',(int)(studio_client()['id']??0)===$client);
                hp_ok('Actual Hosting renderer exposes only the linked client service',$client===1?str_contains($html,'<h2>'.HPC_DOMAIN.'</h2>')&&str_contains($html,'Active')&&!str_contains($html,'No hosting services are linked'):!str_contains($html,HPC_DOMAIN)&&str_contains($html,'No hosting services are linked'));
                hp_ok('Rendered Hosting page excludes provider credentials and private mapping text',!str_contains($html,HPC_SECRET)&&!str_contains($html,'PRIVATE_JOINED')&&!str_contains($html,HPC_PASSWORD));
                hp_ok('Authenticated Hosting rendering is read only',hp_state()===$before);
                $result['passed']=true;$result['html_sha256']=hash('sha256',$html);$result['html_bytes']=strlen($html);
            }catch(Throwable $e){$result['error']=['class'=>get_class($e),'message'=>$e->getMessage()];}
            $result['checks']=$checks;hp_write($phase.'-results.json',$result);exit($result['passed']?0:1);
        });
        provider_services_portal_render();
    }
    if($phase==='prepare'){
        $step='fresh-schema';hp_ok('Dedicated purchase namespace starts empty',all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=?',['hpci_'])===[]);
        foreach(schema_statements('hpci_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
        foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','2');
        query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Purchase owner','purchase-owner@example.invalid',password_hash(HPC_PASSWORD,PASSWORD_DEFAULT),str_repeat('a',32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);
        foreach([1,2] as $id){query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Purchase client '.$id,'purchase-client'.$id.'@example.invalid',password_hash(HPC_CLIENT_PASSWORD,PASSWORD_DEFAULT),str_repeat($id===1?'b':'c',32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,'Purchase project '.$id,'Synthetic project','[]',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);}
        hp_owner();catalog_migrate(HPC_PASSWORD);provider_catalog_import_prepare(HPC_PASSWORD);provider_settings_migrate(HPC_PASSWORD);
        $profile=provider_settings_save('',0,'Synthetic joined hosting',true,HPC_SECRET,HPC_PASSWORD);provider_inventory_migrate(HPC_PASSWORD);provider_package_types_prepare(HPC_PASSWORD);
        $step='catalog-import';hp_ok('Joined path records an actual typed catalog refresh through fixed synthetic transport',provider_inventory_refresh($profile,1,HPC_PASSWORD,static fn()=>'8.8.8.8','hp_catalog',InventoryRead::PACKAGE_TYPES)==='complete'&&$GLOBALS['hp_catalog_calls']===1);
        $preview=provider_catalog_import_preview(1,'package.types');$product=provider_catalog_import_create($preview['hash'],'7001','JOINED-HOSTING','month',HPC_PASSWORD,'package.types');
        $terms=['name'=>'Joined hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['One website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Studio business hours'];
        catalog_save($product,1,['sku'=>'JOINED-HOSTING','title'=>'Joined hosting','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount'=>'25.00','setup'=>'0.00','cost'=>'9.87','provider_ref'=>'PRIVATE_JOINED_PROVIDER','summary'=>'Reviewed joined hosting service','features'=>'One website','terms'=>'Studio business hours','sort_order'=>'0']);
        service_plans_migrate(HPC_PASSWORD);$plan=service_plans_create(hp_id(1),$terms,HPC_PASSWORD);$plan=service_plans_change(hp_id(1),$plan->revision(),hp_id(2),'activate',[],HPC_PASSWORD);provider_plan_catalog_prepare(HPC_PASSWORD);
        $config=['reseller_id'=>42,'type'=>'7001','label'=>'Reviewed joined account','region'=>'Reviewed region','limits'=>['websites'=>1],'cost'=>['amount'=>'9.87','currency'=>'USD','period_months'=>1,'tax'=>'unknown','basis'=>'per-package','reference'=>'PRIVATE_JOINED_COST']];
        $mapping=provider_plan_catalog_save(hp_id(10),hp_id(1),$plan->revision(),$plan->fingerprint(),null,$profile,1,$config,7,true,HPC_PASSWORD);hosting_catalog_prepare(HPC_PASSWORD);
        $step='publication-request-conversion';$review=hosting_catalog_preview(hp_id(20),$product,hp_id(1));$pub=hosting_catalog_publish(hp_id(20),$product,hp_id(1),$review['review_sha256'],HPC_PASSWORD);
        hp_client();$request=hosting_catalog_request(hp_id(30),hp_id(20),$pub->fingerprint(),1);$before=hp_state();
        hp_ok('Joined request replay preserves all rows',hosting_catalog_request(hp_id(30),hp_id(20),$pub->fingerprint(),1)->json()===$request->json()&&hp_state()===$before);
        hp_client(2);hp_deny('Wrong client cannot replay the joined request',fn()=>hosting_catalog_request(hp_id(30),hp_id(20),$pub->fingerprint(),1));hp_owner();
        hp_deny('Joined conversion refuses the other client project',fn()=>hosting_catalog_convert(hp_id(40),hp_id(30),$request->fingerprint(),2,HPC_PASSWORD));
        $agreement=hosting_catalog_convert(hp_id(40),hp_id(30),$request->fingerprint(),1,HPC_PASSWORD);$aid=$agreement->data()['id'];
        hp_ok('Converted agreement retains exact imported publication plan and selected project',service_plans_json($agreement->data()['plan'])===$pub->data()['plan_json']&&(int)one('SELECT job_id FROM '.table('hosting_catalog_decisions').' WHERE request_id=?',[hp_id(30)])['job_id']===1&&$pub->data()['mapping_json']===one('SELECT binding_json FROM '.table('provider_plan_catalog').' WHERE id=?',[$mapping['id']])['binding_json']);
        $step='agreement-consent';$consent=['terms_hash'=>$agreement->model()->plan->termsHash()];hp_client(2);hp_deny('Wrong client cannot consent to joined agreement',fn()=>service_plans_client_change($aid,1,0,hp_id(41),'consent',$consent,true));hp_client();
        $agreed=service_plans_client_change($aid,1,0,hp_id(41),'consent',$consent,true);$before=hp_state();hp_ok('Joined agreement consent replay is inert',service_plans_client_change($aid,1,0,hp_id(41),'consent',$consent,true)->json()===$agreed->json()&&hp_state()===$before);hp_owner();
        $active=service_plans_subscription_change($aid,1,$agreed->revision(),hp_id(42),'activate',[],HPC_PASSWORD);hp_ok('Actual owner activation retains actual client terms consent',$active->model()->state()==='active'&&$active->model()->consent()['by']==='client:1');
        $step='billing-consent';payments_migrate(HPC_PASSWORD);ledger_migrate(HPC_PASSWORD);recurring_billing_migrate(HPC_PASSWORD);
        $fields=['format'=>1,'id'=>hp_id(50),'client_id'=>1,'project_id'=>1,'source_subscription'=>['id'=>$aid,'revision'=>$active->revision(),'sha256'=>$active->fingerprint()],'title'=>'Joined monthly hosting','terms'=>'Reviewed whole-period hosting invoice; no automatic charge.','currency'=>'USD','amount_cents'=>2500,'cadence'=>'monthly','anchor'=>gmdate('Y-m-d',time()+86400),'alignment'=>'day-of-month','timezone'=>'UTC','due_after_days'=>14,'collection'=>'invoice-only'];
        $billing=recurring_billing_offer($fields);$args=['terms_sha256'=>RecurringBillingTerms::fromArray($fields)->fingerprint()];hp_client(2);hp_deny('Wrong client cannot accept joined billing terms',fn()=>recurring_billing_decide(hp_id(50),1,0,hp_id(51),'accept',$args,true));hp_client();
        $billing=recurring_billing_decide(hp_id(50),1,0,hp_id(51),'accept',$args,true);hp_owner();$billing=recurring_billing_change(hp_id(50),1,$billing->revision(),hp_id(52),'activate',['period_index'=>0]);
        hp_ok('Real billing activation schedules a future period without early invoice',$billing->phase()==='running'&&$billing->assessment(0,time())['reason']==='not-started');$before=hp_state();
        hp_deny('Period-zero invoice remains refused before its agreed date',fn()=>recurring_billing_issue(hp_id(50),1,$billing->revision(),$billing->fingerprint(),0,hp_id(60)));hp_ok('Early invoice refusal preserves exact retained rows',hp_state()===$before&&(int)query('SELECT COUNT(*) FROM '.table('studio_invoices'))->fetchColumn()===0);
        // A second real origin remains unmapped until B supersedes A in the due phase.
        // It gets its own actual consent, schedule, invoice and receipt; no business rows are fabricated.
        $step='second-real-request';hp_client();$secondRequest=hosting_catalog_request(hp_id(130),hp_id(20),$pub->fingerprint(),1);hp_owner();
        $second=hosting_catalog_convert(hp_id(140),hp_id(130),$secondRequest->fingerprint(),1,HPC_PASSWORD);$secondId=$second->data()['id'];hp_client();
        $second=service_plans_client_change($secondId,1,0,hp_id(141),'consent',['terms_hash'=>$second->model()->plan->termsHash()],true);hp_owner();
        $second=service_plans_subscription_change($secondId,1,$second->revision(),hp_id(142),'activate',[],HPC_PASSWORD);
        $secondFields=$fields;$secondFields['id']=hp_id(150);$secondFields['source_subscription']=['id'=>$secondId,'revision'=>$second->revision(),'sha256'=>$second->fingerprint()];
        $secondBilling=recurring_billing_offer($secondFields);hp_client();$secondBilling=recurring_billing_decide(hp_id(150),1,0,hp_id(151),'accept',['terms_sha256'=>RecurringBillingTerms::fromArray($secondFields)->fingerprint()],true);hp_owner();
        $secondBilling=recurring_billing_change(hp_id(150),1,$secondBilling->revision(),hp_id(152),'activate',['period_index'=>0]);
        hp_ok('Supersession regression retains a second real accepted request agreement and future billing schedule',$second->model()->state()==='active'&&$secondBilling->assessment(0,time())['reason']==='not-started'&&(int)query('SELECT COUNT(*) FROM '.table('studio_invoices'))->fetchColumn()===0);
        $state=['profile'=>$profile,'product'=>$product,'publication'=>hp_id(20),'publication_sha256'=>$pub->fingerprint(),'request'=>hp_id(30),'agreement'=>$aid,'agreement_sha256'=>$active->fingerprint(),'mapping_id'=>$mapping['id'],'mapping_sha256'=>$mapping['sha256'],'billing'=>hp_id(50),'billing_sha256'=>$billing->fingerprint(),'anchor'=>$fields['anchor'],'supersession'=>['request'=>hp_id(130),'agreement'=>$secondId,'agreement_sha256'=>$second->fingerprint(),'billing'=>hp_id(150),'billing_sha256'=>$secondBilling->fingerprint()],'rows_sha256'=>hp_state()];
        $f=fopen($runtime.'/state.json','x');if(!$f)throw new RuntimeException('Purchase phase already exists');fwrite($f,json_encode($state,JSON_THROW_ON_ERROR));fclose($f);chmod($runtime.'/state.json',0600);$result['retained']=$state;
    }else{
        $step='retained-phase';$state=json_decode(file_get_contents($runtime.'/state.json'),true,16,JSON_THROW_ON_ERROR);
        hp_ok('Due phase starts with every exact retained database row from preparation',hp_state()===$state['rows_sha256']);hp_owner();
        $agreement=service_plans_subscription_get($state['agreement'],1);$billing=recurring_billing_get($state['billing'],1);
        hp_ok('Due time preserves agreement and billing histories without synthetic row rewrites',$agreement->fingerprint()===$state['agreement_sha256']&&$billing->fingerprint()===$state['billing_sha256']&&$billing->assessment(0,time())['eligible']===true);
        $step='period-zero-invoice';$issue=fn()=>recurring_billing_issue($state['billing'],1,$billing->revision(),$billing->fingerprint(),0,hp_id(60));$before=hp_state();
        hp_ok('Invoice audit failure rolls back real period-zero creation',hp_failure('recurring_billing.issued',$issue)&&hp_state()===$before);
        $issued=$issue();$invoice=ledger_invoice($issued['invoice_id']);hp_ok('Actual period-zero wrapper creates the exact joined client project obligation',!$issued['replayed']&&(int)$invoice['job_id']===1&&(int)$invoice['client_id']===1&&(int)$invoice['amount_cents']===2500&&$invoice['currency']==='USD'&&recurring_billing_invoice_origin((int)$invoice['id'])!==null);$before=hp_state();
        hp_ok('Exact period-zero invoice replay retains one obligation',$issue()===['invoice_id'=>(int)$invoice['id'],'replayed'=>true]&&hp_state()===$before);
        $step='manual-verified-receipt';$receiptArgs=[(int)$invoice['id'],2500,'bank','Synthetic verified joined hosting receipt',gmdate('Y-m-d'),hash('sha256','joined-hosting-receipt'),ledger_review_hash($invoice)];
        hp_client(2);hp_deny('Client cannot record a verified payment receipt',fn()=>ledger_receive(...$receiptArgs));hp_owner();$before=hp_state();
        hp_ok('Receipt audit failure rolls back the ledger and invoice status',hp_failure('payments.ledger.manual',fn()=>ledger_receive(...$receiptArgs))&&hp_state()===$before);
        $receipt=ledger_receive(...$receiptArgs);$before=hp_state();hp_ok('Verified synthetic manual receipt replay is inert',ledger_receive(...$receiptArgs)===$receipt&&hp_state()===$before);
        $invoice=ledger_invoice((int)$invoice['id']);hp_ok('Recorded manual evidence pays the exact invoice without a gateway attempt',$invoice['status']==='paid'&&ledger_balance($invoice)['due_cents']===0&&(int)query('SELECT COUNT(*) FROM '.table('payment_attempts'))->fetchColumn()===0);
        $step='hosting-order';service_orders_migrate(HPC_PASSWORD);
        $order=service_orders_offer(hp_id(70),$state['agreement'],1,$agreement->revision(),$agreement->fingerprint(),(int)$invoice['id'],ledger_invoice_hash($invoice),HPC_PASSWORD);
        hp_ok('Order retains the joined agreement and its first recurring invoice',$order->data()['invoice']['basis']==='initial-recurring-period'&&$order->data()['invoice']['schedule_id']===$state['billing']&&$order->data()['invoice']['period_index']===0&&$order->data()['invoice']['project_id']===1);
        $accept=['domain_name'=>HPC_DOMAIN,'review_sha256'=>$order->fingerprint()];hp_client(2);hp_deny('Wrong client cannot accept the joined hosting order',fn()=>service_orders_decide(hp_id(70),1,0,hp_id(71),'accept',$accept,true));hp_client();$order=service_orders_decide(hp_id(70),1,0,hp_id(71),'accept',$accept,true);hp_owner();
        $step='mapping';hosting_order_links_prepare(HPC_PASSWORD);hosting_fulfillment_prepare(HPC_PASSWORD);hosting_fulfillment_confirmation_prepare(HPC_PASSWORD);
        $mapping=recurring_billing_locked(fn()=>provider_plan_catalog_lookup($state['mapping_id']));$package=provider_plan_catalog_package($mapping['configuration'],hp_id(70));
        $before=hp_state();hp_deny('Catalog-origin purchase cannot bypass its published mapping with manual preparation',fn()=>hosting_order_links_create(hp_id(70),1,$order->revision(),$order->fingerprint(),hp_id(80),$package,$state['profile'],1,42,HPC_PASSWORD,null));
        hp_ok('Refused manual preparation leaves the real accepted order and every row intact',hp_state()===$before);
        $link=hp_measure('original_mapping_preparation',fn()=>hosting_order_links_create(hp_id(70),1,$order->revision(),$order->fingerprint(),hp_id(80),$package,$state['profile'],1,42,HPC_PASSWORD,$mapping['id']));
        hp_ok('Order preparation retains the originally published mapping identity and hash',$mapping['sha256']===$state['mapping_sha256']&&$link['binding']['catalog']===['id'=>$state['mapping_id'],'sha256'=>$state['mapping_sha256']]&&$link['binding']['commercial']['basis']==='verified-payment');
        $savedLink=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[hp_id(70)]);$review=['account_type'=>true,'defaults'=>true,'costs'=>true,'confirmation'=>'CREATE '.HPC_DOMAIN];
        $execute=fn()=>hosting_fulfillment_execute(hp_id(70),1,$order->revision(),$order->fingerprint(),$savedLink['binding_sha256'],hp_id(90),$review,HPC_PASSWORD,static fn()=>'8.8.8.8','hp_perform');
        $step='fulfillment';$before=hp_state();hp_deny('Fulfillment refuses a foreign client before synthetic transport',fn()=>hosting_fulfillment_execute(hp_id(70),2,$order->revision(),$order->fingerprint(),$savedLink['binding_sha256'],hp_id(90),$review,HPC_PASSWORD,static fn()=>'8.8.8.8','hp_perform'));
        hp_ok('Final dispatch approval audit failure preserves all rows and sends nothing',hp_failure('hosting_records.fulfillment_approved',$execute)&&hp_state()===$before&&HostingPurchaseTransport::$calls===[]);
        hp_ok('Real gateway and adapter dispatch exactly one synthetic purchase',hp_measure('synthetic_fulfillment',$execute)===['state'=>'accepted_pending','attempt_started'=>true]&&HostingPurchaseTransport::$calls===[['method'=>'POST','operation'=>'packages.create']]);$before=hp_state();
        hp_ok('Exact fulfillment replay never purchases twice',$execute()===['state'=>'replayed','attempt_started'=>false]&&hp_state()===$before&&count(HostingPurchaseTransport::$calls)===1);
        $step='confirmation';$approval=one('SELECT binding_sha256 FROM '.table('hosting_fulfillments').' WHERE id=?',[hp_id(90)]);
        $confirm=fn()=>hosting_fulfillment_confirm(hp_id(70),1,hp_id(90),$approval['binding_sha256'],HPC_PASSWORD,static fn()=>'8.8.8.8','hp_read');
        hp_ok('Actual saved inventory confirmation activates only the original synthetic package',hp_measure('synthetic_confirmation',$confirm)==='confirmed'&&hosting_records_get(hp_id(80),1)->account()->providerRef()===(string)HPC_PACKAGE&&HostingPurchaseTransport::$calls===[['method'=>'POST','operation'=>'packages.create'],['method'=>'GET','operation'=>'packages.list']]);$before=hp_state();
        hp_ok('Confirmed replay does not repeat the provider status read',$confirm()==='already_confirmed'&&hp_state()===$before&&count(HostingPurchaseTransport::$calls)===2);
        hp_client();hp_ok('Active order progress alone does not silently publish a client package link',hosting_order_links_context(hp_id(70),1,true)['progress']['status']==='active'&&provider_inventory_client_services(1)===[]);hp_owner();
        $before=hp_state();$handoff=hosting_fulfillment_view(hp_id(70),1)['assignment'];
        hp_ok('Owner handoff identifies the exact unlinked client package and inventory anchor',$handoff['state']==='unlinked'&&$handoff['client_id']===1&&$handoff['package_id']===(string)HPC_PACKAGE&&$handoff['url']===admin_url('provider-settings').'#provider-package-'.HPC_PACKAGE&&hp_state()===$before);
        $step='explicit-owner-package-link';$before=hp_state();hp_ok('Owner link audit failure preserves exact purchase and inventory history',hp_failure('provider.service_linked',fn()=>provider_inventory_link($state['profile'],HPC_PACKAGE,1,1,HPC_PASSWORD))&&hp_state()===$before);
        provider_inventory_link($state['profile'],HPC_PACKAGE,1,1,HPC_PASSWORD);$before=hp_state();hp_deny('Duplicate owner package link cannot reassign the purchased service',fn()=>provider_inventory_link($state['profile'],HPC_PACKAGE,2,1,HPC_PASSWORD));hp_ok('Duplicate owner link refusal preserves all rows',hp_state()===$before);
        $handoff=hosting_fulfillment_view(hp_id(70),1)['assignment'];hp_ok('Owner handoff confirms the exact linked client package without mutation',$handoff['state']==='linked'&&$handoff['client_id']===1&&$handoff['package_id']===(string)HPC_PACKAGE&&$handoff['url']===admin_url('provider-settings').'#provider-package-'.HPC_PACKAGE&&hp_state()===$before);
        hp_client();$client=studio_client();$services=provider_inventory_client_services((int)$client['id']);hp_ok('Authenticated client Hosting projection includes the exact purchased package',count($services)===1&&$services[0]['present']&&(int)$services[0]['link']['client_id']===1&&(int)$services[0]['package']['package_id']===HPC_PACKAGE&&$services[0]['package']['name']===HPC_DOMAIN&&(int)$services[0]['package']['enabled']===1);
        hp_client(2);hp_ok('Other authenticated client has no purchased Hosting service',provider_inventory_client_services((int)studio_client()['id'])===[]);hp_deny('Other client cannot read the original order progress',fn()=>hosting_order_links_context(hp_id(70),1,true));hp_owner();
        $step='real-unmapped-order-supersession';$secondState=$state['supersession'];$second=service_plans_subscription_get($secondState['agreement'],1);$secondBilling=recurring_billing_get($secondState['billing'],1);
        hp_ok('Second real agreement and schedule retain their original consent histories',$second->fingerprint()===$secondState['agreement_sha256']&&$secondBilling->fingerprint()===$secondState['billing_sha256']);
        $secondIssued=recurring_billing_issue($secondState['billing'],1,$secondBilling->revision(),$secondBilling->fingerprint(),0,hp_id(160));$secondInvoice=ledger_invoice($secondIssued['invoice_id']);
        ledger_receive((int)$secondInvoice['id'],2500,'bank','Synthetic verified supersession receipt',gmdate('Y-m-d'),hash('sha256','joined-supersession-receipt'),ledger_review_hash($secondInvoice));$secondInvoice=ledger_invoice((int)$secondInvoice['id']);
        $secondOrder=service_orders_offer(hp_id(170),$secondState['agreement'],1,$second->revision(),$second->fingerprint(),(int)$secondInvoice['id'],ledger_invoice_hash($secondInvoice),HPC_PASSWORD);
        hp_client();$secondOrder=service_orders_decide(hp_id(170),1,0,hp_id(171),'accept',['domain_name'=>'second-purchase.example','review_sha256'=>$secondOrder->fingerprint()],true);hp_owner();
        $configB=$mapping['configuration'];$configB['label']='Replacement reviewed mapping';
        $mappingB=provider_plan_catalog_save(hp_id(11),$mapping['plan_id'],$mapping['plan_revision'],$mapping['plan_sha256'],$mapping['id'],$state['profile'],1,$configB,7,true,HPC_PASSWORD);
        $packageB=provider_plan_catalog_package($mappingB['configuration'],hp_id(170));$packageA=provider_plan_catalog_package($mapping['configuration'],hp_id(170));$before=hp_state();
        hp_deny('Unmapped catalog-origin paid order refuses replacement mapping B for the same retail plan',fn()=>hosting_order_links_create(hp_id(170),1,$secondOrder->revision(),$secondOrder->fingerprint(),hp_id(180),$packageB,$state['profile'],1,42,HPC_PASSWORD,$mappingB['id']));
        hp_deny('Unmapped catalog-origin paid order refuses manual mapping after supersession',fn()=>hosting_order_links_create(hp_id(170),1,$secondOrder->revision(),$secondOrder->fingerprint(),hp_id(180),$packageB,$state['profile'],1,42,HPC_PASSWORD,null));
        hp_deny('Unmapped catalog-origin paid order cannot newly prepare superseded original A',fn()=>hosting_order_links_create(hp_id(170),1,$secondOrder->revision(),$secondOrder->fingerprint(),hp_id(180),$packageA,$state['profile'],1,42,HPC_PASSWORD,$mapping['id']));
        hp_ok('All supersession refusals preserve both real purchases and create no second hosting account',hp_state()===$before&&one('SELECT id FROM '.table('hosting_order_links').' WHERE id=?',[hp_id(170)])===null&&one('SELECT id FROM '.table('hosting_records').' WHERE id=?',[hp_id(180)])===null&&count(HostingPurchaseTransport::$calls)===2);
        $original=hosting_fulfillment_view(hp_id(70),1);$originalLink=one('SELECT binding_json,binding_sha256 FROM '.table('hosting_order_links').' WHERE id=?',[hp_id(70)]);
        hp_ok('Original A linked active history remains readable after B without reassociation',$original['mode']==='active'&&$original['assignment']['state']==='linked'&&$originalLink['binding_json']===$savedLink['binding_json']&&$originalLink['binding_sha256']===$savedLink['binding_sha256']&&hp_state()===$before);
        $result['supersession']=['request'=>$secondState['request'],'agreement'=>$secondState['agreement'],'billing'=>$secondState['billing'],'invoice'=>(int)$secondInvoice['id'],'order'=>hp_id(170),'original_mapping'=>$mapping['id'],'replacement_mapping'=>$mappingB['id'],'unmapped'=>true];
        $result['joined']=['publication'=>$state['publication'],'request'=>$state['request'],'agreement'=>$state['agreement'],'mapping'=>$state['mapping_id'],'billing'=>$state['billing'],'invoice'=>(int)$invoice['id'],'receipt'=>$receipt,'order'=>hp_id(70),'account'=>hp_id(80),'package_id'=>HPC_PACKAGE,'client_id'=>1,'project_id'=>1,'synthetic_transport'=>HostingPurchaseTransport::$calls,'rows_sha256'=>hp_state()];
    }
    $result['passed']=true;
}catch(Throwable $e){
    $message=str_replace([HPC_PASSWORD,HPC_CLIENT_PASSWORD,HPC_SECRET,(string)config()['db']['pass'],$root],['[synthetic-password]','[synthetic-client-password]','[synthetic-key]','[database-password]','[checkout]'],$e->getMessage());
    $result['error']=['operation'=>$step,'class'=>get_class($e),'message'=>substr($message,0,600),'file'=>basename($e->getFile()),'line'=>$e->getLine()];
}
$result['checks']=$checks;hp_write($phase.'-results.json',$result);echo json_encode(['passed'=>$result['passed'],'phase'=>$phase,'checks'=>count($checks)],JSON_THROW_ON_ERROR)."\n";exit($result['passed']?0:1);
