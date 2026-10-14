<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/providers/credentials.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_settings_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_settings_migrate.php';

function provider_settings_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_settings_schema_version'])['value']??'')==='1';}
function provider_settings_manager(bool $ready=true): array {
    if (support_current() || array_key_exists('support',$_GET)) throw new RuntimeException('Leave client support before using provider settings.');
    $actor=admin_user();
    if (!$actor || !staff_ready() || $actor['role']!=='owner' || !staff_can('staff.manage',$actor)) throw new RuntimeException('An active owner account is required for provider settings.');
    if ($ready && !provider_settings_ready()) throw new RuntimeException('An owner must prepare provider settings first.');
    return $actor;
}
function provider_settings_owner(#[\SensitiveParameter] string $password,bool $ready=true): array {
    $actor=provider_settings_manager($ready);
    if (!password_verify($password,$actor['password_hash'])) throw new InvalidArgumentException('Your current password is not correct.');
    return $actor;
}
function provider_settings_audit(int $actor,string $action,string $detail): void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'provider.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
function provider_settings_load(string $id,bool $lock=false): array {
    if (!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new InvalidArgumentException('Provider profile unavailable.');
    $row=one('SELECT * FROM '.table('provider_profiles').' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);
    if (!$row || $row['provider_code']!=='twenty-i' || $row['environment']!=='live') throw new InvalidArgumentException('Provider profile unavailable.');
    return $row;
}
/** Explicit safe projection; no credential fragment, ciphertext or inferred capability. */
function provider_settings_projection(array $row): array {
    $out=[];foreach(['id','provider_code','environment','revision','label','enabled','updated_by','updated_at'] as $name)$out[$name]=$row[$name];
    $out['has_credentials']=$row['credentials']!=='';return $out;
}
function provider_settings_profile(): ?array {
    provider_settings_manager();
    $row=one('SELECT * FROM '.table('provider_profiles').' WHERE provider_code=? AND environment=?',['twenty-i','live']);
    return $row?provider_settings_projection($row):null;
}
function provider_settings_history(string $id,int $page=1): array {
    provider_settings_manager();provider_settings_load($id);$page=max(1,min($page,100000));
    return all('SELECT r.*,u.name AS actor_name FROM '.table('provider_revisions').' r LEFT JOIN '.table('users').' u ON u.id=r.actor_id WHERE r.profile_id=? ORDER BY r.revision DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
}
function provider_settings_snapshot(array $row,int $actor,string $action): void {
    query('INSERT INTO '.table('provider_revisions').' (id,profile_id,revision,label,enabled,has_credentials,action,actor_id,recorded_at) VALUES (?,?,?,?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$row['id'],$row['revision'],$row['label'],$row['enabled'],(int)($row['credentials']!==''),$action,$actor,gmdate('Y-m-d H:i:s')]);
    provider_settings_audit($actor,$action,'Profile '.$row['id'].' revision '.$row['revision']);
}
function provider_settings_save(string $id,int $revision,string $label,bool $enabled,#[\SensitiveParameter] string $secret,#[\SensitiveParameter] string $password): string {
    return staff_locked(fn()=>staff_transaction(function()use($id,$revision,$label,$enabled,$secret,$password):string {
        $actor=provider_settings_owner($password);$old=$id!==''?provider_settings_load($id,true):null;
        if ($revision<0 || $revision>=2147483647 || (int)($old['revision']??0)!==$revision) throw new RuntimeException('This provider profile changed. Reload before saving.');
        if (!$old && one('SELECT id FROM '.table('provider_profiles').' WHERE provider_code=? AND environment=?',['twenty-i','live'])) throw new RuntimeException('The 20i profile already exists. Reload before saving.');
        $label=trim($label);
        if ($label==='' || strlen($label)>120 || preg_match('//u',$label)!==1 || preg_match('/[\x00-\x1F\x7F]/',$label)) throw new InvalidArgumentException('Enter a profile label up to 120 bytes without control characters.');
        $id=$old?$id:bin2hex(random_bytes(16));
        $saved=provider_settings_unseal($id,'live',$old['credentials']??'');
        $cipher=provider_settings_seal($id,'live',$secret!==''?$secret:$saved);
        $now=gmdate('Y-m-d H:i:s');$args=[$revision+1,$label,$cipher,(int)$enabled,(int)$actor['id'],$now];
        if ($old) query('UPDATE '.table('provider_profiles').' SET revision=?,label=?,credentials=?,enabled=?,updated_by=?,updated_at=? WHERE id=?',[...$args,$id]);
        else query('INSERT INTO '.table('provider_profiles').' (revision,label,credentials,enabled,updated_by,updated_at,id,provider_code,environment) VALUES (?,?,?,?,?,?,?,?,?)',[...$args,$id,'twenty-i','live']);
        provider_settings_snapshot(provider_settings_load($id),(int)$actor['id'],$old?'save':'create');return $id;
    }));
}
function provider_settings_state(string $id,int $revision,string $action,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function()use($id,$revision,$action,$password):void {
        $actor=provider_settings_owner($password);$row=provider_settings_load($id,true);
        if ($revision<1 || $revision>=2147483647 || (int)$row['revision']!==$revision) throw new RuntimeException('This provider profile changed. Reload before changing its state.');
        if (!in_array($action,['enable','disable','clear'],true)) throw new InvalidArgumentException('Unknown provider action.');
        if ($action==='enable') provider_settings_key_value(provider_settings_unseal($id,$row['environment'],$row['credentials']));
        query('UPDATE '.table('provider_profiles').' SET revision=?,enabled=?,credentials=?,updated_by=?,updated_at=? WHERE id=?',[$revision+1,(int)($action==='enable'),$action==='clear'?'':$row['credentials'],(int)$actor['id'],gmdate('Y-m-d H:i:s'),$id]);
        provider_settings_snapshot(provider_settings_load($id),(int)$actor['id'],$action);
    }));
}
