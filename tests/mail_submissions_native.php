<?php
/** Disposable native storage/authority tests; no resolver, credential decryption or SMTP. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/mail/submissions.php';
require_once __DIR__.'/../site/app/mail/copies.php';
use CajunWebPro\Foundation\MailCompose\{DraftAttachment,DraftContent};
use CajunWebPro\Foundation\MailSubmission\{PreparedMime,SubmissionSnapshot,TransportOutcome};
$private=__DIR__.'/../test-runtime/submission-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-submission-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'suci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic submission owner passphrase';
function su_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function su_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;su_ok($name,true);return;}su_ok($name,false);}
function su_actor(int $id=2):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function su_content(string $subject='SYNTHETIC_SUBMISSION_SUBJECT'):DraftContent {return new DraftContent(['client@example.invalid'],['copy@example.invalid'],['private@example.invalid'],$subject,"SYNTHETIC_SUBMISSION_BODY\n</textarea><script>literal</script>");}
function su_account(string $label):string {
    global $password;return mail_accounts_save('',0,['label'=>$label,'address'=>'Sender@example.NET','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic','smtp_host'=>'smtp.example.net','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic'],true,['imap_password'=>'Synthetic incoming','smtp_password'=>'Synthetic outgoing'],$password);
}
function su_grant(string $account,int $user):void {global $password;$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=?',[$account,$user]);mail_accounts_grant($account,$user,(int)($g['revision']??0),true,true,true,0,$password);}
function su_draft(string $account):string {$id=bin2hex(random_bytes(16));mail_drafts_save($id,0,$account,1,su_content());return $id;}
function su_prepare(string $account):array {$draft=su_draft($account);$id=mail_submissions_prepare(bin2hex(random_bytes(16)),$draft,1,1);return [$id,$draft];}
function su_snapshot():string {return json_encode([all('SELECT * FROM '.table('mail_submissions').' ORDER BY id'),all('SELECT * FROM '.table('mail_submission_dispatches').' ORDER BY id'),all('SELECT * FROM '.table('mail_submission_results').' ORDER BY id'),all('SELECT * FROM '.table('audit').' ORDER BY id')],JSON_THROW_ON_ERROR);}
function su_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER suci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic submission audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER suci_fail');}return $failed;
}
function su_outcome(array $claim,string $status='accepted',string $phase='confirmation',string $reason='accepted',?int $code=250,?string $attempt=null,?PreparedMime $message=null):TransportOutcome {
    return new TransportOutcome($message??$claim['message'],$attempt??$claim['row']['attempt_id'],$status,$phase,$reason,$code);
}
/** Synthetic clock-history setup only, not an application mutation or retry API. */
function su_age_dispatches():void {query('UPDATE '.table('mail_submission_dispatches').' SET started_epoch=0');}
function su_worker(array $args):array {
    $p=proc_open([PHP_BINARY,__FILE__,...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start submission worker');foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);return [$p,$pipes];
}
function su_finish(array $worker):string {
    [$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0 || $error!=='')throw new RuntimeException('Submission fixture worker failed: '.$error);return $out;
}
if(str_starts_with($argv[1]??'','worker-')) {
    su_actor();echo "ready\n";fflush(STDOUT);
    try {echo $argv[1]==='worker-prepare'?mail_submissions_prepare($argv[2],$argv[3],1,1):(mail_submissions_claim($argv[2])?'claimed':'replay');}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo 'denied';}exit;
}
try {
    // Begin before submission setup so additive schema refusal remains exercised.
    foreach(schema_statements('suci_') as $name=>$sql)if(!str_starts_with($name,'mail_submission'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=5;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Submission staff '.$i,'submission'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>['administrator',['mail.send']],3=>['administrator',['mail.read']],4=>['owner',[]],5=>['administrator',array_keys(staff_permissions())]] as $id=>[$role,$permissions])query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode($permissions)]);
    su_actor(1);mail_accounts_migrate($password);mail_drafts_migrate($password);$a=su_account('Submission A');$b=su_account('Submission B');foreach([1,2,3,4,5] as $actor){su_grant($a,$actor);su_grant($b,$actor);}
    $mailKey=file_get_contents($private.'/mail-master.key');file_put_contents($private.'/provider-master.key','SYNTHETIC_EXISTING_PROVIDER_KEY');$providerKey=file_get_contents($private.'/provider-master.key');
    $settings=all('SELECT * FROM '.table('settings').' ORDER BY name');
    su_deny('Submission setup requires correct current owner password',fn()=>mail_submissions_migrate('wrong'));
    su_actor(5);su_deny('Administrator permissions do not confer owner setup',fn()=>mail_submissions_migrate($password));su_actor(1);
    su_deny('Unprepared storage cannot be used',fn()=>mail_submissions_actor());
    save_setting('mail_submissions_schema_version','future');su_deny('Future submission storage version cannot be downgraded',fn()=>mail_submissions_migrate($password));query('DELETE FROM '.table('settings').' WHERE name=?',['mail_submissions_schema_version']);
    foreach(mail_submissions_schema('suci_') as $sql)db()->exec($sql);
    db()->exec('ALTER TABLE '.table('mail_submissions').' ADD INDEX unexpected(created_at)');su_deny('Unexpected index refused and preserved',fn()=>mail_submissions_migrate($password));db()->exec('ALTER TABLE '.table('mail_submissions').' DROP INDEX unexpected');
    db()->exec('ALTER TABLE '.table('mail_submissions').' MODIFY snapshot_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');su_deny('Case-insensitive fingerprint column refused',fn()=>mail_submissions_migrate($password));db()->exec('ALTER TABLE '.table('mail_submissions').' MODIFY snapshot_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    db()->exec('ALTER TABLE '.table('mail_submission_results').' MODIFY smtp_code INT UNSIGNED NOT NULL');su_deny('Unexpected result nullability refused',fn()=>mail_submissions_migrate($password));db()->exec('ALTER TABLE '.table('mail_submission_results').' MODIFY smtp_code INT UNSIGNED NULL');
    $fk=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['suci_mail_submissions','draft_id'])['CONSTRAINT_NAME'];
    db()->exec('ALTER TABLE '.table('mail_submissions').' DROP FOREIGN KEY `'.$fk.'`');
    db()->exec('ALTER TABLE '.table('mail_submissions').' ADD CONSTRAINT `'.$fk.'` FOREIGN KEY (draft_id) REFERENCES '.table('mail_drafts').' (id) ON DELETE CASCADE ON UPDATE RESTRICT');
    su_deny('Cascading draft deletion cannot be adopted',fn()=>mail_submissions_migrate($password));
    db()->exec('ALTER TABLE '.table('mail_submissions').' DROP FOREIGN KEY `'.$fk.'`');db()->exec('ALTER TABLE '.table('mail_submissions').' ADD CONSTRAINT `'.$fk.'` FOREIGN KEY (draft_id) REFERENCES '.table('mail_drafts').' (id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    su_ok('Failed setup audit leaves readiness unset',su_audit_failure(fn()=>mail_submissions_migrate($password))&&!mail_submissions_ready());
    mail_submissions_migrate($password);mail_submissions_migrate($password);
    su_ok('Repeated additive setup preserves prior settings',mail_submissions_ready()&&all('SELECT * FROM '.table('settings')." WHERE name<>'mail_submissions_schema_version' ORDER BY name")===$settings);
    su_actor();$draft=su_draft($a);$id=bin2hex(random_bytes(16));$before=su_snapshot();
    su_ok('Preparation audit failure atomically rolls back intent',su_audit_failure(fn()=>mail_submissions_prepare($id,$draft,1,1))&&su_snapshot()===$before);
    su_deny('Wrong draft revision cannot prepare',fn()=>mail_submissions_prepare($id,$draft,2,1));su_deny('Wrong account revision cannot prepare',fn()=>mail_submissions_prepare($id,$draft,1,2));
    su_ok('Current reviewed draft prepares once',mail_submissions_prepare($id,$draft,1,1)===$id);
    $row=mail_submissions_row($id);$message=mail_submissions_message($row);$before=su_snapshot();
    su_ok('Exact initial intent replay performs no writes',mail_submissions_prepare($id,$draft,1,1)===$id&&su_snapshot()===$before);
    su_ok('Different form id for same saved revision returns existing intent',mail_submissions_prepare(bin2hex(random_bytes(16)),$draft,1,1)===$id&&su_snapshot()===$before);
    su_deny('Conflicting intent identity cannot change revision',fn()=>mail_submissions_prepare($id,$draft,2,1));
    su_ok('Saved private snapshot and MIME retain Bcc only in private envelope',str_contains($row['payload_json'],'private@example.invalid')&&!str_contains($row['mime_bytes'],'private@example.invalid')&&$message->sha256===$row['mime_sha256']);
    su_actor(4);su_deny('Owner cannot read another authors submission',fn()=>mail_submissions_get($id));su_deny('Owner cannot claim another authors submission',fn()=>mail_submissions_claim($id));su_actor(3);su_deny('Read-only staff cannot use Send storage',fn()=>mail_submissions_get($id));su_actor();
    foreach(['global','grant','account','session','support','anonymous'] as $mode) {
        if($mode==='global')query('UPDATE '.table('staff_profiles')." SET permissions='[]' WHERE user_id=2");
        elseif($mode==='grant')query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$a]);
        elseif($mode==='account')query('UPDATE '.table('mail_accounts').' SET enabled=0 WHERE id=?',[$a]);
        elseif($mode==='session')$_SESSION['auth_version']='revoked';
        elseif($mode==='support')$_GET['support']='fixture';
        else $_SESSION=[];
        su_deny('Current '.$mode.' boundary denies private submission read',fn()=>mail_submissions_get($id));
        su_deny('Current '.$mode.' boundary denies dispatch claim',fn()=>mail_submissions_claim($id));
        query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['mail.send'])]);query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);query('UPDATE '.table('mail_accounts').' SET enabled=1 WHERE id=?',[$a]);unset($_GET['support']);su_actor();
    }
    foreach(['payload_json'=>'{}','snapshot_fingerprint'=>str_repeat('0',64),'mime_bytes'=>'changed','mime_sha256'=>str_repeat('0',64)] as $field=>$bad) {
        query('UPDATE '.table('mail_submissions').' SET '.$field.'=? WHERE id=?',[$bad,$id]);$broken=su_snapshot();
        su_deny('Damaged '.$field.' cannot dispatch',fn()=>mail_submissions_claim($id));su_ok('Damaged '.$field.' is preserved without outcome',su_snapshot()===$broken);
        query('UPDATE '.table('mail_submissions').' SET '.$field.'=? WHERE id=?',[$row[$field],$id]);
    }
    $fake=['row'=>$row,'message'=>$message];su_deny('A terminal observation requires a committed dispatch fence',fn()=>mail_submissions_record(su_outcome($fake)));
    $before=su_snapshot();su_ok('Dispatch audit failure leaves no fence',su_audit_failure(fn()=>mail_submissions_claim($id))&&su_snapshot()===$before);
    $claim=mail_submissions_claim($id);su_ok('Claim commits exactly one attempt before returning bytes',$claim!==null&&one('SELECT id FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$id])['id']===$row['attempt_id']&&!db()->inTransaction());
    $before=su_snapshot();su_ok('Repeated dispatch claim cannot grant another attempt',mail_submissions_claim($id)===null&&su_snapshot()===$before);
    su_deny('Preparation refusal cannot clear a dispatched fence',fn()=>mail_submissions_refuse($id,$row['attempt_id'],$row['snapshot_fingerprint'],$row['mime_sha256'],'destination'));
    su_deny('Wrong attempt cannot record a result',fn()=>mail_submissions_record(su_outcome($claim,attempt:str_repeat('f',32))));
    $p=$message->snapshot->privatePayload();$other=PreparedMime::fromSnapshot(new SubmissionSnapshot($p['id'],$p['author_id'],$p['draft_id'],$p['draft_revision'],$p['account_id'],$p['account_revision'],$p['from'],new DraftContent($p['to'],$p['cc'],['other-private@example.invalid'],$p['subject'],$p['body']),$p['prepared_epoch']));
    su_ok('Different Bcc can have identical wire checksum',$other->sha256===$message->sha256&&$other->snapshot->fingerprint!==$message->snapshot->fingerprint);
    su_deny('Different private Bcc fingerprint cannot resolve same attempt',fn()=>mail_submissions_record(su_outcome($claim,message:$other)));
    foreach([['accepted','confirmation','accepted',550],['rejected','confirmation','server_rejected',250],['unknown','confirmation','protocol',250],['not_submitted','confirmation','connection',null]] as [$s,$p,$r,$code])su_deny('Contradictory result refused '.$s,fn()=>mail_submissions_record(su_outcome($claim,$s,$p,$r,$code)));
    $before=su_snapshot();su_ok('Failed result audit preserves unresolved dispatch fence',su_audit_failure(fn()=>mail_submissions_record(su_outcome($claim)))&&su_snapshot()===$before);
    mail_drafts_save($draft,1,$a,1,su_content('Edited after uncertain dispatch'));
    su_deny('Unresolved prior dispatch blocks a new draft revision',fn()=>mail_submissions_prepare(bin2hex(random_bytes(16)),$draft,2,1));
    query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$a]);$_SESSION=[];
    mail_submissions_record(su_outcome($claim));
    su_ok('Actual acceptance recorded despite later authority/session loss',one('SELECT status FROM '.table('mail_submission_results').' WHERE submission_id=?',[$id])['status']==='accepted');
    su_deny('Lost authority still cannot disclose accepted history',fn()=>mail_submissions_get($id));query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);su_actor();
    $before=su_snapshot();mail_submissions_record(su_outcome($claim));su_ok('Exact terminal replay adds no audit or mutation',su_snapshot()===$before);
    su_deny('Conflicting terminal receipt cannot rewrite acceptance',fn()=>mail_submissions_record(su_outcome($claim,'rejected','confirmation','server_rejected',550)));
    su_ok('Accepted history preserves old reviewed revision after later edit',mail_submissions_get($id)['draft_revision']===1&&mail_submissions_get($id)['content']['subject']==='SYNTHETIC_SUBMISSION_SUBJECT');
    su_deny('Accepted draft cannot be resent merely by editing',fn()=>mail_submissions_prepare(bin2hex(random_bytes(16)),$draft,2,1));
    su_age_dispatches();[$refusal,$refusalDraft]=su_prepare($a);$refusalRow=mail_submissions_row($refusal);
    su_deny('Predispatch refusal requires its exact reserved attempt',fn()=>mail_submissions_refuse($refusal,str_repeat('0',32),$refusalRow['snapshot_fingerprint'],$refusalRow['mime_sha256'],'destination'));
    mail_submissions_refuse($refusal,$refusalRow['attempt_id'],$refusalRow['snapshot_fingerprint'],$refusalRow['mime_sha256'],'destination');$before=su_snapshot();mail_submissions_refuse($refusal,$refusalRow['attempt_id'],$refusalRow['snapshot_fingerprint'],$refusalRow['mime_sha256'],'destination');
    su_ok('Exact DNS refusal replay is immutable without dispatch',su_snapshot()===$before&&mail_submissions_claim($refusal)===null);
    mail_drafts_save($refusalDraft,1,$a,1,su_content('Corrected review'));$new=mail_submissions_prepare(bin2hex(random_bytes(16)),$refusalDraft,2,1);su_ok('Known unused preparation permits a newly reviewed revision',$new!==$refusal);
    $newClaim=mail_submissions_claim($new);mail_submissions_record(su_outcome($newClaim,'rejected','confirmation','server_rejected',550));
    su_ok('Final rejection retained separately from older preparation refusal',mail_submissions_get($new)['result']['status']==='rejected'&&mail_submissions_get($refusal)['result']['scope']==='preparation');
    mail_drafts_save($refusalDraft,2,$a,1,su_content('Reviewed after rejection'));$afterRejected=mail_submissions_prepare(bin2hex(random_bytes(16)),$refusalDraft,3,1);
    su_ok('Known server rejection permits a later explicitly reviewed revision',$afterRejected!==$new&&mail_submissions_get($afterRejected)['result']===null);
    [$cooldown]=su_prepare($a);su_deny('Account cooldown refuses a second immediate dispatch',fn()=>mail_submissions_claim($cooldown));su_age_dispatches();
    [$unknown,$unknownDraft]=su_prepare($a);$unknownClaim=mail_submissions_claim($unknown);mail_submissions_record(su_outcome($unknownClaim,'unknown','confirmation','connection',null));mail_drafts_save($unknownDraft,1,$a,1,su_content('Later edit'));
    su_deny('Recorded uncertain outcome blocks later draft revision',fn()=>mail_submissions_prepare(bin2hex(random_bytes(16)),$unknownDraft,2,1));su_age_dispatches();
    [$stale,$staleDraft]=su_prepare($a);mail_drafts_save($staleDraft,1,$a,1,su_content('Changed'));
    su_deny('Saved draft edit after preparation prevents dispatch',fn()=>mail_submissions_claim($stale));
    [$changed]=su_prepare($a);query('UPDATE '.table('mail_accounts').' SET revision=2 WHERE id=?',[$a]);su_deny('Account revision change after preparation prevents dispatch',fn()=>mail_submissions_claim($changed));query('UPDATE '.table('mail_accounts').' SET revision=1 WHERE id=?',[$a]);
    [$discarded,$discardedDraft]=su_prepare($a);mail_drafts_discard($discardedDraft,1);su_deny('Discarded draft cannot dispatch its old preparation',fn()=>mail_submissions_claim($discarded));
    $raceDraft=su_draft($a);$r1=su_worker(['worker-prepare',bin2hex(random_bytes(16)),$raceDraft]);$r2=su_worker(['worker-prepare',bin2hex(random_bytes(16)),$raceDraft]);$o1=su_finish($r1);$o2=su_finish($r2);$raceId=trim(substr($o1,6));
    su_ok('Concurrent different form ids yield one saved-revision intent',$o1===$o2&&preg_match('/\A[a-f0-9]{32}\z/',$raceId)===1&&(int)query('SELECT COUNT(*) FROM '.table('mail_submissions').' WHERE draft_id=?',[$raceDraft])->fetchColumn()===1);
    $r1=su_worker(['worker-claim',$raceId]);$r2=su_worker(['worker-claim',$raceId]);$outputs=[su_finish($r1),su_finish($r2)];sort($outputs);
    su_ok('Concurrent dispatch claims yield one durable fence and one replay',$outputs===["ready\nclaimed","ready\nreplay"]&&(int)query('SELECT COUNT(*) FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$raceId])->fetchColumn()===1);su_age_dispatches();
    [$waiting]=su_prepare($a);$worker=staff_locked(function()use($waiting,$a):array {$w=su_worker(['worker-claim',$waiting]);su_ok('Waiting dispatch worker reaches authority boundary',fgets($w[1][1])==="ready\n");query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$a]);return $w;});
    su_ok('Revocation while waiting prevents a dispatch fence',su_finish($worker)==='denied'&&!one('SELECT id FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$waiting]));query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);
    su_actor(1);query('DELETE FROM '.table('settings').' WHERE name=?',['mail_submissions_schema_version']);$before=su_snapshot();su_deny('Populated unrecognized storage is not adopted',fn()=>mail_submissions_migrate($password));su_ok('Failed adoption preserves every submission/dispatch/result/audit',su_snapshot()===$before&&!mail_submissions_ready());save_setting('mail_submissions_schema_version','1');su_actor();
    su_actor(1);su_deny('Sent-copy storage requires the correct owner password',fn()=>mail_copies_migrate('wrong'));
    mail_copies_migrate($password);su_ok('Sent-copy storage is additive and ready',mail_copies_ready());
    su_deny('Wire-unsafe folder names refuse before any request',fn()=>mail_sent_folder_save($a,'Sent"Items',$password));su_deny('Folder wildcard refused',fn()=>mail_sent_folder_save($a,'Sent*',$password));
    su_deny('Folder setting requires the owner password',fn()=>mail_sent_folder_save($a,'Sent','wrong'));
    su_actor(2);
    su_deny('A copy cannot run without a configured server Sent folder',fn()=>mail_submission_copy($id));
    su_actor(1);mail_sent_folder_save($a,'Sent',$password);su_actor(2);su_ok('Sent folder saved for the account',mail_sent_folder($a)==='Sent');
    $copiedBytes=null;$resolver=fn($h)=>['8.8.8.8'];$copied=mail_submission_copy($id,$resolver,function($row,$secrets,$address,$folder,$mime,$authorize)use(&$copiedBytes){$authorize();$copiedBytes=$mime;});
    su_ok('Copy appends the exact accepted MIME bytes to the configured folder',$copied['code']==='appended'&&$copiedBytes===$claim['message']->bytes&&$copied['folder']==='Sent');
    $attemptCount=(int)query('SELECT COUNT(*) FROM '.table('mail_copy_attempts').' WHERE submission_id=?',[$id])->fetchColumn();
    su_deny('A recorded Sent copy cannot be appended again',fn()=>mail_submission_copy($id,$resolver,fn()=>null));
    su_ok('Refused recopy adds no attempt row',(int)query('SELECT COUNT(*) FROM '.table('mail_copy_attempts').' WHERE submission_id=?',[$id])->fetchColumn()===$attemptCount);
    [$uncopied]=su_prepare($a);$claim2=mail_submissions_claim($uncopied);mail_submissions_record(su_outcome($claim2));
    su_deny('Account copy cooldown bounds repeat requests',fn()=>mail_submission_copy($uncopied,$resolver,fn()=>null));
    query('UPDATE '.table('mail_copy_attempts').' SET started_epoch=0');
    $mid=mail_submission_copy($uncopied,$resolver,function($row,$secrets,$address,$folder,$mime,$authorize)use($a){$authorize();query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$a]);$authorize();});
    su_ok('Mid-copy grant loss records a bounded failure, not a copy',$mid['code']==='access_changed'&&mail_copy_status($uncopied)['result_code']==='access_changed');
    query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);query('UPDATE '.table('mail_copy_attempts').' SET started_epoch=0');
    $failed=mail_submission_copy($uncopied,$resolver,function(){throw new CajunWebPro\Foundation\MailReceiving\ReceivingFailure('folder');});
    su_ok('Server folder refusal records a bounded result and stays retryable',$failed['code']==='folder'&&mail_copy_status($uncopied)['result_code']==='folder');
    query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);query('UPDATE '.table('mail_copy_attempts').' SET started_epoch=0');
    $copied2=mail_submission_copy($uncopied,$resolver,function($row,$secrets,$address,$folder,$mime,$authorize){$authorize();});
    su_ok('Failed copy retries cleanly under restored authority',$copied2['code']==='appended'&&mail_copy_status($uncopied)['result_code']==='appended');
    su_deny('Copy requires the author\'s own submission history',function()use($id,$resolver){su_actor(5);try{mail_submission_copy($id,$resolver,fn()=>null);}finally{su_actor(2);}});
    su_deny('Rejected submissions cannot be copied',fn()=>mail_submission_copy($new,$resolver,fn()=>null));
    su_ok('Copy history is metadata only',!str_contains(json_encode(all('SELECT * FROM '.table('mail_copy_attempts').' a LEFT JOIN '.table('mail_copy_results').' r ON r.id=a.id')),'SYNTHETIC_SUBMISSION'));
    su_actor(1);su_deny('Attachment storage requires the correct owner password',fn()=>mail_files_migrate('wrong'));
    mail_files_migrate($password);su_ok('Attachment storage is additive and ready',mail_files_ready());
    mail_files_migrate($password);su_ok('Repeated attachment setup stays ready without changes',mail_files_ready());
    su_actor(2);$attachDraft=su_draft($a);
    su_deny('Attachment requires an exact saved revision',fn()=>mail_drafts_attach($attachDraft,2,new DraftAttachment('a.txt','text/plain','bytes one')));
    mail_drafts_attach($attachDraft,1,new DraftAttachment('report.pdf','application/pdf','%PDF '.str_repeat("\x00\xff",50)));
    su_ok('Attaching bumps the saved draft revision',(int)mail_drafts_get($attachDraft)['revision']===2);
    mail_drafts_attach($attachDraft,2,new DraftAttachment('notes.txt','text/plain','second file'));
    $files=mail_drafts_files($attachDraft);
    su_ok('Draft lists bounded attachment metadata without bytes',count($files)===2&&$files[0]['name']==='report.pdf'&&!array_key_exists('data',$files[0]));
    $download=mail_drafts_file($attachDraft,$files[1]['id']);
    su_ok('Author downloads exact stored attachment bytes',hash_equals($download['sha256'],hash('sha256',$download['data']))&&$download['data']==='second file');
    su_actor(4);
    su_deny('Another author cannot list draft attachments',fn()=>mail_drafts_files($attachDraft));
    su_deny('Another author cannot download draft attachments',fn()=>mail_drafts_file($attachDraft,$files[0]['id']));
    su_deny('Another author cannot detach draft attachments',fn()=>mail_drafts_detach($attachDraft,3,$files[0]['id']));
    su_actor(2);
    su_deny('Stale revision cannot attach',fn()=>mail_drafts_attach($attachDraft,1,new DraftAttachment('x.txt','text/plain','x')));
    su_deny('Stale revision cannot detach',fn()=>mail_drafts_detach($attachDraft,1,$files[0]['id']));
    // Two filler files reach the four-file bound; the fifth is refused without mutation.
    mail_drafts_attach($attachDraft,3,new DraftAttachment('x.txt','text/plain','x'));
    mail_drafts_attach($attachDraft,4,new DraftAttachment('y.txt','text/plain','y'));
    su_deny('A fifth attachment exceeds the per-draft bound',fn()=>mail_drafts_attach($attachDraft,5,new DraftAttachment('z.txt','text/plain','z')));
    $extra=all('SELECT id FROM '.table('mail_draft_files').' WHERE draft_id=? AND name IN (?,?) ORDER BY name',[$attachDraft,'x.txt','y.txt']);
    mail_drafts_detach($attachDraft,5,$extra[0]['id']);mail_drafts_detach($attachDraft,6,$extra[1]['id']);
    su_ok('Bound refusal and cleanup restore the two saved attachments',(int)mail_drafts_get($attachDraft)['revision']===7&&count(mail_drafts_files($attachDraft))===2);
    su_age_dispatches();
    $attachId=mail_submissions_prepare(bin2hex(random_bytes(16)),$attachDraft,7,1);
    $attachMessage=mail_submissions_message(mail_submissions_row($attachId));
    su_ok('Attachment-bearing submission uses payload format 3',$attachMessage->snapshot->privatePayload()['format']===3);
    su_ok('Preparation copies attachments into immutable submission storage',(int)query('SELECT COUNT(*) FROM '.table('mail_submission_files').' WHERE submission_id=?',[$attachId])->fetchColumn()===2);
    su_ok('Prepared MIME carries the encoded attachment parts',str_contains($attachMessage->bytes,'multipart/mixed')&&str_contains($attachMessage->bytes,'report.pdf'));
    mail_drafts_detach($attachDraft,7,$files[0]['id']);
    su_ok('Later draft file changes cannot rewrite the saved submission',mail_submissions_message(mail_submissions_row($attachId))->bytes===$attachMessage->bytes);
    su_deny('A changed attachment set prevents dispatch of its reviewed submission',fn()=>mail_submissions_claim($attachId));
    $attachId2=mail_submissions_prepare(bin2hex(random_bytes(16)),$attachDraft,8,1);$attachClaim=mail_submissions_claim($attachId2);
    su_ok('A re-reviewed attachment set dispatches only the current files',$attachClaim!==null&&str_contains($attachClaim['message']->bytes,'notes.txt')&&!str_contains($attachClaim['message']->bytes,'report.pdf'));
    mail_submissions_record(su_outcome($attachClaim));
    su_ok('Accepted attachment submission reconstructs byte-exactly',mail_submissions_message(mail_submissions_row($attachId2))->bytes===$attachClaim['message']->bytes);
    $kept=one('SELECT data FROM '.table('mail_submission_files').' WHERE submission_id=? LIMIT 1',[$attachId2]);
    query('UPDATE '.table('mail_submission_files').' SET data=? WHERE submission_id=? LIMIT 1',['tampered',$attachId2]);
    su_deny('Damaged submission attachment refuses disclosure',fn()=>mail_submissions_get($attachId2));
    query('UPDATE '.table('mail_submission_files').' SET data=? WHERE submission_id=?',[$kept['data'],$attachId2]);
    // Formatted alternative: prepare/snapshot/persisted-MIME round-trip and damage fence.
    $richDraft=su_draft($a);mail_drafts_save($richDraft,1,$a,1,new DraftContent(['client@example.invalid'],['copy@example.invalid'],['private@example.invalid'],'Rich draft','Plain body','<p>Formatted <b>part</b></p>'));
    $richId=mail_submissions_prepare(bin2hex(random_bytes(16)),$richDraft,2,1);
    $richMessage=mail_submissions_message(mail_submissions_row($richId));
    su_ok('Formatted alternative uses payload format 5 with exact alternative bytes',$richMessage->snapshot->privatePayload()['format']===5&&$richMessage->snapshot->privatePayload()['body_html']==='<p>Formatted <b>part</b></p>'&&str_contains($richMessage->bytes,'multipart/alternative'));
    su_ok('Formatted submission reconstructs byte-exactly from private storage',mail_submissions_message(mail_submissions_row($richId))->bytes===$richMessage->bytes&&str_contains($richMessage->bytes,'Content-Type: text/html'));
    query('UPDATE '.table('mail_submissions').' SET payload_json=? WHERE id=?',[json_encode($richMessage->snapshot->privatePayload()+['body_html'=>'<p>tampered</p>']),$richId]);
    su_deny('Damaged formatted alternative refuses disclosure',fn()=>mail_submissions_get($richId));
    query('UPDATE '.table('mail_submissions').' SET payload_json=? WHERE id=?',[json_encode($richMessage->snapshot->privatePayload()),$richId]);
    su_actor(2);
    $metadata=json_encode(all('SELECT detail FROM '.table('audit')." WHERE action LIKE 'mail_submissions.%'"));su_ok('Audit omits subject/body and recipient identities',!str_contains($metadata,'SYNTHETIC_SUBMISSION')&&!str_contains($metadata,'private@example.invalid')&&!str_contains($metadata,'client@example.invalid'));
    su_ok('Private original mailbox and provider keys stay exact',file_get_contents($private.'/mail-master.key')===$mailKey&&file_get_contents($private.'/provider-master.key')===$providerKey);
    su_ok('No system-mail configuration enabled by storage',setting('email_mode')==='disabled'&&setting('smtp_host')===''&&setting('smtp_password')==='');
    su_ok('No transaction leaked after storage operations',!db()->inTransaction());
    // Seed honest completed attempts, then set their synthetic clock metadata to
    // the same current hour. This exercises the actor limit on another mailbox
    // without sleeping through twenty independent account cooldowns.
    $count=(int)query('SELECT COUNT(*) FROM '.table('mail_submission_dispatches').' d JOIN '.table('mail_submissions').' s ON s.id=d.submission_id WHERE s.owner_id=2')->fetchColumn();
    for($i=$count;$i<20;$i++) {su_age_dispatches();[$rateId]=su_prepare($a);$rateClaim=mail_submissions_claim($rateId);mail_submissions_record(su_outcome($rateClaim,'rejected','confirmation','server_rejected',550));}
    query('UPDATE '.table('mail_submission_dispatches').' SET started_epoch=?',[time()]);[$hourly]=su_prepare($b);$before=su_snapshot();
    su_deny('Actor hourly limit spans different sending mailboxes',fn()=>mail_submissions_claim($hourly));su_ok('Hourly refusal leaves no extra dispatch or audit',su_snapshot()===$before);
    $count=(int)query('SELECT COUNT(*) FROM '.table('mail_submissions').' WHERE owner_id=2')->fetchColumn();for($i=$count;$i<100;$i++)su_prepare($b);
    $limitDraft=su_draft($b);$before=su_snapshot();su_deny('Preparation limit bounds abandoned intents per actor',fn()=>mail_submissions_prepare(bin2hex(random_bytes(16)),$limitDraft,1,1));su_ok('Preparation limit leaves no partial intent or audit',su_snapshot()===$before);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/mail-submissions-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB current-authorized preparation/fences/receipts/audit/process races; no network, credential decryption, application routes or Send UI','checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo ($ok?'PASS':'FAIL').': '.count($checks)." native submission storage checks\n";exit($ok?0:1);
