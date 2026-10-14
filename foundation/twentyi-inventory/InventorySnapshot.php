<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One bounded observation of the provider's package list. States are
 * honest: COMPLETE means every item projected, PARTIAL means some items
 * were skipped, UNAVAILABLE means the response gives no trustworthy
 * inventory. An empty COMPLETE list is a real empty observation — a
 * failed read never masquerades as one.
 */
final readonly class InventorySnapshot
{
    public const COMPLETE = 'complete';
    public const PARTIAL = 'partial';
    public const UNAVAILABLE = 'unavailable';

    /**
     * @param InventoryPackage[] $packages
     * @param string[]           $skipReasons First few reasons items were skipped (bounded).
     * @param string             $reason      Unavailable kind; empty when packages were observed.
     */
    public function __construct(
        public string $state,
        public array $packages,
        public int $observedAt,
        public int $skipped = 0,
        public array $skipReasons = [],
        public string $reason = '',
    ) {
        if (!in_array($state, [self::COMPLETE, self::PARTIAL, self::UNAVAILABLE], true)) {
            throw new InvalidArgumentException('Unknown inventory snapshot state.');
        }
        if ($state === self::UNAVAILABLE && ($packages !== [] || $reason === '')) {
            throw new InvalidArgumentException('An unavailable snapshot carries a reason and no packages.');
        }
        if ($state !== self::UNAVAILABLE && $reason !== '') {
            throw new InvalidArgumentException('An observed snapshot has no unavailable reason.');
        }
        if ($state === self::COMPLETE && $skipped > 0) {
            throw new InvalidArgumentException('A complete snapshot cannot have skipped items.');
        }
        if ($skipped > 0 && $state === self::PARTIAL && $skipReasons === []) {
            throw new InvalidArgumentException('A partial snapshot names why items were skipped.');
        }
    }

    public static function unavailable(string $reason, int $observedAt): self
    {
        return new self(self::UNAVAILABLE, [], $observedAt, 0, [], $reason);
    }

    public function ok(): bool
    {
        return $this->state !== self::UNAVAILABLE;
    }
}
