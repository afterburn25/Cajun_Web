<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once __DIR__.'/client_stackcp_permissions.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-support-handoff/ClientStackCpAccessRead.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-support-handoff/StackCpLaunchSender.php';
use CajunWebPro\Foundation\TwentyISupportHandoff\{ClientStackCpAccessRead,ClientStackCpAccessFailure,StackCpClientHandoffContext,StackCpLaunchSender,StackCpLaunchFailure};

function provider_client_stackcp_launch_actor(int $clientId):array {
    $actor=provider_client_stackcp_actor($clientId);
    if ($actor['kind']!=='client'||$actor['id']!==$clientId) throw new RuntimeException('Primary client access is required.');
    return $actor;
}
function provider_client_stackcp_launch_summary(array $state):array {
    $a=$state['admission'];$terminal=$state['terminal'];
    return ['admission_id'=>$a['admission_id'],'state'=>$terminal['payload']['state']??($state['possible_effect']?'unknown':'interrupted'),
        'phase'=>$terminal['phase']??$state['phase'],'reason'=>$terminal['payload']['reason']??'','created_epoch'=>$a['recorded_epoch']];
}
function provider_client_stackcp_launch_previous(array $states,array $permission):array {
    $previous=null;foreach ($states as $state) {
        $a=$state['admission'];if ($a['profile_id']===$permission['profile_id']&&$a['package_id']===$permission['package_id']&&$a['client_id']===$permission['client_id']) $previous=$state;
    }
    if ($previous===null) return ['previous_admission_id'=>'','previous_event_sha256'=>'','previous_state'=>'none','previous_unknown'=>false];
    $last=$previous['events'][array_key_last($previous['events'])];$summary=provider_client_stackcp_launch_summary($previous);
    return ['previous_admission_id'=>$summary['admission_id'],'previous_event_sha256'=>hash('sha256',provider_client_stackcp_json($last)),
        'previous_state'=>$summary['state'],'previous_unknown'=>$summary['state']==='unknown'];
}
function provider_client_stackcp_launch_approval(string $id,int $expires,array $permission,array $actor,array $previous):array {
    $b=provider_client_stackcp_resource($permission);
    return provider_client_stackcp_approval(['version'=>1,'admission_id'=>$id,'permission_id'=>$permission['id'],
        'permission_sha256'=>hash('sha256',provider_client_stackcp_json($permission)),
        'client_id'=>$actor['id'],'client_auth_version'=>$actor['auth_version'],'link_id'=>$b['link_id'],'expires_epoch'=>$expires,
        'previous_admission_id'=>$previous['previous_admission_id'],'previous_event_sha256'=>$previous['previous_event_sha256'],
        'confirmed'=>true,'provider_session_scope'=>'whole-stack-user','remote_scope_restricted'=>false]);
}
function provider_client_stackcp_status(int $clientId):bool {
    if (db()->inTransaction()) throw new LogicException('Client StackCP status must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($clientId):bool {
        provider_client_stackcp_launch_actor($clientId);return provider_client_stackcp_events()!==null;
    }));
}
/** GET is local only. No read, credential opening, journal append, or provider traffic. */
function provider_client_stackcp_review(string $permissionId,int $clientId):array {
    if (db()->inTransaction()) throw new LogicException('Client StackCP review must own its transaction.');
    provider_stackcp_launch_hex($permissionId);
    return staff_locked(fn()=>staff_transaction(function()use($permissionId,$clientId):array {
        $actor=provider_client_stackcp_launch_actor($clientId);$events=provider_client_stackcp_events();
        $out=['ready'=>$events!==null,'available'=>false,'reason'=>'unprepared','permission_id'=>$permissionId,
            'admission_id'=>'','expires_epoch'=>0,'review_proof'=>'','package_id'=>0,
            'provider_session_scope'=>'whole-stack-user','remote_scope_restricted'=>false,'previous_state'=>'none','previous_unknown'=>false];
        if ($events===null) return $out;
        $permission=provider_client_stackcp_permission($permissionId,$events);$b=provider_client_stackcp_resource($permission);
        if ($b['client_id']!==$clientId) throw new RuntimeException('This hosting panel is unavailable.');
        $out['package_id']=$b['package_id'];
        $previous=provider_client_stackcp_launch_previous(provider_client_stackcp_project($events)['admissions'],$permission);
        $out['previous_state']=$previous['previous_state'];$out['previous_unknown']=$previous['previous_unknown'];
        if (!provider_client_stackcp_current($permission)) {$out['reason']='access_changed';return $out;}
        if (count($events)>9988) {$out['reason']='capacity';return $out;}
        $id=bin2hex(random_bytes(16));$expires=time()+300;
        $approval=provider_client_stackcp_launch_approval($id,$expires,$permission,$actor,$previous);
        $out['available']=true;$out['reason']='';$out['admission_id']=$id;$out['expires_epoch']=$expires;
        $out['review_proof']=provider_client_stackcp_hmac('review',provider_client_stackcp_json($approval));return $out;
    }));
}
function provider_client_stackcp_history(string $permissionId,int $clientId,int $page=1):array {
    if (db()->inTransaction()) throw new LogicException('Client StackCP history must own its transaction.');
    provider_stackcp_launch_hex($permissionId);
    if ($page<1||$page>500) throw new InvalidArgumentException('Invalid client StackCP history page.');
    return staff_locked(fn()=>staff_transaction(function()use($permissionId,$clientId,$page):array {
        provider_client_stackcp_launch_actor($clientId);$events=provider_client_stackcp_events();
        $out=['ready'=>$events!==null,'items'=>[],'page'=>$page,'has_more'=>false];if ($events===null) return $out;
        $permission=provider_client_stackcp_permission($permissionId,$events);
        if (provider_client_stackcp_resource($permission)['client_id']!==$clientId) throw new RuntimeException('This hosting panel is unavailable.');
        $items=[];foreach (provider_client_stackcp_project($events)['admissions'] as $s) if ($s['admission']['permission_id']===$permissionId&&$s['admission']['client_id']===$clientId) $items[]=provider_client_stackcp_launch_summary($s);
        $slice=array_slice(array_reverse($items),($page-1)*20,21);$out['has_more']=count($slice)>20;$out['items']=array_slice($slice,0,20);return $out;
    }));
}
/** Re-read all current local authority at every boundary; retain mutex, release transactions. */
function provider_client_stackcp_launch_authorize(array $admission,bool $delivery=false):void {
    provider_stackcp_guard();if (db()->inTransaction()) throw new LogicException('Client StackCP network cannot inherit a transaction.');
    staff_transaction(function()use($admission,$delivery):void {
        $actor=provider_client_stackcp_launch_actor($admission['client_id']);$events=provider_client_stackcp_events();
        $s=$events===null?null:(provider_client_stackcp_project($events)['admissions'][$admission['admission_id']]??null);
        $allowed=$delivery&&$s!==null&&$s['terminal']!==null&&$s['terminal']['payload']['state']==='handoff_attempted';
        if (!$s||($delivery?!$allowed:$s['terminal']!==null)||$s['admission']!==$admission||$actor!==$admission['actor']) throw new RuntimeException('Client StackCP authority changed.');
        if (time()>$admission['payload']['approval']['expires_epoch']) throw new RuntimeException('Client StackCP approval expired.');
        $permission=provider_client_stackcp_permission($admission['permission_id'],$events);
        if (!provider_client_stackcp_current($permission)||!hash_equals(hash('sha256',provider_client_stackcp_json($permission)),$admission['payload']['approval']['permission_sha256'])) throw new RuntimeException('Client StackCP delegation changed.');
    });
}
function provider_client_stackcp_launch_credential(array $admission,string $role):string {
    provider_client_stackcp_launch_authorize($admission);
    $permission=provider_client_stackcp_permission($admission['permission_id']);$b=provider_client_stackcp_resource($permission);
    if ($role==='general-api-key') {
        $profile=provider_settings_load($b['profile_id']);
        if ((int)$profile['revision']!==$b['profile_revision']||hash('sha256',$profile['credentials'])!==$b['credential_sha256']) throw new RuntimeException('Client StackCP credentials changed.');
        $secret=provider_settings_unseal($b['profile_id'],'live',$profile['credentials']);
        if (!hash_equals(provider_inventory_fingerprint($secret),$b['key_fingerprint'])) throw new RuntimeException('Client StackCP credentials changed.');
    } elseif ($role==='auth-client-key') {
        $row=one('SELECT * FROM '.table('provider_stackcp_auth').' WHERE id=?',[$b['auth_id']]);
        if (!$row||hash('sha256',$row['ciphertext'])!==$b['auth_ciphertext_sha256']||$row['document_sha256']!==$b['auth_sha256']||$row['proof']!==$b['auth_proof']) throw new RuntimeException('Client StackCP authentication changed.');
        $secret=provider_stackcp_vault()->open(ProviderStackCpAuthVault::context(config()['db']['prefix'],$b['profile_id'],$b['auth_id'],$b['auth_revision']),$row['ciphertext']);
    } else throw new LogicException('Unknown client StackCP credential role.');
    provider_client_stackcp_launch_authorize($admission);return $secret;
}
/** Only accepted client journal reads can supply the detached sender context. */
function provider_client_stackcp_launch_context(array $admission):StackCpClientHandoffContext {
    provider_stackcp_guard(true);$events=provider_client_stackcp_events();
    $state=$events===null?null:(provider_client_stackcp_project($events)['admissions'][$admission['admission_id']]??null);
    if (!$state||$state['admission']!==$admission||$state['terminal']!==null) throw new RuntimeException('Client StackCP admission changed.');
    $permission=provider_client_stackcp_permission($admission['permission_id'],$events);$b=provider_client_stackcp_resource($permission);$reads=[];
    foreach ($state['events'] as $event) if ($event['kind']==='accepted'&&in_array($event['phase'],['membership','grants'],true)) $reads[$event['phase']]=$event;
    if (count($reads)!==2||!in_array($b['stack_identity'],$reads['membership']['payload']['values'],true)||$reads['grants']['payload']['values']!==['package:'.$b['package_id']]) throw new RuntimeException('Fresh client StackCP reads are required.');
    $package=[];foreach (['profile_id','profile_revision','key_fingerprint','credential_sha256','package_id','client_id','link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','source_id','source_seq','source_sha256'] as $key) $package[$key]=$b[$key];
    return new StackCpClientHandoffContext(['package'=>$package,'client_id'=>$admission['client_id'],'client_auth_version'=>$admission['actor']['auth_version'],
        'delegation_id'=>$permission['id'],'delegation_revision'=>$permission['payload']['revision'],'delegation_sha256'=>hash('sha256',provider_client_stackcp_json($permission)),
        'delegation_owner_id'=>$permission['actor']['id'],'delegation_owner_auth_version'=>$permission['actor']['auth_version'],
        'auth_credential_id'=>$b['auth_id'],'auth_revision'=>$b['auth_revision'],'auth_credential_sha256'=>$b['auth_ciphertext_sha256'],
        'stack_user_identity'=>$b['stack_identity'],'membership_source_id'=>$reads['membership']['id'],'membership_source_seq'=>$reads['membership']['ordinal'],
        'membership_source_sha256'=>hash('sha256',provider_client_stackcp_json($reads['membership'])),
        'grants_source_id'=>$reads['grants']['id'],'grants_source_seq'=>$reads['grants']['ordinal'],'grants_source_sha256'=>hash('sha256',provider_client_stackcp_json($reads['grants'])),
        'observed_grants'=>$reads['grants']['payload']['values'],'observed_at'=>min($reads['membership']['payload']['observed_epoch'],$reads['grants']['payload']['observed_epoch']),
        'permission_id'=>$admission['admission_id'],'permission_revision'=>1,'permission_sha256'=>hash('sha256',provider_client_stackcp_json($admission['payload']['approval']))]);
}
/** All admitted attempts count, regardless of outcome. Exact replay bypasses this admission limit. */
function provider_client_stackcp_launch_limit(array $states,int $clientId,string $profileId,int $now):void {
    provider_stackcp_guard(true);$client=0;$profile=0;
    foreach ($states as $state) {
        $a=$state['admission'];if ($a['recorded_epoch']<=$now-60) continue;
        if ($a['client_id']===$clientId) ++$client;
        if ($a['profile_id']===$profileId) ++$profile;
    }
    if ($client>=5||$profile>=30) throw new RuntimeException('Too many control panel attempts. Try again in a minute.');
}
/** Explicit POST consent consumes one admission before any DNS/key/GET/POST effect. */
function provider_client_stackcp_open(string $admissionId,string $permissionId,int $clientId,int $expiresEpoch,string $reviewProof,
    bool $confirmed,callable $deliver,?callable $lookup=null,?callable $readTransportFactory=null,?callable $transportFactory=null):array {
    if (db()->inTransaction()) throw new LogicException('Client StackCP launch must own its transactions.');
    foreach ([$admissionId,$permissionId] as $id) provider_stackcp_launch_hex($id);provider_stackcp_launch_hex($reviewProof,64);
    if ($clientId<1||$expiresEpoch<1||$expiresEpoch>2147483647) throw new InvalidArgumentException('Invalid client StackCP admission.');
    if (!$confirmed) throw new InvalidArgumentException('Confirm whole-user StackCP access before continuing.');
    return staff_locked(function()use($admissionId,$permissionId,$clientId,$expiresEpoch,$reviewProof,$deliver,$lookup,$readTransportFactory,$transportFactory):array {
        $admission=staff_transaction(function()use($admissionId,$permissionId,$clientId,$expiresEpoch,$reviewProof):array {
            $actor=provider_client_stackcp_launch_actor($clientId);$events=provider_client_stackcp_events();
            if ($events===null) throw new RuntimeException('Client StackCP access is not prepared.');
            $permission=provider_client_stackcp_permission($permissionId,$events);$b=provider_client_stackcp_resource($permission);
            if ($b['client_id']!==$clientId||!provider_client_stackcp_current($permission)) throw new RuntimeException('Client StackCP delegation changed.');
            $states=provider_client_stackcp_project($events)['admissions'];
            if (isset($states[$admissionId])) {
                $s=$states[$admissionId];$a=$s['admission'];
                if ($a['permission_id']!==$permissionId||$a['actor']!==$actor||$a['payload']['approval']['expires_epoch']!==$expiresEpoch||!hash_equals($a['payload']['review_proof'],$reviewProof)) throw new RuntimeException('This launch does not match the original approval.');
                return ['replay'=>provider_client_stackcp_launch_summary($s)];
            }
            if ($expiresEpoch<time()||$expiresEpoch>time()+300) throw new RuntimeException('The client StackCP review expired.');
            $approval=provider_client_stackcp_launch_approval($admissionId,$expiresEpoch,$permission,$actor,provider_client_stackcp_launch_previous($states,$permission));
            $proof=provider_client_stackcp_hmac('review',provider_client_stackcp_json($approval));
            if (!hash_equals($proof,$reviewProof)) throw new RuntimeException('Client StackCP access or a previous launch changed. Review again.');
            provider_client_stackcp_launch_limit($states,$clientId,$b['profile_id'],time());
            return provider_client_stackcp_append('admitted','admission',['approval'=>$approval,'review_proof'=>$proof],['id'=>$admissionId,'permission_id'=>$permissionId,'actor'=>$actor]);
        });
        if (isset($admission['replay'])) return $admission['replay'];
        $authorize=static fn()=>provider_client_stackcp_launch_authorize($admission);
        $phase=static function(string $kind,string $phase,array $payload=[])use($admission,$authorize):void {
            $authorize();staff_transaction(fn()=>provider_client_stackcp_append($kind,$phase,$payload,['id'=>$admission['admission_id']]));
        };
        try {
            $permission=provider_client_stackcp_permission($permissionId);$b=provider_client_stackcp_resource($permission);
            foreach (['membership','grants'] as $readPhase) {
                $phase('started',$readPhase);
                $observed=ClientStackCpAccessRead::read($readPhase,$b['package_id'],$b['stack_identity'],
                    static fn(string $role):string=>provider_client_stackcp_launch_credential($admission,$role),$authorize,$lookup,$readTransportFactory);
                $phase('accepted',$readPhase,$observed);
            }
            $context=staff_transaction(fn()=>provider_client_stackcp_launch_context($admission));
            $delivery=StackCpLaunchSender::launch($context,static fn(string $role):string=>provider_client_stackcp_launch_credential($admission,$role),
                $authorize,static fn(string $p)=>$phase('started',$p),static fn(string $p)=>$phase('accepted',$p),$lookup,$transportFactory);
            $authorize();staff_transaction(fn()=>provider_client_stackcp_append('terminal','delivery',['state'=>'handoff_attempted','reason'=>''],['id'=>$admissionId]));
        } catch (Throwable $error) {
            if ($error instanceof PDOException) throw $error;
            $result=staff_transaction(function()use($admissionId,$error):array {
                $events=provider_client_stackcp_events();$s=$events===null?null:(provider_client_stackcp_project($events)['admissions'][$admissionId]??null);
                if (!$s||$s['terminal']!==null) throw new RuntimeException('Client StackCP launch history needs review.');
                $reason=$error instanceof StackCpLaunchFailure||$error instanceof ClientStackCpAccessFailure?$error->reason:
                    ($error instanceof RuntimeException||$error instanceof InvalidArgumentException?'authority_changed':'internal_failure');
                $possible=$s['possible_effect']||($error instanceof StackCpLaunchFailure&&$error->possibly_sent);
                provider_client_stackcp_append('terminal',$s['phase'],['state'=>$possible?'unknown':'not_issued','reason'=>$reason],['id'=>$admissionId]);
                return provider_client_stackcp_launch_summary(provider_client_stackcp_project(provider_client_stackcp_events())['admissions'][$admissionId]);
            });
            if (!$error instanceof StackCpLaunchFailure&&!$error instanceof ClientStackCpAccessFailure&&!$error instanceof RuntimeException&&!$error instanceof InvalidArgumentException) throw new LogicException('Client StackCP launch stopped after recording its disposition.');
            return $result;
        }
        provider_client_stackcp_launch_authorize($admission,true);
        $delivery->consume($deliver);unset($delivery);
        return staff_transaction(fn()=>provider_client_stackcp_launch_summary(provider_client_stackcp_project(provider_client_stackcp_events())['admissions'][$admissionId]));
    });
}
