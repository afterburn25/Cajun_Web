<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\TransportException;
use CajunWebPro\Foundation\Provider\TransportRequest;
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\InventoryCapability;
use CajunWebPro\Foundation\TwentyIInventory\InventoryPackage;
use CajunWebPro\Foundation\TwentyIInventory\InventoryProjector;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRegister;
use CajunWebPro\Foundation\TwentyIInventory\InventorySnapshot;

require __DIR__.'/../../foundation/provider/TransportRequest.php';
require __DIR__.'/../../foundation/provider/TransportResponse.php';
require __DIR__.'/../../foundation/provider/TransportException.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventoryCapability.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventoryRead.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventoryPackage.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventorySnapshot.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventoryProjector.php';
require __DIR__.'/../../foundation/twentyi-inventory/InventoryRegister.php';

// ---------------------------------------------------------------------------
// SYNTHETIC FIXTURES. The "documented" item shape is derived from 20i's
// public support article (docs.20i.com/api/retrieve-packages-api); it is not
// a captured live response and proves nothing about the real account.
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

/** Item shaped exactly like the published support-article sample. */
function doc_item(int $id = 797701): array
{
    return ['id' => $id, 'created' => '2020-01-15T16:30:30+00:00', 'enabled' => true, 'name' => 'example-site.test', 'names' => ['example-site.test'], 'packageTypeName' => 'Linux Unlimited', 'productSpec' => null, 'stackUsers' => ['stack-user:989901'], 'typeRef' => 811, 'packageLabels' => []];
}
/** The form a real JSON response body takes after object-preserving decode. */
function obj(array $item): stdClass { return json_decode(json_encode($item)); }
function body(array $items): string { return json_encode(array_values($items)); }
function response(int $status, array|string $body): TransportResponse { return new TransportResponse($status, [], is_string($body) ? $body : body($body)); }
function project(mixed $r, int $at = 1729000000, string $scope = ''): InventorySnapshot { return $r instanceof TransportResponse ? InventoryProjector::packagesList($r, $at, $scope) : InventoryProjector::unavailableFromTransport($r, $at, $scope); }

// --- Request descriptors: closed read set ---------------------------------

ok('List descriptor is a GET on the fixed provider host', ($r = InventoryRead::request(InventoryRead::PACKAGES_LIST)) instanceof TransportRequest && $r->method === 'GET' && $r->url === 'https://api.20i.com/package' && $r->body === null && $r->timeoutSeconds === 15.0);
ok('Detail descriptor embeds the allocated package id', InventoryRead::request(InventoryRead::PACKAGE_DETAIL, 1234)->url === 'https://api.20i.com/package/1234');
ok('Types descriptor accepts the documented reseller self alias', InventoryRead::request(InventoryRead::PACKAGE_TYPES, '*')->url === 'https://api.20i.com/reseller/*/packageTypes');
ok('Balance descriptor accepts a numeric reseller id', InventoryRead::request(InventoryRead::ACCOUNT_BALANCE, 42)->url === 'https://api.20i.com/reseller/42/accountBalance');
ok('String allocated ids are accepted for detail reads', InventoryRead::request(InventoryRead::PACKAGE_DETAIL, '90125')->url === 'https://api.20i.com/package/90125');
ok('No descriptor carries an Authorization header', InventoryRead::request(InventoryRead::PACKAGES_LIST)->headers === ['Accept' => 'application/json']);
deny('Unknown operations cannot build a request', fn() => InventoryRead::request('domains.register', 1));
deny('Mutating documented endpoints are not reads', fn() => InventoryRead::request('packages.create', 1));
deny('Detail reads refuse a missing package id', fn() => InventoryRead::request(InventoryRead::PACKAGE_DETAIL));
deny('Detail reads refuse a non-numeric id', fn() => InventoryRead::request(InventoryRead::PACKAGE_DETAIL, 'abc'));
deny('Detail reads refuse a zero id', fn() => InventoryRead::request(InventoryRead::PACKAGE_DETAIL, 0));
deny('Detail reads refuse negative ids', fn() => InventoryRead::request(InventoryRead::PACKAGE_DETAIL, -5));
deny('Detail reads refuse id strings with a scheme-injection shape', fn() => InventoryRead::request(InventoryRead::PACKAGE_DETAIL, '1/../../admin'));
deny('Reseller slot refuses an arbitrary alias', fn() => InventoryRead::request(InventoryRead::PACKAGE_TYPES, 'other'));
ok('Register and descriptor sets agree', array_keys(InventoryRegister::all()) === InventoryRead::operations());

// --- Capability register ----------------------------------------------------

$register = InventoryRegister::all();
ok('Every registered operation is a documented GET', array_reduce($register, fn($c, $cap) => $c && $cap->method === 'GET', true));
ok('Every registered path is a literal documented template', array_reduce($register, fn($c, $cap) => $c && str_starts_with($cap->pathTemplate, '/'), true));
ok('Only packages.list carries authenticated-read evidence', count(array_filter($register, fn($cap) => $cap->evidence === InventoryCapability::EVIDENCE_AUTHENTICATED_READ)) === 1);
ok('Package detail is a documented sensitive read', InventoryRegister::get(InventoryRead::PACKAGE_DETAIL)->risk === InventoryCapability::RISK_SENSITIVE_READ);
ok('Package types schema is honestly unknown', InventoryRegister::get(InventoryRead::PACKAGE_TYPES)->schemaKnown === false);
ok('Account balance schema is honestly unknown', InventoryRegister::get(InventoryRead::ACCOUNT_BALANCE)->schemaKnown === false);
ok('Only the evidenced list shape is projectable', InventoryRegister::projectable(InventoryRead::PACKAGES_LIST) && !InventoryRegister::projectable(InventoryRead::PACKAGE_DETAIL) && !InventoryRegister::projectable(InventoryRead::PACKAGE_TYPES) && !InventoryRegister::projectable(InventoryRead::ACCOUNT_BALANCE));
deny('Register refuses unknown operations', fn() => InventoryRegister::get('vps.power'));
deny('A capability cannot claim a non-GET method', fn() => new InventoryCapability('x', 'POST', '/x', InventoryCapability::RISK_READ, InventoryCapability::EVIDENCE_DOCUMENTED, false));

// --- Happy path: documented list shape --------------------------------------

$snap = project(response(200, [doc_item(797701), doc_item(527649)]));
ok('Documented list projects complete', $snap->state === InventorySnapshot::COMPLETE && $snap->ok());
ok('Two packages projected', count($snap->packages) === 2);
$p = $snap->packages[0];
ok('Package identity and terms fields retained', $p instanceof InventoryPackage && $p->id === 797701 && $p->name === 'example-site.test' && $p->enabled === true && $p->typeRef === 811 && $p->packageTypeName === 'Linux Unlimited');
ok('Created instant kept raw and as epoch', $p->createdAt === '2020-01-15T16:30:30+00:00' && $p->createdEpoch === strtotime('2020-01-15T16:30:30+00:00'));
ok('Stack-user references retained without user detail', $p->stackUsers === ['stack-user:989901']);
ok('Empty observed list is a complete observation, not unavailability', project(response(200, []))->state === InventorySnapshot::COMPLETE && project(response(200, []))->packages === []);

// --- Field validation and honest partials ------------------------------------

ok('Missing optional stackUsers defaults to none', project(response(200, [array_diff_key(doc_item(), ['stackUsers' => 1])]))->state === InventorySnapshot::COMPLETE);
ok('Extra unknown fields report names only and stay complete', ($s = project(response(200, [array_replace(doc_item(), ['ftpPassword' => 'SECRET', 'futureField' => 1])])))->state === InventorySnapshot::COMPLETE && $s->packages[0]->unmappedKeys === ['ftpPassword', 'futureField']);
ok('Credential-bearing values are never retained', !isset($s->packages[0]->ftpPassword) && json_encode($s) === json_encode(json_decode(json_encode($s))) && strpos(json_encode($s), 'SECRET') === false);
ok('Missing required id skips the item partial', ($s = project(response(200, [array_diff_key(doc_item(), ['id' => 1])])))->state === InventorySnapshot::PARTIAL && $s->skipped === 1 && $s->packages === []);
ok('Missing name skips the item partial', project(response(200, [array_diff_key(doc_item(), ['name' => 1])]))->state === InventorySnapshot::PARTIAL);
ok('Non-boolean enabled skips the item', project(response(200, [array_replace(doc_item(), ['enabled' => 'yes'])]))->state === InventorySnapshot::PARTIAL);
ok('Malformed created timestamp skips the item', project(response(200, [array_replace(doc_item(), ['created' => 'not a date'])]))->state === InventorySnapshot::PARTIAL);
ok('Duplicate package id keeps the first and marks partial', ($s = project(response(200, [doc_item(5), doc_item(5), doc_item(6)])))->state === InventorySnapshot::PARTIAL && count($s->packages) === 2 && $s->packages[0]->id === 5 && $s->skipped === 1);
ok('Skip reasons are recorded', $s->skipReasons === ['Duplicate package id in response.']);
ok('Mixed valid and invalid items give partial with the valid item', ($s = project(response(200, [doc_item(1), ['broken' => true]])))->state === InventorySnapshot::PARTIAL && count($s->packages) === 1 && $s->skipped === 1);
ok('Digit-string ids normalize to integers', project(response(200, [array_replace(doc_item(), ['id' => '445', 'typeRef' => '900'])]))->packages[0]->id === 445);
deny('Zero package id is refused', fn() => InventoryPackage::fromItem(obj(array_replace(doc_item(), ['id' => 0]))));
deny('Negative package id is refused', fn() => InventoryPackage::fromItem(obj(array_replace(doc_item(), ['id' => -1]))));
deny('Float package id is refused', fn() => InventoryPackage::fromItem(obj(array_replace(doc_item(), ['id' => 1.5]))));
deny('Wrong stackUsers shape is refused', fn() => InventoryPackage::fromItem(obj(array_replace(doc_item(), ['stackUsers' => [123]]))));
deny('Non-reference stackUsers entry is refused', fn() => InventoryPackage::fromItem(obj(array_replace(doc_item(), ['stackUsers' => ['user:1']]))));
ok('UTF-8 names are retained', InventoryPackage::fromItem(obj(array_replace(doc_item(), ['name' => 'café-site.test', 'names' => ['café-site.test']])))->name === 'café-site.test');
deny('Control characters in names are refused', fn() => InventoryPackage::fromItem(obj(array_replace(doc_item(), ['name' => "bad\x01name.test"]))));
deny('Invalid UTF-8 names are refused', fn() => InventoryPackage::fromItem((object) array_replace(doc_item(), ['name' => "bad\xffname"])));
deny('Overlong names are refused', fn() => InventoryPackage::fromItem(obj(array_replace(doc_item(), ['name' => str_repeat('a', 254)]))));
deny('Nested names array is refused', fn() => InventoryPackage::fromItem(obj(array_replace(doc_item(), ['names' => [['nested']]]))));
ok('A present non-null productSpec is flagged, not parsed', InventoryPackage::fromItem(obj(array_replace(doc_item(), ['productSpec' => ['unknown' => 'shape']])))->hasProductSpec === true);
ok('Documented null productSpec is not flagged', InventoryPackage::fromItem(obj(doc_item()))->hasProductSpec === false);
ok('Null packageTypeName stays null', InventoryPackage::fromItem(obj(array_replace(doc_item(), ['packageTypeName' => null])))->packageTypeName === null);
ok('Primary name absent from names is an inconsistency skip, not a union', ($s = project(response(200, [array_replace(doc_item(), ['names' => ['alias.test']])])))->state === InventorySnapshot::PARTIAL && $s->skipped === 1 && $s->skipReasons === ['Package name is absent from the reported names list.']);
ok('Repeated names are preserved verbatim, not deduped', InventoryPackage::fromItem(obj(array_replace(doc_item(), ['names' => ['example-site.test', 'example-site.test']])))->names === ['example-site.test', 'example-site.test']);

// --- Response classification -------------------------------------------------

ok('401 is authentication unavailable', project(response(401, '{}'))->reason === 'authentication');
ok('403 is authentication unavailable', project(response(403, '{}'))->reason === 'authentication');
ok('429 is rate limited', project(response(429, '{}'))->reason === 'rate_limited');
ok('500 is remote error', project(response(500, '{}'))->reason === 'remote_error');
ok('503 is remote error', project(response(503, '{}'))->reason === 'remote_error');
ok('418 is an unexpected status', project(response(418, '{}'))->reason === 'unexpected_status');
ok('Redirect status is not followed by the projector', project(response(302, ''))->reason === 'unexpected_status');
ok('Truncated JSON is malformed', project(response(200, '[{"id": 1'))->reason === 'malformed_body');
ok('Object body is an unexpected shape, not a list', project(response(200, '{"id":1}'))->reason === 'unexpected_shape');
ok('Scalar body is an unexpected shape', project(response(200, '"ok"'))->reason === 'unexpected_shape');
$snap = project(response(200, str_repeat(' ', InventoryProjector::MAX_BODY_BYTES + 1)));
ok('Body over the byte bound is unavailable', $snap->reason === 'response_limit');
$snap = project(response(200, body(array_fill(0, InventoryProjector::MAX_PACKAGES + 1, doc_item(1)))));
ok('Item count over the bound is unavailable', $snap->reason === 'response_limit');
ok('Transport failure maps to its finite kind', project(new TransportException(TransportException::KIND_TIMEOUT))->reason === 'transport_timeout');
ok('An unavailable snapshot carries no packages', project(response(500, ''))->packages === []);
deny('An unavailable snapshot cannot be built with packages', fn() => new InventorySnapshot(InventorySnapshot::UNAVAILABLE, [InventoryPackage::fromItem(obj(doc_item()))], 0, 0, [], 'x'));
deny('A complete snapshot cannot claim skipped items', fn() => new InventorySnapshot(InventorySnapshot::COMPLETE, [], 0, 1, ['x']));
deny('A partial snapshot must name its skips', fn() => new InventorySnapshot(InventorySnapshot::PARTIAL, [], 0, 1, []));

// --- Review regressions: object/list identity, coverage, field integrity ----

ok('JSON object body {} is unexpected shape, not an empty inventory', project(response(200, '{}'))->reason === 'unexpected_shape');
ok('JSON object with numeric keys is unexpected shape, not a list', project(response(200, '{"0":'.json_encode(doc_item()).'}'))->reason === 'unexpected_shape');
ok('JSON array of scalars does not project', ($s = project(response(200, '[1,2,3]')))->state === InventorySnapshot::PARTIAL && $s->packages === []);
ok('List items that are JSON arrays are skipped', project(response(200, '[[1,2]]'))->skipped === 1);
ok('Impossible calendar date is rejected, not normalized', project(response(200, [array_replace(doc_item(), ['created' => '2026-02-30T16:30:30+00:00'])]))->skipped === 1);
ok('Hour 24 is rejected', project(response(200, [array_replace(doc_item(), ['created' => '2026-02-28T24:00:00+00:00'])]))->skipped === 1);
ok('Oversized UTF-8 key truncates at a character boundary', ($s = project(response(200, [array_replace(doc_item(), [str_repeat('a', 63).'é' => 1])])))->state === InventorySnapshot::COMPLETE && json_encode($s) !== false && $s->packages[0]->unmappedCount === 1);
ok('Unprintable key still counts but is not named', ($s = project(response(200, [json_decode(json_encode(array_replace(doc_item(), ["bad\x01key" => 1])))])))->state === InventorySnapshot::COMPLETE && $s->packages[0]->unmappedKeys === [] && $s->packages[0]->unmappedCount === 1);
ok('Scope is recorded on the observation', project(response(200, []), 1729000000, 'twenty-i:acct-9:packages.list')->scope === 'twenty-i:acct-9:packages.list');
ok('Scope is recorded on unavailable observations', project(response(500, '{}'), 1729000000, 'twenty-i:acct-9')->scope === 'twenty-i:acct-9');
ok('Coverage is never claimed complete', project(response(200, [doc_item()]))->coverage === InventorySnapshot::COVERAGE_UNKNOWN);
deny('Snapshot rejects a non-record package', fn() => new InventorySnapshot(InventorySnapshot::COMPLETE, [['not' => 'record']], 0));
deny('Snapshot rejects negative observedAt', fn() => new InventorySnapshot(InventorySnapshot::COMPLETE, [], -1));
deny('Snapshot rejects negative skipped count', fn() => new InventorySnapshot(InventorySnapshot::PARTIAL, [], 0, -1, ['x']));
deny('Snapshot rejects a free-form unavailable reason', fn() => InventorySnapshot::unavailable('the provider said: '.str_repeat('x', 300), 0));
deny('Snapshot rejects unavailable with a reason outside the closed set', fn() => InventorySnapshot::unavailable('custom_reason', 0));
deny('Snapshot rejects control bytes in scope', fn() => new InventorySnapshot(InventorySnapshot::COMPLETE, [], 0, 0, [], '', "sc\x01ope"));
deny('Snapshot rejects complete coverage on a partial observation', fn() => new InventorySnapshot(InventorySnapshot::PARTIAL, [], 0, 1, ['x'], '', '', InventorySnapshot::COVERAGE_COMPLETE));
deny('Snapshot rejects partial without skipped items', fn() => new InventorySnapshot(InventorySnapshot::PARTIAL, [], 0));
deny('fromItem refuses a plain array (object identity required)', fn() => InventoryPackage::fromItem(doc_item()));

// --- Review regressions: numeric keys, construction boundary, references ----

ok('Numeric unknown key in raw JSON stays a serializable observation', ($s = project(response(200, '[{"id":1,"created":"2020-01-15T16:30:30+00:00","enabled":true,"name":"a.test","names":["a.test"],"typeRef":1,"123":"synthetic"}]')))->state === InventorySnapshot::COMPLETE && $s->packages[0]->unmappedKeys === ['123'] && $s->packages[0]->unmappedCount === 1 && strpos(json_encode($s), 'synthetic') === false);
ok('Package records are only built by the validated factory', !(new ReflectionClass(InventoryPackage::class))->getConstructor()->isPublic());
deny('Snapshot rejects a complete-coverage claim', fn() => new InventorySnapshot(InventorySnapshot::COMPLETE, [], 0, 0, [], '', '', InventorySnapshot::COVERAGE_COMPLETE));
deny('Snapshot rejects an arbitrary package-shaped array', fn() => new InventorySnapshot(InventorySnapshot::COMPLETE, [['id' => 1, 'private' => 'synthetic']], 0));
$pkg = InventoryPackage::fromItem(obj(doc_item()));
$mutablePkg = $pkg;
$snap = new InventorySnapshot(InventorySnapshot::COMPLETE, [&$mutablePkg], 1729000000);
$mutablePkg = ['attacker' => 'data'];
ok('Caller-held references cannot mutate snapshot packages', $snap->packages === [$pkg]);
$mutableReason = 'skipped once';
$snap2 = new InventorySnapshot(InventorySnapshot::PARTIAL, [], 1729000000, 1, [&$mutableReason]);
$mutableReason = 42;
ok('Caller-held references cannot mutate skip reasons', $snap2->skipReasons === ['skipped once']);

// ---------------------------------------------------------------------------

$failed = count(array_filter($checks, fn($c) => !$c['passed']));
$total = count($checks);
echo json_encode(['passed' => $failed === 0, 'scope' => 'Synthetic read-only inventory contract: fixtures from public docs only; no live 20i call, credential, persistence or client-linking tested.', 'checks' => $checks], JSON_PRETTY_PRINT), "\n";
fwrite(STDERR, "{$total} checks, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
