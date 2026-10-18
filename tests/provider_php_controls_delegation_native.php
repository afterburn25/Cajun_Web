<?php
/** Real disposable SQL/authority/lock checks. Synthetic saved inventory only; no provider transport. */
declare(strict_types=1);
if (PHP_SAPI!=='cli' || PHP_OS_FAMILY==='Windows' || getenv('CWP_TEST_DATABASE')!=='cwp_test'
    || (getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1') exit(2);
$worker=($argv??[])!==[] && count($argv)===2 && $argv[1]==='--worker';
if (count($argv)!==($worker?2:1)) exit(2);
$portText=getenv('CWP_TEST_PORT')?:'33306';
if (!ctype_digit($portText) || (int)$portText<1 || (int)$portText>65535) exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/php_controls_authority.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventoryProjector};

const PCD_PASSWORD='Synthetic PHP delegation owner passphrase';
const PCD_CLIENT_PASSWORD='Synthetic PHP delegation client passphrase';
const PCD_SECRET='SYNTHETIC_PHP_DELEGATION_KEY';
const PCD_PREFIX='ppctl_';
$root=realpath(__DIR__.'/..');
if (!$root) exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/php-controls-delegation-private';
foreach ([$runtime,$qa] as $dir) {
    if (is_link($dir) || (file_exists($dir)&&!is_dir($dir))) exit(2);
    if (!is_dir($dir)&&!mkdir($dir,0700)) exit(2);
}
$report=$qa.'/provider-php-controls-delegation-native-results.json';
if (is_link($private) || ($worker?!is_dir($private):file_exists($private)) || (!$worker&&(is_link($report)||file_exists($report)))) exit(2);
if (!$worker&&!mkdir($private,0700)) exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/php-controls','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-php-delegation-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$portText,
    'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>PCD_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=realpath($root.'/site');start_session();
$checks=[];$stage='preflight';
function pcd_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pcd_deny(string $name,callable $fn,?string $message=null):void {
    $caught=null;try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    pcd_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function pcd_owner(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);$_GET=[];}
function pcd_client(int $id=1):void {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);$_GET=[];}
function pcd_scope(callable $fn):mixed {return staff_locked(fn()=>staff_transaction($fn));}
function pcd_ready():bool {return staff_locked(fn()=>provider_php_controls_ready());}
function pcd_count(string $name):int {return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function pcd_digest():string {
    $rows=[];foreach (['settings','audit','users','staff_profiles','studio_clients','provider_profiles','provider_package_links','provider_package_link_events','provider_refreshes','provider_refresh_results','provider_observation_packages','provider_php_control_delegations'] as $name) {
        $present=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[PCD_PREFIX.$name]);
        $values=$present?all('SELECT * FROM '.table($name)):[];
        usort($values,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$values;
    }return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
/** Intent/source rows are new synthetic observations; no retained clocks or proofs are rewritten. */
function pcd_inventory(string $profile):string {
    return pcd_scope(function()use($profile):string {
        $now=time();$r=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profile,'profile_revision'=>1,'actor_id'=>1,
            'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now,'operation'=>InventoryRead::PACKAGES_LIST];
        query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch,operation) VALUES (?,?,?,?,?,?,?)',array_values($r));
        provider_settings_audit(1,'refresh_started','Refresh '.$r['id'].' profile '.$profile.' revision 1');
        $items=[];foreach([701,702] as $id)$items[]=['id'=>$id,'name'=>'site'.$id.'.synthetic.invalid','names'=>['site'.$id.'.synthetic.invalid'],
            'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];
        $s=InventoryProjector::packagesList(new TransportResponse(200,[],json_encode($items,JSON_THROW_ON_ERROR)),$now,'twenty-i|'.$profile.'|packages.list');
        if($s->state!=='complete'||count($s->packages)!==2)throw new LogicException('Synthetic inventory projection failed.');
        provider_inventory_record_transaction($r,$s->state,$s->reason,$s->scope,'unknown',count($s->packages),$s->skipped,json_encode($s->skipReasons),0,$s,provider_inventory_fingerprint(PCD_SECRET));return $r['id'];
    });
}
function pcd_review(string $link,bool $enabled):array {return pcd_scope(fn()=>provider_php_controls_delegation_review($link,$enabled));}
function pcd_delegate(string $link,bool $enabled):array {$r=pcd_review($link,$enabled);return provider_php_controls_delegate($link,$r['permission_id'],bin2hex(random_bytes(16)),$enabled,$r['review_sha256'],PCD_PASSWORD);}
/** Damage is restricted to this fixture's rows, and its exact transaction is always rolled back. */
function pcd_damage(string $name,callable $mutate,?callable $read=null):void {
    $before=pcd_digest();$session=$_SESSION;
    staff_locked(function()use($name,$mutate,$read):void {db()->beginTransaction();try{$mutate();pcd_deny($name,$read??fn()=>provider_php_controls_ready());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);pcd_ok($name.' exact rows restored',pcd_digest()===$before&&pcd_ready());
}
/** Real DB trigger failure, never accepted as an ordinary authorization denial. */
function pcd_fault(string $action,callable $fn):bool {
    if(!in_array($action,['php_control_delegation_started','php_control_delegation_prepared','php_control_delegation_changed'],true))throw new LogicException('Unknown fault.');
    $insertWitness=$action==='php_control_delegation_changed'?'SET @ppctl_fault_rows=(SELECT COUNT(*) FROM '.table('provider_php_control_delegations').'); ':'';
    db()->exec('CREATE TRIGGER ppctl_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.".$action."' THEN SET @ppctl_fault_hits=COALESCE(@ppctl_fault_hits,0)+1; ".$insertWitness."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic delegation audit fault'; END IF; END");
    query('SET @ppctl_fault_hits=0,@ppctl_fault_rows=0');$hit=false;
    try{$fn();}catch(PDOException $e){$hit=$e->getCode()==='45000'&&(int)query('SELECT @ppctl_fault_hits')->fetchColumn()===1&&($action!=='php_control_delegation_changed'||(int)query('SELECT @ppctl_fault_rows')->fetchColumn()===1);}
    finally{db()->exec('DROP TRIGGER ppctl_fault');}return $hit;
}
function pcd_child(array $input):array {
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start isolated lock worker.');
    fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    $out='';$err='';$deadline=microtime(true)+15;$exit=null;
    try{do{$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);if(strlen($out)>8192||strlen($err)>8192)throw new RuntimeException('Worker output bound.');$status=proc_get_status($process);if(!$status['running']){$exit=$status['exitcode'];break;}if(microtime(true)>$deadline)throw new RuntimeException('Worker deadline.');usleep(20000);}while(true);$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);}
    finally{$status=proc_get_status($process);if($status['running'])proc_terminate($process);fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($process);if($exit===null)$exit=$closed;}
    if($exit!==0||$err!==''||strlen($out)>8192)throw new RuntimeException('Isolated worker failed.');return json_decode($out,true,8,JSON_THROW_ON_ERROR);
}
if($worker){
    try{$input=json_decode(stream_get_contents(STDIN,4097),true,8,JSON_THROW_ON_ERROR);
        if(!is_array($input)||array_keys($input)!==['link','previous','event','review','owner_connection','expect_block']||!is_bool($input['expect_block'])||!is_int($input['owner_connection']))exit(2);
        pcd_owner();$key='cwp-staff-'.substr(hash('sha256','cwp_test|'.PCD_PREFIX),0,40);
        $other=(int)query('SELECT CONNECTION_ID()')->fetchColumn()!==$input['owner_connection'];
        $owns=(int)query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn()===$input['owner_connection'];$before=pcd_digest();$blocked=false;$result=null;
        try{$result=provider_php_controls_delegate($input['link'],$input['previous'],$input['event'],true,$input['review'],PCD_PASSWORD);}
        catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$blocked=$e->getMessage()==='Another account change is in progress. Please retry.';if(!$blocked)throw $e;}
        $ok=$other&&pcd_digest()===$before&&($input['expect_block']?($owns&&$blocked):(!$blocked&&($result['document']['id']??null)===$input['event']));
        echo json_encode(['passed'=>$ok,'different_connection'=>$other,'parent_owns_lock'=>$owns,'blocked'=>$blocked,'unchanged'=>pcd_digest()===$before,'replayed_id'=>$result['document']['id']??null]);exit($ok?0:1);
    }catch(Throwable $e){fwrite(STDERR,'Worker refused: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);exit(1);}
}

try {
    pcd_ok('Disposable database and new namespace verified',query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&
        !one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? LIMIT 1',[strlen(PCD_PREFIX),PCD_PREFIX]));
    $stage='prerequisites';$base=schema_statements(PCD_PREFIX);
    foreach(['settings','users','audit','rate_limits','studio_clients','staff_profiles'] as $name)db()->exec($base[$name]);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','2');
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['PHP staff '.$id,'php-delegation-staff'.$id.'@example.invalid',password_hash(PCD_PASSWORD,PASSWORD_DEFAULT),str_repeat(dechex($id+9),32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,'[]'),(3,'owner',1,'[]')");
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['PHP client '.$id,'php-delegation-client'.$id.'@example.invalid',password_hash(PCD_CLIENT_PASSWORD,PASSWORD_DEFAULT),str_repeat(dechex($id+12),32),gmdate('Y-m-d H:i:s')]);
    pcd_owner();provider_settings_migrate(PCD_PASSWORD);provider_inventory_migrate(PCD_PASSWORD);provider_php_prepare(PCD_PASSWORD);
    $profile=provider_settings_save('',0,'Synthetic PHP delegation',true,PCD_SECRET,PCD_PASSWORD);
    pcd_ok('Production vault retains real POSIX private key permissions',(fileperms($private.'/provider-master.key')&0777)===0600);
    pcd_ok('Unprepared read returns false without creating delegation table',!pcd_ready()&&!one('SHOW TABLES LIKE ?',[PCD_PREFIX.'provider_php_control_delegations']));
    pcd_deny('Readiness without staff mutex refuses',fn()=>provider_php_controls_ready());
    staff_locked(fn()=>pcd_deny('Context without a transaction refuses',fn()=>provider_php_controls_owner_context(str_repeat('a',32))));
    db()->beginTransaction();pcd_deny('Preparation refuses inherited transaction before DDL',fn()=>provider_php_controls_prepare(PCD_PASSWORD));pcd_ok('Caller transaction remains active after refusal',db()->inTransaction());db()->rollBack();
    pcd_deny('Wrong preparation password refuses',fn()=>provider_php_controls_prepare('wrong'));pcd_owner(2);pcd_deny('Non-owner staff cannot prepare delegation',fn()=>provider_php_controls_prepare(PCD_PASSWORD));pcd_owner();
    $stage='preparation';$before=pcd_digest();pcd_ok('Preparation-start audit fault rolls back marker',pcd_fault('php_control_delegation_started',fn()=>provider_php_controls_prepare(PCD_PASSWORD))&&pcd_digest()===$before&&!pcd_ready());
    pcd_ok('Final preparation audit fault is actually reached',pcd_fault('php_control_delegation_prepared',fn()=>provider_php_controls_prepare(PCD_PASSWORD)));
    pcd_ok('Interrupted preparation retains only recognized empty state',one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_delegation_schema_version'])['value']==='preparing-1'&&pcd_count('provider_php_control_delegations')===0&&!one('SELECT name FROM '.table('settings').' WHERE name=?',['provider_php_control_delegation_count']));
    pcd_deny('Interrupted preparation refuses normal readiness',fn()=>pcd_ready());
    db()->exec('ALTER TABLE '.table('provider_php_control_delegations').' ADD synthetic_extra INT NULL');
    try{pcd_deny('Recognized preparing marker does not adopt malformed schema',fn()=>provider_php_controls_prepare(PCD_PASSWORD));pcd_ok('Malformed preparing schema is not repaired automatically',one('SHOW COLUMNS FROM '.table('provider_php_control_delegations')." LIKE 'synthetic_extra'")!==null);}
    finally{db()->exec('ALTER TABLE '.table('provider_php_control_delegations').' DROP COLUMN synthetic_extra');}
    provider_php_controls_prepare(PCD_PASSWORD);$before=pcd_digest();provider_php_controls_prepare(PCD_PASSWORD);
    pcd_ok('Explicit retry resumes exact empty preparation once',pcd_ready()&&pcd_digest()===$before&&provider_php_controls_counter()===0);
    pcd_damage('Unknown extension marker refuses',fn()=>save_setting('provider_php_control_delegation_schema_version','2'));
    pcd_damage('Missing marker cannot adopt surviving table or audit',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_php_control_delegation_schema_version']));
    pcd_damage('Unknown extension setting refuses',fn()=>save_setting('provider_php_control_delegation_unknown','1'));
    pcd_damage('Unknown extension audit refuses',fn()=>provider_settings_audit(1,'php_control_delegation_unknown','Synthetic unknown'));
    pcd_damage('Missing preparation witness refuses',fn()=>query('DELETE FROM '.table('audit').' WHERE action=?',['provider.php_control_delegation_prepared']));
    $exactTable=table('provider_php_control_delegations');db()->exec('RENAME TABLE '.$exactTable.' TO `ppctl_retained_delegations`');
    try{pcd_deny('Marked missing table refuses preparation instead of recreation',fn()=>provider_php_controls_prepare(PCD_PASSWORD));pcd_ok('Missing marked table is not recreated',!one('SHOW TABLES LIKE ?',[PCD_PREFIX.'provider_php_control_delegations']));}
    finally{db()->exec('RENAME TABLE `ppctl_retained_delegations` TO '.$exactTable);}pcd_ok('Exact table restoration restores readiness',pcd_ready());
    db()->exec('CREATE TABLE `ppctl_provider_php_control_delegation_unknown` (id INT PRIMARY KEY) ENGINE=InnoDB');
    try{pcd_deny('Unknown extension table refuses readiness',fn()=>pcd_ready());}finally{db()->exec('DROP TABLE `ppctl_provider_php_control_delegation_unknown`');}
    $stage='assignment';pcd_inventory($profile);provider_inventory_link($profile,701,1,1,PCD_PASSWORD);provider_inventory_link($profile,702,2,1,PCD_PASSWORD);
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=701')['id'];$other=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=702')['id'];
    pcd_deny('Enable requires inventory after assignment',fn()=>pcd_review($link,true));$source=pcd_inventory($profile);
    pcd_client();pcd_deny('Assignment and saved-value sharing schema do not enable changes',fn()=>pcd_scope(fn()=>provider_php_controls_client_context(1,$link)));pcd_owner();
    $review=pcd_review($link,true);$event=bin2hex(random_bytes(16));
    pcd_deny('Delegation wrong password refuses',fn()=>provider_php_controls_delegate($link,'',$event,true,$review['review_sha256'],'wrong'));
    $before=pcd_digest();pcd_ok('Real changed-audit fault rolls back row counter and audit',pcd_fault('php_control_delegation_changed',fn()=>provider_php_controls_delegate($link,'',$event,true,$review['review_sha256'],PCD_PASSWORD))&&pcd_digest()===$before&&pcd_ready());
    $permission=provider_php_controls_delegate($link,'',$event,true,$review['review_sha256'],PCD_PASSWORD);$before=pcd_digest();
    $again=provider_php_controls_delegate($link,'',$event,true,$review['review_sha256'],PCD_PASSWORD);
    pcd_ok('Exact latest-event replay preserves all durable rows',$again['document']===$permission['document']&&$again['sha256']===$permission['sha256']&&pcd_digest()===$before);
    pcd_deny('Reused event cannot change enabled state',fn()=>provider_php_controls_delegate($link,'',$event,false,$review['review_sha256'],PCD_PASSWORD));
    pcd_deny('Stale previous event cannot append',fn()=>provider_php_controls_delegate($link,'',bin2hex(random_bytes(16)),true,$review['review_sha256'],PCD_PASSWORD));
    pcd_ok('Delegation leaves old saved-value sharing unchanged',pcd_count('provider_php_permissions')===0);
    $ownerContext=pcd_scope(fn()=>provider_php_controls_owner_context($link));pcd_client();$clientContext=pcd_scope(fn()=>provider_php_controls_client_context(1,$link));
    pcd_ok('Current client context has real client actor and exact permission',$clientContext['document']['actor']['kind']==='client'&&$clientContext['document']['actor']['id']===1&&$clientContext['document']['permission_id']===$event&&$clientContext['binding']->data['source_id']===$source);
    pcd_ok('Current client password uses the actual primary account',pcd_scope(fn()=>provider_php_controls_client_password(1,PCD_CLIENT_PASSWORD))['id']==1);
    pcd_deny('Wrong primary client password refuses',fn()=>pcd_scope(fn()=>provider_php_controls_client_password(1,'wrong')));
    pcd_deny('Client cannot select sibling assignment',fn()=>pcd_scope(fn()=>provider_php_controls_client_context(1,$other)));
    pcd_client(2);pcd_deny('Other client cannot claim original context',fn()=>pcd_scope(fn()=>provider_php_controls_reauthorize($clientContext)));pcd_client();
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;pcd_deny('Retained contact marker refuses before normalization '.$key,fn()=>pcd_scope(fn()=>provider_php_controls_client_context(1,$link)));pcd_ok('Contact marker was not silently normalized '.$key,array_key_exists($key,$_SESSION));unset($_SESSION[$key]);}
    $_GET['support']='synthetic-context';pcd_deny('Support selector refuses primary-client context',fn()=>pcd_scope(fn()=>provider_php_controls_client_context(1,$link)));$_GET=[];
    pcd_owner();support_migrate(PCD_PASSWORD);support_policy(1,true,PCD_PASSWORD);$support=support_start(1);$GLOBALS['cwp_support']=support_resolve($support);
    pcd_ok('Actual support session resolves before denial',support_current()!==null);
    pcd_deny('Actual owner support session cannot use client change context',fn()=>pcd_scope(fn()=>provider_php_controls_client_context(1,$link)));
    support_end($support);pcd_owner();
    pcd_client();
    foreach(['active'=>0,'password_hash'=>'','auth_version'=>str_repeat('f',32)] as $column=>$value)pcd_damage('Changed client '.$column.' refuses current authority',fn()=>query('UPDATE '.table('studio_clients').' SET '.$column.'=? WHERE id=1',[$value]),fn()=>provider_php_controls_reauthorize($clientContext));
    pcd_damage('Changed owner auth version revokes delegated context',fn()=>query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('b',32)]),fn()=>provider_php_controls_client_context(1,$link));
    pcd_damage('Changed owner role revokes delegated context',fn()=>query('UPDATE '.table('staff_profiles')." SET role='administrator' WHERE user_id=1"),fn()=>provider_php_controls_client_context(1,$link));
    pcd_damage('Disabled owner revokes delegated context',fn()=>query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1'),fn()=>provider_php_controls_client_context(1,$link));
    foreach(['enabled'=>0,'revision'=>2,'credentials'=>'different-sealed-context'] as $column=>$value)pcd_damage('Changed provider '.$column.' revokes delegated context',fn()=>query('UPDATE '.table('provider_profiles').' SET '.$column.'=? WHERE id=?',[$value,$profile]),fn()=>provider_php_controls_client_context(1,$link));
    pcd_owner();$stage='locks';$input=['link'=>$link,'previous'=>'','event'=>$event,'review'=>$review['review_sha256'],'owner_connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn(),'expect_block'=>true];
    $blocked=staff_locked(fn()=>pcd_child($input));pcd_ok('Second real PDO connection observes owned mutex and cannot replay',$blocked['passed']===true&&$blocked['different_connection']===true&&$blocked['parent_owns_lock']===true&&$blocked['blocked']===true&&$blocked['unchanged']===true);
    $input['expect_block']=false;$replay=pcd_child($input);pcd_ok('After release separate connection replays exact event without insert',$replay['passed']===true&&!$replay['blocked']&&$replay['replayed_id']===$event&&$replay['unchanged']===true);
    pcd_deny('Context reauthorization rejects changed hash',fn()=>pcd_scope(fn()=>provider_php_controls_reauthorize(array_replace($ownerContext,['sha256'=>str_repeat('0',64)]))));
    $stage='history';
    pcd_damage('Lost delegation counter refuses',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_php_control_delegation_count']));
    pcd_damage('Missing delegation row refuses surviving count and audit',fn()=>query('DELETE FROM '.table('provider_php_control_delegations').' WHERE id=?',[$event]));
    pcd_damage('Missing delegation audit refuses retained row',fn()=>query('DELETE FROM '.table('audit').' WHERE action=?',['provider.php_control_delegation_changed']));
    pcd_damage('Paired row and audit loss refuses independent count',function()use($event):void{query('DELETE FROM '.table('provider_php_control_delegations').' WHERE id=?',[$event]);query('DELETE FROM '.table('audit').' WHERE action=?',['provider.php_control_delegation_changed']);});
    pcd_damage('Changed canonical document refuses hash proof',fn()=>query('UPDATE '.table('provider_php_control_delegations').' SET document_json=? WHERE id=?',['{}',$event]));
    pcd_damage('Changed retained HMAC refuses',fn()=>query('UPDATE '.table('provider_php_control_delegations').' SET proof=? WHERE id=?',[str_repeat('0',64),$event]));
    pcd_damage('Changed audit actor refuses',fn()=>query('UPDATE '.table('audit').' SET user_id=3 WHERE action=?',['provider.php_control_delegation_changed']));
    pcd_damage('Lost original assignment witness refuses',fn()=>query('DELETE FROM '.table('provider_package_link_events').' WHERE id=?',[$permission['binding']->data['event_id']]));
    pcd_damage('Nonempty preparing state cannot become ready',fn()=>save_setting('provider_php_control_delegation_schema_version','preparing-1'));
    $stage='revocation';$off=pcd_delegate($link,false);pcd_client();pcd_deny('Explicit owner revocation immediately removes change authority',fn()=>pcd_scope(fn()=>provider_php_controls_client_context(1,$link)));pcd_owner();$permission=pcd_delegate($link,true);
    pcd_client();$current=pcd_scope(fn()=>provider_php_controls_client_context(1,$link));pcd_owner();pcd_inventory($profile);pcd_client();
    $fresh=pcd_scope(fn()=>provider_php_controls_client_context(1,$link));pcd_ok('Later valid inventory retains delegation with new context',$fresh['document']['permission_id']===$permission['document']['id']&&$fresh['sha256']!==$current['sha256']);
    pcd_deny('Prior read context cannot be silently reused',fn()=>pcd_scope(fn()=>provider_php_controls_reauthorize($current)));pcd_owner();
    provider_inventory_relink($link,1,PCD_PASSWORD);pcd_inventory($profile);pcd_client();pcd_deny('Owner assignment reapproval requires new explicit delegation',fn()=>pcd_scope(fn()=>provider_php_controls_client_context(1,$link)));pcd_owner();$permission=pcd_delegate($link,true);
    provider_inventory_unlink($link,PCD_PASSWORD);pcd_client();pcd_deny('Unlinked package loses client change authority',fn()=>pcd_scope(fn()=>provider_php_controls_client_context(1,$link)));pcd_owner();$revoked=pcd_delegate($link,false);
    pcd_ok('Owner can revoke retained delegation after unlink',$revoked['document']['enabled']===false&&pcd_ready());
    provider_inventory_link($profile,701,2,1,PCD_PASSWORD);pcd_inventory($profile);$reassigned=one('SELECT id FROM '.table('provider_package_links').' WHERE package_id=701')['id'];pcd_client(2);
    pcd_deny('Reassigned client inherits no old delegation',fn()=>pcd_scope(fn()=>provider_php_controls_client_context(2,$reassigned)));pcd_owner();
    $public=json_encode([all('SELECT * FROM '.table('provider_php_control_delegations')),all('SELECT * FROM '.table('audit'))],JSON_THROW_ON_ERROR);
    pcd_ok('Delegation and audit retain no raw credential or passwords',!str_contains($public,PCD_SECRET)&&!str_contains($public,PCD_PASSWORD)&&!str_contains($public,PCD_CLIENT_PASSWORD));
    pcd_ok('No PHP provider read or write was executed',pcd_count('provider_php_reads')===0&&!one('SHOW TABLES LIKE ?',[PCD_PREFIX.'provider_operations']));
    pcd_ok('Final delegation history and original observation schema remain ready',pcd_ready()&&provider_php_ready());
}catch(Throwable $e){$checks[]=['name'=>'Unexpected native delegation failure','passed'=>false];$failure=['stage'=>$stage,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()];fwrite(STDERR,json_encode($failure,JSON_THROW_ON_ERROR).PHP_EOL);}
$passed=count(array_filter($checks,fn($row)=>$row['passed']!==true))===0;
$result=['passed'=>$passed,'scope'=>'Actual disposable SQL, retained synthetic inventory, real owner/client/delegation and second-connection locks. No provider transport, choice schema, PHP send, HTTP or browser acceptance.','checks'=>$checks,'failure'=>$failure??null];
$bytes=json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$handle=fopen($report,'xb');if(!$handle)exit(1);$written=fwrite($handle,$bytes);fclose($handle);if($written!==strlen($bytes))exit(1);
echo ($passed?'PASS':'FAIL').': '.count($checks).' native PHP delegation checks'.PHP_EOL;exit($passed?0:1);
