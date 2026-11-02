<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="workspace-card" data-mail-message>
<a href="<?=esc(admin_url('mail-inbox',['account_id'=>$accountId,'folder'=>$route['folder']]))?>">← Recent headers</a>
<p><a href="<?=esc(admin_url('mail-notices'))?>">Recent email notices</a></p>
<h2><?=esc($record['label'])?></h2><p><?=esc($record['address'])?> · <?=esc($route['folder'])?> · Saved version <?=(int)$record['revision']?></p>
<p>Read this message without changing its server read flag. Remote images and sending are unavailable in this view. Messages over 256 KiB, including any attachments, must be opened in your regular email app.</p>
<?php if(!mail_messages_ready()): ?><p class="notice">An owner must prepare message reading in Email accounts first.</p>
<?php else: ?>
<form method="post" data-message-read><?php admin_action('mail_message_read'); ?>
<?php foreach($route as $key=>$value): ?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach; ?>
<button class="btn gold"><?=$result===null?'Load message':'Load message again'?></button></form>
<p class="hint">Allow 2 seconds between requests for this account. Each account and administrator can make up to 240 message requests per hour. This page does not refresh automatically.</p>
<?php if($result===null): ?><p>No message loaded for this page.</p>
<?php elseif($result['code']!=='ok'): ?><p class="notice error" role="alert"><?=esc(mail_message_outcome($result['code']))?></p>
<?php else: $content=$result['content']; ?>
<article data-message-content>
<p class="notice">Loaded <?=esc($result['loaded_at'])?> UTC · <?=esc($content['seen']?'Read on server':'Unread on server')?> · Reading never changes the server flag</p>
<?php if(mail_flags_ready()): ?>
<form method="post" data-message-flag><?php admin_action('mail_message_flag'); ?>
<?php foreach($route as $key=>$value): ?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach; ?>
<input type="hidden" name="mark" value="<?=esc($content['seen']?'unread':'read')?>">
<button class="btn outline"><?=$content['seen']?'Mark unread on server':'Mark read on server'?></button></form>
<p class="hint">Marking contacts the server once and is recorded. Allow 5 seconds between flag requests for this account.</p>
<?php endif; ?>
<h3><?=esc($content['subject']!==''?$content['subject']:'(No subject)')?></h3>
<p><strong>From:</strong> <?=esc($content['from']!==''?$content['from']:'Not provided')?></p>
<p><strong>Date from message:</strong> <?=esc($content['date']!==''?$content['date']:'Not provided')?></p>
<?php if($content['headers_truncated']): ?><p class="hint">A long header has been shortened for this view.</p><?php endif; ?>
<?php if($content['text']!==null): ?><h4>Plain text</h4><pre class="message-plain" data-message-plain><?=esc($content['text'])?></pre><?php endif; ?>
<?php if($content['html']!==null): ?><h4>Formatted message</h4><p class="hint">Basic formatting is shown in an isolated view. Links open a new tab only when you select them.</p>
<iframe class="message-formatted" data-message-formatted title="Formatted email message" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="<?=esc(mail_message_document($content['html']))?>"></iframe>
<?php elseif($content['has_html']&&!$content['format_available']): ?><p class="notice" data-message-format-unavailable>Formatted content is unavailable on this host. Use the plain text above, when provided, or open the message in your regular email app.</p><?php endif; ?>
<?php if($content['text']===null&&$content['html']===null&&!$content['has_html']): ?><p data-message-no-body>No supported inline text was provided. Attachment contents are not displayed.</p><?php endif; ?>
<?php if($content['additional_inline_parts']>0): ?><p class="hint"><?=(int)$content['additional_inline_parts']?> additional inline parts are not displayed.</p><?php endif; ?>
<?php if($content['attachments']): ?><h4>Attachments</h4><p class="hint">Each download is another recorded message read and still leaves the server read flag unchanged. Files download instead of opening in the browser.</p>
<ul data-message-attachments><?php foreach($content['attachments'] as $i=>$attachment): ?>
<li><form method="post" action="<?=esc(admin_url('mail-message',$route))?>" data-mail-attachment><?=csrf_field()?><?php foreach($route as $key=>$value): ?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach; ?><input type="hidden" name="attachment" value="<?=(int)$i?>"><button class="btn outline" name="action" value="mail_message_attachment"><?=esc($attachment['name'])?> (<?=(int)$attachment['size']?> bytes)</button></form></li>
<?php endforeach; ?></ul><?php endif; ?>
</article>
<?php if($route['folder']!=='INBOX'): ?><p class="hint">Reply and forward are only available for inbox messages.</p>
<?php elseif(mail_replies_ready()&&mail_drafts_ready()): ?>
<section class="workspace-card" data-mail-reply><h3>Reply or forward this message</h3>
<?php if(!$replySenders): ?><p class="hint">No sending account is currently assigned to you. An owner can review your email account assignments before you reply or forward.</p>
<?php else: ?>
<p class="hint">The draft copies only the displayed message under your current access and saves it privately. Attachments, hidden recipients and raw headers are not copied. Nothing is sent until you review and send the saved draft.</p>
<form method="post" action="<?=esc(admin_url('mail-message',$route))?>" data-mail-reply-form>
<?=csrf_field()?><?php foreach($route as $key=>$value): ?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach; ?>
<input type="hidden" name="draft_id" value="<?=esc(bin2hex(random_bytes(16)))?>">
<label>From account<select name="sender" required><?php foreach($replySenders as $sendAccount): ?><option value="<?=esc($sendAccount['id'].':'.$sendAccount['revision'])?>"><?=esc($sendAccount['label'].' · '.$sendAccount['address'])?></option><?php endforeach; ?></select></label>
<button class="btn gold" name="action" value="mail_message_reply">Reply</button>
<button class="btn outline" name="action" value="mail_message_reply_all">Reply all</button>
<button class="btn outline" name="action" value="mail_message_forward">Forward</button></form></section>
<?php endif; endif; ?>
<?php endif; ?>
<?php $historyPage=max(1,min((int)($_GET['p']??1),100000));$history=mail_message_history($accountId,$historyPage); ?>
<h3>Your recent message requests</h3><p class="hint">History keeps outcomes and message identifiers. Subjects, senders and message contents are not saved.</p>
<?php if(!$history): ?><p>No message requests recorded.</p><?php endif; ?>
<?php foreach(array_slice($history,0,20) as $entry): ?>
<article class="workspace-card"><h4><?=esc($entry['outcome']==='success'?'Message loaded':($entry['outcome']==='failed'?'Request failed':'Result not recorded'))?></h4>
<p><?=esc($entry['started_at'])?> UTC · <?=esc($entry['folder'])?> · Version <?=(int)$entry['account_revision']?><?php if((int)$entry['account_revision']!==(int)$record['revision']): ?> · Historical settings<?php endif; ?></p>
<p><?=esc(mail_message_outcome($entry['result_code']??''))?></p></article>
<?php endforeach; ?>
<nav aria-label="Message request history pages"><?php if($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-message',$route+['p'=>$historyPage-1]))?>">Newer requests</a><?php endif; ?><?php if(count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('mail-message',$route+['p'=>$historyPage+1]))?>">Older requests</a><?php endif; ?></nav>
<?php if(mail_flags_ready()): $flagHistory=mail_flags_history($accountId); ?>
<h3>Your recent flag requests</h3><p class="hint">History keeps outcomes and message identifiers. Message contents are never saved.</p>
<?php if(!$flagHistory): ?><p>No flag requests recorded.</p><?php endif; ?>
<?php foreach(array_slice($flagHistory,0,20) as $entry): ?>
<article class="workspace-card"><h4><?=esc($entry['outcome']==='success'?'Flag '.((int)$entry['mark_seen']===1?'read':'unread').((int)($entry['applied']??0)===0&&$entry['result_code']==='unchanged'?' already set':' changed'):($entry['outcome']==='uncertain'?'Flag change unconfirmed':($entry['outcome']==='failed'?'Flag request failed':'Result not recorded')))?></h4>
<p><?=esc($entry['started_at'])?> UTC · <?=esc($entry['folder'])?> · Version <?=(int)$entry['account_revision']?><?php if((int)$entry['account_revision']!==(int)$record['revision']): ?> · Historical settings<?php endif; ?></p>
<p><?=esc(mail_flag_outcome($entry['result_code']??''))?></p></article>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?></section>
