<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="workspace-card" data-cloud-websites><h3>Add a website to a cloud server</h3>
<p>Create a hosting package on an existing server assigned to a client. Review available server capacity and permission to host every domain before submitting. Domain registration, DNS changes, server purchases and billing are separate.</p>
<?php if(!provider_operations_ready()): ?>
<p>Prepare operation history in <a href="<?=esc(admin_url('provider-operations'))?>">20i requests</a> before adding websites.</p>
<?php else: foreach(provider_cloud_links($profileId) as $cloudLink):
    try{$cloudContext=provider_cloud_sites_context($cloudLink['id'],(int)$cloudLink['revision'],(int)$record['revision'],$cloudObservation['id']??'');}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;continue;}
?>
<form method="post" class="workspace-card" data-cloud-website-create>
<h4>Server #<?=(int)$cloudLink['server_id']?> · <?=esc($cloudLink['client_name'])?></h4>
<?php admin_action('provider_inventory_cloudsite_create'); ?>
<input type="hidden" name="request_id" value="<?=esc(bin2hex(random_bytes(16)))?>">
<input type="hidden" name="link_id" value="<?=esc($cloudLink['id'])?>">
<input type="hidden" name="link_revision" value="<?=(int)$cloudLink['revision']?>">
<input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<input type="hidden" name="refresh_id" value="<?=esc($cloudObservation['id'])?>">
<?php admin_input('domain_name','Primary domain','','text','required maxlength="253" placeholder="example.com"'); ?>
<label>Additional domains <textarea name="extra_domains" rows="3" maxlength="5080" placeholder="One domain per line; optional"></textarea></label>
<label><input type="checkbox" name="reviewed" value="1" required> I reviewed this client's domain permission and the server's available capacity, and approve this website creation.</label>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Create cloud website</button>
</form>
<?php endforeach; ?>
<p class="hint">Creation forms require an active client assignment and a complete cloud refresh from the last 15 minutes.</p>
<?php if(!provider_cloud_confirmation_ready()): ?>
<form method="post" class="workspace-card" data-cloud-confirmation-prepare><h4>Prepare website confirmation history</h4><p>Retain reviewed hosting observations and explicit client assignments. Back up the database before adding this storage.</p>
<?php admin_action('provider_inventory_cloud_confirmation_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn">Prepare confirmation history</button></form>
<?php endif; ?>
<?php $cloudRequests=provider_cloud_sites_history($profileId);if($cloudRequests): ?>
<h4>Recent cloud website requests</h4>
<?php foreach($cloudRequests as $request): ?><article class="workspace-card"><strong><?=esc($request['domain'])?></strong><p>Requested server #<?=(int)$request['server_id']?> · Client #<?=(int)$request['client_id']?> · <?=esc($request['created_at'])?> UTC · <?=badge($request['status'])?></p>
<?php if($request['package_id']!==null): ?><p>Returned package #<?=esc($request['package_id'])?>. Refresh hosting inventory, verify this exact package, then explicitly link it to the correct client. Acceptance alone does not establish an active service.</p><?php require CWP_CODE_ROOT.'/admin/views/provider_cloud_confirmation.php'; ?><?php else: ?><p>Uncertain or interrupted requests require investigation before another attempt. They are never resubmitted automatically.</p><?php endif; ?></article><?php endforeach; ?>
<?php endif; endif; ?></section>
