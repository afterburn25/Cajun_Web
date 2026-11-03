<?php
/** Real InnoDB/mutex/transaction test; synthetic owner and gateway boundaries, no provider contact. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$root=dirname(__DIR__);
$port=(int)getenv('CWP_TEST_PORT');
if(getenv('CWP_TEST_HOST')!=='127.0.0.1'||$port<1||$port>65535)throw new RuntimeException('Use an explicit isolated loopback test database.');
$user=(string)getenv('CWP_TEST_USER');$password=(string)getenv('CWP_TEST_PASSWORD');
if($user===''||$password==='')throw new RuntimeException('Explicit disposable test credentials are required.');
$prefix=(string)($argv[2]??'');
if(!preg_match('/^fq_[a-z0-9_]{3,14}_$/D',$prefix))throw new RuntimeException('Use a fresh native-test prefix.');
$pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function db():PDO{global $pdo;return $pdo;}
function config():array{global $prefix;return ['db'=>['name'=>'cwp_test','prefix'=>$prefix]];}
function table(string $name):string{global $prefix;if(!preg_match('/^[a-z_]+$/D',$name))throw new LogicException('Unexpected table');return '`'.$prefix.$name.'`';}
function query(string $sql,array $params=[]):PDOStatement{$s=db()->prepare($sql);$s->execute($params);return $s;}
function one(string $sql,array $params=[]):?array{
    if(str_contains($sql,'FOR UPDATE')){
        $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
        if(!db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn()!==(int)query('SELECT CONNECTION_ID()')->fetchColumn())throw new LogicException('Row lock attempted without actual account mutex and transaction');
    }
    return query($sql,$params)->fetch()?:null;
}
function extracted(string $path,array $names):array{
    $source=file_get_contents($path);$bodies=[];$hashes=[];
    foreach($names as $name){
        if(!preg_match('/^function '.preg_quote($name,'/').'\(.*?(?=^function |\z)/ms',$source,$m))throw new RuntimeException('Missing source function '.$name);
        // Stop before the next function; intervening require statements are not part of these selected boundaries.
        $body=rtrim($m[0]);$bodies[]=$body;$hashes[$name]=hash('sha256',$body);
    }
    $module=tempnam(sys_get_temp_dir(),'cwp-native-finance-');
    try{file_put_contents($module,"<?php\ndeclare(strict_types=1);\n".implode("\n",$bodies));require $module;}finally{unlink($module);}
    return $hashes;
}
$staffHashes=extracted($root.'/site/app/staff/accounts.php',['staff_locked','staff_transaction']);
if(($argv[1]??'')==='--writer'){
    $offer=(string)$argv[3];$changes=json_decode(base64_decode($argv[4],true),true,16,JSON_THROW_ON_ERROR);
    staff_locked(fn()=>staff_transaction(function()use($offer,$changes):void{
        $sets=[];$values=[];
        foreach($changes as $key=>$value){
            if(!in_array($key,['status','gateway_subscription_id','customer_profile_id','payment_profile_id','period_offset','replacement_count'],true))throw new LogicException('Unexpected competing field');
            $sets[]=$key.'=?';$values[]=$value;
        }
        $values[]=$offer;query('UPDATE '.table('license_finance_subscriptions').' SET '.implode(',',$sets).' WHERE offer_id=?',$values);
    }));
    echo json_encode(['connection_id'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn(),'committed'=>!db()->inTransaction()],JSON_THROW_ON_ERROR);exit;
}
$sourcePath=(string)$argv[1];
$sourceHashes=extracted($sourcePath,['license_finance_subscription_row','license_finance_subscription_reconcile']);
$auditHashes=extracted($root.'/site/app/licenses/service.php',['license_audit']);
define('CWP_BOOT',true);
require $root.'/site/database/schema/license_finance_gateway_schema.php';
// Minimal referenced parent fixture; the subscription DDL, indexes and FK are canonical and unmodified.
query('CREATE TABLE '.table('license_finance_offers').' (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
query(license_finance_gateway_schema($prefix)['license_finance_subscriptions']);
$baseSchema=file_get_contents($root.'/site/database/schema/base.php');
if(!preg_match("/^        'audit'=>(.*),$/m",$baseSchema,$auditExpression))throw new RuntimeException('Missing canonical audit DDL');
$t=fn($name)=>table($name);$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$auditSql=eval('return '.$auditExpression[1].';');query($auditSql);
$auditOffers=array_map(fn($name)=>substr(hash('sha256',$prefix.'|'.$name),0,32),['audit_rollback_nonactive','audit_rollback_active']);
query('CREATE TRIGGER `'.$prefix.'reject_audit` BEFORE INSERT ON '.table('audit').
    " FOR EACH ROW BEGIN IF NEW.detail LIKE '%".$auditOffers[0]."%' OR NEW.detail LIKE '%".$auditOffers[1].
    "%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic native audit failure'; END IF; END");
$fixture=[];
function support_current():bool{return false;}
function license_finance_gateway_ready():bool{return true;}
// This native fixture owns subscription/audit tables, not a paid financing
// ledger. Keep its stale-read and rollback cases on the unpaid path; the full
// payoff suites exercise actual ledger validation and permanent payoff.
function license_finance_subscription_payoff_due(array $offer):bool{return false;}
function license_finance_offer_id(string $id):void{if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new LogicException('Bad fixture offer');}
function staff_owner(string $password):array{if($password!=='synthetic-owner')throw new LogicException('Unexpected owner credential');return ['id'=>1];}
function license_finance_offer_row(string $id):array{return ['id'=>$id,'status'=>'signed','installments'=>6,'license_id'=>7];}
function license_finance_anet_settings():array{global $fixture;return ['mode'=>$fixture['mode']];}
function license_finance_anet_subscription_readback(mixed ...$args):array{global $fixture;return ['status'=>$fixture['remote']];}
function license_finance_anet_execute(string $method,array $request):array{
    global $fixture,$prefix;
    if($method!=='ARBGetSubscriptionRequest'||$request!==['subscriptionId'=>'810001','includeTransactions'=>false]||db()->inTransaction())throw new LogicException('Unexpected remote read boundary');
    $fixture['reads']++;
    if($fixture['changes']){
        $command=[PHP_BINARY,__FILE__,'--writer',$prefix,$fixture['offer'],base64_encode(json_encode($fixture['changes'],JSON_THROW_ON_ERROR))];
        $child=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($child))throw new RuntimeException('Could not start competing SQL writer');
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        $exit=proc_close($child);if($exit!==0||$err!=='')throw new RuntimeException('Competing writer failed: '.$err);
        $fixture['writer']=json_decode($out,true,8,JSON_THROW_ON_ERROR);
    }
    $fixture['before_write']=license_finance_subscription_row($fixture['offer']);
    return ['subscription'=>['profile'=>['customerProfileId'=>'910001','customerPaymentProfileId'=>'910002']]];
}
$cases=[];
foreach(['suspended','expired','canceled'] as $remote){
    $cases[$remote.'_paid_off']=['local'=>'billing_suspended','remote'=>$remote,'changes'=>['status'=>'paid_off']];
    $cases[$remote.'_replacement']=['local'=>'review','remote'=>$remote,'changes'=>['gateway_subscription_id'=>'810002','status'=>'pending_first_payment','customer_profile_id'=>'920001','payment_profile_id'=>'920002','period_offset'=>3,'replacement_count'=>1]];
    $cases[$remote.'_unchanged']=['local'=>'billing_suspended','remote'=>$remote,'changes'=>[]];
}
$cases['active_replacement']=['local'=>'replacing','remote'=>'active','changes'=>['gateway_subscription_id'=>'810002','status'=>'replacing','replacement_count'=>1]];
$cases['active_paid_off']=['local'=>'replacing','remote'=>'active','changes'=>['status'=>'paid_off']];
$cases['active_unchanged']=['local'=>'replacing','remote'=>'active','changes'=>[]];
$cases['sandbox_readonly']=['local'=>'billing_suspended','remote'=>'suspended','changes'=>[],'mode'=>'sandbox'];
$cases['audit_rollback_nonactive']=['local'=>'billing_suspended','remote'=>'suspended','changes'=>[],'audit_failure'=>true];
$cases['audit_rollback_active']=['local'=>'replacing','remote'=>'active','changes'=>[],'audit_failure'=>true];
$checks=[];$results=[];$reader=(int)query('SELECT CONNECTION_ID()')->fetchColumn();
$lockKey='cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40);
foreach($cases as $name=>$case){
    // Separate retained namespaces avoid overwriting any earlier fixture row or unique gateway ID.
    // Earlier gateway IDs are retired only within these synthetic per-case rows after their proof is captured.
    $offer=substr(hash('sha256',$prefix.'|'.$name),0,32);
    query('INSERT INTO '.table('license_finance_offers').' (id) VALUES (?)',[$offer]);
    query('INSERT INTO '.table('license_finance_subscriptions').' (offer_id,gateway_subscription_id,customer_profile_id,payment_profile_id,interval_months,amount_cents,total_occurrences,period_offset,replacement_count,start_date,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$offer,'810001','900001','900002',1,2500,6,0,0,'2099-01-01',$case['local'],'2098-01-01 00:00:00','2098-01-01 00:00:00']);
    $fixture=['offer'=>$offer,'changes'=>$case['changes'],'remote'=>$case['remote'],'mode'=>$case['mode']??'production','audit_failure'=>$case['audit_failure']??false,'reads'=>0,'writer'=>null];
    $error=null;$result=null;
    try{$result=license_finance_subscription_reconcile($offer,'810001','synthetic-owner');}catch(RuntimeException $e){$error=$e->getMessage();}
    $after=license_finance_subscription_row($offer);$stale=$case['changes']!==[];$sandbox=$fixture['mode']==='sandbox';$rollback=$fixture['audit_failure'];
    $expected=$sandbox?'sandbox_verified':match($case['remote']){'active'=>'review','suspended'=>'billing_suspended',default=>'cancelled'};
    $expectedError=$stale?'Financing subscription changed during gateway verification. Retry reconciliation.':($rollback?'Synthetic native audit failure':null);
    $checks[]=['name'=>$name.' expected reconciliation outcome','passed'=>$rollback?str_contains($error??'','Synthetic native audit failure'):($error===$expectedError&&($expectedError!==null||$result===$expected))];
    $checks[]=['name'=>$name.' native row preserves concurrent or rolled-back state','passed'=>($stale||$sandbox||$rollback)?$after===$fixture['before_write']:($after['status']===$expected&&$after['gateway_subscription_id']==='810001')];
    $checks[]=['name'=>$name.' native mutex and transaction released','passed'=>!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',[$lockKey])->fetchColumn()===1];
    $checks[]=['name'=>$name.' competing writer uses a separate committed SQL connection','passed'=>!$stale||($fixture['writer']['committed']&&$fixture['writer']['connection_id']!==$reader)];
    $checks[]=['name'=>$name.' expected audit count','passed'=>(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE detail LIKE ?',['%'.$offer.'%'])->fetchColumn()===($stale||$rollback?0:1)];
    $results[]=['case'=>$name,'result'=>$result,'error'=>$error,'row'=>$after,'writer'=>$fixture['writer']];
    // Preserve this completed case with a unique numeric gateway identity, freeing only the synthetic next-case inputs.
    query('UPDATE '.table('license_finance_subscriptions').' SET gateway_subscription_id=? WHERE offer_id=?',[(string)(820000+count($results)),$offer]);
}
$passed=!in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'checks'=>$checks,'count'=>count($checks),'failed'=>count(array_filter($checks,fn($c)=>!$c['passed'])),
    'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),'prefix'=>$prefix,'source_sha256'=>hash_file('sha256',$sourcePath),
    'function_sha256'=>$sourceHashes,'staff_function_sha256'=>$staffHashes,'audit_function_sha256'=>$auditHashes,'audit_schema_sha256'=>hash('sha256',$auditSql),'subscription_schema_sha256'=>hash('sha256',license_finance_gateway_schema($prefix)['license_finance_subscriptions']),
    'results'=>$results,'scope'=>'Actual subscription DDL/FK/indexes, InnoDB rows, account mutex, transactions and separate-process competing SQL commits. Synthetic parent/owner/gateway boundaries. No actual provider, installed HTTP or whole-license-access claim.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
