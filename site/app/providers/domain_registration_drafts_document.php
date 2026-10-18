<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-domain-registration/RegistrationDraft.php';
use CajunWebPro\Foundation\TwentyIDomainRegistration\RegistrationDraft;

function registration_drafts_id(string $value):string {
    if(!preg_match('/\A[a-f0-9]{32}\z/',$value))throw new InvalidArgumentException('Reload the registration draft form.');return $value;
}
function registration_drafts_hash(string $value):string {
    if(!preg_match('/\A[a-f0-9]{64}\z/',$value))throw new InvalidArgumentException('Reload the registration draft form.');return $value;
}
function registration_drafts_json(array $value):string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function registration_drafts_mac(string $purpose,#[SensitiveParameter] string $bytes):string {
    return hash_hmac('sha256','registration-drafts-v1|'.config()['db']['prefix'].'|'.$purpose.'|'.$bytes,config()['key']);
}
/** A local draft cannot record registry consent or unsupported extension requirements. */
function registration_drafts_content(RegistrationDraft $draft):array {
    $data=$draft->privateDocument();
    if($data['registry_acceptance']!==null||$data['contact']['extension']!==[]||$data['client_id']>4294967295)throw new InvalidArgumentException('This draft saves contact details only. Registry requirements need separate review.');
    return $data;
}
function registration_drafts_state(?string $before,string $action,string $kind):string {
    if(!in_array($kind,['client','staff'],true))throw new InvalidArgumentException('Unknown draft author.');
    if($before!==null&&!in_array($before,['draft','submitted','withdrawn'],true))throw new InvalidArgumentException('Unknown draft state.');
    if($action==='save')return $before==='withdrawn'?'withdrawn':'draft';
    if($kind!=='client')throw new InvalidArgumentException('Only the client can submit, withdraw or reopen a draft.');
    if($action==='submit'&&$before==='draft')return 'submitted';
    if($action==='withdraw'&&in_array($before,['draft','submitted'],true))return 'withdrawn';
    if($action==='reopen'&&$before==='withdrawn')return 'draft';
    throw new InvalidArgumentException('This draft changed. Reload it before continuing.');
}
function registration_drafts_envelope(array $data):array {
    $keys=['version','id','draft_id','client_id','revision','state','action','actor_kind','actor_id','actor_version','client_version','recorded_epoch','expected_revision','previous_sha256','domain','requested_years','privacy_service','content_hmac','cipher_sha256'];
    if(array_keys($data)!==$keys||$data['version']!==1)throw new RuntimeException('Registration draft history needs recovery.');
    foreach(['id','draft_id','actor_version','client_version'] as $name)registration_drafts_id($data[$name]);
    foreach(['previous_sha256','content_hmac','cipher_sha256'] as $name)registration_drafts_hash($data[$name]);
    foreach(['client_id','actor_id'] as $name)if(!is_int($data[$name])||$data[$name]<1||$data[$name]>4294967295)throw new RuntimeException('Registration draft history needs recovery.');
    if(!is_int($data['revision'])||$data['revision']<1||$data['revision']>256||!is_int($data['expected_revision'])||$data['expected_revision']!==$data['revision']-1||!is_int($data['recorded_epoch'])||$data['recorded_epoch']<1||$data['recorded_epoch']>9999999999||!in_array($data['state'],['draft','submitted','withdrawn'],true)||!in_array($data['action'],['save','submit','withdraw','reopen'],true)||!in_array($data['actor_kind'],['client','staff'],true)||($data['actor_kind']==='client'&&($data['actor_id']!==$data['client_id']||$data['actor_version']!==$data['client_version']))||($data['actor_kind']==='staff'&&$data['action']!=='save')||!is_int($data['requested_years'])||$data['requested_years']<1||$data['requested_years']>10||!is_bool($data['privacy_service']))throw new RuntimeException('Registration draft history needs recovery.');
    RegistrationDraft::name($data['domain']);return $data;
}
/** Authenticate the complete document before consulting any indexed subject field. */
function registration_drafts_decode(array $row):array {
    try{
        if(!is_string($row['document_json'])||strlen($row['document_json'])>4096)throw new RuntimeException();
        $d=registration_drafts_envelope(json_decode($row['document_json'],true,8,JSON_THROW_ON_ERROR));
        if(registration_drafts_json($d)!==$row['document_json']||!hash_equals(registration_drafts_mac('revision',$row['document_json']),$row['proof'])||!hash_equals(hash('sha256',$row['document_json']),$row['document_sha256'])||!is_string($row['ciphertext'])||strlen($row['ciphertext'])>44000||!hash_equals(hash('sha256',$row['ciphertext']),$d['cipher_sha256']))throw new RuntimeException();
        foreach(['id','draft_id','client_id','revision','state','action','actor_kind','actor_id','recorded_epoch'] as $field)if((string)$row[$field]!== (string)$d[$field])throw new RuntimeException();
        return $d;
    }catch(Throwable $e){throw new RuntimeException('Registration draft authenticated history needs recovery.',0,$e);}
}
function registration_drafts_checkpoint(array $d):array {
    if(array_keys($d)!==['version','drafts','revisions','last_id','last_sha256','last_audit_id','key_sha256','prepared_by','prepared_audit_id']||$d['version']!==1)throw new RuntimeException('Registration draft count history needs recovery.');
    foreach(['drafts','revisions','last_audit_id'] as $field)if(!is_int($d[$field])||$d[$field]<0||$d[$field]>999999999999999)throw new RuntimeException('Registration draft count history needs recovery.');
    registration_drafts_hash($d['key_sha256']);registration_drafts_hash($d['last_sha256']);registration_drafts_id($d['last_id']);
    foreach(['prepared_by','prepared_audit_id'] as $field)if(!is_int($d[$field])||$d[$field]<1||$d[$field]>999999999999999)throw new RuntimeException('Registration draft count history needs recovery.');
    if(($d['revisions']===0)!==($d['drafts']===0)||$d['drafts']>$d['revisions']||($d['revisions']===0&&($d['last_id']!==str_repeat('0',32)||$d['last_sha256']!==str_repeat('0',64)||$d['last_audit_id']!==0))||($d['revisions']>0&&$d['last_audit_id']===0))throw new RuntimeException('Registration draft count history needs recovery.');
    return $d;
}
