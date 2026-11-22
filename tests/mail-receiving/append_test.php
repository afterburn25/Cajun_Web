<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

require_once __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
require_once __DIR__.'/../../foundation/mail-receiving/AppendReceipt.php';
require_once __DIR__.'/../../foundation/mail-receiving/AppendSession.php';

use CajunWebPro\Foundation\MailReceiving\{AppendReceipt,AppendSession,ReceivingFailure};
use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;

final class AppendFixture implements StreamInterface
{
    private $memory;
    private bool $open = true;
    private int $wireCalls = 0;
    private int $cryptoCalls = 0;
    /** @var list<string> */
    private array $written = [];
    /** @var list<string> */
    private array $literals = [];

    public function __construct(
        string $responses,
        private ?int $partialWireCall = null,
        private ?int $failWireCall = null,
        private ?int $failLiteralAfter = null,
        private bool $closeThrows = false,
        private bool $timeoutAfterLiteral = false,
    ) {
        $this->memory = fopen('php://memory', 'w+b');
        fwrite($this->memory, $responses);
        rewind($this->memory);
    }

    public function open(string $transport, string $host, int $port, int $timeout, array $options = []): bool { return false; }
    public function close(): void { $this->open = false; if (is_resource($this->memory)) fclose($this->memory); if ($this->closeThrows) throw new RuntimeException('private close detail'); }
    public function read(int $length): string|false { return $length === 0 ? '' : fread($this->memory, $length); }
    public function fgets(): string|false { if ($this->timeoutAfterLiteral && $this->literals!==[]) throw new ReceivingFailure('timeout'); return fgets($this->memory); }
    public function fwrite(#[\SensitiveParameter] string $data): int|false {
        ++$this->wireCalls;
        if ($this->failWireCall === $this->wireCalls) throw new RuntimeException('private write detail');
        if ($this->partialWireCall === $this->wireCalls) { $part = substr($data, 0, max(0, strlen($data)-1)); $this->written[] = $part; return strlen($part); }
        $this->written[] = $data;
        return strlen($data);
    }
    public function fwriteLiteral(#[\SensitiveParameter] string $data, int $max): int|false {
        if (strlen($data) > $max) throw new ReceivingFailure('limit');
        if ($this->failLiteralAfter !== null) {
            $this->literals[] = substr($data, 0, $this->failLiteralAfter);
            throw new ReceivingFailure('connection');
        }
        $this->literals[] = $data;
        return strlen($data);
    }
    public function meta(): array { return ['crypto'=>['protocol'=>'TLSv1.3'],'timed_out'=>false,'unread_bytes'=>0]; }
    public function opened(): bool { return $this->open; }
    public function setTimeout(int $seconds): bool { return false; }
    public function setSocketSetCrypto(bool $enabled, ?int $method): bool|int { ++$this->cryptoCalls; return true; }
    /** @return list<string> */ public function commands(): array { return $this->written; }
    /** @return list<string> */ public function literals(): array { return $this->literals; }
    public function cryptoCalls(): int { return $this->cryptoCalls; }
}

$checks = [];
function check(string $name, bool $passed): void { global $checks; $checks[]=['name'=>$name,'passed'=>$passed]; if (!$passed) throw new RuntimeException($name); }
function failure(callable $run): ?ReceivingFailure { try { $run(); } catch (ReceivingFailure $e) { return $e; } return null; }
function finite(string $name, ?ReceivingFailure $failure, string $code): void {
    check($name, $failure instanceof ReceivingFailure && $failure->getMessage()===$code && $failure->getPrevious()===null
        && !preg_match('/synthetic password|Exact saved body|private|denied|folder text/i', $failure->getMessage()));
}

const MIME = "From: Synthetic <synthetic@example.net>\r\nTo: dest@example.net\r\nSubject: Sent copy\r\nMessage-ID: <copy-1@example.invalid>\r\nDate: Tue, 13 Oct 2026 12:00:00 +0000\r\nMIME-Version: 1.0\r\nContent-Type: text/plain\r\n\r\nExact saved body\r\n";
function prefix(string $caps='IMAP4rev1 AUTH=PLAIN'): string { return "* OK synthetic\r\n* CAPABILITY ".$caps."\r\nCWA001 OK done\r\n+ \r\nCWA002 OK authenticated\r\n"; }
function normal(): string { return prefix()."+ \r\nCWA003 OK appended\r\n"; }
/** @return array{AppendReceipt|ReceivingFailure,AppendFixture,int} */
function exercise(string $responses, int $revoke=0, string $folder='Sent', string $message=MIME, int $max=65536,
    ?int $partialWire=null, ?int $failWire=null, ?int $failLiteral=null, bool $closeThrows=false, bool $timeoutAfterLiteral=false): array {
    $stream = new AppendFixture($responses, $partialWire, $failWire, $failLiteral, $closeThrows, $timeoutAfterLiteral);
    $calls = 0;
    try {
        $receipt = (new AppendSession($stream, function() use (&$calls,$revoke): void {
            if (++$calls === $revoke) throw new RuntimeException('private authority detail');
        }, $max))->append('ssl', 'synthetic@example.net', 'synthetic password', $folder, $message);
        return [$receipt,$stream,$calls];
    } catch (ReceivingFailure $e) { return [$e,$stream,$calls]; }
}

[$out,$stream,$calls] = exercise(normal());
check('Tagged OK returns an immutable accepted receipt', $out instanceof AppendReceipt && $out->accepted && $out->authorityCurrent);
check('Wire uses the fixed bounded APPEND sequence', $stream->commands() === [
    "CWA001 CAPABILITY\r\n", "CWA002 AUTHENTICATE PLAIN\r\n", base64_encode("\0synthetic@example.net\0synthetic password")."\r\n",
    'CWA003 APPEND "Sent" (\\Seen) {'.strlen(MIME)."}\r\n", "\r\n", "CWA004 LOGOUT\r\n",
]);
check('Literal transmits exact MIME once', $stream->literals() === [MIME]);
check('Authority is checked at all four boundaries', $calls === 4);
check('Accepted session closes', !$stream->opened());
[$out,$stream] = exercise(prefix()."+ Ready for literal data\r\nCWA003 OK appended\r\n");
check('Bounded APPEND continuation text is interoperable', $out instanceof AppendReceipt && $stream->literals()===[MIME]);

foreach (['NO','BAD'] as $status) {
    [$out] = exercise(prefix()."+ \r\nCWA003 ".$status." private folder text\r\n");
    finite('Tagged '.$status.' is a definite folder refusal', $out instanceof ReceivingFailure?$out:null, 'folder');
}
[$out,$stream] = exercise(prefix()."CWA003 NO private folder text\r\n");
finite('Immediate APPEND refusal occurs before the literal', $out instanceof ReceivingFailure?$out:null, 'folder');
check('Immediate refusal emits no literal', $stream->literals() === []);

[$out] = exercise(prefix()."+ \r\n", 0);
finite('Lost final response after literal is uncertain', $out instanceof ReceivingFailure?$out:null, 'uncertain');
[$out] = exercise(normal(), 0, 'Sent', MIME, 65536, null, null, null, false, true);
finite('Timeout after literal is uncertain', $out instanceof ReceivingFailure?$out:null, 'uncertain');
[$out,$stream] = exercise(normal(), 0, 'Sent', MIME, 65536, null, null, 17);
finite('Partial literal write is uncertain', $out instanceof ReceivingFailure?$out:null, 'uncertain');
check('Partial literal evidence is preserved in fixture only', $stream->literals() === [substr(MIME,0,17)]);
[$out] = exercise(normal(), 0, 'Sent', MIME, 65536, 5);
finite('Partial terminator write after literal is uncertain', $out instanceof ReceivingFailure?$out:null, 'uncertain');
[$out] = exercise(prefix()."+ \r\n* BYE private detail\r\n", 0);
finite('BYE after literal is uncertain', $out instanceof ReceivingFailure?$out:null, 'uncertain');
[$out] = exercise(prefix()."+ \r\nCWA999 OK wrong tag\r\n", 0);
finite('Wrong final tag after literal is uncertain', $out instanceof ReceivingFailure?$out:null, 'uncertain');

[$out,$stream,$calls] = exercise(normal(), 4);
check('Tagged OK survives late authority withdrawal', $out instanceof AppendReceipt && $out->accepted && !$out->authorityCurrent && $calls===4 && !$stream->opened());
[$out] = exercise(normal(), 0, 'Sent', MIME, 65536, null, 6, null, true);
check('Logout and close errors cannot repaint tagged acceptance', $out instanceof AppendReceipt && $out->accepted);

foreach ([1,2,3] as $revoke) {
    [$out,$stream] = exercise(normal(), $revoke);
    finite('Pre-literal revocation '.$revoke.' is definite', $out instanceof ReceivingFailure?$out:null, 'access_changed');
    if ($revoke===1) check('First revocation emits no credential', !str_contains(implode('', $stream->commands()), base64_encode("\0synthetic@example.net\0synthetic password")));
    if ($revoke<=2) check('Early revocation emits no APPEND literal '.$revoke, $stream->literals()===[]);
}

foreach (['INBOX.Sent','Sent Items','Archive-2026'] as $folder) {
    [$out] = exercise(normal(), 0, $folder);
    check('Protocol-safe folder accepted: '.$folder, $out instanceof AppendReceipt);
}
foreach (['Sent"folder','Sen\\t','Sent*','%Sent','with space inside'.str_repeat('x',60),''] as $folder) {
    [$out,$stream,$calls] = exercise(normal(), 0, $folder);
    finite('Unsafe folder is refused: '.json_encode($folder), $out instanceof ReceivingFailure?$out:null, 'folder');
    check('Unsafe folder is rejected before protocol I/O', $calls===0 && $stream->commands()===[] && $stream->literals()===[]);
}

$largePrefix = "From: a@example.invalid\r\nTo: b@example.invalid\r\n\r\n";
$oneMiB = $largePrefix.str_repeat('a',1048576-strlen($largePrefix)-2)."\r\n";
check('One-MiB fixture is exact', strlen($oneMiB)===1048576);
[$out,$stream] = exercise(normal(), 0, 'Sent', $oneMiB, 1048576);
check('One-MiB ceiling is accepted exactly once', $out instanceof AppendReceipt && $stream->literals()===[$oneMiB]);
[$out,$stream] = exercise(normal(), 0, 'Sent', $oneMiB.'x', 1048576);
finite('One byte over ceiling is refused', $out instanceof ReceivingFailure?$out:null, 'protocol');
check('Over-ceiling payload is refused before protocol I/O', $stream->commands()===[]);
finite('Caller-selected bound above product ceiling is refused', failure(fn()=>new AppendSession(new AppendFixture(normal()), static fn()=>null, 1048577)), 'limit');
finite('Caller-selected bound below minimum is refused', failure(fn()=>new AppendSession(new AppendFixture(normal()), static fn()=>null, 31)), 'limit');

foreach ([
    "From: a@example.invalid\nTo: b@example.invalid\n\nbody\n",
    "From: a@example.invalid\rTo: b@example.invalid\r\rbody\r",
    "From: a@example.invalid\r\nTo: b@example.invalid\r\n\r\nbody",
    "From: a@example.invalid\r\nTo: b@example.invalid\r\n\r\nbody\x80\r\n",
    "short",
] as $i=>$bad) {
    [$out,$stream] = exercise(normal(), 0, 'Sent', $bad);
    finite('Malformed MIME is refused '.$i, $out instanceof ReceivingFailure?$out:null, 'protocol');
    check('Malformed MIME has no protocol I/O '.$i, $stream->commands()===[]);
}

[$out] = exercise(str_replace('AUTH=PLAIN','AUTH=XOAUTH2',normal()));
finite('Missing credential mechanism is finite', $out instanceof ReceivingFailure?$out:null, 'mechanism');
[$out] = exercise(str_replace('CWA002 OK authenticated','CWA002 NO denied',normal()));
finite('Rejected credentials are finite', $out instanceof ReceivingFailure?$out:null, 'authentication');
[$out] = exercise(str_replace('* OK synthetic','* PREAUTH synthetic',normal()));
finite('PREAUTH greeting is refused', $out instanceof ReceivingFailure?$out:null, 'protocol');

$starttls = "* OK synthetic\r\n* CAPABILITY IMAP4rev1 STARTTLS AUTH=PLAIN\r\nCWA001 OK done\r\nCWA002 OK upgraded\r\n"
    ."* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nCWA003 OK done\r\n+ \r\nCWA004 OK authenticated\r\n+ \r\nCWA005 OK appended\r\n";
$stream = new AppendFixture($starttls); $calls=0;
$receipt = (new AppendSession($stream, function()use(&$calls){++$calls;}))->append('starttls','u','p','Sent',MIME);
check('STARTTLS refreshes capabilities and appends', $receipt->accepted && $stream->cryptoCalls()===1 && $calls===4);

$stream = new AppendFixture(normal()); $session = new AppendSession($stream, static fn()=>null);
$session->append('ssl','u','p','Sent',MIME);
finite('Session is single-use', failure(fn()=>$session->append('ssl','u','p','Sent',MIME)), 'protocol');

echo json_encode(['scope'=>'Synthetic in-memory APPEND protocol and immutable outcome only; no socket, configured-folder authority, persistence, transport wrapper, provider or application acceptance','passed'=>true,'checks'=>$checks], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
