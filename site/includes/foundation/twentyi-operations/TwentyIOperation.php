<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One documented provider write operation.
 *
 * A descriptor is documentation evidence only: it records the literal
 * method, path template, path slots and the exact body fields the
 * owner-supplied API reference shows. `shapeComplete` is false when the
 * reference names an endpoint without showing its body fields; such
 * operations are catalogued for completeness but the request builder
 * refuses them — inventing undocumented fields is not allowed.
 *
 * Field types: 'int' (positive integer), 'bool', 'string' (bounded
 * printable UTF-8), 'hostname' (DNS name), 'hostname_list', 'map'
 * (bounded string-keyed JSON object) and 'json' (any bounded JSON
 * value). 'json' is used only where the reference names the field
 * without showing its value shape; unknown keys are still refused.
 */
final readonly class TwentyIOperation
{
    /**
     * @param list<string> $slots
     * @param array<string,string> $fields
     * @param list<string> $required
     */
    public function __construct(
        public string $name,
        public string $method,
        public string $path,
        public array $slots,
        public array $fields,
        public array $required = [],
        public bool $shapeComplete = true,
        public bool $destructive = false,
        public bool $mayChargeBilling = false,
        public bool $mayTriggerEmail = false,
    ) {
        if (!preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $name)) {
            throw new InvalidArgumentException('Operation names use dotted lowercase form.');
        }
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException('Write descriptors must use a mutating HTTP method.');
        }
        if (!str_starts_with($path, '/') || str_contains($path, '..')) {
            throw new InvalidArgumentException('Operation paths must be absolute without traversal.');
        }
        foreach ($slots as $slot) {
            if (!str_contains($path, '{' . $slot . '}')) {
                throw new InvalidArgumentException('Path slot ' . $slot . ' is not present in the path template.');
            }
        }
        foreach ($fields as $field => $type) {
            if (!is_string($field) || $field === '' || strlen($field) > 64
                || !in_array($type, ['int', 'id_list', 'package_map', 'bool', 'string', 'hostname', 'hostname_list', 'map', 'json'], true)) {
                throw new InvalidArgumentException('Field declarations need a name and a known type.');
            }
        }
        foreach ($required as $field) {
            if (!isset($fields[$field])) {
                throw new InvalidArgumentException('Required field ' . $field . ' is not declared.');
            }
        }
        if (!$shapeComplete && $fields !== []) {
            throw new InvalidArgumentException('A shape-incomplete operation must not declare body fields.');
        }
    }
}
