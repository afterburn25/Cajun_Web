<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIPackageWordpressInstalled;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** A reported installation indication, not absence, version, health or an action result. */
final readonly class PackageWordpressInstalledObservation
{
    public string $scope;
    public ?bool $value;
    public PackageWordpressInstalledBinding $binding;

    public function __construct(
        PackageWordpressInstalledBinding $binding,
        public int $observedAt,
        public string $state,
        public string $reason,
        mixed $value,
    ) {
        $this->binding=$binding->validated();
        if($observedAt<0||!in_array($state,['complete','unavailable'],true))throw new \InvalidArgumentException('Invalid WordPress installation observation state.');
        if($state==='complete'){
            if($reason!==''||!is_bool($value))throw new \InvalidArgumentException('A complete WordPress installation observation requires a boolean.');
        }elseif($value!==null||!in_array($reason,['authentication','rate_limited','remote_error','unexpected_status','response_limit','malformed_body','value_null','unexpected_shape','invalid_value','transport_connect','transport_tls','transport_timeout','transport_protocol','transport_unavailable','transport_unknown'],true))throw new \InvalidArgumentException('Invalid unavailable WordPress installation observation.');
        $this->value=$value;
        $this->scope=$this->binding->scope;
    }

    public function validate():void
    {
        $this->validated();
    }

    /** Restored observations are consumed through a validated detached snapshot. */
    public function validated():self
    {
        if(!isset($this->binding,$this->observedAt,$this->state,$this->reason,$this->scope)||!array_key_exists('value',get_object_vars($this)))throw new \InvalidArgumentException('Incomplete WordPress installation observation.');
        $expected=new self($this->binding,$this->observedAt,$this->state,$this->reason,$this->value);
        if($expected->scope!==$this->scope)throw new \InvalidArgumentException('Inconsistent WordPress installation observation scope.');
        return $expected;
    }
}
