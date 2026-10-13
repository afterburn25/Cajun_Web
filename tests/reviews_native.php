<?php
/** Native L-02 acceptance against disposable synthetic tables only. */
declare(strict_types=1);
if (PHP_SAPI!=='cli' || getenv('CWP_TEST_DATABASE')!=='cwp_test') exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/app/marketing/growth.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='revci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];start_session();
$checks=[];$password='Synthetic review owner passphrase';
function r_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if (!$ok) throw new RuntimeException($name);}
function r_deny(string $name,callable $fn): void {try {$fn();}catch (InvalidArgumentException|RuntimeException $e) {if ($e instanceof PDOException) throw $e;r_ok($name,true);return;}r_ok($name,false);}
function r_actor(int $id=1): void {$user=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$user['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function r_lead(string $service='Website review',int $consent=1): int {
    $key=bin2hex(random_bytes(10));query('INSERT INTO '.table('leads')." (reference,request_key,name,email,service,budget,timeline,message,consent,ip_hash,status,notes,created_at) VALUES (?,?,?,?,?,'Agreed','Flexible','Review these synthetic business goals',?,?,'new','Retain existing note',?)",[$key,hash('sha256',$key),'Synthetic prospect','prospect@example.invalid',$service,$consent,str_repeat('0',64),gmdate('Y-m-d H:i:s')]);return (int)db()->lastInsertId();
}
function r_finding(string $evidence='human-review'): array {return [['title'=>'Clear contact route','detail'=>'Human-authored synthetic finding','evidence'=>$evidence,'source'=>$evidence==='human-review'?'':'Synthetic isolated observation','observed_at'=>$evidence==='human-review'?'':gmdate('Y-m-d\TH:i:s',time()-10)]];}
function r_audit_failure(callable $fn): bool {
    db()->exec('CREATE TRIGGER revci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    try {$fn();$failed=false;}catch (PDOException) {$failed=true;}finally {db()->exec('DROP TRIGGER revci_fail');}return $failed;
}
function r_capture_form(): array {$token=growth_token();$_SESSION['growth_tokens'][$token]=time()-3;$_POST['csrf']=csrf();return ['submission_token'=>$token,'kind'=>'website-review','name'=>'Synthetic public prospect','email'=>'public@example.invalid','company'=>'','phone'=>'','timeline'=>'','message'=>'Please review this synthetic business website.','consent'=>'1','website_address'=>'','current_url'=>'Example.org/public'];}
if (($argv[1]??'')==='worker') {
    r_actor(2);
    try {reviews_save_report((int)$argv[2],(int)$argv[3],$argv[4],r_finding());echo 'saved';}
    catch (RuntimeException $e) {if ($e instanceof PDOException || !str_contains($e->getMessage(),'changed')) throw $e;echo 'stale';}exit;
}
try {
    foreach (schema_statements('revci_') as $name=>$sql) if (!str_starts_with($name,'website_review')) db()->exec($sql);
    foreach (default_settings() as $key=>$value) save_setting($key,$value);
    for ($id=1;$id<=3;$id++) query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Review staff '.$id,'review'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach ([2=>['website.manage'],3=>['clients.manage']] as $id=>$permissions) query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[$id,json_encode($permissions)]);
    r_actor();$lead=r_lead();$original=one('SELECT * FROM '.table('leads').' WHERE id=?',[$lead]);
    r_deny('Setup needs owner current password',fn()=>reviews_migrate('wrong'));
    r_actor(2);r_deny('Website staff cannot initialize schema',fn()=>reviews_migrate($password));r_deny('Review unavailable before setup',fn()=>reviews_get($lead));r_actor();
    foreach (reviews_schema('revci_') as $sql) db()->exec($sql);
    db()->exec('ALTER TABLE '.table('website_review_revisions').' DROP INDEX review_revision');r_deny('Setup refuses missing revision uniqueness',fn()=>reviews_migrate($password));db()->exec('ALTER TABLE '.table('website_review_revisions').' ADD UNIQUE review_revision(review_id,revision)');
    $fk=one('SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',['revci_website_review_reports'])['CONSTRAINT_NAME'];
    if (!preg_match('/^[a-z0-9_]+$/D',$fk)) throw new RuntimeException('Unexpected synthetic foreign key identifier.');
    db()->exec('ALTER TABLE '.table('website_review_reports').' DROP FOREIGN KEY `'.$fk.'`');r_deny('Setup refuses missing personal-report deletion relationship',fn()=>reviews_migrate($password));db()->exec('ALTER TABLE '.table('website_review_reports').' ADD FOREIGN KEY (review_id) REFERENCES '.table('website_reviews').'(id) ON DELETE CASCADE');
    query('INSERT INTO '.table('website_reviews')." (lead_id,website,stage,revision,updated_by,updated_at,created_at) VALUES (?,'https://example.org/','received',1,1,?,?)",[$lead,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    r_deny('Setup refuses populated unknown review records',fn()=>reviews_migrate($password));r_ok('Refused setup retains unknown record',reviews_row($lead)!==null);query('DELETE FROM '.table('website_reviews').' WHERE lead_id=?',[$lead]);
    r_ok('Setup audit failure rolls back readiness',r_audit_failure(fn()=>reviews_migrate($password))&&!reviews_ready());reviews_migrate($password);reviews_migrate($password);
    r_ok('Repeated setup preserves original inquiry bytes',reviews_ready()&&one('SELECT * FROM '.table('leads').' WHERE id=?',[$lead])===$original);
    r_ok('Bare hostname intentionally normalizes',reviews_address('Example.ORG/path?q=1')->url()==='https://example.org/path?q=1');
    foreach (['https://example.org/#part','https://user:pass@example.org/','ftp://example.org/',"https://example.org/a\r\nb",'https://example.org/'.str_repeat('x',500)] as $i=>$url) r_deny('Address boundary '.$i,fn()=>reviews_address($url));
    r_ok('Private-address flag is informational and not a fetch',!reviews_address('http://127.0.0.1/')->isFetchCandidate());
    r_actor(3);r_deny('Client-management grant cannot read review',fn()=>reviews_get($lead));r_deny('Client-management grant cannot adopt review',fn()=>reviews_adopt($lead,'example.org'));r_actor(2);
    reviews_adopt($lead,'example.org/legacy');$review=reviews_get($lead);$rid=(int)$review['row']['id'];
    r_ok('Explicit legacy adoption retains inquiry and starts received history',$review['row']['stage']==='received'&&count($review['history'])===1&&$review['lead']===$original);
    r_deny('Existing adoption never overwrites review',fn()=>reviews_adopt($lead,'example.net'));
    r_deny('Other inquiry kinds cannot be adopted',fn()=>reviews_adopt(r_lead('Consultation request'),'example.org'));
    r_deny('Received cannot jump to drafted',fn()=>reviews_stage($lead,1,'report-drafted'));
    r_deny('Generic stage action cannot fake delivery',fn()=>reviews_stage($lead,1,'delivered'));
    reviews_stage($lead,1,'in-review');
    r_deny('Draft stage needs saved findings',fn()=>reviews_stage($lead,2,'report-drafted'));
    r_deny('Empty report findings refused',fn()=>reviews_save_report($lead,2,'Summary',[]));
    $bad=r_finding();$bad[0]['evidence']='absent-data';r_deny('Missing data needs source and observation',fn()=>reviews_save_report($lead,2,'Summary',$bad));
    $future=r_finding('lab-data');$future[0]['observed_at']=gmdate('Y-m-d\TH:i:s',time()+3600);r_deny('Future observations refused',fn()=>reviews_save_report($lead,2,'Summary',$future));
    r_ok('Failed validations retain current state',reviews_row($lead)['revision']===2&&reviews_report($rid)===null);
    reviews_save_report($lead,2,'Saved human report',r_finding('field-data'));r_ok('Saved report is still undelivered',reviews_report($rid)['delivered_at']===null&&reviews_row($lead)['stage']==='in-review');
    r_deny('Stale report edit refused',fn()=>reviews_save_report($lead,2,'Overwrite',r_finding()));
    reviews_stage($lead,3,'report-drafted');reviews_save_report($lead,4,'Revised report',r_finding());r_ok('Editing drafted report returns to review',reviews_row($lead)['stage']==='in-review'&&reviews_row($lead)['revision']===5);
    $before=reviews_get($lead);r_ok('Report audit failure rolls back report stage and history',r_audit_failure(fn()=>reviews_save_report($lead,5,'Do not retain',r_finding()))&&reviews_get($lead)===$before);
    $processes=[];
    foreach (['Concurrent A','Concurrent B'] as $title) {$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$lead,'5',$title],[1=>['pipe','w'],2=>['pipe','w']],$pipes);if (!is_resource($process)) throw new RuntimeException('Cannot start fixture worker.');$processes[]=[$process,$pipes];}
    $results=[];foreach ($processes as [$process,$pipes]) {$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);r_ok('Concurrent worker exits cleanly',proc_close($process)===0&&$err==='');$results[]=$out;}sort($results);
    r_ok('Concurrent editors commit once and reject stale peer',$results===['saved','stale']&&reviews_row($lead)['revision']===6);
    reviews_stage($lead,6,'report-drafted');$report=reviews_report($rid);$now=gmdate('Y-m-d\TH:i:s');
    r_deny('Delivery needs current password',fn()=>reviews_deliver($lead,7,$now,'email','Synthetic mail reference','wrong'));
    r_deny('Delivery cannot predate saved report',fn()=>reviews_deliver($lead,7,gmdate('Y-m-d\TH:i:s',(int)$report['prepared_at']-1),'email','Fixture',$password));
    r_deny('Delivery cannot claim a future time',fn()=>reviews_deliver($lead,7,gmdate('Y-m-d\TH:i:s',time()+3600),'email','Fixture',$password));
    query('UPDATE '.table('leads').' SET consent=0 WHERE id=?',[$lead]);r_deny('Current consent rechecked at delivery',fn()=>reviews_deliver($lead,7,$now,'email','Fixture',$password));query('UPDATE '.table('leads').' SET consent=1 WHERE id=?',[$lead]);
    $before=reviews_get($lead);r_ok('Delivery audit failure leaves draft unconfirmed',r_audit_failure(fn()=>reviews_deliver($lead,7,$now,'email','Fixture',$password))&&reviews_get($lead)===$before);
    reviews_deliver($lead,7,$now,'email','Synthetic externally delivered reference',$password);r_ok('Explicit external delivery and transition commit together',reviews_row($lead)['stage']==='delivered'&&reviews_report($rid)['delivery_reference']==='Synthetic externally delivered reference');
    r_deny('Delivered report cannot be edited',fn()=>reviews_save_report($lead,8,'Change delivered content',r_finding()));
    r_deny('Stale delivery cannot duplicate recorded event',fn()=>reviews_deliver($lead,7,$now,'email','Again',$password));reviews_stage($lead,8,'closed');r_deny('Closed review cannot reopen',fn()=>reviews_stage($lead,9,'in-review'));
    r_ok('Full retained lifecycle history exists',count(reviews_get($lead)['history'])===9);
    $_GET['support']='fixture';r_deny('Support mode cannot read review',fn()=>reviews_get($lead));r_deny('Support mode cannot delete inquiry',fn()=>reviews_delete_lead($lead));unset($_GET['support']);
    $session=$_SESSION;query('UPDATE '.table('users').' SET auth_version=? WHERE id=2',[bin2hex(random_bytes(16))]);r_deny('Revoked session denied on fresh read',fn()=>reviews_get($lead));r_actor(2);
    query('UPDATE '.table('staff_profiles')." SET permissions='[]' WHERE user_id=2");r_deny('Removed website permission takes effect immediately',fn()=>reviews_get($lead));query('UPDATE '.table('staff_profiles')." SET permissions='[\"website.manage\"]',enabled=0 WHERE user_id=2");r_deny('Disabled staff denied',fn()=>reviews_get($lead));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=2');r_actor(2);
    $_SESSION=[];r_deny('Unauthenticated or client-only session denied',fn()=>reviews_get($lead));r_actor();
    $form=r_capture_form();$out=growth_submit($form);$public=one('SELECT * FROM '.table('leads').' WHERE reference=?',[$out['reference']]);$publicReview=reviews_row((int)$public['id']);
    r_ok('Public intake atomically captures structured normalized review',$publicReview['website']==='https://example.org/public'&&count(reviews_get((int)$public['id'])['history'])===1&&$public['consent']===1);
    $again=growth_submit($form);r_ok('Idempotent public replay adds no review history',$again['reference']===$out['reference']&&count(reviews_get((int)$public['id'])['history'])===1);
    $count=(int)query('SELECT COUNT(*) FROM '.table('leads'))->fetchColumn();$form=r_capture_form();r_ok('Intake audit failure rolls back new lead and review',r_audit_failure(fn()=>growth_submit($form))&&(int)query('SELECT COUNT(*) FROM '.table('leads'))->fetchColumn()===$count);
    $before=reviews_get($lead);r_ok('Delete audit failure restores inquiry and dependent reports',r_audit_failure(fn()=>reviews_delete_lead($lead))&&reviews_get($lead)===$before);
    reviews_delete_lead($lead);r_ok('Explicit permanent inquiry deletion removes all linked personal review data',one('SELECT id FROM '.table('leads').' WHERE id=?',[$lead])===null&&reviews_row($lead)===null&&reviews_report($rid)===null&&!all('SELECT id FROM '.table('website_review_revisions').' WHERE review_id=?',[$rid]));
    r_ok('Unrelated public review survives targeted delete',reviews_row((int)$public['id'])!==null);
    $pageLead=r_lead();reviews_adopt($pageLead,'example.org');reviews_stage($pageLead,1,'in-review');for($rev=2;$rev<=23;$rev++)reviews_save_report($pageLead,$rev,'History '.$rev,r_finding());
    r_ok('Review history pagination has a sentinel and older page',count(reviews_get($pageLead)['history'])===21&&count(reviews_get($pageLead,2)['history'])===4);
    r_ok('Review routes reuse website permission and owner-only setup',staff_view_permission('website-review')==='website.manage'&&staff_action_permission('reviews_report')==='website.manage'&&staff_action_permission('reviews_setup')==='staff.manage');
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e."\n");}
@mkdir(__DIR__.'/../qa',0700,true);$pass=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/reviews-native-results.json',json_encode(['passed'=>$pass,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");echo count($checks).' native review checks: '.($pass?'PASS':'FAIL')."\n";exit($pass?0:1);
