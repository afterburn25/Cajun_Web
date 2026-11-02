<?php
/** Isolated MariaDB tests and synthetic HMAC/reporting fixtures, not provider acceptance. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='notifyci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$password='Synthetic notification owner passphrase';$checks=[];$key=str_repeat('ab',64);$webhook='01234567-89ab-cdef-0123-456789abcdef';
function n_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function n_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;n_ok($name,true);return;}n_ok($name,false);}
function n_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function n_signature(string $body,?string $secret=null): string {global $key;return 'sha512='.strtoupper(hash_hmac('sha512',$body,hex2bin($secret??$key)));}
function n_payload(array $extra=[],array $payload=[]): string {global $webhook;return json_encode(array_replace(['notificationId'=>sprintf('10000000-0000-0000-0000-%012d',random_int(1,999999)),'webhookId'=>$webhook,'eventType'=>'net.authorize.payment.authcapture.created','eventDate'=>'2026-10-13T01:02:03.1234567Z','payload'=>array_replace(['entityName'=>'transaction','id'=>'910001','merchantReferenceId'=>'CW'.str_repeat('a',18),'authAmount'=>999.99,'customer'=>['email'=>'NeverPersistThis@example.invalid']],$payload)],$extra),JSON_THROW_ON_ERROR);}
function n_receive(string $body): int {return notifications_receive($body,n_signature($body));}
function n_seed(int $id,string $reference,?string $transaction=null,string $state='unknown'): array {
    global $credentials;$now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('payment_attempts').' (id,request_id,invoice_id,actor_id,attempt_key,reference,environment,account_hash,gateway_revision,amount_cents,currency,state,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$id,1,1,hash('sha256','notification-attempt-'.$id),$reference,'sandbox',attempts_account($credentials),1,12510,'USD',$state,$transaction,$now,$now]);
    attempts_event($id,1,'submitted');return one('SELECT * FROM '.table('payment_attempts').' WHERE id=?',[$id]);
}
function n_transport(string $body): array {
    global $calls,$overrides,$during;$v=json_decode($body,true,32,JSON_THROW_ON_ERROR);$method=array_key_first($v);$calls[]=$method;
    $ok=['messages'=>['resultCode'=>'Ok','message'=>[['code'=>'I00001','text'=>'Synthetic']]]];
    if($method==='getMerchantDetailsRequest')return [200,json_encode($ok+['isTestMode'=>false,'currencies'=>['USD']])];
    if($method==='getTransactionDetailsRequest'){
        if($during)$during();
        return [200,json_encode($ok+['transaction'=>array_replace_recursive(['transId'=>$v[$method]['transId'],'transactionType'=>'authCaptureTransaction','transactionStatus'=>'capturedPendingSettlement','responseCode'=>1,'order'=>['invoiceNumber'=>'CW'.str_repeat('a',18)],'authAmount'=>125.10,'settleAmount'=>125.10],$overrides)])];
    }
    throw new LogicException('Unexpected provider mutation');
}
if(($argv[1]??'')==='receipt-worker'){$body=stream_get_contents(STDIN);echo n_receive($body);exit;}
try{
    foreach(schema_statements('notifyci_') as $name=>$sql)if($name!=='payment_notifications')db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    foreach(['staff','support','catalog','payment_attempts'] as $module)save_setting($module.'_schema_version','1');save_setting('payments_schema_version','2');
    $now=gmdate('Y-m-d H:i:s');
    foreach([['Owner','owner-notify@example.invalid'],['Staff','staff-notify@example.invalid']] as [$name,$email])query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,?)",[json_encode(['payments.manage','clients.manage'])]);n_actor();
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Notification client','notification-client@example.invalid',str_repeat('c',32),$now]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?)',['Notification project','Retain summary','Retain milestones',$now,$now]);
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (1,?,?,?,?,?)',['NOTIFICATION-INVOICE','Synthetic invoice',12510,'Retain instructions',$now]);$invoice=one('SELECT * FROM '.table('studio_invoices'));
    n_deny('Revoked owner refuses: Migration requires owner reauthentication',fn()=>cwp_test_owner_revoked(fn()=>notifications_migrate('wrong')));
    db()->exec(notifications_schema('notifyci_')['payment_notifications']);db()->exec('ALTER TABLE '.table('payment_notifications').' DROP INDEX notification_id');
    n_deny('Migration refuses missing notification uniqueness',fn()=>notifications_migrate($password));db()->exec('ALTER TABLE '.table('payment_notifications').' ADD UNIQUE notification_id(notification_id)');
    db()->exec('ALTER TABLE '.table('payment_notifications').' MODIFY body_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');n_deny('Migration refuses case-folded body digests',fn()=>notifications_migrate($password));db()->exec('ALTER TABLE '.table('payment_notifications').' MODIFY body_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    notifications_migrate($password);notifications_migrate($password);n_ok('Additive repeat migration preserves invoices and starts disabled',notifications_ready()&&!notifications_config()['enabled']&&one('SELECT * FROM '.table('studio_invoices'))===$invoice);
    $credentials=['api_login_id'=>'SandboxNotifyFixture','transaction_key'=>'FixtureKey123456','signature_key'=>$key,'public_client_key'=>'SandboxPublicFixture'];
    payments_save('sandbox',0,$credentials,$password);query('UPDATE '.table('payment_gateways')." SET validation='authenticated' WHERE environment='sandbox'");
    $body=n_payload();n_ok('Unconfigured receiver stores nothing',n_receive($body)===503&&!all('SELECT * FROM '.table('payment_notifications')));
    n_deny('Revoked owner refuses: Configuration refuses wrong password',fn()=>cwp_test_owner_revoked(fn()=>notifications_configure(0,1,$webhook,true,'wrong')));
    n_deny('Configuration refuses malformed provider ID',fn()=>notifications_configure(0,1,'bad',true,$password));
    n_deny('Configuration refuses stale gateway revision',fn()=>notifications_configure(0,2,$webhook,true,$password));
    notifications_configure(0,1,$webhook,true,$password);n_deny('Configuration uses compare-and-swap revision',fn()=>notifications_configure(0,1,$webhook,false,$password));
    // RFC 4231 case 1 independently fixes algorithm/binary-key behavior (pad key to stored 64 bytes).
    $rfcKey=str_repeat('0b',20).str_repeat('00',44);$rfc='87aa7cdea5ef619d4ff0b4241a1d6cb02379f4e2ce4ec2787ad0b30545e17cde'.'daa833b7d6b8a702038b274eaea3f4e4be9d914eeb61f1702e696c203a126854';
    n_ok('Independent RFC4231 binary-key SHA512 vector',notifications_signature('Hi There','sha512='.$rfc,$rfcKey));
    n_ok('Signature authenticates original bytes only',notifications_signature($body,n_signature($body),$key)&&!notifications_signature($body.' ',n_signature($body),$key));
    foreach(['','sha256='.str_repeat('a',64),'sha512='.str_repeat('0',128),'sha512='.hash_hmac('sha512',$body,$key),n_signature($body).',duplicate'] as $sig)n_ok('Forged malformed or ASCII-key signature denied '.strlen($sig),notifications_receive($body,$sig)===401);
    n_ok('Signed malformed JSON rejected',n_receive('{')===400);
    n_ok('Signed wrong webhook rejected',n_receive(n_payload(['webhookId'=>'ffffffff-ffff-ffff-ffff-ffffffffffff']))===401);
    foreach([['notificationId'=>[]],['eventDate'=>'2026-02-30T00:00:00Z'],['eventType'=>'<script>'],['payload'=>['entityName'=>'customerProfile','id'=>'910001']],['payload'=>['entityName'=>'transaction','id'=>'0']],['payload'=>['entityName'=>'transaction','id'=>[]]]] as $bad)n_ok('Invalid signed shape rejected '.array_key_first($bad),n_receive(n_payload($bad))===400);
    n_ok('Oversized body rejected before persistence',notifications_receive(str_repeat('a',32769),'')===400);
    $a=n_seed(1,'CW'.str_repeat('a',18));$calls=[];$overrides=[];$during=null;
    n_ok('Authenticated receipt durable and no reporting calls',n_receive($body)===200&&!$calls&&one('SELECT state FROM '.table('payment_attempts').' WHERE id=1')['state']==='unknown');
    $n=one('SELECT * FROM '.table('payment_notifications'));$id=(int)$n['id'];$count=(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn();
    n_ok('Identical retry has no second record or audit',n_receive($body)===200&&(int)query('SELECT COUNT(*) FROM '.table('payment_notifications'))->fetchColumn()===1&&(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn()===$count);
    n_ok('Same notification ID with changed bytes conflicts',n_receive($body.' ')===409);
    n_ok('Receipt stores neither raw payload nor signature',!str_contains(json_encode($n),'NeverPersistThis')&&!str_contains(json_encode($n),'999.99')&&!str_contains(json_encode($n),$key));
    $parallel=n_payload();$proc=proc_open([PHP_BINARY,__FILE__,'receipt-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],$parallel);fclose($pipes[0]);$status=n_receive($parallel);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
    n_ok('Concurrent receiver processes retain exactly one delivery',$status===200&&trim($out)==='200'&&$err===''&&$exit===0&&(int)query('SELECT COUNT(*) FROM '.table('payment_notifications').' WHERE notification_id=?',[json_decode($parallel,true)['notificationId']])->fetchColumn()===1);
    db()->exec('CREATE TRIGGER notifyci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic strict audit failure'");
    $before=(int)query('SELECT COUNT(*) FROM '.table('payment_notifications'))->fetchColumn();try{n_receive(n_payload());$failed=false;}catch(PDOException){$failed=true;}n_ok('Receipt audit failure rolls back before acknowledgement',$failed&&(int)query('SELECT COUNT(*) FROM '.table('payment_notifications'))->fetchColumn()===$before);db()->exec('DROP TRIGGER notifyci_fail');
    n_deny('Revoked owner refuses: Verification requires owner password',fn()=>cwp_test_owner_revoked(fn()=>notifications_verify($id,1,'wrong','n_transport')));
    foreach([['authAmount'=>999.99],['order'=>['invoiceNumber'=>'Unrelated']],['transactionType'=>'refundTransaction'],['responseCode'=>2],['transactionStatus'=>'unrecognized']] as $bad){$overrides=$bad;n_deny('Reporting mismatch leaves queued '.array_key_first($bad),fn()=>notifications_verify($id,1,$password,'n_transport'));}
    n_ok('Notification amount never overwrites request or attempt',one('SELECT * FROM '.table('payment_attempts').' WHERE id=1')===$a&&one('SELECT status FROM '.table('payment_notifications').' WHERE id=?',[$id])['status']==='queued');$overrides=[];
    $during=function(): void {db()->exec('CREATE TRIGGER notifyci_verify_fail BEFORE UPDATE ON '.table('payment_notifications')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic processing failure'");};
    try{notifications_verify($id,1,$password,'n_transport');$failed=false;}catch(PDOException){$failed=true;}$during=null;db()->exec('DROP TRIGGER notifyci_verify_fail');
    n_ok('Processing failure rolls back attempt observation and events too',$failed&&one('SELECT * FROM '.table('payment_attempts').' WHERE id=1')===$a&&(int)query('SELECT COUNT(*) FROM '.table('payment_attempt_events').' WHERE attempt_id=1')->fetchColumn()===1);
    notifications_verify($id,1,$password,'n_transport');n_ok('Late notification recovers unknown attempt only through reporting',one('SELECT state,transaction_id FROM '.table('payment_attempts').' WHERE id=1')===['state'=>'captured','transaction_id'=>'910001']&&one('SELECT status,attempt_id FROM '.table('payment_notifications').' WHERE id=?',[$id])===['status'=>'verified','attempt_id'=>1]);
    $before=$calls;notifications_verify($id,1,$password,'n_transport');n_ok('Verified notification retry makes no network call',$calls===$before);
    $queued=one('SELECT * FROM '.table('payment_notifications')." WHERE status='queued' ORDER BY id LIMIT 1");$qid=(int)$queued['id'];
    $during=function() use($password): void {payments_save('sandbox',1,[],$password);query('UPDATE '.table('payment_gateways')." SET validation='authenticated'");};
    n_deny('Credential rotation during reporting cannot commit stale result',fn()=>notifications_verify($qid,1,$password,'n_transport'));$during=null;
    n_ok('Credential rotation pauses receipt automatically',n_receive(n_payload())===503);notifications_configure(1,2,$webhook,true,$password);
    $overrides=['transactionStatus'=>'settledSuccessfully'];notifications_verify($qid,2,$password,'n_transport');n_ok('Same merchant key revision can verify retained notifications',one('SELECT state FROM '.table('payment_attempts').' WHERE id=1')['state']==='settled');
    $late=n_payload(['eventType'=>'net.authorize.payment.fraud.declined']);n_receive($late);$lateid=(int)db()->lastInsertId();$lateid=(int)one('SELECT id FROM '.table('payment_notifications').' WHERE notification_id=?',[json_decode($late,true)['notificationId']])['id'];
    notifications_verify($lateid,2,$password,'n_transport');n_ok('Out-of-order declined event uses current settled reporting',one('SELECT state FROM '.table('payment_attempts').' WHERE id=1')['state']==='settled');
    $conflict=n_payload();n_receive($conflict);$conflictId=(int)one('SELECT id FROM '.table('payment_notifications').' WHERE notification_id=?',[json_decode($conflict,true)['notificationId']])['id'];$overrides=['transactionStatus'=>'declined','responseCode'=>2];n_deny('Conflicting terminal report does not downgrade settlement',fn()=>notifications_verify($conflictId,2,$password,'n_transport'));$overrides=[];
    $unmatched=n_payload([],['id'=>'910099','merchantReferenceId'=>'OtherMerchantOrder']);n_receive($unmatched);$unmatchedId=(int)one('SELECT id FROM '.table('payment_notifications').' WHERE notification_id=?',[json_decode($unmatched,true)['notificationId']])['id'];$before=$calls;n_deny('Unmatched transaction remains queued without arbitrary gateway lookup',fn()=>notifications_verify($unmatchedId,2,$password,'n_transport'));n_ok('Unmatched receipt performs no reporting call',$calls===$before);
    $ignored=n_payload(['eventType'=>'net.authorize.customer.created','payload'=>['id'=>'1','entityName'=>'customerProfile','email'=>'NeverPersistThis@example.invalid']]);n_ok('Authentic unsupported event acknowledged without payment processing',n_receive($ignored)===200&&one('SELECT status,transaction_id FROM '.table('payment_notifications').' WHERE notification_id=?',[json_decode($ignored,true)['notificationId']])===['status'=>'ignored','transaction_id'=>null]);
    $a2=n_seed(2,'CW'.str_repeat('b',18));$staleBody=n_payload([],['id'=>'910002','merchantReferenceId'=>$a2['reference']]);n_receive($staleBody);$sid=(int)one('SELECT id FROM '.table('payment_notifications').' WHERE notification_id=?',[json_decode($staleBody,true)['notificationId']])['id'];$overrides=['order'=>['invoiceNumber'=>$a2['reference']]];
    $during=function() use($a2): void {attempts_record($a2,['state'=>'held','transaction_id'=>'910002'],1,'verified');};n_deny('Concurrent newer observation leaves notification queued for fresh verification',fn()=>notifications_verify($sid,2,$password,'n_transport'));$during=null;
    n_ok('Newer observation retained after stale reporting',one('SELECT state FROM '.table('payment_attempts').' WHERE id=2')['state']==='held');notifications_verify($sid,2,$password,'n_transport');
    payments_save('sandbox',2,['api_login_id'=>'DifferentNotify'],$password);query('UPDATE '.table('payment_gateways')." SET validation='authenticated'");n_deny('Another merchant cannot process original queue',fn()=>notifications_verify($unmatchedId,3,$password,'n_transport'));
    n_actor(2);n_deny('Payment staff cannot configure receiver',fn()=>notifications_configure(2,3,$webhook,true,$password));n_deny('Payment staff cannot verify notifications',fn()=>notifications_verify($id,3,$password,'n_transport'));n_deny('Payment staff cannot migrate inbox',fn()=>notifications_migrate($password));n_actor();
    $GLOBALS['cwp_support']=['id'=>1];n_deny('Support session cannot configure receiver',fn()=>notifications_configure(2,3,$webhook,false,$password));n_deny('Support session cannot verify notifications',fn()=>notifications_verify($id,3,$password,'n_transport'));unset($GLOBALS['cwp_support']);
    notifications_configure(2,3,$webhook,false,$password);n_ok('Owner can disable receiver despite rotated merchant',n_receive(n_payload())===503);
    save_setting('payment_notifications_schema_version','');n_deny('Migration never adopts unknown populated notification table',fn()=>notifications_migrate($password));save_setting('payment_notifications_schema_version','1');
    n_ok('All webhook paths preserve real invoice byte for byte',one('SELECT * FROM '.table('studio_invoices'))===$invoice);
    $dump=json_encode(all('SELECT * FROM '.table('payment_notifications'))).json_encode(all('SELECT * FROM '.table('audit')));n_ok('Notification and audit history contain no payload customer data or keys',!str_contains($dump,'NeverPersistThis')&&!str_contains($dump,$key)&&!str_contains($dump,'FixtureKey123456'));
    n_ok('Reporting fixture never executes a payment mutation',!in_array('createTransactionRequest',$calls,true));
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
file_put_contents(__DIR__.'/../qa/notifications-native-results.json',json_encode(['scope'=>'Isolated MariaDB; concurrent PHP receivers; synthetic HMAC and reporting; no actual merchant delivery','checks'=>$checks,'passed'=>!isset($failure),'failure'=>$failure??null],JSON_PRETTY_PRINT));
foreach($checks as $c)echo ($c['passed']?'PASS ':'FAIL ').$c['name'].PHP_EOL;
if(isset($failure)){fwrite(STDERR,$failure.PHP_EOL);exit(1);}echo 'Native notification checks: '.count($checks).' passed'.PHP_EOL;
