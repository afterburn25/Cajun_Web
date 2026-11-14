<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

require_once __DIR__.'/AppendReceipt.php';

use DirectoryTree\ImapEngine\Connection\ImapParser;
use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;
use DirectoryTree\ImapEngine\Connection\Responses\{ContinuationResponse,Response,TaggedResponse,UntaggedResponse};
use DirectoryTree\ImapEngine\Connection\Tokens\{Atom,Token};

/** Fixed, single-use APPEND protocol. Durable copy policy belongs to a later adapter. */
final class AppendSession
{
    private const BEFORE_LITERAL = 0;
    private const LITERAL_STARTED = 1;
    private const TAGGED_ACCEPTED = 2;

    private ImapParser $parser;
    private BoundedTokenizer $tokenizer;
    private int $tag = 0;
    private int $responses = 0;
    private int $phase = self::BEFORE_LITERAL;
    private bool $used = false;
    private \Closure $authorize;

    public function __construct(private StreamInterface $stream, callable $authorize, private int $maxBytes = 65536)
    {
        if ($maxBytes < 32 || $maxBytes > 1048576) throw new ReceivingFailure('limit');
        $this->authorize = \Closure::fromCallable($authorize);
        $this->tokenizer = new BoundedTokenizer($stream, ReadProfile::Append);
        $this->parser = new ImapParser($this->tokenizer);
    }

    private static function word(mixed $token): string
    {
        if (!$token instanceof Atom) throw new ReceivingFailure('protocol');
        return strtoupper($token->value);
    }

    private function next(): Response
    {
        if (++$this->responses > 40) throw new ReceivingFailure('limit');
        $response = $this->parser->next();
        if (!$response instanceof Response || count($response->tokens()) < 1) throw new ReceivingFailure('protocol');
        return $response;
    }

    private function writeLine(#[\SensitiveParameter] string $line): void
    {
        $written = $this->stream->fwrite($line);
        if ($written !== strlen($line)) throw new ReceivingFailure('connection');
    }

    private function command(string $command): string
    {
        $tag = 'CWA'.str_pad((string)++$this->tag, 3, '0', STR_PAD_LEFT);
        $this->writeLine($tag.' '.$command."\r\n");
        return $tag;
    }

    /** @return list<Response> */
    private function replies(string $tag, string $failure = 'protocol'): array
    {
        $rows = [];
        while (true) {
            $row = $this->next();
            $tokens = $row->tokens();
            if ($row instanceof TaggedResponse) {
                if (!(($tokens[0] ?? null) instanceof Token) || $tokens[0]->value !== $tag) throw new ReceivingFailure('protocol');
                $status = self::word($tokens[1] ?? null);
                if (in_array($status, ['NO','BAD'], true)) throw new ReceivingFailure($failure);
                if ($status !== 'OK') throw new ReceivingFailure('protocol');
                $this->tokenizer->assertBoundary();
                $rows[] = $row;
                return $rows;
            }
            if (!$row instanceof UntaggedResponse || self::word($tokens[1] ?? null) === 'BYE') throw new ReceivingFailure('connection');
            $rows[] = $row;
        }
    }

    /** @return list<string> */
    private function capabilities(): array
    {
        $caps = [];
        $found = false;
        foreach ($this->replies($this->command('CAPABILITY')) as $row) {
            if ($row instanceof TaggedResponse) continue;
            $tokens = $row->tokens();
            $type = self::word($tokens[1] ?? null);
            if ($type === 'OK') continue;
            if ($type !== 'CAPABILITY' || $found) throw new ReceivingFailure('protocol');
            $found = true;
            foreach (array_slice($tokens, 2) as $token) $caps[] = self::word($token);
        }
        if (!$found) throw new ReceivingFailure('protocol');
        return $caps;
    }

    private function requireAuthority(): void
    {
        try { ($this->authorize)(); }
        catch (\Throwable) { throw new ReceivingFailure('access_changed'); }
        if ($this->stream instanceof ReceivingStream) $this->stream->assertWithinBudget();
    }

    private function authorityCurrentAfterAcceptance(): bool
    {
        try { ($this->authorize)(); return true; }
        catch (\Throwable) { return false; }
    }

    /** Printable wire-safe folder syntax only; configured-folder authority is adapter-owned. */
    public static function folder(string $name): string
    {
        if (!preg_match('/\A[\x20-\x7E]{1,64}\z/D', $name) || str_contains($name, '"') || str_contains($name, '\\')
            || str_contains($name, '%') || str_contains($name, '*')) throw new ReceivingFailure('folder');
        return $name;
    }

    /** Exact ASCII MIME with canonical CRLF and a finite caller-selected product bound. */
    public static function message(#[\SensitiveParameter] string $bytes, int $maxBytes): string
    {
        if ($maxBytes < 32 || $maxBytes > 1048576 || strlen($bytes) < 32 || strlen($bytes) > $maxBytes
            || !str_contains($bytes, "\r\n\r\n") || !str_ends_with($bytes, "\r\n")
            || preg_match('/[^\x09\x0A\x0D\x20-\x7E]/', $bytes) || preg_match('/(?<!\r)\n|\r(?!\n)/', $bytes)) {
            throw new ReceivingFailure('protocol');
        }
        return $bytes;
    }

    private function awaitAppendResult(string $tag): void
    {
        while (true) {
            $row = $this->next();
            $tokens = $row->tokens();
            if ($row instanceof TaggedResponse) {
                if (!(($tokens[0] ?? null) instanceof Token) || $tokens[0]->value !== $tag) throw new ReceivingFailure('protocol');
                $status = self::word($tokens[1] ?? null);
                if (in_array($status, ['NO','BAD'], true)) throw new ReceivingFailure('folder');
                if ($status !== 'OK') throw new ReceivingFailure('protocol');
                $this->phase = self::TAGGED_ACCEPTED;
                return;
            }
            if (!$row instanceof UntaggedResponse || self::word($tokens[1] ?? null) === 'BYE') throw new ReceivingFailure('connection');
        }
    }

    public function append(string $encryption, #[\SensitiveParameter] string $user, #[\SensitiveParameter] string $password,
        string $folder, #[\SensitiveParameter] string $message): AppendReceipt
    {
        if ($this->used) throw new ReceivingFailure('protocol');
        $this->used = true;
        try {
            if (!in_array($encryption, ['ssl','starttls'], true)) throw new ReceivingFailure('protocol');
            if ($user === '' || $password === '' || strlen($user) > 190 || strlen($password) > 1024
                || str_contains($user, "\0") || str_contains($password, "\0")) throw new ReceivingFailure('credential');
            $folder = self::folder($folder);
            $message = self::message($message, $this->maxBytes);

            $greeting = $this->next();
            if (!$greeting instanceof UntaggedResponse || self::word($greeting->tokens()[1] ?? null) !== 'OK') throw new ReceivingFailure('protocol');
            $caps = $this->capabilities();
            if ($encryption === 'starttls') {
                if (!in_array('STARTTLS', $caps, true)) throw new ReceivingFailure('upgrade');
                $this->replies($this->command('STARTTLS'), 'upgrade');
                if ($this->stream->setSocketSetCrypto(true, null) !== true) throw new ReceivingFailure('tls');
                $caps = $this->capabilities();
            }
            if (!in_array($this->stream->meta()['crypto']['protocol'] ?? '', ['TLSv1.2','TLSv1.3'], true)) throw new ReceivingFailure('tls');
            if (!in_array('AUTH=PLAIN', $caps, true)) throw new ReceivingFailure('mechanism');

            $tag = $this->command('AUTHENTICATE PLAIN');
            $challenge = $this->next();
            if ($challenge instanceof TaggedResponse && ($challenge->tokens()[0]->value ?? null) === $tag
                && in_array(self::word($challenge->tokens()[1] ?? null), ['NO','BAD'], true)) throw new ReceivingFailure('authentication');
            if (!$challenge instanceof ContinuationResponse || count($challenge->tokens()) !== 1) throw new ReceivingFailure('protocol');
            $this->requireAuthority();
            $this->writeLine(base64_encode("\0".$user."\0".$password)."\r\n");
            $this->replies($tag, 'authentication');
            $this->requireAuthority();

            $tag = $this->command('APPEND "'.$folder.'" (\\Seen) {'.strlen($message).'}');
            $go = $this->next();
            if ($go instanceof TaggedResponse && ($go->tokens()[0]->value ?? null) === $tag
                && in_array(self::word($go->tokens()[1] ?? null), ['NO','BAD'], true)) throw new ReceivingFailure('folder');
            if (!$go instanceof ContinuationResponse) throw new ReceivingFailure('protocol');
            $this->requireAuthority();

            if (!method_exists($this->stream, 'fwriteLiteral')) throw new ReceivingFailure('protocol');
            $this->phase = self::LITERAL_STARTED;
            $written = $this->stream->fwriteLiteral($message, $this->maxBytes);
            if ($written !== strlen($message)) throw new ReceivingFailure('connection');
            $this->writeLine("\r\n");
            $this->awaitAppendResult($tag);

            $receipt = new AppendReceipt($this->authorityCurrentAfterAcceptance());
            try { $this->command('LOGOUT'); } catch (\Throwable) {}
            return $receipt;
        } catch (ReceivingFailure $e) {
            if ($this->phase === self::TAGGED_ACCEPTED) return new AppendReceipt(false);
            if ($this->phase === self::LITERAL_STARTED && $e->getMessage() !== 'folder') throw new ReceivingFailure('uncertain');
            throw $e;
        } catch (\Throwable) {
            if ($this->phase === self::TAGGED_ACCEPTED) return new AppendReceipt(false);
            throw new ReceivingFailure($this->phase === self::LITERAL_STARTED ? 'uncertain' : 'protocol');
        } finally {
            try { $this->stream->close(); } catch (\Throwable) {}
        }
    }
}
