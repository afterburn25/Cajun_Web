<?php
/** Isolated native tests for immutable staff-only project notes. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_HOSTING_PAYMENT_FOREIGN_DATABASE')!=='cwp_hpay_foreign')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/clients/workspace.php';
require __DIR__.'/../site/app/projects/private_notes.php';

$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';
$GLOBALS['cwp_config']['db']['prefix']='projectnotesci_';
$private=__DIR__.'/../test-runtime/project-private-notes';
if(!is_dir($private))mkdir($private,0700,true);
$GLOBALS['cwp_config']['storage']=realpath($private);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
start_session();

$checks=[];$password='Synthetic private note owner passphrase';
function pn_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pn_deny(string $name,callable $work):void{try{$work();}catch(InvalidArgumentException|RuntimeException|LogicException $error){if($error instanceof PDOException)throw $error;pn_ok($name,true);return;}pn_ok($name,false);}
function pn_session(int $id):void{$user=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$user['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function pn_count(string $table):int{return (int)query('SELECT COUNT(*) FROM '.table($table))->fetchColumn();}
function pn_audit_failure(callable $work):bool{
    db()->exec('CREATE TRIGGER projectnotesci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    try{$work();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER projectnotesci_fail');}
    return $failed;
}

try{
    db()->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE ?',[config()['db']['prefix'].'%']) as $row)db()->exec('DROP TABLE `'.str_replace('`','``',(string)$row['TABLE_NAME']).'`');
    db()->exec('SET FOREIGN_KEY_CHECKS=1');
    foreach(schema_statements(config()['db']['prefix']) as $name=>$sql)if($name!=='project_private_notes')db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    save_setting('project_private_notes_schema_version','');
    $now=gmdate('Y-m-d H:i:s');
    foreach([1=>'Owner',2=>'Project staff',3=>'Website staff'] as $id=>$name)query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$id,$name,'private-note-'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);
    staff_seed_owner(1);
    query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,'[\"clients.manage\"]'),(3,'administrator',1,'[\"website.manage\"]')");
    query('INSERT INTO '.table('studio_clients').' (id,name,email,auth_version,created_at) VALUES (1,?,?,?,?)',['Private note client','private-note-client@example.invalid',bin2hex(random_bytes(16)),$now]);
    query('INSERT INTO '.table('studio_jobs').' (id,client_id,title,summary,milestones,created_at,updated_at) VALUES (1,1,?,?,?,?,?)',['Private note project','Preserve summary','Preserve milestones',$now,$now]);
    pn_session(1);

    db()->exec(project_private_notes_schema(config()['db']['prefix'])['project_private_notes']);
    db()->exec('ALTER TABLE '.table('project_private_notes').' DROP INDEX project_notes');
    pn_deny('Setup rejects a private-note table missing its exact project index',fn()=>project_private_notes_migrate($password));
    db()->exec('ALTER TABLE '.table('project_private_notes').' ADD INDEX project_notes(project_id,id)');
    $foreignDatabase=(string)getenv('CWP_TEST_HOSTING_PAYMENT_FOREIGN_DATABASE');
    $foreignTables=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=? AND table_name LIKE ?',[$foreignDatabase,config()['db']['prefix'].'%']);
    if($foreignDatabase!=='cwp_hpay_foreign'||$foreignTables)throw new RuntimeException('Exact isolated foreign fixture schema unavailable.');
    db()->exec('DROP TABLE '.table('project_private_notes'));
    try{
        db()->exec('CREATE TABLE `'.$foreignDatabase.'`.`'.config()['db']['prefix'].'studio_jobs` (id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
        db()->exec('CREATE TABLE `'.$foreignDatabase.'`.`'.config()['db']['prefix'].'users` (id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
        db()->exec('CREATE TABLE '.table('project_private_notes').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,project_id INT UNSIGNED NOT NULL,staff_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,body TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,INDEX project_notes(project_id,id),INDEX staff_id(staff_id),FOREIGN KEY (project_id) REFERENCES `'.$foreignDatabase.'`.`'.config()['db']['prefix'].'studio_jobs` (id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY (staff_id) REFERENCES `'.$foreignDatabase.'`.`'.config()['db']['prefix'].'users` (id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        pn_deny('Setup rejects exact-looking foreign keys into another database',fn()=>project_private_notes_migrate($password));
        pn_ok('Cross-database refusal leaves the readiness marker unchanged',!project_private_notes_ready());
    }finally{
        db()->exec('DROP TABLE IF EXISTS '.table('project_private_notes'));
        db()->exec('DROP TABLE IF EXISTS `'.$foreignDatabase.'`.`'.config()['db']['prefix'].'studio_jobs`');
        db()->exec('DROP TABLE IF EXISTS `'.$foreignDatabase.'`.`'.config()['db']['prefix'].'users`');
    }
    db()->exec(project_private_notes_schema(config()['db']['prefix'])['project_private_notes']);
    query('INSERT INTO '.table('project_private_notes').' (project_id,staff_id,created_epoch,body) VALUES (1,1,1,?)',['Unrecognized retained note']);
    pn_deny('Setup refuses to adopt unmarked private note data',fn()=>project_private_notes_migrate($password));
    query('DELETE FROM '.table('project_private_notes'));
    pn_ok('Setup audit failure rolls back only readiness and preserves the verified empty table',pn_audit_failure(fn()=>project_private_notes_migrate($password))&&!project_private_notes_ready()&&(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'project_private_notes']));
    project_private_notes_migrate($password);project_private_notes_migrate($password);
    pn_ok('Repeated additive setup is ready and preserves workspace records',project_private_notes_ready()&&pn_count('studio_jobs')===1&&pn_count('project_private_notes')===0);

    $messages=pn_count('studio_messages');$notifications=pn_count('notification_feed');$started=time();
    $first=project_private_notes_create(1,"  Staff-only </textarea><script>alert(1)</script> & é  ");
    $firstData=$first->data();$stored=one('SELECT * FROM '.table('project_private_notes').' WHERE id=?',[$firstData['id']]);
    pn_ok('Create binds server actor project and time while storing canonical text',(int)$stored['project_id']===1&&(int)$stored['staff_id']===1&&(int)$stored['created_epoch']>=$started&&(int)$stored['created_epoch']<=time()&&$stored['body']==='Staff-only </textarea><script>alert(1)</script> & é');
    $listed=project_private_notes_list(1);
    pn_ok('Authorized list revalidates the canonical model and staff attribution',count($listed)===1&&$listed[0]['note']->json()===$first->json()&&$listed[0]['staff_name']==='Owner');
    pn_ok('Private creation emits no client message or notification',pn_count('studio_messages')===$messages&&pn_count('notification_feed')===$notifications);
    $audit=one('SELECT * FROM '.table('audit')." WHERE action='project.private_note.create' ORDER BY id DESC LIMIT 1");
    pn_ok('Creation audit is body-free and bound to the note facts',$audit!==null&&!str_contains((string)$audit['detail'],'Staff-only')&&json_decode((string)$audit['detail'],true,8,JSON_THROW_ON_ERROR)===$first->auditReference());

    pn_session(2);$second=project_private_notes_create(1,'Second direct staff note');
    pn_ok('A current client-management administrator can create and list',count(project_private_notes_list(1))===2&&$second->data()['staff_id']===2);
    pn_session(3);pn_deny('Website-only administrator cannot create',fn()=>project_private_notes_create(1,'Forbidden website note'));pn_deny('Website-only administrator cannot list',fn()=>project_private_notes_list(1));
    pn_ok('Denied permission creates no record',pn_count('project_private_notes')===2);

    pn_session(1);$GLOBALS['cwp_support']=['synthetic'=>true];
    pn_deny('Support context cannot create private staff notes',fn()=>project_private_notes_create(1,'Forbidden support note'));
    pn_deny('Support context cannot list private staff notes',fn()=>project_private_notes_list(1));
    unset($GLOBALS['cwp_support']);$_GET['support']='forged';
    pn_deny('Explicit support marker cannot create private staff notes',fn()=>project_private_notes_create(1,'Forbidden query note'));
    pn_deny('Explicit support marker cannot list private staff notes',fn()=>project_private_notes_list(1));
    unset($_GET['support']);

    pn_session(1);$_SESSION['auth_version']=str_repeat('0',32);
    pn_deny('Stale administrator authorization cannot create',fn()=>project_private_notes_create(1,'Forbidden stale note'));
    pn_session(1);pn_deny('Unknown project cannot create a private note',fn()=>project_private_notes_create(999,'Unknown project note'));
    $before=pn_count('project_private_notes');
    pn_ok('Audit failure rolls back the private note atomically',pn_audit_failure(fn()=>project_private_notes_create(1,'Must roll back'))&&pn_count('project_private_notes')===$before);

    query('UPDATE '.table('project_private_notes').' SET body=? WHERE id=?',[' Corrupt canonical body',$firstData['id']]);
    pn_deny('Read fails closed on noncanonical stored text',fn()=>project_private_notes_list(1));
    query('UPDATE '.table('project_private_notes').' SET body=? WHERE id=?',[$firstData['body'],$firstData['id']]);
    for($i=0;$i<105;$i++)query('INSERT INTO '.table('project_private_notes').' (project_id,staff_id,created_epoch,body) VALUES (1,1,?,?)',[time()+$i,'Synthetic retained note '.$i]);
    $page=project_private_notes_list(1);$ids=array_map(fn($entry)=>(int)$entry['note']->data()['id'],$page);
    $sorted=$ids;rsort($sorted,SORT_NUMERIC);
    pn_ok('Read is capped at one hundred newest-first validated notes',count($page)===100&&$ids===$sorted&&count(array_unique($ids))===100);

    try{query('DELETE FROM '.table('studio_jobs').' WHERE id=1');$restricted=false;}catch(PDOException){$restricted=true;}
    pn_ok('Foreign-key retention prevents deleting a project that owns private notes',$restricted&&pn_count('studio_jobs')===1);
    save_setting('project_private_notes_schema_version','9');
    pn_deny('Unknown storage marker fails closed without changing notes',fn()=>project_private_notes_migrate($password));
    save_setting('project_private_notes_schema_version','1');
    pn_ok('Unknown-marker refusal preserved all private notes',pn_count('project_private_notes')===107);
}catch(Throwable $error){$failure=get_class($error).': '.$error->getMessage();}

if(!is_dir(__DIR__.'/../qa'))mkdir(__DIR__.'/../qa',0700,true);
file_put_contents(__DIR__.'/../qa/project-private-notes-native-results.json',json_encode(['scope'=>'Isolated MariaDB schema, migration, authorization, immutable note, audit and privacy tests; synthetic records only; no customer message, email, provider, payment or live operation.','checks'=>$checks,'passed'=>!isset($failure),'failure'=>$failure??null],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
foreach($checks as $check)echo ($check['passed']?'PASS ':'FAIL ').$check['name'].PHP_EOL;
if(isset($failure)){fwrite(STDERR,$failure.PHP_EOL);exit(1);}
echo 'Native private project note checks: '.count($checks).' passed'.PHP_EOL;
