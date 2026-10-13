<?php
declare(strict_types=1);

/**
 * Synthetic A-07 email-template contract checks. Fixture templates only —
 * no compose UI, no sending, no persistence.
 */

use CajunWebPro\Foundation\EmailTemplates\EmailTemplate;
use CajunWebPro\Foundation\EmailTemplates\PlaceholderRegistry;

require __DIR__ . '/../../foundation/email-templates/PlaceholderRegistry.php';
require __DIR__ . '/../../foundation/email-templates/EmailTemplate.php';

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

function registry(): PlaceholderRegistry
{
    return new PlaceholderRegistry([
        'client_name' => 'Client display name',
        'project_name' => 'Project title',
        'support_link' => 'Portal support URL',
    ]);
}

function tpl(array $changes = []): EmailTemplate
{
    return EmailTemplate::draft(...array_replace([
        'id' => 'tpl-1',
        'name' => 'Welcome',
        'subject' => 'Welcome {{client_name}}',
        'body' => 'Hi {{client_name}}, your {{project_name}} is underway. Reach us at {{support_link}}.',
        'visibility' => EmailTemplate::VISIBILITY_SHARED,
        'ownerId' => 'staff-amy',
        'transactional' => false,
        'updatedBy' => 'staff-amy',
        'updatedAt' => 1700000000,
        'registry' => registry(),
    ], $changes));
}

// --- PlaceholderRegistry ---------------------------------------------------

check('registry allows listed name', registry()->allows('client_name'));
check('registry rejects unknown', !registry()->allows('raw_html'));
check('registry lists sorted', array_keys(registry()->all()) === ['client_name', 'project_name', 'support_link']);
throws('empty allowlist rejected', fn () => new PlaceholderRegistry([]));
throws('bad placeholder name rejected', fn () => new PlaceholderRegistry(['Client Name' => 'x']));
throws('empty description rejected', fn () => new PlaceholderRegistry(['ok_name' => ' ']));

// --- Construction / placeholder allowlist ----------------------------------

check('template constructs as draft', tpl()->status === EmailTemplate::DRAFT);
check('placeholders enumerated in order', tpl()->placeholders() === ['client_name', 'project_name', 'support_link']);
throws('unknown placeholder rejected', fn () => tpl(['body' => 'Pay {{invoice_total}} now']));
throws('unknown subject placeholder rejected', fn () => tpl(['subject' => 'Hi {{secret}}']));
check('non-allowlisted braces ignored', tpl(['subject' => 'Plain', 'body' => 'Literal {note} stays'])->placeholders() === []);
throws('empty subject rejected', fn () => tpl(['subject' => ' ']));
throws('empty body rejected', fn () => tpl(['body' => '']));
throws('bad visibility rejected', fn () => tpl(['visibility' => 'public']));

// --- Lifecycle ---------------------------------------------------------------

$t = tpl();
$active = $t->activate('staff-amy', 1700000100);
check('activate reaches active', $active->status === EmailTemplate::ACTIVE);
throws('double activate rejected', fn () => $active->activate('staff-amy', 1700000200));
$archived = $active->archive('staff-amy', 1700000300);
check('archive retains record', $archived->status === EmailTemplate::ARCHIVED && $archived->subject !== '');
throws('archive is terminal for edits', fn () => $archived->edit('S', 'B', 'x', 1, registry()));
throws('double archive rejected', fn () => $archived->archive('staff-amy', 1700000400));

// Edit produces a fresh draft, never mutates the active copy
$edited = $active->edit('New subject {{client_name}}', 'New body {{project_name}}', 'staff-ben', 1700000500, registry());
check('edit returns draft', $edited->status === EmailTemplate::DRAFT);
check('edit records new author', $edited->updatedBy === 'staff-ben');
check('active copy unchanged', $active->subject === 'Welcome {{client_name}}');
throws('edit rejects unknown placeholder', fn () => $active->edit('S {{bogus}}', 'B', 'x', 1, registry()));

// Duplicate → personal draft with new identity
$dup = $active->duplicate('tpl-2', 'Welcome (copy)', 'staff-ben', 1700000600);
check('duplicate is personal draft', $dup->status === EmailTemplate::DRAFT && $dup->visibility === EmailTemplate::VISIBILITY_PERSONAL && $dup->ownerId === 'staff-ben');
check('duplicate keeps content', $dup->subject === $active->subject);

// --- Insertable / visibility -------------------------------------------------

check('shared active insertable by anyone', $active->insertableBy('staff-ben'));
check('draft not insertable', !tpl()->insertableBy('staff-amy'));
check('archived not insertable', !$archived->insertableBy('staff-amy'));
check('transactional not insertable', !tpl(['transactional' => true])->activate('a', 1)->insertableBy('staff-amy'));
check('personal insertable only by owner', tpl(['visibility' => EmailTemplate::VISIBILITY_PERSONAL])->activate('a', 1)->insertableBy('staff-amy'));
check('personal not insertable by others', !tpl(['visibility' => EmailTemplate::VISIBILITY_PERSONAL])->activate('a', 1)->insertableBy('staff-ben'));

// --- Preview -------------------------------------------------------------------

$p = $active->preview(['client_name' => 'Acme Co', 'project_name' => 'Site', 'support_link' => 'portal.example.invalid']);
check('preview resolves all', $p['unresolved'] === [] && !str_contains($p['body'], '{{'));
check('preview substitutes subject', str_contains($p['subject'], 'Acme Co'));

$partial = $active->preview(['client_name' => 'Acme Co']);
check('unresolved placeholders named', $partial['unresolved'] === ['project_name', 'support_link']);
check('raw placeholders survive unresolved', str_contains($partial['body'], '{{project_name}}'));

check('preview never mutates template', $active->subject === 'Welcome {{client_name}}');

// --- Result -------------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks email-template checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated template/placeholder contract only; no compose UI, sending or persistence tested." . PHP_EOL;
