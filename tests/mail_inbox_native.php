<?php
/** Disposable MySQL fixture; injected DNS/transport here, actual TLS in the separate receiving suite. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='mici_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic inbox owner passphrase';$resolveCalls=0;$transportCalls=0;$mode='ok';
function mi_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function mi_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;mi_ok($name,true);return;}mi_ok($name,false);}
function mi_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function mi_input():array {return ['label'=>'Synthetic inbox','address'=>'synthetic@example.net','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic-incoming','smtp_host'=>'smtp.example.net','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic-outgoing'];}
function mi_create():string {global $password;return mail_accounts_save('',0,mi_input(),true,['imap_password'=>'Synthetic private inbox password','smtp_password'=>'Synthetic outgoing password'],$password);}
function mi_grant(string $id,int $user=1,bool $read=true,bool $send=false):void {global $password;$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=?',[$id,$user]);mail_accounts_grant($id,$user,(int)($g['revision']??0),$read,$send,true,0,$password);}
function mi_resolve(string $host):array {global $resolveCalls;$resolveCalls++;if(db()->inTransaction())throw new LogicException('DNS inside transaction');return ['8.8.8.8'];}
function mi_batch():array {return ['folder'=>'INBOX','uidvalidity'=>'4294967295','total'=>1,'messages'=>[['uid'=>'4294967295','seen'=>false,'bytes'=>'2048','subject'=>'SYNTHETIC_PRIVATE_HEADER <script>claim</script>','from'=>'Synthetic sender <synthetic@example.net>','date'=>'Claimed date','headers_truncated'=>false]]];}
function mi_transport(array $row,#[SensitiveParameter] array $secrets,string $address,callable $authorize):array {
    global $transportCalls,$mode;$transportCalls++;
    if(db()->inTransaction()||$address!=='8.8.8.8'||$secrets['imap_password']!=='Synthetic private inbox password')throw new LogicException('Invalid receiving boundary');
    if($mode==='expire')query('UPDATE '.table('mail_account_grants').' SET expires_at=? WHERE account_id=? AND user_id=1',[time()-1,$row['id']]);
    $authorize();if($mode==='tls')throw new ReceivingFailure('tls');if($mode==='throw')throw new RuntimeException('private provider secret');
    $batch=mi_batch();
    if($mode==='after')query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=1',[$row['id']]);
    if($mode==='partial')$batch['total']=2;
    if($mode==='extra'){$batch['secret']='provider body';$batch['messages'][0]['body']='provider body';}
    return $batch;
}
function mi_read(string $id,int $revision=1):array {return mail_inbox_read($id,$revision,'mi_resolve','mi_transport');}
function mi_age():void {query('UPDATE '.table('mail_inbox_reads').' SET started_epoch=?',[time()-7200]);}
function mi_audit_failure(callable $fn,string $action):bool {
    db()->exec('CREATE TRIGGER mici_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='mail_accounts.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic inbox audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER mici_fail');}return $failed;
}
if(($argv[1]??'')==='pause-dns') {mi_actor();$r=mail_inbox_read($argv[2],1,function($h){echo "resolving\n";fflush(STDOUT);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Fixture sync');return mi_resolve($h);},'mi_transport');echo $r['code'].':'.$transportCalls;exit;}
if(($argv[1]??'')==='read-worker') {mi_actor();echo "ready\n";fflush(STDOUT);try{echo mi_read($argv[2])['code'];}catch(RuntimeException $e){if($e instanceof PDOException||!str_contains($e->getMessage(),'15 seconds'))throw $e;echo 'cooldown';}exit;}
if(($argv[1]??'')==='pause-transport') {mi_actor();$r=mail_inbox_read($argv[2],1,'mi_resolve',function($row,$secrets,$address,$authorize){echo "transport\n";fflush(STDOUT);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Fixture sync');return mi_transport($row,$secrets,$address,$authorize);});echo $r['code'];exit;}
if(($argv[1]??'')==='disable-worker') {mi_actor();echo "ready\n";fflush(STDOUT);mail_accounts_state($argv[2],1,'disable',$password);echo 'disabled';exit;}
try {
    foreach(schema_statements('mici_') as $name=>$sql)if(!str_starts_with($name,'mail_inbox_'))db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    for($i=1;$i<=4;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Inbox staff '.$i,'inbox'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>['mail.read'],3=>['mail.send'],4=>[]] as $user=>$permissions)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$user,$user===4?'owner':'administrator',json_encode($permissions)]);
    save_setting('mail_accounts_schema_version','1');mi_actor();$id=mi_create();mi_grant($id);
    mi_deny('Inbox storage setup requires current owner password',fn()=>mail_inbox_migrate('wrong'));
    mi_actor(2);mi_deny('Reader cannot enable inbox storage',fn()=>mail_inbox_migrate($password));mi_actor();
    mi_deny('Inbox reads require explicit storage setup',fn()=>mi_read($id));
    mi_ok('Setup audit failure preserves unready state',mi_audit_failure(fn()=>mail_inbox_migrate($password),'inbox_setup')&&!mail_inbox_ready());
    db()->exec('ALTER TABLE '.table('mail_inbox_results').' MODIFY uidvalidity VARCHAR(10) CHARACTER SET ascii COLLATE ascii_general_ci NULL');mi_deny('Setup refuses incompatible mailbox identity collation',fn()=>mail_inbox_migrate($password));db()->exec('ALTER TABLE '.table('mail_inbox_results').' MODIFY uidvalidity VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL');
    $foreign=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['mici_mail_inbox_results'])['CONSTRAINT_NAME'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$foreign))throw new RuntimeException('Unexpected fixture key');
    db()->exec('ALTER TABLE '.table('mail_inbox_results').' DROP FOREIGN KEY `'.$foreign.'`');mi_deny('Setup refuses missing immutable result relationship',fn()=>mail_inbox_migrate($password));db()->exec('ALTER TABLE '.table('mail_inbox_results').' ADD FOREIGN KEY (id) REFERENCES '.table('mail_inbox_reads').' (id) ON DELETE RESTRICT');
    query('INSERT INTO '.table('mail_inbox_reads').' (id,account_id,account_revision,actor_id,started_at,started_epoch) VALUES (?,?,1,1,?,?)',[str_repeat('a',32),$id,gmdate('Y-m-d H:i:s'),time()]);mi_deny('Unknown existing requests cannot be silently adopted',fn()=>mail_inbox_migrate($password));query('DELETE FROM '.table('mail_inbox_reads'));
    $cipher=mail_accounts_load($id)['credentials'];mail_inbox_migrate($password);mail_inbox_migrate($password);mi_ok('Inbox storage setup is additive and idempotent',mail_inbox_ready()&&mail_accounts_load($id)['credentials']===$cipher&&!mail_inbox_history($id));
    mi_actor(4);mi_deny('Another owner has no implicit mailbox assignment',fn()=>mi_read($id));mi_actor();
    mi_grant($id,1,false,true);mi_deny('Send-only assignment cannot read headers',fn()=>mi_read($id));mi_grant($id);
    mi_grant($id,3,true,true);mi_actor(3);mi_deny('Global send permission cannot use a read assignment',fn()=>mi_read($id));mi_actor();
    foreach(['disabled','future','expired','revoked'] as $condition) {
        if($condition==='disabled')query('UPDATE '.table('mail_accounts').' SET enabled=0 WHERE id=?',[$id]);
        elseif($condition==='future')query('UPDATE '.table('mail_account_grants').' SET issued_at=? WHERE account_id=? AND user_id=1',[time()+3600,$id]);
        elseif($condition==='expired')query('UPDATE '.table('mail_account_grants').' SET expires_at=? WHERE account_id=? AND user_id=1',[time()-1,$id]);
        else query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=1',[$id]);
        mi_deny('Mailbox '.$condition.' prevents all network activity',fn()=>mi_read($id));query('UPDATE '.table('mail_accounts').' SET enabled=1 WHERE id=?',[$id]);mi_grant($id);
    }
    mi_deny('Stale saved version prevents network activity',fn()=>mi_read($id,99));
    $_GET['support']='synthetic';mi_deny('Support context cannot read staff inboxes',fn()=>mi_read($id));unset($_GET['support']);
    $_SESSION=['client_id'=>1];mi_deny('Client identity cannot read staff inboxes',fn()=>mi_read($id));mi_actor();$_SESSION['auth_version']='revoked';mi_deny('Stale authentication version cannot read headers',fn()=>mi_read($id));mi_actor();
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');mi_deny('Disabled staff cannot read headers',fn()=>mi_read($id));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');mi_actor();
    mi_ok('All denied requests avoid DNS and transport',$resolveCalls===0&&$transportCalls===0);
    mi_ok('Started audit failure rolls back intent before DNS',mi_audit_failure(fn()=>mi_read($id),'inbox_started')&&$resolveCalls===0&&$transportCalls===0&&!mail_inbox_history($id));
    $r=mi_read($id);mi_ok('Successful request returns current exact account and immutable message identity',$r['code']==='ok'&&$r['account_id']===$id&&$r['account_revision']===1&&$r['actor_id']===1&&$r['batch']['uidvalidity']==='4294967295'&&$r['batch']['messages'][0]['uid']==='4294967295');
    $stored=one('SELECT * FROM '.table('mail_inbox_results').' WHERE id=?',[$r['request_id']]);mi_ok('Success persists counts and identity epoch without header text',$stored['message_count']===1&&$stored['mailbox_total']===1&&$stored['uidvalidity']==='4294967295'&&!str_contains(json_encode($stored),'PRIVATE_HEADER'));
    mi_deny('Immediate repeat is limited before DNS',fn()=>mi_read($id));mi_ok('Repeat does not authenticate twice',$resolveCalls===1&&$transportCalls===1);
    mi_age();mi_grant($id,2);mi_actor(2);$r=mi_read($id);mi_ok('Currently assigned non-owner can read the inbox',$r['code']==='ok'&&$r['actor_id']===2);
    mi_ok('Reader request history contains only their own requests',count(mail_inbox_history($id))===1&&mail_inbox_history($id)[0]['actor_id']===2);mi_actor();
    foreach(['tls'=>'tls','throw'=>'connection','partial'=>'protocol','expire'=>'access_changed','after'=>'access_changed'] as $mode=>$expected) {
        mi_age();$r=mi_read($id);mi_ok('Transport '.$mode.' returns a finite failure with no partial headers',$r['code']===$expected&&$r['batch']===null);mi_grant($id);
    }
    $mode='extra';mi_age();$r=mi_read($id);mi_ok('Extra transport MIME fields never leave explicit header projection',$r['code']==='ok'&&!str_contains(json_encode($r),'provider body'));$mode='ok';
    mi_age();$before=$transportCalls;$r=mail_inbox_read($id,1,fn($h)=>['8.8.8.8','127.0.0.1'],'mi_transport');mi_ok('Mixed public private DNS is refused before transport',$r['code']==='destination'&&$transportCalls===$before);
    mi_age();$r=mail_inbox_read($id,1,fn($h)=>throw new RuntimeException('private DNS detail'),'mi_transport');mi_ok('Resolver errors are sanitized',$r['code']==='connection'&&!str_contains(json_encode($r),'private DNS'));
    mi_age();query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',['broken synthetic envelope',$id]);$r=mi_read($id);mi_ok('Damaged ciphertext never reaches the transport',$r['code']==='credential'&&$transportCalls===$before);query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',[$cipher,$id]);
    mi_age();mi_ok('Final audit failure returns no headers and leaves incomplete intent',mi_audit_failure(fn()=>mi_read($id),'inbox_finished')&&mail_inbox_history($id)[0]['outcome']===null);
    $unfinished=mail_inbox_history($id)[0];mail_inbox_record($unfinished,'connection',1);try{mail_inbox_record($unfinished,'ok',2,mi_batch());$duplicate=false;}catch(PDOException){$duplicate=true;}mi_ok('A request can receive only one immutable terminal result',$duplicate);
    mi_age();for($i=0;$i<120;$i++)query('INSERT INTO '.table('mail_inbox_reads').' (id,account_id,account_revision,actor_id,started_at,started_epoch) VALUES (?,?,1,1,?,?)',[bin2hex(random_bytes(16)),$id,gmdate('Y-m-d H:i:s'),time()-100]);mi_deny('Per-staff hourly request ceiling enforced',fn()=>mi_read($id));mi_age();
    $race=mi_create();mi_grant($race);$lock='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);query('SELECT GET_LOCK(?,5)',[$lock]);$workers=[];
    try{for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'read-worker',$race],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$proc,$pipes];mi_ok('Concurrent read worker ready '.($i+1),trim(fgets($pipes[1]))==='ready');}}finally{query('SELECT RELEASE_LOCK(?)',[$lock]);}
    $out=[];foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);mi_ok('Concurrent read worker finishes '.count($out),proc_close($proc)===0&&$err==='');}sort($out);mi_ok('Simultaneous requests authenticate once',$out===['cooldown','ok']&&count(mail_inbox_history($race))===1);
    foreach(['revoke','edit'] as $change) {
        mi_age();$interleave=mi_create();mi_grant($interleave);$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'pause-dns',$interleave],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        mi_ok('Intent is committed before paused DNS and '.$change,trim(fgets($pipes[1]))==='resolving'&&count(mail_inbox_history($interleave))===1);
        if($change==='revoke'){$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=1',[$interleave]);mail_accounts_grant($interleave,1,(int)$g['revision'],true,false,false,0,$password);}else mail_accounts_save($interleave,1,mi_input(),true,[],$password);
        fwrite($pipes[0],"continue\n");fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
        mi_ok('Concurrent '.$change.' during DNS prevents credential use',$exit===0&&$err===''&&$out==='access_changed:0'&&one('SELECT r.result_code FROM '.table('mail_inbox_results').' r JOIN '.table('mail_inbox_reads').' q ON q.id=r.id WHERE q.account_id=?',[$interleave])['result_code']==='access_changed');
    }
    mi_age();$locked=mi_create();mi_grant($locked);$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'pause-transport',$locked],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);mi_ok('Network worker enters transport without database transaction',trim(fgets($pipes[1]))==='transport');
    $editPipes=[];$editor=proc_open([PHP_BINARY,__FILE__,'disable-worker',$locked],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$editPipes);fclose($editPipes[0]);mi_ok('Concurrent account writer begins',trim(fgets($editPipes[1]))==='ready');stream_set_blocking($editPipes[1],false);usleep(100000);mi_ok('Account changes wait while current credentials are in use',stream_get_contents($editPipes[1])===''&&(int)mail_accounts_load($locked)['enabled']===1);
    fwrite($pipes[0],"continue\n");fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);stream_set_blocking($editPipes[1],true);$edited=stream_get_contents($editPipes[1]);$editErr=stream_get_contents($editPipes[2]);fclose($editPipes[1]);fclose($editPipes[2]);$editExit=proc_close($editor);mi_ok('Credential lock releases after the bounded request and account edit proceeds',$exit===0&&$err===''&&$out==='ok'&&$editExit===0&&$editErr===''&&$edited==='disabled'&&(int)mail_accounts_load($locked)['enabled']===0);
    $paged=mi_create();mi_grant($paged);for($i=0;$i<24;$i++)query('INSERT INTO '.table('mail_inbox_reads').' (id,account_id,account_revision,actor_id,started_at,started_epoch) VALUES (?,?,1,1,?,?)',[bin2hex(random_bytes(16)),$paged,gmdate('Y-m-d H:i:s'),time()-7200-$i]);$a=mail_inbox_history($paged);$b=mail_inbox_history($paged,2);mi_ok('Personal inbox history pages are distinct and bounded',count($a)===21&&count($b)===4&&!array_intersect(array_column(array_slice($a,0,20),'id'),array_column($b,'id')));
    $retained=json_encode([all('SELECT * FROM '.table('mail_inbox_reads')),all('SELECT * FROM '.table('mail_inbox_results')),all('SELECT * FROM '.table('audit'))]);mi_ok('Request and audit storage contain no headers passwords or remote exception text',!str_contains($retained,'PRIVATE_HEADER')&&!str_contains($retained,'private inbox password')&&!str_contains($retained,'private provider'));
    mi_ok('Inbox reading keeps website system-email settings unchanged',setting('email_mode')==='disabled'&&setting('smtp_host')==='');
    mi_ok('Routes separate owner setup from assigned staff reading',staff_view_permission('mail-inbox')==='mail.read'&&staff_action_permission('mail_inbox_read')==='mail.read'&&staff_action_permission('mail_inbox_setup')==='staff.manage');
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e."\n");}
$pass=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/mail-inbox-native-results.json',json_encode(['passed'=>$pass,'scope'=>'Native database and injected resolver/transport; separate actual TLS and HTTP acceptance','checks'=>$checks],JSON_PRETTY_PRINT)."\n");echo count($checks).' native inbox checks: '.($pass?'PASS':'FAIL')."\n";exit($pass?0:1);
