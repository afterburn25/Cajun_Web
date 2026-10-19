<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-stackcp-access/StackCpAccessBinding.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-support-handoff/StackCpHandoffContext.php';
use CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding;
use CajunWebPro\Foundation\TwentyISupportHandoff\StackCpHandoffContext;

function provider_stackcp_launch_json(array $value):string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function provider_stackcp_launch_keys(array $value,array $keys):void {
    $actual=array_keys($value);sort($actual);sort($keys);
    if ($actual!==$keys) throw new InvalidArgumentException('Invalid StackCP launch document.');
}
function provider_stackcp_launch_hex(mixed $value,int $length=32):void {
    if (!is_string($value)||preg_match('/\A[a-f0-9]{'.$length.'}\z/',$value)!==1) throw new InvalidArgumentException('Invalid StackCP launch identifier.');
}
function provider_stackcp_launch_number(mixed $value,int $minimum=1):void {
    if (!is_int($value)||$value<$minimum||$value>2147483647) throw new InvalidArgumentException('Invalid StackCP launch number.');
}
/** Signed form data; the proof is not a substitute for current DB authority. */
function provider_stackcp_launch_approval(array $input):array {
    $keys=['version','admission_id','expires_epoch','binding','grants_id','grants_seq','grants_sha256','membership_observed_epoch','grants_observed_epoch','previous_admission_id','previous_event_sha256','confirmed','provider_session_scope','remote_scope_restricted'];
    provider_stackcp_launch_keys($input,$keys);
    if ($input['version']!==1||!is_array($input['binding'])||$input['confirmed']!==true||$input['provider_session_scope']!=='whole-stack-user'||$input['remote_scope_restricted']!==false) throw new InvalidArgumentException('Invalid StackCP launch approval.');
    provider_stackcp_launch_hex($input['admission_id']);provider_stackcp_launch_hex($input['grants_id']);provider_stackcp_launch_hex($input['grants_sha256'],64);
    if ($input['previous_admission_id']===''&&$input['previous_event_sha256']==='') {} else {provider_stackcp_launch_hex($input['previous_admission_id']);provider_stackcp_launch_hex($input['previous_event_sha256'],64);}
    foreach (['expires_epoch','grants_seq','membership_observed_epoch','grants_observed_epoch'] as $key) provider_stackcp_launch_number($input[$key]);
    $binding=new StackCpAccessBinding($input['binding']);
    if ($binding->data['operation']!==StackCpAccessBinding::GRANTS) throw new InvalidArgumentException('Invalid StackCP launch observation.');
    $out=[];foreach ($keys as $key) $out[$key]=$key==='binding'?$binding->data:$input[$key];
    return $out;
}
/** The permission is this exact one-use consent, not a website support policy. */
function provider_stackcp_launch_context(array $approval):StackCpHandoffContext {
    $a=provider_stackcp_launch_approval($approval);$b=$a['binding'];$package=[];
    foreach (['profile_id','profile_revision','key_fingerprint','credential_sha256','package_id','client_id','link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','source_id','source_seq','source_sha256'] as $key) $package[$key]=$b[$key];
    return new StackCpHandoffContext(['package'=>$package,'actor_id'=>$b['actor_id'],'actor_auth_version'=>$b['actor_auth_version'],'client_auth_version'=>$b['client_auth_version'],'auth_credential_id'=>$b['auth_id'],'auth_revision'=>$b['auth_revision'],'auth_credential_sha256'=>$b['auth_ciphertext_sha256'],'stack_user_identity'=>$b['stack_identity'],'membership_source_id'=>$b['membership_id'],'membership_source_seq'=>$b['membership_seq'],'membership_source_sha256'=>$b['membership_sha256'],'grants_source_id'=>$a['grants_id'],'grants_source_seq'=>$a['grants_seq'],'grants_source_sha256'=>$a['grants_sha256'],'observed_grants'=>['package:'.$b['package_id']],'observed_at'=>min($a['membership_observed_epoch'],$a['grants_observed_epoch']),'permission_id'=>$a['admission_id'],'permission_revision'=>1,'permission_sha256'=>hash('sha256',provider_stackcp_launch_json($a))]);
}
function provider_stackcp_launch_reason(string $reason):string {
    // Finite local/transport classifications only. Never retain exception messages or bodies.
    if (!in_array($reason,['','authority_changed','internal_failure','dns','credentials','access_changed','transport','http_status','authentication','rate_limited','upstream_unavailable','body_limit','malformed_body','shape_unsupported','field_unsupported','field_limit','duplicate_field','credential_echo','custom_brand','unavailable'],true)) throw new InvalidArgumentException('Invalid StackCP launch reason.');
    return $reason;
}
function provider_stackcp_launch_document(array $input):array {
    $keys=['version','id','ordinal','previous_sha256','admission_id','profile_id','package_id','actor_id','actor_auth_version','recorded_epoch','kind','phase','payload'];
    provider_stackcp_launch_keys($input,$keys);
    if ($input['version']!==1||!is_array($input['payload'])) throw new InvalidArgumentException('Invalid StackCP launch event.');
    foreach (['id','admission_id','profile_id','actor_auth_version'] as $key) provider_stackcp_launch_hex($input[$key]);provider_stackcp_launch_hex($input['previous_sha256'],64);
    foreach (['ordinal','package_id','actor_id','recorded_epoch'] as $key) provider_stackcp_launch_number($input[$key]);
    if (!in_array($input['kind'],['admitted','started','accepted','terminal'],true)||!in_array($input['phase'],['admission','brand','token','session','delivery'],true)) throw new InvalidArgumentException('Invalid StackCP launch phase.');
    $p=$input['payload'];
    if ($input['kind']==='admitted') {
        provider_stackcp_launch_keys($p,['approval','review_proof','context_sha256']);
        if (!is_array($p['approval'])) throw new InvalidArgumentException('Invalid StackCP launch approval.');
        $a=provider_stackcp_launch_approval($p['approval']);$b=$a['binding'];$context=provider_stackcp_launch_context($a);
        provider_stackcp_launch_hex($p['review_proof'],64);provider_stackcp_launch_hex($p['context_sha256'],64);
        if ($input['phase']!=='admission'||$input['id']!==$input['admission_id']||$a['admission_id']!==$input['admission_id']||$b['profile_id']!==$input['profile_id']||$b['package_id']!==$input['package_id']||$b['actor_id']!==$input['actor_id']||$b['actor_auth_version']!==$input['actor_auth_version']||!hash_equals($context->sha256,$p['context_sha256'])||$input['recorded_epoch']>$a['expires_epoch']||$a['expires_epoch']>$input['recorded_epoch']+300) throw new InvalidArgumentException('Invalid StackCP launch admission binding.');
        $p=['approval'=>$a,'review_proof'=>$p['review_proof'],'context_sha256'=>$context->sha256];
    } elseif ($input['kind']==='terminal') {
        provider_stackcp_launch_keys($p,['state','reason']);
        if (!in_array($p['state'],['not_issued','unknown','handoff_attempted'],true)||!is_string($p['reason'])) throw new InvalidArgumentException('Invalid StackCP launch disposition.');
        provider_stackcp_launch_reason($p['reason']);
        if (($p['state']==='handoff_attempted')!==($input['phase']==='delivery')||($p['state']==='handoff_attempted')!==($p['reason']==='')) throw new InvalidArgumentException('Invalid StackCP launch delivery disposition.');
        $p=['state'=>$p['state'],'reason'=>$p['reason']];
    } elseif ($p!==[]||!in_array($input['phase'],['brand','token','session'],true)) throw new InvalidArgumentException('Invalid StackCP launch phase event.');
    $out=[];foreach ($keys as $key) $out[$key]=$key==='payload'?$p:$input[$key];
    if (strlen(provider_stackcp_launch_json($out))>16384) throw new InvalidArgumentException('StackCP launch document is too large.');
    return $out;
}
/** Strict closed sequence, including permanently consumed interrupted admissions. */
function provider_stackcp_launch_project(array $events):array {
    $out=[];$steps=[['started','brand'],['accepted','brand'],['started','token'],['accepted','token'],['started','session'],['accepted','session']];
    foreach ($events as $event) {
        $d=provider_stackcp_launch_document($event);$id=$d['admission_id'];
        if ($d['kind']==='admitted') {
            if (isset($out[$id])) throw new RuntimeException('Duplicate StackCP launch admission.');
            $prior=null;foreach ($out as $old) if ($old['admission']['profile_id']===$d['profile_id']&&$old['admission']['package_id']===$d['package_id']) $prior=$old;
            $approval=$d['payload']['approval'];$last=$prior===null?null:$prior['events'][array_key_last($prior['events'])];
            if ($approval['previous_admission_id']!==($prior['admission']['admission_id']??'')||$approval['previous_event_sha256']!==($last===null?'':hash('sha256',provider_stackcp_launch_json($last)))) throw new RuntimeException('StackCP launch prior outcome changed.');
            $out[$id]=['admission'=>$d,'events'=>[$d],'step'=>0,'terminal'=>null,'phase'=>'admission','possible_effect'=>false];continue;
        }
        if (!isset($out[$id])) throw new RuntimeException('Missing StackCP launch admission.');
        $s=&$out[$id];$a=$s['admission'];$previous=$s['events'][array_key_last($s['events'])];
        foreach (['profile_id','package_id','actor_id','actor_auth_version'] as $key) if ($d[$key]!==$a[$key]) throw new RuntimeException('StackCP launch actor or resource changed.');
        if ($s['terminal']!==null||$d['recorded_epoch']<$previous['recorded_epoch']) throw new RuntimeException('StackCP launch phase history changed.');
        if ($d['kind']==='terminal') {
            if ($d['payload']['state']==='handoff_attempted') {
                if ($s['step']!==6) throw new RuntimeException('StackCP launch is not ready for browser handoff.');
            } elseif ($d['phase']!==$s['phase']||($d['payload']['state']==='not_issued'&&$s['possible_effect'])) throw new RuntimeException('Invalid StackCP launch failure phase.');
            $s['terminal']=$d;
        } else {
            if (($steps[$s['step']]??null)!==[$d['kind'],$d['phase']]) throw new RuntimeException('StackCP launch phase is not reusable.');
            ++$s['step'];$s['phase']=$d['phase'];if ($d['kind']==='started'&&in_array($d['phase'],['token','session'],true)) $s['possible_effect']=true;
        }
        $s['events'][]=$d;unset($s);
    }
    return $out;
}
