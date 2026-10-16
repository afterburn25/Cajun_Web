<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailCompose;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailReceiving\BodyHtmlParser;
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;
use Symfony\Component\HtmlSanitizer\{HtmlSanitizer,HtmlSanitizerConfig};

require_once dirname(__DIR__).'/mail-receiving/vendor/autoload.php';
require_once dirname(__DIR__).'/mail-receiving/ReceivingFailure.php';
require_once dirname(__DIR__).'/mail-receiving/BodyHtml.php';

/**
 * Bounded outbound formatted-body sanitizer. Composed markup is reduced to an
 * email-safe subset — basic formatting, tables and links only — before it can
 * enter a draft. No scripts, forms, styles, images or remote resources.
 */
final class ComposeHtml
{
    public const MAX_INPUT_BYTES = 40960;
    /** Matches DraftContent::MAX_HTML_BYTES; keep the two bounds identical. */
    public const MAX_BYTES = 61440;

    /** Returns the canonical sanitized fragment, or '' when nothing usable remains. */
    public static function sanitize(#[\SensitiveParameter] string $html): string
    {
        if (strlen($html)>self::MAX_INPUT_BYTES || substr_count($html,'<')>2048) throw new ReceivingFailure('limit');
        if (!preg_match('//u',$html) || preg_match('/\x00/',$html) || !extension_loaded('dom')) throw new ReceivingFailure('protocol');
        try {
            static $sanitizer=null;
            if ($sanitizer===null) {
                $config=(new HtmlSanitizerConfig())->allowLinkSchemes(['http','https','mailto'])
                    ->allowRelativeLinks(false)->withMaxInputLength(self::MAX_INPUT_BYTES)
                    ->blockElement('html')->blockElement('body');
                foreach(['p','div','span','br','hr','b','strong','i','em','u','s','blockquote','pre','code','ul','ol','li','table','thead','tbody','tr','th','td','h1','h2','h3','h4','h5','h6'] as $tag) $config=$config->allowElement($tag);
                $config=$config->allowElement('a',['href','title'])
                    ->forceAttribute('a','rel','noopener noreferrer')
                    ->forceAttribute('a','target','_blank');
                $sanitizer=new HtmlSanitizer($config,new BodyHtmlParser());
            }
            $result=$sanitizer->sanitize($html);
            if (strlen($result)>self::MAX_BYTES) throw new ReceivingFailure('limit');
            return trim($result)===''?'':$result;
        } catch(ReceivingFailure $e) {throw $e;}
        catch(\Throwable) {throw new ReceivingFailure('protocol');}
    }
}
