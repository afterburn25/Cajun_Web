<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
?>
<section class="workspace-card" data-provider-cloud><h2>Cloud server inventory</h2>
<p>Refresh the cloud server list from your saved 20i account. This makes one live read when you submit the form. Observed specifications do not establish service health, prices or client ownership.</p>
<?php if(!$record): ?>
<p>Save your 20i connection above before reading cloud servers.</p>
<?php elseif(!provider_inventory_ready()): ?>
<p>Prepare inventory storage above before reading cloud servers. Existing package records and links are preserved.</p>
<?php else:
$cloudLatest=provider_inventory_latest($profileId,InventoryRead::CLOUD_SERVERS_LIST);
$cloudObservation=provider_inventory_observation($profileId,InventoryRead::CLOUD_SERVERS_LIST);
if($cloudLatest): ?><p>Last cloud refresh: <?=badge($cloudLatest['state'])?> · <?=esc($cloudLatest['started_at'])?> UTC · saved version <?=(int)$cloudLatest['profile_revision']?><?=$cloudLatest['reason']!==''?' · '.esc(str_replace('_',' ',$cloudLatest['reason'])):''?></p><?php endif; ?>
<?php if((int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post" data-provider-cloud-refresh><?php admin_action('provider_inventory_cloud_refresh'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Refresh cloud inventory</button><p class="hint">Uses the same per-profile cooldown and owner refresh allowance as package inventory.</p>
</form><?php else: ?><p>Enable the saved connection and save a General API key before refreshing.</p><?php endif; ?>
<?php if(!$cloudObservation): ?><p>No validated cloud observation has been saved.</p>
<?php elseif((int)$cloudObservation['profile_revision']!==(int)$record['revision']): ?><p>The saved observation belongs to earlier provider settings. Refresh under the current settings before using its details.</p>
<?php else: $cloudServers=provider_inventory_cloud_servers($cloudObservation['id']); ?>
<p>Observed <?=esc($cloudObservation['started_at'])?> UTC. <?=esc($cloudObservation['state']==='partial'?'Some returned entries were skipped.':'Returned entries were validated.')?> Account-wide coverage is unknown; an absent server is not proof of cancellation or deletion.</p>
<?php if(!$cloudServers): ?><p>No servers were present in this observation.</p><?php endif; ?>
<?php foreach($cloudServers as $cloudRow): $server=$cloudRow['server']; ?>
<article class="workspace-card"><h3><?=esc($server->name??('Cloud server #'.$server->id))?></h3>
<p>Provider ID <?=(int)$server->id?> · Location <?=esc($server->location??'not reported')?> · Specification <?=esc($server->productSpec??'not reported')?></p>
<?php if($server->configuration!==null): $facts=$server->configuration; ?>
<dl><?php foreach(['CpuCores'=>'CPU cores','RamMb'=>'Memory (MB)','OsDiskSizeGb'=>'OS disk (GB)','DataDiskSizeGb'=>'Data disk (GB)','Provider'=>'Infrastructure provider','Zone'=>'Zone','Country'=>'Country','VpsOsId'=>'OS reference','spec'=>'Size reference'] as $field=>$label): ?><dt><?=esc($label)?></dt><dd><?=esc($facts[$field]===null?'Not reported':(string)$facts[$field])?></dd><?php endforeach; ?></dl>
<?php else: ?><p>Configuration was not reported.</p><?php endif; ?>
<p>Hosted package IDs: <?=esc($server->packageIds===null?'Not reported':($server->packageIds?implode(', ',$server->packageIds):'None in this observation'))?>.</p>
<p class="hint">Client assignment, service health and management controls require their own verified workflows. Unmapped provider fields: <?=(int)$cloudRow['unmapped_count']?>.</p>
</article><?php endforeach; ?>
<?php endif; ?>
<?php $cloudHistory=provider_inventory_history($profileId,1,InventoryRead::CLOUD_SERVERS_LIST);if($cloudHistory): ?><h3>Cloud refresh history</h3>
<?php foreach(array_slice($cloudHistory,0,20) as $row): ?><p><?=esc($row['started_at'])?> UTC · <?=badge($row['state'])?> · <?=(int)$row['package_count']?> server records · <?=(int)$row['skipped']?> skipped · saved version <?=(int)$row['profile_revision']?></p><?php endforeach; ?>
<?php endif; ?>
<?php endif; ?></section>
