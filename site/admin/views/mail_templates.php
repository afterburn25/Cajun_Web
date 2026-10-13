<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<p class="hint">Create reusable plain-text messages and check their wording with sample values. This library does not send email.</p>
<?php if ($view==='mail-templates'):
    $filter=is_string($_GET['status']??null)?$_GET['status']:'all';
    $search=is_string($_GET['search']??null)?$_GET['search']:'';
    $page=max(1,min((int)($_GET['p']??1),100000));
    $rows=mail_templates_list($filter,$search,$page);$more=count($rows)>20;
?>
<div class="workspace-card"><a class="btn gold" href="<?=esc(admin_url('mail-template'))?>">Create template</a>
<form method="get"><input type="hidden" name="view" value="mail-templates">
<?php admin_input('search','Find a template',$search,'search','maxlength="120"');admin_select('status','Show',['all'=>'All visible templates','draft'=>'Drafts','active'=>'Active','archived'=>'Archived'],$filter);?>
<button class="btn outline">Filter templates</button></form></div>
<div class="workspace-grid">
<?php foreach (array_slice($rows,0,20) as $row): ?>
<article class="workspace-card"><h2><a href="<?=esc(admin_url('mail-template',['template_id'=>$row['id']]))?>"><?=esc($row['name'])?></a></h2>
<p><?=esc($row['subject'])?></p><p><?=badge($row['status'])?> · <?=esc($row['visibility']==='personal'?'Only you':'Shared with template staff')?> · Version <?=(int)$row['revision']?></p></article>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?><p class="notice">No templates match this view.</p><?php endif; ?>
<nav aria-label="Template pages">
<?php if ($page>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-templates',['status'=>$filter,'search'=>$search,'p'=>$page-1]))?>">Previous</a><?php endif; ?>
<?php if ($more): ?><a class="btn outline" href="<?=esc(admin_url('mail-templates',['status'=>$filter,'search'=>$search,'p'=>$page+1]))?>">Next</a><?php endif; ?>
</nav>
<?php else:
    $templateId=is_string($_GET['template_id']??null)?$_GET['template_id']:'';
    $record=$templateId!==''?mail_templates_get($templateId):null;
    $row=$record['row']??['name'=>'','subject'=>'','body'=>'','status'=>'draft','visibility'=>'personal','revision'=>0];
    $editable=(!$record || $record['editable']) && $row['status']!=='archived';
    $values=$row;$revision=(int)$row['revision'];
    // Preserve submitted text after failed validation, conflict or preview. Never update its stale revision implicitly.
    if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['mail_templates_create','mail_templates_edit','mail_templates_preview'],true) && $editable) {
        foreach (['name','subject','body','visibility'] as $field) if (is_string($_POST[$field]??null)) $values[$field]=$_POST[$field];
        if (is_string($_POST['revision']??null)) $revision=(int)$_POST['revision'];
    }
    $historyPage=max(1,min((int)($_GET['history_page']??1),100000));
?>
<p><a class="text-link" href="<?=esc(admin_url('mail-templates'))?>">← Template library</a></p>
<?php if ($record): ?><p><?=badge($row['status'])?> · <?=esc($row['visibility']==='personal'?'Personal: visible only to its creator':'Shared with staff granted template access')?> · Saved version <?=(int)$row['revision']?></p><?php endif; ?>
<div class="workspace-grid"><section class="workspace-card"><h2><?=$record?'Message text':'New template'?></h2>
<form method="post" data-template-editor><?=csrf_field()?>
<input type="hidden" name="template_id" value="<?=esc($templateId)?>"><input type="hidden" name="revision" value="<?=$revision?>">
<?php if ($editable):
    admin_input('name','Template name',$values['name'],'text','required maxlength="120"');
    admin_input('subject','Subject',$values['subject'],'text','required maxlength="200"');
    admin_textarea('body','Message',$values['body'],12,20000);
    if (!$record) admin_select('visibility','Visibility',['personal'=>'Personal — only you','shared'=>'Shared with template staff'],$values['visibility']);
    if ($row['status']==='active'): ?><p class="hint">Saving changes creates a draft. Review it, then activate it again. Earlier versions remain in history.</p><?php endif; ?>
<button class="btn gold" name="action" value="<?=$record?'mail_templates_edit':'mail_templates_create'?>">Save draft</button>
<?php else: ?><h3><?=esc($row['name'])?></h3><p><strong><?=esc($row['subject'])?></strong></p><div class="formatted"><?=nl2br(esc($row['body']))?></div><p class="hint">Duplicate this template to create an editable personal draft.</p><?php endif; ?>
<h3>Preview with sample values</h3><p class="hint">Blank sample fields are shown as missing. No client records are loaded.</p>
<?php foreach (['client_name'=>'Client name','project_name'=>'Project name','studio_name'=>'Studio name'] as $key=>$label) {
    $sample=is_string($_POST['sample_'.$key]??null)?$_POST['sample_'.$key]:'';
    admin_input('sample_'.$key,$label,$sample,'text','maxlength="200"');
} ?>
<button class="btn outline" name="action" value="mail_templates_preview">Preview message</button>
</form>
<?php if ($record && $record['editable'] && $row['status']!=='archived'): ?>
<form method="post"><?php admin_action($row['status']==='draft'?'mail_templates_activate':'mail_templates_archive'); ?>
<input type="hidden" name="template_id" value="<?=esc($templateId)?>"><input type="hidden" name="revision" value="<?=(int)$row['revision']?>">
<button class="btn outline"><?=$row['status']==='draft'?'Activate saved draft':'Archive template'?></button></form>
<?php if ($row['status']==='draft'): ?><form method="post"><?php admin_action('mail_templates_archive'); ?><input type="hidden" name="template_id" value="<?=esc($templateId)?>"><input type="hidden" name="revision" value="<?=(int)$row['revision']?>"><button class="btn outline">Archive saved draft</button></form><?php endif; ?>
<?php endif; ?>
<?php if ($record): ?><form method="post"><?php admin_action('mail_templates_duplicate'); ?><input type="hidden" name="template_id" value="<?=esc($templateId)?>"><input type="hidden" name="revision" value="<?=(int)$row['revision']?>"><?php admin_input('copy_name','Name for your copy','','text','required maxlength="120"'); ?><button class="btn outline">Duplicate as personal draft</button></form><?php endif; ?>
</section><section class="workspace-card"><h2>Available fields</h2>
<p>Insert an exact field below into your subject or message. Double braces are reserved for fields; use ordinary single braces for literal notes. HTML is displayed as text.</p>
<dl><?php foreach (mail_templates_registry()->all() as $field=>$description): ?><dt><code>{{<?=esc($field)?>}}</code></dt><dd><?=esc($description)?></dd><?php endforeach; ?></dl>
<p class="hint">The client sign-in address is filled from this website. Templates do not contain authentication or password-reset links.</p>
<?php if (isset($GLOBALS['mail_template_preview'])): $preview=$GLOBALS['mail_template_preview']; ?>
<section data-template-preview aria-live="polite"><h2>Message preview</h2>
<?php if ($preview['unresolved']): ?><p class="notice warning">Missing sample values: <?=esc(implode(', ',$preview['unresolved']))?>.</p><?php else: ?><p class="notice success">All template fields have sample values.</p><?php endif; ?>
<h3><?=esc($preview['subject'])?></h3><div class="formatted"><?=nl2br(esc($preview['body']))?></div><p class="hint">Preview only. No email was sent.</p></section>
<?php endif; ?>
</section></div>
<?php if ($record): $history=mail_templates_history($templateId,$historyPage);$more=count($history)>20; ?>
<h2>Version history</h2><p class="hint">Saved versions are retained. To reuse an older message, copy its text into a new draft.</p>
<?php foreach (array_slice($history,0,20) as $snapshot): ?><details class="workspace-card"><summary>Version <?=(int)$snapshot['revision']?> · <?=esc($snapshot['action'])?> · <?=esc($snapshot['recorded_at'])?> UTC · <?=esc($snapshot['actor_name']??'Former administrator')?></summary><h3><?=esc($snapshot['name'])?></h3><p><strong><?=esc($snapshot['subject'])?></strong></p><div class="formatted"><?=nl2br(esc($snapshot['body']))?></div></details><?php endforeach; ?>
<nav aria-label="Template history pages"><?php if ($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-template',['template_id'=>$templateId,'history_page'=>$historyPage-1]))?>">Newer versions</a><?php endif; ?><?php if ($more): ?><a class="btn outline" href="<?=esc(admin_url('mail-template',['template_id'=>$templateId,'history_page'=>$historyPage+1]))?>">Older versions</a><?php endif; ?></nav>
<?php endif; endif; ?>
