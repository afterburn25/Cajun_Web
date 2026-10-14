<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/vendor/phpmailer/src/SMTP.php';
require_once __DIR__.'/../mail-transport/ProbeChannel.php';
require_once __DIR__.'/../mail-transport/ProbeFailure.php';
use CajunWebPro\Foundation\MailTransport\ProbeChannel;
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
use PHPMailer\PHPMailer\SMTP;

/** Internal maintained SMTP primitives over an explicitly supplied bounded channel. */
final class SmtpSession extends SMTP
{
    private bool $open=true;
    private int $sentBytes=0;
    private int $receivedBytes=0;
    private int $writes=0;
    private int $replyLines=0;
    private bool $endAttempted=false;
    private ?int $replyCode=null;
    public function __construct(private ProbeChannel $channel,private \Closure $authorize) {}
    public function terminatorAttempted():bool {return $this->endAttempted;}
    public function lastCode():?int {return $this->replyCode;}
    public function begin():bool
    {
        $this->last_reply=$this->get_lines();
        return $this->replyCode===220;
    }
    // The parent TCP opener, debug output, HELO fallback and automatic TLS path
    // cannot bypass the supplied channel or its original-host TLS policy.
    public function connect($host,$port=null,$timeout=30,$options=[])
    {throw new \LogicException('Only an existing bounded channel is supported.');}
    protected function edebug($str,$level=0) {}
    public function connected() {return $this->open;}
    public function hello($host='') {return $this->sendHello('EHLO','cajunwebpro.invalid');}
    public function startTLS()
    {
        if($this->channel->isTls() || !$this->getServerExt('STARTTLS'))throw new ProbeFailure('upgrade');
        if(!$this->sendCommand('STARTTLS','STARTTLS',220))return false;
        $this->channel->startTls();
        $this->server_caps=null;
        if(!$this->channel->isTls())throw new ProbeFailure('tls');
        return true;
    }
    public function authenticate(#[\SensitiveParameter] $username,#[\SensitiveParameter] $password,$authtype=null,$OAuth=null)
    {
        if(!$this->channel->isTls())throw new ProbeFailure('tls');
        $caps=$this->getServerExt('AUTH');
        // Select one advertised mechanism explicitly; never invoke the upstream
        // implicit fallback to CRAM-MD5/XOAUTH2 when our choice is unavailable.
        $method=is_array($caps)&&in_array('PLAIN',$caps,true)?'PLAIN':
            (is_array($caps)&&in_array('LOGIN',$caps,true)?'LOGIN':null);
        if($method===null)throw new ProbeFailure('mechanism');
        return parent::authenticate($username,$password,$method);
    }
    public function client_send(#[\SensitiveParameter] $data,$command='')
    {
        if(!$this->open)throw new ProbeFailure('connection');
        if(in_array($command,['User & Password','Username','Password','MAIL FROM','RCPT TO','DATA','DATA END'],true)) {
            if(!$this->channel->isTls())throw new ProbeFailure('tls');
            // Body writes also use DATA, so recheck only the DATA command here.
            if($command!=='DATA' || $data==="DATA\r\n")($this->authorize)();
        }
        $length=strlen($data);
        if(++$this->writes>1100 || $length>2048 || ($this->sentBytes+=$length)>73728)throw new ProbeFailure('limit');
        if(!str_ends_with($data,"\r\n") || preg_match('/[\x00\r\n]/',substr($data,0,-2)))throw new ProbeFailure('protocol');
        $this->replyCode=null;
        // Mark before the write: even a thrown partial-write failure may have
        // completed the terminator at the peer. Never retry this ambiguous case.
        if($command==='DATA END')$this->endAttempted=true;
        $this->channel->write($data);
        return $length;
    }
    protected function get_lines()
    {
        $reply='';$code=null;$this->replyCode=null;
        for($i=0;$i<32;$i++) {
            $line=$this->channel->line();
            if(++$this->replyLines>160 || ($this->receivedBytes+=strlen($line)+2)>65536)throw new ProbeFailure('limit');
            if(strlen($line)>510 || !preg_match('/\A([2-5][0-9]{2})([ -])[\x09\x20-\x7E]*\z/',$line,$m))throw new ProbeFailure('protocol');
            if($code!==null && $code!==(int)$m[1])throw new ProbeFailure('protocol');
            $code=(int)$m[1];$reply.=$line."\r\n";
            if($m[2]===' ') {$this->replyCode=$code;return $reply;}
        }
        throw new ProbeFailure('limit');
    }
    public function close()
    {
        $this->open=false;
        try {$this->channel->close();} finally {
            $this->last_reply='';$this->helo_rply='';$this->server_caps=null;
            $this->last_smtp_transaction_id=null;$this->setError('');
        }
    }
}
