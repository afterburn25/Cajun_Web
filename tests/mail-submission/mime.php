<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/PreparedMime.php';
use CajunWebPro\Foundation\MailCompose\{DraftAttachment,DraftContent,MessageThread};
use CajunWebPro\Foundation\MailSubmission\{SubmissionSnapshot,PreparedMime,PreparationFailure,OfflineMailer};
$checks=[];$messages=[];$complete=false;
function check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function snapshot(DraftContent $content):SubmissionSnapshot{return new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,"Studio+o'neil@example.invalid",$content,1791964800);}
try {
    $goldenSnapshot=new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,'sender@example.invalid',new DraftContent(['client@example.invalid'],[],[],'Plain','Body'),1791964800);
    $goldenMime=PreparedMime::fromSnapshot($goldenSnapshot);
    check('Plain MIME bytes remain byte-identical to the predecessor',$goldenSnapshot->fingerprint==='a74941a4478f3da07f0b2e4f875a1e373d42233e40018d620ed9d57cee34fc9a'&&$goldenMime->sha256==='81006e946fa0f2d02c4a0ac8ab0eaff98fb4180dbf5c20d183d50d0b1b903d61'&&strlen($goldenMime->bytes)===300);
    $thread=MessageThread::reply('<parent@example.invalid>',['<root@example.invalid>']);
    $attachments=[new DraftAttachment('one.txt','text/plain','PRIVATE-SENTINEL'),new DraftAttachment('two.bin','application/octet-stream',"\x00\xffbinary")];
    $formatMimes=[];
    for($bits=0;$bits<8;$bits++){
        $rich=($bits&4)!==0;$withThread=($bits&1)!==0;$withAttachments=($bits&2)!==0;
        $shape=new SubmissionSnapshot(str_repeat('3',32),9,str_repeat('4',32),5,str_repeat('b',32),6,'sender@example.invalid',
            new DraftContent(['client@example.invalid'],[],['private@example.invalid'],'Shape '.$bits,'Plain fallback',$rich?'<p>Formatted <strong>body</strong></p>':null),1791964800,
            $withThread?$thread:null,$withThread?str_repeat('e',64):null,$withAttachments?$attachments:[]);
        $a=PreparedMime::fromSnapshot($shape);$b=PreparedMime::fromSnapshot($shape);
        check('Format '.(1+$bits).' MIME is deterministic and bounded',$a->bytes===$b->bytes&&$a->sha256===hash('sha256',$a->bytes)&&strlen($a->bytes)<=PreparedMime::MAX_BYTES);
        $formatMimes[$bits]=$a->bytes;
        $messages[]=['name'=>'format-'.(1+$bits),'snapshot'=>$shape->privatePayload(),'mime_base64'=>base64_encode($a->bytes)];
    }
    check('Formatted snapshot produces a plain plus HTML alternative',str_contains($formatMimes[4],'multipart/alternative')&&str_contains($formatMimes[4],'Content-Type: text/plain')&&str_contains($formatMimes[4],'Content-Type: text/html'));
    check('Attachment snapshot produces deterministic mixed MIME',str_contains($formatMimes[2],'multipart/mixed')&&substr_count($formatMimes[2],'Content-Disposition: attachment')===2);
    $threadHeaders=explode("\r\nMIME-Version:",$formatMimes[1],2)[0];
    check('Thread identifiers stay literal and unencoded',str_contains($threadHeaders,"In-Reply-To: <parent@example.invalid>\r\n")&&str_contains($threadHeaders,'References: <root@example.invalid> <parent@example.invalid>')&&!preg_match('/(?:In-Reply-To|References):[^\r\n]*=\?/',$threadHeaders));
    check('Formatted alternative composes inside mixed attachments',str_contains($formatMimes[6],'multipart/mixed')&&str_contains($formatMimes[6],'multipart/alternative'));
    preg_match('/boundary="([^"]+)"/',$formatMimes[6],$boundaryA);
    $changed=new SubmissionSnapshot(str_repeat('3',32),9,str_repeat('4',32),5,str_repeat('b',32),6,'sender@example.invalid',new DraftContent(['client@example.invalid'],[],['private@example.invalid'],'Changed','Plain fallback','<p>Formatted <strong>body</strong></p>'),1791964800,$thread,str_repeat('e',64),$attachments);
    $changedMime=PreparedMime::fromSnapshot($changed)->bytes;preg_match('/boundary="([^"]+)"/',$changedMime,$boundaryB);
    check('Different snapshot fingerprint changes multipart boundary',($boundaryA[1]??'')!==''&&($boundaryB[1]??'')!==''&&$boundaryA[1]!==$boundaryB[1]);

    foreach(['empty'=>'','zero'=>'0'] as $name=>$fallback){
        $shape=new SubmissionSnapshot(str_repeat('5',32),7,str_repeat('6',32),3,str_repeat('c',32),4,'sender@example.invalid',new DraftContent(['client@example.invalid'],[],[],'Fallback '.$name,$fallback,'<p>Formatted</p>'),1791964800);
        $wire=PreparedMime::fromSnapshot($shape)->bytes;
        check(ucfirst($name).' plaintext fallback remains a real alternative',str_contains($wire,'multipart/alternative')&&str_contains($wire,'Content-Type: text/plain')&&str_contains($wire,'Content-Type: text/html'));
        $messages[]=['name'=>'fallback-'.$name,'snapshot'=>$shape->privatePayload(),'mime_base64'=>base64_encode($wire)];
    }

    $longId='<'.str_repeat('a',500).'@x>';$maxThread=MessageThread::reply($longId,array_fill(0,7,$longId));
    $threadShape=new SubmissionSnapshot(str_repeat('7',32),7,str_repeat('8',32),3,str_repeat('d',32),4,'sender@example.invalid',new DraftContent(['client@example.invalid'],[],[],'Maximum thread','Body'),1791964800,$maxThread,str_repeat('d',64));
    $threadWire=PreparedMime::fromSnapshot($threadShape)->bytes;
    $maximumThreadHeaders=explode("\r\nMIME-Version:",$threadWire,2)[0];
    check('Maximum thread identifiers remain raw and folded only between ids',!preg_match('/(?:In-Reply-To|References):[^\r\n]*=\?/',$maximumThreadHeaders)&&str_contains($maximumThreadHeaders,'In-Reply-To: '.$longId)&&max(array_map('strlen',explode("\r\n",$threadWire)))<=998);
    $messages[]=['name'=>'maximum-thread','snapshot'=>$threadShape->privatePayload(),'mime_base64'=>base64_encode($threadWire)];

    $maxRecipients=[];for($i=0;$i<20;$i++)$maxRecipients[]=str_repeat('l',61).sprintf('%02d',$i).'@'.str_repeat('d',60).'.'.str_repeat('e',60).'.'.str_repeat('f',50).'.invalid';
    $maxAttachments=[];for($i=0;$i<4;$i++)$maxAttachments[]=new DraftAttachment(str_repeat(chr(110+$i),175).'.bin','application/'.str_repeat(chr(97+$i),88),str_repeat(chr(65+$i),131072));
    $maxHtml='<p>'.str_repeat('h',61433).'</p>';
    $maxShape=new SubmissionSnapshot(str_repeat('9',32),7,str_repeat('a',32),3,str_repeat('e',32),4,'sender@example.invalid',new DraftContent($maxRecipients,[],[],str_repeat('s',200),str_repeat("\n",20000),$maxHtml),1791964800,$maxThread,str_repeat('c',64),$maxAttachments);
    $maxMime=PreparedMime::fromSnapshot($maxShape);
    check('Maximum accepted snapshot exceeds stale bound but fits exact one-MiB boundary',strlen($maxMime->bytes)>786432&&strlen($maxMime->bytes)<=PreparedMime::MAX_BYTES&&PreparedMime::MAX_BYTES===1048576);
    check('Maximum MIME is APPEND-compatible ASCII CRLF with bounded lines',str_contains($maxMime->bytes,"\r\n\r\n")&&!preg_match('/(?<!\r)\n|\r(?!\n)|[^\x09\x0A\x0D\x20-\x7E]/',$maxMime->bytes)&&max(array_map('strlen',explode("\r\n",$maxMime->bytes)))<=998);
    $messages[]=['name'=>'maximum-shape','snapshot'=>$maxShape->privatePayload(),'mime_base64'=>base64_encode($maxMime->bytes)];
    $subjects=['Simple subject',' Leading and trailing spaces ','=?UTF-8?B?Tm90IGV2YWx1YXRlZA==?=','Résumé — project','',str_repeat('é',100),str_repeat('x',200),'Question? Equals= underscore_',str_repeat('🚀',50),str_repeat("e\u{0301}",66)];
    foreach($subjects as $i=>$subject){
        $draft=new DraftContent(['Client+work@example.invalid'],['copy@example.invalid'],['private-envelope@example.invalid'],$subject,".first\n..second\n\nRésumé\tliteral </textarea><script>no()</script>\n");$snap=snapshot($draft);$a=PreparedMime::fromSnapshot($snap);$b=PreparedMime::fromSnapshot($snap);
        check('MIME bytes are repeatable for subject case '.$i,$a->bytes===$b->bytes&&$a->sha256===hash('sha256',$a->bytes));
        check('Private Bcc stays out of MIME headers for subject case '.$i,!str_contains(explode("\r\n\r\n",$a->bytes,2)[0],'private-envelope@example.invalid'));
        $messages[]=['name'=>'subject-'.$i,'snapshot'=>$snap->privatePayload(),'mime_base64'=>base64_encode($a->bytes)];
    }
    $addresses=[];for($i=0;$i<20;$i++)$addresses[]=str_repeat('l',61).sprintf('%02d',$i).'@'.str_repeat('d',60).'.'.str_repeat('e',60).'.'.str_repeat('f',50).'.invalid';
    foreach([
        'long-recipients'=>new DraftContent($addresses,[],[],'Long recipient list',str_repeat('b',20000)),
        'bcc-only'=>new DraftContent([],[],['private-envelope@example.invalid'],'',''),
        'cc-only'=>new DraftContent([],['copy@example.invalid'],[],'Only Cc',"no final newline"),
        'literal-dots'=>new DraftContent(['client@example.invalid'],[],[],'Dot lines',".\n..\n.\r\n<script>literal</script>"),
    ] as $name=>$draft){
        $snap=snapshot($draft);$a=PreparedMime::fromSnapshot($snap);check('Bounded immutable MIME for '.$name,strlen($a->bytes)<=PreparedMime::MAX_BYTES&&$a->snapshot===$snap);
        $messages[]=['name'=>$name,'snapshot'=>$snap->privatePayload(),'mime_base64'=>base64_encode($a->bytes)];
    }
    $references=[];for($i=0;$i<19;$i++)$references[]='<'.str_repeat('r',58).sprintf('%02d',$i).'@x>';
    $parent='<'.str_repeat('p',60).'@x>';
    $thread=MessageThread::reply($parent,$references);
    $threaded=new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,"Studio+o'neil@example.invalid",new DraftContent(['client@example.invalid'],[],[],'Long thread','Body'),1791964800,$thread,str_repeat('b',64));
    $threadedMime=PreparedMime::fromSnapshot($threaded);
    $threadHeaders=explode("\r\n\r\n",$threadedMime->bytes,2)[0];$inReply=[];$referenceHeader=[];
    preg_match('/^In-Reply-To:[^\r\n]*(?:\r\n[ \t][^\r\n]*)*/m',$threadHeaders,$inReply);
    preg_match('/^References:[^\r\n]*(?:\r\n[ \t][^\r\n]*)*/m',$threadHeaders,$referenceHeader);
    check('Long structured thread is not transformed into encoded words',strlen(implode(' ',$thread->references))>982&&count($inReply)===1&&count($referenceHeader)===1&&!str_contains($inReply[0].$referenceHeader[0],'=?'));
    $messages[]=['name'=>'long-thread','snapshot'=>$threaded->privatePayload(),'mime_base64'=>base64_encode($threadedMime->bytes)];
    foreach(['In-Reply-To','References'] as $header){$mail=new OfflineMailer();$mail->setFrom('sender@example.invalid');$mail->addAddress('recipient@example.invalid');$mail->Body='Alias fixture';$identifier='<safe@example.com>';$aliases=[&$identifier];$accepted=$mail->addIdentifierHeader($header,$aliases);$identifier="<safe@example.com>\r\nBcc: injected@example.invalid";$prepared=$mail->preSend();$wire=$mail->getSentMIMEMessage();check('Structured '.$header.' detaches caller aliases before render',$accepted&&$prepared&&str_contains($wire,$header.': <safe@example.com>')&&!str_contains($wire,"\r\nBcc: injected@example.invalid"));unset($aliases,$identifier);}
    $denied=false;try{PreparedMime::fromSnapshot(snapshot(new DraftContent(['Client@example.invalid','client@example.invalid'],[],[],'','')));}catch(PreparationFailure $e){$denied=$e->reason==='recipient_mismatch'&&!str_contains($e->getMessage(),'example.invalid')&&$e->getPrevious()===null;}
    check('Maintained library case conflation refuses instead of silently dropping a recipient',$denied);
    $one=PreparedMime::fromSnapshot(snapshot(new DraftContent(['client@example.invalid'],[],['first-private@example.invalid'],'Same visible message','Body')));$two=PreparedMime::fromSnapshot(snapshot(new DraftContent(['client@example.invalid'],[],['second-private@example.invalid'],'Same visible message','Body')));
    check('Full snapshot fingerprint binds hidden envelope even when MIME checksum is identical',$one->bytes===$two->bytes&&$one->sha256===$two->sha256&&$one->snapshot->fingerprint!==$two->snapshot->fingerprint);
    $formatOne=new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,'Studio+work@Example.INVALID',new DraftContent(['Client@example.invalid'],['copy@example.invalid'],['private@example.invalid'],'Project update','Saved literal body'),1791964800);
    $formatOneMime=PreparedMime::fromSnapshot($formatOne);
    check('Ordinary format-1 MIME bytes retain their exact checksum',$formatOneMime->sha256==='eded77d9b87f652450215918e2fb55e890fd20c1eb8298c3d9b1f73ed96281c7');
    check('Ordinary format-1 MIME does not invent reply-thread headers',!str_contains($formatOneMime->bytes,"\r\nIn-Reply-To:")&&!str_contains($formatOneMime->bytes,"\r\nReferences:"));
    $mail=new OfflineMailer();$denied=false;try{$mail->postSend();}catch(LogicException){$denied=true;}check('Offline mailer postSend refuses any delivery',$denied);
    $mail->setFrom('sender@example.invalid');$mail->addAddress('recipient@example.invalid');$mail->Body='Fixture';$denied=false;try{$mail->send();}catch(LogicException){$denied=true;}check('Accidental high-level send also refuses delivery',$denied);
    $old=date_default_timezone_get();date_default_timezone_set('Pacific/Auckland');$one=PreparedMime::fromSnapshot(snapshot(new DraftContent(['client@example.invalid'],[],[],'Time','Body')))->bytes;date_default_timezone_set('America/Chicago');$two=PreparedMime::fromSnapshot(snapshot(new DraftContent(['client@example.invalid'],[],[],'Time','Body')))->bytes;date_default_timezone_set($old);check('Saved Date and bytes do not depend on host timezone',$one===$two);
    $complete=true;
} finally {
    if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$complete&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Offline maintained MIME preparation only; synthetic fixture content','checks'=>$checks,'messages'=>$messages],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." maintained MIME preparation checks; ".count($messages)." independent parser fixtures\n";
