<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIStackCpAccess;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Bounded duplicate-aware JSON validation before ordinary decoding loses object keys. */
final class StackCpAccessJson {
    public const MAX_BYTES=131072;
    private int $offset=0;
    private int $tokens=0;
    private function __construct(private string $body) {}
    public static function decode(string $body):mixed {
        if (strlen($body)>self::MAX_BYTES||preg_match('//u',$body)!==1) self::deny();
        $parser=new self($body);$parser->value(0);$parser->space();if ($parser->offset!==strlen($body)) self::deny();
        return json_decode($body,false,16,JSON_THROW_ON_ERROR);
    }
    private function space():void {while (isset($this->body[$this->offset])&&str_contains(" \r\n\t",$this->body[$this->offset])) $this->offset++;}
    private function value(int $depth):void {
        $this->space();if (++$this->tokens>100000||$depth>8) self::deny();$c=$this->body[$this->offset]??'';
        if ($c==='"') {$this->string();return;}
        if ($c==='{'||$c==='[') {
            $this->offset++;$this->space();$close=$c==='{'?'}':']';if (($this->body[$this->offset]??'')===$close) {$this->offset++;return;}
            $keys=[];$count=0;
            while (true) {
                if (++$count>($c==='{'?64:1000)) self::deny();
                if ($c==='{') {$key=$this->string();if (strlen($key)>256||isset($keys['k'.$key])) self::deny();$keys['k'.$key]=true;$this->space();if (($this->body[$this->offset++]??'')!==':') self::deny();}
                $this->value($depth+1);$this->space();$next=$this->body[$this->offset++]??'';
                if ($next===$close) return;if ($next!==',') self::deny();$this->space();
            }
        }
        if (!preg_match('/\G(?:true|false|null|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)/',$this->body,$m,0,$this->offset)) self::deny();
        $value=json_decode($m[0],false,2,JSON_THROW_ON_ERROR);if (is_float($value)&&!is_finite($value)) self::deny();$this->offset+=strlen($m[0]);
    }
    private function string():string {
        $this->space();$start=$this->offset;if (($this->body[$this->offset++]??'')!=='"') self::deny();$escaped=false;
        while (isset($this->body[$this->offset])) {
            $c=$this->body[$this->offset++];
            if (!$escaped&&$c==='"') {$value=json_decode(substr($this->body,$start,$this->offset-$start),false,2,JSON_THROW_ON_ERROR);if (!is_string($value)) self::deny();return $value;}
            if (!$escaped&&$c==='\\') $escaped=true;else $escaped=false;
        }
        self::deny();
    }
    private static function deny():never {throw new \InvalidArgumentException('Unsupported StackCP access response.');}
}
