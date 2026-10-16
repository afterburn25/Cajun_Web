<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIPackageForceSsl;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Supplied assignment facts only. Construction does not establish current database authority. */
final readonly class PackageForceSslBinding
{
    public array $data;
    public string $json;
    public string $sha256;
    public string $scope;

    public function __construct(array $input)
    {
        $keys=['profile_id','profile_revision','key_fingerprint','credential_sha256','package_id','client_id','link_id',
            'link_sha256','event_id','event_sha256','audit_id','audit_sha256','source_id','source_seq','source_sha256'];
        $actual=array_keys($input);sort($actual);$expected=$keys;sort($expected);
        if($actual!==$expected)throw new \InvalidArgumentException('Invalid Force SSL observation binding.');
        $data=[];
        // Assign each validated scalar by value, detaching references held by the caller.
        foreach($keys as $key){
            $value=$input[$key];
            if(in_array($key,['profile_id','link_id','source_id'],true))$valid=is_string($value)&&preg_match('/\A[a-f0-9]{32}\z/',$value)===1;
            elseif(in_array($key,['key_fingerprint','credential_sha256','link_sha256','event_sha256','audit_sha256','source_sha256'],true))$valid=is_string($value)&&preg_match('/\A[a-f0-9]{64}\z/',$value)===1;
            else $valid=is_int($value)&&$value>0&&$value<=999999999999999999;
            if(!$valid)throw new \InvalidArgumentException('Invalid Force SSL observation identity.');
            $data[$key]=$value;
        }
        $this->data=$data;
        $this->json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $this->sha256=hash('sha256',$this->json);
        $this->scope='twenty-i|'.$data['profile_id'].'|package.force-ssl|'.$this->sha256;
    }

    /** Restored objects must retain the same canonical input and derived fields. */
    public function validate():void
    {
        $this->validated();
    }

    /** Return a detached snapshot, including when restored input contains external references. */
    public function validated():self
    {
        if(!isset($this->data,$this->json,$this->sha256,$this->scope))throw new \InvalidArgumentException('Incomplete Force SSL observation binding.');
        $expected=new self($this->data);
        if($expected->data!==$this->data||$expected->json!==$this->json||!hash_equals($expected->sha256,$this->sha256)||$expected->scope!==$this->scope)throw new \InvalidArgumentException('Inconsistent Force SSL observation binding.');
        return $expected;
    }
}
