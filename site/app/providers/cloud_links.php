<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\TwentyIInventory\InventoryCloudServer;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
require_once CWP_CODE_ROOT.'/app/providers/cloud_assignment.php';

/** Owner selection must still name the exact observation displayed in the form. */
function provider_cloud_link_observation(string $profileId,int $revision,string $refreshId,int $serverId): array {
    if($serverId<1||$serverId>999999999999999999||!preg_match('/^[a-f0-9]{32}$/D',$refreshId))throw new InvalidArgumentException('Reload the current cloud inventory form.');
    return provider_cloud_assignment_observation($profileId,$revision,$refreshId,$serverId);
}

function provider_cloud_link_event(array $link,string $event,int $actor):void {
    query('INSERT INTO '.table('provider_cloud_link_events').' (link_id,profile_id,server_id,client_id,revision,event,actor_id,recorded_at,key_fingerprint,profile_revision,refresh_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$link['id'],$link['profile_id'],$link['server_id'],$link['client_id'],$link['revision'],$event,$actor,gmdate('Y-m-d H:i:s'),$link['key_fingerprint'],$link['profile_revision'],$link['refresh_id']]);
    provider_settings_audit($actor,'cloud_'.$event,'Cloud link '.$link['id'].' profile '.$link['profile_id'].' server '.$link['server_id'].' client '.$link['client_id'].' revision '.$link['revision']);
}

/** No provider request: an explicit local approval for one existing client and server. */
function provider_cloud_link(string $profileId,int $serverId,int $clientId,int $profileRevision,string $refreshId,#[\SensitiveParameter] string $password):void {
    provider_cloud_assignment_link($profileId,$serverId,$clientId,$profileRevision,$refreshId,$password);
}

function provider_cloud_link_current(string $linkId,int $expected):array {
    if(!preg_match('/^[a-f0-9]{32}$/D',$linkId)||$expected<1)throw new InvalidArgumentException('Reload the current cloud link form.');
    $link=one('SELECT * FROM '.table('provider_cloud_links').' WHERE id=?',[$linkId]);
    if(!$link||(int)$link['revision']!==$expected)throw new RuntimeException('This cloud assignment changed. Reload and review it before continuing.');
    return provider_cloud_assignment_inspect(function()use($link):array{if(provider_cloud_assignment_ready())provider_cloud_assignment_assignment($link);return $link;});
}

function provider_cloud_unlink(string $linkId,int $expected,#[\SensitiveParameter] string $password):void {
    provider_cloud_assignment_unlink($linkId,$expected,$password);
}

/** Reapproval preserves the original client and resource identity; never reassigns. */
function provider_cloud_relink(string $linkId,int $expected,int $profileRevision,string $refreshId,#[\SensitiveParameter] string $password):void {
    provider_cloud_assignment_relink($linkId,$expected,$profileRevision,$refreshId,$password);
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
    return provider_cloud_assignment_client_services($clientId);
}
