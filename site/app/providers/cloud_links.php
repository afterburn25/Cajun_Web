<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\TwentyIInventory\InventoryCloudServer;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;

/** Owner selection must still name the exact observation displayed in the form. */
function provider_cloud_link_observation(string $profileId,int $revision,string $refreshId,int $serverId): array {
    if($serverId<1||$serverId>999999999999999999||!preg_match('/^[a-f0-9]{32}$/D',$refreshId))throw new InvalidArgumentException('Reload the current cloud inventory form.');
    $observation=provider_inventory_observation($profileId,InventoryRead::CLOUD_SERVERS_LIST);
    if(!$observation||$observation['id']!==$refreshId||(int)$observation['profile_revision']!==$revision||!preg_match('/^[a-f0-9]{64}$/D',$observation['key_fingerprint']))throw new RuntimeException('The cloud observation changed or belongs to earlier settings. Refresh and review the current server before approving.');
    $row=one('SELECT metadata FROM '.table('provider_observation_cloud').' WHERE refresh_id=? AND server_id=?',[$refreshId,$serverId]);
    if(!$row)throw new RuntimeException('That server is not in the current cloud observation. Refresh first.');
    $server=InventoryCloudServer::fromItem(json_decode($row['metadata'],false,16,JSON_THROW_ON_ERROR));
    if($server->id!==$serverId)throw new RuntimeException('Cloud observation identity is inconsistent. Restore the original database.');
    return $observation;
}

function provider_cloud_link_event(array $link,string $event,int $actor):void {
    query('INSERT INTO '.table('provider_cloud_link_events').' (link_id,profile_id,server_id,client_id,revision,event,actor_id,recorded_at,key_fingerprint,profile_revision,refresh_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$link['id'],$link['profile_id'],$link['server_id'],$link['client_id'],$link['revision'],$event,$actor,gmdate('Y-m-d H:i:s'),$link['key_fingerprint'],$link['profile_revision'],$link['refresh_id']]);
    provider_settings_audit($actor,'cloud_'.$event,'Cloud link '.$link['id'].' profile '.$link['profile_id'].' server '.$link['server_id'].' client '.$link['client_id'].' revision '.$link['revision']);
}

/** No provider request: an explicit local approval for one existing client and server. */
function provider_cloud_link(string $profileId,int $serverId,int $clientId,int $profileRevision,string $refreshId,#[\SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($profileId,$serverId,$clientId,$profileRevision,$refreshId,$password):void {
        [$actor]=provider_inventory_authority($profileId,$profileRevision,$password);
        $observation=provider_cloud_link_observation($profileId,$profileRevision,$refreshId,$serverId);
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new InvalidArgumentException('Active client account not found.');
        if(one('SELECT id FROM '.table('provider_cloud_links').' WHERE profile_id=? AND server_id=?',[$profileId,$serverId]))throw new RuntimeException('That cloud server is already linked. Review and unlink it before changing its client.');
        $link=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profileId,'server_id'=>$serverId,'client_id'=>$clientId,'revision'=>1,'linked_by'=>(int)$actor['id'],'linked_at'=>gmdate('Y-m-d H:i:s'),'key_fingerprint'=>$observation['key_fingerprint'],'profile_revision'=>$profileRevision,'refresh_id'=>$refreshId];
        query('INSERT INTO '.table('provider_cloud_links').' (id,profile_id,server_id,client_id,revision,linked_by,linked_at,key_fingerprint,profile_revision,refresh_id) VALUES (?,?,?,?,?,?,?,?,?,?)',array_values($link));
        provider_cloud_link_event($link,'link',(int)$actor['id']);
    }));
}

function provider_cloud_link_current(string $linkId,int $expected):array {
    if(!preg_match('/^[a-f0-9]{32}$/D',$linkId)||$expected<1)throw new InvalidArgumentException('Reload the current cloud link form.');
    $link=one('SELECT * FROM '.table('provider_cloud_links').' WHERE id=?',[$linkId]);
    if(!$link||(int)$link['revision']!==$expected)throw new RuntimeException('This cloud assignment changed. Reload and review it before continuing.');
    return $link;
}

function provider_cloud_unlink(string $linkId,int $expected,#[\SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($linkId,$expected,$password):void {
        [$actor]=provider_inventory_authority_args($password);$link=provider_cloud_link_current($linkId,$expected);
        query('DELETE FROM '.table('provider_cloud_links').' WHERE id=?',[$linkId]);
        provider_cloud_link_event($link,'unlink',(int)$actor['id']);
    }));
}

/** Reapproval preserves the original client and resource identity; never reassigns. */
function provider_cloud_relink(string $linkId,int $expected,int $profileRevision,string $refreshId,#[\SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($linkId,$expected,$profileRevision,$refreshId,$password):void {
        [$actor]=provider_inventory_authority_args($password);$link=provider_cloud_link_current($linkId,$expected);
        provider_inventory_authority($link['profile_id'],$profileRevision,$password);
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new RuntimeException('The assigned client account is inactive. Review the account before approving.');
        $observation=provider_cloud_link_observation($link['profile_id'],$profileRevision,$refreshId,(int)$link['server_id']);
        if($expected>=4294967295)throw new RuntimeException('Cloud assignment revision is exhausted. Unlink and create a new reviewed assignment.');
        $link['revision']=$expected+1;$link['key_fingerprint']=$observation['key_fingerprint'];$link['profile_revision']=$profileRevision;$link['refresh_id']=$refreshId;
        query('UPDATE '.table('provider_cloud_links').' SET revision=?,key_fingerprint=?,profile_revision=?,refresh_id=? WHERE id=?',[$link['revision'],$link['key_fingerprint'],$profileRevision,$refreshId,$linkId]);
        provider_cloud_link_event($link,'relink',(int)$actor['id']);
    }));
}

function provider_cloud_links(string $profileId):array {
    provider_settings_manager();
    return all('SELECT l.*,c.name AS client_name,c.active AS client_active,u.name AS actor_name FROM '.table('provider_cloud_links').' l JOIN '.table('studio_clients').' c ON c.id=l.client_id LEFT JOIN '.table('users').' u ON u.id=l.linked_by WHERE l.profile_id=? ORDER BY l.server_id',[$profileId]);
}

function provider_cloud_link_events(string $profileId,int $page=1):array {
    provider_settings_manager();$page=max(1,min($page,100000));
    return all('SELECT e.*,c.name AS client_name,u.name AS actor_name FROM '.table('provider_cloud_link_events').' e LEFT JOIN '.table('studio_clients').' c ON c.id=e.client_id LEFT JOIN '.table('users').' u ON u.id=e.actor_id WHERE e.profile_id=? ORDER BY e.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$profileId]);
}

/** Internal portal projection. Caller obtains client ID from authenticated studio_client(). */
function provider_cloud_client_services(int $clientId):array {
    if(!provider_inventory_ready()||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))return [];
    $out=[];
    foreach(all('SELECT * FROM '.table('provider_cloud_links').' WHERE client_id=? ORDER BY server_id',[$clientId]) as $link) {
        $profile=one('SELECT revision,enabled FROM '.table('provider_profiles').' WHERE id=?',[$link['profile_id']]);
        $current=$profile&&(int)$profile['enabled']===1&&(int)$profile['revision']===(int)$link['profile_revision'];
        $observation=$current?one('SELECT r.id,r.key_fingerprint,f.profile_revision,f.started_at FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id WHERE f.profile_id=? AND f.operation=? AND r.state IN (\'complete\',\'partial\') ORDER BY f.started_epoch DESC,f.seq DESC LIMIT 1',[$link['profile_id'],InventoryRead::CLOUD_SERVERS_LIST]):null;
        $context=$current&&provider_inventory_link_context_matches($link,$profile,$observation);
        $row=$context?one('SELECT server_id,metadata FROM '.table('provider_observation_cloud').' WHERE refresh_id=? AND server_id=?',[$observation['id'],$link['server_id']]):null;
        $facts=null;
        if($row) {
            $server=InventoryCloudServer::fromItem(json_decode($row['metadata'],false,16,JSON_THROW_ON_ERROR));
            if($server->id!==(int)$link['server_id'])throw new RuntimeException('Cloud observation identity is inconsistent.');
            // Package IDs can describe other customers on this machine. Never project them.
            $facts=['id'=>$server->id,'name'=>$server->name,'location'=>$server->location,'product_spec'=>$server->productSpec,'configuration'=>$server->configuration];
        }
        $out[]=['server_id'=>(int)$link['server_id'],'server'=>$facts,'context_changed'=>!$context,'observed_at'=>$context?$observation['started_at']:null,'linked_at'=>$link['linked_at']];
    }
    return $out;
}
