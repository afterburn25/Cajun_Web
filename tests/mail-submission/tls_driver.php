<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/SubmissionChannel.php';
require __DIR__.'/../../foundation/mail-submission/SubmissionTransport.php';
use CajunWebPro\Foundation\MailSubmission\{SubmissionChannel,SubmissionTransport,SubmissionSnapshot,PreparedMime};
use CajunWebPro\Foundation\MailCompose\DraftContent;
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
$GLOBALS['submission_fixture']=$input;require __DIR__.'/tls_connector.php';$channel=null;
try {
    $body=($input['large']??false)?str_repeat('x',20000):".literal\n..second\nRésumé";
    $message=PreparedMime::fromSnapshot(new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,'sender@example.invalid',
        new DraftContent(['first@example.invalid'],['second@example.invalid'],['private@example.invalid'],'Synthetic TLS',$body),1791964800));
    $channel=SubmissionChannel::open($input['host'],$input['encryption']==='ssl'?465:587,$input['encryption'],'8.8.8.8');
    (new ReflectionClass(SubmissionChannel::class))->getProperty('deadline')->setValue($channel,hrtime(true)/1e9+($input['budget']??4.0));
    $calls=0;
    $outcome=(new SubmissionTransport)->submit($message,str_repeat('b',32),$channel,$input['encryption'],'synthetic@example.net','synthetic fixture password',
        static function()use($input,&$calls):void{$calls++;if(($input['revoke_at']??0)===$calls)throw new ProbeFailure('access_changed');});
    echo json_encode(['outcome'=>$outcome,'pinned'=>$GLOBALS['pinned_verified']??false,'mime_sha256'=>$message->sha256]);
}catch(ProbeFailure $e){echo json_encode(['open_failure'=>$e->getMessage(),'pinned'=>$GLOBALS['pinned_verified']??false]);}
finally{$channel?->close();}
