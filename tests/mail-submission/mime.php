<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/PreparedMime.php';
use CajunWebPro\Foundation\MailCompose\DraftContent;
use CajunWebPro\Foundation\MailSubmission\{SubmissionSnapshot,PreparedMime,PreparationFailure,OfflineMailer};
$checks=[];$messages=[];$complete=false;
function check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function snapshot(DraftContent $content):SubmissionSnapshot{return new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,"Studio+o'neil@example.invalid",$content,1791964800);}
try {
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
    $denied=false;try{PreparedMime::fromSnapshot(snapshot(new DraftContent(['Client@example.invalid','client@example.invalid'],[],[],'','')));}catch(PreparationFailure $e){$denied=$e->reason==='recipient_mismatch'&&!str_contains($e->getMessage(),'example.invalid')&&$e->getPrevious()===null;}
    check('Maintained library case conflation refuses instead of silently dropping a recipient',$denied);
    $one=PreparedMime::fromSnapshot(snapshot(new DraftContent(['client@example.invalid'],[],['first-private@example.invalid'],'Same visible message','Body')));$two=PreparedMime::fromSnapshot(snapshot(new DraftContent(['client@example.invalid'],[],['second-private@example.invalid'],'Same visible message','Body')));
    check('Full snapshot fingerprint binds hidden envelope even when MIME checksum is identical',$one->bytes===$two->bytes&&$one->sha256===$two->sha256&&$one->snapshot->fingerprint!==$two->snapshot->fingerprint);
    $mail=new OfflineMailer();$denied=false;try{$mail->postSend();}catch(LogicException){$denied=true;}check('Offline mailer postSend refuses any delivery',$denied);
    $mail->setFrom('sender@example.invalid');$mail->addAddress('recipient@example.invalid');$mail->Body='Fixture';$denied=false;try{$mail->send();}catch(LogicException){$denied=true;}check('Accidental high-level send also refuses delivery',$denied);
    $old=date_default_timezone_get();date_default_timezone_set('Pacific/Auckland');$one=PreparedMime::fromSnapshot(snapshot(new DraftContent(['client@example.invalid'],[],[],'Time','Body')))->bytes;date_default_timezone_set('America/Chicago');$two=PreparedMime::fromSnapshot(snapshot(new DraftContent(['client@example.invalid'],[],[],'Time','Body')))->bytes;date_default_timezone_set($old);check('Saved Date and bytes do not depend on host timezone',$one===$two);
    $html='<p>Hello <strong>client</strong> — résumé</p><table><tr><td>C</td></tr></table>';
    $rich=new DraftContent(['client@example.invalid'],[],[],'Rich','Plain fallback body',$html);
    $payload=snapshot($rich)->privatePayload();
    check('Formatted alternative uses format 5 with canonical key order',$payload['format']===5&&array_keys($payload)===['format','id','author_id','draft_id','draft_revision','account_id','account_revision','from','message_id','prepared_epoch','to','cc','bcc','subject','body','body_html']&&$payload['body_html']===$html);
    $a=PreparedMime::fromSnapshot(snapshot($rich));$b=PreparedMime::fromSnapshot(snapshot($rich));
    check('Formatted alternative MIME bytes are repeatable',$a->bytes===$b->bytes&&$a->sha256===hash('sha256',$a->bytes));
    [$headers,$parts]=explode("\r\n\r\n",$a->bytes,2);
    check('Formatted alternative produces multipart alternative framing',str_contains($headers,'multipart/alternative')&&str_contains($parts,'Content-Type: text/plain')&&str_contains($parts,'Content-Type: text/html'));
    check('Formatted alternative keeps the exact plain body as a separate part',str_contains($a->bytes,chunk_split(base64_encode('Plain fallback body'),76,"\r\n"))||str_contains($a->bytes,base64_encode('Plain fallback body')));
    check('Formatted alternative emits the sanitized fragment as the html part',str_contains($a->bytes,base64_encode(str_replace("\n","\r\n",$html)))||str_contains($a->bytes,chunk_split(base64_encode($html),76,"\r\n")));
    check('Formatted alternative bytes stay wire-safe and bounded',!preg_match('/[^\x09\x0A\x0D\x20-\x7E]/',$a->bytes)&&strlen($a->bytes)<=PreparedMime::MAX_BYTES);
    check('Raw fragment markup never appears unencoded on the wire',!str_contains($a->bytes,'<strong>client</strong>'));
    $thread=\CajunWebPro\Foundation\MailCompose\MessageThread::reply('<parent@example.invalid>',['<ref@example.invalid>']);
    $full=new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,'sender@example.invalid',$rich,1791964800,$thread,str_repeat('f',64),[new \CajunWebPro\Foundation\MailCompose\DraftAttachment('a.txt','text/plain','data')]);
    check('Formats cover thread attachments and the alternative together',array_keys($full->privatePayload())===['format','id','author_id','draft_id','draft_revision','account_id','account_revision','from','message_id','prepared_epoch','to','cc','bcc','subject','body','body_html','thread','origin_fingerprint','attachments']&&$full->privatePayload()['format']===8);
    $mixed=PreparedMime::fromSnapshot($full);
    check('Formatted alternative composes inside the attachment wrapper',str_contains($mixed->bytes,'multipart/mixed')&&str_contains($mixed->bytes,'multipart/alternative'));
    check('Plain payload keys are unchanged without an alternative',!array_key_exists('body_html',snapshot(new DraftContent(['client@example.invalid'],[],[],'S','B'))->privatePayload()));
    $complete=true;
} finally {
    if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$complete&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Offline maintained MIME preparation only; synthetic fixture content','checks'=>$checks,'messages'=>$messages],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." maintained MIME preparation checks; ".count($messages)." independent parser fixtures\n";
