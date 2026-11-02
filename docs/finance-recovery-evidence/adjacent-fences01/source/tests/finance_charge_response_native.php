<?php
/** Native delayed-result attempt fences; synthetic ledger/owner boundary, no network provider. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$root=dirname(__DIR__);$port=(int)getenv('CWP_TEST_PORT');
if(getenv('CWP_TEST_HOST')!=='127.0.0.1'||$port<1||$port>65535)throw new RuntimeException('Use an explicit isolated loopback database.');
$prefix=(string)($argv[2]??'');
if(!preg_match('/^fc_[a-z0-9_]{3,14}_$/D',$prefix))throw new RuntimeException('Use a fresh native-test prefix.');
$user=(string)getenv('CWP_TEST_USER');$password=(string)getenv('CWP_TEST_PASSWORD');
if($user===''||$password==='')throw new RuntimeException('Explicit disposable test credentials required.');
$pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function db():PDO{global $pdo;return $pdo;}
function config():array{global $prefix;return ['db'=>['name'=>'cwp_test','prefix'=>$prefix]];}
function table(string $name):string{global $prefix;if(!preg_match('/^[a-z_]+$/D',$name))throw new LogicException('Unexpected table');return '`'.$prefix.$name.'`';}
function query(string $sql,array $params=[]):PDOStatement{$s=db()->prepare($sql);$s->execute($params);return $s;}
function one(string $sql,array $params=[]):?array{
    global $prefix;
    if(str_contains($sql,'studio_clients'))return ['email'=>'synthetic@example.invalid'];
    if(str_contains($sql,'FOR UPDATE')){
        $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40);
        if(!db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn()!==(int)query('SELECT CONNECTION_ID()')->fetchColumn())throw new LogicException('Row lock requires actual mutex and transaction');
    }
    return query($sql,$params)->fetch()?:null;
}
function extracted(string $path,array $names):array{
    $source=file_get_contents($path);$bodies=[];$hashes=[];
    foreach($names as $name){
        if(!preg_match('/^function '.preg_quote($name,'/').'\(.*?(?=^function |\z)/ms',$source,$m))throw new RuntimeException('Missing function '.$name);
        $body=rtrim($m[0]);$bodies[]=$body;$hashes[$name]=hash('sha256',$body);
    }
    $module=tempnam(sys_get_temp_dir(),'cwp-native-capture-');
    try{file_put_contents($module,"<?php\ndeclare(strict_types=1);\n".implode("\n",$bodies));require $module;}finally{unlink($module);}
    return $hashes;
}
$staffHashes=extracted($root.'/site/app/staff/accounts.php',['staff_locked','staff_transaction']);
define('CWP_BOOT',true);
require $root.'/site/app/billing/authorize_net_contract.php';
require $root.'/site/database/schema/license_finance_gateway_schema.php';
$sourcePath=(string)$argv[1];
$sourceHashes=extracted($sourcePath,['license_finance_charge_mark','license_finance_charge_record_paid','license_finance_charge_run']);
function license_finance_gateway_ready():bool{return true;}
function ledger_ready():bool{return true;}
// Fresh namespace only. Canonical charge/attempt DDL, indexes and FKs are unmodified.
foreach(['payment_requests','studio_invoices'] as $parent)query('CREATE TABLE '.table($parent).' (id INT UNSIGNED PRIMARY KEY, retained VARCHAR(30) NOT NULL) ENGINE=InnoDB');
query('CREATE TABLE '.table('license_finance_offers').' (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, retained VARCHAR(30) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$schema=license_finance_gateway_schema($prefix);
query($schema['license_finance_charges']);query($schema['license_finance_charge_attempts']);
function staff_owner(string $password):array{
    if(!db()->inTransaction())throw new LogicException('Owner must reauthenticate in transaction');
    if($password!=='synthetic-owner')throw new InvalidArgumentException('Current owner required');
    return ['id'=>1];
}
// Do not simulate successful ledger settlement. This sentinel proves only that
// an eligible attempt reaches the unchanged ledger boundary; rollback follows.
function ledger_invoice(int $invoice):array{throw new RuntimeException('eligible_ledger_boundary');}
function license_financing_sync_by_invoice(int $invoice):void{throw new LogicException('No ledger settlement expected');}
$cases=[];
foreach(['declined','uncertain','review','sandbox_test'] as $outcome){
    foreach(['same','new_key','conflicting_transaction','missing_transaction','terminal'] as $kind){
        $cases['mark_'.$outcome.'_'.$kind]=['method'=>'mark','kind'=>$kind,'outcome'=>$outcome,'initial'=>$kind==='terminal'?'refunded':'processing'];
    }
}
foreach(['processing','uncertain','review'] as $initial){
    foreach(['same','unbound_transaction','new_key','conflicting_transaction','historical_transaction'] as $kind){
        $cases['paid_'.$initial.'_'.$kind]=['method'=>'paid','kind'=>$kind,'initial'=>$initial];
    }
}
$cases['paid_owner_revoked']=['method'=>'paid','kind'=>'owner_revoked','initial'=>'processing'];
$checks=[];$results=[];
$lockKey='cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40);
$newInterface=(new ReflectionFunction('license_finance_charge_record_paid'))->getParameters()[3]->getName()==='attemptKey';
foreach($cases as $name=>$case){
    $invoice=500+count($results);$offer=substr(hash('sha256',$prefix.'|'.$name),0,32);$key=hash('sha256','current|'.$offer);$oldKey=hash('sha256','original|'.$offer);$tx=(string)(770000000+$invoice);
    $requestedKey=$case['kind']==='new_key'?$oldKey:$key;
    $localTx=in_array($case['kind'],['unbound_transaction','historical_transaction'],true)?null:($case['kind']==='conflicting_transaction'?(string)((int)$tx+10000):$tx);
    $responseTx=$case['kind']==='missing_transaction'?null:$tx;
    query('INSERT INTO '.table('payment_requests').' VALUES (?,?)',[$invoice,'retained-request']);
    query('INSERT INTO '.table('studio_invoices').' VALUES (?,?)',[$invoice,'retained-invoice']);
    query('INSERT INTO '.table('license_finance_offers').' VALUES (?,?)',[$offer,'retained-signed-agreement']);
    query('INSERT INTO '.table('license_finance_charges').' (request_id,invoice_id,offer_id,amount_cents,status,request_key,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
        [$invoice,$invoice,$offer,1250,$case['initial'],$key,$localTx,'2098-01-01 00:00:00','2098-01-01 00:00:00']);
    query('INSERT INTO '.table('license_finance_charge_attempts').' (invoice_id,request_key,amount_cents,status,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
        [$invoice,$key,1250,$case['initial'],$localTx,'2098-01-01 00:00:00','2098-01-01 00:00:00']);
    if($case['kind']==='historical_transaction')query('INSERT INTO '.table('license_finance_charge_attempts').' (invoice_id,request_key,amount_cents,status,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
        [$invoice,$oldKey,1250,'paid',$tx,'2097-01-01 00:00:00','2097-01-01 00:00:00']);
    $beforeCharge=query('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice])->fetch();
    $beforeAttempt=query('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$key])->fetch();
    $parents=[];foreach(['payment_requests','studio_invoices','license_finance_offers'] as $t)$parents[$t]=query('SELECT * FROM '.table($t).' ORDER BY id')->fetchAll();
    $error=null;$applied=null;
    try{
        if($case['method']==='mark')$applied=license_finance_charge_mark($invoice,$case['outcome'],$responseTx,$requestedKey);
        elseif($newInterface)license_finance_charge_record_paid($invoice,$tx,1250,$requestedKey,null,$case['kind']==='owner_revoked'?'revoked':'synthetic-owner');
        else license_finance_charge_record_paid($invoice,$tx,1250,null,$case['kind']==='owner_revoked'?'revoked':'synthetic-owner');
    }catch(Throwable $e){$error=$e->getMessage();}
    $afterCharge=query('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice])->fetch();
    $afterAttempt=query('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$key])->fetch();
    $expectedCharge=$beforeCharge;$expectedAttempt=$beforeAttempt;
    if($case['method']==='mark'&&$case['kind']==='same'){
        foreach(['status'=>$case['outcome'],'transaction_id'=>$tx,'updated_at'=>$afterCharge['updated_at']] as $field=>$value){$expectedCharge[$field]=$value;$expectedAttempt[$field]=$value;}
    }
    $allowed=in_array($case['kind'],['same','unbound_transaction'],true);
    $expectedError=$case['method']==='mark'?null:($case['kind']==='owner_revoked'?'Current owner required':($allowed?'eligible_ledger_boundary':'Financing gateway attempt no longer matches this payment.'));
    if($case['method']==='mark')$checks[]=['name'=>$name.' reports whether mark applied','passed'=>$applied===($case['kind']==='same')];
    $checks[]=['name'=>$name.' expected outcome','passed'=>$error===$expectedError];
    $checks[]=['name'=>$name.' exact charge retained or updated','passed'=>$afterCharge===$expectedCharge];
    $checks[]=['name'=>$name.' exact attempt retained or updated','passed'=>$afterAttempt===$expectedAttempt];
    $afterParents=[];foreach(array_keys($parents) as $t)$afterParents[$t]=query('SELECT * FROM '.table($t).' ORDER BY id')->fetchAll();
    $checks[]=['name'=>$name.' parent records unchanged','passed'=>$parents===$afterParents];
    $checks[]=['name'=>$name.' mutex and transaction released','passed'=>!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',[$lockKey])->fetchColumn()===1];
    $results[]=['case'=>$name,'error'=>$error,'before_charge'=>$beforeCharge,'after_charge'=>$afterCharge,'before_attempt'=>$beforeAttempt,'after_attempt'=>$afterAttempt];
}

// Actual checkout plus actual locked mark. Provider/prepare/subscription boundaries
// are synthetic; a second SQL connection commits the intervening attempt/state.
$sandbox=[];$profileCalls=[];$snapshotAtReply=[];
function license_finance_anet_settings():array{return ['mode'=>'sandbox'];}
function license_finance_charge_prepare(array $request,string $key):array{global $sandbox;return ['invoice'=>$sandbox['invoice'],'amount_cents'=>1250,'offer'=>['id'=>$sandbox['offer'],'client_id'=>4],'create_profile'=>true];}
function license_finance_anet_charge_request(mixed ...$args):array{return [];}
function sandbox_rows():array{return ['charges'=>query('SELECT * FROM '.table('license_finance_charges').' ORDER BY id')->fetchAll(),'attempts'=>query('SELECT * FROM '.table('license_finance_charge_attempts').' ORDER BY id')->fetchAll()];}
function license_finance_anet_execute(string $operation,array $payload):array{
    global $sandbox,$port,$user,$password,$snapshotAtReply;
    if($operation==='createTransactionRequest')return ['messages'=>['resultCode'=>'Ok'],'transactionResponse'=>['responseCode'=>'1','transId'=>$sandbox['tx']]];
    if($operation!=='getTransactionDetailsRequest')throw new LogicException('Unexpected provider operation');
    $writer=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if($sandbox['kind']==='replacement'){
        $writer->beginTransaction();
        $writer->prepare('UPDATE '.table('license_finance_charge_attempts').' SET status=? WHERE request_key=?')->execute(['declined',$sandbox['key']]);
        $writer->prepare('UPDATE '.table('license_finance_charges').' SET request_key=?,transaction_id=NULL WHERE invoice_id=?')->execute([$sandbox['new_key'],$sandbox['invoice']]);
        $writer->prepare('INSERT INTO '.table('license_finance_charge_attempts').' (invoice_id,request_key,amount_cents,status,created_at,updated_at) VALUES (?,?,?,?,?,?)')->execute([$sandbox['invoice'],$sandbox['new_key'],1250,'processing','2098-01-01 00:00:00','2098-01-01 00:00:00']);
        $writer->commit();
    }elseif($sandbox['kind']!=='same'){
        $writer->prepare('UPDATE '.table('license_finance_charges').' SET status=? WHERE invoice_id=?')->execute([$sandbox['kind'],$sandbox['invoice']]);
        $writer->prepare('UPDATE '.table('license_finance_charge_attempts').' SET status=? WHERE request_key=?')->execute([$sandbox['kind'],$sandbox['key']]);
    }
    $snapshotAtReply=sandbox_rows();
    return ['messages'=>['resultCode'=>'Ok'],'transaction'=>['transId'=>$sandbox['tx'],'transactionType'=>'authCaptureTransaction','transactionStatus'=>'capturedPendingSettlement','authAmount'=>'12.50','settleAmount'=>'12.50','order'=>['invoiceNumber'=>'CWP-'.$sandbox['invoice']]]];
}
function license_finance_anet_profile_ids(array $reply,array $details):?array{return ['customer_profile_id'=>'1001','payment_profile_id'=>'2001'];}
function license_finance_subscription_create_profile(string $offer,array $profiles):string{global $profileCalls;$profileCalls[]=['offer'=>$offer,'profiles'=>$profiles];return 'sandbox';}
function report_error(Throwable $e):void{throw new LogicException('Unexpected orchestration error: '.$e->getMessage(),0,$e);}
foreach(['same','replacement','refunded','voided','paid'] as $kind){
    $invoice=1000+count($results);$offer=substr(hash('sha256',$prefix.'|sandbox|'.$kind),0,32);$key=hash('sha256','attempt|'.$offer);$tx=(string)(880000000+$invoice);
    $sandbox=['kind'=>$kind,'invoice'=>$invoice,'offer'=>$offer,'key'=>$key,'new_key'=>hash('sha256','replacement|'.$offer),'tx'=>$tx];$profileCalls=[];
    query('INSERT INTO '.table('payment_requests').' VALUES (?,?)',[$invoice,'retained-request']);query('INSERT INTO '.table('studio_invoices').' VALUES (?,?)',[$invoice,'retained-invoice']);query('INSERT INTO '.table('license_finance_offers').' VALUES (?,?)',[$offer,'retained-signed-agreement']);
    query('INSERT INTO '.table('license_finance_charges').' (request_id,invoice_id,offer_id,amount_cents,status,request_key,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',[$invoice,$invoice,$offer,1250,'processing',$key,'2098-01-01 00:00:00','2098-01-01 00:00:00']);
    query('INSERT INTO '.table('license_finance_charge_attempts').' (invoice_id,request_key,amount_cents,status,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$invoice,$key,1250,'processing','2098-01-01 00:00:00','2098-01-01 00:00:00']);
    $result=license_finance_charge_run([],$key,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic',[]);$after=sandbox_rows();$allowed=$kind==='same';
    $checks[]=['name'=>'sandbox '.$kind.' expected orchestration result','passed'=>$result===($allowed?'sandbox':'review')];
    $checks[]=['name'=>'sandbox '.$kind.' subscription creation admission','passed'=>$profileCalls===($allowed?[['offer'=>$offer,'profiles'=>['customer_profile_id'=>'1001','payment_profile_id'=>'2001']]]:[])];
    $checks[]=['name'=>'sandbox '.$kind.' exact stale rows or accepted mark','passed'=>$allowed?(one('SELECT status,transaction_id FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice])===['status'=>'sandbox_test','transaction_id'=>$tx]):$after===$snapshotAtReply];
    $checks[]=['name'=>'sandbox '.$kind.' mutex and transaction released','passed'=>!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',[$lockKey])->fetchColumn()===1];
    $results[]=['case'=>'sandbox_'.$kind,'result'=>$result,'profile_calls'=>$profileCalls];
}

$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'results'=>$results,'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),'source_sha256'=>hash_file('sha256',$sourcePath),'function_sha256'=>$sourceHashes,'staff_function_sha256'=>$staffHashes,'scope'=>'Canonical charge/attempt SQL, real mutex/transaction and delayed result snapshots. Synthetic owner, ledger-boundary sentinel and checkout preparation/provider/subscription boundaries. Actual sandbox checkout plus mark sees a separate SQL writer replace its attempt or state; no ledger settlement, license access, HTTP or provider claim.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
