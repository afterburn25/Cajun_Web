<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/mail/replies.php';
require_once __DIR__.'/../site/app/mail/submissions.php';
use CajunWebPro\Foundation\MailCompose\{DraftContent,ReplyDraft,DraftOrigin};
$prefix=getenv('CWP_TEST_PREFIX')?:'rpci_';if(!preg_match('/\Arp[a-z0-9_]{2,15}_\z/',$prefix))exit(2);
$private=__DIR__.'/../test-runtime/'.$prefix.'private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-reply-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>$prefix]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic reply owner passphrase';
function rp_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function rp_deny(string $name,callable $fn):void{try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;rp_ok($name,true);return;}rp_ok($name,false);}
function rp_actor(int $id=2):void{$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function rp_raw(string $subject='PRIVATE_COPIED_SUBJECT'):string{return "From: Source <source@example.invalid>\r\nSubject: ".$subject."\r\nMessage-ID: <source@example.invalid>\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nPRIVATE_COPIED_BODY\n</textarea><script>literal()</script>";}
function rp_prepared(string $mode='reply'):ReplyDraft{return ReplyDraft::prepare($mode,rp_raw());}
function rp_account(string $label):string{global $password;return mail_accounts_save('',0,['label'=>$label,'address'=>'sender@example.invalid','imap_host'=>'imap.example.net','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'synthetic','smtp_host'=>'smtp.example.net','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'synthetic'],true,['imap_password'=>'Synthetic incoming','smtp_password'=>'Synthetic outgoing'],$password);}
function rp_grant(string $account,int $user,bool $read,bool $send):void{global $password;$g=one('SELECT revision FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=?',[$account,$user]);mail_accounts_grant($account,$user,(int)($g['revision']??0),$read,$send,true,0,$password);}
function rp_read(string $account,string $uid='44',int $actor=2):array{
    $id=bin2hex(random_bytes(16));$now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,1,?,?,?,?,?,?)',[$id,$account,$actor,'INBOX','7',$uid,$now,time()]);
    query('INSERT INTO '.table('mail_message_results')." (id,outcome,result_code,finished_at,elapsed_ms,message_bytes) VALUES (?,'success','ok',?,1,?)",[$id,$now,strlen(rp_raw())]);
    return ['code'=>'ok','request_id'=>$id,'account_id'=>$account,'account_revision'=>1,'actor_id'=>$actor,'uidvalidity'=>'7','uid'=>$uid,'content'=>['folder'=>'INBOX','uidvalidity'=>'7','uid'=>$uid,'bytes'=>(string)strlen(rp_raw())]];
}
function rp_snapshot():string{return json_encode([all('SELECT * FROM '.table('mail_drafts').' ORDER BY id'),all('SELECT * FROM '.table('mail_reply_drafts').' ORDER BY id'),all('SELECT * FROM '.table('mail_draft_revisions').' ORDER BY draft_id,revision'),all('SELECT * FROM '.table('audit').' ORDER BY id')],JSON_THROW_ON_ERROR);}
function rp_audit_failure(callable $fn):bool{$trigger=config()['db']['prefix'].'fail';db()->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic reply audit failure'");try{$fn();return false;}catch(PDOException){return true;}finally{db()->exec('DROP TRIGGER '.$trigger);}}
function rp_worker(string $id,int $revision,string $account):array{$p=proc_open([PHP_BINARY,__FILE__,'worker',$id,(string)$revision,$account],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Cannot start worker');foreach([1,2] as $i)stream_set_timeout($pipes[$i],15);return [$p,$pipes];}
function rp_finish(array $w):string{[$p,$pipes]=$w;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException('Reply worker failed: '.$err);return $out;}
if(($argv[1]??'')==='worker'){rp_actor();echo "ready\n";fflush(STDOUT);try{mail_reply_drafts_save($argv[2],(int)$argv[3],$argv[4],1,new DraftContent(['client@example.invalid'],[],[],'Worker saved','Worker body'));echo 'saved';}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo 'denied';}exit;}
try{
    if((int)query('SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($prefix),$prefix])->fetchColumn()!==0)throw new RuntimeException('Preserve existing fixture tables; choose a fresh rp test prefix.');
    foreach(schema_statements($prefix) as $sql)db()->exec($sql);foreach(default_settings() as $k=>$v)save_setting($k,$v);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Reply staff '.$i,'reply'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2,3] as $i)query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[$i,json_encode(['mail.read','mail.send','mail.templates.manage'])]);
    rp_actor(1);mail_accounts_migrate($password);mail_templates_migrate($password);mail_drafts_migrate($password);mail_messages_migrate($password);mail_submissions_migrate($password);
    $source=rp_account('Source mailbox');$sender=rp_account('Sending mailbox');$other=rp_account('Other sender');$key=file_get_contents($private.'/mail-master.key');
    rp_grant($source,1,true,true);rp_grant($sender,1,true,true);rp_grant($source,2,true,false);rp_grant($sender,2,false,true);rp_grant($other,2,false,true);rp_grant($source,3,true,true);rp_grant($sender,3,true,true);
    rp_deny('Reply setup requires current owner password',fn()=>mail_replies_migrate('wrong'));
    rp_actor();rp_deny('Administrator grants do not confer reply setup',fn()=>mail_replies_migrate($password));rp_deny('Unprepared reply storage refuses lists',fn()=>mail_reply_drafts_list($sender));rp_actor(1);
    save_setting('mail_replies_schema_version','future');rp_deny('Future storage is not downgraded',fn()=>mail_replies_migrate($password));query('DELETE FROM '.table('settings').' WHERE name=?',['mail_replies_schema_version']);
    foreach(mail_replies_schema($prefix) as $sql)db()->exec($sql);
    db()->exec('ALTER TABLE '.table('mail_reply_drafts')." ALTER status SET DEFAULT 'draft'");rp_deny('Unexpected native defaults are refused',fn()=>mail_replies_migrate($password));db()->exec('ALTER TABLE '.table('mail_reply_drafts').' ALTER status DROP DEFAULT');
    db()->exec('ALTER TABLE '.table('mail_reply_drafts').' ADD INDEX unexpected_status(status)');rp_deny('Unexpected native indexes are refused',fn()=>mail_replies_migrate($password));db()->exec('ALTER TABLE '.table('mail_reply_drafts').' DROP INDEX unexpected_status');
    rp_ok('Setup audit failure leaves readiness disabled',rp_audit_failure(fn()=>mail_replies_migrate($password))&&!mail_replies_ready());
    $settings=all('SELECT * FROM '.table('settings').' ORDER BY name');mail_replies_migrate($password);mail_replies_migrate($password);
    rp_ok('Repeated additive setup preserves existing settings',mail_replies_ready()&&all('SELECT * FROM '.table('settings')." WHERE name<>'mail_replies_schema_version' ORDER BY name")===$settings);
    rp_actor();$read=rp_read($source);$prepared=rp_prepared();$id=bin2hex(random_bytes(16));
    rp_ok('Fresh creation request resolves no existing draft',mail_reply_drafts_existing($id,$source,1,'7','44','reply',$sender,1)===null);
    mail_reply_drafts_create($id,$sender,1,$read,$prepared);$draft=mail_reply_drafts_get($id);
    rp_ok('Author can create and reopen copied content with separate source and sender',$draft['body']===$prepared->content->body&&$draft['subject']==='Re: PRIVATE_COPIED_SUBJECT'&&$draft['origin']['source_account_id']===$source&&$draft['account_id']===$sender&&$draft['to']===['source@example.invalid']);
    rp_ok('Copied list exposes content only after current authority',mail_reply_drafts_list($sender)[0]['subject']===$draft['subject']&&!mail_reply_drafts_list($sender)[0]['unavailable']);
    $legacy=mail_drafts_get($id);rp_ok('Retained ordinary getter exposes only a content-free recovery placeholder',$legacy['status']==='source'&&$legacy['subject']==='Copied message draft'&&$legacy['body']===''&&$legacy['to']===[]&&$legacy['cc']===[]&&$legacy['bcc']===[]);
    rp_ok('Retained ordinary list cannot expose a copied subject',mail_drafts_list($sender)===[]);
    rp_deny('Retained ordinary writer cannot overwrite a copied draft',fn()=>mail_drafts_save($id,1,$sender,1,new DraftContent([],[],[],'Attempted legacy edit','Private')));
    rp_deny('Retained ordinary discard cannot change copied draft storage',fn()=>mail_drafts_discard($id,1));
    $intent=bin2hex(random_bytes(16));mail_submissions_prepare($intent,$id,1,1);
    $sub=mail_submissions_get($intent);$originFp=hash('sha256',one('SELECT origin_json FROM '.table('mail_reply_drafts').' WHERE id=?',[$id])['origin_json']);
    rp_ok('Copied draft preparation binds origin and reply thread as format 2',$sub['content']['format']===2&&$sub['content']['thread']===['in_reply_to'=>'<source@example.invalid>','references'=>['<source@example.invalid>']]&&$sub['content']['origin_fingerprint']===$originFp&&$sub['content']['draft_id']===$id);
    $subRow=one('SELECT * FROM '.table('mail_submissions').' WHERE id=?',[$intent]);$subMime=mail_submissions_message($subRow);
    rp_ok('Prepared MIME carries exact RFC reply headers',str_contains($subMime->bytes,'In-Reply-To: <source@example.invalid>')&&str_contains($subMime->bytes,'References: <source@example.invalid>')&&preg_match('/^Reply-To:/mi',$subMime->bytes)!==1&&$subMime->sha256===$subRow['mime_sha256']);
    rp_ok('Format2 replay returns the committed intent without another copy',mail_submissions_prepare(bin2hex(random_bytes(16)),$id,1,1)===$intent);
    $tampered=$subRow;$tampered['payload_json']=str_replace('<source@example.invalid>','<other@example.invalid>',$subRow['payload_json']);rp_deny('Changed stored thread binding is refused',fn()=>mail_submissions_message($tampered));
    $tampered=$subRow;$tampered['payload_json']=str_replace('"format":2','"format":1',$subRow['payload_json']);rp_deny('Downgraded stored format is refused',fn()=>mail_submissions_message($tampered));
    $tampered=$subRow;$tampered['payload_json']=str_replace('"format":2','"format":3',$subRow['payload_json']);rp_deny('Unknown stored format is refused',fn()=>mail_submissions_message($tampered));
    $tampered=$subRow;$tampered['payload_json']=json_encode(json_decode($subRow['payload_json'],true)+['extra'=>'x']);rp_deny('Appended stored payload fields are refused',fn()=>mail_submissions_message($tampered));
    $forwardIntent=null;
    $before=rp_snapshot();rp_ok('Exact create replay is write-free',mail_reply_drafts_create($id,$sender,1,$read,$prepared)===$id&&rp_snapshot()===$before);
    rp_ok('Exact form replay resolves existing draft without fetching',mail_reply_drafts_existing($id,$source,1,'7','44','reply',$sender,1)===$id&&rp_snapshot()===$before);
    rp_deny('Form identity cannot be reused for another message',fn()=>mail_reply_drafts_existing($id,$source,1,'7','45','reply',$sender,1));
    rp_deny('Form identity cannot be reused for another action',fn()=>mail_reply_drafts_existing($id,$source,1,'7','44','forward',$sender,1));
    rp_deny('Form identity cannot be reused for a reply-all draft',fn()=>mail_reply_drafts_existing($id,$source,1,'7','44','reply-all',$sender,1));
    rp_deny('Repeated creation cannot overwrite changed source content',fn()=>mail_reply_drafts_create($id,$sender,1,$read,ReplyDraft::prepare('reply',rp_raw('Different source subject'))));
    $bad=$read;$bad['uid']='45';rp_deny('Source UID must match durable successful read',fn()=>mail_reply_drafts_create(bin2hex(random_bytes(16)),$sender,1,$bad,$prepared));
    $bad=$read;$bad['request_id']=str_repeat('a',32);rp_deny('Unknown source read cannot create copied content',fn()=>mail_reply_drafts_create(bin2hex(random_bytes(16)),$sender,1,$bad,$prepared));
    $bad=$read;$bad['content']['bytes']='1';rp_deny('Source byte evidence must match successful read',fn()=>mail_reply_drafts_create(bin2hex(random_bytes(16)),$sender,1,$bad,$prepared));
    rp_actor(3);rp_deny('Another authorized staff member cannot read the private draft',fn()=>mail_reply_drafts_get($id));rp_deny('Another staff member cannot reuse the creation identity',fn()=>mail_reply_drafts_create($id,$sender,1,$read,$prepared));rp_actor();
    query('UPDATE '.table('mail_account_grants').' SET can_read=0 WHERE account_id=? AND user_id=2',[$source]);
    rp_deny('Revoked source Read refuses copied detail',fn()=>mail_reply_drafts_get($id));rp_deny('Revoked source Read refuses copied history',fn()=>mail_reply_drafts_history($id));rp_deny('Revoked source Read refuses new creation',fn()=>mail_reply_drafts_create(bin2hex(random_bytes(16)),$sender,1,$read,$prepared));
    rp_deny('Revoked source Read refuses edits',fn()=>mail_reply_drafts_save($id,1,$sender,1,new DraftContent([],[],[],'Edited','Body')));
    rp_deny('Revoked source Read refuses discard',fn()=>mail_reply_drafts_discard($id,1));
    rp_deny('Revoked source Read refuses copied submission preparation',fn()=>mail_submissions_prepare(bin2hex(random_bytes(16)),$id,1,1));
    rp_deny('Revoked source Read refuses copied submission detail',fn()=>mail_submissions_get($intent));
    rp_deny('Revoked source Read refuses existing-id submission replay',fn()=>mail_submissions_prepare($intent,$id,1,1));
    $hiddenSubs=mail_submissions_list($sender);rp_ok('Copied submission history masks its subject while source Read is revoked',array_column($hiddenSubs,'subject','id')[$intent]==='Copied message unavailable'&&!str_contains(json_encode($hiddenSubs),'PRIVATE_COPIED'));
    $hidden=mail_reply_drafts_list($sender);rp_ok('Send-only list retains a content-free unavailable entry',$hidden[0]['unavailable']&&$hidden[0]['subject']==='Copied message draft unavailable'&&!str_contains(json_encode($hidden),'PRIVATE_COPIED'));
    rp_ok('Older getter remains content-free after Read revocation',mail_drafts_get($id)['body']===''&&!str_contains(json_encode(mail_drafts_get($id)),'PRIVATE_COPIED'));
    query('UPDATE '.table('mail_account_grants').' SET can_read=1 WHERE account_id=? AND user_id=2',[$source]);
    rp_ok('Restored source Read returns copied submission detail',mail_submissions_get($intent)['content']['subject']==='Re: PRIVATE_COPIED_SUBJECT');
    rp_ok('Restored source Read replays the committed intent',mail_submissions_prepare($intent,$id,1,1)===$intent);
    query('UPDATE '.table('mail_accounts').' SET revision=2 WHERE id=?',[$source]);rp_deny('Source revision change cannot silently rebind copied content',fn()=>mail_reply_drafts_get($id));rp_deny('Source revision change refuses copied submission detail',fn()=>mail_submissions_get($intent));query('UPDATE '.table('mail_accounts').' SET revision=1 WHERE id=?',[$source]);
    query('UPDATE '.table('mail_account_grants').' SET can_send=0 WHERE account_id=? AND user_id=2',[$sender]);rp_deny('Current sender Send is independently required',fn()=>mail_reply_drafts_get($id));rp_deny('Sender Send loss also refuses copied submission detail',fn()=>mail_submissions_get($intent));query('UPDATE '.table('mail_account_grants').' SET can_send=1 WHERE account_id=? AND user_id=2',[$sender]);
    query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['mail.send'])]);rp_deny('Mailbox Read grant cannot replace global Read',fn()=>mail_reply_drafts_get($id));rp_deny('Global mail.read loss refuses copied submission detail',fn()=>mail_submissions_get($intent));query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['mail.read','mail.send','mail.templates.manage'])]);
    $_SESSION['auth_version']='stale';rp_deny('Revoked staff session cannot read copied content',fn()=>mail_reply_drafts_get($id));rp_actor();$_SESSION['last_activity']=time()-1801;rp_deny('Expired session cannot read copied content',fn()=>mail_reply_drafts_get($id));rp_actor();
    $_GET['support']='synthetic';rp_deny('Support context cannot read private copied drafts',fn()=>mail_reply_drafts_get($id));unset($_GET['support']);
    $edit=new DraftContent(['client@example.invalid'],[],[],'Edited subject','Edited body');
    rp_deny('Changing outgoing mailbox requires explicit review',fn()=>mail_reply_drafts_save($id,1,$other,1,$edit));
    mail_reply_drafts_save($id,1,$other,1,$edit,true);$edited=mail_reply_drafts_get($id);
    rp_ok('Reviewed sender change retains exact original source and threading',$edited['revision']===2&&$edited['account_id']===$other&&$edited['origin']===$draft['origin']&&$edited['body']==='Edited body');
    staff_locked(function()use($subRow,$subMime):void{rp_deny('An edited copied draft no longer authorizes its earlier submission',fn()=>mail_submissions_authorize($subRow,$subMime));});
    $before=rp_snapshot();rp_ok('Original form replay preserves subsequent edits',mail_reply_drafts_existing($id,$source,1,'7','44','reply',$sender,1)===$id&&rp_snapshot()===$before&&mail_reply_drafts_get($id)['body']==='Edited body');
    rp_ok('Repeated create after edit does not overwrite the new content',mail_reply_drafts_create($id,$sender,1,$read,$prepared)===$id&&rp_snapshot()===$before);
    rp_deny('Stale edit preserves current content',fn()=>mail_reply_drafts_save($id,1,$other,1,$edit));
    rp_ok('Create audit failure rolls back placeholder content and history',rp_audit_failure(fn()=>mail_reply_drafts_create(bin2hex(random_bytes(16)),$sender,1,$read,$prepared))&&rp_snapshot()===$before);
    rp_ok('Edit audit failure rolls back both storage rows',rp_audit_failure(fn()=>mail_reply_drafts_save($id,2,$other,1,new DraftContent([],[],[],'Failed edit','Changed')))&&rp_snapshot()===$before);
    rp_ok('Discard audit failure rolls back both storage rows',rp_audit_failure(fn()=>mail_reply_drafts_discard($id,2))&&rp_snapshot()===$before);
    $saved=one('SELECT * FROM '.table('mail_reply_drafts').' WHERE id=?',[$id]);query('UPDATE '.table('mail_reply_drafts').' SET content_json=? WHERE id=?',[str_replace('Edited body','Changed body',$saved['content_json']),$id]);rp_deny('Changed content at unchanged revision is detected',fn()=>mail_reply_drafts_get($id));query('UPDATE '.table('mail_reply_drafts').' SET content_json=? WHERE id=?',[$saved['content_json'],$id]);
    query('UPDATE '.table('mail_reply_drafts').' SET origin_json=? WHERE id=?',[str_replace('"uid":"44"','"uid":"45"',$saved['origin_json']),$id]);rp_deny('Changed source binding is detected against immutable fingerprint',fn()=>mail_reply_drafts_get($id));query('UPDATE '.table('mail_reply_drafts').' SET origin_json=? WHERE id=?',[$saved['origin_json'],$id]);
    query('UPDATE '.table('mail_drafts')." SET body='Unexpected copied bytes' WHERE id=?",[$id]);rp_deny('Contaminated legacy placeholder is refused',fn()=>mail_reply_drafts_get($id));query('UPDATE '.table('mail_drafts')." SET body='' WHERE id=?",[$id]);
    $origin=DraftOrigin::fromJson($saved['origin_json']);rp_ok('Canonical origin reconstructs exact immutable fingerprint',$origin->json===$saved['origin_json']&&$origin->fingerprint===one('SELECT create_fingerprint FROM '.table('mail_drafts').' WHERE id=?',[$id])['create_fingerprint']);
    foreach(['float format'=>str_replace('"format":1','"format":1.0',$origin->json),'duplicate key'=>str_replace('"format":1','"format":1,"format":1',$origin->json),'out-of-range UID'=>str_replace('"uid":"44"','"uid":"4294967296"',$origin->json),'unknown omission'=>str_replace('"omissions":[]','"omissions":["unknown"]',$origin->json)] as $label=>$json)rp_deny('Origin refuses '.$label,fn()=>DraftOrigin::fromJson($json));
    $before=rp_snapshot();staff_locked(function()use($id,$other,$source):void{$w=rp_worker($id,2,$other);rp_ok('Concurrent writer reaches current-authority lock',trim(fgets($w[1][1]))==='ready');query('UPDATE '.table('mail_account_grants').' SET can_read=0 WHERE account_id=? AND user_id=2',[$source]);$GLOBALS['reply_worker']=$w;});
    rp_ok('Read revoked while writer waits prevents commit',rp_finish($GLOBALS['reply_worker'])==='denied'&&rp_snapshot()===$before);query('UPDATE '.table('mail_account_grants').' SET can_read=1 WHERE account_id=? AND user_id=2',[$source]);
    mail_reply_drafts_discard($id,2);rp_ok('Discard retains protected body and immutable origin',mail_reply_drafts_get($id)['status']==='discarded'&&mail_reply_drafts_get($id)['body']==='Edited body'&&mail_reply_drafts_get($id)['origin']===$draft['origin']);
    rp_ok('Discard moves only the protected list filter',mail_reply_drafts_list($other)===[]&&array_column(mail_reply_drafts_list($other,'discarded'),'id')===[$id]);
    rp_deny('Discarded copied draft cannot be revived by save',fn()=>mail_reply_drafts_save($id,3,$other,1,$edit));
    $before=rp_snapshot();rp_ok('Old form replay does not revive discarded content',mail_reply_drafts_existing($id,$source,1,'7','44','reply',$sender,1)===$id&&rp_snapshot()===$before);
    $forward=bin2hex(random_bytes(16));mail_reply_drafts_create($forward,$sender,1,$read,rp_prepared('forward'));$f=mail_reply_drafts_get($forward);rp_ok('Forward remains recipientless with no reply headers',$f['to']===[]&&$f['origin']['mode']==='forward'&&$f['origin']['thread']===['in_reply_to'=>null,'references'=>[]]);
    $ordinary=bin2hex(random_bytes(16));mail_drafts_save($ordinary,0,$sender,1,new DraftContent(['ordinary@example.invalid'],[],[],'Ordinary subject','Ordinary body'));rp_ok('Ordinary drafts remain editable through their original API',mail_drafts_get($ordinary)['subject']==='Ordinary subject'&&array_column(mail_drafts_list($sender),'id')===[$ordinary]);
    $intent2=bin2hex(random_bytes(16));mail_submissions_prepare($intent2,$ordinary,1,1);rp_ok('Ordinary draft preparation keeps format1 and exact ordinary body',mail_submissions_get($intent2)['content']['format']===1&&mail_submissions_get($intent2)['content']['body']==='Ordinary body');
    rp_deny('A recipientless forward cannot prepare a submission',fn()=>mail_submissions_prepare(bin2hex(random_bytes(16)),$forward,1,1));
    mail_reply_drafts_save($forward,1,$sender,1,new DraftContent(['forward@example.invalid'],[],[],'Fwd: PRIVATE_COPIED_SUBJECT','Forward body'));
    $forwardIntent=bin2hex(random_bytes(16));mail_submissions_prepare($forwardIntent,$forward,2,1);
    rp_ok('Forward submission is format 2 with no inherited reply headers',mail_submissions_get($forwardIntent)['content']['format']===2&&mail_submissions_get($forwardIntent)['content']['thread']===['in_reply_to'=>null,'references'=>[]]);
    $forwardMime=mail_submissions_message(one('SELECT * FROM '.table('mail_submissions').' WHERE id=?',[$forwardIntent]));
    rp_ok('Forward MIME carries no reply threading headers',!str_contains($forwardMime->bytes,'In-Reply-To:')&&!str_contains($forwardMime->bytes,'References:'));
    // One stable merged page boundary over ordinary and copied drafts.
    for($i=0;$i<21;$i++){mail_drafts_save(bin2hex(random_bytes(16)),0,$sender,1,new DraftContent(['p'.$i.'@example.invalid'],[],[],'Paged ordinary '.$i,'Body '.$i));}
    for($i=0;$i<21;$i++){mail_reply_drafts_create(bin2hex(random_bytes(16)),$sender,1,rp_read($source,(string)(100+$i)),rp_prepared());}
    $seen=[];$pages=0;
    for($p=1;$p<=4;$p++){$list=mail_drafts_combined_list($sender,'draft',$p);foreach(array_slice($list,0,20) as $r)$seen[$r['id']]=($seen[$r['id']]??0)+1;$pages++;if(count($list)<=20)break;}
    rp_ok('Merged pagination returns every draft exactly once',$pages===3&&count($seen)===44&&max($seen)===1);
    rp_actor(3);mail_drafts_save(bin2hex(random_bytes(16)),0,$sender,1,new DraftContent(['x@example.invalid'],[],[],'Foreign','B'));rp_actor();
    rp_ok('Merged list stays owner-scoped',!in_array('Foreign',array_column(mail_drafts_combined_list($sender),'subject'),true));
    query('UPDATE '.table('mail_drafts').' SET updated_at=? WHERE owner_id=?',['2026-01-01 00:00:00',2]);
    $pageOne=array_column(array_slice(mail_drafts_combined_list($sender,'draft',1),0,20),'id');$pageTwo=array_column(array_slice(mail_drafts_combined_list($sender,'draft',2),0,20),'id');
    rp_ok('Equal timestamps keep a stable disjoint page boundary',count($pageOne)===20&&count($pageTwo)===20&&array_intersect($pageOne,$pageTwo)===[]);
    $before=rp_snapshot();rp_actor(1);mail_replies_migrate($password);rp_actor();rp_ok('Repeated setup preserves populated copied content and ordinary drafts',mail_reply_drafts_get($forward)['origin']===$f['origin']&&mail_drafts_get($ordinary)['body']==='Ordinary body');
    $metadata=json_encode([all('SELECT * FROM '.table('mail_draft_revisions')),all('SELECT * FROM '.table('audit'))]);rp_ok('Metadata history and audit never retain copied subject body or recipients',!str_contains($metadata,'PRIVATE_COPIED')&&!str_contains($metadata,'source@example.invalid')&&!str_contains($metadata,'Edited body'));
    rp_ok('Original mailbox encryption key remains unchanged',file_get_contents($private.'/mail-master.key')===$key);
    rp_ok('No SMTP dispatch record or system-mail activation exists',(int)query('SELECT COUNT(*) FROM '.table('mail_submission_dispatches'))->fetchColumn()===0&&setting('email_mode')==='disabled'&&setting('smtp_host')==='');
    rp_ok('All reply modes delegate to current mailbox authority',staff_action_permission('mail_message_reply')===null&&staff_action_permission('mail_message_reply_all')===null&&staff_action_permission('mail_message_forward')===null);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/mail-replies-native-results.json',json_encode(['passed'=>$passed,'scope'=>'Native MariaDB protected source drafts, synthetic successful-read journals, current authority, audit/race and retained ordinary-code recovery barriers. No real IMAP, SMTP, HTTP, packaged recovery or UI acceptance.','checks'=>$checks],JSON_PRETTY_PRINT)."\n");echo ($passed?'PASS':'FAIL').': '.count($checks)." native reply draft checks\n";exit($passed?0:1);
