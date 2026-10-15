<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationRegister;
$record=provider_settings_profile();$profileId=$record['id']??'';$revision=(int)($record['revision']??0);
$register=new TwentyIOperationRegister();$buildable=$register->buildableNames();
$grants=$profileId!==''?provider_operation_grants($profileId):[];
$history=provider_operations_history();
?>
<section class="workspace-card"><h2>Approved 20i operations</h2>
<p>An approval records the exact documented request — operation and every field — for one run. Running it needs your password again plus a fresh idempotency key you choose; the approved fields cannot be widened at send time. Each approval is used once, expires, and can be revoked.</p>
<p>Only operations with a fully documented request shape can be approved. Requests are claimed before sending so the same key can never execute twice; ambiguous outcomes are recorded as <strong>uncertain</strong> for reconciliation, never silently retried.</p>
<?php if($record&&(int)$record['enabled']===1&&$record['has_credentials']&&$buildable): ?>
<form method="post" data-provider-grant>
<?php admin_action('provider_operations_grant'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>">
<p><label for="operation">Documented operation</label><br>
<select id="operation" name="operation"><?php foreach($buildable as $name): ?><option value="<?=esc($name)?>"><?=esc($name)?></option><?php endforeach; ?></select></p>
<?php admin_textarea('scope_params','Approved request fields (JSON object)',isset($_POST['scope_params'])&&($_POST['action']??'')==='provider_operations_grant'?(string)$_POST['scope_params']:'',3,4096); ?>
<p class="hint">Only fields documented for the chosen operation are accepted. Path ids like <code>resellerId</code> belong here too.</p>
<p><label for="ttl_seconds">Approval valid for</label><br>
<select id="ttl_seconds" name="ttl_seconds"><?php foreach([300=>'5 minutes',900=>'15 minutes',3600=>'1 hour',14400=>'4 hours',86400=>'24 hours'] as $secs=>$label): ?><option value="<?=$secs?>"<?=$secs===3600?' selected':''?>><?=$label?></option><?php endforeach; ?></select></p>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Approve one request</button>
</form>
<?php else: ?><p>Save and enable a General API key on the <a href="<?=esc(admin_url('provider-settings'))?>">20i connection page</a> before approving operations.</p><?php endif; ?>
</section>
<?php if($grants): ?>
<section class="workspace-card"><h2>Approvals</h2>
<?php foreach($grants as $grant):
    $state=$grant['revoked_at']!==null?'revoked':($grant['consumed_key']!==null?'used':((int)$grant['expires_epoch']<=time()?'expired':'active')); ?>
<article class="workspace-card"><h3><?=esc($grant['operation'])?> · <?=badge($state)?></h3>
<p>Approved <?=esc($grant['granted_at'])?> UTC · Expires <?=esc(gmdate('Y-m-d H:i:s',(int)$grant['expires_epoch']))?> UTC<?=$grant['consumed_key']!==null?' · Used for key <code>'.esc($grant['consumed_key']).'</code> at '.esc($grant['consumed_at']).' UTC':''?></p>
<p><code><?=esc((string)$grant['scope_params'])?></code></p>
<?php if($state==='active'): ?>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=$revision?>"><input type="hidden" name="grant_id" value="<?=esc($grant['id'])?>">
<?php admin_input('idempotency_key','Idempotency key for this one run','','text','required maxlength="190" autocomplete="off"'); ?>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn outline" name="action" value="provider_operations_run">Send the approved request</button>
<button class="btn outline" name="action" value="provider_operations_revoke" formnovalidate>Revoke approval</button>
</form>
<?php endif; ?></article>
<?php endforeach; ?></section>
<?php endif; ?>
<section class="workspace-card"><h2>Operation history</h2>
<?php if(!$history): ?><p>No provider operation has been attempted.</p><?php endif; ?>
<?php foreach($history as $row): ?>
<article><h3><?=esc((string)($row['operation']??'provider.operation'))?> · <?=badge($row['status'])?></h3>
<p><code><?=esc($row['idempotency_key'])?></code> · claimed <?=esc($row['claimed_at'])?> UTC · <?=(int)($row['attempts']??0)?> attempt(s)<?=$row['provider_code']!==null?' · '.esc($row['provider_code']):''?><?=$row['external_id']!==null?' · provider ref '.esc($row['external_id']):''?></p>
<?php if($row['result_message']!==''): ?><p><?=esc($row['result_message'])?></p><?php endif; ?>
<?php if($row['status']==='uncertain'): ?>
<?php if($row['reconciled_outcome']!==null): ?><p>Reconciled <?=esc(gmdate('Y-m-d H:i:s',(int)$row['reconciled_epoch']))?> UTC · <?=badge($row['reconciled_outcome'])?></p><?php endif; ?>
<p class="hint">This write may have reached 20i. The request is never retried automatically — an owner check performs one fresh, evidence-backed observation of the package list and reports whether the approved effect is visible.</p>
<?php if($record&&(int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=$revision?>"><input type="hidden" name="idempotency_key" value="<?=esc($row['idempotency_key'])?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn outline" name="action" value="provider_operations_reconcile">Check the provider now</button>
</form>
<?php endif; ?>
<?php endif; ?></article>
<?php endforeach; ?></section>
