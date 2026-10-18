<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/mailbox_views.php';
$mailboxReady=false;$mailboxUnavailable=false;$mailboxOptions=[];$mailboxHistory=[];$mailboxLinks=[];
try{
    provider_settings_manager();$mailboxReady=provider_mailbox_ready();
    if($mailboxReady){$mailboxHistory=provider_mailbox_history();$mailboxOptions=provider_mailbox_options();$mailboxLinks=provider_inventory_domain_links($profileId);}
}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$mailboxUnavailable=true;}
?>
<section class="workspace-card" id="mailbox-management"><h2>Hosting mailbox permissions</h2>
<p>Approve creation separately for each linked client domain. Refresh hosting inventory before granting access. Permissions expire and stop working when the account, owner approval or domain assignment changes.</p>
<?php if($mailboxUnavailable): ?><p>Mailbox records need attention. Restore the original records before making requests.</p>
<?php elseif(!$mailboxReady): ?><form method="post"><?php admin_action('provider_inventory_mailbox_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare mailbox history</button></form>
<?php else: ?>
<?php if(!$mailboxLinks): ?><p>Approve a client domain link above before enabling mailbox creation.</p><?php endif; ?>
<?php foreach($mailboxLinks as $link):
    try{$policy=provider_mailbox_policy($link['id']);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p>Mailbox permission history needs recovery for '.esc($link['domain']).'.</p>';continue;}
?><article class="workspace-card"><h3><?=esc($link['domain'])?></h3><p><?=esc($link['client_name'])?></p>
<?php if($policy): ?><p><?=$policy['enabled']?'Enabled':'Disabled'?> · expires <?=esc(gmdate('Y-m-d H:i:s',$policy['expires_epoch']))?> UTC</p><?php endif; ?>
<form method="post"><?php admin_action('provider_inventory_mailbox_policy'); ?><input type="hidden" name="link_id" value="<?=esc($link['id'])?>"><input type="hidden" name="previous_policy" value="<?=esc($policy['id']??'')?>">
<label><input type="checkbox" name="mailbox_enabled" value="1"<?=!empty($policy['enabled'])?' checked':''?>> Allow mailbox creation</label>
<label><input type="checkbox" name="client_enabled" value="1"<?=!empty($policy['client_enabled'])?' checked':''?>> Let this client create mailboxes in their portal</label>
<label>Maximum requests in 24 hours<input type="number" name="daily_limit" min="1" max="20" value="<?=(int)($policy['daily_limit']??5)?>" required></label>
<label>Permission duration in hours<input type="number" name="permission_hours" min="1" max="720" value="24" required></label>
<p class="hint">This request allowance does not set mailbox storage or provider quotas. Verify available capacity and any provider costs before enabling creation.</p>
<label><input type="checkbox" name="capacity_reviewed" value="1"> I reviewed mailbox availability and costs for this hosting service.</label>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Save mailbox permissions</button></form></article>
<?php endforeach; ?>
<?php endif; ?></section>
<?php if($mailboxReady&&!$mailboxUnavailable): $mailboxAdmin=true;require CWP_CODE_ROOT.'/portal/views/mailboxes.php';endif; ?>
