<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Desk;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * HC-12 — a typed reference between records, so staff can trace a paid
 * order to its provider operation to its invoice/service/client records
 * without raw database access.
 *
 * Links are constrained `kind:id` pairs over an allowlisted set of
 * record kinds — a link can never point at an unrecognized kind, so a
 * trace view can only surface routes the application actually has.
 * Stringly-typed references elsewhere are not retrovalidated here; this
 * class only guarantees the shape of new links.
 *
 * @internal Roadmap HC-12; additively inserted.
 */
final readonly class TraceLink
{
    /** Record kinds that may appear in a trace chain. */
    public const KINDS = [
        'order', 'order_item', 'invoice', 'invoice_item', 'payment',
        'provider_operation', 'hosting_service', 'vps_service', 'domain',
        'client', 'project', 'ticket', 'fulfillment_job', 'subscription',
    ];

    public function __construct(
        public string $kind,
        public string $id,
    ) {
        if (!in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown traceable kind '{$kind}'.");
        }
        if (trim($id) === '') {
            throw new InvalidArgumentException('A trace link needs a non-empty id.');
        }
    }

    public function key(): string
    {
        return $this->kind . ':' . $this->id;
    }

    public static function parse(string $key): self
    {
        $pos = strpos($key, ':');
        if ($pos === false) {
            throw new InvalidArgumentException("Malformed trace link '{$key}'.");
        }
        return new self(substr($key, 0, $pos), substr($key, $pos + 1));
    }
}
