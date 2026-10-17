<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$page=max(1,min((int)($_GET['p']??1),100000));
?>
<p class="notice">Manage email settings, check saved connections and load recent inbox headers. Open individual messages after message reading is prepared. Send saved plaintext drafts after submission storage is prepared. Attachments remain unavailable.</p>
<?php if ($view==='mailboxes'):
    $rows=mail_accounts_assigned($page);
?>
<?php if(mail_drafts_admin_access($actor)): ?><p><a class="btn outline" href="<?=esc(admin_url('mail-drafts'))?>">My email drafts</a> <a class="btn outline" href="<?=esc(admin_url('mail-submissions'))?>">My email submissions</a></p><?php endif; ?>
<?php if(mail_notices_access($actor)): ?><p><a class="btn outline" href="<?=esc(admin_url('mail-notices'))?>">Recent email notices</a></p><?php endif; ?>
<p>Your owner assigns each email account separately. Read access lets you request recent inbox headers and individual messages. Send assignments let you prepare private drafts, review saved messages and submit them from that account.</p>
<div class="workspace-grid"><?php foreach (array_slice($rows,0,20) as $row): ?>
<article class="workspace-card"><h2><?=esc($row['label'])?></h2><p><?=esc($row['address'])?></p><p>Assigned: <?=esc(implode(' and ',array_keys(array_filter(['Read'=>$row['can_read'],'Send'=>$row['can_send']]))))?></p><?php if($row['can_read']): ?><p><a class="btn outline" href="<?=esc(admin_url('mail-inbox',['account_id'=>$row['id']]))?>">Open inbox</a></p><?php endif; ?><?php if($row['can_send']): ?><p><a class="btn outline" href="<?=esc(admin_url('mail-drafts',['account_id'=>$row['id']]))?>">My drafts</a> <a class="btn outline" href="<?=esc(admin_url('mail-draft',['account_id'=>$row['id']]))?>">New draft</a> <a class="btn outline" href="<?=esc(admin_url('mail-submissions',['account_id'=>$row['id']]))?>">Submission history</a></p><?php endif; ?><p>Expires: <?=$row['expires_at']===null?'No expiry':esc(gmdate('Y-m-d H:i:s',(int)$row['expires_at']).' UTC')?></p></article>
<?php endforeach; ?></div>
<?php if (!$rows): ?><p>No currently available email accounts are assigned to you.</p><?php endif; ?>
<?php elseif ($view==='mail-accounts'):
    $rows=mail_accounts_list($page);
?>
<a class="btn gold" href="<?=esc(admin_url('mail-account'))?>">Add email account</a>
<?php if(!mail_inbox_ready()): ?>
<section class="workspace-card" data-inbox-setup><h2>Prepare inbox reading</h2><p>Prepare request history before assigned staff load recent inbox headers. This step connects to no email server.</p>
<form method="post"><?php admin_action('mail_inbox_setup'); ?><?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Prepare inbox reading</button></form></section>
<?php else: ?><p>Inbox reading is ready. Assign yourself Read access, then open an account from My email accounts.</p><?php endif; ?>
<?php if(!mail_messages_ready()): ?>
<section class="workspace-card" data-message-setup><h2>Prepare message reading</h2><p>Prepare private request history before assigned staff open individual messages. This step connects to no email server.</p>
<form method="post"><?php admin_action('mail_message_setup'); ?><?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Prepare message reading</button></form></section>
<?php else: ?><p>Message reading is ready. Use Open message after loading recent inbox headers.</p><?php endif; ?>
<?php if(!mail_notices_ready()): ?>
<section class="workspace-card" data-mail-notices-setup><h2>Prepare email notices</h2><p>After inbox and message reading are prepared, enable recent email notices for assigned administrators. The bell uses the newest checked message window; this step contacts no server.</p>
<form method="post"><?php admin_action('mail_notices_setup'); ?><?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Prepare email notices</button></form></section>
<?php else: ?><p>Email notices are ready. Load recent headers to update the checked message window.</p><?php endif; ?>
<p class="hint">Only owners manage account settings and assignments. Owning a profile does not grant mailbox access.</p>
<div class="workspace-grid"><?php foreach (array_slice($rows,0,20) as $row): ?>
<article class="workspace-card"><h2><a href="<?=esc(admin_url('mail-account',['account_id'=>$row['id']]))?>"><?=esc($row['label'])?></a></h2><p><?=esc($row['address'])?></p><p><?=badge((int)$row['enabled']===1?'saved':'disabled')?> · Open settings for connection checks · Version <?=(int)$row['revision']?></p></article>
<?php endforeach; ?></div>
<?php if (!$rows): ?><p>No email accounts have been saved.</p><?php endif; ?>
<?php else:
    $accountId=is_string($_GET['account_id']??null)?$_GET['account_id']:'';
    $record=$accountId!==''?mail_accounts_get($accountId):null;
    $values=$record??['label'=>'','address'=>'','imap_host'=>'','imap_port'=>993,'imap_encryption'=>'ssl','imap_username'=>'','smtp_host'=>'','smtp_port'=>465,'smtp_encryption'=>'ssl','smtp_username'=>'','enabled'=>0,'revision'=>0];
    $revision=(int)$values['revision'];
    if (($_POST['action']??'')==='mail_accounts_save' && ($_POST['account_id']??'')===$accountId) {
        foreach (mail_accounts_fields() as $key=>$max) if (is_string($_POST[$key]??null)) $values[$key]=substr($_POST[$key],0,$max+512);
        $values['enabled']=($_POST['enabled']??'')==='1';
        if (is_string($_POST['revision']??null) && ctype_digit($_POST['revision'])) $revision=(int)$_POST['revision'];
    }
?>
<a href="<?=esc(admin_url('mail-accounts'))?>">← Email accounts</a>
<?php if ($record): ?><p><?=badge((int)$record['enabled']===1?'saved':'disabled')?> · Saved version <?=(int)$record['revision']?></p><?php endif; ?>
<form method="post" class="workspace-card" data-mail-account-editor>
<?php admin_action('mail_accounts_save'); ?>
<input type="hidden" name="account_id" value="<?=esc($accountId)?>"><input type="hidden" name="revision" value="<?=$revision?>">
<h2>Account identity</h2>
<?php admin_input('label','Account name',$values['label'],'text','required maxlength="120"');admin_input('address','Email address',$values['address'],'email','required maxlength="190"'); ?>
<div class="workspace-grid">
<?php foreach (['imap'=>'Incoming mail (IMAP)','smtp'=>'Outgoing mail (SMTP)'] as $protocol=>$title): ?>
<section><h3><?=esc($title)?></h3>
<?php admin_input($protocol.'_host','Server hostname',$values[$protocol.'_host'],'text','required maxlength="253" autocomplete="off"');
admin_input($protocol.'_port','Server port',$values[$protocol.'_port'],'number','required min="1" max="65535"');
admin_select($protocol.'_encryption','Connection encryption',['ssl'=>'TLS from connection start','starttls'=>'STARTTLS required'],$values[$protocol.'_encryption']);
admin_input($protocol.'_username','Account username',$values[$protocol.'_username'],'text','required maxlength="190" autocomplete="off"');
admin_input($protocol.'_password',($record && $record['has_credentials'])?'New account password (blank keeps saved password)':'Account password','','password',(($record && $record['has_credentials'])?'':'required ').'maxlength="1024" autocomplete="new-password"'); ?>
</section>
<?php endforeach; ?></div>
<p class="hint">Passwords are never displayed or refilled after an error. Reenter any changed password before resubmitting. Use your provider's approved account or app password.</p>
<?php admin_check('enabled','Make this saved account available to assigned staff',$values['enabled']); ?>
<p class="hint">Saving settings does not check a connection or send an email. Keep a recoverable backup of the database and private mail-master.key together.</p>
<?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Save email account</button>
</form>
<?php if ($record): ?>
<?php require CWP_CODE_ROOT.'/admin/views/mail_connections.php'; ?>
<section class="workspace-card"><h2>Account availability</h2><form method="post">
<?=csrf_field()?><input type="hidden" name="account_id" value="<?=esc($accountId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?>
<?php if ((int)$record['enabled']===1): ?><button class="btn outline" name="action" value="mail_accounts_disable">Disable account</button>
<?php elseif ($record['has_credentials']): ?><button class="btn outline" name="action" value="mail_accounts_enable">Enable saved account</button><?php endif; ?>
<?php if ($record['has_credentials']): ?><button class="btn outline" name="action" value="mail_accounts_clear">Clear saved passwords and disable</button><?php endif; ?>
</form><p class="hint">Disabling removes current staff availability. Clearing removes stored passwords and disables this account; settings and assignment history remain.</p></section>
<section class="workspace-card"><h2>Server Sent folder</h2><p>Accepted submissions can save an exact copy to this account's server Sent folder. Set the folder name your provider uses, such as Sent or INBOX.Sent. Saving this setting connects to no email server.</p>
<?php if(!mail_copies_ready()): ?>
<p class="hint">Sent-copy storage is not prepared yet.</p><form method="post"><?php admin_action('mail_accounts_sent_setup'); ?><input type="hidden" name="account_id" value="<?=esc($accountId)?>"><?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Prepare Sent-copy storage</button></form>
<?php else: $sentFolder=mail_sent_folder($accountId); ?>
<form method="post" data-mail-sent-folder><?php admin_action('mail_accounts_sent_folder'); ?><input type="hidden" name="account_id" value="<?=esc($accountId)?>">
<?php admin_input('sent_folder','Server Sent folder name',$sentFolder,'text','maxlength="64"'); ?>
<?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?>
<button class="btn outline">Save Sent folder</button></form>
<p class="hint">Current: <?=esc($sentFolder===''?'not set':$sentFolder)?>. Leave blank to clear the setting.</p>
<?php endif; ?></section>
<section class="workspace-card"><h2>Staff assignments</h2><p>Each staff member needs a separate assignment, including owners. Administrators also need the matching Read assigned email or Send from assigned email permission under Administrators. The owner also needs the corresponding assignment to check each connection. Reading and sending also require their respective storage to be prepared.</p>
<?php $grants=mail_accounts_grants($accountId);$options=[];
foreach (all('SELECT u.id,u.name,u.email FROM '.table('users').' u JOIN '.table('staff_profiles')." p ON p.user_id=u.id WHERE p.enabled=1 AND u.password_hash<>'' AND p.role IN ('owner','administrator') ORDER BY u.name,u.id") as $staff) $options[(string)$staff['id']]=$staff['name'].' · '.$staff['email'];
foreach ([null,...$grants] as $grant):
    $isNew=$grant===null;$userId=(int)($grant['user_id']??0);$grantRevision=(int)($grant['revision']??0);
    $submitted=($_POST['action']??'')==='mail_accounts_grant' && ($_POST['account_id']??'')===$accountId && ($isNew?(($_POST['revision']??'')==='0'):(string)$userId===($_POST['user_id']??''));
    if ($submitted && is_string($_POST['revision']??null) && ctype_digit($_POST['revision'])) $grantRevision=(int)$_POST['revision'];
    $days=($grant && $grant['expires_at']!==null)?max(1,(int)ceil(((int)$grant['expires_at']-time())/86400)):0;
?>
<form method="post" data-mail-grant><h3><?=$isNew?'Add an assignment':esc($grant['name'].' · '.$grant['email'])?></h3>
<?php admin_action('mail_accounts_grant'); ?><input type="hidden" name="account_id" value="<?=esc($accountId)?>"><input type="hidden" name="revision" value="<?=$grantRevision?>">
<?php if ($isNew) admin_select('user_id','Administrator',$options,$submitted?($_POST['user_id']??''):'');else echo '<input type="hidden" name="user_id" value="'.$userId.'">';
admin_check('can_read','Read this mailbox',$submitted?(($_POST['can_read']??'')==='1'):($grant['can_read']??false));
admin_check('can_send','Send from this mailbox',$submitted?(($_POST['can_send']??'')==='1'):($grant['can_send']??false));
admin_check('enabled','Assignment enabled',$submitted?(($_POST['enabled']??'')==='1'):($grant['enabled']??true));
admin_input('days','Expires in days (0 means no expiry)',$submitted && is_string($_POST['days']??null)?substr($_POST['days'],0,3):$days,'number','required min="0" max="365"');
admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?>
<button class="btn outline">Save assignment</button>
<?php if ($grant): ?><p class="hint">Version <?=(int)$grant['revision']?> · <?=(int)$grant['enabled']===1?'Enabled':'Disabled'?> · Expiry: <?=$grant['expires_at']===null?'None':esc(gmdate('Y-m-d H:i:s',(int)$grant['expires_at']).' UTC')?>. Saving starts the chosen duration again.</p><?php endif; ?>
</form><?php endforeach; ?></section>
<?php $historyPage=max(1,min((int)($_GET['history_page']??1),100000));$history=mail_accounts_history($accountId,$historyPage); ?>
<h2>Settings history</h2><p class="hint">Prior settings are retained without passwords. Review older values here; restoring credentials requires your protected backup.</p>
<?php foreach (array_slice($history,0,20) as $snapshot): $metadata=json_decode($snapshot['metadata'],true,16,JSON_THROW_ON_ERROR); ?>
<details class="workspace-card"><summary>Version <?=(int)$snapshot['revision']?> · <?=esc($snapshot['action'])?> · <?=esc($snapshot['recorded_at'])?> UTC · <?=esc($snapshot['actor_name']??'Former administrator')?></summary><dl><?php foreach (mail_accounts_fields() as $key=>$limit): ?><dt><?=esc(str_replace('_',' ',$key))?></dt><dd><?=esc((string)($metadata[$key]??''))?></dd><?php endforeach; ?></dl></details>
<?php endforeach; ?>
<nav aria-label="Account history pages"><?php if ($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-account',['account_id'=>$accountId,'history_page'=>$historyPage-1]))?>">Newer settings</a><?php endif; ?><?php if (count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('mail-account',['account_id'=>$accountId,'history_page'=>$historyPage+1]))?>">Older settings</a><?php endif; ?></nav>
<?php $grantPage=max(1,min((int)($_GET['grant_page']??1),100000));$grantHistory=mail_accounts_grant_history($accountId,$grantPage); ?>
<h2>Assignment history</h2>
<?php foreach (array_slice($grantHistory,0,20) as $snapshot): ?>
<article class="workspace-card"><h3><?=esc($snapshot['staff_name']??'Former administrator')?> · Version <?=(int)$snapshot['revision']?></h3><p><?=esc($snapshot['recorded_at'])?> UTC · <?=esc($snapshot['actor_name']??'Former administrator')?></p><p>Read: <?=(int)$snapshot['can_read']===1?'Yes':'No'?> · Send: <?=(int)$snapshot['can_send']===1?'Yes':'No'?> · Assignment: <?=(int)$snapshot['enabled']===1?'Enabled':'Disabled'?></p><p>Expiry: <?=$snapshot['expires_at']===null?'None':esc(gmdate('Y-m-d H:i:s',(int)$snapshot['expires_at']).' UTC')?></p></article>
<?php endforeach; ?>
<nav aria-label="Assignment history pages"><?php if ($grantPage>1): ?><a class="btn outline" href="<?=esc(admin_url('mail-account',['account_id'=>$accountId,'grant_page'=>$grantPage-1]))?>">Newer assignments</a><?php endif; ?><?php if (count($grantHistory)>20): ?><a class="btn outline" href="<?=esc(admin_url('mail-account',['account_id'=>$accountId,'grant_page'=>$grantPage+1]))?>">Older assignments</a><?php endif; ?></nav>
<?php endif; endif; ?>
<?php if (in_array($view,['mailboxes','mail-accounts'],true)): ?><nav aria-label="Email account pages"><?php if ($page>1): ?><a class="btn outline" href="<?=esc(admin_url($view,['p'=>$page-1]))?>">Previous</a><?php endif; ?><?php if (count($rows)>20): ?><a class="btn outline" href="<?=esc(admin_url($view,['p'=>$page+1]))?>">Next</a><?php endif; ?></nav><?php endif; ?>
