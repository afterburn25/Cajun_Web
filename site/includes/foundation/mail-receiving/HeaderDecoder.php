<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use ZBateson\MailMimeParser\MailMimeParser;
use Psr\Log\NullLogger;

/** Bounded header claims only. The UI must still HTML-escape every returned value. */
final class HeaderDecoder
{
    public static function decode(#[\SensitiveParameter] string $headers): array
    {
        if (strlen($headers)>16384) throw new ReceivingFailure('limit');
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $headers) || preg_match('/(?<!\r)\n|\r(?!\n)/', $headers)) throw new ReceivingFailure('protocol');
        $lines=explode("\r\n", $headers);
        if (count($lines)>128) throw new ReceivingFailure('limit');
        $fields=[]; $name=null; $ended=false;
        foreach($lines as $line) {
            if($line==='') { $ended=true; continue; }
            if($ended) throw new ReceivingFailure('protocol'); // No body bytes in this boundary.
            if($line[0]===' ' || $line[0]==="\t") {
                if($name===null) throw new ReceivingFailure('protocol');
                $fields[$name].="\r\n".$line;
            } else {
                if(!preg_match('/\A([!-9;-~]+):/D',$line,$m)) throw new ReceivingFailure('protocol');
                $name=strtolower($m[1]);
                // Repeated unrelated fields (e.g. Received) are discarded after validation.
                if(isset($fields[$name]) && in_array($name,['subject','from','date'],true)) throw new ReceivingFailure('protocol');
                $fields[$name]=$line;
            }
            if(strlen($fields[$name])>4096) throw new ReceivingFailure('limit');
        }
        $safe='';
        foreach(['subject','from','date'] as $name) if(isset($fields[$name])) {
            // Bound recursive RFC comment work even for a malicious 16 KiB header.
            $depth=0; $escaped=false;
            foreach(str_split($fields[$name]) as $char) {
                if($escaped) { $escaped=false; continue; }
                if($char==='\\') { $escaped=true; continue; }
                if($char==='(' && ++$depth>12) throw new ReceivingFailure('limit');
                if($char===')') $depth=max(0,$depth-1);
            }
            $safe.=$fields[$name]."\r\n";
        }
        // Content-Type and every other MIME body directive are deliberately absent.
        static $parser=null;
        $parser??=new MailMimeParser(new NullLogger(),[
            'maxCommentDepth'=>12, 'maxHeaderTokenCount'=>1024, 'maxMessageHeaderTokenCount'=>3072,
            // One spare slot lets the upstream parser consume the terminating blank line.
            'maxHeaderCount'=>4, 'maxMessageHeaderCount'=>4, 'maxHeaderSizeBytes'=>16384,
            'maxMessageHeaderSizeBytes'=>16384, 'maxMessagePartCount'=>1, 'maxMimePartDepth'=>1,
        ],false);
        $message=$parser->parse($safe."\r\n",false); $result=['headers_truncated'=>false];
        foreach(['subject','from','date'] as $name) {
            $value=(string)($message->getHeader($name)?->getDecodedValue()??'');
            $value=mb_convert_encoding($value,'UTF-8','UTF-8');
            $value=preg_replace('/[\p{Cc}\p{Cf}]+/u',' ',$value)??'';
            if(strlen($value)>1000) $result['headers_truncated']=true;
            $result[$name]=trim(mb_strcut($value,0,1000,'UTF-8'));
        }
        if($message->getAllErrors(false)) throw new ReceivingFailure('protocol');
        return $result;
    }
}
