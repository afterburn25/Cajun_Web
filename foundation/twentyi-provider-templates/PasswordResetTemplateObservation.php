<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIProviderTemplates;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/PasswordResetTemplate.php';

/** A private reported string, never proof of rendered output, reset behavior or email delivery. */
final readonly class PasswordResetTemplateObservation implements \JsonSerializable
{
    private ?PasswordResetTemplate $templateValue;
    public string $scope;
    public const REASONS = ['authentication', 'rate_limited', 'remote_error', 'unexpected_status',
        'response_limit', 'malformed_body', 'unexpected_shape', 'invalid_value',
        'transport_connect', 'transport_tls', 'transport_timeout', 'transport_protocol',
        'transport_unavailable', 'transport_unknown'];

    public function __construct(
        public string $profileId,
        public int $observedAt,
        public string $state,
        public string $reason,
        #[\SensitiveParameter] ?PasswordResetTemplate $template,
    ) {
        if (preg_match('/\A[a-f0-9]{32}\z/', $profileId) !== 1 || $observedAt < 0
            || !in_array($state, ['complete', 'unavailable'], true)) {
            throw new \InvalidArgumentException('Invalid template observation context.');
        }
        if (($state === 'complete' && ($reason !== '' || $template === null))
            || ($state === 'unavailable' && ($template !== null || !in_array($reason, self::REASONS, true)))) {
            throw new \InvalidArgumentException('Invalid template observation outcome.');
        }
        $this->templateValue = $template?->validated();
        $this->scope = 'twenty-i|'.$profileId.'|reseller.passwordresettemplate.read';
    }

    /** Explicit access; ordinary JSON/debug output contains metadata only. */
    public function template(): ?PasswordResetTemplate { return $this->validated()->templateValue; }

    public function validated(): self
    {
        if (!isset($this->profileId, $this->observedAt, $this->state, $this->reason, $this->scope)
            || !array_key_exists('templateValue', get_object_vars($this))) {
            throw new \InvalidArgumentException('Incomplete template observation.');
        }
        $copy = new self($this->profileId, $this->observedAt, $this->state, $this->reason, $this->templateValue);
        if ($copy->scope !== $this->scope) throw new \InvalidArgumentException('Inconsistent template observation.');
        return $copy;
    }

    public function jsonSerialize(): array
    {
        $copy = $this->validated();
        return ['profile_id'=>$copy->profileId, 'observed_at'=>$copy->observedAt,
            'state'=>$copy->state, 'reason'=>$copy->reason, 'scope'=>$copy->scope,
            'content_sha256'=>$copy->templateValue?->sha256, 'content_bytes'=>$copy->templateValue?->bytes];
    }

    public function __debugInfo(): array { return $this->jsonSerialize(); }
    public function __serialize(): array { throw new \LogicException('Private template observation serialization is unavailable.'); }
    public function __unserialize(array $data): void { throw new \LogicException('Private template observation restoration is unavailable.'); }
}
