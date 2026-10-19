<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once __DIR__.'/stackcp_launch_document.php';
use CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding;

function provider_support_stackcp_json(array $value):string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function provider_support_stackcp_keys(array $value,array $keys):void {provider_stackcp_launch_keys($value,$keys);}
function provider_support_stackcp_principal(array $actor):array {
    $kind=$actor['kind']??null;
    $keys=$kind==='owner'?['kind','id','auth_version']:['kind','id','auth_version','client_id','client_auth_version','support_id','binding_sha256','expires_epoch'];
    provider_support_stackcp_keys($actor,$keys);
    if (!in_array($kind,['owner','support'],true)) throw new InvalidArgumentException('Invalid support StackCP actor.');
    provider_stackcp_launch_number($actor['id']);provider_stackcp_launch_hex($actor['auth_version']);
    if ($kind==='support') {
        foreach (['client_id','expires_epoch'] as $key) provider_stackcp_launch_number($actor[$key]);
        foreach (['client_auth_version','support_id'] as $key) provider_stackcp_launch_hex($actor[$key]);
        provider_stackcp_launch_hex($actor['binding_sha256'],64);
    }
    $out=[];foreach($keys as $key)$out[$key]=$actor[$key];return $out;
}
function provider_support_stackcp_resource_document(array $r):array {
    provider_support_stackcp_keys($r,['binding','grants_id','grants_seq','grants_sha256','membership_observed_epoch','grants_observed_epoch','staff']);
    if (!is_array($r['binding'])) throw new InvalidArgumentException('Invalid support StackCP resource.');
    $b=new StackCpAccessBinding($r['binding']);
    if ($b->data['operation']!==StackCpAccessBinding::GRANTS) throw new InvalidArgumentException('Invalid support StackCP source.');
    provider_stackcp_launch_hex($r['grants_id']);provider_stackcp_launch_hex($r['grants_sha256'],64);
    foreach (['grants_seq','membership_observed_epoch','grants_observed_epoch'] as $key) provider_stackcp_launch_number($r[$key]);
    if (!is_array($r['staff'])) throw new InvalidArgumentException('Invalid delegated staff identity.');
    provider_support_stackcp_keys($r['staff'],['id','auth_version']);provider_stackcp_launch_number($r['staff']['id']);provider_stackcp_launch_hex($r['staff']['auth_version']);
    return ['binding'=>$b->data,'grants_id'=>$r['grants_id'],'grants_seq'=>$r['grants_seq'],'grants_sha256'=>$r['grants_sha256'],'membership_observed_epoch'=>$r['membership_observed_epoch'],'grants_observed_epoch'=>$r['grants_observed_epoch'],'staff'=>['id'=>$r['staff']['id'],'auth_version'=>$r['staff']['auth_version']]];
}
/** A five-minute one-use support form, separate from the enduring owner permission. */
function provider_support_stackcp_approval(array $a):array {
    $keys=['version','admission_id','permission_id','permission_sha256','actor','link_id','expires_epoch','previous_admission_id','previous_event_sha256','confirmed','provider_session_scope','remote_scope_restricted'];
    provider_support_stackcp_keys($a,$keys);
    if ($a['version']!==1||$a['confirmed']!==true||$a['provider_session_scope']!=='whole-stack-user'||$a['remote_scope_restricted']!==false) throw new InvalidArgumentException('Invalid support StackCP approval.');
    foreach (['admission_id','permission_id','link_id'] as $key) provider_stackcp_launch_hex($a[$key]);
    provider_stackcp_launch_hex($a['permission_sha256'],64);
    if (!is_array($a['actor'])) throw new InvalidArgumentException('Invalid support approval actor.');
    $actor=provider_support_stackcp_principal($a['actor']);provider_stackcp_launch_number($a['expires_epoch']);
    if ($actor['kind']!=='support'||$a['expires_epoch']>$actor['expires_epoch']) throw new InvalidArgumentException('Support form outlives its session.');
    if ($a['previous_admission_id']!==''||$a['previous_event_sha256']!=='') {provider_stackcp_launch_hex($a['previous_admission_id']);provider_stackcp_launch_hex($a['previous_event_sha256'],64);}
    $out=[];foreach ($keys as $key) $out[$key]=$key==='actor'?$actor:$a[$key];return $out;
}
function provider_support_stackcp_reason(string $reason):string {
    if (in_array($reason,['membership_mismatch','grants_mismatch','response_limit','remote_error','unexpected_status','unexpected_shape','invalid_value','transport_dns','transport_tls','transport_connect','transport_timeout','transport_network','transport_protocol','transport_unknown','permission_changed'],true)) return $reason;
    return provider_stackcp_launch_reason($reason);
}
/** No raw HTTP bodies, credentials or browser URLs are admitted to this journal. */
function provider_support_stackcp_document(array $d):array {
    $keys=['version','id','ordinal','previous_sha256','permission_id','admission_id','profile_id','package_id','client_id','staff_id','actor','recorded_epoch','kind','phase','payload'];
    provider_support_stackcp_keys($d,$keys);
    if ($d['version']!==1||!is_array($d['actor'])||!is_array($d['payload'])) throw new InvalidArgumentException('Invalid support StackCP event.');
    foreach (['id','permission_id','profile_id'] as $key) provider_stackcp_launch_hex($d[$key]);
    provider_stackcp_launch_hex($d['previous_sha256'],64);
    foreach (['ordinal','package_id','client_id','staff_id','recorded_epoch'] as $key) provider_stackcp_launch_number($d[$key]);
    $actor=provider_support_stackcp_principal($d['actor']);$p=$d['payload'];
    if ($d['kind']==='permission') {
        provider_support_stackcp_keys($p,['revision','previous_permission_id','previous_permission_sha256','enabled','resource','confirmed']);
        provider_stackcp_launch_number($p['revision']);provider_stackcp_launch_hex($p['previous_permission_sha256'],64);
        if ($p['previous_permission_id']!=='') provider_stackcp_launch_hex($p['previous_permission_id']);
        if (!is_bool($p['enabled'])||$p['confirmed']!==true||!is_array($p['resource'])||$actor['kind']!=='owner'||$d['phase']!=='permission'||$d['id']!==$d['permission_id']||$d['admission_id']!=='') throw new InvalidArgumentException('Invalid support StackCP permission.');
        $r=provider_support_stackcp_resource_document($p['resource']);$b=$r['binding'];
        foreach (['profile_id','package_id','client_id'] as $key) if ($d[$key]!==$b[$key]) throw new InvalidArgumentException('Support StackCP permission resource changed.');
        if ($d['staff_id']!==$r['staff']['id']) throw new InvalidArgumentException('Delegated staff changed.');
        if ($p['enabled']&&($actor['id']!==$b['actor_id']||$actor['auth_version']!==$b['actor_auth_version'])) throw new InvalidArgumentException('Support StackCP permission owner changed.');
        $p=['revision'=>$p['revision'],'previous_permission_id'=>$p['previous_permission_id'],'previous_permission_sha256'=>$p['previous_permission_sha256'],'enabled'=>$p['enabled'],'resource'=>$r,'confirmed'=>true];
    } else {
        provider_stackcp_launch_hex($d['admission_id']);
        if ($actor['kind']!=='support'||$actor['id']!==$d['staff_id']||$actor['client_id']!==$d['client_id']) throw new InvalidArgumentException('Support StackCP launch actor changed.');
        if ($d['kind']==='admitted') {
            provider_support_stackcp_keys($p,['approval','review_proof']);
            if (!is_array($p['approval'])) throw new InvalidArgumentException('Invalid support StackCP form.');
            $a=provider_support_stackcp_approval($p['approval']);provider_stackcp_launch_hex($p['review_proof'],64);
            if ($d['phase']!=='admission'||$d['id']!==$d['admission_id']||$a['admission_id']!==$d['id']||$a['permission_id']!==$d['permission_id']||$a['actor']!==$actor||$d['recorded_epoch']>$a['expires_epoch']||$a['expires_epoch']>$d['recorded_epoch']+300) throw new InvalidArgumentException('Invalid support StackCP admission.');
            $p=['approval'=>$a,'review_proof'=>$p['review_proof']];
        } elseif (in_array($d['kind'],['started','accepted'],true)) {
            if (!in_array($d['phase'],['membership','grants','brand','token','session'],true)) throw new InvalidArgumentException('Invalid support StackCP phase.');
            if ($d['kind']==='accepted'&&in_array($d['phase'],['membership','grants'],true)) {
                provider_support_stackcp_keys($p,['values','observed_epoch']);provider_stackcp_launch_number($p['observed_epoch']);
                if ($p['observed_epoch']>$d['recorded_epoch']+30||!is_array($p['values'])||!array_is_list($p['values'])||count($p['values'])>1000) throw new InvalidArgumentException('Invalid support StackCP observation.');
                $seen=[];$values=[];foreach ($p['values'] as $value) {$v=$d['phase']==='membership'?StackCpAccessBinding::identity($value):StackCpAccessBinding::tuple($value);if (in_array($v,$seen,true)) throw new InvalidArgumentException('Duplicate support StackCP observation.');$seen[]=$v;$values[]=$v;}
                $p=['values'=>$values,'observed_epoch'=>$p['observed_epoch']];
            } elseif ($p!==[]) throw new InvalidArgumentException('Unexpected support StackCP phase data.');
        } elseif ($d['kind']==='terminal') {
            provider_support_stackcp_keys($p,['state','reason']);
            if (!in_array($p['state'],['not_issued','unknown','handoff_attempted'],true)||!is_string($p['reason'])||!in_array($d['phase'],['admission','membership','grants','brand','token','session','delivery'],true)) throw new InvalidArgumentException('Invalid support StackCP outcome.');
            provider_support_stackcp_reason($p['reason']);
            if (($p['state']==='handoff_attempted')!==($d['phase']==='delivery')||($p['state']==='handoff_attempted')!==($p['reason']==='')) throw new InvalidArgumentException('Invalid support StackCP handoff.');
            $p=['state'=>$p['state'],'reason'=>$p['reason']];
        } else throw new InvalidArgumentException('Invalid support StackCP event kind.');
    }
    $out=[];foreach ($keys as $key) $out[$key]=match($key){'actor'=>$actor,'payload'=>$p,default=>$d[$key]};
    // Local defensive bound accommodates the existing reader's 1,000 normalized values.
    if (strlen(provider_support_stackcp_json($out))>131072) throw new InvalidArgumentException('Support StackCP event is too large.');
    return $out;
}
function provider_support_stackcp_project(array $events):array {
    $permissions=[];$allPermissions=[];$admissions=[];$head=str_repeat('0',64);$ordinal=0;$epoch=0;$ids=[];
    $steps=[];foreach (['membership','grants','brand','token','session'] as $phase) {$steps[]=['started',$phase];$steps[]=['accepted',$phase];}
    foreach ($events as $event) {
        $d=provider_support_stackcp_document($event);$scope=$d['profile_id'].':'.$d['package_id'].':'.$d['staff_id'];
        if ($d['ordinal']!==++$ordinal||$d['previous_sha256']!==$head||$d['recorded_epoch']<$epoch||isset($ids[$d['id']])) throw new RuntimeException('Support StackCP chain needs recovery.');
        $ids[$d['id']]=true;$head=hash('sha256',provider_support_stackcp_json($d));$epoch=$d['recorded_epoch'];
        if ($d['kind']==='permission') {
            $old=$permissions[$scope]??null;$p=$d['payload'];
            if ($p['revision']!==($old['payload']['revision']??0)+1||$p['previous_permission_id']!==($old['id']??'')||$p['previous_permission_sha256']!==($old===null?str_repeat('0',64):hash('sha256',provider_support_stackcp_json($old)))||(!$p['enabled']&&($old===null||!$old['payload']['enabled']||$p['resource']!==$old['payload']['resource']))) throw new RuntimeException('Support StackCP permission chain needs recovery.');
            $permissions[$scope]=$d;$allPermissions[$d['id']]=$d;continue;
        }
        $permission=$allPermissions[$d['permission_id']]??null;
        if (!$permission) throw new RuntimeException('Support StackCP permission is missing.');
        if ($d['kind']==='admitted') {
            $a=$d['payload']['approval'];$b=$permission['payload']['resource']['binding'];$prior=null;
            foreach ($admissions as $s) if ($s['admission']['profile_id']===$d['profile_id']&&$s['admission']['package_id']===$d['package_id']&&$s['admission']['client_id']===$d['client_id']&&$s['admission']['staff_id']===$d['staff_id']) $prior=$s;
            $last=$prior===null?null:$prior['events'][array_key_last($prior['events'])];
            if (isset($admissions[$d['id']])||!$permission['payload']['enabled']||($permissions[$scope]['id']??null)!==$permission['id']||$a['permission_sha256']!==hash('sha256',provider_support_stackcp_json($permission))||$a['link_id']!==$b['link_id']||$a['actor']['client_auth_version']!==$b['client_auth_version']||$a['actor']['auth_version']!==$permission['payload']['resource']['staff']['auth_version']||$a['previous_admission_id']!==($prior['admission']['id']??'')||$a['previous_event_sha256']!==($last===null?'':hash('sha256',provider_support_stackcp_json($last)))) throw new RuntimeException('Support StackCP admission binding changed.');
            foreach (['profile_id','package_id','client_id','staff_id'] as $key) if ($d[$key]!==$permission[$key]) throw new RuntimeException('Support StackCP admission resource changed.');
            $admissions[$d['id']]=['admission'=>$d,'events'=>[$d],'step'=>0,'phase'=>'admission','possible_effect'=>false,'terminal'=>null];continue;
        }
        if (!isset($admissions[$d['admission_id']])) throw new RuntimeException('Support StackCP admission is missing.');
        $s=&$admissions[$d['admission_id']];$a=$s['admission'];
        foreach (['permission_id','profile_id','package_id','client_id','staff_id','actor'] as $key) if ($d[$key]!==$a[$key]) throw new RuntimeException('Support StackCP launch identity changed.');
        if ($s['terminal']!==null) throw new RuntimeException('Support StackCP admission is already consumed.');
        if ($d['kind']==='terminal') {
            if ($d['payload']['state']==='handoff_attempted') {if ($s['step']!==10) throw new RuntimeException('Support StackCP handoff is incomplete.');}
            elseif ($d['phase']!==$s['phase']||($d['payload']['state']==='not_issued'&&$s['possible_effect'])) throw new RuntimeException('Support StackCP outcome is not truthful.');
            $s['terminal']=$d;
        } else {
            if (($steps[$s['step']]??null)!==[$d['kind'],$d['phase']]) throw new RuntimeException('Support StackCP phase cannot be reused.');
            if ($d['kind']==='accepted'&&in_array($d['phase'],['membership','grants'],true)) {
                $b=$permission['payload']['resource']['binding'];$p=$d['payload'];$started=$s['events'][array_key_last($s['events'])];
                if ($p['observed_epoch']<$started['recorded_epoch']||($d['phase']==='membership'&&!in_array($b['stack_identity'],$p['values'],true))||($d['phase']==='grants'&&$p['values']!==['package:'.$b['package_id']])) throw new RuntimeException('Support StackCP read does not admit this identity.');
            }
            ++$s['step'];$s['phase']=$d['phase'];if ($d['kind']==='started'&&in_array($d['phase'],['token','session'],true)) $s['possible_effect']=true;
        }
        $s['events'][]=$d;unset($s);
    }
    return ['permissions'=>$permissions,'admissions'=>$admissions];
}
