<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-compose/DraftOrigin.php';

use CajunWebPro\Foundation\MailCompose\{DraftOrigin,ReplyDraft,ReplyMetadata};
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;

$replyAllChecks=[];
function reply_all_check(string $name,callable $test):void {
    global $replyAllChecks;
    try {$ok=$test()===true;} catch(Throwable $e) {$ok=false;$error=get_class($e).': '.$e->getMessage();}
    $replyAllChecks[]=['name'=>$name,'passed'=>$ok]+(isset($error)?['error'=>$error]:[]);
}
function reply_all_denied(callable $test):bool {
    try {$test();return false;} catch(InvalidArgumentException|ReceivingFailure|JsonException) {return true;}
}
function reply_all_raw(string $extra='',string $body='Synthetic reply-all body.',string $subject='Synthetic reply all'):string {
    return "From: Author <Author@example.invalid>\r\n".
        "Date: Tue, 14 Oct 2025 10:00:00 +0000\r\n".
        'Subject: '.$subject."\r\n".
        "Message-ID: <Parent@Example.invalid>\r\n".
        $extra.
        "Content-Type: text/plain; charset=UTF-8\r\n\r\n".$body;
}
function reply_all_prepare(string $extra='',string $self='me@example.invalid'):ReplyDraft {
    return ReplyDraft::prepare('reply-all',reply_all_raw($extra),$self);
}

reply_all_check('ordinary parser initialization cannot narrow later reply-all metadata',function(){
    ReplyMetadata::decode("From: ordinary@example.invalid\r\n");
    $metadata=ReplyMetadata::decodeForReplyAll(
        "From: author@example.invalid\r\n".
        "To: one@example.invalid\r\n".
        "Cc: two@example.invalid\r\n".
        "Message-ID: <parent@example.invalid>\r\n".
        "References: <root@example.invalid>\r\n".
        "In-Reply-To: <root@example.invalid>\r\n"
    );
    return $metadata->to===['one@example.invalid']&&$metadata->cc===['two@example.invalid']
        &&$metadata->thread->inReplyTo==='<parent@example.invalid>';
});
reply_all_check('reply all retains mode threading and ordinary bounded quote',function(){
    $draft=reply_all_prepare("To: me@example.invalid, other@example.invalid\r\n");
    return $draft->mode==='reply-all'
        && $draft->content->to===['Author@example.invalid','other@example.invalid']
        && $draft->content->cc===[]&&$draft->content->bcc===[]
        && $draft->thread->inReplyTo==='<Parent@Example.invalid>'
        && str_contains($draft->content->body,'> Synthetic reply-all body.');
});
reply_all_check('Reply-To replaces From and remains first',function(){
    $draft=reply_all_prepare("Reply-To: Helper <helper@EXAMPLE.invalid>\r\nTo: other@example.invalid\r\n");
    return $draft->content->to===['helper@example.invalid','other@example.invalid'];
});
reply_all_check('multiple Reply-To addresses remain ordered before original To',function(){
    $draft=reply_all_prepare(
        "Reply-To: First <first@example.invalid>, Second <second@example.invalid>\r\n".
        "To: second@EXAMPLE.invalid, third@example.invalid\r\n"
    );
    return $draft->content->to===['first@example.invalid','second@example.invalid','third@example.invalid'];
});
reply_all_check('From fallback remains first when Reply-To is absent',fn()=>
    reply_all_prepare("To: other@example.invalid\r\n")->content->to===['Author@example.invalid','other@example.invalid']);
reply_all_check('To and Cc preserve first occurrence order and dedupe across lists',function(){
    $draft=reply_all_prepare(
        "To: other@example.invalid, duplicate@example.invalid, duplicate@EXAMPLE.invalid\r\n".
        "Cc: watcher@example.invalid, other@EXAMPLE.invalid, second@example.invalid\r\n"
    );
    return $draft->content->to===['Author@example.invalid','other@example.invalid','duplicate@example.invalid']
        && $draft->content->cc===['watcher@example.invalid','second@example.invalid'];
});
reply_all_check('chosen self exclusion preserves local-part case and folds only domain case',function(){
    $draft=reply_all_prepare("To: me@example.invalid, Me@EXAMPLE.INVALID\r\n",'Me@example.invalid');
    return $draft->content->to===['Author@example.invalid','me@example.invalid'];
});
reply_all_check('chosen sender is removed from the reply recipient too',function(){
    $raw=reply_all_raw("Reply-To: Me@EXAMPLE.INVALID\r\nTo: other@example.invalid\r\n");
    $draft=ReplyDraft::prepare('reply-all',$raw,'Me@example.invalid');
    return $draft->content->to===['other@example.invalid'];
});
reply_all_check('Bcc is never copied or exposed',function(){
    $draft=reply_all_prepare("To: other@example.invalid\r\nBcc: hidden@example.invalid\r\n");
    return $draft->content->to===['Author@example.invalid','other@example.invalid']
        && $draft->content->bcc===[]&&!str_contains($draft->content->body,'hidden@example.invalid');
});
reply_all_check('ordinary reply still ignores malformed unrelated To Cc and Bcc',function(){
    $draft=ReplyDraft::prepare('reply',reply_all_raw(
        "To: Broken <a b@example.invalid>\r\n".
        "Cc: Team: one@example.invalid;\r\n".
        "Bcc: hidden@example.invalid\r\n"
    ));
    return $draft->content->to===['Author@example.invalid']&&$draft->content->cc===[]
        &&$draft->content->bcc===[]&&!str_contains($draft->content->body,'hidden@example.invalid');
});
reply_all_check('folded copied recipients are parsed in order',function(){
    $draft=reply_all_prepare("To: First <first@example.invalid>,\r\n Second <second@example.invalid>\r\n");
    return $draft->content->to===['Author@example.invalid','first@example.invalid','second@example.invalid'];
});

foreach([
    'missing'=>null,
    'empty'=>'',
    'display name'=>'Me <me@example.invalid>',
    'header injection'=>"me@example.invalid\r\nBcc: hidden@example.invalid",
    'quoted local'=>'"me"@example.invalid',
    'international local'=>'mé@example.invalid',
    'missing domain'=>'me',
    'oversized'=>str_repeat('a',250).'@example.invalid',
] as $label=>$self) reply_all_check('reply all refuses '.$label.' chosen address without leakage',function()use($self){
    try {ReplyDraft::prepare('reply-all',reply_all_raw("To: other@example.invalid\r\n"),$self);return false;}
    catch(InvalidArgumentException $e){return !str_contains($e->getMessage(),'hidden@example.invalid')&&!str_contains($e->getMessage(),'mé@');}
});

foreach([
    'group'=>'Team: one@example.invalid;',
    'obsolete route'=>'<@a.example,@b.example:one@example.invalid>',
    'quoted local'=>'"one two"@example.invalid',
    'whitespace in address'=>'one two@example.invalid',
    'trailing text'=>'one@example.invalid ignored',
    'empty first mailbox'=>',one@example.invalid',
] as $label=>$value) reply_all_check('reply all refuses '.$label.' copied recipient generically',function()use($value){
    try {reply_all_prepare('To: '.$value."\r\n");return false;}
    catch(InvalidArgumentException $e){return $e->getMessage()==='This message has no supported reply metadata.';}
});
reply_all_check('duplicate To fields are refused',fn()=>reply_all_denied(fn()=>
    reply_all_prepare("To: one@example.invalid\r\nTO: two@example.invalid\r\n")));
reply_all_check('duplicate Cc fields are refused',fn()=>reply_all_denied(fn()=>
    reply_all_prepare("Cc: one@example.invalid\r\nCC: two@example.invalid\r\n")));
reply_all_check('twenty total unique reply recipients are usable',function(){
    $to=implode(',',array_map(fn($i)=>'p'.$i.'@example.invalid',range(1,19)));
    return count(reply_all_prepare('To: '.$to."\r\n")->content->to)===20;
});
reply_all_check('combined recipient count over twenty is refused',function(){
    $to=implode(',',array_map(fn($i)=>'p'.$i.'@example.invalid',range(1,20)));
    return reply_all_denied(fn()=>reply_all_prepare('To: '.$to."\r\n"));
});
reply_all_check('copied lists are bounded before final merge',function(){
    $to=implode(',',array_map(fn($i)=>'p'.$i.'@example.invalid',range(1,21)));
    return reply_all_denied(fn()=>reply_all_prepare('To: '.$to."\r\n"));
});
reply_all_check('reply-all subject normalization and UTF-8 bound match ordinary reply',function(){
    $draft=ReplyDraft::prepare('reply-all',reply_all_raw('', 'Body', str_repeat('é',150)),'me@example.invalid');
    return str_starts_with($draft->content->subject,'Re: ')
        && in_array('subject_or_header_shortened',$draft->omissions,true)
        && strlen($draft->content->subject)<=200&&preg_match('//u',$draft->content->subject)===1;
});
reply_all_check('reply-all quote expansion remains bounded with an explicit omission',function(){
    $draft=ReplyDraft::prepare('reply-all',reply_all_raw('',str_repeat("é\n",9000)),'me@example.invalid');
    return in_array('quoted_text_shortened',$draft->omissions,true)
        && strlen($draft->content->body)<=20000&&preg_match('//u',$draft->content->body)===1
        && str_contains($draft->content->body,'Quoted text shortened')
        && str_ends_with($draft->content->body,'[Attachments are not included.]');
});
reply_all_check('malformed copied address matching self is still refused before exclusion',fn()=>
    reply_all_denied(fn()=>reply_all_prepare("To: me @example.invalid\r\n")));
reply_all_check('metadata exposes canonical copied lists but never Bcc',function(){
    $metadata=ReplyMetadata::decodeForReplyAll(
        "From: author@example.invalid\r\n".
        "To: One@EXAMPLE.invalid\r\n".
        "Cc: Two@EXAMPLE.invalid\r\n".
        "Bcc: hidden@example.invalid\r\n"
    );
    return $metadata->to===['One@example.invalid']&&$metadata->cc===['Two@example.invalid']
        && !property_exists($metadata,'bcc');
});
reply_all_check('reply-all draft and metadata remain immutable',function(){
    $draft=reply_all_prepare("To: other@example.invalid\r\n");
    try {$draft->content->to[]='evil@example.invalid';return false;}
    catch(Error){return $draft->content->to===['Author@example.invalid','other@example.invalid'];}
});
reply_all_check('reply-all origin round trips with exact mode and content fingerprint',function(){
    $draft=reply_all_prepare("To: other@example.invalid\r\n");
    $origin=DraftOrigin::create(
        str_repeat('1',32),7,str_repeat('2',32),3,'9','44',str_repeat('3',32),str_repeat('4',32),5,$draft
    );
    $copy=DraftOrigin::fromJson($origin->json);
    return $copy->data['mode']==='reply-all'&&$copy->data['thread']===$draft->thread->toArray()
        && $copy->data['initial_content_sha256']===hash('sha256',DraftOrigin::contentJson($draft->content));
});
reply_all_check('unknown stored origin mode remains refused',function(){
    $draft=reply_all_prepare();
    $origin=DraftOrigin::create(
        str_repeat('1',32),7,str_repeat('2',32),3,'9','44',str_repeat('3',32),str_repeat('4',32),5,$draft
    );
    return reply_all_denied(fn()=>DraftOrigin::fromJson(str_replace('"mode":"reply-all"','"mode":"reply-all-later"',$origin->json)));
});

$replyAllPassed=!array_filter($replyAllChecks,fn($row)=>!$row['passed']);
echo json_encode([
    'scope'=>'Pure bounded reply-all metadata, recipient and origin models with synthetic messages; no database, authority, socket, mailbox, persistence, application, UI or sending acceptance',
    'passed'=>$replyAllPassed,
    'checks'=>$replyAllChecks,
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($replyAllPassed?0:1);
