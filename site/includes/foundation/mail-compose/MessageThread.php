<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailCompose;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/** Parsed message identifiers only; no raw-header parsing, authority, storage or I/O. */
final readonly class MessageThread
{
    public const MAX_IDENTIFIER_BYTES = 512;
    public const MAX_REFERENCES = 32;
    public const MAX_REFERENCE_BYTES = 4096;

    public ?string $inReplyTo;
    /** @var list<string> */
    public array $references;

    private function __construct(?string $inReplyTo, array $references)
    {
        if ($inReplyTo !== null) self::identifier($inReplyTo);
        $this->inReplyTo = $inReplyTo;
        $this->references = self::identifiers($references);
        if (strlen(implode(' ', $this->references)) > self::MAX_REFERENCE_BYTES) {
            throw new InvalidArgumentException('Conversation references exceed the supported header size.');
        }
    }

    /** New messages and forwards do not inherit another conversation's reply headers. */
    public static function none(): self
    {
        return new self(null, []);
    }

    /**
     * RFC 5322 section 3.6.4, single-parent reply. Inputs are already parsed
     * identifier tokens; missing Message-ID is distinct from a malformed id.
     * Bounds refuse rather than silently discard a conversation's history.
     */
    public static function reply(?string $parentId, array $parentReferences = [], array $parentInReplyTo = []): self
    {
        if ($parentId !== null) self::identifier($parentId);
        $references = self::identifiers($parentReferences);
        $inReplyTo = self::identifiers($parentInReplyTo);
        if ($references === [] && count($inReplyTo) === 1) $references = $inReplyTo;
        if ($parentId !== null) $references[] = $parentId;
        return new self($parentId, $references);
    }

    /** Strict persisted shape; this does not authenticate the record or its author. */
    public static function fromStored(array $value): self
    {
        $keys = array_keys($value);
        sort($keys);
        if ($keys !== ['in_reply_to', 'references']
            || (!is_string($value['in_reply_to']) && $value['in_reply_to'] !== null)
            || !is_array($value['references'])) {
            throw new InvalidArgumentException('Saved conversation references are damaged.');
        }
        return new self($value['in_reply_to'], $value['references']);
    }

    public function toArray(): array
    {
        return ['in_reply_to' => $this->inReplyTo, 'references' => $this->references];
    }

    private static function identifiers(array $input): array
    {
        if (!array_is_list($input) || count($input) > self::MAX_REFERENCES) {
            throw new InvalidArgumentException('Conversation references must be a bounded ordered list.');
        }
        $copy = [];
        foreach ($input as $id) {
            if (!is_string($id)) throw new InvalidArgumentException('Each conversation reference must be a message identifier.');
            self::identifier($id);
            // Copy each value so external PHP references cannot mutate this object.
            $copy[] = $id;
        }
        return $copy;
    }

    private static function identifier(string $value): void
    {
        // Modern dot-atom identifiers and no-fold literals; obsolete quoted ids
        // and external CFWS must not be guessed or trimmed into valid headers.
        $atom = '[A-Za-z0-9!#$%&\'*+\-/=?^_`{|}\x7E]+';
        $dot = $atom . '(?:\.' . $atom . ')*';
        if (strlen($value) > self::MAX_IDENTIFIER_BYTES
            || preg_match('~\A<' . $dot . '@(?:' . $dot . '|\[[\x21-\x5A\x5E-\x7E]*\])>\z~D', $value) !== 1) {
            throw new InvalidArgumentException('Unsupported conversation message identifier.');
        }
    }
}
