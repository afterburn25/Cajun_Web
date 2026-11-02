<?php
/** Disposable native MariaDB fixtures. Synthetic journals are not provider evidence. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/operations.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage,OperationResult};
$private=__DIR__.'/../test-runtime/hosting-delete-retry-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-hosting-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hdel_']];
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
    db()->exec('CREATE TRIGGER hdel_final_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='hosting_records.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic final audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER hdel_final_fail');}return $failed;
}
function hr_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER hdel_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic hosting audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER hdel_fail');}return $failed;
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
if(($argv[1]??'')==='delete-retry-worker') {
    hr_actor((int)$argv[2]);echo "ready\n";fflush(STDOUT);
    try{$claim=hosting_operations_retry_deprovision($argv[3],1,(int)$argv[4],$argv[5],$argv[6],(int)$argv[7],$password);echo $claim?'claimed':'replayed';}
    catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
if(($argv[1]??'')==='read-worker') {hr_actor(3);echo hr_load($argv[2])->json();exit;}
function hr_deleted_failure(int $account,int $request,string $outcome='failed',bool $retryable=true):array {
    global $password;
    hr_actor();hr_active($account,$request);$id=hr_id($account);
    hosting_records_change($id,1,hr_load($id)->revision(),hr_id($request+1),'request_cancellation',[],$password);
    hr_actor(3);$claim=hr_claim($id,$request+2,'hosting.deprovision',['retention_confirmed'=>true]);
    if($outcome!=='pending')hr_record($claim,hr_result($claim,$outcome,null,$retryable));
    return $claim;
}
function hr_delete_retry(string $id,int $event):?array {
    global $password,$profileId;
    return hosting_operations_retry_deprovision($id,1,hr_load($id)->revision(),hr_id($event),$profileId,(int)provider_settings_load($profileId)['revision'],$password);
}
function hr_delete_worker(string $id,int $revision,int $event,int $actor=3):array {
    global $profileId;
    return hr_worker('delete-retry-worker',[(string)$actor,$id,(string)$revision,hr_id($event),$profileId,(string)provider_settings_load($profileId)['revision']]);
}
try {
    foreach(schema_statements('hdel_') as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    save_setting('studio_schema_version','2');
    for($i=1;$i<=4;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Deletion operator '.$i,'deletion-operator'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>'administrator',3=>'owner',4=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    for($i=1;$i<=2;$i++)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Deletion client '.$i,'deletion-client'.$i.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    hr_actor();hosting_records_migrate($password);provider_settings_migrate($password);
    $profileId=provider_settings_save('',0,'Synthetic deletion provider',true,'synthetic-local-only-key',$password);hosting_operations_migrate($password);
    $credential=provider_settings_load($profileId)['credentials'];$keyBytes=file_get_contents($private.'/provider-master.key');
    $claim=hr_deleted_failure(1,100);$id=hr_id(1);$revision=hr_load($id)->revision();$before=hr_all();
    hr_deny('Revoked owner refuses: Deletion retry requires the current owner password',fn()=>cwp_test_owner_revoked(fn()=>hosting_operations_retry_deprovision($id,1,$revision,hr_id(103),$profileId,1,'wrong')));
    hr_actor(2);hr_deny('Delegated full-grant administrator cannot retry deletion',fn()=>hr_delete_retry($id,103));
    hr_actor();hr_deny('Cancellation requester cannot substitute for the retained second approver',fn()=>hr_delete_retry($id,103));
    hr_actor(4);hr_deny('Unreviewed different owner cannot inherit deletion approval',fn()=>hr_delete_retry($id,103));hr_actor(3);
    hr_deny('Deletion retry cannot select another client',fn()=>hosting_operations_retry_deprovision($id,2,$revision,hr_id(103),$profileId,1,$password));
    $_GET['support']='';hr_deny('Support selector cannot retry deletion',fn()=>hr_delete_retry($id,103));unset($_GET['support']);
    $GLOBALS['cwp_support']=['client'=>['id'=>1]];hr_deny('Support context cannot retry deletion',fn()=>hr_delete_retry($id,103));unset($GLOBALS['cwp_support']);
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');hr_deny('Inactive client cannot authorize deletion retry',fn()=>hr_delete_retry($id,103));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
    hr_deny('Deletion retry rejects stale hosting revision',fn()=>hosting_operations_retry_deprovision($id,1,$revision-1,hr_id(103),$profileId,1,$password));
    hr_deny('Deletion retry rejects stale provider revision',fn()=>hosting_operations_retry_deprovision($id,1,$revision,hr_id(103),$profileId,9,$password));
    hr_deny('Deletion retry rejects trailing newline in stable identity',fn()=>hosting_operations_retry_deprovision($id,1,$revision,hr_id(103)."\n",$profileId,1,$password));
    hr_deny('Generic local command cannot arm deletion retry',fn()=>hosting_records_change($id,1,$revision,hr_id(103),'retry_deprovision',[],$password));
    hr_deny('Ordinary deletion claim cannot bypass explicit retry action',fn()=>hr_claim($id,103,'hosting.deprovision',['retention_confirmed'=>true]));
    hr_deny('Internal retry path rejects another operation',fn()=>hosting_operations_claim_event($id,1,$revision,hr_id(103),'hosting.provision',[],$profileId,1,$password,true));
    hr_deny('Internal retry path rejects arbitrary arguments',fn()=>hosting_operations_claim_event($id,1,$revision,hr_id(103),'hosting.deprovision',['retention_confirmed'=>true],$profileId,1,$password,true));
    db()->beginTransaction();hr_deny('Deletion retry refuses an inherited transaction',fn()=>hr_delete_retry($id,103));hr_ok('Nested transaction refusal preserves the caller transaction',db()->inTransaction());db()->rollBack();
    hr_ok('All invalid deletion requests preserve journals fences results and audit',hr_all()===$before);
    hr_ok('Strict final dispatch audit failure rolls back the newly armed deletion attempt',hr_final_audit_failure('dispatch_fenced',fn()=>hr_delete_retry($id,103))&&hr_all()===$before);
    hr_ok('Strict event audit failure rolls back deletion retry completely',hr_audit_failure(fn()=>hr_delete_retry($id,103))&&hr_all()===$before);
    $oldDispatch=one('SELECT * FROM '.table('hosting_dispatches').' WHERE id=?',[$claim['binding']['id']]);$oldResult=one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[$claim['binding']['id']]);$resources=all('SELECT * FROM '.table('hosting_resources'));
    $retry=hr_delete_retry($id,103);$binding=$retry['binding'];
    hr_ok('Deletion retry commits one fresh attempt with immutable exact resource binding',$binding['operation']==='hosting.deprovision'&&$binding['attempt_ref']!==$claim['binding']['attempt_ref']&&$binding['resource_ref']===$claim['binding']['resource_ref']&&$binding['client_id']===1&&$binding['actor_id']===3&&$binding['profile_id']===$profileId);
    hr_ok('New retry is durably fenced before its only claim is returned',hr_load($id)->account()->pendingAttempt()===$binding['attempt_ref']&&hr_load($id)->revision()===$revision+1&&count(all('SELECT id FROM '.table('hosting_dispatches').' WHERE account_id=?',[$id]))===3);
    hr_ok('Original failure and historical resource association remain unchanged',one('SELECT * FROM '.table('hosting_dispatches').' WHERE id=?',[$claim['binding']['id']])===$oldDispatch&&one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[$claim['binding']['id']])===$oldResult&&all('SELECT * FROM '.table('hosting_resources'))===$resources);
    $before=hr_all();hr_ok('Exact deletion request replay returns no claim',hosting_operations_retry_deprovision($id,1,$revision,hr_id(103),$profileId,1,$password)===null&&hr_all()===$before);
    hr_deny('Changed revision cannot reuse deletion retry identity',fn()=>hosting_operations_retry_deprovision($id,1,$revision+1,hr_id(103),$profileId,1,$password));
    hr_deny('Ordinary dispatch cannot replay a deletion retry identity',fn()=>hosting_operations_claim($id,1,$revision,hr_id(103),'hosting.deprovision',[],$profileId,1,$password));
    hr_deny('Deletion retry cannot reuse the original approval identity',fn()=>hosting_operations_retry_deprovision($id,1,$revision,hr_id(102),$profileId,1,$password));
    hr_actor(4);hr_deny('Another current owner cannot replay a retained retry identity',fn()=>hosting_operations_retry_deprovision($id,1,$revision,hr_id(103),$profileId,1,$password));hr_actor(3);
    hr_deny('Pending retried deletion cannot allocate another attempt',fn()=>hr_delete_retry($id,104));
    hr_ok('Independent process reload preserves the exact retry fence and journal',hr_finish(hr_worker('read-worker',[$id]))===hr_load($id)->json());
    $original=OperationResult::failed('hosting.deprovision','synthetic-definite-failure',true,json_decode($oldResult['result_json'],true)['at'],$id,$claim['binding']['attempt_ref']);hr_record($claim,$original);
    hr_ok('Exact old failure replay cannot settle the new attempt',hr_all()===$before&&hr_load($id)->account()->pendingAttempt()===$binding['attempt_ref']);
    hr_deny('Late changed old success cannot settle the fresh attempt',fn()=>hr_record($claim,hr_result($claim,'applied',$binding['resource_ref'])));
    hr_deny('Retry finalizer rejects a substituted provider resource',fn()=>hr_record($retry,hr_result($retry,'applied','wrong-resource')));
    $observed=hr_result($retry,'applied',$binding['resource_ref']);
    hr_ok('Failed result audit keeps the retry fence and original pending state',hr_final_audit_failure('result_recorded',fn()=>hr_record($retry,$observed))&&hr_all()===$before);
    hr_ok('Replayed request after failed result persistence still returns no claim',hosting_operations_retry_deprovision($id,1,$revision,hr_id(103),$profileId,1,$password)===null);
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=3');query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');query('UPDATE '.table('provider_profiles').' SET enabled=0 WHERE id=?',[$profileId]);
    hr_record($retry,$observed);
    hr_ok('Internal verified result preserves reality after owner client and profile access loss',hr_load($id)->account()->status()==='deprovisioned'&&hr_load($id)->account()->pendingAttempt()===null);
    hr_ok('Deletion never releases a historical provider resource for cross-client reuse',all('SELECT * FROM '.table('hosting_resources'))===$resources);
    query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=3');query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');query('UPDATE '.table('provider_profiles').' SET enabled=1 WHERE id=?',[$profileId]);hr_actor(3);
    $before=hr_all();hr_record($retry,$observed);hr_ok('Completed retry and result replay remain idempotent',hosting_operations_retry_deprovision($id,1,$revision,hr_id(103),$profileId,1,$password)===null&&hr_all()===$before);
    hr_deny('Completed deletion cannot be retried under another identity',fn()=>hr_delete_retry($id,104));
    hr_deleted_failure(2,200,'failed',false);hr_deny('Provider-marked unsafe deletion failure remains blocked',fn()=>hr_delete_retry(hr_id(2),203));
    hr_deleted_failure(3,300,'timeout');$before=hr_all();hr_deny('Uncertain deletion requires verified reconciliation',fn()=>hr_delete_retry(hr_id(3),303));hr_ok('Unknown deletion refusal preserves the exact unresolved attempt',hr_all()===$before&&hr_load(hr_id(3))->account()->awaitingReconciliation());
    hr_deleted_failure(4,400,'pending');hr_deny('Missing deletion confirmation cannot be treated as definite failure',fn()=>hr_delete_retry(hr_id(4),403));
    hr_actor();hr_active(5,500);hr_actor(3);hr_deny('Active account cannot skip cancellation and second approval',fn()=>hr_delete_retry(hr_id(5),503));
    hosting_records_create(hr_id(6),1,$package,$password);hr_deny('Never-provisioned account cannot enter deletion retry',fn()=>hr_delete_retry(hr_id(6),603));
    hr_deleted_failure(7,700);$race=hr_id(7);$v=hr_load($race)->revision();$workers=[hr_delete_worker($race,$v,703),hr_delete_worker($race,$v,703)];$out=array_map('hr_finish',$workers);sort($out);
    hr_ok('Competing identical deletion retries grant exactly one new claim',$out===["ready\nclaimed","ready\nreplayed"]&&hr_load($race)->revision()===$v+1);
    hr_deleted_failure(8,800);$race=hr_id(8);$v=hr_load($race)->revision();$workers=[hr_delete_worker($race,$v,803),hr_delete_worker($race,$v,804)];$out=array_map('hr_finish',$workers);sort($out);
    hr_ok('Competing distinct deletion retries reject the stale request',$out===["ready\nclaimed","ready\nstale"]&&hr_load($race)->revision()===$v+1);
    hr_deleted_failure(9,900);$race=hr_id(9);$v=hr_load($race)->revision();
    staff_locked(function()use($race,$v):void{$GLOBALS['delete_waiter']=hr_delete_worker($race,$v,903);if(trim(fgets($GLOBALS['delete_waiter'][1][1]))!=='ready')throw new RuntimeException('Delete waiter did not reach guard');query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=3');});$before=hr_all();hr_ok('Approver revoked while waiting cannot fence a retry',hr_finish($GLOBALS['delete_waiter'])==='denied'&&hr_all()===$before);query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=3');hr_actor(3);
    staff_locked(function()use($race,$v):void{$GLOBALS['delete_waiter']=hr_delete_worker($race,$v,903);if(trim(fgets($GLOBALS['delete_waiter'][1][1]))!=='ready')throw new RuntimeException('Delete waiter did not reach guard');query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');});$before=hr_all();hr_ok('Client revoked while waiting cannot fence a retry',hr_finish($GLOBALS['delete_waiter'])==='denied'&&hr_all()===$before);query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
    staff_locked(function()use($race,$v,$profileId,$password):void{$GLOBALS['delete_waiter']=hr_delete_worker($race,$v,903);if(trim(fgets($GLOBALS['delete_waiter'][1][1]))!=='ready')throw new RuntimeException('Delete waiter did not reach guard');provider_settings_state($profileId,1,'disable',$password);});$before=hr_all();hr_ok('Provider disabled while waiting cannot fence a retry',hr_finish($GLOBALS['delete_waiter'])==='stale'&&hr_all()===$before);
    hr_deny('Current disabled provider profile cannot authorize retry',fn()=>hr_delete_retry($race,903));provider_settings_state($profileId,2,'enable',$password);
    $other=hr_id(99999);$before=hr_all();hr_deny('Unknown provider identity cannot replace retained deletion binding',fn()=>hosting_operations_retry_deprovision($race,1,$v,hr_id(903),$other,1,$password));hr_ok('Unknown profile refusal preserves the original failed record and dispatches',hr_all()===$before);
    $retry9=hr_delete_retry($race,903);hr_ok('Reviewed current provider revision is retained without changing original resource',$retry9['binding']['profile_revision']===3&&$retry9['binding']['resource_ref']==='synthetic-resource-9');
    $saved=one('SELECT * FROM '.table('hosting_dispatches').' WHERE id=?',[hr_id(903)]);
    query('DELETE FROM '.table('hosting_dispatches').' WHERE id=?',[hr_id(903)]);hr_deny('Missing retry fence cannot be rebuilt from its armed journal',fn()=>hr_load($race));query('INSERT INTO '.table('hosting_dispatches').' (id,account_id,profile_id,profile_revision,actor_id,revision,operation,attempt_ref,started_epoch,input_json,binding_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)',array_values($saved));
    hr_ok('Restoring exact immutable retry fence never grants another claim',hosting_operations_retry_deprovision($race,1,$v,hr_id(903),$profileId,3,$password)===null);
    foreach(['operation'=>'hosting.resume','actor_id'=>4,'attempt_ref'=>$race.'#999','input_json'=>'{}','binding_json'=>'{}','profile_revision'=>2] as $field=>$value){query('UPDATE '.table('hosting_dispatches').' SET '.$field.'=? WHERE id=?',[$value,hr_id(903)]);hr_deny('Changed deletion retry binding refuses reconstruction '.$field,fn()=>hr_load($race));query('UPDATE '.table('hosting_dispatches').' SET '.$field.'=? WHERE id=?',[$saved[$field],hr_id(903)]);}
    $result=one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[hr_id(902)]);query('DELETE FROM '.table('hosting_operation_results').' WHERE id=?',[hr_id(902)]);hr_deny('Retried deletion without its retained definite failure is refused',fn()=>hr_load($race));query('INSERT INTO '.table('hosting_operation_results').' (id,event_id,result_json) VALUES (?,?,?)',array_values($result));hr_ok('Original failure restoration validates the same pending attempt',hr_load($race)->account()->pendingAttempt()===$retry9['binding']['attempt_ref']);
    hr_deny('Retry identity cannot be reused on another account',fn()=>hr_delete_retry(hr_id(2),903));
    hr_ok('Retry keeps original encrypted provider credential and private key',provider_settings_load($profileId)['credentials']===$credential&&file_get_contents($private.'/provider-master.key')===$keyBytes);
    $audits=json_encode(all('SELECT * FROM '.table('audit')));hr_ok('Deletion audit excludes credentials full bindings and customer mail',!str_contains($audits,'synthetic-local-only-key')&&!str_contains($audits,$credential)&&!str_contains($audits,'before_sha256')&&!is_file($private.'/mail-master.key'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/hosting-delete-retry-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB explicit fenced deletion retry after synthetic definite failure; no provider call or route','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native hosting deletion retry checks'.PHP_EOL;exit($ok?0:1);
