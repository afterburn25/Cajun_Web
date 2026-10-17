<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailCompose;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailReceiving\HeaderDecoder;
use InvalidArgumentException;
use Psr\Log\NullLogger;
use ZBateson\MailMimeParser\Header\{AddressHeader,IdHeader};
use ZBateson\MailMimeParser\MailMimeParser;

require_once __DIR__.'/../mail-receiving/bootstrap.php';
require_once __DIR__.'/DraftContent.php';
require_once __DIR__.'/MessageThread.php';

/** Bounded received-header metadata. This does not establish mailbox authority. */
final readonly class ReplyMetadata
{
    private function __construct(public array $recipients, public MessageThread $thread, public array $to=[], public array $cc=[]) {}

    public static function decode(#[\SensitiveParameter] string $headers): self
    {
        try {return self::decodeHeaders($headers);}
        catch(\CajunWebPro\Foundation\MailReceiving\ReceivingFailure $e) {throw $e;}
        catch(\Throwable) {
            // Never surface dependency diagnostics containing addresses or raw headers.
            throw new InvalidArgumentException('This message has no supported reply metadata.');
        }
    }

    private static function decodeHeaders(#[\SensitiveParameter] string $headers): self
    {
        // Reuse the receiving envelope limits and its CRLF/body/control validation.
        HeaderDecoder::decode($headers);
        $fields=[]; $name=null;
        foreach(explode("\r\n",$headers) as $line) {
            if($line==='') continue;
            if($line[0]===' ' || $line[0]==="\t") {
                if(isset($fields[$name])) $fields[$name].="\r\n".$line;
                continue;
            }
            $colon=strpos($line,':'); $name=strtolower(substr($line,0,$colon));
            if(!in_array($name,['from','reply-to','to','cc','message-id','references','in-reply-to'],true))continue;
            if(isset($fields[$name])) throw new InvalidArgumentException('Duplicate reply metadata is unsupported.');
            $fields[$name]=substr($line,$colon+1);
        }
        // A present but unusable Reply-To must never silently fall back to From.
        $recipientField=array_key_exists('reply-to',$fields)?'reply-to':'from';
        if(!isset($fields[$recipientField])) throw new InvalidArgumentException('The message has no supported reply address.');
        $safe='';
        foreach([$recipientField,'to','cc','message-id','references','in-reply-to'] as $key) if(isset($fields[$key])) {
            self::boundedComments($fields[$key]);
            $value=preg_replace('/\r\n[ \t]+/',' ',$fields[$key]);
            self::syntax($value,in_array($key,[$recipientField,'to','cc'],true));
            $safe.=$key.':'.$fields[$key]."\r\n";
        }
        static $parser=null;
        $parser??=new MailMimeParser(new NullLogger(),[
            'maxCommentDepth'=>12,'maxHeaderTokenCount'=>1024,'maxMessageHeaderTokenCount'=>3072,
            'maxHeaderCount'=>7,'maxMessageHeaderCount'=>7,'maxHeaderSizeBytes'=>16384,
            'maxMessageHeaderSizeBytes'=>16384,'maxMessagePartCount'=>1,'maxMimePartDepth'=>1,
        ],false);
        $message=$parser->parse($safe."\r\n",false);
        $address=$message->getHeader($recipientField);
        if(!$address instanceof AddressHeader || $address->getGroups()!==[] || $address->getAllErrors(true)) {
            throw new InvalidArgumentException('The message has no supported reply address.');
        }
        $recipients=array_map(fn($part)=>$part->getEmail(),$address->getAddresses());
        if($recipients===[]) throw new InvalidArgumentException('The message has no supported reply address.');
        // Apply the same exact address, duplicate and total-recipient rules as Send.
        $recipients=(new DraftContent($recipients,[],[],'',''))->to;
        $lists=[];
        foreach(['to','cc'] as $key) {
            $lists[$key]=[];
            if(!isset($fields[$key])) continue;
            $header=$message->getHeader($key);
            if(!$header instanceof AddressHeader || $header->getGroups()!==[] || $header->getAllErrors(true)) {
                throw new InvalidArgumentException('Unsupported recipient header in reply metadata.');
            }
            foreach($header->getAddresses() as $part) {
                $email=$part->getEmail();
                if(!in_array($email,$lists[$key],true)) $lists[$key][]=$email;
            }
        }
        // Bound copied recipient lists before the draft-level recipient cap applies.
        if(count($lists['to'])>20 || count($lists['cc'])>20) throw new InvalidArgumentException('This message has too many recipients to reply to all.');
        $ids=[];
        foreach(['message-id','references','in-reply-to'] as $key) {
            $header=$message->getHeader($key); $ids[$key]=[];
            if($header!==null) {
                if(!$header instanceof IdHeader || $header->getAllErrors(true)) throw new InvalidArgumentException('Unsupported conversation identifiers.');
                $ids[$key]=array_map(fn($id)=>'<'.$id.'>',$header->getIds());
                if($ids[$key]===[]) throw new InvalidArgumentException('Empty conversation identifiers are unsupported.');
            }
        }
        if(count($ids['message-id'])>1 || $message->getAllErrors(false)) throw new InvalidArgumentException('Unsupported conversation identifiers.');
        return new self($recipients,MessageThread::reply($ids['message-id'][0]??null,$ids['references'],$ids['in-reply-to']),$lists['to'],$lists['cc']);
    }

    private static function boundedComments(string $value):void
    {
        $depth=0; $escaped=false; $quoted=false;
        foreach(str_split($value) as $char) {
            if($escaped) {$escaped=false;continue;}
            if($char==='\\') {$escaped=true;continue;}
            if($depth===0 && $char==='"') {$quoted=!$quoted;continue;}
            if($quoted)continue;
            if($char==='(' && ++$depth>12) throw new InvalidArgumentException('Reply metadata nesting exceeds the supported limit.');
            if($char===')' && --$depth<0) throw new InvalidArgumentException('Unsupported reply metadata comments.');
        }
        if($depth!==0 || $quoted || $escaped) throw new InvalidArgumentException('Unterminated reply metadata.');
    }

    /**
     * Restrict raw input before the maintained parser's intentionally lenient
     * normalization (it can otherwise join "a b@host" or ignore trailing text).
     * Supports modern bare addresses, display names, quoted names and CFWS.
     * Address groups, obsolete address syntax and quoted local parts are not Send-supported.
     */
    private static function syntax(string $value,bool $address):void
    {
        $comment='(?(DEFINE)(?<comment>\((?:[^()\\\\]|\\\\[\x20-\x7e]|(?&comment))*\)))';
        $cfws='(?:[ \t]|(?&comment))*';
        if($address) {
            $bare="[A-Za-z0-9.!#$%&'*+/=?^_`{|}\\x7e\\x2d]+@[A-Za-z0-9.\\x2d]+";
            $quoted='"(?:[^"\\\\]|\\\\[\x20-\x7e])*"';
            $word='(?:[^\s()<>@,;:\\"\[\]\\\\]+|'.$quoted.')';
            $mailbox=$cfws.'(?:'.$bare.'|(?:'.$word.$cfws.')*<'.$cfws.$bare.$cfws.'>)'.$cfws;
            $pattern=$mailbox.'(?:,'.$mailbox.')*';
        } else {
            $pattern=$cfws.'<[^<\x00-\x20]+>(?:'.$cfws.'<[^<\x00-\x20]+>)*'.$cfws;
        }
        if(preg_match('~\A'.$comment.$pattern.'\z~D',$value)!==1) throw new InvalidArgumentException('Unsupported reply metadata syntax.');
    }
}
