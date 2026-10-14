<?php
/** Disposable MySQL fixture; injected DNS/transport here, actual TLS in the separate receiving suite. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='mmci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic message owner passphrase';$resolveCalls=0;$transportCalls=0;$mode='ok';
function mm_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function mm_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;mm_ok($name,true);return;}mm_ok($name,false);}
function mm_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function mm_input():array {return ['label'=>'Synthetic message','address'=>'synthetic@example.net','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic-incoming','smtp_host'=>'smtp.example.net','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic-outgoing'];}
function mm_create():string {global $password;return mail_accounts_save('',0,mm_input(),true,['imap_password'=>'Synthetic private message password','smtp_password'=>'Synthetic outgoing password'],$password);}
function mm_grant(string $id,int $user=1,bool $read=true,bool $send=false):void {global $password;$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=?',[$id,$user]);mail_accounts_grant($id,$user,(int)($g['revision']??0),$read,$send,true,0,$password);}
function mm_resolve(string $host):array {global $resolveCalls;$resolveCalls++;if(db()->inTransaction())throw new LogicException('DNS inside transaction');return ['8.8.8.8'];}
function mm_batch():array {
    $raw="Subject: SYNTHETIC_PRIVATE_HEADER <script>claim</script>\r\nFrom: Synthetic <synthetic@example.net>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=m\r\n\r\n--m\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSYNTHETIC_PRIVATE_BODY 👩‍💻\r\n--m\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<html><body><p>SYNTHETIC_PRIVATE_BODY</p><script>attack()</script><img src=https://tracker.invalid></body></html>\r\n--m--\r\n";
    return ['folder'=>'INBOX','uidvalidity'=>'4294967295','uid'=>'4294967295','seen'=>false,'bytes'=>(string)strlen($raw),'raw'=>$raw];
}
function mm_transport(array $row,#[SensitiveParameter] array $secrets,string $address,string $validity,string $uid,callable $authorize):array {
    global $transportCalls,$mode;$transportCalls++;
    if(db()->inTransaction()||$address!=='8.8.8.8'||$secrets['imap_password']!=='Synthetic private message password')throw new LogicException('Invalid receiving boundary');
    if($mode==='expire')query('UPDATE '.table('mail_account_grants').' SET expires_at=? WHERE account_id=? AND user_id=1',[time()-1,$row['id']]);
    $authorize();if($mode==='tls')throw new ReceivingFailure('tls');if($mode==='throw')throw new RuntimeException('private provider secret');
    $batch=mm_batch();
    if($mode==='after')query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=1',[$row['id']]);
    if($mode==='partial')$batch['bytes']='1';
    if($mode==='uid')$batch['uid']='1';
    if($mode==='epoch')$batch['uidvalidity']='1';
    if($mode==='folder')$batch['folder']='Sent';
    if($mode==='oversize'){$batch['raw']=str_repeat('x',262145);$batch['bytes']='262145';}
    if($mode==='badmime'){$batch['raw']='No MIME headers';$batch['bytes']=(string)strlen($batch['raw']);}
    if($mode==='extra'){$batch['secret']='provider body';$batch['html']='<script>provider body</script>';$batch['subject']='provider body';}
    return $batch;
}
function mm_read(string $id,int $revision=1):array {return mail_message_read($id,$revision,'4294967295','4294967295','mm_resolve','mm_transport');}
function mm_age():void {query('UPDATE '.table('mail_message_reads').' SET started_epoch=?',[time()-7200]);}
function mm_audit_failure(callable $fn,string $action):bool {
    db()->exec('CREATE TRIGGER mmci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='mail_accounts.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic message audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER mmci_fail');}return $failed;
}
if(($argv[1]??'')==='pause-dns') {mm_actor();$r=mail_message_read($argv[2],1,'4294967295','4294967295',function($h){echo "resolving\n";fflush(STDOUT);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Fixture sync');return mm_resolve($h);},'mm_transport');echo $r['code'].':'.$transportCalls;exit;}
if(($argv[1]??'')==='read-worker') {mm_actor();echo "ready\n";fflush(STDOUT);try{echo mm_read($argv[2])['code'];}catch(RuntimeException $e){if($e instanceof PDOException||!str_contains($e->getMessage(),'2 seconds'))throw $e;echo 'cooldown';}exit;}
if(($argv[1]??'')==='pause-transport') {mm_actor();$r=mail_message_read($argv[2],1,'4294967295','4294967295','mm_resolve',function($row,$secrets,$address,$validity,$uid,$authorize){echo "transport\n";fflush(STDOUT);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Fixture sync');return mm_transport($row,$secrets,$address,$validity,$uid,$authorize);});echo $r['code'];exit;}
if(($argv[1]??'')==='disable-worker') {mm_actor();echo "ready\n";fflush(STDOUT);mail_accounts_state($argv[2],1,'disable',$password);echo 'disabled';exit;}
try {
    foreach(schema_statements('mmci_') as $name=>$sql)if(!str_starts_with($name,'mail_message_'))db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    for($i=1;$i<=4;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Message staff '.$i,'message'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>['mail.read'],3=>['mail.send'],4=>[]] as $user=>$permissions)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$user,$user===4?'owner':'administrator',json_encode($permissions)]);
    save_setting('mail_accounts_schema_version','1');mm_actor();$id=mm_create();mm_grant($id);
    mm_deny('Message storage setup requires current owner password',fn()=>mail_messages_migrate('wrong'));
    mm_actor(2);mm_deny('Reader cannot enable message storage',fn()=>mail_messages_migrate($password));mm_actor();
    mm_deny('Message reads require explicit storage setup',fn()=>mm_read($id));
    mm_ok('Setup audit failure preserves unready state',mm_audit_failure(fn()=>mail_messages_migrate($password),'message_setup')&&!mail_messages_ready());
    db()->exec('ALTER TABLE '.table('mail_message_reads').' MODIFY uidvalidity VARCHAR(10) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');mm_deny('Setup refuses incompatible mailbox identity collation',fn()=>mail_messages_migrate($password));db()->exec('ALTER TABLE '.table('mail_message_reads').' MODIFY uidvalidity VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    $foreign=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['mmci_mail_message_results'])['CONSTRAINT_NAME'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$foreign))throw new RuntimeException('Unexpected fixture key');
    db()->exec('ALTER TABLE '.table('mail_message_results').' DROP FOREIGN KEY `'.$foreign.'`');mm_deny('Setup refuses missing immutable result relationship',fn()=>mail_messages_migrate($password));db()->exec('ALTER TABLE '.table('mail_message_results').' ADD FOREIGN KEY (id) REFERENCES '.table('mail_message_reads').' (id) ON DELETE RESTRICT');
    query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,1,1,\'INBOX\',\'4294967295\',\'4294967295\',?,?)',[str_repeat('a',32),$id,gmdate('Y-m-d H:i:s'),time()]);mm_deny('Unknown existing requests cannot be silently adopted',fn()=>mail_messages_migrate($password));query('DELETE FROM '.table('mail_message_reads'));
    $cipher=mail_accounts_load($id)['credentials'];mail_messages_migrate($password);mail_messages_migrate($password);mm_ok('Message storage setup is additive and idempotent',mail_messages_ready()&&mail_accounts_load($id)['credentials']===$cipher&&!mail_message_history($id));
    mm_actor(4);mm_deny('Another owner has no implicit mailbox assignment',fn()=>mm_read($id));mm_actor();
    mm_grant($id,1,false,true);mm_deny('Send-only assignment cannot read messages',fn()=>mm_read($id));mm_grant($id);
    mm_grant($id,3,true,true);mm_actor(3);mm_deny('Global send permission cannot use a read assignment',fn()=>mm_read($id));mm_actor();
    foreach(['disabled','future','expired','revoked'] as $condition) {
        if($condition==='disabled')query('UPDATE '.table('mail_accounts').' SET enabled=0 WHERE id=?',[$id]);
        elseif($condition==='future')query('UPDATE '.table('mail_account_grants').' SET issued_at=? WHERE account_id=? AND user_id=1',[time()+3600,$id]);
        elseif($condition==='expired')query('UPDATE '.table('mail_account_grants').' SET expires_at=? WHERE account_id=? AND user_id=1',[time()-1,$id]);
        else query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=1',[$id]);
        mm_deny('Mailbox '.$condition.' prevents all network activity',fn()=>mm_read($id));query('UPDATE '.table('mail_accounts').' SET enabled=1 WHERE id=?',[$id]);mm_grant($id);
    }
    foreach([['0','1'],['1','0'],['01','1'],['4294967296','1'],['1','1 INBOX']] as [$epoch,$uid])mm_deny('Invalid exact identity refused before network '.json_encode([$epoch,$uid]),fn()=>mail_message_read($id,1,$epoch,$uid,'mm_resolve','mm_transport'));
    mm_deny('Stale saved version prevents network activity',fn()=>mm_read($id,99));
    $_GET['support']='synthetic';mm_deny('Support context cannot read staff messagees',fn()=>mm_read($id));unset($_GET['support']);
    $_SESSION=['client_id'=>1];mm_deny('Client identity cannot read staff messagees',fn()=>mm_read($id));mm_actor();$_SESSION['auth_version']='revoked';mm_deny('Stale authentication version cannot read messages',fn()=>mm_read($id));mm_actor();
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');mm_deny('Disabled staff cannot read messages',fn()=>mm_read($id));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');mm_actor();
    mm_ok('All denied requests avoid DNS and transport',$resolveCalls===0&&$transportCalls===0);
    mm_ok('Started audit failure rolls back intent before DNS',mm_audit_failure(fn()=>mm_read($id),'message_started')&&$resolveCalls===0&&$transportCalls===0&&!mail_message_history($id));
    $r=mm_read($id);mm_ok('Successful request returns current exact account and immutable message identity',$r['code']==='ok'&&$r['account_id']===$id&&$r['account_revision']===1&&$r['actor_id']===1&&$r['content']['uidvalidity']==='4294967295'&&$r['content']['uid']==='4294967295');
    $stored=one('SELECT * FROM '.table('mail_message_results').' WHERE id=?',[$r['request_id']]);mm_ok('Success persists counts and identity epoch without message content',(int)$stored['message_bytes']===(int)mm_batch()['bytes']&&one('SELECT uidvalidity FROM '.table('mail_message_reads').' WHERE id=?',[$r['request_id']])['uidvalidity']==='4294967295'&&!str_contains(json_encode($stored),'PRIVATE_HEADER'));
    mm_ok('Adapter always decodes raw MIME and sanitizes formatted content',$r['content']['text']==='SYNTHETIC_PRIVATE_BODY 👩‍💻'&&$r['content']['html']==='<p>SYNTHETIC_PRIVATE_BODY</p>'&&!array_key_exists('raw',$r['content']));
    mm_deny('Immediate repeat is limited before DNS',fn()=>mm_read($id));mm_ok('Repeat does not authenticate twice',$resolveCalls===1&&$transportCalls===1);
    mm_age();mm_grant($id,2);mm_actor(2);$r=mm_read($id);mm_ok('Currently assigned non-owner can read the exact message',$r['code']==='ok'&&$r['actor_id']===2);
    mm_ok('Reader request history contains only their own requests',count(mail_message_history($id))===1&&mail_message_history($id)[0]['actor_id']===2);mm_actor();
    foreach(['tls'=>'tls','throw'=>'connection','partial'=>'protocol','uid'=>'protocol','epoch'=>'protocol','folder'=>'protocol','oversize'=>'limit','badmime'=>'protocol','expire'=>'access_changed','after'=>'access_changed'] as $mode=>$expected) {
        mm_age();$r=mm_read($id);mm_ok('Transport '.$mode.' returns a finite failure with no partial content',$r['code']===$expected&&$r['content']===null);mm_grant($id);
    }
    $mode='extra';mm_age();$r=mm_read($id);mm_ok('Extra transport MIME fields never leave raw MIME decoding',$r['code']==='ok'&&!str_contains(json_encode($r),'provider body'));$mode='ok';
    mm_age();$before=$transportCalls;$r=mail_message_read($id,1,'4294967295','4294967295',fn($h)=>['8.8.8.8','127.0.0.1'],'mm_transport');mm_ok('Mixed public private DNS is refused before transport',$r['code']==='destination'&&$transportCalls===$before);
    mm_age();$r=mail_message_read($id,1,'4294967295','4294967295',fn($h)=>throw new RuntimeException('private DNS detail'),'mm_transport');mm_ok('Resolver errors are sanitized',$r['code']==='connection'&&!str_contains(json_encode($r),'private DNS'));
    mm_age();query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',['broken synthetic envelope',$id]);$r=mm_read($id);mm_ok('Damaged ciphertext never reaches the transport',$r['code']==='credential'&&$transportCalls===$before);query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',[$cipher,$id]);
    mm_age();mm_ok('Final audit failure returns no content and leaves incomplete intent',mm_audit_failure(fn()=>mm_read($id),'message_finished')&&mail_message_history($id)[0]['outcome']===null);
    $unfinished=mail_message_history($id)[0];mail_message_record($unfinished,'connection',1);try{mail_message_record($unfinished,'ok',2,mm_batch());$duplicate=false;}catch(PDOException){$duplicate=true;}mm_ok('A request can receive only one immutable terminal result',$duplicate);
    mm_age();$otherAccount=mm_create();for($i=0;$i<240;$i++)query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,1,1,\'INBOX\',\'4294967295\',\'4294967295\',?,?)',[bin2hex(random_bytes(16)),$otherAccount,gmdate('Y-m-d H:i:s'),time()-100]);mm_deny('Per-staff hourly ceiling includes requests to other accounts',fn()=>mm_read($id));mm_age();
    for($i=0;$i<240;$i++)query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,1,2,\'INBOX\',\'4294967295\',\'4294967295\',?,?)',[bin2hex(random_bytes(16)),$id,gmdate('Y-m-d H:i:s'),time()-100]);mm_deny('Per-account hourly ceiling includes requests by other administrators',fn()=>mm_read($id));mm_age();
    $race=mm_create();mm_grant($race);$lock='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);query('SELECT GET_LOCK(?,5)',[$lock]);$workers=[];
    try{for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'read-worker',$race],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$proc,$pipes];mm_ok('Concurrent read worker ready '.($i+1),trim(fgets($pipes[1]))==='ready');}}finally{query('SELECT RELEASE_LOCK(?)',[$lock]);}
    $out=[];foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);mm_ok('Concurrent read worker finishes '.count($out),proc_close($proc)===0&&$err==='');}sort($out);mm_ok('Simultaneous requests authenticate once',$out===['cooldown','ok']&&count(mail_message_history($race))===1);
    foreach(['revoke','edit'] as $change) {
        mm_age();$interleave=mm_create();mm_grant($interleave);$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'pause-dns',$interleave],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        mm_ok('Intent is committed before paused DNS and '.$change,trim(fgets($pipes[1]))==='resolving'&&count(mail_message_history($interleave))===1);
        if($change==='revoke'){$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=1',[$interleave]);mail_accounts_grant($interleave,1,(int)$g['revision'],true,false,false,0,$password);}else mail_accounts_save($interleave,1,mm_input(),true,[],$password);
        fwrite($pipes[0],"continue\n");fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
        mm_ok('Concurrent '.$change.' during DNS prevents credential use',$exit===0&&$err===''&&$out==='access_changed:0'&&one('SELECT r.result_code FROM '.table('mail_message_results').' r JOIN '.table('mail_message_reads').' q ON q.id=r.id WHERE q.account_id=?',[$interleave])['result_code']==='access_changed');
    }
    mm_age();$locked=mm_create();mm_grant($locked);$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'pause-transport',$locked],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);mm_ok('Network worker enters transport without database transaction',trim(fgets($pipes[1]))==='transport');
    $editPipes=[];$editor=proc_open([PHP_BINARY,__FILE__,'disable-worker',$locked],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$editPipes);fclose($editPipes[0]);mm_ok('Concurrent account writer begins',trim(fgets($editPipes[1]))==='ready');stream_set_blocking($editPipes[1],false);usleep(100000);mm_ok('Account changes wait while current credentials are in use',stream_get_contents($editPipes[1])===''&&(int)mail_accounts_load($locked)['enabled']===1);
    fwrite($pipes[0],"continue\n");fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);stream_set_blocking($editPipes[1],true);$edited=stream_get_contents($editPipes[1]);$editErr=stream_get_contents($editPipes[2]);fclose($editPipes[1]);fclose($editPipes[2]);$editExit=proc_close($editor);mm_ok('Credential lock releases after the bounded request and account edit proceeds',$exit===0&&$err===''&&$out==='ok'&&$editExit===0&&$editErr===''&&$edited==='disabled'&&(int)mail_accounts_load($locked)['enabled']===0);
    $paged=mm_create();mm_grant($paged);for($i=0;$i<24;$i++)query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,1,1,\'INBOX\',\'4294967295\',\'4294967295\',?,?)',[bin2hex(random_bytes(16)),$paged,gmdate('Y-m-d H:i:s'),time()-7200-$i]);$a=mail_message_history($paged);$b=mail_message_history($paged,2);mm_ok('Personal message history pages are distinct and bounded',count($a)===21&&count($b)===4&&!array_intersect(array_column(array_slice($a,0,20),'id'),array_column($b,'id')));
    $retained=json_encode([all('SELECT * FROM '.table('mail_message_reads')),all('SELECT * FROM '.table('mail_message_results')),all('SELECT * FROM '.table('audit'))]);mm_ok('Request and audit storage contain no content passwords or remote exception text',!str_contains($retained,'PRIVATE_HEADER')&&!str_contains($retained,'PRIVATE_BODY')&&!str_contains($retained,'private message password')&&!str_contains($retained,'private provider'));
    mm_ok('Message reading keeps website system-email settings unchanged',setting('email_mode')==='disabled'&&setting('smtp_host')==='');
    mm_ok('Message routes delegate to current mailbox authority and keep setup owner-only',staff_view_permission('mail-message')===null&&staff_action_permission('mail_message_read')===null&&staff_action_permission('mail_message_setup')==='staff.manage');
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e."\n");}
$pass=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/mail-message-native-results.json',json_encode(['passed'=>$pass,'scope'=>'Native database and injected resolver/transport; separate actual TLS and HTTP acceptance','checks'=>$checks],JSON_PRETTY_PRINT)."\n");echo count($checks).' native message checks: '.($pass?'PASS':'FAIL')."\n";exit($pass?0:1);
