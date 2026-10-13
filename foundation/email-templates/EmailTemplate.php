<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\EmailTemplates;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One reusable email template. A-07 requires safe allowlisted
 * placeholders — construction rejects any `{{placeholder}}` not
 * registered in the shared allowlist, so a template can never smuggle
 * in an unresolved or unsafe merge field. Lifecycle `draft → active →
 * archived`; archival retains the record instead of deleting history.
 * `transactional` templates are excluded from ad-hoc compose insertion
 * — they belong to fixed flows the adapter controls.
 */
final readonly class EmailTemplate
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const ARCHIVED = 'archived';

    public const VISIBILITY_SHARED = 'shared';
    public const VISIBILITY_PERSONAL = 'personal';

    /** Allowed placeholder syntax: {{lower_snake}}. */
    private const PLACEHOLDER = '#\{\{([a-z][a-z0-9_]*)\}\}#';

    /**
     * @param list<string> $allowedPlaceholders registered names from PlaceholderRegistry
     */
    private function __construct(
        public string $id,
        public string $name,
        public string $subject,
        public string $body,
        public string $status,
        public string $visibility,
        public string $ownerId,
        public bool $transactional,
        public string $updatedBy,
        public int $updatedAt,
    ) {
        foreach (['id', 'name', 'subject', 'body', 'ownerId', 'updatedBy'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A template needs ' . $field . '.');
            }
        }
        if (!in_array($status, [self::DRAFT, self::ACTIVE, self::ARCHIVED], true)) {
            throw new InvalidArgumentException('Unknown template status.');
        }
        if (!in_array($visibility, [self::VISIBILITY_SHARED, self::VISIBILITY_PERSONAL], true)) {
            throw new InvalidArgumentException('Unknown template visibility.');
        }
        if ($updatedAt < 0) {
            throw new InvalidArgumentException('Template timestamp cannot be negative.');
        }
    }

    /**
     * Create a draft. Every `{{name}}` used in subject or body must be
     * registered in the allowlist — unknown placeholders are rejected.
     */
    public static function draft(
        string $id,
        string $name,
        string $subject,
        string $body,
        string $visibility,
        string $ownerId,
        bool $transactional,
        string $updatedBy,
        int $updatedAt,
        PlaceholderRegistry $registry,
    ): self {
        $template = new self($id, $name, $subject, $body, self::DRAFT, $visibility, $ownerId, $transactional, $updatedBy, $updatedAt);
        $template->assertPlaceholdersAllowed($registry);
        return $template;
    }

    /** Copy with new content — still draft-safe (edits land on the draft). */
    public function edit(string $subject, string $body, string $by, int $at, PlaceholderRegistry $registry): self
    {
        if ($this->status === self::ARCHIVED) {
            throw new InvalidArgumentException('An archived template cannot be edited; duplicate it first.');
        }
        $copy = new self($this->id, $this->name, $subject, $body, self::DRAFT, $this->visibility, $this->ownerId, $this->transactional, $by, $at);
        $copy->assertPlaceholdersAllowed($registry);
        return $copy;
    }

    /** Clone as a new draft under a new id/name — the A-07 duplicate path. */
    public function duplicate(string $newId, string $newName, string $by, int $at): self
    {
        return new self($newId, $newName, $this->subject, $this->body, self::DRAFT, self::VISIBILITY_PERSONAL, $by, $this->transactional, $by, $at);
    }

    /** Publish so it can be inserted into compose. */
    public function activate(string $by, int $at): self
    {
        if ($this->status !== self::DRAFT) {
            throw new InvalidArgumentException('Only a draft can be activated.');
        }
        return new self($this->id, $this->name, $this->subject, $this->body, self::ACTIVE, $this->visibility, $this->ownerId, $this->transactional, $by, $at);
    }

    /** Retire from compose lists without deleting the record. */
    public function archive(string $by, int $at): self
    {
        if ($this->status === self::ARCHIVED) {
            throw new InvalidArgumentException('Template is already archived.');
        }
        return new self($this->id, $this->name, $this->subject, $this->body, self::ARCHIVED, $this->visibility, $this->ownerId, $this->transactional, $by, $at);
    }

    /** Whether this template may appear in a staff member's compose list. */
    public function insertableBy(string $staffId): bool
    {
        if ($this->status !== self::ACTIVE || $this->transactional) {
            return false;
        }
        return $this->visibility === self::VISIBILITY_SHARED || $this->ownerId === $staffId;
    }

    /**
     * Every `{{name}}` placeholder used in subject + body, in first-use
     * order, deduplicated.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        preg_match_all(self::PLACEHOLDER, $this->subject . "\n" . $this->body, $matches);
        return array_values(array_unique($matches[1]));
    }

    /**
     * Resolve placeholders for preview. Missing values are NOT
     * substituted — the result carries them in `unresolved` so a preview
     * never silently emits raw `{{name}}` text as if it were content.
     *
     * @param array<string,string> $values
     * @return array{subject:string,body:string,unresolved:list<string>}
     */
    public function preview(array $values): array
    {
        $resolvedSubject = $this->subject;
        $resolvedBody = $this->body;
        $unresolved = [];
        foreach ($this->placeholders() as $name) {
            if (array_key_exists($name, $values) && $values[$name] !== '') {
                $resolvedSubject = str_replace('{{' . $name . '}}', $values[$name], $resolvedSubject);
                $resolvedBody = str_replace('{{' . $name . '}}', $values[$name], $resolvedBody);
            } else {
                $unresolved[] = $name;
            }
        }
        return ['subject' => $resolvedSubject, 'body' => $resolvedBody, 'unresolved' => $unresolved];
    }

    private function assertPlaceholdersAllowed(PlaceholderRegistry $registry): void
    {
        foreach ($this->placeholders() as $name) {
            if (!$registry->allows($name)) {
                throw new InvalidArgumentException("Placeholder '{{$name}}' is not in the allowlist.");
            }
        }
    }
}
