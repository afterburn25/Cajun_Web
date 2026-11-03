<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIPackagePhp;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

require_once __DIR__.'/PackagePhpVersion.php';

/** Internal normalized rows only. This class does not parse a provider response or grant authority. */
final readonly class PackagePhpOptionSet
{
    /** Local defensive bounds, not provider capabilities or response-schema claims. */
    public const MAX_OPTIONS = 128;
    public const MAX_LABEL_BYTES = 256;
    public const MAX_HANDLER_BYTES = 128;
    public const MAX_JSON_BYTES = 65536;

    private function __construct(public array $options, public string $json, public string $sha256) {}

    /**
     * A future verified adapter supplies exact {value,label,handler} application records.
     * Row order is retained; field order is canonical. Empty sets admit no selection.
     */
    public static function fromNormalizedRows(array $rows): self
    {
        if (!array_is_list($rows) || count($rows) > self::MAX_OPTIONS) {
            throw new \InvalidArgumentException('Invalid normalized PHP option list.');
        }
        $options = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new \InvalidArgumentException('Invalid normalized PHP option.');
            $keys = array_keys($row);
            sort($keys, SORT_STRING);
            if ($keys !== ['handler', 'label', 'value']) throw new \InvalidArgumentException('Invalid normalized PHP option fields.');
            $option = [
                'value' => PackagePhpVersion::value($row['value']),
                'label' => self::displayText($row['label'], self::MAX_LABEL_BYTES),
                'handler' => self::displayText($row['handler'], self::MAX_HANDLER_BYTES),
            ];
            // Never key by value: PHP converts some numeric-looking string array keys.
            foreach ($options as $existing) {
                if ($existing['value'] === $option['value']) throw new \InvalidArgumentException('Duplicate normalized PHP option value.');
            }
            $options[] = $option;
        }
        $json = json_encode(['format' => 1, 'options' => $options], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > self::MAX_JSON_BYTES) throw new \InvalidArgumentException('Normalized PHP options exceed their storage limit.');
        return new self($options, $json, hash('sha256', $json));
    }

    /** Exact membership only: no trim, case-folding, numeric coercion or fallback. */
    public function select(mixed $value): array
    {
        $value = PackagePhpVersion::value($value);
        foreach ($this->options as $option) {
            if ($option['value'] === $value) return $option;
        }
        throw new \InvalidArgumentException('The selected PHP version is not in this option set.');
    }

    private static function displayText(mixed $value, int $maximumBytes): string
    {
        if (!is_string($value) || strlen($value) > $maximumBytes || preg_match('//u', $value) !== 1 ||
            preg_match('/\A[\p{Z}\s]*\z/u', $value) === 1 || preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            throw new \InvalidArgumentException('Invalid normalized PHP option display text.');
        }
        return $value;
    }

    /** Native PHP serialization is not a second input format. */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('Construct normalized PHP options through their validated factory.');
    }
}
