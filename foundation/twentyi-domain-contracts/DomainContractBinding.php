<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDomainContracts;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
use CajunWebPro\Foundation\TwentyIInventory\InventoryDomain;
/** Exact local authority captured before one resource read; never provider response data. */
final readonly class DomainContractBinding
{
    public array $data;
    public string $json;
    public string $sha256;
    public string $scope;
    public function __construct(array $input)
    {
        $keys=['profile_id','profile_revision','key_fingerprint','domain_id','domain','link_id','link_revision','client_id','link_event_id','link_event_sha256','link_audit_id','link_audit_sha256','source_refresh_id','source_seq','source_sha256'];
        if(array_keys($input)!==$keys)throw new \InvalidArgumentException('Invalid registered-domain read binding.');
        $d=[];foreach($input as $k=>$v){
            if(in_array($k,['profile_id','link_id','source_refresh_id'],true)){$ok=is_string($v)&&preg_match('/\A[a-f0-9]{32}\z/',$v);}
            elseif(in_array($k,['key_fingerprint','link_event_sha256','link_audit_sha256','source_sha256'],true)){$ok=is_string($v)&&preg_match('/\A[a-f0-9]{64}\z/',$v);}
            elseif($k==='domain'){$ok=is_string($v);}
            else{$ok=is_int($v)&&$v>0&&$v<=999999999999999999;}
            if(!$ok)throw new \InvalidArgumentException('Invalid registered-domain read binding.');$d[$k]=$v;
        }
        foreach(['profile_revision','link_revision','client_id'] as $k)if($d[$k]>4294967295)throw new \InvalidArgumentException('Invalid registered-domain read binding.');
        InventoryDomain::fromItem((object)['id'=>$d['domain_id'],'name'=>$d['domain']]);
        $this->data=$d;$this->json=json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$this->sha256=hash('sha256',$this->json);
        $this->scope='twenty-i|'.$d['profile_id'].'|domain.contract|'.$this->sha256;
    }
}
