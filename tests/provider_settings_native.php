<?php
/** Disposable owner/vault/database fixtures. No provider transport or external calls. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/providers/settings.php';
$private=__DIR__.'/../test-runtime/provider-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-provider-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'prci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic provider owner passphrase';$secret=' Synthetic General key +/= ';
function pr_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pr_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;pr_ok($name,true);return;}pr_ok($name,false);}
function pr_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function pr_snapshot():string {return json_encode([all('SELECT * FROM '.table('provider_profiles').' ORDER BY id'),all('SELECT * FROM '.table('provider_revisions').' ORDER BY revision'),all('SELECT * FROM '.table('audit').' ORDER BY id')]);}
function pr_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER prci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic provider audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER prci_fail');}return $failed;
}
function pr_worker(string $mode,array $args=[]):array {
    $p=proc_open([PHP_BINARY,__FILE__,$mode,...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start provider fixture worker');
    foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);return [$p,$pipes];
}
function pr_finish(array $worker):string {
    [$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0||$error!=='')throw new RuntimeException('Provider fixture worker failed: '.$error);return $out;
}
if(($argv[1]??'')==='save-worker') {
    pr_actor();echo "ready\n";fflush(STDOUT);
    try{provider_settings_save($argv[2],(int)$argv[3],$argv[4],true,'',$password);echo 'saved';}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
try {
    foreach(schema_statements('prci_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Provider staff '.$i,'provider'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    pr_actor();$settings=all('SELECT * FROM '.table('settings').' ORDER BY name');
    pr_deny('Setup refuses revoked owner authority without changing stored state',fn()=>cwp_test_owner_revoked(fn()=>provider_settings_migrate('')));
    pr_actor(2);pr_deny('All administrator permissions do not confer owner setup',fn()=>provider_settings_migrate($password));pr_actor();
    pr_deny('Profile access refuses unprepared storage',fn()=>provider_settings_profile());
    save_setting('provider_settings_schema_version','future');pr_deny('Future readiness marker is not downgraded',fn()=>provider_settings_migrate($password));query('DELETE FROM '.table('settings').' WHERE name=?',['provider_settings_schema_version']);
    foreach(provider_settings_schema('prci_') as $sql)db()->exec($sql);
    db()->exec('ALTER TABLE '.table('provider_profiles').' ADD INDEX unexpected_label(label)');pr_deny('Extra index is refused without changing storage',fn()=>provider_settings_migrate($password));db()->exec('ALTER TABLE '.table('provider_profiles').' DROP INDEX unexpected_label');
    db()->exec('ALTER TABLE '.table('provider_profiles').' MODIFY provider_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');pr_deny('Nonbinary provider identity is refused',fn()=>provider_settings_migrate($password));db()->exec('ALTER TABLE '.table('provider_profiles').' MODIFY provider_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    db()->exec('ALTER TABLE '.table('provider_profiles').' ALTER enabled SET DEFAULT 1');pr_deny('Unexpected enabled default is refused',fn()=>provider_settings_migrate($password));db()->exec('ALTER TABLE '.table('provider_profiles').' ALTER enabled DROP DEFAULT');
    db()->exec('ALTER TABLE '.table('provider_profiles').' DROP INDEX provider_environment');pr_deny('Missing provider identity uniqueness is refused',fn()=>provider_settings_migrate($password));db()->exec('ALTER TABLE '.table('provider_profiles').' ADD UNIQUE provider_environment(provider_code,environment)');
    $fk=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['prci_provider_profiles','updated_by'])['CONSTRAINT_NAME'];
    db()->exec('ALTER TABLE '.table('provider_profiles').' DROP FOREIGN KEY `'.$fk.'`');pr_deny('Missing actor relationship is refused',fn()=>provider_settings_migrate($password));db()->exec('ALTER TABLE '.table('provider_profiles').' ADD FOREIGN KEY (updated_by) REFERENCES '.table('users').' (id) ON DELETE CASCADE');pr_deny('Cascading actor deletion is refused',fn()=>provider_settings_migrate($password));
    $fk=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['prci_provider_profiles','updated_by'])['CONSTRAINT_NAME'];db()->exec('ALTER TABLE '.table('provider_profiles').' DROP FOREIGN KEY `'.$fk.'`, ADD FOREIGN KEY (updated_by) REFERENCES '.table('users').' (id) ON DELETE RESTRICT');
    $unknown=str_repeat('a',32);query('INSERT INTO '.table('provider_profiles')." (id,provider_code,environment,revision,label,credentials,enabled,updated_by,updated_at) VALUES (?,'twenty-i','live',1,'Unknown','',0,1,?)",[$unknown,gmdate('Y-m-d H:i:s')]);
    pr_deny('Unrecognized populated storage is not adopted',fn()=>provider_settings_migrate($password));pr_ok('Rejected adoption preserves unknown row and disabled readiness',!provider_settings_ready()&&provider_settings_load($unknown)['label']==='Unknown');query('DELETE FROM '.table('provider_profiles').' WHERE id=?',[$unknown]);
    pr_ok('Strict setup audit rolls back readiness',pr_audit_failure(fn()=>provider_settings_migrate($password))&&!provider_settings_ready());
    provider_settings_migrate($password);provider_settings_migrate($password);
    pr_ok('Repeated setup is additive and profile remains empty',provider_settings_ready()&&provider_settings_profile()===null&&all('SELECT * FROM '.table('settings')." WHERE name<>'provider_settings_schema_version' ORDER BY name")===$settings);
    pr_deny('Initial profile requires a General key',fn()=>provider_settings_save('',0,'20i',true,'',$password));
    pr_deny('Provider save rechecks current owner role and preserves state on refusal',fn()=>cwp_test_owner_revoked(fn()=>provider_settings_save('',0,'20i',true,$secret,'')));
    foreach(['',str_repeat('x',121),"bad\nlabel","\xFF"] as $n=>$label)pr_deny('Invalid label rejected '.$n,fn()=>provider_settings_save('',0,$label,true,$secret,$password));
    foreach(["key\nInjected",str_repeat('x',4097),"\xFF"] as $n=>$value)pr_deny('Invalid General key rejected '.$n,fn()=>provider_settings_save('',0,'20i',true,$value,$password));
    $storage=config()['storage'];$GLOBALS['cwp_config']['storage']=CWP_ROOT;pr_deny('Provider key cannot be created in served root',fn()=>provider_settings_save('',0,'20i',true,$secret,$password));$GLOBALS['cwp_config']['storage']=$storage;
    $id=provider_settings_save('',0,'Studio 20i',false,$secret,$password);$row=provider_settings_load($id);$cipher=$row['credentials'];$key=$storage.'/provider-master.key';$original=file_get_contents($key);
    pr_ok('Raw General key is encrypted with whitespace retained',!str_contains($cipher,$secret)&&provider_settings_unseal($id,'live',$cipher)===$secret);
    pr_ok('Provider key is private and independently named',strlen($original)===32&&(fileperms($key)&0777)===0600&&!is_file($storage.'/mail-master.key')&&!is_file($storage.'/payment-master.key'));
    $projection=provider_settings_profile();pr_ok('Safe projection excludes credential material',!array_key_exists('credentials',$projection)&&$projection['has_credentials']===true&&!str_contains(json_encode($projection),$secret)&&!str_contains(json_encode($projection),$cipher));
    pr_ok('Revision history retains metadata only',count(provider_settings_history($id))===1&&!str_contains(json_encode(provider_settings_history($id)),$secret)&&!str_contains(json_encode(provider_settings_history($id)),$cipher));
    pr_deny('Only one current 20i live profile can exist',fn()=>provider_settings_save('',0,'Duplicate',true,$secret,$password));
    pr_deny('Ciphertext cannot move to another profile',fn()=>provider_settings_unseal(str_repeat('f',32),'live',$cipher));
    pr_deny('Invented sandbox environment is refused',fn()=>provider_settings_unseal($id,'sandbox',$cipher));
    $GLOBALS['cwp_config']['db']['prefix']='other_';pr_deny('Ciphertext cannot move to another installation prefix',fn()=>provider_settings_unseal($id,'live',$cipher));$GLOBALS['cwp_config']['db']['prefix']='prci_';
    $raw=base64_decode($cipher);$raw[30]=chr(ord($raw[30])^1);pr_deny('Authenticated encryption rejects altered bytes',fn()=>provider_settings_unseal($id,'live',base64_encode($raw)));
    pr_deny('Noncanonical credential encoding is refused',fn()=>provider_settings_unseal($id,'live',$cipher."\n"));
    rename($key,$key.'.held');pr_deny('Lost key is not regenerated with saved credentials',fn()=>provider_settings_save($id,1,'20i',true,'',$password));pr_ok('Lost key leaves profile and ciphertext intact',!is_file($key)&&provider_settings_load($id)===$row);rename($key.'.held',$key);
    chmod($key,0644);pr_deny('Insecure key modes are refused',fn()=>provider_settings_unseal($id,'live',$cipher));chmod($key,0600);
    file_put_contents($key,str_repeat('z',32));pr_deny('Wrong key cannot decrypt stored credential',fn()=>provider_settings_unseal($id,'live',$cipher));file_put_contents($key,$original);
    file_put_contents($key,'short');pr_deny('Truncated master key is refused',fn()=>provider_settings_unseal($id,'live',$cipher));file_put_contents($key,$original);
    rename($key,$key.'.held');symlink($key.'.held',$key);pr_deny('Symbolic-link master key is refused',fn()=>provider_settings_unseal($id,'live',$cipher));unlink($key);rename($key.'.held',$key);
    pr_actor(2);pr_deny('Administrator cannot read profile metadata',fn()=>provider_settings_profile());pr_deny('Administrator cannot read history',fn()=>provider_settings_history($id));pr_deny('Administrator cannot replace key',fn()=>provider_settings_save($id,1,'20i',true,$secret,$password));pr_deny('Administrator cannot enable connection',fn()=>provider_settings_state($id,1,'enable',$password));
    pr_actor(3);pr_ok('Another current owner can read the shared profile',provider_settings_profile()['id']===$id);pr_actor();
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');pr_deny('Disabling owner applies to an existing session immediately',fn()=>provider_settings_profile());query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pr_actor();
    $_SESSION['auth_version']='stale';pr_deny('Revoked session cannot read provider settings',fn()=>provider_settings_profile());pr_actor();
    $_SESSION['last_activity']=time()-1801;pr_deny('Idle session cannot read provider settings',fn()=>provider_settings_profile());pr_actor();
    $_GET['support']='fixture';pr_deny('Client support context cannot access owner settings',fn()=>provider_settings_profile());unset($_GET['support']);
    $_SESSION=['client_id'=>1,'client_auth'=>'synthetic'];pr_deny('Client identity cannot access provider settings',fn()=>provider_settings_profile());pr_actor();
    pr_deny('Stale save cannot overwrite newer profile',fn()=>provider_settings_save($id,0,'Stale',true,$secret,$password));
    provider_settings_save($id,1,'Renamed 20i',false,'',$password);pr_ok('Blank edit retains raw key and increments revision',provider_settings_unseal($id,'live',provider_settings_load($id)['credentials'])===$secret&&provider_settings_profile()['revision']===2);
    $nextSecret='Replacement synthetic General key';provider_settings_save($id,2,'Rotated 20i',true,$nextSecret,$password);pr_ok('Explicit replacement rotates encrypted credential',provider_settings_unseal($id,'live',provider_settings_load($id)['credentials'])===$nextSecret&&provider_settings_profile()['revision']===3);
    $before=pr_snapshot();pr_ok('Audit failure rolls back save and revision history',pr_audit_failure(fn()=>provider_settings_save($id,3,'Failed audit',true,$secret,$password))&&pr_snapshot()===$before);
    pr_ok('Audit failure rolls back state and revision history',pr_audit_failure(fn()=>provider_settings_state($id,3,'disable',$password))&&pr_snapshot()===$before);
    pr_deny('Provider state change refuses revoked owner role without changing records',fn()=>cwp_test_owner_revoked(fn()=>provider_settings_state($id,3,'disable','')));
    pr_deny('Stale state form cannot change connection',fn()=>provider_settings_state($id,2,'disable',$password));
    pr_deny('Unknown state action is refused',fn()=>provider_settings_state($id,3,'delete',$password));
    provider_settings_state($id,3,'disable',$password);pr_ok('Disable preserves encrypted credential',provider_settings_profile()['enabled']===0&&provider_settings_unseal($id,'live',provider_settings_load($id)['credentials'])===$nextSecret);
    provider_settings_state($id,4,'enable',$password);pr_ok('Enable requires an intact saved key',provider_settings_profile()['enabled']===1);
    provider_settings_state($id,5,'clear',$password);pr_ok('Explicit clear disables connection and removes saved key',provider_settings_profile()['enabled']===0&&!provider_settings_profile()['has_credentials']&&provider_settings_load($id)['credentials']==='');
    pr_deny('Cleared profile cannot enable without new key',fn()=>provider_settings_state($id,6,'enable',$password));
    provider_settings_save($id,6,'Restored 20i',true,$secret,$password);
    $workers=[pr_worker('save-worker',[$id,'7','Concurrent first']),pr_worker('save-worker',[$id,'7','Concurrent second'])];$out=array_map('pr_finish',$workers);sort($out);
    pr_ok('Two concurrent edits commit exactly one new revision',$out===["ready\nsaved","ready\nstale"]&&provider_settings_profile()['revision']===8&&count(provider_settings_history($id))===8);
    $before=pr_snapshot();staff_locked(function()use($id):void {
        $worker=pr_worker('save-worker',[$id,'8','Revoked while waiting']);pr_ok('Blocked writer reaches authority boundary',trim(fgets($worker[1][1]))==='ready');
        query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');$GLOBALS['provider_waiting_worker']=$worker;
    });pr_ok('Owner revoked while waiting cannot write after lock release',pr_finish($GLOBALS['provider_waiting_worker'])==='denied'&&pr_snapshot()===$before);query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pr_actor();
    for($revision=8;$revision<23;$revision++)provider_settings_save($id,$revision,'History '.$revision,true,'',$password);
    $first=provider_settings_history($id);$older=provider_settings_history($id,2);pr_ok('History pagination preserves distinct revisions',count($first)===21&&count($older)===3&&!array_intersect(array_column(array_slice($first,0,20),'revision'),array_column($older,'revision')));
    $audit=json_encode(all('SELECT * FROM '.table('audit')));pr_ok('Durable audit contains no raw key ciphertext or encoding',!str_contains($audit,$secret)&&!str_contains($audit,$nextSecret)&&!str_contains($audit,$cipher)&&!str_contains($audit,base64_encode($secret)));
    pr_ok('Provider changes preserve original master key',file_get_contents($key)===$original);
    pr_ok('Existing mail configuration remains unchanged',setting('email_mode')==='disabled'&&setting('smtp_host')===''&&setting('smtp_password')==='');
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-settings-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB owner storage, key and process fixtures; no provider call or application UI acceptance','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native provider settings checks'.PHP_EOL;exit($ok?0:1);
