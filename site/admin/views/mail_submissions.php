<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<p class="hint">This private history records sending attempts from this website. It does not synchronize a server Sent folder or confirm recipient delivery.</p>
<p><a class="text-link" href="<?=esc(admin_url('mailboxes'))?>">My email accounts</a> · <a class="text-link" href="<?=esc(admin_url('mail-drafts'))?>">My saved drafts</a></p>
<?php if($view==='mail-submissions'): ?>
<section class="workspace-card"><form method="get"><input type="hidden" name="view" value="mail-submissions"><input type="hidden" name="account_page" value="<?=(int)$data['accountPage']?>">
<?php $options=[];foreach($data['accounts'] as $id=>$account)$options[$id]=$account['label'].' · '.$account['address'];admin_select('account_id','Sending account',$options,$data['account']); ?><button class="btn outline">Show history</button></form>
<nav aria-label="Sending account pages">
<?php if($data['accountPage']>1): ?><a class="text-link" href="<?=esc(admin_url('mail-submissions',['account_page'=>$data['accountPage']-1]))?>">Previous accounts</a><?php endif; ?>
<?php if($data['moreAccounts']): ?><a class="text-link" href="<?=esc(admin_url('mail-submissions',['account_page'=>$data['accountPage']+1]))?>">More accounts</a><?php endif; ?>
</nav></section>
<?php if(!$data['accounts']): ?><p class="notice">No assigned sending account appears on this page. An owner can review your email account assignments.</p>
<?php elseif(!$data['rows']): ?><p class="notice">You have no email submissions for this account.</p><?php endif; ?>
<div class="workspace-grid"><?php foreach(array_slice($data['rows'],0,20) as $row): ?><article class="workspace-card">
<h2><a href="<?=esc(admin_url('mail-submission',['submission_id'=>$row['id']]))?>"><?=esc($row['subject']!==''?$row['subject']:'Email without a subject')?></a></h2><p><strong><?=esc($row['status']['title'])?></strong></p><p>Prepared <?=esc($row['created_at'])?> UTC · Saved draft version <?=(int)$row['draft_revision']?></p></article><?php endforeach; ?></div>
<nav aria-label="Submission history pages">
<?php if($data['page']>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-submissions',['account_id'=>$data['account'],'p'=>$data['page']-1]))?>">Previous submissions</a><?php endif; ?>
<?php if(count($data['rows'])>20): ?><a class="btn outline" href="<?=esc(admin_url('mail-submissions',['account_id'=>$data['account'],'p'=>$data['page']+1]))?>">Older submissions</a><?php endif; ?>
</nav>
<?php elseif($view==='mail-send'&&$data['existing']!==null): $existing=$data['existing'];$status=mail_submissions_status($existing['result'],$existing['dispatch']); ?>
<section class="workspace-card"><h2><?=esc($status['title'])?></h2><p><?=esc($status['text'])?></p><p>This saved version already has a submission record. Opening this page does not repeat it.</p><a class="btn outline" href="<?=esc(admin_url('mail-submission',['submission_id'=>$existing['id']]))?>">View the existing submission</a></section>
<?php else:
$review=$view==='mail-send';
$content=$review?['from'=>$data['account']['address'],'to'=>$data['draft']['to'],'cc'=>$data['draft']['cc'],'bcc'=>$data['draft']['bcc'],'subject'=>$data['draft']['subject'],'body'=>$data['draft']['body']]:$data['row']['content'];
$draft=$review?$data['draft']['id']:$data['row']['draft_id'];
?>
<?php if(!$review): ?><section class="workspace-card" role="status"><h2><?=esc($data['status']['title'])?></h2><p><?=esc($data['status']['text'])?></p><p>Prepared <?=esc($data['row']['created_at'])?> UTC · Saved draft version <?=(int)$data['row']['draft_revision']?></p><?php if($data['row']['result']): ?><p>Result recorded <?=esc($data['row']['result']['finished_at'])?> UTC</p><?php endif; ?></section>
<?php $copy=$data['copy'];$sentFolder=$data['sentFolder'];$accepted=($data['row']['result']['status']??'')==='accepted'; ?>
<section class="workspace-card"><h2>Server Sent folder</h2>
<?php if($copy&&$copy['result_code']==='appended'): ?><p>A copy of the exact sent message was saved in <strong><?=esc($copy['folder'])?></strong> on <?=esc($copy['finished_at'])?> UTC. The server keeps that copy; deleting it is not part of this site.</p>
<?php elseif($accepted&&$sentFolder!==''): ?><p>The server accepted this message. You can save the exact sent copy in this account's <strong><?=esc($sentFolder)?></strong> folder. Recipients and the server read flag are unchanged.</p>
<?php if($copy): ?><p class="hint">Last copy attempt: <?=esc(mail_copy_outcome($copy['result_code']))?> (<?=esc($copy['finished_at'])?> UTC)</p><?php endif; ?>
<form method="post" action="<?=esc(admin_url('mail-submission',['submission_id'=>$data['row']['id']]))?>" data-mail-sent-copy>
<?php admin_action('mail_submissions_copy_sent'); ?><input type="hidden" name="submission_id" value="<?=esc($data['row']['id'])?>"><button class="btn outline">Save copy to server Sent folder</button></form>
<?php elseif($accepted): ?><p class="hint">No server Sent folder is set for this account. An owner can set one in Email account settings.</p>
<?php else: ?><p class="hint">Only a message the server accepted can be copied to a Sent folder.</p><?php endif; ?></section><?php endif; ?>
<section class="workspace-card"><h2><?=$review?'Review the saved message':'Recorded message'?></h2>
<?php if($review): ?><p>Only this saved version will be sent. Changes in an unsaved editor tab are not included.</p><?php endif; ?>
<p><strong>From:</strong> <?=esc($content['from'])?></p>
<p><strong>To:</strong> <?=esc(implode(', ',$content['to']))?></p><p><strong>Cc:</strong> <?=esc(implode(', ',$content['cc']))?></p>
<section aria-label="Blind copy recipients"><h3>Bcc</h3><p><?=esc($content['bcc']?implode(', ',$content['bcc']):'No blind copy recipients')?></p><p class="hint">Bcc recipients receive the email but are not included in its visible recipient headers.</p></section>
<h3><?=esc($content['subject']!==''?$content['subject']:'No subject')?></h3><div class="formatted" data-mail-submission-body><?=nl2br(esc($content['body']))?></div></section>
<?php if($review): ?><section class="workspace-card"><h2>Send this saved message</h2><form method="post" action="<?=esc(admin_url('mail-send',['draft_id'=>$draft]))?>" data-mail-submission-confirm>
<?php admin_action('mail_submissions_send'); ?><input type="hidden" name="submission_id" value="<?=esc($data['submission_id'])?>"><input type="hidden" name="draft_id" value="<?=esc($draft)?>"><input type="hidden" name="draft_revision" value="<?=(int)$data['draft']['revision']?>"><input type="hidden" name="account_revision" value="<?=(int)$data['account']['revision']?>">
<label><input type="checkbox" name="confirm_send" value="1" required> I reviewed the sending account, To/Cc/Bcc recipients and saved message above.</label>
<p class="hint">Send once, then check the recorded result. A missing confirmation does not mean the message failed.</p><button class="btn gold">Send email</button></form></section><?php endif; ?>
<p><a class="text-link" href="<?=esc(admin_url('mail-draft',['draft_id'=>$draft]))?>">Open the saved draft</a> · <a class="text-link" href="<?=esc(admin_url('mail-submissions'))?>">My submission history</a></p>
<?php endif; ?>
