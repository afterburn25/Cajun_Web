<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="workspace-card" data-mail-checks><h2>Check saved connections</h2>
<p>Each check signs in to one saved server using verified encryption, then disconnects. It reads and sends no messages. Saving settings never starts a check.</p>
<?php if (!mail_connections_ready()): ?>
<p>Connection not checked. Prepare the check history before testing connections. Existing account settings and assignments are preserved.</p>
<form method="post"><?php admin_action('mail_accounts_connections_setup'); ?><input type="hidden" name="account_id" value="<?=esc($accountId)?>">
<?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare connection checks</button></form>
<?php else: ?>
<p class="hint">Supported: incoming 993/TLS or 143/STARTTLS; outgoing 465/TLS or 587/STARTTLS. Use your provider’s public server hostname and approved password. OAuth-only accounts are not supported yet. A successful login does not establish sending permission or delivery.</p>
<div class="workspace-grid"><?php foreach (['imap'=>'incoming','smtp'=>'outgoing'] as $target=>$label): ?>
<form method="post" data-mail-check="<?=esc($target)?>"><?php admin_action('mail_accounts_check'); ?>
<input type="hidden" name="account_id" value="<?=esc($accountId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><input type="hidden" name="target" value="<?=esc($target)?>">
<h3><?=esc(ucfirst($label))?> connection</h3><p><?=esc($record[$target.'_host'])?> · <?=(int)$record[$target.'_port']?> · Saved version <?=(int)$record['revision']?></p>
<?php if (mail_accounts_can($target==='imap'?'mail.read':'mail.send',$accountId)): ?>
<?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Test <?=esc($label)?> connection</button>
<?php else: ?><p>Enable this saved account and assign yourself <?=esc($target==='imap'?'Read':'Send')?> access before testing.</p><?php endif; ?>
</form><?php endforeach; ?></div>
<p class="hint">Wait 30 seconds between checks of the same connection. Each owner may start up to 20 checks per hour.</p>
<?php $checkPage=max(1,min((int)($_GET['check_page']??1),100000));$checks=mail_connections_history($accountId,$checkPage); ?>
<h3>Connection-check history</h3><p class="hint">Results describe the saved version and time shown. Earlier results remain historical after settings change.</p>
<?php if (!$checks): ?><p>Connection not checked. No connection has been attempted for this account.</p><?php endif; ?>
<?php foreach(array_slice($checks,0,20) as $check): ?>
<article class="workspace-card"><h4><?=esc($check['target']==='imap'?'Incoming':'Outgoing')?> · Version <?=(int)$check['account_revision']?> · <?=esc($check['outcome']==='success'?'Login checked':($check['outcome']==='failed'?'Check failed':'Result not recorded'))?></h4>
<p><?=esc($check['started_at'])?> UTC · <?=esc($check['actor_name']??'Former administrator')?><?php if ((int)$check['account_revision']!==(int)$record['revision']): ?> · Historical settings<?php endif; ?></p>
<p><?=esc(mail_connections_message($check['result_code']??''))?></p></article>
<?php endforeach; ?>
<nav aria-label="Connection-check history pages"><?php if($checkPage>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-account',['account_id'=>$accountId,'check_page'=>$checkPage-1]))?>">Newer checks</a><?php endif; ?><?php if(count($checks)>20): ?><a class="btn outline" href="<?=esc(admin_url('mail-account',['account_id'=>$accountId,'check_page'=>$checkPage+1]))?>">Older checks</a><?php endif; ?></nav>
<?php endif; ?></section>
