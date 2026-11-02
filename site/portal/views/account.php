<?php
/** Authenticated customer account settings; never render credential controls during support. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
try{$account=studio_account_identity();}catch(RuntimeException $e){fail_page($e->getMessage(),403);}
site_head('Your account','portal');
?><section class="page-hero compact"><div class="container"><span class="eyebrow">CLIENT ACCOUNT</span><h1>Your account.</h1><p>Keep your sign-in secure without changing your projects or services.</p><div class="button-row"><a class="btn outline" href="<?=esc(studio_url())?>">Back to workspace</a></div></div></section>
<section class="section"><div class="container"><?php show_flash();if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>'; ?>
<article class="workspace-card" data-client-account style="max-width:44rem;margin-inline:auto"><h2>Signed in as</h2><p><strong><?=esc($account['name'])?></strong><br><?=esc($account['email'])?></p><p class="hint">Contact the studio to update your account name or email address.</p>
<h2>Change your password</h2><p>This changes your customer sign-in only. It does not change administrator credentials, billing, licenses, or hosting access.</p>
<form method="post" action="<?=esc(studio_url(['account'=>1]))?>" data-account-password><?=csrf_field()?><input type="hidden" name="action" value="account_password">
<label>Current password<input type="password" name="current_password" maxlength="1000" required autocomplete="current-password"></label>
<label>New password<input type="password" name="password" minlength="14" maxlength="72" required autocomplete="new-password" aria-describedby="account-password-help"></label>
<label>Confirm new password<input type="password" name="confirm_password" minlength="14" maxlength="72" required autocomplete="new-password"></label>
<p id="account-password-help" class="hint">Use a unique passphrase of 14–72 bytes. Your other client sessions and unused account-recovery invitations will be revoked. This session stays signed in.</p><button class="btn gold" type="submit">Update password</button></form></article></div></section><?php site_foot(false);exit;
