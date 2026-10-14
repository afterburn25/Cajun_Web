<?php
/** Disposable database driver for real default coordinator/TLS integration, never customer data. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/mail/submission_dispatch.php';
require_once __DIR__.'/mail_submission_native_connector.php';
use CajunWebPro\Foundation\MailCompose\DraftContent;
$private=__DIR__.'/../test-runtime/submission-combined-private';@mkdir($private,0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-combined-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'stci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);$password='Synthetic combined owner passphrase';$observations=[];
function sc_actor(int $id=2):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function sc_lock_owner():mixed {$key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);return query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn();}
function sc_content(string $body=''):DraftContent {return new DraftContent(['first@example.invalid'],['copy@example.invalid'],['private@example.invalid'],'Synthetic combined Café 👩‍💻',$body!==''?$body:".first\n..second\nSYNTHETIC_COMBINED_BODY Résumé\n</textarea><script>literal</script>");}
if($input['action']==='setup') {
    foreach(schema_statements('stci_') as $sql)db()->exec($sql);foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=2;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Combined staff '.$i,'combined'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,'[\"mail.send\"]')");
    sc_actor(1);mail_accounts_migrate($password);mail_drafts_migrate($password);mail_submissions_migrate($password);$accounts=[];
    foreach(['ssl'=>465,'starttls'=>587] as $encryption=>$port) {
        $account=mail_accounts_save('',0,['label'=>'Combined '.$encryption,'address'=>'sender@example.invalid','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic-incoming','smtp_host'=>'submission-fixture.example.net','smtp_port'=>(string)$port,'smtp_encryption'=>$encryption,'smtp_username'=>'synthetic-submission@example.invalid'],true,['imap_password'=>'Synthetic incoming password','smtp_password'=>'Synthetic combined SMTP password'],$password);
        mail_accounts_grant($account,2,0,true,true,true,0,$password);$accounts[$encryption]=$account;
    }
    file_put_contents($private.'/provider-master.key','SYNTHETIC_COMBINED_PROVIDER_KEY');echo json_encode(['accounts'=>$accounts]);exit;
}
sc_actor();
if($input['action']==='draft') {
    // Synthetic clock-history setup avoids waiting through account cooldowns, never a shipping API.
    query('UPDATE '.table('mail_submission_dispatches').' SET started_epoch=0');$draft=bin2hex(random_bytes(16));mail_drafts_save($draft,0,$input['account'],1,sc_content());echo json_encode(['draft'=>$draft,'id'=>bin2hex(random_bytes(16))]);exit;
}
if($input['action']==='edit') {
    $saved=mail_drafts_get($input['draft']);mail_drafts_save($saved['id'],(int)$saved['revision'],$saved['account_id'],1,sc_content('SYNTHETIC_REVIEWED_NEW_VERSION'));
    query('UPDATE '.table('mail_submission_dispatches').' SET started_epoch=0');echo json_encode(['revision'=>(int)$saved['revision']+1,'id'=>bin2hex(random_bytes(16))]);exit;
}
if($input['action']!=='send')throw new InvalidArgumentException('Unknown fixture action');
$id=$input['id'];$draft=$input['draft'];$saved=mail_drafts_get($draft);$account=$saved['account_id'];
$GLOBALS['submission_fixture_dns']=static function()use(&$observations):void {
    $observations[]=['stage'=>'dns','transaction'=>db()->inTransaction(),'lock'=>sc_lock_owner()];
};
$GLOBALS['submission_fixture_before_open']=static function()use(&$observations,$id):void {
    $fence=one('SELECT * FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$id]);
    $audit=one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['mail_submissions.dispatched','Submission '.$id.' %']);
    $observations[]=['stage'=>'socket','transaction'=>db()->inTransaction(),'held'=>sc_lock_owner()==query('SELECT CONNECTION_ID()')->fetchColumn(),'fence'=>$fence!==null,'audit'=>$audit!==null];
};
if($input['revoke_after_open']??false)$GLOBALS['submission_fixture_after_open']=static function()use($account):void {query('UPDATE '.table('mail_account_grants').' SET enabled=0 WHERE account_id=? AND user_id=2',[$account]);};
$trigger=$input['fail_audit']??null;
if($trigger!==null) {
    if(!in_array($trigger,['prepared','dispatched','finished'],true))throw new InvalidArgumentException('Unknown fixture audit');
    db()->exec('CREATE TRIGGER stci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='mail_submissions.".$trigger."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic combined audit failure'; END IF; END");
}
$error=null;$returned=null;
try {$returned=mail_submissions_send($id,$draft,$input['revision']??1,1);}
catch(PDOException) {$error='database';}
catch(InvalidArgumentException|RuntimeException) {$error='refused';}
finally {if($trigger!==null)db()->exec('DROP TRIGGER stci_fail');}
$id=$returned??$id;
$row=one('SELECT * FROM '.table('mail_submissions').' WHERE id=?',[$id]);$fence=one('SELECT * FROM '.table('mail_submission_dispatches').' WHERE submission_id=?',[$id]);$result=one('SELECT * FROM '.table('mail_submission_results').' WHERE submission_id=?',[$id]);
$audit=json_encode(all('SELECT action,detail FROM '.table('audit')),JSON_THROW_ON_ERROR);$disclosure=true;
if($row){try{mail_submissions_get($id);}catch(InvalidArgumentException|RuntimeException){$disclosure=false;}}
query('UPDATE '.table('mail_account_grants').' SET enabled=1 WHERE account_id=? AND user_id=2',[$account]);
echo json_encode(['returned'=>$returned,'error'=>$error,'observations'=>$observations,'intent'=>$row===null?null:['id'=>$row['id'],'attempt'=>$row['attempt_id'],'fingerprint'=>$row['snapshot_fingerprint'],'mime_sha256'=>$row['mime_sha256'],'mime_bytes'=>$row['mime_bytes'],'private'=>json_decode($row['payload_json'],true,flags:JSON_THROW_ON_ERROR)],'fence'=>$fence,'result'=>$result,'disclosure'=>$disclosure,'audit_private'=>str_contains($audit,'SYNTHETIC_COMBINED_BODY')||str_contains($audit,'private@example.invalid')||str_contains($audit,'Synthetic combined SMTP password'),'key_sha256'=>hash_file('sha256',$private.'/mail-master.key'),'provider_key_sha256'=>hash_file('sha256',$private.'/provider-master.key')],JSON_THROW_ON_ERROR);
