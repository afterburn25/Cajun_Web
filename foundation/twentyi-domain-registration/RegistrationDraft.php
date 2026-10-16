<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDomainRegistration;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once __DIR__.'/RegistrationContact.php';
require_once __DIR__.'/../twentyi-inventory/InventoryDomain.php';
use CajunWebPro\Foundation\TwentyIInventory\InventoryDomain;

/** Local immutable requested scope. No price, payment, authorization or provider acceptance. */
final readonly class RegistrationDraft implements \JsonSerializable {
    private function __construct(private array $document) {}
    public static function name(mixed $value):string {
        if(!is_string($value))throw new \InvalidArgumentException('Use a canonical ASCII domain name.');
        $name=InventoryDomain::fromItem((object)['id'=>1,'name'=>$value])->name;
        foreach(explode('.',$name) as $part)if(str_starts_with($part,'xn--'))throw new \InvalidArgumentException('Internationalised domain names are outside this registration subset.');
        return $name;
    }
    public static function fromArray(#[\SensitiveParameter] array $data):self {
        $keys=array_keys($data);sort($keys);
        if($keys!==['client_id','contact','domain','id','nameservers','privacy_service','registry_acceptance','requested_years','version'])throw new \InvalidArgumentException('Review the exact registration draft fields.');
        if($data['version']!==1||!is_string($data['id'])||!preg_match('/\A[a-f0-9]{32}\z/',$data['id'])||!is_int($data['client_id'])||$data['client_id']<1||$data['client_id']>999999999999999999||!is_int($data['requested_years'])||$data['requested_years']<1||$data['requested_years']>10||!is_bool($data['privacy_service']))throw new \InvalidArgumentException('Review the client, requested term and explicit privacy choice.');
        $domain=self::name($data['domain']);
        if(!is_array($data['contact']))throw new \InvalidArgumentException('Review the registrant contact.');
        $contact=RegistrationContact::fromArray($data['contact']);
        if(!is_array($data['nameservers'])||!array_is_list($data['nameservers'])||!$data['nameservers']||count($data['nameservers'])>13)throw new \InvalidArgumentException('Review the explicit nameserver list.');
        $ns=[];foreach($data['nameservers'] as $name){$name=self::name($name);if(in_array($name,$ns,true))throw new \InvalidArgumentException('Duplicate nameservers are not accepted.');$ns[]=$name;}
        $acceptance=$data['registry_acceptance'];$receipt=null;
        if($acceptance!==null){
            if(!str_ends_with($domain,'.ca')||!is_array($acceptance))throw new \InvalidArgumentException('Registry acceptance must match this domain.');
            $keys=array_keys($acceptance);sort($keys);
            if($keys!==['accepted_by','accepted_epoch','domain','terms_sha256']||$acceptance['domain']!==$domain||$acceptance['accepted_by']!=='client:'.$data['client_id']||!is_string($acceptance['terms_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$acceptance['terms_sha256'])||!is_int($acceptance['accepted_epoch'])||$acceptance['accepted_epoch']<1||$acceptance['accepted_epoch']>9999999999)throw new \InvalidArgumentException('Review the direct registrant agreement record.');
            $receipt=['domain'=>$domain,'accepted_by'=>(string)$acceptance['accepted_by'],'accepted_epoch'=>(int)$acceptance['accepted_epoch'],'terms_sha256'=>(string)$acceptance['terms_sha256']];
        }
        return new self(['version'=>1,'id'=>(string)$data['id'],'client_id'=>(int)$data['client_id'],'domain'=>$domain,'requested_years'=>(int)$data['requested_years'],'privacy_service'=>(bool)$data['privacy_service'],'contact'=>$contact->privateFields(),'nameservers'=>$ns,'registry_acceptance'=>$receipt]);
    }
    /** Exact local document bytes only; this is deliberately not provider JSON. */
    public static function fromPrivateJson(#[\SensitiveParameter] string $json):self {
        if(strlen($json)>32768)throw new \InvalidArgumentException('Registration draft exceeds its local bound.');
        try{$data=json_decode($json,true,8,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new \InvalidArgumentException('Registration draft is not canonical.');}
        if(!is_array($data))throw new \InvalidArgumentException('Registration draft is not canonical.');
        $draft=self::fromArray($data);
        if($json!==$draft->privateJson())throw new \InvalidArgumentException('Registration draft is not canonical.');
        return $draft;
    }
    public function privateDocument():array {return $this->document;}
    public function privateJson():string {return json_encode($this->document,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
    /** An equality fingerprint, never an authorization signature. */
    public function fingerprint():string {return hash('sha256',$this->privateJson());}
    /** Explicit authorized-view projection; no contact data or approval claim. */
    public function selection():array {return ['domain'=>$this->document['domain'],'requested_years'=>$this->document['requested_years'],'privacy_service'=>$this->document['privacy_service'],'nameservers'=>$this->document['nameservers'],'ca_acceptance_recorded'=>$this->document['registry_acceptance']!==null];}
    /**
     * Field review projection only. Missing term/response contract prevents a request.
     * Empty extension is kept as the owner's illustrated empty PHP array.
     */
    public function documentedFields():array {
        $d=$this->document;$out=['name'=>$d['domain'],'contact'=>$d['contact'],'nameservers'=>$d['nameservers'],'privacyService'=>$d['privacy_service']];
        if($d['registry_acceptance']!==null)$out['caRegistryAgreement']=true;
        return $out;
    }
    public function jsonSerialize():array {return ['kind'=>'registration_draft','version'=>1,'private_fields'=>'redacted'];}
    public function __debugInfo():array {return $this->jsonSerialize();}
    public function __serialize():array {throw new \LogicException('Use explicit protected registration storage.');}
}
