<?php
/** Verbatim owner reconciliation with deterministic concurrent state changes; no provider/SQL contact. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$source=file_get_contents(__DIR__.'/../site/app/billing/finance_subscription.php');
if(!preg_match('/^function license_finance_subscription_reconcile\(.*?(?=^function |\z)/ms',$source,$match))throw new RuntimeException('Missing reconciliation function');
$body=rtrim($match[0]);$module=tempnam(sys_get_temp_dir(),'cwp-reconcile-');
try{file_put_contents($module,"<?php\ndeclare(strict_types=1);\n".$body);require $module;}finally{unlink($module);}
$fixture=[];
function support_current():bool{return false;}
function license_finance_gateway_ready():bool{return true;}
function license_finance_offer_id(string $id):void{if($id!==str_repeat('a',32))throw new LogicException('Unexpected offer');}
function staff_owner(string $password):array{if($password!=='synthetic-password')throw new LogicException('Unexpected password');return ['id'=>1];}
function license_finance_offer_row(string $id):array{return ['status'=>'signed','installments'=>6,'license_id'=>7];}
function license_finance_subscription_row(string $id,bool $lock=false):array{global $fixture;if($lock&&!$fixture['locked'])throw new LogicException('Unlocked row read');return $fixture['row'];}
function license_finance_anet_execute(string $method,array $request):array{global $fixture;if($method!=='ARBGetSubscriptionRequest'||$request!==['subscriptionId'=>'810001','includeTransactions'=>false]||$fixture['locked'])throw new LogicException('Unexpected gateway read');$fixture['reads']++;$fixture['row']=array_replace($fixture['row'],$fixture['concurrent']);$fixture['before_write']=$fixture['row'];return ['subscription'=>['profile'=>['customerProfileId'=>'910001','customerPaymentProfileId'=>'910002']]];}
function license_finance_anet_subscription_readback(mixed ...$args):array{global $fixture;return ['status'=>$fixture['remote_status']];}
function license_finance_anet_settings():array{global $fixture;return ['mode'=>$fixture['mode']];}
function staff_locked(callable $work):mixed{global $fixture;if($fixture['locked'])throw new LogicException('Nested lock');$fixture['locked']=true;try{return $work();}finally{$fixture['locked']=false;}}
function staff_transaction(callable $work):mixed{return $work();}
function table(string $name):string{return 'probe_'.$name;}
function query(string $sql,array $params):void{
 global $fixture;if(!$fixture['locked'])throw new LogicException('Unlocked write');
 if($sql==='UPDATE probe_license_finance_subscriptions SET gateway_subscription_id=?,customer_profile_id=?,payment_profile_id=?,status=?,updated_at=? WHERE id=?'&&$params[5]===7){
  foreach(['gateway_subscription_id','customer_profile_id','payment_profile_id','status','updated_at'] as $i=>$key)$fixture['row'][$key]=$params[$i];
 }elseif($sql==='UPDATE probe_license_finance_subscriptions SET status=?,updated_at=? WHERE id=?'&&$params[2]===7){$fixture['row']['status']=$params[0];$fixture['row']['updated_at']=$params[1];}
 else throw new LogicException('Unexpected SQL');
 $fixture['writes']++;
}
function license_audit(mixed ...$args):void{}
$cases=[];
foreach(['suspended','expired','canceled'] as $remote){
 foreach(['active','pending_first_payment','paid_off','cancelled','reversal_recovery','replacing'] as $state)$cases[$remote.'_concurrent_'.$state]=['local'=>'billing_suspended','remote'=>$remote,'concurrent'=>['status'=>$state]];
 $cases[$remote.'_replaced_identity']=['local'=>'review','remote'=>$remote,'concurrent'=>['gateway_subscription_id'=>'810002','status'=>'pending_first_payment','customer_profile_id'=>'920001','payment_profile_id'=>'920002','period_offset'=>3,'replacement_count'=>1]];
 $cases[$remote.'_unchanged']=['local'=>'billing_suspended','remote'=>$remote,'concurrent'=>[]];
}
$cases['active_new_replacement']=['local'=>'replacing','remote'=>'active','concurrent'=>['gateway_subscription_id'=>'810002','status'=>'replacing','replacement_count'=>1]];
$cases['active_paid_off']=['local'=>'replacing','remote'=>'active','concurrent'=>['status'=>'paid_off']];
$cases['active_unchanged_replacing']=['local'=>'replacing','remote'=>'active','concurrent'=>[]];
$cases['sandbox_read_only']=['local'=>'billing_suspended','remote'=>'suspended','concurrent'=>[],'mode'=>'sandbox'];
$checks=[];$results=[];
foreach($cases as $name=>$case){
 $fixture=['row'=>['id'=>7,'gateway_subscription_id'=>'810001','status'=>$case['local'],'interval_months'=>1,'amount_cents'=>2500,'total_occurrences'=>6,'period_offset'=>0,'replacement_count'=>0,'start_date'=>'2099-01-01','customer_profile_id'=>'900001','payment_profile_id'=>'900002','updated_at'=>'2098-01-01 00:00:00'],'concurrent'=>$case['concurrent'],'remote_status'=>$case['remote'],'mode'=>$case['mode']??'production','locked'=>false,'writes'=>0,'reads'=>0];
 $exception=null;$result=null;
 try{$result=license_finance_subscription_reconcile(str_repeat('a',32),'810001','synthetic-password');}catch(RuntimeException $error){$exception=$error->getMessage();}
 $stale=$case['concurrent']!==[];$sandbox=$fixture['mode']==='sandbox';$expected=$sandbox?'sandbox_verified':match($case['remote']){'active'=>'review','suspended'=>'billing_suspended',default=>'cancelled'};
 $checks[]=['name'=>$name.' rejects a stale response or returns the expected current result','passed'=>$stale?($exception==='Financing subscription changed during gateway verification. Retry reconciliation.') :($exception===null&&$result===$expected)];
 $checks[]=['name'=>$name.' permits writes only against the verified snapshot','passed'=>$fixture['writes']===($stale||$sandbox?0:1)];
 $checks[]=['name'=>$name.' retains newer state and identity','passed'=>$stale||$sandbox?$fixture['row']===$fixture['before_write']:($fixture['row']['status']===$expected&&$fixture['row']['gateway_subscription_id']==='810001')];
 $checks[]=['name'=>$name.' performs one readback and releases its lock','passed'=>$fixture['reads']===1&&!$fixture['locked']];
 $results[]=['case'=>$name,'result'=>$result,'exception'=>$exception,'writes'=>$fixture['writes'],'final'=>$fixture['row']];
}
$passed=!in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'count'=>count($checks),'checks'=>$checks,'php'=>PHP_VERSION,'source_sha256'=>hash('sha256',$source),'function_sha256'=>hash('sha256',$body),'results'=>$results,'scope'=>'Exact owner orchestration with strict synthetic database and gateway dependencies; deterministic concurrency injection at the readback boundary. No native SQL, real gateway, HTTP or license-access claim.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
