<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\ProviderOperation;
use CajunWebPro\Foundation\Provider\TransportRequest;
use InvalidArgumentException;

/**
 * Translates a validated ProviderOperation into one bounded provider
 * write request, or refuses by throwing InvalidArgumentException.
 *
 * Hard rules:
 *  - Only registered, shape-complete operations can produce a request.
 *  - Path slots accept only positive integer ids (the reseller self
 *    alias '*' additionally for resellerId); nothing else can ever
 *    reach the URL path.
 *  - Body keys are exactly the documented allowlist; unknown or
 *    missing-required fields are refused.
 *  - The body is bounded canonical JSON under MAX_BODY_BYTES.
 *  - The request NEVER carries an Authorization header. Credentials
 *    attach at the execution boundary (adapter/gateway), after grants,
 *    ledger claims and reauthorization — not inside a value object.
 */
final class TwentyIRequestBuilder
{
    public const HOST = 'https://api.20i.com';
    public const MAX_BODY_BYTES = 8192;
    public const TIMEOUT_SECONDS = 30.0;

    private const MAX_STRING = 1024;
    private const MAX_LIST = 64;
    private const MAX_DEPTH = 8;
    private const MAX_NODES = 256;

    public function __construct(private TwentyIOperationRegister $register)
    {
    }

    public function build(ProviderOperation $operation): TransportRequest
    {
        $spec = $this->register->get($operation->name);
        if ($spec === null) {
            throw new InvalidArgumentException('Operation ' . $operation->name . ' is not in the documented write catalog.');
        }
        if (!$spec->shapeComplete) {
            throw new InvalidArgumentException('Operation ' . $operation->name . ' is documented without body fields; refusing to invent them.');
        }

        $params = $operation->params;
        $path = $spec->path;
        foreach ($spec->slots as $slot) {
            $value = $params[$slot] ?? null;
            unset($params[$slot]);
            $path = str_replace('{' . $slot . '}', $this->slotValue($slot, $value), $path);
        }

        $body = [];
        $nodes = 0;
        foreach ($params as $field => $value) {
            if (!is_string($field) || !isset($spec->fields[$field])) {
                throw new InvalidArgumentException('Field ' . (is_string($field) ? $field : '?') . ' is not documented for ' . $operation->name . '.');
            }
            $body[$field] = self::bounded($spec->fields[$field], $field, $value, 0, $nodes);
        }
        foreach ($spec->required as $field) {
            if (!array_key_exists($field, $body)) {
                throw new InvalidArgumentException('Required field ' . $field . ' is missing.');
            }
        }

        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) > self::MAX_BODY_BYTES) {
            throw new InvalidArgumentException('Serialized request body exceeds the bounded size.');
        }

        return new TransportRequest($spec->method, self::HOST . $path, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $json, self::TIMEOUT_SECONDS);
    }

    private function slotValue(string $slot, mixed $value): string
    {
        if ($slot === 'resellerId' && $value === '*') {
            return '*'; // documented self alias for the authenticated reseller account
        }
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException('Path slot ' . $slot . ' requires a positive allocated id.');
        }
        return (string) $value;
    }

    private static function bounded(string $type, string $field, mixed $value, int $depth, int &$nodes): mixed
    {
        if (++$nodes > self::MAX_NODES || $depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Field ' . $field . ' exceeds structural bounds.');
        }
        switch ($type) {
            case 'int':
                if (!is_int($value) || $value <= 0) {
                    throw new InvalidArgumentException('Field ' . $field . ' requires a positive integer.');
                }
                return $value;
            case 'bool':
                if (!is_bool($value)) {
                    throw new InvalidArgumentException('Field ' . $field . ' requires a boolean.');
                }
                return $value;
            case 'hostname':
                return self::hostname($field, $value);
            case 'hostname_list':
                if (!is_array($value) || count($value) > self::MAX_LIST || array_values($value) !== $value) {
                    throw new InvalidArgumentException('Field ' . $field . ' requires a bounded list of hostnames.');
                }
                return array_map(static fn(mixed $item): string => self::hostname($field, $item), $value);
            case 'map':
                if (!is_array($value) || array_values($value) === $value) {
                    throw new InvalidArgumentException('Field ' . $field . ' requires a string-keyed map.');
                }
                $out = [];
                foreach ($value as $key => $item) {
                    if (!is_string($key) || strlen($key) > 64 || preg_match('/[\x00-\x1f\x7f]/', $key)) {
                        throw new InvalidArgumentException('Field ' . $field . ' has an invalid map key.');
                    }
                    $out[$key] = self::bounded('json', $field, $item, $depth + 1, $nodes);
                }
                return $out;
            case 'json':
                return self::jsonValue($field, $value, $depth, $nodes);
            default: // 'string'
                return self::string($field, $value);
        }
    }

    private static function jsonValue(string $field, mixed $value, int $depth, int &$nodes): mixed
    {
        if (is_array($value)) {
            if (++$nodes > self::MAX_NODES || $depth >= self::MAX_DEPTH || count($value) > self::MAX_LIST) {
                throw new InvalidArgumentException('Field ' . $field . ' exceeds structural bounds.');
            }
            $list = array_values($value) === $value;
            $out = [];
            foreach ($value as $key => $item) {
                if (!$list && (!is_string($key) || strlen($key) > 64 || preg_match('/[\x00-\x1f\x7f]/', $key))) {
                    throw new InvalidArgumentException('Field ' . $field . ' has an invalid key.');
                }
                $out[$key] = self::jsonValue($field, $item, $depth + 1, $nodes);
            }
            return $out;
        }
        if (is_string($value)) {
            return self::string($field, $value);
        }
        if (is_int($value) || is_bool($value) || $value === null) {
            return $value;
        }
        if (is_float($value) && is_finite($value)) {
            return $value;
        }
        throw new InvalidArgumentException('Field ' . $field . ' accepts only bounded JSON values.');
    }

    private static function string(string $field, mixed $value): string
    {
        if (!is_string($value) || strlen($value) > self::MAX_STRING
            || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Field ' . $field . ' requires bounded printable text.');
        }
        return $value;
    }

    private static function hostname(string $field, mixed $value): string
    {
        $value = self::string($field, $value);
        if (strlen($value) > 253 || !str_contains($value, '.')
            || preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i', $value) !== 1) {
            throw new InvalidArgumentException('Field ' . $field . ' requires a valid hostname.');
        }
        return $value;
    }
}
