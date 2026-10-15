<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationRegister;
$record=provider_settings_profile();$profileId=$record['id']??'';$revision=(int)($record['revision']??0);
$register=new TwentyIOperationRegister();$buildable=$register->genericNames();
$grants=$profileId!==''?provider_operation_grants($profileId):[];
$history=provider_operations_history();
?>
<section class="workspace-card"><h2>Change a hosting package label</h2>
<p>Choose an existing package and its new label. Approval lasts for the duration you select and can be used once. You will review the approved fields before sending the change.</p>
<p>Purchases, renewals, plan changes, deletion and customer email require their dedicated order and consent workflows, which are not available here yet. Earlier operation records remain available below for recovery.</p>
<?php if($record&&(int)$record['enabled']===1&&$record['has_credentials']&&$buildable): ?>
<form method="post" data-provider-grant>
<?php admin_action('provider_operations_label_grant'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>">
<?php
$submitted=($_POST['action']??'')==='provider_operations_label_grant';
admin_input('package_id','Hosting package ID',$submitted&&is_string($_POST['package_id']??null)?$_POST['package_id']:'','text','required inputmode="numeric" pattern="[1-9][0-9]{0,17}" maxlength="18"');
admin_input('package_label','New package label',$submitted&&is_string($_POST['package_label']??null)?$_POST['package_label']:'','text','required maxlength="160"');
?>
<p class="hint">Use the package ID shown in your 20i inventory. This changes the package label only. An uncertain result needs checking before another request.</p>
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
<?php $params=json_decode((string)$grant['scope_params'],true);$canSend=is_array($params)&&$register->allowsGenericApproval((string)$grant['operation'],$params); ?>
<?php if(!$canSend): ?><p>This earlier approval requires its dedicated order or consent workflow and cannot be sent from this page.</p><?php endif; ?>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=$revision?>"><input type="hidden" name="grant_id" value="<?=esc($grant['id'])?>">
<?php if($canSend)admin_input('idempotency_key','Idempotency key for this one run','','text','required maxlength="190" autocomplete="off"'); ?>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<?php if($canSend): ?><button class="btn outline" name="action" value="provider_operations_run">Send the approved request</button><?php endif; ?>
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
<?php if(in_array($row['status'],['uncertain','accepted_pending'],true)): ?>
<?php if($row['reconciled_outcome']!==null): ?><p>Reconciled <?=esc(gmdate('Y-m-d H:i:s',(int)$row['reconciled_epoch']))?> UTC · <?=badge($row['reconciled_outcome'])?></p><?php endif; ?>
<p class="hint">This write may have reached 20i. The request is never retried automatically — an owner check performs one fresh, evidence-backed observation of the package list and reports the available evidence. A domain-name match does not prove which request created a resource.</p>
<?php if($record&&(int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=$revision?>"><input type="hidden" name="idempotency_key" value="<?=esc($row['idempotency_key'])?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn outline" name="action" value="provider_operations_reconcile">Check the provider now</button>
</form>
<?php endif; ?>
<?php endif; ?></article>
<?php endforeach; ?></section>
