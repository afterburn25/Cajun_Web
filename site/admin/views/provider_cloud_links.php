<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$cloudLinks=provider_cloud_links($profileId);$cloudClients=provider_inventory_clients();$observedServers=[];$assigned=[];
try{$assignmentState=provider_cloud_assignment_view($profileId,(int)$record['revision'],$cloudObservation['id']??null);}
catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;?><section data-cloud-assignments><h3>Cloud client assignments</h3><p class="notice error" data-cloud-assignment-unavailable><?=esc($e->getMessage())?></p><p>Saved assignments are preserved. Restore the original records before making another approval.</p></section><?php return;}
$canApprove=$assignmentState['can_approve'];
if($canApprove)foreach(provider_inventory_cloud_servers($cloudObservation['id']) as $row)$observedServers[$row['server']->id]=$row['server'];
foreach($cloudLinks as $link)$assigned[(int)$link['server_id']]=$link;
$available=array_diff_key($observedServers,$assigned);
?>
<section data-cloud-assignments><h3>Cloud client assignments</h3>
<p>Approve a server for an existing client account. This grants visibility of that server's specifications. It does not grant ownership of hosted packages, create an order, change billing or contact 20i.</p>
<?php if(!$assignmentState['ready']): ?><form method="post" data-cloud-assignment-prepare><?php admin_action('provider_inventory_cloud_assignment_prepare'); ?><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare cloud assignment history</button><p class="hint">Preserves existing assignments. Previously saved assignments need a fresh cloud read and owner review before server details are shared under the current connection.</p></form><?php endif; ?>
<?php if($available&&$cloudClients): ?>
<form method="post" data-cloud-link><?php admin_action('provider_inventory_cloud_link'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><input type="hidden" name="refresh_id" value="<?=esc($cloudObservation['id'])?>">
<label>Observed server<select name="server_id" required><?php foreach($available as $server): ?><option value="<?=(int)$server->id?>"><?=esc(($server->name??'Cloud server').' · #'.$server->id)?></option><?php endforeach; ?></select></label>
<label>Client account<select name="client_id" required><option value="">Choose a client</option><?php foreach($cloudClients as $client): ?><option value="<?=(int)$client['id']?>"><?=esc($client['name'].' · '.$client['email'])?></option><?php endforeach; ?></select></label>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Approve cloud assignment</button>
</form>
<?php elseif(!$canApprove): ?><p data-cloud-assignment-reason><?=esc($assignmentState['reason'])?></p>
<?php elseif(!$cloudClients): ?><p>Add an active client account before assigning a server.</p>
<?php else: ?><p>Every currently observed server already has an assignment, or no server was returned.</p><?php endif; ?>
<?php if(!$cloudLinks): ?><p>No cloud servers are assigned yet.</p><?php endif; ?>
<?php foreach($cloudLinks as $link): $present=isset($observedServers[(int)$link['server_id']]);$saved=$assignmentState['current_links'][$link['id']]??['current'=>false,'context_changed'=>true,'history_status'=>'historical'];$current=$saved['current']; ?>
<article class="workspace-card" data-cloud-assignment><h4>Cloud server #<?=(int)$link['server_id']?> · <?=esc($link['client_name'])?></h4>
<p>Approved <?=esc($link['linked_at'])?> UTC by <?=esc($link['actor_name']??'Former owner')?>. Assignment version <?=(int)$link['revision']?>.</p>
<?php if($saved['context_changed']): ?><p>Requires review under current provider settings. Client server details are hidden.</p>
<?php elseif($saved['history_status']==='not_observed'): ?><p>The server is absent from the latest saved observation. This does not confirm deletion; client server details are hidden.</p>
<?php elseif(!$current): ?><p>Historical specifications remain available to the assigned client. A fresh complete cloud read is required before another approval.</p>
<?php else: ?><p>Approved provider context matches the current observation.</p><?php endif; ?>
<?php if(!(int)$link['client_active']): ?><p>The assigned client account is inactive.</p><?php endif; ?>
<?php if(!$current&&$canApprove&&$present&&(int)$link['client_active']===1): ?>
<form method="post" data-cloud-relink><?php admin_action('provider_inventory_cloud_relink'); ?><input type="hidden" name="link_id" value="<?=esc($link['id'])?>"><input type="hidden" name="link_revision" value="<?=(int)$link['revision']?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><input type="hidden" name="refresh_id" value="<?=esc($cloudObservation['id'])?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Re-approve cloud assignment</button></form>
<?php endif; ?>
<?php if($assignmentState['ready']): ?><form method="post" data-cloud-unlink><?php admin_action('provider_inventory_cloud_unlink'); ?><input type="hidden" name="link_id" value="<?=esc($link['id'])?>"><input type="hidden" name="link_revision" value="<?=(int)$link['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Remove cloud assignment</button><p class="hint">Removes portal visibility only. The server and commercial records are retained.</p></form><?php endif; ?>
</article><?php endforeach; ?>
<?php $cloudEvents=provider_cloud_link_events($profileId);if($cloudEvents): ?><h4>Cloud assignment history</h4>
<?php foreach(array_slice($cloudEvents,0,20) as $event): ?><p><?=esc($event['recorded_at'])?> UTC · <?=esc(match($event['event']){'link'=>'Assigned','relink'=>'Re-approved',default=>'Unlinked'})?> server #<?=(int)$event['server_id']?> · <?=esc($event['client_name']??'Former client')?> · <?=esc($event['actor_name']??'Former owner')?> · assignment version <?=(int)$event['revision']?></p><?php endforeach; ?>
<?php endif; ?></section>
