<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;
use Psr\Log\NullLogger;

/** Attachment enumeration and extraction for one bounded raw message. */
final class AttachmentIndex
{
    /** A part is an attachment only by explicit disposition or an actual filename. */
    private static function attachment(IMessagePart $part): bool
    {
        if(str_starts_with(strtolower($part->getContentType()),'multipart/')) return false;
        $disposition=$part->getContentDisposition();
        if($disposition!==null && strcasecmp($disposition,'attachment')===0) return true;
        $name=$part->getFilename();
        return $name!==null && $name!=='';
    }
    /** Download-safe display name; never a path, never empty. */
    private static function name(?string $name,int $position): string
    {
        $name=trim(str_replace(["\r","\n","\t",'"','\\','/'],'',(string)$name));
        $name=preg_replace('/[^\x{20}-\x{7E}]/u','', $name)??'';
        if(strlen($name)>80)$name=substr($name,0,80);
        return $name===''||$name==='.'||$name==='..' ? 'attachment-'.$position.'.bin' : $name;
    }
    /** Declared type is metadata only; callers must still serve octet-stream downloads. */
    private static function type(string $type): string
    {
        $type=strtolower(trim($type));
        return preg_match('/\A[a-z0-9][a-z0-9!#$&^_.+\x2d]*\/[a-z0-9][a-z0-9!#$&^_.+\x2d]*\z/D',$type)?$type:'application/octet-stream';
    }
    /** Raw encoded body for strict transfer-encoding validation; never returned to callers. */
    private static function encodedBody(IMessagePart $part): string
    {
        $stream=$part->getStream();
        $raw='';
        try {
            while(!$stream->eof()) {
                $chunk=$stream->read(8192);
                if($chunk==='' && !$stream->eof()) throw new ReceivingFailure('protocol');
                $raw.=$chunk;
                if(strlen($raw)>262144) throw new ReceivingFailure('limit');
            }
        } finally {$stream->rewind();}
        $headerEnd=strpos($raw,"\r\n\r\n");
        if($headerEnd===false) throw new ReceivingFailure('protocol');
        return substr($raw,$headerEnd+4);
    }
    /** The maintained parser decodes leniently, so downloads validate encoded bytes first. */
    private static function validateEncoding(IMessagePart $part): void
    {
        $encoding=strtolower(trim((string)$part->getContentTransferEncoding('7bit')));
        if($encoding==='base64') {
            $encoded=self::encodedBody($part);
            if(preg_match('/(?<!\r)\n|\r(?!\n)|[ \t]/',$encoded))throw new ReceivingFailure('protocol');
            $lines=explode("\r\n",$encoded);if(end($lines)==='')array_pop($lines);
            if(!$lines)throw new ReceivingFailure('protocol');
            foreach($lines as $line)if($line===''||strlen($line)>76||preg_match('/\A[A-Za-z0-9+\/=]+\z/D',$line)!==1)throw new ReceivingFailure('protocol');
            $compact=implode('',$lines);
            if(preg_match('/\A(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?\z/D',$compact)!==1
                ||($decoded=base64_decode($compact,true))===false||base64_encode($decoded)!==$compact) {
                throw new ReceivingFailure('protocol');
            }
        } elseif($encoding==='quoted-printable') {
            $encoded=self::encodedBody($part);
            if(preg_match('/(?<!\r)\n|\r(?!\n)/',$encoded))throw new ReceivingFailure('protocol');
            $lines=explode("\r\n",$encoded);if(end($lines)==='')array_pop($lines);
            foreach($lines as $line){
                if(strlen($line)>76||preg_match('/[ \t]\z/D',$line))throw new ReceivingFailure('protocol');
                if(str_ends_with($line,'='))$line=substr($line,0,-1);
                for($i=0,$length=strlen($line);$i<$length;$i++){
                    $byte=ord($line[$i]);
                    if($line[$i]==='='){
                        if($i+2>=$length||preg_match('/\A[0-9A-F]{2}\z/D',substr($line,$i+1,2))!==1)throw new ReceivingFailure('protocol');
                        $i+=2;continue;
                    }
                    if($byte!==9&&$byte!==32&&($byte<33||$byte>126))throw new ReceivingFailure('protocol');
                }
            }
        } elseif($encoding==='7bit'||$encoding==='') {
            $encoded=self::encodedBody($part);
            if(preg_match('/(?<!\r)\n|\r(?!\n)|[^\x09\x20-\x7E\r\n]/',$encoded))throw new ReceivingFailure('protocol');
            foreach(explode("\r\n",$encoded) as $line)if(strlen($line)>998)throw new ReceivingFailure('protocol');
        } elseif(!in_array($encoding,['8bit','binary'],true)) {
            throw new ReceivingFailure('protocol');
        }
    }
    private static function bytes(IMessagePart $part): string
    {
        self::validateEncoding($part);
        $stream=$part->getBinaryContentStream();
        if($stream===null) return '';
        $body='';
        try {
            while(!$stream->eof()) {
                $chunk=$stream->read(8192);
                if($chunk==='' && !$stream->eof()) throw new ReceivingFailure('protocol');
                $body.=$chunk;
                if(strlen($body)>262144) throw new ReceivingFailure('limit');
            }
        } finally {$stream->close();}
        return $body;
    }
    private static function parse(#[\SensitiveParameter] string $raw): array
    {
        if(strlen($raw)>262144) throw new ReceivingFailure('limit');
        $headerEnd=strpos($raw,"\r\n\r\n");
        if($headerEnd===false) throw new ReceivingFailure('protocol');
        if($headerEnd+4>16384) throw new ReceivingFailure('limit');
        try {
            static $parser=null;
            $parser??=new MailMimeParser(new NullLogger(),[
                'maxCommentDepth'=>12,'maxHeaderTokenCount'=>1024,'maxMessageHeaderTokenCount'=>8192,
                'maxHeaderCount'=>128,'maxMessageHeaderCount'=>512,'maxHeaderSizeBytes'=>16384,
                'maxMessageHeaderSizeBytes'=>65536,'maxMessagePartCount'=>32,'maxMimePartDepth'=>8,
            ],false);
            $message=$parser->parse($raw,false);
            $parts=$message->getAllParts();
            if(count($parts)>32) throw new ReceivingFailure('limit');
            $found=[];
            foreach($parts as $part) if(self::attachment($part)) $found[]=$part;
            if($message->getAllErrors(false)) throw new ReceivingFailure('protocol');
            return $found;
        } catch(ReceivingFailure $e) {throw $e;}
        catch(\Throwable) {throw new ReceivingFailure('protocol');}
    }
    /** Attachment metadata in stable order for one already-fetched raw message. */
    public static function list(#[\SensitiveParameter] string $raw): array
    {
        $items=[];
        $position=0;
        foreach(self::parse($raw) as $part) {
            $position++;
            $items[]=['name'=>self::name($part->getFilename(),$position),'type'=>self::type($part->getContentType()),'size'=>strlen(self::bytes($part))];
        }
        return $items;
    }
    /** Decoded bytes of the index-th listed attachment; the same message yields the same order. */
    public static function extract(#[\SensitiveParameter] string $raw,int $index): array
    {
        $parts=self::parse($raw);
        if($index<0||$index>=count($parts)) throw new \InvalidArgumentException('This attachment is unavailable.');
        $part=$parts[$index];
        return ['name'=>self::name($part->getFilename(),$index+1),'type'=>self::type($part->getContentType()),'bytes'=>self::bytes($part)];
    }
}
