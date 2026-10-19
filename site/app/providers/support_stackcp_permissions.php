<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once __DIR__.'/support_stackcp_storage.php';

/** Owner policy changes never adopt a client/support browsing principal. */
function provider_support_stackcp_owner(#[SensitiveParameter] ?string $password=null,bool $ready=true):array {
    provider_stackcp_guard();
    if (array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||array_key_exists('support',$_GET)||support_current()!==null) throw new RuntimeException('Leave client support before managing access.');
    unset($GLOBALS['cwp_settings']);$owner=$password===null?provider_settings_manager($ready):provider_settings_owner($password);
    return provider_support_stackcp_principal(['kind'=>'owner','id'=>(int)$owner['id'],'auth_version'=>$owner['auth_version']]);
}
/** Exact live support identity. Throw on failure so support_check's expiration writes roll back. */
function provider_support_stackcp_actor(string $supportId):array {
    provider_stackcp_guard(true);provider_stackcp_launch_hex($supportId);
    if (array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||(array_key_exists('support',$_GET)&&$_GET['support']!==$supportId)) throw new RuntimeException('Use the current staff support session.');
    $ctx=support_check($supportId,false);
    if ($ctx===null||!staff_can('support.stackcp',$ctx['actor'])) throw new RuntimeException('This staff support session cannot open hosting panels.');
    $s=$ctx['session'];$staff=$ctx['actor'];$client=$ctx['client'];
    if ($s['id']!==$supportId||(int)$s['actor_id']!==(int)$staff['id']||(int)$s['client_id']!==(int)$client['id']||$s['actor_version']!==$staff['auth_version']||$s['client_version']!==$client['auth_version']||$s['binding_hash']!==support_binding()) throw new RuntimeException('The staff support identity changed.');
    return provider_support_stackcp_principal(['kind'=>'support','id'=>(int)$staff['id'],'auth_version'=>$staff['auth_version'],'client_id'=>(int)$client['id'],'client_auth_version'=>$client['auth_version'],'support_id'=>$supportId,'binding_sha256'=>$s['binding_hash'],'expires_epoch'=>(int)$s['expires_at']]);
}
/** Target staff authorization is evaluated with its own current version, never the owner's cookie. */
function provider_support_stackcp_target(int $staffId):?array {
    provider_stackcp_guard();provider_stackcp_launch_number($staffId);if (!staff_ready()) return null;
    $user=one('SELECT * FROM '.table('users').' WHERE id=?',[$staffId]);
    if (!$user||($user['password_hash']??'')===''||preg_match('/\A[a-f0-9]{32}\z/',$user['auth_version']??'')!==1) return null;
    $profile=staff_profile($user);if (!$profile) return null;
    $context=new \CajunWebPro\Foundation\Access\AccessContext((string)$profile['id'],'staff',$profile['role'],true,$profile['auth_version'],$profile['auth_version'],json_decode($profile['permissions'],true));
    $policy=new \CajunWebPro\Foundation\Access\PermissionPolicy();
    return $policy->allows($context,'support.login')&&$policy->allows($context,'support.stackcp')?$profile:null;
}
function provider_support_stackcp_client_allowed(int $clientId):bool {
    provider_stackcp_guard();
    return support_ready()&&setting('support_policy_confirmed')==='1'&&(int)(one('SELECT allowed FROM '.table('support_clients').' WHERE client_id=?',[$clientId])['allowed']??0)===1;
}
function provider_support_stackcp_prepare(#[SensitiveParameter] string $password):void {
    if (db()->inTransaction()) throw new LogicException('Support StackCP preparation must own its transaction.');
    staff_locked(function()use($password):void {
        $actor=provider_support_stackcp_owner($password);if (provider_support_stackcp_ready()) return;
        if (!provider_stackcp_access_ready()||!support_ready()) throw new RuntimeException('Prepare client support and StackCP access observations first.');
        foreach (['settings','audit','provider_profiles','users','studio_clients'] as $name) if (strtolower(one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])['ENGINE']??'')!=='innodb') throw new RuntimeException('Support StackCP requires transactional prerequisites.');
        if (!provider_support_stackcp_exists()) foreach (provider_support_stackcp_schema(config()['db']['prefix']) as $sql) db()->exec($sql);
        provider_support_stackcp_validate_schema();
        staff_transaction(function()use($actor):void {
            if (provider_support_stackcp_events()!==null) throw new RuntimeException('Support StackCP preparation changed.');
            $p=['version'=>1,'actor'=>$actor,'recorded_epoch'=>time()];$raw=provider_support_stackcp_json($p);$proof=provider_support_stackcp_hmac('prepared',$raw);
            save_setting('provider_support_stackcp_schema_version','1');save_setting('provider_support_stackcp_prepared',$raw);save_setting('provider_support_stackcp_prepared_proof',$proof);provider_support_stackcp_save_checkpoint(0,0,0,str_repeat('0',64));
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['id'],'provider.support_stackcp_prepared','Support StackCP storage ready '.$proof,gmdate('Y-m-d H:i:s',$p['recorded_epoch'])]);
            if (!provider_support_stackcp_ready()) throw new LogicException('Support StackCP preparation did not complete.');
        });
    });
}
/** Static local authority, independent of the ambient owner session and observation freshness.
 * Only the original source triplet/membership receipts are historical. Every account,
 * key, assignment, link and authorizing owner's current identity remains mandatory.
 */
function provider_support_stackcp_current(array $permission,?array $events=null):bool {
    provider_stackcp_guard();$permission=provider_support_stackcp_document($permission);
    if ($permission['kind']!=='permission') throw new InvalidArgumentException('Choose a support StackCP permission.');
    $events??=provider_support_stackcp_events();if ($events===null) return false;
    $states=provider_support_stackcp_project($events);$scope=$permission['profile_id'].':'.$permission['package_id'].':'.$permission['staff_id'];$latest=$states['permissions'][$scope]??null;
    if (!$latest||$latest!==$permission||!$permission['payload']['enabled']) return false;
    $target=provider_support_stackcp_target($permission['staff_id']);
    if (!$target||$target['auth_version']!==$permission['payload']['resource']['staff']['auth_version']||!provider_support_stackcp_client_allowed($permission['client_id'])) return false;
    $owner=$permission['actor'];if (!provider_stackcp_actor_current($owner['id'],$owner['auth_version'])) return false;
    if (!provider_stackcp_ready('assignment')) throw new RuntimeException('StackCP assignment history is unavailable.');
    $b=provider_support_stackcp_resource($permission);$local=provider_stackcp_local_binding($b['profile_id'],$b['package_id']);if ($local===null) return false;
    foreach ($local as $key=>$value) { $field=$key==='profile_credentials_sha256'?'credential_sha256':$key;if (!array_key_exists($field,$b)||$b[$field]!==$value) return false; }
    $vault=provider_stackcp_vault();$assignment=provider_stackcp_latest('assignment',$b['profile_id'].':'.$b['package_id']);$auth=provider_stackcp_latest('auth',$b['profile_id']);if (!$assignment||!$auth) throw new RuntimeException('StackCP authority history is missing.');
    $a=provider_stackcp_row('assignment',$assignment,$vault);$k=provider_stackcp_row('auth',$auth,$vault);
    if ($a['id']!==$b['assignment_id']||$a['revision']!==$b['assignment_revision']||$assignment['document_sha256']!==$b['assignment_sha256']||$assignment['proof']!==$b['assignment_proof']||$a['state']!=='unverified'||$a['stack_identity']!==$b['stack_identity']||!provider_stackcp_actor_current($a['actor_id'],$a['actor_auth_version'])) return false;
    if (!$k['enabled']||$k['id']!==$b['auth_id']||$k['revision']!==$b['auth_revision']||$auth['document_sha256']!==$b['auth_sha256']||$auth['proof']!==$b['auth_proof']||$k['ciphertext_sha256']!==$b['auth_ciphertext_sha256']||$a['auth_id']!==$k['id']||$a['auth_sha256']!==$auth['document_sha256']||$k['profile_revision']!==$b['profile_revision']||$k['profile_credentials_sha256']!==$b['credential_sha256']||!provider_stackcp_actor_current($k['actor_id'],$k['actor_auth_version'])) return false;
    return true;
}
/** Local owner review. Enablement needs fresh saved reads; stale delegation can still be revoked. */
function provider_support_stackcp_permission_candidate(string $profile,int $package,int $staffId,array $actor,array $events):array {
    provider_stackcp_guard(true);provider_stackcp_launch_hex($profile);provider_stackcp_launch_number($package);provider_stackcp_launch_number($staffId);
    $states=provider_support_stackcp_project($events);$old=$states['permissions'][$profile.':'.$package.':'.$staffId]??null;$resource=null;$reason='staff_unavailable';
    $staff=one('SELECT id,name FROM '.table('users').' WHERE id=?',[$staffId]);if (!$staff) throw new InvalidArgumentException('Choose an existing staff account.');
    $target=provider_support_stackcp_target($staffId);
    if ($target) {
        $reason='refresh_required';
        try {
            $candidate=provider_stackcp_launch_resource($profile,$package);
            if (provider_support_stackcp_client_allowed($candidate['binding']['client_id'])) {$resource=$candidate+['staff'=>['id'=>$staffId,'auth_version'=>$target['auth_version']]];$reason='ready';}
            else $reason='client_support_unavailable';
        } catch (ProviderStackCpAccessUnavailable) {}
    }
    $base=['version'=>1,'profile_id'=>$profile,'package_id'=>$package,'staff_id'=>$staffId,'actor'=>$actor,'previous_permission_id'=>$old['id']??'','previous_permission_sha256'=>$old===null?str_repeat('0',64):hash('sha256',provider_support_stackcp_json($old))];
    $enable=$resource===null?'':provider_support_stackcp_hmac('permission-enable',provider_support_stackcp_json($base+['resource'=>$resource]));
    $revoke=($old['payload']['enabled']??false)?provider_support_stackcp_hmac('permission-revoke',provider_support_stackcp_json($base)):'';
    $display=$resource??($old['payload']['resource']??null);$b=$display['binding']??null;$client=$b?one('SELECT id,name FROM '.table('studio_clients').' WHERE id=?',[$b['client_id']]):null;
    if ($b&&!$client) throw new RuntimeException('The original client account needs recovery.');
    $packageName='Hosting package '.$package;if ($b) {$source=provider_stackcp_access_package_source($b['source_id'],$package);$packageName=$source['package']['name'];}
    $enabled=($old['payload']['enabled']??false);$current=$old!==null&&provider_support_stackcp_current($old,$events);
    $view=['ready'=>true,'available'=>$enable!==''||$revoke!=='','reason'=>$reason,'profile_id'=>$profile,'package_id'=>$package,'staff_id'=>$staffId,'staff_name'=>$staff['name'],'client_name'=>$client['name']??'','package_name'=>$packageName,'stack_identity'=>$b['stack_identity']??'','enabled'=>$enabled,'can_enable'=>$enable!=='','stale'=>$enabled&&!$current,'permission_id'=>$old['id']??'','review_proof'=>$enabled?$revoke:$enable,'enable_review'=>$enable,'revoke_review'=>$revoke];
    return ['view'=>$view,'resource'=>$resource,'old'=>$old];
}
function provider_support_stackcp_permission_review(string $profile,int $package,int $staffId):array {
    provider_stackcp_launch_hex($profile);provider_stackcp_launch_number($package);provider_stackcp_launch_number($staffId);
    if (db()->inTransaction()) throw new LogicException('Support StackCP review must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($profile,$package,$staffId):array {
        $actor=provider_support_stackcp_owner();$events=provider_support_stackcp_events();
        if ($events===null) return ['ready'=>false,'available'=>false,'reason'=>'unprepared','profile_id'=>$profile,'package_id'=>$package,'staff_id'=>$staffId,'review_proof'=>'','enable_review'=>'','revoke_review'=>''];
        return provider_support_stackcp_permission_candidate($profile,$package,$staffId,$actor,$events)['view'];
    }));
}
function provider_support_stackcp_permission_change(string $profile,int $package,int $staffId,string $reviewProof,bool $enabled,bool $confirmed,#[SensitiveParameter] string $password):array {
    if (db()->inTransaction()) throw new LogicException('Support StackCP permission change must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($profile,$package,$staffId,$reviewProof,$enabled,$confirmed,$password):array {
        $actor=provider_support_stackcp_owner($password);if (!$confirmed) throw new InvalidArgumentException('Confirm this whole-user control panel access decision.');provider_stackcp_launch_hex($reviewProof,64);
        $events=provider_support_stackcp_events();if ($events===null) throw new RuntimeException('Prepare staff control panel access first.');
        $c=provider_support_stackcp_permission_candidate($profile,$package,$staffId,$actor,$events);$expected=$c['view'][$enabled?'enable_review':'revoke_review'];
        if ($expected===''||!hash_equals($expected,$reviewProof)) throw new RuntimeException('Control panel access changed. Reload and review the current permission.');
        $old=$c['old'];$resource=$enabled?$c['resource']:$old['payload']['resource'];
        $event=provider_support_stackcp_append('permission','permission',['revision'=>($old['payload']['revision']??0)+1,'previous_permission_id'=>$old['id']??'','previous_permission_sha256'=>$old===null?str_repeat('0',64):hash('sha256',provider_support_stackcp_json($old)),'enabled'=>$enabled,'resource'=>$resource,'confirmed'=>true],['id'=>bin2hex(random_bytes(16)),'actor'=>$actor]);
        return ['permission_id'=>$event['id'],'enabled'=>$enabled,'profile_id'=>$profile,'package_id'=>$package,'staff_id'=>$staffId];
    }));
}
function provider_support_stackcp_owner_snapshot(int $page=1,?int $staffId=null):array {
    if ($page<1||$page>500) throw new InvalidArgumentException('Choose a valid control panel access page.');if ($staffId!==null) provider_stackcp_launch_number($staffId);
    if (db()->inTransaction()) throw new LogicException('Support StackCP snapshot must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($page,$staffId):array {
        $actor=provider_support_stackcp_owner(null,false);$events=provider_support_stackcp_events();$choices=[];
        if (staff_ready()) foreach (all('SELECT id,name FROM '.table('users').' ORDER BY name,id LIMIT 10001') as $row) $choices[]=['id'=>(int)$row['id'],'name'=>$row['name']];
        if (count($choices)>10000) throw new RuntimeException('The staff list requires review.');
        if ($staffId!==null&&!in_array($staffId,array_column($choices,'id'),true)) throw new InvalidArgumentException('Choose an existing staff account.');
        $selected=$staffId??($choices[0]['id']??null);
        $out=['ready'=>$events!==null,'reason'=>$events===null?'unprepared':($selected===null?'staff_unavailable':'ready'),'items'=>[],'page'=>$page,'has_more'=>false,'staff_choices'=>$choices,'selected_staff_id'=>$selected];if ($events===null||$selected===null) return $out;
        $scopes=[];foreach (provider_support_stackcp_project($events)['permissions'] as $permission) if ($permission['staff_id']===$selected) $scopes[$permission['profile_id'].':'.$permission['package_id']]=[$permission['profile_id'],$permission['package_id']];
        foreach (all('SELECT profile_id,package_id FROM '.table('provider_package_links').' ORDER BY profile_id,package_id') as $l) $scopes[$l['profile_id'].':'.$l['package_id']]=[$l['profile_id'],(int)$l['package_id']];
        ksort($scopes);$rows=array_slice(array_values($scopes),($page-1)*20,21);$out['has_more']=count($rows)>20;
        foreach (array_slice($rows,0,20) as [$profile,$package]) $out['items'][]=provider_support_stackcp_permission_candidate($profile,$package,$selected,$actor,$events)['view'];return $out;
    }));
}
function provider_support_stackcp_staff_snapshot(string $supportId,int $page=1,?string $linkId=null):array {
    if ($page<1||$page>500) throw new InvalidArgumentException('Choose a valid control panel access page.');if ($linkId!==null) provider_stackcp_launch_hex($linkId);
    if (db()->inTransaction()) throw new LogicException('Support StackCP snapshot must own its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($supportId,$page,$linkId):array {
        $actor=provider_support_stackcp_actor($supportId);$staff=one('SELECT id,name FROM '.table('users').' WHERE id=?',[$actor['id']]);$client=one('SELECT id,name FROM '.table('studio_clients').' WHERE id=?',[$actor['client_id']]);
        if (!$staff||!$client) throw new RuntimeException('The support account is unavailable.');
        $events=provider_support_stackcp_events();$out=['ready'=>$events!==null,'reason'=>$events===null?'unprepared':'ready','items'=>[],'page'=>$page,'has_more'=>false,'client_name'=>$client['name'],'staff_name'=>$staff['name']];if ($events===null) return $out;
        $eligible=[];foreach (provider_support_stackcp_project($events)['permissions'] as $permission) {
            if ($permission['client_id']!==$actor['client_id']||$permission['staff_id']!==$actor['id']) continue;$b=provider_support_stackcp_resource($permission);if ($linkId!==null&&$b['link_id']!==$linkId) continue;
            if (!provider_support_stackcp_current($permission,$events)) continue;$source=provider_stackcp_access_package_source($b['source_id'],$b['package_id']);
            $eligible[]=['profile_id'=>$b['profile_id'],'package_id'=>$b['package_id'],'package_name'=>$source['package']['name'],'link_id'=>$b['link_id'],'permission_id'=>$permission['id']];
        }
        $rows=array_slice($eligible,($page-1)*20,21);$out['has_more']=count($rows)>20;$out['items']=array_slice($rows,0,20);return $out;
    }));
}
