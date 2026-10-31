<?php
/** Durable provider operation ledger and owner grants against real MariaDB; no provider API. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
$canonical=__DIR__.'/../site/includes/foundation/provider/';
foreach (['ProviderCapability','ProviderCredential','TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderOperation','OperationResult','ConnectionCheck','RetryPolicy','LedgerClaim','OperationLedger','ProviderAdapter','ProviderGateway'] as $class) require_once $canonical.$class.'.php';
require_once __DIR__.'/../site/app/providers/operations.php';
use CajunWebPro\Foundation\Provider\{ConnectionCheck,HttpTransport,OperationResult,ProviderAdapter,ProviderCapability,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,LedgerClaim,OperationLedger};
$prefix=getenv('CWP_TEST_PREFIX')?:'prop_';
if(!preg_match('/^pops_[a-z0-9_]{1,12}$/D',$prefix)&&$prefix!=='prop_')exit(2);
$private=__DIR__.'/../test-runtime/provider-operations-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-ops-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>$prefix]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic operations owner passphrase';$secret='SYNTHETIC_PRIVATE_GENERAL_KEY';
function po_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function po_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;po_ok($name,true);return;}po_ok($name,false);}
function po_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function po_worker(string $mode,string $key,string $fingerprint):array {
    $p=proc_open([PHP_BINARY,__FILE__,$mode,$key,$fingerprint],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Cannot start worker');foreach([1,2] as $s)stream_set_timeout($pipes[$s],20);return [$p,$pipes];
}
function po_finish(array $worker):string {[$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException('Worker failed: '.$err);return $out;}
/** Fixture adapter: supports one operation, records executions, returns scripted results. */
final class PoAdapter implements ProviderAdapter {
    public int $executions=0;public array $script=[];
    public function id():string{return 'twenty-i';}
    public function label():string{return '20i';}
    public function capabilities():array{return [ProviderCapability::supported('packages.list','synthetic',true)];}
    public function supports(string $operation):bool{return $operation==='packages.list';}
    public function probe(HttpTransport $transport,ProviderCredential $credential):ConnectionCheck{return ConnectionCheck::verified('connected');}
    public function execute(HttpTransport $transport,ProviderCredential $credential,ProviderOperation $operation):OperationResult {
        $this->executions++;return array_shift($this->script)??OperationResult::succeeded($operation->name,'done','ext-1');
    }
}
if(($argv[1]??'')==='claim-worker') {
    // Worker: claim a key, report, wait for 'continue', then record a success.
    $ledger=new ProviderOperationLedger();
    $claim=$ledger->claim($argv[2],$argv[3],time());
    echo $claim->state."\n";fflush(STDOUT);
    stream_set_timeout(STDIN,15);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Fixture synchronization');
    $ledger->record($argv[2],$argv[3],OperationResult::succeeded('packages.list','worker ok','ext-worker'),time());
    echo 'recorded';exit;
}
try {
    foreach(schema_statements($prefix) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Operations staff '.$i,'ops'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    po_actor();provider_settings_migrate($password);
    $profile=provider_settings_save('',0,'20i fixture',true,$secret,$password);

    po_deny('Revoked owner refuses: Operation storage requires owner password',fn()=>cwp_test_owner_revoked(fn()=>provider_operations_migrate('wrong')));
    po_actor(2);po_deny('Administrator cannot prepare operation storage',fn()=>provider_operations_migrate($password));po_actor();
    provider_operations_migrate($password);
    po_ok('Operation storage records version','4'===(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_operations_schema_version'])['value']??''));
    provider_operations_migrate($password);po_ok('Migration is idempotent',provider_operations_ready());
    save_setting('provider_operations_schema_version','99');po_deny('Unsupported version refused',fn()=>provider_operations_migrate($password));save_setting('provider_operations_schema_version','4');
    // Simulate version-1 storage: drop the revision binding, the approval-request columns and the reconciliation fields, then migrate through all steps.
    db()->exec('ALTER TABLE '.table('provider_operation_grants').' DROP COLUMN profile_revision, DROP COLUMN scope_params, DROP COLUMN consumed_key, DROP COLUMN consumed_at');
    db()->exec('ALTER TABLE '.table('provider_operations').' DROP COLUMN reconciled_outcome, DROP COLUMN reconciled_epoch');
    save_setting('provider_operations_schema_version','1');provider_operations_migrate($password);
    po_ok('Version-1 storage gains revision binding, approval-request and reconciliation columns',provider_operations_ready()&&(one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_operation_grants','profile_revision'])!==null)&&(one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_operation_grants','scope_params'])!==null)&&(one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_operations','reconciled_outcome'])!==null));
    // Simulate the preceding execution-v2 shape: request columns present, no revision binding, populated.
    db()->exec('ALTER TABLE '.table('provider_operation_grants').' DROP COLUMN profile_revision');
    query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,scope_params,granted_by,granted_at,expires_epoch) VALUES (?,?,?,?,?,?,?,?)',['v2populated000000000000000000000',$profile,'packages.create','fp','{}',1,gmdate('Y-m-d H:i:s'),time()+60]);
    save_setting('provider_operations_schema_version','2');provider_operations_migrate($password);
    po_ok('Populated execution-v2 storage gains revision binding',provider_operations_ready()&&(int)one('SELECT COUNT(*) AS n FROM '.table('provider_operation_grants')." WHERE id='v2populated000000000000000000000'")['n']===1);
    // Simulate the preceding ledger-v2 shape: revision binding present, no request columns.
    db()->exec('ALTER TABLE '.table('provider_operation_grants').' DROP COLUMN scope_params, DROP COLUMN consumed_key, DROP COLUMN consumed_at');
    save_setting('provider_operations_schema_version','2');provider_operations_migrate($password);
    po_ok('Populated ledger-v2 storage gains approval-request columns',provider_operations_ready()&&(one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_operation_grants','scope_params'])!==null));
    db()->exec('ALTER TABLE '.table('provider_operations').' ADD INDEX unexpected(status)');po_deny('Extra index refused without change',fn()=>provider_operations_migrate($password));db()->exec('ALTER TABLE '.table('provider_operations').' DROP INDEX unexpected');
    // A valid auto-increment marker cannot admit unrelated Extra behavior.
    $beforeInvisible=all('SELECT * FROM '.table('provider_operation_grants').' ORDER BY id');
    db()->exec('ALTER TABLE '.table('provider_operation_grants').' MODIFY seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT INVISIBLE');
    try{po_deny('Unexpected invisible auto-increment sequence is refused',fn()=>provider_operations_migrate($password));}
    finally{db()->exec('ALTER TABLE '.table('provider_operation_grants').' MODIFY seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');}
    po_ok('Rejected invisible sequence preserves original rows and ordering',$beforeInvisible===all('SELECT * FROM '.table('provider_operation_grants').' ORDER BY id'));

    // ---- ledger claim/record semantics ----
    $ledger=new ProviderOperationLedger();$now=time();$fp=hash('sha256','synthetic-request-1');
    po_ok('New idempotency key claims','claimed'===$ledger->claim('op-1',$fp,$now)->state);
    po_ok('Duplicate claim reports in progress','in_progress'===$ledger->claim('op-1',$fp,$now)->state);
    po_ok('Foreign fingerprint conflicts','conflict'===$ledger->claim('op-1',hash('sha256','other'),$now)->state);
    query('UPDATE '.table('provider_operations').' SET claimed_epoch=? WHERE idempotency_key=?',[$now-OperationLedger::RESERVATION_TTL-1,'op-1']);
    po_ok('Stale claim classifies uncertain','uncertain'===$ledger->claim('op-1',$fp,$now)->state);
    query('UPDATE '.table('provider_operations').' SET claimed_epoch=? WHERE idempotency_key=?',[$now,'op-1']);
    $ledger->record('op-1',$fp,OperationResult::succeeded('packages.list','created','pkg-9'),$now);
    $again=$ledger->claim('op-1',$fp,$now+5);
    po_ok('Resolved claim replays','replay'===$again->state);
    po_ok('Replay preserves recorded fields','succeeded'===$again->result->status&&$again->result->externalId==='pkg-9'&&$again->result->message==='created');
    po_deny('Recording over a resolved row refuses',fn()=>$ledger->record('op-1',$fp,OperationResult::failed('packages.list','x'),$now));
    po_deny('Recording a foreign fingerprint refuses',fn()=>$ledger->record('op-2',hash('sha256','missing'),OperationResult::succeeded('x'),$now));

    // ---- gateway at-most-once through the durable ledger ----
    $adapter=new PoAdapter();$transport=new class implements HttpTransport {public function send(TransportRequest $r):TransportResponse{return new TransportResponse(200,[],'[]');}};
    $gateway=new ProviderGateway($adapter,$transport,$ledger);
    $credential=new ProviderCredential('twenty-i','synthetic',static fn():string=>$secret);
    $operation=new ProviderOperation('packages.list','key-once',['b'=>2,'a'=>1]);
    $result=$gateway->perform($credential,$operation);
    po_ok('Gateway performs a claimed operation','succeeded'===$result->status&&$adapter->executions===1);
    $replay=$gateway->perform($credential,$operation);
    po_ok('Replaying the same key does not re-execute','succeeded'===$replay->status&&$replay->replayed&&$adapter->executions===1);
    $conflict=$gateway->perform($credential,new ProviderOperation('packages.list','key-once',['a'=>1,'b'=>3]));
    po_ok('Same key different request conflicts','conflict'===$conflict->status&&$adapter->executions===1);
    po_ok('Unsupported operation never reaches the ledger','unsupported'===$gateway->perform($credential,new ProviderOperation('vps.power','k-2'))->status&&0===(int)query('SELECT COUNT(*) FROM '.table('provider_operations').' WHERE idempotency_key=?',['k-2'])->fetchColumn());

    // ---- cross-process claim race ----
    $worker=po_worker('claim-worker','race-key','race-fp');
    po_ok('Worker claimed','claimed'===trim(fgets($worker[1][1])));
    po_ok('Concurrent claim reports in progress','in_progress'===$ledger->claim('race-key','race-fp',time())->state);
    fwrite($worker[1][0],"continue\n");fflush($worker[1][0]);
    po_ok('Worker recorded','recorded'===po_finish($worker));
    po_ok('Post-record claim replays worker result','replay'===$ledger->claim('race-key','race-fp',time())->state);

    // ---- owner grants ----
    $scope=['resellerId'=>'*','id'=>[42],'labels'=>[42=>'a']];
    po_deny('Revoked owner refuses: Grant requires owner password',fn()=>cwp_test_owner_revoked(fn()=>provider_operation_grant($profile,'packages.update',$scope,'wrong')));
    po_actor(2);po_deny('Administrator cannot grant',fn()=>provider_operation_grant($profile,'packages.update',$scope,$password));po_actor();
    po_deny('Grant bounds the duration',fn()=>provider_operation_grant($profile,'packages.update',$scope,$password,30));
    po_deny('Grant bounds the duration upper',fn()=>provider_operation_grant($profile,'packages.update',$scope,$password,90000));
    $grantId=provider_operation_grant($profile,'packages.update',$scope,$password);
    $active=provider_operations_scope('packages.update',$scope);
    po_ok('Fresh grant covers its exact scope',provider_operation_grant_active($profile,'packages.update',$active));
    po_ok('Reordered params produce the same scope',provider_operation_grant_active($profile,'packages.update',provider_operations_scope('packages.update',['labels'=>[42=>'a'],'id'=>[42],'resellerId'=>'*'])));
    po_ok('Changed params do not match',!provider_operation_grant_active($profile,'packages.update',provider_operations_scope('packages.update',['resellerId'=>'*','id'=>[42],'labels'=>[42=>'b']])));
    po_ok('Different operation does not match',!provider_operation_grant_active($profile,'packages.delete',$active));
    po_ok('Different profile does not match',!provider_operation_grant_active(str_repeat('f',32),'packages.update',$active));
    // Invalid UTF-8 map keys cannot collapse distinct scopes into one fingerprint.
    po_deny('Invalid UTF-8 scope key is refused',fn()=>provider_operations_scope('packages.update',["\xff"=>'same']));
    po_deny('A second invalid UTF-8 scope key is refused',fn()=>provider_operations_scope('packages.update',["\xfe"=>'same']));
    // Approval is bound to the approved profile revision and enabled state.
    provider_settings_save($profile,(int)(one('SELECT revision FROM '.table('provider_profiles').' WHERE id=?',[$profile])['revision']??0),'20i fixture',true,$secret,$password);
    po_ok('Any settings save invalidates the approved revision',!provider_operation_grant_active($profile,'packages.update',$active));
    $grantId=provider_operation_grant($profile,'packages.update',$scope,$password);
    po_ok('Re-grant under current configuration is active',provider_operation_grant_active($profile,'packages.update',$active));
    provider_settings_state($profile,(int)(one('SELECT revision FROM '.table('provider_profiles').' WHERE id=?',[$profile])['revision']??0),'disable',$password);
    po_ok('Disabled profile suspends the grant',!provider_operation_grant_active($profile,'packages.update',$active));
    provider_settings_state($profile,(int)(one('SELECT revision FROM '.table('provider_profiles').' WHERE id=?',[$profile])['revision']??0),'enable',$password);
    po_ok('Re-enable after a revision change does not revive it',!provider_operation_grant_active($profile,'packages.update',$active));
    $grantId=provider_operation_grant($profile,'packages.update',$scope,$password);
    po_ok('Grant under the re-approved configuration is active',provider_operation_grant_active($profile,'packages.update',$active));
    // The newest grant for THIS scope decides even after many other-scope grants.
    for($i=0;$i<70;$i++)provider_operation_grant($profile,'packages.update',['resellerId'=>'*','id'=>[1000+$i],'labels'=>[1000+$i=>'other']],$password);
    po_ok('Newest same-scope grant found beyond 64 other rows',provider_operation_grant_active($profile,'packages.update',$active));
    query('UPDATE '.table('provider_operation_grants').' SET expires_epoch=? WHERE id=?',[time()-1,$grantId]);
    po_ok('Expired grant is inactive',!provider_operation_grant_active($profile,'packages.update',$active));
    $grantId2=provider_operation_grant($profile,'packages.update',$scope,$password);
    po_ok('Re-grant supersedes expiry',provider_operation_grant_active($profile,'packages.update',$active));
    po_deny('Revoked owner refuses: Revoke requires owner password',fn()=>cwp_test_owner_revoked(fn()=>provider_operation_revoke($grantId2,'wrong')));
    provider_operation_revoke($grantId2,$password);
    po_ok('Revoked grant is inactive',!provider_operation_grant_active($profile,'packages.update',$active));
    po_deny('Double revoke refuses',fn()=>provider_operation_revoke($grantId2,$password));
    po_ok('Grant and revoke audit events recorded',2<=(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action IN ('provider.operation_grant','provider.operation_revoke')")->fetchColumn());

    // Each established version must recover both ORIGINAL tables. Keep real
    // populated rows in renamed tables and verify no empty substitute appears.
    $ledgerBefore=all('SELECT * FROM '.table('provider_operations').' ORDER BY id');
    $grantsBefore=all('SELECT * FROM '.table('provider_operation_grants').' ORDER BY seq');
    $auditsBefore=all('SELECT * FROM '.table('audit').' ORDER BY id');
    foreach(['1','2','3','4'] as $stamp)foreach(['provider_operations','provider_operation_grants'] as $missing){
        save_setting('provider_operations_schema_version',$stamp);
        $held=$missing.'_recovery';
        db()->exec('RENAME TABLE '.table($missing).' TO '.table($held));
        try{
            po_ok('Missing history is not ready v'.$stamp.' '.$missing,!provider_operations_ready());
            $error='';try{provider_operations_migrate($password);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$error=$e->getMessage();}
            po_ok('Missing stamped table requires original recovery v'.$stamp.' '.$missing,str_contains($error,'Restore the original database backup'));
            po_ok('Recovery creates no empty replacement v'.$stamp.' '.$missing,!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$missing]));
            po_ok('Failed recovery preserves schema stamp v'.$stamp.' '.$missing,one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_operations_schema_version'])['value']===$stamp);
        }finally{db()->exec('RENAME TABLE '.table($held).' TO '.table($missing));}
        po_ok('Recovery preserves ledger grants and audit v'.$stamp.' '.$missing,$ledgerBefore===all('SELECT * FROM '.table('provider_operations').' ORDER BY id')&&$grantsBefore===all('SELECT * FROM '.table('provider_operation_grants').' ORDER BY seq')&&$auditsBefore===all('SELECT * FROM '.table('audit').' ORDER BY id'));
    }
    save_setting('provider_operations_schema_version','4');provider_operations_migrate($password);
    po_ok('Restored original ledger still suppresses duplicate dispatch',$ledger->claim('op-1',$fp,time())->state==='replay');

    // ---- storage is secret-free ----
    $leak=false;
    foreach(['provider_operations','provider_operation_grants'] as $t)foreach(all('SELECT * FROM '.table($t)) as $row)foreach($row as $v)if(is_string($v)&&str_contains($v,$secret))$leak=true;
    po_ok('No credential material in operation storage',!$leak);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-operations-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Durable operation ledger and owner grants against real MariaDB with a synthetic adapter; no provider call','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native provider operation assertions'.PHP_EOL;exit($ok?0:1);
