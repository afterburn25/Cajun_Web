<?php
/** Disposable database/account fixtures. No IMAP, SMTP or external credential use. */
declare(strict_types=1);
if (PHP_SAPI!=='cli' || getenv('CWP_TEST_DATABASE')!=='cwp_test') exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='maci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic email account owner passphrase';
function ma_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if (!$ok) throw new RuntimeException($name);}
function ma_deny(string $name,callable $fn): void {try {$fn();} catch (InvalidArgumentException|RuntimeException $e) {if ($e instanceof PDOException) throw $e;ma_ok($name,true);return;}ma_ok($name,false);}
function ma_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function ma_input(string $label='Synthetic support account'): array {return ['label'=>$label,'address'=>'support@example.invalid','imap_host'=>'imap.example.invalid','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic-incoming','smtp_host'=>'smtp.example.invalid','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic-outgoing'];}
function ma_secrets(): array {return ['imap_password'=>' Synthetic incoming secret ','smtp_password'=>'Synthetic outgoing secret'];}
function ma_rows(): array {return all('SELECT * FROM '.table('mail_accounts').' ORDER BY id');}
function ma_audit_failure(callable $fn): bool {
    db()->exec('CREATE TRIGGER maci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic mail audit failure'");
    try {$fn();$failed=false;}catch (PDOException) {$failed=true;}finally {db()->exec('DROP TRIGGER maci_fail');}return $failed;
}
if (($argv[1]??'')==='save-worker') {
    ma_actor();
    try {mail_accounts_save($argv[2],(int)$argv[3],ma_input($argv[4]),true,[],$password);echo 'saved';}
    catch (RuntimeException $e) {if ($e instanceof PDOException || !str_contains($e->getMessage(),'changed')) throw $e;echo 'stale';}exit;
}
try {
    // This fixture intentionally starts before account setup, so its dependent
    // inbox/message/notice tables must remain absent until their own setup tests.
    foreach (schema_statements('maci_') as $name=>$sql) if (!array_key_exists($name,mail_accounts_schema('maci_')) && !array_key_exists($name,mail_connections_schema('maci_')) && !array_key_exists($name,mail_inbox_schema('maci_')) && !array_key_exists($name,mail_messages_schema('maci_')) && !array_key_exists($name,mail_notices_schema('maci_')) && !array_key_exists($name,mail_drafts_schema('maci_')) && !array_key_exists($name,mail_submissions_schema('maci_'))) db()->exec($sql);
    foreach (default_settings() as $key=>$value) save_setting($key,$value);
    $now=gmdate('Y-m-d H:i:s');
    for ($id=1;$id<=5;$id++) query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Mail staff '.$id,'mail'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);
    staff_seed_owner(1);
    foreach ([2=>['mail.read'],3=>['mail.send'],4=>['mail.templates.manage'],5=>[]] as $id=>$permissions) query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$id===5?'owner':'administrator',json_encode($permissions)]);
    ma_actor();$settings=all('SELECT * FROM '.table('settings').' ORDER BY name');
    ma_deny('Account setup requires current owner password',fn()=>mail_accounts_migrate('wrong'));
    ma_actor(2);ma_deny('Read permission cannot enable account storage',fn()=>mail_accounts_migrate($password));ma_actor();
    ma_deny('Account library is unavailable before setup',fn()=>mail_accounts_list());
    foreach (mail_accounts_schema('maci_') as $sql) db()->exec($sql);
    db()->exec('ALTER TABLE '.table('mail_account_grants').' DROP INDEX account_staff');
    ma_deny('Setup rejects missing unique staff assignment key',fn()=>mail_accounts_migrate($password));
    db()->exec('ALTER TABLE '.table('mail_account_grants').' ADD UNIQUE account_staff(account_id,user_id)');
    db()->exec('ALTER TABLE '.table('mail_accounts').' MODIFY id CHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci');
    ma_deny('Setup rejects case-insensitive account identifiers',fn()=>mail_accounts_migrate($password));
    db()->exec('ALTER TABLE '.table('mail_accounts').' MODIFY id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin');
    query('INSERT INTO '.table('mail_account_revisions').' (account_id,revision,metadata,action,actor_id,recorded_at) VALUES (?,1,?,?,1,?)',[str_repeat('a',32),'{}','unknown',$now]);
    ma_deny('Setup refuses populated unrecognized account data',fn()=>mail_accounts_migrate($password));
    ma_ok('Rejected setup keeps unknown records',!mail_accounts_ready()&&(int)query('SELECT COUNT(*) FROM '.table('mail_account_revisions'))->fetchColumn()===1);
    query('DELETE FROM '.table('mail_account_revisions'));
    ma_ok('Setup audit failure leaves readiness disabled',ma_audit_failure(fn()=>mail_accounts_migrate($password))&&!mail_accounts_ready());
    mail_accounts_migrate($password);mail_accounts_migrate($password);
    ma_ok('Additive repeated setup preserves earlier settings',mail_accounts_ready()&&all('SELECT * FROM '.table('settings')." WHERE name<>'mail_accounts_schema_version' ORDER BY name")===$settings);
    ma_deny('Initial account requires both passwords',fn()=>mail_accounts_save('',0,ma_input(),true,[],$password));
    ma_deny('Account save reauthenticates owner',fn()=>mail_accounts_save('',0,ma_input(),true,ma_secrets(),'wrong'));
    $storage=config()['storage'];$GLOBALS['cwp_config']['storage']=CWP_ROOT;
    ma_deny('Mail key cannot be stored under served root',fn()=>mail_accounts_save('',0,ma_input(),true,ma_secrets(),$password));$GLOBALS['cwp_config']['storage']=$storage;
    foreach (['imap_host'=>'imap..example.invalid','smtp_host'=>'https://smtp.example.invalid','imap_port'=>'0','smtp_port'=>'65536','imap_encryption'=>'none','address'=>"bad\n@example.invalid",'label'=>str_repeat('x',121)] as $key=>$bad) {
        ma_deny('Invalid account field rejected: '.$key,fn()=>mail_accounts_save('',0,array_replace(ma_input(),[$key=>$bad]),true,ma_secrets(),$password));
    }
    ma_deny('Password control characters rejected',fn()=>mail_accounts_save('',0,ma_input(),true,['imap_password'=>"secret\nInjected",'smtp_password'=>'fixture'],$password));
    $id=mail_accounts_save('',0,ma_input(),true,ma_secrets(),$password);$row=mail_accounts_load($id);$cipher=$row['credentials'];$key=$storage.'/mail-master.key';$originalKey=file_get_contents($key);
    ma_ok('Database passwords are encrypted and whitespace is retained',!str_contains($cipher,'Synthetic')&&mail_accounts_unseal($id,$cipher)===ma_secrets());
    ma_ok('Mail key is private and separate from payment key',(fileperms($key)&0777)===0600&&strlen($originalKey)===32&&(!is_file($storage.'/payment-master.key')||file_get_contents($storage.'/payment-master.key')!==$originalKey));
    ma_ok('Metadata projection excludes passwords and ciphertext',!array_key_exists('credentials',mail_accounts_get($id))&&!str_contains(json_encode(mail_accounts_get($id)),'secret')&&mail_accounts_get($id)['connection']==='untested');
    ma_ok('History contains metadata without encrypted or raw passwords',count(mail_accounts_history($id))===1&&!str_contains(json_encode(mail_accounts_history($id)),$cipher)&&!str_contains(json_encode(mail_accounts_history($id)),'secret'));
    ma_deny('Account ciphertext cannot be transplanted',fn()=>mail_accounts_unseal(str_repeat('f',32),$cipher));
    $raw=base64_decode($cipher);$raw[30]=chr(ord($raw[30])^1);ma_deny('Tampered credential bytes are rejected',fn()=>mail_accounts_unseal($id,base64_encode($raw)));
    ma_deny('Malformed credential encoding is rejected',fn()=>mail_accounts_unseal($id,'not-base64!'));
    rename($key,$key.'.held');ma_deny('Missing mail key is never regenerated over stored credentials',fn()=>mail_accounts_save($id,1,ma_input(),true,[],$password));
    ma_ok('Key loss preserves database revision and ciphertext',!is_file($key)&&mail_accounts_load($id)===$row);rename($key.'.held',$key);
    chmod($key,0644);ma_deny('Insecure existing key permissions are rejected',fn()=>mail_accounts_unseal($id,$cipher));chmod($key,0600);
    file_put_contents($key,str_repeat('z',32));ma_deny('Wrong master key fails authenticated decryption',fn()=>mail_accounts_unseal($id,$cipher));file_put_contents($key,$originalKey);
    rename($key,$key.'.held');symlink($key.'.held',$key);ma_deny('Symbolic-link master key is rejected',fn()=>mail_accounts_unseal($id,$cipher));unlink($key);rename($key.'.held',$key);
    ma_deny('Stale account save cannot replace settings',fn()=>mail_accounts_save($id,0,ma_input('Stale'),true,[],$password));
    mail_accounts_save($id,1,ma_input('Renamed account'),true,['smtp_password'=>'Replacement synthetic secret'],$password);
    ma_ok('Blank password edits retain incoming credential and rotate outgoing',mail_accounts_unseal($id,mail_accounts_load($id)['credentials'])===['imap_password'=>ma_secrets()['imap_password'],'smtp_password'=>'Replacement synthetic secret']);
    $second=mail_accounts_save('',0,ma_input('Second account'),true,ma_secrets(),$password);
    ma_ok('Multiple profiles retain distinct identity-bound credentials',$id!==$second&&mail_accounts_load($id)['credentials']!==mail_accounts_load($second)['credentials']);
    ma_ok('Owner has no implicit mailbox access',!mail_accounts_can('mail.read',$id)&&!mail_accounts_can('mail.send',$id)&&mail_accounts_assigned()===[]);
    mail_accounts_grant($id,1,0,true,false,true,0,$password);
    ma_ok('Owner explicit read grant does not imply send',mail_accounts_can('mail.read',$id)&&!mail_accounts_can('mail.send',$id));
    mail_accounts_grant($id,2,0,true,true,true,7,$password);mail_accounts_grant($second,3,0,true,true,true,0,$password);
    ma_actor(2);
    ma_ok('Read-only staff needs both global and mailbox capability',mail_accounts_can('mail.read',$id)&&!mail_accounts_can('mail.send',$id)&&!mail_accounts_can('mail.read',$second));
    ma_ok('Assigned list reveals only current authorized account',array_column(mail_accounts_assigned(),'id')===[$id]&&!array_key_exists('imap_username',mail_accounts_assigned()[0]));
    ma_deny('Mail reader cannot view account configuration',fn()=>mail_accounts_get($id));
    ma_deny('Mail reader cannot replace credentials',fn()=>mail_accounts_save($id,2,ma_input(),true,ma_secrets(),$password));
    ma_deny('Mail reader cannot grant itself send permission',fn()=>mail_accounts_grant($id,2,1,true,true,true,0,$password));
    ma_actor(3);ma_ok('Send-only staff cannot read mail',!mail_accounts_can('mail.read',$second)&&mail_accounts_can('mail.send',$second));
    ma_actor(4);ma_ok('Template permission does not grant mailbox access',!mail_accounts_can('mail.read',$id)&&!mail_accounts_access(admin_user()));
    ma_actor(5);ma_ok('Another owner still needs a mailbox assignment',!mail_accounts_can('mail.read',$id));
    ma_actor(2);query('UPDATE '.table('staff_profiles')." SET permissions='[]' WHERE user_id=2");ma_ok('Removing global permission applies to current session immediately',!mail_accounts_can('mail.read',$id));query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['["mail.read"]']);
    query('UPDATE '.table('mail_account_grants').' SET issued_at=?,expires_at=? WHERE account_id=? AND user_id=2',[time()+100,time()+200,$id]);ma_ok('Future assignment grants no current access',!mail_accounts_can('mail.read',$id)&&mail_accounts_assigned()===[]);
    query('UPDATE '.table('mail_account_grants').' SET issued_at=?,expires_at=? WHERE account_id=? AND user_id=2',[time()-100,time(),$id]);ma_ok('Assignment expiration applies immediately',!mail_accounts_can('mail.read',$id));
    ma_actor();mail_accounts_grant($id,2,1,true,false,true,0,$password);ma_actor(2);ma_ok('Reissued current assignment restores only permitted read',mail_accounts_can('mail.read',$id));
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=2');ma_ok('Disabled staff loses mailbox access immediately',!mail_accounts_can('mail.read',$id));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=2');ma_actor(2);
    $_SESSION['auth_version']='stale';ma_ok('Revoked session cannot use old mailbox grant',!mail_accounts_can('mail.read',$id));ma_actor(2);
    $_GET['support']='fixture';ma_ok('Support context cannot use staff mailbox access',!mail_accounts_can('mail.read',$id));unset($_GET['support']);
    $_SESSION=['client_id'=>1,'client_auth'=>'fixture'];ma_ok('Client identity cannot use staff mail grants',!mail_accounts_can('mail.read',$id));ma_actor();
    mail_accounts_grant($id,2,2,true,false,false,0,$password);ma_actor(2);ma_ok('Revoked mailbox assignment applies immediately',!mail_accounts_can('mail.read',$id));ma_actor();
    ma_deny('Stale assignment form cannot resurrect prior access',fn()=>mail_accounts_grant($id,2,2,true,true,true,0,$password));
    $grants=mail_accounts_grants($id);$history=mail_accounts_grant_history($id);
    ma_ok('Assignment audit failure rolls back current and retained state',ma_audit_failure(fn()=>mail_accounts_grant($id,2,3,true,true,true,0,$password))&&mail_accounts_grants($id)===$grants&&mail_accounts_grant_history($id)===$history);
    $before=ma_rows();$history=mail_accounts_history($id);
    ma_ok('Credential mutation audit failure rolls back ciphertext and metadata history',ma_audit_failure(fn()=>mail_accounts_save($id,2,ma_input('Audit failure'),true,ma_secrets(),$password))&&ma_rows()===$before&&mail_accounts_history($id)===$history);
    ma_ok('Account creation audit failure creates no orphan row',ma_audit_failure(fn()=>mail_accounts_save('',0,ma_input('Failed create'),true,ma_secrets(),$password))&&ma_rows()===$before);
    mail_accounts_state($id,2,'disable',$password);ma_ok('Disabling account removes owner assigned access',!mail_accounts_can('mail.read',$id));
    mail_accounts_state($id,3,'enable',$password);ma_ok('Reenable preserves explicit assignment',mail_accounts_can('mail.read',$id));
    mail_accounts_state($id,4,'clear',$password);ma_ok('Explicit clear removes passwords and disables account',mail_accounts_load($id)['credentials']===''&&mail_accounts_load($id)['enabled']===0&&!mail_accounts_can('mail.read',$id));
    ma_deny('Cleared account cannot be enabled without replacement passwords',fn()=>mail_accounts_state($id,5,'enable',$password));
    mail_accounts_save($id,5,ma_input('Restored account'),true,ma_secrets(),$password);
    $workers=[];
    foreach (['Concurrent first','Concurrent second'] as $label) {$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'save-worker',$id,'6',$label],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $results=[];foreach ($workers as [$process,$pipes]) {$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);ma_ok('Concurrent email worker completes safely '.count($results),$exit===0&&$error==='');}
    sort($results);ma_ok('Concurrent account edits save exactly one revision',$results===['saved','stale']&&mail_accounts_get($id)['revision']===7&&count(mail_accounts_history($id))===7);
    for ($rev=7;$rev<23;$rev++) mail_accounts_save($id,$rev,ma_input('History '.$rev),true,[],$password);
    $first=mail_accounts_history($id);$older=mail_accounts_history($id,2);
    ma_ok('Metadata history pagination retains distinct versions',count($first)===21&&count($older)===3&&!array_intersect(array_column(array_slice($first,0,20),'revision'),array_column($older,'revision')));
    ma_ok('Audit records never contain passwords',!str_contains(json_encode(all('SELECT * FROM '.table('audit'))),'secret'));
    ma_ok('Existing system mail configuration remains unchanged',setting('email_mode')==='disabled'&&setting('smtp_host')===''&&setting('smtp_password')==='');
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);
file_put_contents(__DIR__.'/../qa/mail-accounts-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native database and private vault fixtures only; no mail server connection','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native email account checks'.PHP_EOL;exit($ok?0:1);
