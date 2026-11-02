<?php
/** Native charge-row race regression; synthetic parent data and gateway details, no network provider. */
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
$fixture=[];
function db():PDO{global $pdo;return $pdo;}
function config():array{global $prefix;return ['db'=>['name'=>'cwp_test','prefix'=>$prefix]];}
function table(string $name):string{global $prefix;if(!preg_match('/^[a-z_]+$/D',$name))throw new LogicException('Unexpected table');return '`'.$prefix.$name.'`';}
function query(string $sql,array $params=[]):PDOStatement{$s=db()->prepare($sql);$s->execute($params);return $s;}
function one(string $sql,array $params=[]):?array{
    global $fixture,$prefix;
    if(str_contains($sql,'FOR UPDATE')){
        $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40);
        if(!db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn()!==(int)query('SELECT CONNECTION_ID()')->fetchColumn())throw new LogicException('Row lock requires actual mutex and transaction');
    }
    $row=query($sql,$params)->fetch()?:null;
    if(($fixture['armed']??false)&&$sql==='SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?'){
        $fixture['armed']=false;
        if($fixture['changes']){
            $command=[PHP_BINARY,__FILE__,'--writer',$prefix,(string)$params[0],base64_encode(json_encode($fixture['changes'],JSON_THROW_ON_ERROR))];
            $child=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
            if(!is_resource($child))throw new RuntimeException('Cannot start competing SQL writer');
            fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            if(proc_close($child)!==0||$err!=='')throw new RuntimeException('Competing writer failed: '.$err);
            $fixture['writer']=json_decode($out,true,8,JSON_THROW_ON_ERROR);
        }
        $fixture['before_write']=query($sql,$params)->fetch();
    }
    return $row;
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
if(($argv[1]??'')==='--writer'){
    $invoice=(int)$argv[3];$changes=json_decode(base64_decode($argv[4],true),true,16,JSON_THROW_ON_ERROR);
    staff_locked(fn()=>staff_transaction(function()use($invoice,$changes):void{
        $sets=[];$values=[];
        foreach($changes as $name=>$value){
            if(!in_array($name,['status','request_key','transaction_id'],true))throw new LogicException('Unexpected competing field');
            $sets[]=$name.'=?';$values[]=$value;
        }
        $values[]=$invoice;query('UPDATE '.table('license_finance_charges').' SET '.implode(',',$sets).' WHERE invoice_id=?',$values);
    }));
    echo json_encode(['connection_id'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn(),'committed'=>!db()->inTransaction()],JSON_THROW_ON_ERROR);exit;
}
define('CWP_BOOT',true);
require $root.'/site/app/billing/authorize_net_contract.php';
require $root.'/site/database/schema/license_finance_gateway_schema.php';
$sourcePath=(string)$argv[1];
$sourceHashes=extracted($sourcePath,['license_finance_charge_webhook_review']);
function license_finance_gateway_ready():bool{return true;}
function ledger_ready():bool{return true;}
// Fresh namespace only. Canonical charge/attempt DDL, indexes and FKs are unmodified.
foreach(['payment_requests','studio_invoices'] as $parent)query('CREATE TABLE '.table($parent).' (id INT UNSIGNED PRIMARY KEY, retained VARCHAR(30) NOT NULL) ENGINE=InnoDB');
query('CREATE TABLE '.table('license_finance_offers').' (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, retained VARCHAR(30) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$schema=license_finance_gateway_schema($prefix);
query($schema['license_finance_charges']);query($schema['license_finance_charge_attempts']);
function retained_snapshot():string{
    $values=[];
    foreach(['payment_requests','studio_invoices','license_finance_offers','license_finance_charge_attempts'] as $name)$values[$name]=query('SELECT * FROM '.table($name).' ORDER BY id')->fetchAll();
    return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
}
$cases=[];
foreach(['processing','uncertain','review'] as $initial){
    foreach(['refunded','voided','declined','sandbox_test','paid'] as $terminal)$cases[$initial.'_to_'.$terminal]=['initial'=>$initial,'changes'=>['status'=>$terminal],'expected'=>'finance_payment_replay'];
    $cases[$initial.'_new_attempt']=['initial'=>$initial,'new_attempt'=>true,'expected'=>'finance_payment_replay'];
    $cases[$initial.'_unchanged']=['initial'=>$initial,'changes'=>[],'expected'=>'finance_payment_review'];
    $cases[$initial.'_historical_capture']=['initial'=>$initial,'changes'=>[],'historical'=>true,'expected'=>'finance_payment_replay'];
}
$cases['new_attempt_current_capture']=['initial'=>'processing','changes'=>[],'historical'=>true,'current_capture'=>true,'expected'=>'finance_payment_review'];
$cases['already_paid']=['initial'=>'paid','changes'=>[],'expected'=>'finance_payment_replay'];
$cases['wrong_amount']=['initial'=>'processing','changes'=>[],'expected'=>'finance_payment_mismatch','wrong_amount'=>true];
$cases['conflicting_transaction']=['initial'=>'processing','changes'=>['transaction_id'=>'990000001'],'error'=>'Financing charge retains a different gateway transaction.'];
$checks=[];$results=[];$reader=(int)query('SELECT CONNECTION_ID()')->fetchColumn();
$lockKey='cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40);
foreach($cases as $name=>$case){
    $invoice=100+count($results);$offer=substr(hash('sha256',$prefix.'|'.$name),0,32);$oldKey=hash('sha256','original|'.$offer);$tx=(string)(660000000+$invoice);
    $historical=$case['historical']??false;$key=$historical?hash('sha256','replacement|'.$offer):$oldKey;
    query('INSERT INTO '.table('payment_requests').' VALUES (?,?)',[$invoice,'retained-request']);
    query('INSERT INTO '.table('studio_invoices').' VALUES (?,?)',[$invoice,'retained-invoice']);
    query('INSERT INTO '.table('license_finance_offers').' VALUES (?,?)',[$offer,'retained-signed-agreement']);
    query('INSERT INTO '.table('license_finance_charges').' (request_id,invoice_id,offer_id,amount_cents,status,request_key,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
        [$invoice,$invoice,$offer,1250,$case['initial'],$key,$historical?null:$tx,'2098-01-01 00:00:00','2098-01-01 00:00:00']);
    query('INSERT INTO '.table('license_finance_charge_attempts').' (invoice_id,request_key,amount_cents,status,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
        [$invoice,$oldKey,1250,$historical?'paid':$case['initial'],($case['current_capture']??false)?(string)((int)$tx-10000):$tx,'2098-01-01 00:00:00','2098-01-01 00:00:00']);
    if($historical)query('INSERT INTO '.table('license_finance_charge_attempts').' (invoice_id,request_key,amount_cents,status,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
        [$invoice,$key,1250,$case['initial'],null,'2098-01-02 00:00:00','2098-01-02 00:00:00']);
    $changes=($case['new_attempt']??false)?['status'=>'processing','request_key'=>hash('sha256','replacement|'.$offer),'transaction_id'=>null]:$case['changes'];
    $before=retained_snapshot();
    $fixture=['armed'=>true,'changes'=>$changes,'writer'=>null];
    $details=['messages'=>['resultCode'=>'Ok'],'transaction'=>['transId'=>$tx,'transactionType'=>'authCaptureTransaction','transactionStatus'=>'settledSuccessfully','authAmount'=>($case['wrong_amount']??false)?'12.51':'12.50','order'=>['invoiceNumber'=>'CWP-'.$invoice]]];
    $error=null;$result=null;
    try{$result=license_finance_charge_webhook_review($details,$tx);}catch(RuntimeException $e){$error=$e->getMessage();}
    $after=query('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice])->fetch();
    $normal=($case['expected']??null)==='finance_payment_review';
    $expectedRow=$fixture['before_write'];
    if($normal){$expectedRow['status']='review';$expectedRow['transaction_id']=$tx;$expectedRow['updated_at']=$after['updated_at'];}
    $checks[]=['name'=>$name.' expected routing result','passed'=>isset($case['error'])?$error===$case['error']:($error===null&&$result===$case['expected'])];
    $checks[]=['name'=>$name.' correct exact charge row','passed'=>$after===$expectedRow];
    $checks[]=['name'=>$name.' retained parent and attempt rows unchanged','passed'=>$before===retained_snapshot()];
    $checks[]=['name'=>$name.' actual mutex and transaction released','passed'=>!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',[$lockKey])->fetchColumn()===1];
    $checks[]=['name'=>$name.' competing writer used separate committed SQL connection','passed'=>$changes===[]||($fixture['writer']['committed']&&$fixture['writer']['connection_id']!==$reader)];
    $results[]=['case'=>$name,'result'=>$result,'error'=>$error,'before'=>$fixture['before_write'],'after'=>$after,'writer'=>$fixture['writer']];
}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'results'=>$results,
    'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),'prefix'=>$prefix,'source_sha256'=>hash_file('sha256',$sourcePath),
    'function_sha256'=>$sourceHashes,'staff_function_sha256'=>$staffHashes,'contract_sha256'=>hash_file('sha256',$root.'/site/app/billing/authorize_net_contract.php'),
    'schema_sha256'=>array_map(fn($sql)=>hash('sha256',$sql),array_intersect_key($schema,array_flip(['license_finance_charges','license_finance_charge_attempts']))),
    'scope'=>'Actual canonical charge/attempt DDL, PDO/InnoDB, account mutex, transactions, exact invoice/amount validator and separate-process competing commits. Synthetic parent records and gateway transaction details; no provider contact, installed HTTP or license-access claim.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
