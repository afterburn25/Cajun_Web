<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIHostingDns;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding;

/** Local proposal identity only. No package-domain relationship or DNS authority is established here. */
final readonly class HostingDnsContext
{
    public array $data;
    public string $json;
    public string $sha256;

    public function __construct(PackagePhpVersionBinding $package, string $packageDomainId, string $zoneName)
    {
        // Reuse the existing validated assignment witness without inheriting its operation authority.
        $copy = new PackagePhpVersionBinding($package->data);
        if ($copy->json !== $package->json || !hash_equals($copy->sha256, $package->sha256) || $copy->scope !== $package->scope) {
            throw new \InvalidArgumentException('Inconsistent hosting proposal context.');
        }
        self::opaqueId($packageDomainId);
        self::zoneName($zoneName);
        // Decode the validated canonical bytes to detach any caller-owned scalar references.
        $this->data = ['format'=>'cwp-hosting-dns-context/v1', 'package'=>json_decode($copy->json, true, 8, JSON_THROW_ON_ERROR),
            'package_domain_id'=>$packageDomainId, 'zone_name'=>$zoneName, 'identity_status'=>'unverified'];
        $this->json = json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->sha256 = hash('sha256', $this->json);
    }

    /** Opaque local reference, not an inferred provider ID grammar or URL segment. */
    public static function opaqueId(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 128 || trim($value) !== $value
            || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new \InvalidArgumentException('Enter a bounded local DNS identifier.');
        }
        return $value;
    }

    /** Deliberately narrow local ASCII zone/target policy; no normalization or network lookup. */
    public static function zoneName(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 253 || !str_contains($value, '.') || filter_var($value, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Use a canonical ASCII DNS name.');
        }
        foreach (explode('.', $value) as $label) {
            if (!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $label)) {
                throw new \InvalidArgumentException('Use a canonical ASCII DNS name.');
            }
        }
        return $value;
    }

    public static function validated(self $context): self
    {
        $data = $context->data;
        if (array_keys($data) !== ['format','package','package_domain_id','zone_name','identity_status']
            || !is_array($data['package']) || !is_string($data['package_domain_id']) || !is_string($data['zone_name'])) {
            throw new \InvalidArgumentException('Inconsistent hosting proposal context.');
        }
        $copy = new self(new PackagePhpVersionBinding($data['package']), $data['package_domain_id'], $data['zone_name']);
        if ($copy->data !== $data || $copy->json !== $context->json || !hash_equals($copy->sha256, $context->sha256)) {
            throw new \InvalidArgumentException('Inconsistent hosting proposal context.');
        }
        return $copy;
    }
}
