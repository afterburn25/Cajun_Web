<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ReviewRequests;

use InvalidArgumentException;

/**
 * The saved human review for one request. A report must contain at least one
 * finding and name its reviewer; `deliveredAt` records actual delivery and
 * stays null until an adapter confirms it. Nothing here sends anything.
 */
final readonly class ReviewReport
{
    /** @var list<ReviewFinding> */
    public array $findings;

    /**
     * @param list<ReviewFinding> $findings At least one finding is required.
     * @param string $preparedBy Human reviewer label; measured findings still need a human reviewer.
     */
    public function __construct(
        public string $requestId,
        public string $preparedBy,
        public int $preparedAt,
        array $findings,
        public ?int $deliveredAt = null,
    ) {
        if (trim($requestId) === '') {
            throw new InvalidArgumentException('A review report needs the request id it belongs to.');
        }
        if (trim($preparedBy) === '') {
            throw new InvalidArgumentException('A review report needs a named human reviewer.');
        }
        if ($preparedAt < 0) {
            throw new InvalidArgumentException('A report timestamp cannot be negative.');
        }
        if ($findings === []) {
            throw new InvalidArgumentException('A review report needs at least one finding.');
        }
        foreach ($findings as $finding) {
            if (!$finding instanceof ReviewFinding) {
                throw new InvalidArgumentException('A review report only accepts ReviewFinding entries.');
            }
        }
        if ($deliveredAt !== null && ($deliveredAt < 0 || $deliveredAt < $preparedAt)) {
            throw new InvalidArgumentException('Delivery cannot predate the report itself.');
        }
        $this->findings = array_values($findings);
    }

    public function withDelivery(int $deliveredAt): self
    {
        return new self($this->requestId, $this->preparedBy, $this->preparedAt, $this->findings, $deliveredAt);
    }
}
