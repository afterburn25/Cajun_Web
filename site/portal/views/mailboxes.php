<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<section class="workspace-card" data-mailbox-create><h2>Create a hosting mailbox</h2>
<p>Create an address on a domain approved for your account. Save the password securely; it cannot be retrieved from this page.</p>
<?php if($mailboxOptions&&!$mailboxUnavailable): ?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="<?=$mailboxAdmin?'provider_inventory_mailbox_create':'client_mailbox_create'?>">
<input type="hidden" name="mailbox_request" value="<?=esc(bin2hex(random_bytes(16)))?>">
<?php $selectedPolicy=is_string($_POST['mailbox_policy']??null)&&in_array($_POST['mailbox_policy'],array_column($mailboxOptions,'id'),true)?$_POST['mailbox_policy']:''; ?>
<label>Domain<select name="mailbox_policy" required><option value=""<?=$selectedPolicy===''?' selected':''?>>Choose a domain</option><?php foreach($mailboxOptions as $option): ?><option value="<?=esc($option['id'])?>"<?=$selectedPolicy===$option['id']?' selected':''?>><?=esc($option['domain'])?></option><?php endforeach; ?></select></label>
<?php $local=is_string($_POST['mailbox_local']??null)?substr($_POST['mailbox_local'],0,64):''; ?>
<label>Mailbox name<input name="mailbox_local" value="<?=esc($local)?>" maxlength="64" placeholder="hello" required autocapitalize="none" spellcheck="false"></label>
<p class="hint">Use lowercase letters, numbers, dots, underscores, plus signs or hyphens. Enter only the part before @.</p>
<label>Mailbox password<input type="password" name="mailbox_password" minlength="12" maxlength="128" required autocomplete="new-password"></label>
<label>Confirm mailbox password<input type="password" name="mailbox_password_confirm" minlength="12" maxlength="128" required autocomplete="new-password"></label>

<label><input type="checkbox" name="mailbox_confirm" value="1" required> Create this mailbox on the selected domain.</label>
<button class="btn gold">Create mailbox</button></form>
<?php else: ?><p>Mailbox creation is currently unavailable. Contact the studio to enable it for your domain.</p><?php endif; ?>
</section>
<section class="workspace-card" data-mailbox-history><h2><?=$mailboxAdmin?'Recent mailbox requests':'Your mailbox requests'?></h2>
<p>This history shows creation requests made here. It is not a complete list of mailboxes at your provider.</p>
<?php if($mailboxUnavailable): ?><p>Mailbox management needs attention. Please contact the studio.</p>
<?php elseif(!$mailboxHistory): ?><p>No mailbox requests have been recorded.</p><?php endif; ?>
<?php foreach(array_slice($mailboxHistory,0,20) as $request): ?><article class="workspace-card"><h3 style="overflow-wrap:anywhere"><?=esc($request['address'])?></h3><p><?=esc($request['at'])?> UTC</p><p><?=esc($request['message'])?></p></article><?php endforeach; ?>
<?php if(count($mailboxHistory)>20): ?><p>Showing the 20 most recent requests.</p><?php endif; ?>
</section>
