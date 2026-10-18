<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyICloudAssignment;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Detached local authority facts, never proof of provider health or entitlement. */
final readonly class CloudAssignmentBinding
{
    public array $data;
    public string $json;
    public string $sha256;
    public function __construct(array $data)
    {
        $keys=['profile_id','profile_revision','credential_sha256','key_fingerprint','server_id','client_id','link_id','link_revision','link_sha256','event_id','event_proof','source_id','source_seq','start_proof','finish_proof','server_sha256'];
        if(array_keys($data)!==$keys)throw new \InvalidArgumentException('Cloud assignment binding fields are invalid.');
        $copy=[];
        foreach($keys as $key){
            $value=$data[$key];
            if(in_array($key,['profile_revision','server_id','client_id','link_revision','event_id','source_seq'],true)){
                if(!is_int($value)||$value<1||$value>999999999999999999)throw new \InvalidArgumentException('Cloud assignment numeric identity is invalid.');
            }else{
                $length=in_array($key,['profile_id','link_id','source_id'],true)?32:64;
                if(!is_string($value)||!preg_match('/\A[a-f0-9]{'.$length.'}\z/D',$value))throw new \InvalidArgumentException('Cloud assignment retained identity is invalid.');
            }
            $copy[$key]=$value;
        }
        $this->data=$copy;$this->json=json_encode($copy,JSON_THROW_ON_ERROR);$this->sha256=hash('sha256',$this->json);
    }
    /** Rebuild restored objects and reject forged derived fields before use. */
    public static function validated(self $value):self
    {
        $copy=new self($value->data);
        if($copy->json!==$value->json||!hash_equals($copy->sha256,$value->sha256))throw new \InvalidArgumentException('Cloud assignment binding needs recovery.');
        return $copy;
    }
}
