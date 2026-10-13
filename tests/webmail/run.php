<?php
declare(strict_types=1);

/**
 * Synthetic A-06 webmail contract checks. Fixture profiles/messages only —
 * no IMAP/SMTP transport, credentials, persistence or UI.
 */

use CajunWebPro\Foundation\Webmail\ConnectionResult;
use CajunWebPro\Foundation\Webmail\MailboxGrant;
use CajunWebPro\Foundation\Webmail\MailboxProfile;
use CajunWebPro\Foundation\Webmail\MailMessage;
use CajunWebPro\Foundation\Webmail\UnifiedInbox;

require __DIR__ . '/../../foundation/webmail/MailboxProfile.php';
require __DIR__ . '/../../foundation/webmail/MailboxGrant.php';
require __DIR__ . '/../../foundation/webmail/ConnectionResult.php';
require __DIR__ . '/../../foundation/webmail/MailMessage.php';
require __DIR__ . '/../../foundation/webmail/UnifiedInbox.php';

$checks = 0;
$failures = [];

function check(string $name, bool $actual): void
{
    global $checks, $failures;
    $checks++;
    if (!$actual) {
        $failures[] = $name;
    }
}

function throws(string $name, callable $action): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid action was accepted';
    } catch (InvalidArgumentException) {
    }
}

function profile(array $over = []): MailboxProfile
{
    return new MailboxProfile(...array_replace([
        'id' => 'mb-1',
        'label' => 'Support mailbox',
        'ownerStaffId' => 'staff-amy',
        'imapHost' => 'imap.example.invalid',
        'imapPort' => 993,
        'imapEncryption' => 'ssl',
        'smtpHost' => 'smtp.example.invalid',
        'smtpPort' => 587,
        'smtpEncryption' => 'starttls',
        'credentialRef' => 'cred-ref-abc',
        'status' => MailboxProfile::STATUS_CONFIGURED,
    ], $over));
}

function msg(array $over = []): MailMessage
{
    return new MailMessage(...array_replace([
        'uid' => 1,
        'mailboxId' => 'mb-1',
        'folder' => 'INBOX',
        'from' => 'client@example.invalid',
        'subject' => 'Question',
        'date' => 1000,
        'flags' => [],
    ], $over));
}

// ── MailboxProfile validation ────────────────────────────────────

$p = profile();
check('valid profile constructs', $p->usable());
check('disabled profile not usable', !profile(['status' => 'disabled'])->usable());
throws('unknown status rejected', fn () => profile(['status' => 'broken']));
throws('plaintext imap refused', fn () => profile(['imapEncryption' => 'none']));
throws('plaintext smtp refused', fn () => profile(['smtpEncryption' => 'plain']));
check('ssl accepted', profile(['smtpEncryption' => 'ssl', 'smtpPort' => 465])->smtpPort === 465);
throws('imap port out of range', fn () => profile(['imapPort' => 0]));
throws('imap port above range', fn () => profile(['imapPort' => 70000]));
throws('host with spaces rejected', fn () => profile(['imapHost' => 'imap example.com']));
throws('empty host rejected', fn () => profile(['imapHost' => '']));
throws('empty credential ref rejected', fn () => profile(['credentialRef' => ' ']));
check('credential is an opaque ref only', $p->credentialRef === 'cred-ref-abc' && !property_exists($p, 'password'));

// ── MailboxGrant ─────────────────────────────────────────────────

$g = new MailboxGrant('gr-1', 'mb-1', 'staff-ben', 'staff-amy', 100);
check('fresh grant active', $g->active(200));
check('grant records issuer', $g->issuedBy === 'staff-amy');
$expiring = new MailboxGrant('gr-2', 'mb-1', 'staff-cam', 'staff-amy', 100, 500);
check('grant active before expiry', $expiring->active(400));
check('grant inactive at expiry', !$expiring->active(500));
throws('expiry before issue rejected', fn () => new MailboxGrant('g', 'mb-1', 's', 'o', 500, 100));
$g->revoke(300);
check('revoked grant inactive', !$g->active(100));
check('revocation timestamped', $g->revokedAt() === 300);
throws('double revocation rejected', fn () => $g->revoke(400));
throws('empty staff rejected', fn () => new MailboxGrant('g', 'mb-1', '', 'o', 1));

// ── ConnectionResult ─────────────────────────────────────────────

$ok = ConnectionResult::success('mb-1', 'imap', 700);
check('success reports ok', $ok->ok() && $ok->checkedAt === 700 && $ok->error === null);
$bad = ConnectionResult::failure('mb-1', 'smtp', 710, 'auth refused');
check('failure carries error', !$bad->ok() && $bad->error === 'auth refused');
$unt = ConnectionResult::untested('mb-1', 'imap');
check('untested is honest', !$unt->ok() && $unt->outcome === 'untested' && $unt->checkedAt === null);
throws('failure needs an error', fn () => ConnectionResult::failure('mb-1', 'smtp', 1, ''));
throws('unknown target rejected', fn () => ConnectionResult::success('mb-1', 'pop3', 1));

// ── MailMessage ──────────────────────────────────────────────────

$m = msg();
check('new message unread', $m->isUnread());
$seen = $m->markSeen();
check('markSeen returns flagged copy', !$seen->isUnread() && $m->isUnread());
check('markUnread restores', $seen->markUnread()->isUnread());
check('flag query works', $seen->hasFlag('seen') && !$seen->hasFlag('flagged'));
$flagged = $m->withFlag('flagged', true);
check('withFlag adds', $flagged->hasFlag('flagged'));
check('withFlag removes', $flagged->withFlag('flagged', false)->hasFlag('flagged') === false);
throws('unknown flag rejected', fn () => $m->withFlag('starred', true));
throws('uid below 1 rejected', fn () => msg(['uid' => 0]));
throws('empty folder rejected', fn () => msg(['folder' => '']));
check('no body field exists', !property_exists($m, 'body'));

// ── UnifiedInbox privacy ─────────────────────────────────────────

$grant = new MailboxGrant('gr-1', 'mb-1', 'staff-ben', 'staff-amy', 0);
$inbox = new UnifiedInbox('staff-ben', [$grant], [
    msg(['uid' => 2, 'date' => 3000]),
    msg(['uid' => 1, 'date' => 1000]),
    msg(['uid' => 3, 'date' => 2000, 'flags' => ['seen']]),
], 50);

check('inbox newest-first', $inbox->page()[0]->uid === 2);
check('unread count across grant', $inbox->unreadCount() === 2);
check('per-mailbox unread', $inbox->unreadIn('mb-1') === 2);
check('page slices', count($inbox->page(1, 1)) === 1 && $inbox->page(1, 1)[0]->uid === 3);
throws('page requires limit >= 1', fn () => $inbox->page(0, 0));

throws('message from ungranted mailbox rejected', fn () => new UnifiedInbox('staff-ben', [$grant], [
    msg(['mailboxId' => 'mb-2']),
], 50));
throws('message via revoked grant rejected', fn () => new UnifiedInbox('staff-ben', [
    (static function () { $x = new MailboxGrant('g', 'mb-1', 'staff-ben', 'o', 0); $x->revoke(10); return $x; })(),
], [msg()], 50));
throws('message via expired grant rejected', fn () => new UnifiedInbox('staff-ben', [
    new MailboxGrant('g', 'mb-1', 'staff-ben', 'o', 0, 10),
], [msg()], 50));
throws('grant for another staff does not authorize', fn () => new UnifiedInbox('staff-ben', [
    new MailboxGrant('g', 'mb-1', 'staff-cam', 'o', 0),
], [msg()], 50));

$empty = new UnifiedInbox('staff-ben', [], [], 50);
check('empty inbox is zero', $empty->total() === 0 && $empty->unreadCount() === 0);

// ── Summary ──────────────────────────────────────────────────────

if ($failures) {
    fwrite(STDERR, "FAIL: " . count($failures) . " of {$checks} checks\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "PASS: {$checks} webmail contract checks on PHP " . PHP_VERSION . "\n";
echo "Scope: isolated model only; no IMAP/SMTP, credentials, persistence or UI tested.\n";
