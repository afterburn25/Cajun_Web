<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<div class="provider-hub-group-heading" id="twentyi-connection"><span class="eyebrow">01 / CONNECT</span><h2>Connection &amp; API key</h2><p>Manage your saved key, test one read, and review connection history. Nothing in this section places an order.</p></div>
<section class="workspace-card"><h2>Your 20i account</h2>
<p>Owners can save a General API key and explicitly check whether 20i accepts it for the package-list read. This uses your live 20i account.</p>
<p>This saves your connection only. Import plans in Service catalog; publish reviewed offers in Hosting offers.</p>
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
<section class="workspace-card" id="twentyi-check"><h2>Check the saved connection</h2>
<p>This makes one read request to 20i. A successful check confirms that read at the recorded time. Saving settings does not run a check.</p>
<?php if((int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post" data-provider-check><?php admin_action('provider_settings_check'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Check saved 20i connection</button></form>
<?php else: ?><p>Save a General API key and allow explicit checks first.</p><?php endif; ?>
<p class="hint">Wait 30 seconds between checks. Each owner can start up to 20 checks per hour.</p></section>
<details class="workspace-card provider-hub-utility"><summary>Advanced connection maintenance · enable, disable or clear saved key</summary><div class="provider-hub-utility-body"><h2>Connection availability</h2>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<?php if((int)$record['enabled']===1): ?><button class="btn outline" name="action" value="provider_settings_disable">Disable connection checks</button>
<?php elseif($record['has_credentials']): ?><button class="btn outline" name="action" value="provider_settings_enable">Allow connection checks</button><?php endif; ?>
<?php if($record['has_credentials']): ?><button class="btn outline" name="action" value="provider_settings_clear">Clear saved key and disable</button><?php endif; ?>
</form><p class="hint">Clearing removes the saved General API key and keeps the profile and history. Keep a recoverable backup of your database and private provider-master.key together.</p></div></details>
<?php $checkPage=max(1,min((int)($_GET['check_page']??1),100000));$attempts=provider_checks_history($profileId,$checkPage); ?>
<details class="workspace-card provider-hub-utility" <?=isset($_GET['check_page'])?'open':''?>><summary>Past connection checks and diagnostics</summary><div class="provider-hub-utility-body" data-provider-history><h2>Connection checks</h2>
<?php if(!$attempts): ?><p>No connection check has been recorded.</p><?php endif; ?>
<?php foreach(array_slice($attempts,0,20) as $attempt): ?>
<article class="workspace-card"><h3><?=esc($attempt['result_code']==='connected'?'Read verified':($attempt['state']===null?'Incomplete':ucfirst(str_replace('_',' ',$attempt['state']))))?></h3>
<p><?=esc($attempt['started_at'])?> UTC · <?=esc($attempt['actor_name']??'Former owner')?> · Saved version <?=(int)$attempt['profile_revision']?><?=((int)$attempt['profile_revision']!==(int)$record['revision'])?' · Historical settings':''?></p>
<p><?=esc(provider_checks_message($attempt['result_code']))?></p></article>
<?php endforeach; ?>
<nav aria-label="Connection check pages"><?php if($checkPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['section'=>'connection','check_page'=>$checkPage-1]))?>">Newer checks</a><?php endif; ?><?php if(count($attempts)>20): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['section'=>'connection','check_page'=>$checkPage+1]))?>">Older checks</a><?php endif; ?></nav></div></details>
<?php $historyPage=max(1,min((int)($_GET['history_page']??1),100000));$history=provider_settings_history($profileId,$historyPage); ?>
<details class="workspace-card provider-hub-utility" <?=isset($_GET['history_page'])?'open':''?>><summary>Saved key and connection change history</summary><div class="provider-hub-utility-body"><h2>Settings history</h2>
<?php foreach(array_slice($history,0,20) as $snapshot): ?><article><h3>Version <?=(int)$snapshot['revision']?> · <?=esc($snapshot['label'])?></h3><p><?=esc($snapshot['recorded_at'])?> UTC · <?=esc($snapshot['actor_name']??'Former owner')?> · <?=esc(ucfirst($snapshot['action']))?> · <?=esc((int)$snapshot['enabled']===1?'Checks enabled':'Checks disabled')?> · <?=esc((int)$snapshot['has_credentials']===1?'Key was saved':'No key saved')?></p></article><?php endforeach; ?>
<nav aria-label="Settings history pages"><?php if($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['section'=>'connection','history_page'=>$historyPage-1]))?>">Newer settings</a><?php endif; ?><?php if(count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['section'=>'connection','history_page'=>$historyPage+1]))?>">Older settings</a><?php endif; ?></nav></div></details>
<?php endif; ?>
