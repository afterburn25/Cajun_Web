<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
/* Small, bounded SMTP client. TLS is mandatory for SMTP; certificates and peer names are verified. */
function smtp_remaining($stream): void {
    $remaining=($GLOBALS['cwp_smtp_deadline']??(microtime(true)+8))-microtime(true);
    if($remaining<=0)throw new RuntimeException('SMTP exceeded its delivery time budget. The saved inquiry remains available in Studio.');
    $seconds=min(8.0,$remaining);stream_set_timeout($stream,(int)$seconds,(int)(($seconds-(int)$seconds)*1000000));
}
function smtp_read($stream, array $expected): string {
    $reply='';$code=0;
    for($i=0;$i<60;$i++) { smtp_remaining($stream);$line=fgets($stream,4096); if($line===false) throw new RuntimeException('SMTP connection closed or timed out.'); $reply.=$line; if(strlen($reply)>20000) throw new RuntimeException('SMTP response too large.'); if(!preg_match('/^(\d{3})([ -])/', $line,$m)) throw new RuntimeException('Invalid SMTP response.');$code=(int)$m[1];if($m[2]===' ')break; }
    if(!in_array($code,$expected,true)) throw new RuntimeException('SMTP server rejected the request ('.$code.').'); return $reply;
}
function smtp_write($stream,string $data): void { $len=strlen($data);$pos=0;while($pos<$len){smtp_remaining($stream);$n=fwrite($stream,substr($data,$pos));if($n===false||$n===0)throw new RuntimeException('SMTP write failed.');$pos+=$n;} }
function smtp_command($stream,string $command,array $expected): string { smtp_write($stream,$command."\r\n");return smtp_read($stream,$expected); }
function send_message(string $recipient,string $subject,string $body,?string $replyTo=null): string {
    $mode=setting('email_mode','disabled');if($mode==='disabled')return 'not_configured';
    $from=setting('mail_from');foreach([$from,$recipient] as $address)if(!filter_var($address,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$address))throw new RuntimeException('A valid sender and notification email are required.');
    if($replyTo && (!filter_var($replyTo,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$replyTo)))throw new RuntimeException('Invalid reply address.');
    $subject=preg_replace('/[\r\n]/',' ',$subject)??'Website notification';
    $encodedSubject='=?UTF-8?B?'.base64_encode($subject).'?=';
    $headers=['Date: '.gmdate('D, d M Y H:i:s').' +0000','From: CajunWebPro <'.$from.'>','To: '.$recipient,'Subject: '.$encodedSubject,'Message-ID: <'.bin2hex(random_bytes(16)).'@'.substr(strrchr($from,'@'),1).'>','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64'];
    if($replyTo)$headers[]='Reply-To: '.$replyTo;
    $payload=implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
    if($mode==='mail') { if(!function_exists('mail'))throw new RuntimeException('PHP mail() is unavailable.');$mailHeaders=array_filter($headers,fn($h)=>!str_starts_with($h,'Subject:')&&!str_starts_with($h,'To:'));if(!@mail($recipient,$encodedSubject,chunk_split(base64_encode($body),76,"\r\n"),implode("\r\n",$mailHeaders)))throw new RuntimeException('The host did not accept the email.');return 'accepted'; }
    if($mode!=='smtp')throw new RuntimeException('Select a valid delivery method.');
    $host=setting('smtp_host');$port=(int)setting('smtp_port','587');$security=setting('smtp_security','tls');
    if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$host)||strlen($host)>253||!in_array($port,[465,587,2525],true)||!in_array($security,['tls','ssl'],true))throw new RuntimeException('Invalid SMTP host, port or encryption mode.');
    $ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>$host,'SNI_enabled'=>true]]);
    $GLOBALS['cwp_smtp_deadline']=microtime(true)+22;
    $s=@stream_socket_client(($security==='ssl'?'ssl':'tcp').'://'.$host.':'.$port,$errno,$errstr,8,STREAM_CLIENT_CONNECT,$ctx);if(!$s)throw new RuntimeException('Cannot connect securely to SMTP. Check the host, port and outbound-mail policy.');
    smtp_remaining($s);
    try { smtp_read($s,[220]);$greeting=smtp_command($s,'EHLO cajunwebpro.local',[250]);
        if($security==='tls'){if(stripos($greeting,'STARTTLS')===false)throw new RuntimeException('The SMTP server does not advertise STARTTLS.');smtp_command($s,'STARTTLS',[220]);smtp_remaining($s);if(!stream_socket_enable_crypto($s,true,STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT))throw new RuntimeException('TLS negotiation failed.');smtp_command($s,'EHLO cajunwebpro.local',[250]);}
        $user=setting('smtp_user');if($user!==''){smtp_command($s,'AUTH LOGIN',[334]);smtp_command($s,base64_encode($user),[334]);smtp_command($s,base64_encode(decrypt_secret(setting('smtp_password'))),[235]);}
        smtp_command($s,'MAIL FROM:<'.$from.'>',[250]);smtp_command($s,'RCPT TO:<'.$recipient.'>',[250,251]);smtp_command($s,'DATA',[354]);smtp_write($s,$payload."\r\n.\r\n");smtp_read($s,[250]);@fwrite($s,"QUIT\r\n");return 'accepted';
    } finally {fclose($s);unset($GLOBALS['cwp_smtp_deadline']);}
}
function notify_lead(array $lead): string { return send_message(setting('notification_email'),'New project inquiry · '.$lead['reference'],"New CajunWebPro project inquiry\n\nReference: ".$lead['reference']."\nName: ".$lead['name']."\nEmail: ".$lead['email']."\nBusiness: ".$lead['company']."\nPhone: ".$lead['phone']."\nService: ".$lead['service']."\nBudget: ".$lead['budget']."\nTimeline: ".$lead['timeline']."\n\n".$lead['message']."\n\nManage this inquiry: ".admin_url('leads'),$lead['email']); }
