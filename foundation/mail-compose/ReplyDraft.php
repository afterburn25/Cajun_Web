<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailCompose;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailReceiving\BodyDecoder;
use InvalidArgumentException;

require_once __DIR__.'/ReplyMetadata.php';

/** Editable, bounded plain-text content from a received message; never sends or saves. */
final readonly class ReplyDraft
{
    private function __construct(public string $mode,public DraftContent $content,public MessageThread $thread,public array $omissions) {}

    public static function prepare(string $mode,#[\SensitiveParameter] string $raw):self
    {
        if(!in_array($mode,['reply','forward'],true))throw new InvalidArgumentException('Unsupported mail action.');
        $message=BodyDecoder::decode($raw);
        $metadata=$mode==='reply'?ReplyMetadata::decode(substr($raw,0,strpos($raw,"\r\n\r\n")+2)):null;
        $prefix=$mode==='reply'?'Re: ':'Fwd: ';
        $subject=$message['subject']; $omissions=[];
        if(!preg_match($mode==='reply'?'/\ARe:\s*/i':'/\AFw(?:d)?:\s*/i',$subject))$subject=$prefix.$subject;
        if(strlen($subject)>DraftContent::MAX_SUBJECT_BYTES || $message['headers_truncated'])$omissions[]='subject_or_header_shortened';
        $subject=mb_strcut($subject,0,DraftContent::MAX_SUBJECT_BYTES,'UTF-8');
        $quote="From: ".$message['from']."\nDate: ".$message['date']."\nSubject: ".$message['subject']."\n\n";
        if($message['text']===null) {
            $omissions[]='plain_text_unavailable';
            $quote.='[No supported plain-text body. Open the original message to review its formatting.]';
        } else $quote.=$message['text'];
        if($message['additional_inline_parts']>0)$omissions[]='additional_inline_parts';
        // Never include raw headers, hidden recipients or MIME attachments in a quote.
        $body="\n\n".($mode==='reply'?'Original message:':'Forwarded message:')."\n> ".str_replace("\n","\n> ",$quote);
        $footer="\n\n[Attachments are not included.]";
        if($message['additional_inline_parts']>0)$footer.="\n[Additional inline message parts are not included.]";
        $maximum=DraftContent::MAX_BODY_BYTES-strlen($footer);
        if(strlen($body)>$maximum) {
            $omissions[]='quoted_text_shortened';
            $marker="\n[Quoted text shortened to fit this draft. Open the original message for the rest.]";
            $body=mb_strcut($body,0,$maximum-strlen($marker),'UTF-8').$marker;
        }
        return new self($mode,new DraftContent($metadata?->recipients??[],[],[],$subject,$body.$footer),$metadata?->thread??MessageThread::none(),$omissions);
    }
}
