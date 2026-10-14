<?php
/** Disposable database/current-authority fixtures. No SMTP, IMAP or customer data. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/mail/drafts.php';
use CajunWebPro\Foundation\MailCompose\DraftContent;
$private=__DIR__.'/../test-runtime/draft-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-draft-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'drci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic draft owner passphrase';
function dr_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function dr_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;dr_ok($name,true);return;}dr_ok($name,false);}
function dr_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function dr_content(string $subject='SYNTHETIC_PRIVATE_DRAFT_SUBJECT'):DraftContent {return new DraftContent(['client@example.invalid'],['copy@example.invalid'],['private@example.invalid'],$subject,"SYNTHETIC_PRIVATE_DRAFT_BODY\n</textarea><script>untrusted()</script>");}
function dr_account(string $label):string {
    global $password;return mail_accounts_save('',0,['label'=>$label,'address'=>'sender@example.invalid','imap_host'=>'imap.example.invalid','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic','smtp_host'=>'smtp.example.invalid','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic'],true,['imap_password'=>'Synthetic draft incoming','smtp_password'=>'Synthetic draft outgoing'],$password);
}
function dr_grant(string $account,int $user,bool $send=true):void {
    global $password;$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=?',[$account,$user]);mail_accounts_grant($account,$user,(int)($g['revision']??0),true,$send,true,0,$password);
}
function dr_snapshot():string {return json_encode([all('SELECT * FROM '.table('mail_drafts').' ORDER BY id'),all('SELECT * FROM '.table('mail_draft_revisions').' ORDER BY draft_id,revision'),all('SELECT * FROM '.table('audit').' ORDER BY id')],JSON_THROW_ON_ERROR);}
function dr_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER drci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic draft audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER drci_fail');}return $failed;
}
function dr_worker(string $id,string $revision,string $account,string $subject,int $actor=2):array {
    $p=proc_open([PHP_BINARY,__FILE__,'worker',$id,$revision,$account,$subject,(string)$actor],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start draft fixture worker');foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);return [$p,$pipes];
}
function dr_finish(array $worker):string {
    [$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0||$error!=='')throw new RuntimeException('Draft fixture worker failed: '.$error);return $out;
}
if(($argv[1]??'')==='worker') {
    dr_actor((int)$argv[6]);echo "ready\n";fflush(STDOUT);
    try{mail_drafts_save($argv[2],(int)$argv[3],$argv[4],1,dr_content($argv[5]));echo 'saved';}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
try {
    foreach(schema_statements('drci_') as $name=>$sql)if(!in_array($name,['mail_drafts','mail_draft_revisions'],true)&&!str_starts_with($name,'mail_submission'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=5;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Draft staff '.$i,'draft'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>['administrator',['mail.send','mail.templates.manage']],3=>['administrator',['mail.read']],4=>['owner',[]],5=>['administrator',array_keys(staff_permissions())]] as $id=>[$role,$permissions])query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode($permissions)]);
    dr_actor();mail_accounts_migrate($password);mail_templates_migrate($password);$a=dr_account('Draft account A');$b=dr_account('Draft account B');$key=file_get_contents($private.'/mail-master.key');
    $settings=all('SELECT * FROM '.table('settings').' ORDER BY name');
    dr_deny('Draft storage setup requires current owner password',fn()=>mail_drafts_migrate('wrong'));
    dr_actor(5);dr_deny('All administrator grants do not confer owner setup',fn()=>mail_drafts_migrate($password));dr_actor();
    dr_deny('Draft access refuses unprepared storage',fn()=>mail_drafts_list($a));
    save_setting('mail_drafts_schema_version','future');dr_deny('Future draft storage is not downgraded',fn()=>mail_drafts_migrate($password));query('DELETE FROM '.table('settings').' WHERE name=?',['mail_drafts_schema_version']);
    foreach(mail_drafts_schema('drci_') as $sql)db()->exec($sql);
    db()->exec('ALTER TABLE '.table('mail_drafts').' ADD INDEX unexpected_subject(subject)');dr_deny('Unexpected draft index is preserved and refused',fn()=>mail_drafts_migrate($password));db()->exec('ALTER TABLE '.table('mail_drafts').' DROP INDEX unexpected_subject');
    db()->exec('ALTER TABLE '.table('mail_drafts')." ALTER status SET DEFAULT 'draft'");dr_deny('Unexpected draft default is refused',fn()=>mail_drafts_migrate($password));db()->exec('ALTER TABLE '.table('mail_drafts').' ALTER status DROP DEFAULT');
    db()->exec('ALTER TABLE '.table('mail_drafts').' MODIFY create_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');dr_deny('Case-insensitive replay fingerprint is refused',fn()=>mail_drafts_migrate($password));db()->exec('ALTER TABLE '.table('mail_drafts').' MODIFY create_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    $fk=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['drci_mail_drafts','owner_id'])['CONSTRAINT_NAME'];
    db()->exec('ALTER TABLE '.table('mail_drafts').' DROP FOREIGN KEY '.$fk);dr_deny('Missing draft author relationship is refused',fn()=>mail_drafts_migrate($password));db()->exec('ALTER TABLE '.table('mail_drafts').' ADD FOREIGN KEY (owner_id) REFERENCES '.table('users').' (id) ON DELETE CASCADE');dr_deny('Cascading draft author deletion is refused',fn()=>mail_drafts_migrate($password));
    $fk=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',['drci_mail_drafts','owner_id'])['CONSTRAINT_NAME'];db()->exec('ALTER TABLE '.table('mail_drafts').' DROP FOREIGN KEY '.$fk.', ADD FOREIGN KEY (owner_id) REFERENCES '.table('users').' (id) ON DELETE RESTRICT');
    $unknown=str_repeat('a',32);$now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('mail_drafts')." (id,owner_id,account_id,account_revision,revision,status,create_fingerprint,to_json,cc_json,bcc_json,subject,body,created_at,updated_at) VALUES (?,1,?,1,1,'draft',?,'[]','[]','[]','','',?,?)",[$unknown,$a,str_repeat('0',64),$now,$now]);
    dr_deny('Populated unrecognized draft storage is not adopted',fn()=>mail_drafts_migrate($password));dr_ok('Failed adoption retains unknown record and disabled readiness',!mail_drafts_ready()&&one('SELECT id FROM '.table('mail_drafts').' WHERE id=?',[$unknown])!==null);query('DELETE FROM '.table('mail_drafts').' WHERE id=?',[$unknown]);
    dr_ok('Strict setup audit rolls back readiness',dr_audit_failure(fn()=>mail_drafts_migrate($password))&&!mail_drafts_ready());
    mail_drafts_migrate($password);mail_drafts_migrate($password);
    dr_ok('Repeated setup is additive and earlier settings remain exact',mail_drafts_ready()&&all('SELECT * FROM '.table('settings')." WHERE name<>'mail_drafts_schema_version' ORDER BY name")===$settings);
    dr_deny('Owner still requires a sending assignment',fn()=>mail_drafts_list($a));
    dr_grant($a,1,false);dr_deny('Owner Read assignment does not grant Send drafts',fn()=>mail_drafts_list($a));dr_grant($a,1);
    dr_grant($a,2);dr_grant($a,3);dr_grant($a,4);dr_grant($a,5);dr_grant($b,1);
    $ownerDraft=bin2hex(random_bytes(16));mail_drafts_save($ownerDraft,0,$a,1,dr_content('Owner private draft'));
    dr_actor(2);$id=bin2hex(random_bytes(16));mail_drafts_save($id,0,$a,1,dr_content());$row=mail_drafts_get($id);
    dr_ok('Staff saves and reopens own separate To Cc Bcc and literal body',$row['owner_id']===2&&$row['revision']===1&&$row['to']===['client@example.invalid']&&$row['bcc']===['private@example.invalid']&&$row['body']===dr_content()->body);
    dr_ok('Draft listing reveals only this author and omits recipients and body',array_column(mail_drafts_list($a),'id')===[$id]&&!array_key_exists('body',mail_drafts_list($a)[0])&&!array_key_exists('bcc',mail_drafts_list($a)[0]));
    dr_ok('Draft projection omits private replay fingerprint',!array_key_exists('create_fingerprint',$row));
    $before=dr_snapshot();dr_ok('Exact create replay returns existing id without writes',mail_drafts_save($id,0,$a,1,dr_content())===$id&&dr_snapshot()===$before);
    dr_deny('Create token cannot replay with changed content',fn()=>mail_drafts_save($id,0,$a,1,dr_content('Changed replay')));dr_ok('Rejected create replay changes no record',dr_snapshot()===$before);
    dr_deny('Draft creation rejects an unexpected nonzero revision',fn()=>mail_drafts_save(bin2hex(random_bytes(16)),1,$a,1,dr_content()));
    dr_deny('Writer cannot reuse another authors draft id',fn()=>mail_drafts_save($ownerDraft,0,$a,1,dr_content()));
    dr_deny('Writer cannot read another authors draft',fn()=>mail_drafts_get($ownerDraft));
    dr_deny('Writer cannot read another authors history',fn()=>mail_drafts_history($ownerDraft));
    dr_deny('Writer cannot discard another authors draft',fn()=>mail_drafts_discard($ownerDraft,1));
    dr_actor(4);dr_deny('Another owner cannot read staff private draft',fn()=>mail_drafts_get($id));dr_actor(3);dr_deny('Mailbox Send grant cannot replace missing global Send',fn()=>mail_drafts_get($id));dr_actor(2);
    dr_deny('Draft cannot switch to an unassigned account',fn()=>mail_drafts_save($id,1,$b,1,dr_content(),true));
    dr_actor();dr_grant($b,2);dr_actor(2);dr_deny('Account switch requires explicit confirmation',fn()=>mail_drafts_save($id,1,$b,1,dr_content()));
    mail_drafts_save($id,1,$b,1,dr_content(),true);dr_ok('Confirmed account switch increments draft revision and clears prior list',mail_drafts_get($id)['revision']===2&&mail_drafts_get($id)['account_id']===$b&&mail_drafts_list($a)===[]);
    mail_drafts_save($id,2,$a,1,dr_content(),true);
    query('UPDATE '.table('mail_accounts').' SET revision=2 WHERE id=?',[$a]);
    dr_ok('Changed sender settings are visible without silently accepting them',mail_drafts_get($id)['sender_changed']===true&&mail_drafts_get($id)['account_revision']===1);
    dr_deny('Stale sender revision cannot save',fn()=>mail_drafts_save($id,3,$a,1,dr_content(),true));
    dr_deny('Fresh sender revision still requires review confirmation',fn()=>mail_drafts_save($id,3,$a,2,dr_content()));
    mail_drafts_save($id,3,$a,2,dr_content(),true);dr_ok('Explicit sender review acknowledges current revision',mail_drafts_get($id)['account_revision']===2&&!mail_drafts_get($id)['sender_changed']);
    // Controlled fixture reset restores account A revision for independent race workers.
    query('UPDATE '.table('mail_accounts').' SET revision=1 WHERE id=?',[$a]);mail_drafts_save($id,4,$a,1,dr_content(),true);
    $before=dr_snapshot();dr_ok('Create audit failure leaves no draft or metadata history',dr_audit_failure(fn()=>mail_drafts_save(bin2hex(random_bytes(16)),0,$a,1,dr_content()))&&dr_snapshot()===$before);
    dr_ok('Edit audit failure rolls back body revision and history',dr_audit_failure(fn()=>mail_drafts_save($id,5,$a,1,dr_content('Failed audit edit')))&&dr_snapshot()===$before);
    dr_ok('Discard audit failure rolls back retained state and history',dr_audit_failure(fn()=>mail_drafts_discard($id,5))&&dr_snapshot()===$before);
    dr_deny('Stale draft edit does not overwrite newer text',fn()=>mail_drafts_save($id,4,$a,1,dr_content('Stale')));dr_deny('Stale discard cannot hide newer draft',fn()=>mail_drafts_discard($id,4));
    foreach(['future','expired','revoked','disabled','credentials','global'] as $mode) {
        $profile=mail_accounts_load($a);
        if($mode==='future')query('UPDATE '.table('mail_account_grants').' SET issued_at=? WHERE account_id=? AND user_id=2',[time()+3600,$a]);
        elseif($mode==='expired')query('UPDATE '.table('mail_account_grants').' SET expires_at=? WHERE account_id=? AND user_id=2',[time()-1,$a]);
        elseif($mode==='revoked')query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$a]);
        elseif($mode==='disabled')query('UPDATE '.table('mail_accounts').' SET enabled=0 WHERE id=?',[$a]);
        elseif($mode==='credentials')query('UPDATE '.table('mail_accounts')." SET credentials='' WHERE id=?",[$a]);
        else query('UPDATE '.table('staff_profiles')." SET permissions='[]' WHERE user_id=2");
        dr_deny('Current access loss prevents draft read: '.$mode,fn()=>mail_drafts_get($id));dr_deny('Current access loss prevents draft write: '.$mode,fn()=>mail_drafts_save($id,5,$a,1,dr_content()));
        query('UPDATE '.table('mail_account_grants').' SET issued_at=?,expires_at=NULL,enabled=1 WHERE account_id=? AND user_id=2',[time()-1,$a]);query('UPDATE '.table('mail_accounts').' SET enabled=1,credentials=? WHERE id=?',[$profile['credentials'],$a]);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['mail.send','mail.templates.manage'])]);dr_actor(2);
    }
    $_SESSION['auth_version']='stale';dr_deny('Revoked staff session cannot reopen draft',fn()=>mail_drafts_get($id));dr_actor(2);
    $_SESSION['last_activity']=time()-1801;dr_deny('Idle staff session cannot reopen draft',fn()=>mail_drafts_get($id));dr_actor(2);
    $_GET['support']='fixture';dr_deny('Support context cannot read private staff drafts',fn()=>mail_drafts_get($id));unset($_GET['support']);
    $_SESSION=['client_id'=>1,'client_auth'=>'synthetic'];dr_deny('Client identity cannot read staff drafts',fn()=>mail_drafts_get($id));$_SESSION=[];dr_deny('Anonymous identity cannot read drafts',fn()=>mail_drafts_get($id));dr_actor(2);
    $template=mail_templates_create('Synthetic draft template','Hello {{client_name}}','Project {{project_name}}','personal');
    dr_deny('Draft template is not insertable',fn()=>mail_drafts_template($a,1,$template,1,['client_name'=>'Name','project_name'=>'Project']));
    mail_templates_change($template,1,'activate');$before=dr_snapshot();$text=mail_drafts_template($a,1,$template,2,['client_name'=>'Name','project_name'=>'Project']);
    dr_ok('Active personal template previews plain snapshot without saving',$text->subject==='Hello Name'&&$text->body==='Project Project'&&dr_snapshot()===$before);
    dr_deny('Template insertion rejects stale source revision',fn()=>mail_drafts_template($a,1,$template,1,['client_name'=>'Name','project_name'=>'Project']));
    dr_deny('Template insertion rejects unresolved fields',fn()=>mail_drafts_template($a,1,$template,2,['client_name'=>'Name']));
    dr_deny('Template insertion rejects header injection from replacement',fn()=>mail_drafts_template($a,1,$template,2,['client_name'=>"Name\r\nBcc: bad@example.org",'project_name'=>'Project']));
    query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['mail.send'])]);dr_deny('Send permission does not confer template library access',fn()=>mail_drafts_template($a,1,$template,2,['client_name'=>'Name','project_name'=>'Project']));query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['mail.send','mail.templates.manage'])]);
    dr_actor();dr_deny('Owner cannot insert another authors personal template',fn()=>mail_drafts_template($a,1,$template,2,['client_name'=>'Name','project_name'=>'Project']));dr_actor(2);
    mail_templates_change($template,2,'archive');dr_deny('Archived source is refused even with its current revision',fn()=>mail_drafts_template($a,1,$template,3,['client_name'=>'Name','project_name'=>'Project']));
    $damaged=one('SELECT to_json FROM '.table('mail_drafts').' WHERE id=?',[$id]);query('UPDATE '.table('mail_drafts')." SET to_json='{}' WHERE id=?",[$id]);dr_deny('Stored object cannot masquerade as an empty recipient list',fn()=>mail_drafts_get($id));query('UPDATE '.table('mail_drafts').' SET to_json=? WHERE id=?',[$damaged['to_json'],$id]);
    rename($private.'/mail-master.key',$private.'/mail-master.key.held');try{dr_ok('Draft content never decrypts mailbox credentials',mail_drafts_get($id)['body']===dr_content()->body);mail_drafts_save($id,5,$a,1,dr_content());}finally{rename($private.'/mail-master.key.held',$private.'/mail-master.key');}
    $workers=[dr_worker($id,'6',$a,'Concurrent first'),dr_worker($id,'6',$a,'Concurrent second')];$out=array_map('dr_finish',$workers);sort($out);dr_ok('Two concurrent edits commit only one new version',$out===["ready\nsaved","ready\nstale"]&&mail_drafts_get($id)['revision']===7);
    $race=bin2hex(random_bytes(16));$workers=[dr_worker($race,'0',$a,'Duplicate create'),dr_worker($race,'0',$a,'Duplicate create')];$out=array_map('dr_finish',$workers);dr_ok('Concurrent identical create replay makes one draft and one history row',$out===["ready\nsaved","ready\nsaved"]&&count(mail_drafts_history($race))===1);
    $before=dr_snapshot();staff_locked(function()use($id,$a):void{$worker=dr_worker($id,'7',$a,'Revoked writer');dr_ok('Waiting writer reaches the shared authorization boundary',trim(fgets($worker[1][1]))==='ready');query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$a]);$GLOBALS['draft_waiting_worker']=$worker;});
    dr_ok('Grant revoked while writer waits prevents commit',dr_finish($GLOBALS['draft_waiting_worker'])==='denied'&&dr_snapshot()===$before);query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$a]);
    mail_drafts_discard($id,7);dr_ok('Discard retains content and hides only active local draft',mail_drafts_get($id)['status']==='discarded'&&mail_drafts_get($id)['body']!==''&&!in_array($id,array_column(mail_drafts_list($a),'id'),true)&&in_array($id,array_column(mail_drafts_list($a,'discarded'),'id'),true));
    dr_deny('Discarded draft cannot silently resume through stale save',fn()=>mail_drafts_save($id,8,$a,1,dr_content()));dr_deny('Old create replay cannot revive a discarded draft',fn()=>mail_drafts_save($id,0,$a,1,dr_content()));
    for($i=0;$i<22;$i++)mail_drafts_save(bin2hex(random_bytes(16)),0,$a,1,new DraftContent([],[],[],'Page '.$i,''));
    $first=mail_drafts_list($a);$older=mail_drafts_list($a,'draft',2);dr_ok('Draft pagination has distinct displayed pages and bounded lookahead',count($first)===21&&count($older)===3&&!array_intersect(array_column(array_slice($first,0,20),'id'),array_column($older,'id')));
    $metadata=json_encode([all('SELECT * FROM '.table('mail_draft_revisions')),all('SELECT * FROM '.table('audit'))]);dr_ok('Audit and draft history exclude subject body and recipients',!str_contains($metadata,'SYNTHETIC_PRIVATE_DRAFT')&&!str_contains($metadata,'private@example.invalid')&&!str_contains($metadata,'client@example.invalid'));
    dr_ok('Draft workflows preserve the original mailbox encryption key',file_get_contents($private.'/mail-master.key')===$key);
    dr_ok('System mail remains disabled and unconfigured',setting('email_mode')==='disabled'&&setting('smtp_host')===''&&setting('smtp_password')==='');
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/mail-drafts-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB synthetic author/mailbox storage, strict audit and process races; no mail transport or application UI tested','checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo ($ok?'PASS':'FAIL').': '.count($checks)." native draft checks\n";exit($ok?0:1);
