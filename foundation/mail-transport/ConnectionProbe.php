<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Authentication diagnostics only. No mailbox selection, message command or submission API. */
final class ConnectionProbe
{
    private int $tag = 0;
    private \Closure $authorize;
    public function __construct(private ProbeChannel $channel,callable $beforeCredentials) {$this->authorize=\Closure::fromCallable($beforeCredentials);}

    public function run(string $protocol,string $encryption,#[\SensitiveParameter] string $username,#[\SensitiveParameter] string $password): void
    {
        try {
            if (!in_array($protocol,['imap','smtp'],true) || !in_array($encryption,['ssl','starttls'],true)) throw new ProbeFailure('protocol');
            if ($username==='' || $password==='' || strlen($username)>190 || strlen($password)>1024 || str_contains($username,"\0") || str_contains($password,"\0")) throw new ProbeFailure('credential');
            if ($this->channel->isTls()!==($encryption==='ssl')) throw new ProbeFailure('tls');
            if ($protocol==='imap') $this->imap($encryption,$username,$password);
            else $this->smtp($encryption,$username,$password);
        } finally { $this->channel->close(); }
    }

    private function command(string $name): string
    {
        $tag='CWP'.str_pad((string)++$this->tag,3,'0',STR_PAD_LEFT);
        $this->channel->write($tag.' '.$name."\r\n");return $tag;
    }

    /** Only bounded status/capability lines are valid during a diagnostic exchange. */
    private function imapReply(string $tag,string $failure='protocol'): array
    {
        $caps=[];
        for ($i=0;$i<40;$i++) {
            $line=$this->channel->line();
            if (str_starts_with($line,$tag.' ')) {
                if (preg_match('/^OK(?: |$)/i',substr($line,strlen($tag)+1))) return $caps;
                if (preg_match('/^(NO|BAD)(?: |$)/i',substr($line,strlen($tag)+1))) throw new ProbeFailure($failure);
                throw new ProbeFailure('protocol');
            }
            if (preg_match('/^\* CAPABILITY ([A-Za-z0-9=._+-]+(?: [A-Za-z0-9=._+-]+)*)$/i',$line,$m)) $caps=array_merge($caps,explode(' ',strtoupper($m[1])));
            elseif (!preg_match('/^\* OK(?: |$)/i',$line) || preg_match('/\{[0-9]+\+?\}$/',$line)) throw new ProbeFailure('protocol');
        }
        throw new ProbeFailure('limit');
    }

    private function imap(string $encryption,#[\SensitiveParameter] string $user,#[\SensitiveParameter] string $password): void
    {
        if (!preg_match('/^\* OK(?: |$)/i',$this->channel->line())) throw new ProbeFailure('protocol');
        $caps=$this->imapReply($this->command('CAPABILITY'));
        if ($encryption==='starttls') {
            if (!in_array('STARTTLS',$caps,true)) throw new ProbeFailure('upgrade');
            $this->imapReply($this->command('STARTTLS'),'upgrade');$this->channel->startTls();
            $caps=$this->imapReply($this->command('CAPABILITY'));
        }
        if (!$this->channel->isTls()) throw new ProbeFailure('tls');
        if (!in_array('AUTH=PLAIN',$caps,true)) throw new ProbeFailure('mechanism');
        $tag=$this->command('AUTHENTICATE PLAIN');$line=$this->channel->line();
        if (!preg_match('/^\+ ?$/D',$line)) {
            if (str_starts_with($line,$tag.' NO ') || str_starts_with($line,$tag.' BAD ')) throw new ProbeFailure('authentication');
            throw new ProbeFailure('protocol');
        }
        ($this->authorize)();$this->channel->write(base64_encode("\0".$user."\0".$password)."\r\n");
        $this->imapReply($tag,'authentication');
        // Authentication is now proven. Logout is best-effort; local close always follows.
        try {$this->command('LOGOUT');} catch (ProbeFailure) {}
    }

    private function smtpReply(int $expected,string $failure='protocol'): array
    {
        $lines=[];$code=null;
        for ($i=0;$i<40;$i++) {
            $line=$this->channel->line();
            if (strlen($line)>510 || !preg_match('/^([2-5][0-9]{2})([ -])(.*)$/D',$line,$m)) throw new ProbeFailure('protocol');
            if ($code!==null && $code!==(int)$m[1]) throw new ProbeFailure('protocol');
            $code=(int)$m[1];$lines[]=$m[3];
            if ($m[2]===' ') {if($code!==$expected) throw new ProbeFailure($failure);return $lines;}
        }
        throw new ProbeFailure('limit');
    }
    private function smtpCommand(#[\SensitiveParameter] string $command,int $expected,string $failure='protocol'): array
    {
        $this->channel->write($command."\r\n");return $this->smtpReply($expected,$failure);
    }
    private function smtp(string $encryption,#[\SensitiveParameter] string $user,#[\SensitiveParameter] string $password): void
    {
        $this->smtpReply(220);$caps=$this->smtpCommand('EHLO cajunwebpro.invalid',250);
        if ($encryption==='starttls') {
            if (!array_filter($caps,fn($s)=>strtoupper($s)==='STARTTLS')) throw new ProbeFailure('upgrade');
            $this->smtpCommand('STARTTLS',220,'upgrade');$this->channel->startTls();
            $caps=$this->smtpCommand('EHLO cajunwebpro.invalid',250);
        }
        if (!$this->channel->isTls()) throw new ProbeFailure('tls');
        $auth=[];foreach($caps as $cap)if(preg_match('/^AUTH[ =]([A-Za-z0-9_-]+(?: [A-Za-z0-9_-]+)*)$/i',$cap,$m))$auth=array_merge($auth,explode(' ',strtoupper($m[1])));
        if (in_array('PLAIN',$auth,true)) {
            $this->smtpCommand('AUTH PLAIN',334,'authentication');
            ($this->authorize)();
            $this->smtpCommand(base64_encode("\0".$user."\0".$password),235,'authentication');
        } elseif (in_array('LOGIN',$auth,true)) {
            $this->smtpCommand('AUTH LOGIN',334,'authentication');
            ($this->authorize)();
            $this->smtpCommand(base64_encode($user),334,'authentication');
            ($this->authorize)();
            $this->smtpCommand(base64_encode($password),235,'authentication');
        } else throw new ProbeFailure('mechanism');
        try {$this->channel->write("QUIT\r\n");} catch (ProbeFailure) {}
    }
}
