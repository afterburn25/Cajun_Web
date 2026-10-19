<?php
/** Disposable native MariaDB fixtures. Synthetic journals are not provider evidence. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/operations.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage,OperationResult};
$private=__DIR__.'/../test-runtime/hosting-operations-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-hosting-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hoci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic hosting owner passphrase';
$package=new HostingPackage('HOST-1','Synthetic café hosting','synthetic-type','synthetic-region',['disk_gb'=>10,'sites'=>null]);
function ho_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function ho_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;ho_ok($name,true);return;}ho_ok($name,false);}
function ho_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function ho_id(int $i):string {return str_pad(dechex($i),32,'0',STR_PAD_LEFT);}
function ho_snapshot():string {return json_encode([all('SELECT * FROM '.table('hosting_records').' ORDER BY id'),all('SELECT * FROM '.table('hosting_record_events').' ORDER BY id'),all('SELECT * FROM '.table('audit').' ORDER BY id')]);}
function ho_all():string {return json_encode([ho_snapshot(),all('SELECT * FROM '.table('hosting_dispatches').' ORDER BY id'),all('SELECT * FROM '.table('hosting_operation_results').' ORDER BY id'),all('SELECT * FROM '.table('hosting_resources').' ORDER BY id')]);}
function ho_load(string $id):HostingJournal {return hosting_operations_validate(hosting_records_row($id,1));}
function ho_claim(string $id,int $request,string $operation='hosting.provision',array $arguments=[]):?array {
    global $password,$profileId;
    return hosting_operations_claim($id,1,ho_load($id)->revision(),ho_id($request),$operation,$arguments,$profileId,(int)provider_settings_load($profileId)['revision'],$password);
}
function ho_result(array $claim,string $outcome='applied',?string $resource=null,bool $retryable=false):OperationResult {
    $b=$claim['binding'];
    return match($outcome){
        'applied'=>OperationResult::applied($b['operation'],'synthetic-receipt',time(),$b['account_id'],$resource,$b['attempt_ref']),
        'failed'=>OperationResult::failed($b['operation'],'synthetic-definite-failure',$retryable,time(),$b['account_id'],$b['attempt_ref']),
        'timeout'=>OperationResult::timeout($b['operation'],time(),$b['account_id'],$b['attempt_ref']),
    };
}
function ho_record(array $claim,OperationResult $result):void {hosting_operations_record($claim['binding']['id'],$claim['fingerprint'],$result);}
function ho_active(int $account,int $request):array {
    global $password,$package;
    $id=ho_id($account);hosting_records_create($id,1,$package,$password);$claim=ho_claim($id,$request);ho_record($claim,ho_result($claim,'applied','synthetic-resource-'.$account));return $claim;
}
function ho_final_audit_failure(string $action,callable $fn):bool {
    db()->exec('CREATE TRIGGER hoci_final_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='hosting_records.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic final audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER hoci_final_fail');}return $failed;
}
function ho_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER hoci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic hosting audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER hoci_fail');}return $failed;
}
function ho_worker(string $mode,array $args=[]):array {
    $p=proc_open([PHP_BINARY,__FILE__,$mode,...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start hosting fixture worker');
    foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);return [$p,$pipes];
}
function ho_finish(array $worker):string {
    [$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0||$error!=='')throw new RuntimeException('Hosting fixture worker failed: '.$error);return $out;
}
/** Tests only: seed full canonical remote states without exposing a production import API. */
function ho_seed(HostingJournal $journal):void {
    $d=json_decode($journal->json(),true);$initial=$d;$initial['events']=[];$prefix=$initial;$previous=hash('sha256',hosting_records_json($initial));
    query('INSERT INTO '.table('hosting_records').' (id,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,1,1,?,?,?,?,?)',[$d['id'],$d['created_at'],hosting_records_json($initial),$journal->json(),$journal->fingerprint(),$journal->revision()]);
    foreach($d['events'] as $index=>$event){
        $prefix['events'][]=$event;$next=hash('sha256',hosting_records_json($prefix));
        query('INSERT INTO '.table('hosting_record_events').' (id,account_id,revision,actor_id,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,1,?,?,?,?)',[$event['id'],$d['id'],$index+1,$event['at'],hosting_records_json(['revision'=>$index,'command'=>$event['command'],'arguments'=>$event['arguments']]),$previous,$next]);$previous=$next;
    }
}
if(($argv[1]??'')==='claim-worker') {
    ho_actor();$profileId=$argv[4];echo "ready\n";fflush(STDOUT);
    try{$claim=hosting_operations_claim($argv[2],1,0,$argv[3],'hosting.provision',[],$profileId,(int)$argv[5],$password);echo $claim?'claimed':'replayed';}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
if(($argv[1]??'')==='read-worker') {ho_actor();echo ho_load($argv[2])->json();exit;}
if(($argv[1]??'')==='revoke-client-worker') {query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');echo 'revoked';exit;}
try {
    foreach(schema_statements('hoci_') as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    save_setting('studio_schema_version','3');
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Hosting operator '.$i,'operator'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    for($i=1;$i<=2;$i++)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Synthetic client '.$i,'client'.$i.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    ho_actor();hosting_records_migrate($password);
    ho_deny('Operation setup requires prepared provider storage',fn()=>hosting_operations_migrate($password));provider_settings_migrate($password);
    $profileId=provider_settings_save('',0,'Synthetic provider',true,'synthetic-local-only-key',$password);
    $keyBytes=file_get_contents($private.'/provider-master.key');$credential=provider_settings_load($profileId)['credentials'];
    ho_deny('Operation setup requires current owner password',fn()=>hosting_operations_migrate('wrong'));
    ho_actor(2);ho_deny('Administrator cannot prepare operation storage',fn()=>hosting_operations_migrate($password));ho_actor();
    save_setting('hosting_operations_schema_version','future');ho_deny('Future operation schema is preserved and refused',fn()=>hosting_operations_migrate($password));query('DELETE FROM '.table('settings').' WHERE name=?',['hosting_operations_schema_version']);
    foreach(hosting_operations_schema('hoci_') as $sql)db()->exec($sql);
    db()->exec('ALTER TABLE '.table('hosting_dispatches').' ADD INDEX unexpected_operation(operation)');ho_deny('Operation setup refuses extra indexes',fn()=>hosting_operations_migrate($password));db()->exec('ALTER TABLE '.table('hosting_dispatches').' DROP INDEX unexpected_operation');
    db()->exec('ALTER TABLE '.table('hosting_dispatches').' MODIFY binding_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');ho_deny('Operation setup refuses nonbinary bindings',fn()=>hosting_operations_migrate($password));db()->exec('ALTER TABLE '.table('hosting_dispatches').' MODIFY binding_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    db()->beginTransaction();ho_deny('Operation setup refuses inherited transaction before DDL',fn()=>hosting_operations_migrate($password));ho_ok('Refused operation DDL retains caller transaction',db()->inTransaction());db()->rollBack();
    $legacy=HostingJournal::create(ho_id(99),$package,'client:1',10)->append(ho_id(990),'begin_provisioning','staff:1',11);ho_seed($legacy);
    ho_deny('Setup refuses unfenced existing remote history',fn()=>hosting_operations_migrate($password));ho_ok('Unfenced legacy history remains intact with readiness unset',!hosting_operations_ready()&&hosting_records_get(ho_id(99),1)->json()===$legacy->json());query('DELETE FROM '.table('hosting_record_events').' WHERE account_id=?',[ho_id(99)]);query('DELETE FROM '.table('hosting_records').' WHERE id=?',[ho_id(99)]);
    ho_ok('Operation setup audit failure rolls back readiness',ho_audit_failure(fn()=>hosting_operations_migrate($password))&&!hosting_operations_ready());hosting_operations_migrate($password);hosting_operations_migrate($password);
    ho_ok('Operation setup creates empty ready storage',hosting_operations_ready()&&!all('SELECT * FROM '.table('hosting_dispatches'))&&!all('SELECT * FROM '.table('hosting_operation_results'))&&!all('SELECT * FROM '.table('hosting_resources')));
    $id=ho_id(1);hosting_records_create($id,1,$package,$password);$before=ho_all();
    ho_deny('Claim requires current password',fn()=>hosting_operations_claim($id,1,0,ho_id(100),'hosting.provision',[],$profileId,1,'wrong'));
    ho_deny('Claim refuses wrong client',fn()=>hosting_operations_claim($id,2,0,ho_id(100),'hosting.provision',[],$profileId,1,$password));
    ho_deny('Claim refuses stale provider revision',fn()=>hosting_operations_claim($id,1,0,ho_id(100),'hosting.provision',[],$profileId,2,$password));
    ho_deny('Claim refuses unknown operation',fn()=>ho_claim($id,100,'hosting.rebuild'));
    ho_deny('Claim refuses injected command arguments',fn()=>ho_claim($id,100,'hosting.provision',['extra'=>true]));
    ho_deny('Claim refuses newline request identity',fn()=>hosting_operations_claim($id,1,0,ho_id(100)."\n",'hosting.provision',[],$profileId,1,$password));
    ho_actor(2);ho_deny('Administrator cannot claim provider work',fn()=>ho_claim($id,100));ho_actor();
    $_GET['support']='';ho_deny('Owner support context cannot claim provider work',fn()=>ho_claim($id,100));unset($_GET['support']);
    ho_ok('Denied preflight leaves all durable records unchanged',ho_all()===$before);
    ho_ok('Final dispatch audit failure rolls back journal event and fence',ho_final_audit_failure('dispatch_fenced',fn()=>ho_claim($id,100))&&ho_all()===$before);
    $claim=ho_claim($id,100);$before=ho_all();$b=$claim['binding'];
    ho_ok('New claim returns only exact committed account profile attempt binding',!db()->inTransaction()&&$b['account_id']===$id&&$b['client_id']===1&&$b['profile_id']===$profileId&&$b['profile_revision']===1&&$b['attempt_ref']===$id.'#1'&&$b['resource_ref']===null&&$claim['fingerprint']===hash('sha256',hosting_records_json($b)));
    ho_ok('Fence and journal are durably committed before any transport',ho_load($id)->account()->status()==='provisioning'&&count(all('SELECT * FROM '.table('hosting_dispatches')))===1&&count(all('SELECT * FROM '.table('hosting_operation_results')))===0);
    ho_ok('Exact dispatch replay grants no second permission',hosting_operations_claim($id,1,0,ho_id(100),'hosting.provision',[],$profileId,1,$password)===null&&ho_all()===$before);
    ho_deny('Changed stable dispatch request is refused',fn()=>hosting_operations_claim($id,1,1,ho_id(100),'hosting.provision',[],$profileId,1,$password));
    ho_deny('New identity cannot rearm an unresolved dispatch',fn()=>ho_claim($id,101));
    ho_ok('Process restart reconstructs pending state without another claim',ho_finish(ho_worker('read-worker',[$id]))===ho_load($id)->json()&&ho_all()===$before);
    ho_deny('Foreign fingerprint cannot save a provider result',fn()=>hosting_operations_record(ho_id(100),str_repeat('0',64),ho_result($claim,'applied','synthetic-resource-1')));
    foreach([
        OperationResult::applied('hosting.suspend','synthetic',time(),$id,null,$b['attempt_ref']),
        OperationResult::applied('hosting.provision','synthetic',time(),ho_id(2),'synthetic-resource-1',$b['attempt_ref']),
        OperationResult::applied('hosting.provision','synthetic',time(),$id,'synthetic-resource-1',$id.'#0'),
        OperationResult::applied('hosting.provision','synthetic',$b['started_epoch']-1,$id,'synthetic-resource-1',$b['attempt_ref']),
        OperationResult::applied('hosting.provision','synthetic',time()+3600,$id,'synthetic-resource-1',$b['attempt_ref']),
    ] as $n=>$result)ho_deny('Unbound or impossible observation is refused '.$n,fn()=>ho_record($claim,$result));
    ho_ok('Refused results preserve original unresolved fence',ho_all()===$before);
    provider_settings_state($profileId,1,'disable',$password);query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');$_SESSION=[];
    $accepted=ho_result($claim,'applied','synthetic-resource-1');ho_record($claim,$accepted);
    ho_ok('Trusted finalizer persists actual acceptance after owner client and profile revocation',ho_load($id)->account()->status()==='active'&&ho_load($id)->account()->providerRef()==='synthetic-resource-1');
    $before=ho_all();ho_record($claim,$accepted);ho_ok('Exact observed result replay does not duplicate audit or state',ho_all()===$before);
    ho_deny('Conflicting observed result cannot overwrite saved acceptance',fn()=>ho_record($claim,ho_result($claim,'timeout')));
    query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');ho_actor();provider_settings_state($profileId,2,'enable',$password);
    ho_ok('Completed original request still cannot grant another dispatch',hosting_operations_claim($id,1,0,ho_id(100),'hosting.provision',[],$profileId,1,$password)===null);
    hosting_records_change($id,1,2,ho_id(110),'open_dispute',[],$password);ho_deny('Saved billing dispute blocks suspension before fence',fn()=>ho_claim($id,111,'hosting.suspend',['reason'=>'Synthetic manual decision']));
    hosting_records_change($id,1,3,ho_id(112),'close_dispute',[],$password);
    $suspend=ho_claim($id,113,'hosting.suspend',['reason'=>'Synthetic manual decision']);ho_ok('Suspension claim binds verified stored resource',$suspend['binding']['resource_ref']==='synthetic-resource-1'&&ho_load($id)->account()->status()==='suspending');
    ho_deny('Suspension result cannot name another provider resource',fn()=>ho_record($suspend,ho_result($suspend,'applied','other-resource')));ho_record($suspend,ho_result($suspend,'failed',null,true));
    ho_ok('Definite failed suspension returns to active without pending attempt',ho_load($id)->account()->status()==='active'&&ho_load($id)->account()->pendingAttempt()===null);
    $next=ho_claim($id,114,'hosting.suspend',['reason'=>'Synthetic second decision']);ho_deny('Old result cannot settle a later attempt',fn()=>ho_record($next,ho_result($suspend,'applied')));
    ho_record($next,ho_result($next,'timeout'));ho_ok('Unknown suspension remains unresolved with exact attempt',ho_load($id)->account()->awaitingReconciliation()&&ho_load($id)->account()->pendingAttempt()===$next['binding']['attempt_ref']);ho_deny('Unknown suspension cannot be automatically rearmed',fn()=>ho_claim($id,115,'hosting.suspend',['reason'=>'Retry']));
    ho_active(2,200);$id2=ho_id(2);$s=ho_claim($id2,201,'hosting.suspend',['reason'=>'Synthetic']);ho_record($s,ho_result($s));$r=ho_claim($id2,202,'hosting.resume');ho_record($r,ho_result($r));ho_ok('Confirmed suspend and resume retain exact provider association',ho_load($id2)->account()->status()==='active'&&ho_load($id2)->account()->providerRef()==='synthetic-resource-2');
    hosting_records_change($id2,1,6,ho_id(203),'request_cancellation',[],$password);
    ho_deny('Cancellation requester cannot also arm deletion',fn()=>ho_claim($id2,204,'hosting.deprovision',['retention_confirmed'=>true]));ho_actor(3);ho_deny('Second owner must explicitly confirm retention',fn()=>ho_claim($id2,204,'hosting.deprovision',['retention_confirmed'=>false]));
    $delete=ho_claim($id2,204,'hosting.deprovision',['retention_confirmed'=>true]);ho_record($delete,ho_result($delete));ho_ok('Two-owner confirmed deletion retains historical resource binding',ho_load($id2)->account()->status()==='deprovisioned'&&one('SELECT resource_ref FROM '.table('hosting_resources').' WHERE account_id=?',[$id2])['resource_ref']==='synthetic-resource-2');ho_actor();
    ho_active(3,300);hosting_records_change(ho_id(3),1,2,ho_id(301),'set_grace',['until'=>time()+3600],$password);ho_deny('Saved grace deadline blocks suspension before fence',fn()=>ho_claim(ho_id(3),302,'hosting.suspend',['reason'=>'Synthetic']));
    hosting_records_create(ho_id(4),1,$package,$password);$unknown=ho_claim(ho_id(4),400);ho_record($unknown,ho_result($unknown,'timeout'));ho_ok('Uncertain create stays failed awaiting reconciliation',ho_load(ho_id(4))->account()->status()==='failed'&&ho_load(ho_id(4))->account()->awaitingReconciliation());ho_deny('Uncertain create cannot be cancelled as never provisioned',fn()=>hosting_records_change(ho_id(4),1,2,ho_id(401),'cancel_order',[],$password));ho_deny('Uncertain create cannot obtain another dispatch',fn()=>ho_claim(ho_id(4),402));
    hosting_records_create(ho_id(5),1,$package,$password);$missing=ho_claim(ho_id(5),500);ho_record($missing,ho_result($missing));ho_ok('Applied create without resource remains unresolved with no resource association',ho_load(ho_id(5))->account()->awaitingReconciliation()&&!one('SELECT id FROM '.table('hosting_resources').' WHERE account_id=?',[ho_id(5)]));
    hosting_records_create(ho_id(6),1,$package,$password);$duplicate=ho_claim(ho_id(6),600);$before=ho_all();try{ho_record($duplicate,ho_result($duplicate,'applied','synthetic-resource-2'));$denied=false;}catch(PDOException){$denied=true;}ho_ok('Same provider resource cannot be assigned to another client account even after deletion',$denied&&ho_all()===$before&&ho_load(ho_id(6))->account()->status()==='provisioning');
    hosting_records_create(ho_id(11),2,$package,$password);$foreign=hosting_operations_claim(ho_id(11),2,0,ho_id(1100),'hosting.provision',[],$profileId,3,$password);$before=ho_all();try{ho_record($foreign,ho_result($foreign,'applied','synthetic-resource-1'));$denied=false;}catch(PDOException){$denied=true;}ho_ok('Provider resource cannot cross client ownership boundaries',$foreign['binding']['client_id']===2&&$denied&&ho_all()===$before&&hosting_operations_validate(hosting_records_row(ho_id(11),2))->account()->status()==='provisioning');
    hosting_records_create(ho_id(7),1,$package,$password);$interrupted=ho_claim(ho_id(7),700);$before=ho_all();ho_ok('Final result audit failure retains fence and rolls back result resource and journal',ho_final_audit_failure('result_recorded',fn()=>ho_record($interrupted,ho_result($interrupted,'applied','synthetic-resource-7')))&&ho_all()===$before);ho_deny('Result persistence failure cannot authorize another call',fn()=>ho_claim(ho_id(7),701));
    ho_ok('Interrupted result survives independent process restart',ho_finish(ho_worker('read-worker',[ho_id(7)]))===ho_load(ho_id(7))->json());
    hosting_records_create(ho_id(8),1,$package,$password);$workers=[ho_worker('claim-worker',[ho_id(8),ho_id(800),$profileId,'3']),ho_worker('claim-worker',[ho_id(8),ho_id(800),$profileId,'3'])];$out=array_map('ho_finish',$workers);sort($out);ho_ok('Competing identical claims authorize exactly one invocation',$out===["ready\nclaimed","ready\nreplayed"]&&count(all('SELECT id FROM '.table('hosting_dispatches').' WHERE account_id=?',[ho_id(8)]))===1);
    hosting_records_create(ho_id(9),1,$package,$password);$workers=[ho_worker('claim-worker',[ho_id(9),ho_id(900),$profileId,'3']),ho_worker('claim-worker',[ho_id(9),ho_id(901),$profileId,'3'])];$out=array_map('ho_finish',$workers);sort($out);ho_ok('Competing distinct claims reject the stale invocation',$out===["ready\nclaimed","ready\nstale"]&&count(all('SELECT id FROM '.table('hosting_dispatches').' WHERE account_id=?',[ho_id(9)]))===1);
    hosting_records_create(ho_id(10),1,$package,$password);
    staff_locked(function()use($profileId):void{$GLOBALS['waiting_operation']=ho_worker('claim-worker',[ho_id(10),ho_id(1000),$profileId,'3']);if(trim(fgets($GLOBALS['waiting_operation'][1][1]))!=='ready')throw new RuntimeException('Worker failed to reach operation mutex');query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');});$before=ho_all();ho_ok('Revoked owner waiting for mutex cannot commit a fence',ho_finish($GLOBALS['waiting_operation'])==='denied'&&ho_all()===$before);query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');ho_actor();
    db()->beginTransaction();one('SELECT id,active FROM '.table('studio_clients').' WHERE id=1');ho_ok('Separate client writer commits outside the staff mutex',ho_finish(ho_worker('revoke-client-worker'))==='revoked');ho_deny('Active-client authorization uses a current locking read beyond the transaction snapshot',fn()=>hosting_records_client(1,true));db()->rollBack();query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
    $saved=one('SELECT * FROM '.table('hosting_dispatches').' WHERE id=?',[ho_id(700)]);
    foreach(['binding_json'=>'{}','input_json'=>'{}','attempt_ref'=>ho_id(7).'#99','operation'=>'hosting.resume','profile_revision'=>2,'started_epoch'=>1,'actor_id'=>3] as $field=>$value){query('UPDATE '.table('hosting_dispatches').' SET '.$field.'=? WHERE id=?',[$value,ho_id(700)]);ho_deny('Altered dispatch refuses reconstruction '.$field,fn()=>ho_load(ho_id(7)));query('UPDATE '.table('hosting_dispatches').' SET '.$field.'=? WHERE id=?',[$saved[$field],ho_id(700)]);}
    query('DELETE FROM '.table('hosting_dispatches').' WHERE id=?',[ho_id(700)]);ho_deny('Missing dispatch cannot be silently rebuilt from pending lifecycle',fn()=>ho_load(ho_id(7)));query('INSERT INTO '.table('hosting_dispatches').' (id,account_id,profile_id,profile_revision,actor_id,revision,operation,attempt_ref,started_epoch,input_json,binding_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)',array_values($saved));ho_ok('Restored exact fence still grants no second invocation',hosting_operations_claim(ho_id(7),1,0,ho_id(700),'hosting.provision',[],$profileId,3,$password)===null);
    $resource=one('SELECT * FROM '.table('hosting_resources').' WHERE account_id=?',[ho_id(3)]);query('UPDATE '.table('hosting_resources').' SET resource_ref=? WHERE id=?',['changed-resource',$resource['id']]);ho_deny('Changed resource association is not adopted',fn()=>ho_load(ho_id(3)));query('UPDATE '.table('hosting_resources').' SET resource_ref=? WHERE id=?',[$resource['resource_ref'],$resource['id']]);
    $result=one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[ho_id(300)]);query('UPDATE '.table('hosting_operation_results').' SET result_json=? WHERE id=?',['{}',ho_id(300)]);ho_deny('Changed result payload is not adopted',fn()=>ho_load(ho_id(3)));query('UPDATE '.table('hosting_operation_results').' SET result_json=? WHERE id=?',[$result['result_json'],ho_id(300)]);
    $before=ho_all();hosting_operations_migrate($password);ho_ok('Repeated setup retains all operation and resource records',all('SELECT id FROM '.table('hosting_dispatches'))!==[]&&ho_load(ho_id(3))->account()->providerRef()==='synthetic-resource-3');
    ho_ok('Operation records never alter or rotate saved credentials',provider_settings_load($profileId)['credentials']===$credential&&file_get_contents($private.'/provider-master.key')===$keyBytes);
    $audits=json_encode(all('SELECT * FROM '.table('audit')));ho_ok('Audit contains no credential or private binding payload',!str_contains($audits,'synthetic-local-only-key')&&!str_contains($audits,$credential)&&!str_contains($audits,'before_sha256'));
    require_once __DIR__.'/hosting_storage_recovery_cases.php';ho_actor();
    hosting_test_recovery('operations',['hosting_dispatches','hosting_operation_results','hosting_resources'],fn()=>hosting_operations_ready(),fn()=>hosting_operations_migrate($password),fn()=>hosting_operations_require_ready(),fn()=>ho_all(),'ho_ok');
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/hosting-operations-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB durable operation fences and synthetic result fixtures only; no provider transport or UI acceptance','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native hosting operation checks'.PHP_EOL;exit($ok?0:1);
