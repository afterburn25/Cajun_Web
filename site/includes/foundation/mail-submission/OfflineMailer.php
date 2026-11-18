<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/PreparationFailure.php';
require_once __DIR__.'/vendor/phpmailer/src/Exception.php';
require_once __DIR__.'/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__.'/vendor/phpmailer/src/SMTP.php';
require_once __DIR__.'/../mail-compose/MessageThread.php';
use PHPMailer\PHPMailer\PHPMailer;
use CajunWebPro\Foundation\MailCompose\MessageThread;

/** Maintained MIME encoder with bounded folding; never a delivery entry point. */
final class OfflineMailer extends PHPMailer
{
    /** @var array<string,list<string>> Validated structured fields keyed by exact header name. */
    private array $identifierHeaders=[];
    private ?string $boundarySeed=null;
    private bool $forceAlternative=false;

    public function __construct()
    {
        parent::__construct(true);
        $this->isSMTP();
        static::setLE(self::CRLF);
        $this->CharSet=self::CHARSET_UTF8;
        $this->Encoding=self::ENCODING_BASE64;
        $this->AllowEmpty=true;
        $this->XMailer=false;
        $this->SMTPDebug=0;
        $this->WordWrap=0;
    }

    public function useBoundarySeed(string $seed):void
    {
        if(!preg_match('/\A[a-f0-9]{64}\z/D',$seed) || $this->boundarySeed!==null)throw new PreparationFailure('encoding_failed');
        $this->boundarySeed=substr($seed,0,43);
    }

    /** Empty and literal-0 plaintext are both real fallbacks for formatted mail. */
    public function requireAlternative():void {$this->forceAlternative=true;}

    public function alternativeExists() {return $this->forceAlternative||parent::alternativeExists();}

    /**
     * Force literal subject text through maintained Q encoding and UTF-8-aware
     * wrapping. This preserves edge spaces and literal encoded-word-looking
     * text that ordinary preSend trimming/unencoded ASCII would reinterpret.
     * Join complete <=75-byte encoded words; headerLine then folds at whitespace.
     */
    public function literalSubject(string $text):string
    {
        if($text==='')return '';
        // Leave room for "Subject: " on the first physical header line. Some
        // older parsers retain a leading fold as an extra subject space.
        $wrapped=rtrim($this->wrapText($this->encodeQ($text,'text'),54,true),"\r\n");
        $parts=explode("=\r\n",$wrapped);
        foreach($parts as $part)if(strlen($part)>54)throw new PreparationFailure('encoding_failed');
        return implode(' ',array_map(fn(string $part):string=>'=?UTF-8?Q?'.$part.'?=',$parts));
    }

    /**
     * Retain validated message identifiers as structured tokens. PHPMailer's
     * generic custom-header path RFC 2047-encodes long ASCII values, which is
     * invalid for In-Reply-To and References. The ordinary custom-header list
     * is still populated so its duplicate/count protections remain observable.
     * @param list<string> $identifiers
     */
    public function addIdentifierHeader(string $name,array $identifiers):bool
    {
        if(isset($this->identifierHeaders[$name])
            || ($name==='In-Reply-To' && count($identifiers)!==1)
            || ($name==='References' && $identifiers===[])
            || !in_array($name,['In-Reply-To','References'],true))return false;
        $canonical=$this->validatedIdentifierHeader($name,$identifiers);
        if($canonical===null)return false;
        $value=implode(' ',$canonical);
        if(!parent::addCustomHeader($name,$value))return false;
        $this->identifierHeaders[$name]=$canonical;
        return true;
    }

    public function headerLine($name,$value)
    {
        // PHPMailer renders dates in the host timezone. Keep the fixed UTC
        // value chosen from the immutable snapshot for reproducible wire bytes.
        if($name==='Date' && $this->MessageDate!=='')$value=$this->MessageDate;
        if(isset($this->identifierHeaders[$name]))return $this->identifierHeaderLine($name,$this->identifierHeaders[$name]);
        return $this->foldHeader(parent::headerLine($name,$value));
    }

    public function addrAppend($type,$addr)
    {
        return $this->foldHeader(parent::addrAppend($type,$addr));
    }

    private function foldHeader(string $header):string
    {
        $wrapped=$this->wrapText(rtrim($header,"\r\n"),77,false);
        $lines=explode("\r\n",rtrim($wrapped,"\r\n"));
        return implode("\r\n ",$lines)."\r\n";
    }

    /** @param list<string> $identifiers */
    private function identifierHeaderLine(string $name,array $identifiers):string
    {
        // Revalidate at the final render boundary as well as admission. This is
        // deliberately fail-closed even though admission stores detached data.
        $identifiers=$this->validatedIdentifierHeader($name,$identifiers);
        if($identifiers===null)throw new PreparationFailure('encoding_failed');
        $lines=[];$line=$name.':';
        foreach($identifiers as $identifier) {
            $candidate=$line.' '.$identifier;
            // Structured identifiers are indivisible. Prefer the RFC 5322
            // 78-byte recommendation, but never split or encode a token.
            if($line!==$name.':' && strlen($candidate)>77) {
                $lines[]=$line;$line=' '.$identifier;
            } else {
                $line=$candidate;
            }
            if(strlen($line)>998)throw new PreparationFailure('message_bounds');
        }
        $lines[]=$line;
        return implode("\r\n",$lines)."\r\n";
    }

    /** @param list<string> $identifiers @return ?list<string> */
    private function validatedIdentifierHeader(string $name,array $identifiers):?array
    {
        try {
            $thread=$name==='In-Reply-To'
                ? MessageThread::fromStored(['in_reply_to'=>$identifiers[0]??null,'references'=>[]])
                : MessageThread::fromStored(['in_reply_to'=>null,'references'=>$identifiers]);
        } catch(\InvalidArgumentException) {
            return null;
        }
        // MessageThread copies every string value; caller-owned PHP references
        // can no longer change the validated values retained for later render.
        return $name==='In-Reply-To'?[$thread->inReplyTo]:$thread->references;
    }

    /** An accidental send() call cannot reach PHP mail(), a shell or SMTP. */
    public function postSend()
    {
        throw new \LogicException('Offline MIME preparation does not deliver messages.');
    }

    /** Multipart boundaries derive only from public message identity. */
    protected function generateId() {return $this->boundarySeed??parent::generateId();}
}
