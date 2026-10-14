<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$accounts=$data['accounts'];
?>
<p class="hint">Private drafts are saved on this website for your sending accounts. Saving, previewing and inserting a template do not send email or synchronize a server Drafts folder.</p>
<p><a class="text-link" href="<?=esc(admin_url('mailboxes'))?>">My email accounts</a></p>
<?php if($view==='mail-drafts'): ?>
<section class="workspace-card">
<form method="get"><input type="hidden" name="view" value="mail-drafts">
<input type="hidden" name="account_page" value="<?=(int)$data['accountPage']?>">
<?php $choices=[];foreach($accounts as $id=>$account)$choices[$id]=$account['label'].' · '.$account['address'];admin_select('account_id','Sending account',$choices,$data['account']);admin_select('status','Show',['draft'=>'Saved drafts','discarded'=>'Discarded drafts'],$data['status']);?>
<button class="btn outline">Show drafts</button></form>
<?php if($data['account']!==''): ?><p><a class="btn gold" href="<?=esc(admin_url('mail-draft',['account_id'=>$data['account']]))?>">New draft</a></p><?php endif; ?>
<nav aria-label="Sending account pages">
<?php if($data['accountPage']>1): ?><a class="text-link" href="<?=esc(admin_url('mail-drafts',['account_page'=>$data['accountPage']-1]))?>">Previous accounts</a><?php endif; ?>
<?php if($data['moreAccounts']): ?><a class="text-link" href="<?=esc(admin_url('mail-drafts',['account_page'=>$data['accountPage']+1]))?>">More accounts</a><?php endif; ?>
</nav></section>
<?php if(!$accounts): ?><p class="notice">No assigned sending account appears on this page. An owner can review your email account assignments.</p><?php endif; ?>
<div class="workspace-grid">
<?php foreach(array_slice($data['rows'],0,20) as $row): ?>
<article class="workspace-card"><h2><a href="<?=esc(admin_url('mail-draft',['draft_id'=>$row['id']]))?>"><?=esc($row['subject']!==''?$row['subject']:'Untitled draft')?></a></h2>
<p><?=badge($row['status'])?> · Saved version <?=(int)$row['revision']?></p><p>Updated <?=esc($row['updated_at'])?> UTC</p></article>
<?php endforeach; ?>
</div>
<?php if($accounts&&!$data['rows']): ?><p class="notice">You have no drafts in this view.</p><?php endif; ?>
<nav aria-label="Draft pages">
<?php if($data['page']>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-drafts',['account_id'=>$data['account'],'status'=>$data['status'],'p'=>$data['page']-1]))?>">Previous drafts</a><?php endif; ?>
<?php if(count($data['rows'])>20): ?><a class="btn outline" href="<?=esc(admin_url('mail-drafts',['account_id'=>$data['account'],'status'=>$data['status'],'p'=>$data['page']+1]))?>">Older drafts</a><?php endif; ?>
</nav>
<?php else: $row=$data['row'];$values=$data['values']; ?>
<p><a class="text-link" href="<?=esc(admin_url('mail-drafts',['account_id'=>$data['account']]))?>">Back to my drafts</a></p>
<?php if($row&&$row['status']==='draft'): ?><p><a class="btn outline" href="<?=esc(admin_url('mail-send',['draft_id'=>$data['id']]))?>">Review saved message for sending</a></p><?php endif; ?>
<?php if($row): ?><p><?=badge($row['status'])?> · Saved version <?=(int)$row['revision']?> · Updated <?=esc($row['updated_at'])?> UTC</p><?php endif; ?>
<?php if($row&&(int)$row['revision']!==$data['revision']): ?><p class="notice warning">Your entered text is preserved with its earlier version. Open the saved draft in a separate tab to compare before making a new edit.</p><p><a class="btn outline" target="_blank" rel="noopener" href="<?=esc(admin_url('mail-draft',['draft_id'=>$data['id']]))?>">Open current saved version</a></p><?php endif; ?>
<?php if($data['unsaved']!==null): ?>
<section class="workspace-card" data-mail-draft-unsaved><h2>Your unsaved text</h2>
<p class="notice warning">This draft was discarded before your edit could be saved. Copy any text you want to keep into a new draft. The discarded draft below still contains its last saved text.</p>
<?php foreach(['to'=>'To','cc'=>'Cc','bcc'=>'Bcc','subject'=>'Subject'] as $key=>$label)admin_input($key,$label,$data['unsaved'][$key],'text','readonly'); ?>
<label>Unsaved message<textarea rows="12" readonly><?=esc($data['unsaved']['body'])?></textarea></label>
</section>
<?php endif; ?>
<?php if(!$accounts): ?><p class="notice">An owner must assign you a sending account before you can prepare a draft.</p>
<?php elseif($data['editable']): ?>
<section class="workspace-card"><h2><?=$row?'Edit your draft':'New private draft'?></h2>
<form method="post" action="<?=esc(admin_url('mail-draft',$row?['draft_id'=>$data['id']]:['account_id'=>$data['account']]))?>" data-mail-draft-editor>
<?=csrf_field()?><input type="hidden" name="draft_id" value="<?=esc($data['id'])?>"><input type="hidden" name="revision" value="<?=(int)$data['revision']?>">
<?php
$senders=[];foreach($accounts as $account)$senders[$account['id'].':'.$account['revision']]=$account['label'].' · '.$account['address'];
$staleSender=$data['sender']!==''&&!array_key_exists($data['sender'],$senders);
?>
<label>From account<select name="sender" required>
<?php if($staleSender): ?><option value="<?=esc($data['sender'])?>" selected disabled>Earlier sender version — choose the current account</option><?php endif; ?>
<?php foreach($senders as $ref=>$label): ?><option value="<?=esc($ref)?>" <?=$data['sender']===$ref?'selected':''?>><?=esc($label)?></option><?php endforeach; ?>
</select></label>
<?php if($row): ?>
<p class="hint">Changing the sending account or accepting changed account settings requires your confirmation.</p>
<?php admin_check('confirm_sender','I reviewed and confirm any change to the sending account',($_POST['confirm_sender']??'')==='1');endif; ?>
<?php if($data['accountPage']>1||$data['moreAccounts']): ?>
<label>Sending account page<input type="number" name="account_page" min="1" max="100000" value="<?=(int)$data['accountPage']?>"></label>
<button class="btn outline" name="action" value="mail_drafts_accounts">Show account page</button>
<?php else: ?><input type="hidden" name="account_page" value="<?=(int)$data['accountPage']?>">
<?php endif; ?>
<p class="hint">Use bare email addresses separated by commas. Up to 20 recipients across To, Cc and Bcc. Empty fields are allowed while drafting.</p>
<?php foreach(['to'=>'To','cc'=>'Cc','bcc'=>'Bcc'] as $key=>$label)admin_input($key,$label,$values[$key],'text','maxlength="5600" autocomplete="off" spellcheck="false"');
admin_input('subject','Subject',$values['subject'],'text','maxlength="200"');admin_textarea('body','Message',$values['body'],12,40000); ?>
<p class="hint">Plain text only. Subject: up to 200 UTF-8 bytes. Message: up to 20,000 UTF-8 bytes. Non-English characters may use more than one byte.</p>
<button class="btn gold" name="action" value="mail_drafts_save">Save draft</button>
<button class="btn outline" name="action" value="mail_drafts_preview">Preview draft</button>
<?php if(staff_can('mail.templates.manage',$data['actor'])&&mail_templates_ready()): ?>
<details><summary>Insert an email template</summary><p>Choose an active template. Insertion replaces the subject and message with an editable copy; recipients stay as entered.</p>
<?php admin_input('template_search','Find an active template',$data['templateSearch'],'search','maxlength="120"');admin_input('template_page','Template page',$data['templatePage'],'number','min="1" max="100000"');?>
<button class="btn outline" name="action" value="mail_drafts_templates">Find templates</button>
<?php $options=[''=>'Choose a template'];foreach(array_slice($data['templates'],0,20) as $template)$options[$template['id'].':'.$template['revision']]=$template['name'].' · Version '.$template['revision'];admin_select('template','Active template',$options,is_string($_POST['template']??null)?$_POST['template']:''); ?>
<?php if(count($data['templates'])>20): ?><p class="hint">More templates are available on the next page.</p><?php endif; ?>
<?php foreach(['client_name'=>'Client name','project_name'=>'Project name','studio_name'=>'Studio name'] as $key=>$label)admin_input('sample_'.$key,$label,is_string($_POST['sample_'.$key]??null)?substr($_POST['sample_'.$key],0,200):'','text','maxlength="200"'); ?>
<p class="hint">Enter any values the template needs. The portal sign-in address comes from this website.</p>
<?php admin_check('replace_text','Replace the current subject and message with this template',false); ?>
<button class="btn outline" name="action" value="mail_drafts_insert">Insert template</button></details>
<?php endif; ?>
</form>
<?php if($row): ?><form method="post" action="<?=esc(admin_url('mail-draft',['draft_id'=>$data['id']]))?>"><?php admin_action('mail_drafts_discard');?><input type="hidden" name="draft_id" value="<?=esc($data['id'])?>"><input type="hidden" name="revision" value="<?=(int)$data['revision']?>">
<p class="hint">Discard keeps the last saved text and its history in your discarded drafts. Unsaved changes are not saved.</p><button class="btn outline">Discard saved draft</button></form><?php endif; ?>
</section>
<?php else: ?>
<section class="workspace-card"><h2>Discarded draft</h2><p>This retained draft is read-only.</p>
<?php foreach(['to'=>'To','cc'=>'Cc','bcc'=>'Bcc','subject'=>'Subject'] as $key=>$label): ?><p><strong><?=esc($label)?>:</strong> <?=esc($values[$key])?></p><?php endforeach; ?>
<div class="formatted"><?=nl2br(esc($values['body']))?></div></section>
<?php endif; ?>
<?php if($data['preview']): $preview=$data['preview']; ?><section class="workspace-card" data-mail-draft-preview><h2>Draft preview</h2><p class="hint">This is a preview of your entered text. It has not been sent.</p>
<?php [$senderId]=mail_drafts_form_reference($data['sender']); ?><p><strong>From:</strong> <?=esc($accounts[$senderId]['address'])?></p>
<?php foreach(['to'=>'To','cc'=>'Cc','bcc'=>'Bcc'] as $key=>$label): ?><p><strong><?=esc($label)?>:</strong> <?=esc(implode(', ',$preview->$key))?></p><?php endforeach; ?>
<h3><?=esc($preview->subject)?></h3><div class="formatted"><?=nl2br(esc($preview->body))?></div></section><?php endif; ?>
<?php if($row): ?><section class="workspace-card"><h2>Draft history</h2>
<?php foreach(array_slice($data['history'],0,20) as $event): ?><p>Version <?=(int)$event['revision']?> · <?=esc($event['action'])?> · <?=esc($event['recorded_at'])?> UTC</p><?php endforeach; ?>
<nav aria-label="Draft history pages">
<?php if($data['historyPage']>1): ?><a class="text-link" href="<?=esc(admin_url('mail-draft',['draft_id'=>$data['id'],'history_page'=>$data['historyPage']-1]))?>">Newer history</a><?php endif; ?>
<?php if(count($data['history'])>20): ?><a class="text-link" href="<?=esc(admin_url('mail-draft',['draft_id'=>$data['id'],'history_page'=>$data['historyPage']+1]))?>">Older history</a><?php endif; ?>
</nav></section><?php endif; ?>
<?php endif; ?>
