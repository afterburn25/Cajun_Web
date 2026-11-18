<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<p class="hint">This private history records sending attempts from this website. An accepted submission can be copied to a configured server Sent folder only through a separate explicit action; a server copy does not confirm recipient delivery.</p>
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
$content=$review?['from'=>$data['account']['address'],'to'=>$data['draft']['to'],'cc'=>$data['draft']['cc'],'bcc'=>$data['draft']['bcc'],'subject'=>$data['draft']['subject'],'body'=>$data['draft']['body'],'body_html'=>$data['draft']['html']??($data['draft']['body_html']??null)]:$data['row']['content'];
$draft=$review?$data['draft']['id']:$data['row']['draft_id'];
?>
<?php if(!$review): ?><section class="workspace-card" role="status"><h2><?=esc($data['status']['title'])?></h2><p><?=esc($data['status']['text'])?></p><p>Prepared <?=esc($data['row']['created_at'])?> UTC · Saved draft version <?=(int)$data['row']['draft_revision']?></p><?php if($data['row']['result']): ?><p>Result recorded <?=esc($data['row']['result']['finished_at'])?> UTC</p><?php endif; ?></section><?php endif; ?>
<?php if(!$review && ($data['row']['result']['status']??'')==='accepted'): ?>
<section class="workspace-card" data-mail-sent-copy><h2>Server Sent-folder copy</h2>
<?php if($data['copy']!==null): ?><p><strong><?=esc(mail_copy_outcome($data['copy']['result_code']))?></strong></p><p>Requested <?=esc($data['copy']['started_at'])?> UTC for <?=esc($data['copy']['folder'])?><?php if($data['copy']['finished_at']!==null): ?> · Result recorded <?=esc($data['copy']['finished_at'])?> UTC<?php endif; ?></p><?php endif; ?>
<?php if($data['copy']!==null && ($data['copy']['result_code']===null||$data['copy']['result_code']==='uncertain')): ?><p class="notice error" role="alert">This copy is unresolved or may already exist on the server. Do not retry it.</p>
<?php elseif($data['copy']!==null && $data['copy']['result_code']==='appended'): ?><p>The server confirmed this exact saved MIME copy. It will not be copied again.</p>
<?php elseif(!mail_copies_ready()): ?><p>An owner must prepare Sent-copy storage in Email accounts first.</p>
<?php elseif($data['sent_folder']===''): ?><p>An owner must configure the exact server Sent folder on this email account first.</p>
<?php else: ?><form method="post" data-mail-copy-confirm><?php admin_action('mail_submissions_copy_sent'); ?><input type="hidden" name="submission_id" value="<?=esc($data['row']['id'])?>"><label><input type="checkbox" name="confirm_copy" value="1" required> Copy this exact accepted saved message once to <?=esc($data['sent_folder'])?> on the server.</label><p class="hint">A lost confirmation after upload is recorded as uncertain and cannot be retried automatically or manually.</p><button class="btn outline">Copy to server Sent folder</button></form><?php endif; ?>
</section>
<?php endif; ?>
<section class="workspace-card"><h2><?=$review?'Review the saved message':'Recorded message'?></h2>
<?php if($review): ?><p>Only this saved version will be sent. Changes in an unsaved editor tab are not included.</p><?php endif; ?>
<p><strong>From:</strong> <?=esc($content['from'])?></p>
<p><strong>To:</strong> <?=esc(implode(', ',$content['to']))?></p><p><strong>Cc:</strong> <?=esc(implode(', ',$content['cc']))?></p>
<section aria-label="Blind copy recipients"><h3>Bcc</h3><p><?=esc($content['bcc']?implode(', ',$content['bcc']):'No blind copy recipients')?></p><p class="hint">Bcc recipients receive the email but are not included in its visible recipient headers.</p></section>
<?php $files=$review?($data['files']??[]):($content['attachments']??[]); ?>
<section aria-label="Message attachments" data-mail-submission-files><h3>Attachments</h3>
<?php if($files): foreach($files as $file): ?><p><strong><?=esc($file['name'])?></strong> · <?=esc($file['mime'])?> · <?=(int)$file['bytes']?> bytes</p><?php endforeach; else: ?><p class="hint">No files are attached to this saved message.</p><?php endif; ?></section>
<h3><?=esc($content['subject']!==''?$content['subject']:'No subject')?></h3><div class="formatted" data-mail-submission-body><?=nl2br(esc($content['body']))?></div>
<?php if(($content['body_html']??null)!==null): ?><p class="hint">This saved message also sends a sanitized formatted alternative:</p>
<iframe class="message-formatted" data-mail-submission-formatted title="Formatted message alternative" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="<?=esc(mail_message_document($content['body_html']))?>"></iframe><?php endif; ?></section>
<?php if($review): ?><section class="workspace-card"><h2>Send this saved message</h2><form method="post" action="<?=esc(admin_url('mail-send',['draft_id'=>$draft]))?>" data-mail-submission-confirm>
<?php admin_action('mail_submissions_send'); ?><input type="hidden" name="submission_id" value="<?=esc($data['submission_id'])?>"><input type="hidden" name="draft_id" value="<?=esc($draft)?>"><input type="hidden" name="draft_revision" value="<?=(int)$data['draft']['revision']?>"><input type="hidden" name="account_revision" value="<?=(int)$data['account']['revision']?>">
<label><input type="checkbox" name="confirm_send" value="1" required> I reviewed the sending account, To/Cc/Bcc recipients, attachments and saved message above.</label>
<p class="hint">Send once, then check the recorded result. A missing confirmation does not mean the message failed.</p><button class="btn gold">Send email</button></form></section><?php endif; ?>
<p><a class="text-link" href="<?=esc(admin_url('mail-draft',['draft_id'=>$draft]))?>">Open the saved draft</a> · <a class="text-link" href="<?=esc(admin_url('mail-submissions'))?>">My submission history</a></p>
<?php endif; ?>
