<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/service_observations.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_auth.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_assignment_storage.php';

/** Reconcile later link/relink/unlink events with exact actor/resource audits before selecting current ownership. */
function provider_stackcp_link_coverage(string $profileId,int $packageId): void {
    $e=table('provider_package_link_events');$a=table('audit');
    if (one("SELECT id FROM $e WHERE BINARY profile_id=? AND package_id=? AND BINARY event NOT IN ('link','relink','unlink') LIMIT 1",[$profileId,$packageId])) throw new RuntimeException('StackCP package assignment history needs recovery.');
    foreach (['link'=>['provider.service_linked','Profile '.$profileId.' package '.$packageId.' client %'],'relink'=>['provider.service_relinked','Link % profile '.$profileId.' package '.$packageId.' revision %'],'unlink'=>['provider.service_unlinked','Link % profile '.$profileId.' package '.$packageId]] as $kind=>[$action,$pattern]) {
        $event='BINARY e.profile_id=? AND e.package_id=? AND BINARY e.event=?';$audit='BINARY a.action=? AND BINARY a.detail LIKE ?';
        if (one("SELECT e.id FROM $e e WHERE $event AND (SELECT COUNT(*) FROM $e x WHERE BINARY x.profile_id=? AND x.package_id=? AND BINARY x.event=? AND x.actor_id=e.actor_id)<>(SELECT COUNT(*) FROM $a a WHERE $audit AND a.user_id=e.actor_id) LIMIT 1",[$profileId,$packageId,$kind,$profileId,$packageId,$kind,$action,$pattern]) || one("SELECT a.id FROM $a a WHERE $audit AND (SELECT COUNT(*) FROM $a x WHERE BINARY x.action=? AND BINARY x.detail LIKE ? AND x.user_id=a.user_id)<>(SELECT COUNT(*) FROM $e e WHERE $event AND e.actor_id=a.user_id) LIMIT 1",[$action,$pattern,$action,$pattern,$profileId,$packageId,$kind])) throw new RuntimeException('StackCP package assignment audits need recovery.');
    }
}
/** Local explicit assignment only. It makes no claim about remote Stack-user membership or grants. */
function provider_stackcp_local_binding(string $profileId,int $packageId): ?array {
    provider_stackcp_guard();provider_stackcp_hex($profileId);provider_stackcp_number($packageId);if (!provider_inventory_ready()) throw new RuntimeException('Hosting inventory needs preparation or recovery.');
    provider_stackcp_link_coverage($profileId,$packageId);$l=one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?',[$profileId,$packageId]);if (!$l) return null;
    $p=provider_settings_load($profileId);$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$l['client_id']]);
    // Ordinary revocation/staleness is distinct from damaged retained source history.
    if (!$client || (int)$client['active']!==1 || (int)$p['enabled']!==1 || $p['credentials']==='' || (int)$l['profile_revision']!==(int)$p['revision']) return null;
    $source=service_observations_binding($l['id']);$event=one('SELECT * FROM '.table('provider_package_link_events').' WHERE id=?',[$source['event']['id']]);$audit=one('SELECT * FROM '.table('audit').' WHERE id=?',[$source['event']['audit_id']]);
    if (!$event||!$audit) throw new RuntimeException('StackCP package assignment history needs recovery.');
    return ['link_id'=>$l['id'],'package_id'=>$packageId,'client_id'=>(int)$l['client_id'],'client_auth_version'=>$client['auth_version'],'profile_revision'=>(int)$p['revision'],'profile_credentials_sha256'=>hash('sha256',$p['credentials']),'key_fingerprint'=>$l['key_fingerprint'],'link_sha256'=>provider_stackcp_record_hash($l),'event_id'=>(int)$event['id'],'event_sha256'=>provider_stackcp_record_hash($event),'audit_id'=>(int)$audit['id'],'audit_sha256'=>provider_stackcp_record_hash($audit)];
}
function provider_stackcp_assignment_context(string $profileId,int $packageId,ProviderStackCpAuthVault $vault): array {
    return ProviderStackCpReadProjection::assignment($profileId,$packageId,$vault);
}
function provider_stackcp_assignment_safe(array $c): array {
    $out=['prepared'=>$c['prepared'],'status'=>$c['status'],'login_ready'=>false,'provider_verified'=>false];if (!$c['prepared']) return $out;$d=$c['document'];
    return $out+['profile_id'=>$c['profile_id'],'package_id'=>$c['package_id'],'assignment_id'=>$d['id']??'','revision'=>$d['revision']??0,'stack_identity'=>$d['stack_identity']??'','client_id'=>$d['client_id']??null,'current_link_id'=>$c['binding']['link_id']??'','recorded_epoch'=>$d['recorded_epoch']??null,'review_sha256'=>$c['review_sha256'],'can_save_draft'=>$c['binding']!==null && $c['auth']['status']==='configured'];
}
function provider_stackcp_assignment_status(string $profileId,int $packageId): array {
    return staff_locked(fn()=>staff_transaction(fn()=>provider_stackcp_assignment_safe(provider_stackcp_assignment_context($profileId,$packageId,provider_stackcp_vault()))));
}
function provider_stackcp_assignment_change(string $profileId,int $packageId,string $expectedReview,string $linkId,string $identity,bool $revoke,#[SensitiveParameter] string $password): array {
    return staff_locked(fn()=>staff_transaction(function()use($profileId,$packageId,$expectedReview,$linkId,$identity,$revoke,$password):array {
        $actor=provider_settings_owner($password);provider_stackcp_hex($expectedReview,64);$vault=provider_stackcp_vault();$c=provider_stackcp_assignment_context($profileId,$packageId,$vault);
        if (!$c['prepared']) throw new RuntimeException('Prepare StackCP assignment storage first.');
        if (!hash_equals($c['review_sha256'],$expectedReview)) throw new RuntimeException('StackCP assignment or account authority changed. Reload the owner form.');
        $old=$c['document'];
        if ($revoke) {
            if ($old===null || $linkId!==$old['link_id'] || $identity!==$old['stack_identity']) throw new InvalidArgumentException('Review the original StackCP draft before revoking it.');
            $binding=[];foreach (['link_id','package_id','client_id','client_auth_version','profile_revision','profile_credentials_sha256','key_fingerprint','link_sha256','event_id','event_sha256','audit_id','audit_sha256','auth_id','auth_sha256'] as $key) $binding[$key]=$old[$key];
        } else {
            provider_stackcp_identity($identity);
            if ($c['binding']===null || $c['auth']['status']!=='configured' || $linkId!==$c['binding']['link_id']) throw new RuntimeException('Review a current hosting assignment and separate StackCP authentication configuration first.');
            $binding=$c['binding']+['auth_id'=>$c['auth']['document']['id'],'auth_sha256'=>$c['auth']['row']['document_sha256']];
        }
        $base=['version'=>1,'kind'=>'assignment','id'=>bin2hex(random_bytes(16)),'ordinal'=>provider_stackcp_counter('assignment')+1,'scope_id'=>$profileId.':'.$packageId,'profile_id'=>$profileId,'revision'=>($old['revision']??0)+1,'actor_id'=>(int)$actor['id'],'actor_auth_version'=>$actor['auth_version'],'recorded_epoch'=>max(time(),$old['recorded_epoch']??0),'previous_sha256'=>$c['row']['document_sha256']??str_repeat('0',64)];
        $d=$base;foreach (['link_id','package_id','client_id','client_auth_version'] as $key) $d[$key]=$binding[$key];$d['state']=$revoke?'revoked':'unverified';$d['stack_identity']=$identity;
        foreach (['profile_revision','profile_credentials_sha256','key_fingerprint','link_sha256','event_id','event_sha256','audit_id','audit_sha256','auth_id','auth_sha256'] as $key) $d[$key]=$binding[$key];
        provider_stackcp_append('assignment',$d,'',$vault);return provider_stackcp_assignment_safe(provider_stackcp_assignment_context($profileId,$packageId,$vault));
    }));
}
function provider_stackcp_assignment_history(string $profileId,int $packageId,int $before=0): array {
    return ProviderStackCpReadProjection::assignmentHistory($profileId,$packageId,$before);
}
