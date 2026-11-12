<?php
/** Disposable packaged checkout bridge with closed CLI-only gateway/provider continuations. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOSTING_CHECKOUT_HTTP')!=='1')exit(2);
$operation=$argv[1]??'';if(!in_array($operation,['prepare','state','contact-context','billing-state','payment-state','payment-outcome','payment-fulfill','payment-activate'],true))exit(2);
set_exception_handler(static function(Throwable $e)use($operation):never{
    $safe=['operation'=>$operation,'class'=>get_class($e),'message'=>'Disposable fixture precondition or application guard failed.','file'=>basename($e->getFile()),'line'=>$e->getLine()];
    if($e instanceof PDOException){$s=$e->errorInfo[0]??null;$c=$e->errorInfo[1]??null;$safe['sqlstate']=is_string($s)&&preg_match('/\A[A-Z0-9]{5}\z/',$s)?$s:null;$safe['driver_code']=is_int($c)?$c:null;}
    fwrite(STDERR,json_encode($safe,JSON_THROW_ON_ERROR).PHP_EOL);exit(1);
});
set_error_handler(static function(int $n,string $m,string $f,int $l):bool{if(!(error_reporting()&$n))return false;throw new ErrorException('Fixture PHP diagnostic',0,$n,$f,$l);});
$port=filter_var(getenv('CWP_TEST_PORT'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
if($port===false||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')throw new RuntimeException('Assigned disposable loopback database required');
$root=realpath(dirname(__DIR__));$site=$root.'/test-runtime/hosting-checkout-web/hosting-checkout';$private=$root.'/test-runtime/hosting-checkout-private';$sessions=$root.'/test-runtime/hosting-checkout-browser-sessions';
foreach([$site,$private,$sessions]as$p){if(!is_dir($p)||realpath($p)!==$p)throw new RuntimeException('Exact private runtime required');for($q=$p;$q!==dirname($q);$q=dirname($q))if(is_link($q))throw new RuntimeException('Linked runtime refused');}
if(!is_file($private.'/provider-master.key')||is_link($private.'/provider-master.key')||(fileperms($private.'/provider-master.key')&0077)!==0)throw new RuntimeException('Original protected provider key required');
$reportFile=$root.'/qa/hosting-checkout-native-results.json';if(!is_file($reportFile)||is_link($reportFile)||filesize($reportFile)>262144)throw new RuntimeException('Bounded original native report required');$raw=file_get_contents($reportFile);$expected=getenv('CWP_CHECKOUT_NATIVE_REPORT_SHA256');
if(!is_string($expected)||!preg_match('/\A[a-f0-9]{64}\z/',$expected)||!hash_equals($expected,hash('sha256',$raw)))throw new RuntimeException('Original native report differs');
$report=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$checks=$report['checks']??[];
    if(($report['passed']??null)!==true||isset($report['error'])||count($checks)!==84||count(array_unique(array_column($checks,'name')))!==84||array_filter($checks,static fn(array$c):bool=>($c['passed']??null)!==true))throw new RuntimeException('All corrected native outcomes required');
define('CWP_HOSTING_PRODUCTION_PAYMENTS',true);define('CWP_BOOT',true);define('CWP_ROOT',$site);require $site.'/includes/bootstrap.php';require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout.php';
require_once CWP_CODE_ROOT.'/app/hosting/payments.php';
require_once CWP_CODE_ROOT.'/database/migrations/gateway_receipts_migrate.php';
require_once CWP_CODE_ROOT.'/database/migrations/hosting_payments_migrate.php';
require_once CWP_CODE_ROOT.'/app/hosting/order_link_views.php';
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment_confirmation.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\{InventoryReader,PackageTypes};
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-checkout','storage'=>$private,'key'=>str_repeat('synthetic-checkout-local-',4),'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hcc_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$site;
ini_set('session.save_path',$sessions);
function hch_id(int$n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function hch_refresh_ready_at(int$started,int$now):int{if($now<1||$started<1||$started>$now||$started>PHP_INT_MAX-30)throw new RuntimeException('Unexpected retained refresh time');return $started+30;}
function hch_count(string$name):int{return(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function hch_rows(bool$financialOnly=false):string{
    $data=[];
    foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,4)=BINARY ? ORDER BY TABLE_NAME',['hcc_'])as$t){
        if(!preg_match('/\Ahcc_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unknown table');$n=substr($t['name'],4);
        if($financialOnly){if(!preg_match('/\A(?:service_|recurring_|payment_)/',$n)&&!in_array($n,['studio_jobs','studio_invoices','invoice_payments','hosting_checkout_reviews','hosting_checkout_results'],true))continue;}
        elseif(!preg_match('/\A(?:service_|recurring_|payment_|hosting_|catalog_|provider_)/',$n)&&!in_array($n,['studio_jobs','studio_invoices','invoice_payments'],true))continue;
        $values=array_map(static fn(array$r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$t['name'].'`'));sort($values,SORT_STRING);$data[$n]=$values;
    }
    // Business witnesses are included; login, rate and support audit effects intentionally are not.
    $where=$financialOnly?"action LIKE 'hosting_checkout.%' OR action LIKE 'recurring_billing.%' OR action LIKE 'service_plans.%'":"action LIKE 'hosting_checkout.%' OR action LIKE 'hosting_catalog.%' OR action LIKE 'recurring_billing.%' OR action LIKE 'service_plans.%'";
    $where.=" OR action LIKE 'payments.%'";
    $data['business_audits']=array_map(static fn(array$r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM '.table('audit').' WHERE '.$where.' ORDER BY id'));
    return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
}
function hch_read(array$row,string$key,string$address,callable$authorize,string$operation):InventorySnapshot{
    global $calls;$authorize();if($key!=='SYNTHETIC_CHECKOUT_PROVIDER_KEY'||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unknown synthetic read');$calls++;
    return PackageTypes::project(new TransportResponse(200,[],'[{"id":7001,"label":"Synthetic checkout hosting"}]'),time(),'twenty-i|'.$row['id'].'|'.$operation);
}
function hch_payment_owner():void{global$owner;$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];$_POST=[];}
function hch_payment_client(int$id=1):void{$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);if(!$client)throw new RuntimeException('Synthetic payment client missing');$_SESSION=['client_id'=>$id,'client_auth'=>$client['auth_version'],'client_login'=>time(),'client_last'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];$_POST=[];}
function hch_payment_gateway(string$outcome,string$transaction,int$invoice,string$request):callable{
    return static function(string$method,array$body,array$context)use($outcome,$transaction,$invoice,$request):array{
        if(($context['mode']??null)!=='production'||!is_int($context['revision']??null)||(int)$context['revision']<1||!preg_match('/\A[a-f0-9]{64}\z/',(string)($context['account_sha256']??'')))throw new LogicException('Synthetic gateway context differs');
        if($method==='createTransactionRequest'){
            $charge=$body['transactionRequest']??null;
            if(!is_array($charge)||($charge['transactionType']??null)!=='authCaptureTransaction'||($charge['amount']??null)!=='25.00'||($charge['order']['invoiceNumber']??null)!==substr('CWP-'.$invoice,0,20)
                ||($charge['payment']['opaqueData']['dataDescriptor']??null)!=='COMMON.ACCEPT.INAPP.PAYMENT'||isset($charge['payment']['creditCard'])
                ||($charge['customer']['id']??null)!==hosting_anet_customer_id($request)||($charge['customer']['email']??null)!=='checkout-client1@example.invalid')throw new LogicException('Synthetic gateway charge differs');
            if($outcome==='declined')return['messages'=>['resultCode'=>'Ok'],'transactionResponse'=>['responseCode'=>'2','transId'=>'0']];
            return['messages'=>['resultCode'=>'Ok'],'transactionResponse'=>['responseCode'=>'1','transId'=>$transaction]];
        }
        if($method!=='getTransactionDetailsRequest'||($body['transId']??null)!==$transaction)throw new LogicException('Synthetic gateway readback differs');
        if($outcome==='uncertain')throw new RuntimeException('Synthetic authoritative readback unavailable');
        return['messages'=>['resultCode'=>'Ok'],'transaction'=>['transId'=>$transaction,'transactionType'=>'authCaptureTransaction','transactionStatus'=>'settledSuccessfully','authAmount'=>'25.00','order'=>['invoiceNumber'=>substr('CWP-'.$invoice,0,20)],'customer'=>['id'=>hosting_anet_customer_id($request),'email'=>'checkout-client1@example.invalid']]];
    };
}
final class HchPaymentProviderTransport implements HttpTransport{
    public static int$calls=0;
    public function __construct(private Closure$authorize){}
    public function send(TransportRequest$request):TransportResponse{($this->authorize)();self::$calls++;if($request->method!=='POST'||$request->url!=='https://api.20i.com/reseller/42/addWeb'||json_decode((string)$request->body,true,8,JSON_THROW_ON_ERROR)!==['type'=>'7001','domain_name'=>'paid.example'])throw new LogicException('Synthetic provider request differs');return new TransportResponse(200,[],'{"result":866244}');}
}
function hch_payment_provider_perform(array$profile,string$key,string$address,callable$authorize,ProviderOperation$operation):\CajunWebPro\Foundation\Provider\OperationResult{
    if($key!=='SYNTHETIC_CHECKOUT_PROVIDER_KEY'||$address!=='8.8.8.8')throw new LogicException('Synthetic provider context differs');
    $identity=$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key);$credential=new ProviderCredential('twenty-i',$identity,static function()use($key,$authorize):string{$authorize();return$key;});
    return(new ProviderGateway(new TwentyIOperationAdapter(),new HchPaymentProviderTransport(Closure::fromCallable($authorize)),new ProviderOperationLedger()))->perform($credential,$operation);
}
function hch_payment_inventory_read(array$profile,string$key,string$address,callable$authorize,string$operation):InventorySnapshot{
    if($key!=='SYNTHETIC_CHECKOUT_PROVIDER_KEY'||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGES_LIST)throw new LogicException('Synthetic provider status context differs');
    $item=['id'=>866244,'typeRef'=>7001,'enabled'=>true,'name'=>'paid.example','names'=>['paid.example'],'created'=>'2026-11-11T00:00:00Z','packageTypeName'=>'Shared Hosting','packageLabels'=>[],'stackUsers'=>[],'productSpec'=>new stdClass()];
    $transport=new class(json_encode([$item],JSON_THROW_ON_ERROR),$authorize)implements HttpTransport{public function __construct(private string$body,private$authorize){}public function send(TransportRequest$request):TransportResponse{($this->authorize)();if($request->method!=='GET'||$request->url!=='https://api.20i.com/package'||$request->body!==null)throw new LogicException('Synthetic provider status request differs');return new TransportResponse(200,[],$this->body);}};
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($key,$authorize):string{$authorize();return$key;});return InventoryReader::read($transport,$credential,$operation,0,time());
}
$marker=$private.'/checkout-http-prepared.json';
$paymentMarker=$private.'/checkout-http-payment.json';
if($operation==='contact-context'){
    if(!is_file($marker)||is_link($marker))throw new RuntimeException('Prepare first');$input=json_decode(stream_get_contents(STDIN,1024),true,4,JSON_THROW_ON_ERROR);$sid=$input['session_id']??null;
    if(!is_string($sid)||!preg_match('/\A[A-Za-z0-9,-]{16,128}\z/',$sid))throw new RuntimeException('Existing session identity required');$file=$sessions.'/sess_'.$sid;
    if(!is_file($file)||is_link($file)||realpath($file)!==$file)throw new RuntimeException('Existing isolated session required');session_id($sid);start_session();
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');if(($_SESSION['client_id']??null)!==1||!is_string($_SESSION['client_auth']??null)||!hash_equals($c['auth_version'],$_SESSION['client_auth'])||isset($_SESSION['uid'])||array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Original primary login required');
    $_SESSION['contact_id']=900001;$_SESSION['contact_auth']=str_repeat('f',32);session_write_close();echo json_encode(['contact_context_set'=>true,'scope'=>'Negative established-key session probe only; not a contact login'],JSON_THROW_ON_ERROR);exit;
}
start_session();$owner=one('SELECT * FROM '.table('users').' WHERE id=1');$password='Synthetic native checkout owner';
if(!$owner||$owner['email']!=='checkout-owner@example.invalid'||!password_verify($password,$owner['password_hash']))throw new RuntimeException('Original synthetic owner required');
foreach([1,2]as$id){$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);if(!$c||$c['email']!=='checkout-client'.$id.'@example.invalid'||!password_verify('Synthetic checkout client',$c['password_hash']))throw new RuntimeException('Original clients required');}
$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];
if($operation==='prepare'){
    foreach([$marker,$site.'/config.php',$site.'/.installed.php']as$p)if(file_exists($p)||is_link($p))throw new RuntimeException('Preserve prior continuation');
    $initial=hosting_checkout_locked(function():array{
        hosting_checkout_require();$a=hosting_checkout_result_row(one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=?',[hch_id(30)]));$b=hosting_checkout_result_row(one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=?',[hch_id(40)]));
        if(hch_count('hosting_checkout_results')!==2||hch_count('studio_invoices')!==2||hch_count('invoice_payments')!==2||hch_count('gateway_receipts')!==1||hch_count('hosting_payment_attempts')!==1||hch_count('payment_attempts')!==0||$a['invoice']['status']!=='paid'||$b['invoice']['status']!=='paid'||!attempts_ready())throw new RuntimeException('Expected native graph differs');
        $old=provider_plan_catalog_lookup(hch_id(10));$pub=hosting_checkout_publication(hch_id(22));$p=provider_settings_load($old['profile_id']);if((int)$p['revision']!==2||(int)$p['enabled']!==0)throw new RuntimeException('Expected native final connection differs');
        return['financial_sha256'=>hch_rows(true),'profile'=>$p['id'],'product'=>$pub->data()['product']['id'],'plan'=>hch_id(1),'mapping'=>$old,'unpaid_invoice'=>(int)$b['invoice']['id']];
    });
    provider_settings_state($initial['profile'],2,'enable',$password);$calls=0;
    // Honor the real profile cooldown without editing retained intent timestamps or retrying a refresh.
    if(db()->inTransaction())throw new LogicException('Cooldown wait must be outside a transaction.');
    $latest=filter_var(query('SELECT MAX(started_epoch) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$initial['profile']])->fetchColumn(),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($latest===false)throw new RuntimeException('Original refresh time required');
    $readyAt=hch_refresh_ready_at($latest,time());$deadline=hrtime(true)+35000000000;
    while(time()<$readyAt){if(hrtime(true)>=$deadline)throw new RuntimeException('Bounded real cooldown wait expired');usleep(100000);}
    if(provider_inventory_refresh($initial['profile'],3,$password,static fn()=>'8.8.8.8','hch_read',InventoryRead::PACKAGE_TYPES)!=='complete'||$calls!==1)throw new RuntimeException('Closed refresh failed');
    $plan=recurring_billing_locked(static fn()=>service_plans_validate(service_plans_row($initial['plan'],false),false));
    provider_plan_catalog_save(hch_id(900),$initial['plan'],$plan->revision(),$plan->fingerprint(),hch_id(10),$initial['profile'],3,$initial['mapping']['configuration'],7,true,$password);
    if(hosting_checkout_locked(static fn():string=>hch_rows(true))!==$initial['financial_sha256'])throw new RuntimeException('Continuation changed prior commercial history');
    $safe=['prepared'=>true,'prefix'=>'hcc_','product_id'=>$initial['product'],'plan_id'=>$initial['plan'],'mapping_id'=>hch_id(900),'unpaid_invoice'=>$initial['unpaid_invoice'],'native_report_sha256'=>$expected,'prior_financial_sha256'=>$initial['financial_sha256'],'synthetic_refresh_calls'=>$calls,'native_end_state_sealed'=>false];
    foreach([$site.'/config.php'=>'<?php define(\'CWP_HOSTING_PRODUCTION_PAYMENTS\',true); return '.var_export(config(),true).';',$site.'/.installed.php'=>CWP_GUARD.'synthetic installed guard',$marker=>json_encode($safe,JSON_THROW_ON_ERROR)]as$p=>$bytes){$f=fopen($p,'x');if(!$f)throw new RuntimeException('Existing private output');try{if(fwrite($f,$bytes)!==strlen($bytes))throw new RuntimeException('Incomplete private output');}finally{fclose($f);}if(!chmod($p,0600))throw new RuntimeException('Private output permissions failed');}
    echo json_encode($safe,JSON_THROW_ON_ERROR);exit;
}
if(!is_file($marker)||is_link($marker))throw new RuntimeException('Prepare first');
if(in_array($operation,['payment-state','payment-outcome','payment-fulfill','payment-activate'],true)){
    require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';
    if($operation==='payment-state'&&!is_file($paymentMarker)){
        hch_payment_owner();gateway_receipts_prepare($password);hosting_payments_prepare($password);unset($GLOBALS['cwp_settings']);$gateway=hosting_anet_settings();
        if($gateway['mode']!=='production'||$gateway['login']!=='SYNTHETICCHECKOUT'||$gateway['client_key']!=='SYNTHETIC_PUBLIC_KEY'||!hosting_anet_production_enabled())throw new RuntimeException('Exact retained production fixture gateway required');
        $seed=json_decode(file_get_contents($marker),true,8,JSON_THROW_ON_ERROR);$publicationRow=one('SELECT id FROM '.table('hosting_catalog_publications').' WHERE product_id=? ORDER BY seq DESC LIMIT 1',[(int)$seed['product_id']]);
        if(!$publicationRow)throw new RuntimeException('Current Shared publication missing');$publication=hosting_catalog_publication($publicationRow['id']);
        $journeys=['approved'=>hch_id(510),'declined'=>hch_id(511),'uncertain'=>hch_id(512)];$created=[];
        foreach($journeys as$outcome=>$request){hch_payment_client();$review=hosting_checkout_preview($request,$publication->data()['id'],$publication->fingerprint(),1,['mode'=>'new-hosting']);$created[$outcome]=hosting_checkout_confirm($request,$review['review_sha256'],1,true);}
        if(count(array_unique(array_column($created,'invoice_id')))!==3||array_filter($created,static fn(array$row):bool=>$row['invoice_status']!=='issued'||$row['amount_cents']!==2500))throw new RuntimeException('Exact unpaid Shared payment journeys required');
        $saved=['journeys'=>$journeys,'order_id'=>hch_id(570),'decision_id'=>hch_id(571),'account_id'=>hch_id(580),'dispatch_id'=>hch_id(590)];$bytes=json_encode($saved,JSON_THROW_ON_ERROR);$file=fopen($paymentMarker,'x');if(!$file)throw new RuntimeException('Existing payment marker');try{if(fwrite($file,$bytes)!==strlen($bytes))throw new RuntimeException('Incomplete payment marker');}finally{fclose($file);}if(!chmod($paymentMarker,0600))throw new RuntimeException('Payment marker permissions failed');
    }
    if(!is_file($paymentMarker)||is_link($paymentMarker)||filesize($paymentMarker)>4096)throw new RuntimeException('Prepared payment marker required');
    $payment=json_decode(file_get_contents($paymentMarker),true,8,JSON_THROW_ON_ERROR);$journeys=$payment['journeys']??null;
    if(!is_array($journeys)||array_keys($journeys)!==['approved','declined','uncertain']||array_filter($journeys,static fn(mixed$id):bool=>!is_string($id)||!preg_match('/\A[a-f0-9]{32}\z/',$id)))throw new RuntimeException('Payment marker differs');
    if($operation==='payment-state'){
        hch_payment_client();$rows=[];$fulfillmentReady=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'hosting_fulfillments'])!==null;$providerReady=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_operations'])!==null;
        foreach($journeys as$outcome=>$request){$result=hosting_checkout_result($request,1);$projection=hosting_payment_projection($request,1);$rows[$outcome]=['request_id'=>$request,'invoice_id'=>$result['invoice_id'],'invoice_reference'=>$result['invoice_reference'],'result_url'=>studio_url(['hosting'=>'checkout','checkout_result'=>$request]),'invoice_url'=>studio_url(['invoice'=>$result['invoice_id']]),'payment_url'=>studio_url(['hosting'=>'checkout','checkout_result'=>$request]),'payment_stage'=>$projection['payment_stage'],'invoice_status'=>$projection['invoice_status'],'charge_state'=>$projection['charge_state'],'receipt_count'=>(int)query('SELECT COUNT(*) FROM '.table('gateway_receipts').' WHERE source=? AND source_id=?',['hosting_checkout',$request])->fetchColumn(),'attempt_count'=>(int)query('SELECT COUNT(*) FROM '.table('hosting_payment_attempts').' WHERE request_id=?',[$request])->fetchColumn(),'fulfillment_count'=>$outcome==='approved'&&$fulfillmentReady?(int)query('SELECT COUNT(*) FROM '.table('hosting_fulfillments').' WHERE order_id=?',[$payment['order_id']])->fetchColumn():0,'provider_attempt_count'=>$outcome==='approved'&&$providerReady?(int)(one('SELECT attempts FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$payment['dispatch_id']])['attempts']??0):0];}
        echo json_encode(['prepared'=>true,'journeys'=>$rows,'vps_url'=>site_url('index.php?page=vps-hosting'),'live_gateway_calls'=>0,'live_provider_calls'=>0],JSON_THROW_ON_ERROR);exit;
    }
    $input=json_decode(stream_get_contents(STDIN,4096),true,8,JSON_THROW_ON_ERROR);if(!is_array($input))throw new RuntimeException('Bounded payment input required');$request=$input['request_id']??null;
    if(!is_string($request)||!in_array($request,$journeys,true))throw new RuntimeException('Known payment journey required');
    if($operation==='payment-outcome'){
        $outcome=$input['outcome']??null;$attempt=$input['attempt_key']??null;$expected=array_search($request,$journeys,true);
        if(!is_string($outcome)||$outcome!==$expected||!is_string($attempt)||!preg_match('/\A[a-f0-9]{64}\z/',$attempt))throw new RuntimeException('Exact form attempt and outcome required');
        hch_payment_client();$result=hosting_checkout_result($request,1);$transaction=match($outcome){'approved'=>'9900510','uncertain'=>'9900512',default=>'9900511'};$status=hosting_payment_run($request,1,$attempt,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-local-opaque-token',hch_payment_gateway($outcome,$transaction,$result['invoice_id'],$request));
        if($status!==match($outcome){'approved'=>'paid','declined'=>'declined',default=>'review'})throw new RuntimeException('Closed payment outcome differs');
        echo json_encode(['configured'=>true,'request_id'=>$request,'outcome'=>$outcome,'live_gateway_calls'=>0],JSON_THROW_ON_ERROR);exit;
    }
    if($request!==$journeys['approved'])throw new RuntimeException('Only the approved journey may fulfill');hch_payment_owner();
    service_orders_migrate($password);hosting_order_links_prepare($password);hosting_operations_migrate($password);hosting_reconciliation_migrate($password);provider_operations_migrate($password);hosting_fulfillment_migrate($password);hosting_fulfillment_confirmation_prepare($password);
    hch_payment_client();$result=hosting_checkout_result($request,1);hch_payment_owner();$invoice=ledger_invoice($result['invoice_id']);$orderRow=one('SELECT id FROM '.table('service_orders').' WHERE id=?',[$payment['order_id']]);
    if(!$orderRow){$agreement=service_plans_validate(service_plans_row($result['agreement_id'],true),true);$order=service_orders_offer($payment['order_id'],$result['agreement_id'],1,$agreement->revision(),$agreement->fingerprint(),$result['invoice_id'],ledger_invoice_hash($invoice),$password);hch_payment_client();$order=service_orders_decide($payment['order_id'],1,$order->revision(),$payment['decision_id'],'accept',['domain_name'=>'paid.example','review_sha256'=>$order->fingerprint()],true);hch_payment_owner();$origin=hosting_catalog_agreement_origin($result['agreement_id'],1);if(!$origin)throw new RuntimeException('Checkout mapping origin missing');$mapping=provider_plan_catalog_lookup($origin['mapping_id']);$package=provider_plan_catalog_package($mapping['configuration'],$payment['order_id']);hosting_order_links_create($payment['order_id'],1,$order->revision(),$order->fingerprint(),$payment['account_id'],$package,$mapping['profile_id'],$mapping['profile_revision'],$mapping['configuration']['reseller_id'],$password,$mapping['id']);}
    $order=service_orders_validate(service_orders_row($payment['order_id']));$link=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$payment['order_id']]);if(!$link)throw new RuntimeException('Shared order link missing');
    if($operation==='payment-fulfill'){$review=['account_type'=>true,'defaults'=>true,'costs'=>true,'confirmation'=>'CREATE paid.example'];$sent=hosting_fulfillment_execute($payment['order_id'],1,$order->revision(),$order->fingerprint(),$link['binding_sha256'],$payment['dispatch_id'],$review,$password,static fn()=>'8.8.8.8','hch_payment_provider_perform');if(!in_array($sent['state']??null,['accepted_pending','replayed'],true))throw new RuntimeException('Closed provider fulfillment differs');hch_payment_client();$projection=hosting_payment_projection($request,1);echo json_encode(['fulfilled'=>true,'request_id'=>$request,'payment_stage'=>$projection['payment_stage'],'provider_attempts'=>(int)one('SELECT attempts FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$payment['dispatch_id']])['attempts'],'live_provider_calls'=>0],JSON_THROW_ON_ERROR);exit;}
    $approval=one('SELECT binding_sha256 FROM '.table('hosting_fulfillments').' WHERE id=? AND order_id=?',[$payment['dispatch_id'],$payment['order_id']]);if(!$approval)throw new RuntimeException('Fulfillment approval required before activation');$already=one('SELECT id FROM '.table('hosting_observations').' WHERE dispatch_id=? LIMIT 1',[$payment['dispatch_id']]);
    if(!$already){$latest=filter_var(query('SELECT MAX(started_epoch) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$link['profile_id']])->fetchColumn(),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($latest===false)throw new RuntimeException('Provider refresh history required');$readyAt=hch_refresh_ready_at($latest,time());$deadline=hrtime(true)+35000000000;while(time()<$readyAt){if(hrtime(true)>=$deadline)throw new RuntimeException('Bounded provider status cooldown wait expired');usleep(100000);}}
    $confirmed=hosting_fulfillment_confirm($payment['order_id'],1,$payment['dispatch_id'],$approval['binding_sha256'],$password,static fn()=>'8.8.8.8','hch_payment_inventory_read');if(!in_array($confirmed,['confirmed','already_confirmed'],true))throw new RuntimeException('Closed provider activation differs');hch_payment_client();$projection=hosting_payment_projection($request,1);echo json_encode(['activated'=>true,'request_id'=>$request,'payment_stage'=>$projection['payment_stage'],'provider_attempts'=>(int)one('SELECT attempts FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$payment['dispatch_id']])['attempts'],'live_provider_calls'=>0],JSON_THROW_ON_ERROR);exit;
}
if($operation==='billing-state'){
    require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';
    // Separate bounded read contract; the original state response below is unchanged.
    $result=hosting_checkout_locked(function():array{
        hosting_checkout_require();
        if(!provider_catalog_import_ready())throw new RuntimeException('Original catalog required');
        $variantsReady=provider_catalog_variant_ready();
        $counts=[];foreach(['reviews'=>'hosting_checkout_reviews','results'=>'hosting_checkout_results','jobs'=>'studio_jobs','invoices'=>'studio_invoices','receipts'=>'invoice_payments','attempts'=>'payment_attempts','publications'=>'hosting_catalog_publications','products'=>'catalog_products','media'=>'media','plans'=>'service_plans','plan_events'=>'service_plan_events','mappings'=>'provider_plan_catalog','provider_refreshes'=>'provider_refreshes','provider_checks'=>'provider_checks']as$key=>$name){
            $counts[$key]=hch_count($name);if($counts[$key]>128)throw new RuntimeException('Disposable billing fixture exceeded its bound');
        }
        $counts['variants']=$variantsReady?hch_count('catalog_provider_variants'):0;
        if($counts['variants']>128)throw new RuntimeException('Disposable variants exceeded their bound');
        $products=[];
        foreach(all('SELECT id,revision,sku,title,term,amount_cents,status FROM '.table('catalog_products').' ORDER BY id LIMIT 129')as$p){
            $id=(int)$p['id'];$origin=provider_catalog_import_record($id);if(!$origin)continue;
            $canonical=provider_catalog_import_canonical_record($origin);
            $variant=$variantsReady?one('SELECT id FROM '.table('catalog_provider_variants').' WHERE product_id=?',[$id]):null;
            $products[(string)$id]=['id'=>$id,'revision'=>(int)$p['revision'],'sku'=>$p['sku'],'title'=>$p['title'],'term'=>$p['term'],'amount_cents'=>$p['amount_cents']===null?null:(int)$p['amount_cents'],'status'=>$p['status'],
                'canonical_import_id'=>(int)$canonical['id'],'canonical_product_id'=>(int)$canonical['product_id'],'variant_id'=>$variant['id']??null,'media_id'=>$origin['media_id'],'media_revision'=>(int)$origin['media_revision']];
        }
        $publications=[];foreach(all('SELECT id FROM '.table('hosting_catalog_publications').' ORDER BY seq LIMIT 129')as$row){
            $publication=hosting_catalog_publication($row['id']);$plan=ServicePlanDocument::fromJson($publication->data()['plan_json']);
            $publications[$row['id']]=$publication->historicalProjection()+['plan_id'=>$plan->data()['id']];
        }
        $reviews=[];foreach(all('SELECT * FROM '.table('hosting_checkout_reviews').' ORDER BY id LIMIT 129')as$row){
            $review=hosting_checkout_review_row($row);$r=$review->data();
            $reviews[$row['id']]=['request_id'=>$r['request_id'],'review_sha256'=>$review->fingerprint(),'publication_id'=>$r['publication_id'],'publication_sha256'=>$r['publication_sha256'],'client_id'=>$r['client_id'],'project'=>$r['project'],'billing'=>$r['billing']];
        }
        $results=[];foreach(all('SELECT * FROM '.table('hosting_checkout_results').' ORDER BY id LIMIT 129')as$row){
            $validated=hosting_checkout_result_row($row);$d=$validated['document'];
            $map=one('SELECT snapshot_json FROM '.table('recurring_billing_invoices').' WHERE id=?',[$d['issuance_id']]);
            $snapshot=json_decode($map['snapshot_json'],true,8,JSON_THROW_ON_ERROR);
            $results[$row['id']]=hosting_checkout_projection($validated,true)+['publication_id'=>$d['publication_id'],'publication_sha256'=>$d['publication_sha256'],'client_id'=>$d['client_id'],'billing'=>$reviews[$row['id']]['billing'],'issued_period'=>$snapshot['period']];
        }
        // Hash original financial rows without exporting their contents. Fixed namespace, no caller selectors.
        $retained=[];$total=0;
        foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,4)=BINARY ? ORDER BY TABLE_NAME',['hcc_'])as$t){
            if(!preg_match('/\Ahcc_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unknown table');$n=substr($t['name'],4);
            if(!preg_match('/\A(?:service_|recurring_|payment_)/',$n)&&!in_array($n,['studio_jobs','studio_invoices','invoice_payments','hosting_checkout_reviews','hosting_checkout_results','catalog_provider_imports','hosting_catalog_publications','media'],true))continue;
            $rows=all('SELECT * FROM `'.$t['name'].'` LIMIT 129');$total+=count($rows);
            if(count($rows)>128||$total>1500)throw new RuntimeException('Disposable retained history exceeded its bound');
            $hashes=array_map(static fn(array$r):string=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$rows);sort($hashes,SORT_STRING);$retained[$n]=$hashes;
        }
        $audits=all('SELECT * FROM '.table('audit')." WHERE action LIKE 'hosting_checkout.%' OR action LIKE 'hosting_catalog.%' OR action LIKE 'recurring_billing.%' OR action LIKE 'service_plans.%' OR action LIKE 'payments.%' ORDER BY id LIMIT 129");
        if(count($audits)>128)throw new RuntimeException('Disposable business audit exceeded its bound');
        $hashes=array_map(static fn(array$r):string=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$audits);sort($hashes,SORT_STRING);$retained['business_audits']=$hashes;
        $io=[];foreach(['provider_refreshes','provider_refresh_results','provider_checks','provider_check_results','provider_operations','provider_operation_grants']as$name){
            $exists=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['hcc_'.$name])!==null;
            if(!$exists){if(!in_array($name,['provider_operations','provider_operation_grants'],true))throw new RuntimeException('Original provider read history missing');$io[$name]=['exists'=>false,'rows'=>[]];continue;}
            $rows=all('SELECT * FROM '.table($name).' LIMIT 129');if(count($rows)>128)throw new RuntimeException('Disposable provider history exceeded its bound');
            $hashes=array_map(static fn(array$r):string=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$rows);sort($hashes,SORT_STRING);$io[$name]=['exists'=>true,'rows'=>$hashes];
        }
        return['counts'=>$counts,'variants_ready'=>$variantsReady,'products'=>$products,'publications'=>$publications,'reviews'=>$reviews,'results'=>$results,'retained_rows'=>$retained,'provider_io_sha256'=>hash('sha256',json_encode($io,JSON_THROW_ON_ERROR))];
    });
    $encoded=json_encode($result,JSON_THROW_ON_ERROR);if(strlen($encoded)>262144)throw new RuntimeException('Disposable billing state exceeded its output bound');echo $encoded;exit;
}
$result=hosting_checkout_locked(function():array{
    hosting_checkout_require();$rows=[];foreach(all('SELECT * FROM '.table('hosting_checkout_results').' ORDER BY id')as$r){$v=hosting_checkout_result_row($r);$rows[$r['id']]=hosting_checkout_projection($v,true);}
    $latest=one('SELECT id,document_sha256 FROM '.table('hosting_catalog_publications').' ORDER BY seq DESC LIMIT 1');
    return['business_sha256'=>hch_rows(),'counts'=>['reviews'=>hch_count('hosting_checkout_reviews'),'results'=>hch_count('hosting_checkout_results'),'jobs'=>hch_count('studio_jobs'),'invoices'=>hch_count('studio_invoices'),'receipts'=>hch_count('invoice_payments'),'attempts'=>hch_count('payment_attempts'),'publications'=>hch_count('hosting_catalog_publications')],'latest_publication'=>$latest,'results'=>$rows,
        'setup_counts'=>['plans'=>hch_count('service_plans'),'plan_events'=>hch_count('service_plan_events'),'mappings'=>hch_count('provider_plan_catalog'),'provider_refreshes'=>hch_count('provider_refreshes'),'provider_checks'=>hch_count('provider_checks')]];
});echo json_encode($result,JSON_THROW_ON_ERROR);
