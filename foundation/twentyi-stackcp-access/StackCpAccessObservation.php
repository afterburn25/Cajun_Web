<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIStackCpAccess;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

final readonly class StackCpAccessObservation implements \JsonSerializable {
    public StackCpAccessBinding $binding;
    public string $scope;
    public ?array $values;
    public function __construct(StackCpAccessBinding $binding,public int $observedAt,public string $state,public string $reason,?array $values) {
        $this->binding=$binding->validated();$this->scope=$this->binding->scope;
        if ($observedAt<0||!in_array($state,['complete','unavailable'],true)) self::deny();
        $reasons=['authentication','rate_limited','remote_error','unexpected_status','response_limit','malformed_body','unexpected_shape','invalid_value','transport_dns','transport_tls','transport_connect','transport_timeout','transport_network','transport_protocol','transport_unknown'];
        if ($state==='unavailable') {if ($values!==null||!in_array($reason,$reasons,true)) self::deny();$this->values=null;return;}
        if ($reason!==''||!is_array($values)||!array_is_list($values)||count($values)>1000) self::deny();
        $copy=[];$seen=[];
        foreach ($values as $value) {$v=$this->binding->data['operation']===StackCpAccessBinding::MEMBERSHIP?StackCpAccessBinding::identity($value):StackCpAccessBinding::tuple($value);if (isset($seen[$v])) self::deny();$seen[$v]=true;$copy[]=$v;}
        $this->values=$copy;
    }
    private static function deny():never {throw new \InvalidArgumentException('Invalid StackCP access observation.');}
    public function validated():self {
        if (!isset($this->binding,$this->scope,$this->observedAt,$this->state,$this->reason)) self::deny();
        $copy=new self($this->binding,$this->observedAt,$this->state,$this->reason,$this->values);
        if ($copy->scope!==$this->scope||$copy->values!==$this->values) self::deny();return $copy;
    }
    public function selectedMatch():bool {
        $copy=$this->validated();return $copy->state==='complete'&&($copy->binding->data['operation']===StackCpAccessBinding::MEMBERSHIP
            ?in_array($copy->binding->data['stack_identity'],$copy->values,true):$copy->values===['package:'.$copy->binding->data['package_id']]);
    }
    public function jsonSerialize():array {return ['state'=>$this->state,'reason'=>$this->reason,'observed_at'=>$this->observedAt,'values'=>$this->values,'login_ready'=>false,'remote_scope_restricted'=>false];}
}
