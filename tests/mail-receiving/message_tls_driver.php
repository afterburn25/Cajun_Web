<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
use CajunWebPro\Foundation\MailReceiving\{MessageReader,ReceivingStream,ReadSession,ReadProfile,ReceivingFailure};
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
$GLOBALS['receiving_fixture']=$input;require __DIR__.'/tls_connector.php';$calls=0;$stream=null;
$authorize=static function()use(&$calls,$input){if(++$calls===($input['revoke']??0))throw new RuntimeException('Synthetic private grant change');};
try {
    $port=$input['encryption']==='ssl'?993:143;
    if(isset($input['budget'])) {
        $stream=new ReceivingStream($input['host'],$port,$input['encryption'],'8.8.8.8',ReadProfile::Message);
        $stream->open($input['encryption'],$input['host'],$port,20);
        (new ReflectionClass(ReceivingStream::class))->getProperty('deadline')->setValue($stream,hrtime(true)/1e9+$input['budget']);
        $result=(new ReadSession($stream,$authorize,ReadProfile::Message))->message($input['encryption'],'synthetic@example.net','synthetic fixture password','37','45');
    } else $result=MessageReader::read($input['host'],$port,$input['encryption'],'8.8.8.8','synthetic@example.net','synthetic fixture password','37','45',$authorize);
    $result['raw_bytes']=strlen($result['raw']);$result['raw_sha256']=hash('sha256',$result['raw']);unset($result['raw']);
    echo json_encode(['ok'=>true,'pinned'=>$GLOBALS['pinned_verified']??false,'calls'=>$calls,'result'=>$result],JSON_THROW_ON_ERROR);
} catch(ReceivingFailure $e) {echo json_encode(['ok'=>false,'pinned'=>$GLOBALS['pinned_verified']??false,'calls'=>$calls,'reason'=>$e->getMessage(),'chain'=>$e->getPrevious()!==null],JSON_THROW_ON_ERROR);}
finally {$stream?->close();}
