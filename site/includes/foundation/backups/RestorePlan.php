<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Backups;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * A restore is a two-phase operation: `preview` describes exactly which
 * manifest items would overwrite current state, then `authorize` —
 * with an explicit actor and matching confirmation phrase — unlocks the
 * plan for the adapter to apply. Restores of expired backups, wrong
 * confirmation phrases, or manifests whose format this build cannot
 * read are all refused. The adapter performs the actual file/table
 * writes; this object only guarantees the deliberate-approval contract.
 */
final class RestorePlan
{
    public const PREVIEW = 'preview';
    public const AUTHORIZED = 'authorized';
    public const APPLIED = 'applied';
    public const ABANDONED = 'abandoned';

    private string $state = self::PREVIEW;
    private ?string $authorizedBy = null;

    /**
     * @param array<string,array{item:string,current:string}> $impact
     *        per item: current state description ('missing','differs','identical')
     */
    private function __construct(
        public readonly string $id,
        public readonly BackupManifest $manifest,
        public readonly array $impact,
    ) {
    }

    /**
     * Build a preview by comparing manifest hashes to the caller's
     * current-state map (identifier => sha256 or null when absent).
     *
     * @param array<string,?string> $currentHashes
     */
    public static function preview(
        string $id,
        BackupManifest $manifest,
        array $currentHashes,
        int $supportedFormatVersion,
    ): self {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A restore plan needs an id.');
        }
        if (BackupManifest::FORMAT_VERSION > $supportedFormatVersion) {
            throw new InvalidArgumentException('Backup format ' . BackupManifest::FORMAT_VERSION . ' is newer than this installation supports.');
        }
        $impact = [];
        foreach ($manifest->items as $name => $hash) {
            $current = $currentHashes[$name] ?? null;
            $impact[$name] = [
                'item' => $name,
                'current' => $current === null ? 'missing' : (hash_equals($hash, $current) ? 'identical' : 'differs'),
            ];
        }
        return new self($id, $manifest, $impact);
    }

    /**
     * Deliberate authorization: the actor must type the exact phrase and
     * the backup must be within retention. Returns the authorized plan.
     */
    public function authorize(string $actorId, string $confirmation, string $expectedPhrase, int $now): self
    {
        if ($this->state !== self::PREVIEW) {
            throw new LogicException('Restore already ' . $this->state . '.');
        }
        if (trim($actorId) === '') {
            throw new InvalidArgumentException('A restore needs an authorizing actor.');
        }
        if (!hash_equals($expectedPhrase, $confirmation)) {
            throw new LogicException('Confirmation phrase did not match.');
        }
        if ($this->manifest->expired($now)) {
            throw new LogicException('Backup is past its retention date; restore needs a fresh archive.');
        }
        $this->state = self::AUTHORIZED;
        $this->authorizedBy = $actorId;
        return $this;
    }

    /** Adapter reports the apply completed — only from authorized. */
    public function applied(): self
    {
        if ($this->state !== self::AUTHORIZED) {
            throw new LogicException('Cannot apply an unauthorized restore.');
        }
        $this->state = self::APPLIED;
        return $this;
    }

    /** Operator abandons the plan — allowed any time before applied. */
    public function abandon(): self
    {
        if ($this->state === self::APPLIED) {
            throw new LogicException('An applied restore cannot be abandoned.');
        }
        $this->state = self::ABANDONED;
        return $this;
    }

    /** Items that would actually change — for the impact screen. */
    public function changedItems(): array
    {
        return array_values(array_filter($this->impact, fn ($i) => $i['current'] !== 'identical'));
    }

    public function state(): string
    {
        return $this->state;
    }

    public function authorizedBy(): ?string
    {
        return $this->authorizedBy;
    }
}
