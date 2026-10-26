<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Immutable provenance register for one provider's documented
 * operation surface (e.g. the 20i reseller API reference the owner
 * supplied). Entries record *evidence*, never capability: no entry
 * carries a credential, transport or executable request, and adding
 * metadata here cannot make the gateway run anything.
 *
 * Projection to ProviderCapability is deliberately conservative:
 * - VERIFIED      -> SUPPORTED (idempotent only when risk is READ);
 *                    no catalog entry may reach VERIFIED without a
 *                    separately recorded authorized verification.
 * - DOCUMENTED /  -> UNVERIFIED. Documentation or a single authorized
 *   AUTHENTICATED   live observation does not prove the account's
 *                   entitlement or the response schema.
 * - UNSUPPORTED   -> UNSUPPORTED.
 * An operation absent from the catalog is simply absent — callers must
 * not treat absence as either support or refusal.
 */
final class CapabilityCatalog
{
    /** @var array<string,CatalogEntry> */
    private array $entries = [];

    public function __construct(
        public readonly string $providerId,
        CatalogEntry ...$entries,
    ) {
        if (trim($providerId) === '') {
            throw new InvalidArgumentException('A capability catalog needs a provider identifier.');
        }
        foreach ($entries as $entry) {
            if (isset($this->entries[$entry->operation])) {
                throw new InvalidArgumentException('Duplicate catalog operation: ' . $entry->operation);
            }
            $this->entries[$entry->operation] = $entry;
        }
    }

    public function entry(string $operation): ?CatalogEntry
    {
        return $this->entries[$operation] ?? null;
    }

    /** @return CatalogEntry[] In insertion order. */
    public function entries(): array
    {
        return array_values($this->entries);
    }

    /**
     * Read-only evidence projection. Anything below VERIFIED projects
     * UNVERIFIED — the execution layer can therefore never be enabled
     * by documentation alone.
     *
     * @return ProviderCapability[]
     */
    public function capabilities(): array
    {
        $out = [];
        foreach ($this->entries as $entry) {
            $out[] = match ($entry->evidence) {
                CatalogEntry::EVIDENCE_VERIFIED => ProviderCapability::supported(
                    $entry->operation, $entry->note, $entry->risk === CatalogEntry::RISK_READ),
                CatalogEntry::EVIDENCE_UNSUPPORTED => ProviderCapability::unsupported(
                    $entry->operation, $entry->note),
                default => ProviderCapability::unverified(
                    $entry->operation, $entry->evidence . ': ' . $entry->note),
            };
        }
        return $out;
    }
}
