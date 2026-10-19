<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once __DIR__.'/client_stackcp_storage.php';

/** Reject provenance before studio_client/admin helpers can normalize session state. */
function provider_client_stackcp_actor(?int $clientId=null,#[SensitiveParameter] ?string $password=null):array {
    provider_stackcp_guard();
    if (array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||array_key_exists('support',$_GET)||support_current()!==null) throw new RuntimeException('Sign in directly to manage control panel access.');
    if ($clientId!==null&&array_key_exists('uid',$_SESSION)) throw new RuntimeException('Sign in directly to your client account.');
    if ($clientId===null) {
        unset($GLOBALS['cwp_settings']);$owner=$password===null?provider_settings_manager():provider_settings_owner($password);
        return provider_client_stackcp_principal(['kind'=>'owner','id'=>(int)$owner['id'],'auth_version'=>$owner['auth_version']]);
    }
    provider_stackcp_launch_number($clientId);$client=studio_client();
    if (!$client||(int)$client['id']!==$clientId||(int)$client['active']!==1) throw new RuntimeException('Sign in to your own client account.');
    if ($password!==null&&!password_verify($password,$client['password_hash'])) throw new InvalidArgumentException('Your current password is not correct.');
    return provider_client_stackcp_principal(['kind'=>'client','id'=>$clientId,'auth_version'=>$client['auth_version']]);
}
function provider_client_stackcp_prepare(#[SensitiveParameter] string $password):void {
    if (db()->inTransaction()) throw new LogicException('Client StackCP preparation must own its transaction.');
    staff_locked(function()use($password):void {
        $actor=provider_client_stackcp_actor(null,$password);if (provider_client_stackcp_ready()) return;
        if (!provider_stackcp_access_ready()||!studio_ready()) throw new RuntimeException('Prepare hosting clients and StackCP access observations first.');
        foreach (['settings','audit','provider_profiles','users','studio_clients'] as $name) if (strtolower(one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])['ENGINE']??'')!=='innodb') throw new RuntimeException('Client StackCP requires transactional prerequisites.');
        if (!provider_client_stackcp_exists()) foreach (provider_client_stackcp_schema(config()['db']['prefix']) as $sql) db()->exec($sql);
        provider_client_stackcp_validate_schema();
        staff_transaction(function()use($actor):void {
            if (provider_client_stackcp_events()!==null) throw new RuntimeException('Client StackCP preparation changed.');
            $p=['version'=>1,'actor'=>$actor,'recorded_epoch'=>time()];$raw=provider_client_stackcp_json($p);$proof=provider_client_stackcp_hmac('prepared',$raw);
            save_setting('provider_client_stackcp_schema_version','1');save_setting('provider_client_stackcp_prepared',$raw);save_setting('provider_client_stackcp_prepared_proof',$proof);provider_client_stackcp_save_checkpoint(0,0,0,str_repeat('0',64));
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['id'],'provider.client_stackcp_prepared','Client StackCP storage ready '.$proof,gmdate('Y-m-d H:i:s',$p['recorded_epoch'])]);
            if (!provider_client_stackcp_ready()) throw new LogicException('Client StackCP preparation did not complete.');
        });
    });
}
/** Static local authority, independent of the ambient owner session and observation freshness.
 * Only the original source triplet/membership receipts are historical. Every account,
 * key, assignment, link and authorizing owner's current identity remains mandatory.
 */
function provider_client_stackcp_current(array $permission,?array $events=null):bool {
    provider_stackcp_guard();$permission=provider_client_stackcp_document($permission);
    if ($permission['kind']!=='permission') throw new InvalidArgumentException('Choose a client StackCP permission.');
    $events??=provider_client_stackcp_events();if ($events===null) return false;
    $states=provider_client_stackcp_project($events);$scope=$permission['profile_id'].':'.$permission['package_id'];$latest=$states['permissions'][$scope]??null;
    if (!$latest||$latest!==$permission||!$permission['payload']['enabled']) return false;
    $owner=$permission['actor'];if (!provider_stackcp_actor_current($owner['id'],$owner['auth_version'])) return false;
    if (!provider_stackcp_ready('assignment')) throw new RuntimeException('StackCP assignment history is unavailable.');
    $b=provider_client_stackcp_resource($permission);$local=provider_stackcp_local_binding($b['profile_id'],$b['package_id']);if ($local===null) return false;
    foreach ($local as $key=>$value) { $field=$key==='profile_credentials_sha256'?'credential_sha256':$key;if (!array_key_exists($field,$b)||$b[$field]!==$value) return false; }
    $vault=provider_stackcp_vault();$assignment=provider_stackcp_latest('assignment',$scope);$auth=provider_stackcp_latest('auth',$b['profile_id']);if (!$assignment||!$auth) throw new RuntimeException('StackCP authority history is missing.');
    $a=provider_stackcp_row('assignment',$assignment,$vault);$k=provider_stackcp_row('auth',$auth,$vault);
    if ($a['id']!==$b['assignment_id']||$a['revision']!==$b['assignment_revision']||$assignment['document_sha256']!==$b['assignment_sha256']||$assignment['proof']!==$b['assignment_proof']||$a['state']!=='unverified'||$a['stack_identity']!==$b['stack_identity']||!provider_stackcp_actor_current($a['actor_id'],$a['actor_auth_version'])) return false;
    if (!$k['enabled']||$k['id']!==$b['auth_id']||$k['revision']!==$b['auth_revision']||$auth['document_sha256']!==$b['auth_sha256']||$auth['proof']!==$b['auth_proof']||$k['ciphertext_sha256']!==$b['auth_ciphertext_sha256']||$a['auth_id']!==$k['id']||$a['auth_sha256']!==$auth['document_sha256']||$k['profile_revision']!==$b['profile_revision']||$k['profile_credentials_sha256']!==$b['credential_sha256']||!provider_stackcp_actor_current($k['actor_id'],$k['actor_auth_version'])) return false;
    return true;
}
/** Local owner review. Only enablement depends on fresh independently saved access reads. */
function provider_client_stackcp_permission_candidate(string $profile,int $package,array $actor,array $events):array {
    provider_stackcp_guard(true);provider_stackcp_launch_hex($profile);provider_stackcp_launch_number($package);
    $states=provider_client_stackcp_project($events);$old=$states['permissions'][$profile.':'.$package]??null;$resource=null;$reason='refresh_required';
    try {$resource=provider_stackcp_launch_resource($profile,$package);$reason='ready';} catch (ProviderStackCpAccessUnavailable) {}
    $base=['version'=>1,'profile_id'=>$profile,'package_id'=>$package,'actor'=>$actor,'previous_permission_id'=>$old['id']??'','previous_permission_sha256'=>$old===null?str_repeat('0',64):hash('sha256',provider_client_stackcp_json($old))];
    $enable=$resource===null?'':provider_client_stackcp_hmac('permission-enable',provider_client_stackcp_json($base+['resource'=>$resource]));
    $revoke=($old['payload']['enabled']??false)?provider_client_stackcp_hmac('permission-revoke',provider_client_stackcp_json($base)):'';
    $display=$resource??($old['payload']['resource']??null);$b=$display['binding']??null;$client=$b?one('SELECT id,name FROM '.table('studio_clients').' WHERE id=?',[$b['client_id']]):null;
    if ($b&&!$client) throw new RuntimeException('The original client account needs recovery.');
    $packageName='Hosting package '.$package;
    if ($b) {$source=provider_stackcp_access_package_source($b['source_id'],$package);$packageName=$source['package']['name'];}
    $enabled=($old['payload']['enabled']??false);$current=$old!==null&&provider_client_stackcp_current($old,$events);
    $view=['ready'=>true,'available'=>$enable!==''||$revoke!=='','reason'=>$reason,'profile_id'=>$profile,'package_id'=>$package,'client_name'=>$client['name']??'','package_name'=>$packageName,'stack_identity'=>$b['stack_identity']??'','enabled'=>$enabled,'can_enable'=>$enable!=='','stale'=>$enabled&&!$current,'permission_id'=>$old['id']??'','review_proof'=>$enabled?$revoke:$enable,'enable_review'=>$enable,'revoke_review'=>$revoke];
    return ['view'=>$view,'resource'=>$resource,'old'=>$old];
}
function provider_client_stackcp_permission_review(string $profile,int $package):array {
    if (db()->inTransaction()) throw new LogicException('Client StackCP review must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($profile,$package):array {
        $actor=provider_client_stackcp_actor();$events=provider_client_stackcp_events();
        if ($events===null) return ['ready'=>false,'available'=>false,'reason'=>'unprepared','profile_id'=>$profile,'package_id'=>$package,'review_proof'=>'','enable_review'=>'','revoke_review'=>''];
        return provider_client_stackcp_permission_candidate($profile,$package,$actor,$events)['view'];
    }));
}
function provider_client_stackcp_permission_change(string $profile,int $package,string $reviewProof,bool $enabled,bool $confirmed,#[SensitiveParameter] string $password):array {
    return staff_locked(fn()=>staff_transaction(function()use($profile,$package,$reviewProof,$enabled,$confirmed,$password):array {
        $actor=provider_client_stackcp_actor(null,$password);if (!$confirmed) throw new InvalidArgumentException('Confirm this whole-user control panel access decision.');provider_stackcp_launch_hex($reviewProof,64);
        $events=provider_client_stackcp_events();if ($events===null) throw new RuntimeException('Prepare client control panel access first.');
        $c=provider_client_stackcp_permission_candidate($profile,$package,$actor,$events);$expected=$c['view'][$enabled?'enable_review':'revoke_review'];
        if ($expected===''||!hash_equals($expected,$reviewProof)) throw new RuntimeException('Control panel access changed. Reload and review the current permission.');
        $old=$c['old'];$resource=$enabled?$c['resource']:$old['payload']['resource'];
        $event=provider_client_stackcp_append('permission','permission',['revision'=>($old['payload']['revision']??0)+1,'previous_permission_id'=>$old['id']??'','previous_permission_sha256'=>$old===null?str_repeat('0',64):hash('sha256',provider_client_stackcp_json($old)),'enabled'=>$enabled,'resource'=>$resource,'confirmed'=>true],['id'=>bin2hex(random_bytes(16)),'actor'=>$actor]);
        return ['permission_id'=>$event['id'],'enabled'=>$enabled,'profile_id'=>$profile,'package_id'=>$package];
    }));
}
function provider_client_stackcp_owner_snapshot(int $page=1):array {
    if ($page<1||$page>500) throw new InvalidArgumentException('Choose a valid control panel access page.');
    if (db()->inTransaction()) throw new LogicException('Client StackCP snapshot must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($page):array {
        // A fresh owner site must be able to see the setup explanation without enabling settings.
        if (array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||array_key_exists('support',$_GET)||support_current()!==null) throw new RuntimeException('Leave client support before managing access.');
        $owner=provider_settings_manager(false);$actor=provider_client_stackcp_principal(['kind'=>'owner','id'=>(int)$owner['id'],'auth_version'=>$owner['auth_version']]);$events=provider_client_stackcp_events();
        $out=['ready'=>$events!==null,'reason'=>$events===null?'unprepared':'ready','items'=>[],'page'=>$page,'has_more'=>false];if ($events===null) return $out;
        $scopes=[];foreach (provider_client_stackcp_project($events)['permissions'] as $permission) $scopes[$permission['profile_id'].':'.$permission['package_id']]=[$permission['profile_id'],$permission['package_id']];
        foreach (all('SELECT profile_id,package_id FROM '.table('provider_package_links').' ORDER BY profile_id,package_id') as $l) $scopes[$l['profile_id'].':'.$l['package_id']]=[$l['profile_id'],(int)$l['package_id']];
        ksort($scopes);$rows=array_slice(array_values($scopes),($page-1)*20,21);$out['has_more']=count($rows)>20;
        foreach (array_slice($rows,0,20) as [$profile,$package]) $out['items'][]=provider_client_stackcp_permission_candidate($profile,$package,$actor,$events)['view'];return $out;
    }));
}
function provider_client_stackcp_client_snapshot(int $clientId,int $page=1,?string $linkId=null):array {
    if ($page<1||$page>500) throw new InvalidArgumentException('Choose a valid control panel access page.');if ($linkId!==null) provider_stackcp_launch_hex($linkId);
    if (db()->inTransaction()) throw new LogicException('Client StackCP snapshot must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$page,$linkId):array {
        provider_client_stackcp_actor($clientId);$events=provider_client_stackcp_events();$out=['ready'=>$events!==null,'reason'=>$events===null?'unprepared':'ready','items'=>[],'page'=>$page,'has_more'=>false];if ($events===null) return $out;
        $eligible=[];foreach (provider_client_stackcp_project($events)['permissions'] as $permission) {
            if ($permission['client_id']!==$clientId) continue;$b=provider_client_stackcp_resource($permission);if ($linkId!==null&&$b['link_id']!==$linkId) continue;
            if (!provider_client_stackcp_current($permission,$events)) continue;$source=provider_stackcp_access_package_source($b['source_id'],$b['package_id']);
            $eligible[]=['profile_id'=>$b['profile_id'],'package_id'=>$b['package_id'],'package_name'=>$source['package']['name'],'link_id'=>$b['link_id'],'permission_id'=>$permission['id']];
        }
        $rows=array_slice($eligible,($page-1)*20,21);$out['has_more']=count($rows)>20;$out['items']=array_slice($rows,0,20);return $out;
    }));
}
