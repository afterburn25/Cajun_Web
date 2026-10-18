<?php
/** Disposable native MariaDB fixtures. Synthetic journals are not provider evidence. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/records.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage};
$private=__DIR__.'/../test-runtime/hosting-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-hosting-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hrci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic hosting owner passphrase';
$package=new HostingPackage('HOST-1','Synthetic café hosting','synthetic-type','synthetic-region',['disk_gb'=>10,'sites'=>null]);
function hr_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function hr_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;hr_ok($name,true);return;}hr_ok($name,false);}
function hr_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function hr_id(int $i):string {return str_pad(dechex($i),32,'0',STR_PAD_LEFT);}
function hr_snapshot():string {return json_encode([all('SELECT * FROM '.table('hosting_records').' ORDER BY id'),all('SELECT * FROM '.table('hosting_record_events').' ORDER BY id'),all('SELECT * FROM '.table('audit').' ORDER BY id')]);}
function hr_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER hrci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic hosting audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER hrci_fail');}return $failed;
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
if(($argv[1]??'')==='change-worker') {
    hr_actor();echo "ready\n";fflush(STDOUT);
    try{hosting_records_change($argv[2],1,(int)$argv[3],$argv[4],'cancel_order',[],$password);echo 'saved';}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
if(($argv[1]??'')==='read-worker') {hr_actor();echo hosting_records_get($argv[2],1)->json();exit;}
try {
    foreach(schema_statements('hrci_') as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    save_setting('studio_schema_version','4');
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Hosting staff '.$i,'hosting'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    for($i=1;$i<=2;$i++)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Synthetic client '.$i,'client'.$i.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    hr_actor();$settings=all('SELECT * FROM '.table('settings').' ORDER BY name');
    hr_deny('Setup requires current password',fn()=>hosting_records_migrate('wrong'));
    hr_actor(2);hr_deny('Administrator with all grants cannot prepare owner hosting storage',fn()=>hosting_records_migrate($password));hr_actor();
    hr_deny('Read refuses unprepared storage',fn()=>hosting_records_get(hr_id(1),1));
    save_setting('hosting_records_schema_version','future');hr_deny('Future readiness is not downgraded',fn()=>hosting_records_migrate($password));query('DELETE FROM '.table('settings').' WHERE name=?',['hosting_records_schema_version']);
    db()->beginTransaction();hr_deny('Setup refuses an inherited transaction before DDL',fn()=>hosting_records_migrate($password));hr_ok('Inherited setup transaction remains owned by caller',db()->inTransaction());db()->rollBack();
    foreach(hosting_records_schema('hrci_') as $sql)db()->exec($sql);
    db()->exec('ALTER TABLE '.table('hosting_records').' ADD INDEX unexpected_revision(revision)');hr_deny('Unexpected index is refused',fn()=>hosting_records_migrate($password));db()->exec('ALTER TABLE '.table('hosting_records').' DROP INDEX unexpected_revision');
    db()->exec('ALTER TABLE '.table('hosting_records').' MODIFY journal_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');hr_deny('Nonbinary journal text is refused',fn()=>hosting_records_migrate($password));db()->exec('ALTER TABLE '.table('hosting_records').' MODIFY journal_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    db()->exec('ALTER TABLE '.table('hosting_records').' ALTER revision SET DEFAULT 0');hr_deny('Unexpected column default is refused',fn()=>hosting_records_migrate($password));db()->exec('ALTER TABLE '.table('hosting_records').' ALTER revision DROP DEFAULT');
    $fk=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['hrci_hosting_record_events','account_id'])['CONSTRAINT_NAME'];
    db()->exec('ALTER TABLE '.table('hosting_record_events').' DROP FOREIGN KEY `'.$fk.'`');
    hr_deny('Missing account relationship is refused',fn()=>hosting_records_migrate($password));
    db()->exec('ALTER TABLE '.table('hosting_record_events').' ADD FOREIGN KEY (account_id) REFERENCES '.table('hosting_records').' (id) ON DELETE CASCADE');hr_deny('Cascading history deletion is refused',fn()=>hosting_records_migrate($password));
    $fk=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['hrci_hosting_record_events','account_id'])['CONSTRAINT_NAME'];
    db()->exec('ALTER TABLE '.table('hosting_record_events').' DROP FOREIGN KEY `'.$fk.'`, ADD FOREIGN KEY (account_id) REFERENCES '.table('hosting_records').' (id) ON DELETE RESTRICT');
    $unknown=HostingJournal::create(hr_id(9),$package,'client:1',10);hr_seed($unknown);
    hr_deny('Populated storage without readiness is not adopted',fn()=>hosting_records_migrate($password));hr_ok('Rejected adoption retains unknown data',!hosting_records_ready()&&one('SELECT id FROM '.table('hosting_records').' WHERE id=?',[hr_id(9)])!==null);query('DELETE FROM '.table('hosting_records').' WHERE id=?',[hr_id(9)]);
    hr_ok('Strict setup audit failure rolls back readiness',hr_audit_failure(fn()=>hosting_records_migrate($password))&&!hosting_records_ready());
    hosting_records_migrate($password);hosting_records_migrate($password);
    hr_ok('Repeated setup is additive and empty',hosting_records_ready()&&!all('SELECT * FROM '.table('hosting_records'))&&all('SELECT * FROM '.table('settings')." WHERE name<>'hosting_records_schema_version' ORDER BY name")===$settings);
    $id=hr_id(1);$before=hr_snapshot();
    hr_deny('Create requires current password',fn()=>hosting_records_create($id,1,$package,'wrong'));
    hr_deny('Unknown client cannot receive a record',fn()=>hosting_records_create($id,999,$package,$password));
    hr_deny('Identifier trailing newline is refused',fn()=>hosting_records_create($id."\n",1,$package,$password));
    hr_ok('Strict creation audit failure rolls back record',hr_audit_failure(fn()=>hosting_records_create($id,1,$package,$password))&&hr_snapshot()===$before);
    hosting_records_create($id,1,$package,$password);$journal=hosting_records_get($id,1);$before=hr_snapshot();
    hr_ok('Created record preserves exact approved terms and has no provider attempt',$journal->account()->package->limits===['disk_gb'=>10,'sites'=>null]&&$journal->account()->package->label==='Synthetic café hosting'&&$journal->account()->status()==='pending'&&$journal->account()->pendingAttempt()===null);
    hr_ok('Exact create replay returns existing record without audit duplication',hosting_records_create($id,1,$package,$password)===$id&&hr_snapshot()===$before);
    hr_deny('Create identity cannot select another client',fn()=>hosting_records_create($id,2,$package,$password));
    hr_deny('Create identity cannot change approved terms',fn()=>hosting_records_create($id,1,new HostingPackage('HOST-2','Other','synthetic-type','synthetic-region',['disk_gb'=>20]),$password));
    hr_actor(3);hr_deny('Different owner cannot reuse creation identity',fn()=>hosting_records_create($id,1,$package,$password));hr_actor();
    hr_deny('Explicit wrong-client selection cannot load a record',fn()=>hosting_records_get($id,2));
    hr_ok('Independent PHP process reconstructs exact committed state',hr_finish(hr_worker('read-worker',[$id]))===$journal->json());
    $copy=$journal->account();$copy->cancelOrder('untrusted',time());hr_ok('Returned mutable model cannot change saved state',hosting_records_get($id,1)->account()->status()==='pending'&&hr_snapshot()===$before);
    foreach(['begin_provisioning','record_provision','retry_provision','reconcile','suspend','resume','approve_deprovision','record_deprovision','retry_deprovision','unknown'] as $command)hr_deny('Generic local API refuses '.$command,fn()=>hosting_records_change($id,1,0,hr_id(10),$command,[],$password));
    hr_deny('Canonical lifecycle refuses domain linking on pending account',fn()=>hosting_records_change($id,1,0,hr_id(10),'link_domain',['domain'=>'example.invalid'],$password));
    hr_deny('Unknown local arguments are refused',fn()=>hosting_records_change($id,1,0,hr_id(10),'cancel_order',['extra'=>1],$password));
    hr_ok('Refused transitions retain records events and audit',hr_snapshot()===$before);
    hr_ok('Strict command audit failure rolls back journal and event',hr_audit_failure(fn()=>hosting_records_change($id,1,0,hr_id(10),'cancel_order',[],$password))&&hr_snapshot()===$before);
    $journal=hosting_records_change($id,1,0,hr_id(10),'cancel_order',[],$password);$before=hr_snapshot();
    hr_ok('Local cancellation commits one journal event and matching ledger',$journal->revision()===1&&hosting_records_get($id,1)->account()->status()==='cancelled'&&count(all('SELECT id FROM '.table('hosting_record_events')))===1);
    hr_ok('Stable command replay is idempotent after revision changes',hosting_records_change($id,1,0,hr_id(10),'cancel_order',[],$password)->json()===$journal->json()&&hr_snapshot()===$before);
    hr_deny('Changed event reuse is refused',fn()=>hosting_records_change($id,1,0,hr_id(10),'close_dispute',[],$password));
    hr_deny('Changed original revision is not a replay',fn()=>hosting_records_change($id,1,1,hr_id(10),'cancel_order',[],$password));
    hr_deny('New stale event cannot overwrite later state',fn()=>hosting_records_change($id,1,0,hr_id(11),'cancel_order',[],$password));
    hr_actor(3);hr_deny('Another owner cannot replay attributed event',fn()=>hosting_records_change($id,1,0,hr_id(10),'cancel_order',[],$password));hr_actor();
    hosting_records_create(hr_id(2),1,$package,$password);hr_deny('Event identity is globally unique across accounts',fn()=>hosting_records_change(hr_id(2),1,0,hr_id(10),'cancel_order',[],$password));
    foreach([2,0] as $actor){if($actor)hr_actor($actor);else $_SESSION=[];hr_deny('Current nonowner read denied '.$actor,fn()=>hosting_records_get($id,1));hr_deny('Current nonowner create denied '.$actor,fn()=>hosting_records_create(hr_id(3),1,$package,$password));}hr_actor();
    $_GET['support']='';hr_deny('Support selection denies owner reads',fn()=>hosting_records_get($id,1));hr_deny('Support selection denies owner writes',fn()=>hosting_records_change($id,1,0,hr_id(10),'cancel_order',[],$password));unset($_GET['support']);
    $_SESSION['auth_version']=str_repeat('0',32);hr_deny('Changed authentication version denies current read',fn()=>hosting_records_get($id,1));hr_actor();
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');hr_deny('Disabled owner cannot replay a saved event',fn()=>hosting_records_change($id,1,0,hr_id(10),'cancel_order',[],$password));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');hr_actor();
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');hr_deny('Inactive client cannot receive new records',fn()=>hosting_records_create(hr_id(3),1,$package,$password));hr_deny('Inactive client blocks mutations and replay',fn()=>hosting_records_change($id,1,0,hr_id(10),'cancel_order',[],$password));hr_ok('Owner can inspect retained records for inactive client',hosting_records_get($id,1)->json()===$journal->json());query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
    db()->beginTransaction();hr_deny('Read refuses inherited transaction snapshots',fn()=>hosting_records_get($id,1));hr_deny('Write refuses inherited transaction',fn()=>hosting_records_create(hr_id(3),1,$package,$password));hr_ok('Rejected inherited read and write leave transaction open',db()->inTransaction());db()->rollBack();
    $record=hosting_records_row($id,1);$event=one('SELECT * FROM '.table('hosting_record_events').' WHERE id=?',[hr_id(10)]);
    foreach(['journal_json'=>$record['journal_json'].' ','journal_sha256'=>str_repeat('0',64),'revision'=>0,'initial_json'=>str_replace('HOST-1','HOST-2',$record['initial_json'])] as $field=>$value){
        query('UPDATE '.table('hosting_records').' SET '.$field.'=? WHERE id=?',[$value,$id]);$before=hr_snapshot();
        hr_deny('Damaged record refuses read '.$field,fn()=>hosting_records_get($id,1));hr_deny('Damaged record refuses exact replay '.$field,fn()=>hosting_records_change($id,1,0,hr_id(10),'cancel_order',[],$password));hr_ok('Damaged record remains untouched '.$field,hr_snapshot()===$before);query('UPDATE '.table('hosting_records').' SET '.$field.'=? WHERE id=?',[$record[$field],$id]);
    }
    foreach(['input_json'=>'{}','before_sha256'=>str_repeat('0',64),'after_sha256'=>str_repeat('0',64),'actor_id'=>3,'recorded_epoch'=>1] as $field=>$value){
        query('UPDATE '.table('hosting_record_events').' SET '.$field.'=? WHERE id=?',[$value,hr_id(10)]);hr_deny('Mismatched immutable event refuses adoption '.$field,fn()=>hosting_records_get($id,1));query('UPDATE '.table('hosting_record_events').' SET '.$field.'=? WHERE id=?',[$event[$field],hr_id(10)]);
    }
    query('DELETE FROM '.table('hosting_record_events').' WHERE id=?',[hr_id(10)]);hr_deny('Missing event is not reconstructed silently',fn()=>hosting_records_get($id,1));
    query('INSERT INTO '.table('hosting_record_events').' (id,account_id,revision,actor_id,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?)',array_values($event));
    hr_ok('Original intact ledger restores exact retained state',hosting_records_get($id,1)->json()===$journal->json());
    $workers=[hr_worker('change-worker',[hr_id(2),'0',hr_id(21)]),hr_worker('change-worker',[hr_id(2),'0',hr_id(22)])];$out=array_map('hr_finish',$workers);sort($out);
    hr_ok('Competing processes commit exactly one revision and reject stale writer',$out===["ready\nsaved","ready\nstale"]&&hosting_records_get(hr_id(2),1)->revision()===1);
    hosting_records_create(hr_id(6),1,$package,$password);
    $beforeCount=(int)query('SELECT COUNT(*) FROM '.table('hosting_record_events'))->fetchColumn();
    $workers=[hr_worker('change-worker',[hr_id(6),'0',hr_id(61)]),hr_worker('change-worker',[hr_id(6),'0',hr_id(61)])];$out=array_map('hr_finish',$workers);
    hr_ok('Competing identical event replay commits exactly once',$out===["ready\nsaved","ready\nsaved"]&&(int)query('SELECT COUNT(*) FROM '.table('hosting_record_events'))->fetchColumn()===$beforeCount+1&&hosting_records_get(hr_id(6),1)->revision()===1);
    hosting_records_create(hr_id(3),1,$package,$password);
    staff_locked(function()use($password):void {
        $GLOBALS['hosting_waiting_worker']=hr_worker('change-worker',[hr_id(3),'0',hr_id(31)]);
        if(trim(fgets($GLOBALS['hosting_waiting_worker'][1][1]))!=='ready')throw new RuntimeException('Worker failed to reach hosting lock');
        query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');
    });$before=hr_snapshot();
    hr_ok('Owner revoked while waiting cannot mutate after lock release',hr_finish($GLOBALS['hosting_waiting_worker'])==='denied'&&hr_snapshot()===$before);query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');hr_actor();
    $remote=hr_id(4);$at=time()-10;$synthetic=HostingJournal::create($remote,$package,'client:1',$at)->append(hr_id(40),'begin_provisioning','staff:1',$at+1);
    $unknown=$synthetic->append(hr_id(41),'record_provision','staff:1',$at+2,['result'=>['operation'=>'hosting.provision','outcome'=>'timeout','at'=>$at+2,'subject_ref'=>$remote,'receipt'=>null,'resource_ref'=>null,'error'=>null,'retryable'=>false,'attempt_ref'=>$remote.'#1']]);hr_seed($unknown);
    $restored=hosting_records_get($remote,1);hr_ok('Persisted synthetic uncertain state retains exact attempt and reconciliation requirement',$restored->account()->pendingAttempt()===$remote.'#1'&&$restored->account()->awaitingReconciliation());
    hr_deny('Stored unknown create cannot be cancelled as unused',fn()=>hosting_records_change($remote,1,2,hr_id(42),'cancel_order',[],$password));
    hr_ok('Separate process retains uncertain committed state without a new claim',hr_finish(hr_worker('read-worker',[$remote]))===$unknown->json());
    $remote=hr_id(5);$active=HostingJournal::create($remote,$package,'client:1',$at)->append(hr_id(50),'begin_provisioning','staff:1',$at+1)->append(hr_id(51),'record_provision','staff:1',$at+2,['result'=>['operation'=>'hosting.provision','outcome'=>'applied','at'=>$at+2,'subject_ref'=>$remote,'receipt'=>'synthetic-only','resource_ref'=>'synthetic-resource','error'=>null,'retryable'=>false,'attempt_ref'=>$remote.'#1']]);hr_seed($active);
    $active=hosting_records_change($remote,1,2,hr_id(52),'link_domain',['domain'=>'EXAMPLE.INVALID'],$password);hr_ok('Local domain association preserves canonical normalized value',$active->account()->primaryDomain()==='example.invalid');
    $active=hosting_records_change($remote,1,3,hr_id(53),'set_grace',['until'=>time()+3600],$password);
    $active=hosting_records_change($remote,1,4,hr_id(54),'open_dispute',[],$password);hr_ok('Local dispute and grace survive native reload',hosting_records_get($remote,1)->account()->disputeOpen()&&hosting_records_get($remote,1)->json()===$active->json());
    $active=hosting_records_change($remote,1,5,hr_id(55),'close_dispute',[],$password);hr_ok('Owner can explicitly close local dispute',!$active->account()->disputeOpen());
    $active=hosting_records_change($remote,1,6,hr_id(56),'request_cancellation',[],$password);hr_ok('Local cancellation request does not arm deletion',$active->account()->status()==='cancellation_requested'&&$active->account()->pendingAttempt()===null);
    $before=hr_snapshot();hosting_records_migrate($password);hr_ok('Repeated verified setup retains all populated hosting rows',all('SELECT id FROM '.table('hosting_records'))!==[]&&hosting_records_get($remote,1)->json()===$active->json());
    hr_ok('Audit excludes password and serialized package payload',!str_contains(json_encode(all('SELECT * FROM '.table('audit'))),$password)&&!str_contains(json_encode(all('SELECT * FROM '.table('audit'))),'HOST-1'));
    hr_ok('No provider profile or credential key created',!all('SELECT * FROM '.table('provider_profiles'))&&!is_file($private.'/provider-master.key')&&!is_file($private.'/mail-master.key'));
    require_once __DIR__.'/hosting_storage_recovery_cases.php';hr_actor();
    hosting_test_recovery('records',['hosting_records','hosting_record_events'],fn()=>hosting_records_ready(),fn()=>hosting_records_migrate($password),fn()=>hosting_records_get($remote,1),fn()=>hr_snapshot(),'hr_ok');
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/hosting-records-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB owner records and competing processes; synthetic remote state only, no provider dispatch or UI acceptance','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native hosting record checks'.PHP_EOL;exit($ok?0:1);
