<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/SubmissionChannel.php';
require __DIR__.'/../../foundation/mail-submission/SubmissionTransport.php';
use CajunWebPro\Foundation\MailSubmission\{SubmissionChannel,SubmissionTransport,SubmissionSnapshot,PreparedMime};
use CajunWebPro\Foundation\MailCompose\{DraftAttachment,DraftContent,MessageThread};
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
$GLOBALS['submission_fixture']=$input;require __DIR__.'/tls_connector.php';$channel=null;
try {
    if($input['maximum']??false){
        $domain=str_repeat('d',63).'.'.str_repeat('e',63).'.'.str_repeat('f',61);
        $to=[];for($i=0;$i<20;$i++)$to[]=str_repeat('l',62).sprintf('%02d',$i).'@'.$domain;
        $references=[];for($i=0;$i<7;$i++)$references[]='<'.str_repeat(chr(97+$i),494).'@example.invalid>';
        $references[]='<'.str_repeat('z',487).'@example.invalid>';
        $thread=MessageThread::fromStored(['in_reply_to'=>'<'.str_repeat('i',494).'@example.invalid>','references'=>$references]);
        $attachments=[];for($i=0;$i<4;$i++)$attachments[]=new DraftAttachment(str_repeat(chr(110+$i),180),'application/'.str_repeat(chr(97+$i),88),str_repeat(chr(65+$i),131072));
        $message=PreparedMime::fromSnapshot(new SubmissionSnapshot(str_repeat('9',32),7,str_repeat('a',32),3,str_repeat('e',32),4,str_repeat('s',64).'@'.$domain,
            new DraftContent($to,[],[],str_repeat('é',100),str_repeat("\n",20000),'<pre>'.str_repeat("\n",61429).'</pre>'),1791964800,$thread,str_repeat('c',64),$attachments));
    }else{
        $body=($input['large']??false)?str_repeat('x',20000):".literal\n..second\nRésumé";
        $message=PreparedMime::fromSnapshot(new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,'sender@example.invalid',
            new DraftContent(['first@example.invalid'],['second@example.invalid'],['private@example.invalid'],'Synthetic TLS',$body),1791964800));
    }
    $channel=SubmissionChannel::open($input['host'],$input['encryption']==='ssl'?465:587,$input['encryption'],'8.8.8.8');
    (new ReflectionClass(SubmissionChannel::class))->getProperty('deadline')->setValue($channel,hrtime(true)/1e9+($input['budget']??4.0));
    $calls=0;
    $outcome=(new SubmissionTransport)->submit($message,str_repeat('b',32),$channel,$input['encryption'],'synthetic@example.net','synthetic fixture password',
        static function()use($input,&$calls):void{$calls++;if(($input['revoke_at']??0)===$calls)throw new ProbeFailure('access_changed');});
    echo json_encode(['outcome'=>$outcome,'pinned'=>$GLOBALS['pinned_verified']??false,'mime_sha256'=>$message->sha256,'mime_bytes'=>strlen($message->bytes)]);
}catch(ProbeFailure $e){echo json_encode(['open_failure'=>$e->getMessage(),'pinned'=>$GLOBALS['pinned_verified']??false]);}
finally{$channel?->close();}
