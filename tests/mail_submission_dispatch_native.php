<?php
/** Disposable MariaDB orchestration checks. Synthetic secrets and fake transport only; no SMTP. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/mail/submission_dispatch.php';
require_once __DIR__.'/../site/app/mail/submission_views.php';
use CajunWebPro\Foundation\MailCompose\DraftContent;
use CajunWebPro\Foundation\MailSubmission\{PreparedMime,TransportOutcome};
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
$private=__DIR__.'/../test-runtime/submission-dispatch-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-dispatch-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'sdci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic dispatch owner passphrase';$dnsCalls=0;$driverCalls=0;$observations=[];
function sd_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function sd_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;sd_ok($name,true);return;}sd_ok($name,false);}
function sd_actor(int $id=2):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function sd_content(string $subject='SYNTHETIC_DISPATCH_SUBJECT'):DraftContent {return new DraftContent(['client@example.invalid'],[],['private@example.invalid'],$subject,'SYNTHETIC_DISPATCH_BODY');}
function sd_draft(string $account):string {$id=bin2hex(random_bytes(16));mail_drafts_save($id,0,$account,1,sd_content());return $id;}
function sd_age():void {query('UPDATE '.table('mail_submission_dispatches').' SET started_epoch=0');}
function sd_lock_owner():mixed {$key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);return query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn();}
function sd_resolver(string $host):array {
    global $dnsCalls,$observations;$dnsCalls++;
    $observations[]=['stage'=>'dns','transaction'=>db()->inTransaction(),'lock'=>sd_lock_owner(),'host'=>$host];return ['8.8.8.8','1.1.1.1'];
}
function sd_driver(array $profile,PreparedMime $message,string $attempt,#[\SensitiveParameter] array $secrets,string $address,Closure $authorize):TransportOutcome {
    global $driverCalls,$observations;$driverCalls++;$authorize();
    $fence=one('SELECT * FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$message->snapshot->id]);
    $observations[]=['stage'=>'driver','transaction'=>db()->inTransaction(),'lock'=>sd_lock_owner(),'connection'=>query('SELECT CONNECTION_ID()')->fetchColumn(),'address'=>$address,'selected'=>$profile['smtp_username']==='selected-user'&&$secrets['smtp_password']==='Synthetic selected outgoing','fence'=>$fence&&$fence['id']===$attempt,'audit'=>(bool)one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['mail_submissions.dispatched','Submission '.$message->snapshot->id.' %'])];
    return new TransportOutcome($message,$attempt,'accepted','confirmation','accepted',250);
}
function sd_send(string $draft,?string $id=null,?callable $resolver=null,?callable $driver=null):string {return mail_submissions_send($id??bin2hex(random_bytes(16)),$draft,1,1,$resolver??'sd_resolver',$driver??'sd_driver');}
function sd_result(string $id):?array {return one('SELECT * FROM '.table('mail_submission_results').' WHERE submission_id=?',[$id]);}
function sd_fence(string $id):?array {return one('SELECT * FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$id]);}
function sd_trigger(string $action):void {db()->exec('CREATE TRIGGER sdci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='mail_submissions.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic dispatch audit failure'; END IF; END");}
function sd_audit_fail(string $action,callable $fn):bool {sd_trigger($action);try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER sdci_fail');}return $failed;}
function sd_worker(string $id,string $draft):array {
    $p=proc_open([PHP_BINARY,__FILE__,'worker',$id,$draft],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start dispatch worker');foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);return [$p,$pipes];
}
function sd_finish(array $worker):string {[$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$error!=='')throw new RuntimeException('Dispatch worker failed: '.$error);return $out;}
if(($argv[1]??'')==='worker') {
    sd_actor();echo "ready\n";fflush(STDOUT);fgets(STDIN);
    echo sd_send($argv[3],$argv[2],null,static function(...$args):TransportOutcome {
        global $private;file_put_contents($private.'/invocations.log',"transport\n",FILE_APPEND|LOCK_EX);usleep(250000);return sd_driver(...$args);
    });exit;
}
try {
    foreach(schema_statements('sdci_') as $sql)db()->exec($sql);foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Dispatch staff '.$i,'dispatch'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2,3] as $actor)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,\'administrator\',1,?)',[$actor,'["mail.send"]']);
    sd_actor(1);mail_accounts_migrate($password);mail_drafts_migrate($password);mail_submissions_migrate($password);
    $a=mail_accounts_save('',0,['label'=>'Selected','address'=>'Sender@example.NET','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'incoming-user','smtp_host'=>'smtp.example.net','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'selected-user'],true,['imap_password'=>'Synthetic incoming','smtp_password'=>'Synthetic selected outgoing'],$password);
    foreach([2,3] as $actor)mail_accounts_grant($a,$actor,0,true,true,true,0,$password);
    $keyBytes=file_get_contents($private.'/mail-master.key');file_put_contents($private.'/provider-master.key','SYNTHETIC_PROVIDER_KEY');sd_actor();

    $draft=sd_draft($a);$id=bin2hex(random_bytes(16));db()->beginTransaction();sd_deny('Entry refuses a surrounding database transaction',fn()=>sd_send($draft,$id));db()->rollBack();
    sd_ok('Transaction refusal performs no preparation or callbacks',!one('SELECT id FROM '.table('mail_submissions').' WHERE id=?',[$id])&&$dnsCalls===0&&$driverCalls===0);
    sd_ok('Failed preparation audit prevents intent DNS and transport',sd_audit_fail('prepared',fn()=>sd_send($draft,$id))&&!one('SELECT id FROM '.table('mail_submissions').' WHERE id=?',[$id])&&$dnsCalls===0&&$driverCalls===0);
    sd_ok('One newly prepared reviewed intent records acceptance',sd_send($draft,$id)===$id&&sd_result($id)['status']==='accepted'&&$dnsCalls===1&&$driverCalls===1);
    sd_ok('DNS occurs outside transaction and staff mutex',$observations[0]===['stage'=>'dns','transaction'=>false,'lock'=>null,'host'=>'smtp.example.net']);
    $o=$observations[1];sd_ok('Transport holds staff mutex with no database transaction',!$o['transaction']&&(string)$o['lock']===(string)$o['connection']);
    sd_ok('Selected credentials and first validated numeric address only',$o['selected']&&$o['address']==='8.8.8.8');
    sd_ok('Committed dispatch fence and audit precede transport',$o['fence']&&$o['audit']);
    sd_ok('Exact replay performs no DNS or driver call',sd_send($draft,$id)===$id&&$dnsCalls===1&&$driverCalls===1);
    sd_ok('Different form replay for same reviewed revision does no work',sd_send($draft)===$id&&$dnsCalls===1&&$driverCalls===1);
    file_put_contents($private.'/mail-master.key','SYNTHETIC_INVALID_KEY');
    try {sd_ok('Replay does not open the private credential key',sd_send($draft,$id)===$id&&$dnsCalls===1&&$driverCalls===1);}
    finally {file_put_contents($private.'/mail-master.key',$keyBytes);chmod($private.'/mail-master.key',0600);}
    sd_actor(3);sd_deny('Other assigned author cannot replay or disclose',fn()=>sd_send($draft,$id));sd_actor();
    sd_ok('Unauthorized replay did not perform callbacks',$dnsCalls===1&&$driverCalls===1);
    $abandoned=sd_draft($a);$abandonedId=mail_submissions_prepare(bin2hex(random_bytes(16)),$abandoned,1,1);
    sd_ok('Interrupted preparation is never resumed automatically',sd_send($abandoned,$abandonedId)===$abandonedId&&!sd_fence($abandonedId)&&!sd_result($abandonedId)&&$dnsCalls===1&&$driverCalls===1);

    foreach(['private'=>['8.8.8.8','127.0.0.1'],'empty'=>[],'invalid'=>['not-an-ip']] as $name=>$answers) {
        $did=sd_send(sd_draft($a),null,fn()=>$answers);$r=sd_result($did);
        sd_ok('Full DNS set refusal '.$name,$r['scope']==='preparation'&&$r['reason']==='destination'&&!sd_fence($did)&&$driverCalls===1);
    }
    foreach(['timeout','limit','protocol','authentication'] as $reason) {
        $did=sd_send(sd_draft($a),null,static function()use($reason){throw new ProbeFailure($reason);});
        sd_ok('Finite resolver failure '.$reason,sd_result($did)['reason']===($reason==='authentication'?'connection':$reason)&&!sd_fence($did));
    }
    $did=sd_send(sd_draft($a),null,static function(){throw new RuntimeException('SYNTHETIC_SECRET_EXCEPTION');});sd_ok('Unexpected DNS exceptions are sanitized',sd_result($did)['reason']==='connection');

    foreach(['grant','global','account','session','draft','sender','host'] as $mode) {
        sd_age();sd_actor();$d=sd_draft($a);$before=$driverCalls;
        $did=sd_send($d,null,static function()use($mode,$a,$d) {
            if($mode==='grant')query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$a]);
            if($mode==='global')query('UPDATE '.table('staff_profiles')." SET permissions='[]' WHERE user_id=2");
            if($mode==='account')query('UPDATE '.table('mail_accounts').' SET revision=2 WHERE id=?',[$a]);
            if($mode==='session')$_SESSION['last_activity']=0;
            if($mode==='draft')mail_drafts_save($d,1,$a,1,sd_content('Changed during DNS'));
            if($mode==='sender')query('UPDATE '.table('mail_accounts')." SET address='different@example.net' WHERE id=?",[$a]);
            if($mode==='host')query('UPDATE '.table('mail_accounts')." SET smtp_host='changed.example.net' WHERE id=?",[$a]);
            return ['8.8.8.8'];
        });
        sd_ok('Authority or settings change during DNS prevents transport '.$mode,sd_result($did)['reason']==='access_changed'&&$driverCalls===$before);
        sd_ok('Authority refusal retains its correct fence boundary '.$mode,$mode==='host'?sd_fence($did)!==null:sd_fence($did)===null);
        query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);query('UPDATE '.table('staff_profiles')." SET permissions='[\"mail.send\"]' WHERE user_id=2");query('UPDATE '.table('mail_accounts')." SET revision=1,address='Sender@example.NET',smtp_host='smtp.example.net' WHERE id=?",[$a]);sd_actor();
    }
    sd_age();$d=sd_draft($a);$did=bin2hex(random_bytes(16));$before=$driverCalls;
    sd_ok('Dispatch audit failure prevents driver and rolls back fence',sd_audit_fail('dispatched',fn()=>sd_send($d,$did))&&!sd_fence($did)&&!sd_result($did)&&$driverCalls===$before);
    $dnsBefore=$dnsCalls;sd_ok('Failed dispatch audit does not allow automatic replay',sd_send($d,$did)===$did&&$dnsCalls===$dnsBefore&&$driverCalls===$before);
    $encoded=mail_accounts_load($a)['credentials'];query('UPDATE '.table('mail_accounts')." SET credentials='damaged' WHERE id=?",[$a]);sd_age();$did=sd_send(sd_draft($a));query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',[$encoded,$a]);
    sd_ok('Damaged selected credentials record unused dispatch refusal',sd_fence($did)!==null&&sd_result($did)['scope']==='dispatch'&&sd_result($did)['reason']==='credential'&&$driverCalls===$before);
    sd_age();$did=sd_send(sd_draft($a),null,null,static function(...$args):TransportOutcome {global $driverCalls;$driverCalls++;$_SESSION['last_activity']=0;try{$args[5]();}catch(ProbeFailure $e){return new TransportOutcome($args[1],$args[2],'not_submitted','authentication',$e->getMessage());}throw new LogicException('Expected authority failure');});
    sd_ok('Transport callback sees current session loss',sd_result($did)['status']==='not_submitted'&&sd_result($did)['reason']==='access_changed');sd_actor();
    sd_age();$did=sd_send(sd_draft($a),null,null,static function(...$args):TransportOutcome {$result=sd_driver(...$args);$_SESSION['last_activity']=0;return $result;});
    sd_ok('Observed acceptance survives session loss after response',sd_result($did)['status']==='accepted');sd_deny('Expired session cannot disclose accepted history',fn()=>mail_submissions_get($did));sd_actor();
    sd_age();$did=sd_send(sd_draft($a),null,null,static function(...$args):TransportOutcome {$result=sd_driver(...$args);query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$args[0]['id']]);return $result;});
    sd_ok('Observed acceptance survives grant loss after response',sd_result($did)['status']==='accepted');sd_deny('Revoked grant cannot disclose accepted history',fn()=>mail_submissions_get($did));query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);
    foreach(['accepted'=>['accepted','confirmation','accepted',250],'rejected'=>['rejected','confirmation','server_rejected',550],'unknown'=>['unknown','confirmation','timeout',null],'recipient'=>['not_submitted','recipient','server_rejected',550]] as $name=>$fields) {
        sd_age();$did=sd_send(sd_draft($a),null,null,fn(...$args)=>new TransportOutcome($args[1],$args[2],...$fields));$r=sd_result($did);
        sd_ok('Coordinator preserves finite result '.$name,[$r['status'],$r['phase'],$r['reason'],$r['smtp_code']===null?null:(int)$r['smtp_code']]===$fields);
    }
    sd_age();$d=sd_draft($a);$did=sd_send($d,null,null,static function(){throw new RuntimeException('SYNTHETIC_SECRET_EXCEPTION');});
    sd_ok('Escaping driver exception remains uncertain',sd_result($did)['status']==='unknown'&&sd_result($did)['phase']==='confirmation');mail_drafts_save($d,1,$a,1,sd_content('Edited after uncertain result'));
    sd_deny('Uncertain attempt blocks sending an edited revision',fn()=>mail_submissions_send(bin2hex(random_bytes(16)),$d,2,1,'sd_resolver','sd_driver'));
    foreach(['attempt','submission','return_type','contradictory'] as $mode) {
        sd_age();$d=sd_draft($a);$did=bin2hex(random_bytes(16));
        $driver=static function(...$args)use($mode) {
            if($mode==='return_type')return null;
            if($mode==='submission') {$s=$args[1]->snapshot;$m=PreparedMime::fromSnapshot(new \CajunWebPro\Foundation\MailSubmission\SubmissionSnapshot(bin2hex(random_bytes(16)),$s->authorId,$s->draftId,$s->draftRevision,$s->accountId,$s->accountRevision,$s->from,$s->content,$s->preparedEpoch));return new TransportOutcome($m,$args[2],'accepted','confirmation','accepted',250);}
            return new TransportOutcome($args[1],$mode==='attempt'?bin2hex(random_bytes(16)):$args[2],'accepted',$mode==='contradictory'?'prepare':'confirmation','accepted',250);
        };
        sd_deny('Invalid driver receipt refused '.$mode,fn()=>sd_send($d,$did,null,$driver));sd_ok('Invalid receipt retains unresolved fence '.$mode,sd_fence($did)!==null&&!sd_result($did));
    }
    sd_age();$d=sd_draft($a);$did=bin2hex(random_bytes(16));$before=$driverCalls;
    sd_ok('Result audit failure retains committed unresolved fence',sd_audit_fail('finished',fn()=>sd_send($d,$did))&&sd_fence($did)!==null&&!sd_result($did)&&$driverCalls===$before+1);
    $dnsBefore=$dnsCalls;sd_ok('Unresolved result failure cannot retry network',sd_send($d,$did)===$did&&$driverCalls===$before+1&&$dnsCalls===$dnsBefore);
    // Synthetic timestamp aging exercises explicit account cooldown without waiting 30 seconds.
    sd_age();$first=sd_send(sd_draft($a));$before=$driverCalls;$did=sd_send(sd_draft($a));sd_ok('Account cooldown becomes finite preparation refusal',sd_result($did)['scope']==='preparation'&&sd_result($did)['reason']==='limit'&&!sd_fence($did)&&$driverCalls===$before);
    foreach(['same','different'] as $mode) {
        sd_age();$d=sd_draft($a);$did=bin2hex(random_bytes(16));file_put_contents($private.'/invocations.log','');
        $w1=sd_worker($did,$d);$w2=sd_worker($mode==='same'?$did:bin2hex(random_bytes(16)),$d);fgets($w1[1][1]);fgets($w2[1][1]);fwrite($w1[1][0],"go\n");fflush($w1[1][0]);fwrite($w2[1][0],"go\n");fflush($w2[1][0]);$r1=sd_finish($w1);$r2=sd_finish($w2);
        sd_ok('Concurrent '.$mode.' forms return one durable identity',$r1===$r2&&strlen($r1)===32);
        sd_ok('Concurrent '.$mode.' forms invoke transport exactly once',file_get_contents($private.'/invocations.log')==="transport\n"&&sd_result($r1)['status']==='accepted');
    }
    $audit=json_encode(all('SELECT action,detail FROM '.table('audit')),JSON_THROW_ON_ERROR);
    sd_ok('Audit excludes private message credentials and exception text',!str_contains($audit,'SYNTHETIC_DISPATCH_')&&!str_contains($audit,'Synthetic selected outgoing')&&!str_contains($audit,'private@example.invalid')&&!str_contains($audit,'SYNTHETIC_SECRET_EXCEPTION'));
    sd_ok('Original mail and provider keys unchanged',file_get_contents($private.'/mail-master.key')===$keyBytes&&file_get_contents($private.'/provider-master.key')==='SYNTHETIC_PROVIDER_KEY');
    $d=sd_draft($a);$before=all('SELECT * FROM '.table('mail_submissions').' ORDER BY id');$auditBefore=all('SELECT * FROM '.table('audit').' ORDER BY id');$calls=[$dnsCalls,$driverCalls];
    $review=mail_submissions_review($d);sd_ok('Review returns exact current author saved draft and account',$review['existing']===null&&$review['draft']['id']===$d&&$review['account']['id']===$a);
    sd_ok('Review performs no persistence audit DNS or transport',all('SELECT * FROM '.table('mail_submissions').' ORDER BY id')===$before&&all('SELECT * FROM '.table('audit').' ORDER BY id')===$auditBefore&&[$dnsCalls,$driverCalls]===$calls);
    sd_deny('Review refuses earlier saved draft version',fn()=>mail_submissions_review($d,2,1));sd_deny('Review refuses earlier saved account version',fn()=>mail_submissions_review($d,1,2));
    query('UPDATE '.table('mail_accounts').' SET revision=2 WHERE id=?',[$a]);sd_deny('Review requires acknowledgement of changed sender settings',fn()=>mail_submissions_review($d));query('UPDATE '.table('mail_accounts').' SET revision=1 WHERE id=?',[$a]);
    $rows=mail_submissions_list($a);sd_ok('Private history uses bounded 21-row lookahead',count($rows)===21);
    sd_ok('History projection excludes recipients body credential and attempt token',array_keys($rows[0])===['id','draft_id','draft_revision','created_at','subject','status']);
    sd_ok('History pagination does not repeat prior page ids',!array_intersect(array_column(array_slice($rows,0,20),'id'),array_column(mail_submissions_list($a,2),'id')));
    $existing=mail_submissions_review($draft);sd_ok('Review of accepted saved version returns existing private history',$existing['existing']['id']===$id&&$existing['existing']['result']['status']==='accepted');
    sd_actor(3);sd_ok('Other assigned author sees no private submission history',mail_submissions_list($a)===[]);sd_deny('Other assigned author cannot review another draft',fn()=>mail_submissions_review($d));sd_actor();
    query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$a]);sd_deny('Revoked mailbox denies list disclosure',fn()=>mail_submissions_list($a));sd_deny('Revoked mailbox denies review disclosure',fn()=>mail_submissions_review($d));query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/mail-submission-dispatch-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB current-authorized server-only DNS/dispatch orchestration; synthetic credentials, fake transport, no SMTP or application UI','checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo ($ok?'PASS':'FAIL').': '.count($checks)." native submission orchestration checks\n";exit($ok?0:1);
