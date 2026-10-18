<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/InventoryDomain.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-domain-contracts/DomainContractBinding.php';

/** An immutable link to existing service terms. Agreement events remain the only consent lifecycle. */
final class DomainRenewalAgreementDocument {
    public const MAX_BINDINGS=128;
    public const MAX_BYTES=8192;
    public const MAX_OFFER_SECONDS=7776000; // Local 90-day offer limit, not domain eligibility.
    private function __construct(private string $bytes){}
    public static function fromArray(array $input):self {
        return self::fromJson(json_encode($input,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }
    public static function fromJson(string $bytes):self {
        if(strlen($bytes)>self::MAX_BYTES)throw new InvalidArgumentException('Renewal terms are too large.');
        $d=json_decode($bytes,true,12,JSON_THROW_ON_ERROR);
        $keys=['version','id','request_id','revision','previous_sha256','request_revision','request_sha256','request_document_sha256','agreement_id','agreement_sha256','agreement_terms_sha256','price_minor','currency','requested_years','requested_privacy','renew_privacy','registrar','credential_sha256','actor_id','created_epoch','expires_epoch'];
        if(!is_array($d)||array_keys($d)!==$keys||$d['version']!==1)throw new InvalidArgumentException('Invalid renewal agreement binding.');
        foreach(['id','request_id','agreement_id'] as $key)if(!is_string($d[$key])||!preg_match('/\A[a-f0-9]{32}\z/',$d[$key]))throw new InvalidArgumentException('Invalid renewal agreement identity.');
        foreach(['request_sha256','request_document_sha256','agreement_sha256','agreement_terms_sha256','credential_sha256'] as $key)if(!is_string($d[$key])||!preg_match('/\A[a-f0-9]{64}\z/',$d[$key]))throw new InvalidArgumentException('Invalid retained renewal terms.');
        foreach(['revision','request_revision','price_minor','requested_years','actor_id','created_epoch','expires_epoch'] as $key)if(!is_int($d[$key]))throw new InvalidArgumentException('Invalid renewal agreement number.');
        if($d['revision']<1||$d['revision']>self::MAX_BINDINGS||$d['request_revision']<0||$d['request_revision']>4294967295||$d['price_minor']<0||$d['price_minor']>99999999||$d['currency']!=='USD'||$d['requested_years']<1||$d['requested_years']>10||$d['actor_id']<1||$d['actor_id']>2147483647||$d['created_epoch']<1||$d['expires_epoch']<=$d['created_epoch']||$d['expires_epoch']-$d['created_epoch']>self::MAX_OFFER_SECONDS)throw new InvalidArgumentException('Invalid renewal agreement terms or deadline.');
        if(($d['revision']===1&&$d['previous_sha256']!==null)||($d['revision']>1&&(!is_string($d['previous_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['previous_sha256']))))throw new InvalidArgumentException('Invalid renewal offer history.');
        if((!is_bool($d['requested_privacy'])&&$d['requested_privacy']!==null)||!is_bool($d['renew_privacy'])||($d['requested_privacy']!==null&&$d['requested_privacy']!==$d['renew_privacy']))throw new InvalidArgumentException('Quote the requested privacy preference or explicitly resolve an undecided preference.');
        if(!is_array($d['registrar']))throw new InvalidArgumentException('Invalid registered-domain context.');
        $registrar=new \CajunWebPro\Foundation\TwentyIDomainContracts\DomainContractBinding($d['registrar']);
        if($registrar->data!==$d['registrar']||json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)!==$bytes)throw new InvalidArgumentException('Noncanonical renewal agreement binding.');
        return new self($bytes);
    }
    public function data():array{return json_decode($this->bytes,true,12,JSON_THROW_ON_ERROR);}
    public function json():string{return $this->bytes;}
    public function fingerprint():string{return hash('sha256',$this->bytes);}
}
