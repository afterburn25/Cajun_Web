<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use DirectoryTree\ImapEngine\Connection\ImapTokenizer;
use DirectoryTree\ImapEngine\Connection\Tokens\{Token,Literal,ListOpen,ListClose,ResponseCodeOpen,ResponseCodeClose};

/** Maintained tokenizer with strict header-only literal/token/nesting boundaries. */
final class BoundedTokenizer extends ImapTokenizer
{
    private int $tokens=0;
    private array $groups=[];
    public function nextToken(): ?Token
    {
        if(++$this->tokens>4096)throw new \RuntimeException('limit');
        $token=parent::nextToken();
        if($token instanceof ListOpen || $token instanceof ResponseCodeOpen){
            if(count($this->groups)>=12)throw new \RuntimeException('limit');
            $this->groups[]=$token instanceof ListOpen?'list':'code';
        } elseif($token instanceof ListClose || $token instanceof ResponseCodeClose){
            if(array_pop($this->groups)!==($token instanceof ListClose?'list':'code'))throw new \RuntimeException('protocol');
        }
        return $token;
    }
    protected function readLiteral(): Literal
    {
        // The bounded line stream must supply one physical line at a time, through CRLF.
        $marker=substr($this->buffer,$this->position);
        if(!preg_match('/\A\{(0|[1-9][0-9]{0,5})\}\r\n\z/D',$marker,$m))throw new \RuntimeException('protocol');
        if((int)$m[1]>16384)throw new \RuntimeException('limit');
        return parent::readLiteral();
    }
}
