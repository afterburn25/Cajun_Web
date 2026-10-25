<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Guard for subscription plans in the shared customer portal. */
function software_subscription_term(string $term): string {
    if (!in_array($term, ['month', 'year'], true)) {
        throw new InvalidArgumentException('Subscription term must be monthly or yearly.');
    }
    return $term;
}
