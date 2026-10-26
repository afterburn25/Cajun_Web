<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Domains;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A normalized ASCII domain name. Validation follows LDH rules:
 * labels of 1–63 chars, letters/digits/hyphen, no leading or trailing
 * hyphen, at least two labels, total length ≤253. Internationalized
 * names must be punycode-encoded by the adapter before reaching this
 * component — raw non-ASCII input is rejected, not guessed at.
 */
final readonly class DomainName
{
    public string $name;
    public string $sld;
    public string $tld;

    public function __construct(string $name)
    {
        $normalized = strtolower(rtrim(trim($name), '.'));
        if ($normalized === '' || strlen($normalized) > 253 || preg_match('/[^\x21-\x7e]/', $normalized)) {
            throw new InvalidArgumentException('Domain name must be printable ASCII of 1–253 characters.');
        }
        $labels = explode('.', $normalized);
        if (count($labels) < 2) {
            throw new InvalidArgumentException('A domain name needs at least a name and a TLD.');
        }
        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63
                || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
                throw new InvalidArgumentException('Invalid domain label: ' . $label);
            }
        }
        $tld = end($labels);
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $tld)) {
            throw new InvalidArgumentException('Domain TLD must start with a letter.');
        }
        $this->name = $normalized;
        $this->tld = $tld;
        $this->sld = $labels[count($labels) - 2];
    }
}
