<?php
declare(strict_types=1);
foreach(['EndpointPolicy','ProbeChannel','ProbeFailure','SecureChannel','ConnectionProbe'] as $file)require __DIR__.'/../../foundation/mail-transport/'.$file.'.php';
use CajunWebPro\Foundation\MailTransport\SecureChannel;
use CajunWebPro\Foundation\MailTransport\ConnectionProbe;
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
// Synthetic loopback fixture only. Production has no private-address or alternate-CA switch.
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
$ctx=stream_context_create(['ssl'=>['cafile'=>$input['ca'],'peer_name'=>$input['host'],'verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'SNI_enabled'=>true,'disable_compression'=>true]]);
$stream=stream_socket_client('tcp://127.0.0.1:'.$input['port'],$eno,$err,2,STREAM_CLIENT_CONNECT,$ctx);
$reflection=new ReflectionClass(SecureChannel::class);$channel=$reflection->newInstanceWithoutConstructor();
$reflection->getProperty('stream')->setValue($channel,$stream);
$reflection->getProperty('deadline')->setValue($channel,hrtime(true)/1e9+($input['budget']??4));
try {
    if ($input['encryption']==='ssl') $channel->startTls();
    (new ConnectionProbe($channel,static function() use ($input): void {if ($input['revoke']??false) throw new ProbeFailure('credential');}))->run($input['protocol'],$input['encryption'],'synthetic@example.net','synthetic fixture password');
    echo json_encode(['ok'=>true]);
} catch(ProbeFailure $e) {echo json_encode(['ok'=>false,'reason'=>$e->getMessage()]);}
finally {$channel->close();}
