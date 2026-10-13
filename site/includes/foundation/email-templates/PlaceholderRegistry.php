<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\EmailTemplates;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * The allowlist of merge fields templates may use. A-07 requires
 * allowlisted placeholders — the registry is the single place names and
 * their descriptions live, so the adapter's compose UI can explain each
 * field and templates can never reference arbitrary data.
 */
final readonly class PlaceholderRegistry
{
    /** @var array<string,string> name => human description */
    private array $allowed;

    /** @param array<string,string> $allowed */
    public function __construct(array $allowed)
    {
        if ($allowed === []) {
            throw new InvalidArgumentException('The placeholder allowlist cannot be empty.');
        }
        foreach ($allowed as $name => $description) {
            if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException('Placeholder names must be lower_snake: ' . (string) $name);
            }
            if (!is_string($description) || trim($description) === '') {
                throw new InvalidArgumentException('Every placeholder needs a human description.');
            }
        }
        $this->allowed = $allowed;
    }

    public function allows(string $name): bool
    {
        return isset($this->allowed[$name]);
    }

    /** @return array<string,string> name => description, sorted by name for stable UI. */
    public function all(): array
    {
        $sorted = $this->allowed;
        ksort($sorted);
        return $sorted;
    }
}
