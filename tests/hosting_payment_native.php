<?php
/**
 * UX-08 ordinary hosting payment, authoritative receipt and Shared lifecycle.
 *
 * Disposable Linux/MariaDB only. Gateway and provider callbacks are closed
 * in-process fixtures; no live charge, provisioning, message or deployment.
 */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_HOSTING_PAYMENT')!=='1'
    ||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOSTING_PAYMENT_FOREIGN_DATABASE')!=='cwp_hpay_foreign'
    ||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$port=filter_var(getenv('CWP_TEST_PORT')?:'33306',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);if($port===false)exit(2);
define('CWP_HOSTING_PRODUCTION_PAYMENTS',true);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/database/migrations/hosting_storefront_migrate.php';
require_once __DIR__.'/../site/app/hosting/catalog_offers.php';
require_once __DIR__.'/../site/app/hosting/catalog_checkout.php';
require_once __DIR__.'/../site/app/hosting/payments.php';
require_once __DIR__.'/../site/app/billing/software_subscription_settle.php';
require_once __DIR__.'/../site/database/migrations/gateway_receipts_migrate.php';
require_once __DIR__.'/../site/database/migrations/hosting_payments_migrate.php';
require_once __DIR__.'/../site/app/hosting/order_link_views.php';
require_once __DIR__.'/../site/app/hosting/fulfillment_confirmation.php';

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes;

$checks=[];$stage='preflight';$reportReady=false;$password='Synthetic hosting payment owner';$secret='SYNTHETIC_HOSTING_PAYMENT_PROVIDER_KEY';
$runtime=__DIR__.'/../test-runtime';$private=$runtime.'/hosting-payment-private';$qa=__DIR__.'/../qa';
$reportPath=$qa.'/hosting-payment-native-results.json';
if(file_exists($reportPath)||is_link($reportPath))exit(2);
function hp_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException('Named hosting payment assertion failed.');}
function hp_deny(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;hp_ok($name,true);return;}hp_ok($name,false);}
function hp_id(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function hp_key(int $n):string{return hash('sha256','hosting-payment-attempt-'.$n);}
function hp_count(string $name):int{return(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function hp_refresh_ready_at(int $started,int $now):int {if($now<1||$started<1||$started>$now||$started>PHP_INT_MAX-30)throw new RuntimeException('Unexpected retained refresh time.');return $started+30;}
function hp_identifier(string $name):string {if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/',$name))throw new LogicException('Invalid fixture identifier.');return '`'.$name.'`';}
function hp_cross_schema_fk_refused(string $child,string $column,string $parent,string $parentColumn,callable $validate):void {
    $database=config()['db']['name'];$foreign=(string)getenv('CWP_TEST_HOSTING_PAYMENT_FOREIGN_DATABASE');$childTable=config()['db']['prefix'].$child;$parentTable=config()['db']['prefix'].$parent;
    if($foreign!=='cwp_hpay_foreign'||$foreign===$database||!one('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[$foreign]))throw new RuntimeException('Exact isolated foreign fixture schema unavailable.');
    if(one('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? LIMIT 1',[$foreign]))throw new RuntimeException('Preserve unexpected foreign fixture data.');
    $relation=one('SELECT r.CONSTRAINT_NAME,r.DELETE_RULE,r.UPDATE_RULE,k.REFERENCED_TABLE_SCHEMA FROM information_schema.REFERENTIAL_CONSTRAINTS r JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.COLUMN_NAME=? AND k.REFERENCED_TABLE_NAME=?',[$childTable,$column,$parentTable]);
    if(!$relation)throw new RuntimeException('Expected local fixture relationship missing.');$constraint=(string)$relation['CONSTRAINT_NAME'];
    if($relation['REFERENCED_TABLE_SCHEMA']!==$database)throw new RuntimeException('Expected local fixture relationship is already cross-database.');$created=false;
    try{
        db()->exec('CREATE TABLE '.hp_identifier($foreign).'.'.hp_identifier($parentTable).' LIKE '.hp_identifier($database).'.'.hp_identifier($parentTable));
        $created=true;
        db()->exec('ALTER TABLE '.hp_identifier($childTable).' DROP FOREIGN KEY '.hp_identifier($constraint));
        db()->exec('ALTER TABLE '.hp_identifier($childTable).' ADD CONSTRAINT '.hp_identifier($constraint).' FOREIGN KEY ('.hp_identifier($column).') REFERENCES '.hp_identifier($foreign).'.'.hp_identifier($parentTable).' ('.hp_identifier($parentColumn).') ON DELETE '.$relation['DELETE_RULE'].' ON UPDATE '.$relation['UPDATE_RULE']);
        hp_deny('Cross-database '.$child.' relationship is refused before schema adoption',$validate);
        db()->exec('ALTER TABLE '.hp_identifier($childTable).' DROP FOREIGN KEY '.hp_identifier($constraint));
        db()->exec('ALTER TABLE '.hp_identifier($childTable).' ADD CONSTRAINT '.hp_identifier($constraint).' FOREIGN KEY ('.hp_identifier($column).') REFERENCES '.hp_identifier($database).'.'.hp_identifier($parentTable).' ('.hp_identifier($parentColumn).') ON DELETE '.$relation['DELETE_RULE'].' ON UPDATE '.$relation['UPDATE_RULE']);
        $validate();
    }finally{
        $current=one('SELECT k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.CONSTRAINT_NAME=? AND k.COLUMN_NAME=?',[$childTable,$constraint,$column]);
        if(!$current||$current['REFERENCED_TABLE_SCHEMA']!==$database||$current['REFERENCED_TABLE_NAME']!==$parentTable){
            if($current)db()->exec('ALTER TABLE '.hp_identifier($childTable).' DROP FOREIGN KEY '.hp_identifier($constraint));
            db()->exec('ALTER TABLE '.hp_identifier($childTable).' ADD CONSTRAINT '.hp_identifier($constraint).' FOREIGN KEY ('.hp_identifier($column).') REFERENCES '.hp_identifier($database).'.'.hp_identifier($parentTable).' ('.hp_identifier($parentColumn).') ON DELETE '.$relation['DELETE_RULE'].' ON UPDATE '.$relation['UPDATE_RULE']);
        }
        if($created)db()->exec('DROP TABLE '.hp_identifier($foreign).'.'.hp_identifier($parentTable));
    }
}
function hp_owner():void {$u=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];$_POST=[];}
function hp_client(int $id=1):void {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];$_POST=[];}
function hp_state():string {
    $out=[];foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ? ORDER BY TABLE_NAME',['hpay_'])as$t){
        if(!preg_match('/\Ahpay_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unexpected fixture table.');
        $rows=array_map(static fn(array$row):string=>json_encode($row,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$t['name'].'`'));sort($rows,SORT_STRING);$out[$t['name']]=$rows;
    }return hash('sha256',json_encode($out,JSON_THROW_ON_ERROR));
}
function hp_policy():array {return ['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>'UTC','alignment'=>'day-of-month','review_ttl_seconds'=>900];}
function hp_checkout(HostingCatalogPublication $publication,int $number,int $client=1):array {
    hp_client($client);$review=hosting_checkout_preview(hp_id($number),$publication->data()['id'],$publication->fingerprint(),$client,['mode'=>'existing','id'=>$client]);
    return hosting_checkout_confirm(hp_id($number),$review['review_sha256'],$client,true);
}
function hp_gateway_details(string $transaction,int $invoice,string $request,int $client=1,int $cents=2500,string $status='settledSuccessfully'):array {
    return ['messages'=>['resultCode'=>'Ok'],'transaction'=>['transId'=>$transaction,'transactionType'=>'authCaptureTransaction','transactionStatus'=>$status,'authAmount'=>number_format($cents/100,2,'.',''),'order'=>['invoiceNumber'=>substr('CWP-'.$invoice,0,20)],
        'customer'=>['id'=>hosting_anet_customer_id($request),'email'=>'hosting-payment-client'.$client.'@example.invalid']]];
}
/** Closed gateway script. Every call verifies the exact nonsecret production context. */
function hp_gateway(string $outcome,string $transaction,int $invoice,string $checkoutRequest,int $client=1,int $cents=2500,string $mode='production'):callable {
    return static function(string $method,array $request,array $context)use($outcome,$transaction,$invoice,$checkoutRequest,$client,$cents,$mode):array{
        if(($context['mode']??null)!==$mode||(int)($context['revision']??0)<1
            ||!preg_match('/\A[a-f0-9]{64}\z/',(string)($context['account_sha256']??'')))throw new LogicException('Synthetic gateway context differs.');
        if($method==='createTransactionRequest'){
            $charge=$request['transactionRequest']??null;
            if(!is_array($charge)||($charge['transactionType']??null)!=='authCaptureTransaction'||($charge['amount']??null)!==number_format($cents/100,2,'.','')
                ||($charge['order']['invoiceNumber']??null)!==substr('CWP-'.$invoice,0,20)||isset($charge['payment']['creditCard'])
                ||($charge['payment']['opaqueData']['dataDescriptor']??null)!=='COMMON.ACCEPT.INAPP.PAYMENT'
                ||($charge['customer']['id']??null)!==hosting_anet_customer_id($checkoutRequest)
                ||($charge['customer']['email']??null)!=='hosting-payment-client'.$client.'@example.invalid')throw new LogicException('Synthetic charge request differs.');
            if($outcome==='declined')return ['messages'=>['resultCode'=>'Ok'],'transactionResponse'=>['responseCode'=>'2','transId'=>'0']];
            return ['messages'=>['resultCode'=>'Ok'],'transactionResponse'=>['responseCode'=>'1','transId'=>$transaction]];
        }
        if($method!=='getTransactionDetailsRequest'||($request['transId']??null)!==$transaction)throw new LogicException('Synthetic readback request differs.');
        if($outcome==='uncertain')throw new RuntimeException('Synthetic authoritative readback unavailable.');
        if($outcome==='forged')return hp_gateway_details($transaction,$invoice+1,$checkoutRequest,$client,$cents);
        return hp_gateway_details($transaction,$invoice,$checkoutRequest,$client,$cents);
    };
}
/** Capture one deliberately exercised review-path diagnostic without hiding any unexpected output. */
function hp_expected_report(callable $work,string $origin,int $line):array {
    if(PHP_SAPI!=='cli'||getenv('CWP_TEST_HOSTING_PAYMENT')!=='1'||config()['db']['prefix']!=='hpay_'
        ||!in_array([$origin,$line],[['hosting_payment_native.php',102],['payments.php',79]],true))throw new LogicException('Expected diagnostic scope differs.');
    $path=tempnam(sys_get_temp_dir(),'cwp-hosting-payment-fault-');if($path===false)throw new RuntimeException('Could not capture expected hosting payment diagnostic.');
    $old=ini_get('error_log');$value=null;$escaped=null;$restoreFailed=false;$readFailed=false;$log='';
    try{
        if(ini_set('error_log',$path)===false)throw new RuntimeException('Could not isolate expected hosting payment diagnostic.');
        $value=$work();
    }catch(Throwable $error){
        $escaped=$error;
    }finally{
        $restoreFailed=ini_set('error_log',$old===false?'':$old)===false;
        $captured=@file_get_contents($path);$readFailed=$captured===false;$log=$readFailed?'':$captured;
    }
    $expected=$escaped===null&&!$restoreFailed&&!$readFailed
        &&preg_match('/\A(?:\[[^\r\n]+\] )?CWP \[[A-F0-9]{8}\] RuntimeException code=0 in '.preg_quote($origin,'/').':'.$line.'\r?\n\z/D',$log)===1;
    $preserve=static function(string $bytes):void{$offset=0;$length=strlen($bytes);while($offset<$length){$written=fwrite(STDERR,substr($bytes,$offset));if($written===false||$written===0)throw new RuntimeException('Could not preserve unexpected hosting payment diagnostic.');$offset+=$written;}};
    if(!$expected){
        $preserve($log);
    }
    if(!@unlink($path)){
        if($expected)$preserve($log);
        throw new RuntimeException('Could not remove expected hosting payment diagnostic storage.');
    }
    if($escaped!==null)throw $escaped;
    if($restoreFailed)throw new RuntimeException('Could not restore hosting payment diagnostic destination.');
    if($readFailed)throw new RuntimeException('Could not read expected hosting payment diagnostic.');
    return ['value'=>$value,'expected'=>$expected];
}

final class HpProviderTransport implements HttpTransport {
    public static int $calls=0;
    public function __construct(private Closure $authorize){}
    public function send(TransportRequest $request):TransportResponse {
        ($this->authorize)();self::$calls++;
        if($request->method!=='POST'||$request->url!=='https://api.20i.com/reseller/42/addWeb'
            ||json_decode((string)$request->body,true,8,JSON_THROW_ON_ERROR)!==['type'=>'7001','domain_name'=>'paid.example'])throw new LogicException('Unexpected synthetic provider request.');
        return new TransportResponse(200,[],'{"result":866244}');
    }
}
function hp_provider_perform(array $profile,string $key,string $address,callable $authorize,ProviderOperation $operation):\CajunWebPro\Foundation\Provider\OperationResult {
    global $secret;if($key!==$secret||$address!=='8.8.8.8')throw new LogicException('Unexpected synthetic provider context.');
    $identity=$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key);
    $credential=new ProviderCredential('twenty-i',$identity,static function()use($key,$authorize):string{$authorize();return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new HpProviderTransport(Closure::fromCallable($authorize)),new ProviderOperationLedger()))->perform($credential,$operation);
}
function hp_inventory_read(array $profile,string $key,string $address,callable $authorize,string $operation):InventorySnapshot {
    global $secret;if($key!==$secret||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGES_LIST)throw new LogicException('Unexpected synthetic status context.');
    $item=['id'=>866244,'typeRef'=>7001,'enabled'=>true,'name'=>'paid.example','names'=>['paid.example'],'created'=>'2026-11-11T00:00:00Z','packageTypeName'=>'Shared Hosting','packageLabels'=>[],'stackUsers'=>[],'productSpec'=>new stdClass()];
    $transport=new class(json_encode([$item],JSON_THROW_ON_ERROR),$authorize) implements HttpTransport {
        public function __construct(private string $body,private $authorize){}
        public function send(TransportRequest $request):TransportResponse {
            ($this->authorize)();
            if($request->method!=='GET'||$request->url!=='https://api.20i.com/package'||$request->body!==null)throw new LogicException('Unexpected synthetic status request.');
            return new TransportResponse(200,[],$this->body);
        }
    };
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($key,$authorize):string{$authorize();return $key;});
    return InventoryReader::read($transport,$credential,$operation,0,time());
}
function hp_paths(string $path):void {$current=$path;while(true){if(is_link($current))throw new RuntimeException('Fixture path has a link ancestor.');$parent=dirname($current);if($parent===$current)break;$current=$parent;}}

try {
    hp_paths($runtime);hp_paths($private);hp_paths($qa);
    if(file_exists($private)||is_link($private))throw new RuntimeException('Preserve existing hosting payment fixture storage.');
    if(!is_dir($runtime)&&!mkdir($runtime,0700,true))throw new RuntimeException('Cannot create runtime directory.');
    if(!mkdir($private,0700)||!mkdir($private.'/sessions',0700)||(!is_dir($qa)&&!mkdir($qa,0700,true)))throw new RuntimeException('Cannot create isolated fixture paths.');
    $reportReady=true;
    $GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8092/hosting-payment','storage'=>realpath($private),'key'=>str_repeat('synthetic-hosting-payment-',4),'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hpay_']];
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8092','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');ini_set('session.save_path',$private.'/sessions');start_session();
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ? LIMIT 1',['hpay_']))throw new RuntimeException('Preserve existing hpay_ data.');
    hp_ok('Explicit disposable database and separate hpay namespace are selected',config()['db']['name']==='cwp_test'&&config()['db']['prefix']==='hpay_');

    $stage='seed';foreach(schema_statements('hpay_')as$name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings()as$key=>$value)save_setting($key,$value);save_setting('studio_schema_version','2');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Hosting payment owner','hosting-payment-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('a',32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);
    foreach([1,2]as$id){query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Hosting payment client '.$id,'hosting-payment-client'.$id.'@example.invalid',password_hash('Synthetic hosting payment client',PASSWORD_DEFAULT),str_repeat($id===1?'c':'d',32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,'Hosting payment project '.$id,'Synthetic project','',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);}
    hp_owner();catalog_migrate($password);provider_catalog_import_prepare($password);provider_settings_migrate($password);
    $profile=provider_settings_save('',0,'Synthetic hosting payment connection',true,$secret,$password);provider_inventory_migrate($password);provider_package_types_prepare($password);
    $typeRead=static function(array$row,string$key,string$address,callable$authorize,string$operation):InventorySnapshot{global$secret;if($key!==$secret||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unexpected package type read.');$authorize();return PackageTypes::project(new TransportResponse(200,[],'[{"id":7001,"label":"Synthetic Shared"}]'),time(),'twenty-i|'.$row['id'].'|'.$operation);};
    hp_ok('One closed provider catalog read seeds the Shared mapping',provider_inventory_refresh($profile,1,$password,static fn()=>'8.8.8.8',$typeRead,InventoryRead::PACKAGE_TYPES)==='complete');
    $preview=provider_catalog_import_preview(1,'package.types');$product=provider_catalog_import_create($preview['hash'],'7001','HOSTING-PAYMENT','month',$password,'package.types');
    catalog_save($product,1,['sku'=>'HOSTING-PAYMENT','title'=>'Verified Shared hosting','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount'=>'25.00','setup'=>'0.00','cost'=>'9.87','provider_ref'=>'PRIVATE_PROVIDER_REFERENCE','summary'=>'One verified Shared plan.','features'=>'PRIVATE_FEATURES','terms'=>'PRIVATE_TERMS','sort_order'=>'0']);
    service_plans_migrate($password);$terms=['name'=>'Verified Shared hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['One website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email support'];
    $plan=service_plans_create(hp_id(1),$terms,$password);$plan=service_plans_change(hp_id(1),$plan->revision(),hp_id(2),'activate',[],$password);
    provider_plan_catalog_prepare($password);$configuration=['reseller_id'=>42,'type'=>'7001','label'=>'Synthetic Shared account','region'=>'Reviewed region','limits'=>['websites'=>1],'cost'=>['amount'=>'9.87','currency'=>'USD','period_months'=>1,'tax'=>'unknown','basis'=>'per-package','reference'=>'PRIVATE_COST']];
    provider_plan_catalog_save(hp_id(10),hp_id(1),$plan->revision(),$plan->fingerprint(),null,$profile,1,$configuration,7,true,$password);
    hosting_catalog_prepare($password);hosting_storefront_prepare($password);payments_migrate($password);attempts_migrate($password);ledger_migrate($password);service_plans_checkout_migrate($password);recurring_billing_migrate($password);recurring_billing_checkout_migrate($password);hosting_checkout_prepare($password);
    gateway_receipts_prepare($password);hosting_payments_prepare($password);
    $ledgerColumn=one('SHOW FULL COLUMNS FROM '.table('gateway_receipts').' WHERE Field=?',['ledger_entry_id']);
    hp_ok('Gateway receipt ledger foreign key has the exact BIGINT UNSIGNED width',(string)($ledgerColumn['Type']??'')==='bigint(20) unsigned'||(string)($ledgerColumn['Type']??'')==='bigint unsigned');
    hp_cross_schema_fk_refused('gateway_receipts','client_id','studio_clients','id','gateway_receipts_validate_schema');
    $paymentSchemas=hosting_payments_schema(config()['db']['prefix']);
    hp_cross_schema_fk_refused('hosting_payment_events','request_id','hosting_payment_charges','request_id',fn()=>hosting_payments_validate_schema('hosting_payment_events',$paymentSchemas['hosting_payment_events']));
    payments_save('production',0,['api_login_id'=>'SYNTHETICLOGIN','transaction_key'=>'1234567890ABCDEF','signature_key'=>str_repeat('a',128),'public_client_key'=>'SYNTHETIC_PUBLIC_KEY'],$password);
    hosting_payment_configure('production',true,$password);unset($GLOBALS['cwp_settings']);
    $sharedWebhook=authorize_net_webhook_hosting_gateway(['mode'=>'production','login'=>'SYNTHETICLOGIN']);
    hp_ok('Shared webhook preserves the current hosting gateway revision',is_array($sharedWebhook)&&$sharedWebhook['mode']==='production'&&$sharedWebhook['revision']===1&&$sharedWebhook['account_sha256']===gateway_receipt_account_hash('SYNTHETICLOGIN'));
    $review=hosting_catalog_preview(hp_id(20),$product,hp_id(1),hp_policy());$publication=hosting_catalog_publish(hp_id(20),$product,hp_id(1),$review['review_sha256'],$password,hp_policy());
    $approved=hp_checkout($publication,30);$declined=hp_checkout($publication,31);$uncertain=hp_checkout($publication,32);$forged=hp_checkout($publication,33);$rotation=hp_checkout($publication,34);$sandboxRotation=hp_checkout($publication,35);
    hp_ok('Six actual Shared checkout confirmations begin as separate unpaid invoices',count(array_unique(array_column([$approved,$declined,$uncertain,$forged,$rotation,$sandboxRotation],'invoice_id')))==6&&hp_count('invoice_payments')===0&&array_reduce([$approved,$declined,$uncertain,$forged,$rotation,$sandboxRotation],static fn(bool$ok,array$row):bool=>$ok&&$row['invoice_status']==='issued'&&!$row['automatic_charge']&&!$row['provider_activation'],true));

    $stage='binding';hp_client();$initial=hosting_payment_projection(hp_id(30),1);$before=hp_state();
    $_GET=['payment'=>'success','transactionId'=>'999999999'];$returned=hosting_payment_projection(hp_id(30),1);$_GET=[];
    hp_ok('Success-looking browser return is a read and cannot settle hosting',$returned===$initial&&$initial['payment_stage']==='awaiting_payment'&&hp_state()===$before);
    hp_client(2);hp_deny('Another authenticated client cannot project the payment',fn()=>hosting_payment_projection(hp_id(30),2));
    hp_deny('Another authenticated client cannot submit the payment',fn()=>hosting_payment_run(hp_id(30),2,hp_key(1),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token',hp_gateway('approved','8800001',$approved['invoice_id'],hp_id(30))));
    hp_client();hp_deny('Malformed token is rejected before an attempt exists',fn()=>hosting_payment_run(hp_id(30),1,hp_key(1),'forged','raw-card-data',hp_gateway('approved','8800001',$approved['invoice_id'],hp_id(30))));
    hp_ok('Binding refusals preserve every commercial row',hp_state()===$before&&hp_count('hosting_payment_attempts')===0&&hp_count('gateway_receipts')===0);

    $stage='outcomes';$declinedStatus=hosting_payment_run(hp_id(31),1,hp_key(10),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token',hp_gateway('declined','8800010',$declined['invoice_id'],hp_id(31)));
    $declinedProjection=hosting_payment_projection(hp_id(31),1);
    hp_ok('Decline remains unpaid and records no authoritative receipt',$declinedStatus==='declined'&&$declinedProjection['invoice_status']==='issued'&&$declinedProjection['charge_state']==='declined'&&$declinedProjection['payment_stage']==='awaiting_payment'&&hp_count('gateway_receipts')===0);
    $retryStatus=hosting_payment_run(hp_id(31),1,hp_key(11),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token-2',hp_gateway('approved','8800011',$declined['invoice_id'],hp_id(31)));
    hp_ok('A fresh retry after a definite decline settles once',$retryStatus==='paid'&&hosting_payment_projection(hp_id(31),1)['payment_stage']==='paid_setup_pending');

    $uncertainCapture=hp_expected_report(fn()=>hosting_payment_run(hp_id(32),1,hp_key(20),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token',hp_gateway('uncertain','8800020',$uncertain['invoice_id'],hp_id(32))),'hosting_payment_native.php',102);$uncertainStatus=$uncertainCapture['value'];
    $uncertainProjection=hosting_payment_projection(hp_id(32),1);$uncertainBefore=hp_state();
    hp_ok('Unknown authoritative readback fences the invoice for review',$uncertainCapture['expected']&&$uncertainStatus==='review'&&in_array($uncertainProjection['charge_state'],['uncertain','review'],true)&&$uncertainProjection['invoice_status']==='issued'&&$uncertainProjection['payment_stage']==='payment_review'&&hp_count('gateway_receipts')===1);
    hp_deny('Uncertain result refuses a second charge attempt',fn()=>hosting_payment_run(hp_id(32),1,hp_key(21),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token-2',hp_gateway('approved','8800021',$uncertain['invoice_id'],hp_id(32))));
    hp_ok('Refused uncertain retry is inert',hp_state()===$uncertainBefore);
    hp_owner();$manualBefore=hp_state();hp_deny('Unresolved hosting capture blocks a concurrent manual invoice receipt',fn()=>ledger_receive($uncertain['invoice_id'],2500,'bank','Synthetic conflicting bank transfer',gmdate('Y-m-d'),hp_key(22),ledger_review_hash(ledger_invoice($uncertain['invoice_id']))));
    hp_ok('Blocked manual receipt leaves the complete payment graph unchanged',hp_state()===$manualBefore);
    hp_owner();$reconciled=hosting_payment_reconcile(hp_id(32),'8800020',$password,hp_gateway('approved','8800020',$uncertain['invoice_id'],hp_id(32)));hp_client();
    hp_ok('Owner exact readback reconciliation settles the retained uncertain attempt',$reconciled==='paid'&&hosting_payment_projection(hp_id(32),1)['payment_stage']==='paid_setup_pending');

    $forgedCapture=hp_expected_report(fn()=>hosting_payment_run(hp_id(33),1,hp_key(30),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token',hp_gateway('forged','8800030',$forged['invoice_id'],hp_id(33))),'payments.php',79);$forgedStatus=$forgedCapture['value'];
    hp_ok('Wrong invoice in authoritative readback never creates a paid receipt',$forgedCapture['expected']&&$forgedStatus==='review'&&hosting_payment_projection(hp_id(33),1)['invoice_status']==='issued'&&hosting_payment_projection(hp_id(33),1)['payment_stage']==='payment_review');

    $paid=hosting_payment_run(hp_id(30),1,hp_key(40),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token',hp_gateway('approved','8800040',$approved['invoice_id'],hp_id(30)));
    $paidProjection=hosting_payment_projection(hp_id(30),1);$paidState=hp_state();
    hp_ok('Exact production readback creates one actor-zero gateway ledger receipt',$paid==='paid'&&$paidProjection['invoice_status']==='paid'&&$paidProjection['charge_state']==='paid'&&$paidProjection['payment_stage']==='paid_setup_pending'&&hp_count('gateway_receipts')===3);
    hp_ok('Exact paid retry is idempotent',hosting_payment_run(hp_id(30),1,hp_key(40),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token',hp_gateway('approved','8800040',$approved['invoice_id'],hp_id(30)))==='already_paid'&&hp_state()===$paidState);
    $invoice=ledger_invoice($approved['invoice_id']);$entry=one('SELECT * FROM '.table('invoice_payments').' WHERE invoice_id=?',[$approved['invoice_id']]);$receipt=gateway_receipt_for_entry($entry,$invoice);
    $evidence=staff_locked(fn()=>staff_transaction(fn()=>ledger_fulfillment_evidence($approved['invoice_id'],1)));
    hp_ok('Central receipt is exact fulfillment evidence',$receipt['transaction_id']==='8800040'&&$receipt['source']==='hosting_checkout'&&$evidence['payment_evidence_ready']&&$evidence['receipt_ids']===[$entry['id']]);
    $savedReceipt=one('SELECT * FROM '.table('gateway_receipts').' WHERE id=?',[$receipt['id']]);query('UPDATE '.table('gateway_receipts').' SET readback_sha256=? WHERE id=?',[str_repeat('0',64),$receipt['id']]);
    try{hp_deny('Forged gateway receipt hash fails closed',fn()=>staff_locked(fn()=>staff_transaction(fn()=>ledger_fulfillment_evidence($approved['invoice_id'],1))));}
    finally{query('UPDATE '.table('gateway_receipts').' SET readback_sha256=? WHERE id=?',[$savedReceipt['readback_sha256'],$receipt['id']]);}
    query('SET FOREIGN_KEY_CHECKS=0');try{query('DELETE FROM '.table('gateway_receipts').' WHERE id=?',[$receipt['id']]);}finally{query('SET FOREIGN_KEY_CHECKS=1');}
    try{hp_deny('Missing gateway receipt fails closed',fn()=>staff_locked(fn()=>staff_transaction(fn()=>ledger_fulfillment_evidence($approved['invoice_id'],1))));}
    finally{query('INSERT INTO '.table('gateway_receipts').' ('.implode(',',array_keys($savedReceipt)).') VALUES ('.implode(',',array_fill(0,count($savedReceipt),'?')).')',array_values($savedReceipt));}
    $audit=one('SELECT * FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['gateway_receipts.recorded','Receipt '.$receipt['id'].' %']);query('DELETE FROM '.table('audit').' WHERE id=?',[$audit['id']]);
    try{hp_deny('Missing gateway receipt witness fails closed',fn()=>staff_locked(fn()=>staff_transaction(fn()=>ledger_fulfillment_evidence($approved['invoice_id'],1))));}
    finally{query('INSERT INTO '.table('audit').' ('.implode(',',array_keys($audit)).') VALUES ('.implode(',',array_fill(0,count($audit),'?')).')',array_values($audit));}
    hp_ok('Restoring exact receipt history restores fulfillment evidence',staff_locked(fn()=>staff_transaction(fn()=>ledger_fulfillment_evidence($approved['invoice_id'],1)))===$evidence);

    $stage='gateway-rotation';hp_client();$rotationCapture=hp_expected_report(fn()=>hosting_payment_run(hp_id(34),1,hp_key(50),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token',hp_gateway('uncertain','8800050',$rotation['invoice_id'],hp_id(34))),'hosting_payment_native.php',102);$rotationStatus=$rotationCapture['value'];
    hp_ok('Production account rotation fixture retains one uncertain original-account intent',$rotationCapture['expected']&&$rotationStatus==='review'&&hosting_payment_projection(hp_id(34),1)['payment_stage']==='payment_review');
    hp_owner();payments_save('production',1,['api_login_id'=>'SYNTHETICLOGINB','transaction_key'=>'ABCDEF1234567890','signature_key'=>str_repeat('b',128),'public_client_key'=>'SYNTHETIC_PUBLIC_KEY_B'],$password);unset($GLOBALS['cwp_settings']);$rotatedState=hp_state();$rotationCalls=0;
    hp_deny('Owner reconciliation refuses an intent retained under a prior production account revision',fn()=>hosting_payment_reconcile(hp_id(34),'8800050',$password,static function()use(&$rotationCalls):array{$rotationCalls++;throw new LogicException('Rotated transport must remain closed.');}));
    hp_ok('Production rotation refusal is inert before transport',$rotationCalls===0&&hp_state()===$rotatedState);

    payments_save('sandbox',0,['api_login_id'=>'SYNTHETICSANDBOX','transaction_key'=>'0123456789ABCDEF','signature_key'=>str_repeat('c',128),'public_client_key'=>'SYNTHETIC_SANDBOX_KEY'],$password);hosting_payment_configure('sandbox',false,$password);unset($GLOBALS['cwp_settings']);hp_client();
    $sandboxCapture=hp_expected_report(fn()=>hosting_payment_run(hp_id(35),1,hp_key(60),'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-opaque-token',hp_gateway('uncertain','8800060',$sandboxRotation['invoice_id'],hp_id(35),1,2500,'sandbox')),'hosting_payment_native.php',102);$sandboxStatus=$sandboxCapture['value'];
    hp_ok('Sandbox uncertainty remains an unpaid retained sandbox intent',$sandboxCapture['expected']&&$sandboxStatus==='review'&&hosting_payment_projection(hp_id(35),1)['invoice_status']==='issued');
    hp_owner();hosting_payment_configure('production',true,$password);unset($GLOBALS['cwp_settings']);$sandboxRotatedState=hp_state();$sandboxRotationCalls=0;
    hp_deny('Owner reconciliation refuses a sandbox intent after production activation',fn()=>hosting_payment_reconcile(hp_id(35),'8800060',$password,static function()use(&$sandboxRotationCalls):array{$sandboxRotationCalls++;throw new LogicException('Cross-environment transport must remain closed.');}));
    hp_ok('Sandbox to production refusal is inert before transport',$sandboxRotationCalls===0&&hp_state()===$sandboxRotatedState);

    $stage='historical-owner-read';query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');$inactive=hosting_checkout_locked(fn():array=>hosting_payment_source_locked(hp_id(30),1,false));hp_ok('Inactive client history remains available to the owner without reopening payment authority',$inactive['request_id']===hp_id(30)&&$inactive['client_id']===1);
    hp_deny('Inactive client cannot initiate or project a new payment',fn()=>hosting_checkout_locked(fn():array=>hosting_payment_source_locked(hp_id(30),1,true)));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');

    $stage='shared-lifecycle';hp_owner();service_orders_migrate($password);hosting_order_links_prepare($password);hosting_operations_migrate($password);hosting_reconciliation_migrate($password);provider_operations_migrate($password);hosting_fulfillment_migrate($password);hosting_fulfillment_confirmation_prepare($password);
    $agreement=service_plans_validate(service_plans_row($approved['agreement_id'],true),true);$order=service_orders_offer(hp_id(70),$approved['agreement_id'],1,$agreement->revision(),$agreement->fingerprint(),$approved['invoice_id'],ledger_invoice_hash($invoice),$password);
    hp_client();$order=service_orders_decide(hp_id(70),1,0,hp_id(71),'accept',['domain_name'=>'paid.example','review_sha256'=>$order->fingerprint()],true);hp_owner();
    $commercial=recurring_billing_locked(fn()=>service_orders_assess($order,time()));
    hp_ok('Accepted checkout retains exactly one verified gateway receipt for fulfillment',$commercial['eligible']&&$commercial['basis']==='verified-payment'&&count($commercial['payment']['gateway_receipts'])===1&&$commercial['checkout_gateway_receipt']['source_id']===hp_id(30));
    $mapping=recurring_billing_locked(fn()=>provider_plan_catalog_lookup(hp_id(10)));$package=provider_plan_catalog_package($mapping['configuration'],hp_id(70));
    $link=hosting_order_links_create(hp_id(70),1,$order->revision(),$order->fingerprint(),hp_id(80),$package,$profile,1,42,$password,$mapping['id']);
    hp_ok('Shared setup binding remains verified-payment only',$link['binding']['commercial']['basis']==='verified-payment'&&$link['binding']['commercial']['manual_event_id']===null);
    $linkRow=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[hp_id(70)]);if(!$linkRow)throw new RuntimeException('Expected Shared order link missing.');
    hp_client();hp_ok('Paid Shared order remains setup pending before provider authorization',hosting_payment_projection(hp_id(30),1)['payment_stage']==='paid_setup_pending');hp_owner();
    $dispatch=hp_id(90);$review=['account_type'=>true,'defaults'=>true,'costs'=>true,'confirmation'=>'CREATE paid.example'];
    $sent=hosting_fulfillment_execute($order->data()['id'],1,$order->revision(),$order->fingerprint(),$linkRow['binding_sha256'],$dispatch,$review,$password,static fn()=>'8.8.8.8','hp_provider_perform');
    hp_ok('Verified Shared payment authorizes exactly one closed provider attempt',$sent===['state'=>'accepted_pending','attempt_started'=>true]&&HpProviderTransport::$calls===1);
    hp_client();hp_ok('Provider acknowledgement is still Paid setup pending',hosting_payment_projection(hp_id(30),1)['payment_stage']==='paid_setup_pending');hp_owner();
    $approval=one('SELECT binding_sha256 FROM '.table('hosting_fulfillments').' WHERE id=?',[$dispatch]);
    $latest=filter_var(query('SELECT MAX(started_epoch) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn(),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($latest===false)throw new RuntimeException('Provider refresh history required.');
    $readyAt=hp_refresh_ready_at($latest,time());$deadline=hrtime(true)+35000000000;
    while(time()<$readyAt){if(hrtime(true)>=$deadline)throw new RuntimeException('Bounded provider status cooldown wait expired.');usleep(100000);}
    $confirmed=hosting_fulfillment_confirm($order->data()['id'],1,$dispatch,$approval['binding_sha256'],$password,static fn()=>'8.8.8.8','hp_inventory_read');
    hp_ok('Authenticated exact package observation confirms the Shared service',$confirmed==='confirmed');
    hp_client();$active=hosting_payment_projection(hp_id(30),1);hp_ok('Shared status becomes Active only after provider confirmation',$active['payment_stage']==='active'&&$active['invoice_status']==='paid');hp_owner();
    $final=hp_state();$replay=hosting_fulfillment_execute($order->data()['id'],1,$order->revision(),$order->fingerprint(),$linkRow['binding_sha256'],$dispatch,$review,$password,static fn()=>'8.8.8.8','hp_provider_perform');
    hp_ok('Fulfillment replay never duplicates provider service',$replay===['state'=>'replayed','attempt_started'=>false]&&HpProviderTransport::$calls===1&&hosting_fulfillment_confirm($order->data()['id'],1,$dispatch,$approval['binding_sha256'],$password,static fn()=>'8.8.8.8','hp_inventory_read')==='already_confirmed'&&hp_state()===$final);

    $stage='complete';$report=['passed'=>true,'checks'=>$checks,'scope'=>'Isolated Linux SQL ordinary Shared hosting payment with synthetic local Authorize.Net and provider callbacks; no live charge, provisioning, customer message or deployment. VPS remains unavailable by the separate storefront contract.','runtime'=>['php'=>PHP_VERSION,'database'=>(string)query('SELECT VERSION()')->fetchColumn()],'prefix'=>'hpay_','live_gateway_calls'=>0,'live_provider_calls'=>0];
}catch(Throwable $error){$report=['passed'=>false,'checks'=>$checks,'scope'=>'Native hosting payment fixture stopped; no acceptance beyond named outcomes.','stage'=>$stage,'error'=>['class'=>get_class($error),'file'=>basename($error->getFile()),'line'=>$error->getLine(),'sqlstate'=>$error instanceof PDOException?($error->errorInfo[0]??null):null,'driver_code'=>$error instanceof PDOException?($error->errorInfo[1]??null):null]];}
$bytes=json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
if($reportReady&&is_dir($qa)&&!is_link($qa)){$reportFile=fopen($reportPath,'x');if(!$reportFile)exit(2);try{fwrite($reportFile,$bytes);}finally{fclose($reportFile);}}
echo $bytes;if(!$report['passed'])exit(1);
