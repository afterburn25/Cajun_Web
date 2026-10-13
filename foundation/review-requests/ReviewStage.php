<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ReviewRequests;

/**
 * Request lifecycle. `delivered` is only reachable after a drafted report;
 * `closed` is terminal. No stage implies anything was sent to the visitor.
 */
final class ReviewStage
{
    public const RECEIVED = 'received';
    public const IN_REVIEW = 'in-review';
    public const REPORT_DRAFTED = 'report-drafted';
    public const DELIVERED = 'delivered';
    public const CLOSED = 'closed';

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::RECEIVED => [self::IN_REVIEW, self::CLOSED],
        self::IN_REVIEW => [self::REPORT_DRAFTED, self::CLOSED],
        self::REPORT_DRAFTED => [self::DELIVERED, self::IN_REVIEW, self::CLOSED],
        self::DELIVERED => [self::CLOSED],
        self::CLOSED => [],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return [self::RECEIVED, self::IN_REVIEW, self::REPORT_DRAFTED, self::DELIVERED, self::CLOSED];
    }

    public static function isKnown(string $stage): bool
    {
        return in_array($stage, self::all(), true);
    }

    /** @return list<string> */
    public static function allowedFrom(string $stage): array
    {
        return self::TRANSITIONS[$stage] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::allowedFrom($from), true);
    }
}
