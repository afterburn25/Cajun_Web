<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Access;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Opaque random hex tokens, compatible with the inspected 1.1.0 auth_version. */
final class AuthorizationVersion
{
    public static function valid(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $value) === 1;
    }

    public static function matches(
        #[\SensitiveParameter] string $current,
        #[\SensitiveParameter] string $presented,
    ): bool {
        return self::valid($current) && hash_equals($current, $presented);
    }
}
