<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ReviewRequests;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One visitor's request to have their existing website reviewed. Immutable:
 * stage moves produce a new instance through the pipeline. `submittedAt` is
 * a unix timestamp supplied by the adapter; the component never invents one.
 */
final readonly class ReviewRequest
{
    public function __construct(
        public string $id,
        public WebsiteAddress $website,
        public ReviewContact $contact,
        public string $goals,
        public string $status = ReviewStage::RECEIVED,
        public int $submittedAt = 0,
        public ?string $notes = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A review request needs an id.');
        }
        if (trim($goals) === '') {
            throw new InvalidArgumentException('A review request needs the visitor\'s business goals.');
        }
        if (!ReviewStage::isKnown($status)) {
            throw new InvalidArgumentException("Unknown review request status '{$status}'.");
        }
        if ($submittedAt < 0) {
            throw new InvalidArgumentException('A review request timestamp cannot be negative.');
        }
        if ($notes !== null && trim($notes) === '') {
            throw new InvalidArgumentException('Request notes must be non-empty or null.');
        }
    }

    public function withStatus(string $status): self
    {
        return new self($this->id, $this->website, $this->contact, $this->goals, $status, $this->submittedAt, $this->notes);
    }
}
