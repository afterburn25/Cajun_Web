<?php
/** Disposable MySQL fixture; injected DNS/transport here, actual TLS in the separate receiving suite. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/app/mail/flags.php';
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='mfci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic flag owner passphrase';$resolveCalls=0;$transportCalls=0;$mode='ok';
function mf_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function mf_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;mf_ok($name,true);return;}mf_ok($name,false);}
function mf_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function mf_input():array {return ['label'=>'Synthetic flags','address'=>'synthetic@example.net','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic-incoming','smtp_host'=>'smtp.example.net','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic-outgoing'];}
function mf_create():string {global $password;return mail_accounts_save('',0,mf_input(),true,['imap_password'=>'Synthetic private flag password','smtp_password'=>'Synthetic outgoing password'],$password);}
function mf_grant(string $id,int $user=1,bool $read=true,bool $send=false):void {global $password;$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=?',[$id,$user]);mail_accounts_grant($id,$user,(int)($g['revision']??0),$read,$send,true,0,$password);}
function mf_resolve(string $host):array {global $resolveCalls;$resolveCalls++;if(db()->inTransaction())throw new LogicException('DNS inside transaction');return ['8.8.8.8'];}
function mf_transport(array $row,#[SensitiveParameter] array $secrets,string $address,string $folder,string $validity,string $uid,bool $seen,callable $authorize):array {
    global $transportCalls,$mode;$transportCalls++;
    if(db()->inTransaction()||$address!=='8.8.8.8'||$secrets['imap_password']!=='Synthetic private flag password')throw new LogicException('Invalid flag boundary');
    if($mode==='expire')query('UPDATE '.table('mail_account_grants').' SET expires_at=? WHERE account_id=? AND user_id=1',[time()-1,$row['id']]);
    $authorize();if($mode==='flags')throw new ReceivingFailure('flags');if($mode==='throw')throw new RuntimeException('private provider secret');
    $response=['folder'=>$folder,'uidvalidity'=>$validity,'uid'=>$uid,'seen'=>$seen,'applied'=>true,'confirmed'=>true];
    if($mode==='after')query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=1',[$row['id']]);
    if($mode==='unchanged')$response['applied']=false;
    if($mode==='unconfirmed')$response['confirmed']=false;
    if($mode==='folder')$response['folder']=$folder==='INBOX'?'Sent':'INBOX';
    if($mode==='uid')$response['uid']='1';
    if($mode==='extra')$response['secret']='provider detail';
    return $response;
}
function mf_mark(string $id,bool $seen=true,int $revision=1,string $folder='INBOX'):array {return mail_message_mark($id,$revision,$folder,'4294967295','4294967295',$seen,'mf_resolve','mf_transport');}
/** Aging also pins each request to a distinct staggered epoch; equal started_epoch values leave InnoDB history ordering undefined. */
function mf_age():void {
    global $mfSeq;$mfSeq=$mfSeq??0;
    foreach(all('SELECT id FROM '.table('mail_flag_requests').' WHERE started_epoch>? ORDER BY started_epoch,started_at,id',[time()-600]) as $row)
        query('UPDATE '.table('mail_flag_requests').' SET started_epoch=? WHERE id=?',[time()-7200+(++$mfSeq),$row['id']]);
}
function mf_audit_failure(callable $fn,string $action):bool {
    db()->exec('CREATE TRIGGER mfci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='mail_accounts.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic flag audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER mfci_fail');}return $failed;
}
try {
    foreach(schema_statements('mfci_') as $name=>$sql)if(!str_starts_with($name,'mail_flag_'))db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Flag staff '.$i,'flag'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>['mail.read'],3=>[]] as $user=>$permissions)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$user,'administrator',json_encode($permissions)]);
    save_setting('mail_accounts_schema_version','1');save_setting('mail_inbox_schema_version','2');save_setting('mail_messages_schema_version','2');
    mf_actor();$id=mf_create();mf_grant($id);
    mf_deny('Flag storage setup requires current owner password',fn()=>mail_flags_migrate('wrong'));
    mf_actor(2);mf_deny('Reader cannot enable flag storage',fn()=>mail_flags_migrate($password));mf_actor();
    mf_deny('Flag marks require explicit storage setup',fn()=>mf_mark($id));
    mf_ok('Setup audit failure preserves unready state',mf_audit_failure(fn()=>mail_flags_migrate($password),'flag_setup')&&!mail_flags_ready());
    query('INSERT INTO '.table('mail_flag_requests').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,mark_seen,started_at,started_epoch) VALUES (?,?,1,1,\'INBOX\',\'4294967295\',\'4294967295\',1,?,?)',[str_repeat('a',32),$id,gmdate('Y-m-d H:i:s'),time()]);mf_deny('Unknown existing flag requests cannot be silently adopted',fn()=>mail_flags_migrate($password));query('DELETE FROM '.table('mail_flag_requests'));
    $cipher=mail_accounts_load($id)['credentials'];mail_flags_migrate($password);mail_flags_migrate($password);mf_ok('Flag storage setup is additive and idempotent',mail_flags_ready()&&mail_accounts_load($id)['credentials']===$cipher&&!mail_flags_history($id));
    mf_actor(3);mf_deny('Staff without global read cannot mark flags',fn()=>mf_mark($id));mf_actor();
    mf_grant($id,1,false,true);mf_deny('Send-only assignment cannot mark flags',fn()=>mf_mark($id));mf_grant($id);
    mf_deny('Stale saved version prevents flag network activity',fn()=>mf_mark($id,true,99));
    foreach([['0','1'],['4294967296','1']] as [$epoch,$uid])mf_deny('Invalid exact identity refused before network '.json_encode([$epoch,$uid]),fn()=>mail_message_mark($id,1,'INBOX',$epoch,$uid,true,'mf_resolve','mf_transport'));
    foreach(['Bad"Folder','IN*','A'.str_repeat('x',64)] as $folder)mf_deny('Unsafe folder refused before network '.json_encode($folder),fn()=>mf_mark($id,true,1,$folder));
    mf_ok('All denied flag requests avoid DNS and transport',$resolveCalls===0&&$transportCalls===0);
    mf_ok('Started audit failure rolls back flag intent before DNS',mf_audit_failure(fn()=>mf_mark($id),'flag_started')&&$resolveCalls===0&&$transportCalls===0&&!mail_flags_history($id));
    $r=mf_mark($id);mf_ok('Successful mark binds the complete exact identity',$r['code']==='ok'&&$r['applied']===true&&$r['account_id']===$id&&$r['account_revision']===1&&$r['actor_id']===1&&$r['folder']==='INBOX'&&$r['uidvalidity']==='4294967295'&&$r['uid']==='4294967295'&&$r['seen']===true);
    $stored=one('SELECT * FROM '.table('mail_flag_results').' WHERE id=?',[$r['request_id']]);mf_ok('Success persists an immutable applied result without credentials',(int)$stored['applied']===1&&!str_contains(json_encode($stored),'flag password'));
    mf_deny('Immediate repeat is limited before DNS',fn()=>mf_mark($id));mf_ok('Repeat does not authenticate twice',$resolveCalls===1&&$transportCalls===1);
    mf_age();$r=mf_mark($id,false);mf_ok('Mark unread records the remove intent',$r['code']==='ok'&&$r['seen']===false&&(int)one('SELECT mark_seen FROM '.table('mail_flag_requests').' WHERE id=?',[$r['request_id']])['mark_seen']===0);
    mf_age();$mode='unchanged';$r=mf_mark($id);mf_ok('Already-matching server state records unchanged',$r['code']==='unchanged'&&$r['applied']===false);$mode='ok';
    mf_age();mf_grant($id,2);mf_actor(2);$r=mf_mark($id);mf_ok('Currently assigned non-owner can mark the exact message',$r['code']==='ok'&&$r['actor_id']===2);mf_actor();
    $ownerHistory=mail_flags_history($id);mf_ok('Flag history is private to the requesting staff member',count($ownerHistory)===3&&array_map('intval',array_column($ownerHistory,'mark_seen'))===[1,0,1]&&!isset($ownerHistory[0]['actor_id']));
    mf_actor(2);$readerHistory=mail_flags_history($id);mf_ok('Reader sees only their own flag history',count($readerHistory)===1&&(int)$readerHistory[0]['mark_seen']===1);mf_actor();
    foreach(['flags'=>'flags','throw'=>'connection','folder'=>'protocol','uid'=>'protocol','expire'=>'access_changed','after'=>'access_changed','unconfirmed'=>'unconfirmed'] as $flagMode=>$expected) {
        mf_age();$mode=$flagMode;$r=mf_mark($id);mf_ok('Transport '.$flagMode.' returns a finite failure',$r['code']===$expected);$mode='ok';mf_grant($id);
    }
    $mode='extra';mf_age();$r=mf_mark($id);mf_ok('Extra transport fields never escape the flag contract',$r['code']==='ok'&&!str_contains(json_encode($r),'provider detail'));$mode='ok';
    mf_age();$before=$transportCalls;$r=mail_message_mark($id,1,'INBOX','4294967295','4294967295',true,fn($h)=>['8.8.8.8','127.0.0.1'],'mf_transport');mf_ok('Mixed public private DNS is refused before flag transport',$r['code']==='destination'&&$transportCalls===$before);
    mf_age();query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',['broken synthetic envelope',$id]);$r=mf_mark($id);mf_ok('Damaged ciphertext never reaches the flag transport',$r['code']==='credential'&&$transportCalls===$before);query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',[$cipher,$id]);
    mf_age();mf_ok('Final audit failure leaves incomplete flag intent',mf_audit_failure(fn()=>mf_mark($id),'flag_finished')&&mail_flags_history($id)[0]['outcome']===null);
    $started=one('SELECT * FROM '.table('mail_flag_requests').' ORDER BY started_epoch DESC LIMIT 1');
    mail_flag_record($started,'connection',1,false);
    try{mail_flag_record($started,'ok',2,true);$duplicate=false;}catch(PDOException){$duplicate=true;}
    mf_ok('A flag request can receive only one immutable terminal result',$duplicate);
    mf_age();$other=mf_create();for($i=0;$i<120;$i++)query('INSERT INTO '.table('mail_flag_requests').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,mark_seen,started_at,started_epoch) VALUES (?,?,1,1,\'INBOX\',\'4294967295\',\'4294967295\',1,?,?)',[bin2hex(random_bytes(16)),$other,gmdate('Y-m-d H:i:s'),time()-100]);mf_deny('Per-staff hourly flag ceiling includes requests to other accounts',fn()=>mf_mark($id));mf_age();
    for($i=0;$i<120;$i++)query('INSERT INTO '.table('mail_flag_requests').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,mark_seen,started_at,started_epoch) VALUES (?,?,1,2,\'INBOX\',\'4294967295\',\'4294967295\',1,?,?)',[bin2hex(random_bytes(16)),$id,gmdate('Y-m-d H:i:s'),time()-100]);mf_deny('Per-account hourly flag ceiling includes requests by other administrators',fn()=>mf_mark($id));mf_age();
    save_setting('mail_notices_schema_version','2');
    query('INSERT INTO '.table('mail_inbox_reads').' (id,account_id,account_revision,actor_id,folder,started_at,started_epoch) VALUES (?,?,1,1,\'INBOX\',?,?)',[str_repeat('b',32),$id,gmdate('Y-m-d H:i:s'),time()]);
    query('INSERT INTO '.table('mail_notice_windows').' (account_id,account_revision,folder,uidvalidity,request_id,checked_at) VALUES (?,1,\'INBOX\',\'4294967295\',?,?)',[$id,str_repeat('b',32),gmdate('Y-m-d H:i:s')]);
    query('INSERT INTO '.table('mail_notice_items').' (handle,account_id,account_revision,folder,uidvalidity,uid,slot,server_seen,flags_observed_at) VALUES (?,?,1,\'INBOX\',\'4294967295\',\'4294967295\',1,0,?)',[str_repeat('c',32),$id,gmdate('Y-m-d H:i:s')]);
    mf_age();$r=mf_mark($id);mf_ok('A successful mark reconciles the recorded notice flag state',(int)one('SELECT server_seen FROM '.table('mail_notice_items').' WHERE handle=?',[str_repeat('c',32)])['server_seen']===1);
    $retained=json_encode([all('SELECT * FROM '.table('mail_flag_requests')),all('SELECT * FROM '.table('mail_flag_results')),all('SELECT * FROM '.table('audit'))]);mf_ok('Flag request and audit storage contain no passwords or remote exception text',!str_contains($retained,'flag password')&&!str_contains($retained,'private provider'));
    mf_ok('Flag routes delegate to current mailbox authority and keep setup owner-only',staff_action_permission('mail_message_flag')===null&&staff_action_permission('mail_message_flags_setup')==='staff.manage');
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e."\n");}
$pass=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/mail-flags-native-results.json',json_encode(['passed'=>$pass,'scope'=>'Native database and injected resolver/transport; separate actual TLS and HTTP acceptance','checks'=>$checks],JSON_PRETTY_PRINT)."\n");echo count($checks).' native flag checks: '.($pass?'PASS':'FAIL')."\n";exit($pass?0:1);
