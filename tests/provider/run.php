<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\ConnectionCheck;
use CajunWebPro\Foundation\Provider\ExternalResource;
use CajunWebPro\Foundation\Provider\HttpTransport;
use CajunWebPro\Foundation\Provider\OperationLedger;
use CajunWebPro\Foundation\Provider\OperationResult;
use CajunWebPro\Foundation\Provider\ProviderAdapter;
use CajunWebPro\Foundation\Provider\ProviderCapability;
use CajunWebPro\Foundation\Provider\ProviderCredential;
use CajunWebPro\Foundation\Provider\ProviderGateway;
use CajunWebPro\Foundation\Provider\ProviderOperation;
use CajunWebPro\Foundation\Provider\RetryPolicy;
use CajunWebPro\Foundation\Provider\TransportException;
use CajunWebPro\Foundation\Provider\TransportRequest;
use CajunWebPro\Foundation\Provider\TransportResponse;

require __DIR__ . '/../../foundation/provider/ProviderCapability.php';
require __DIR__ . '/../../foundation/provider/ProviderCredential.php';
require __DIR__ . '/../../foundation/provider/TransportRequest.php';
require __DIR__ . '/../../foundation/provider/TransportResponse.php';
require __DIR__ . '/../../foundation/provider/TransportException.php';
require __DIR__ . '/../../foundation/provider/HttpTransport.php';
require __DIR__ . '/../../foundation/provider/ProviderOperation.php';
require __DIR__ . '/../../foundation/provider/OperationResult.php';
require __DIR__ . '/../../foundation/provider/ConnectionCheck.php';
require __DIR__ . '/../../foundation/provider/RetryPolicy.php';
require __DIR__ . '/../../foundation/provider/OperationLedger.php';
require __DIR__ . '/../../foundation/provider/ProviderAdapter.php';
require __DIR__ . '/../../foundation/provider/ProviderGateway.php';
require __DIR__ . '/../../foundation/provider/ExternalResource.php';

// ---------------------------------------------------------------------------
// FIXTURE doubles — conspicuously synthetic. None of these responses come
// from, or claim to describe, the real 20i API.
// ---------------------------------------------------------------------------

/** Scripted transport: returns queued responses, records every request. */
final class FixtureTransport implements HttpTransport
{
    /** @var array<int,TransportResponse|TransportException> */
    public array $script = [];
    /** @var TransportRequest[] */
    public array $requests = [];

    public function send(TransportRequest $request): TransportResponse
    {
        $this->requests[] = $request;
        $next = array_shift($this->script);
        if ($next instanceof TransportException) {
            throw $next;
        }
        if (!$next instanceof TransportResponse) {
            throw new TransportException('fixture: script exhausted');
        }
        return $next;
    }
}

/** In-memory ledger: first record for a key wins. */
final class MemoryLedger implements OperationLedger
{
    /** @var array<string,OperationResult> */
    public array $results = [];

    public function findByKey(string $idempotencyKey): ?OperationResult
    {
        return $this->results[$idempotencyKey] ?? null;
    }

    public function record(string $idempotencyKey, OperationResult $result): void
    {
        $this->results[$idempotencyKey] ??= $result;
    }
}

/**
 * FIXTURE adapter standing in for a 20i reseller adapter. Declares one
 * supported read, one supported write, one unverified and one
 * unsupported operation; authenticates only the fixture key 'fx-key'.
 */
final class FixtureTwentyIAdapter implements ProviderAdapter
{
    public function id(): string
    {
        return 'fx-provider';
    }

    public function label(): string
    {
        return 'Fixture provider (not 20i)';
    }

    public function capabilities(): array
    {
        return [
            ProviderCapability::supported('packages.list', 'fixture read'),
            ProviderCapability::supported('hosting.create', 'fixture write'),
            ProviderCapability::unverified('domains.register', 'endpoint not inspected'),
            ProviderCapability::unsupported('vps.create', 'not offered'),
        ];
    }

    public function supports(string $operation): bool
    {
        foreach ($this->capabilities() as $cap) {
            if ($cap->operation === $operation) {
                return $cap->state === ProviderCapability::SUPPORTED;
            }
        }
        return false;
    }

    public function probe(HttpTransport $transport, ProviderCredential $credential): ConnectionCheck
    {
        $response = $transport->send(new TransportRequest('GET', 'https://fixture.invalid/api/package', ['Authorization' => 'Bearer ' . $credential->secret()]));
        return match (true) {
            $response->ok() => ConnectionCheck::verified('fixture credentials accepted'),
            $response->status === 401 || $response->status === 403 => ConnectionCheck::authenticationFailed('fixture rejected credentials'),
            default => ConnectionCheck::unsupported('fixture status ' . $response->status),
        };
    }

    public function execute(HttpTransport $transport, ProviderCredential $credential, ProviderOperation $operation): OperationResult
    {
        $response = $transport->send(new TransportRequest('POST', 'https://fixture.invalid/api/' . str_replace('.', '/', $operation->name), ['Authorization' => 'Bearer ' . $credential->secret()], json_encode($operation->params)));
        if ($response->ok()) {
            $data = json_decode($response->body, true);
            return OperationResult::succeeded($operation->name, 'fixture ok', is_array($data) ? ($data['id'] ?? null) : null);
        }
        if ($response->retryable()) {
            return OperationResult::retryable($operation->name, 'fixture status ' . $response->status, (string) $response->status);
        }
        return OperationResult::failed($operation->name, 'fixture status ' . $response->status, (string) $response->status);
    }
}

// ---------------------------------------------------------------------------

$checks = 0;
$failures = [];

function check(bool $cond, string $label): void
{
    global $checks, $failures;
    $checks++;
    if (!$cond) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: $label\n");
    }
}

function expectThrows(callable $fn, string $label): void
{
    try {
        $fn();
        check(false, $label . ' (no exception)');
    } catch (InvalidArgumentException) {
        check(true, $label);
    }
}

function credential(string $secret = 'fx-key-abc123'): ProviderCredential
{
    return new ProviderCredential('fx-provider', 'fixture-account-7', fn () => $secret, 'us-east');
}

$adapter = new FixtureTwentyIAdapter();

// --- ProviderCapability -----------------------------------------------------
expectThrows(fn () => new ProviderCapability('', 'supported'), 'capability needs an operation name');
expectThrows(fn () => new ProviderCapability('x', 'bogus'), 'capability rejects unknown state');
check(ProviderCapability::unverified('domains.register')->state === 'unverified', 'unverified capability state');

// --- ProviderCredential -----------------------------------------------------
expectThrows(fn () => new ProviderCredential('', 'acct', fn () => 'x'), 'credential needs provider id');
expectThrows(fn () => new ProviderCredential('p', '', fn () => 'x'), 'credential needs account identity');
$c = credential();
check($c->hasSecret(), 'credential reports configured secret');
check($c->secret() === 'fx-key-abc123', 'resolver returns raw secret');
check($c->masked() === '********c123', 'masked shows only last four');
check(strpos($c->masked(), 'fx-key') === false, 'masked never leaks the secret');
check(credential('')->masked() === 'not configured', 'empty secret masks as not configured');
check(credential('ab')->masked() === '********ab', 'short secrets still masked, never full');

// --- TransportRequest / TransportResponse ------------------------------------
expectThrows(fn () => new TransportRequest('WAT', 'https://x.test/'), 'request rejects unknown method');
expectThrows(fn () => new TransportRequest('get', 'http://x.test/'), 'request requires https');
expectThrows(fn () => new TransportRequest('GET', 'https://user:pw@x.test/'), 'request rejects embedded credentials');
expectThrows(fn () => new TransportRequest('GET', 'https://x.test/', [], null, 0), 'request rejects non-positive timeout');
expectThrows(fn () => new TransportRequest('GET', 'https://x.test/', [], null, 999), 'request rejects excessive timeout');
$req = new TransportRequest('get', 'https://api.test/path');
check($req->method === 'GET', 'method normalized to uppercase');
check((new TransportResponse(200, [], '{}'))->ok(), '2xx response is ok');
check((new TransportResponse(503, [], ''))->retryable(), '5xx response is retryable');
check((new TransportResponse(429, [], ''))->retryable(), '429 response is retryable');
check(!(new TransportResponse(404, [], ''))->retryable(), '4xx response is not retryable');
expectThrows(fn () => new TransportResponse(99, [], ''), 'response rejects invalid status');

// --- ProviderOperation -------------------------------------------------------
check((new ProviderOperation('packages.list', 'key-1'))->name === 'packages.list', 'dotted operation name accepted');
expectThrows(fn () => new ProviderOperation('List Packages!', 'k'), 'operation name must be dotted lowercase');
expectThrows(fn () => new ProviderOperation('x.y', ''), 'operation needs an idempotency key');
expectThrows(fn () => new ProviderOperation('x.y', str_repeat('k', 200)), 'idempotency key length bounded');
expectThrows(fn () => new ProviderOperation('x.y', "bad\nkey"), 'idempotency key rejects control chars');

// --- RetryPolicy -------------------------------------------------------------
expectThrows(fn () => new RetryPolicy(0), 'retry attempts lower bound');
expectThrows(fn () => new RetryPolicy(9), 'retry attempts upper bound');
expectThrows(fn () => new RetryPolicy(3, -1), 'backoff cannot be negative');

// --- Gateway: support matrix -------------------------------------------------
$transport = new FixtureTransport();
$ledger = new MemoryLedger();
$gateway = new ProviderGateway($adapter, $transport, $ledger, new RetryPolicy(3, 0));

$result = $gateway->perform(credential(), new ProviderOperation('domains.register', 'k-unverified'));
check($result->status === OperationResult::UNSUPPORTED, 'unverified operation refused by gateway');
check($transport->requests === [], 'unverified operation never reaches transport');
check($result->attempts === 0, 'unverified operation performs zero attempts');

$result = $gateway->perform(credential(), new ProviderOperation('vps.create', 'k-unsupported'));
check($result->status === OperationResult::UNSUPPORTED, 'unsupported operation refused');
check($transport->requests === [], 'unsupported operation never reaches transport');

$result = $gateway->perform(credential(), new ProviderOperation('unknown.op', 'k-unknown'));
check($result->status === OperationResult::UNSUPPORTED, 'undeclared operation refused');
check($transport->requests === [], 'undeclared operation never reaches transport');
check($ledger->results === [], 'refused operations are not recorded');

// --- Gateway: success + replay -----------------------------------------------
$transport->script = [new TransportResponse(200, [], '{"id":"pkg_9"}')];
$op = new ProviderOperation('hosting.create', 'idem-1', ['domain' => 'fixture.test']);
$r1 = $gateway->perform(credential(), $op);
check($r1->status === OperationResult::SUCCEEDED, 'supported operation succeeds');
check($r1->externalId === 'pkg_9', 'external provider id captured');
check($r1->attempts === 1 && !$r1->replayed, 'single attempt, not a replay');
check(count($transport->requests) === 1, 'one transport call for fresh operation');
check($transport->requests[0]->url === 'https://fixture.invalid/api/hosting/create', 'adapter maps operation to endpoint');

$r2 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-1'));
check($r2->replayed, 'same idempotency key replays recorded result');
check($r2->externalId === 'pkg_9', 'replay preserves recorded external id');
check(count($transport->requests) === 1, 'replay performs no transport call');

// --- Gateway: retry classification -------------------------------------------
$transport->script = [
    new TransportResponse(503, [], ''),
    new TransportResponse(200, [], '{"id":"pkg_10"}'),
];
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-2'));
check($r->status === OperationResult::SUCCEEDED && $r->attempts === 2, 'retryable response retried to success');
check(count($transport->requests) === 3, 'retry consumed two transport calls');

$transport->script = [new TransportResponse(503, [], ''), new TransportResponse(503, [], '')];
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-3'), );
check($r->status === OperationResult::RETRYABLE && $r->attempts === 3, 'exhausted retries recorded as retryable');
check($ledger->findByKey('idem-3')?->status === OperationResult::RETRYABLE, 'exhausted result recorded for later replay');
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-3'));
check($r->replayed && count($transport->requests) === 6, 'exhausted operation replays without re-executing');

$transport->script = [new TransportException('fixture: DNS failure')];
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-4'));
check($r->status === OperationResult::RETRYABLE && $r->attempts === 3, 'connection failure classified retryable');

$transport->script = [new TransportResponse(400, [], '{"error":"bad domain"}')];
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-5'));
check($r->status === OperationResult::FAILED && $r->attempts === 1, '4xx provider error fails without retry');
check($r->isFinal(), 'failed result is final');

// --- Gateway: connection check -----------------------------------------------
$transport->script = [new TransportResponse(200, [], '{}')];
check($gateway->checkConnection(credential())->ok(), 'probe verifies good credentials');
check(str_starts_with($transport->requests[count($transport->requests) - 1]->headers['Authorization'], 'Bearer '), 'probe sends credential to provider only');

$transport->script = [new TransportResponse(403, [], '')];
$check = $gateway->checkConnection(credential('wrong'));
check($check->state === ConnectionCheck::AUTHENTICATION_FAILED, 'probe classifies auth rejection');
check($check->checkedAt !== '', 'check carries a timestamp');

$transport->script = [new TransportException('fixture: connect timeout')];
check($gateway->checkConnection(credential())->state === ConnectionCheck::UNREACHABLE, 'probe classifies transport failure as unreachable');

// --- ExternalResource ---------------------------------------------------------
$link = ExternalResource::link('hosting_account', '42', 'pkg_9');
check($link->state === ExternalResource::LINKED && !$link->verified(), 'linked record is a claim, not verified');
check($link->verify('pkg_9')->verified(), 'matching provider id verifies the link');
$mismatch = $link->verify('pkg_other');
check($mismatch->state === ExternalResource::MISMATCH, 'conflicting provider id records mismatch');
check($mismatch->providerId === 'pkg_other', 'mismatch records what provider actually reported');
expectThrows(fn () => ExternalResource::link('hosting_account', '42', ''), 'link needs a provider id');
expectThrows(fn () => ExternalResource::link('', '42', 'pkg'), 'link needs a local type');

// --- Adapter contract ---------------------------------------------------------
check($adapter->supports('packages.list'), 'supported capability is performable');
check(!$adapter->supports('domains.register'), 'unverified capability is not performable');
check(!$adapter->supports('vps.create'), 'unsupported capability is not performable');
check(!$adapter->supports('never.heard.of'), 'undeclared operation is not performable');

// ---------------------------------------------------------------------------
if ($failures) {
    fwrite(STDERR, count($failures) . " of $checks checks failed\n");
    exit(1);
}
echo "$checks provider checks passed\n";
