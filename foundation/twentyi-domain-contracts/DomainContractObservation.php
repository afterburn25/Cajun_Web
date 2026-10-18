<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDomainContracts;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
/** A date-only provider fact. It does not establish expiry, payment or renewal eligibility. */
final readonly class DomainContractObservation
{
    public const REASONS=['authentication','rate_limited','remote_error','unexpected_status','response_limit','malformed_body','unexpected_shape','date_missing','date_null','date_type','date_invalid','transport_connect','transport_timeout','transport_tls','transport_protocol','transport_unavailable','transport_unknown'];
    public string $scope;
    public int $domainId;
    public string $domainName;
    public function __construct(public DomainContractBinding $binding,public string $state,public ?string $renewalDate,public string $reason,public int $observedAt)
    {
        if($observedAt<0||!in_array($state,['complete','unavailable'],true))throw new \InvalidArgumentException('Invalid domain observation.');
        if($state==='complete'){if($reason!==''||!self::validDate($renewalDate))throw new \InvalidArgumentException('Invalid domain observation.');}
        elseif($renewalDate!==null||!in_array($reason,self::REASONS,true))throw new \InvalidArgumentException('Invalid domain observation.');
        $this->scope=$binding->scope;$this->domainId=$binding->data['domain_id'];$this->domainName=$binding->data['domain'];
    }
    /** Strict calendar validation is local policy; no timezone/midnight conversion is applied. */
    public static function validDate(mixed $date):bool
    {
        return is_string($date)&&preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/',$date,$m)===1&&checkdate((int)$m[2],(int)$m[3],(int)$m[1]);
    }
}
