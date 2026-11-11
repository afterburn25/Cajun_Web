<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);

define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/admin/controllers/mail_drafts.php';

$adminChecks=[];
function copied_admin_check(string $name,callable $test):void {
    global $adminChecks;
    try{$ok=$test()===true;}catch(Throwable $e){$ok=false;$error=get_class($e).': '.$e->getMessage();}
    $adminChecks[]=['name'=>$name,'passed'=>$ok]+(isset($error)?['error'=>$error]:[]);
}
function copied_admin_denied(callable $test):bool {
    try{$test();return false;}catch(RuntimeException){return true;}
}

copied_admin_check('missing draft has no copied-storage classification',fn()=>mail_drafts_admin_copied(null,false)===false);
copied_admin_check('ordinary saved draft remains on the ordinary storage path',fn()=>mail_drafts_admin_copied(['status'=>'draft'],false)===false);
copied_admin_check('ordinary discarded draft remains on the ordinary storage path',fn()=>mail_drafts_admin_copied(['status'=>'discarded'],false)===false);
copied_admin_check('source placeholder selects only protected copied storage',fn()=>mail_drafts_admin_copied(['status'=>'source'],true)===true);
copied_admin_check('missing stored status fails closed',fn()=>copied_admin_denied(fn()=>mail_drafts_admin_copied(['id'=>str_repeat('a',32)],false)));
copied_admin_check('unknown future stored status fails closed',fn()=>copied_admin_denied(fn()=>mail_drafts_admin_copied(['status'=>'future'],false)));
copied_admin_check('protected row with ordinary status fails closed before ordinary dispatch',fn()=>copied_admin_denied(fn()=>mail_drafts_admin_copied(['status'=>'draft'],true)));
copied_admin_check('source status with missing protected row fails closed before protected dispatch',fn()=>copied_admin_denied(fn()=>mail_drafts_admin_copied(['status'=>'source'],false)));
copied_admin_check('protected row without a base draft fails closed',fn()=>copied_admin_denied(fn()=>mail_drafts_admin_copied(null,true)));

copied_admin_check('ordinary saved draft retains its existing submission-review link policy',fn()=>mail_drafts_admin_can_review_send(['status'=>'draft']));
copied_admin_check('copied saved draft cannot enter submission review in this held slice',fn()=>!mail_drafts_admin_can_review_send(['status'=>'draft','copied'=>true]));
copied_admin_check('discarded and missing drafts cannot enter submission review',fn()=>!mail_drafts_admin_can_review_send(['status'=>'discarded'])&&!mail_drafts_admin_can_review_send(null));

copied_admin_check('reply origin label is finite and generic',fn()=>mail_drafts_admin_origin_action('reply')==='replying to');
copied_admin_check('reply-all origin label is finite and generic',fn()=>mail_drafts_admin_origin_action('reply-all')==='replying to everyone on');
copied_admin_check('forward origin label is finite and generic',fn()=>mail_drafts_admin_origin_action('forward')==='forwarding');
copied_admin_check('unknown origin mode fails closed',fn()=>copied_admin_denied(fn()=>mail_drafts_admin_origin_action('reply-later')));

$omissions=[
    'subject_or_header_shortened'=>'a long subject or header was shortened',
    'plain_text_unavailable'=>'no plain-text body was available in the source',
    'additional_inline_parts'=>'additional inline message parts were not copied',
    'quoted_text_shortened'=>'quoted text was shortened to fit the draft',
];
copied_admin_check('every canonical omission has one finite display label',function()use($omissions){foreach($omissions as $flag=>$label)if(mail_drafts_admin_omission($flag)!==$label)return false;return true;});
copied_admin_check('unknown omission fails closed instead of echoing stored text',fn()=>copied_admin_denied(fn()=>mail_drafts_admin_omission('private_future_detail')));

copied_admin_check('controller selects protected get save discard history and combined-list services',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/mail_drafts.php');
    foreach(['mail_reply_drafts_get(','mail_reply_drafts_save(','mail_reply_drafts_discard(','mail_reply_drafts_history(','mail_drafts_combined_list('] as $call)if(!str_contains($source,$call))return false;
    return true;
});
copied_admin_check('owner setup uses the additive protected migration without direct schema execution',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/mail_drafts.php');
    return str_contains($source,"mail_replies_migrate(posted('current_password',200))")&&!str_contains($source,'db()->exec');
});
copied_admin_check('copied origin presentation exposes no account read or fingerprint identifiers',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/admin/views/mail_drafts.php');
    foreach(['source_account_id','source_revision','read_id','initial_account_id','initial_account_revision','origin_json','fingerprint'] as $private)if(str_contains($source,$private))return false;
    return str_contains($source,"esc(mail_drafts_admin_origin_action(")&&str_contains($source,"esc(\$origin['uid'])")&&str_contains($source,"array_map('mail_drafts_admin_omission'");
});
copied_admin_check('copied view explicitly discloses that submission and sending are unavailable',fn()=>str_contains(file_get_contents(CWP_CODE_ROOT.'/admin/views/mail_drafts.php'),'This held slice cannot submit or send a copied draft.'));
copied_admin_check('admin slice imports no message-read raw MIME SMTP or submission service',function(){
    $source=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/mail_drafts.php').file_get_contents(CWP_CODE_ROOT.'/admin/views/mail_drafts.php');
    return !preg_match('/mail_message_read|CopiedDraftSource|PreparedMime|mail_submissions|smtp/i',$source);
});

$adminPassed=!array_filter($adminChecks,fn($row)=>!$row['passed']);
echo json_encode([
    'scope'=>'Pure copied-draft admin dispatch and presentation policy plus exact source-boundary checks; no SQL, HTTP, browser, mailbox, raw MIME, submission, SMTP or sending acceptance',
    'passed'=>$adminPassed,'checks'=>$adminChecks,
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($adminPassed?0:1);
