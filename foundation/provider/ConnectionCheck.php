<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * The classified outcome of a provider connection probe. VERIFIED means
 * the credential authenticated against the provider endpoint named by
 * the adapter — it does not certify that any particular operation is
 * supported (that is what ProviderCapability states are for).
 */
final readonly class ConnectionCheck
{
    public const VERIFIED = 'verified';
    public const AUTHENTICATION_FAILED = 'authentication_failed';
    public const UNREACHABLE = 'unreachable';
    public const UNSUPPORTED = 'unsupported';

    private function __construct(
        public string $state,
        public string $detail,
        public string $checkedAt,
    ) {
    }

    private static function make(string $state, string $detail): self
    {
        return new self($state, $detail, gmdate('c'));
    }

    public static function verified(string $detail = 'Provider accepted the configured credentials.'): self
    {
        return self::make(self::VERIFIED, $detail);
    }

    public static function authenticationFailed(string $detail = 'Provider rejected the configured credentials.'): self
    {
        return self::make(self::AUTHENTICATION_FAILED, $detail);
    }

    public static function unreachable(string $detail): self
    {
        return self::make(self::UNREACHABLE, $detail);
    }

    public static function unsupported(string $detail): self
    {
        return self::make(self::UNSUPPORTED, $detail);
    }

    public function ok(): bool
    {
        return $this->state === self::VERIFIED;
    }
}
