<?php
/** Exact orchestration functions with strict synthetic dependencies; no SQL or gateway contact. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$source=file_get_contents(__DIR__.'/../site/app/billing/finance_subscription.php');if(!$source)exit(2);
$functions=['license_finance_subscription_replacement_reserve','license_finance_subscription_replacement_review','license_finance_subscription_replacement_declined','license_finance_subscription_reenroll_opaque'];
$hashes=[];$bodies=[];
foreach($functions as $name){
    if(!preg_match('/^function '.preg_quote($name,'/').'\(.*?(?=^function |\z)/ms',$source,$match))throw new RuntimeException('Function boundary missing: '.$name);
    $body=rtrim($match[0]);$hashes[$name]=hash('sha256',$body);$bodies[]=$body;
}
// Copy only verbatim function declarations into an isolated temporary module.
// Their includes are deliberately omitted: strict dependency doubles below
// make provider traffic impossible and reject unexpected state mutations.
$module=tempnam(sys_get_temp_dir(),'cwp-finance-outcome-');
if($module===false)throw new RuntimeException('Temporary module unavailable.');
try{
    file_put_contents($module,"<?php\ndeclare(strict_types=1);\n".implode("\n",$bodies));
    require $module;
}finally{unlink($module);}
$fixture=[];
function support_current():bool{return false;}
function license_finance_gateway_ready():bool{return true;}
function software_anet_nonce(string $descriptor,string $nonce):void{if($descriptor!=='COMMON.ACCEPT.INAPP.PAYMENT'||$nonce!=='synthetic-one-use')throw new LogicException('Unexpected synthetic nonce.');}
function license_finance_offer_row(string $id,int $client):array{if($id!==str_repeat('a',32)||$client!==1)throw new LogicException('Wrong owner');return ['status'=>'signed','license_id'=>7];}
function license_financing_sync(int $id):array{return ['state'=>'active'];}
function license_finance_subscription_replacement_contract(array $offer):array{return ['amount_cents'=>2500,'start_date'=>'2099-01-01','months'=>1,'occurrences'=>3,'period_offset'=>1];}
function staff_locked(callable $work):mixed{return $work();}
function staff_transaction(callable $work):mixed{return $work();}
function table(string $name):string{return 'probe_'.$name;}
function license_finance_subscription_row(string $id,bool $lock=false):array{global $fixture;return ['id'=>7,'gateway_subscription_id'=>$fixture['gateway_id'],'status'=>$fixture['status']];}
function query(string $sql,array $params):void{
    global $fixture;
    if($sql!=='UPDATE probe_license_finance_subscriptions SET status=?,updated_at=? WHERE id=?'||$params[2]!==7)throw new LogicException('Unexpected SQL mutation');
    $fixture['status']=$params[0];$fixture['states'][]=$params[0];
}
function license_finance_anet_retire_subscription(string $id,array $old):string{global $fixture;$fixture['retire_calls']++;return 'canceled';}
function license_finance_anet_arb_opaque_request(mixed ...$args):array{return ['synthetic'=>true];}
function license_finance_anet_execute(string $method,array $request):array{
    global $fixture;
    if($method==='ARBCreateSubscriptionRequest'){$fixture['creates']++;if($fixture['reply'] instanceof Throwable)throw $fixture['reply'];return $fixture['reply'];}
    if($method==='ARBGetSubscriptionRequest')return ['subscription'=>[]];
    throw new LogicException('Unexpected synthetic gateway method');
}
function license_finance_anet_subscription_readback(mixed ...$args):array{return ['status'=>'active'];}
function license_finance_anet_settings():array{return ['mode'=>'sandbox'];}
function license_finance_subscription_replacement_finalize(string $offer,string $old,string $new,array $contract,array $profiles,string $state,string $retired):string{global $fixture;$fixture['gateway_id']=$new;$fixture['status']=$state;return $state;}
function license_audit(mixed ...$args):void{}
function report_error(Throwable $error):void{global $fixture;$fixture['errors'][]=get_class($error);}
$cases=[
    'success_missing_id'=>['messages'=>['resultCode'=>'Ok']],
    'success_invalid_id'=>['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>'unknown'],
    'success_nonscalar_id'=>['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>['810002']],
    'empty_response'=>[],
    'unknown_result'=>['messages'=>['resultCode'=>'Pending']],
    'error_response'=>['messages'=>['resultCode'=>'Error','message'=>[['code'=>'E00001','text'=>'Synthetic unknown error']]]],
    'transport_exception'=>new RuntimeException('Synthetic uncertain transport'),
    'valid_id'=>['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>'810002'],
    'valid_integer_id'=>['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>810002],
    'oversized_id'=>['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>str_repeat('8',26)],
    'float_id'=>['messages'=>['resultCode'=>'Ok'],'subscriptionId'=>810002.5],
    'error_with_candidate'=>['messages'=>['resultCode'=>'Error'],'subscriptionId'=>'810002'],
    'duplicate_error'=>['messages'=>['resultCode'=>'Error','message'=>[['code'=>'E00012']]]],
    'empty_errors'=>['messages'=>['resultCode'=>'Error','message'=>[]]],
    'mixed_errors'=>['messages'=>['resultCode'=>'Error','message'=>[['code'=>'E00014'],['code'=>'E00001']]]],
    'nonscalar_error'=>['messages'=>['resultCode'=>'Error','message'=>['E00014']]],
    'validation_with_candidate'=>['messages'=>['resultCode'=>'Error','message'=>[['code'=>'E00014']]],'subscriptionId'=>'810002'],
];
foreach(['E00003','E00013','E00014','E00015','E00016','E00017','E00018','E00019'] as $code){
    $cases['validation_'.$code]=['messages'=>['resultCode'=>'Error','message'=>[['code'=>$code]]]];
}
$results=[];$checks=[];
foreach($cases as $name=>$reply){
    $fixture=['status'=>'billing_suspended','gateway_id'=>'810001','creates'=>0,'retire_calls'=>0,'states'=>[],'errors'=>[],'reply'=>$reply];
    $first=license_finance_subscription_reenroll_opaque(str_repeat('a',32),1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-one-use');
    $firstState=$fixture['status'];$before=$fixture['creates'];$secondDenied=false;
    try{license_finance_subscription_reenroll_opaque(str_repeat('a',32),1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-one-use');}
    catch(RuntimeException $error){$secondDenied=true;}
    $valid=in_array($name,['valid_id','valid_integer_id'],true);
    $rejected=str_starts_with($name,'validation_E');
    $expected=$valid?'sandbox_test':($rejected?'billing_suspended':'review');
    $checks[]=['name'=>$name.' records a safe first result','passed'=>$first===$expected];
    $checks[]=['name'=>$name.' retains the correct durable state','passed'=>$firstState===$expected];
    $checks[]=['name'=>$name.' gates another create by certainty of prior rejection','passed'=>$before===1&&($rejected?(!$secondDenied&&$fixture['creates']===2):($secondDenied&&$fixture['creates']===1))];
    $checks[]=['name'=>$name.' preserves the authoritative subscription identity','passed'=>$fixture['gateway_id']===($valid?'810002':'810001')];
    $results[]=['case'=>$name,'first_result'=>$first,'first_state'=>$firstState,'second_denied'=>$secondDenied,'second_attempt_created'=>$fixture['creates']>$before,'create_calls'=>$fixture['creates'],'gateway_id'=>$fixture['gateway_id']];
}
$passed=!in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'count'=>count($checks),'checks'=>$checks,'php'=>PHP_VERSION,'source_sha256'=>hash('sha256',$source),'function_sha256'=>$hashes,'results'=>$results,'scope'=>'Exact orchestration function bodies extracted without edits and executed with synthetic gateway/state doubles. No real PDO, HTTP, credentials or provider operations. Verifies uncertainty fences and positive finalization, not actual gateway transactions or native database persistence.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
