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
    return ProviderStackCpReadProjection::accessBase($profile,$package);
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
    return ProviderStackCpReadProjection::accessBinding($profile,$package,$operation);
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
    return ProviderStackCpReadProjection::accessProjection($profile,$package);
}
function provider_stackcp_access_status(string $profile,int $package):array {return staff_locked(fn()=>staff_transaction(fn()=>provider_stackcp_access_projection($profile,$package)));}
