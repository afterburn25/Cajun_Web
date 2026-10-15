<?php
/** Owner-entered catalog evidence. It is not a provider response, quote or entitlement. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/plans/documents.php';
require_once CWP_CODE_ROOT.'/includes/foundation/hosting/HostingPackage.php';

function provider_plan_catalog_text(mixed $value,int $max):string {
    if(!is_string($value)||$value===''||strlen($value)>$max||trim($value)!==$value||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))throw new InvalidArgumentException('Enter bounded, plain catalog text.');return $value;
}
/** Amount is an exact decimal in major currency units, never a float or retail cents. */
function provider_plan_catalog_cost(mixed $cost):?array {
    if($cost===null)return null;
    if(!is_array($cost))throw new InvalidArgumentException('Invalid provider cost.');
    service_plans_keys($cost,['amount','currency','period_months','tax','basis','reference']);
    if(!is_string($cost['amount'])||!preg_match('/\A(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,4})?\z/',$cost['amount'])||!is_string($cost['currency'])||!preg_match('/\A[A-Z]{3}\z/',$cost['currency'])||!is_int($cost['period_months'])||$cost['period_months']<1||$cost['period_months']>120||!in_array($cost['tax'],['included','excluded','unknown'],true)||!in_array($cost['basis'],['per-package','shared-account'],true))throw new InvalidArgumentException('Record an exact provider cost, currency, period and tax treatment.');
    provider_plan_catalog_text($cost['reference'],120);return $cost;
}
function provider_plan_catalog_config(array $config):array {
    service_plans_keys($config,['reseller_id','type','label','region','limits','cost']);
    service_plans_actor($config['reseller_id']);
    if(!is_string($config['type'])||!preg_match('/\A[1-9][0-9]{0,17}\z/',$config['type']))throw new InvalidArgumentException('Enter the exact numeric provider package type.');
    provider_plan_catalog_text($config['label'],120);provider_plan_catalog_text($config['region'],80);
    if(!is_array($config['limits'])||!$config['limits'])throw new InvalidArgumentException('Record at least one known provider limit.');
    foreach($config['limits'] as $key=>$value)if(!in_array($key,['websites','disk_mb','bandwidth_mb','mailboxes','databases'],true)||!is_int($value)||$value<0||$value>2147483647)throw new InvalidArgumentException('Use supported numeric provider limits; zero means zero.');
    ksort($config['limits']);provider_plan_catalog_cost($config['cost']);return $config;
}
function provider_plan_catalog_package(array $config,string $order):\CajunWebPro\Foundation\Hosting\HostingPackage {
    $config=provider_plan_catalog_config($config);service_plans_id($order);
    return new \CajunWebPro\Foundation\Hosting\HostingPackage('order-'.$order,$config['label'],$config['type'],$config['region'],$config['limits']);
}
function provider_plan_catalog_document(string $bytes):array {
    try{
    if(strlen($bytes)>16384)throw new RuntimeException('Provider catalog document exceeds its bound.');
    $d=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);
    if(!is_array($d))throw new RuntimeException('Invalid provider catalog document.');
    service_plans_keys($d,['format','id','plan_id','revision','previous_id','previous_sha256','plan_revision','plan_sha256','profile_id','profile_revision','credential_sha256','created_by','owner_auth','created_at','expires_at','enabled','configuration','evidence']);
    foreach(['id','plan_id','profile_id'] as $key)service_plans_id($d[$key]);
    foreach(['plan_sha256','credential_sha256'] as $key)if(!is_string($d[$key])||!preg_match('/\A[a-f0-9]{64}\z/',$d[$key]))throw new RuntimeException('Invalid catalog fingerprint.');
    provider_plan_catalog_text($d['owner_auth'],128);service_plans_actor($d['created_by']);service_plans_time($d['created_at']);service_plans_time($d['expires_at']);
    if($d['format']!==1||!is_int($d['revision'])||$d['revision']<1||$d['revision']>128||!is_int($d['plan_revision'])||$d['plan_revision']<1||$d['plan_revision']>256||!is_int($d['profile_revision'])||$d['profile_revision']<1||!is_bool($d['enabled'])||$d['expires_at']<=$d['created_at']||$d['expires_at']>$d['created_at']+30*86400||$d['evidence']!=='owner-account-review')throw new RuntimeException('Invalid catalog review context.');
    if($d['revision']===1){if($d['previous_id']!==null||$d['previous_sha256']!==null)throw new RuntimeException('Invalid catalog origin.');}
    else {service_plans_id($d['previous_id']);if(!is_string($d['previous_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['previous_sha256']))throw new RuntimeException('Invalid catalog predecessor.');}
    if(provider_plan_catalog_config($d['configuration'])!==$d['configuration']||service_plans_json($d)!==$bytes)throw new RuntimeException('Noncanonical provider catalog record.');return $d;
    }catch(Throwable $e){throw new RuntimeException('Original provider catalog document is inconsistent. Restore its original history.',0,$e);}
}
