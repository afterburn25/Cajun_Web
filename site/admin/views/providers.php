<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$record=provider_settings_profile();$profileId=$record['id']??'';
$values=$record??['label'=>'Studio 20i','revision'=>0,'enabled'=>0];$revision=(int)$values['revision'];
if(($_POST['action']??'')==='provider_settings_save'&&($_POST['profile_id']??'')===$profileId){
    if(is_string($_POST['label']??null))$values['label']=substr($_POST['label'],0,632);
    $values['enabled']=($_POST['enabled']??'')==='1';
    if(is_string($_POST['revision']??null)&&ctype_digit($_POST['revision']))$revision=(int)$_POST['revision'];
}
?>
<section class="workspace-card"><h2>Your 20i account</h2>
<p>Owners can save a General API key and explicitly check whether 20i accepts it for the package-list read. This uses your live 20i account.</p>
<p>Hosting, domains, VPS, billing and SSH controls still need their own integration and verification.</p>
<?php if($record): ?><p><?=badge((int)$record['enabled']===1?'enabled':'disabled')?> · Saved version <?=(int)$record['revision']?> · <?=esc($record['has_credentials']?'General API key saved':'No General API key saved')?></p><?php endif; ?>
<form method="post" data-provider-editor>
<?php admin_action('provider_settings_save'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=$revision?>">
<?php admin_input('label','Connection name',$values['label'],'text','required maxlength="120"');
admin_input('general_key',($record&&$record['has_credentials'])?'Replacement General API key (blank keeps saved key)':'General API key','','password',(($record&&$record['has_credentials'])?'':'required ').'maxlength="4096" autocomplete="new-password"'); ?>
<p class="hint">Enter the raw General API key from 20i. It is never shown again or refilled after an error. Reenter a changed key before resubmitting.</p>
<?php admin_check('enabled','Allow explicit checks of this saved connection',$values['enabled']);
admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Save 20i settings</button>
</form></section>
<?php if($record): ?>
<section class="workspace-card"><h2>Check the saved connection</h2>
<p>This makes one read request to 20i. A successful check confirms that read at the recorded time. Saving settings does not run a check.</p>
<?php if((int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post" data-provider-check><?php admin_action('provider_settings_check'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Check saved 20i connection</button></form>
<?php else: ?><p>Save a General API key and allow explicit checks first.</p><?php endif; ?>
<p class="hint">Wait 30 seconds between checks. Each owner can start up to 20 checks per hour.</p></section>
<section class="workspace-card"><h2>Connection availability</h2>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<?php if((int)$record['enabled']===1): ?><button class="btn outline" name="action" value="provider_settings_disable">Disable connection checks</button>
<?php elseif($record['has_credentials']): ?><button class="btn outline" name="action" value="provider_settings_enable">Allow connection checks</button><?php endif; ?>
<?php if($record['has_credentials']): ?><button class="btn outline" name="action" value="provider_settings_clear">Clear saved key and disable</button><?php endif; ?>
</form><p class="hint">Clearing removes the saved General API key and keeps the profile and history. Keep a recoverable backup of your database and private provider-master.key together.</p></section>
<?php $checkPage=max(1,min((int)($_GET['check_page']??1),100000));$attempts=provider_checks_history($profileId,$checkPage); ?>
<section class="workspace-card" data-provider-history><h2>Connection checks</h2>
<?php if(!$attempts): ?><p>No connection check has been recorded.</p><?php endif; ?>
<?php foreach(array_slice($attempts,0,20) as $attempt): ?>
<article class="workspace-card"><h3><?=esc($attempt['result_code']==='connected'?'Read verified':($attempt['state']===null?'Incomplete':ucfirst(str_replace('_',' ',$attempt['state']))))?></h3>
<p><?=esc($attempt['started_at'])?> UTC · <?=esc($attempt['actor_name']??'Former owner')?> · Saved version <?=(int)$attempt['profile_revision']?><?=((int)$attempt['profile_revision']!==(int)$record['revision'])?' · Historical settings':''?></p>
<p><?=esc(provider_checks_message($attempt['result_code']))?></p></article>
<?php endforeach; ?>
<nav aria-label="Connection check pages"><?php if($checkPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['check_page'=>$checkPage-1]))?>">Newer checks</a><?php endif; ?><?php if(count($attempts)>20): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['check_page'=>$checkPage+1]))?>">Older checks</a><?php endif; ?></nav></section>
<?php $historyPage=max(1,min((int)($_GET['history_page']??1),100000));$history=provider_settings_history($profileId,$historyPage); ?>
<section class="workspace-card"><h2>Settings history</h2>
<?php foreach(array_slice($history,0,20) as $snapshot): ?><article><h3>Version <?=(int)$snapshot['revision']?> · <?=esc($snapshot['label'])?></h3><p><?=esc($snapshot['recorded_at'])?> UTC · <?=esc($snapshot['actor_name']??'Former owner')?> · <?=esc(ucfirst($snapshot['action']))?> · <?=esc((int)$snapshot['enabled']===1?'Checks enabled':'Checks disabled')?> · <?=esc((int)$snapshot['has_credentials']===1?'Key was saved':'No key saved')?></p></article><?php endforeach; ?>
<nav aria-label="Settings history pages"><?php if($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['history_page'=>$historyPage-1]))?>">Newer settings</a><?php endif; ?><?php if(count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['history_page'=>$historyPage+1]))?>">Older settings</a><?php endif; ?></nav></section>
<?php endif; ?>
<section class="workspace-card" data-provider-inventory><h2>Hosting package inventory</h2>
<p>An explicit refresh performs one authenticated package-list read against the saved 20i profile and keeps the validated result. Observations are scoped to this saved profile and never claim to be complete account coverage.</p>
<?php if(!provider_inventory_ready()): ?>
<p>Prepare durable inventory storage first. Back up your database before adding these tables.</p>
<form method="post"><?php admin_action('provider_inventory_prepare'); ?><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare inventory storage</button></form>
<?php else:
$latest=provider_inventory_latest($profileId);$observation=provider_inventory_observation($profileId);
$packages=$observation?provider_inventory_packages($observation['id']):[];
$links=[];foreach(provider_inventory_links($profileId) as $link)$links[(int)$link['package_id']]=$link;
$clients=provider_inventory_clients();
?>
<?php if($latest): ?><p>Last refresh: <?=badge($latest['state'])?> <?=esc($latest['started_at'])?> UTC · <?=esc($latest['actor_name']??'Former owner')?> · Saved version <?=(int)$latest['profile_revision']?><?php if($latest['reason']!==''): ?> · <?=esc(str_replace('_',' ',$latest['reason']))?><?php endif; ?></p>
<?php if($observation&&(int)$observation['profile_revision']!==(int)$record['revision']): ?><p class="hint">The saved observation below was made under an earlier settings version; refresh under the current version before relying on it.</p><?php endif; ?>
<?php else: ?><p>No inventory refresh has been recorded.</p><?php endif; ?>
<?php if((int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post" data-provider-refresh><?php admin_action('provider_inventory_refresh'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Refresh package inventory</button></form>
<p class="hint">One bounded request per refresh. Wait 30 seconds between refreshes; each owner can start up to 20 per hour.</p>
<?php else: ?><p>Save a General API key and allow explicit checks before refreshing inventory.</p><?php endif; ?>
<?php if($observation): ?>
<h3>Observed packages<?=$observation['id']!==($latest['id']??null)?' — earlier than the latest refresh':''?></h3>
<p><?=esc($observation['started_at'])?> UTC · <?=esc($observation['state'])?> · coverage <?=esc($observation['coverage'])?><?php if($observation['skipped']>0): ?> · <?=(int)$observation['skipped']?> skipped<?php endif; ?></p>
<?php if($observation['state']==='partial'): ?><p class="hint">Some reported packages were skipped because they did not match the documented shape. Absence from a partial observation does not prove a package was removed.</p><?php endif; ?>
<?php foreach($packages as $package): $linked=$links[(int)$package['package_id']]??null; ?>
<article class="workspace-card"><h3><?=esc($package['name'])?> <span class="workspace-status">#<?=(int)$package['package_id']?></span></h3>
<p><?=esc($package['package_type_name']??'Type unreported')?> · <?=esc((int)$package['enabled']===1?'Enabled':'Disabled')?> · Created <?=esc($package['created_at'])?><?php if($package['names']): ?> · <?=esc(implode(', ',$package['names']))?><?php endif; ?></p>
<?php if($linked): ?><p><?=badge('linked')?> <?=esc($linked['client_name'])?> · linked <?=esc($linked['linked_at'])?> UTC by <?=esc($linked['linked_by_name']??'Former owner')?></p>
<form method="post"><?php admin_action('provider_inventory_unlink'); ?><input type="hidden" name="link_id" value="<?=esc($linked['id'])?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Unlink service</button></form>
<?php elseif($clients): ?><form method="post"><?php admin_action('provider_inventory_link'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><input type="hidden" name="package_id" value="<?=(int)$package['package_id']?>">
<label>Client account<select name="client_id" required><?php foreach($clients as $client): ?><option value="<?=(int)$client['id']?>"><?=esc($client['name'].' · '.$client['email'])?></option><?php endforeach; ?></select></label>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Link to client</button></form>
<?php endif; ?></article>
<?php endforeach; ?>
<?php $events=provider_inventory_link_events($profileId);if($events): ?><h3>Link history</h3><?php foreach(array_slice($events,0,20) as $event): ?><p><?=esc($event['recorded_at'])?> UTC · <?=esc($event['event']==='link'?'Linked':'Unlinked')?> package #<?=(int)$event['package_id']?><?=$event['client_name']?' · '.esc($event['client_name']):''?> · <?=esc($event['actor_name']??'Former owner')?></p><?php endforeach; ?><?php endif; ?>
<?php endif; ?>
<?php endif; ?></section>
<section class="workspace-card"><h2>Service capabilities</h2><p>These capabilities remain unverified and unavailable, including after a successful connection check.</p>
<ul><li>Hosting inventory, plan types and account balance</li><li>Hosting creation, changes and deletion</li><li>Domain search, registration, renewals and transfers</li><li>VPS inventory, ordering and power controls</li><li>Backup restoration and SSH access</li></ul></section>
