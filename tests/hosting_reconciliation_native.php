<?php
/** Disposable native MariaDB fixtures. Synthetic journals are not provider evidence. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/operations.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage,OperationResult};
$private=__DIR__.'/../test-runtime/hosting-reconciliation-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-hosting-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hrec_']];
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
    db()->exec('CREATE TRIGGER hrec_final_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='hosting_records.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic final audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER hrec_final_fail');}return $failed;
}
function hr_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER hrec_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic hosting audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER hrec_fail');}return $failed;
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
function hr_evidence(array $claim,string $outcome='unknown',?string $resource=null):array {
    return ['outcome'=>$outcome,'observed_at'=>time(),'resource_ref'=>$claim['binding']['operation']==='hosting.provision'?$resource:$claim['binding']['resource_ref'],'evidence_ref'=>'synthetic-final-observation','evidence_sha256'=>hash('sha256','synthetic-authoritative-fixture')];
}
function hr_observe(array $claim,int $id,array $input):bool {return hosting_reconciliation_record($claim['binding']['id'],$claim['fingerprint'],hr_id($id),$input);}
function hr_full():string {return json_encode([hr_all(),all('SELECT * FROM '.table('hosting_observations').' ORDER BY id'),all('SELECT * FROM '.table('hosting_reconciliations').' ORDER BY id')]);}
function hr_uncertain(int $account,string $operation='hosting.provision',string $result='timeout'):array {
    global $password,$package;
    hr_actor();$request=$account*100;$id=hr_id($account);
    if($operation==='hosting.provision'){
        hosting_records_create($id,1,$package,$password);$claim=hr_claim($id,$request);
    }else{
        hr_active($account,$request);
        if($operation==='hosting.deprovision'){
            hosting_records_change($id,1,hr_load($id)->revision(),hr_id($request+1),'request_cancellation',[],$password);hr_actor(3);
            $claim=hr_claim($id,$request+2,$operation,['retention_confirmed'=>true]);
        }elseif($operation==='hosting.suspend')$claim=hr_claim($id,$request+1,$operation,['reason'=>'synthetic-review']);
        else{
            $suspend=hr_claim($id,$request+1,'hosting.suspend',['reason'=>'synthetic-review']);hr_record($suspend,hr_result($suspend));
            $claim=hr_claim($id,$request+2,'hosting.resume');
        }
    }
    if($result!=='pending')hr_record($claim,hr_result($claim,$result));return $claim;
}
if(($argv[1]??'')==='observe-worker'){
    $claim=json_decode(base64_decode($argv[2]),true);$input=json_decode(base64_decode($argv[4]),true);
    echo "ready\n";fflush(STDOUT);
    try{echo hr_observe($claim,(int)$argv[3],$input)?'recorded':'replayed';}
    catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo 'denied';}exit;
}
try {
    foreach(schema_statements('hrec_') as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    save_setting('studio_schema_version','2');
    for($i=1;$i<=4;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Reconciliation operator '.$i,'reconcile'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>'administrator',3=>'owner',4=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    for($i=1;$i<=2;$i++)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Reconciliation client '.$i,'reconcile-client'.$i.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    hr_actor();hosting_records_migrate($password);provider_settings_migrate($password);
    $profileId=provider_settings_save('',0,'Synthetic reconciliation provider',true,'synthetic-local-only-key',$password);hosting_operations_migrate($password);
    hr_ok('Existing operation setup does not activate reconciliation storage',!hosting_reconciliation_ready());
    hr_deny('Reconciliation setup requires the owner password',fn()=>hosting_reconciliation_migrate('wrong'));
    hr_actor(2);hr_deny('Administrator cannot prepare reconciliation storage',fn()=>hosting_reconciliation_migrate($password));hr_actor();
    $_GET['support']='';hr_deny('Support selector cannot prepare reconciliation storage',fn()=>hosting_reconciliation_migrate($password));unset($_GET['support']);
    db()->beginTransaction();hr_deny('Reconciliation setup refuses an inherited transaction',fn()=>hosting_reconciliation_migrate($password));db()->rollBack();
    hosting_reconciliation_migrate($password);hr_ok('Owner prepares empty additive reconciliation storage',hosting_reconciliation_ready());
    $schema=hosting_reconciliation_schema('hrec_');
    db()->exec('ALTER TABLE '.table('hosting_observations').' ADD INDEX unexpected(recorded_epoch)');
    hr_deny('Unexpected observation index fails strict schema validation',fn()=>hosting_reconciliation_migrate($password));db()->exec('ALTER TABLE '.table('hosting_observations').' DROP INDEX unexpected');
    db()->exec('ALTER TABLE '.table('hosting_observations').' MODIFY journal_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');
    hr_deny('Case folding evidence hash storage is refused',fn()=>hosting_reconciliation_migrate($password));db()->exec('ALTER TABLE '.table('hosting_observations').' MODIFY journal_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    hosting_reconciliation_migrate($password);hr_ok('Restored exact schema remains usable without replacing tables',hosting_reconciliation_ready());

    $serial=500000;$account=10;
    foreach(['hosting.provision'=>['active','failed'],'hosting.suspend'=>['suspended','active'],'hosting.resume'=>['active','suspended'],'hosting.deprovision'=>['deprovisioned','deprovision_failed']] as $operation=>$states){
        foreach(['applied','not_applied'] as $index=>$outcome){
            $a=$account++;$claim=hr_uncertain($a,$operation);$id=$claim['binding']['account_id'];$before=hr_load($id)->json();$unknown=hr_evidence($claim);$observation=$serial++;
            hr_ok($operation.' '.$outcome.' retains a distinct unknown observation',hr_observe($claim,$observation,$unknown));
            hr_ok($operation.' unknown does not mutate lifecycle or mint dispatch',hr_load($id)->json()===$before&&hr_load($id)->account()->awaitingReconciliation());
            $saved=hr_full();hr_ok($operation.' exact unknown replay does not append audit',!hr_observe($claim,$observation,$unknown)&&hr_full()===$saved);
            $input=hr_evidence($claim,$outcome,$operation==='hosting.provision'&&$outcome==='applied'?'adopted-'.$a:null);$decisionId=$serial++;
            hr_deny($operation.' unknown identity cannot become a conclusive result',fn()=>hr_observe($claim,$observation,$input));
            hr_ok($operation.' '.$outcome.' conclusive evidence commits',hr_observe($claim,$decisionId,$input));
            $settled=hr_load($id);hr_ok($operation.' '.$outcome.' settles without another attempt',$settled->account()->status()===$states[$index]&&!$settled->account()->awaitingReconciliation()&&$settled->account()->pendingAttempt()===null);
            $saved=hr_full();hr_ok($operation.' '.$outcome.' exact decision replay is a no-op',!hr_observe($claim,$decisionId,$input)&&hr_full()===$saved);
            hr_deny($operation.' settled attempt refuses a new observation identity',fn()=>hr_observe($claim,$serial++,$input));
            hr_ok($operation.' original timeout result remains immutable',json_decode(one('SELECT result_json FROM '.table('hosting_operation_results').' WHERE id=?',[$claim['binding']['id']])['result_json'],true)['outcome']==='timeout');
            if($operation==='hosting.provision'){
                if($outcome==='applied')hr_ok('Reconciled provisioning uniquely adopts the resource',one('SELECT resource_ref FROM '.table('hosting_resources').' WHERE account_id=?',[$id])['resource_ref']==='adopted-'.$a);
                else{
                    hr_actor();$revision=$settled->revision();hosting_operations_retry_provision($id,1,$revision,hr_id($a*100+10),$password);
                    hr_ok('Confirmed absence prepares a separate local provisioning retry',hr_load($id)->account()->status()==='pending'&&hr_load($id)->account()->pendingAttempt()===null);
                    $next=hr_claim($id,$a*100+11);hr_ok('New provisioning claim uses the next distinct attempt',$next['binding']['attempt_ref']!==$claim['binding']['attempt_ref']);
                    $saved=hr_full();hr_ok('Old conclusive replay stays no-op after a newer provisioning attempt',!hr_observe($claim,$decisionId,$input)&&hr_full()===$saved);
                    hr_deny('Late conclusive observation cannot settle a newer provisioning attempt',fn()=>hr_observe($claim,$serial++,$input));
                }
            }
            if($operation==='hosting.deprovision'&&$outcome==='not_applied'){
                hr_actor();hr_deny('Negative deletion reconciliation retains the original second approver',fn()=>hr_delete_retry($id,$a*100+10));hr_actor(3);
                $next=hr_delete_retry($id,$a*100+10);hr_ok('Current original approver deliberately fences a distinct deletion attempt',$next!==null&&$next['binding']['attempt_ref']!==$claim['binding']['attempt_ref']);
                $saved=hr_full();hr_ok('Old deletion decision replay is a no-op after a new attempt',!hr_observe($claim,$decisionId,$input)&&hr_full()===$saved);
                hr_deny('Changed old deletion decision cannot settle a new attempt',fn()=>hr_observe($claim,$decisionId,array_replace($input,['outcome'=>'applied'])));
                hr_record($next,hr_result($next));hr_ok('Retried deletion retains historical unique resource association',hr_load($id)->account()->status()==='deprovisioned'&&one('SELECT resource_ref FROM '.table('hosting_resources').' WHERE account_id=?',[$id])!==null);
            }
        }
    }

    $claim=hr_uncertain(30);$input=hr_evidence($claim,'applied','adopted-30');$saved=hr_full();
    hr_deny('Wrong dispatch fingerprint is refused',fn()=>hosting_reconciliation_record($claim['binding']['id'],str_repeat('f',64),hr_id(600001),$input));
    foreach(['outcome'=>'partial','observed_at'=>time()+3600,'evidence_ref'=>"raw\nsecret",'evidence_sha256'=>'invalid','resource_ref'=>''] as $key=>$value)hr_deny('Malformed conclusive '.$key.' is refused',fn()=>hr_observe($claim,600001,array_replace($input,[$key=>$value])));
    hr_deny('Extra caller supplied binding cannot override the committed dispatch',fn()=>hr_observe($claim,600001,$input+['binding'=>[]]));
    hr_deny('Absent provisioning cannot claim a created resource',fn()=>hr_observe($claim,600001,array_replace($input,['outcome'=>'not_applied'])));
    hr_ok('Malformed observation attempts preserve every row and audit',hr_full()===$saved);
    db()->beginTransaction();hr_deny('Observation finalizer refuses an inherited transaction',fn()=>hr_observe($claim,600001,$input));hr_ok('Inherited transaction remains owned by the caller',db()->inTransaction());db()->rollBack();
    hr_ok('Strict final audit failure rolls back observation decision event and adopted resource',hr_final_audit_failure('reconciliation_recorded',fn()=>hr_observe($claim,600001,$input))&&hr_full()===$saved);
    hr_ok('Unknown observation audit failure preserves the original uncertainty',hr_final_audit_failure('reconciliation_recorded',fn()=>hr_observe($claim,600001,hr_evidence($claim)))&&hr_full()===$saved);
    hr_ok('Canonical event audit failure also rolls back evidence',hr_audit_failure(fn()=>hr_observe($claim,600001,$input))&&hr_full()===$saved);
    hr_ok('Retry after audit recovery can commit exactly once',hr_observe($claim,600001,$input));
    $duplicate=hr_uncertain(31);$saved=hr_full();
    try{hr_observe($duplicate,600002,hr_evidence($duplicate,'applied','adopted-30'));$failed=false;}catch(PDOException){$failed=true;}
    hr_ok('A provider resource cannot be adopted by two clients or accounts',$failed&&hr_full()===$saved&&hr_load(hr_id(31))->account()->awaitingReconciliation());
    $target=hr_uncertain(32,'hosting.suspend');$saved=hr_full();hr_deny('Observation cannot substitute a different retained provider resource',fn()=>hr_observe($target,600003,array_replace(hr_evidence($target,'not_applied'),['resource_ref'=>'foreign-resource'])));hr_ok('Foreign resource refusal retains original result and fence',hr_full()===$saved);
    $pending=hr_uncertain(33,'hosting.provision','pending');$saved=hr_full();hr_deny('Missing result is not reconstructed from a pending journal',fn()=>hr_observe($pending,600004,hr_evidence($pending,'not_applied')));hr_ok('Missing-result refusal leaves the dispatch blocked',hr_full()===$saved&&hr_load(hr_id(33))->account()->pendingAttempt()===$pending['binding']['attempt_ref']);
    $noid=hr_uncertain(34,'hosting.provision','applied');hr_ok('Accepted provisioning without identity requires reconciliation',hr_load(hr_id(34))->account()->awaitingReconciliation());hr_observe($noid,600005,hr_evidence($noid,'applied','verified-identity-34'));hr_ok('Verified identity resolves accepted provisioning without a new create',hr_load(hr_id(34))->account()->providerRef()==='verified-identity-34');

    $claim=hr_uncertain(40);$input=hr_evidence($claim,'not_applied');
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');query('UPDATE '.table('provider_profiles').' SET enabled=0 WHERE id=?',[$profileId]);$_SESSION=[];
    hr_ok('Internal verified observation preserves reality after owner client and provider access loss',hr_observe($claim,700001,$input)&&hr_load(hr_id(40))->account()->status()==='failed');
    hr_actor();hr_deny('Reconciled absence does not restore revoked owner retry authority',fn()=>hosting_operations_retry_provision(hr_id(40),1,hr_load(hr_id(40))->revision(),hr_id(4010),$password));
    query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');hr_actor();hr_deny('Inactive client cannot authorize a post-reconciliation retry',fn()=>hosting_operations_retry_provision(hr_id(40),1,hr_load(hr_id(40))->revision(),hr_id(4010),$password));
    query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');hosting_operations_retry_provision(hr_id(40),1,hr_load(hr_id(40))->revision(),hr_id(4010),$password);
    hr_deny('Disabled provider cannot dispatch a locally prepared reconciled retry',fn()=>hr_claim(hr_id(40),4011));query('UPDATE '.table('provider_profiles').' SET enabled=1 WHERE id=?',[$profileId]);
    $next=hr_claim(hr_id(40),4011);hr_ok('Restored current authorization grants one explicit fresh claim',$next!==null);

    $race=hr_uncertain(50);$input=hr_evidence($race,'not_applied');$args=[base64_encode(json_encode($race)),'800001',base64_encode(json_encode($input))];
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);query('SELECT GET_LOCK(?,5)',[$key]);
    $one=hr_worker('observe-worker',$args);$two=hr_worker('observe-worker',$args);hr_ok('Competing observation workers reach the shared lock',trim(fgets($one[1][1]))==='ready'&&trim(fgets($two[1][1]))==='ready');query('SELECT RELEASE_LOCK(?)',[$key]);
    $out=[hr_finish($one),hr_finish($two)];sort($out);hr_ok('Competing identical decisions commit one record and replay the other',$out===['recorded','replayed']);
    $race=hr_uncertain(51);$input=hr_evidence($race,'not_applied');query('SELECT GET_LOCK(?,5)',[$key]);
    $one=hr_worker('observe-worker',[base64_encode(json_encode($race)),'800002',base64_encode(json_encode($input))]);$two=hr_worker('observe-worker',[base64_encode(json_encode($race)),'800003',base64_encode(json_encode(hr_evidence($race,'applied','race-resource')))]);
    hr_ok('Conflicting observation workers reach the shared lock',trim(fgets($one[1][1]))==='ready'&&trim(fgets($two[1][1]))==='ready');query('SELECT RELEASE_LOCK(?)',[$key]);$out=[hr_finish($one),hr_finish($two)];sort($out);hr_ok('Competing conflicting conclusions cannot both settle one attempt',$out===['denied','recorded']);
    $saved=hr_load(hr_id(50))->json();hr_ok('A new process replays the same durable resolved journal',hr_finish(hr_worker('read-worker',[hr_id(50)]))===$saved);

    $decision=one('SELECT * FROM '.table('hosting_reconciliations').' WHERE id=?',[hr_id(5000)]);
    query('DELETE FROM '.table('hosting_reconciliations').' WHERE id=?',[$decision['id']]);hr_deny('Missing conclusive proof is never reconstructed from a settled journal',fn()=>hr_load(hr_id(50)));query('INSERT INTO '.table('hosting_reconciliations').' (id,observation_id,event_id) VALUES (?,?,?)',array_values($decision));
    $observation=one('SELECT * FROM '.table('hosting_observations').' WHERE id=?',[$decision['observation_id']]);query('UPDATE '.table('hosting_observations').' SET journal_sha256=? WHERE id=?',[str_repeat('0',64),$observation['id']]);hr_deny('Tampered original observation prefix invalidates the decision',fn()=>hr_load(hr_id(50)));query('UPDATE '.table('hosting_observations').' SET journal_sha256=? WHERE id=?',[$observation['journal_sha256'],$observation['id']]);
    $input=json_decode($observation['observation_json'],true);$input['binding']['profile_revision']++;query('UPDATE '.table('hosting_observations').' SET observation_json=? WHERE id=?',[hosting_records_json($input),$observation['id']]);hr_deny('Tampered profile binding is refused',fn()=>hr_load(hr_id(50)));query('UPDATE '.table('hosting_observations').' SET observation_json=? WHERE id=?',[$observation['observation_json'],$observation['id']]);
    hr_ok('Restored original proof validates without a new action',hr_load(hr_id(50))->json()===$saved);
    save_setting('hosting_reconciliation_schema_version','');hr_deny('Cleared readiness cannot adopt populated evidence tables',fn()=>hosting_reconciliation_migrate($password));hr_deny('Old adapter cannot accept uncovered reconciliation events',fn()=>hr_load(hr_id(50)));save_setting('hosting_reconciliation_schema_version','1');
    hr_ok('Original readiness and evidence remain recoverable',hr_load(hr_id(50))->json()===$saved);
    $bounded=hr_uncertain(60);$unknown=hr_evidence($bounded);$original=hr_load(hr_id(60))->json();
    for($i=0;$i<32;$i++)hr_observe($bounded,900000+$i,$unknown);
    hr_ok('Bounded unknown observations retain the original unresolved journal',hr_load(hr_id(60))->json()===$original);
    $before=hr_full();hr_deny('Thirty-third unknown observation is refused without deleting history',fn()=>hr_observe($bounded,900032,$unknown));hr_ok('Observation bound preserves every row and audit',hr_full()===$before);
    hr_ok('Exact unknown replay remains idempotent at the observation bound',!hr_observe($bounded,900000,$unknown)&&hr_full()===$before);
    hr_observe($bounded,900033,hr_evidence($bounded,'not_applied'));hr_ok('Conclusive resolution remains possible after the unknown limit',!hr_load(hr_id(60))->account()->awaitingReconciliation()&&hr_load(hr_id(60))->account()->pendingAttempt()===null);
    $before=hr_full();hr_ok('Original unknown replay stays idempotent after conclusive resolution',!hr_observe($bounded,900000,$unknown)&&hr_full()===$before);
    hr_deny('Generic local command cannot forge conclusive reconciliation',fn()=>hosting_records_change(hr_id(60),1,hr_load(hr_id(60))->revision(),hr_id(900034),'reconcile_without_dispatch',[],$password));
    hr_deny('Legacy automatic rearming reconciliation is never a local action',fn()=>hosting_records_change(hr_id(60),1,hr_load(hr_id(60))->revision(),hr_id(900034),'reconcile',[],$password));
    $audits=json_encode(all('SELECT * FROM '.table('audit')));hr_ok('Reconciliation audit excludes evidence bodies digests and provider credentials',!str_contains($audits,'synthetic-local-only-key')&&!str_contains($audits,'synthetic-final-observation')&&!str_contains($audits,'dispatch_fingerprint'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/hosting-reconciliation-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB trusted synthetic observations and current-authorized retries; no provider verification, route or worker','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native hosting reconciliation checks'.PHP_EOL;exit($ok?0:1);
