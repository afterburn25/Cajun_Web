<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * The classified outcome of a provider connection probe. VERIFIED means
 * the credential authenticated against the provider endpoint named by
 * the adapter — it does not certify that any particular operation is
 * supported (that is what ProviderCapability states are for).
 *
 * UNKNOWN is the honest verdict for ambiguity: a timeout or protocol
 * fault after the request may have been sent, a lost result, or a
 * response whose body does not match the endpoint's accepted shape.
 * It must never be reported as either a successful connection or a
 * clean failure.
 *
 * Details are a bounded classification channel — short printable UTF-8
 * only, like OperationResult messages. Provider response bodies,
 * headers, URLs and credential material must never be placed here;
 * they would persist into durable attempt history.
 */
final readonly class ConnectionCheck
{
    public const VERIFIED = 'verified';
    public const AUTHENTICATION_FAILED = 'authentication_failed';
    public const UNREACHABLE = 'unreachable';
    public const UNSUPPORTED = 'unsupported';
    public const UNKNOWN = 'unknown';

    private const MAX_DETAIL = 200;

    private function __construct(
        public string $state,
        public string $detail,
        public string $checkedAt,
    ) {
        if (strlen($detail) > self::MAX_DETAIL
            || preg_match('//u', $detail) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $detail)
        ) {
            throw new InvalidArgumentException('Check details are bounded printable classifications (<=200 chars, no control characters).');
        }
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

    /**
     * The probe outcome is indeterminate — the request may have been
     * sent but no usable verdict arrived (timeout, protocol fault
     * mid-transfer, lost result) or the response did not match the
     * endpoint's accepted shape. Never report this as authenticated
     * or as a definitive failure; the caller may retry explicitly.
     */
    public static function unknown(string $detail): self
    {
        return self::make(self::UNKNOWN, $detail);
    }

    public function ok(): bool
    {
        return $this->state === self::VERIFIED;
    }
}
