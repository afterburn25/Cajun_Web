<?php
declare(strict_types=1);

use CajunWebPro\Foundation\ReviewRequests\ReviewContact;
use CajunWebPro\Foundation\ReviewRequests\ReviewFinding;
use CajunWebPro\Foundation\ReviewRequests\ReviewInbox;
use CajunWebPro\Foundation\ReviewRequests\ReviewReport;
use CajunWebPro\Foundation\ReviewRequests\ReviewRequest;
use CajunWebPro\Foundation\ReviewRequests\ReviewStage;
use CajunWebPro\Foundation\ReviewRequests\WebsiteAddress;

require __DIR__ . '/../../foundation/review-requests/WebsiteAddress.php';
require __DIR__ . '/../../foundation/review-requests/ReviewContact.php';
require __DIR__ . '/../../foundation/review-requests/ReviewStage.php';
require __DIR__ . '/../../foundation/review-requests/ReviewRequest.php';
require __DIR__ . '/../../foundation/review-requests/ReviewFinding.php';
require __DIR__ . '/../../foundation/review-requests/ReviewReport.php';
require __DIR__ . '/../../foundation/review-requests/ReviewInbox.php';

$checks = 0;
$failures = [];

function check(string $label, bool $ok): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $label;
    }
}

function throws(string $label, callable $fn): void
{
    try {
        $fn();
        check($label, false);
    } catch (InvalidArgumentException) {
        check($label, true);
    }
}

function fixtureRequest(string $id, int $submittedAt = 1700000000, array $changes = []): ReviewRequest
{
    return new ReviewRequest(...array_replace([
        'id' => $id,
        'website' => new WebsiteAddress('fx-client.example.com'),
        'contact' => new ReviewContact('Fixture Owner', 'owner@fx-client.example.com', null, true),
        'goals' => 'More quote requests from the existing site.',
        'status' => ReviewStage::RECEIVED,
        'submittedAt' => $submittedAt,
        'notes' => null,
    ], $changes));
}

function fixtureReport(string $requestId = 'fx-001'): ReviewReport
{
    return new ReviewReport($requestId, 'fx-reviewer', 1700001000, [
        new ReviewFinding('Navigation hides the services page', 'Human reviewer observation.'),
    ]);
}

// --- WebsiteAddress normalization -------------------------------------------------

$a = new WebsiteAddress('example.com');
check('bare host normalizes to https', $a->url() === 'https://example.com/');
check('bare host keeps path', (new WebsiteAddress('example.com/a/B'))->url() === 'https://example.com/a/B');
check('scheme and host are lowercased', (new WebsiteAddress('HTTPS://ExAmple.COM/'))->url() === 'https://example.com/');
check('default port is dropped', (new WebsiteAddress('http://example.com:80/x'))->url() === 'http://example.com/x');
check('non-default port is kept', (new WebsiteAddress('https://example.com:8443/x'))->url() === 'https://example.com:8443/x');
check('query is preserved', (new WebsiteAddress('example.com/p?q=1'))->url() === 'https://example.com/p?q=1');
check('uppercase host trailing dot stripped', (new WebsiteAddress('EXAMPLE.COM.'))->host === 'example.com');

throws('blank address rejected', fn () => new WebsiteAddress('   '));
throws('non-http scheme rejected', fn () => new WebsiteAddress('ftp://example.com/'));
throws('javascript scheme rejected', fn () => new WebsiteAddress('javascript:alert(1)'));
throws('embedded credentials rejected', fn () => new WebsiteAddress('https://user:pass@example.com/'));
throws('fragment rejected', fn () => new WebsiteAddress('https://example.com/#top'));
throws('garbage host rejected', fn () => new WebsiteAddress('https://exa mple.com/'));
throws('overlong address rejected', fn () => new WebsiteAddress('https://' . str_repeat('a', 2100)));

// --- SSRF-readiness flags ----------------------------------------------------------

check('public name is a fetch candidate', (new WebsiteAddress('example.com'))->isFetchCandidate());
check('loopback name is not', !(new WebsiteAddress('localhost'))->isFetchCandidate());
check('localhost suffix is not', !(new WebsiteAddress('foo.localhost'))->isFetchCandidate());
check('.internal name is not', !(new WebsiteAddress('db.internal'))->isFetchCandidate());
check('private IPv4 is not', !(new WebsiteAddress('http://10.0.0.8/'))->isFetchCandidate());
check('loopback IPv4 is not', !(new WebsiteAddress('http://127.0.0.1/'))->isFetchCandidate());
check('link-local IPv4 is not', !(new WebsiteAddress('http://169.254.169.254/'))->isFetchCandidate());
check('ULA IPv6 is not', !(new WebsiteAddress('http://[fd00::1]/'))->isFetchCandidate());
check('public IPv4 remains a candidate', (new WebsiteAddress('http://8.8.8.8/'))->isFetchCandidate());

// --- ReviewContact ------------------------------------------------------------------

check('valid contact accepted', (new ReviewContact('A B', 'a@b.co'))->email === 'a@b.co');
throws('blank name rejected', fn () => new ReviewContact(' ', 'a@b.co'));
throws('bad email rejected', fn () => new ReviewContact('A B', 'not-an-email'));
throws('blank phone rejected', fn () => new ReviewContact('A B', 'a@b.co', '  '));
check('no consent by default', !(new ReviewContact('A B', 'a@b.co'))->consentToContact);

// --- ReviewRequest ------------------------------------------------------------------

throws('blank id rejected', fn () => fixtureRequest(' '));
throws('blank goals rejected', fn () => fixtureRequest('fx-x', 1, ['goals' => ' ']));
throws('unknown status rejected', fn () => fixtureRequest('fx-x', 1, ['status' => 'in-progress']));
throws('negative timestamp rejected', fn () => fixtureRequest('fx-x', -1));
throws('blank notes rejected', fn () => fixtureRequest('fx-x', 1, ['notes' => ' ']));
check('default status is received', fixtureRequest('fx-a')->status === ReviewStage::RECEIVED);
check('withStatus preserves fields', fixtureRequest('fx-a')->withStatus(ReviewStage::IN_REVIEW)->id === 'fx-a');

// --- ReviewStage --------------------------------------------------------------------

check('received can start review', ReviewStage::canTransition(ReviewStage::RECEIVED, ReviewStage::IN_REVIEW));
check('received can close', ReviewStage::canTransition(ReviewStage::RECEIVED, ReviewStage::CLOSED));
check('received cannot deliver', !ReviewStage::canTransition(ReviewStage::RECEIVED, ReviewStage::DELIVERED));
check('drafted can deliver', ReviewStage::canTransition(ReviewStage::REPORT_DRAFTED, ReviewStage::DELIVERED));
check('drafted can return to review', ReviewStage::canTransition(ReviewStage::REPORT_DRAFTED, ReviewStage::IN_REVIEW));
check('delivered can only close', ReviewStage::allowedFrom(ReviewStage::DELIVERED) === [ReviewStage::CLOSED]);
check('closed is terminal', ReviewStage::allowedFrom(ReviewStage::CLOSED) === []);
check('self transition rejected', !ReviewStage::canTransition(ReviewStage::IN_REVIEW, ReviewStage::IN_REVIEW));
check('unknown stage not known', !ReviewStage::isKnown('nonsense'));

// --- ReviewFinding ------------------------------------------------------------------

check('human finding needs no timestamp', (new ReviewFinding('T', 'D'))->evidence === ReviewFinding::EVIDENCE_HUMAN);
throws('blank title rejected', fn () => new ReviewFinding(' ', 'D'));
throws('blank detail rejected', fn () => new ReviewFinding('T', ' '));
throws('unknown evidence rejected', fn () => new ReviewFinding('T', 'D', 'guess'));
throws('lab data needs timestamp', fn () => new ReviewFinding('T', 'D', ReviewFinding::EVIDENCE_LAB, null, 'labtool'));
throws('lab data needs source', fn () => new ReviewFinding('T', 'D', ReviewFinding::EVIDENCE_LAB, 1700000000));
throws('field data needs timestamp', fn () => new ReviewFinding('T', 'D', ReviewFinding::EVIDENCE_FIELD));
throws('absent data needs source', fn () => new ReviewFinding('T', 'D', ReviewFinding::EVIDENCE_ABSENT, 1700000000));
check('lab finding ok with both', (new ReviewFinding('T', 'D', ReviewFinding::EVIDENCE_LAB, 1700000000, 'lighthouse-12'))->source === 'lighthouse-12');
check('absence is declared', (new ReviewFinding('T', 'D', ReviewFinding::EVIDENCE_ABSENT, 1, 'none'))->declaresAbsence());
check('human finding is not absence', !(new ReviewFinding('T', 'D'))->declaresAbsence());

// --- ReviewReport -------------------------------------------------------------------

throws('report needs request id', fn () => new ReviewReport(' ', 'fx-reviewer', 1, [new ReviewFinding('T', 'D')]));
throws('report needs reviewer', fn () => new ReviewReport('fx-1', ' ', 1, [new ReviewFinding('T', 'D')]));
throws('report needs a finding', fn () => new ReviewReport('fx-1', 'fx-reviewer', 1, []));
throws('report rejects non-findings', fn () => new ReviewReport('fx-1', 'fx-reviewer', 1, ['T']));
throws('delivery cannot predate report', fn () => fixtureReport()->withDelivery(0)->requestId && new ReviewReport('fx-001', 'r', 2000, [new ReviewFinding('T', 'D')], 1000));
check('report stores findings in order', fixtureReport()->findings[0]->title === 'Navigation hides the services page');
check('undelivered report has null deliveredAt', fixtureReport()->deliveredAt === null);
check('withDelivery keeps findings', fixtureReport()->withDelivery(1700002000)->deliveredAt === 1700002000);

// --- ReviewInbox --------------------------------------------------------------------

$inbox = new ReviewInbox([
    fixtureRequest('fx-b', 1700000100),
    fixtureRequest('fx-a', 1700000100),
    fixtureRequest('fx-c', 1699999999),
]);
check('ordering is submittedAt then id', array_map(fn (ReviewRequest $r) => $r->id, $inbox->all()) === ['fx-c', 'fx-a', 'fx-b']);

$orderA = new ReviewInbox([fixtureRequest('fx-1', 5), fixtureRequest('fx-2', 5)]);
$orderB = new ReviewInbox([fixtureRequest('fx-2', 5), fixtureRequest('fx-1', 5)]);
check('input order does not change ordering', serialize(array_map(fn (ReviewRequest $r) => $r->id, $orderA->all())) === serialize(array_map(fn (ReviewRequest $r) => $r->id, $orderB->all())));

throws('duplicate ids rejected', fn () => new ReviewInbox([fixtureRequest('fx-d'), fixtureRequest('fx-d')]));
throws('non-requests rejected', fn () => new ReviewInbox(['fx-d']));
throws('add duplicate rejected', fn () => (new ReviewInbox([fixtureRequest('fx-d')]))->add(fixtureRequest('fx-d')));

$inbox2 = new ReviewInbox([
    fixtureRequest('fx-1', 1, ['status' => ReviewStage::IN_REVIEW]),
    fixtureRequest('fx-2', 2),
]);
check('status filter returns matches', array_map(fn (ReviewRequest $r) => $r->id, $inbox2->withStatus(ReviewStage::IN_REVIEW)) === ['fx-1']);
check('status filter empty when none', $inbox2->withStatus(ReviewStage::DELIVERED) === []);
throws('unknown status filter rejected', fn () => $inbox2->withStatus('bogus'));
check('byId resolves', $inbox2->byId('fx-2')?->goals === 'More quote requests from the existing site.');
check('byId misses cleanly', $inbox2->byId('fx-9') === null);

// --- Pipeline transitions ------------------------------------------------------------

$inbox3 = new ReviewInbox();
$r = fixtureRequest('fx-100');
check('received to in-review', $inbox3->transition($r, ReviewStage::IN_REVIEW)->status === ReviewStage::IN_REVIEW);
$drafted = $inbox3->transition($inbox3->transition($r, ReviewStage::IN_REVIEW), ReviewStage::REPORT_DRAFTED);
throws('delivered needs its report', fn () => $inbox3->transition($drafted, ReviewStage::DELIVERED));
throws('delivered rejects wrong report', fn () => $inbox3->transition($drafted, ReviewStage::DELIVERED, fixtureReport('fx-other')));
$noConsent = fixtureRequest('fx-101', 1, [
    'status' => ReviewStage::REPORT_DRAFTED,
    'contact' => new ReviewContact('No Consent', 'n@fx.example.com', null, false),
]);
throws('delivered requires consent', fn () => $inbox3->transition($noConsent, ReviewStage::DELIVERED, fixtureReport('fx-101')));
check('delivered ok with report and consent', $inbox3->transition($drafted, ReviewStage::DELIVERED, fixtureReport('fx-100'))->status === ReviewStage::DELIVERED);
$closed = $inbox3->transition($inbox3->transition($drafted, ReviewStage::DELIVERED, fixtureReport('fx-100')), ReviewStage::CLOSED);
throws('closed cannot reopen', fn () => $inbox3->transition($closed, ReviewStage::IN_REVIEW));
throws('illegal jump rejected', fn () => $inbox3->transition(fixtureRequest('fx-200'), ReviewStage::DELIVERED, fixtureReport('fx-200')));

printf("%d checks, %d failures\n", $checks, count($failures));
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: {$failure}\n");
}
exit($failures === [] ? 0 : 1);
