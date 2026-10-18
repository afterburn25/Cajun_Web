<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/PreparedMime.php';
use CajunWebPro\Foundation\MailCompose\{DraftAttachment,DraftContent,MessageThread};
use CajunWebPro\Foundation\MailSubmission\{SubmissionSnapshot,PreparedMime};
$checks=[];$messages=[];$complete=false;
function check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function refused(callable $fn):bool{try{$fn();return false;}catch(InvalidArgumentException|TypeError){return true;}}
function snapshot(array $attachments=[],?MessageThread $thread=null,?string $origin=null):SubmissionSnapshot{
    return new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,"Studio+o'neil@example.invalid",
        new DraftContent(['Client+work@example.invalid'],['copy@example.invalid'],['private-envelope@example.invalid'],'Project files',"Saved body\nSecond line"),1791964800,$thread,$origin,$attachments);
}
try {
    $file=new DraftAttachment('Quarterly report.pdf','application/pdf',"%PDF-1.4\n".str_repeat("\x00\xffA",100));
    check('Attachment name, type and checksum are captured exactly',$file->name==='Quarterly report.pdf'&&$file->mime==='application/pdf'&&$file->sha256===hash('sha256',$file->bytes));
    check('Descriptor order is canonical and carries no bytes',$file->descriptor()===['name'=>'Quarterly report.pdf','mime'=>'application/pdf','sha256'=>$file->sha256,'bytes'=>strlen($file->bytes)]);
    check('Unsafe and non-ASCII name bytes are stripped to a printable wire name',(new DraftAttachment("../evil\r\n\t\"file<>|?.txt",'text/plain','x'))->name==='..evilfile.txt');
    check('A 181-byte name is truncated to the wire bound',strlen((new DraftAttachment(str_repeat('n',181),'text/plain','x'))->name)===180);
    foreach(['','..','.'] as $i=>$bad)check('Unusable attachment name '.$i.' is refused',refused(fn()=>new DraftAttachment($bad,'text/plain','x')));
    check('A name reduced to only unsafe bytes is refused',refused(fn()=>new DraftAttachment("\xC3\xA9\xC3\xA9",'text/plain','x')));
    check('Partially unsafe bytes strip to their printable remainder',(new DraftAttachment("\xC3\x28",'text/plain','x'))->name==='(');
    check('Empty attachment bytes are refused',refused(fn()=>new DraftAttachment('a.txt','text/plain','')));
    check('A file above 256 KiB is refused',refused(fn()=>new DraftAttachment('a.txt','text/plain',str_repeat('a',262145))));
    check('Exactly 256 KiB is accepted',strlen((new DraftAttachment('a.txt','application/octet-stream',str_repeat('a',262144)))->bytes)===262144);
    foreach(['text/plain','application/pdf','image/png','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] as $i=>$mime)check('Declared MIME token '.$i.' is accepted',(new DraftAttachment('a.bin',$mime,'x'))->mime===$mime);
    foreach(['','not a mime','text','text/','/plain','text/plain; charset=x',"text/plain\n",'TEXT/PLAIN'."\x7F",str_repeat('t',101)] as $i=>$bad)check('Unsupported MIME token '.$i.' is refused',refused(fn()=>new DraftAttachment('a.bin',$bad,'x')));
    check('Five attachments exceed the per-draft bound',refused(fn()=>DraftAttachment::list([new DraftAttachment('1','text/plain','a'),new DraftAttachment('2','text/plain','a'),new DraftAttachment('3','text/plain','a'),new DraftAttachment('4','text/plain','a'),new DraftAttachment('5','text/plain','a')])));
    check('Combined attachments above 512 KiB are refused',refused(fn()=>DraftAttachment::list([new DraftAttachment('1','text/plain',str_repeat('a',262144)),new DraftAttachment('2','text/plain',str_repeat('a',262144)),new DraftAttachment('3','text/plain','a')])));
    check('Non-attachment values are refused in the list',refused(fn()=>DraftAttachment::list([['name'=>'a']])));

    $one=new DraftAttachment('notes.txt','text/plain',"Plain note \x00 bytes\r\n");
    $two=new DraftAttachment('photo.png','image/png',"\x89PNG\r\n\x1A\n".str_repeat("\xDE\xAD",500));
    $snap=snapshot([$one,$two]);
    $payload=$snap->privatePayload();
    check('Attachment-bearing ordinary submission uses format 3',$payload['format']===3);
    check('Format 3 keeps the exact canonical key order',array_keys($payload)===['format','id','author_id','draft_id','draft_revision','account_id','account_revision','from','message_id','prepared_epoch','to','cc','bcc','subject','body','attachments']);
    check('Payload attachment descriptors are ordered metadata only',$payload['attachments']===[$one->descriptor(),$two->descriptor()]);
    $plain=snapshot();
    check('An attachment-free ordinary submission stays format 1',$plain->privatePayload()['format']===1&&!array_key_exists('attachments',$plain->privatePayload()));
    check('Identical attachments keep a stable fingerprint',snapshot([new DraftAttachment('notes.txt','text/plain',"Plain note \x00 bytes\r\n"),new DraftAttachment('photo.png','image/png',"\x89PNG\r\n\x1A\n".str_repeat("\xDE\xAD",500))])->fingerprint===$snap->fingerprint);
    check('Changed attachment bytes change the fingerprint',snapshot([$one,new DraftAttachment('photo.png','image/png',"\x89PNG\r\n\x1A\n".str_repeat("\xDE\xAD",500).'!')])->fingerprint!==$snap->fingerprint);
    check('Changed attachment name changes the fingerprint',snapshot([$one,new DraftAttachment('other.png','image/png',$two->bytes)])->fingerprint!==$snap->fingerprint);
    check('Attachment order is part of the fingerprint',snapshot([$two,$one])->fingerprint!==$snap->fingerprint);

    $thread=MessageThread::reply('<parent@example.invalid>',['<parent@example.invalid>']);
    $copied=snapshot([$one],$thread,str_repeat('f',64));
    check('Copied submission with attachments uses format 4',$copied->privatePayload()['format']===4);
    check('Format 4 keeps canonical key order',array_keys($copied->privatePayload())===['format','id','author_id','draft_id','draft_revision','account_id','account_revision','from','message_id','prepared_epoch','to','cc','bcc','subject','body','thread','origin_fingerprint','attachments']);
    check('Copied submission without attachments stays format 2',snapshot([],$thread,str_repeat('f',64))->privatePayload()['format']===2);

    $a=PreparedMime::fromSnapshot($snap);$b=PreparedMime::fromSnapshot($snap);
    check('Attachment MIME bytes are repeatable',$a->bytes===$b->bytes&&$a->sha256===hash('sha256',$a->bytes));
    check('Attached message stays inside the raised bound',strlen($a->bytes)<=PreparedMime::MAX_BYTES);
    $headers=explode("\r\n\r\n",$a->bytes,2)[0];
    check('Top-level message is multipart/mixed',str_contains($headers,'multipart/mixed'));
    check('No hidden-recipient or extra authority headers',!preg_match('/^(?:Bcc|Resent-Bcc|Reply-To|Return-Path|Disposition-Notification-To):/mi',$headers));
    foreach(explode("\r\n",$a->bytes) as $i=>$line)check('Wire line '.$i.' fits SMTP bounds',strlen($line)<=998);
    check('Attachment bytes stay base64-armoured printable ASCII',!preg_match('/[^\x09\x0A\x0D\x20-\x7E]/',$a->bytes));

    // Structural part split on the maintained library's own boundaries.
    preg_match('/boundary="?([^";\r\n]+)"?/i',$headers,$m);$boundary=$m[1]??'';
    check('A MIME boundary exists',$boundary!=='');
    $parts=explode('--'.$boundary,$a->bytes);
    check('Two content parts follow the boundary preamble',count($parts)>=4);
    $found=[];
    foreach($parts as $part) {
        if(str_contains($part,'Content-Disposition: attachment')) {
            preg_match('/filename="?([^";\r\n]+)"?/',$part,$f);
            $body=explode("\r\n\r\n",$part,2)[1]??'';
            $found[]=['name'=>$f[1]??'','bytes'=>base64_decode(str_replace("\r\n",'',$body),true)];
        }
    }
    check('Both attachments survive the wire encoding',count($found)===2&&$found[0]['name']==='notes.txt'&&$found[1]['name']==='photo.png');
    check('Decoded attachment bytes are exact',$found[0]['bytes']===$one->bytes&&$found[1]['bytes']===$two->bytes);
    $messages[]=['name'=>'two-attachments','snapshot'=>$payload,'mime_base64'=>base64_encode($a->bytes)];

    // Maximum-size attachment set still fits the raised wire bound.
    $big=[];
    for($i=0;$i<4;$i++)$big[]=new DraftAttachment('big'.$i.'.bin','application/octet-stream',str_repeat("\xAB",131072));
    $huge=PreparedMime::fromSnapshot(snapshot($big));
    check('Four 128 KiB attachments fit the wire bound',strlen($huge->bytes)<=PreparedMime::MAX_BYTES);
    $messages[]=['name'=>'max-attachments','snapshot'=>$huge->snapshot->privatePayload(),'mime_base64'=>base64_encode($huge->bytes)];

    $copiedMime=PreparedMime::fromSnapshot($copied);
    check('Format 4 keeps RFC 5322 threading headers',str_contains(explode("\r\n\r\n",$copiedMime->bytes,2)[0],'In-Reply-To: <parent@example.invalid>'));
    $messages[]=['name'=>'copied-attachment','snapshot'=>$copied->privatePayload(),'mime_base64'=>base64_encode($copiedMime->bytes)];

    $complete=true;
} finally {
    if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$complete&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Offline attachment value objects, snapshot formats and maintained MIME encoding only; synthetic fixture bytes','checks'=>$checks,'messages'=>$messages],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." send-side attachment checks; ".count($messages)." independent parser fixtures\n";
