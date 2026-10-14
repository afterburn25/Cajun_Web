<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;
use Psr\Log\NullLogger;

/** One bounded raw message, at most one inline body of each type. */
final class BodyDecoder
{
    private static function eligible(IMessagePart $part): bool
    {
        if(!in_array(strtolower($part->getContentType()),['text/plain','text/html'],true)) return false;
        for($node=$part;$node!==null;$node=$node->getParent()) {
            $disposition=$node->getContentDisposition();
            if($disposition!==null && strcasecmp($disposition,'inline')!==0) return false;
            if($node->getFilename()!==null && $node->getFilename()!=='') return false;
            if(str_starts_with(strtolower($node->getContentType()),'message/')) return false;
        }
        return true;
    }
    private static function content(IMessagePart $part,int $maximum): string
    {
        $stream=$part->getContentStream();
        if($stream===null) return '';
        $body='';
        try {
            while(!$stream->eof()) {
                $chunk=$stream->read(8192);
                if($chunk==='' && !$stream->eof()) throw new ReceivingFailure('protocol');
                $body.=$chunk;
                if(strlen($body)>$maximum) throw new ReceivingFailure('limit');
            }
        } finally {$stream->close();}
        if(!preg_match('//u',$body)) throw new ReceivingFailure('protocol');
        $body=str_replace(["\r\n","\r"],"\n",$body);
        // Keep ordinary Unicode (including emoji joiners), tabs and line breaks.
        return preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u',' ',$body)??'';
    }
    public static function decode(#[\SensitiveParameter] string $raw): array
    {
        if(strlen($raw)>262144) throw new ReceivingFailure('limit');
        $headerEnd=strpos($raw,"\r\n\r\n");
        if($headerEnd===false) throw new ReceivingFailure('protocol');
        if($headerEnd+4>16384) throw new ReceivingFailure('limit');
        try {
            $headers=HeaderDecoder::decode(substr($raw,0,$headerEnd+2));
            static $parser=null;
            $parser??=new MailMimeParser(new NullLogger(),[
                'maxCommentDepth'=>12,'maxHeaderTokenCount'=>1024,'maxMessageHeaderTokenCount'=>8192,
                'maxHeaderCount'=>128,'maxMessageHeaderCount'=>512,'maxHeaderSizeBytes'=>16384,
                'maxMessageHeaderSizeBytes'=>65536,'maxMessagePartCount'=>32,'maxMimePartDepth'=>8,
            ],false);
            $message=$parser->parse($raw,false);
            $parts=$message->getAllParts();
            if(count($parts)>32) throw new ReceivingFailure('limit');
            $selected=[]; $candidates=0;
            foreach($parts as $part) if(self::eligible($part)) {
                ++$candidates;
                $type=strtolower($part->getContentType());
                $selected[$type]??=$part;
            }
            if($message->getAllErrors(false)) throw new ReceivingFailure('protocol');
            $text=isset($selected['text/plain'])?self::content($selected['text/plain'],131072):null;
            $html=isset($selected['text/html'])?self::content($selected['text/html'],65536):null;
            if($message->getAllErrors(false)) throw new ReceivingFailure('protocol');
            // App must display the unavailable formatting state when DOM is absent.
            $formatAvailable=extension_loaded('dom');
            $formatted=$html!==null && $formatAvailable ? BodyHtml::render($html):null;
            return $headers+['text'=>$text,'html'=>$formatted,'format_available'=>$formatAvailable,
                'has_html'=>$html!==null,'additional_inline_parts'=>max(0,$candidates-count($selected))];
        } catch(ReceivingFailure $e) {throw $e;}
        catch(\Throwable) {throw new ReceivingFailure('protocol');}
    }
}
