<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ReviewRequests;

use InvalidArgumentException;

/**
 * Ordered inbox of requests plus the legal stage moves. Ordering is
 * deterministic — submittedAt, then id — independent of input order. Stage
 * changes never invent a report: `delivered` additionally requires the
 * saved report for that request and the contact's recorded consent.
 */
final class ReviewInbox
{
    /** @var array<string, ReviewRequest> */
    private array $requests = [];

    /** @param iterable<mixed> $requests */
    public function __construct(iterable $requests = [])
    {
        foreach ($requests as $request) {
            if (!$request instanceof ReviewRequest) {
                throw new InvalidArgumentException('The review inbox only accepts ReviewRequest entries.');
            }
            if (isset($this->requests[$request->id])) {
                throw new InvalidArgumentException("Duplicate review request id '{$request->id}'.");
            }
            $this->requests[$request->id] = $request;
        }
        $this->sort();
    }

    /** @return list<ReviewRequest> Deterministically ordered: submittedAt, then id. */
    public function all(): array
    {
        return array_values($this->requests);
    }

    /** @return list<ReviewRequest> */
    public function withStatus(string $status): array
    {
        if (!ReviewStage::isKnown($status)) {
            throw new InvalidArgumentException("Unknown review request status '{$status}'.");
        }
        return array_values(array_filter($this->requests, static fn (ReviewRequest $r): bool => $r->status === $status));
    }

    public function byId(string $id): ?ReviewRequest
    {
        return $this->requests[$id] ?? null;
    }

    public function add(ReviewRequest $request): void
    {
        if (isset($this->requests[$request->id])) {
            throw new InvalidArgumentException("Duplicate review request id '{$request->id}'.");
        }
        $this->requests[$request->id] = $request;
        $this->sort();
    }

    /**
     * Apply a legal stage move. Delivery is guarded: the saved report for the
     * request must exist and the contact must have consented to being
     * contacted, otherwise nothing can honestly be called delivered.
     */
    public function transition(ReviewRequest $request, string $to, ?ReviewReport $report = null): ReviewRequest
    {
        if (!ReviewStage::isKnown($to)) {
            throw new InvalidArgumentException("Unknown review request status '{$to}'.");
        }
        if (!ReviewStage::canTransition($request->status, $to)) {
            throw new InvalidArgumentException("Cannot move a review request from '{$request->status}' to '{$to}'.");
        }
        if ($to === ReviewStage::DELIVERED) {
            if ($report === null || $report->requestId !== $request->id) {
                throw new InvalidArgumentException('A request can only be marked delivered with its saved report.');
            }
            if (!$request->contact->consentToContact) {
                throw new InvalidArgumentException('A report cannot be delivered to a contact who did not consent.');
            }
        }
        return $request->withStatus($to);
    }

    private function sort(): void
    {
        uasort(
            $this->requests,
            static fn (ReviewRequest $a, ReviewRequest $b): int =>
                [$a->submittedAt, $a->id] <=> [$b->submittedAt, $b->id]
        );
    }
}
