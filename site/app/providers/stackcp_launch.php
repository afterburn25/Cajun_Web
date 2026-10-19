<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once __DIR__.'/stackcp_launch_storage.php';
use CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding;
use CajunWebPro\Foundation\TwentyISupportHandoff\{StackCpLaunchSender,StackCpLaunchFailure};

/** Exact current observation and assignment facts. No credential is decrypted here. */
function provider_stackcp_launch_resource(string $profile,int $package):array {
    provider_stackcp_guard(true);
    if (!provider_stackcp_access_ready()) throw new ProviderStackCpAccessUnavailable('Prepare StackCP access observations first.');
    $binding=provider_stackcp_access_binding($profile,$package,StackCpAccessBinding::GRANTS);$b=$binding->data;
    $row=provider_stackcp_access_latest($profile,$package,$b['stack_identity'],StackCpAccessBinding::GRANTS);$saved=$row?provider_stackcp_access_saved($row):null;
    if (!$saved||!provider_stackcp_access_recent($saved)||!hash_equals($binding->sha256,$saved['binding']->sha256)||$saved['finish']['values']!==['package:'.$package]) throw new ProviderStackCpAccessUnavailable('Refresh and review the selected StackCP user access first.');
    $member=one('SELECT * FROM '.table('provider_stackcp_reads').' WHERE id=?',[$b['membership_id']]);$m=$member?provider_stackcp_access_saved($member):null;
    if (!$m||!provider_stackcp_access_recent($m)) throw new ProviderStackCpAccessUnavailable('Refresh package membership first.');
    return ['binding'=>$b,'grants_id'=>$row['id'],'grants_seq'=>(int)$saved['start']['common']['seq'],'grants_sha256'=>$saved['row']['document_sha256'],'membership_observed_epoch'=>$m['finish']['observed_epoch'],'grants_observed_epoch'=>$saved['finish']['observed_epoch']];
}
function provider_stackcp_launch_summary(array $state):array {
    $a=$state['admission'];$terminal=$state['terminal'];
    return ['admission_id'=>$a['admission_id'],'state'=>$terminal['payload']['state']??($state['possible_effect']?'unknown':'interrupted'),'phase'=>$terminal['phase']??$state['phase'],'reason'=>$terminal['payload']['reason']??'','created_epoch'=>$a['recorded_epoch']];
}
function provider_stackcp_launch_previous(array $states,string $profile,int $package):array {
    $previous=null;foreach ($states as $s) if ($s['admission']['profile_id']===$profile&&$s['admission']['package_id']===$package) $previous=$s;
    if ($previous===null) return ['previous_admission_id'=>'','previous_event_sha256'=>'','previous_state'=>'none','previous_unknown'=>false];
    $last=$previous['events'][array_key_last($previous['events'])];$summary=provider_stackcp_launch_summary($previous);
    return ['previous_admission_id'=>$summary['admission_id'],'previous_event_sha256'=>hash('sha256',provider_stackcp_launch_json($last)),'previous_state'=>$summary['state'],'previous_unknown'=>$summary['state']==='unknown'];
}
function provider_stackcp_launch_make_approval(string $id,int $expires,array $resource,array $previous):array {
    return provider_stackcp_launch_approval(['version'=>1,'admission_id'=>$id,'expires_epoch'=>$expires]+$resource+['previous_admission_id'=>$previous['previous_admission_id'],'previous_event_sha256'=>$previous['previous_event_sha256'],'confirmed'=>true,'provider_session_scope'=>'whole-stack-user','remote_scope_restricted'=>false]);
}
function provider_stackcp_launch_status():array {
    if (db()->inTransaction()) throw new LogicException('StackCP launch status must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function():array {provider_stackcp_launch_actor();return ['ready'=>provider_stackcp_launch_ready()];}));
}
/** GET mints only an HMAC-bound form; admission is not persisted until explicit POST consent. */
function provider_stackcp_launch_review(string $profileId,int $packageId):array {
    if (db()->inTransaction()) throw new LogicException('StackCP launch review must own its transaction.');
    provider_stackcp_launch_hex($profileId);provider_stackcp_launch_number($packageId);
    return staff_locked(fn()=>staff_transaction(function()use($profileId,$packageId):array {
        provider_stackcp_launch_actor();$events=provider_stackcp_launch_events();
        $out=['ready'=>$events!==null,'available'=>false,'reason'=>'unprepared','admission_id'=>'','expires_epoch'=>0,'review_proof'=>'','profile_id'=>$profileId,'package_id'=>$packageId,'provider_session_scope'=>'whole-stack-user','remote_scope_restricted'=>false,'stack_identity'=>'','previous_state'=>'none','previous_unknown'=>false];
        if ($events===null) return $out;
        $previous=provider_stackcp_launch_previous(provider_stackcp_launch_project($events),$profileId,$packageId);$out['previous_state']=$previous['previous_state'];$out['previous_unknown']=$previous['previous_unknown'];
        try {$resource=provider_stackcp_launch_resource($profileId,$packageId);} catch (ProviderStackCpAccessUnavailable) {$out['reason']='refresh_required';return $out;}
        if (count($events)>9992) {$out['reason']='capacity';return $out;}
        $id=bin2hex(random_bytes(16));$expires=time()+300;$a=provider_stackcp_launch_make_approval($id,$expires,$resource,$previous);
        $out['available']=true;$out['reason']='';$out['admission_id']=$id;$out['expires_epoch']=$expires;$out['review_proof']=provider_stackcp_launch_hmac('review',provider_stackcp_launch_json($a));$out['stack_identity']=$resource['binding']['stack_identity'];return $out;
    }));
}
function provider_stackcp_launch_history(string $profileId,int $packageId,int $page=1):array {
    if (db()->inTransaction()) throw new LogicException('StackCP launch history must own its transaction.');
    provider_stackcp_launch_hex($profileId);provider_stackcp_launch_number($packageId);
    if ($page<1||$page>500) throw new InvalidArgumentException('Invalid StackCP launch history page.');
    return staff_locked(fn()=>staff_transaction(function()use($profileId,$packageId,$page):array {
        provider_stackcp_launch_actor();$events=provider_stackcp_launch_events();$out=['ready'=>$events!==null,'items'=>[],'page'=>$page,'has_more'=>false];if ($events===null) return $out;
        $items=[];foreach (provider_stackcp_launch_project($events) as $s) if ($s['admission']['profile_id']===$profileId&&$s['admission']['package_id']===$packageId) $items[]=provider_stackcp_launch_summary($s);
        $slice=array_slice(array_reverse($items),($page-1)*20,21);$out['has_more']=count($slice)>20;$out['items']=array_slice($slice,0,20);return $out;
    }));
}
/** Callback guard: all durable/current authority checks occur in a fresh short transaction. */
function provider_stackcp_launch_authorize(array $admission,bool $delivery=false):void {
    provider_stackcp_guard();if (db()->inTransaction()) throw new LogicException('StackCP transport cannot inherit a transaction.');
    staff_transaction(function()use($admission,$delivery):void {
        $actor=provider_stackcp_launch_actor();$events=provider_stackcp_launch_events();$states=$events===null?[]:provider_stackcp_launch_project($events);$s=$states[$admission['admission_id']]??null;
        $terminalAllowed=$delivery&&$s!==null&&$s['terminal']!==null&&$s['terminal']['payload']['state']==='handoff_attempted';
        if (!$s||($delivery?!$terminalAllowed:$s['terminal']!==null)||$s['admission']!==$admission||(int)$actor['id']!==$admission['actor_id']||$actor['auth_version']!==$admission['actor_auth_version']) throw new RuntimeException('StackCP launch authority changed.');
        $approval=$admission['payload']['approval'];
        if (time()>$approval['expires_epoch']) throw new RuntimeException('StackCP launch approval expired.');
        $resource=provider_stackcp_launch_resource($admission['profile_id'],$admission['package_id']);
        foreach ($resource as $key=>$value) if ($approval[$key]!==$value) throw new RuntimeException('StackCP launch authority changed.');
    });
}
/** Open only the requested role, after the sender's validated fixed-host DNS and fresh authority. */
function provider_stackcp_launch_credential(array $admission,string $role):string {
    provider_stackcp_launch_authorize($admission);$b=$admission['payload']['approval']['binding'];
    if ($role==='general-api-key') {
        $profile=provider_settings_load($b['profile_id']);
        if ((int)$profile['revision']!==$b['profile_revision']||hash('sha256',$profile['credentials'])!==$b['credential_sha256']) throw new RuntimeException('StackCP launch credentials changed.');
        $secret=provider_settings_unseal($b['profile_id'],'live',$profile['credentials']);
        if (!hash_equals(provider_inventory_fingerprint($secret),$b['key_fingerprint'])) throw new RuntimeException('StackCP launch credentials changed.');
    } elseif ($role==='auth-client-key') {
        $row=one('SELECT * FROM '.table('provider_stackcp_auth').' WHERE id=?',[$b['auth_id']]);
        if (!$row||hash('sha256',$row['ciphertext'])!==$b['auth_ciphertext_sha256']||$row['document_sha256']!==$b['auth_sha256']||$row['proof']!==$b['auth_proof']) throw new RuntimeException('StackCP launch authentication changed.');
        $secret=provider_stackcp_vault()->open(ProviderStackCpAuthVault::context(config()['db']['prefix'],$b['profile_id'],$b['auth_id'],$b['auth_revision']),$row['ciphertext']);
    } else throw new LogicException('Unknown StackCP launch credential role.');
    provider_stackcp_launch_authorize($admission);return $secret;
}
/** One-use owner launch. Resolver/transport are internal tests only, never HTTP input. */
function provider_stackcp_launch_open(string $admissionId,string $profileId,int $packageId,int $expiresEpoch,string $reviewProof,bool $confirmed,#[SensitiveParameter] string $password,callable $deliver,?callable $lookup=null,?callable $transportFactory=null):array {
    if (db()->inTransaction()) throw new LogicException('StackCP launch must own its transactions.');
    foreach ([$admissionId,$profileId] as $id) provider_stackcp_launch_hex($id);provider_stackcp_launch_hex($reviewProof,64);provider_stackcp_launch_number($packageId);provider_stackcp_launch_number($expiresEpoch);
    if (!$confirmed) throw new InvalidArgumentException('Confirm the selected StackCP user and whole-user access before continuing.');
    return staff_locked(function()use($admissionId,$profileId,$packageId,$expiresEpoch,$reviewProof,$password,$deliver,$lookup,$transportFactory):array {
        $admission=staff_transaction(function()use($admissionId,$profileId,$packageId,$expiresEpoch,$reviewProof,$password):array {
            $actor=provider_stackcp_launch_actor($password);$events=provider_stackcp_launch_events();if ($events===null) throw new RuntimeException('Prepare StackCP launches first.');$states=provider_stackcp_launch_project($events);
            if (isset($states[$admissionId])) {
                $s=$states[$admissionId];$a=$s['admission'];
                if ($a['profile_id']!==$profileId||$a['package_id']!==$packageId||$a['actor_id']!==(int)$actor['id']||$a['actor_auth_version']!==$actor['auth_version']||$a['payload']['approval']['expires_epoch']!==$expiresEpoch||!hash_equals($a['payload']['review_proof'],$reviewProof)) throw new RuntimeException('This StackCP launch does not match the original approval.');
                return ['replay'=>provider_stackcp_launch_summary($s)];
            }
            if ($expiresEpoch<time()||$expiresEpoch>time()+300) throw new RuntimeException('The StackCP review expired. Reload and review again.');
            $resource=provider_stackcp_launch_resource($profileId,$packageId);$previous=provider_stackcp_launch_previous($states,$profileId,$packageId);$a=provider_stackcp_launch_make_approval($admissionId,$expiresEpoch,$resource,$previous);$proof=provider_stackcp_launch_hmac('review',provider_stackcp_launch_json($a));
            if (!hash_equals($proof,$reviewProof)) throw new RuntimeException('StackCP access or a previous launch changed. Reload and review again.');
            $context=provider_stackcp_launch_context($a);
            return provider_stackcp_launch_append($admissionId,'admitted','admission',['approval'=>$a,'review_proof'=>$proof,'context_sha256'=>$context->sha256],$a);
        });
        if (isset($admission['replay'])) return $admission['replay'];
        $authorize=static fn()=>provider_stackcp_launch_authorize($admission);
        $phase=static function(string $kind,string $phase)use($admission,$authorize):void {
            $authorize();staff_transaction(fn()=>provider_stackcp_launch_append($admission['admission_id'],$kind,$phase,[]));
        };
        try {
            require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-support-handoff/StackCpLaunchSender.php';
            $delivery=StackCpLaunchSender::launch(provider_stackcp_launch_context($admission['payload']['approval']),static fn(string $role):string=>provider_stackcp_launch_credential($admission,$role),$authorize,static fn(string $p)=>$phase('started',$p),static fn(string $p)=>$phase('accepted',$p),$lookup,$transportFactory);
            $authorize();
            staff_transaction(fn()=>provider_stackcp_launch_append($admissionId,'terminal','delivery',['state'=>'handoff_attempted','reason'=>'']));
        } catch (Throwable $error) {
            // A failed SQL commit has unknown persistence. Leave the admission consumed;
            // do not write a competing terminal or expose an in-memory delivery.
            if ($error instanceof PDOException) throw $error;
            $result=staff_transaction(function()use($admissionId,$error):array {
                $events=provider_stackcp_launch_events();$s=$events===null?null:(provider_stackcp_launch_project($events)[$admissionId]??null);
                if (!$s||$s['terminal']!==null) throw new RuntimeException('StackCP launch history needs review.');
                $reason=$error instanceof StackCpLaunchFailure?$error->reason:($error instanceof RuntimeException||$error instanceof InvalidArgumentException?'authority_changed':'internal_failure');
                $possible=$s['possible_effect']||($error instanceof StackCpLaunchFailure&&$error->possibly_sent);
                provider_stackcp_launch_append($admissionId,'terminal',$s['phase'],['state'=>$possible?'unknown':'not_issued','reason'=>$reason]);
                $events=provider_stackcp_launch_events();return provider_stackcp_launch_summary(provider_stackcp_launch_project($events)[$admissionId]);
            });
            if (!$error instanceof StackCpLaunchFailure&&!$error instanceof RuntimeException&&!$error instanceof InvalidArgumentException) throw new LogicException('StackCP launch stopped after recording its disposition.');
            return $result;
        }
        // A terminating/throwing browser callback can never expose this session again.
        // Its one-use terminal has already committed, with no token or URL in SQL.
        provider_stackcp_launch_authorize($admission,true);
        $delivery->consume($deliver);unset($delivery);
        return staff_transaction(function()use($admissionId):array {$events=provider_stackcp_launch_events();return provider_stackcp_launch_summary(provider_stackcp_launch_project($events)[$admissionId]);});
    });
}
