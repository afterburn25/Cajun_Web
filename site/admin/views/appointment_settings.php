<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$settings=appointments_settings();$submitted=($_POST['action']??'')==='appointments_configure';$values=$submitted?$_POST:array_map(fn($v)=>(string)$v,$settings);
$revision=$submitted?appointments_value($values,'revision',(string)$settings['revision']):(string)$settings['revision'];
$days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];$hours=[];
foreach (json_decode($settings['rules'],true,16,JSON_THROW_ON_ERROR) as $rule) $hours[$rule['day']]=['enabled'=>'1','start'=>sprintf('%02d:%02d',intdiv($rule['start'],60),$rule['start']%60),'end'=>sprintf('%02d:%02d',intdiv($rule['end'],60),$rule['end']%60)];
if ($submitted) $hours=is_array($_POST['hours']??null)?$_POST['hours']:[];
?>
<p class="notice">One studio calendar. Requests stay pending until staff confirm them. This calendar sends no messages or invitations and has no external calendar connection.</p>
<form method="post" class="workspace-card" data-appointment-settings><?php admin_action('appointments_configure'); ?><input type="hidden" name="revision" value="<?=esc($revision)?>">
<h2>Studio availability</h2><p>Saved version <?=(int)$settings['revision']?>. Choose your hours before enabling public slot requests. Existing appointments are retained when settings change.</p>
<?php admin_select('timezone','Studio time zone',array_combine(DateTimeZone::listIdentifiers(),DateTimeZone::listIdentifiers()),appointments_value($values,'timezone','UTC'));
admin_select('slot_minutes','Consultation length',[15=>'15 minutes',30=>'30 minutes',45=>'45 minutes',60=>'60 minutes',90=>'90 minutes',120=>'120 minutes'],appointments_value($values,'slot_minutes','30'));
admin_input('buffer_minutes','Minutes between appointments',appointments_value($values,'buffer_minutes','15'),'number','required min="0" max="120"');
admin_input('notice_minutes','Minimum notice in minutes for new requests',appointments_value($values,'notice_minutes','120'),'number','required min="0" max="10080"'); ?>
<p>Offered times cover the next 30 days. Enter hours in 24-hour format, such as 09:00 and 17:00. Use 24:00 only for the end of a day.</p>
<?php foreach ($days as $day=>$label): $hour=is_array($hours[$day]??null)?$hours[$day]:[]; ?><fieldset class="workspace-card"><legend><?=esc($label)?></legend>
<?php admin_check('hours['.$day.'][enabled]','Offer this weekday',appointments_value($hour,'enabled')==='1');admin_input('hours['.$day.'][start]','Start time',appointments_value($hour,'start'),'text','maxlength="5" placeholder="09:00"');admin_input('hours['.$day.'][end]','End time',appointments_value($hour,'end'),'text','maxlength="5" placeholder="17:00"'); ?></fieldset><?php endforeach; ?>
<?php admin_check('enabled','Enable public appointment requests',appointments_value($values,'enabled')==='1');admin_input('current_password','Your current password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Save availability</button></form>
<?php $page=max(1,min((int)($_GET['history_page']??1),100000));$history=all('SELECT h.*,u.name AS actor_name FROM '.table('appointment_settings_revisions').' h LEFT JOIN '.table('users').' u ON u.id=h.actor_id ORDER BY h.revision DESC LIMIT 21 OFFSET '.(($page-1)*20)); ?>
<h2>Availability history</h2>
<?php foreach (array_slice($history,0,20) as $item): $saved=json_decode($item['snapshot'],true,16,JSON_THROW_ON_ERROR); ?><details class="workspace-card"><summary>Version <?=(int)$item['revision']?> · <?=esc($item['recorded_at'])?> UTC · <?=esc($item['actor_name']??((int)$item['actor_id']===0?'Initial setup':'Former administrator'))?></summary><p><?=esc($saved['timezone'])?> · <?=(int)$saved['slot_minutes']?> minutes · Buffer <?=(int)$saved['buffer_minutes']?> minutes · Notice <?=(int)$saved['notice_minutes']?> minutes · <?=(int)$saved['enabled']?'Enabled':'Disabled'?></p><ul><?php foreach (json_decode($saved['rules'],true,16,JSON_THROW_ON_ERROR) as $rule): ?><li><?=esc($days[$rule['day']])?> · <?=esc(sprintf('%02d:%02d – %02d:%02d',intdiv($rule['start'],60),$rule['start']%60,intdiv($rule['end'],60),$rule['end']%60))?></li><?php endforeach; ?></ul></details><?php endforeach; ?>
<nav aria-label="Availability history pages"><?php if ($page>1): ?><a class="btn outline" href="<?=esc(admin_url('consultation-settings',['history_page'=>$page-1]))?>">Newer history</a><?php endif; ?><?php if (count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('consultation-settings',['history_page'=>$page+1]))?>">Older history</a><?php endif; ?></nav>
