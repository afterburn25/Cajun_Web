<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements_document.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-operations/DomainRenewal.php';
use CajunWebPro\Foundation\TwentyI\Operations\DomainRenewal;

/** Immutable purpose allocation; never creates an invoice or records a payment. */
final class DomainRenewalInvoiceDocument {
    public const MAX_BYTES=32768;
    private function __construct(private string $bytes){}
    public static function keys(array $d,array $keys):void {if(array_keys($d)!==$keys)throw new InvalidArgumentException('Invalid renewal funding fields.');}
    public static function id(mixed $v):void {if(!is_string($v)||!preg_match('/\A[a-f0-9]{32}\z/',$v))throw new InvalidArgumentException('Invalid renewal funding identity.');}
    public static function hash(mixed $v):void {if(!is_string($v)||!preg_match('/\A[a-f0-9]{64}\z/',$v))throw new InvalidArgumentException('Invalid renewal funding fingerprint.');}
    public static function number(mixed $v,int $min=1,int $max=2147483647):void {if(!is_int($v)||$v<$min||$v>$max)throw new InvalidArgumentException('Invalid renewal funding number.');}
    public static function text(mixed $v,int $max,bool $empty=false):void {if(!is_string($v)||(!$empty&&$v==='')||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))throw new InvalidArgumentException('Invalid renewal funding text.');}
    public static function json(array $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
    public static function fromArray(array $d):self {return self::fromJson(self::json($d));}
    public static function fromJson(string $bytes):self {
        if(strlen($bytes)>self::MAX_BYTES)throw new InvalidArgumentException('Renewal funding exceeds its limit.');$d=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);if(!is_array($d))throw new InvalidArgumentException('Invalid renewal funding.');
        self::keys($d,['version','id','binding','binding_sha256','agreement_revision','agreement_sha256','invoice','actor_id','created_epoch']);
        if($d['version']!==1||!is_array($d['binding'])||!is_array($d['invoice']))throw new InvalidArgumentException('Invalid renewal funding version.');self::id($d['id']);self::hash($d['binding_sha256']);self::hash($d['agreement_sha256']);self::number($d['agreement_revision']);self::number($d['actor_id']);self::number($d['created_epoch'],1,PHP_INT_MAX);
        $b=DomainRenewalAgreementDocument::fromArray($d['binding']);$terms=$b->data();if(!hash_equals($b->fingerprint(),$d['binding_sha256'])||$terms['price_minor']<1||$d['created_epoch']<$terms['created_epoch'])throw new InvalidArgumentException('Renewal funding differs from agreed terms.');
        $i=$d['invoice'];self::keys($i,['id','project_id','client_id','sha256','reference','title','amount_minor','currency','instructions','created_at']);foreach(['id','project_id','client_id'] as $key)self::number($i[$key]);self::hash($i['sha256']);self::text($i['reference'],40);self::text($i['title'],160);self::text($i['instructions'],6000,true);self::text($i['created_at'],19);self::number($i['amount_minor'],1,99999999);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$i['created_at'],new DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d H:i:s')!==$i['created_at']||$date->getTimestamp()>$d['created_epoch']||$i['client_id']!==$terms['registrar']['client_id']||$i['amount_minor']!==$terms['price_minor']||$i['currency']!=='USD')throw new InvalidArgumentException('Invoice identity differs from renewal terms.');
        if(self::json($d)!==$bytes)throw new InvalidArgumentException('Noncanonical renewal funding.');return new self($bytes);
    }
    public function data():array{return json_decode($this->bytes,true,16,JSON_THROW_ON_ERROR);}
    public function jsonBytes():string{return $this->bytes;}
    public function fingerprint():string{return hash('sha256',$this->bytes);}
}

/** One finite owner approval; the operation ledger remains the only execution journal. */
final class DomainRenewalDispatchDocument {
    private function __construct(private string $bytes){}
    public static function fromArray(array $d):self {return self::fromJson(DomainRenewalInvoiceDocument::json($d));}
    public static function fromJson(string $bytes):self {
        // Existing grant scope_params is VARCHAR(4096); do not silently truncate its immutable context.
        if(strlen($bytes)>4096)throw new InvalidArgumentException('Renewal approval exceeds its limit.');$d=json_decode($bytes,true,12,JSON_THROW_ON_ERROR);if(!is_array($d))throw new InvalidArgumentException('Invalid renewal approval.');
        DomainRenewalInvoiceDocument::keys($d,['version','id','allocation_id','allocation_sha256','request_id','binding_id','client_id','registrar','credential_sha256','agreement_revision','agreement_sha256','payment_review_sha256','receipt_ids','params','reviewed','review_sha256','actor_id','actor_auth','approved_epoch','expires_epoch']);
        if($d['version']!==1||!is_array($d['registrar'])||!is_array($d['params'])||!is_array($d['reviewed'])||!is_array($d['receipt_ids'])||!array_is_list($d['receipt_ids'])||!$d['receipt_ids']||count($d['receipt_ids'])>4096)throw new InvalidArgumentException('Invalid renewal approval context.');
        foreach(['id','allocation_id','request_id','binding_id'] as $k)DomainRenewalInvoiceDocument::id($d[$k]);foreach(['allocation_sha256','credential_sha256','agreement_sha256','payment_review_sha256','review_sha256','actor_auth'] as $k)DomainRenewalInvoiceDocument::hash($d[$k]);foreach(['client_id','agreement_revision','actor_id'] as $k)DomainRenewalInvoiceDocument::number($d[$k]);foreach(['approved_epoch','expires_epoch'] as $k)DomainRenewalInvoiceDocument::number($d[$k],1,PHP_INT_MAX);
        $r=new \CajunWebPro\Foundation\TwentyIDomainContracts\DomainContractBinding($d['registrar']);$p=DomainRenewal::params($d['params']);if($r->data!==$d['registrar']||$p!==$d['params']||$r->data['client_id']!==$d['client_id']||$r->data['domain']!==$p['name']||$d['expires_epoch']-$d['approved_epoch']!==900)throw new InvalidArgumentException('Renewal approval scope changed.');
        DomainRenewalInvoiceDocument::keys($d['reviewed'],['eligible_years','privacy_scope','provider_cost','balance_charge']);foreach($d['reviewed'] as $v)if($v!==true)throw new InvalidArgumentException('Explicit current provider and balance-charge review is required.');
        $last=0;foreach($d['receipt_ids'] as $id){DomainRenewalInvoiceDocument::number($id,1,PHP_INT_MAX);if($id<=$last)throw new InvalidArgumentException('Invalid receipt identity order.');$last=$id;}
        if(DomainRenewalInvoiceDocument::json($d)!==$bytes)throw new InvalidArgumentException('Noncanonical renewal approval.');return new self($bytes);
    }
    public function data():array{return json_decode($this->bytes,true,12,JSON_THROW_ON_ERROR);}
    public function jsonBytes():string{return $this->bytes;}
    public function fingerprint():string{return hash('sha256',$this->bytes);}
}
