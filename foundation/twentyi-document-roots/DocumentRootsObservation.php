<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDocumentRoots;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Provider-observed text only: no path resolution, confinement, filesystem use or hosting-state claim. */
final readonly class DocumentRootsObservation
{
    public const MAX_ENTRIES = 256;
    public const MAX_PATH_BYTES = 1024;
    public const REASONS = ['authentication','rate_limited','upstream_unavailable','http_status','body_limit',
        'malformed_body','shape_unsupported','entry_unsupported','duplicate_name','provider_empty_array',
        'credential_echo','transport_connect','transport_timeout','transport_tls','transport_protocol',
        'transport_unavailable','transport_unknown'];

    public DocumentRootsBinding $binding;
    public array $entries;
    public string $scope;

    /** @param list<array{name:string,path:string}> $entries */
    public function __construct(DocumentRootsBinding $binding, public string $state, array $entries,
        public string $reason, public int $observedAt)
    {
        if ($observedAt < 0 || !in_array($state, ['complete','unavailable'], true) || !array_is_list($entries)
            || count($entries) > self::MAX_ENTRIES || ($state === 'complete' && $reason !== '')
            || ($state === 'unavailable' && ($entries !== [] || !in_array($reason, self::REASONS, true)))) {
            throw new \InvalidArgumentException('Invalid document roots observation.');
        }
        $this->binding = DocumentRootsBinding::validated($binding);
        $copy = []; $seen = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || array_keys($entry) !== ['name','path'] || !self::validName($entry['name'])
                || !self::validPath($entry['path']) || isset($seen[$entry['name']])) {
                throw new \InvalidArgumentException('Invalid observed document root.');
            }
            $seen[$entry['name']] = true;
            $copy[] = ['name'=>$entry['name'], 'path'=>$entry['path']];
        }
        $this->entries = $copy;
        $this->scope = $this->binding->scope;
    }

    /** Deliberately narrow ASCII DNS-name subset, without normalization or membership inference. */
    public static function validName(mixed $value): bool
    {
        if (!is_string($value) || strlen($value) > 253 || !str_contains($value, '.') || filter_var($value, FILTER_VALIDATE_IP)) return false;
        foreach (explode('.', $value) as $label) if (!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $label)) return false;
        return true;
    }

    /** Empty and unusual-looking paths remain exact text; acceptance does not establish safe path confinement. */
    public static function validPath(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= self::MAX_PATH_BYTES && preg_match('//u', $value) === 1
            && !preg_match('/[\x00-\x1f\x7f]/', $value);
    }
}
