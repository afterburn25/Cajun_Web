<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/stackcp_auth_storage.php';

function provider_stackcp_auth_prepare(#[SensitiveParameter] string $password): void {provider_stackcp_prepare_family('auth',$password);}
function provider_stackcp_actor_current(int $id,string $version): bool {
    $user=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$profile=$user?staff_profile($user):null;
    return $profile!==null && $profile['role']==='owner' && $profile['password_hash']!=='' && hash_equals($version,$profile['auth_version']);
}
/** Internal under-lock context: the injected boundary is trusted code only, never a request parameter. */
function provider_stackcp_auth_context(string $profileId,ProviderStackCpAuthVault $vault): array {
    provider_stackcp_guard();$actor=provider_settings_manager();provider_stackcp_hex($profileId);
    if (!provider_stackcp_ready('auth',$vault)) return ['prepared'=>false,'status'=>'unprepared','login_ready'=>false];
    $p=provider_settings_load($profileId);$row=provider_stackcp_latest('auth',$profileId);$d=$row?provider_stackcp_row('auth',$row,$vault):null;
    $review=['profile_id'=>$profileId,'profile_revision'=>(int)$p['revision'],'profile_credentials_sha256'=>hash('sha256',$p['credentials']),'profile_enabled'=>(int)$p['enabled'],'auth_id'=>$d['id']??'','auth_sha256'=>$row['document_sha256']??'','actor_id'=>(int)$actor['id'],'actor_auth_version'=>$actor['auth_version']];
    $status=$d===null?'not_configured':(!$d['enabled']?'disabled':'configured');
    if ($d!==null && $d['enabled'] && ($d['profile_revision']!==(int)$p['revision'] || !hash_equals($d['profile_credentials_sha256'],hash('sha256',$p['credentials'])) || (int)$p['enabled']!==1 || $p['credentials']==='' || !provider_stackcp_actor_current($d['actor_id'],$d['actor_auth_version']))) $status='stale';
    return ['prepared'=>true,'status'=>$status,'login_ready'=>false,'review_sha256'=>provider_stackcp_proof($vault,'auth-review',provider_stackcp_json($review)),'profile'=>$p,'row'=>$row,'document'=>$d,'actor'=>$actor];
}
function provider_stackcp_auth_safe(array $context): array {
    $out=['prepared'=>$context['prepared'],'status'=>$context['status'],'login_ready'=>false];
    if (!$context['prepared']) return $out;
    $d=$context['document'];$out+=['profile_id'=>$context['profile']['id'],'profile_revision'=>(int)$context['profile']['revision'],'auth_id'=>$d['id']??'','revision'=>$d['revision']??0,'recorded_epoch'=>$d['recorded_epoch']??null,'review_sha256'=>$context['review_sha256']];return $out;
}
/** Ordinary status reads HMAC evidence only. It never decrypts either provider key. */
function provider_stackcp_auth_status(string $profileId): array {
    return staff_locked(fn()=>staff_transaction(fn()=>provider_stackcp_auth_safe(provider_stackcp_auth_context($profileId,provider_stackcp_vault()))));
}
/** Enable/rotation requires fresh key entry. Disable appends an empty-secret version; history is retained. */
function provider_stackcp_auth_change(string $profileId,string $expectedReview,bool $enabled,#[SensitiveParameter] string $secret,#[SensitiveParameter] string $password): array {
    return staff_locked(fn()=>staff_transaction(function()use($profileId,$expectedReview,$enabled,$secret,$password):array {
        $actor=provider_settings_owner($password);provider_stackcp_hex($expectedReview,64);$vault=provider_stackcp_vault();$c=provider_stackcp_auth_context($profileId,$vault);
        if (!$c['prepared']) throw new RuntimeException('Prepare StackCP authentication storage first.');
        if (!hash_equals($c['review_sha256'],$expectedReview)) throw new RuntimeException('StackCP authentication or account authority changed. Reload the owner form.');
        $old=$c['document'];$p=$c['profile'];
        if ($enabled) {
            if ((int)$p['enabled']!==1 || $p['credentials']==='') throw new RuntimeException('Enable the saved 20i profile before configuring StackCP authentication.');
            ProviderStackCpAuthVault::keyValue($secret);
        } elseif ($old===null || $secret!=='') throw new InvalidArgumentException('Choose an existing configuration to disable without entering a key.');
        $revision=($old['revision']??0)+1;$id=bin2hex(random_bytes(16));$cipher=$enabled?$vault->seal(ProviderStackCpAuthVault::context(config()['db']['prefix'],$profileId,$id,$revision),$secret):'';
        $d=['version'=>1,'kind'=>'auth','id'=>$id,'ordinal'=>provider_stackcp_counter('auth')+1,'scope_id'=>$profileId,'profile_id'=>$profileId,'revision'=>$revision,'actor_id'=>(int)$actor['id'],'actor_auth_version'=>$actor['auth_version'],'recorded_epoch'=>max(time(),$old['recorded_epoch']??0),'previous_sha256'=>$c['row']['document_sha256']??str_repeat('0',64),'profile_revision'=>(int)$p['revision'],'profile_credentials_sha256'=>hash('sha256',$p['credentials']),'enabled'=>$enabled,'ciphertext_sha256'=>hash('sha256',$cipher)];
        provider_stackcp_append('auth',$d,$cipher,$vault);return provider_stackcp_auth_safe(provider_stackcp_auth_context($profileId,$vault));
    }));
}
/** Owner-only bounded metadata history, with no ciphertext, secret-derived digest or raw document projection. */
function provider_stackcp_auth_history(string $profileId,int $before=0): array {
    return staff_locked(fn()=>staff_transaction(function()use($profileId,$before):array {
        provider_settings_manager();provider_stackcp_hex($profileId);$vault=provider_stackcp_vault();if (!provider_stackcp_ready('auth',$vault)) return [];
        $rows=all('SELECT * FROM '.table('provider_stackcp_auth').' WHERE profile_id=?'.($before>0?' AND ordinal<?':'').' ORDER BY ordinal DESC LIMIT 21',$before>0?[$profileId,$before]:[$profileId]);$out=[];
        foreach ($rows as $r) {$d=provider_stackcp_row('auth',$r,$vault);$out[]=['id'=>$d['id'],'ordinal'=>$d['ordinal'],'revision'=>$d['revision'],'state'=>$d['enabled']?'configured_unverified':'disabled','actor_id'=>$d['actor_id'],'recorded_epoch'=>$d['recorded_epoch']];}return $out;
    }));
}
