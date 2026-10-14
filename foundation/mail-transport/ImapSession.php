<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailTransport;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Read-only IMAP4rev1 session: greeting → CAPABILITY → (STARTTLS) → LOGIN →
 * EXAMINE/UID SEARCH/UID FETCH → LOGOUT. The protocol boundary is injected
 * (`MailSocket`), so nothing here opens a connection itself.
 *
 * Bounded deliberately: no message bodies, no flag/store/expunge writes —
 * EXAMINE is used so polling never mutates the server mailbox. Each failure
 * maps to a classified `MailTransportException` the adapter renders as a
 * `ConnectionResult`; success is only asserted after the server confirms it.
 */
final class ImapSession
{
    private int $tagCounter = 0;
    private bool $authenticated = false;
    private bool $encrypted;

    public function __construct(
        private MailSocket $socket,
        bool $encryptedAtConnect,
        private int $maxLine = TlsMailSocket::MAX_LINE,
    ) {
        $this->encrypted = $encryptedAtConnect;
    }

    /** Server greeting; PREAUTH means the connection is already logged in. */
    public function greeting(): void
    {
        $line = $this->socket->readLine($this->maxLine);
        $items = ImapParser::parse($this->socket, $line);
        $first = $items[0] ?? null;
        if ($first !== '*' || !isset($items[1])) {
            throw new MailTransportException(MailTransportException::PROTOCOL, 'Malformed server greeting.');
        }
        $word = strtoupper((string) $items[1]);
        if ($word === 'PREAUTH') {
            $this->authenticated = true;
            return;
        }
        if ($word === 'BYE') {
            throw new MailTransportException(MailTransportException::REFUSED, 'Server refused the connection.');
        }
        if ($word !== 'OK') {
            throw new MailTransportException(MailTransportException::PROTOCOL, "Unexpected greeting '{$word}'.");
        }
    }

    /** @return list<string> server capabilities (uppercase atoms). */
    public function capability(): array
    {
        $untagged = $this->command('CAPABILITY');
        foreach ($untagged as $items) {
            if (($items[0] ?? null) === '*' && strtoupper((string) ($items[1] ?? '')) === 'CAPABILITY') {
                return array_values(array_map('strtoupper', array_map('strval', array_slice($items, 2))));
            }
        }
        throw new MailTransportException(MailTransportException::PROTOCOL, 'CAPABILITY response missing.');
    }

    /** Negotiate STARTTLS; refuses when the server does not advertise it. */
    public function startTls(): void
    {
        if ($this->encrypted) {
            return;
        }
        $caps = $this->capability();
        if (!in_array('STARTTLS', $caps, true)) {
            throw new MailTransportException(MailTransportException::TLS, 'Server does not offer STARTTLS — refusing plaintext login.');
        }
        $this->command('STARTTLS');
        $this->socket->startTls();
        $this->encrypted = true;
    }

    public function login(string $user, #[\SensitiveParameter] string $password): void
    {
        if ($this->authenticated) {
            return;
        }
        if (!$this->encrypted) {
            throw new MailTransportException(MailTransportException::REFUSED, 'Login requires an encrypted connection.');
        }
        try {
            $this->command('LOGIN ' . self::quote($user) . ' ' . self::quote($password));
        } catch (MailTransportException $e) {
            if ($e->kind() !== MailTransportException::REFUSED) {
                throw $e;
            }
            throw new MailTransportException(MailTransportException::AUTH, 'The mailbox rejected these credentials.');
        }
        $this->authenticated = true;
    }

    /**
     * Open a folder read-only (EXAMINE — never marks anything seen).
     * @return array{uidValidity:int, exists:int}
     */
    public function examine(string $folder): array
    {
        $this->requireAuth();
        $untagged = $this->command('EXAMINE ' . self::quote($folder));
        $uidValidity = 0;
        $exists = 0;
        foreach ($untagged as $items) {
            if (($items[0] ?? null) !== '*') {
                continue;
            }
            $joined = implode(' ', array_map('strval', $items));
            if (strtoupper((string) ($items[1] ?? '')) === 'OK' && preg_match('/\[UIDVALIDITY (\d+)\]/i', $joined, $m)) {
                $uidValidity = (int) $m[1];
            }
            if (strtoupper((string) ($items[2] ?? '')) === 'EXISTS') {
                $exists = (int) $items[1];
            }
        }
        if ($uidValidity < 1) {
            throw new MailTransportException(MailTransportException::PROTOCOL, 'Server did not report UIDVALIDITY.');
        }
        return ['uidValidity' => $uidValidity, 'exists' => $exists];
    }

    /** @return list<int> unseen message UIDs in the examined folder. */
    public function searchUnseen(): array
    {
        $this->requireAuth();
        $untagged = $this->command('UID SEARCH UNSEEN');
        foreach ($untagged as $items) {
            if (($items[0] ?? null) === '*' && strtoupper((string) ($items[1] ?? '')) === 'SEARCH') {
                return array_map('intval', array_slice($items, 2));
            }
        }
        return [];
    }

    /**
     * Fetch header summaries for UIDs. Returns one row per message:
     * uid, flags (list<string>), from, subject, date (unix).
     * Bodies are never requested.
     *
     * @param list<int> $uids
     * @return list<array{uid:int,flags:list<string>,from:string,subject:string,date:int}>
     */
    public function fetchSummaries(array $uids): array
    {
        $this->requireAuth();
        if (!$uids) {
            return [];
        }
        $set = implode(',', array_map('intval', $uids));
        $untagged = $this->command("UID FETCH {$set} (UID FLAGS ENVELOPE INTERNALDATE)");
        $out = [];
        foreach ($untagged as $items) {
            if (($items[0] ?? null) !== '*' || strtoupper((string) ($items[2] ?? '')) !== 'FETCH') {
                continue;
            }
            $data = $items[3] ?? null;
            if (!is_array($data)) {
                continue;
            }
            $fields = [];
            for ($i = 0; $i + 1 < count($data); $i += 2) {
                if (is_string($data[$i])) {
                    $fields[strtoupper($data[$i])] = $data[$i + 1];
                }
            }
            $uid = (int) ($fields['UID'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $flags = [];
            foreach ((array) ($fields['FLAGS'] ?? []) as $f) {
                $flags[] = ltrim(strtolower((string) $f), '\\');
            }
            $env = $fields['ENVELOPE'] ?? [];
            $from = '';
            if (is_array($env)) {
                $addr = $env[2] ?? [];
                if (is_array($addr) && isset($addr[0]) && is_array($addr[0])) {
                    $box = $addr[0];
                    $from = trim((string) ($box[0] ?? '') . ' <' . (string) ($box[2] ?? '') . '@' . (string) ($box[3] ?? '') . '>');
                }
            }
            $out[] = [
                'uid' => $uid,
                'flags' => $flags,
                'from' => $from !== '' ? $from : '(unknown sender)',
                'subject' => is_array($env) ? (string) ($env[1] ?? '') : '',
                'date' => isset($fields['INTERNALDATE']) ? (int) strtotime((string) $fields['INTERNALDATE']) : 0,
            ];
        }
        usort($out, static fn (array $a, array $b): int => $a['uid'] <=> $b['uid']);
        return $out;
    }

    public function logout(): void
    {
        if (!$this->socket->isOpen()) {
            return;
        }
        try {
            $this->command('LOGOUT');
        } catch (MailTransportException) {
            // Best-effort: the socket close is what matters.
        } finally {
            $this->socket->close();
        }
    }

    // -- framing ----------------------------------------------------------

    /**
     * Send one tagged command, collect untagged responses until the tagged
     * completion. NO/BAD/BYE are failures, not data.
     *
     * @return list<list<mixed>> parsed untagged responses
     */
    private function command(string $command): array
    {
        $tag = 'a' . ++$this->tagCounter;
        $this->socket->write($tag . ' ' . $command . "\r\n");
        $untagged = [];
        while (true) {
            $line = $this->socket->readLine($this->maxLine);
            $items = ImapParser::parse($this->socket, $line);
            $first = $items[0] ?? null;
            if ($first === '*') {
                if (strtoupper((string) ($items[1] ?? '')) === 'BYE') {
                    throw new MailTransportException(MailTransportException::CLOSED, 'Server ended the session.');
                }
                $untagged[] = $items;
                continue;
            }
            if ($first !== $tag) {
                throw new MailTransportException(MailTransportException::PROTOCOL, 'Response tag mismatch.');
            }
            $status = strtoupper((string) ($items[1] ?? ''));
            if ($status === 'OK') {
                return $untagged;
            }
            if ($status === 'NO') {
                throw new MailTransportException(MailTransportException::REFUSED, 'Server refused the request.');
            }
            if ($status === 'BAD') {
                throw new MailTransportException(MailTransportException::PROTOCOL, 'Server reported a malformed command.');
            }
            throw new MailTransportException(MailTransportException::PROTOCOL, "Unknown completion status '{$status}'.");
        }
    }

    private function requireAuth(): void
    {
        if (!$this->authenticated) {
            throw new MailTransportException(MailTransportException::REFUSED, 'Mailbox access requires authentication first.');
        }
    }

    /** RFC 3501 quoted-string escaping for user-supplied login/folder data. */
    private static function quote(string $value): string
    {
        if ($value === '' || strlen($value) > 1000 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('A mail credential or folder contains invalid characters.');
        }
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
