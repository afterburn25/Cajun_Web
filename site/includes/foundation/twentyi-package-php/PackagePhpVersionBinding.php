<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIPackagePhp;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Exact local assignment and retained package-list source; no provider ownership inference. */
final readonly class PackagePhpVersionBinding
{
    public array $data;
    public string $json;
    public string $sha256;
    public string $scope;

    public function __construct(array $data)
    {
        $keys=['profile_id','profile_revision','key_fingerprint','credential_sha256','package_id','client_id','link_id',
            'link_sha256','event_id','event_sha256','audit_id','audit_sha256',
            'source_id','source_seq','source_sha256'];
        $actual=array_keys($data);sort($actual);$expected=$keys;sort($expected);
        if($actual!==$expected)throw new \InvalidArgumentException('Invalid package observation binding.');
        foreach(['profile_id','link_id','source_id'] as $key)if(!is_string($data[$key])||preg_match('/\A[a-f0-9]{32}\z/',$data[$key])!==1)throw new \InvalidArgumentException('Invalid package observation identity.');
        foreach(['key_fingerprint','credential_sha256','link_sha256','event_sha256','audit_sha256','source_sha256'] as $key)if(!is_string($data[$key])||preg_match('/\A[a-f0-9]{64}\z/',$data[$key])!==1)throw new \InvalidArgumentException('Invalid package observation witness.');
        foreach(['profile_revision','client_id','event_id','audit_id','source_seq'] as $key)if(!is_int($data[$key])||$data[$key]<1)throw new \InvalidArgumentException('Invalid package observation sequence.');
        self::packageId($data['package_id']);
        $this->data=array_replace(array_fill_keys($keys,null),$data);
        $this->json=json_encode($this->data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $this->sha256=hash('sha256',$this->json);
        $this->scope='twenty-i|'.$data['profile_id'].'|package.php-version|'.$this->sha256;
    }

    /** Allocated numeric ID follows the existing package identity bound. */
    public static function packageId(mixed $value): int
    {
        if(!is_int($value)||$value<1||$value>999999999999999999)throw new \InvalidArgumentException('Invalid allocated package identity.');
        return $value;
    }
}
