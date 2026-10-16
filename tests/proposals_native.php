<?php
/** Isolated MariaDB proposal lifecycle, no customer data or provider calls. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/includes/layout/proposals.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='propci_';
$private=__DIR__.'/../test-runtime/proposals-native-private';if(!is_dir($private))mkdir($private,0700);$GLOBALS['cwp_config']['storage']=realpath($private);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$password='Synthetic proposal owner passphrase';$checks=[];
function p_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function p_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;p_ok($name,true);return;}p_ok($name,false);}
function p_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function p_client(int $id): array {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];return $c;}
function p_input(string $title='Synthetic proposal'): array {return ['title'=>$title,'deliverables'=>"Design and build five-page site\nContent population",'exclusions'=>"Logo redesign",'milestones'=>"Design and build | 1200.00\nHosting year one | 95.88",'schedule_notes'=>'Design preview within three weeks of kickoff.'];}
function p_event_failure(callable $fn): bool {db()->exec('CREATE TRIGGER propci_fail BEFORE INSERT ON '.table('proposal_events')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic history failure'");try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER propci_fail');}return $failed;}
try{
    foreach(schema_statements('propci_') as $sql)db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);save_setting('studio_schema_version','2');save_setting('staff_schema_version','1');
    $now=gmdate('Y-m-d H:i:s');for($id=1;$id<=3;$id++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Proposal staff '.$id,'proposal'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);staff_seed_owner(1);
    foreach([2=>['clients.manage'],3=>['website.manage']] as $id=>$grants)query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[$id,json_encode($grants)]);p_actor();
    for($id=1;$id<=2;$id++)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Proposal client '.$id,'proposal-client'.$id.'@example.invalid',str_repeat((string)$id,32),$now]);
    p_deny('Setup requires owner reauthentication',fn()=>proposals_migrate('wrong'));
    p_deny('Setup requires the client workspace schema',function() use($password){save_setting('studio_schema_version','0');try{proposals_migrate($password);}finally{save_setting('studio_schema_version','2');}});
    proposals_migrate($password);p_ok('Setup creates additive proposal schema',proposals_ready());proposals_migrate($password);p_ok('Repeated setup is safe',proposals_ready());
    p_actor(3);p_deny('Website-only staff cannot create proposals',fn()=>proposal_create(1,p_input(),bin2hex(random_bytes(32))));p_deny('Website-only staff cannot run owner setup',fn()=>proposals_migrate($password));p_actor(2);
    p_deny('Proposal needs a deliverable',fn()=>proposal_create(1,['title'=>'x','deliverables'=>''],bin2hex(random_bytes(32))));
    p_deny('Milestones reject malformed amounts',fn()=>proposal_create(1,['title'=>'x','deliverables'=>'Site','exclusions'=>'','milestones'=>'Build | $1,200','schedule_notes'=>'soon'],bin2hex(random_bytes(32))));
    p_deny('Unknown client rejected',fn()=>proposal_create(99,p_input(),bin2hex(random_bytes(32))));
    $key=bin2hex(random_bytes(32));$id=proposal_create(1,p_input(),$key);$row=proposal_row($id);
    p_ok('Draft created with version 1 and replay key',strlen($id)===32&&$row['status']==='draft'&&(int)$row['current_version']===1&&(int)$row['created_by']===2);
    p_ok('Same request returns same proposal',proposal_create(1,p_input(),$key)===$id&&(int)query('SELECT COUNT(*) FROM '.table('proposals'))->fetchColumn()===1&&(int)query('SELECT COUNT(*) FROM '.table('proposal_versions'))->fetchColumn()===1);
    p_deny('Reusing key with changed details rejected',fn()=>proposal_create(1,p_input('Changed'),$key));p_actor();
    p_deny('Another actor cannot reuse the request key',fn()=>proposal_create(1,p_input(),$key));p_actor(2);
    p_deny('Draft cannot be decided by client',function() use($id){$client=p_client(1);proposal_decide($id,'approve','',$client);});p_actor(2);
    proposal_send($id);p_ok('Send marks proposal awaiting decision',proposal_row($id)['status']==='sent'&&proposal_row($id)['sent_at']!==null);
    p_deny('Sent proposal cannot be edited in place',fn()=>proposal_revise($id,p_input('Sent edit')));
    p_deny('Sent proposal cannot be re-sent',fn()=>proposal_send($id));
    $client=p_client(2);p_deny('Another client cannot decide',fn()=>proposal_decide($id,'approve','',$client));
    $client=p_client(1);p_deny('Change request needs a note',fn()=>proposal_decide($id,'changes','   ',$client));
    proposal_decide($id,'changes','Different color palette please',$client);
    p_ok('Change request moves to changes-requested',proposal_row($id)['status']==='changes-requested'&&(int)query('SELECT COUNT(*) FROM '.table('proposal_decisions').' WHERE proposal_id=?',[$id])->fetchColumn()===1);
    p_actor(2);p_deny('Decided proposal cannot be decided again',function() use($id){$client=p_client(1);proposal_decide($id,'approve','',$client);});p_actor(2);
    proposal_revise($id,p_input('Revised proposal'));p_ok('Revision returns to draft at v2',proposal_row($id)['status']==='draft'&&(int)proposal_row($id)['current_version']===2);
    proposal_send($id);$client=p_client(1);proposal_decide($id,'approve','',$client);
    $row=proposal_row($id);p_ok('Approval pins version 2', $row['status']==='approved'&&one('SELECT decision FROM '.table('proposal_decisions').' WHERE proposal_id=? AND version=2',[$id])!==false);
    p_actor(2);p_deny('Approved proposal cannot be withdrawn',fn()=>proposal_withdraw($id));
    $id2=proposal_create(1,p_input('Second proposal'),bin2hex(random_bytes(32)));proposal_withdraw($id2);p_ok('Draft withdrawal works',proposal_row($id2)['status']==='withdrawn');p_deny('Withdrawn proposal cannot send',fn()=>proposal_send($id2));
    $history=array_column(proposal_history($id),'event');p_ok('Append-only history attributes every transition',in_array('version-added',$history)&&in_array('sent',$history)&&in_array('changes-requested',$history)&&in_array('revised',$history)&&in_array('approved',$history));
    p_ok('Client list hides drafts and withdrawals',array_column(proposals_for_client(1),'id')===[$id]&&proposals_for_client(2)===[]);
    $GLOBALS['cwp_support']=['session'=>['id'=>'synthetic'],'actor'=>['id'=>1]];
    p_deny('Support sessions cannot decide proposals',function() use($id){$client=p_client(1);proposal_decide($id,'approve','',$client);});
    p_deny('Support sessions cannot manage proposals',fn()=>proposal_send($id));unset($GLOBALS['cwp_support']);p_actor(2);
    p_ok('History write failure rolls back proposal and version atomically',p_event_failure(fn()=>proposal_create(1,p_input('Atomic fail'),bin2hex(random_bytes(32))))&&!one('SELECT id FROM '.table('proposals')." WHERE title='Atomic fail'")&&(int)query('SELECT COUNT(*) FROM '.table('proposal_versions').' WHERE proposal_id NOT IN (SELECT id FROM '.table('proposals').')')->fetchColumn()===0);
    $kept=all('SELECT * FROM '.table('proposals'));p_actor();proposals_migrate($password);p_ok('Re-running setup preserves every proposal record',all('SELECT * FROM '.table('proposals'))===$kept&&all('SELECT * FROM '.table('proposal_decisions'))!==[]);
}catch(Throwable $e){echo 'FAILED: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n";exit(1);}
echo 'proposals native: '.count($checks).' checks, 0 failures'."\n";
