<?php
/** Native A-07 application acceptance. Disposable synthetic tables only. */
declare(strict_types=1);
if (PHP_SAPI!=='cli' || getenv('CWP_TEST_DATABASE')!=='cwp_test') exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='tplci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];start_session();
$checks=[];$password='Synthetic template owner passphrase';
function t_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if (!$ok) throw new RuntimeException($name);}
function t_deny(string $name,callable $fn): void {try {$fn();} catch (InvalidArgumentException|RuntimeException $e) {if ($e instanceof PDOException) throw $e;t_ok($name,true);return;}t_ok($name,false);}
function t_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function t_rows(): array {return all('SELECT * FROM '.table('mail_templates').' ORDER BY id');}
function t_history(string $id): array {return all('SELECT * FROM '.table('mail_template_revisions').' WHERE template_id=? ORDER BY revision',[$id]);}
function t_fail_audit(callable $fn): bool {
    db()->exec('CREATE TRIGGER tplci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    try {$fn();$failed=false;}catch (PDOException) {$failed=true;}finally {db()->exec('DROP TRIGGER tplci_fail');}return $failed;
}
if (($argv[1]??'')==='edit-worker') {
    t_actor(2);
    try {mail_templates_change($argv[2],(int)$argv[3],'edit',['name'=>'Concurrent','subject'=>$argv[4],'body'=>'Retained']);echo 'saved';}
    catch (RuntimeException $e) {if ($e instanceof PDOException || !str_contains($e->getMessage(),'changed')) throw $e;echo 'stale';}
    exit;
}
try {
    foreach (schema_statements('tplci_') as $name=>$sql) if (!str_starts_with($name,'mail_template')) db()->exec($sql);
    foreach (default_settings() as $key=>$value) save_setting($key,$value);
    $now=gmdate('Y-m-d H:i:s');
    for ($id=1;$id<=4;$id++) query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Template staff '.$id,'template'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);
    staff_seed_owner(1);
    foreach ([2=>['mail.templates.manage'],3=>['website.manage'],4=>['mail.templates.manage']] as $id=>$grants) query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[$id,json_encode($grants)]);
    t_actor();$users=all('SELECT * FROM '.table('users').' ORDER BY id');
    t_deny('Owner setup requires current password',fn()=>mail_templates_migrate('wrong'));
    t_actor(2);t_deny('Template permission cannot enable schema',fn()=>mail_templates_migrate($password));
    t_deny('Library unavailable before explicit setup',fn()=>mail_templates_list());t_actor();
    foreach (mail_templates_schema('tplci_') as $sql) db()->exec($sql);
    db()->exec('ALTER TABLE '.table('mail_template_revisions').' DROP INDEX template_revision');
    t_deny('Interrupted setup rejects missing history uniqueness',fn()=>mail_templates_migrate($password));
    db()->exec('ALTER TABLE '.table('mail_template_revisions').' ADD UNIQUE template_revision(template_id,revision)');
    db()->exec('ALTER TABLE '.table('mail_templates').' MODIFY id CHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');
    t_deny('Setup rejects case-folded template identifiers',fn()=>mail_templates_migrate($password));
    db()->exec('ALTER TABLE '.table('mail_templates').' MODIFY id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin');
    query('INSERT INTO '.table('mail_templates')." (id,revision,name,subject,body,status,visibility,owner_id,updated_by,updated_at) VALUES (?,1,'Unrecognized','Subject','Retain','draft','personal',1,1,?)",[str_repeat('0',32),$now]);
    t_deny('Setup refuses populated unrecognized template records',fn()=>mail_templates_migrate($password));
    t_ok('Rejected setup retains unrecognized bytes',one('SELECT body FROM '.table('mail_templates').' WHERE id=?',[str_repeat('0',32)])['body']==='Retain');
    query('DELETE FROM '.table('mail_templates').' WHERE id=?',[str_repeat('0',32)]);
    t_ok('Setup audit failure rolls back readiness',t_fail_audit(fn()=>mail_templates_migrate($password))&&!mail_templates_ready());
    mail_templates_migrate($password);mail_templates_migrate($password);
    t_ok('Repeated additive setup preserves staff records',mail_templates_ready()&&all('SELECT * FROM '.table('users').' ORDER BY id')===$users);
    t_actor(3);t_deny('Website grant gives no template access',fn()=>mail_templates_list('all'));
    t_actor(2);t_ok('Template-only staff receives correct home',staff_home(admin_user())==='mail-templates');
    t_ok('Template permission grants no accounts or send privileges',!staff_can('mail.accounts.manage')&&!staff_can('mail.send')&&!staff_can('staff.manage'));
    t_ok('View and action use explicit template grant',staff_view_permission('mail-template')==='mail.templates.manage'&&staff_action_permission('mail_templates_create')==='mail.templates.manage'&&staff_action_permission('mail_templates_setup')==='staff.manage');
    $id=mail_templates_create('Welcome','Hello {{client_name}}','Project {{project_name}} — {{portal_url}}','shared');
    $personal=mail_templates_create('Private notes','Personal subject','Only my words','personal');
    $row=mail_templates_get($id)['row'];
    t_ok('Create records draft owner and first history', $row['status']==='draft'&&(int)$row['owner_id']===2&&(int)$row['revision']===1&&count(t_history($id))===1);
    t_ok('Library lists visible shared and own personal templates',count(mail_templates_list('all'))===2);
    t_actor(4);t_ok('Other staff sees only shared templates',array_column(mail_templates_list('all'),'id')===[$id]);
    t_deny('Other staff cannot read personal template',fn()=>mail_templates_get($personal));
    t_deny('Other staff cannot read personal history',fn()=>mail_templates_history($personal));
    t_deny('Other staff cannot preview personal template',fn()=>mail_templates_preview($personal,[],[]));
    t_deny('Other staff cannot edit shared source',fn()=>mail_templates_change($id,1,'edit',['name'=>'Replace','subject'=>'Bad','body'=>'Bad']));
    t_deny('Other staff cannot archive shared source',fn()=>mail_templates_change($id,1,'archive'));
    $copy=mail_templates_change($id,1,'duplicate',['name'=>'My welcome']);
    t_ok('Duplicate has new personal identity and draft state',$copy!==$id&&mail_templates_get($copy)['row']['visibility']==='personal'&&(int)mail_templates_get($copy)['row']['owner_id']===4);
    t_actor();t_deny('Owner cannot read another persons personal template',fn()=>mail_templates_get($personal));
    mail_templates_change($id,1,'activate');t_ok('Owner can moderate shared template',mail_templates_get($id)['row']['status']==='active');
    t_actor(2);$before=t_history($id);mail_templates_change($id,2,'edit',['name'=>'Renamed','subject'=>'New {{client_name}}','body'=>'Project {{project_name}}']);
    t_ok('Editing active returns draft while retaining immutable prior versions',mail_templates_get($id)['row']['status']==='draft'&&array_slice(t_history($id),0,2)===$before);
    t_deny('Stale edit cannot overwrite later revision',fn()=>mail_templates_change($id,2,'edit',['name'=>'Stale','subject'=>'Stale','body'=>'Stale']));
    t_deny('Stale activation is rejected',fn()=>mail_templates_change($id,2,'activate'));
    mail_templates_change($id,3,'activate');t_deny('Active template cannot activate twice',fn()=>mail_templates_change($id,4,'activate'));
    $beforeRows=t_rows();$beforeHistory=t_history($id);
    t_ok('Audit failure rolls back current row and revision history',t_fail_audit(fn()=>mail_templates_change($id,4,'archive'))&&t_rows()===$beforeRows&&t_history($id)===$beforeHistory);
    t_ok('Create audit failure leaves no orphan template',t_fail_audit(fn()=>mail_templates_create('Rollback','Subject','Body','personal'))&&t_rows()===$beforeRows);
    mail_templates_change($id,4,'archive');t_deny('Archived template cannot be edited',fn()=>mail_templates_change($id,5,'edit',['name'=>'Old','subject'=>'Old','body'=>'Old']));
    t_deny('Archived template cannot reactivate',fn()=>mail_templates_change($id,5,'activate'));
    $revive=mail_templates_change($id,5,'duplicate',['name'=>'New draft from archive']);
    t_ok('Archive remains discoverable and can be copied',count(mail_templates_list('archived'))===1&&mail_templates_get($revive)['row']['status']==='draft');
    foreach ([['Bad {{UNKNOWN}}','Body'],['Subject','{{ unknown }}'],['Subject','{{unknown}}'],["Injected\r\nBcc: x@example.invalid",'Body'],['Subject',"Nul\0text"],[str_repeat('x',201),'Body'],['Subject',str_repeat('x',20001)]] as $i=>[$subject,$body]) {
        t_deny('Invalid content rejected case '.$i,fn()=>mail_templates_create('Invalid',$subject,$body,'personal'));
    }
    t_deny('Unknown visibility rejected',fn()=>mail_templates_create('Invalid','Subject','Body','public'));
    $previewId=mail_templates_create('Preview','Hello {{client_name}}','Project {{project_name}} {{portal_url}}','personal');
    $content=mail_templates_get($previewId)['row'];$beforeRows=t_rows();$beforeHistory=t_history($previewId);
    $preview=mail_templates_preview($previewId,$content,['client_name'=>'{{project_name}}','project_name'=>'<script>alert(1)</script>']);
    t_ok('Preview replacement values are literal and body remains plain text',$preview['subject']==='Hello {{project_name}}'&&str_contains($preview['body'],'<script>')&&$preview['unresolved']===[]);
    t_ok('Preview has no persistence effects',t_rows()===$beforeRows&&t_history($previewId)===$beforeHistory);
    $missing=mail_templates_preview($previewId,$content,[]);t_ok('Preview identifies absent sample values',$missing['unresolved']===['client_name','project_name']);
    t_deny('Preview header-injection sample is rejected',fn()=>mail_templates_preview($previewId,$content,['client_name'=>"Name\nBcc: bad"]));
    t_deny('Preview nonstring sample is rejected',fn()=>mail_templates_preview($previewId,$content,['client_name'=>['bad']]));
    $keys=mail_templates_registry()->all();t_ok('Registry contains only documented nonsensitive fields',array_keys($keys)===['client_name','portal_url','project_name','studio_name']);
    $_SESSION=[];t_deny('Unauthenticated actor cannot read templates',fn()=>mail_templates_list('all'));
    $_SESSION=['client_id'=>1,'client_auth'=>str_repeat('a',32)];t_deny('Client identity cannot substitute for staff',fn()=>mail_templates_get($id));
    t_actor(2);$_GET['support']='pretend';t_deny('Support context cannot read templates',fn()=>mail_templates_get($id));unset($_GET['support']);
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=2');t_deny('Disabled staff immediately loses access',fn()=>mail_templates_list('all'));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=2');t_actor(2);
    $_SESSION['auth_version']='stale';t_deny('Stale authentication version cannot mutate',fn()=>mail_templates_change($previewId,1,'archive'));t_actor(2);
    $race=mail_templates_create('Concurrent','Before','Retained','personal');
    $workers=[];
    foreach (['First editor','Second editor'] as $subject) {
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'edit-worker',$race,'1',$subject],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $out=[];foreach ($workers as [$process,$pipes]) {$out[]=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);t_ok('Concurrent worker completes safely '.count($out),$exit===0&&$stderr==='');}
    sort($out);t_ok('Concurrent writes yield exactly one saved and one stale result',$out===['saved','stale']&&count(t_history($race))===2);
    for ($revision=2;$revision<25;$revision++) mail_templates_change($race,$revision,'edit',['name'=>'History pagination','subject'=>'Version '.($revision+1),'body'=>'Retained']);
    $historyFirst=mail_templates_history($race,1);$historySecond=mail_templates_history($race,2);
    t_ok('History pagination preserves all versions without overlap',count($historyFirst)===21&&count($historySecond)===5&&!array_intersect(array_column(array_slice($historyFirst,0,20),'revision'),array_column($historySecond,'revision'))&&$historySecond[4]['revision']===1);
    for ($i=0;$i<24;$i++) mail_templates_create('Page item '.str_pad((string)$i,2,'0',STR_PAD_LEFT),'Subject','Body','personal');
    $first=mail_templates_list('all','Page item',1);$second=mail_templates_list('all','Page item',2);
    t_ok('Library pagination has bounded rows and no repeated page members',count($first)===21&&count($second)===4&&!array_intersect(array_column(array_slice($first,0,20),'id'),array_column($second,'id')));
    mail_templates_create('Literal % _ !','Subject','Body','personal');t_ok('Search wildcard characters stay literal',count(mail_templates_list('all','% _ !'))===1);
    t_ok('Audit metadata contains no template bodies',!str_contains(json_encode(all('SELECT detail FROM '.table('audit'))),'Only my words'));
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);
file_put_contents(__DIR__.'/../qa/mail-templates-native-results.json',json_encode(['passed'=>$ok,'checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native template checks'.PHP_EOL;exit($ok?0:1);
