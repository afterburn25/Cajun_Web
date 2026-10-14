<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use Symfony\Component\HtmlSanitizer\{HtmlSanitizer,HtmlSanitizerConfig};
use Symfony\Component\HtmlSanitizer\Parser\{ParserInterface,NativeParser,MastermindsParser};

/** Bound the parsed DOM before the maintained sanitizer visits it recursively. */
final class BodyHtmlParser implements ParserInterface
{
    private ParserInterface $parser;
    public function __construct()
    {
        $this->parser=PHP_VERSION_ID>=80400 ? new NativeParser() : new MastermindsParser();
    }
    public function parse(string $html,string $context='body'): \DOMNode|\Dom\Node|null
    {
        $root=$this->parser->parse($html,$context);
        if(!$root) return null;
        $stack=[[$root,0]]; $count=0;
        while($stack) {
            [$node,$depth]=array_pop($stack);
            if(++$count>2048 || $depth>32) throw new ReceivingFailure('limit');
            foreach($node->childNodes as $child) $stack[]=[$child,$depth+1];
        }
        return $root;
    }
}

/** Minimal formatting with no remote resource elements or provider CSS. */
final class BodyHtml
{
    public static function render(#[\SensitiveParameter] string $html): string
    {
        if(strlen($html)>65536 || substr_count($html,'<')>4096) throw new ReceivingFailure('limit');
        if(!preg_match('//u',$html) || !extension_loaded('dom')) throw new ReceivingFailure('protocol');
        try {
            static $sanitizer=null;
            if($sanitizer===null) {
                $config=(new HtmlSanitizerConfig())->allowLinkSchemes(['http','https'])
                    ->allowRelativeLinks(false)->withMaxInputLength(65536)
                    // Masterminds retains document wrappers in fragments. Unwrap only
                    // these containers; their attributes and unknown/head subtrees drop.
                    ->blockElement('html')->blockElement('body');
                foreach(['p','div','span','br','hr','b','strong','i','em','u','s','blockquote','pre','code','ul','ol','li','table','thead','tbody','tr','th','td','h1','h2','h3','h4','h5','h6'] as $tag) $config=$config->allowElement($tag);
                $config=$config->allowElement('a',['href','title'])
                    ->forceAttribute('a','rel','noopener noreferrer')->forceAttribute('a','target','_blank');
                $sanitizer=new HtmlSanitizer($config,new BodyHtmlParser());
            }
            $result=$sanitizer->sanitize($html);
            if(strlen($result)>131072) throw new ReceivingFailure('limit');
            return $result;
        } catch(ReceivingFailure $e) {throw $e;}
        catch(\Throwable) {throw new ReceivingFailure('protocol');}
    }
}
