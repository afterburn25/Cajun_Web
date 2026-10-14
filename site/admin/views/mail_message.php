<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="workspace-card" data-mail-message>
<a href="<?=esc(admin_url('mail-inbox',['account_id'=>$accountId]))?>">← Recent inbox headers</a>
<p><a href="<?=esc(admin_url('mail-notices'))?>">Recent email notices</a></p>
<h2><?=esc($record['label'])?></h2><p><?=esc($record['address'])?> · INBOX · Saved version <?=(int)$record['revision']?></p>
<p>Read this message without changing its server read flag. Remote images, attachments and sending are unavailable in this view. Messages over 256 KiB, including any attachments, must be opened in your regular email app.</p>
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
<p class="notice">Loaded <?=esc($result['loaded_at'])?> UTC · <?=esc($content['seen']?'Read on server':'Unread on server')?> · Server read flag unchanged</p>
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
</article><?php endif; ?>
<?php $historyPage=max(1,min((int)($_GET['p']??1),100000));$history=mail_message_history($accountId,$historyPage); ?>
<h3>Your recent message requests</h3><p class="hint">History keeps outcomes and message identifiers. Subjects, senders and message contents are not saved.</p>
<?php if(!$history): ?><p>No message requests recorded.</p><?php endif; ?>
<?php foreach(array_slice($history,0,20) as $entry): ?>
<article class="workspace-card"><h4><?=esc($entry['outcome']==='success'?'Message loaded':($entry['outcome']==='failed'?'Request failed':'Result not recorded'))?></h4>
<p><?=esc($entry['started_at'])?> UTC · Version <?=(int)$entry['account_revision']?><?php if((int)$entry['account_revision']!==(int)$record['revision']): ?> · Historical settings<?php endif; ?></p>
<p><?=esc(mail_message_outcome($entry['result_code']??''))?></p></article>
<?php endforeach; ?>
<nav aria-label="Message request history pages"><?php if($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-message',$route+['p'=>$historyPage-1]))?>">Newer requests</a><?php endif; ?><?php if(count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('mail-message',$route+['p'=>$historyPage+1]))?>">Older requests</a><?php endif; ?></nav>
<?php endif; ?></section>
