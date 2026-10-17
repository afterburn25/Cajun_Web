<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-operations/ForwardingRequest.php';
use CajunWebPro\Foundation\TwentyI\Operations\ForwardingRequest;
function provider_forwarding_keys(array $d,array $keys):bool {$actual=array_keys($d);sort($actual);sort($keys);return $actual===$keys;}
function provider_forwarding_note(string $note):string {$note=trim($note);if(strlen($note)>1000||preg_match('//u',$note)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$note))throw new InvalidArgumentException('Use a review message of at most 1,000 bytes.');return $note;}
function provider_forwarding_resource_document(array $r):void {
    if(!provider_forwarding_keys($r,['binding','credential_sha256','refresh_id','link_event_id'])||!is_array($r['binding'])||!is_string($r['credential_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$r['credential_sha256'])||!is_string($r['refresh_id']))throw new RuntimeException('Forwarding resource history is inconsistent.');provider_forwarding_id($r['refresh_id']);if(!is_int($r['link_event_id'])||$r['link_event_id']<1)throw new RuntimeException('Invalid forwarding assignment event.');$b=$r['binding'];
    if(!provider_forwarding_keys($b,['link_id','profile_id','profile_revision','package_id','client_id','domain','key_fingerprint','linked_by','linked_at']))throw new RuntimeException('Forwarding assignment history is inconsistent.');
    foreach(['link_id','profile_id'] as $k){if(!is_string($b[$k]))throw new RuntimeException('Invalid forwarding resource identity.');provider_forwarding_id($b[$k]);}
    foreach(['profile_revision','package_id','client_id','linked_by'] as $k)if(!is_int($b[$k])||$b[$k]<1||$b[$k]>4294967295)throw new RuntimeException('Invalid forwarding assignment context.');
    if(!is_string($b['key_fingerprint'])||!preg_match('/\A[a-f0-9]{64}\z/',$b['key_fingerprint'])||!is_string($b['linked_at'])||!preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/',$b['linked_at']))throw new RuntimeException('Invalid forwarding assignment evidence.');\CajunWebPro\Foundation\TwentyI\Operations\MailboxCreate::domain($b['domain']);
}
function provider_forwarding_same_resource(array $a,array $b):bool {return provider_forwarding_json($a['binding'])===provider_forwarding_json($b['binding'])&&hash_equals($a['credential_sha256'],$b['credential_sha256'])&&$a['link_event_id']===$b['link_event_id'];}
function provider_forwarding_event_document(array $d):void {
    if(!provider_forwarding_keys($d,['version','id','subject_id','client_id','kind','actor','recorded_epoch','ordinal','previous','payload'])||$d['version']!==1)throw new RuntimeException('Forwarding event history is inconsistent.');
    foreach(['id','subject_id'] as $k){if(!is_string($d[$k]))throw new RuntimeException('Invalid forwarding event identity.');provider_forwarding_id($d[$k]);}
    foreach(['client_id','recorded_epoch','ordinal'] as $k)if(!is_int($d[$k])||$d[$k]<1)throw new RuntimeException('Invalid forwarding event context.');
    if(!is_string($d['previous'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['previous'])||!in_array($d['kind'],['permission','request','review','decline','cancel'],true)||!is_array($d['actor'])||!provider_forwarding_keys($d['actor'],['kind','id','auth_version'])||!is_int($d['actor']['id'])||$d['actor']['id']<1||$d['actor']['kind']!==(in_array($d['kind'],['request','cancel'],true)?'client':'owner')||!is_string($d['actor']['auth_version'])||!preg_match('/\A[a-f0-9]{32}\z/',$d['actor']['auth_version'])||!is_array($d['payload']))throw new RuntimeException('Invalid forwarding event actor or kind.');
    $p=$d['payload'];
    if($d['kind']==='permission'){
        if(!provider_forwarding_keys($p,['previous_id','enabled','daily_limit','expires_epoch','resource'])||!is_string($p['previous_id'])||!is_bool($p['enabled'])||!is_int($p['daily_limit'])||$p['daily_limit']<1||$p['daily_limit']>20||!is_int($p['expires_epoch'])||$p['expires_epoch']<1||!is_array($p['resource']))throw new RuntimeException('Invalid forwarding permission.');if($p['previous_id']!=='')provider_forwarding_id($p['previous_id']);provider_forwarding_resource_document($p['resource']);
        if($d['subject_id']!==$p['resource']['binding']['link_id']||$d['client_id']!==$p['resource']['binding']['client_id'])throw new RuntimeException('Forwarding permission identity changed.');
    }elseif($d['kind']==='request'){
        if(!provider_forwarding_keys($p,['permission_id','local','remote','resource'])||!is_string($p['permission_id'])||!is_array($p['resource']))throw new RuntimeException('Invalid forwarding request.');provider_forwarding_id($p['permission_id']);provider_forwarding_resource_document($p['resource']);$b=$p['resource']['binding'];ForwardingRequest::parameters(['packageId'=>$b['package_id'],'domain'=>$b['domain'],'local'=>$p['local'],'remote'=>$p['remote']]);
        if($d['subject_id']!==$d['id']||$d['client_id']!==$b['client_id']||$d['actor']['id']!==$b['client_id'])throw new RuntimeException('Forwarding request client changed.');
    }else{
        if(!provider_forwarding_keys($p,['previous_event','note','resource'])||!is_string($p['previous_event'])||!is_string($p['note'])||provider_forwarding_note($p['note'])!==$p['note'])throw new RuntimeException('Invalid forwarding review.');provider_forwarding_id($p['previous_event']);
        if(($d['kind']==='cancel'&&($p['note']!==''||$d['actor']['id']!==$d['client_id']))||($d['kind']!=='cancel'&&$p['note']===''))throw new InvalidArgumentException('Add a message for the client.');
        if($d['kind']==='review'){if(!is_array($p['resource']))throw new RuntimeException('Forwarding review needs resource evidence.');provider_forwarding_resource_document($p['resource']);}elseif($p['resource']!==null)throw new RuntimeException('Invalid terminal forwarding event.');
    }
}
/** Rebuild local state from immutable events; no state means provider fulfillment. */
function provider_forwarding_project(array $events):array {
    $permissions=[];$requests=[];$seen=[];
    foreach($events as $e){provider_forwarding_event_document($e);if(isset($seen[$e['id']]))throw new RuntimeException('Duplicate forwarding event identity.');$seen[$e['id']]=true;$p=$e['payload'];$subject=$e['subject_id'];
        if($e['kind']==='permission'){if(($permissions[$subject]['id']??'')!==$p['previous_id'])throw new RuntimeException('Forwarding permission history is incomplete.');$permissions[$subject]=$e;continue;}
        if($e['kind']==='request'){
            $permission=$permissions[$p['resource']['binding']['link_id']]??null;
            if(!$permission||$permission['id']!==$p['permission_id']||!$permission['payload']['enabled']||$permission['payload']['expires_epoch']<=$e['recorded_epoch']||!provider_forwarding_same_resource($p['resource'],$permission['payload']['resource']))throw new RuntimeException('Original forwarding permission is inconsistent.');
            foreach($requests as $r)if($r['state']==='pending_review'&&$r['request']['payload']['resource']['binding']['link_id']===$p['resource']['binding']['link_id']&&$r['request']['payload']['local']===$p['local'])throw new RuntimeException('A request for this source address is already awaiting review.');
            $requests[$subject]=['request'=>$e,'latest_event'=>$e['id'],'state'=>'pending_review','events'=>[]];continue;
        }
        $r=$requests[$subject]??null;if(!$r||$r['state']!=='pending_review'||$p['previous_event']!==$r['latest_event']||$r['request']['client_id']!==$e['client_id'])throw new RuntimeException('Forwarding review history is incomplete.');
        if($e['kind']==='review'){
            $original=$r['request']['payload'];$permission=$permissions[$original['resource']['binding']['link_id']]??null;
            if(!$permission||$permission['id']!==$original['permission_id']||!$permission['payload']['enabled']||$permission['payload']['expires_epoch']<=$e['recorded_epoch']||!provider_forwarding_same_resource($p['resource'],$original['resource']))throw new RuntimeException('Forwarding review belongs to changed permission or assignment.');
        }
        $requests[$subject]['events'][]=$e;$requests[$subject]['latest_event']=$e['id'];if($e['kind']!=='review')$requests[$subject]['state']=$e['kind']==='cancel'?'cancelled':'declined';
    }return ['permissions'=>$permissions,'requests'=>$requests];
}
