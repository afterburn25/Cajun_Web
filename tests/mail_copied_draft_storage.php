<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);

define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');

final class CopiedStorageResult {
    public function __construct(private int|string|null $value=null) {}
    public function fetchColumn():int|string|false {return $this->value??false;}
}

$GLOBALS['copiedState']=[
    'settings'=>['mail_replies_schema_version'=>'1'],
    'actor'=>['id'=>7,'role'=>'administrator','active'=>true,'global_read'=>true,'global_send'=>true],
    'support'=>false,'source_read'=>true,'sender_send'=>true,
    'accounts'=>[], 'drafts'=>[], 'replies'=>[], 'reads'=>[], 'revisions'=>[], 'audit'=>[], 'queries'=>[],
];

function table(string $name):string {return $name;}
function staff_locked(callable $operation):mixed {
    if($GLOBALS['copiedState']['support'])throw new RuntimeException('Support context is read-only.');
    return $operation();
}
function staff_transaction(callable $operation):mixed {
    $before=unserialize(serialize($GLOBALS['copiedState']));
    try{return $operation();}catch(Throwable $e){$GLOBALS['copiedState']=$before;throw $e;}
}
function mail_drafts_actor():array {
    $actor=$GLOBALS['copiedState']['actor'];
    if(!$actor['active']||!$actor['global_send'])throw new RuntimeException('Current email Send permission is required.');
    return $actor;
}
function mail_drafts_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/D',$id))throw new InvalidArgumentException('Draft unavailable.');}
function mail_message_identity(mixed $validity,mixed $uid):void {
    foreach([$validity,$uid] as $number)if(!is_string($number)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$number)||(strlen($number)===10&&strcmp($number,'4294967295')>0))throw new InvalidArgumentException('Open a message from the current inbox headers.');
}
function mail_message_authority(string $id,?int $revision=null,bool $ready=true):array {
    $state=$GLOBALS['copiedState'];$actor=$state['actor'];$row=$state['accounts'][$id]??null;
    if(!$actor['active']||!$actor['global_read']||!$state['source_read']||!$row||$row['kind']!=='source')throw new RuntimeException('Current source-message authority is required.');
    if($revision!==null&&$revision!==$row['revision'])throw new RuntimeException('Source account changed.');
    return [$actor,$row];
}
function mail_drafts_account(string $id,?int $revision=null):array {
    $state=$GLOBALS['copiedState'];$actor=$state['actor'];$row=$state['accounts'][$id]??null;
    if(!$actor['active']||!$actor['global_send']||!$state['sender_send']||!$row||$row['kind']!=='sender')throw new RuntimeException('Current Send access to this email account is required.');
    if($revision!==null&&$revision!==$row['revision'])throw new RuntimeException('The sending account changed.');
    return ['id'=>$id,'revision'=>$row['revision'],'label'=>$row['label'],'address'=>$row['address']];
}
function mail_drafts_row(string $id,int $actor,bool $lock=false):array {
    mail_drafts_id($id);$row=$GLOBALS['copiedState']['drafts'][$id]??null;
    if(!$row||(int)$row['owner_id']!==$actor)throw new InvalidArgumentException('Draft unavailable.');
    return $row;
}
function mail_drafts_snapshot(array $row,int $actor,string $action):void {
    $GLOBALS['copiedState']['revisions'][]=['draft_id'=>$row['id'],'revision'=>(int)$row['revision'],'account_id'=>$row['account_id'],'account_revision'=>(int)$row['account_revision'],'status'=>$row['status'],'action'=>$action,'actor_id'=>$actor,'recorded_at'=>'2026-11-11 00:00:00'];
    $GLOBALS['copiedState']['audit'][]=['actor_id'=>$actor,'action'=>$action,'detail'=>'Draft '.$row['id'].' revision '.$row['revision']];
}
function one(string $sql,array $params=[]):?array {
    $state=$GLOBALS['copiedState'];
    if(str_contains($sql,'FROM settings'))return isset($state['settings'][$params[0]])?['value'=>$state['settings'][$params[0]]]:null;
    if(str_contains($sql,'FROM mail_message_reads q JOIN mail_message_results'))return $state['reads'][$params[0]]??null;
    if(str_contains($sql,'FROM mail_reply_drafts'))return $state['replies'][$params[0]]??null;
    if(str_contains($sql,'FROM mail_drafts')){
        $row=$state['drafts'][$params[0]]??null;
        if($row&&isset($params[1])&&(int)$row['owner_id']!==(int)$params[1])return null;
        return $row;
    }
    throw new LogicException('Unhandled one() SQL: '.$sql);
}
function all(string $sql,array $params=[]):array {
    $state=$GLOBALS['copiedState'];
    if(str_contains($sql,'FROM mail_drafts d JOIN mail_reply_drafts r')){
        [$owner,$account,$status]=$params;$rows=[];
        foreach($state['replies'] as $id=>$reply){$draft=$state['drafts'][$id];if((int)$draft['owner_id']===(int)$owner&&$draft['account_id']===$account&&$reply['status']===$status)$rows[]=['id'=>$id,'revision'=>$draft['revision'],'updated_at'=>$draft['updated_at']];}
        usort($rows,fn($a,$b)=>[$b['updated_at'],$b['id']]<=>[$a['updated_at'],$a['id']]);return array_slice($rows,0,21);
    }
    if(str_contains($sql,'FROM mail_draft_revisions'))return array_values(array_filter($state['revisions'],fn($row)=>$row['draft_id']===$params[0]));
    throw new LogicException('Unhandled all() SQL: '.$sql);
}
function query(string $sql,array $params=[]):CopiedStorageResult {
    $state=&$GLOBALS['copiedState'];$state['queries'][]=['sql'=>$sql,'params'=>$params];
    if(str_starts_with($sql,'INSERT INTO mail_drafts ')){
        [$id,$owner,$account,$accountRevision,$fingerprint,$created,$updated]=$params;
        $state['drafts'][$id]=['id'=>$id,'owner_id'=>$owner,'account_id'=>$account,'account_revision'=>$accountRevision,'revision'=>1,'status'=>'source','create_fingerprint'=>$fingerprint,'to_json'=>'[]','cc_json'=>'[]','bcc_json'=>'[]','subject'=>'Copied message draft','body'=>'','created_at'=>$created,'updated_at'=>$updated];return new CopiedStorageResult();
    }
    if(str_starts_with($sql,'INSERT INTO mail_reply_drafts ')){
        [$id,$source,$read,$status,$origin,$content,$fingerprint]=$params;
        $state['replies'][$id]=['id'=>$id,'source_account_id'=>$source,'source_read_id'=>$read,'revision'=>1,'status'=>$status,'origin_json'=>$origin,'content_json'=>$content,'content_fingerprint'=>$fingerprint];return new CopiedStorageResult();
    }
    if(str_starts_with($sql,'UPDATE mail_drafts SET account_id=')){
        [$account,$accountRevision,$revision,$updated,$id]=$params;$state['drafts'][$id]['account_id']=$account;$state['drafts'][$id]['account_revision']=$accountRevision;$state['drafts'][$id]['revision']=$revision;$state['drafts'][$id]['updated_at']=$updated;return new CopiedStorageResult();
    }
    if(str_starts_with($sql,'UPDATE mail_reply_drafts SET revision=')){
        [$revision,$status,$content,$fingerprint,$id]=$params;$state['replies'][$id]['revision']=$revision;$state['replies'][$id]['status']=$status;$state['replies'][$id]['content_json']=$content;$state['replies'][$id]['content_fingerprint']=$fingerprint;return new CopiedStorageResult();
    }
    throw new LogicException('Unhandled query() SQL: '.$sql);
}

require CWP_CODE_ROOT.'/app/mail/replies.php';

use CajunWebPro\Foundation\MailCompose\{CopiedDraftSource,DraftContent,DraftOrigin};

$storageChecks=[];
function storage_check(string $name,callable $test):void {
    global $storageChecks;
    try {$ok=$test()===true;}catch(Throwable $e){$ok=false;$error=get_class($e).': '.$e->getMessage();}
    $storageChecks[]=['name'=>$name,'passed'=>$ok]+(isset($error)?['error'=>$error]:[]);
}
function storage_denied(callable $test):bool {
    try{$test();return false;}catch(InvalidArgumentException|RuntimeException $e){return true;}
}
function storage_snapshot():string {
    $state=$GLOBALS['copiedState'];unset($state['queries']);return json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
}
function storage_raw(string $subject='PRIVATE_COPIED_SUBJECT',string $body='PRIVATE_COPIED_BODY'):string {
    return "From: Source <source@example.invalid>\r\nBcc: NEVER_PERSIST_RAW_MARKER@example.invalid\r\nSubject: ".$subject."\r\nMessage-ID: <source@example.invalid>\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n".$body;
}
function storage_source(int $actor=7,string $mode='reply',string $raw=''):CopiedDraftSource {
    global $sourceAccount,$readId;$raw=$raw===''?storage_raw():$raw;
    return CopiedDraftSource::fromRead($actor,$sourceAccount,1,'7','44',$readId,$mode,$raw,$mode==='reply-all'?'sender@example.invalid':null);
}

$sourceAccount=str_repeat('a',32);$senderAccount=str_repeat('b',32);$otherSender=str_repeat('c',32);$readId=str_repeat('d',32);$draftId=str_repeat('e',32);
$GLOBALS['copiedState']['accounts']=[
    $sourceAccount=>['id'=>$sourceAccount,'revision'=>1,'kind'=>'source','label'=>'Source','address'=>'source@example.invalid'],
    $senderAccount=>['id'=>$senderAccount,'revision'=>1,'kind'=>'sender','label'=>'Sender','address'=>'sender@example.invalid'],
    $otherSender=>['id'=>$otherSender,'revision'=>1,'kind'=>'sender','label'=>'Other','address'=>'other@example.invalid'],
];
$GLOBALS['copiedState']['reads'][$readId]=['id'=>$readId,'outcome'=>'success','result_code'=>'ok','message_bytes'=>strlen(storage_raw()),'actor_id'=>7,'account_id'=>$sourceAccount,'account_revision'=>1,'folder'=>'INBOX','uidvalidity'=>'7','uid'=>'44'];

storage_check('fresh identity resolves no copied draft without writing',function()use($draftId,$sourceAccount,$senderAccount){$before=storage_snapshot();return mail_reply_drafts_existing($draftId,$sourceAccount,1,'7','44','reply',$senderAccount,1)===null&&storage_snapshot()===$before;});
storage_check('creation stores a content-free legacy placeholder and one canonical protected row',function()use($draftId,$senderAccount){
    mail_reply_drafts_create($draftId,$senderAccount,1,storage_source());$state=$GLOBALS['copiedState'];$base=$state['drafts'][$draftId];$reply=$state['replies'][$draftId];
    $content=DraftOrigin::contentFromJson($reply['content_json']);
    return $base['status']==='source'&&$base['subject']==='Copied message draft'&&$base['body']===''&&$base['to_json']==='[]'&&$base['cc_json']==='[]'&&$base['bcc_json']==='[]'
        &&$content->subject==='Re: PRIVATE_COPIED_SUBJECT'&&$content->body!==''&&!str_contains($reply['origin_json'].$reply['content_json'],'NEVER_PERSIST_RAW_MARKER');
});
storage_check('detail returns derived content only under current source Read and sender Send',function()use($draftId,$sourceAccount,$senderAccount){
    $draft=mail_reply_drafts_get($draftId);
    return $draft['owner_id']===7&&$draft['account_id']===$senderAccount&&$draft['origin']['source_account_id']===$sourceAccount
        &&$draft['to']===['source@example.invalid']&&$draft['bcc']===[]&&$draft['subject']==='Re: PRIVATE_COPIED_SUBJECT';
});
storage_check('revision and audit metadata contain no copied recipients subject or body',function(){
    $metadata=json_encode([$GLOBALS['copiedState']['revisions'],$GLOBALS['copiedState']['audit']],JSON_THROW_ON_ERROR);
    return !str_contains($metadata,'PRIVATE_COPIED')&&!str_contains($metadata,'source@example.invalid')&&!str_contains($metadata,'NEVER_PERSIST_RAW_MARKER');
});
storage_check('exact create replay and exact form replay are write-free',function()use($draftId,$sourceAccount,$senderAccount){
    $before=storage_snapshot();return mail_reply_drafts_create($draftId,$senderAccount,1,storage_source())===$draftId
        &&mail_reply_drafts_existing($draftId,$sourceAccount,1,'7','44','reply',$senderAccount,1)===$draftId&&storage_snapshot()===$before;
});
storage_check('conflicting mode replay preserves the existing draft',function()use($draftId,$sourceAccount,$senderAccount){$before=storage_snapshot();return storage_denied(fn()=>mail_reply_drafts_existing($draftId,$sourceAccount,1,'7','44','forward',$senderAccount,1))&&storage_snapshot()===$before;});
storage_check('changed repeated source content cannot overwrite the existing draft',function()use($draftId,$senderAccount){$before=storage_snapshot();return storage_denied(fn()=>mail_reply_drafts_create($draftId,$senderAccount,1,storage_source(7,'reply',storage_raw('CHANGED_PRIVATE_SUBJECT'))))&&storage_snapshot()===$before;});
storage_check('journal byte mismatch blocks a new copied draft atomically',function()use($senderAccount){$id=str_repeat('f',32);$before=storage_snapshot();return storage_denied(fn()=>mail_reply_drafts_create($id,$senderAccount,1,storage_source(7,'reply',storage_raw('PRIVATE_COPIED_SUBJECT','PRIVATE_COPIED_BODY_CHANGED'))))&&storage_snapshot()===$before;});
storage_check('another actor cannot use a transient source or open the private draft',function()use($draftId,$senderAccount){
    $before=storage_snapshot();$GLOBALS['copiedState']['actor']['id']=8;
    $deniedCreate=storage_denied(fn()=>mail_reply_drafts_create(str_repeat('1',32),$senderAccount,1,storage_source(7)));
    $deniedRead=storage_denied(fn()=>mail_reply_drafts_get($draftId));$GLOBALS['copiedState']['actor']['id']=7;
    return $deniedCreate&&$deniedRead&&storage_snapshot()===$before;
});
storage_check('revoked source Read blocks detail edit and discard without changing storage',function()use($draftId,$senderAccount){
    $GLOBALS['copiedState']['source_read']=false;$before=storage_snapshot();
    $ok=storage_denied(fn()=>mail_reply_drafts_get($draftId))
        &&storage_denied(fn()=>mail_reply_drafts_save($draftId,1,$senderAccount,1,new DraftContent([],[],[],'Edit','Body')))
        &&storage_denied(fn()=>mail_reply_drafts_discard($draftId,1))&&storage_snapshot()===$before;
    $GLOBALS['copiedState']['source_read']=true;return $ok;
});
storage_check('revoked source Read list exposes only a content-free unavailable label',function()use($senderAccount){
    $GLOBALS['copiedState']['source_read']=false;$list=mail_reply_drafts_list($senderAccount);$GLOBALS['copiedState']['source_read']=true;
    $json=json_encode($list,JSON_THROW_ON_ERROR);return count($list)===1&&$list[0]['unavailable']&&$list[0]['subject']==='Copied message draft unavailable'&&!str_contains($json,'PRIVATE_COPIED')&&!str_contains($json,'source@example.invalid');
});
storage_check('revoked sender Send blocks detail without changing storage',function()use($draftId){$GLOBALS['copiedState']['sender_send']=false;$before=storage_snapshot();$ok=storage_denied(fn()=>mail_reply_drafts_get($draftId))&&storage_snapshot()===$before;$GLOBALS['copiedState']['sender_send']=true;return $ok;});
storage_check('support context blocks copied draft access without changing storage',function()use($draftId){$GLOBALS['copiedState']['support']=true;$before=storage_snapshot();$ok=storage_denied(fn()=>mail_reply_drafts_get($draftId))&&storage_snapshot()===$before;$GLOBALS['copiedState']['support']=false;return $ok;});
storage_check('changed content is detected by its protected fingerprint',function()use($draftId){$saved=$GLOBALS['copiedState']['replies'][$draftId]['content_json'];$GLOBALS['copiedState']['replies'][$draftId]['content_json']=str_replace('PRIVATE_COPIED_BODY','CHANGED_PRIVATE_BODY',$saved);$ok=storage_denied(fn()=>mail_reply_drafts_get($draftId));$GLOBALS['copiedState']['replies'][$draftId]['content_json']=$saved;return $ok;});
storage_check('changed origin is detected against the immutable placeholder fingerprint',function()use($draftId){$saved=$GLOBALS['copiedState']['replies'][$draftId]['origin_json'];$GLOBALS['copiedState']['replies'][$draftId]['origin_json']=str_replace('"uid":"44"','"uid":"45"',$saved);$ok=storage_denied(fn()=>mail_reply_drafts_get($draftId));$GLOBALS['copiedState']['replies'][$draftId]['origin_json']=$saved;return $ok;});
storage_check('changed stored fingerprint is detected',function()use($draftId){$saved=$GLOBALS['copiedState']['replies'][$draftId]['content_fingerprint'];$GLOBALS['copiedState']['replies'][$draftId]['content_fingerprint']=str_repeat('0',64);$ok=storage_denied(fn()=>mail_reply_drafts_get($draftId));$GLOBALS['copiedState']['replies'][$draftId]['content_fingerprint']=$saved;return $ok;});
storage_check('save requires explicit sender review and optimistic current revision',function()use($draftId,$otherSender){
    $content=new DraftContent(['client@example.invalid'],[],[],'Edited subject','Edited body');$before=storage_snapshot();
    if(!storage_denied(fn()=>mail_reply_drafts_save($draftId,1,$otherSender,1,$content))||storage_snapshot()!==$before)return false;
    mail_reply_drafts_save($draftId,1,$otherSender,1,$content,true);$after=mail_reply_drafts_get($draftId);
    $saved=storage_snapshot();$stale=storage_denied(fn()=>mail_reply_drafts_save($draftId,1,$otherSender,1,$content))&&storage_snapshot()===$saved;
    return $stale&&$after['revision']===2&&$after['account_id']===$otherSender&&$after['body']==='Edited body';
});
storage_check('discard retains protected content and immutable origin but refuses revival',function()use($draftId,$otherSender){
    $origin=mail_reply_drafts_get($draftId)['origin'];mail_reply_drafts_discard($draftId,2);$discarded=mail_reply_drafts_get($draftId);$before=storage_snapshot();
    return $discarded['status']==='discarded'&&$discarded['revision']===3&&$discarded['body']==='Edited body'&&$discarded['origin']===$origin
        &&storage_denied(fn()=>mail_reply_drafts_save($draftId,3,$otherSender,1,new DraftContent([],[],[],'Revive','Body')))&&storage_snapshot()===$before;
});
storage_check('slice defines no submission MIME SMTP or send path',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/app/mail/replies.php');
    return !function_exists('mail_reply_drafts_for_submission')&&!preg_match('/mail_submissions|PreparedMime|smtp|send_message/i',$source);
});
storage_check('query parameters never contain raw MIME or its hidden marker',function(){
    $queries=json_encode($GLOBALS['copiedState']['queries'],JSON_THROW_ON_ERROR);
    return !str_contains($queries,'NEVER_PERSIST_RAW_MARKER')&&!str_contains($queries,"Bcc:")&&!str_contains($queries,'Content-Type:');
});

$storagePassed=!array_filter($storageChecks,fn($row)=>!$row['passed']);
echo json_encode([
    'scope'=>'In-memory exact-service contract for protected copied-draft storage, current authorization, replay, tamper, privacy and optimistic writes; no native SQL, mailbox, HTTP, UI, submission, SMTP or sending acceptance',
    'passed'=>$storagePassed,'checks'=>$storageChecks,
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($storagePassed?0:1);
