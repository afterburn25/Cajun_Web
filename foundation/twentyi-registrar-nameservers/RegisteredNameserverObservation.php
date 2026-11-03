<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIRegistrarNameservers;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/RegisteredNameserverBinding.php';
require_once __DIR__.'/../twentyi-operations/NameserverChange.php';
use CajunWebPro\Foundation\TwentyI\Operations\NameserverChange;

/** A provider-reported set. Neither registry eligibility nor proof that a particular write caused it. */
final readonly class RegisteredNameserverObservation
{
    public const REASONS=['authentication','rate_limited','remote_error','unexpected_status','response_limit','malformed_body','unexpected_shape','invalid_nameservers','transport_connect','transport_timeout','transport_tls','transport_protocol','transport_unavailable','transport_unknown'];
    public string $scope;
    public int $domainId;
    public string $domainName;
    public ?array $nameservers;
    public function __construct(public RegisteredNameserverBinding $binding,public string $state,?array $nameservers,public string $reason,public int $observedAt)
    {
        if ($observedAt<0 || !in_array($state,['complete','unavailable'],true)) throw new \InvalidArgumentException('Invalid registrar nameserver observation.');
        if ($state==='complete') {
            if ($reason!=='' || $nameservers===null || NameserverChange::observed($nameservers)!==$nameservers) throw new \InvalidArgumentException('Invalid registrar nameserver observation.');
            $this->nameservers=NameserverChange::observed($nameservers);
        } else {
            if ($nameservers!==null || !in_array($reason,self::REASONS,true)) throw new \InvalidArgumentException('Invalid registrar nameserver observation.');
            $this->nameservers=null;
        }
        $this->scope=$binding->scope;$this->domainId=$binding->data['domain_id'];$this->domainName=$binding->data['domain'];
    }
}
