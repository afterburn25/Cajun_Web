<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);

require __DIR__.'/../../foundation/mail-compose/CopiedDraftSource.php';

use CajunWebPro\Foundation\MailCompose\CopiedDraftSource;

$copiedChecks=[];
function copied_check(string $name,callable $test):void {
    global $copiedChecks;
    try {$ok=$test()===true;} catch(Throwable $e) {$ok=false;$error=get_class($e).': '.$e->getMessage();}
    $copiedChecks[]=['name'=>$name,'passed'=>$ok]+(isset($error)?['error'=>$error]:[]);
}
function copied_denied(callable $test):bool {
    try {$test();return false;} catch(InvalidArgumentException|LogicException) {return true;}
}
function copied_raw(string $extra='',string $body='Synthetic copied body.'):string {
    return "From: Author <author@example.invalid>\r\n".
        "To: me@example.invalid, other@example.invalid\r\n".
        "Bcc: NEVER_PERSIST_RAW_MARKER@example.invalid\r\n".
        "Subject: Synthetic copied subject\r\n".
        "Message-ID: <copied@example.invalid>\r\n".$extra.
        "Content-Type: text/plain; charset=UTF-8\r\n\r\n".$body;
}
function copied_source(string $mode='reply',?string $self=null,string $raw=''):CopiedDraftSource {
    $raw=$raw===''?copied_raw():$raw;
    return CopiedDraftSource::fromRead(7,str_repeat('a',32),3,'9','44',str_repeat('b',32),$mode,$raw,$self);
}

copied_check('one transient factory binds exact actor account revision UID and read identity',function(){
    $source=copied_source();
    return $source->actorId===7&&$source->accountId===str_repeat('a',32)&&$source->accountRevision===3
        &&$source->uidvalidity==='9'&&$source->uid==='44'&&$source->readId===str_repeat('b',32)
        &&$source->messageBytes===(string)strlen(copied_raw());
});
copied_check('reply derives bounded ordinary content and threading without exposing Bcc',function(){
    $source=copied_source();
    return $source->draft->mode==='reply'&&$source->draft->content->to===['author@example.invalid']
        &&$source->draft->content->bcc===[]&&$source->draft->thread->inReplyTo==='<copied@example.invalid>'
        &&!str_contains(json_encode($source->draft,JSON_THROW_ON_ERROR),'NEVER_PERSIST_RAW_MARKER');
});
copied_check('reply all requires and applies one exact chosen self address',function(){
    $source=copied_source('reply-all','me@example.invalid');
    return $source->draft->content->to===['author@example.invalid','other@example.invalid']
        &&$source->draft->content->cc===[]&&$source->draft->content->bcc===[];
});
copied_check('forward is recipientless and carries no inherited reply thread',function(){
    $source=copied_source('forward');
    return $source->draft->content->to===[]&&$source->draft->thread->toArray()===['in_reply_to'=>null,'references'=>[]];
});
copied_check('raw MIME is not a property or JSON second channel',function(){
    $source=copied_source();$properties=array_map(fn(ReflectionProperty $p)=>$p->getName(),(new ReflectionClass($source))->getProperties());
    $json=json_encode($source,JSON_THROW_ON_ERROR);
    return !in_array('raw',$properties,true)&&!str_contains($json,'NEVER_PERSIST_RAW_MARKER')
        &&!str_contains($json,'Bcc:')&&!str_contains($json,'Content-Type:');
});
copied_check('native serialization cannot persist a transient trusted source',function(){
    return copied_denied(fn()=>serialize(copied_source()));
});
copied_check('native unserialize cannot construct another trusted source channel',function(){
    $class=CopiedDraftSource::class;$payload='O:'.strlen($class).':"'.$class.'":0:{}';
    return copied_denied(fn()=>unserialize($payload));
});
copied_check('copied source and derived draft are immutable',function(){
    $source=copied_source();
    try {$source->uid='45';return false;}catch(Error){return $source->uid==='44';}
});

foreach([
    'zero actor'=>[0,str_repeat('a',32),3,'9','44',str_repeat('b',32),'reply',copied_raw(),null],
    'overflow actor'=>[2147483648,str_repeat('a',32),3,'9','44',str_repeat('b',32),'reply',copied_raw(),null],
    'zero account revision'=>[7,str_repeat('a',32),0,'9','44',str_repeat('b',32),'reply',copied_raw(),null],
    'invalid account id'=>[7,str_repeat('A',32),3,'9','44',str_repeat('b',32),'reply',copied_raw(),null],
    'invalid read id'=>[7,str_repeat('a',32),3,'9','44',str_repeat('g',32),'reply',copied_raw(),null],
    'zero UIDVALIDITY'=>[7,str_repeat('a',32),3,'0','44',str_repeat('b',32),'reply',copied_raw(),null],
    'overflow UID'=>[7,str_repeat('a',32),3,'9','4294967296',str_repeat('b',32),'reply',copied_raw(),null],
    'empty raw source'=>[7,str_repeat('a',32),3,'9','44',str_repeat('b',32),'reply','',null],
    'oversized raw source'=>[7,str_repeat('a',32),3,'9','44',str_repeat('b',32),'reply',str_repeat('x',262145),null],
    'reply all without self'=>[7,str_repeat('a',32),3,'9','44',str_repeat('b',32),'reply-all',copied_raw(),null],
    'ordinary reply with self'=>[7,str_repeat('a',32),3,'9','44',str_repeat('b',32),'reply',copied_raw(),'me@example.invalid'],
    'forward with self'=>[7,str_repeat('a',32),3,'9','44',str_repeat('b',32),'forward',copied_raw(),'me@example.invalid'],
] as $label=>$arguments)copied_check('factory refuses '.$label,fn()=>copied_denied(fn()=>CopiedDraftSource::fromRead(...$arguments)));

copied_check('all canonical compose models have byte-exact deployed mirrors',function(){
    foreach(['CopiedDraftSource','DraftOrigin','MessageThread','ReplyDraft','ReplyMetadata'] as $name){
        if(file_get_contents(__DIR__.'/../../foundation/mail-compose/'.$name.'.php')!==file_get_contents(__DIR__.'/../../site/includes/foundation/mail-compose/'.$name.'.php'))return false;
    }
    return true;
});

$copiedPassed=!array_filter($copiedChecks,fn($row)=>!$row['passed']);
echo json_encode([
    'scope'=>'Pure transient raw-to-derived copied-draft capability using synthetic MIME; no database, account authority, mailbox, persistence, application, UI, submission or sending acceptance',
    'passed'=>$copiedPassed,
    'checks'=>$copiedChecks,
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($copiedPassed?0:1);
