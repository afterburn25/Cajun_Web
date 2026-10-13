<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ReviewRequests;

use InvalidArgumentException;

/**
 * Who asked for the review and whether they agreed to be contacted about it.
 * Consent is explicit: a request without it can exist as a draft in an
 * adapter, but the pipeline refuses to mark any report delivered.
 */
final readonly class ReviewContact
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public bool $consentToContact = false,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A review request needs a contact name.');
        }
        if (trim($email) === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A review request needs a valid contact email address.');
        }
        if ($phone !== null && trim($phone) === '') {
            throw new InvalidArgumentException('A contact phone number must be non-empty or null.');
        }
    }
}
