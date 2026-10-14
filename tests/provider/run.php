<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\ConnectionCheck;
use CajunWebPro\Foundation\Provider\ExternalResource;
use CajunWebPro\Foundation\Provider\HttpTransport;
use CajunWebPro\Foundation\Provider\LedgerClaim;
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
require __DIR__ . '/../../foundation/provider/LedgerClaim.php';
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
        if ($next instanceof \Throwable) {
            throw $next;
        }
        if (!$next instanceof TransportResponse) {
            throw new TransportException(TransportException::KIND_UNAVAILABLE);
        }
        return $next;
    }
}

/**
 * Transport that re-enters ProviderGateway::perform() with the same
 * idempotency key while the first request is mid-flight — a scripted
 * single-thread interleaving standing in for a concurrent duplicate.
 */
final class InterleavingTransport implements HttpTransport
{
    public int $calls = 0;
    public ?ProviderGateway $gateway = null;
    public ?ProviderCredential $credential = null;
    public ?ProviderOperation $innerOp = null;
    public ?OperationResult $inner = null;

    public function send(TransportRequest $request): TransportResponse
    {
        $this->calls++;
        if ($this->gateway !== null && $this->innerOp !== null) {
            $this->inner = $this->gateway->perform($this->credential, $this->innerOp);
            $this->innerOp = null; // interleave once
        }
        return new TransportResponse(200, [], '{"id":"pkg_x"}');
    }
}

/**
 * In-memory ledger implementing the atomic claim/record contract.
 * Single-process PHP cannot express a real concurrent race — the
 * interleaving tests below exercise the protocol deterministically;
 * durable implementations must back claim() with a unique-key INSERT.
 */
final class MemoryLedger implements OperationLedger
{
    /** @var array<string,OperationResult> */
    public array $results = [];

    /** @var array<string,string> key -> bound request fingerprint */
    public array $bound = [];

    /** @var array<string,int> key -> reservation epoch (open claims only) */
    public array $reservations = [];

    public function claim(string $idempotencyKey, string $fingerprint, int $now): LedgerClaim
    {
        if (isset($this->bound[$idempotencyKey]) && $this->bound[$idempotencyKey] !== $fingerprint) {
            return LedgerClaim::conflict();
        }
        if (isset($this->results[$idempotencyKey])) {
            return LedgerClaim::replay($this->results[$idempotencyKey]);
        }
        if (isset($this->reservations[$idempotencyKey])) {
            return $now - $this->reservations[$idempotencyKey] >= OperationLedger::RESERVATION_TTL
                ? LedgerClaim::uncertain()
                : LedgerClaim::inProgress();
        }
        $this->bound[$idempotencyKey] = $fingerprint;
        $this->reservations[$idempotencyKey] = $now;
        return LedgerClaim::claimed();
    }

    public function record(string $idempotencyKey, string $fingerprint, OperationResult $result, int $now): void
    {
        if (isset($this->results[$idempotencyKey])) {
            return; // first terminal result wins
        }
        if (isset($this->bound[$idempotencyKey]) && $this->bound[$idempotencyKey] !== $fingerprint) {
            throw new LogicException('Cannot record under a reservation owned by a different request.');
        }
        unset($this->reservations[$idempotencyKey]);
        $this->bound[$idempotencyKey] = $fingerprint;
        $this->results[$idempotencyKey] = $result;
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
            ProviderCapability::supported('packages.list', 'fixture read', true),
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
check(credential('a')->masked() === '********', 'one-character secret fully masked');
check(credential('ab')->masked() === '********', 'short secrets fully masked, never full');
check(credential('AB12')->masked() === '********', 'four-character secret fully masked');
check(credential('12345678')->masked() === '********', 'eight-character secret fully masked');
check(credential('123456789')->masked() === '********6789', 'nine-character secret shows last four only');

// --- TransportRequest / TransportResponse ------------------------------------
expectThrows(fn () => new TransportRequest('WAT', 'https://x.test/'), 'request rejects unknown method');
expectThrows(fn () => new TransportRequest('get', 'http://x.test/'), 'request requires https');
expectThrows(fn () => new TransportRequest('GET', 'https://user:pw@x.test/'), 'request rejects embedded credentials');
expectThrows(fn () => new TransportRequest('GET', 'https://x.test/', [], null, 0), 'request rejects non-positive timeout');
expectThrows(fn () => new TransportRequest('GET', 'https://x.test/', [], null, 999), 'request rejects excessive timeout');
expectThrows(fn () => new TransportRequest('GET', 'https://x.test/', [], null, NAN), 'request rejects NaN timeout');
expectThrows(fn () => new TransportRequest('GET', 'https://x.test/', [], null, INF), 'request rejects infinite timeout');
expectThrows(fn () => new TransportRequest('GET', 'https://x.test/', [], null, -INF), 'request rejects negative-infinite timeout');
check((new TransportRequest('GET', 'https://x.test/', [], null, 120.0))->timeoutSeconds === 120.0, 'finite boundary timeout accepted');
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
expectThrows(fn () => new RetryPolicy(3, NAN), 'retry backoff rejects NaN');
expectThrows(fn () => new RetryPolicy(3, INF), 'retry backoff rejects infinity');
expectThrows(fn () => new RetryPolicy(3, 0, NAN), 'retry timeout rejects NaN');
expectThrows(fn () => new RetryPolicy(3, 0, INF), 'retry timeout rejects infinity');
expectThrows(fn () => new RetryPolicy(3, 0, -INF), 'retry timeout rejects negative infinity');
check((new RetryPolicy(5, 10.0, 120.0))->timeoutSeconds === 120.0, 'finite boundary retry values accepted');

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

$r2 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-1', ['domain' => 'fixture.test']));
check($r2->replayed, 'same key + same request replays recorded result');
check($r2->externalId === 'pkg_9', 'replay preserves recorded external id');
check(count($transport->requests) === 1, 'replay performs no transport call');

// --- Gateway: request binding ------------------------------------------------
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-1', ['domain' => 'changed.test']));
check($r->status === OperationResult::CONFLICT, 'key reuse with different params rejected');
check(count($transport->requests) === 1, 'conflict performs no transport call');
$r = $gateway->perform(credential(), new ProviderOperation('packages.list', 'idem-1'));
check($r->status === OperationResult::CONFLICT, 'key reuse with different operation rejected');
$r = $gateway->perform(new ProviderCredential('fx-provider', 'fixture-account-9', fn () => 'fx-key-abc123'), new ProviderOperation('hosting.create', 'idem-1', ['domain' => 'fixture.test']));
check($r->status === OperationResult::CONFLICT, 'key reuse under a different account rejected');
check(count($transport->requests) === 1, 'conflicts never reach transport');

// Canonical params: same request in different key order replays.
$transport->script = [new TransportResponse(200, [], '{"id":"pkg_o"}')];
$r1 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-order', ['a' => 1, 'b' => ['x' => 1, 'y' => 2]]));
$r2 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-order', ['b' => ['y' => 2, 'x' => 1], 'a' => 1]));
check($r1->status === OperationResult::SUCCEEDED, 'ordered-params request executes');
check($r2->replayed, 'canonicalized params replay regardless of key order');
check(count($transport->requests) === 2, 'reordered identical request performs no extra call');

// --- Regression: canonicalization preserves map/list JSON type -------
// The reviewed defect: sorting numeric keys turned the map
// {"1":"B","0":"A"} into sequential keys, so it fingerprinted
// identically to the list ["A","B"] and replayed a different request's
// result. Map shape must be preserved.
$transport->script = [new TransportResponse(200, [], '{"id":"pkg_map"}')];
$before = count($transport->requests);
$r1 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-key', ['payload' => [1 => 'B', 0 => 'A']]));
check($r1->status === OperationResult::SUCCEEDED && $r1->externalId === 'pkg_map', 'numeric-key map request executes');
$r2 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-key', ['payload' => ['A', 'B']]));
check($r2->status === OperationResult::CONFLICT, 'JSON list with same values is a different request, not a replay');
check(count($transport->requests) === $before + 1, 'map/list distinction performs no second transport call');

// The same map in a different key order is the SAME request.
$transport->script = [new TransportResponse(200, [], '{"id":"pkg_map2"}')];
$before = count($transport->requests);
$r1 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-map', ['payload' => [2 => 'c', 1 => 'b']]));
$r2 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-map', ['payload' => [1 => 'b', 2 => 'c']]));
check($r1->status === OperationResult::SUCCEEDED, 'first numeric-map request executes');
check($r2->replayed && $r2->externalId === 'pkg_map2', 'reordered numeric-key map replays as the same request');
check(count($transport->requests) === $before + 1, 'reordered map performs no extra transport call');

// List order is semantically significant — it is never sorted away.
$transport->script = [new TransportResponse(200, [], '{"id":"pkg_l1"}'), new TransportResponse(200, [], '{"id":"pkg_l2"}')];
$before = count($transport->requests);
$r1 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-list', ['payload' => ['A', 'B']]));
check($r1->status === OperationResult::SUCCEEDED, 'ordered list request executes');
$r2 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-list', ['payload' => ['B', 'A']]));
check($r2->status === OperationResult::CONFLICT, 'reordered list is a different request, not a replay');
check(count($transport->requests) === $before + 1, 'list-order distinction performs no second transport call');

// Nested shape: a map inside a list inside a map keeps every level's type.
$transport->script = [new TransportResponse(200, [], '{"id":"pkg_n1"}')];
$before = count($transport->requests);
$nested = ['outer' => ['list' => [['k' => 1, 'j' => 2], 'x'], 'map' => [1 => 'z']]];
$nestedReordered = ['outer' => ['map' => [1 => 'z'], 'list' => [['j' => 2, 'k' => 1], 'x']]];
$nestedList = ['outer' => ['list' => [['k' => 1, 'j' => 2], 'x'], 'map' => ['z']]];
$r1 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-nested', $nested));
check($r1->status === OperationResult::SUCCEEDED, 'nested map/list request executes');
$r2 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-nested', $nestedReordered));
check($r2->replayed && $r2->externalId === 'pkg_n1', 'nested reordered maps replay as the same request');
$r3 = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-nested', $nestedList));
check($r3->status === OperationResult::CONFLICT, 'nested map-to-list change conflicts');
check(count($transport->requests) === $before + 1, 'nested checks performed exactly one transport call');

// Unsupported values cannot be smuggled into a fingerprint — the graph
// is restricted to JSON scalars, lists and maps, bounded in size/depth.
expectThrows(fn () => $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-obj', ['o' => new stdClass()])), 'object param rejected before claim');
$deep = 'x'; for ($i = 0; $i < 40; $i++) { $deep = [$deep]; }
expectThrows(fn () => $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-deep', ['d' => $deep])), 'over-deep param graph rejected');
expectThrows(fn () => $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-nan', ['n' => NAN])), 'non-finite param rejected');
$wide = []; for ($i = 0; $i < 2100; $i++) { $wide['k' . $i] = $i; }
expectThrows(fn () => $gateway->perform(credential(), new ProviderOperation('hosting.create', 'shape-wide', ['w' => $wide])), 'oversized param graph rejected');
check(!isset($ledger->results['shape-obj']) && !isset($ledger->results['shape-deep']) && !isset($ledger->results['shape-nan']) && !isset($ledger->results['shape-wide']), 'rejected param graphs left no ledger state');

// --- Gateway: retry classification -------------------------------------------
// Idempotent (read-only) operations retry within the bounded policy.
$transport->script = [
    new TransportResponse(503, [], ''),
    new TransportResponse(200, [], '{"id":"pkg_10"}'),
];
$r = $gateway->perform(credential(), new ProviderOperation('packages.list', 'idem-2'));
check($r->status === OperationResult::SUCCEEDED && $r->attempts === 2, 'idempotent retryable response retried to success');
check(count($transport->requests) === 8, 'retry consumed two transport calls');

$transport->script = [new TransportResponse(503, [], ''), new TransportResponse(503, [], '')];
$r = $gateway->perform(credential(), new ProviderOperation('packages.list', 'idem-3'));
check($r->status === OperationResult::RETRYABLE && $r->attempts === 3, 'exhausted idempotent retries recorded as retryable');
check($ledger->results['idem-3']->status === OperationResult::RETRYABLE, 'exhausted result recorded for later replay');
$r = $gateway->perform(credential(), new ProviderOperation('packages.list', 'idem-3'));
check($r->replayed && count($transport->requests) === 11, 'exhausted operation replays without re-executing');

$transport->script = [new TransportException(TransportException::KIND_CONNECT)];
$r = $gateway->perform(credential(), new ProviderOperation('packages.list', 'idem-4'));
check($r->status === OperationResult::RETRYABLE && $r->attempts === 3, 'idempotent connection failure classified retryable');

$transport->script = [new TransportResponse(400, [], '{"error":"bad domain"}')];
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'idem-5'));
check($r->status === OperationResult::FAILED && $r->attempts === 1, '4xx provider error fails without retry');
check($r->isFinal(), 'failed result is final');

// --- Regression: uncertain writes are never blindly retried --------------------
$transport->script = [new TransportResponse(503, [], '')];
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'w-1'));
check($r->status === OperationResult::UNCERTAIN && $r->attempts === 1, 'non-idempotent 5xx records uncertain after one attempt');
check($r->needsReconciliation() && $r->isFinal(), 'uncertain is a durable terminal verdict');
$before = count($transport->requests);
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'w-1'));
check($r->replayed && $r->status === OperationResult::UNCERTAIN, 'uncertain verdict replays');
check(count($transport->requests) === $before, 'uncertain replay never re-executes');

// Side effect then transport fault: exactly one remote call, recorded
// UNCERTAIN — the unguarded code executed this fixture twice.
$transport->script = [new TransportException(TransportException::KIND_TIMEOUT)];
$before = count($transport->requests);
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'w-2'));
check($r->status === OperationResult::UNCERTAIN && $r->attempts === 1, 'timeout after possible write records uncertain');
check(count($transport->requests) === $before + 1, 'uncertain write executed exactly once');
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'w-2'));
check($r->replayed && count($transport->requests) === $before + 1, 'uncertain write is never re-executed');

// --- Regression: deterministic interleaving -------------------------------------
$ilLedger = new MemoryLedger();
$ilTransport = new InterleavingTransport();
$ilGateway = new ProviderGateway($adapter, $ilTransport, $ilLedger, new RetryPolicy(1, 0));
$ilTransport->gateway = $ilGateway;
$ilTransport->credential = credential();
$ilTransport->innerOp = new ProviderOperation('hosting.create', 'race-1');
$outer = $ilGateway->perform(credential(), new ProviderOperation('hosting.create', 'race-1'));
check($ilTransport->inner?->status === OperationResult::IN_PROGRESS, 'interleaved duplicate sees in-progress reservation');
check($ilTransport->calls === 1, 'duplicate performs no second remote execution');
check($outer->status === OperationResult::SUCCEEDED, 'first execution completes normally');
$r = $ilGateway->perform(credential(), new ProviderOperation('hosting.create', 'race-1'));
check($r->replayed && $r->status === OperationResult::SUCCEEDED, 'completed key replays after interleave');
check($ilTransport->calls === 1, 'post-completion replay performs no execution');

// --- Regression: crash-after-write reconciles as uncertain ----------------------
$crashLedger = new MemoryLedger();
$crashTransport = new FixtureTransport();
$crashGateway = new ProviderGateway($adapter, $crashTransport, $crashLedger, new RetryPolicy(2, 0));
$crashTransport->script = [new RuntimeException('fixture crash after remote write')];
try {
    $crashGateway->perform(credential(), new ProviderOperation('hosting.create', 'crash-1'));
    check(false, 'mid-execution crash propagates');
} catch (RuntimeException) {
    check(true, 'mid-execution crash propagates');
}
check(isset($crashLedger->reservations['crash-1']), 'crash leaves an open reservation');
check(count($crashTransport->requests) === 1, 'crashed attempt executed once');
$crashLedger->reservations['crash-1'] = time() - OperationLedger::RESERVATION_TTL - 1;
$r = $crashGateway->perform(credential(), new ProviderOperation('hosting.create', 'crash-1'));
check($r->status === OperationResult::UNCERTAIN, 'stale reservation reconciles to uncertain');
check(count($crashTransport->requests) === 1, 'crashed operation is never re-executed');
$r = $crashGateway->perform(credential(), new ProviderOperation('hosting.create', 'crash-1', ['different' => true]));
check($r->status === OperationResult::CONFLICT, 'crashed key stays bound to its original request');

// --- Regression: failure text is a finite classification --------------------------
$transport->script = [new TransportException('https://fixture.invalid/private?token=SYNTHETIC_PRIVATE_XYZ')];
$r = $gateway->perform(credential(), new ProviderOperation('hosting.create', 'sec-1'));
check($r->status === OperationResult::UNCERTAIN, 'leaky transport text classified uncertain for a write');
check(strpos($r->message, 'SYNTHETIC_PRIVATE') === false, 'credential fragments never reach results');
check(strpos($r->message, 'fixture.invalid') === false, 'provider URLs never reach results');
check(strpos($ledger->results['sec-1']->message, 'SYNTHETIC_PRIVATE') === false, 'durable records carry no raw detail');
expectThrows(fn () => OperationResult::failed('x.y', str_repeat('x', 201)), 'result message length is bounded');
expectThrows(fn () => OperationResult::failed('x.y', "bad\nmessage"), 'result message rejects control characters');

// --- Regression: ledger record guards -------------------------------------------
$guardLedger = new MemoryLedger();
$guardLedger->claim('guard-1', 'fp-a', 100);
try {
    $guardLedger->record('guard-1', 'fp-b', OperationResult::succeeded('x.y'), 101);
    check(false, 'record under foreign fingerprint refused');
} catch (LogicException) {
    check(true, 'record under foreign fingerprint refused');
}
$guardLedger->record('guard-1', 'fp-a', OperationResult::succeeded('x.y'), 102);
$guardLedger->record('guard-1', 'fp-a', OperationResult::failed('x.y', 'second'), 103);
check($guardLedger->results['guard-1']->status === OperationResult::SUCCEEDED, 'first terminal record wins');

// --- Gateway: connection check -----------------------------------------------
$transport->script = [new TransportResponse(200, [], '{}')];
check($gateway->checkConnection(credential())->ok(), 'probe verifies good credentials');
check(str_starts_with($transport->requests[count($transport->requests) - 1]->headers['Authorization'], 'Bearer '), 'probe sends credential to provider only');

$transport->script = [new TransportResponse(403, [], '')];
$check = $gateway->checkConnection(credential('wrong'));
check($check->state === ConnectionCheck::AUTHENTICATION_FAILED, 'probe classifies auth rejection');
check($check->checkedAt !== '', 'check carries a timestamp');

$transport->script = [new TransportException('connect timeout to https://fixture.invalid?token=SYNTHETIC_PRIVATE')];
$probe = $gateway->checkConnection(credential());
check($probe->state === ConnectionCheck::UNREACHABLE, 'probe classifies transport failure as unreachable');
check(strpos($probe->detail, 'SYNTHETIC_PRIVATE') === false && strpos($probe->detail, 'fixture.invalid') === false, 'probe detail is a finite kind, not raw transport text');

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

// --- Regression: real cross-process reservation --------------------------------
// The interleaving above is scripted inside one thread. This section races
// real OS processes against the flock-backed FileLedger — the same atomicity
// contract a MySQL adapter implements with a unique key + INSERT.
require __DIR__ . '/FileLedger.php';
if (function_exists('proc_open')) {
    $worker = __DIR__ . '/file_ledger_worker.php';
    $spawn = function (array $args) use ($worker): string {
        $pipes = [];
        $proc = proc_open(array_merge([PHP_BINARY, $worker], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return 'spawn-failed';
        }
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        return trim((string) $out);
    };

    // Parallel race: exactly one of four processes may claim the key.
    $tmp = tempnam(sys_get_temp_dir(), 'cwp-ledger-');
    $procs = [];
    $pipeSets = [];
    for ($i = 0; $i < 4; $i++) {
        $pipes = [];
        $procs[] = proc_open([PHP_BINARY, $worker, $tmp, 'claim', 'conc-1', 'fp-x'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $pipeSets[] = $pipes;
    }
    $states = [];
    foreach ($procs as $i => $proc) {
        $states[] = trim((string) stream_get_contents($pipeSets[$i][1]));
        fclose($pipeSets[$i][1]);
        fclose($pipeSets[$i][2]);
        proc_close($proc);
    }
    check(count(array_filter($states, fn ($s) => $s === 'claimed')) === 1, 'parallel race: exactly one process claims the key');
    check(count(array_filter($states, fn ($s) => $s === 'in_progress')) === 3, 'parallel race: losers observe in-progress, never execute');

    // Abandoned claim -> uncertain; foreign fingerprint -> conflict.
    $tmp2 = tempnam(sys_get_temp_dir(), 'cwp-ledger-');
    $spawn([$tmp2, 'hold', 'conc-2', 'fp-y', '300']);
    check($spawn([$tmp2, 'claim', 'conc-2', 'fp-y']) === 'uncertain', 'cross-process stale claim reports uncertain');
    check($spawn([$tmp2, 'claim', 'conc-2', 'fp-z']) === 'conflict', 'cross-process key reuse conflicts');

    // Terminal record -> replay for the matching request.
    $tmp3 = tempnam(sys_get_temp_dir(), 'cwp-ledger-');
    check($spawn([$tmp3, 'finish', 'conc-3', 'fp-w']) === 'claimed', 'cross-process claim-then-record succeeds');
    check($spawn([$tmp3, 'claim', 'conc-3', 'fp-w']) === 'replay', 'cross-process terminal result replays');

    @unlink($tmp);
    @unlink($tmp2);
    @unlink($tmp3);
} else {
    check(false, 'proc_open unavailable; cross-process reservation check skipped');
}

// ---------------------------------------------------------------------------
if ($failures) {
    fwrite(STDERR, count($failures) . " of $checks checks failed\n");
    exit(1);
}
echo "$checks provider checks passed\n";
