<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="workspace-card" data-mail-inbox>
<a href="<?=esc(admin_url('mailboxes'))?>">← My email accounts</a>
<h2><?=esc($record['label'])?></h2><p><?=esc($record['address'])?> · INBOX · Saved version <?=(int)$record['revision']?></p>
<p>Load the newest 20 inbox headers. This view shows sender, subject and reported date. Select Open message to read one message. Attachments and sending remain unavailable. Loading headers leaves read flags unchanged.</p>
<?php if(!mail_inbox_ready()): ?>
<p class="notice">An owner must prepare inbox reading in Email accounts first.</p>
<?php else: ?>
<form method="post" data-inbox-read><?php admin_action('mail_inbox_read'); ?>
<input type="hidden" name="account_id" value="<?=esc($accountId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<button class="btn gold">Load recent headers</button></form>
<p class="hint">Allow 15 seconds between requests for this account. Each administrator can request up to 120 inbox views per hour. This page does not refresh automatically.</p>
<?php if($result===null): ?><p>No headers loaded for this page.</p>
<?php elseif($result['code']!=='ok'): ?><p class="notice error" role="alert"><?=esc(mail_inbox_message($result['code']))?></p>
<?php else: $batch=$result['batch']; ?>
<p class="notice" data-inbox-loaded>Loaded <?=esc($result['loaded_at'])?> UTC · <?=count($batch['messages'])?> of <?=(int)$batch['total']?> messages at the time of the request.</p>
<?php if(!$batch['messages']): ?><p data-inbox-empty>The server reported an empty inbox.</p><?php endif; ?>
<div class="inbox-headers"><?php foreach($batch['messages'] as $message): ?>
<article class="workspace-card inbox-header" data-inbox-uid="<?=esc($message['uid'])?>">
<p class="hint"><?=esc($message['seen']?'Read on server':'Unread on server')?></p>
<h3><?=esc($message['subject']!==''?$message['subject']:'(No subject)')?></h3>
<p><strong>From:</strong> <?=esc($message['from']!==''?$message['from']:'Not provided')?></p>
<p><strong>Date from message:</strong> <?=esc($message['date']!==''?$message['date']:'Not provided')?></p>
<?php if($message['headers_truncated']): ?><p class="hint">A long header has been shortened for this view.</p><?php endif; ?>
<?php if(mail_messages_ready()): $messageRoute=['account_id'=>$accountId,'revision'=>(string)$record['revision'],'uidvalidity'=>$batch['uidvalidity'],'uid'=>$message['uid']]; ?>
<form method="post" action="<?=esc(admin_url('mail-message',$messageRoute))?>" data-open-message><?php admin_action('mail_message_read'); ?>
<?php foreach($messageRoute as $key=>$value): ?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach; ?>
<button class="btn outline">Open message</button></form>
<?php else: ?><p class="hint">An owner must prepare message reading before you can open this message.</p><?php endif; ?>
</article><?php endforeach; ?></div>
<?php endif; ?>
<?php $historyPage=max(1,min((int)($_GET['p']??1),100000));$history=mail_inbox_history($accountId,$historyPage); ?>
<h3>Your recent inbox requests</h3><p class="hint">This history keeps request outcomes and counts. Message headers are not saved in the history.</p>
<?php if(!$history): ?><p>No inbox requests recorded.</p><?php endif; ?>
<?php foreach(array_slice($history,0,20) as $entry): ?>
<article class="workspace-card"><h4><?=esc($entry['outcome']==='success'?'Headers loaded':($entry['outcome']==='failed'?'Request failed':'Result not recorded'))?></h4>
<p><?=esc($entry['started_at'])?> UTC · Version <?=(int)$entry['account_revision']?><?php if((int)$entry['account_revision']!==(int)$record['revision']): ?> · Historical settings<?php endif; ?></p>
<p><?=esc(mail_inbox_message($entry['result_code']??''))?><?php if($entry['outcome']==='success'): ?> <?=(int)$entry['message_count']?> of <?=(int)$entry['mailbox_total']?> headers.<?php endif; ?></p></article>
<?php endforeach; ?>
<nav aria-label="Inbox request history pages"><?php if($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-inbox',['account_id'=>$accountId,'p'=>$historyPage-1]))?>">Newer requests</a><?php endif; ?><?php if(count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('mail-inbox',['account_id'=>$accountId,'p'=>$historyPage+1]))?>">Older requests</a><?php endif; ?></nav>
<?php endif; ?></section>
