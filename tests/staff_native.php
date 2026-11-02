<?php
/** Native A-05 adapter acceptance against disposable tables, never customer data. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='staffci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];start_session();
$results=[];$password='Synthetic staff owner passphrase';
function check_staff(string $name,bool $condition): void {global $results;$results[]=['name'=>$name,'passed'=>$condition];if(!$condition)throw new RuntimeException($name);}
function deny_staff(string $name,callable $f): void {try{$f();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;check_staff($name,true);return;}check_staff($name,false);}
function staff_test_session(int $id): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION['uid']=$id;$_SESSION['auth_version']=$u['auth_version'];$_SESSION['last_activity']=$_SESSION['login_at']=time();}
function setup_code(string $url): string {parse_str((string)parse_url($url,PHP_URL_FRAGMENT),$parts);return $parts['token'];}
try{
    require __DIR__.'/admin_password.php';
    foreach(admin_password_checks() as $boundary)check_staff($boundary['name'],$boundary['passed']);
    foreach(schema_statements('staffci_') as $name=>$sql)if(!str_starts_with($name,'staff_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Original owner','owner-staff@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('d',32),gmdate('Y-m-d H:i:s')]);
    $original=one('SELECT * FROM '.table('users').' WHERE id=1');staff_test_session(1);
    deny_staff('Initial owner setup rejects client-support context and preserves all stored state',fn()=>cwp_test_owner_revoked(fn()=>staff_migrate(1,'')));
    db()->exec(staff_schema('staffci_')['staff_profiles']); // Interrupted DDL, no initialized identities.
    db()->exec('ALTER TABLE '.table('staff_profiles').' MODIFY enabled VARCHAR(3) NOT NULL');
    deny_staff('Incompatible staff schema is rejected without replacement',fn()=>staff_migrate(1,$password));
    check_staff('Rejected migration preserves foreign column',one('SHOW COLUMNS FROM '.table('staff_profiles')." LIKE 'enabled'")['Type']==='varchar(3)');
    db()->exec('ALTER TABLE '.table('staff_profiles').' MODIFY enabled TINYINT NOT NULL');staff_migrate(1,$password);
    check_staff('Interrupted upgrade completes and preserves original owner bytes',staff_ready()&&one('SELECT * FROM '.table('users').' WHERE id=1')===$original);
    staff_migrate(1,$password);check_staff('Repeated upgrade preserves single explicit owner',(int)query('SELECT COUNT(*) FROM '.table('staff_profiles')." WHERE role='owner'")->fetchColumn()===1);
    check_staff('Owner receives management permission',staff_can('staff.manage'));
    deny_staff('Last owner cannot be disabled',fn()=>staff_save(1,'Owner','owner-staff@example.invalid','owner',false,[],$password));
    deny_staff('Last owner cannot be demoted',fn()=>staff_save(1,'Owner','owner-staff@example.invalid','administrator',true,[],$password));
    deny_staff('Unknown permissions cannot be assigned',fn()=>staff_save(0,'Bad','bad@example.invalid','administrator',true,['staff.manage'],$password));
    $id=staff_save(0,'Website administrator','web-staff@example.invalid','administrator',true,['website.manage'],$password);
    $duplicate=false;try{staff_save(0,'Duplicate','web-staff@example.invalid','administrator',true,[],$password);}catch(PDOException $e){$duplicate=$e->getCode()==='23000';}
    check_staff('Duplicate email rolls back without orphan profile',$duplicate&&(int)query('SELECT COUNT(*) FROM '.table('staff_profiles'))->fetchColumn()===2);
    check_staff('New staff has no reusable password',one('SELECT password_hash FROM '.table('users').' WHERE id=?',[$id])['password_hash']==='');
    $first=setup_code(staff_invite($id,$password));$second=setup_code(staff_invite($id,$password));
    check_staff('Invitation stores only purpose-separated token digest',one('SELECT token_hash FROM '.table('staff_invitations').' WHERE subject_id=?',[(string)$id])['token_hash']===hash('sha256',"cwp:staff-invitation:v1\0".$second));
    deny_staff('Replaced invitation is rejected',fn()=>staff_activate($first,$password));
    query('UPDATE '.table('staff_invitations').' SET expires_at=? WHERE subject_id=?',[time()-1,(string)$id]);
    deny_staff('Expired application invitation is rejected',fn()=>staff_activate($second,$password));
    $active=setup_code(staff_invite($id,$password));
    deny_staff('Staff activation rejects eleven-character password',fn()=>staff_activate($active,'AdminPass11'));
    staff_activate($active,'AdminPass12!');
    check_staff('Activation sets strong password and consumes invitation',password_verify('AdminPass12!',one('SELECT password_hash FROM '.table('users').' WHERE id=?',[$id])['password_hash'])&&one('SELECT consumed_at FROM '.table('staff_invitations').' WHERE subject_id=?',[(string)$id])['consumed_at']!==null);
    deny_staff('Application setup link is single use',fn()=>staff_activate($active,$password));
    staff_test_session($id);check_staff('Assigned website access is allowed',staff_can('website.manage'));check_staff('Unassigned client access is denied',!staff_can('clients.manage'));check_staff('Administrator has no implicit account or mailbox permission',!staff_can('staff.manage')&&!staff_can('mail.accounts.manage'));
    deny_staff('Administrator cannot elevate another account',fn()=>staff_save($id,'Website administrator','web-staff@example.invalid','owner',true,[],'AdminPass12!'));
    $session=$_SESSION;staff_test_session(1);$pending=setup_code(staff_invite($id,$password));query('INSERT INTO '.table('resets').' (token_hash,user_id,expires) VALUES (?,?,?)',[str_repeat('a',64),$id,time()+300]);
    staff_save($id,'Website administrator','web-staff@example.invalid','administrator',false,['website.manage'],$password);
    check_staff('Deactivation atomically revokes reset and invitation records',!one('SELECT subject_id FROM '.table('staff_invitations').' WHERE subject_id=?',[(string)$id])&&!one('SELECT user_id FROM '.table('resets').' WHERE user_id=?',[$id]));
    $_SESSION=$session;check_staff('Deactivated existing session denied on next check',admin_user()===null);
    deny_staff('Disabled staff cannot activate pending link',fn()=>staff_activate($pending,$password));
    staff_test_session(1);staff_save($id,'Website administrator','web-staff@example.invalid','administrator',true,['website.manage'],$password);
    $pending=setup_code(staff_invite($id,$password));staff_activate($pending,$password);staff_test_session($id);
    $reset=staff_reset_token('web-staff@example.invalid');check_staff('Enabled staff can request a reset token',$reset!==null);
    deny_staff('Staff reset rejects eleven-character password',fn()=>staff_reset($reset['token'],'AdminPass11'));
    $oldVersion=$_SESSION['auth_version'];staff_reset($reset['token'],'AdminPass12!');check_staff('Reset rotates current session version',admin_user()===null&&one('SELECT auth_version FROM '.table('users').' WHERE id=?',[$id])['auth_version']!==$oldVersion);
    check_staff('Staff reset stores twelve-character password',password_verify('AdminPass12!',one('SELECT password_hash FROM '.table('users').' WHERE id=?',[$id])['password_hash']));
    deny_staff('Password reset is single use',fn()=>staff_reset($reset['token'],$password));
    staff_test_session($id);deny_staff('Staff account rejects eleven-character password',fn()=>staff_account_save($id,'Updated administrator','updated-staff@example.invalid','AdminPass12!','AdminPass11'));
    $newVersion=staff_account_save($id,'Updated administrator','updated-staff@example.invalid','AdminPass12!','AdminPass12!');check_staff('Self-account edit revokes prior session',admin_user()===null);$_SESSION['auth_version']=$newVersion;staff_test_session($id);
    check_staff('Account name and email saved without changing grants',admin_user()['name']==='Updated administrator'&&staff_can('website.manage')&&!staff_can('clients.manage'));
    staff_test_session(1);$secondOwner=staff_save(0,'Second owner','second-owner@example.invalid','owner',true,[],$password);
    deny_staff('Pending owner cannot replace last activated owner',fn()=>staff_save(1,'Owner','owner-staff@example.invalid','administrator',true,[],$password));
    $code=setup_code(staff_invite($secondOwner,$password));staff_activate($code,$password);
    // Two concurrent owner deactivations must leave one usable owner. Each worker captures its old session first.
    file_put_contents(__DIR__.'/../test-runtime/staff-test-config.php','<?php return '.var_export(config(),true).';');
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);query('SELECT GET_LOCK(?,5)',[$key]);$workers=[];
    foreach([[1,$secondOwner],[$secondOwner,1]] as [$actor,$target]){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/staff_worker.php',(string)$actor,(string)$target],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('Worker unavailable');fclose($pipes[0]);$workers[]=[$proc,$pipes,$actor];}
    $deadline=microtime(true)+3;do{$ready=true;foreach($workers as [,, $actor])$ready=$ready&&is_file(__DIR__.'/../test-runtime/staff-ready-'.$actor);if(!$ready)usleep(10000);}while(!$ready&&microtime(true)<$deadline);
    query('SELECT RELEASE_LOCK(?)',[$key]);$accepted=0;foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);if(str_contains($out,'accepted'))$accepted++;if($err!=='')throw new RuntimeException('Worker diagnostic: '.$err);}
    check_staff('Concurrent owner mutations serialize and retain one usable owner',$ready&&$accepted===1&&(int)query('SELECT COUNT(*) FROM '.table('staff_profiles')." p JOIN ".table('users')." u ON u.id=p.user_id WHERE p.role='owner' AND p.enabled=1 AND u.password_hash<>''")->fetchColumn()===1);
    check_staff('Audit records account actions without raw setup tokens',!str_contains(json_encode(all('SELECT * FROM '.table('audit'))),$code));
}catch(Throwable $e){$error=$e;}
file_put_contents(__DIR__.'/../qa/staff-native-results.json',json_encode(['passed'=>!isset($error),'checks'=>$results,'failure'=>isset($error)?get_class($error).': '.$error->getMessage():null],JSON_PRETTY_PRINT));
foreach($results as $r)echo ($r['passed']?'PASS ':'FAIL ').$r['name']."\n";
if(isset($error)){fwrite(STDERR,$error."\n");exit(1);}echo count($results)." staff adapter checks passed\n";
