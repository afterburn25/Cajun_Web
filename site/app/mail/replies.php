<?php
/** Protected copied-message drafts. No credentials, sockets, MIME submission or HTTP actions. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/mail/drafts.php';
require_once CWP_CODE_ROOT.'/app/mail/messages.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-compose/DraftOrigin.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_replies_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_replies_migrate.php';
use CajunWebPro\Foundation\MailCompose\{DraftContent,DraftOrigin,ReplyDraft};

function mail_replies_ready():bool{return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_replies_schema_version'])['value']??'')==='1';}
function mail_reply_drafts_actor():array{
    $actor=mail_drafts_actor();if(!mail_replies_ready())throw new RuntimeException('An owner must prepare protected Reply/Forward drafts first.');return $actor;
}
function mail_reply_drafts_source(int $actor,string $account,int $revision):void{
    [$current]=mail_message_authority($account,$revision);
    if((int)$current['id']!==$actor)throw new RuntimeException('Current source-message authority is required.');
}
/** Exact journal metadata is evidence of the read, not a reusable permission grant. */
function mail_reply_drafts_read_record(array $origin):array{
    $read=one('SELECT q.*,r.outcome,r.result_code,r.message_bytes FROM '.table('mail_message_reads').' q JOIN '.table('mail_message_results').' r ON r.id=q.id WHERE q.id=?',[$origin['read_id']]);
    if(!$read||$read['outcome']!=='success'||$read['result_code']!=='ok'||(int)$read['actor_id']!==$origin['author_id']||$read['account_id']!==$origin['source_account_id']||(int)$read['account_revision']!==$origin['source_revision']||$read['folder']!=='INBOX'||$read['uidvalidity']!==$origin['uidvalidity']||$read['uid']!==$origin['uid'])throw new RuntimeException('The copied draft has no matching successful source read.');
    return $read;
}
function mail_reply_drafts_fingerprint(array $base,DraftOrigin $origin,string $status,string $content):string{
    return hash('sha256',json_encode([$origin->fingerprint,$base['id'],(int)$base['owner_id'],$base['account_id'],(int)$base['account_revision'],(int)$base['revision'],$status,$content],JSON_THROW_ON_ERROR));
}
/** Internal locked projection; copied content is decoded only after current Read and Send. */
function mail_reply_drafts_load(string $id,array $actor,bool $lock=false):array{
    $base=mail_drafts_row($id,(int)$actor['id'],$lock);
    if($base['status']!=='source'||$base['subject']!=='Copied message draft'||$base['body']!==''||$base['to_json']!=='[]'||$base['cc_json']!=='[]'||$base['bcc_json']!=='[]')throw new RuntimeException('Protected draft placeholder is damaged. Existing data was preserved.');
    $row=one('SELECT * FROM '.table('mail_reply_drafts').' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);
    if(!$row)throw new RuntimeException('Protected draft content is unavailable. Existing data was preserved.');
    try{$origin=DraftOrigin::fromJson($row['origin_json']);}
    catch(Throwable){throw new RuntimeException('Protected draft origin is damaged. Existing data was preserved.');}
    $o=$origin->data;
    if($o['draft_id']!==$id||$o['author_id']!==(int)$actor['id']||$row['source_account_id']!==$o['source_account_id']||$row['source_read_id']!==$o['read_id']||!hash_equals($origin->fingerprint,$base['create_fingerprint'])||(int)$row['revision']!==(int)$base['revision']||(int)$base['revision']<1||(int)$base['revision']>=2147483647||!in_array($row['status'],['draft','discarded'],true))throw new RuntimeException('Protected draft identity is inconsistent. Existing data was preserved.');
    mail_reply_drafts_source((int)$actor['id'],$o['source_account_id'],$o['source_revision']);
    $sender=mail_drafts_account($base['account_id']);mail_reply_drafts_read_record($o);
    if(!hash_equals($row['content_fingerprint'],mail_reply_drafts_fingerprint($base,$origin,$row['status'],$row['content_json'])))throw new RuntimeException('Protected draft content changed. Existing data was preserved.');
    try{$content=DraftOrigin::contentFromJson($row['content_json']);}
    catch(Throwable){throw new RuntimeException('Protected draft content is damaged. Existing data was preserved.');}
    if((int)$base['revision']===1&&(!hash_equals($o['initial_content_sha256'],hash('sha256',$row['content_json']))||$base['account_id']!==$o['initial_account_id']||(int)$base['account_revision']!==$o['initial_account_revision']||$row['status']!=='draft'))throw new RuntimeException('Protected draft creation is inconsistent. Existing data was preserved.');
    return ['base'=>$base,'row'=>$row,'origin'=>$origin,'content'=>$content,'sender'=>$sender];
}
function mail_reply_drafts_get(string $id):array{
    return staff_locked(function()use($id):array{
        $loaded=mail_reply_drafts_load($id,mail_reply_drafts_actor());$base=$loaded['base'];$content=$loaded['content'];
        return ['id'=>$base['id'],'owner_id'=>(int)$base['owner_id'],'account_id'=>$base['account_id'],'account_revision'=>(int)$base['account_revision'],'revision'=>(int)$base['revision'],'status'=>$loaded['row']['status'],'created_at'=>$base['created_at'],'updated_at'=>$base['updated_at'],'sender'=>$loaded['sender'],'sender_changed'=>(int)$base['account_revision']!==$loaded['sender']['revision'],'origin'=>$loaded['origin']->data]+DraftOrigin::contentPayload($content);
    });
}
function mail_reply_drafts_match(DraftOrigin $origin,string $source,int $revision,string $validity,string $uid,string $mode,string $sender,int $senderRevision):void{
    $o=$origin->data;
    if([$o['source_account_id'],$o['source_revision'],$o['uidvalidity'],$o['uid'],$o['mode'],$o['initial_account_id'],$o['initial_account_revision']]!==[$source,$revision,$validity,$uid,$mode,$sender,$senderRevision])throw new RuntimeException('This draft request was already used for a different message or sender.');
}
/** Before fetching again, an exact repeated form request resolves its existing private draft. */
function mail_reply_drafts_existing(string $id,string $source,int $revision,string $validity,string $uid,string $mode,string $sender,int $senderRevision):?string{
    return staff_locked(function()use($id,$source,$revision,$validity,$uid,$mode,$sender,$senderRevision):?string{
        $actor=mail_reply_drafts_actor();mail_drafts_id($id);mail_message_identity($validity,$uid);
        if(!in_array($mode,['reply','forward'],true))throw new InvalidArgumentException('Unsupported mail action.');
        mail_reply_drafts_source((int)$actor['id'],$source,$revision);mail_drafts_account($sender,$senderRevision);
        if(!one('SELECT id FROM '.table('mail_drafts').' WHERE id=?',[$id]))return null;
        $loaded=mail_reply_drafts_load($id,$actor);mail_reply_drafts_match($loaded['origin'],$source,$revision,$validity,$uid,$mode,$sender,$senderRevision);return $id;
    });
}
/** Internal only: caller supplies content prepared from the same freshly authorized read; never posted source fields. */
function mail_reply_drafts_create(string $id,string $sender,int $senderRevision,#[\SensitiveParameter] array $read,ReplyDraft $prepared):string{
    return staff_locked(fn()=>staff_transaction(function()use($id,$sender,$senderRevision,$read,$prepared):string{
        $actor=mail_reply_drafts_actor();mail_drafts_id($id);mail_drafts_account($sender,$senderRevision);
        if(($read['code']??null)!=='ok'||($read['actor_id']??null)!==(int)$actor['id']||!is_string($read['account_id']??null)||!is_int($read['account_revision']??null)||!is_string($read['uidvalidity']??null)||!is_string($read['uid']??null)||!is_string($read['request_id']??null)||!is_array($read['content']??null))throw new InvalidArgumentException('A successful current source-message read is required.');
        $origin=DraftOrigin::create($id,(int)$actor['id'],$read['account_id'],$read['account_revision'],$read['uidvalidity'],$read['uid'],$read['request_id'],$sender,$senderRevision,$prepared);$o=$origin->data;
        mail_reply_drafts_source((int)$actor['id'],$o['source_account_id'],$o['source_revision']);$journal=mail_reply_drafts_read_record($o);
        if((string)$journal['message_bytes']!==($read['content']['bytes']??null)||($read['content']['folder']??null)!=='INBOX'||($read['content']['uidvalidity']??null)!==$o['uidvalidity']||($read['content']['uid']??null)!==$o['uid'])throw new InvalidArgumentException('Source-message identity changed.');
        if(one('SELECT id FROM '.table('mail_drafts').' WHERE id=? FOR UPDATE',[$id])){
            $existing=mail_reply_drafts_load($id,$actor,true);mail_reply_drafts_match($existing['origin'],$o['source_account_id'],$o['source_revision'],$o['uidvalidity'],$o['uid'],$o['mode'],$sender,$senderRevision);
            if(!hash_equals($existing['origin']->data['initial_content_sha256'],$o['initial_content_sha256']))throw new RuntimeException('Repeated source-message content changed. The existing draft was preserved.');
            return $id;
        }
        $now=gmdate('Y-m-d H:i:s');$base=['id'=>$id,'owner_id'=>(int)$actor['id'],'account_id'=>$sender,'account_revision'=>$senderRevision,'revision'=>1];$content=DraftOrigin::contentJson($prepared->content);
        // Older application files see no copied content and refuse this non-draft status for edit/send.
        query('INSERT INTO '.table('mail_drafts')." (id,owner_id,account_id,account_revision,revision,status,create_fingerprint,to_json,cc_json,bcc_json,subject,body,created_at,updated_at) VALUES (?,?,?,?,1,'source',?,'[]','[]','[]','Copied message draft','',?,?)",[$id,$actor['id'],$sender,$senderRevision,$origin->fingerprint,$now,$now]);
        query('INSERT INTO '.table('mail_reply_drafts').' (id,source_account_id,source_read_id,revision,status,origin_json,content_json,content_fingerprint) VALUES (?,?,?,1,?,?,?,?)',[$id,$o['source_account_id'],$o['read_id'],'draft',$origin->json,$content,mail_reply_drafts_fingerprint($base,$origin,'draft',$content)]);
        mail_drafts_snapshot($base+['status'=>'draft'],(int)$actor['id'],$prepared->mode);return $id;
    }));
}
/** Internal paired write; the immutable origin and legacy placeholder never become copied content. */
function mail_reply_drafts_write(array $loaded,array $actor,string $account,int $accountRevision,DraftContent $content,string $status,string $action):void{
    $base=$loaded['base'];$base['revision']=(int)$base['revision']+1;$base['account_id']=$account;$base['account_revision']=$accountRevision;$json=DraftOrigin::contentJson($content);
    query('UPDATE '.table('mail_drafts').' SET account_id=?,account_revision=?,revision=?,updated_at=? WHERE id=?',[$account,$accountRevision,$base['revision'],gmdate('Y-m-d H:i:s'),$base['id']]);
    query('UPDATE '.table('mail_reply_drafts').' SET revision=?,status=?,content_json=?,content_fingerprint=? WHERE id=?',[$base['revision'],$status,$json,mail_reply_drafts_fingerprint($base,$loaded['origin'],$status,$json),$base['id']]);
    mail_drafts_snapshot(['status'=>$status]+$base,(int)$actor['id'],$action);
}
function mail_reply_drafts_save(string $id,int $revision,string $account,int $accountRevision,DraftContent $content,bool $confirmSenderChange=false):void{
    staff_locked(fn()=>staff_transaction(function()use($id,$revision,$account,$accountRevision,$content,$confirmSenderChange):void{
        $actor=mail_reply_drafts_actor();$loaded=mail_reply_drafts_load($id,$actor,true);$base=$loaded['base'];mail_drafts_account($account,$accountRevision);
        if($revision<1||$revision>=2147483646||(int)$base['revision']!==$revision||$loaded['row']['status']!=='draft')throw new RuntimeException('The draft changed. Reload before saving.');
        if(($base['account_id']!==$account||(int)$base['account_revision']!==$accountRevision)&&!$confirmSenderChange)throw new RuntimeException('Confirm the current sending account before saving this change.');
        mail_reply_drafts_write($loaded,$actor,$account,$accountRevision,$content,'draft','save');
    }));
}
function mail_reply_drafts_discard(string $id,int $revision):void{
    staff_locked(fn()=>staff_transaction(function()use($id,$revision):void{
        $actor=mail_reply_drafts_actor();$loaded=mail_reply_drafts_load($id,$actor,true);$base=$loaded['base'];
        if($revision<1||$revision>=2147483646||(int)$base['revision']!==$revision||$loaded['row']['status']!=='draft')throw new RuntimeException('The draft changed. Reload before discarding.');
        mail_reply_drafts_write($loaded,$actor,$base['account_id'],(int)$base['account_revision'],$loaded['content'],'discarded','discard');
    }));
}
function mail_reply_drafts_list(string $account,string $status='draft',int $page=1):array{
    return staff_locked(function()use($account,$status,$page):array{
        $actor=mail_reply_drafts_actor();mail_drafts_account($account);
        if(!in_array($status,['draft','discarded'],true))throw new InvalidArgumentException('Unknown draft filter.');$page=max(1,min($page,100000));
        $rows=all('SELECT d.id,d.revision,d.updated_at FROM '.table('mail_drafts').' d JOIN '.table('mail_reply_drafts').' r ON r.id=d.id WHERE d.owner_id=? AND d.account_id=? AND r.status=? ORDER BY d.updated_at DESC,d.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$actor['id'],$account,$status]);
        $result=[];
        foreach($rows as $row){
            try{$loaded=mail_reply_drafts_load($row['id'],$actor);$row['subject']=$loaded['content']->subject;$row['unavailable']=false;}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$row['subject']='Copied message draft unavailable';$row['unavailable']=true;}
            $result[]=$row;
        }
        return $result;
    });
}
function mail_reply_drafts_history(string $id,int $page=1):array{
    return staff_locked(function()use($id,$page):array{
        mail_reply_drafts_load($id,mail_reply_drafts_actor());$page=max(1,min($page,100000));
        return all('SELECT revision,account_id,account_revision,status,action,recorded_at FROM '.table('mail_draft_revisions').' WHERE draft_id=? ORDER BY revision DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
    });
}
