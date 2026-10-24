<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="workspace-card" data-mail-notices>
<div class="mail-notice-heading"><h2>Your recent email notices</h2><a class="btn outline" href="<?=esc(admin_url('mailboxes'))?>">My email accounts</a></div>
<p>Based on the newest 20 messages from each inbox’s last successful check. This is not a live count of all unread mail. Open an inbox below to check for new messages.</p>
<p>Opening or dismissing here changes your notices only. It does not change your mail app’s read flags or another administrator’s local notices. Subjects and message contents are not saved in this list. All times are UTC.</p>
<?php if(!$ready): ?><p class="notice">An owner must prepare inbox reading, message reading and email notices in <a href="<?=esc(admin_url('mail-accounts'))?>">Email accounts</a> first.</p>
<?php else: ?><p class="notice" data-mail-notice-count><strong><?=$count>=100?'99+':$count?></strong> recent notices not opened or dismissed by you and last observed unread on the server.</p>
<h3>Inbox check status</h3>
<?php if(!$accounts): ?><p>No currently assigned inboxes on this page.</p><?php endif; ?>
<?php foreach(array_slice($accounts,0,20) as $account): ?>
<article class="workspace-card mail-notice-account"><h4><?=esc($account['label'])?></h4><p><?=esc($account['address'])?></p>
<?php if($account['checked_at']===null): ?><p>Not checked with these saved settings. No current message window is available.</p>
<?php else: ?><p>Last successful inbox check: <?=esc($account['checked_at'])?> UTC. Messages may have changed since this check.</p><?php endif; ?>
<?php if($account['latest_outcome']==='failed'): ?><p class="notice warning">The most recent inbox request failed. Older notices have not been refreshed.</p>
<?php elseif($account['latest_outcome']===null&&$account['checked_at']!==null): ?><p class="notice warning">A later inbox request has no completed result. These notices still use the earlier check.</p><?php endif; ?>
<a class="btn outline" href="<?=esc(admin_url('mail-inbox',['account_id'=>$account['id']]))?>">Open inbox to refresh</a></article>
<?php endforeach; ?>
<nav aria-label="Assigned inbox status pages"><?php if($accountPage>1): ?><a href="<?=esc(admin_url('mail-notices',['accounts_page'=>$accountPage-1,'p'=>$page]))?>">Previous inboxes</a><?php endif; ?><?php if(count($accounts)>20): ?><a href="<?=esc(admin_url('mail-notices',['accounts_page'=>$accountPage+1,'p'=>$page]))?>">More inboxes</a><?php endif; ?></nav>
<h3>Observed messages</h3>
<?php if(!$items): ?><p>No recent observed messages on this page. Check an assigned inbox to populate this list.</p><?php endif; ?>
<?php foreach(array_slice($items,0,20) as $item): $route=['account_id'=>$item['account_id'],'revision'=>(string)$item['account_revision'],'folder'=>$item['folder'],'uidvalidity'=>$item['uidvalidity'],'uid'=>$item['uid'],'notice'=>$item['handle']]; ?>
<article class="workspace-card mail-notice-item" data-mail-notice="<?=esc($item['handle'])?>"><h4>Recent message · <?=esc($item['label'])?></h4><p><?=esc($item['address'])?></p>
<p>Observed <?=esc($item['flags_observed_at'])?> UTC · <?=(int)$item['server_seen']===1?'Read on server when checked':'Unread on server when checked'?></p>
<p><?=esc($item['opened_at']!==null?'Opened by you '.$item['opened_at'].' UTC':($item['dismissed_at']!==null?'Dismissed by you '.$item['dismissed_at'].' UTC':'Not opened or dismissed by you'))?></p>
<div class="mail-notice-actions"><form method="post" action="<?=esc(admin_url('mail-message',$route))?>" data-mail-notice-open><?php admin_action('mail_message_read'); ?>
<?php foreach($route as $key=>$value): ?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach; ?><button class="btn gold">Open message</button></form>
<?php if($item['opened_at']===null&&$item['dismissed_at']===null): ?><form method="post" action="<?=esc(admin_url('mail-notices'))?>" data-mail-notice-dismiss><?php admin_action('mail_notices_dismiss'); ?><input type="hidden" name="handle" value="<?=esc($item['handle'])?>">
<?php foreach(['account_id','account_revision','folder','uidvalidity','uid'] as $key): ?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($item[$key])?>"><?php endforeach; ?><button class="btn outline">Dismiss for me</button></form><?php endif; ?></div></article>
<?php endforeach; ?>
<nav aria-label="Recent email notice pages"><?php if($page>1): ?><a href="<?=esc(admin_url('mail-notices',['p'=>$page-1,'accounts_page'=>$accountPage]))?>">Newer notices</a><?php endif; ?><?php if(count($items)>20): ?><a href="<?=esc(admin_url('mail-notices',['p'=>$page+1,'accounts_page'=>$accountPage]))?>">Older notices</a><?php endif; ?></nav>
<?php endif; ?></section>
