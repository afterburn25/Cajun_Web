<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIPackagePhp;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** One scalar observation; it cannot masquerade as a package list or a mutation result. */
final readonly class PackagePhpVersionObservation
{
    public string $scope;
    public function __construct(
        public PackagePhpVersionBinding $binding,
        public int $observedAt,
        public string $state,
        public string $reason,
        public ?string $value,
    ) {
        if($observedAt<0||!in_array($state,['complete','unavailable'],true))throw new \InvalidArgumentException('Invalid PHP observation state.');
        if($state==='complete'){
            if($reason!==''||$value===null)throw new \InvalidArgumentException('Invalid complete PHP observation.');
            PackagePhpVersion::value($value);
        }elseif($value!==null||!in_array($reason,['authentication','rate_limited','remote_error','unexpected_status','response_limit','malformed_body','unexpected_shape','invalid_value','transport_connect','transport_tls','transport_timeout','transport_protocol','transport_unavailable','transport_unknown'],true))throw new \InvalidArgumentException('Invalid unavailable PHP observation.');
        $this->scope=$binding->scope;
    }
}
