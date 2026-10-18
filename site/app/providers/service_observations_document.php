<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function service_observations_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload service history.');}
function service_observations_json(array $d):string {return json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function service_observations_document(array $d):array {
    if(array_keys($d)!==['version','id','link_id','revision','enabled','actor_id','recorded_epoch','previous_sha256','client_id','profile_id','profile_revision','package_id','key_fingerprint','link_sha256','link_event_id','link_event_sha256','anchor_id','anchor_seq','anchor_sha256']||$d['version']!==1||!is_bool($d['enabled']))throw new RuntimeException('Service history permission is inconsistent.');
    foreach(['id','link_id','profile_id','anchor_id'] as $k){if(!is_string($d[$k]))throw new RuntimeException('Invalid service history identity.');service_observations_id($d[$k]);}
    foreach(['revision','actor_id','client_id','profile_revision','package_id','link_event_id','anchor_seq','recorded_epoch'] as $k)if(!is_int($d[$k])||$d[$k]<1||$d[$k]>999999999999999999)throw new RuntimeException('Invalid service history context.');
    foreach(['revision','actor_id','client_id','profile_revision'] as $k)if($d[$k]>4294967295)throw new RuntimeException('Invalid service history context.');
    foreach(['previous_sha256','key_fingerprint','link_sha256','link_event_sha256','anchor_sha256'] as $k)if(!is_string($d[$k])||!preg_match('/\A[a-f0-9]{64}\z/',$d[$k]))throw new RuntimeException('Invalid service history fingerprint.');return $d;
}
