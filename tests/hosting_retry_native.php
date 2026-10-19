<?php
/** Disposable native MariaDB fixtures. Synthetic journals are not provider evidence. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/operations.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage,OperationResult};
$private=__DIR__.'/../test-runtime/hosting-retry-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-hosting-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'htry_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic hosting owner passphrase';
$package=new HostingPackage('HOST-1','Synthetic café hosting','synthetic-type','synthetic-region',['disk_gb'=>10,'sites'=>null]);
function hr_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function hr_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;hr_ok($name,true);return;}hr_ok($name,false);}
function hr_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function hr_id(int $i):string {return str_pad(dechex($i),32,'0',STR_PAD_LEFT);}
function hr_snapshot():string {return json_encode([all('SELECT * FROM '.table('hosting_records').' ORDER BY id'),all('SELECT * FROM '.table('hosting_record_events').' ORDER BY id'),all('SELECT * FROM '.table('audit').' ORDER BY id')]);}
function hr_all():string {return json_encode([hr_snapshot(),all('SELECT * FROM '.table('hosting_dispatches').' ORDER BY id'),all('SELECT * FROM '.table('hosting_operation_results').' ORDER BY id'),all('SELECT * FROM '.table('hosting_resources').' ORDER BY id')]);}
function hr_load(string $id):HostingJournal {return hosting_operations_validate(hosting_records_row($id,1));}
function hr_claim(string $id,int $request,string $operation='hosting.provision',array $arguments=[]):?array {
    global $password,$profileId;
    return hosting_operations_claim($id,1,hr_load($id)->revision(),hr_id($request),$operation,$arguments,$profileId,(int)provider_settings_load($profileId)['revision'],$password);
}
function hr_result(array $claim,string $outcome='applied',?string $resource=null,bool $retryable=false):OperationResult {
    $b=$claim['binding'];
    return match($outcome){
        'applied'=>OperationResult::applied($b['operation'],'synthetic-receipt',time(),$b['account_id'],$resource,$b['attempt_ref']),
        'failed'=>OperationResult::failed($b['operation'],'synthetic-definite-failure',$retryable,time(),$b['account_id'],$b['attempt_ref']),
        'timeout'=>OperationResult::timeout($b['operation'],time(),$b['account_id'],$b['attempt_ref']),
    };
}
function hr_record(array $claim,OperationResult $result):void {hosting_operations_record($claim['binding']['id'],$claim['fingerprint'],$result);}
function hr_active(int $account,int $request):array {
    global $password,$package;
    $id=hr_id($account);hosting_records_create($id,1,$package,$password);$claim=hr_claim($id,$request);hr_record($claim,hr_result($claim,'applied','synthetic-resource-'.$account));return $claim;
}
function hr_final_audit_failure(string $action,callable $fn):bool {
    db()->exec('CREATE TRIGGER htry_final_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='hosting_records.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic final audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER htry_final_fail');}return $failed;
}
function hr_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER htry_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic hosting audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER htry_fail');}return $failed;
}
function hr_worker(string $mode,array $args=[]):array {
    $p=proc_open([PHP_BINARY,__FILE__,$mode,...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start hosting fixture worker');
    foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);return [$p,$pipes];
}
function hr_finish(array $worker):string {
    [$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0||$error!=='')throw new RuntimeException('Hosting fixture worker failed: '.$error);return $out;
}
/** Tests only: seed full canonical remote states without exposing a production import API. */
function hr_seed(HostingJournal $journal):void {
    $d=json_decode($journal->json(),true);$initial=$d;$initial['events']=[];$prefix=$initial;$previous=hash('sha256',hosting_records_json($initial));
    query('INSERT INTO '.table('hosting_records').' (id,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,1,1,?,?,?,?,?)',[$d['id'],$d['created_at'],hosting_records_json($initial),$journal->json(),$journal->fingerprint(),$journal->revision()]);
    foreach($d['events'] as $index=>$event){
        $prefix['events'][]=$event;$next=hash('sha256',hosting_records_json($prefix));
        query('INSERT INTO '.table('hosting_record_events').' (id,account_id,revision,actor_id,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,1,?,?,?,?)',[$event['id'],$d['id'],$index+1,$event['at'],hosting_records_json(['revision'=>$index,'command'=>$event['command'],'arguments'=>$event['arguments']]),$previous,$next]);$previous=$next;
    }
}
if(($argv[1]??'')==='claim-worker') {
    hr_actor();$profileId=$argv[4];echo "ready\n";fflush(STDOUT);
    try{$claim=hosting_operations_claim($argv[2],1,0,$argv[3],'hosting.provision',[],$profileId,(int)$argv[5],$password);echo $claim?'claimed':'replayed';}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
if(($argv[1]??'')==='read-worker') {hr_actor();echo hr_load($argv[2])->json();exit;}
if(($argv[1]??'')==='revoke-client-worker') {query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');echo 'revoked';exit;}

if(($argv[1]??'')==='retry-worker') {
    hr_actor((int)$argv[2]);echo "ready\n";fflush(STDOUT);
    try{hosting_operations_retry_provision($argv[3],1,(int)$argv[4],$argv[5],$password);echo 'prepared';}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
function hr_failed(int $account,int $request,bool $retryable=true):array {
    global $package,$password;
    $id=hr_id($account);hosting_records_create($id,1,$package,$password);$claim=hr_claim($id,$request);hr_record($claim,hr_result($claim,'failed',null,$retryable));return $claim;
}
function hr_retry(string $id,int $event):HostingJournal {
    global $password;
    return hosting_operations_retry_provision($id,1,hr_load($id)->revision(),hr_id($event),$password);
}
try {
    foreach(schema_statements('htry_') as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    save_setting('studio_schema_version','3');
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Retry operator '.$i,'retry-operator'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    for($i=1;$i<=2;$i++)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Retry client '.$i,'retry-client'.$i.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    hr_actor();hosting_records_migrate($password);provider_settings_migrate($password);
    $profileId=provider_settings_save('',0,'Synthetic retry provider',true,'synthetic-local-only-key',$password);hosting_operations_migrate($password);
    $credential=provider_settings_load($profileId)['credentials'];$keyBytes=file_get_contents($private.'/provider-master.key');
    $id=hr_id(1);hosting_records_create($id,1,$package,$password);
    hr_deny('New pending account cannot be treated as failed retry',fn()=>hr_retry($id,101));
    $claim=hr_claim($id,100);$before=hr_all();hr_deny('Unresolved dispatch without a result cannot be retried',fn()=>hr_retry($id,101));hr_ok('Missing confirmation refusal preserves the first dispatch',hr_all()===$before);
    $definite=hr_result($claim,'failed',null,true);hr_record($claim,$definite);$before=hr_all();$revision=hr_load($id)->revision();
    hr_deny('Retry requires current owner password',fn()=>hosting_operations_retry_provision($id,1,$revision,hr_id(101),'wrong'));
    hr_actor(2);hr_deny('Full-grant administrator cannot request owner retry',fn()=>hr_retry($id,101));hr_actor();
    hr_deny('Retry cannot select another client',fn()=>hosting_operations_retry_provision($id,2,$revision,hr_id(101),$password));
    $_GET['support']='';hr_deny('Support selector cannot request retry',fn()=>hr_retry($id,101));unset($_GET['support']);
    $GLOBALS['cwp_support']=['client'=>['id'=>1]];hr_deny('Support context cannot request retry',fn()=>hr_retry($id,101));unset($GLOBALS['cwp_support']);
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');hr_deny('Inactive client cannot be prepared for provisioning retry',fn()=>hr_retry($id,101));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
    hr_deny('Retry requires exact current revision',fn()=>hosting_operations_retry_provision($id,1,$revision-1,hr_id(101),$password));
    hr_deny('Retry rejects malformed event identity',fn()=>hosting_operations_retry_provision($id,1,$revision,hr_id(101)."\n",$password));
    hr_deny('Generic local command API still refuses provisioning retry',fn()=>hosting_records_change($id,1,$revision,hr_id(101),'retry_provision',[],$password));
    db()->beginTransaction();hr_deny('Retry refuses inherited transaction',fn()=>hr_retry($id,101));hr_ok('Nested refusal leaves caller transaction intact',db()->inTransaction());db()->rollBack();
    hr_ok('Invalid retry requests preserve every journal fence result and audit',hr_all()===$before);
    hr_ok('Strict final retry audit failure rolls back pending state and event',hr_final_audit_failure('provision_retry_requested',fn()=>hr_retry($id,101))&&hr_all()===$before);
    $dispatches=all('SELECT * FROM '.table('hosting_dispatches'));$results=all('SELECT * FROM '.table('hosting_operation_results'));
    $pending=hr_retry($id,101);
    hr_ok('Explicit definite-failure retry returns only local pending journal',$pending->account()->status()==='pending'&&$pending->account()->pendingAttempt()===null&&!$pending->account()->awaitingReconciliation()&&$pending->revision()===$revision+1);
    hr_ok('Preparing retry does not create a dispatch or change the failed observation',all('SELECT * FROM '.table('hosting_dispatches'))===$dispatches&&all('SELECT * FROM '.table('hosting_operation_results'))===$results);
    $before=hr_all();hosting_operations_retry_provision($id,1,$revision,hr_id(101),$password);hr_ok('Exact request replay preserves journal and audit',hr_all()===$before);
    hr_deny('Changed revision cannot reuse retry identity',fn()=>hosting_operations_retry_provision($id,1,$revision+1,hr_id(101),$password));
    hr_actor(3);hr_deny('A different owner cannot reuse the retry identity',fn()=>hosting_operations_retry_provision($id,1,$revision,hr_id(101),$password));hr_actor();
    hr_deny('Already prepared retry cannot be prepared again under another identity',fn()=>hr_retry($id,102));
    hr_ok('Retry journal and immutable events survive process restart',hr_finish(hr_worker('read-worker',[$id]))===$pending->json());
    provider_settings_state($profileId,1,'disable',$password);
    hr_deny('Prepared retry cannot bypass disabled provider settings',fn()=>hr_claim($id,102));
    hr_ok('Refused new dispatch leaves pending state without an attempt',hr_load($id)->account()->pendingAttempt()===null&&all('SELECT * FROM '.table('hosting_dispatches'))===$dispatches);
    provider_settings_state($profileId,2,'enable',$password);
    $next=hr_claim($id,102);
    hr_ok('Only subsequent normal claim fences a distinct attempt',$next!==null&&$next['binding']['attempt_ref']!==$claim['binding']['attempt_ref']&&$next['binding']['profile_revision']===3&&count(all('SELECT * FROM '.table('hosting_dispatches')))===2);
    hr_ok('Old claim replay never returns permission for another call',hosting_operations_claim($id,1,0,hr_id(100),'hosting.provision',[],$profileId,1,$password)===null);
    $before=hr_all();hosting_operations_retry_provision($id,1,$revision,hr_id(101),$password);hr_record($claim,$definite);hr_ok('Old retry and result replay cannot settle the new attempt',hr_all()===$before&&hr_load($id)->account()->pendingAttempt()===$next['binding']['attempt_ref']);
    hr_deny('Late changed first-attempt observation cannot settle second attempt',fn()=>hr_record($claim,hr_result($claim,'applied','wrong-old-resource')));
    hr_record($next,hr_result($next,'applied','synthetic-retry-resource-1'));hr_ok('Exact second attempt result activates one retained resource',hr_load($id)->account()->status()==='active'&&hr_load($id)->account()->providerRef()==='synthetic-retry-resource-1'&&count(all('SELECT * FROM '.table('hosting_resources')))===1);
    hr_deny('Active account cannot enter provisioning retry',fn()=>hr_retry($id,103));
    hr_failed(2,200,false);hr_deny('Provider marked unsafe-to-retry failure stays blocked',fn()=>hr_retry(hr_id(2),201));
    hosting_records_create(hr_id(3),1,$package,$password);$unknown=hr_claim(hr_id(3),300);hr_record($unknown,hr_result($unknown,'timeout'));$before=hr_all();hr_deny('Timeout remains blocked pending verified reconciliation',fn()=>hr_retry(hr_id(3),301));hr_ok('Unknown-outcome refusal preserves the exact unresolved attempt',hr_all()===$before&&hr_load(hr_id(3))->account()->awaitingReconciliation());
    hosting_records_create(hr_id(4),1,$package,$password);$missing=hr_claim(hr_id(4),400);hr_record($missing,hr_result($missing,'applied'));hr_deny('Reported success without resource identity cannot be retried',fn()=>hr_retry(hr_id(4),401));
    hr_failed(5,500);$race=hr_id(5);$v=hr_load($race)->revision();$workers=[hr_worker('retry-worker',['1',$race,(string)$v,hr_id(501)]),hr_worker('retry-worker',['3',$race,(string)$v,hr_id(502)])];$out=array_map('hr_finish',$workers);sort($out);hr_ok('Competing distinct retry requests commit exactly one pending transition',$out===["ready\nprepared","ready\nstale"]&&hr_load($race)->revision()===$v+1);
    hr_failed(6,600);$race=hr_id(6);$v=hr_load($race)->revision();$workers=[hr_worker('retry-worker',['1',$race,(string)$v,hr_id(601)]),hr_worker('retry-worker',['1',$race,(string)$v,hr_id(601)])];$out=array_map('hr_finish',$workers);hr_ok('Competing identical retry requests preserve one event',$out===["ready\nprepared","ready\nprepared"]&&hr_load($race)->revision()===$v+1);
    hr_failed(7,700);$race=hr_id(7);$v=hr_load($race)->revision();
    staff_locked(function()use($race,$v):void{$GLOBALS['retry_waiter']=hr_worker('retry-worker',['3',$race,(string)$v,hr_id(701)]);if(trim(fgets($GLOBALS['retry_waiter'][1][1]))!=='ready')throw new RuntimeException('Retry waiter did not reach guard');query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=3');});$before=hr_all();hr_ok('Owner disabled while waiting cannot prepare retry',hr_finish($GLOBALS['retry_waiter'])==='denied'&&hr_all()===$before);query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=3');
    staff_locked(function()use($race,$v):void{$GLOBALS['client_retry_waiter']=hr_worker('retry-worker',['1',$race,(string)$v,hr_id(701)]);if(trim(fgets($GLOBALS['client_retry_waiter'][1][1]))!=='ready')throw new RuntimeException('Client retry waiter did not reach guard');query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');});$before=hr_all();hr_ok('Client disabled while waiting cannot enter local pending retry',hr_finish($GLOBALS['client_retry_waiter'])==='denied'&&hr_all()===$before);query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
    hr_deny('Accepted retry identity cannot be reused on another account',fn()=>hr_retry($race,101));
    // Preserve an otherwise canonical history but remove its final observation: never infer proof.
    $stored=one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[hr_id(600)]);query('DELETE FROM '.table('hosting_operation_results').' WHERE id=?',[hr_id(600)]);
    hr_deny('Retained retry history without its original failed result is refused',fn()=>hr_load(hr_id(6)));query('INSERT INTO '.table('hosting_operation_results').' (id,event_id,result_json) VALUES (?,?,?)',array_values($stored));hr_ok('Restored original immutable result makes exact retry history readable',hr_load(hr_id(6))->account()->status()==='pending');
    hr_ok('Retry operations preserve original encrypted provider credentials and key',provider_settings_load($profileId)['credentials']===$credential&&file_get_contents($private.'/provider-master.key')===$keyBytes);
    hr_ok('Retry audit omits credentials private bindings and customer mail',!str_contains(json_encode(all('SELECT * FROM '.table('audit'))),'synthetic-local-only-key')&&!is_file($private.'/mail-master.key'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/hosting-retry-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native explicit local provisioning retry after synthetic definite failure; no provider call or route','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native hosting retry checks'.PHP_EOL;exit($ok?0:1);
