<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);

define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require __DIR__.'/../foundation/mail-compose/CopiedDraftSource.php';

use CajunWebPro\Foundation\MailCompose\CopiedDraftSource;

$GLOBALS['copyAdapter']=['existing'=>false,'read_code'=>'ok','existing_calls'=>0,'account_calls'=>0,'read_calls'=>[],'create_calls'=>[]];
$sourceId=str_repeat('a',32);$senderId=str_repeat('b',32);$readId=str_repeat('c',32);$draftId=str_repeat('d',32);

function mail_drafts_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/D',$id))throw new InvalidArgumentException('Draft unavailable.');}
function mail_message_identity(mixed $validity,mixed $uid):void {foreach([$validity,$uid] as $value)if(!is_string($value)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$value))throw new InvalidArgumentException('Invalid message.');}
function mail_drafts_account(string $id,?int $revision=null):array {global $senderId;$GLOBALS['copyAdapter']['account_calls']++;if($id!==$senderId||$revision!==4)throw new RuntimeException('Changed sender.');return ['id'=>$id,'revision'=>4,'label'=>'Synthetic sender','address'=>'sender@example.invalid'];}
function mail_reply_drafts_existing(string $id,string $source,int $revision,string $validity,string $uid,string $mode,string $sender,int $senderRevision):?string {
    $GLOBALS['copyAdapter']['existing_calls']++;
    return $GLOBALS['copyAdapter']['existing']?$id:null;
}
function copy_adapter_raw():string {return "From: Source <source@example.invalid>\r\nTo: sender@example.invalid, other@example.invalid\r\nBcc: NEVER_PERSIST_COPY_RAW@example.invalid\r\nSubject: Synthetic copy\r\nMessage-ID: <copy@example.invalid>\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSynthetic copied body.";}
function mail_message_copy_source(string $id,int $revision,string $validity,string $uid,string $mode,?string $self=null,?callable $resolver=null,?callable $transport=null,?string $noticeHandle=null):array {
    global $sourceId,$readId;$GLOBALS['copyAdapter']['read_calls'][]=compact('id','revision','validity','uid','mode','self','noticeHandle');
    $result=['code'=>$GLOBALS['copyAdapter']['read_code'],'request_id'=>$readId,'account_id'=>$sourceId,'account_revision'=>2,'actor_id'=>7,'uidvalidity'=>'9','uid'=>'44','notice_handle'=>$noticeHandle,'loaded_at'=>'2026-11-11 00:00:00','content'=>$GLOBALS['copyAdapter']['read_code']==='ok'?['subject'=>'Synthetic copy','text'=>'Synthetic copied body.']:null];
    $source=$result['code']==='ok'?CopiedDraftSource::fromRead(7,$sourceId,2,'9','44',$readId,$mode,copy_adapter_raw(),$self):null;
    return ['result'=>$result,'source'=>$source];
}
function mail_reply_drafts_create(string $id,string $sender,int $senderRevision,CopiedDraftSource $source):string {$GLOBALS['copyAdapter']['create_calls'][]=compact('id','sender','senderRevision','source');return $id;}

require CWP_CODE_ROOT.'/app/mail/copied_drafts.php';

$adapterChecks=[];
function copy_adapter_check(string $name,callable $test):void {global $adapterChecks;try{$ok=$test()===true;}catch(Throwable $e){$ok=false;$error=get_class($e).': '.$e->getMessage();}$adapterChecks[]=['name'=>$name,'passed'=>$ok]+(isset($error)?['error'=>$error]:[]);}
function copy_adapter_reset():void {$GLOBALS['copyAdapter']=['existing'=>false,'read_code'=>'ok','existing_calls'=>0,'account_calls'=>0,'read_calls'=>[],'create_calls'=>[]];}
function copy_adapter_denied(callable $test):bool {try{$test();return false;}catch(InvalidArgumentException|RuntimeException){return true;}}

copy_adapter_check('exact replay returns before sender lookup mailbox read or mutation',function()use($draftId,$sourceId,$senderId){copy_adapter_reset();$GLOBALS['copyAdapter']['existing']=true;$r=mail_copied_draft_create($draftId,$sourceId,2,'9','44','reply',$senderId,4);return $r===['state'=>'existing','draft_id'=>$draftId,'result'=>null]&&$GLOBALS['copyAdapter']['existing_calls']===1&&$GLOBALS['copyAdapter']['account_calls']===0&&$GLOBALS['copyAdapter']['read_calls']===[]&&$GLOBALS['copyAdapter']['create_calls']===[];});
copy_adapter_check('fresh reply performs one derived read and one protected create',function()use($draftId,$sourceId,$senderId){copy_adapter_reset();$r=mail_copied_draft_create($draftId,$sourceId,2,'9','44','reply',$senderId,4);$call=$GLOBALS['copyAdapter']['create_calls'][0]??null;return $r['state']==='created'&&$r['draft_id']===$draftId&&count($GLOBALS['copyAdapter']['read_calls'])===1&&count($GLOBALS['copyAdapter']['create_calls'])===1&&$call['source']->draft->mode==='reply';});
copy_adapter_check('reply-all uses the exact current sender address for self exclusion',function()use($draftId,$sourceId,$senderId){copy_adapter_reset();mail_copied_draft_create($draftId,$sourceId,2,'9','44','reply-all',$senderId,4);$read=$GLOBALS['copyAdapter']['read_calls'][0];$source=$GLOBALS['copyAdapter']['create_calls'][0]['source'];return $read['self']==='sender@example.invalid'&&$source->draft->content->to===['source@example.invalid','other@example.invalid'];});
copy_adapter_check('forward uses no self context and remains recipientless',function()use($draftId,$sourceId,$senderId){copy_adapter_reset();mail_copied_draft_create($draftId,$sourceId,2,'9','44','forward',$senderId,4);$read=$GLOBALS['copyAdapter']['read_calls'][0];$source=$GLOBALS['copyAdapter']['create_calls'][0]['source'];return $read['self']===null&&$source->draft->content->to===[];});
copy_adapter_check('finite mailbox failure returns sanitized result and creates nothing',function()use($draftId,$sourceId,$senderId){copy_adapter_reset();$GLOBALS['copyAdapter']['read_code']='timeout';$r=mail_copied_draft_create($draftId,$sourceId,2,'9','44','reply',$senderId,4);return $r['state']==='read_failed'&&$r['draft_id']===null&&$r['result']['code']==='timeout'&&$r['result']['content']===null&&$GLOBALS['copyAdapter']['create_calls']===[];});
copy_adapter_check('invalid action is refused before sender lookup or mailbox read',function()use($draftId,$sourceId,$senderId){copy_adapter_reset();$ok=copy_adapter_denied(fn()=>mail_copied_draft_create($draftId,$sourceId,2,'9','44','send',$senderId,4));return $ok&&$GLOBALS['copyAdapter']['account_calls']===0&&$GLOBALS['copyAdapter']['read_calls']===[];});
copy_adapter_check('hidden raw marker never enters returned result or derived protected source',function()use($draftId,$sourceId,$senderId){copy_adapter_reset();$r=mail_copied_draft_create($draftId,$sourceId,2,'9','44','reply',$senderId,4);$derived=$GLOBALS['copyAdapter']['create_calls'][0]['source'];return !str_contains(json_encode($r,JSON_THROW_ON_ERROR),'NEVER_PERSIST_COPY_RAW')&&!str_contains(json_encode($derived,JSON_THROW_ON_ERROR),'NEVER_PERSIST_COPY_RAW');});

copy_adapter_check('ordinary display read keeps its established seven-parameter signature',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/app/mail/messages.php');return str_contains($source,'function mail_message_read(string $id,int $revision,string $validity,string $uid,?callable $resolver=null,?callable $transport=null,?string $noticeHandle=null):array');
});
copy_adapter_check('copy read has a finite mode/self API and no retain-source flag',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/app/mail/messages.php');
    return str_contains($source,'function mail_message_copy_source(')&&!str_contains($source,'retainSource')&&!str_contains($source,"content['source']")&&!str_contains($source,'usleep(')&&!str_contains($source,'sleep(');
});
copy_adapter_check('derived source construction precedes terminal journal and raw transport cleanup precedes return',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/app/mail/messages.php');$derive=strpos($source,'CopiedDraftSource::fromRead');$cleanup=strpos($source,'finally {unset($secrets,$response);}',(int)$derive);$record=strpos($source,'mail_message_record($started',(int)$cleanup);$return=strpos($source,"return ['result'=>\$result,'source'=>\$source]",(int)$record);
    return $derive!==false&&$cleanup!==false&&$record!==false&&$return!==false&&$derive<$cleanup&&$cleanup<$record&&$record<$return;
});
copy_adapter_check('unsupported copy metadata is raised only after successful read recording',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/app/mail/messages.php');$derive=strpos($source,'CopiedDraftSource::fromRead');$record=strpos($source,'mail_message_record($started',(int)$derive);$failure=strpos($source,'if($copyUnsupported)throw new InvalidArgumentException',(int)$record);return $record!==false&&$failure!==false&&$record<$failure;
});
copy_adapter_check('all failure catches clear any transient derived source',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/app/mail/messages.php');return substr_count($source,'$source=null;$copyUnsupported=false;')===3;
});
copy_adapter_check('sender query filters current Send grants before its page boundary',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/app/mail/drafts.php');$send=strpos($source,'g.can_send=1');$limit=strpos($source,'LIMIT 21 OFFSET',$send);return $send!==false&&$limit!==false&&$send<$limit;
});
copy_adapter_check('copy controls exist before display result and expose all three finite actions',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/admin/views/mail_message.php');$controls=strpos($source,'data-mail-copy-controls');$result=strpos($source,'if($result===null)');return $controls!==false&&$result!==false&&$controls<$result&&str_contains($source,'mail_message_reply')&&str_contains($source,'mail_message_reply_all')&&str_contains($source,'mail_message_forward');
});
copy_adapter_check('sender pagination remains reachable independently of loaded message content',function(){
    $controller=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/mail_message.php');$view=file_get_contents(CWP_CODE_ROOT.'/admin/views/mail_message.php');return !str_contains($controller,"if(\$result!==null&&\$result['code']==='ok'&&mail_replies_ready()")&&str_contains($view,'Previous sending accounts')&&str_contains($view,'More sending accounts');
});
copy_adapter_check('read-only mailbox access remains available while copy controls require global Send',function(){
    $controller=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/mail_message.php');$view=file_get_contents(CWP_CODE_ROOT.'/admin/views/mail_message.php');return str_contains($controller,"\$mailCopyAllowed=\$mailCopyReady&&staff_can('mail.send',\$actor)")&&str_contains($controller,'if($mailCopyAllowed){')&&str_contains($view,'if($mailCopyAllowed)')&&str_contains($view,'Current email Send permission is required');
});
copy_adapter_check('controller validates exact route draft and sender before one adapter delegation',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/mail_message.php');return str_contains($source,"foreach(\$route as \$key=>\$value)if(posted(\$key,32)!==\$value)")&&str_contains($source,"mail_drafts_id(\$draftId)")&&str_contains($source,"mail_drafts_reference(posted('sender',43))")&&substr_count($source,'mail_copied_draft_create(')===1;
});
copy_adapter_check('copy actions use internal current authority instead of a coarse global permission',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/app/staff/accounts.php');return str_contains($source,"['mail_message_read','mail_message_reply','mail_message_reply_all','mail_message_forward']")&&str_contains($source,'return null; // Exact current Read and chosen Send');
});
copy_adapter_check('slice contains no submission MIME SMTP or send invocation',function(){
    $paths=['app/mail/messages.php','app/mail/copied_drafts.php','admin/controllers/mail_message.php','admin/views/mail_message.php'];$source='';foreach($paths as $path)$source.=file_get_contents(CWP_CODE_ROOT.'/'.$path);return !preg_match('/mail_submissions|PreparedMime|smtp|send_message\s*\(/i',$source);
});

$adapterPassed=!array_filter($adapterChecks,fn($row)=>!$row['passed']);
echo json_encode(['scope'=>'Dedicated copied-draft creation adapter orchestration and shipping-source boundary checks with synthetic derived MIME; no SQL, DNS, IMAP, HTTP, browser, submission, SMTP or sending acceptance','passed'=>$adapterPassed,'checks'=>$adapterChecks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($adapterPassed?0:1);
