<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/SubmissionSnapshot.php';
use CajunWebPro\Foundation\MailCompose\DraftContent;
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
    check('Saved sender keeps local spelling and normalizes its domain',$s->from==='Studio+work@example.invalid');
    check('Message identity uses opaque submission and selected sender domain',$s->messageId==='<cwp.'.str_repeat('1',32).'@example.invalid>');
    check('Identical saved preparation has a stable fingerprint',$s->fingerprint===snapshot()->fingerprint);
    check('Equivalent sender domain case has a stable fingerprint',$s->fingerprint===snapshot(['from'=>'Studio+work@example.invalid'])->fingerprint);
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
    $completed=true;
} finally {
    $result=['scope'=>'Offline immutable preparation only; no MIME generation, SMTP, persistence, authentication or UI acceptance','passed'=>$completed&&!in_array(false,array_column($checks,'passed'),true),'checks'=>$checks];
    if(isset($argv[1])){file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");}
}
echo 'PASS: '.count($checks)." immutable submission preparation checks on PHP ".PHP_VERSION."\n";
