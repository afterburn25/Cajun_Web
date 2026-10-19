<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/StackCpHandoffContext.php';
require_once __DIR__.'/StackCpClientHandoffContext.php';

/** Ephemeral strict SDK-field subset. Parsing is not successful provider login or safe retry evidence. */
final class StackCpSensitiveResponse implements \JsonSerializable
{
    public const MAX_BODY_BYTES = 16384;
    private ?string $opaque;
    private bool $consumed = false;

    private function __construct(
        public readonly string $phase,
        public readonly string $state,
        public readonly string $reason,
        public readonly string $contextSha256,
        #[\SensitiveParameter] ?string $opaque = null,
    ) { $this->opaque = $opaque; }

    public static function parseToken(StackCpHandoffContext|StackCpClientHandoffContext $context, int $status,
        #[\SensitiveParameter] string $body, #[\SensitiveParameter] array $ownedCredentials): self
    { return self::parse('token', $context, $status, $body, $ownedCredentials); }

    public static function parseSession(StackCpHandoffContext|StackCpClientHandoffContext $context, int $status,
        #[\SensitiveParameter] string $body, #[\SensitiveParameter] array $ownedCredentials): self
    { return self::parse('session', $context, $status, $body, $ownedCredentials); }

    private static function parse(string $phase, StackCpHandoffContext|StackCpClientHandoffContext $context, int $status,
        #[\SensitiveParameter] string $body, #[\SensitiveParameter] array $ownedCredentials): self
    {
        $context = $context instanceof StackCpClientHandoffContext ? StackCpClientHandoffContext::validated($context) : StackCpHandoffContext::validated($context);
        // Token phase owns general + auth client keys; session also owns its bearer token.
        $roles = $phase === 'token' ? ['general-api-key','auth-client-key'] : ['general-api-key','auth-client-key','access-token'];
        if (array_keys($ownedCredentials) !== $roles) throw new \InvalidArgumentException('Invalid handoff credential roles.');
        $needles = [];
        foreach ($ownedCredentials as $secret) {
            if (!self::opaque($secret, 4096)) throw new \InvalidArgumentException('Invalid handoff credentials.');
            $needles[] = $secret; $needles[] = base64_encode($secret);
        }
        $failure = fn(string $reason): self => new self($phase, 'unavailable', $reason, $context->sha256);
        if ($status !== 200) return $failure(match (true) {
            in_array($status, [401,403], true)=>'authentication', $status===429=>'rate_limited',
            $status>=500 && $status<=599=>'upstream_unavailable', default=>'http_status',
        });
        if (strlen($body) > self::MAX_BODY_BYTES) return $failure('body_limit');
        $reason = ''; $values = self::flatObject($body, $reason);
        if ($values === null) return $failure($reason);
        // Include decoded nonstring scalars. Optional discarded values receive the
        // same echo protection; no provider-reported expiry is projected publicly.
        foreach ($values as $key=>$value) {
            $text = is_string($value) ? $value : json_encode($value);
            if (!is_string($text)) return $failure('field_unsupported');
            foreach ($needles as $needle) {
                if (str_contains((string)$key, $needle) || str_contains($text, $needle)) return $failure('credential_echo');
            }
        }
        $allowed = $phase === 'token' ? ['access_token','token_type','expires_in','scope','refresh_token'] : ['session_id'];
        if (array_diff(array_keys($values), $allowed) !== []) return $failure('shape_unsupported');
        if ($phase === 'session') {
            if (array_keys($values) !== ['session_id'] || !self::opaque($values['session_id'], 4096)) return $failure('field_unsupported');
            return new self($phase, 'accepted_subset', '', $context->sha256, $values['session_id']);
        }
        if (!self::opaque($values['access_token'] ?? null, 4096) || ($values['token_type'] ?? null) !== 'bearer'
            || !is_int($values['expires_in'] ?? null) || $values['expires_in'] < 1 || $values['expires_in'] > 86400
            // The SDK checks membership of interactive. Our deliberately narrower
            // local subset accepts only that single scope, not undocumented grants.
            || ($values['scope'] ?? null) !== 'interactive'
            || (array_key_exists('refresh_token', $values) && $values['refresh_token'] !== null && !self::opaque($values['refresh_token'], 4096))) return $failure('field_unsupported');
        // expires_in has no evidenced session-lifetime meaning here and may itself
        // echo a returned bearer token. Discard it rather than expose it as metadata.
        return new self($phase, 'accepted_subset', '', $context->sha256, $values['access_token']);
    }

    private static function opaque(mixed $value, int $limit): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= $limit
            && preg_match('//u', $value) === 1 && preg_match('/[\x00-\x20\x7f]/', $value) === 0;
    }

    /** Parse bounded flat fields before a whole-object decoder can erase duplicate keys. */
    private static function flatObject(#[\SensitiveParameter] string $body, string &$reason): ?array
    {
        $body = trim($body, " \t\r\n"); $reason = 'shape_unsupported';
        if ($body === '{}') return [];
        $string = '"(?:[^"\\\\\x00-\x1f]|\\\\(?:["\\\\/bfnrt]|u[0-9a-fA-F]{4}))*"';
        $number = '-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?';
        $pattern = '~\G[ \t\r\n]*(?<key>'.$string.')[ \t\r\n]*:[ \t\r\n]*(?<value>'.$string.'|'.$number.'|true|false|null)[ \t\r\n]*(?<end>[,}])~';
        $offset = 1; $values = []; $seen = [];
        if (str_starts_with($body, '{')) while (preg_match($pattern, $body, $m, 0, $offset) === 1) {
            try { $key = json_decode($m['key'], false, 2, JSON_THROW_ON_ERROR); $value = json_decode($m['value'], false, 2, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { $reason = 'malformed_body'; return null; }
            if (isset($seen[$key])) { $reason = 'duplicate_field'; return null; }
            $seen[$key] = true; $values[$key] = $value;
            if (count($values) > 8) { $reason = 'field_limit'; return null; }
            $offset += strlen($m[0]);
            if ($m['end'] === '}' && trim(substr($body, $offset), " \t\r\n") === '') return $values;
            if ($m['end'] === '}') break;
        }
        try { json_decode($body, false, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { $reason = 'malformed_body'; }
        return null;
    }

    /** In-memory single consumption only; the future caller must provide durable admission first. */
    public function consume(string $phase, callable $consumer): mixed
    {
        if ($phase !== $this->phase || $this->state !== 'accepted_subset' || $this->consumed || $this->opaque === null) {
            throw new \LogicException('Handoff response is unavailable for consumption.');
        }
        $value = $this->opaque; $this->opaque = null; $this->consumed = true;
        try { return $consumer($value); } finally { unset($value); }
    }
    public function jsonSerialize(): array
    {
        return ['phase'=>$this->phase,'state'=>$this->state,'reason'=>$this->reason,'context_sha256'=>$this->contextSha256,
            'consumed'=>$this->consumed];
    }
    public function __debugInfo(): array { return $this->jsonSerialize(); }
    public function __serialize(): array { throw new \LogicException('Handoff responses cannot be serialized.'); }
    public function __unserialize(array $data): void { throw new \LogicException('Handoff responses cannot be restored.'); }
    public function __set(string $name, mixed $value): void { throw new \LogicException('Handoff responses cannot be extended.'); }
    private function __clone() {}
}
