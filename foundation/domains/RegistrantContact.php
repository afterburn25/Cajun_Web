<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Domains;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Registrant details for a domain order. `privacyRequested` records the
 * registrant's WHOIS-privacy preference — whether it can be honored
 * depends on the TLD offering's `privacySupported`, enforced by
 * pricing, never assumed.
 */
final readonly class RegistrantContact
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $address1,
        public string $city,
        public string $region,
        public string $postalCode,
        public string $country,
        public ?string $organization = null,
        public bool $privacyRequested = false,
    ) {
        foreach (['name', 'email', 'phone', 'address1', 'city', 'region', 'postalCode', 'country'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('Registrant ' . $field . ' is required.');
            }
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Registrant email is invalid.');
        }
        if (!preg_match('/^[A-Z]{2}$/i', $country)) {
            throw new InvalidArgumentException('Registrant country must be an ISO 3166-1 alpha-2 code.');
        }
    }
}
