<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/marketing/appointments.php';
$availability=appointments_public();
?>
<?php if ($availability['enabled']): ?>
<input type="hidden" name="appointment_mode" value="slots"><input type="hidden" name="calendar_revision" value="<?=$v('calendar_revision',(string)$availability['revision'])?>">
<label>Available consultation time<select name="slot" required><option value="">Choose an available time</option>
<?php $selected=false;foreach ($availability['slots'] as $slot):$chosen=$v('slot')===(string)$slot['start'];$selected=$selected||$chosen; ?><option value="<?=$slot['start']?>" <?=$chosen?'selected':''?>><?=esc($slot['label'])?></option><?php endforeach; ?>
<?php if (!$selected && $v('slot')!==''): ?><option value="<?=$v('slot')?>" selected>Previously selected time — choose an available time</option><?php endif; ?></select></label>
<p class="hint">Times above use <?=esc($availability['timezone'])?> and show their UTC offset. Your request stays pending until we confirm it with you.</p>
<?php if (!$availability['slots']): ?><p class="notice">No times are currently offered within the next 30 days. <a href="<?=esc(page_url('contact'))?>">Contact us to arrange a consultation</a>.</p><?php endif; ?>
<?php else: ?>
<input type="hidden" name="appointment_mode" value="preferences">
<div class="field-grid"><label>Preferred date<input name="preferred_date" type="date" value="<?=$v('preferred_date')?>" required></label><label>Preferred time<select name="period"><?php foreach (['Morning','Afternoon','Evening','Flexible'] as $period): ?><option <?=$v('period','Flexible')===$period?'selected':''?>><?=esc($period)?></option><?php endforeach; ?></select></label></div>
<p class="hint">This requests a time. It does not reserve a calendar slot.</p>
<?php endif; ?>
<label>Your time zone<select name="timezone" required><?php foreach (DateTimeZone::listIdentifiers() as $zone): ?><option value="<?=esc($zone)?>" <?=$v('timezone','America/Chicago')===$zone?'selected':''?>><?=esc($zone)?></option><?php endforeach; ?></select></label>
