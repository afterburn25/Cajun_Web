<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailSubmission;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/PreparationFailure.php';
require_once __DIR__.'/vendor/phpmailer/src/Exception.php';
require_once __DIR__.'/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__.'/vendor/phpmailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;

/** Maintained MIME encoder with bounded folding; never a delivery entry point. */
final class OfflineMailer extends PHPMailer
{
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

    public function headerLine($name,$value)
    {
        // PHPMailer renders dates in the host timezone. Keep the fixed UTC
        // value chosen from the immutable snapshot for reproducible wire bytes.
        if($name==='Date' && $this->MessageDate!=='')$value=$this->MessageDate;
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

    /** An accidental send() call cannot reach PHP mail(), a shell or SMTP. */
    public function postSend()
    {
        throw new \LogicException('Offline MIME preparation does not deliver messages.');
    }
}
