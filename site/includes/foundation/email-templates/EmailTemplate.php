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
     * Strict double-brace grammar: every `{{` or `}}` in the source must be
     * part of a complete `{{lower_snake}}` token. This pattern also matches a
     * bare `{{`, a stray `}}`, or a malformed `{{...}}` (spaces, uppercase,
     * dots) so they can be rejected instead of slipping through as literal
     * text that would masquerade as "fully resolved" in preview. Ordinary
     * single braces stay usable.
     */
    private const BRACE_TOKEN = '#\{\{[^{}]*\}\}|\{\{|\}\}#';

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
        $template->assertGrammarAndAllowlist($registry);
        return $template;
    }

    /** Copy with new content — still draft-safe (edits land on the draft). */
    public function edit(string $subject, string $body, string $by, int $at, PlaceholderRegistry $registry): self
    {
        if ($this->status === self::ARCHIVED) {
            throw new InvalidArgumentException('An archived template cannot be edited; duplicate it first.');
        }
        $copy = new self($this->id, $this->name, $subject, $body, self::DRAFT, $this->visibility, $this->ownerId, $this->transactional, $by, $at);
        $copy->assertGrammarAndAllowlist($registry);
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
     * Resolve placeholders for preview in a SINGLE pass over the original
     * subject/body: merge values are literal data and are never re-scanned,
     * so a value containing `{{other_name}}` stays literal rather than
     * leaking another field's content.
     *
     * Missing or empty-string values are NOT substituted — the result
     * carries them in `unresolved` so a preview never silently emits raw
     * `{{name}}` text as if it were content.
     *
     * Rendered subject/body are PLAIN TEXT: a UI must escape them for
     * HTML display, and a mail adapter must reject header line breaks
     * before sending.
     *
     * @param array<string,string> $values string values only — anything
     *        else fails predictably instead of string-casting silently.
     * @return array{subject:string,body:string,unresolved:list<string>}
     */
    public function preview(array $values): array
    {
        foreach ($values as $name => $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException("Merge value '{$name}' must be a string.");
            }
        }
        $unresolved = [];
        foreach ($this->placeholders() as $name) {
            if (!isset($values[$name]) || $values[$name] === '') {
                $unresolved[] = $name;
            }
        }
        $resolve = static fn (array $m): string =>
            isset($values[$m[1]]) && $values[$m[1]] !== '' ? $values[$m[1]] : $m[0];
        return [
            'subject' => preg_replace_callback(self::PLACEHOLDER, $resolve, $this->subject),
            'body' => preg_replace_callback(self::PLACEHOLDER, $resolve, $this->body),
            'unresolved' => $unresolved,
        ];
    }

    private function assertGrammarAndAllowlist(PlaceholderRegistry $registry): void
    {
        preg_match_all(self::BRACE_TOKEN, $this->subject . "\n" . $this->body, $tokens);
        foreach ($tokens[0] as $token) {
            if (!preg_match('#^\{\{[a-z][a-z0-9_]*\}\}$#', $token)) {
                throw new InvalidArgumentException(
                    "Malformed placeholder token '{$token}'; use {{lower_snake}}."
                );
            }
        }
        foreach ($this->placeholders() as $name) {
            if (!$registry->allows($name)) {
                throw new InvalidArgumentException("Placeholder '{{$name}}' is not in the allowlist.");
            }
        }
    }
}
