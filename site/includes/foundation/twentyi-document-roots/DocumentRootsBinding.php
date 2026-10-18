<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDocumentRoots;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding;

/** Operation-specific local assignment witness; current authority remains the application caller's duty. */
final readonly class DocumentRootsBinding
{
    public const OPERATION = 'package.document-roots';
    public array $data;
    public string $json;
    public string $sha256;
    public string $scope;

    public function __construct(PackagePhpVersionBinding $package)
    {
        $copy = new PackagePhpVersionBinding($package->data);
        if ($copy->json !== $package->json || !hash_equals($copy->sha256, $package->sha256) || $copy->scope !== $package->scope) {
            throw new \InvalidArgumentException('Inconsistent package observation witness.');
        }
        // Decode validated canonical bytes to detach caller-owned scalar references.
        $this->data = ['operation'=>self::OPERATION, 'package'=>json_decode($copy->json, true, 8, JSON_THROW_ON_ERROR)];
        $this->json = json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->sha256 = hash('sha256', $this->json);
        $this->scope = 'twenty-i|'.$this->data['package']['profile_id'].'|'.self::OPERATION.'|'.$this->sha256;
    }

    public static function validated(self $binding): self
    {
        if (array_keys($binding->data) !== ['operation','package'] || !is_array($binding->data['package'])) {
            throw new \InvalidArgumentException('Inconsistent document roots binding.');
        }
        $copy = new self(new PackagePhpVersionBinding($binding->data['package']));
        if ($copy->data !== $binding->data || $copy->json !== $binding->json
            || !hash_equals($copy->sha256, $binding->sha256) || $copy->scope !== $binding->scope) {
            throw new \InvalidArgumentException('Inconsistent document roots binding.');
        }
        return $copy;
    }
}
