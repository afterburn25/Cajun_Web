<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\ReviewRequests\ReviewStage;

function reviews_form_value(array $values,string $key,string $fallback='',int $limit=8500): string {
    return is_string($values[$key]??null)?substr($values[$key],0,$limit):$fallback;
}
function reviews_render_report(array $report): void {
    echo '<p>Prepared by '.esc($report['prepared_name']).' · '.esc(gmdate('Y-m-d H:i:s',(int)$report['prepared_at'])).' UTC</p><div class="prose">'.nl2br(esc($report['summary'])).'</div>';
    foreach (json_decode($report['findings'],true,32,JSON_THROW_ON_ERROR) as $finding) {
        echo '<article class="workspace-card"><h3>'.esc($finding['title']).'</h3><p><strong>'.esc($finding['evidence']).'</strong></p><div class="prose">'.nl2br(esc($finding['detail'])).'</div>';
        if ($finding['source']!==null) echo '<p>Source: '.esc($finding['source']).'</p>';
        if ($finding['observed_at']!==null) echo '<p>Observed: '.esc(gmdate('Y-m-d H:i:s',(int)$finding['observed_at'])).' UTC</p>';
        echo '</article>';
    }
    if ($report['delivered_at']!==null) echo '<p class="notice">External delivery recorded: '.esc(gmdate('Y-m-d H:i:s',(int)$report['delivered_at'])).' UTC · '.esc($report['delivery_channel']).' · '.esc($report['delivery_reference']).'. This is a staff record, not a delivery confirmation from a mail provider.</p>';
}
$historyPage=max(1,min((int)($_GET['history_page']??1),100000));$data=reviews_get($id,$historyPage);$row=$data['row'];$report=$data['report'];
$post=is_string($_POST['id']??null) && (int)$_POST['id']===$id?$_POST:[];
?>
<p><a href="<?=esc(admin_url('lead',['id'=>$id]))?>">← Inquiry <?=esc($lead['reference'])?></a></p>
<p class="notice">Human website review. These records do not fetch websites, generate audit scores or send reports.</p>
<p><?=esc($lead['name'])?> · <?=esc($lead['email'])?> · Contact consent: <?=(int)$lead['consent']===1?'Recorded':'Not recorded'?>.</p>
<?php if (!$row): ?>
<form method="post" class="workspace-card"><?php admin_action('reviews_adopt'); ?><input type="hidden" name="id" value="<?=$id?>">
<h2>Start this review</h2><p>Check the original inquiry and enter the website address. No structured address is guessed from older inquiry text.</p>
<?php admin_input('website','Website address',reviews_form_value($post,'website'),'text','required maxlength="500" inputmode="url"'); ?>
<button class="btn gold">Start review tracking</button></form>
<?php else:
$revision=(int)$row['revision'];if (is_string($post['revision']??null) && ctype_digit($post['revision'])) $revision=(int)$post['revision'];
$stages=['received'=>'Received','in-review'=>'In review','report-drafted'=>'Report drafted','delivered'=>'External delivery recorded','closed'=>'Closed'];
$controls=function(string $action) use ($id,$revision): void {admin_action($action);echo '<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="revision" value="'.$revision.'">';};
?>
<section class="workspace-card"><h2><?=esc($stages[$row['stage']])?></h2><p><?=esc($row['website'])?></p><p>Saved version <?=(int)$row['revision']?> · Inquiry sales status remains separate.</p>
<?php if (!(new \CajunWebPro\Foundation\ReviewRequests\WebsiteAddress($row['website']))->isFetchCandidate()): ?><p class="notice">This address has a private or reserved host flag. No automatic connection is available.</p><?php endif; ?>
<?php $allowed=array_values(array_diff(ReviewStage::allowedFrom($row['stage']),['delivered']));if (!$report) $allowed=array_values(array_diff($allowed,['report-drafted']));if ($allowed): ?>
<form method="post"><?php $controls('reviews_stage');admin_select('stage','Next review stage',array_intersect_key($stages,array_flip($allowed)),reviews_form_value($post,'stage',$allowed[0])); ?><button class="btn outline">Change review stage</button></form>
<?php endif; ?></section>
<?php if (in_array($row['stage'],['in-review','report-drafted'],true)):
$editing=($post['action']??'')==='reviews_report';$items=[];
if ($editing) $items=reviews_post_findings();
elseif ($report) foreach (json_decode($report['findings'],true,32,JSON_THROW_ON_ERROR) as $finding) {$finding['observed_at']=$finding['observed_at']===null?'':gmdate('Y-m-d\TH:i:s',(int)$finding['observed_at']);$items[]=$finding;}
if (!$items || ($editing && ($post['add_finding']??'')==='1' && count($items)<20)) $items[]=['title'=>'','detail'=>'','evidence'=>'human-review','observed_at'=>'','source'=>''];
if ($editing && is_string($post['remove_finding']??null) && ctype_digit($post['remove_finding']) && count($items)>1 && (int)$post['remove_finding']<count($items)) array_splice($items,(int)$post['remove_finding'],1);
?>
<form method="post" class="workspace-card" data-review-editor><?php $controls('reviews_report'); ?><h2>Report draft</h2>
<p>Write only findings supported by your work. Editing a drafted report returns it to In review.</p>
<?php admin_textarea('summary','Report summary',$editing?reviews_form_value($post,'summary'):($report['summary']??''),5,8000); ?>
<?php foreach ($items as $i=>$finding): $finding=is_array($finding)?$finding:[]; ?>
<details class="workspace-card" <?=($i===count($items)-1 || $error!=='')?'open':''?>><summary>Finding <?=$i+1?><?=reviews_form_value($finding,'title')!==''?' · '.esc(reviews_form_value($finding,'title')):''?></summary>
<?php admin_input('findings['.$i.'][title]','Finding title',reviews_form_value($finding,'title'),'text','required maxlength="160"');
admin_textarea('findings['.$i.'][detail]','Finding detail',reviews_form_value($finding,'detail'),4,5000);
admin_select('findings['.$i.'][evidence]','Evidence type',['human-review'=>'Human review / opinion','lab-data'=>'Lab measurement','field-data'=>'Field measurement','absent-data'=>'Checked but missing data'],reviews_form_value($finding,'evidence','human-review'));
admin_input('findings['.$i.'][source]','Source (required for measured or missing data)',reviews_form_value($finding,'source'),'text','maxlength="500"');
admin_input('findings['.$i.'][observed_at]','Observation time in UTC (required for measured or missing data)',reviews_form_value($finding,'observed_at'),'datetime-local','step="1"'); ?>
<?php if (count($items)>1): ?><button class="btn outline" name="remove_finding" value="<?=$i?>" formnovalidate>Remove this finding from draft</button><?php endif; ?></details><?php endforeach; ?>
<button class="btn gold">Save report draft</button> <?php if (count($items)<20): ?><button class="btn outline" name="add_finding" value="1" formnovalidate>Add another finding</button><?php endif; ?>
</form>
<?php endif; ?>
<?php if ($report): ?><section class="workspace-card"><h2>Saved report</h2><?php reviews_render_report($report); ?></section><?php endif; ?>
<?php if ($row['stage']==='report-drafted' && $report && (int)$lead['consent']===1): ?>
<form method="post" class="workspace-card" data-review-delivery><?php $controls('reviews_delivery'); ?><h2>Record external delivery</h2><p>Use this only after you delivered this exact saved report outside CajunWebPro. This form sends nothing. Contact consent and your current password are checked again.</p>
<?php admin_input('delivered_at','Actual delivery time in UTC',reviews_form_value($post,'delivered_at',gmdate('Y-m-d\TH:i:s')),'datetime-local','required step="1"');
admin_select('channel','External delivery channel',['email'=>'External email','client-portal'=>'External client portal','in-person'=>'In person','other'=>'Other'],reviews_form_value($post,'channel','email'));
admin_input('reference','Delivery reference or note',reviews_form_value($post,'reference'),'text','required maxlength="500"');
admin_input('current_password','Your current password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Record already delivered report</button></form>
<?php elseif ($row['stage']==='report-drafted' && (int)$lead['consent']!==1): ?><p class="notice">External delivery cannot be recorded without the contact's consent.</p><?php endif; ?>
<h2>Review history</h2>
<?php foreach (array_slice($data['history'],0,20) as $history): $saved=json_decode($history['snapshot'],true,32,JSON_THROW_ON_ERROR); ?>
<details class="workspace-card"><summary>Version <?=(int)$history['revision']?> · <?=esc($history['action'])?> · <?=esc($history['recorded_at'])?> UTC · <?=esc($history['actor_name']??((int)$history['actor_id']===0?'Public intake':'Former administrator'))?></summary><p><?=esc($saved['review']['website'])?> · <?=esc($saved['review']['stage'])?></p><?php if ($saved['report']) reviews_render_report($saved['report']); ?></details>
<?php endforeach; ?>
<nav aria-label="Review history pages"><?php if ($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('website-review',['id'=>$id,'history_page'=>$historyPage-1]))?>">Newer history</a><?php endif; ?><?php if (count($data['history'])>20): ?><a class="btn outline" href="<?=esc(admin_url('website-review',['id'=>$id,'history_page'=>$historyPage+1]))?>">Older history</a><?php endif; ?></nav>
<?php endif; ?>
