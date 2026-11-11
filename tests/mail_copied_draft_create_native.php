<?php
/** Disposable SQL integration for exact mailbox-read to protected draft creation. No real network or sending. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/mail/copied_drafts.php';

$private=__DIR__.'/../test-runtime/copied-draft-create-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-copied-draft-key-',3),'db'=>[
    'host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'cdcci_',
]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();

$checks=[];$password='Synthetic copied draft owner passphrase';$resolveCalls=0;$transportCalls=0;$transportMode='normal';$sourceAccount='';$senderAccount='';
function cdc_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function cdc_deny(string $name,callable $operation,string $contains=''):void {
    try{$operation();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;cdc_ok($name,$contains===''||str_contains($e->getMessage(),$contains));return;}cdc_ok($name,false);
}
function cdc_actor(int $id=1):void {$user=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$user['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function cdc_input(string $label,string $address):array {return ['label'=>$label,'address'=>$address,'imap_host'=>'imap.example.invalid','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic-'.$label,'smtp_host'=>'smtp.example.invalid','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic-'.$label];}
function cdc_account(string $label,string $address):string {global $password;return mail_accounts_save('',0,cdc_input($label,$address),true,['imap_password'=>'SYNTHETIC_PRIVATE_INCOMING','smtp_password'=>'SYNTHETIC_PRIVATE_OUTGOING'],$password);}
function cdc_grant(string $account,int $user,bool $read,bool $send,bool $enabled=true):void {
    global $password;$grant=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=?',[$account,$user]);
    mail_accounts_grant($account,$user,(int)($grant['revision']??0),$read,$send,$enabled,0,$password);
}
function cdc_raw(string $kind='normal'):string {
    $from=$kind==='unsupported'?'':"From: Source Person <source@example.invalid>\r\n";
    return "Subject: Synthetic copied message\r\n".$from."To: sender@example.invalid, visible@example.invalid\r\nBcc: RAW_HIDDEN_MARKER@example.invalid\r\nX-Private: RAW_HIDDEN_MARKER\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=outer\r\n\r\n--outer\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nVisible copied body.\r\n--outer\r\nContent-Type: application/octet-stream; name=RAW_ATTACHMENT_MARKER.bin\r\nContent-Disposition: attachment; filename=RAW_ATTACHMENT_MARKER.bin\r\nContent-Transfer-Encoding: base64\r\n\r\nUkFXX0FUVEFDSE1FTlRfQllURVM=\r\n--outer--\r\n";
}
function cdc_resolver(string $host):array {
    global $resolveCalls,$transportMode,$sourceAccount,$senderAccount;$resolveCalls++;if(db()->inTransaction()||$host!=='imap.example.invalid')throw new LogicException('Resolver boundary changed.');
    // DNS runs after the committed intent and outside the staff mutex. Use the
    // real owner mutation API here so revocation follows the production lock.
    if(in_array($transportMode,['revoke_source','revoke_sender'],true)){
        cdc_actor();cdc_grant($transportMode==='revoke_source'?$sourceAccount:$senderAccount,2,$transportMode==='revoke_source',false,false);cdc_actor(2);
    }
    return ['8.8.8.8'];
}
function cdc_transport(array $row,#[SensitiveParameter] array $secrets,string $address,string $validity,string $uid,callable $authorize):array {
    global $transportCalls,$transportMode,$sourceAccount,$senderAccount;$transportCalls++;
    if(db()->inTransaction()||$address!=='8.8.8.8'||$secrets['imap_password']!=='SYNTHETIC_PRIVATE_INCOMING')throw new LogicException('Transport boundary changed.');
    $authorize();
    $raw=cdc_raw($transportMode==='unsupported'?'unsupported':'normal');
    return ['folder'=>'INBOX','uidvalidity'=>$validity,'uid'=>$uid,'seen'=>false,'bytes'=>(string)strlen($raw),'raw'=>$raw];
}
function cdc_age():void {query('UPDATE '.table('mail_message_reads').' SET started_epoch=?',[time()-7200]);}
function cdc_counts():array {return [
    'reads'=>(int)query('SELECT COUNT(*) FROM '.table('mail_message_reads'))->fetchColumn(),
    'results'=>(int)query('SELECT COUNT(*) FROM '.table('mail_message_results'))->fetchColumn(),
    'drafts'=>(int)query('SELECT COUNT(*) FROM '.table('mail_drafts'))->fetchColumn(),
    'protected'=>(int)query('SELECT COUNT(*) FROM '.table('mail_reply_drafts'))->fetchColumn(),
    'revisions'=>(int)query('SELECT COUNT(*) FROM '.table('mail_draft_revisions'))->fetchColumn(),
];}
function cdc_tables():string {return json_encode([
    all('SELECT * FROM '.table('mail_message_reads').' ORDER BY id'),all('SELECT * FROM '.table('mail_message_results').' ORDER BY id'),
    all('SELECT * FROM '.table('mail_drafts').' ORDER BY id'),all('SELECT * FROM '.table('mail_reply_drafts').' ORDER BY id'),
    all('SELECT * FROM '.table('mail_draft_revisions').' ORDER BY id'),all('SELECT * FROM '.table('audit').' ORDER BY id'),
],JSON_THROW_ON_ERROR);}
function cdc_audit_failure(string $action,callable $operation):bool {
    db()->exec('CREATE TRIGGER cdcci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic copied-draft audit failure'; END IF; END");
    try{$operation();$failed=false;}catch(PDOException $e){if((string)$e->getCode()!=='45000'||!str_contains($e->getMessage(),'Synthetic copied-draft audit failure'))throw $e;$failed=true;}finally{db()->exec('DROP TRIGGER cdcci_fail');}return $failed;
}
function cdc_php_command():array {
    $command=[PHP_BINARY];
    if(PHP_OS_FAMILY==='Windows'){
        $extensionDir=(string)ini_get('extension_dir');if($extensionDir===''||!is_dir($extensionDir))throw new RuntimeException('Windows fixture extension directory is unavailable.');
        $command=[PHP_BINARY,'-n','-d','extension_dir='.$extensionDir,'-d','extension=php_openssl.dll','-d','extension=php_pdo_mysql.dll','-d','extension=php_mbstring.dll'];
    }
    return $command;
}
function cdc_worker(string $draft,string $source,string $sender):array {
    $process=proc_open([...cdc_php_command(),__FILE__,'copy-worker',$draft,$source,$sender],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start copied-draft worker.');foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],20);return [$process,$pipes];
}
function cdc_finish(array $worker):string {[$process,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0||$error!=='')throw new RuntimeException('Copied-draft worker failed: '.$error);return $out;}

if(($argv[1]??'')==='copy-worker'){
    cdc_actor(2);$draft=$argv[2];$sourceAccount=$argv[3];$senderAccount=$argv[4];echo "ready\n";fflush(STDOUT);
    try{$result=mail_copied_draft_create($draft,$sourceAccount,1,'9','44','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport');echo $result['state'].':'.$resolveCalls.':'.$transportCalls;}
    catch(RuntimeException $e){if($e instanceof PDOException||!str_contains($e->getMessage(),'2 seconds'))throw $e;echo 'cooldown:'.$resolveCalls.':'.$transportCalls;}exit;
}

try {
    foreach(schema_statements('cdcci_') as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Copied draft staff '.$i,'copied'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (2,\'administrator\',1,?)',[json_encode(['mail.read','mail.send'])]);
    query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (3,\'administrator\',1,?)',[json_encode(['mail.read'])]);
    cdc_actor();mail_accounts_migrate($password);mail_messages_migrate($password);mail_drafts_migrate($password);mail_replies_migrate($password);
    $sourceAccount=cdc_account('Source account','source-mailbox@example.invalid');$senderAccount=cdc_account('Sender account','sender@example.invalid');
    cdc_grant($sourceAccount,2,true,false);cdc_grant($senderAccount,2,false,true);cdc_actor(2);

    $draft=bin2hex(random_bytes(16));$created=mail_copied_draft_create($draft,$sourceAccount,1,'9','44','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport');$row=mail_reply_drafts_get($draft);$counts=cdc_counts();
    cdc_ok('One exact read creates one protected derived draft',$created['state']==='created'&&$created['draft_id']===$draft&&$resolveCalls===1&&$transportCalls===1&&$counts===['reads'=>1,'results'=>1,'drafts'=>1,'protected'=>1,'revisions'=>1]);
    cdc_ok('Created response exposes sanitized content but no raw source channel',!array_key_exists('raw',$created['result']['content'])&&!array_key_exists('source',$created)&&!str_contains(json_encode($created,JSON_THROW_ON_ERROR),'RAW_HIDDEN_MARKER')&&!str_contains(json_encode($created,JSON_THROW_ON_ERROR),'RAW_ATTACHMENT_MARKER'));
    cdc_ok('Created draft contains bounded visible content and a content-free legacy placeholder',$row['origin']['mode']==='reply'&&str_contains($row['body'],'Visible copied body.')&&!str_contains(json_encode($row),'RAW_HIDDEN_MARKER')&&!str_contains(json_encode($row),'RAW_ATTACHMENT_MARKER')&&one('SELECT subject,body,to_json,cc_json,bcc_json,status FROM '.table('mail_drafts').' WHERE id=?',[$draft])===['subject'=>'Copied message draft','body'=>'','to_json'=>'[]','cc_json'=>'[]','bcc_json'=>'[]','status'=>'source']);

    $before=cdc_tables();$beforeCalls=[$resolveCalls,$transportCalls];$replay=mail_copied_draft_create($draft,$sourceAccount,1,'9','44','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport');
    cdc_ok('Exact replay returns before network journal or mutation',$replay['state']==='existing'&&[$resolveCalls,$transportCalls]===$beforeCalls&&cdc_tables()===$before);
    cdc_deny('Conflicting replay is refused before network and preserves storage',fn()=>mail_copied_draft_create($draft,$sourceAccount,1,'9','44','forward',$senderAccount,1,null,'cdc_resolver','cdc_transport'));
    cdc_ok('Conflicting replay performs no network or write',[$resolveCalls,$transportCalls]===$beforeCalls&&cdc_tables()===$before);

    cdc_age();$ordinary=mail_message_read($sourceAccount,1,'10','45','cdc_resolver','cdc_transport');$cooldownDraft=bin2hex(random_bytes(16));$cooldownCalls=[$resolveCalls,$transportCalls];$cooldownCounts=cdc_counts();
    cdc_deny('Ordinary load followed immediately by copy retains the two-second pre-DNS gate',fn()=>mail_copied_draft_create($cooldownDraft,$sourceAccount,1,'10','45','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport'),'2 seconds');
    cdc_ok('Cooldown denial creates no second intent or draft',$ordinary['code']==='ok'&&[$resolveCalls,$transportCalls]===$cooldownCalls&&cdc_counts()===$cooldownCounts);

    cdc_age();$transportMode='unsupported';$unsupported=bin2hex(random_bytes(16));$prior=cdc_counts();
    cdc_deny('Unsupported reply metadata raises a finite generic copy error',fn()=>mail_copied_draft_create($unsupported,$sourceAccount,1,'11','46','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport'),'cannot be copied');$transportMode='normal';
    $latest=one('SELECT r.outcome,r.result_code,r.message_bytes FROM '.table('mail_message_results').' r JOIN '.table('mail_message_reads').' q ON q.id=r.id WHERE q.uidvalidity=\'11\' AND q.uid=\'46\'');
    cdc_ok('Unsupported metadata retains a successful exact read but no draft',$latest['outcome']==='success'&&$latest['result_code']==='ok'&&(int)$latest['message_bytes']===strlen(cdc_raw('unsupported'))&&cdc_counts()['reads']===$prior['reads']+1&&cdc_counts()['drafts']===$prior['drafts']);

    cdc_age();$transportMode='revoke_source';$revokedSource=bin2hex(random_bytes(16));$calls=[$resolveCalls,$transportCalls];$failed=mail_copied_draft_create($revokedSource,$sourceAccount,1,'12','47','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport');$transportMode='normal';
    cdc_ok('Source Read revoked during DNS records access changed before transport and creates nothing',$failed['state']==='read_failed'&&$failed['result']['code']==='access_changed'&&$resolveCalls===$calls[0]+1&&$transportCalls===$calls[1]&&!one('SELECT id FROM '.table('mail_drafts').' WHERE id=?',[$revokedSource]));
    cdc_actor();cdc_grant($sourceAccount,2,true,false);cdc_actor(2);

    cdc_age();$transportMode='revoke_sender';$revokedSender=bin2hex(random_bytes(16));$prior=cdc_counts();$calls=[$resolveCalls,$transportCalls];
    cdc_deny('Sender Send revoked during the read blocks protected persistence',fn()=>mail_copied_draft_create($revokedSender,$sourceAccount,1,'13','48','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport'),'Send');$transportMode='normal';
    $senderJournal=one('SELECT r.outcome,r.result_code FROM '.table('mail_message_results').' r JOIN '.table('mail_message_reads').' q ON q.id=r.id WHERE q.uidvalidity=\'13\' AND q.uid=\'48\'');
    cdc_ok('Sender revocation preserves one successful source read and no draft',$senderJournal===['outcome'=>'success','result_code'=>'ok']&&$resolveCalls===$calls[0]+1&&$transportCalls===$calls[1]+1&&cdc_counts()['reads']===$prior['reads']+1&&cdc_counts()['drafts']===$prior['drafts']);
    cdc_actor();cdc_grant($senderAccount,2,false,true);cdc_actor(2);

    $sendIds=[$senderAccount];$readOnly=[];cdc_actor();
    for($i=0;$i<21;$i++){$id=cdc_account(sprintf('Send %02d',$i),sprintf('send%02d@example.invalid',$i));cdc_grant($id,2,false,true);$sendIds[]=$id;}
    for($i=0;$i<5;$i++){$id=cdc_account(sprintf('Read %02d',$i),sprintf('read%02d@example.invalid',$i));cdc_grant($id,2,true,false);$readOnly[]=$id;}
    cdc_actor(2);$sendPage1=mail_drafts_senders(1);$sendPage2=mail_drafts_senders(2);$page1Ids=array_column($sendPage1,'id');$page2Ids=array_column($sendPage2,'id');
    cdc_ok('Send-only filtering occurs before the sender page boundary',count($sendPage1)===21&&count($sendPage2)===2&&!array_intersect($readOnly,[...$page1Ids,...$page2Ids])&&!array_intersect(array_slice($page1Ids,0,20),$page2Ids)&&count(array_unique([...$page1Ids,...$page2Ids]))===22);

    $stored=cdc_tables();cdc_ok('Journals protected storage and audit retain no raw hidden attachment or credential marker',!str_contains($stored,'RAW_HIDDEN_MARKER')&&!str_contains($stored,'RAW_ATTACHMENT_MARKER')&&!str_contains($stored,'RAW_ATTACHMENT_BYTES')&&!str_contains($stored,'SYNTHETIC_PRIVATE_INCOMING'));

    cdc_age();$failedFinished=bin2hex(random_bytes(16));$prior=cdc_counts();
    cdc_ok('Terminal-read audit failure exposes no transient source or draft',cdc_audit_failure('mail_accounts.message_finished',fn()=>mail_copied_draft_create($failedFinished,$sourceAccount,1,'14','49','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport'))&&cdc_counts()['reads']===$prior['reads']+1&&cdc_counts()['results']===$prior['results']&&cdc_counts()['drafts']===$prior['drafts']);
    cdc_age();$failedDraft=bin2hex(random_bytes(16));$prior=cdc_counts();
    cdc_ok('Draft audit failure rolls back protected writes but preserves the committed successful read',cdc_audit_failure('mail_drafts.reply',fn()=>mail_copied_draft_create($failedDraft,$sourceAccount,1,'15','50','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport'))&&cdc_counts()['reads']===$prior['reads']+1&&cdc_counts()['results']===$prior['results']+1&&cdc_counts()['drafts']===$prior['drafts']&&cdc_counts()['protected']===$prior['protected']&&cdc_counts()['revisions']===$prior['revisions']);

    cdc_age();$raceDraft=bin2hex(random_bytes(16));$prior=cdc_counts();$lock='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);$workers=[];
    if((int)query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)throw new RuntimeException('Cannot acquire copied-draft race barrier.');
    try{for($i=0;$i<2;$i++){$workers[]=cdc_worker($raceDraft,$sourceAccount,$senderAccount);cdc_ok('Duplicate copy worker reaches the shared authority boundary '.($i+1),trim(fgets($workers[$i][1][1]))==='ready');}}
    finally{if((int)query('SELECT RELEASE_LOCK(?)',[$lock])->fetchColumn()!==1)throw new RuntimeException('Cannot release copied-draft race barrier.');}
    $out=array_map('cdc_finish',$workers);sort($out);$finite=$out===['cooldown:0:0','created:1:1']||$out===['created:1:1','existing:0:0'];$after=cdc_counts();
    cdc_ok('Simultaneous duplicate creates one journal and one protected row with finite replay or cooldown',$finite&&$after['reads']===$prior['reads']+1&&$after['results']===$prior['results']+1&&$after['drafts']===$prior['drafts']+1&&$after['protected']===$prior['protected']+1&&$after['revisions']===$prior['revisions']+1);
    $raceCalls=[$resolveCalls,$transportCalls];$raceReplay=mail_copied_draft_create($raceDraft,$sourceAccount,1,'9','44','reply',$senderAccount,1,null,'cdc_resolver','cdc_transport');
    cdc_ok('Sequential retry after the duplicate race resolves existing without network',$raceReplay['state']==='existing'&&[$resolveCalls,$transportCalls]===$raceCalls);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e.PHP_EOL);}

$passed=!array_filter($checks,fn($check)=>!$check['passed']);
file_put_contents(__DIR__.'/../qa/mail-copied-draft-create-native-results.json',json_encode(['passed'=>$passed,'scope'=>'Disposable native SQL plus injected resolver/raw-MIME transport; no DNS, IMAP socket, SMTP, customer message or live operation','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo count($checks).' native copied-draft creation checks: '.($passed?'PASS':'FAIL')."\n";exit($passed?0:1);
