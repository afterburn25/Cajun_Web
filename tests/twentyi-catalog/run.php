<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\CapabilityCatalog;
use CajunWebPro\Foundation\Provider\CatalogEntry;
use CajunWebPro\Foundation\Provider\ProviderCapability;
use CajunWebPro\Foundation\TwentyI\TwentyICatalog;

require __DIR__ . '/../../foundation/provider/ProviderCapability.php';
require __DIR__ . '/../../foundation/provider/CatalogEntry.php';
require __DIR__ . '/../../foundation/provider/CapabilityCatalog.php';
require __DIR__ . '/../../foundation/twentyi/TwentyICatalog.php';

$checks = [];
function check(string $name, bool $ok): void
{
    global $checks;
    $checks[] = ['name' => $name, 'passed' => $ok];
    if (!$ok) {
        fwrite(STDERR, "FAIL $name\n");
    }
}
function throws(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (Throwable) {
        return true;
    }
}

// -- CatalogEntry validation --
check('entry accepts a documented read', (new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src'))->operation === 'a.b');
check('entry rejects unknown method', throws(fn() => new CatalogEntry('a.b', 'YEET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src')));
check('entry rejects lowercase method', throws(fn() => new CatalogEntry('a.b', 'get', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src')));
check('entry rejects path without leading slash', throws(fn() => new CatalogEntry('a.b', 'GET', 'x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src')));
check('entry rejects path with whitespace', throws(fn() => new CatalogEntry('a.b', 'GET', '/x y', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src')));
check('entry rejects path with control byte', throws(fn() => new CatalogEntry('a.b', 'GET', "/x\ty", CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src')));
check('entry rejects oversized path', throws(fn() => new CatalogEntry('a.b', 'GET', '/' . str_repeat('x', 200), CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src')));
check('entry rejects uppercase operation', throws(fn() => new CatalogEntry('A.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src')));
check('entry rejects empty operation', throws(fn() => new CatalogEntry('', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src')));
check('entry rejects unknown evidence', throws(fn() => new CatalogEntry('a.b', 'GET', '/x', 'vibes', CatalogEntry::RISK_READ, 'src')));
check('entry rejects unknown risk', throws(fn() => new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, 'spicy', 'src')));
check('entry rejects empty provenance', throws(fn() => new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, ' ')));
check('entry rejects note with control byte', throws(fn() => new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src', "bad\nnote")));
check('entry rejects oversized note', throws(fn() => new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src', str_repeat('n', 401))));
check('entry rejects invalid UTF-8 note', throws(fn() => new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src', "\xc3\x28")));
check('UTF-8 note accepted', (new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src', 'précision – £5'))->note === 'précision – £5');

// -- semanticallyReadOnly --
check('read risk is semantically read-only', (new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src'))->semanticallyReadOnly());
check('credential risk is read-like but flagged', (new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_CREDENTIAL, 'src'))->semanticallyReadOnly());
check('GET-labelled write is not read-only', !(new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE, 'src'))->semanticallyReadOnly());
check('destructive is not read-only', !(new CatalogEntry('a.b', 'POST', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_DESTRUCTIVE, 'src'))->semanticallyReadOnly());
check('POST-labelled lookup can be read-only', (new CatalogEntry('a.b', 'POST', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 'src'))->semanticallyReadOnly());

// -- CapabilityCatalog mechanics --
check('catalog rejects empty provider id', throws(fn() => new CapabilityCatalog(' ')));
check('catalog rejects duplicate operation', throws(fn() => new CapabilityCatalog('p',
    new CatalogEntry('a.b', 'GET', '/x', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 's'),
    new CatalogEntry('a.b', 'POST', '/y', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_WRITE, 's'))));
$catalog = new CapabilityCatalog('p',
    new CatalogEntry('r.one', 'GET', '/a', CatalogEntry::EVIDENCE_DOCUMENTED, CatalogEntry::RISK_READ, 's', 'n1'),
    new CatalogEntry('r.two', 'GET', '/b', CatalogEntry::EVIDENCE_AUTHENTICATED, CatalogEntry::RISK_READ, 's', 'n2'),
    new CatalogEntry('w.one', 'POST', '/c', CatalogEntry::EVIDENCE_VERIFIED, CatalogEntry::RISK_WRITE, 's', 'n3'),
    new CatalogEntry('r.three', 'GET', '/d', CatalogEntry::EVIDENCE_VERIFIED, CatalogEntry::RISK_READ, 's', 'n4'),
    new CatalogEntry('x.one', 'POST', '/e', CatalogEntry::EVIDENCE_UNSUPPORTED, CatalogEntry::RISK_WRITE, 's', 'n5'));
check('entry lookup and absence', $catalog->entry('r.one') !== null && $catalog->entry('missing') === null);
check('entries preserve insertion order', array_map(fn($e) => $e->operation, $catalog->entries()) === ['r.one', 'r.two', 'w.one', 'r.three', 'x.one']);

// -- Projection rules: metadata never enables execution --
$caps = [];
foreach ($catalog->capabilities() as $cap) {
    $caps[$cap->operation] = $cap;
}
check('documented projects unverified', $caps['r.one']->state === ProviderCapability::UNVERIFIED);
check('authenticated projects unverified', $caps['r.two']->state === ProviderCapability::UNVERIFIED);
check('documented note carries evidence grade', str_starts_with($caps['r.one']->note, 'documented:'));
check('verified write projects supported non-idempotent', $caps['w.one']->state === ProviderCapability::SUPPORTED && !$caps['w.one']->idempotent);
check('verified read projects supported idempotent', $caps['r.three']->state === ProviderCapability::SUPPORTED && $caps['r.three']->idempotent);
check('unsupported projects unsupported', $caps['x.one']->state === ProviderCapability::UNSUPPORTED);

// -- The 20i dataset: provenance and honest states --
$t = TwentyICatalog::catalog();
check('catalog provider id', $t->providerId === 'twenty-i');
check('catalog is substantial', count($t->entries()) >= 40);
$allHaveProvenance = true;
$anySupported = false;
foreach ($t->capabilities() as $cap) {
    if ($cap->state === ProviderCapability::SUPPORTED) {
        $anySupported = true;
    }
}
foreach ($t->entries() as $e) {
    if (trim($e->source) === '' || !preg_match('/(owner-upload L\d+-\d+|issue #65|docs\.20i\.com)/', $e->source)) {
        $allHaveProvenance = false;
    }
}
check('no entry is supported without verified evidence', !$anySupported);
check('every entry carries traceable provenance', $allHaveProvenance);
check('packages.list is authenticated not verified', $t->entry('packages.list')->evidence === CatalogEntry::EVIDENCE_AUTHENTICATED);
check('packages.list projects unverified', $catalog->capabilities() !== null && array_values(array_filter($t->capabilities(), fn($c) => $c->operation === 'packages.list'))[0]->state === ProviderCapability::UNVERIFIED);
check('hosting.delete is destructive documented', $t->entry('hosting.delete')->risk === CatalogEntry::RISK_DESTRUCTIVE);
check('vps.rebuild is destructive documented', $t->entry('vps.rebuild')->risk === CatalogEntry::RISK_DESTRUCTIVE);
check('backup restore is destructive documented', $t->entry('backup.web.restore')->risk === CatalogEntry::RISK_DESTRUCTIVE);
check('vps.vnc is credential read', $t->entry('vps.vnc')->risk === CatalogEntry::RISK_CREDENTIAL && $t->entry('vps.vnc')->semanticallyReadOnly());
check('GET-labelled power changes are not read-only', !$t->entry('vps.stop')->semanticallyReadOnly() && !$t->entry('vps.start')->semanticallyReadOnly());
check('POST lookup classified as read', $t->entry('cloudProviders.specs')->semanticallyReadOnly());
check('userStatus POST is a write not a lookup', !$t->entry('packages.userStatus')->semanticallyReadOnly());
check('wildcard templates retain placeholder braces', str_contains($t->entry('packages.get')->pathTemplate, '{packageId}'));
check('known gaps recorded', count(TwentyICatalog::knownGaps()) === 5 && str_contains(TwentyICatalog::knownGaps()[0], 'authCode'));
check('read operations exist for inventory scope', $t->entry('packages.list') !== null && $t->entry('packages.get') !== null && $t->entry('packages.types') !== null);
check('balance read documented', $t->entry('packages.balance')->evidence === CatalogEntry::EVIDENCE_DOCUMENTED);
check('domain dns get/set split present', $t->entry('domains.dns.get') !== null && $t->entry('domains.dns.set') !== null);
check('vnc credentials flagged credential not plain read', $t->entry('vps.vnc')->risk !== CatalogEntry::RISK_READ);

$passed = count(array_filter($checks, fn($c) => $c['passed']));
echo json_encode(['checks' => $checks, 'passed' => $passed === count($checks)], JSON_PRETTY_PRINT), "\n";
echo "$passed / " . count($checks) . " checks passed\n";
exit($passed === count($checks) ? 0 : 1);
