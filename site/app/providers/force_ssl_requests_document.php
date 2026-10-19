<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-package-force-ssl/PackageForceSslBinding.php';
use CajunWebPro\Foundation\TwentyIPackageForceSsl\PackageForceSslBinding;

function provider_force_ssl_requests_keys(array $d,array $keys):bool {$actual=array_keys($d);sort($actual);sort($keys);return $actual===$keys;}
function provider_force_ssl_requests_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the HTTPS request form.');}
function provider_force_ssl_requests_json(array $d):string {$nodes=0;return provider_operations_canonical($d,0,$nodes);}
function provider_force_ssl_requests_note(string $note):string {
    $note=trim($note);if(strlen($note)>1000||preg_match('//u',$note)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$note))throw new InvalidArgumentException('Use a review message of at most 1,000 bytes.');return $note;
}
/** Canonical event JSON sorts maps; reconstruct the foundation constructor's exact field order. */
function provider_force_ssl_requests_binding(array $data):array {
    $keys=['profile_id','profile_revision','key_fingerprint','credential_sha256','package_id','client_id','link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','source_id','source_seq','source_sha256'];
    if(!provider_force_ssl_requests_keys($data,$keys))throw new RuntimeException('Force SSL request assignment fields need recovery.');
    $ordered=[];foreach($keys as $key)$ordered[$key]=$data[$key];return (new PackageForceSslBinding($ordered))->data;
}
/** Historical structure only. Live client, owner, assignment and credential authority belongs to the service. */
function provider_force_ssl_requests_resource_document(array $r):void {
    if(!provider_force_ssl_requests_keys($r,['binding','owner_id','owner_auth_sha256','sharing_id','sharing_revision','sharing_sha256','sharing_owner_id','sharing_owner_auth_sha256'])||!is_array($r['binding'])||!is_int($r['owner_id'])||$r['owner_id']<1||$r['owner_id']>4294967295)throw new RuntimeException('Force SSL request resource needs recovery.');
    if(!is_int($r['sharing_owner_id'])||$r['sharing_owner_id']<1||$r['sharing_owner_id']>4294967295)throw new RuntimeException('Invalid sharing owner identity.');
    if(!is_string($r['sharing_id'])||!is_int($r['sharing_revision'])||$r['sharing_revision']<1||$r['sharing_revision']>2147483647)throw new RuntimeException('Invalid Force SSL sharing identity.');provider_force_ssl_requests_id($r['sharing_id']);
    foreach(['owner_auth_sha256','sharing_sha256','sharing_owner_auth_sha256'] as $key)if(!is_string($r[$key])||!preg_match('/\A[a-f0-9]{64}\z/',$r[$key]))throw new RuntimeException('Force SSL request resource evidence needs recovery.');
    provider_force_ssl_requests_binding($r['binding']);
}
function provider_force_ssl_requests_event_document(array $d):void {
    if(!provider_force_ssl_requests_keys($d,['version','id','subject_id','client_id','kind','actor','recorded_epoch','ordinal','previous','payload'])||$d['version']!==1)throw new RuntimeException('Force SSL request event needs recovery.');
    foreach(['id','subject_id'] as $key){if(!is_string($d[$key]))throw new RuntimeException('Invalid HTTPS request identity.');provider_force_ssl_requests_id($d[$key]);}
    if(!is_int($d['client_id'])||$d['client_id']<1||$d['client_id']>4294967295)throw new RuntimeException('Invalid HTTPS request client.');
    foreach(['recorded_epoch','ordinal'] as $key)if(!is_int($d[$key])||$d[$key]<1||$d[$key]>2147483647)throw new RuntimeException('Force SSL request exceeds the supported local sequence or date.');
    if(!is_string($d['previous'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['previous'])||!in_array($d['kind'],['request','cancel','decline','dispatch'],true)||!is_array($d['actor'])||!provider_force_ssl_requests_keys($d['actor'],['kind','id','auth_sha256'])||!is_int($d['actor']['id'])||$d['actor']['id']<1||$d['actor']['id']>4294967295||$d['actor']['kind']!==(in_array($d['kind'],['request','cancel'],true)?'client':'owner')||!is_string($d['actor']['auth_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['actor']['auth_sha256'])||!is_array($d['payload']))throw new RuntimeException('Invalid HTTPS request actor or kind.');
    $p=$d['payload'];
    if($d['kind']==='request'){
        if(!provider_force_ssl_requests_keys($p,['resource','desired','confirmed'])||!is_array($p['resource'])||!is_bool($p['desired'])||$p['confirmed']!==true)throw new RuntimeException('Invalid HTTPS request confirmation.');
        provider_force_ssl_requests_resource_document($p['resource']);$binding=provider_force_ssl_requests_binding($p['resource']['binding']);
        if($d['subject_id']!==$d['id']||$d['client_id']!==$binding['client_id']||$d['actor']['id']!==$d['client_id'])throw new RuntimeException('Force SSL request client identity changed.');
    }elseif($d['kind']==='dispatch'){
        if(!provider_force_ssl_requests_keys($p,['previous_event','owner_request_id','owner_request_sha256'])||!is_string($p['previous_event'])||!is_string($p['owner_request_id'])||!is_string($p['owner_request_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$p['owner_request_sha256']))throw new RuntimeException('Invalid HTTPS owner approval link.');
        provider_force_ssl_requests_id($p['previous_event']);provider_force_ssl_requests_id($p['owner_request_id']);
        if($d['id']!==$p['owner_request_id'])throw new RuntimeException('Force SSL dispatch identity changed.');
    }else{
        if(!provider_force_ssl_requests_keys($p,['previous_event','note'])||!is_string($p['previous_event'])||!is_string($p['note'])||provider_force_ssl_requests_note($p['note'])!==$p['note'])throw new RuntimeException('Invalid HTTPS request decision.');
        provider_force_ssl_requests_id($p['previous_event']);
        if(($d['kind']==='cancel'&&($p['note']!==''||$d['actor']['id']!==$d['client_id']))||($d['kind']==='decline'&&$p['note']===''))throw new RuntimeException('Invalid HTTPS request decision actor or note.');
    }
    if(strlen(provider_force_ssl_requests_json($d))>32768)throw new RuntimeException('Force SSL request evidence exceeds its local size bound.');
}
/** Local workflow only: dispatched means linked to an owner approval, never provider success. */
function provider_force_ssl_requests_project(array $events):array {
    if(!array_is_list($events))throw new RuntimeException('Force SSL request history must be an ordered list.');
    $requests=[];$seen=[];$pending=[];$ordinal=0;$epoch=0;
    foreach($events as $e){
        if(!is_array($e))throw new RuntimeException('Invalid HTTPS request event.');provider_force_ssl_requests_event_document($e);
        if(isset($seen[$e['id']])||$e['ordinal']!==++$ordinal||$e['recorded_epoch']<$epoch)throw new RuntimeException('Force SSL request event order needs recovery.');
        $seen[$e['id']]=true;$epoch=$e['recorded_epoch'];$p=$e['payload'];$subject=$e['subject_id'];
        if($e['kind']==='request'){
            $b=$p['resource']['binding'];$resource=$b['profile_id'].'|'.$b['package_id'];
            if(isset($pending[$resource]))throw new RuntimeException('A HTTPS request for this hosting service is already pending.');
            $pending[$resource]=$subject;$requests[$subject]=['request'=>$e,'state'=>'pending','latest_event'=>$e['id'],'events'=>[]];continue;
        }
        $r=$requests[$subject]??null;
        if(!$r||$r['state']!=='pending'||$p['previous_event']!==$r['latest_event']||$e['client_id']!==$r['request']['client_id'])throw new RuntimeException('Force SSL request decision history needs recovery.');
        $b=$r['request']['payload']['resource']['binding'];unset($pending[$b['profile_id'].'|'.$b['package_id']]);
        $requests[$subject]['state']=match($e['kind']){'cancel'=>'cancelled','decline'=>'declined','dispatch'=>'dispatched'};
        $requests[$subject]['latest_event']=$e['id'];$requests[$subject]['events'][]=$e;
    }
    return ['requests'=>$requests];
}
