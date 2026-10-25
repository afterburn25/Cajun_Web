<?php
/**
 * Pure issue/validation policy for CajunWebPro software license entitlements.
 *
 * This does not create licenses, charge customers, deliver ZIPs, or change
 * existing v3 license storage. Persisted grants and the activation/validation
 * API must adopt this policy through an independently reviewed migration.
 */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

final class SoftwareLicensePolicy {
    public const STANDARD = 'standard';
    public const MASTER = 'master';
    public const TEMPORARY = 'temporary';
    public const TRIAL = 'trial';

    public static function type(string $type): string {
        if (!in_array($type, [self::STANDARD, self::MASTER, self::TEMPORARY, self::TRIAL], true)) {
            throw new InvalidArgumentException('Unknown license type.');
        }
        return $type;
    }

    /**
     * Match the existing domain model: www and apex represent one activation.
     * Internationalized names must arrive in their ASCII/Punycode form.
     */
    public static function domain(string $value): string {
        $domain = strtolower(trim($value));
        if (str_starts_with($domain, 'www.')) $domain = substr($domain, 4);
        if (strlen($domain) > 253 || strlen($domain) < 4 || str_contains($domain, '..')) {
            throw new InvalidArgumentException('Invalid installation domain.');
        }
        $labels = explode('.', $domain);
        if (count($labels) < 2) throw new InvalidArgumentException('Invalid installation domain.');
        foreach ($labels as $label) {
            if (strlen($label) < 1 || strlen($label) > 63 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $label)) {
                throw new InvalidArgumentException('Invalid installation domain.');
            }
        }
        if (!preg_match('/^[a-z][a-z0-9-]{1,62}$/D', end($labels))) {
            throw new InvalidArgumentException('Invalid installation domain.');
        }
        return $domain;
    }

    public static function expiry(?string $value): ?DateTimeImmutable {
        if ($value === null) return null;
        $zone = new DateTimeZone('UTC');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException('Invalid UTC license expiry.');
        }
        return $date;
    }

    /**
     * Issue-time metadata contract. This is deliberately independent from
     * existing license_create(): grants must not be issued until their owner-
     * gated storage and entitlement validation are deployed together.
     *
     * Master licenses are staff-only, never exposed in customer bundles.
     * Trial/temporary grants require a finite, future UTC expiry.
     * Standard purchased licenses remain perpetual unless policy changes.
     */
    public static function issue(
        string $kind,
        string $licensee,
        ?string $installationDomain,
        ?string $expiresAt,
        bool $isStaff,
        DateTimeImmutable $now
    ): array {
        self::type($kind);
        $name = trim($licensee);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 190) {
            throw new InvalidArgumentException('Licensee name is required (190 characters maximum).');
        }
        if ($kind === self::MASTER) {
            if (!$isStaff) throw new InvalidArgumentException('Only authorized staff may issue master licenses.');
            if ($installationDomain !== null || $expiresAt !== null) {
                throw new InvalidArgumentException('Master grants cannot be domain-bound or time-limited through customer issuance.');
            }
            return ['type' => $kind, 'licensee' => $name, 'domain' => null, 'expires_at' => null, 'internal_only' => true];
        }
        if ($installationDomain === null) throw new InvalidArgumentException('Installation domain is required.');
        $domain = self::domain($installationDomain);
        $expires = self::expiry($expiresAt);
        if ($kind === self::STANDARD && $expires !== null) {
            throw new InvalidArgumentException('Use temporary or trial for time-limited grants.');
        }
        if ($kind !== self::STANDARD && ($expires === null || $expires <= $now)) {
            throw new InvalidArgumentException('Trial and temporary licenses require a future expiration.');
        }
        return ['type' => $kind, 'licensee' => $name, 'domain' => $domain,
            'expires_at' => $expires?->format('Y-m-d H:i:s'), 'internal_only' => false];
    }

    /**
     * Fail-closed verdict for a fully loaded persisted entitlement. Storage
     * unavailable/invalid must instead produce an inconclusive non-200 API
     * response; callers must not convert that condition into this denial.
     */
    public static function authorized(
        array $grant,
        string $installationDomain,
        DateTimeImmutable $now
    ): bool {
        $kind = self::type((string)($grant['type'] ?? ''));
        if (($grant['active'] ?? false) !== true) return false;
        $expiry = self::expiry($grant['expires_at'] ?? null);
        if ($kind === self::MASTER) {
            if (($grant['internal_only'] ?? false) !== true) return false;
            return $expiry === null;
        }
        if (($grant['internal_only'] ?? false) === true) return false;
        if (in_array($kind, [self::TRIAL, self::TEMPORARY], true) && $expiry === null) return false;
        if ($expiry !== null && $now >= $expiry) return false;
        $bound = (string)($grant['domain'] ?? '');
        return self::domain($bound) === self::domain($installationDomain);
    }
}
