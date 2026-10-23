<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Typed metadata for one provider endpoint/operation as established by
 * external evidence — documentation, an authorized live observation,
 * or completed verification. This is a provenance record, never an
 * execution descriptor: it carries no transport, no credential and no
 * runnable request.
 *
 * Evidence grades, weakest to strongest:
 * - DOCUMENTED:    appears in supplied or public documentation only.
 *                  Says nothing about this account's entitlements.
 * - AUTHENTICATED: an authorized live call to this exact endpoint once
 *                  returned a real response (e.g. the issue #65
 *                  GET /package diagnostic). The general response
 *                  schema and account entitlements remain unverified.
 * - VERIFIED:      response shape and account entitlement verified for
 *                  the intended use. Promotion to this state requires a
 *                  separately recorded authorized verification.
 * - UNSUPPORTED:   documented as absent, or explicitly refused.
 *
 * Risk classes describe the documented semantic effect — the HTTP
 * method alone never proves a read (the owner reference documents
 * GET-labelled mutations):
 * - READ:        no documented remote side effect.
 * - WRITE:       changes remote state without destroying data.
 * - DESTRUCTIVE: deletes data, wipes a disk, or otherwise destroys
 *                resources (irreversible or near-irreversible).
 * - CREDENTIAL:  a documented read that can return or rotate secrets
 *                (auth codes, DKIM private material, FTP/VNC details).
 *                Excluded from ordinary inventory polling.
 */
final readonly class CatalogEntry
{
    public const EVIDENCE_DOCUMENTED = 'documented';
    public const EVIDENCE_AUTHENTICATED = 'authenticated';
    public const EVIDENCE_VERIFIED = 'verified';
    public const EVIDENCE_UNSUPPORTED = 'unsupported';

    private const EVIDENCE = [
        self::EVIDENCE_DOCUMENTED,
        self::EVIDENCE_AUTHENTICATED,
        self::EVIDENCE_VERIFIED,
        self::EVIDENCE_UNSUPPORTED,
    ];

    public const RISK_READ = 'read';
    public const RISK_WRITE = 'write';
    public const RISK_DESTRUCTIVE = 'destructive';
    public const RISK_CREDENTIAL = 'credential';

    private const RISKS = [
        self::RISK_READ,
        self::RISK_WRITE,
        self::RISK_DESTRUCTIVE,
        self::RISK_CREDENTIAL,
    ];

    private const MAX_NOTE = 400;

    public function __construct(
        public string $operation,
        public string $method,
        public string $pathTemplate,
        public string $evidence,
        public string $risk,
        public string $source,
        public string $note = '',
    ) {
        // Catalog names are documentation keys — dotted camelCase is
        // allowed so entries can mirror provider spellings (e.g.
        // 'packages.bundleTypeLimits'). They are not automatically
        // ProviderOperation names: the stricter lowercase grammar there
        // is applied when an operation is actually declared.
        if (!preg_match('/^[a-z][a-zA-Z0-9]*(\.[a-z][a-zA-Z0-9]*)*$/', $operation)) {
            throw new InvalidArgumentException('Catalog operations use dotted camel-lower form.');
        }
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'], true)) {
            throw new InvalidArgumentException('Catalog method must be a literal HTTP method.');
        }
        if (!str_starts_with($pathTemplate, '/') || preg_match('/[\x00-\x20\x7f]/', $pathTemplate)
            || strlen($pathTemplate) > 200) {
            throw new InvalidArgumentException('Catalog paths are bounded printable templates starting with /.');
        }
        if (!in_array($evidence, self::EVIDENCE, true)) {
            throw new InvalidArgumentException('Unknown evidence grade: ' . $evidence);
        }
        if (!in_array($risk, self::RISKS, true)) {
            throw new InvalidArgumentException('Unknown risk class: ' . $risk);
        }
        if (trim($source) === '' || strlen($source) > 190) {
            throw new InvalidArgumentException('Every catalog entry needs bounded provenance.');
        }
        if (strlen($note) > self::MAX_NOTE
            || preg_match('//u', $note) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $note)
        ) {
            throw new InvalidArgumentException('Catalog notes are bounded printable classifications.');
        }
    }

    /** The documented verb is not the semantic effect (GET-labelled writes exist). */
    public function semanticallyReadOnly(): bool
    {
        return $this->risk === self::RISK_READ || $this->risk === self::RISK_CREDENTIAL;
    }
}
