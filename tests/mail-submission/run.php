<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/SubmissionSnapshot.php';
use CajunWebPro\Foundation\MailCompose\{DraftAttachment,DraftContent,MessageThread};
use CajunWebPro\Foundation\MailSubmission\SubmissionSnapshot;
$checks=[];$completed=false;
function check(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function refused(callable $fn):bool {try{$fn();return false;}catch(InvalidArgumentException|TypeError){return true;}}
function snapshot(array $change=[]):SubmissionSnapshot {
    return new SubmissionSnapshot(...array_replace([
        'id'=>str_repeat('1',32),'authorId'=>7,'draftId'=>str_repeat('2',32),'draftRevision'=>3,
        'accountId'=>str_repeat('a',32),'accountRevision'=>4,'from'=>'Studio+work@Example.INVALID',
        'content'=>new DraftContent(['Client@example.invalid'],['copy@example.invalid'],['private@example.invalid'],'Project update','Saved literal body'),
        'preparedEpoch'=>1791964800,
    ],$change));
}
try {
    $s=snapshot();
    $baseKeys=['format','id','author_id','draft_id','draft_revision','account_id','account_revision','from','message_id','prepared_epoch','to','cc','bcc','subject','body'];
    check('Plain format-1 key order remains exact',array_keys($s->privatePayload())===$baseKeys&&$s->privatePayload()['format']===1);
    check('Plain format-1 golden fingerprint remains exact',$s->fingerprint==='ca52583c770423f97cb04d4fa84f0954da466e66e5f28d8db6a6cb1b92a55721');
    check('Saved sender keeps local spelling and normalizes its domain',$s->from==='Studio+work@example.invalid');
    check('Message identity uses opaque submission and selected sender domain',$s->messageId==='<cwp.'.str_repeat('1',32).'@example.invalid>');
    check('Identical saved preparation has a stable fingerprint',$s->fingerprint===snapshot()->fingerprint);
    check('Equivalent sender domain case has a stable fingerprint',$s->fingerprint===snapshot(['from'=>'Studio+work@example.invalid'])->fingerprint);
    $formatOneJson=json_encode($s->privatePayload(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    check('Ordinary preparation retains the exact format-1 private payload bytes',$formatOneJson==='{"format":1,"id":"11111111111111111111111111111111","author_id":7,"draft_id":"22222222222222222222222222222222","draft_revision":3,"account_id":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","account_revision":4,"from":"Studio+work@example.invalid","message_id":"<cwp.11111111111111111111111111111111@example.invalid>","prepared_epoch":1791964800,"to":["Client@example.invalid"],"cc":["copy@example.invalid"],"bcc":["private@example.invalid"],"subject":"Project update","body":"Saved literal body"}');
    check('Ordinary preparation retains the exact format-1 fingerprint',$s->fingerprint==='ca52583c770423f97cb04d4fa84f0954da466e66e5f28d8db6a6cb1b92a55721');
    check('All three recipient classes appear once in the envelope',$s->envelopeRecipients()===['Client@example.invalid','copy@example.invalid','private@example.invalid']);
    check('Recipient visibility classes remain separate in the private snapshot',$s->privatePayload()['to']===['Client@example.invalid']&&$s->privatePayload()['cc']===['copy@example.invalid']&&$s->privatePayload()['bcc']===['private@example.invalid']);
    foreach (['id'=>str_repeat('3',32),'authorId'=>8,'draftId'=>str_repeat('3',32),'draftRevision'=>4,'accountId'=>str_repeat('b',32),'accountRevision'=>5,'from'=>'other@example.invalid','preparedEpoch'=>1791964801] as $key=>$value) {
        check('Fingerprint changes when saved '.$key.' changes',snapshot([$key=>$value])->fingerprint!==$s->fingerprint);
    }
    foreach (['to','cc','bcc','subject','body'] as $field) {
        $p=['to'=>['Client@example.invalid'],'cc'=>['copy@example.invalid'],'bcc'=>['private@example.invalid'],'subject'=>'Project update','body'=>'Saved literal body'];
        $p[$field]=in_array($field,['to','cc','bcc'],true)?['changed@example.invalid']:'Changed text';
        check('Fingerprint binds private '.$field,snapshot(['content'=>new DraftContent(...$p)])->fingerprint!==$s->fingerprint);
    }
    foreach (['id','draftId','accountId'] as $field) {
        foreach (['',str_repeat('f',31),str_repeat('f',32)."\n",str_repeat('F',32)] as $i=>$bad) {
            check('Exact '.$field.' refuses malformed identity '.$i,refused(fn()=>snapshot([$field=>$bad])));
        }
    }
    foreach (['authorId','draftRevision','accountRevision'] as $field) {
        check('Saved '.$field.' cannot be zero',refused(fn()=>snapshot([$field=>0])));
        check('Saved '.$field.' cannot be negative',refused(fn()=>snapshot([$field=>-1])));
    }
    foreach ([0,-1,253402300800] as $bad) check('Timestamp refuses unsupported epoch '.$bad,refused(fn()=>snapshot(['preparedEpoch'=>$bad])));
    check('An unfinished draft with no recipients cannot prepare',refused(fn()=>snapshot(['content'=>new DraftContent([],[],[],'','')])));
    check('Bcc-only mail retains its envelope without inventing visible recipients',snapshot(['content'=>new DraftContent([],[],['private@example.invalid'],'','')])->envelopeRecipients()===['private@example.invalid']);
    foreach (["sender@example.invalid\r\nBcc: hidden@example.invalid",'Display <sender@example.invalid>','sender@localhost'] as $i=>$bad) check('Sender uses canonical bare-address refusal '.$i,refused(fn()=>snapshot(['from'=>$bad])));
    $a='first@example.invalid';$to=[&$a];$draft=new DraftContent($to,[],[],'Snapshot','Original');$f=snapshot(['content'=>$draft]);$hash=$f->fingerprint;$a='changed@example.invalid';
    check('External recipient reference cannot mutate prepared content',$f->envelopeRecipients()===['first@example.invalid']&&$f->fingerprint===$hash);
    $copy=$f->privatePayload();$copy['to'][0]='changed@example.invalid';$copy['body']='Changed';
    check('Returned private payload cannot mutate the saved object',$f->privatePayload()['to']===['first@example.invalid']&&$f->content->body==='Original');
    $copy=$f->envelopeRecipients();$copy[0]='changed@example.invalid';check('Returned envelope cannot mutate the saved object',$f->envelopeRecipients()===['first@example.invalid']);
    $immutable=false;try{$f->accountRevision=99;}catch(Error){$immutable=true;}check('Account revision cannot be retargeted after preparation',$immutable);
    $content=new DraftContent(['Client@example.invalid'],[],[],'Résumé — project',".leading\r\n</textarea><script>literal</script>\rLast");$u=snapshot(['content'=>$content]);
    check('UTF8 and hostile-looking text stay literal under canonical newline normalization',$u->content->subject==='Résumé — project'&&$u->content->body===".leading\n</textarea><script>literal</script>\nLast");
    check('Case-distinct local recipients are retained for later MIME compatibility validation',snapshot(['content'=>new DraftContent(['Client@example.invalid','client@example.invalid'],[],[],'','')])->envelopeRecipients()===['Client@example.invalid','client@example.invalid']);
    check('Preparation retains valid maximum draft text',strlen(snapshot(['content'=>new DraftContent(['client@example.invalid'],[],[],str_repeat('s',200),str_repeat('b',20000))])->content->body)===20000);

    $html='<p>Formatted</p>';
    $thread=MessageThread::reply('<parent@example.invalid>',['<root@example.invalid>']);
    $origin=str_repeat('f',64);
    $one=new DraftAttachment('one.txt','text/plain','PRIVATE-ONE');
    $two=new DraftAttachment('two.bin','application/octet-stream',"\x00PRIVATE-TWO\xff");
    for($bits=0;$bits<8;$bits++){
        $rich=($bits&4)!==0;
        $withThread=($bits&1)!==0;
        $withAttachments=($bits&2)!==0;
        $shape=snapshot([
            'content'=>new DraftContent(['Client@example.invalid'],['copy@example.invalid'],['private@example.invalid'],'Project update','Saved literal body',$rich?$html:null),
            'thread'=>$withThread?$thread:null,
            'originFingerprint'=>$withThread?$origin:null,
            'attachments'=>$withAttachments?[$one,$two]:[],
        ]);
        $expected=$baseKeys;
        if($rich)$expected[]='body_html';
        if($withThread){$expected[]='thread';$expected[]='origin_fingerprint';}
        if($withAttachments)$expected[]='attachments';
        check('Format '.(1+$bits).' has exact bit value and key order',$shape->privatePayload()['format']===1+$bits&&array_keys($shape->privatePayload())===$expected);
    }
    $htmlSnap=snapshot(['content'=>new DraftContent(['Client@example.invalid'],['copy@example.invalid'],['private@example.invalid'],'Project update','Saved literal body',$html)]);
    check('Fingerprint binds canonical formatted alternative',$htmlSnap->fingerprint!==$s->fingerprint);
    $threadSnap=snapshot(['thread'=>$thread,'originFingerprint'=>$origin]);
    check('Fingerprint binds thread and origin together',$threadSnap->fingerprint!==$s->fingerprint&&$threadSnap->fingerprint!==snapshot(['thread'=>$thread,'originFingerprint'=>str_repeat('e',64)])->fingerprint);
    $attachmentSnap=snapshot(['attachments'=>[$one,$two]]);
    check('Attachment payload exposes descriptors but no raw bytes',!str_contains(json_encode($attachmentSnap->privatePayload(),JSON_THROW_ON_ERROR),'PRIVATE-ONE')&&!str_contains(json_encode($attachmentSnap->privatePayload(),JSON_THROW_ON_ERROR),'PRIVATE-TWO'));
    check('Fingerprint binds attachment name',snapshot(['attachments'=>[new DraftAttachment('other.txt','text/plain','PRIVATE-ONE'),$two]])->fingerprint!==$attachmentSnap->fingerprint);
    check('Fingerprint binds attachment MIME',snapshot(['attachments'=>[new DraftAttachment('one.txt','application/octet-stream','PRIVATE-ONE'),$two]])->fingerprint!==$attachmentSnap->fingerprint);
    check('Fingerprint binds attachment content digest and length',snapshot(['attachments'=>[new DraftAttachment('one.txt','text/plain','PRIVATE-ONE!'),$two]])->fingerprint!==$attachmentSnap->fingerprint);
    check('Fingerprint binds attachment order',snapshot(['attachments'=>[$two,$one]])->fingerprint!==$attachmentSnap->fingerprint);
    check('Thread and origin must be present together',refused(fn()=>snapshot(['thread'=>$thread]))&&refused(fn()=>snapshot(['originFingerprint'=>$origin])));
    check('Origin fingerprint is exact lowercase SHA-256 text',refused(fn()=>snapshot(['thread'=>$thread,'originFingerprint'=>str_repeat('F',64)]))&&refused(fn()=>snapshot(['thread'=>$thread,'originFingerprint'=>str_repeat('f',63)])));
    check('Attachment collection validation propagates into snapshots',refused(fn()=>snapshot(['attachments'=>['one'=>$one]]))&&refused(fn()=>snapshot(['attachments'=>[$one,new DraftAttachment('ONE.TXT','text/plain','other')]])));
    $alias=$one;$aliased=[&$alias];$detached=snapshot(['attachments'=>$aliased]);$alias=$two;
    check('External attachment array references cannot mutate a snapshot',$detached->attachments===[$one]&&$detached->fingerprint===snapshot(['attachments'=>[$one]])->fingerprint);

    $jsonError=null;try{json_encode($s,JSON_THROW_ON_ERROR);}catch(LogicException $e){$jsonError=$e;}
    check('Direct snapshot JSON fails closed without private content',$jsonError instanceof LogicException&&!str_contains($jsonError->getMessage(),'private@example.invalid'));
    $serializeError=null;try{serialize($s);}catch(LogicException $e){$serializeError=$e;}
    check('Native snapshot serialization fails closed without private content',$serializeError instanceof LogicException&&!str_contains($serializeError->getMessage(),'Saved literal body'));
    $class=SubmissionSnapshot::class;$crafted='O:'.strlen($class).':"'.$class.'":0:{}';$unserializeError=null;
    try{unserialize($crafted,['allowed_classes'=>[$class]]);}catch(LogicException $e){$unserializeError=$e;}
    check('Crafted snapshot unserialize cannot bypass constructor invariants',$unserializeError instanceof LogicException);
    $completed=true;
} finally {
    $result=['scope'=>'Offline immutable preparation only; no MIME generation, SMTP, persistence, authentication or UI acceptance','passed'=>$completed&&!in_array(false,array_column($checks,'passed'),true),'checks'=>$checks];
    if(isset($argv[1])){file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");}
}
echo 'PASS: '.count($checks)." immutable submission preparation checks on PHP ".PHP_VERSION."\n";
