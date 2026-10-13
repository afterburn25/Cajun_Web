<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Comparisons;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use LogicException;

/**
 * The sourced-fact registry backing comparison content. Duplicate ids
 * are rejected; lookups are by id, provider or aspect. The registry is
 * the single source sections resolve against — a claim that is not a
 * registered fact cannot be published.
 */
final class ComparisonRegistry
{
    /** @var array<string,ComparisonFact> */
    private array $facts = [];

    public function register(ComparisonFact $fact): self
    {
        if (isset($this->facts[$fact->id])) {
            throw new LogicException('Fact id already registered: ' . $fact->id);
        }
        $this->facts[$fact->id] = $fact;
        return $this;
    }

    public function fact(string $id): ?ComparisonFact
    {
        return $this->facts[$id] ?? null;
    }

    /** @return ComparisonFact[] */
    public function forProvider(string $provider): array
    {
        return array_values(array_filter($this->facts, fn (ComparisonFact $f) => $f->provider === $provider));
    }

    /** @return ComparisonFact[] */
    public function forAspect(string $aspect): array
    {
        return array_values(array_filter($this->facts, fn (ComparisonFact $f) => $f->aspect === $aspect));
    }

    /** Facts still within their review window at $now. */
    public function current(int $now): array
    {
        return array_values(array_filter($this->facts, fn (ComparisonFact $f) => !$f->stale($now)));
    }

    /** Facts past their review date — surfaced so content can be refreshed. */
    public function staleFacts(int $now): array
    {
        return array_values(array_filter($this->facts, fn (ComparisonFact $f) => $f->stale($now)));
    }

    public function all(): array
    {
        return array_values($this->facts);
    }
}
