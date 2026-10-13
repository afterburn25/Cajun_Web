<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailTransport;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * IMAP4rev1 response reader/parser. Turns one server response — possibly
 * spanning multiple physical lines because of `{n}` literals — into a
 * token list, then into nested PHP values:
 *
 *   atom      → string (bare words, digits stay strings)
 *   "quoted"  → string
 *   {n}\r\n   → literal string of exactly n bytes
 *   ( a b )   → list<mixed>
 *   NIL       → null
 *
 * The parser never guesses: malformed grammar, unterminated strings,
 * over-large literals, and junk after a response all throw
 * MailTransportException::PROTOCOL.
 */
final class ImapParser
{
    public const MAX_LITERAL = 4 * 1024 * 1024;
    private const MAX_TOKENS = 100000;

    /** Token: ['t'=>kind,'v'=>string] — kinds: atom|string|literal|(|). */
    private array $tokens = [];
    private int $pos = 0;

    private function __construct() {}

    /**
     * Read one complete server response starting at $line (already read,
     * CRLF stripped). Returns the parsed value list and consumes literals
     * from the socket as grammar requires.
     *
     * @return list<mixed> parsed top-level items
     */
    public static function parse(MailSocket $socket, string $line): array
    {
        $p = new self();
        $current = $line;
        while (true) {
            $p->lexLine($current);
            // A line ending in {n} announces a literal; the literal bytes and
            // the remainder of the response continue on the wire.
            if (preg_match('/\{([0-9]+)\}$/', $current, $m)) {
                $n = (int) $m[1];
                if ($n > self::MAX_LITERAL) {
                    throw new MailTransportException(MailTransportException::PROTOCOL, 'Literal exceeds the receive limit.');
                }
                $p->tokens[] = ['t' => 'literal', 'v' => $socket->readBytes($n)];
                $current = $socket->readLine();
                continue;
            }
            break;
        }
        if (count($p->tokens) > self::MAX_TOKENS) {
            throw new MailTransportException(MailTransportException::PROTOCOL, 'Response exceeded the token limit.');
        }
        $out = [];
        while ($p->pos < count($p->tokens)) {
            $out[] = $p->value();
        }
        return $out;
    }

    /** Lex one physical line into tokens; does not consume literals. */
    private function lexLine(string $line): void
    {
        $len = strlen($line);
        $i = 0;
        while ($i < $len) {
            $c = $line[$i];
            if ($c === ' ' || $c === "\t") {
                $i++;
                continue;
            }
            if ($c === '(') {
                $this->tokens[] = ['t' => '(', 'v' => '('];
                $i++;
                continue;
            }
            if ($c === ')') {
                $this->tokens[] = ['t' => ')', 'v' => ')'];
                $i++;
                continue;
            }
            if ($c === '"') {
                $this->tokens[] = ['t' => 'string', 'v' => $this->quoted($line, $i)];
                continue;
            }
            if ($c === '{') {
                // A {n} marker is only valid as the last bytes of the
                // physical line; the caller's loop reads the literal.
                if (preg_match('/\{[0-9]+\}$/', substr($line, $i))) {
                    break;
                }
                throw new MailTransportException(MailTransportException::PROTOCOL, 'Misplaced literal marker.');
            }
            $j = $i;
            while ($j < $len && !str_contains(' ()"{', $line[$j])) {
                $j++;
            }
            $this->tokens[] = ['t' => 'atom', 'v' => substr($line, $i, $j - $i)];
            $i = $j;
        }
    }

    /** Consume a quoted string starting at $line[$i] === '"'; advances $i. */
    private function quoted(string $line, int &$i): string
    {
        $i++;
        $out = '';
        $len = strlen($line);
        while ($i < $len) {
            $c = $line[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $out .= $line[$i + 1];
                $i += 2;
                continue;
            }
            if ($c === '"') {
                $i++;
                return $out;
            }
            $out .= $c;
            $i++;
        }
        throw new MailTransportException(MailTransportException::PROTOCOL, 'Unterminated quoted string.');
    }

    /** Parse one value from the token stream. */
    private function value(): mixed
    {
        if ($this->pos >= count($this->tokens)) {
            throw new MailTransportException(MailTransportException::PROTOCOL, 'Unexpected end of response.');
        }
        $tok = $this->tokens[$this->pos++];
        if ($tok['t'] === '(') {
            $list = [];
            while (true) {
                if ($this->pos >= count($this->tokens)) {
                    throw new MailTransportException(MailTransportException::PROTOCOL, 'Unterminated list.');
                }
                if ($this->tokens[$this->pos]['t'] === ')') {
                    $this->pos++;
                    return $list;
                }
                $list[] = $this->value();
            }
        }
        if ($tok['t'] === ')') {
            throw new MailTransportException(MailTransportException::PROTOCOL, 'Unexpected closing parenthesis.');
        }
        if ($tok['v'] === 'NIL' && $tok['t'] === 'atom') {
            return null;
        }
        return $tok['v'];
    }
}
