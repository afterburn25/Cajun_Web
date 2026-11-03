<?php
/** Actual checkout/reconcile orchestration with isolated provider and persistence doubles. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$sourcePath=$argv[1]??dirname(__DIR__).'/site/app/billing/finance_charge.php';
$source=file_get_contents($sourcePath);$bodies=[];
foreach(['license_finance_charge_run','license_finance_charge_reconcile'] as $name){
    if(!preg_match('/^function '.preg_quote($name,'/').'\(.*?(?=^function |\z)/ms',$source,$m))throw new RuntimeException('Missing '.$name);
    $bodies[]=rtrim($m[0]);
}
$module=tempnam(sys_get_temp_dir(),'cwp-attempt-bindings-');
try{file_put_contents($module,"<?php\ndeclare(strict_types=1);\n".implode("\n",$bodies));require $module;}finally{unlink($module);}
$case=[];$calls=[];
function software_anet_nonce(string $descriptor,string $nonce):void{}
function license_finance_anet_settings():array{global $case;return ['mode'=>$case['mode']??'production'];}
function license_finance_charge_prepare(array $request,string $key):array{global $case;return ['invoice'=>91,'amount_cents'=>1250,'offer'=>['id'=>'offer','client_id'=>4],'create_profile'=>$case['create_profile']??false];}
function table(string $name):string{return $name;}
function one(string $sql,array $params):?array{global $case;return str_contains($sql,'studio_clients')?['email'=>'synthetic@example.invalid']:$case['charge'];}
function license_finance_anet_charge_request(mixed ...$args):array{return [];}
function license_finance_anet_execute(string $operation,array $payload):array{
    global $case;
    if(($case['throw']??'')===$operation)throw new RuntimeException('Synthetic transport uncertainty');
    return $operation==='createTransactionRequest'?['transactionResponse'=>['responseCode'=>$case['response']??'1']]:['synthetic_verified_details'=>true];
}
function software_anet_approved(array $reply):?string{return $reply['transactionResponse']['responseCode']==='1'?'880001':null;}
function software_anet_one_time_charge_confirmed(array $details,string $tx,int $invoice,int $amount):bool{global $case;return $case['confirmed']??true;}
function license_finance_charge_mark(int $invoice,string $state,?string $tx,string $attemptKey):bool{global $calls,$case;$calls[]=['method'=>'mark','invoice'=>$invoice,'state'=>$state,'tx'=>$tx,'key'=>$attemptKey];return $case['mark_applied']??true;}
function license_finance_charge_record_paid(int $invoice,string $tx,int $amount,string $attemptKey,?array $profiles=null,?string $ownerPassword=null):void{global $calls;$calls[]=['method'=>'paid','invoice'=>$invoice,'tx'=>$tx,'amount'=>$amount,'key'=>$attemptKey,'password'=>$ownerPassword];}
function report_error(Throwable $e):void{}
function support_current():bool{return false;}
function license_finance_gateway_ready():bool{return true;}
function ledger_ready():bool{return true;}
function staff_owner(string $password):array{if($password!=='synthetic-owner')throw new RuntimeException('Owner required');return ['id'=>1];}
function license_finance_offer_row(string $id):array{return ['activation_invoice_id'=>null];}
function license_finance_anet_profile_ids(array $reply,array $details):?array{global $case;return ($case['create_profile']??false)?['customer_profile_id'=>'1001','payment_profile_id'=>'2001']:null;}
function license_finance_subscription_create_profile(string $offer,array $profiles):string{global $calls;$calls[]=['method'=>'subscription','offer'=>$offer,'profiles'=>$profiles];return 'sandbox';}
function license_audit(int $actor,string $kind,string $message):void{}
$key=hash('sha256','original-checkout-attempt');$checks=[];
$scenarios=[
    'declined'=>['response'=>'2','result'=>'declined','state'=>'declined','tx'=>null],
    'unapproved'=>['response'=>'3','result'=>'review','state'=>'review','tx'=>null],
    'unconfirmed'=>['confirmed'=>false,'result'=>'review','state'=>'uncertain','tx'=>'880001'],
    'sandbox'=>['mode'=>'sandbox','result'=>'sandbox','state'=>'sandbox_test','tx'=>'880001'],
    'production'=>['result'=>'paid','method'=>'paid','tx'=>'880001'],
    'transport_before_id'=>['throw'=>'createTransactionRequest','result'=>'review','state'=>'uncertain','tx'=>null],
    'transport_after_id'=>['throw'=>'getTransactionDetailsRequest','result'=>'review','state'=>'uncertain','tx'=>'880001'],
];
foreach($scenarios as $name=>$case){
    $calls=[];$error=null;$result=null;
    try{$result=license_finance_charge_run([],$key,'synthetic','synthetic',[]);}catch(Throwable $e){$error=$e->getMessage();}
    $checks[]=['name'=>$name.' expected orchestration outcome','passed'=>$error===null&&$result===$case['result']];
    $checks[]=['name'=>$name.' binds exactly one mutation to original attempt','passed'=>count($calls)===1&&$calls[0]['key']===$key&&$calls[0]['invoice']===91&&$calls[0]['method']===($case['method']??'mark')&&$calls[0]['tx']===$case['tx']&&(!isset($case['state'])||$calls[0]['state']===$case['state'])];
}
foreach(['processing','uncertain','review'] as $state){
    $case=['charge'=>['status'=>$state,'request_key'=>$key,'transaction_id'=>null,'amount_cents'=>1250,'offer_id'=>'offer']];
    $calls=[];$error=null;$result=null;
    try{$result=license_finance_charge_reconcile(91,'880001','synthetic-owner');}catch(Throwable $e){$error=$e->getMessage();}
    $checks[]=['name'=>$state.' owner reconciliation completes','passed'=>$error===null&&$result==='paid'];
    $checks[]=['name'=>$state.' passes original attempt and commit-time owner credential','passed'=>count($calls)===1&&$calls[0]['method']==='paid'&&$calls[0]['key']===$key&&$calls[0]['password']==='synthetic-owner'&&$calls[0]['tx']==='880001'];
}

foreach([true,false] as $applied){
    $case=['mode'=>'sandbox','create_profile'=>true,'mark_applied'=>$applied];$calls=[];
    $result=license_finance_charge_run([],$key,'synthetic','synthetic',[]);
    $checks[]=['name'=>'Sandbox continuation reflects mark admission '.(int)$applied,'passed'=>$result===($applied?'sandbox':'review')];
    $creates=array_values(array_filter($calls,fn($c)=>$c['method']==='subscription'));
    $checks[]=['name'=>'Sandbox subscription boundary follows mark admission '.(int)$applied,'passed'=>count($creates)===($applied?1:0)];
    $checks[]=['name'=>'Sandbox mark keeps original attempt '.(int)$applied,'passed'=>$calls[0]['method']==='mark'&&$calls[0]['key']===$key&&$calls[0]['state']==='sandbox_test'];
}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'php'=>PHP_VERSION,'source_sha256'=>hash_file('sha256',$sourcePath),'scope'=>'Actual checkout and reconciliation functions; synthetic provider, owner and mutation doubles. No gateway, SQL, payment or license-access claim.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
