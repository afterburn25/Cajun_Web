<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/cloud_providers.php';
provider_settings_manager();
?>
<section class="workspace-card" id="cloud-providers"><h2>Cloud providers</h2>
<p>See the provider identifiers returned by your connected 20i account. These saved choices do not include server sizes, locations, prices or permission to place an order.</p>
<?php if(!provider_inventory_ready()): ?><p>Prepare hosting inventory above first.</p>
<?php else: try { $cloudProvidersReady=provider_cloud_providers_ready(); } catch(RuntimeException $e) { if($e instanceof PDOException)throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p></section>';return; } ?>
<?php if(!$cloudProvidersReady): ?><form method="post"><?php admin_action('provider_inventory_cloud_providers_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare cloud provider discovery</button></form>
<?php elseif($record): ?>
<form method="post"><?php admin_action('provider_inventory_cloud_providers_refresh'); ?><input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Refresh cloud providers</button></form>
<?php try { $cloudProviderView=provider_cloud_providers_view($profileId); } catch(RuntimeException $e) { if($e instanceof PDOException)throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p></section>';return; } ?>
<?php if($cloudProviderView['latest']): ?><p>Latest refresh: <?=esc($cloudProviderView['latest']['state'])?> · <?=esc($cloudProviderView['latest']['started_at'])?> UTC.</p><?php endif; ?>
<?php if($cloudProviderView['saved']): ?><p>Saved observation: <?=esc($cloudProviderView['saved']['observed_at'])?> UTC.</p>
<?php if($cloudProviderView['current']): ?><p class="notice success">Observed recently under the current connection. Refresh again before using these identifiers for future setup.</p>
<?php else: ?><p class="notice">Historical observation only. A new refresh is needed to confirm these identifiers under the current connection.</p><?php endif; ?>
<?php if($cloudProviderView['saved']['identifiers']===[]): ?><p>No provider identifiers were returned in this observation. This does not establish that services have been cancelled or that the account has no cloud products.</p>
<?php else: ?><ul aria-label="Observed cloud provider identifiers"><?php foreach($cloudProviderView['saved']['identifiers'] as $identifier): ?><li style="overflow-wrap:anywhere"><?=esc($identifier)?></li><?php endforeach; ?></ul><?php endif; ?>
<p class="hint">The response may not represent every available service. Identifiers are shown exactly as returned; no provider brand or product is inferred.</p>
<?php else: ?><p>No cloud provider observation has been saved yet.</p><?php endif; ?>
<?php else: ?><p>Save the 20i connection above before refreshing providers.</p><?php endif; endif; ?>
</section>
