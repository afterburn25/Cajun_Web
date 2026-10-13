<?php
declare(strict_types=1);

use CajunWebPro\Foundation\MailTransport\InboundSync;
use CajunWebPro\Foundation\MailTransport\ImapParser;
use CajunWebPro\Foundation\MailTransport\ImapSession;
use CajunWebPro\Foundation\MailTransport\MailSocket;
use CajunWebPro\Foundation\MailTransport\MailTransportException;
use CajunWebPro\Foundation\Webmail\ConnectionResult;
use CajunWebPro\Foundation\Webmail\MailboxProfile;

foreach (['MailSocket','MailTransportException','TlsMailSocket','ImapParser','ImapSession','InboundSync'] as $f) {
    require __DIR__ . '/../../foundation/mail-transport/' . $f . '.php';
}
// InboundSync pulls in the webmail contract classes it bridges to.

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

function throwsKind(string $label, string $kind, callable $fn): void
{
    try {
        $fn();
        check($label, false);
    } catch (MailTransportException $e) {
        check($label, $e->kind() === $kind);
    }
}

/** Scripted fake socket: feeds lines and captures writes. */
final class ScriptSocket implements MailSocket
{
    public array $sent = [];
    public bool $tlsUpgraded = false;
    private string $inbound;
    private int $pos = 0;

    public function __construct(string $transcript)
    {
        $this->inbound = $transcript;
    }

    public function readLine(int $maxBytes = 8192): string
    {
        $end = strpos($this->inbound, "\n", $this->pos);
        if ($end === false) {
            throw new MailTransportException(MailTransportException::CLOSED, 'script exhausted');
        }
        $line = substr($this->inbound, $this->pos, $end - $this->pos);
        $this->pos = $end + 1;
        return rtrim($line, "\r");
    }

    public function readBytes(int $count): string
    {
        $out = substr($this->inbound, $this->pos, $count);
        if (strlen($out) !== $count) {
            throw new MailTransportException(MailTransportException::CLOSED, 'literal underrun');
        }
        $this->pos += $count;
        return $out;
    }

    public function write(string $data): void
    {
        $this->sent[] = $data;
    }

    public function startTls(): void
    {
        $this->tlsUpgraded = true;
    }

    public function close(): void {}

    public function isOpen(): bool
    {
        return $this->pos < strlen($this->inbound);
    }
}

function profile(array $over = []): MailboxProfile
{
    return new MailboxProfile(...array_replace([
        'id' => 'mbx-1', 'label' => 'Studio inbox', 'ownerStaffId' => 'st-1',
        'imapHost' => 'imap.example.invalid', 'imapPort' => 993, 'imapEncryption' => 'ssl',
        'smtpHost' => 'smtp.example.invalid', 'smtpPort' => 465, 'smtpEncryption' => 'ssl',
        'credentialRef' => 'ref-1', 'status' => 'configured',
    ], $over));
}

// --- parser ---------------------------------------------------------------

$sock = new ScriptSocket('');
check('simple atoms parse', ImapParser::parse($sock, '* SEARCH 1 2 3') === ['*', 'SEARCH', '1', '2', '3']);
check('list parses', ImapParser::parse($sock, '(FLAGS (\\Seen \\Flagged))') === [['FLAGS', ['\Seen', '\Flagged']]]);
check('NIL is null', ImapParser::parse($sock, '(A NIL B)') === [['A', null, 'B']]);
check('quoted string parses', ImapParser::parse($sock, '* OK "Ready." done') === ['*', 'OK', 'Ready.', 'done']);
check('escaped quote parses', ImapParser::parse($sock, '* OK "a\\"b"') === ['*', 'OK', 'a"b']);

$lit2 = new ScriptSocket("abcde FLAGS (\\Seen))\r\n");
$parsed = ImapParser::parse($lit2, '* 1 FETCH (BODY[HEADER] {5}');
check('literal bytes land in list', is_array($parsed[3]) && $parsed[3][0] === 'BODY[HEADER]' && $parsed[3][1] === 'abcde' && $parsed[3][2] === 'FLAGS');

throwsKind('unterminated string rejected', 'protocol', fn () => ImapParser::parse(new ScriptSocket(''), '* OK "broken'));
throwsKind('stray paren rejected', 'protocol', fn () => ImapParser::parse(new ScriptSocket(''), ') junk'));
throwsKind('oversized literal refused', 'protocol', fn () => ImapParser::parse(new ScriptSocket(''), '* 1 FETCH (X {99999999}'));

// --- session ---------------------------------------------------------------

$ok = new ScriptSocket(
    "* OK IMAP4rev1 ready\r\n" .
    "* CAPABILITY IMAP4rev1 UIDPLUS\r\n" .
    "a1 OK CAPABILITY done\r\n" .
    "a2 OK LOGIN done\r\n"
);
$ses = new ImapSession($ok, true);
$ses->greeting();
check('ssl session skips starttls', !$ok->tlsUpgraded);
check('capability list returned', $ses->capability() === ['IMAP4REV1', 'UIDPLUS']);
$ses->login('user@example.invalid', 'synthetic-pass');
check('login writes quoted credentials', $ok->sent[1] === "a2 LOGIN \"user@example.invalid\" \"synthetic-pass\"\r\n");

$bad = new ScriptSocket("* OK ready\r\n" . "a1 NO invalid credentials\r\n");
$sesBad = new ImapSession($bad, true);
$sesBad->greeting();
throwsKind('login NO maps to auth', 'auth', fn () => $sesBad->login('u', 'p'));

$plain = new ScriptSocket(
    "* OK ready\r\n" .
    "* CAPABILITY IMAP4rev1 STARTTLS\r\n" .
    "a1 OK done\r\n" .
    "a2 OK TLS started\r\n" .
    "a3 OK LOGIN done\r\n"
);
$sesTls = new ImapSession($plain, false);
$sesTls->greeting();
$sesTls->startTls();
check('starttls upgrades socket', $plain->tlsUpgraded);
$sesTls->login('u', 'p');
check('login after tls works', $plain->sent[2] === "a3 LOGIN \"u\" \"p\"\r\n");

$noStart = new ScriptSocket("* OK ready\r\n* CAPABILITY IMAP4rev1\r\na1 OK done\r\n");
$sesNoTls = new ImapSession($noStart, false);
$sesNoTls->greeting();
throwsKind('missing STARTTLS refuses login path', 'tls', fn () => $sesNoTls->startTls());

$unenc = new ScriptSocket("* OK ready\r\n");
$sesUn = new ImapSession($unenc, false);
$sesUn->greeting();
throwsKind('plaintext login refused', 'refused', fn () => $sesUn->login('u', 'p'));

// Examine + search + fetch on a scripted mailbox.
$mbox = new ScriptSocket(
    "* OK ready\r\n" .
    "a1 OK LOGIN done\r\n" .
    "* 2 EXISTS\r\n* OK [UIDVALIDITY 7777] valid\r\na2 OK EXAMINE done\r\n" .
    "* SEARCH 11 12\r\na3 OK SEARCH done\r\n" .
    "* 1 FETCH (UID 11 FLAGS (\\Seen) INTERNALDATE \"13-Oct-2026 10:00:00 +0000\" ENVELOPE (NIL \"Weekly report\" ((\"Boss\" NIL \"boss\" \"example.invalid\")) NIL NIL NIL NIL NIL NIL NIL))\r\n" .
    "* 2 FETCH (UID 12 FLAGS () INTERNALDATE \"13-Oct-2026 11:00:00 +0000\" ENVELOPE (NIL \"Quoted \\\"subject\\\"\" ((NIL NIL \"peer\" \"example.invalid\")) NIL NIL NIL NIL NIL NIL NIL))\r\n" .
    "a4 OK FETCH done\r\n" .
    "a5 OK LOGOUT done\r\n"
);
$sesM = new ImapSession($mbox, true);
$sesM->greeting();
$sesM->login('u', 'p');
$box = $sesM->examine('INBOX');
check('examine reads uidvalidity', $box['uidValidity'] === 7777 && $box['exists'] === 2);
check('unseen uids parsed', $sesM->searchUnseen() === [11, 12]);
$rows = $sesM->fetchSummaries([11, 12]);
check('fetch yields 2 summaries', count($rows) === 2);
check('summary uid+flags', $rows[0]['uid'] === 11 && $rows[0]['flags'] === ['seen']);
check('summary subject/from/date', $rows[0]['subject'] === 'Weekly report' && str_contains($rows[0]['from'], 'boss@example.invalid') && $rows[0]['date'] === strtotime('2026-10-13 10:00:00 +0000'));
check('quoted subject decoded', $rows[1]['subject'] === 'Quoted "subject"');
check('empty flags list', $rows[1]['flags'] === []);
$sesM->logout();
check('logout sent', str_contains(end($mbox->sent), 'LOGOUT'));

$bye = new ScriptSocket("* BYE go away\r\n");
throwsKind('greeting BYE refused', 'refused', fn () => (new ImapSession($bye, true))->greeting());

$noValid = new ScriptSocket("* OK ready\r\na1 OK LOGIN\r\n* 0 EXISTS\r\na2 OK EXAMINE done\r\n");
$sesNv = new ImapSession($noValid, true);
$sesNv->greeting();
$sesNv->login('u', 'p');
throwsKind('missing UIDVALIDITY rejected', 'protocol', fn () => $sesNv->examine('INBOX'));

$tagged = new ScriptSocket("* OK ready\r\na1 OK LOGIN\r\nWRONGTAG NO\r\n");
$sesT = new ImapSession($tagged, true);
$sesT->greeting();
$sesT->login('u', 'p');
throwsKind('tag mismatch rejected', 'protocol', fn () => $sesT->examine('INBOX'));

// --- InboundSync over the same scripted transport ---------------------------

$script = "* OK ready\r\n" .
    "a1 OK LOGIN\r\n" .
    "* 1 EXISTS\r\n* OK [UIDVALIDITY 42] ok\r\na2 OK EXAMINE\r\n" .
    "* SEARCH 5\r\na3 OK SEARCH\r\n" .
    "* 1 FETCH (UID 5 FLAGS () INTERNALDATE \"13-Oct-2026 12:00:00 +0000\" ENVELOPE (NIL \"Hi\" ((NIL NIL \"a\" \"b.invalid\")) NIL NIL NIL NIL NIL NIL NIL))\r\n" .
    "a4 OK FETCH\r\na5 OK LOGOUT\r\n";
$r = InboundSync::checkMailbox(profile(), 'acct@example.invalid', 'pw', fn ($p) => new ScriptSocket($script), ['INBOX'], 1760000000);
check('sync reports success', $r['result']->outcome === ConnectionResult::OUTCOME_SUCCESS);
check('sync yields MailMessage', count($r['messages']) === 1 && $r['messages'][0]->uid === 5 && $r['messages'][0]->uidValidity === 42 && $r['messages'][0]->mailboxId === 'mbx-1');
check('sync message flags', $r['messages'][0]->isUnread());

$disabled = profile(['status' => 'disabled']);
$r2 = InboundSync::checkMailbox($disabled, 'u', 'pw', fn ($p) => new ScriptSocket(''), ['INBOX'], 1760000000);
check('disabled profile never dialed', $r2['result']->outcome === ConnectionResult::OUTCOME_UNTESTED && $r2['messages'] === []);

$failScript = "* BYE go away\r\n";
$r3 = InboundSync::checkMailbox(profile(), 'u', 'pw', fn ($p) => new ScriptSocket($failScript), ['INBOX'], 1760000000);
check('connect refusal maps to failure', $r3['result']->outcome === ConnectionResult::OUTCOME_FAILED && str_contains($r3['result']->error, 'refused'));

$authFail = "* OK ready\r\na1 NO bad creds\r\n";
$r4 = InboundSync::checkMailbox(profile(), 'u', 'pw', fn ($p) => new ScriptSocket($authFail), ['INBOX'], 1760000000);
check('auth failure classified', $r4['result']->outcome === ConnectionResult::OUTCOME_FAILED && str_contains($r4['result']->error, 'auth'));

// --- summary ---------------------------------------------------------------

echo ($failures ? "FAIL\n" : "PASS\n") . $checks . " checks\n";
foreach ($failures as $f) {
    echo " - $f\n";
}
exit($failures ? 1 : 0);
