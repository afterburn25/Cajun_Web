<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/../mail-transport/EndpointPolicy.php';
require_once __DIR__.'/../mail-transport/ProbeChannel.php';
require_once __DIR__.'/../mail-transport/ProbeFailure.php';
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,ProbeChannel,ProbeFailure};

/** One pinned public SMTP socket with original-host TLS verification and finite I/O. */
final class SubmissionChannel implements ProbeChannel
{
    private bool $tls=false;
    private int $received=0;
    private int $sent=0;
    private int $lines=0;
    private int $writes=0;
    private function __construct(private $stream,private float $deadline) {}

    /** Caller resolves and validates the whole DNS answer set before selecting an address. */
    public static function open(string $host,int $port,string $encryption,string $address):self
    {
        $host=EndpointPolicy::host($host);EndpointPolicy::port('smtp',$port,$encryption);
        $address=EndpointPolicy::addresses([$address])[0];$deadline=hrtime(true)/1e9+30.0;
        if(!extension_loaded('openssl'))throw new ProbeFailure('tls');
        $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,
            'peer_name'=>$host,'SNI_enabled'=>true,'crypto_method'=>STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            'disable_compression'=>true]]);
        $socket='tcp://'.(str_contains($address,':')?'['.$address.']':$address).':'.$port;
        try {$stream=@stream_socket_client($socket,$errno,$error,5.0,STREAM_CLIENT_CONNECT,$context);}
        catch(\Throwable){throw new ProbeFailure('connection');}
        if(!is_resource($stream))throw new ProbeFailure('connection');
        $channel=new self($stream,$deadline);
        try {if($encryption==='ssl')$channel->startTls();return $channel;}
        catch(\Throwable $e){$channel->close();throw $e;}
    }
    private function remaining():void
    {
        if(!is_resource($this->stream))throw new ProbeFailure('connection');
        $left=min(5.0,$this->deadline-hrtime(true)/1e9);
        if($left<=0)throw new ProbeFailure('timeout');
        $seconds=(int)$left;$micros=max(1,(int)(($left-$seconds)*1000000));
        if(!stream_set_timeout($this->stream,$seconds,$micros))throw new ProbeFailure('connection');
    }
    public function isTls():bool {return $this->tls&&is_resource($this->stream);}
    public function startTls():void
    {
        $this->remaining();
        if($this->tls || (stream_get_meta_data($this->stream)['unread_bytes']??0)>0)throw new ProbeFailure('tls');
        try {$ok=@stream_socket_enable_crypto($this->stream,true,STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);}
        catch(\Throwable){throw new ProbeFailure('tls');}
        if($ok!==true)throw new ProbeFailure('tls');
        $meta=stream_get_meta_data($this->stream);
        if(!in_array($meta['crypto']['protocol']??'',['TLSv1.2','TLSv1.3'],true))throw new ProbeFailure('tls');
        $this->tls=true;$this->remaining();
    }
    public function line():string
    {
        $this->remaining();if(++$this->lines>160)throw new ProbeFailure('limit');
        try {$line=@fgets($this->stream,514);}catch(\Throwable){throw new ProbeFailure('connection');}
        $meta=stream_get_meta_data($this->stream);$this->remaining();
        if($meta['timed_out']??false)throw new ProbeFailure('timeout');
        if($line===false || $line==='')throw new ProbeFailure('connection');
        $this->received+=strlen($line);
        if(strlen($line)>512 || $this->received>65536)throw new ProbeFailure('limit');
        if(!str_ends_with($line,"\r\n"))throw new ProbeFailure('protocol');
        $line=substr($line,0,-2);
        if(preg_match('/[\x00-\x08\x0A-\x1F\x7F]/',$line))throw new ProbeFailure('protocol');
        return $line;
    }
    public function write(#[\SensitiveParameter] string $data):void
    {
        $length=strlen($data);$this->sent+=$length;
        if(++$this->writes>32768 || $length>2048 || $this->sent>1048576)throw new ProbeFailure('limit');
        if(!str_ends_with($data,"\r\n") || preg_match('/[\x00\r\n]/',substr($data,0,-2)))throw new ProbeFailure('protocol');
        $at=0;
        while($at<$length) {
            $this->remaining();try{$written=@fwrite($this->stream,substr($data,$at));}catch(\Throwable){throw new ProbeFailure('connection');}
            if($written===false || $written===0)throw new ProbeFailure((stream_get_meta_data($this->stream)['timed_out']??false)?'timeout':'connection');
            $at+=$written;
        }
        $this->remaining();
    }
    public function close():void {if(is_resource($this->stream))fclose($this->stream);$this->stream=null;$this->tls=false;}
    public function __destruct() {$this->close();}
}
