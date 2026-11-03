<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-package-php/PackagePhpVersionBinding.php';
require_once CWP_CODE_ROOT.'/includes/foundation/access/AuthorizationVersion.php';

use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding;
use CajunWebPro\Foundation\Access\AuthorizationVersion;

/** Structural historical document only; current authority and retained source live in the native adapter. */
function provider_php_controls_delegation_document(array $d):array {
    $keys=['format','id','link_id','client_id','revision','owner_id','owner_auth_version','client_auth_version','recorded_epoch','previous_id','previous_sha256','enabled','review_sha256','binding'];
    if(array_keys($d)!==$keys||$d['format']!==1||!is_bool($d['enabled']))throw new InvalidArgumentException('Invalid PHP change delegation.');
    foreach(['id','link_id'] as $key)if(!is_string($d[$key])||preg_match('/\A[a-f0-9]{32}\z/',$d[$key])!==1)throw new InvalidArgumentException('Invalid PHP delegation identity.');
    foreach(['previous_sha256','review_sha256'] as $key)if(!is_string($d[$key])||preg_match('/\A[a-f0-9]{64}\z/',$d[$key])!==1)throw new InvalidArgumentException('Invalid PHP delegation witness.');
    foreach(['client_id','owner_id','revision'] as $key)if(!is_int($d[$key])||$d[$key]<1||$d[$key]>2147483647)throw new InvalidArgumentException('Invalid PHP delegation sequence.');
    foreach(['owner_auth_version','client_auth_version'] as $key)if(!is_string($d[$key])||!AuthorizationVersion::valid($d[$key]))throw new InvalidArgumentException('Invalid PHP delegation actor.');
    if(!is_int($d['recorded_epoch'])||$d['recorded_epoch']<0||!is_array($d['binding']))throw new InvalidArgumentException('Invalid PHP delegation time or assignment.');
    $binding=new PackagePhpVersionBinding($d['binding']);
    if($binding->data!==$d['binding']||$binding->data['link_id']!==$d['link_id']||$binding->data['client_id']!==$d['client_id'])throw new InvalidArgumentException('Invalid PHP delegation assignment.');
    if(!is_string($d['previous_id'])||($d['revision']===1?$d['previous_id']!=='':preg_match('/\A[a-f0-9]{32}\z/',$d['previous_id'])!==1))throw new InvalidArgumentException('Invalid previous PHP delegation.');
    if($d['revision']===1&&($d['previous_sha256']!==str_repeat('0',64)||!$d['enabled']))throw new InvalidArgumentException('A PHP delegation starts with explicit enablement.');
    $review=['format'=>1,'link_id'=>$d['link_id'],'enabled'=>$d['enabled'],'previous_id'=>$d['previous_id'],'previous_sha256'=>$d['previous_sha256'],'owner_id'=>$d['owner_id'],'owner_auth_version'=>$d['owner_auth_version'],'client_auth_version'=>$d['client_auth_version'],'binding'=>$d['binding']];
    if(!hash_equals(hash('sha256',provider_php_controls_json($review)),$d['review_sha256']))throw new InvalidArgumentException('PHP delegation review does not match its retained context.');
    return $d;
}
function provider_php_controls_json(array $d):string {return json_encode($d,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function provider_php_controls_delegation_parse(string $json):array {
    if(strlen($json)>16384)throw new InvalidArgumentException('PHP delegation document is too large.');
    $d=json_decode($json,true,12,JSON_THROW_ON_ERROR);
    if(!is_array($d)||provider_php_controls_json(provider_php_controls_delegation_document($d))!==$json)throw new InvalidArgumentException('PHP delegation document is not canonical.');
    return $d;
}
