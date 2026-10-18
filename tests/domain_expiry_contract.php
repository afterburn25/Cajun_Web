<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIDomainContracts\DomainContractBinding;
use CajunWebPro\Foundation\TwentyIDomainExpiry\{DomainExpiry, DomainExpiryBinding, DomainExpiryObservation};

foreach (['TransportRequest', 'TransportResponse'] as $file) require __DIR__ . '/../foundation/provider/' . $file . '.php';
require __DIR__ . '/../foundation/twentyi-inventory/InventoryDomain.php';
require __DIR__ . '/../foundation/twentyi-domain-contracts/DomainContractBinding.php';
foreach (['DomainExpiryBinding', 'DomainExpiryObservation', 'DomainExpiry'] as $file) {
    require __DIR__ . '/../foundation/twentyi-domain-expiry/' . $file . '.php';
}
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$checks = [];
function de_ok(string $name, bool $passed): void
{
    global $checks;
    $checks[] = ['name' => $name, 'passed' => $passed];
    if (!$passed) fwrite(STDERR, 'FAIL ' . $name . PHP_EOL);
}
function de_reject(string $name, callable $call): void
{
    try { $call(); de_ok($name, false); }
    catch (InvalidArgumentException $error) {
        de_ok($name, !str_contains($error->getMessage(), 'PRIVATE_MARKER'));
    }
}
function de_fixture(): array
{
    return [
        'profile_id' => str_repeat('a', 32), 'profile_revision' => 1,
        'key_fingerprint' => str_repeat('b', 64), 'domain_id' => 701,
        'domain' => 'owned.synthetic.invalid', 'link_id' => str_repeat('c', 32),
        'link_revision' => 1, 'client_id' => 3, 'link_event_id' => 4,
        'link_event_sha256' => str_repeat('d', 64), 'link_audit_id' => 5,
        'link_audit_sha256' => str_repeat('e', 64), 'source_refresh_id' => str_repeat('f', 32),
        'source_seq' => 6, 'source_sha256' => str_repeat('0', 64),
    ];
}
$registrar = new DomainContractBinding(de_fixture());
$binding = new DomainExpiryBinding($registrar,str_repeat('1',64));
$at = 1700000000;
function de_project(string $body, int $status = 200): DomainExpiryObservation
{
    global $binding, $at;
    return DomainExpiry::project(new TransportResponse($status, ['X-Private' => 'PRIVATE_MARKER'], $body), $binding, $at);
}

$accepted = [
    '0001-01-01T00:00:00Z', '9999-12-31T23:59:59Z',
    '2000-02-29T12:34:56Z', '1900-02-28T00:00:00Z',
    '2028-02-29T01:02:03.1Z', '2028-02-29T01:02:03.000000Z',
    '2028-02-29T01:02:03.123456+14:00', '2028-02-29T01:02:03-14:00',
    '2028-02-29T01:02:03+00:00', '2028-02-29T01:02:03+05:45',
    '2028-02-29T01:02:03-03:30', '2028-02-29T01:02:03-00:01',
    '2028-02-29T01:02:03+13:59',
];
foreach ($accepted as $index => $value) {
    $result = de_project(json_encode($value, JSON_THROW_ON_ERROR));
    de_ok('Accepted calendar/time/offset retains exact decoded bytes ' . $index,
        $result->state === 'complete' && $result->upstreamExpiryDate === $value
        && $result->reason === '' && $result->observedAt === $at
        && $result->binding === $binding && $result->domainId === 701
        && $result->domainName === 'owned.synthetic.invalid' && $result->scope === $binding->scope);
}
$rejected = [
    'nonleap-century' => '1900-02-29T00:00:00Z', 'nonleap-year' => '2027-02-29T00:00:00Z',
    'invalid-month-day' => '2028-04-31T00:00:00Z', 'year-zero' => '0000-01-01T00:00:00Z',
    'month-zero' => '2028-00-01T00:00:00Z', 'month-overflow' => '2028-13-01T00:00:00Z',
    'day-zero' => '2028-01-00T00:00:00Z', 'hour-overflow' => '2028-01-01T24:00:00Z',
    'minute-overflow' => '2028-01-01T00:60:00Z', 'leap-second' => '2028-01-01T00:00:60Z',
    'offset-hour-overflow' => '2028-01-01T00:00:00+15:00',
    'offset-upper-minute' => '2028-01-01T00:00:00+14:01',
    'offset-lower-minute' => '2028-01-01T00:00:00-14:01',
    'offset-minute-overflow' => '2028-01-01T00:00:00+01:60',
    'unknown-offset' => '2028-01-01T00:00:00-00:00',
    'missing-offset' => '2028-01-01T00:00:00', 'named-zone' => '2028-01-01T00:00:00UTC',
    'offset-without-colon' => '2028-01-01T00:00:00+0100',
    'offset-seconds' => '2028-01-01T00:00:00+01:00:00',
    'date-only' => '2028-01-01', 'lowercase-t' => '2028-01-01t00:00:00Z',
    'lowercase-z' => '2028-01-01T00:00:00z', 'space-separator' => '2028-01-01 00:00:00Z',
    'missing-seconds' => '2028-01-01T00:00Z', 'single-digit-month' => '2028-1-01T00:00:00Z',
    'extended-year' => '+02028-01-01T00:00:00Z', 'ordinal-date' => '2028-001T00:00:00Z',
    'week-date' => '2028-W01-1T00:00:00Z', 'comma-fraction' => '2028-01-01T00:00:00,1Z',
    'empty-fraction' => '2028-01-01T00:00:00.Z', 'precision-overflow' => '2028-01-01T00:00:00.1234567Z',
    'trailing-space' => '2028-01-01T00:00:00Z ', 'leading-space' => ' 2028-01-01T00:00:00Z',
    'trailing-newline' => "2028-01-01T00:00:00Z\n", 'embedded-null' => "2028-01-01T00:00:00\0Z",
    'unverified-sdk-suffix' => '2028-01-01T00:00:00X', 'private-error' => 'PRIVATE_MARKER',
    'empty' => '',
];
foreach ($rejected as $name => $value) {
    $result = de_project(json_encode($value, JSON_THROW_ON_ERROR));
    de_ok('Unavailable unsupported timestamp ' . $name,
        $result->state === 'unavailable' && $result->reason === 'timestamp_unsupported'
        && $result->upstreamExpiryDate === null && !str_contains(json_encode($result), 'PRIVATE_MARKER'));
}

$timezone = date_default_timezone_get();
try {
    date_default_timezone_set('Pacific/Honolulu');
    $first = de_project('"2028-03-12T02:30:00-05:00"');
    date_default_timezone_set('Europe/London');
    $second = de_project('"2028-03-12T02:30:00-05:00"');
    de_ok('Host timezone cannot reinterpret accepted explicit timestamp',
        $first->upstreamExpiryDate === '2028-03-12T02:30:00-05:00'
        && json_encode($first) === json_encode($second));
} finally { date_default_timezone_set($timezone); }
de_ok('JSON escapes decode without timestamp normalization',
    de_project('"2028-01-01\\u005400:00:00\\u005a"')->upstreamExpiryDate === '2028-01-01T00:00:00Z');
de_ok('JSON surrounding whitespace is distinct from timestamp whitespace',
    de_project(" \n\t\"2028-01-01T00:00:00Z\"\r\n")->state === 'complete');

foreach ([
    'null' => 'timestamp_null', 'true' => 'timestamp_type', 'false' => 'timestamp_type',
    '0' => 'timestamp_type', '1700000000' => 'timestamp_type', '1.5' => 'timestamp_type',
    '[]' => 'timestamp_type', '{}' => 'timestamp_type',
    '["2028-01-01T00:00:00Z"]' => 'timestamp_type',
    '{"upstreamExpiryDate":"2028-01-01T00:00:00Z"}' => 'timestamp_type',
    '{"result":"2028-01-01T00:00:00Z","error":"PRIVATE_MARKER"}' => 'timestamp_type',
    '' => 'malformed_body', 'PRIVATE_MARKER' => 'malformed_body',
    '"2028-01-01T00:00:00Z" false' => 'malformed_body',
    '"\\uD800"' => 'malformed_body', "\"\xff\"" => 'malformed_body',
    '[[[[[[[[["PRIVATE_MARKER"]]]]]]]]]' => 'malformed_body',
] as $body => $reason) {
    $result = de_project((string)$body);
    de_ok('Scalar shape or decode classification ' . count($checks),
        $result->state === 'unavailable' && $result->reason === $reason
        && $result->upstreamExpiryDate === null && !str_contains(json_encode($result), 'PRIVATE_MARKER'));
}
foreach ([100 => 'unexpected_status', 201 => 'unexpected_status', 202 => 'unexpected_status',
    204 => 'unexpected_status', 301 => 'unexpected_status', 304 => 'unexpected_status',
    400 => 'unexpected_status', 401 => 'authentication', 403 => 'authentication',
    404 => 'unexpected_status', 410 => 'unexpected_status', 429 => 'rate_limited',
    500 => 'remote_error', 503 => 'remote_error', 599 => 'remote_error'] as $status => $reason) {
    $result = de_project('"2028-01-01T00:00:00Z"', $status);
    de_ok('HTTP status cannot become complete timestamp ' . $status,
        $result->state === 'unavailable' && $result->reason === $reason && $result->upstreamExpiryDate === null);
}
$error = DomainExpiry::project(new TransportResponse(403,
    ['Location' => 'https://PRIVATE_MARKER.invalid/', 'X-Private' => 'PRIVATE_MARKER'],
    '{"error":"PRIVATE_MARKER"}'), $binding, $at);
de_ok('Error headers and raw provider detail never survive',
    $error->reason === 'authentication' && !str_contains(json_encode($error), 'PRIVATE_MARKER'));
de_ok('Status classification precedes raw response parsing',
    de_project(str_repeat('X', 4097), 429)->reason === 'rate_limited');
$wire = '"2028-01-01T00:00:00Z"';
de_ok('Bounded response accepts exact local byte boundary',
    de_project($wire . str_repeat(' ', 4096 - strlen($wire)))->state === 'complete');
de_ok('Bounded response refuses one byte over local limit',
    de_project($wire . str_repeat(' ', 4097 - strlen($wire)))->reason === 'response_limit');

de_reject('Observed time cannot be negative', fn() => DomainExpiry::project(new TransportResponse(200, [], $wire), $binding, -1));
de_reject('Complete observation requires supported timestamp', fn() => new DomainExpiryObservation($binding, 'complete', 'PRIVATE_MARKER', '', $at));
de_reject('Complete observation cannot retain a failure reason', fn() => new DomainExpiryObservation($binding, 'complete', '2028-01-01T00:00:00Z', 'authentication', $at));
de_reject('Unavailable observation cannot retain an old timestamp', fn() => new DomainExpiryObservation($binding, 'unavailable', '2028-01-01T00:00:00Z', 'authentication', $at));
de_reject('Unavailable observation requires a finite reason', fn() => new DomainExpiryObservation($binding, 'unavailable', null, 'PRIVATE_MARKER', $at));
de_reject('Arbitrary observation states cannot infer expiry', fn() => new DomainExpiryObservation($binding, 'expired', null, '', $at));
de_ok('Observation epoch zero is valid and independent',
    DomainExpiry::project(new TransportResponse(200, [], $wire), $binding, 0)->observedAt === 0);
de_ok('Operation scope cannot replay renewal-date binding',
    $binding->scope !== $registrar->scope && $binding->sha256 !== $registrar->sha256
    && strlen($binding->scope) <= 190 && hash('sha256', $binding->json) === $binding->sha256);

$changes = [
    'profile_id' => str_repeat('1', 32), 'profile_revision' => 2, 'key_fingerprint' => str_repeat('1', 64),
    'domain_id' => 702, 'domain' => 'other.synthetic.invalid', 'link_id' => str_repeat('2', 32),
    'link_revision' => 2, 'client_id' => 4, 'link_event_id' => 7, 'link_event_sha256' => str_repeat('2', 64),
    'link_audit_id' => 8, 'link_audit_sha256' => str_repeat('3', 64), 'source_refresh_id' => str_repeat('3', 32),
    'source_seq' => 9, 'source_sha256' => str_repeat('4', 64),
];
foreach ($changes as $key => $value) {
    $data = de_fixture(); $data[$key] = $value;
    $other = new DomainExpiryBinding(new DomainContractBinding($data),str_repeat('1',64));
    de_ok('Exact authority change invalidates operation scope ' . $key,
        $other->scope !== $binding->scope && $other->sha256 !== $binding->sha256);
}
$name = 'owned.synthetic.invalid'; $data = de_fixture(); $data['domain'] = &$name;
$detached = new DomainExpiryBinding(new DomainContractBinding($data),str_repeat('1',64)); $name = 'changed.synthetic.invalid';
de_ok('Caller reference cannot change bound request name',
    $detached->registrar->data['domain'] === 'owned.synthetic.invalid'
    && DomainExpiry::request($detached)->url === 'https://api.20i.com/domain/owned.synthetic.invalid/upstreamExpiryDate');
de_ok('Expiry value owns rebuilt immutable registrar data',
    $binding->registrar !== $registrar && $binding->registrar->json === $registrar->json);
foreach (['json', 'sha256', 'scope'] as $field) {
    $restored = (new ReflectionClass(DomainContractBinding::class))->newInstanceWithoutConstructor();
    foreach (get_object_vars($registrar) as $key => $value) {
        (new ReflectionProperty(DomainContractBinding::class, $key))->setValue(
            $restored, $key === $field ? 'PRIVATE_MARKER' : $value,
        );
    }
    de_reject('Inconsistent restored registrar document rejected ' . $field, fn() => new DomainExpiryBinding($restored,str_repeat('1',64)));
}

$request = DomainExpiry::request($binding);
de_ok('Fixed registrar name GET descriptor has no credential or body',
    $request->method === 'GET' && $request->url === 'https://api.20i.com/domain/owned.synthetic.invalid/upstreamExpiryDate'
    && $request->headers === ['Accept' => 'application/json'] && $request->body === null
    && $request->timeoutSeconds === 15.0);
foreach (['701', 'UPPER.invalid', 'owned.invalid.', 'owned.invalid/contract', 'owned.invalid?x=1',
    'owned.invalid#fragment', 'owned.invalid%2fcontract', 'owned.invalid:443', 'user@owned.invalid',
    '../owned.invalid', 'owned..invalid', '127.0.0.1', 'https://other.invalid',
    "owned.invalid\r\nX-Private: PRIVATE_MARKER", '-owned.invalid', 'owned-.invalid'] as $index => $invalidName) {
    $data = de_fixture(); $data['domain'] = $invalidName;
    de_reject('No arbitrary path or noncanonical registered name ' . $index,
        fn() => DomainExpiry::request(new DomainExpiryBinding(new DomainContractBinding($data),str_repeat('1',64))));
}
foreach (['xn--bcher-kva.invalid', 'registered.co.invalid'] as $canonicalName) {
    $data = de_fixture(); $data['domain'] = $canonicalName;
    de_ok('Canonical registered ASCII name preserved ' . $canonicalName,
        DomainExpiry::request(new DomainExpiryBinding(new DomainContractBinding($data),str_repeat('1',64)))->url
        === 'https://api.20i.com/domain/' . $canonicalName . '/upstreamExpiryDate');
}
de_ok('Pure component exposes no network dispatch or credential resolver',
    array_diff(get_class_methods(DomainExpiry::class), ['request', 'project']) === []
    && !class_exists('CajunWebPro\\Foundation\\TwentyI\\FixedHttpTransport', false));
de_ok('Named checks are unique', count(array_unique(array_column($checks, 'name'))) === count($checks));
$passed = !array_filter($checks, static fn(array $check): bool => !$check['passed']);
echo json_encode([
    'passed' => $passed,
    'scope' => 'Synthetic pure registrar expiry descriptor, exact typed binding and defensive timestamp projection; no network, database, routes or current-authority acceptance',
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($passed ? 0 : 1);
