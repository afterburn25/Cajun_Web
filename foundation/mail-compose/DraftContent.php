<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailCompose;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\EmailTemplates\EmailTemplate;
use InvalidArgumentException;

require_once __DIR__.'/../email-templates/EmailTemplate.php';

/**
 * Bounded plain-text content only. This grants no access, sends nothing and
 * performs no persistence, MIME generation or remote mailbox synchronization.
 */
final readonly class DraftContent
{
    public const MAX_RECIPIENTS = 20;
    public const MAX_SUBJECT_BYTES = 200;
    public const MAX_BODY_BYTES = 20000;

    /** @var list<string> */
    public array $to;
    /** @var list<string> */
    public array $cc;
    /** @var list<string> */
    public array $bcc;
    public string $subject;
    public string $body;
    /** Sanitized formatted-fragment bound; ComposeHtml::MAX_BYTES must match. */
    public const MAX_HTML_BYTES = 61440;

    /**
     * Optional sanitized formatted alternative. A fragment is only stored when
     * exactly repeatable; the caller supplies ComposeHtml::sanitize() output.
     */
    public ?string $html;

    /** Empty fields are allowed for an unfinished draft, never proof of send readiness. */
    public function __construct(array $to, array $cc, array $bcc, string $subject, string $body, ?string $html = null)
    {
        if (count($to)+count($cc)+count($bcc)>self::MAX_RECIPIENTS) {
            throw new InvalidArgumentException('A draft supports at most 20 recipients in total.');
        }
        $seen=[];
        $this->to=self::recipients($to,$seen);
        $this->cc=self::recipients($cc,$seen);
        $this->bcc=self::recipients($bcc,$seen);
        $this->subject=self::text($subject,self::MAX_SUBJECT_BYTES,false);
        $this->body=self::text($body,self::MAX_BODY_BYTES,true);
        if ($html!==null) {
            if (strlen($html)>self::MAX_HTML_BYTES || substr_count($html,'<')>2048 || !preg_match('//u',$html) || preg_match('/\x00/',$html)) {
                throw new InvalidArgumentException('The formatted message text is outside the supported bounds.');
            }
            if (preg_match('/<(script|style|iframe|object|embed|form|input|button|svg|math|link|meta|base|title|noscript|audio|video|source|img|applet|frameset|frame|plaintext|textarea|select)\b/i',$html) || preg_match('/\son[a-z]+\s*=|srcdoc\s*=|background\s*=|class\s*=|id\s*=|style\s*=/i',$html)) {
                throw new InvalidArgumentException('The formatted message text must be sanitized before it can be saved.');
            }
        }
        $this->html=$html===''?null:$html;
    }

    /** @param list<string> $input @param array<string,true> $seen */
    private static function recipients(array $input, array &$seen):array
    {
        if (!array_is_list($input)) throw new InvalidArgumentException('Recipients must be an ordered list of email addresses.');
        $result=[];
        foreach ($input as $address) {
            if (!is_string($address)) throw new InvalidArgumentException('Each recipient must be an email address.');
            // Do not trim CR/LF or other controls into an apparently valid header.
            $address=trim($address,' ');
            if (strlen($address)>254
                || !preg_match('/^[A-Za-z0-9.!#$%&\'*+\/=?^_\x60{|}~-]+@[A-Za-z0-9.-]+$/D',$address)
                || filter_var($address,FILTER_VALIDATE_EMAIL)===false) {
                throw new InvalidArgumentException('Use a bare ASCII email address without display names or control characters.');
            }
            $split=strrpos($address,'@');
            $address=substr($address,0,$split+1).strtolower(substr($address,$split+1));
            // Preserve local-part case; provider-specific equivalence is not inferred.
            if (isset($seen[$address])) throw new InvalidArgumentException('A recipient appears more than once across To, Cc or Bcc.');
            $seen[$address]=true;
            $result[]=$address;
        }
        return $result;
    }

    private static function text(string $value, int $max, bool $multiline):string
    {
        if ($multiline) $value=str_replace(["\r\n","\r"],"\n",$value);
        $controls=$multiline?'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/':'/[\x00-\x1F\x7F]/';
        if (strlen($value)>$max || preg_match('//u',$value)!==1 || preg_match($controls,$value)) {
            throw new InvalidArgumentException($multiline?'The draft body must be valid plain text up to 20,000 bytes.':'The draft subject must be valid single-line text up to 200 bytes.');
        }
        return $value;
    }

    /**
     * Current application authorization and an exact template revision are
     * adapter prerequisites. This only enforces the canonical insertion policy
     * and copies rendered text; it does not mutate the source template.
     */
    public static function fromTemplate(
        EmailTemplate $template,
        string $currentActorId,
        array $values,
        array $to=[],
        array $cc=[],
        array $bcc=[],
    ):self {
        if (trim($currentActorId)==='' || !$template->insertableBy($currentActorId)) {
            throw new InvalidArgumentException('This template is not available for draft insertion.');
        }
        $rendered=$template->preview($values);
        if ($rendered['unresolved']!==[]) throw new InvalidArgumentException('Complete every required template value before insertion.');
        return new self($to,$cc,$bcc,$rendered['subject'],$rendered['body']);
    }
}
