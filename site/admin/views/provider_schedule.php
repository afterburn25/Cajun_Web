<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
provider_settings_manager();
if(!$record||!provider_inventory_ready())return;
$raw=$_GET['schedule_page']??'1';$page=is_string($raw)&&preg_match('/\A[1-9][0-9]{0,5}\z/',$raw)?min(100000,(int)$raw):1;
try{$schedule=provider_schedule_context($page);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<section class="workspace-card" id="scheduled-refresh"><h2>Scheduled hosting refresh</h2><p class="notice error">Scheduled refresh history needs recovery. Restore the original database before running or approving another scheduled read.</p></section>';return;}
?>
<section class="workspace-card" id="scheduled-refresh" data-provider-schedule>
<h2>Scheduled hosting refresh</h2>
<p>Keep a saved observation of your hosting packages up to date. This reads the hosting list only; client assignments, orders and provider services are unchanged.</p>
<?php if(!$schedule['ready']): ?>
<p>Prepare its history first. This does not enable a schedule or contact 20i. Back up your database before preparing storage.</p>
<form method="post" data-schedule-prepare><?php admin_action('provider_inventory_schedule_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Prepare scheduled refresh</button></form>
<?php else: ?>
<p data-schedule-status><?=badge(str_replace('_',' ',$schedule['status']))?> <?=esc($schedule['message'])?></p>
<?php if($schedule['reviewed_at']): ?><p>Last approval: <?=esc(gmdate('Y-m-d H:i:s',$schedule['reviewed_at']))?> UTC · owner #<?=(int)$schedule['reviewer']?></p><?php endif; ?>
<p>Next due: <?=$schedule['next']?esc(gmdate('Y-m-d H:i:s',$schedule['next'])).' UTC':'No automatic run scheduled'?> · Consecutive incomplete reads: <?=(int)$schedule['failures']?></p>
<?php if($schedule['pending']): ?>
<p>An attempt is unfinished. Its lease ends <?=esc(gmdate('Y-m-d H:i:s',$schedule['lease']))?> UTC. An existing worker must also release its lock before recovery is allowed.</p>
<?php if($schedule['lease']<=time()): ?><form method="post" data-schedule-recover><?php admin_action('provider_inventory_schedule_recover');?><input type="hidden" name="schedule_sequence" value="<?=(int)$schedule['sequence']?>"><input type="hidden" name="schedule_pending" value="<?=esc($schedule['pending'])?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn outline">Review interrupted attempt and disable schedule</button></form><?php endif; ?>
<?php elseif((int)$record['enabled']===1&&$record['has_credentials']):
$interval=$schedule['interval'];if(($_POST['action']??'')==='provider_inventory_schedule_enable'&&is_string($_POST['schedule_interval']??null)&&in_array($_POST['schedule_interval'],['900','1800','3600','21600','86400'],true))$interval=(int)$_POST['schedule_interval']; ?>
<form method="post" data-schedule-enable><?php admin_action('provider_inventory_schedule_enable');?><input type="hidden" name="schedule_sequence" value="<?=(int)$schedule['sequence']?>"><input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<label>Check hosting list<select name="schedule_interval"><?php foreach([900=>'Every 15 minutes',1800=>'Every 30 minutes',3600=>'Every hour',21600=>'Every 6 hours',86400=>'Every day'] as $seconds=>$label):?><option value="<?=$seconds?>"<?=$seconds===$interval?' selected':''?>><?=esc($label)?></option><?php endforeach;?></select></label>
<?php admin_check('schedule_reviewed','I approve repeated read-only hosting list requests using this saved connection and my current owner access',false);admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Approve scheduled reads</button></form>
<p class="hint">Changing the saved connection, key, or approving owner’s access or password requires fresh approval. Failed or partial reads back off up to once a day.</p>
<?php endif; ?>
<form method="post" data-schedule-disable><?php admin_action('provider_inventory_schedule_disable');?><input type="hidden" name="schedule_sequence" value="<?=(int)$schedule['sequence']?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn outline">Disable scheduled reads</button></form>
<?php if(in_array($schedule['status'],['due','scheduled'],true)):?><form method="post" data-schedule-run><?php admin_action('provider_inventory_schedule_run');?><input type="hidden" name="schedule_sequence" value="<?=(int)$schedule['sequence']?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn outline">Run if due</button></form><?php endif; ?>
<details><summary>Set up the hosting scheduled task</summary><p>In your hosting control panel, schedule PHP to run this installed file every five minutes. Use the PHP command provided by your host. The job checks the approved interval before doing any work.</p><p><code style="overflow-wrap:anywhere"><?=esc(str_replace('\\','/',CWP_ROOT).'/app/jobs/run.php')?></code></p><p>Run it as the same hosting account that owns the protected provider key. Do not add passwords or API keys to the command. This file is available to PHP scheduled tasks only.</p><p>One run starts at most one request, with a 15-second HTTP limit and a 30-second dispatch budget. The host controls DNS lookup time. An unfinished attempt is retained for owner review.</p></details>
<h3>Scheduled refresh history</h3>
<?php foreach(array_slice($schedule['history'],0,20) as $event):?><p><?=esc(gmdate('Y-m-d H:i:s',$event['at']))?> UTC · <?=esc(match($event['kind']){'setup'=>'History prepared','approval'=>'Read approval saved','pause'=>'Schedule disabled','start'=>'Refresh started',default=>$event['recovered']?'Interrupted attempt reviewed':'Refresh '.str_replace('_',' ',$event['outcome']??'unfinished')})?><?php if($event['reason']):?> · <?=esc(str_replace('_',' ',$event['reason']))?><?php endif;?> · owner #<?=(int)$event['actor']?></p><?php endforeach;?>
<nav aria-label="Scheduled refresh history pages"><?php if($page>1):?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['section'=>'services','schedule_page'=>$page-1]).'#scheduled-refresh')?>">Newer scheduled activity</a><?php endif;?><?php if(count($schedule['history'])>20):?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['section'=>'services','schedule_page'=>$page+1]).'#scheduled-refresh')?>">Older scheduled activity</a><?php endif;?></nav>
<?php endif; ?></section>
