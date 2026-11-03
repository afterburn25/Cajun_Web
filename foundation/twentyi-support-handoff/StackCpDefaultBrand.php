<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/StackCpLaunchFailure.php';

/** Opaque actual customisations object. No invented brand fields or destination rewriting. */
final class StackCpDefaultBrand implements \JsonSerializable
{
    public const MAX_BODY_BYTES = 16384;
    private function __construct(#[\SensitiveParameter] private string $json) {}

    public static function fromResponse(int $status, #[\SensitiveParameter] string $body,
        #[\SensitiveParameter] array $secrets): self
    {
        if ($status !== 200) throw new StackCpLaunchFailure('brand', 'http_status', false);
        $value = self::decode($body);
        self::defaultObject($value);
        $brand = new self($body);
        $brand->assertNoEcho($secrets);
        return $brand;
    }

    /** Recheck when the lazily loaded auth key or token becomes known. */
    public function assertNoEcho(#[\SensitiveParameter] array $secrets): void
    {
        $needles = [];
        foreach ($secrets as $secret) {
            if (!is_string($secret) || $secret === '') throw new \InvalidArgumentException('Invalid brand echo witness.');
            $needles[] = $secret; $needles[] = base64_encode($secret);
        }
        // Preserve lexical numbers as well as decoded strings: JSON numeric coercion can lose exact credential bytes.
        foreach ($needles as $needle) if (str_contains($this->json, $needle)) throw new StackCpLaunchFailure('brand','credential_echo',false);
        $walk = static function(#[\SensitiveParameter] mixed $value) use (&$walk, $needles): void {
            if (is_object($value) || is_array($value)) {
                foreach ($value as $key=>$item) { if (is_object($value)) $walk((string)$key); $walk($item); }
            } else {
                if (is_float($value) && !is_finite($value)) self::fail('field_unsupported');
                $text = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
                foreach ($needles as $needle) if (str_contains($text, $needle)) throw new StackCpLaunchFailure('brand','credential_echo',false);
            }
        };
        $walk(self::decode($this->json));
    }

    /** Only the sender consumes this in-memory body; the actual object bytes stay intact. */
    public function withBody(callable $consumer): mixed
    { return $consumer('{"brand":'.$this->json.'}'); }

    public static function validateSessionBody(#[\SensitiveParameter] string $body): void
    {
        $value = self::decode($body, self::MAX_BODY_BYTES + 10);
        if (array_keys(get_object_vars($value)) !== ['brand']) self::fail('shape_unsupported');
        self::defaultObject($value->brand);
    }

    private static function defaultObject(mixed $value): void
    {
        if (!$value instanceof \stdClass) self::fail('shape_unsupported');
        if (property_exists($value, 'brandDomain') && $value->brandDomain !== null && $value->brandDomain !== '') self::fail('custom_brand');
    }

    /** Bounded structural scanner rejects decoded duplicate names at every object depth. */
    private static function decode(#[\SensitiveParameter] string $body, int $limit=self::MAX_BODY_BYTES): \stdClass
    {
        if (strlen($body) > $limit) self::fail('body_limit');
        $offset = 0; $nodes = 0; $length = strlen($body);
        $space = static function() use (&$offset, $length, $body): void {
            while ($offset < $length && str_contains(" \t\r\n", $body[$offset])) $offset++;
        };
        $string = static function() use (&$offset, $body): string {
            if (preg_match('~\G"(?:[^"\\\\\x00-\x1f]|\\\\(?:["\\\\/bfnrt]|u[0-9a-fA-F]{4}))*"~', $body, $match, 0, $offset) !== 1) self::fail('malformed_body');
            $offset += strlen($match[0]);
            try { return json_decode($match[0], false, 2, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { self::fail('malformed_body'); }
        };
        $scan = static function(int $depth) use (&$scan, &$offset, &$nodes, $length, $body, $space, $string): void {
            if ($depth > 8 || ++$nodes > 1024) self::fail('field_limit');
            $space(); $char = $body[$offset] ?? '';
            if ($char === '"') { $string(); return; }
            if ($char === '{' || $char === '[') {
                $object = $char === '{'; $end = $object ? '}' : ']'; $offset++; $space(); $seen = [];
                if (($body[$offset] ?? '') === $end) { $offset++; return; }
                while ($offset < $length) {
                    if ($object) {
                        $space(); $key = $string();
                        // Prefix prevents PHP's numeric-string key coercion from conflating names.
                        if (isset($seen['key:'.$key])) self::fail('duplicate_field');
                        $seen['key:'.$key] = true; $space();
                        if (($body[$offset++] ?? '') !== ':') self::fail('malformed_body');
                    }
                    $scan($depth + 1); $space(); $separator = $body[$offset++] ?? '';
                    if ($separator === $end) return;
                    if ($separator !== ',') self::fail('malformed_body');
                }
                self::fail('malformed_body');
            }
            if (preg_match('~\G(?:true|false|null|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)~', $body, $match, 0, $offset) !== 1) self::fail('malformed_body');
            $offset += strlen($match[0]);
        };
        $scan(0); $space();
        if ($offset !== $length) self::fail('malformed_body');
        try { $value = json_decode($body, false, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { self::fail('malformed_body'); }
        if (!$value instanceof \stdClass) self::fail('shape_unsupported');
        return $value;
    }
    private static function fail(string $reason): never { throw new StackCpLaunchFailure('brand',$reason,false); }
    public function jsonSerialize(): never { throw new \LogicException('StackCP brand cannot be serialized.'); }
    public function __serialize(): never { throw new \LogicException('StackCP brand cannot be serialized.'); }
    public function __unserialize(array $data): never { throw new \LogicException('StackCP brand cannot be restored.'); }
    public function __debugInfo(): array { return ['default_destination'=>true]; }
    private function __clone() {}
}
