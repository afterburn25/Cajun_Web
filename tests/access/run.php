<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Access\AccessContext;
use CajunWebPro\Foundation\Access\MailboxGrant;
use CajunWebPro\Foundation\Access\PermissionPolicy;

require __DIR__ . '/../../foundation/access/AuthorizationVersion.php';
require __DIR__ . '/../../foundation/access/MailboxGrant.php';
require __DIR__ . '/../../foundation/access/AccessContext.php';
require __DIR__ . '/../../foundation/access/PermissionPolicy.php';

$checks = 0;
$failures = [];
$policy = new PermissionPolicy();

function context(array $changes = []): AccessContext
{
    return new AccessContext(...array_replace([
        'actorId' => 'staff-1',
        'principalType' => 'staff',
        'role' => 'administrator',
        'active' => true,
        'authorizationVersion' => str_repeat('a', 32),
        'sessionAuthorizationVersion' => str_repeat('a', 32),
        'permissions' => [],
        'mailboxes' => [],
    ], $changes));
}

function expect(string $name, bool $expected, bool $actual): void
{
    global $checks, $failures;
    $checks++;
    if ($actual !== $expected) {
        $failures[] = $name . ': expected ' . ($expected ? 'allow' : 'deny');
    }
}

function rejects(string $name, callable $action): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid context was accepted';
    } catch (InvalidArgumentException) {
        // Expected failure; other exceptions must fail the runner.
    }
}

$globals = [
    'website.manage', 'clients.manage','catalog.manage', 'payments.manage', 'support.login', 'support.write', 'support.download', 'support.tickets',
    'staff.manage', 'staff.permissions.manage', 'mail.accounts.manage',
    'mail.templates.manage', 'billing.settings.manage', 'hosting.settings.manage',
];
foreach ($globals as $permission) {
    expect("new admin denied $permission", false, $policy->allows(context(), $permission));
    expect("owner allowed $permission", true, $policy->allows(context(['role' => 'owner']), $permission));
    expect("explicit staff grant $permission", true, $policy->allows(context([
        'role' => 'staff', 'permissions' => [$permission],
    ]), $permission));
    expect("global permission rejects mailbox scope $permission", false, $policy->allows(
        context(['role' => 'owner']), $permission, 'mailbox-a',
    ));
}

$fullAccess = context([
    'role' => 'owner',
    'mailboxes' => [new MailboxGrant('mailbox-a', true, true, true)],
]);
$invalidActors = [
    'empty identity' => ['actorId' => ''],
    'blank identity' => ['actorId' => '  '],
    'customer posing as owner' => ['principalType' => 'customer'],
    'unknown principal type' => ['principalType' => 'service'],
    'inactive owner' => ['active' => false],
    'unknown role' => ['role' => 'superuser'],
    'wrong-case role' => ['role' => 'Owner'],
    'revoked session' => ['authorizationVersion' => str_repeat('b', 32)],
    'future session revision' => ['sessionAuthorizationVersion' => str_repeat('b', 32)],
    'missing revisions' => ['authorizationVersion' => '', 'sessionAuthorizationVersion' => ''],
    'malformed revisions' => ['authorizationVersion' => 'not-a-token', 'sessionAuthorizationVersion' => 'not-a-token'],
];
foreach ($invalidActors as $label => $changes) {
    $actor = context(array_replace([
        'role' => 'owner',
        'permissions' => $globals,
        'mailboxes' => [new MailboxGrant('mailbox-a', true, true, true)],
    ], $changes));
    foreach ($globals as $permission) {
        expect("$label denied $permission", false, $policy->allows($actor, $permission));
    }
    expect("$label cannot read mail", false, $policy->allows($actor, 'mail.read', 'mailbox-a'));
    expect("$label cannot send mail", false, $policy->allows($actor, 'mail.send', 'mailbox-a'));
}

expect('unknown permission denied to owner', false, $policy->allows($fullAccess, 'staff.delete_everything'));
expect('empty permission denied to owner', false, $policy->allows($fullAccess, ''));
expect('wildcard is not a permission', false, $policy->allows($fullAccess, '*'));
expect('wildcard grant gives no rights', false, $policy->allows(context(['permissions' => ['*']]), 'staff.manage'));
expect('mailbox grant alone gives no read rights', false, $policy->allows(context([
    'mailboxes' => [new MailboxGrant('mailbox-a', true, true, true)],
]), 'mail.read', 'mailbox-a'));
expect('mail capability alone gives no mailbox rights', false, $policy->allows(context([
    'permissions' => ['mail.read'],
]), 'mail.read', 'mailbox-a'));
expect('owner has no implicit mailbox access', false, $policy->allows(context(['role' => 'owner']), 'mail.read', 'mailbox-a'));
expect('owner explicit mailbox read', true, $policy->allows($fullAccess, 'mail.read', 'mailbox-a'));
expect('owner explicit mailbox send', true, $policy->allows($fullAccess, 'mail.send', 'mailbox-a'));
expect('wrong mailbox denied', false, $policy->allows($fullAccess, 'mail.read', 'mailbox-b'));
expect('missing mailbox denied', false, $policy->allows($fullAccess, 'mail.read'));
expect('empty mailbox denied', false, $policy->allows($fullAccess, 'mail.read', ''));
expect('mailbox IDs are case sensitive', false, $policy->allows($fullAccess, 'mail.read', 'MAILBOX-A'));

$reader = context([
    'permissions' => ['mail.read', 'mail.send'],
    'mailboxes' => [new MailboxGrant('mailbox-a', true, false, true)],
]);
expect('read-only mailbox can be read', true, $policy->allows($reader, 'mail.read', 'mailbox-a'));
expect('read-only mailbox cannot send', false, $policy->allows($reader, 'mail.send', 'mailbox-a'));
$sender = context([
    'permissions' => ['mail.read', 'mail.send'],
    'mailboxes' => [new MailboxGrant('mailbox-a', false, true, true)],
]);
expect('send-only mailbox can send', true, $policy->allows($sender, 'mail.send', 'mailbox-a'));
expect('send-only mailbox cannot read', false, $policy->allows($sender, 'mail.read', 'mailbox-a'));
foreach (['mail.read', 'mail.send'] as $permission) {
    expect("disabled mailbox denied $permission even to owner", false, $policy->allows(context([
        'role' => 'owner', 'mailboxes' => [new MailboxGrant('mailbox-a', true, true, false)],
    ]), $permission, 'mailbox-a'));
}
expect('account configuration does not grant mail access', false, $policy->allows(context([
    'permissions' => ['mail.accounts.manage'], 'mailboxes' => [new MailboxGrant('mailbox-a', true, true, true)],
]), 'mail.read', 'mailbox-a'));
expect('staff management does not grant permission management', false, $policy->allows(context([
    'permissions' => ['staff.manage'],
]), 'staff.permissions.manage'));
expect('mail access does not grant billing settings', false, $policy->allows($reader, 'billing.settings.manage'));
expect('removed mailbox grant revokes access', false, $policy->allows(context([
    'permissions' => ['mail.read', 'mail.send'], 'mailboxes' => [],
]), 'mail.read', 'mailbox-a'));
expect('removed capability revokes access', false, $policy->allows(context([
    'permissions' => [], 'mailboxes' => [new MailboxGrant('mailbox-a', true, true, true)],
]), 'mail.read', 'mailbox-a'));
expect('new session can use current permissions', true, $policy->allows(context([
    'authorizationVersion' => str_repeat('b', 32), 'sessionAuthorizationVersion' => str_repeat('b', 32), 'permissions' => ['staff.manage'],
]), 'staff.manage'));
expect('opaque numeric-looking mailbox IDs are not coerced', false, $policy->allows(context([
    'role' => 'owner', 'mailboxes' => [new MailboxGrant('01', true, true, true)],
]), 'mail.read', '1'));

rejects('empty mailbox identifier', fn() => new MailboxGrant('  ', true, true, true));
rejects('malformed permission list', fn() => context(['permissions' => [true]]));
rejects('malformed mailbox grant list', fn() => context(['mailboxes' => [['mailboxId' => 'mailbox-a']]]));
rejects('duplicate contradictory mailbox grants', fn() => context([
    'mailboxes' => [new MailboxGrant('mailbox-a', true, true, true), new MailboxGrant('mailbox-a', false, false, true)],
]));

expect('numeric-looking opaque tokens must not compare loosely', false, $policy->allows(context([
    'role' => 'owner', 'authorizationVersion' => '0e' . str_repeat('1', 30),
    'sessionAuthorizationVersion' => '0e' . str_repeat('2', 30),
]), 'staff.manage'));
expect('actual 32-character source token matches', true, $policy->allows(context([
    'role' => 'owner', 'authorizationVersion' => 'af192d64082946f2aefee1e3176a614c',
    'sessionAuthorizationVersion' => 'af192d64082946f2aefee1e3176a614c',
]), 'staff.manage'));
expect('64-character token remains opaque', true, $policy->allows(context([
    'role' => 'owner', 'authorizationVersion' => str_repeat('c', 64),
    'sessionAuthorizationVersion' => str_repeat('c', 64),
]), 'staff.manage'));
expect('token with appended whitespace is denied', false, $policy->allows(context([
    'role' => 'owner', 'sessionAuthorizationVersion' => str_repeat('a', 32) . ' ',
]), 'staff.manage'));

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks permission policy checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated decisions only; no login, database, session-revocation delivery or application integration tested." . PHP_EOL;
