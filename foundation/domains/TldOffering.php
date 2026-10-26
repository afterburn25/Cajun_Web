<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Domains;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One TLD's commercial configuration, supplied by the trusted adapter
 * from admin-entered prices. Prices are integer minor units; a null
 * price means that operation is not offered for this TLD — the
 * component cannot invent registrar pricing.
 */
final readonly class TldOffering
{
    public const REGISTER = 'register';
    public const RENEW = 'renew';
    public const TRANSFER = 'transfer';

    private const OPERATIONS = [self::REGISTER, self::RENEW, self::TRANSFER];

    public function __construct(
        public string $tld,
        public ?int $registerCents,
        public ?int $renewCents,
        public ?int $transferCents,
        public int $minYears = 1,
        public int $maxYears = 10,
        public bool $privacySupported = false,
        public ?int $privacyCents = null,
    ) {
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $tld)) {
            throw new InvalidArgumentException('Invalid TLD: ' . $tld);
        }
        foreach (['registerCents', 'renewCents', 'transferCents', 'privacyCents'] as $price) {
            if ($this->$price !== null && $this->$price < 0) {
                throw new InvalidArgumentException('Domain prices cannot be negative.');
            }
        }
        if ($minYears < 1 || $maxYears < $minYears || $maxYears > 10) {
            throw new InvalidArgumentException('Registration years must be within 1–10.');
        }
        if (!$privacySupported && $privacyCents !== null) {
            throw new InvalidArgumentException('A privacy price requires privacy support.');
        }
    }

    /** Configured per-year minor-unit price for an operation, or null when not offered. */
    public function priceFor(string $operation): ?int
    {
        return match ($operation) {
            self::REGISTER => $this->registerCents,
            self::RENEW => $this->renewCents,
            self::TRANSFER => $this->transferCents,
            default => throw new InvalidArgumentException('Unknown domain operation: ' . $operation),
        };
    }

    public static function validOperation(string $operation): bool
    {
        return in_array($operation, self::OPERATIONS, true);
    }
}
