<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
foreach (['StackCpAccessBinding','StackCpAccessJson','StackCpAccessObservation','StackCpAccessRead'] as $class) require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-stackcp-access/'.$class.'.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_access_storage.php';
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding,StackCpAccessObservation};
final class ProviderStackCpAccessUnavailable extends RuntimeException {}

/** Independent package-source order check; no PHP/WordPress operation grants are borrowed. */
function provider_stackcp_access_package_history(string $profile):void {
    $f=table('provider_refreshes');$a=table('audit');$detail="CONCAT(BINARY 'Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision)";
    if ((int)query("SELECT COUNT(*) FROM $f WHERE BINARY profile_id=?",[$profile])->fetchColumn()!==(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.refresh_started' AND BINARY detail LIKE ?",['Refresh % profile '.$profile.' revision %'])->fetchColumn()||one("SELECT f.id FROM $f f WHERE BINARY f.profile_id=? AND ((SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id)<>1) LIMIT 1",[$profile])) throw new RuntimeException('StackCP hosting intent correspondence needs recovery.');
    $filter='BINARY f.profile_id=? AND (f.operation IS NULL OR BINARY f.operation=?)';$args=[$profile,InventoryRead::PACKAGES_LIST];
    $seq=one("SELECT f.id FROM $f f WHERE $filter ORDER BY f.seq DESC LIMIT 1",$args);$audit=one("SELECT f.id FROM $f f JOIN $a a ON a.user_id=f.actor_id AND BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail WHERE $filter ORDER BY a.id DESC LIMIT 1",$args);
    if (($seq['id']??null)!==($audit['id']??null)) throw new RuntimeException('StackCP hosting intent order needs recovery.');
}
/** Current locally approved resource facts; distinct from remotely observed access and from a session. */
function provider_stackcp_access_base(string $profile,int $package):array {
    provider_stackcp_guard();$actor=provider_settings_manager();$vault=provider_stackcp_vault();$c=provider_stackcp_assignment_context($profile,$package,$vault);
    if (!$c['prepared']||$c['status']!=='unverified'||$c['binding']===null||$c['auth']['status']!=='configured') throw new ProviderStackCpAccessUnavailable('Review a current protected StackCP assignment first.');
    $assignment=$c['document'];$auth=$c['auth']['document'];$local=$c['binding'];provider_stackcp_access_package_history($profile);$latest=service_observations_reads($profile)[0]??null;$p=provider_settings_load($profile);
    if (!$latest||!service_observations_fresh($latest)||!provider_inventory_link_context_matches($local,$p,$latest)) throw new ProviderStackCpAccessUnavailable('Refresh hosting inventory for this exact assignment first.');
    $source=provider_stackcp_access_package_source($latest['id'],$package);if ((int)$source['start']['id']<=$local['audit_id']) throw new ProviderStackCpAccessUnavailable('Refresh hosting inventory after this assignment or reapproval.');
    $b=['operation'=>StackCpAccessBinding::MEMBERSHIP,'profile_id'=>$profile,'profile_revision'=>$local['profile_revision'],'credential_sha256'=>$local['profile_credentials_sha256'],'key_fingerprint'=>$local['key_fingerprint'],'package_id'=>$package,'client_id'=>$local['client_id'],'client_auth_version'=>$local['client_auth_version'],'link_id'=>$local['link_id'],'link_sha256'=>$local['link_sha256'],'event_id'=>$local['event_id'],'event_sha256'=>$local['event_sha256'],'audit_id'=>$local['audit_id'],'audit_sha256'=>$local['audit_sha256'],'source_id'=>$source['row']['id'],'source_seq'=>(int)$source['row']['seq'],'source_sha256'=>$source['sha256'],'actor_id'=>(int)$actor['id'],'actor_auth_version'=>$actor['auth_version'],'assignment_id'=>$assignment['id'],'assignment_revision'=>$assignment['revision'],'assignment_sha256'=>$c['row']['document_sha256'],'assignment_proof'=>$c['row']['proof'],'auth_id'=>$auth['id'],'auth_revision'=>$auth['revision'],'auth_sha256'=>$c['auth']['row']['document_sha256'],'auth_proof'=>$c['auth']['row']['proof'],'auth_ciphertext_sha256'=>$auth['ciphertext_sha256'],'stack_identity'=>$assignment['stack_identity'],'membership_id'=>'','membership_seq'=>0,'membership_sha256'=>''];
    return (new StackCpAccessBinding($b))->data;
}
/** Select across every client/assignment in the profile. A later failed or unfinished read wins. */
function provider_stackcp_access_latest(string $profile,int $package,string $identity,string $operation):?array {
    provider_stackcp_guard();provider_stackcp_hex($profile);provider_stackcp_number($package);StackCpAccessBinding::identity($identity);
    if (!in_array($operation,[StackCpAccessBinding::MEMBERSHIP,StackCpAccessBinding::GRANTS],true)) throw new InvalidArgumentException('Choose a supported StackCP observation.');
    $condition=$operation===StackCpAccessBinding::MEMBERSHIP?'r.package_id=?':'BINARY r.stack_identity=?';
    return one('SELECT r.* FROM '.table('provider_stackcp_reads').' r JOIN '.table('provider_refreshes')." f ON f.id=r.id WHERE BINARY r.profile_id=? AND BINARY r.operation=? AND $condition ORDER BY f.seq DESC LIMIT 1",[$profile,$operation,$operation===StackCpAccessBinding::MEMBERSHIP?$package:$identity]);
}
function provider_stackcp_access_saved(array $read):array {
    $start=provider_stackcp_access_row('started',$read);$row=one('SELECT * FROM '.table('provider_stackcp_observations').' WHERE id=?',[$read['id']]);$finish=$row?provider_stackcp_access_row('finished',$row):null;if ($finish) provider_stackcp_access_finish_check($start,$finish);
    return ['start'=>$start,'finish'=>$finish,'row'=>$row,'binding'=>new StackCpAccessBinding($start['binding'])];
}
function provider_stackcp_access_recent(array $saved):bool {return $saved['finish']!==null&&$saved['finish']['state']==='complete'&&(int)$saved['start']['common']['started_epoch']>=time()-900&&(int)$saved['start']['common']['started_epoch']<=time()+30&&$saved['finish']['observed_epoch']<=time()+30;}
function provider_stackcp_access_binding(string $profile,int $package,string $operation):StackCpAccessBinding {
    $b=provider_stackcp_access_base($profile,$package);
    if ($operation===StackCpAccessBinding::MEMBERSHIP) return new StackCpAccessBinding($b);
    if ($operation!==StackCpAccessBinding::GRANTS) throw new InvalidArgumentException('Choose a supported StackCP observation.');
    $r=provider_stackcp_access_latest($profile,$package,$b['stack_identity'],StackCpAccessBinding::MEMBERSHIP);$saved=$r?provider_stackcp_access_saved($r):null;
    if (!$saved||!provider_stackcp_access_recent($saved)||$saved['binding']->data!==$b||!in_array($b['stack_identity'],$saved['finish']['values'],true)) throw new ProviderStackCpAccessUnavailable('Refresh package membership and review this exact selected Stack user first.');
    $b['operation']=$operation;$b['membership_id']=$r['id'];$b['membership_seq']=(int)$saved['start']['common']['seq'];$b['membership_sha256']=$saved['row']['document_sha256'];return new StackCpAccessBinding($b);
}
/** Shared executor revalidates before credentials/transport and after response; no auth-client key is opened. */
function provider_stackcp_access_authority(StackCpAccessBinding $expected):void {
    $expected=$expected->validated();staff_locked(function()use($expected):void {
        provider_settings_manager();if (!provider_stackcp_access_ready()) throw new RuntimeException('Prepare StackCP access observations first.');$d=$expected->data;$current=provider_stackcp_access_binding($d['profile_id'],$d['package_id'],$d['operation']);
        if (!hash_equals($expected->sha256,$current->sha256)) throw new RuntimeException('StackCP access authority changed. Reload and review the owner form.');
    });
}
function provider_stackcp_access_started(array $started,StackCpAccessBinding $binding):void {
    provider_stackcp_guard(true);$binding=$binding->validated();$f=provider_stackcp_access_common_row($started['id'],false);if (!$f) throw new LogicException('Missing StackCP common intent.');
    foreach (['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key) if (!array_key_exists($key,$started)||(string)$started[$key]!== (string)$f[$key]) throw new LogicException('StackCP common intent changed.');
    $head=provider_stackcp_access_checkpoint();$d=['version'=>1,'kind'=>'started','id'=>$f['id'],'ordinal'=>$head['started']+1,'previous_sha256'=>$head['started_sha256'],'common'=>provider_stackcp_access_common($f),'binding'=>$binding->data];provider_stackcp_access_append('started',$d);
}
/** Exclusive typed recorder; common outcome and both audits commit under the existing inventory transaction. */
function provider_stackcp_access_record(array $started,?StackCpAccessObservation $snapshot,string $fingerprint):void {
    provider_stackcp_guard(true);$read=one('SELECT * FROM '.table('provider_stackcp_reads').' WHERE id=?',[$started['id']]);$f=provider_stackcp_access_common_row($started['id'],true);if (!$read||!$f) throw new LogicException('Missing StackCP observation intent or result.');$start=provider_stackcp_access_row('started',$read);$binding=new StackCpAccessBinding($start['binding']);
    if ($f['state']==='complete') {
        if (!$snapshot) throw new LogicException('Only a typed complete StackCP observation can persist.');$snapshot=$snapshot->validated();if ($snapshot->state!=='complete'||!hash_equals($binding->sha256,$snapshot->binding->sha256)||$f['scope']!==$snapshot->scope||$fingerprint!==$binding->data['key_fingerprint']||$f['key_fingerprint']!==$fingerprint) throw new LogicException('StackCP result does not match its exact signed intent.');
    } elseif ($snapshot!==null) throw new LogicException('Unavailable StackCP results must not retain provider values.');
    $head=provider_stackcp_access_checkpoint();$d=['version'=>1,'kind'=>'finished','id'=>$f['id'],'ordinal'=>$head['finished']+1,'previous_sha256'=>$head['finished_sha256'],'common'=>provider_stackcp_access_common($f,true),'read_sha256'=>$read['document_sha256'],'state'=>$f['state'],'reason'=>$f['reason'],'observed_epoch'=>$snapshot?->observedAt??0,'values'=>$snapshot?->values];provider_stackcp_access_finish_check($start,$d);provider_stackcp_access_append('finished',$d);
}
function provider_stackcp_access_refresh(string $profile,int $package,string $operation,string $expectedReview,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if (db()->inTransaction()) throw new LogicException('StackCP observations cannot run inside a transaction.');
    $binding=staff_locked(function()use($profile,$package,$operation,$expectedReview,$password):StackCpAccessBinding {
        provider_settings_owner($password);provider_stackcp_hex($expectedReview,64);if (!provider_stackcp_access_ready()) throw new RuntimeException('Prepare StackCP access observations first.');$b=provider_stackcp_access_binding($profile,$package,$operation);if (!hash_equals($b->sha256,$expectedReview)) throw new RuntimeException('StackCP access authority changed. Reload and review the owner form.');return $b;
    });
    return provider_inventory_refresh_attempt($profile,$binding->data['profile_revision'],$password,$resolver,$transport,$operation,stackcpAccessContext:$binding);
}
/** Owner-only finite projection; ciphertext, digests, raw documents and sibling identities stay private. */
function provider_stackcp_access_projection(string $profile,int $package):array {
    provider_stackcp_guard();provider_settings_manager();provider_stackcp_hex($profile);provider_stackcp_number($package);
    $out=['prepared'=>provider_stackcp_access_ready(),'profile_id'=>$profile,'package_id'=>$package,'status'=>'unprepared','login_ready'=>false,'remote_scope_restricted'=>false,'membership'=>null,'grants'=>null,'reviews'=>[]];if (!$out['prepared']) return $out;
    try {$base=provider_stackcp_access_base($profile,$package);}catch (ProviderStackCpAccessUnavailable $e) {$out['status']='assignment_or_source_unavailable';return $out;}
    $out['stack_identity']=$base['stack_identity'];$out['status']='not_observed';$memberBinding=new StackCpAccessBinding($base);$out['reviews'][StackCpAccessBinding::MEMBERSHIP]=$memberBinding->sha256;
    try {$grantBinding=provider_stackcp_access_binding($profile,$package,StackCpAccessBinding::GRANTS);$out['reviews'][StackCpAccessBinding::GRANTS]=$grantBinding->sha256;}catch (ProviderStackCpAccessUnavailable) {$grantBinding=null;}
    foreach (['membership'=>StackCpAccessBinding::MEMBERSHIP,'grants'=>StackCpAccessBinding::GRANTS] as $key=>$operation) {
        $r=provider_stackcp_access_latest($profile,$package,$base['stack_identity'],$operation);if (!$r) continue;$saved=provider_stackcp_access_saved($r);$finish=$saved['finish'];$current=$key==='membership'?$memberBinding:$grantBinding;
        $same=$current!==null&&hash_equals($current->sha256,$saved['binding']->sha256);$state=$finish['state']??'unfinished';$currentSaved=$same&&provider_stackcp_access_recent($saved);$match=false;
        if ($currentSaved) {$observation=new StackCpAccessObservation($saved['binding'],$finish['observed_epoch'],'complete','',$finish['values']);$match=$observation->selectedMatch();}
        // For a whole-user grants receipt owned by another package/client, expose only the stale state.
        $out[$key]=['id'=>$r['id'],'state'=>$same?$state:'context_changed','reason'=>$same?($finish['reason']??''):'','observed_epoch'=>$same?($finish['observed_epoch']??0):0,'current'=>$currentSaved,'matches'=>$match,'empty'=>$same&&$finish!==null&&$state==='complete'&&$finish['values']===[],'count'=>$same&&$finish!==null&&$state==='complete'?count($finish['values']):null];
    }
    if (($out['membership']['current']??false)&&($out['membership']['matches']??false)&&($out['grants']['current']??false)&&($out['grants']['matches']??false)) $out['status']='observed_match';
    elseif ($out['membership']!==null||$out['grants']!==null) $out['status']='not_verified';return $out;
}
function provider_stackcp_access_status(string $profile,int $package):array {return staff_locked(fn()=>staff_transaction(fn()=>provider_stackcp_access_projection($profile,$package)));}
