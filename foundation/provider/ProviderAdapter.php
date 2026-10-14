<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Contract between the application and one hosting/billing provider
 * (e.g. 20i). Adapters declare a capability matrix up front — callers
 * ask `supports($operation)` before performing — and translate provider
 * responses into OperationResult / ConnectionCheck. Adapters never
 * fabricate capabilities: anything not verified against the real
 * account is UNVERIFIED, and the gateway refuses to execute it.
 */
interface ProviderAdapter
{
    /** Machine identifier, e.g. 'twenty-i'. */
    public function id(): string;

    /** Human display name, e.g. '20i Reseller'. */
    public function label(): string;

    /**
     * The declared support matrix: every operation this adapter knows
     * about with its current state. Missing operations are treated as
     * unsupported.
     *
     * @return ProviderCapability[]
     */
    public function capabilities(): array;

    /** True only for capabilities in the SUPPORTED state. */
    public function supports(string $operation): bool;

    /**
     * Authenticate against the provider's cheapest read endpoint and
     * classify the outcome. Must not perform any mutating operation.
     *
     * The adapter pins the exact endpoint it probes — fixed host,
     * method and path; callers cannot redirect it elsewhere. VERIFIED
     * is allowed only when the response is the endpoint's strictly
     * accepted success shape (an otherwise-unparseable or differently
     * shaped 2xx is UNKNOWN, never a pass). Shape checks must preserve
     * JSON object/list identity — associative decoding that would
     * accept an object like {} or {"0":"x"} as a list must not be
     * used. Transport failures thrown by send() are classified by the
     * gateway — probe must not convert them into authentication
     * verdicts.
     */
    public function probe(HttpTransport $transport, ProviderCredential $credential): ConnectionCheck;

    /**
     * Execute one operation. Only called when supports($operation->name)
     * is true. Must return a result (never throw for provider-side
     * failures); throw only TransportException for connection-level
     * failures — the gateway classifies those as retryable.
     */
    public function execute(HttpTransport $transport, ProviderCredential $credential, ProviderOperation $operation): OperationResult;
}
