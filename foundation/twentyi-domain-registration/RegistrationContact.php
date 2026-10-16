<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIDomainRegistration;
if(PHP_SAPI!=='cli'&&!defined('CWP_BOOT')){http_response_code(404);exit;}

/** Bounded documented contact fields, not a registry eligibility validator. */
final readonly class RegistrationContact implements \JsonSerializable {
    public const EXTENSIONS=['type','trad-name','co-no','language','cro-number','nationality-code','reg_code','country_of_citizenship','eligibility_type','eligibility_name','eligibility_id_type','eligibility_id','registrant_id_type','registrant_id','policy_reason','es_id_type','es_id_number','date_of_birth','registrant_vat_id','registration_number','tax_number','br_register_number','fax'];
    private function __construct(private array $fields) {}
    public static function fromArray(#[\SensitiveParameter] array $data):self {
        $keys=array_keys($data);sort($keys);
        if($keys!==['address','cc','city','email','extension','name','organisation','pc','sp','telephone'])throw new \InvalidArgumentException('Review the documented registrant contact fields.');
        $out=[];
        foreach(['organisation'=>200,'name'=>200,'address'=>1024,'telephone'=>64,'email'=>254,'cc'=>2,'pc'=>64,'sp'=>200,'city'=>200] as $key=>$limit){
            $value=$data[$key];
            if(!is_string($value)||strlen($value)>$limit||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value)||trim($value)!==$value||($value===''&&!in_array($key,['organisation','pc','sp'],true)))throw new \InvalidArgumentException('Review the bounded registrant contact text.');
            $out[$key]=(string)$value;
        }
        if(!preg_match('/\A[A-Z]{2}\z/',$out['cc'])||filter_var($out['email'],FILTER_VALIDATE_EMAIL)===false)throw new \InvalidArgumentException('Review the registrant country code and email syntax.');
        $extension=$data['extension'];
        if(!is_array($extension)||count($extension)>count(self::EXTENSIONS))throw new \InvalidArgumentException('Review the documented registry contact fields.');
        $ext=[];
        foreach($extension as $key=>$value){
            if(!is_string($key)||!in_array($key,self::EXTENSIONS,true)||!is_string($value)||$value===''||strlen($value)>512||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value)||trim($value)!==$value)throw new \InvalidArgumentException('Review the documented registry contact fields.');
            $ext[$key]=(string)$value;
        }
        ksort($ext,SORT_STRING);$out['extension']=$ext;
        return new self($out);
    }
    /** Explicit private-data export: native storage must encrypt this, never log it. */
    public function privateFields():array {return $this->fields;}
    public function jsonSerialize():array {return ['kind'=>'registration_contact','private_fields'=>'redacted'];}
    public function __debugInfo():array {return $this->jsonSerialize();}
    public function __serialize():array {throw new \LogicException('Use explicit protected contact storage.');}
}

