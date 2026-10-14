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
    $complete=true;
} finally {
    if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$complete&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Offline maintained MIME preparation only; synthetic fixture content','checks'=>$checks,'messages'=>$messages],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." maintained MIME preparation checks; ".count($messages)." independent parser fixtures\n";
