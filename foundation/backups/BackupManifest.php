<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Backups;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * The integrity/scope record for one backup. A-03 requires secrets
 * protection — items matching private paths are rejected at
 * construction so a manifest can never describe a backup containing
 * config, keys, env files or the private store. Every item carries its
 * own sha256; `manifestHash()` binds the whole scope for tamper checks.
 */
final readonly class BackupManifest
{
    public const FORMAT_VERSION = 1;

    /** Path patterns that must never enter a restorable backup scope. */
    private const FORBIDDEN = '#^(config\.php|proxy-config\.php|\.installed\.php|\.env|.*\.(key|pem|p12)|storage/|.*payment-master)#i';

    /**
     * @param array<string,string> $items  path/table identifier => sha256 hex
     * @param string $retainUntil ISO date after which the backup may be pruned
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $createdBy,
        public string $createdAt,
        public array $items,
        public string $retainUntil,
        public string $notes = '',
    ) {
        foreach (['id', 'kind', 'createdBy', 'createdAt'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A manifest needs ' . $field . '.');
            }
        }
        if ($items === []) {
            throw new InvalidArgumentException('A backup needs at least one item.');
        }
        if (strtotime($createdAt) === false || strtotime($retainUntil) === false) {
            throw new InvalidArgumentException('createdAt and retainUntil must be parseable dates.');
        }
        if (strtotime($retainUntil) <= strtotime($createdAt)) {
            throw new InvalidArgumentException('Retention must extend past creation — a manifest that is born expired protects nothing.');
        }
        foreach ($items as $name => $hash) {
            if (preg_match(self::FORBIDDEN, (string) $name)) {
                throw new InvalidArgumentException('Backup scope cannot contain private/secret material: ' . $name);
            }
            if (!preg_match('/^[0-9a-f]{64}$/', (string) $hash)) {
                throw new InvalidArgumentException('Item hash must be a sha256 hex digest: ' . $name);
            }
        }
    }

    /** sha256 over the canonical item map — verifies the manifest itself. */
    public function manifestHash(): string
    {
        $items = $this->items;
        ksort($items);
        return hash('sha256', json_encode($items, JSON_UNESCAPED_SLASHES));
    }

    /** Whether a stored item still matches its recorded hash. */
    public function verifyItem(string $name, string $actualHash): bool
    {
        return isset($this->items[$name]) && hash_equals($this->items[$name], $actualHash);
    }

    public function expired(int $now): bool
    {
        return $now >= strtotime($this->retainUntil);
    }
}
