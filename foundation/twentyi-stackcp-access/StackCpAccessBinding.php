<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIStackCpAccess;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Detached facts only; the application must establish current signed authority. */
final readonly class StackCpAccessBinding {
    public const MEMBERSHIP='stackcp.membership';
    public const GRANTS='stackcp.grants';
    public array $data;
    public string $json;
    public string $sha256;
    public string $scope;
    public function __construct(array $input) {
        $keys=['operation','profile_id','profile_revision','credential_sha256','key_fingerprint','package_id','client_id','client_auth_version',
            'link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','source_id','source_seq','source_sha256',
            'actor_id','actor_auth_version','assignment_id','assignment_revision','assignment_sha256','assignment_proof',
            'auth_id','auth_revision','auth_sha256','auth_proof','auth_ciphertext_sha256','stack_identity',
            'membership_id','membership_seq','membership_sha256'];
        $actual=array_keys($input);sort($actual);$expected=$keys;sort($expected);
        if ($actual!==$expected) self::deny();
        $out=[];
        foreach ($keys as $key) {
            $v=$input[$key];
            if ($key==='operation') {if (!in_array($v,[self::MEMBERSHIP,self::GRANTS],true)) self::deny();}
            elseif ($key==='stack_identity') self::identity($v);
            elseif ($key==='membership_id') {if ($v!=='') self::hex($v,32);}
            elseif ($key==='membership_sha256') {if ($v!=='') self::hex($v,64);}
            elseif ($key==='membership_seq') {if (!is_int($v)||$v<0||$v>2147483647) self::deny();}
            elseif (in_array($key,['profile_id','client_auth_version','link_id','source_id','actor_auth_version','assignment_id','auth_id'],true)) self::hex($v,32);
            elseif (str_ends_with($key,'_sha256')||str_ends_with($key,'_proof')||$key==='key_fingerprint') self::hex($v,64);
            elseif (!is_int($v)||$v<1||$v>2147483647) self::deny();
            $out[$key]=$v; // Copy each scalar, never a caller-held reference.
        }
        if ($out['operation']===self::MEMBERSHIP) {
            if ($out['membership_id']!==''||$out['membership_seq']!==0||$out['membership_sha256']!=='') self::deny();
        } elseif ($out['membership_id']===''||$out['membership_seq']===0||$out['membership_sha256']==='') self::deny();
        $this->data=$out;$this->json=json_encode($out,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$this->sha256=hash('sha256',$this->json);
        $this->scope='twenty-i|'.$out['profile_id'].'|'.$out['operation'].'|'.$this->sha256;
    }
    public static function identity(mixed $v):string {
        if (!is_string($v)||preg_match('/\Astack-user:([1-9][0-9]{0,9})\z/',$v,$m)!==1||(int)$m[1]>2147483647) self::deny();return $v;
    }
    public static function tuple(mixed $v):string {
        // A defensive subset, not the provider's complete tuple grammar or permission model.
        if (!is_string($v)||strlen($v)>256||preg_match('/\A[a-z][a-z0-9_-]{0,31}:[1-9][0-9]{0,17}\z/',$v)!==1) self::deny();return $v;
    }
    private static function hex(mixed $v,int $length):void {if (!is_string($v)||preg_match('/\A[a-f0-9]{'.$length.'}\z/',$v)!==1) self::deny();}
    private static function deny():never {throw new \InvalidArgumentException('Invalid StackCP access observation binding.');}
    public function validated():self {
        if (!isset($this->data,$this->json,$this->sha256,$this->scope)) self::deny();$copy=new self($this->data);
        if ($copy->data!==$this->data||$copy->json!==$this->json||!hash_equals($copy->sha256,$this->sha256)||$copy->scope!==$this->scope) self::deny();return $copy;
    }
}
