<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-compose/ReplyDraft.php';
use CajunWebPro\Foundation\MailCompose\{ReplyMetadata,ReplyDraft,DraftContent};
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;

$checks=[];
function check(string $name,callable $test):void {
    global $checks;
    try {$ok=$test()===true;} catch(Throwable $e) {$ok=false;$error=get_class($e).': '.$e->getMessage();}
    $checks[]=['name'=>$name,'passed'=>$ok]+(isset($error)?['error'=>$error]:[]);
}
function denied(callable $test):bool {
    try {$test();return false;} catch(InvalidArgumentException|ReceivingFailure) {return true;}
}
function metadata(string $extra='',string $from='Author <Author@EXAMPLE.invalid>'):ReplyMetadata {
    return ReplyMetadata::decode('From: '.$from."\r\nSubject: Synthetic message\r\n".$extra);
}
function raw(string $extra='',string $body='Hello from the synthetic sender.',string $subject='Synthetic message'):string {
    return 'From: Author <Author@example.invalid>'."\r\nDate: Tue, 14 Oct 2025 10:00:00 +0000\r\nSubject: ".$subject."\r\nMessage-ID: <Parent@Example.invalid>\r\n".$extra."Content-Type: text/plain; charset=UTF-8\r\n\r\n".$body;
}
if(in_array('--without-dom',$argv,true))check('this runner actually has no DOM extension',fn()=>!extension_loaded('dom'));
check('reply uses structured From and preserves local case',fn()=>metadata()->recipients===['Author@example.invalid']);
check('explicit Reply-To replaces From without adding it',fn()=>metadata("Reply-To: Support <help@EXAMPLE.invalid>\r\n")->recipients===['help@example.invalid']);
check('multiple Reply-To mailboxes stay ordered',fn()=>metadata("Reply-To: \"Comma, Name\" <one@example.invalid>,\r\n Two <two@example.invalid>\r\n")->recipients===['one@example.invalid','two@example.invalid']);
check('encoded display names do not become recipient addresses',fn()=>metadata("Reply-To: =?UTF-8?B?Sm9zw6k=?= <one@example.invalid>\r\n")->recipients===['one@example.invalid']);
check('nested comments around a modern address are accepted',fn()=>metadata("Reply-To: (outer (inner)) Name < (comment) one@example.invalid > (tail)\r\n")->recipients===['one@example.invalid']);
check('address-like display-name text cannot select a recipient',fn()=>metadata("Reply-To: \"other@example.invalid\" <right@example.invalid>\r\n")->recipients===['right@example.invalid']);
check('empty Reply-To does not fall back to From',fn()=>denied(fn()=>metadata("Reply-To: \r\n")));
check('invalid Reply-To does not fall back to From',fn()=>denied(fn()=>metadata("Reply-To: Broken <no-address>\r\n")));
check('unsupported reply metadata leaves existing message reading available',function(){
 $r=raw("Reply-To: Broken <a b@example.invalid>\r\n");
 $m=\CajunWebPro\Foundation\MailReceiving\BodyDecoder::decode($r);
 return $m['text']==='Hello from the synthetic sender.' && denied(fn()=>ReplyDraft::prepare('reply',$r));
});
check('refusal does not expose incoming addresses through diagnostics',function(){
 try {metadata("Reply-To: private secret@example.invalid\r\n");return false;}
 catch(InvalidArgumentException $e){return $e->getMessage()==='This message has no supported reply metadata.' && $e->getPrevious()===null;}
});
check('no reply identity does not invent one',fn()=>denied(fn()=>ReplyMetadata::decode("Subject: Only a subject\r\n")));
foreach([
 'whitespace within address'=>'Name <a b@example.invalid>',
 'misleading display and normalized address'=>'"ab@example.invalid" <a b@example.invalid>',
 'text after a completed mailbox'=>'Name <a@example.invalid> ignored',
 'extra angle pair'=>'Name <a@example.invalid><b@example.invalid>',
 'duplicate addresses'=>'a@example.invalid, a@EXAMPLE.invalid',
 'empty address group'=>'Nobody:;',
 'nonempty address group'=>'Team: a@example.invalid;',
 'quoted unsupported local part'=>'"a b"@example.invalid',
 'international unsupported local part'=>'café@example.invalid',
 'unclosed display quote'=>'"Open <a@example.invalid>',
 'unclosed comment'=>'a@example.invalid (open',
 'extra comment close'=>'a@example.invalid )',
 'trailing list comma'=>'a@example.invalid,',
 'empty first mailbox'=>',a@example.invalid',
 'invalid address dots'=>'a..b@example.invalid',
] as $label=>$value)check('reject '.$label,fn()=>denied(fn()=>metadata('Reply-To: '.$value."\r\n")));
check('recipient total is capped before creating a draft',fn()=>denied(fn()=>metadata('Reply-To: '.implode(',',array_map(fn($i)=>'a'.$i.'@example.invalid',range(1,21)))."\r\n")));
check('recipient maximum is usable',fn()=>count(metadata('Reply-To: '.implode(',',array_map(fn($i)=>'a'.$i.'@example.invalid',range(1,20)))."\r\n")->recipients)===20);
check('duplicate Reply-To fields are refused case insensitively',fn()=>denied(fn()=>metadata("Reply-To: a@example.invalid\r\nREPLY-TO: b@example.invalid\r\n")));
check('only selected recipient and thread fields are parsed for reply',fn()=>metadata("To: Visible <visible@example.invalid>\r\nBcc: Hidden <secret@example.invalid>\r\nX-Ignore: Name <a b@example.invalid>\r\n")->recipients===['Author@example.invalid']);
check('folded parent reference chain preserves exact IDs',fn()=>metadata("Message-ID: (parent) <P@EXAMPLE.invalid>\r\nReferences: <root@example.invalid>\r\n (middle) <M@example.invalid>\r\n")->thread->references===['<root@example.invalid>','<M@example.invalid>','<P@EXAMPLE.invalid>']);
check('single parent In-Reply-To supplies a missing chain',fn()=>metadata("Message-ID: <p@example.invalid>\r\nIn-Reply-To: <r@example.invalid>\r\n")->thread->references===['<r@example.invalid>','<p@example.invalid>']);
check('absent identifiers stay absent',fn()=>metadata()->thread->toArray()===['in_reply_to'=>null,'references'=>[]]);
check('IPv6 message-id literal remains an identifier',fn()=>metadata("Message-ID: <a@[IPv6:2001:db8::1]>\r\n")->thread->inReplyTo==='<a@[IPv6:2001:db8::1]>');
foreach([
 'unbracketed parent'=>'Message-ID: parent@example.invalid',
 'multiple parents'=>'Message-ID: <a@example.invalid> <b@example.invalid>',
 'duplicate identifier header'=>"Message-ID: <a@example.invalid>\r\nMessage-ID: <b@example.invalid>",
 'empty identifier field'=>'References: ',
 'ignored trailing identifier text'=>'Message-ID: <a@example.invalid> ignored',
 'whitespace inside identifier'=>'Message-ID: <a b@example.invalid>',
 'control in identifier'=>"Message-ID: <a\t@example.invalid>",
 'excessive comment nesting'=>'Reply-To: '.str_repeat('(',13).'x'.str_repeat(')',13).' a@example.invalid',
] as $label=>$value)check('reject '.$label,fn()=>denied(fn()=>metadata($value."\r\n")));
check('header bytes remain bounded',fn()=>denied(fn()=>metadata('X-Large: '.str_repeat('x',16385)."\r\n")));
check('header field bytes remain bounded',fn()=>denied(fn()=>metadata('Reply-To: '.str_repeat('x',4097)."\r\n")));
check('bare LF header injection is refused',fn()=>denied(fn()=>metadata("Reply-To: a@example.invalid\nBcc: hidden@example.invalid\r\n")));
check('body bytes cannot masquerade as header input',fn()=>denied(fn()=>metadata("\r\nReply-To: injected@example.invalid\r\n")));
check('quote is an editable ordinary reply with no hidden recipients',function(){
 $d=ReplyDraft::prepare('reply',raw("Reply-To: helper@example.invalid\r\nBcc: hidden@example.invalid\r\n"));
 return $d->content instanceof DraftContent && $d->content->to===['helper@example.invalid'] && $d->content->cc===[] && $d->content->bcc===[] && $d->content->subject==='Re: Synthetic message' && str_contains($d->content->body,'> Hello from the synthetic sender.') && !str_contains($d->content->body,'hidden@example.invalid') && $d->thread->inReplyTo==='<Parent@Example.invalid>';
});
check('forward is recipientless and does not reuse reply threading',function(){ $d=ReplyDraft::prepare('forward',raw("Reply-To: Broken <a b@example.invalid>\r\n"));return $d->content->to===[] && $d->content->subject==='Fwd: Synthetic message' && $d->thread->toArray()===['in_reply_to'=>null,'references'=>[]];});
check('existing reply prefix is preserved once',fn()=>ReplyDraft::prepare('reply',raw('','Hi','rE: Existing'))->content->subject==='rE: Existing');
check('existing forward prefix is preserved once',fn()=>ReplyDraft::prepare('forward',raw('','Hi','Fw: Existing'))->content->subject==='Fw: Existing');
check('UTF8 subject shortening is explicit and stays valid',function(){ $d=ReplyDraft::prepare('reply',raw('','Hi',str_repeat('é',150)));return in_array('subject_or_header_shortened',$d->omissions,true) && strlen($d->content->subject)<=200 && preg_match('//u',$d->content->subject)===1;});
check('quoted newline expansion is bounded with an explicit omission',function(){ $d=ReplyDraft::prepare('reply',raw('',str_repeat("é\n",9000)));return in_array('quoted_text_shortened',$d->omissions,true) && strlen($d->content->body)<=20000 && preg_match('//u',$d->content->body)===1 && str_contains($d->content->body,'Quoted text shortened') && str_ends_with($d->content->body,'[Attachments are not included.]');});
check('small quote remains complete and has no shortening claim',function(){ $d=ReplyDraft::prepare('reply',raw('',"First\nLast"));return !in_array('quoted_text_shortened',$d->omissions,true) && str_contains($d->content->body,"> First\n> Last");});
check('HTML-only message has an honest unavailable plain quote',function(){ $r="From: a@example.invalid\r\nSubject: HTML\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<p>HTML body <script>secret</script></p>";$d=ReplyDraft::prepare('reply',$r);return in_array('plain_text_unavailable',$d->omissions,true) && str_contains($d->content->body,'No supported plain-text body') && !str_contains($d->content->body,'secret');});
check('MIME attachments and hidden headers are excluded from forwarded body',function(){
 $r="From: a@example.invalid\r\nBcc: hidden@example.invalid\r\nSubject: Attachment\r\nContent-Type: multipart/mixed; boundary=synthetic\r\n\r\n--synthetic\r\nContent-Type: text/plain\r\n\r\nVisible body\r\n--synthetic\r\nContent-Type: text/plain\r\nContent-Disposition: attachment; filename=private.txt\r\n\r\nPRIVATE_ATTACHMENT_BYTES\r\n--synthetic--\r\n";
 $d=ReplyDraft::prepare('forward',$r);return str_contains($d->content->body,'Visible body') && !str_contains($d->content->body,'PRIVATE_ATTACHMENT_BYTES') && !str_contains($d->content->body,'hidden@example.invalid') && !str_contains($d->content->body,'private.txt');
});
check('multiple inline parts disclose omission without concatenating content',function(){
 $r="From: a@example.invalid\r\nSubject: Parts\r\nContent-Type: multipart/mixed; boundary=synthetic\r\n\r\n--synthetic\r\nContent-Type: text/plain\r\n\r\nFirst body\r\n--synthetic\r\nContent-Type: text/plain\r\n\r\nSECOND_BODY\r\n--synthetic--\r\n";
 $d=ReplyDraft::prepare('forward',$r);return in_array('additional_inline_parts',$d->omissions,true) && str_contains($d->content->body,'Additional inline message parts') && !str_contains($d->content->body,'SECOND_BODY');
});
check('oversized raw source is refused before draft construction',fn()=>denied(fn()=>ReplyDraft::prepare('reply',raw('',str_repeat('x',262144)))));
check('unknown action cannot prepare content',fn()=>denied(fn()=>ReplyDraft::prepare('send',raw())));
check('constructed metadata is immutable',function(){ $m=metadata();try{$m->recipients[]='evil@example.invalid';return false;}catch(Error){return $m->recipients===['Author@example.invalid'];} });
check('reply all keeps reply recipient and original To minus the sender',function(){
 $d=ReplyDraft::prepare('reply-all',raw("To: me@example.invalid, other@example.invalid\r\n"),'me@example.invalid');
 return $d->mode==='reply-all' && $d->content->to===['Author@example.invalid','other@example.invalid'] && $d->content->cc===[] && $d->thread->toArray()!==['in_reply_to'=>null,'references'=>[]];
});
check('reply all moves original Cc minus sender and addresses already in To',function(){
 $d=ReplyDraft::prepare('reply-all',raw("To: me@example.invalid, other@example.invalid\r\nCc: watcher@example.invalid, other@example.invalid\r\n"),'me@example.invalid');
 return $d->content->to===['Author@example.invalid','other@example.invalid'] && $d->content->cc===['watcher@example.invalid'];
});
check('reply all excludes the sender only by exact address',function(){
 $d=ReplyDraft::prepare('reply-all',raw("To: me@example.invalid, Me@EXAMPLE.INVALID\r\n"),'me@example.INVALID');
 return $d->content->to===['Author@example.invalid','Me@example.invalid'];
});
check('reply all preserves Reply-To precedence over From',function(){
 $d=ReplyDraft::prepare('reply-all',raw("Reply-To: helper@example.invalid\r\nTo: me@example.invalid\r\n"),'me@example.invalid');
 return $d->content->to===['helper@example.invalid'] && $d->content->cc===[];
});
check('reply all requires the chosen sender address',fn()=>denied(fn()=>ReplyDraft::prepare('reply-all',raw("To: me@example.invalid\r\n"))));
check('reply all refuses malformed copied recipient headers',fn()=>denied(fn()=>ReplyDraft::prepare('reply-all',raw("Cc: Broken <a b@example.invalid>\r\n"),'me@example.invalid')));
check('reply all still applies the total recipient bound',function(){
 $many='To: '.implode(',',array_map(fn($i)=>'p'.$i.'@example.invalid',range(1,21)))."\r\n";
 return denied(fn()=>ReplyDraft::prepare('reply-all',raw($many),'me@example.invalid'));
});
check('prepared draft is immutable and cannot expose mutable aliases',function(){ $d=ReplyDraft::prepare('reply',raw());$o=$d->omissions;$o[]='changed';return $d->omissions===[]; });
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['scope'=>'Maintained MIME parser and bounded reply/forward content using synthetic messages; no database, account authority, application, network or sending acceptance','passed'=>$passed,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
