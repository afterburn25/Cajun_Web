<?php
declare(strict_types=1);
foreach(['EndpointPolicy','ProbeChannel','ProbeFailure','SecureChannel','ConnectionProbe'] as $file)require __DIR__.'/../../foundation/mail-transport/'.$file.'.php';
use CajunWebPro\Foundation\MailTransport\SecureChannel;
use CajunWebPro\Foundation\MailTransport\ConnectionProbe;
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
// Synthetic loopback fixture only. Production has no private-address or alternate-CA switch.
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
$GLOBALS['tls_fixture']=$input;require __DIR__.'/tls_connector.php';$channel=null;
try {
    $port=['imap'=>['ssl'=>993,'starttls'=>143],'smtp'=>['ssl'=>465,'starttls'=>587]][$input['protocol']][$input['encryption']];
    $channel=SecureChannel::open($input['protocol'],$input['host'],$port,$input['encryption'],'8.8.8.8');
    (new ReflectionClass(SecureChannel::class))->getProperty('deadline')->setValue($channel,hrtime(true)/1e9+($input['budget']??4));
    (new ConnectionProbe($channel,static function() use ($input): void {if ($input['revoke']??false) throw new ProbeFailure('credential');}))->run($input['protocol'],$input['encryption'],'synthetic@example.net','synthetic fixture password');
    echo json_encode(['ok'=>true,'pinned'=>$GLOBALS['pinned_verified']??false]);
} catch(ProbeFailure $e) {echo json_encode(['ok'=>false,'reason'=>$e->getMessage(),'pinned'=>$GLOBALS['pinned_verified']??false]);}
finally {$channel?->close();}
