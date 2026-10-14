<?php
/** Disposable native database checks for local notice storage; no external mail operation. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require_once __DIR__.'/../site/app/mail/notices.php';
$private=__DIR__.'/../test-runtime/notice-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-notice-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'ntci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic notice owner passphrase';
function nt_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function nt_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;nt_ok($name,true);return;}nt_ok($name,false);}
function nt_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function nt_account():string {global $password;return mail_accounts_save('',0,['label'=>'Synthetic notice inbox','address'=>'notice@example.net','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic','smtp_host'=>'smtp.example.net','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic'],true,['imap_password'=>'synthetic notice private password','smtp_password'=>'synthetic outgoing password'],$password);}
function nt_grant(string $id,int $user=1):void {global $password;$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=?',[$id,$user]);mail_accounts_grant($id,$user,(int)($g['revision']??0),true,false,true,0,$password);}
function nt_batch(array $uids=['1','2'],string $epoch='55',bool $seen=false):array {return ['folder'=>'INBOX','uidvalidity'=>$epoch,'total'=>count($uids),'messages'=>array_map(fn($uid)=>['uid'=>$uid,'seen'=>$seen,'bytes'=>'200','headers_truncated'=>false,'subject'=>'SYNTHETIC_PRIVATE_SUBJECT','from'=>'SYNTHETIC_PRIVATE_SENDER','date'=>'Synthetic message date'],$uids)];}
function nt_observe(string $id,array $batch,?callable $handle=null,int $revision=1):void {
    staff_locked(fn()=>staff_transaction(function()use($id,$batch,$handle,$revision):void {
        $actor=mail_notices_actor();$started=['id'=>bin2hex(random_bytes(16)),'account_id'=>$id,'account_revision'=>$revision,'actor_id'=>(int)$actor['id'],'started_at'=>gmdate('Y-m-d H:i:s'),'started_epoch'=>time()];
        query('INSERT INTO '.table('mail_inbox_reads').' (id,account_id,account_revision,actor_id,started_at,started_epoch) VALUES (?,?,?,?,?,?)',array_values($started));
        query('INSERT INTO '.table('mail_inbox_results')." (id,outcome,result_code,finished_at,elapsed_ms,message_count,mailbox_total,uidvalidity) VALUES (?,'success','ok',?,1,?,?,?)",[$started['id'],$started['started_at'],count($batch['messages']),$batch['total'],$batch['uidvalidity']]);
        mail_notices_observe($started,$batch,$handle);mail_accounts_audit((int)$actor['id'],'inbox_finished','Synthetic result '.$started['id']);
    }));
}
function nt_open(array $item,bool $seen=false):void {
    staff_locked(fn()=>staff_transaction(function()use($item,$seen):void {
        $actor=mail_notices_actor();$started=['id'=>bin2hex(random_bytes(16)),'account_id'=>$item['account_id'],'account_revision'=>$item['account_revision'],'actor_id'=>(int)$actor['id'],'folder'=>'INBOX','uidvalidity'=>$item['uidvalidity'],'uid'=>$item['uid'],'started_at'=>gmdate('Y-m-d H:i:s'),'started_epoch'=>time()];
        query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',array_values($started));
        query('INSERT INTO '.table('mail_message_results')." (id,outcome,result_code,finished_at,elapsed_ms,message_bytes) VALUES (?,'success','ok',?,1,200)",[$started['id'],$started['started_at']]);
        mail_notices_opened($started,['folder'=>'INBOX','uidvalidity'=>$item['uidvalidity'],'uid'=>$item['uid'],'seen'=>$seen]);mail_accounts_audit((int)$actor['id'],'message_finished','Synthetic result '.$started['id']);
    }));
}
function nt_snapshot():string {return json_encode([all('SELECT * FROM '.table('mail_notice_windows')),all('SELECT * FROM '.table('mail_notice_items')),all('SELECT * FROM '.table('mail_notice_states'))]);}
function nt_audit_failure(callable $fn,string $action):bool {
    db()->exec('CREATE TRIGGER ntci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='mail_accounts.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic notice audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER ntci_fail');}return $failed;
}
function nt_age():void {
    foreach(['mail_inbox_reads','mail_message_reads'] as $table)query('UPDATE '.table($table).' SET started_epoch=?',[time()-7200]);
}
function nt_resolve(string $host):array {
    if(db()->inTransaction())throw new LogicException('Notice DNS must be outside a transaction');
    return ['8.8.8.8'];
}
function nt_headers(array $row,#[SensitiveParameter] array $secrets,string $address,callable $authorize):array {
    if(db()->inTransaction()||$address!=='8.8.8.8'||$secrets['imap_password']!=='synthetic notice private password')throw new LogicException('Notice header boundary');
    $authorize();return nt_batch(['81','82']);
}
$bodyCalls=0;
function nt_body(array $row,#[SensitiveParameter] array $secrets,string $address,string $epoch,string $uid,callable $authorize):array {
    global $bodyCalls;$bodyCalls++;
    if(db()->inTransaction()||$address!=='8.8.8.8'||$secrets['imap_password']!=='synthetic notice private password')throw new LogicException('Notice message boundary');
    $authorize();$raw="Subject: SYNTHETIC_PRIVATE_SUBJECT\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSYNTHETIC_PRIVATE_BODY";
    return ['folder'=>'INBOX','uidvalidity'=>$epoch,'uid'=>$uid,'seen'=>false,'bytes'=>(string)strlen($raw),'raw'=>$raw];
}
function nt_read(array $item,?callable $resolver=null):array {
    return mail_message_read($item['account_id'],(int)$item['account_revision'],$item['uidvalidity'],$item['uid'],$resolver??'nt_resolve','nt_body',$item['handle']);
}
function nt_worker(string $mode,array $item):array {
    $process=proc_open([PHP_BINARY,__FILE__,$mode,base64_encode(json_encode($item))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start notice fixture process');
    foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);
    return [$process,$pipes];
}
function nt_finish(array $worker):string {
    [$process,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Notice worker failed: '.$error);
    return $out;
}
if(in_array($argv[1]??'',['pause-notice-dns','dismiss-notice-worker'],true)) {
    nt_actor();$item=json_decode(base64_decode($argv[2]),true,512,JSON_THROW_ON_ERROR);
    if($argv[1]==='pause-notice-dns') {
        $result=nt_read($item,function($host){echo "resolving\n";fflush(STDOUT);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Notice DNS sync');return nt_resolve($host);});
        echo $result['code'].':'.$bodyCalls;
    }else {
        echo "ready\n";fflush(STDOUT);try{mail_notices_dismiss($item['handle'],$item);echo 'dismissed';}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo 'unavailable';}
    }
    exit;
}
try {
    foreach(schema_statements('ntci_') as $name=>$sql)if(!str_starts_with($name,'mail_notice_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=4;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Notice staff '.$i,'notice'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>['mail.read'],3=>['mail.send'],4=>[]] as $user=>$permissions)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$user,$user===4?'owner':'administrator',json_encode($permissions)]);
    save_setting('mail_accounts_schema_version','1');save_setting('mail_inbox_schema_version','1');save_setting('mail_messages_schema_version','1');nt_actor();$id=nt_account();nt_grant($id);nt_grant($id,2);nt_grant($id,3);
    nt_deny('Setup requires current owner password',fn()=>mail_notices_migrate('wrong'));
    nt_actor(2);nt_deny('Mail reader cannot prepare storage',fn()=>mail_notices_migrate($password));nt_actor();
    nt_deny('Count refuses unprepared storage',fn()=>mail_notices_count());
    nt_ok('Strict setup audit rolls back readiness',nt_audit_failure(fn()=>mail_notices_migrate($password),'notice_setup')&&!mail_notices_ready());
    db()->exec('ALTER TABLE '.table('mail_notice_items').' ADD INDEX notice_fixture_account(account_id), DROP INDEX message_identity');nt_deny('Migration refuses missing full identity uniqueness',fn()=>mail_notices_migrate($password));db()->exec('ALTER TABLE '.table('mail_notice_items').' ADD UNIQUE message_identity(account_id,account_revision,folder,uidvalidity,uid), DROP INDEX notice_fixture_account');
    db()->exec('ALTER TABLE '.table('mail_notice_items').' MODIFY uid VARCHAR(10) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');nt_deny('Migration refuses nonbinary UID collation',fn()=>mail_notices_migrate($password));db()->exec('ALTER TABLE '.table('mail_notice_items').' MODIFY uid VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    mail_notices_migrate($password);mail_notices_migrate($password);nt_ok('Setup is additive idempotent and empty',mail_notices_ready()&&mail_notices_count()===0&&mail_notices_feed()===[]);
    nt_ok('Never-observed account is distinguished from checked empty',mail_notices_accounts()[0]['checked_at']===null);
    nt_observe($id,nt_batch());$items=mail_notices_feed();nt_ok('Complete window exposes two recent unseen notices',count($items)===2&&mail_notices_count()===2&&$items[0]['checked_at']!==null);
    nt_actor(2);nt_ok('Mail-only staff can see notices without client-workspace permission',mail_notices_count()===2&&!staff_can('clients.manage'));
    mail_notices_dismiss($items[0]['handle'],$items[0]);nt_ok('Dismiss affects only this staff member',mail_notices_count()===1);nt_actor();nt_ok('Another staff member retains both notices',mail_notices_count()===2);
    nt_actor(2);nt_open($items[1]);nt_ok('Local opened state excludes this person only',mail_notices_count()===0);nt_actor();nt_ok('BODY.PEEK local opening does not invent shared server Seen',mail_notices_count()===2&&array_sum(array_column(all('SELECT server_seen FROM '.table('mail_notice_items')),'server_seen'))===0);
    $snapshot=nt_snapshot();nt_ok('Audit failure rolls back local dismissal',nt_audit_failure(fn()=>mail_notices_dismiss($items[0]['handle'],$items[0]),'notice_dismissed')&&nt_snapshot()===$snapshot);
    nt_observe($id,nt_batch());nt_ok('Refresh reuses unchanged handles',array_column(mail_notices_feed(),'handle')===array_column($items,'handle'));nt_actor(2);nt_ok('Refresh preserves independent opened/dismissed state',mail_notices_count()===0);nt_actor();
    $wrong=$items[0];$wrong['uid']='99';nt_deny('Opaque handle with mismatched independent tuple is denied',fn()=>mail_notices_resolve($items[0]['handle'],$wrong));
    $before=nt_snapshot();$results=(int)query('SELECT COUNT(*) FROM '.table('mail_inbox_results'))->fetchColumn();nt_deny('Injected handle collision refuses a different full identity',fn()=>nt_observe($id,nt_batch(['3']),fn()=>$items[0]['handle']));nt_ok('Collision rolls back the entire window',nt_snapshot()===$before);
    nt_ok('Collision cannot commit a successful terminal result',(int)query('SELECT COUNT(*) FROM '.table('mail_inbox_results'))->fetchColumn()===$results);
    nt_ok('Strict terminal audit rolls back observation replacement',nt_audit_failure(fn()=>nt_observe($id,nt_batch(['3'])),'inbox_finished')&&nt_snapshot()===$before);
    nt_deny('An oversized header window is rejected before mutation',fn()=>nt_observe($id,nt_batch(array_map('strval',range(1,21)))));nt_ok('Oversized rejection preserves prior window',nt_snapshot()===$before);
    nt_observe($id,nt_batch(['2','3']));$new=mail_notices_feed();nt_ok('Eviction retains matching tuple and removes absent local states',$new[0]['handle']===$items[1]['handle']&&!one('SELECT handle FROM '.table('mail_notice_states').' WHERE handle=?',[$items[0]['handle']]));
    nt_deny('Evicted notice cannot resolve',fn()=>mail_notices_resolve($items[0]['handle'],$items[0]));$before=nt_snapshot();nt_open($items[0]);nt_ok('Opening a message outside the window does not repopulate notices',nt_snapshot()===$before);
    nt_open($new[0],true);nt_ok('Actual returned server Seen is shared separately from local opened state',mail_notices_count()===1);nt_actor(2);nt_ok('Other staff sees the actual returned server flag',mail_notices_count()===1);nt_actor();
    nt_observe($id,nt_batch(['2','3'],'56'));nt_ok('UIDVALIDITY rollover produces new handles',!array_intersect(array_column(mail_notices_feed(),'handle'),array_column($new,'handle')));nt_deny('Old epoch link cannot resolve',fn()=>mail_notices_resolve($new[0]['handle'],$new[0]));
    nt_observe($id,nt_batch([],'56'));nt_ok('Successful empty window clears items and local state',mail_notices_feed()===[]&&!all('SELECT * FROM '.table('mail_notice_states'))&&mail_notices_accounts()[0]['checked_at']!==null);
    nt_observe($id,nt_batch());$items=mail_notices_feed();
    foreach(['disabled','future','expired','revoked','credentials'] as $mode) {
        if($mode==='disabled')query('UPDATE '.table('mail_accounts').' SET enabled=0 WHERE id=?',[$id]);
        elseif($mode==='credentials'){$credentials=mail_accounts_load($id)['credentials'];query('UPDATE '.table('mail_accounts')." SET credentials='' WHERE id=?",[$id]);}
        elseif($mode==='future')query('UPDATE '.table('mail_account_grants').' SET issued_at=? WHERE account_id=? AND user_id=1',[time()+3600,$id]);
        elseif($mode==='expired')query('UPDATE '.table('mail_account_grants').' SET expires_at=? WHERE account_id=? AND user_id=1',[time()-1,$id]);
        else query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=1',[$id]);
        nt_ok($mode.' authority excludes counts labels and account metadata',mail_notices_count()===0&&mail_notices_feed()===[]&&mail_notices_accounts()===[]);
        nt_deny($mode.' authority denies exact notice resolution',fn()=>mail_notices_resolve($items[0]['handle'],$items[0]));
        query('UPDATE '.table('mail_accounts').' SET enabled=1 WHERE id=?',[$id]);if($mode==='credentials')query('UPDATE '.table('mail_accounts').' SET credentials=? WHERE id=?',[$credentials,$id]);nt_grant($id);
    }
    query('UPDATE '.table('mail_accounts').' SET revision=2 WHERE id=?',[$id]);nt_ok('Edited account hides all old revision items',mail_notices_count()===0&&mail_notices_feed()===[]&&mail_notices_accounts()[0]['checked_at']===null);nt_deny('Old revision handle denied',fn()=>mail_notices_resolve($items[0]['handle'],$items[0]));
    nt_observe($id,nt_batch(),null,2);nt_ok('New saved revision receives distinct handles',!array_intersect(array_column(mail_notices_feed(),'handle'),array_column($items,'handle')));
    nt_actor(3);nt_deny('Global send-only staff cannot see read notices',fn()=>mail_notices_count());nt_actor(4);nt_ok('Owner without an assignment sees no account metadata',mail_notices_count()===0&&mail_notices_accounts()===[]);nt_actor();
    $_GET['support']='synthetic';nt_deny('Support context excludes email notices',fn()=>mail_notices_count());unset($_GET['support']);$_SESSION=['client_id'=>1];nt_deny('Client identity excludes email notices',fn()=>mail_notices_count());$_SESSION=[];nt_deny('Anonymous identity excludes email notices',fn()=>mail_notices_count());nt_actor();
    $_SESSION['last_activity']=time()-120;$idle=$_SESSION['last_activity'];mail_notices_passive_count();nt_ok('Passive count does not extend idle expiry',$_SESSION['last_activity']===$idle);
    $_SESSION['last_activity']=time()-1801;nt_deny('Expired session cannot read count',fn()=>mail_notices_passive_count());nt_ok('Passive count does not resurrect expired authentication',!isset($_SESSION['uid']));nt_actor();
    $_SESSION['auth_version']='revoked';nt_deny('Revoked authentication version excludes notices',fn()=>mail_notices_count());nt_actor();
    save_setting('mail_notices_schema_version','0');nt_deny('Unknown nonempty storage cannot be silently adopted',fn()=>mail_notices_migrate($password));save_setting('mail_notices_schema_version','1');
    $stored=nt_snapshot();nt_ok('Notice storage retains no message contents or credentials',!str_contains($stored,'PRIVATE_SUBJECT')&&!str_contains($stored,'PRIVATE_SENDER')&&!str_contains($stored,'private password')&&!str_contains($stored,'Synthetic message date'));
    nt_ok('Notice projection returns no ciphertext or message content',!str_contains(json_encode(mail_notices_feed()),'credentials')&&!str_contains(json_encode(mail_notices_feed()),'PRIVATE_'));
    require __DIR__.'/mail_notices_hook_cases.php';
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e."\n");}
$pass=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/mail-notices-native-results.json',json_encode(['passed'=>$pass,'scope'=>'Native local notice storage, current permissions, actual reader hooks with injected DNS/transport, rollback and separate-process races; no HTTP UI or real mailbox acceptance','checks'=>$checks],JSON_PRETTY_PRINT)."\n");echo count($checks).' native email notice checks: '.($pass?'PASS':'FAIL')."\n";exit($pass?0:1);
