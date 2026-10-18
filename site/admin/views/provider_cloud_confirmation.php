<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<?php if($request['confirmation']!==null): ?>
<p data-cloud-confirmed><strong><?=esc($request['confirmation']['status'])?></strong> · <?=esc($request['confirmation']['confirmed_at'])?> UTC. The requested server is historical context; current server location, website health and billing activation are not verified.</p>
<?php elseif(provider_cloud_confirmation_ready()):
    try{$confirmation=provider_cloud_confirmation_review($request['id']);}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$confirmation=null;$confirmationMessage=$e->getMessage();}
    if($confirmation): ?>
<form method="post" data-cloud-confirmation>
<h5>Confirm observed package #<?=(int)$confirmation['package']['package_id']?></h5>
<p>Primary domain: <?=esc($confirmation['package']['name'])?>. Client #<?=(int)$confirmation['request']['binding']['client_id']?>. Hosting observation: <?=esc($confirmation['refresh']['started_at'])?> UTC.</p>
<p>The exact returned package ID and all approved domain names match this saved response. This does not verify current server location, website health or billing activation.</p>
<?php admin_action('provider_inventory_cloud_confirmation_confirm'); ?>
<input type="hidden" name="request_id" value="<?=esc($request['id'])?>">
<input type="hidden" name="refresh_id" value="<?=esc($confirmation['refresh']['id'])?>">
<input type="hidden" name="review" value="<?=esc($confirmation['review'])?>">
<label><input type="checkbox" name="reviewed" value="1" required> I reviewed this observed package and explicitly approve its assignment to the original client.</label>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Confirm package and client assignment</button>
</form>
<?php else: ?><p class="hint"><?=esc($confirmationMessage)?></p><?php endif; endif; ?>
