<?php
/** Isolated MariaDB lane for pipeline transition rules + append-only history. No customer data. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/admin/controllers/studio.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='pipeleci_';
$private=__DIR__.'/../test-runtime/pipeline-native-private';if(!is_dir($private))mkdir($private,0700);$GLOBALS['cwp_config']['storage']=realpath($private);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$password='Synthetic pipeline owner passphrase';$checks=[];
function p_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function p_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;p_ok($name,true);return;}p_ok($name,false);}
function p_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function p_user(int $id=1): array {return one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);}
function p_audit_failure(callable $fn): bool {db()->exec('CREATE TRIGGER pipeleci_fail BEFORE INSERT ON '.table('lead_stage_history')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic history failure'");try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER pipeleci_fail');}return $failed;}
try{
    foreach(schema_statements('pipeleci_') as $name=>$sql)db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);save_setting('staff_schema_version','1');
    $now=gmdate('Y-m-d H:i:s');for($id=1;$id<=2;$id++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Pipeline staff '.$id,'pipeline'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);staff_seed_owner(1);
    query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,?)",[json_encode(['website.manage'])]);p_actor();
    query('INSERT INTO '.table('leads')." (reference,request_key,name,email,company,phone,service,budget,timeline,message,consent,ip_hash,status,notes,notification_status,created_at) VALUES ('PIPE-1','".str_repeat('a',64)."','Pipeline lead','lead@example.invalid','','','Design','Flexible','Discuss','Synthetic inquiry',1,'".str_repeat('b',64)."','new','','not_configured',?)",[$now]);
    $lead=(int)db()->lastInsertId();$u=p_user(1);
    p_deny('History setup requires owner reauthentication',fn()=>pipeline_migrate('wrong'));
    foreach(pipeline_schema('pipeleci_') as $sql)db()->exec($sql);
    query('INSERT INTO '.table('lead_stage_history')." (lead_id,from_stage,to_stage,actor_id,note,created_at) VALUES (1,'x','y',1,'',?)",[$now]);
    p_deny('Setup refuses unrecognized populated history',fn()=>pipeline_migrate($password));query('DELETE FROM '.table('lead_stage_history'));
    p_deny('Unknown stage refused',fn()=>pipeline_transition($lead,'sideways','','',$u));
    p_deny('Illegal first move refused',fn()=>pipeline_transition($lead,'proposal','jumping ahead','',$u));
    pipeline_transition($lead,'contacted','Called the lead','2026-10-20',$u);
    p_ok('Valid transition applies before history exists',one('SELECT status FROM '.table('leads').' WHERE id=?',[$lead])['status']==='contacted');
    pipeline_transition($lead,'contacted','Notes only, same stage','',$u);
    p_ok('Same-stage save updates notes without a move',one('SELECT notes FROM '.table('leads').' WHERE id=?',[$lead])['notes']==='Notes only, same stage');
    p_deny('Malformed follow-up refused',fn()=>pipeline_transition($lead,'qualified','','2026-13-40',$u));
    pipeline_migrate($password);p_ok('Owner enables append-only history',pipeline_ready());
    pipeline_migrate($password);p_ok('Repeated setup is safe',pipeline_ready());
    pipeline_transition($lead,'qualified','Discovery done','',$u);
    p_ok('Enabled history records actor and both stages',($h=pipeline_history($lead))&&count($h)===1&&$h[0]['from_stage']==='contacted'&&$h[0]['to_stage']==='qualified'&&(int)$h[0]['actor_id']===1);
    pipeline_transition($lead,'proposal','Sent proposal','',$u);pipeline_transition($lead,'won','Signed','',$u);
    p_ok('Forward moves chain and append in order',count(pipeline_history($lead))===3&&pipeline_history($lead)[0]['to_stage']==='won');
    p_deny('Won is terminal — no moves allowed',fn()=>pipeline_transition($lead,'spam','','',$u));
    query('INSERT INTO '.table('leads')." (reference,request_key,name,email,company,phone,service,budget,timeline,message,consent,ip_hash,status,notes,notification_status,created_at) VALUES ('PIPE-2','".str_repeat('c',64)."','Reopen lead','lead2@example.invalid','','','Design','Flexible','Discuss','Synthetic inquiry',1,'".str_repeat('d',64)."','new','','not_configured',?)",[$now]);
    $lead2=(int)db()->lastInsertId();pipeline_transition($lead2,'closed','Walked away','',$u);pipeline_transition($lead2,'new','Client returned','',$u);pipeline_transition($lead2,'spam','Junk','',$u);pipeline_transition($lead2,'new','False positive','',$u);
    p_ok('Closed and spam reopen to new only',one('SELECT status FROM '.table('leads').' WHERE id=?',[$lead2])['status']==='new'&&count(pipeline_history($lead2))===4);
    $before=count(pipeline_history($lead2));
    p_ok('History-write failure rolls back the move',p_audit_failure(fn()=>pipeline_transition($lead2,'contacted','will fail','',$u))&&one('SELECT status FROM '.table('leads').' WHERE id=?',[$lead2])['status']==='new'&&count(pipeline_history($lead2))===$before);
    $choices=pipeline_choices('new');p_ok('Choices expose current plus legal moves',$choices['new']!==''&&isset($choices['contacted'],$choices['spam'],$choices['closed'])&&!isset($choices['proposal'],$choices['won'],$choices['qualified']));
    p_ok('Audit recorded each real move',(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action='pipeline.move'")->fetchColumn()===8);
}catch(Throwable $e){fwrite(STDERR,'pipeline_native failed: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");exit(1);}
echo 'pipeline_native: '.count($checks).' checks passed'.PHP_EOL;
