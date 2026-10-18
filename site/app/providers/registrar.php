<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/registrar_storage.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/InventoryDomain.php';
use CajunWebPro\Foundation\TwentyIInventory\{InventoryDomain,InventoryRead};

/** Latest intent, including unfinished attempts: never fall back through a newer failure. */
function provider_registrar_latest(string $profileId):?array {
    return one('SELECT f.id,f.profile_revision,f.started_at,f.started_epoch,r.state,r.key_fingerprint,r.coverage,r.package_count,r.skipped,r.reason FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.profile_id=? AND f.operation=? ORDER BY f.seq DESC LIMIT 1',[$profileId,InventoryRead::DOMAINS_LIST]);
}
function provider_registrar_fresh(array $profile,?array $observation):bool {
    return $observation!==null&&$observation['state']==='complete'&&(int)$profile['enabled']===1&&$profile['credentials']!==''
        &&(int)$observation['profile_revision']===(int)$profile['revision']&&preg_match('/\A[a-f0-9]{64}\z/',(string)$observation['key_fingerprint'])===1
        &&(int)$observation['started_epoch']<=time()+30&&(int)$observation['started_epoch']>=time()-900;
}
function provider_registrar_rows(string $refreshId):array {
    $rows=all('SELECT domain_id,domain,unmapped_count FROM '.table('provider_registrar_observations').' WHERE refresh_id=? ORDER BY domain',[$refreshId]);
    foreach($rows as $row)InventoryDomain::fromItem((object)['id'=>(int)$row['domain_id'],'name'=>$row['domain']]);
    return $rows;
}
/** One current observed identity is a local assignment prerequisite, never purchase/renewal consent. */
function provider_registrar_observed(string $profileId,int $revision,string $refreshId,int $domainId):array {
    if(!provider_registrar_ready()||!preg_match('/\A[a-f0-9]{32}\z/',$refreshId)||$domainId<1||$domainId>999999999999999999)throw new InvalidArgumentException('Reload the registered-domain form.');
    $profile=provider_settings_load($profileId);$observation=provider_registrar_latest($profileId);
    if((int)$profile['revision']!==$revision||!provider_registrar_fresh($profile,$observation)||$observation['id']!==$refreshId)throw new RuntimeException('Refresh registered domains and review the current observation before assigning.');
    $rows=provider_registrar_rows($refreshId);
    if(count($rows)!==(int)$observation['package_count'])throw new RuntimeException('Registered-domain observation history needs recovery.');
    foreach($rows as $row)if((int)$row['domain_id']===$domainId)return [$row,$observation];
    throw new RuntimeException('That registered domain is not present in the current observation.');
}
function provider_registrar_event(array $link,string $event,int $actor):void {
    query('INSERT INTO '.table('provider_registrar_events').' (link_id,profile_id,domain_id,domain,client_id,revision,event,actor_id,recorded_at,key_fingerprint,profile_revision,refresh_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$link['id'],$link['profile_id'],$link['domain_id'],$link['domain'],$link['client_id'],$link['revision'],$event,$actor,gmdate('Y-m-d H:i:s'),$link['key_fingerprint'],$link['profile_revision'],$link['refresh_id']]);
    provider_settings_audit($actor,'registrar_'.$event,'Registered-domain link '.$link['id'].' revision '.$link['revision']);
}
function provider_registrar_current(string $id,int $revision):array {
    if(!provider_registrar_ready()||!preg_match('/\A[a-f0-9]{32}\z/',$id)||$revision<1)throw new InvalidArgumentException('Reload the registered-domain assignment.');
    $link=one('SELECT * FROM '.table('provider_registrar_links').' WHERE id=?',[$id]);
    if(!$link||(int)$link['revision']!==$revision)throw new RuntimeException('This registered-domain assignment changed. Reload it.');
    $event=one('SELECT * FROM '.table('provider_registrar_events').' WHERE link_id=? ORDER BY id DESC LIMIT 1',[$id]);
    if(!$event||!in_array($event['event'],['link','relink'],true))throw new RuntimeException('Registered-domain assignment history needs recovery.');
    foreach(['profile_id','domain_id','domain','client_id','revision','key_fingerprint','profile_revision','refresh_id'] as $field)if((string)$link[$field]!== (string)$event[$field])throw new RuntimeException('Registered-domain assignment history is inconsistent.');
    return $link;
}
function provider_registrar_link(string $profileId,int $domainId,int $clientId,int $revision,string $refreshId,#[SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($profileId,$domainId,$clientId,$revision,$refreshId,$password):void{
        [$actor]=provider_inventory_authority($profileId,$revision,$password);[$domain,$observation]=provider_registrar_observed($profileId,$revision,$refreshId,$domainId);
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new InvalidArgumentException('Choose an active client.');
        if(one('SELECT id FROM '.table('provider_registrar_links').' WHERE profile_id=? AND (domain_id=? OR domain=?)',[$profileId,$domainId,$domain['domain']]))throw new RuntimeException('This registered domain is already assigned. Unlink its current assignment before changing clients.');
        $link=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profileId,'domain_id'=>$domainId,'domain'=>$domain['domain'],'client_id'=>$clientId,'revision'=>1,'linked_by'=>(int)$actor['id'],'linked_at'=>gmdate('Y-m-d H:i:s'),'key_fingerprint'=>$observation['key_fingerprint'],'profile_revision'=>$revision,'refresh_id'=>$refreshId];
        query('INSERT INTO '.table('provider_registrar_links').' (id,profile_id,domain_id,domain,client_id,revision,linked_by,linked_at,key_fingerprint,profile_revision,refresh_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',array_values($link));provider_registrar_event($link,'link',(int)$actor['id']);
    }));
}
function provider_registrar_unlink(string $id,int $revision,#[SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($id,$revision,$password):void{
        $actor=provider_settings_owner($password);$link=provider_registrar_current($id,$revision);
        query('DELETE FROM '.table('provider_registrar_links').' WHERE id=?',[$id]);provider_registrar_event($link,'unlink',(int)$actor['id']);
    }));
}
function provider_registrar_relink(string $id,int $revision,int $profileRevision,string $refreshId,#[SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($id,$revision,$profileRevision,$refreshId,$password):void{
        $actor=provider_settings_owner($password);$link=provider_registrar_current($id,$revision);
        provider_inventory_authority($link['profile_id'],$profileRevision,$password);
        [$domain,$observation]=provider_registrar_observed($link['profile_id'],$profileRevision,$refreshId,(int)$link['domain_id']);
        if($domain['domain']!==$link['domain']||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new RuntimeException('The registered-domain identity or client changed. Review and unlink the old assignment.');
        if($revision>=4294967295)throw new RuntimeException('Assignment revision is exhausted. Unlink and review a new assignment.');
        $link['revision']=$revision+1;$link['profile_revision']=$profileRevision;$link['refresh_id']=$refreshId;$link['key_fingerprint']=$observation['key_fingerprint'];
        query('UPDATE '.table('provider_registrar_links').' SET revision=?,profile_revision=?,refresh_id=?,key_fingerprint=? WHERE id=?',[$link['revision'],$profileRevision,$refreshId,$link['key_fingerprint'],$id]);provider_registrar_event($link,'relink',(int)$actor['id']);
    }));
}
function provider_registrar_client_domains(int $clientId):array {
    $client=studio_client();if(!$client||(int)$client['id']!==$clientId)throw new RuntimeException('Sign in to view your registered domains.');
    if(!provider_registrar_ready())return [];$out=[];
    foreach(all('SELECT * FROM '.table('provider_registrar_links').' WHERE client_id=? ORDER BY domain_id',[$clientId]) as $raw){
        $link=provider_registrar_current($raw['id'],(int)$raw['revision']);$profile=provider_settings_load($link['profile_id']);$observation=provider_registrar_latest($link['profile_id']);
        $current=provider_registrar_fresh($profile,$observation)&&provider_inventory_link_context_matches($link,$profile,$observation);
        $row=$current?one('SELECT domain FROM '.table('provider_registrar_observations').' WHERE refresh_id=? AND domain_id=?',[$observation['id'],$link['domain_id']]):null;
        $present=$row!==null&&$row['domain']===$link['domain'];
        $out[]=['domain'=>$present?$row['domain']:null,'observed_at'=>$present?$observation['started_at']:null,'available'=>$present];
    }
    return $out;
}
