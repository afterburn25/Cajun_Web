<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One registered 20i read operation's evidence record. Evidence levels
 * are deliberately separate: documentation says an endpoint exists,
 * an authenticated read proves the account can retrieve it, and nothing
 * here asserts a response schema the sources do not show.
 */
final readonly class InventoryCapability
{
    /** Owner-uploaded documentation or public support articles mention the endpoint. */
    public const EVIDENCE_DOCUMENTED = 'documented';
    /** An authenticated account read succeeded for this exact operation (issue #65: GET /package). */
    public const EVIDENCE_AUTHENTICATED_READ = 'authenticated-read';

    public const RISK_READ = 'read';
    /** Reads that can return credential-bearing or sensitive sections; projectors must allowlist. */
    public const RISK_SENSITIVE_READ = 'sensitive-read';

    private const EVIDENCE = [self::EVIDENCE_DOCUMENTED, self::EVIDENCE_AUTHENTICATED_READ];
    private const RISKS = [self::RISK_READ, self::RISK_SENSITIVE_READ];

    /**
     * @param string $pathTemplate Exact documented path; {id} placeholders name allocated-id slots.
     * @param bool   $schemaKnown  True only when a primary source shows the response shape.
     */
    public function __construct(
        public string $operation,
        public string $method,
        public string $pathTemplate,
        public string $risk,
        public string $evidence,
        public bool $schemaKnown,
        public string $note = '',
    ) {
        if (trim($operation) === '' || $method !== 'GET' || !str_starts_with($pathTemplate, '/')) {
            throw new InvalidArgumentException('Inventory capabilities are read-only documented GET paths.');
        }
        if (!in_array($risk, self::RISKS, true) || !in_array($evidence, self::EVIDENCE, true)) {
            throw new InvalidArgumentException('Unknown inventory capability risk or evidence level.');
        }
    }
}
