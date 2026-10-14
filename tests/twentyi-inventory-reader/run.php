<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\HttpTransport;
use CajunWebPro\Foundation\Provider\ProviderCredential;
use CajunWebPro\Foundation\Provider\TransportException;
use CajunWebPro\Foundation\Provider\TransportRequest;
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
use CajunWebPro\Foundation\TwentyIInventory\InventorySnapshot;
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;

require __DIR__.'/../../foundation/provider/HttpTransport.php';
require __DIR__.'/../../foundation/provider/ProviderCredential.php';
require __DIR__.'/../../foundation/provider/TransportRequest.php';
require __DIR__.'/../../foundation/provider/TransportResponse.php';
require __DIR__.'/../../foundation/provider/TransportException.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventoryRead.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventoryPackage.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventorySnapshot.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventoryProjector.php';
require __DIR__.'/../../foundation/twentyi-inventory-reader/InventoryReader.php';

// ---------------------------------------------------------------------------
// SYNTHETIC FIXTURES. Item shape derives from 20i's public support article
// (docs.20i.com/api/retrieve-packages-api); nothing here is a captured live
// response or a real credential.
// ---------------------------------------------------------------------------

$checks = [];
function ok(string $name, bool $passed): void
{
    global $checks;
    $checks[] = ['name' => $name, 'passed' => $passed];
    if (!$passed) fwrite(STDERR, "FAIL: {$name}\n");
}
function deny(string $name, callable $work): void
{
    try { $work(); } catch (InvalidArgumentException|RuntimeException|LogicException) { ok($name, true); return; }
    ok($name, false);
}

/** Scripted transport: records requests, returns queued responses or throws. */
final class ScriptedTransport implements HttpTransport
{
    /** @var TransportRequest[] */ public array $sent = [];
    /** @var list<TransportResponse|Throwable> */ private array $script;
    public function __construct(array $script) { $this->script = $script; }
    public function send(TransportRequest $request): TransportResponse
    {
        $this->sent[] = $request;
        $next = array_shift($this->script) ?? new TransportResponse(500, [], 'no fixture queued');
        if ($next instanceof Throwable) throw $next;
        return $next;
    }
}

/** Sample item with the full documented fields and unknown extras. */
function item(int $id, string $name = 'pkg.example'): array
{
    return ['id' => $id, 'typeRef' => 9, 'enabled' => true, 'name' => $name, 'names' => [$name],
        'created' => '2024-01-02T03:04:05Z', 'packageTypeName' => 'Web', 'packageLabels' => ['LBL'],
        'stackUsers' => [], 'productSpec' => new stdClass(),
        'unmappedSecret' => 'credential-material', 'unmappedFlag' => true];
}
function body(array $items): string { return json_encode($items, JSON_THROW_ON_ERROR); }
function response(array $items, int $status = 200): TransportResponse
{
    return new TransportResponse($status, [], body($items));
}
function credential(string $secret = 'test-key-material', string $account = 'reseller-42'): ProviderCredential
{
    return new ProviderCredential('twenty-i', $account, fn(): string => $secret);
}

// --- Refusals before any credential resolution or transport call -----------

deny('refuse_wrong_provider_no_call', function (): void {
    $t = new ScriptedTransport([]);
    $resolved = false;
    $c = new ProviderCredential('other-provider', 'acct', function () use (&$resolved): string { $resolved = true; return 'k'; });
    InventoryReader::read($t, $c, InventoryRead::PACKAGES_LIST, null, 1700000000);
    ok('wrong_provider_resolved_anyway', $resolved); // marker fail path below
    if ($resolved) throw new LogicException('resolved');
});
ok('refuse_wrong_provider_no_send', (function (): bool {
    $t = new ScriptedTransport([]);
    $c = new ProviderCredential('other-provider', 'acct', function (): string { throw new LogicException('resolver called'); });
    try { InventoryReader::read($t, $c, InventoryRead::PACKAGES_LIST, null, 1700000000); } catch (InvalidArgumentException) {}
    return count($t->sent) === 0;
})());

deny('refuse_documented_only_detail', fn() => InventoryReader::read(new ScriptedTransport([]), credential(), InventoryRead::PACKAGE_DETAIL, 7, 1700000000));
deny('refuse_documented_only_types', fn() => InventoryReader::read(new ScriptedTransport([]), credential(), InventoryRead::PACKAGE_TYPES, '*', 1700000000));
deny('refuse_documented_only_balance', fn() => InventoryReader::read(new ScriptedTransport([]), credential(), InventoryRead::ACCOUNT_BALANCE, '*', 1700000000));
deny('refuse_unknown_op', fn() => InventoryReader::read(new ScriptedTransport([]), credential(), 'packages.delete', null, 1700000000));
ok('no_send_on_refusal', (function (): bool {
    $t = new ScriptedTransport([]);
    foreach ([InventoryRead::PACKAGE_DETAIL, InventoryRead::PACKAGE_TYPES, InventoryRead::ACCOUNT_BALANCE, 'packages.delete'] as $op) {
        try { InventoryReader::read($t, credential(), $op, null, 1700000000); } catch (Throwable) {}
    }
    return count($t->sent) === 0;
})());

// --- Credential resolution -------------------------------------------------

deny('refuse_empty_secret', fn() => InventoryReader::read(new ScriptedTransport([]), credential(''), InventoryRead::PACKAGES_LIST, null, 1700000000));
ok('empty_secret_no_send', (function (): bool {
    $t = new ScriptedTransport([]);
    try { InventoryReader::read($t, credential(''), InventoryRead::PACKAGES_LIST, null, 1700000000); } catch (InvalidArgumentException) {}
    return count($t->sent) === 0;
})());
deny('refuse_oversize_secret', fn() => InventoryReader::read(new ScriptedTransport([]), credential(str_repeat('x', 5000)), InventoryRead::PACKAGES_LIST, null, 1700000000));
deny('refuse_control_secret', fn() => InventoryReader::read(new ScriptedTransport([]), credential("bad\x01key"), InventoryRead::PACKAGES_LIST, null, 1700000000));
deny('refuse_account_controls', function (): void {
    $c = new ProviderCredential('twenty-i', "acct\n7", fn(): string => 'k');
    InventoryReader::read(new ScriptedTransport([]), $c, InventoryRead::PACKAGES_LIST, null, 1700000000);
});
deny('refuse_account_oversize', function (): void {
    $c = new ProviderCredential('twenty-i', str_repeat('a', 200), fn(): string => 'k');
    InventoryReader::read(new ScriptedTransport([]), $c, InventoryRead::PACKAGES_LIST, null, 1700000000);
});
deny('refuse_negative_time', fn() => InventoryReader::read(new ScriptedTransport([]), credential(), InventoryRead::PACKAGES_LIST, null, -1));
deny('refuse_throwing_resolver', function (): void {
    $c = new ProviderCredential('twenty-i', 'acct', function (): string { throw new RuntimeException('vault exploded with secret xyz'); });
    InventoryReader::read(new ScriptedTransport([]), $c, InventoryRead::PACKAGES_LIST, null, 1700000000);
});
ok('resolver_fault_bounded_message', (function (): bool {
    $c = new ProviderCredential('twenty-i', 'acct', function (): string { throw new RuntimeException('vault exploded with secret xyz'); });
    try { InventoryReader::read(new ScriptedTransport([]), $c, InventoryRead::PACKAGES_LIST, null, 1700000000); }
    catch (InvalidArgumentException $e) { return !str_contains($e->getMessage(), 'xyz') && strlen($e->getMessage()) < 80; }
    return false;
})());

// --- Single bounded authenticated request ----------------------------------

ok('one_request_bearer_fixed_url', (function (): bool {
    $t = new ScriptedTransport([response([item(1)])]);
    InventoryReader::read($t, credential('sk-live-material'), InventoryRead::PACKAGES_LIST, null, 1700000000);
    if (count($t->sent) !== 1) return false;
    $r = $t->sent[0];
    return $r->method === 'GET' && $r->url === 'https://api.20i.com/package'
        && $r->timeoutSeconds === 15.0 && $r->body === null
        && ($r->headers['Authorization'] ?? '') === 'Bearer '.base64_encode('sk-live-material')
        && ($r->headers['Accept'] ?? '') === 'application/json';
})());

ok('descriptor_carries_no_auth', (function (): bool {
    $r = InventoryRead::request(InventoryRead::PACKAGES_LIST, 0);
    foreach ($r->headers as $k => $v) {
        if (stripos($k, 'auth') !== false || str_contains($v, 'Bearer')) return false;
    }
    return true;
})());

// --- Snapshot binding -------------------------------------------------------

ok('scope_is_account_operation_bound', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([response([item(1)])]), credential('k', 'reseller-42'), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $s->scope === 'twenty-i|reseller-42|packages.list' && $s->state === InventorySnapshot::COMPLETE
        && $s->coverage === InventorySnapshot::COVERAGE_UNKNOWN && count($s->packages) === 1 && $s->packages[0]->id === 1;
})());

ok('different_accounts_different_scope', (function (): bool {
    $a = InventoryReader::read(new ScriptedTransport([response([item(1)])]), credential('k', 'acct-A'), InventoryRead::PACKAGES_LIST, null, 1700000000);
    $b = InventoryReader::read(new ScriptedTransport([response([item(2)])]), credential('k', 'acct-B'), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $a->scope !== $b->scope && str_contains($a->scope, 'acct-A') && str_contains($b->scope, 'acct-B');
})());

ok('secret_absent_from_snapshot', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([response([item(1)])]), credential('sk-highly-sensitive'), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return !str_contains(var_export($s, true), 'sk-highly-sensitive')
        && !str_contains(json_encode($s), 'sk-highly-sensitive')
        && !str_contains(var_export($s->packages[0], true), 'sk-highly-sensitive');
})());

// --- Failure classification -------------------------------------------------

ok('transport_timeout_unavailable', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([new TransportException(TransportException::KIND_TIMEOUT)]), credential(), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $s->state === InventorySnapshot::UNAVAILABLE && $s->reason === 'transport_timeout'
        && $s->scope === 'twenty-i|reseller-42|packages.list';
})());

ok('non_transport_throw_classified', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([new RuntimeException('socket leak with header Bearer abc')]), credential(), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $s->state === InventorySnapshot::UNAVAILABLE && $s->reason === 'transport_unknown';
})());

ok('http_401_authentication', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([new TransportResponse(401, [], '{}')]), credential(), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $s->state === InventorySnapshot::UNAVAILABLE && $s->reason === 'authentication';
})());
ok('http_429_rate_limited', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([new TransportResponse(429, [], '{}')]), credential(), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $s->state === InventorySnapshot::UNAVAILABLE && $s->reason === 'rate_limited';
})());
ok('malformed_body', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([new TransportResponse(200, [], 'not json')]), credential(), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $s->state === InventorySnapshot::UNAVAILABLE && $s->reason === 'malformed_body';
})());
ok('object_body_not_list', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([new TransportResponse(200, [], '{"0":{"id":1}}')]), credential(), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $s->state === InventorySnapshot::UNAVAILABLE && $s->reason === 'unexpected_shape';
})());
ok('partial_skip_classified', (function (): bool {
    $s = InventoryReader::read(new ScriptedTransport([response([item(1), ['names' => ['x']]])]), credential(), InventoryRead::PACKAGES_LIST, null, 1700000000);
    return $s->state === InventorySnapshot::PARTIAL && $s->skipped === 1 && count($s->packages) === 1;
})());

$failed = array_filter($checks, fn($c) => !$c['passed']);
printf("%d checks, %d failed\n", count($checks), count($failed));
exit($failed ? 1 : 0);
