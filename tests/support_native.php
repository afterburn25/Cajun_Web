<?php
/** Disposable native A-09 tests. Real PDO/InnoDB, no customer connection. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='supportci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];start_session();
$results=[];$password='Synthetic support test passphrase';
function support_ok(string $name,bool $value): void {global $results;$results[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}
function support_denied(string $name,callable $f): void {try{$f();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;support_ok($name,true);return;}support_ok($name,false);}
function support_actor(int $id): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION['uid']=$id;$_SESSION['auth_version']=$u['auth_version'];$_SESSION['login_at']=$_SESSION['last_activity']=time();unset($GLOBALS['cwp_support']);}
try{
    foreach(schema_statements('supportci_') as $name=>$sql)if(!str_starts_with($name,'support_'))db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Owner','support-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('a',32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);support_actor(1);
    foreach([1,2] as $id){
        query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Client '.$id,'support-client'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat((string)$id,32),gmdate('Y-m-d H:i:s')]);
        query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,'Private '.$id,'Synthetic summary','Synthetic milestones',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    }
    $original=all('SELECT * FROM '.table('studio_clients'));
    support_denied('Support unavailable until explicit migration',fn()=>support_start(1));
    support_denied('Revoked owner refuses: Support migration requires owner current password',fn()=>cwp_test_owner_revoked(fn()=>support_migrate('wrong')));
    db()->exec(support_schema('supportci_')['support_clients']);db()->exec('ALTER TABLE '.table('support_clients').' MODIFY allowed VARCHAR(3) NOT NULL');
    support_denied('Incompatible support schema rejected without replacement',fn()=>support_migrate($password));
    support_ok('Incompatible support data shape retained',one('SHOW COLUMNS FROM '.table('support_clients')." LIKE 'allowed'")['Type']==='varchar(3)');
    db()->exec('ALTER TABLE '.table('support_clients').' MODIFY allowed TINYINT NOT NULL');support_migrate($password);support_migrate($password);
    support_ok('Interrupted and repeated migration preserves client credentials',support_ready()&&all('SELECT * FROM '.table('studio_clients'))===$original);
    support_denied('Unconfigured client policy denies access',fn()=>support_start(1));
    support_policy(1,true,$password);support_policy(2,true,$password);
    $_SESSION['client_id']=2;$_SESSION['client_auth']=$original[1]['auth_version'];$_SESSION['client_login']=$_SESSION['client_last']=time();$clientKeys=array_intersect_key($_SESSION,array_flip(['client_id','client_auth','client_login','client_last']));
    $id=support_start(1);$binding=$_SESSION['support_binding'];$ctx=support_resolve($id);$GLOBALS['cwp_support']=$ctx;
    support_ok('Support resolves immutable staff and selected-client identities',(int)$ctx['actor']['id']===1&&(int)$ctx['client']['id']===1);
    support_ok('Support never overwrites actual customer login',$clientKeys===array_intersect_key($_SESSION,$clientKeys));
    support_ok('Stored binding is a digest rather than browser secret',one('SELECT binding_hash FROM '.table('support_sessions').' WHERE id=?',[$id])['binding_hash']===hash('sha256',$binding));
    support_ok('Support has 15 minute fixed maximum',(int)$ctx['session']['expires_at']-(int)$ctx['session']['started_at']===900);
    support_ok('Portal URLs retain immutable support context',str_contains(studio_url(['job'=>1]),'support='.$id));
    support_ok('Support form token is separate from actual client token',support_csrf_token($id)!==csrf());
    support_denied('Nested client switching is blocked',fn()=>support_start(2));
    support_denied('Client decisions are forbidden in support context',fn()=>studio_decide(1,1,'approved','No'));
    support_denied('Client activation is forbidden in support context',fn()=>studio_activate(str_repeat('a',64),$password));
    support_message($id,1,'Synthetic support message');
    $m=one('SELECT * FROM '.table('studio_messages').' ORDER BY id DESC LIMIT 1');
    support_ok('Support message retains staff attribution',$m['author_type']==='admin'&&(int)$m['author_id']===1);
    support_denied('Cross-client support write denied',fn()=>support_message($id,2,'Must never be stored'));
    support_ok('Denied support write cannot mutate another client',!one('SELECT id FROM '.table('studio_messages').' WHERE job_id=2'));
    $_SESSION['support_binding']=str_repeat('c',64);support_ok('Copied support identifier fails in a different browser',support_resolve($id)===null);$_SESSION['support_binding']=$binding;
    support_end($id);support_ok('Ended context cannot be replayed',support_resolve($id)===null);
    support_ok('Return keeps original customer identity',$clientKeys===array_intersect_key($_SESSION,$clientKeys));
    $id=support_start(2);query('UPDATE '.table('support_sessions').' SET expires_at=? WHERE id=?',[time()-1,$id]);support_ok('Expired context denied and durably ended',support_resolve($id)===null&&one('SELECT end_reason FROM '.table('support_sessions').' WHERE id=?',[$id])['end_reason']==='expired');
    $id=support_start(1);query('UPDATE '.table('support_sessions').' SET last_seen_at=? WHERE id=?',[time()-301,$id]);support_ok('Idle context denied',support_resolve($id)===null);
    $id=support_start(1);support_policy(1,false,$password);support_ok('Client policy revocation ends live context',support_resolve($id)===null);support_policy(1,true,$password);
    $id=support_start(1);query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[str_repeat('d',32)]);support_ok('Client credential change revokes support',support_resolve($id)===null);
    $id=support_start(1);query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');support_ok('Disabled client revokes support',support_resolve($id)===null);query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
    $staff=staff_save(0,'Support viewer','viewer@example.invalid','administrator',true,['support.login'],$password);$link=staff_invite($staff,$password);parse_str((string)parse_url($link,PHP_URL_FRAGMENT),$parts);staff_activate($parts['token'],$password);support_actor($staff);
    support_denied('Non-owner cannot change client support authorization',fn()=>support_policy(1,true,$password));
    $id=support_start(1);support_denied('View permission does not grant troubleshooting writes',fn()=>support_message($id,1,'No write grant'));
    support_ok('View permission does not grant file download',!support_can('support.download'));
    $viewerSession=$_SESSION;support_actor(1);staff_save($staff,'Support viewer','viewer@example.invalid','administrator',true,[],$password);$_SESSION=$viewerSession;
    support_ok('Staff permission change revokes old support session',support_resolve($id)===null);support_actor($staff);
    support_denied('Staff without explicit support permission cannot start',fn()=>support_start(1));
    support_actor(1);$id=support_start(1);support_end_browser('admin-logout');support_ok('Staff logout ends bound support context',one('SELECT end_reason FROM '.table('support_sessions').' WHERE id=?',[$id])['end_reason']==='admin-logout'&&support_binding()==='');
    $id=support_start(1);$before=(int)query('SELECT COUNT(*) FROM '.table('studio_messages'))->fetchColumn();
    db()->exec('CREATE TRIGGER supportci_audit_failure BEFORE INSERT ON '.table('support_events')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    $blocked=false;try{support_message($id,1,'Audit failure must roll back this message');}catch(PDOException $e){$blocked=$e->getCode()==='45000';}
    support_ok('Audit failure rolls back the actual support write',$blocked&&(int)query('SELECT COUNT(*) FROM '.table('studio_messages'))->fetchColumn()===$before);
    db()->exec('DROP TRIGGER supportci_audit_failure');support_end($id);
    $events=all('SELECT * FROM '.table('support_events'));support_ok('Support audit contains starts ends and attributed actions',in_array('session.start',array_column($events,'action'),true)&&in_array('session.end',array_column($events,'action'),true)&&in_array('message.send',array_column($events,'action'),true));
    support_ok('Support audit excludes message body browser binding and password',!str_contains(json_encode($events),'Synthetic support message')&&!str_contains(json_encode($events),$binding)&&!str_contains(json_encode($events),$password));
}catch(Throwable $e){$error=$e;}
file_put_contents(__DIR__.'/../qa/support-native-results.json',json_encode(['passed'=>!isset($error),'checks'=>$results,'failure'=>isset($error)?get_class($error).': '.$error->getMessage():null],JSON_PRETTY_PRINT));
foreach($results as $r)echo ($r['passed']?'PASS ':'FAIL ').$r['name']."\n";
if(isset($error)){fwrite(STDERR,$error."\n");exit(1);}echo count($results)." support adapter checks passed\n";
