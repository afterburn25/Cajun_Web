<?php
/** Disposable database and injected transport boundaries. Real TLS is covered separately. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='mcci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic connection-check owner passphrase';$resolveCalls=0;$transportCalls=0;$mode='ok';
function mc_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function mc_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;mc_ok($name,true);return;}mc_ok($name,false);}
function mc_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function mc_input(): array {return ['label'=>'Synthetic diagnostics','address'=>'synthetic@example.net','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic-incoming','smtp_host'=>'smtp.example.net','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic-outgoing'];}
function mc_resolve(string $host): array {global $resolveCalls;$resolveCalls++;if(db()->inTransaction())throw new LogicException('DNS inside transaction');return ['8.8.8.8'];}
function mc_transport(array $row,string $target,#[SensitiveParameter] array $secrets,string $address,callable $authorize): void {
    global $transportCalls,$mode;$transportCalls++;
    if(db()->inTransaction()||$address!=='8.8.8.8'||$secrets[$target.'_password']!=='Synthetic private password')throw new LogicException('Invalid transport boundary.');
    if($mode==='expire')query('UPDATE '.table('mail_account_grants').' SET expires_at=? WHERE account_id=? AND user_id=1',[time()-1,$row['id']]);
    $authorize();if($mode==='tls')throw new ProbeFailure('tls');if($mode==='throw')throw new RuntimeException('Do not expose synthetic private password or server reply');
}
function mc_check(string $id,int $revision=1,string $target='imap'): string {global $password;return mail_connections_check($id,$revision,$target,$password,'mc_resolve','mc_transport');}
function mc_age(): void {query('UPDATE '.table('mail_connection_checks').' SET started_epoch=?',[time()-7200]);}
function mc_grant(string $id,bool $read=true,bool $send=true): void {global $password;$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=1',[$id]);mail_accounts_grant($id,1,(int)($g['revision']??0),$read,$send,true,0,$password);}
function mc_audit_failure(callable $fn,string $action=''): bool {
    $when=$action!==''?"IF NEW.action='mail_accounts.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic check audit failure'; END IF;":"SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic check audit failure';";
    db()->exec('CREATE TRIGGER mcci_fail BEFORE INSERT ON '.table('audit').' FOR EACH ROW BEGIN '.$when.' END');
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER mcci_fail');}return $failed;
}
if(($argv[1]??'')==='pause-dns-worker') {
    mc_actor();
    $code=mail_connections_check($argv[2],1,'imap',$password,function($host){echo "resolving\n";fflush(STDOUT);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Fixture synchronization');return mc_resolve($host);},'mc_transport');
    echo $code.':'.$transportCalls;exit;
}
if(($argv[1]??'')==='check-worker') {
    mc_actor();echo "ready\n";fflush(STDOUT);
    try{echo mc_check($argv[2]);}catch(RuntimeException $e){if($e instanceof PDOException||!str_contains($e->getMessage(),'30 seconds'))throw $e;echo 'cooldown';}exit;
}
try {
    foreach(schema_statements('mcci_') as $name=>$sql)if(!str_starts_with($name,'mail_connection_'))db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Check staff '.$i,'check'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,?)",[json_encode(['mail.read','mail.send'])]);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (3,'owner',1,'[]')");
    save_setting('mail_accounts_schema_version','1');mc_actor();
    $id=mail_accounts_save('',0,mc_input(),true,['imap_password'=>'Synthetic private password','smtp_password'=>'Synthetic private password'],$password);
    mc_deny('Connection setup requires current owner password',fn()=>mail_connections_migrate('wrong'));
    mc_actor(2);mc_deny('Mailbox permissions cannot enable connection checks',fn()=>mail_connections_migrate($password));mc_actor();
    mc_deny('Checking is unavailable before explicit setup',fn()=>mc_check($id));
    mc_ok('Setup audit failure does not mark checks ready',mc_audit_failure(fn()=>mail_connections_migrate($password))&&!mail_connections_ready());
    db()->exec('ALTER TABLE '.table('mail_connection_checks').' MODIFY target VARCHAR(11) NOT NULL');
    mc_deny('Setup refuses an incompatible preexisting column',fn()=>mail_connections_migrate($password));
    db()->exec('ALTER TABLE '.table('mail_connection_checks').' MODIFY target VARCHAR(10) NOT NULL');
    $foreign=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['mcci_mail_connection_results'])['CONSTRAINT_NAME'];
    if(!preg_match('/^[A-Za-z0-9_]+$/D',$foreign))throw new RuntimeException('Unexpected fixture constraint name');
    db()->exec('ALTER TABLE '.table('mail_connection_results').' DROP FOREIGN KEY `'.$foreign.'`');
    mc_deny('Setup refuses a missing immutable-result relationship',fn()=>mail_connections_migrate($password));
    db()->exec('ALTER TABLE '.table('mail_connection_results').' ADD FOREIGN KEY (id) REFERENCES '.table('mail_connection_checks').' (id) ON DELETE RESTRICT');
    query('INSERT INTO '.table('mail_connection_checks').' (id,account_id,account_revision,target,actor_id,started_at,started_epoch) VALUES (?,?,1,?,1,?,?)',[str_repeat('a',32),$id,'imap',gmdate('Y-m-d H:i:s'),time()]);
    mc_deny('Setup refuses unknown existing check records',fn()=>mail_connections_migrate($password));query('DELETE FROM '.table('mail_connection_checks'));
    mail_connections_migrate($password);mail_connections_migrate($password);
    mc_ok('Additive setup is idempotent and preserves saved credentials',mail_connections_ready()&&mail_accounts_get($id)['has_credentials']&&!mail_connections_history($id));
    mc_deny('Owner must have a matching mailbox assignment',fn()=>mc_check($id));
    mc_grant($id,true,false);mc_deny('Read assignment cannot test outgoing credentials',fn()=>mc_check($id,1,'smtp'));mc_grant($id);
    query('UPDATE '.table('mail_accounts').' SET enabled=0 WHERE id=?',[$id]);mc_deny('Disabled mailbox prevents all connection checks',fn()=>mc_check($id));query('UPDATE '.table('mail_accounts').' SET enabled=1 WHERE id=?',[$id]);
    query('UPDATE '.table('mail_account_grants').' SET issued_at=? WHERE account_id=? AND user_id=1',[time()+3600,$id]);mc_deny('Future assignment cannot test credentials early',fn()=>mc_check($id));mc_grant($id);
    query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=1',[$id]);mc_deny('Revoked assignment prevents all connection checks',fn()=>mc_check($id));mc_grant($id);
    query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');mc_deny('Disabled staff identity prevents all connection checks',fn()=>mc_check($id));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');mc_actor();
    query('UPDATE '.table('staff_profiles')." SET role='administrator',permissions=? WHERE user_id=1",[json_encode(['mail.read','mail.send'])]);mc_deny('Changed owner role is refreshed before checking',fn()=>mc_check($id));query('UPDATE '.table('staff_profiles')." SET role='owner',permissions='[]' WHERE user_id=1");
    mc_deny('Wrong current password prevents DNS and transport',fn()=>mail_connections_check($id,1,'imap','wrong','mc_resolve','mc_transport'));
    mc_deny('Stale account revision prevents DNS and transport',fn()=>mc_check($id,99));
    mc_deny('Unknown protocol is rejected',fn()=>mc_check($id,1,'pop3'));
    mc_ok('No network boundary was called by denied requests',$resolveCalls===0&&$transportCalls===0);
    $_GET['support']='x';mc_deny('Support query context cannot test mail',fn()=>mc_check($id));unset($_GET['support']);
    $_SESSION=['client_id'=>1];mc_deny('Client-only identity cannot test mail',fn()=>mc_check($id));mc_actor(2);mc_deny('Assigned non-owner cannot run owner diagnostics',fn()=>mc_check($id));mc_actor(3);mc_deny('Another owner gains no automatic account assignment',fn()=>mc_check($id));mc_actor();
    $oldSession=$_SESSION;$_SESSION['auth_version']='old';mc_deny('Revoked staff session cannot test mail',fn()=>mc_check($id));$_SESSION=$oldSession;
    mc_ok('Started audit rollback prevents all resolver activity',mc_audit_failure(fn()=>mc_check($id))&&$resolveCalls===0&&$transportCalls===0&&!mail_connections_history($id));
    mc_ok('Successful incoming diagnostic records actual injected outcome',mc_check($id)==='ok'&&$resolveCalls===1&&$transportCalls===1);
    $history=mail_connections_history($id);mc_ok('Check history binds current account revision and actor',$history[0]['account_revision']===1&&$history[0]['actor_id']===1&&$history[0]['target']==='imap'&&$history[0]['result_code']==='ok');
    mc_deny('Immediate repeat is rate limited before resolving',fn()=>mc_check($id));mc_ok('Repeat check makes no transport call',$transportCalls===1);
    mc_ok('Outgoing has its own explicit diagnostic',mc_check($id,1,'smtp')==='ok'&&$transportCalls===2);
    $mode='tls';mc_age();mc_ok('Finite TLS failure is retained honestly',mc_check($id)==='tls'&&mail_connections_history($id)[0]['outcome']==='failed');$mode='throw';mc_age();mc_ok('Unknown transport exceptions never expose remote details',mc_check($id)==='connection'&&!str_contains(json_encode(mail_connections_history($id)),'private password'));$mode='ok';
    mc_age();$before=$transportCalls;
    mc_ok('Mixed public and private resolver answers are refused',mail_connections_check($id,1,'imap',$password,fn($h)=>['8.8.8.8','127.0.0.1'],'mc_transport')==='destination'&&$transportCalls===$before);
    mc_age();mc_ok('Resolver exception is sanitized',mail_connections_check($id,1,'imap',$password,fn($h)=>throw new RuntimeException('private provider details'),'mc_transport')==='connection'&&$transportCalls===$before);
    mc_age();$cipher=mail_accounts_load($id)['credentials'];query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',['damaged synthetic ciphertext',$id]);
    mc_ok('Damaged credential envelope never reaches transport',mc_check($id)==='credential'&&$transportCalls===$before);query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',[$cipher,$id]);
    mc_age();$mode='expire';mc_ok('Expiry immediately before credentials stops authentication',mc_check($id)==='access_changed');$mode='ok';mc_grant($id);
    mc_age();$before=$transportCalls;
    mc_ok('Assignment revoked while resolving stops before transport',mail_connections_check($id,1,'imap',$password,function($host)use($id){query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=1',[$id]);return ['8.8.8.8'];},'mc_transport')==='access_changed'&&$transportCalls===$before);mc_grant($id);mc_age();
    mc_ok('Settings changed during DNS reject old credentials',mail_connections_check($id,1,'imap',$password,function($host)use($id,$password){mail_accounts_save($id,1,mc_input(),true,[],$password);return ['8.8.8.8'];},'mc_transport')==='access_changed'&&$transportCalls===$before);
    mc_age();mc_ok('Current settings receive a separately versioned result',mc_check($id,2)==='ok'&&mail_connections_history($id)[0]['account_revision']===2);
    mc_age();mc_ok('Final audit rollback leaves a truthful incomplete attempt',mc_audit_failure(fn()=>mc_check($id,2),'check_finished')&&mail_connections_history($id)[0]['outcome']===null);
    $record=mail_connections_history($id)[0];mail_connections_record($record,'connection',1);
    try{mail_connections_record($record,'ok',2);$duplicate=false;}catch(PDOException){$duplicate=true;}mc_ok('One check can receive only one final result',$duplicate&&one('SELECT outcome FROM '.table('mail_connection_results').' WHERE id=?',[$record['id']])['outcome']==='failed');
    mc_age();query('UPDATE '.table('mail_connection_checks').' SET started_epoch=?',[time()-100]);$count=(int)query('SELECT COUNT(*) FROM '.table('mail_connection_checks'))->fetchColumn();
    for($i=$count;$i<20;$i++)query('INSERT INTO '.table('mail_connection_checks').' (id,account_id,account_revision,target,actor_id,started_at,started_epoch) VALUES (?,?,2,?,1,?,?)',[bin2hex(random_bytes(16)),$id,'imap',gmdate('Y-m-d H:i:s'),time()-100]);
    mc_deny('Owner hourly check limit is enforced',fn()=>mc_check($id,2));mc_age();
    $race=mail_accounts_save('',0,mc_input(),true,['imap_password'=>'Synthetic private password','smtp_password'=>'Synthetic private password'],$password);mc_grant($race);
    $lock='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);query('SELECT GET_LOCK(?,5)',[$lock]);$workers=[];
    try{for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'check-worker',$race],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$proc,$pipes];mc_ok('Concurrent check worker ready '.($i+1),trim(fgets($pipes[1]))==='ready');}}finally{query('SELECT RELEASE_LOCK(?)',[$lock]);}
    $out=[];foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);mc_ok('Concurrent check worker finishes '.count($out),proc_close($proc)===0&&$err==='');}sort($out);
    mc_ok('Simultaneous checks authenticate once and reject the repeat',$out===['cooldown','ok']&&count(mail_connections_history($race))===1);
    foreach(['revoke','edit'] as $change) {
        mc_age();$interleave=mail_accounts_save('',0,mc_input(),true,['imap_password'=>'Synthetic private password','smtp_password'=>'Synthetic private password'],$password);mc_grant($interleave);
        $pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'pause-dns-worker',$interleave],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        mc_ok('Separate process reaches committed intent before '.$change,trim(fgets($pipes[1]))==='resolving'&&count(mail_connections_history($interleave))===1);
        if($change==='revoke'){$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=1',[$interleave]);mail_accounts_grant($interleave,1,(int)$g['revision'],true,true,false,0,$password);}
        else mail_accounts_save($interleave,1,mc_input(),true,[],$password);
        fwrite($pipes[0],"continue\n");fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
        mc_ok('Concurrent '.$change.' finishes while DNS waits and prevents credential transport',$exit===0&&$err===''&&$out==='access_changed:0'&&mail_connections_history($interleave)[0]['result_code']==='access_changed');
    }
    mc_age();$count=(int)query('SELECT COUNT(*) FROM '.table('mail_connection_checks').' WHERE account_id=?',[$id])->fetchColumn();
    for($i=$count;$i<24;$i++)query('INSERT INTO '.table('mail_connection_checks').' (id,account_id,account_revision,target,actor_id,started_at,started_epoch) VALUES (?,?,2,?,1,?,?)',[bin2hex(random_bytes(16)),$id,'imap',gmdate('Y-m-d H:i:s'),time()-7200-$i]);
    $a=mail_connections_history($id);$b=mail_connections_history($id,2);mc_ok('History has distinct pages with a sentinel',count($a)===21&&count($b)===4&&!array_intersect(array_column(array_slice($a,0,20),'id'),array_column($b,'id')));
    mc_ok('History and audit contain no private credential or server error text',!str_contains(json_encode([all('SELECT * FROM '.table('mail_connection_checks')),all('SELECT * FROM '.table('mail_connection_results')),all('SELECT * FROM '.table('audit'))]),'private password'));
    mc_ok('Existing system email configuration is unchanged',setting('email_mode')==='disabled'&&setting('smtp_host')==='');
    mc_ok('Diagnostic actions use owner-only routing',staff_action_permission('mail_accounts_check')==='staff.manage'&&staff_action_permission('mail_accounts_connections_setup')==='staff.manage');
} catch(Throwable $e) {if(db()->inTransaction())db()->rollBack();$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e."\n");}
$pass=!array_filter($checks,fn($v)=>!$v['passed']);file_put_contents(__DIR__.'/../qa/mail-connections-native-results.json',json_encode(['passed'=>$pass,'scope'=>'Native database and injected resolver/transport; actual socket/TLS suite is separate','checks'=>$checks],JSON_PRETTY_PRINT)."\n");echo count($checks).' native connection checks: '.($pass?'PASS':'FAIL')."\n";exit($pass?0:1);
