<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyICloudCatalog;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\TwentyIInventory\{CloudProviderList, InventoryCloudProvider, InventoryRead};
require_once __DIR__.'/../twentyi-inventory/CloudProviderList.php';
require_once __DIR__.'/../twentyi-inventory/InventoryRead.php';

/**
 * Pure request identity from supplied saved-list facts. This validates their
 * shape and consistency, not their database provenance, HMAC or authorization.
 * Future application wiring must obtain/revalidate the complete tuple under the
 * existing staff lock, including current owner, profile/key and retained history.
 * No credentials, database, transport or response parser are owned here.
 */
final readonly class CloudProviderCatalogBinding
{
    /** Same local freshness policy as the existing cloud-provider observation. */
    public const MAX_AGE_SECONDS = 900;
    public const MAX_FUTURE_SECONDS = 30;
    private const KEYS = ['profile_id','profile_revision','key_fingerprint','credential_sha256',
        'source_id','source_seq','source_sha256','source_proof','operation','scope','state',
        'coverage','skipped','reason','started_epoch','observed_epoch','latest_id','identifiers_sha256'];

    public array $data;
    public string $json;
    public string $sha256;
    public string $provider;

    private function __construct(array $context, array $identifiers, string $provider)
    {
        $this->data = ['source'=>array_replace(array_fill_keys(self::KEYS, null), $context),
            'identifiers'=>$identifiers, 'provider'=>$provider];
        $this->json = json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->sha256 = hash('sha256', $this->json);
        $this->provider = $provider;
    }

    /** The caller supplies authoritative facts; a valid tuple alone proves no authority. */
    public static function fromSavedList(array $context, array $identifiers, string $provider, int $now): self
    {
        $actual = array_keys($context); sort($actual); $expected = self::KEYS; sort($expected);
        if ($actual !== $expected) throw new \InvalidArgumentException('Cloud catalog source fields are unsupported.');
        foreach (['profile_id','source_id','latest_id'] as $key) {
            if (!is_string($context[$key]) || preg_match('/\A[a-f0-9]{32}\z/', $context[$key]) !== 1) {
                throw new \InvalidArgumentException('Cloud catalog source identity is unsupported.');
            }
        }
        foreach (['key_fingerprint','credential_sha256','source_sha256','source_proof','identifiers_sha256'] as $key) {
            if (!is_string($context[$key]) || preg_match('/\A[a-f0-9]{64}\z/', $context[$key]) !== 1) {
                throw new \InvalidArgumentException('Cloud catalog source witness is unsupported.');
            }
        }
        foreach (['profile_revision','source_seq','started_epoch','observed_epoch'] as $key) {
            if (!is_int($context[$key]) || $context[$key] < 1) {
                throw new \InvalidArgumentException('Cloud catalog source sequence is unsupported.');
            }
        }
        if ($context['operation'] !== InventoryRead::CLOUD_PROVIDERS_LIST
            || $context['scope'] !== 'twenty-i|'.$context['profile_id'].'|'.InventoryRead::CLOUD_PROVIDERS_LIST
            || $context['state'] !== 'complete' || $context['coverage'] !== 'unknown'
            || $context['skipped'] !== 0 || $context['reason'] !== '' || $context['latest_id'] !== $context['source_id']) {
            throw new \InvalidArgumentException('Cloud catalog requires the supplied latest complete provider list.');
        }
        CloudProviderList::fromValues($identifiers);
        InventoryCloudProvider::fromValue($provider);
        $document = json_encode($identifiers, JSON_THROW_ON_ERROR);
        if (!hash_equals($context['identifiers_sha256'], hash('sha256', $document))
            || !in_array($provider, $identifiers, true)) {
            throw new \InvalidArgumentException('Cloud catalog provider does not match the supplied saved list.');
        }
        $binding = new self($context, $identifiers, $provider);
        $binding->assertFresh($now);
        return $binding;
    }

    /** Recheck time at request description; current account/history still requires application authority. */
    public function assertFresh(int $now): void
    {
        $source = $this->data['source'];
        if ($now < 1 || $now > PHP_INT_MAX - self::MAX_FUTURE_SECONDS
            || $source['started_epoch'] < $now - self::MAX_AGE_SECONDS
            || $source['started_epoch'] > $now + self::MAX_FUTURE_SECONDS
            || $source['observed_epoch'] < $source['started_epoch']
            || $source['observed_epoch'] > $now + self::MAX_FUTURE_SECONDS) {
            throw new \InvalidArgumentException('Cloud catalog supplied observation is outside its local freshness window.');
        }
    }
}
