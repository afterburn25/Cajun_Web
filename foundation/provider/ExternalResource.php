<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Reconciliation link between a local record and a provider-side
 * resource. HC-07 requires reviewed import/linking — a LINKED record is
 * only a *recorded claim* (e.g. staff typed in a package ID), not proof
 * of ownership; `verify()` transitions it to VERIFIED only when the
 * provider-confirmed ID matches. Ownership is never inferred from
 * email or domain matches.
 */
final readonly class ExternalResource
{
    public const LINKED = 'linked';
    public const VERIFIED = 'verified';
    public const MISMATCH = 'mismatch';

    private function __construct(
        public string $localType,
        public string $localId,
        public string $providerId,
        public string $state,
    ) {
        if (trim($localType) === '' || trim($localId) === '') {
            throw new InvalidArgumentException('An external resource needs a local type and id.');
        }
    }

    /** Record an observed/claimed provider ID — not yet confirmed. */
    public static function link(string $localType, string $localId, string $providerId): self
    {
        if (trim($providerId) === '') {
            throw new InvalidArgumentException('Linking needs a provider-side identifier.');
        }
        return new self($localType, $localId, $providerId, self::LINKED);
    }

    /**
     * Confirm against the ID the provider itself reports. A different
     * ID produces a MISMATCH record for review rather than silently
     * re-linking.
     */
    public function verify(string $confirmedProviderId): self
    {
        if ($confirmedProviderId === $this->providerId) {
            return new self($this->localType, $this->localId, $this->providerId, self::VERIFIED);
        }
        return new self($this->localType, $this->localId, $confirmedProviderId, self::MISMATCH);
    }

    public function verified(): bool
    {
        return $this->state === self::VERIFIED;
    }
}
