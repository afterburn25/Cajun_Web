<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-cloud-assignment/CloudAssignmentBinding.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-operations/CloudWebsiteCreate.php';
use CajunWebPro\Foundation\TwentyICloudAssignment\CloudAssignmentBinding;
use CajunWebPro\Foundation\TwentyI\Operations\CloudWebsiteCreate;

function provider_cloud_site_requests_keys(array $document,array $keys):bool {
    $actual=array_keys($document);sort($actual);sort($keys);return $actual===$keys;
}
function provider_cloud_site_requests_id(string $id):void {
    if(!preg_match('/\A[a-f0-9]{32}\z/D',$id))throw new InvalidArgumentException('Reload the website request form.');
}
function provider_cloud_site_requests_note(string $note):string {
    $note=trim($note);
    if(strlen($note)>1000||preg_match('//u',$note)!==1||preg_match('/\p{Cc}/u',$note))throw new InvalidArgumentException('Use a review message of at most 1,000 bytes without control characters.');
    return $note;
}
/** Canonical event maps sort keys; restore the foundation's declared order after exact-key validation. */
function provider_cloud_site_requests_binding(array $data):array {
    $keys=['profile_id','profile_revision','credential_sha256','key_fingerprint','server_id','client_id','link_id','link_revision','link_sha256','event_id','event_proof','source_id','source_seq','start_proof','finish_proof','server_sha256'];
    if(!provider_cloud_site_requests_keys($data,$keys))throw new RuntimeException('Cloud website assignment fields are inconsistent.');
    $ordered=[];foreach($keys as $key)$ordered[$key]=$data[$key];
    return (new CloudAssignmentBinding($ordered))->data;
}
/** Compare assignment authority, separately from the observation that may have advanced. */
function provider_cloud_site_requests_same_assignment(array $a,array $b):bool {
    return array_slice(provider_cloud_site_requests_binding($a),0,11,true)===array_slice(provider_cloud_site_requests_binding($b),0,11,true);
}
function provider_cloud_site_requests_meta(array $meta):void {
    if(!provider_cloud_site_requests_keys($meta,['request_id','request_hash','dispatch_id','dispatch_hash']))throw new RuntimeException('Cloud website request metadata is inconsistent.');
    foreach(['request_id','dispatch_id'] as $key){if(!is_string($meta[$key]))throw new RuntimeException('Invalid website request metadata identity.');provider_cloud_site_requests_id($meta[$key]);}
    foreach(['request_hash','dispatch_hash'] as $key)if(!is_string($meta[$key])||!preg_match('/\A[a-f0-9]{64}\z/D',$meta[$key]))throw new RuntimeException('Invalid website request metadata hash.');
}
/** Historical structure only: current client/owner authority and shared grants are checked by the service. */
function provider_cloud_site_requests_event_document(array $d):void {
    if(!provider_cloud_site_requests_keys($d,['version','id','subject_id','client_id','kind','actor','recorded_epoch','ordinal','previous','payload'])||$d['version']!==1)throw new RuntimeException('Cloud website request history is inconsistent.');
    foreach(['id','subject_id'] as $key){if(!is_string($d[$key]))throw new RuntimeException('Invalid website event identity.');provider_cloud_site_requests_id($d[$key]);}
    foreach(['client_id','recorded_epoch','ordinal'] as $key)if(!is_int($d[$key])||$d[$key]<1)throw new RuntimeException('Invalid website event sequence or client.');
    if($d['client_id']>4294967295||!is_string($d['previous'])||!preg_match('/\A[a-f0-9]{64}\z/D',$d['previous'])||!in_array($d['kind'],['request','cancel','decline','dispatch'],true)||!is_array($d['actor'])||!is_array($d['payload']))throw new RuntimeException('Invalid website event kind or context.');
    $actor=$d['actor'];$clientActor=in_array($d['kind'],['request','cancel'],true);
    if(!provider_cloud_site_requests_keys($actor,['kind','id','auth_sha256'])||$actor['kind']!==($clientActor?'client':'owner')||!is_int($actor['id'])||$actor['id']<1||$actor['id']>4294967295||!is_string($actor['auth_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/D',$actor['auth_sha256'])||($clientActor&&$actor['id']!==$d['client_id']))throw new RuntimeException('Invalid website request actor.');
    $p=$d['payload'];
    if($d['kind']==='request'){
        if(!provider_cloud_site_requests_keys($p,['binding','domain','aliases','confirmed'])||!is_array($p['binding'])||$p['confirmed']!==true)throw new RuntimeException('Invalid website request review.');
        $binding=provider_cloud_site_requests_binding($p['binding']);
        CloudWebsiteCreate::params(['cloudServerId'=>$binding['server_id'],'domain_name'=>$p['domain'],'extra_domain_names'=>$p['aliases']]);
        if($d['subject_id']!==$d['id']||$d['client_id']!==$binding['client_id'])throw new RuntimeException('Website request client or identity changed.');
        return;
    }
    if($d['kind']==='cancel'||$d['kind']==='decline'){
        if(!provider_cloud_site_requests_keys($p,['previous_event','note'])||!is_string($p['previous_event'])||!is_string($p['note'])||provider_cloud_site_requests_note($p['note'])!==$p['note'])throw new RuntimeException('Invalid website request decision.');
        provider_cloud_site_requests_id($p['previous_event']);
        if(($d['kind']==='cancel'&&$p['note']!=='')||($d['kind']==='decline'&&$p['note']===''))throw new InvalidArgumentException('Add a review message when declining the request.');
        return;
    }
    if(!provider_cloud_site_requests_keys($p,['previous_event','grant_id','binding','cloud_binding','confirmed'])||!is_string($p['previous_event'])||!is_string($p['grant_id'])||!is_array($p['binding'])||!is_array($p['cloud_binding'])||$p['confirmed']!==true)throw new RuntimeException('Invalid website dispatch review.');
    provider_cloud_site_requests_id($p['previous_event']);provider_cloud_site_requests_id($p['grant_id']);$binding=provider_cloud_site_requests_binding($p['binding']);
    $cloud=$p['cloud_binding'];
    if(!provider_cloud_site_requests_keys($cloud,['link_id','link_revision','client_id','server_id','profile_revision','key_fingerprint','refresh_id','credentials_hash','actor_auth']))throw new RuntimeException('Invalid website dispatch binding.');
    foreach(['link_id','link_revision','client_id','server_id','profile_revision','key_fingerprint'] as $key)if($cloud[$key]!==$binding[$key])throw new RuntimeException('Website dispatch assignment changed.');
    if($d['client_id']!==$binding['client_id']||$cloud['refresh_id']!==$binding['source_id']||$cloud['credentials_hash']!==$binding['credential_sha256']||$cloud['actor_auth']!==$actor['auth_sha256'])throw new RuntimeException('Website dispatch observation or actor changed.');
}
/** Local request stages only. Dispatched never implies provider success, health, or membership. */
function provider_cloud_site_requests_project(array $events):array {
    if(!array_is_list($events))throw new RuntimeException('Website events must be an ordered list.');
    $requests=[];$seen=[];$grants=[];
    foreach($events as $event){
        if(!is_array($event))throw new RuntimeException('Invalid website event.');provider_cloud_site_requests_event_document($event);
        if(isset($seen[$event['id']]))throw new RuntimeException('Duplicate website event identity.');$seen[$event['id']]=true;$subject=$event['subject_id'];$payload=$event['payload'];
        if($event['kind']==='request'){
            $requests[$subject]=['request'=>$event,'stage'=>'pending','latest_event'=>$event['id'],'latest'=>$event,'dispatch'=>null];continue;
        }
        $prior=$requests[$subject]??null;
        if(!$prior||$prior['stage']!=='pending'||$payload['previous_event']!==$prior['latest_event']||$event['client_id']!==$prior['request']['client_id'])throw new RuntimeException('Website request decision history is incomplete.');
        if($event['kind']==='cancel'&&$event['actor']['auth_sha256']!==$prior['request']['actor']['auth_sha256'])throw new RuntimeException('Original website request client authorization changed.');
        if($event['kind']==='dispatch'){
            if(!provider_cloud_site_requests_same_assignment($prior['request']['payload']['binding'],$payload['binding']))throw new RuntimeException('Original website assignment changed before dispatch.');
            if(isset($grants[$payload['grant_id']]))throw new RuntimeException('A cloud website grant already belongs to another request.');$grants[$payload['grant_id']]=true;
        }
        $requests[$subject]['stage']=match($event['kind']){'cancel'=>'cancelled','decline'=>'declined','dispatch'=>'dispatched'};
        $requests[$subject]['latest_event']=$event['id'];$requests[$subject]['latest']=$event;
        $requests[$subject]['dispatch']=$event['kind']==='dispatch'?$event:null;
    }
    return ['requests'=>$requests];
}
