<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$record=provider_settings_profile();$profileId=$record['id']??'';
$values=$record??['label'=>'Studio 20i','revision'=>0,'enabled'=>0];$revision=(int)$values['revision'];
provider_cloud_site_request_confirmation_render();
if(($_POST['action']??'')==='provider_settings_save'&&($_POST['profile_id']??'')===$profileId){
    if(is_string($_POST['label']??null))$values['label']=substr($_POST['label'],0,632);
    $values['enabled']=($_POST['enabled']??'')==='1';
    if(is_string($_POST['revision']??null)&&ctype_digit($_POST['revision']))$revision=(int)$_POST['revision'];
}
?>
<?php
// This overview uses only the current owner-safe projections and recorded read
// results. It does not contact 20i or enable any provider operation.
$hubHasKey=$record && (bool)$record['has_credentials'];
$hubEnabled=$hubHasKey && (int)$record['enabled']===1;
$hubChecks=$record?provider_checks_history($profileId,1):[];
$hubLastCheck=$hubChecks[0]??null;
$hubVerified=$hubEnabled && $hubLastCheck
    && (int)$hubLastCheck['profile_revision']===(int)$record['revision']
    && ($hubLastCheck['result_code']??null)==='connected';
$hubHasInventory=$record&&provider_inventory_ready();
$hubRefresh=$hubHasInventory?provider_inventory_latest($profileId):null;
$hubInventoryCurrent=$hubHasInventory&&$hubRefresh
    && (int)$hubRefresh['profile_revision']===(int)$record['revision']
    && ($hubRefresh['state']??'')==='complete';
$hubNext=!$hubHasKey
    ?['Save the General API key','twentyi-connection','Start by saving the key from your My20i reseller account.']
    :(!$hubEnabled
      ?['Enable connection checks','twentyi-connection','Your key is saved, but connection checks are disabled.']
      :(!$hubVerified
        ?['Check your connection','twentyi-check','Confirm 20i accepts the current key for a package-list read.']
        :(!$hubHasInventory
          ?['Prepare inventory storage','twentyi-hosting','Create local records for read-only hosting observations.']
          :(!$hubInventoryCurrent
            ?['Refresh your hosting inventory','twentyi-hosting','Save a fresh package observation under the current connection settings.']
            :['Review hosting and client links','twentyi-hosting','Your latest complete hosting observation is ready for review.']))));
?>
<section class="workspace-card provider-hub-intro" aria-labelledby="twentyi-hub-title" data-provider-hub>
  <div class="provider-hub-header">
    <span class="eyebrow">HOSTING &amp; DOMAINS / CONNECTION GUIDE</span>
    <h2 id="twentyi-hub-title">Your 20i control center</h2>
    <p>Connect your existing My20i reseller account, see which services have actually been observed, and manage the client links you approve. The sections below are organized by task, rather than mixing every provider feature together.</p>
  </div>
  <div class="provider-hub-status" aria-label="Connection setup status">
    <div><span class="provider-hub-step">01</span><strong>API key</strong><small><?=esc($hubHasKey?'Saved securely':'Not saved')?></small></div>
    <div><span class="provider-hub-step">02</span><strong>Connection</strong><small><?=esc($hubVerified?'Read verified for current settings':($hubEnabled?'Check required':'Not enabled'))?></small></div>
    <div><span class="provider-hub-step">03</span><strong>Inventory</strong><small><?=esc($hubInventoryCurrent?'Current package read complete':($hubHasInventory?'Refresh required':'Storage not prepared'))?></small></div>
    <div><span class="provider-hub-step">04</span><strong>Clients &amp; products</strong><small>Link or draft only after review</small></div>
  </div>
  <div class="provider-hub-next" aria-label="Recommended next step">
    <div><span class="eyebrow">RECOMMENDED NEXT STEP</span><h3><?=esc($hubNext[0])?></h3><p><?=esc($hubNext[2])?></p></div>
    <a class="btn gold" href="#<?=esc($hubNext[1])?>">Go to this step</a>
  </div>
  <nav class="provider-hub-nav" aria-label="20i connection topics">
    <a href="#twentyi-connection">1. Connection</a>
    <a href="#twentyi-hosting">2. Hosting</a>
    <a href="#twentyi-cloud">3. Cloud</a>
    <a href="#twentyi-domains">4. Domains</a>
    <a href="#twentyi-site-tools">5. Website controls</a>
    <a href="#twentyi-access">6. Access &amp; advanced</a>
  </nav>
  <div class="provider-hub-capabilities">
    <section>
      <h3>Read from 20i</h3>
      <p>Check the General API key, retrieve hosting and cloud observations, and review domain or package details where the corresponding read tool has been prepared. A successful read is a snapshot, not proof of complete account coverage.</p>
    </section>
    <section>
      <h3>Organize locally</h3>
      <p>Associate an observed package or domain with a CajunWebPro client. You can also create <strong>unpublished, unpriced</strong> hosting-product drafts in <a href="<?=esc(admin_url('catalog'))?>">Service catalog</a>. Local links are not transfers of ownership.</p>
    </section>
    <section>
      <h3>Changes requiring approval</h3>
      <p>Provider-side changes, cloud website creation, package configuration and similar operations follow separate owner review, permission and safety workflows. Checking a key or refreshing inventory does <strong>not</strong> create orders, change services or charge anyone.</p>
    </section>
  </div>
  <details class="provider-hub-help">
    <summary>Where do I get the General API key, and what should I do first?</summary>
    <div class="provider-hub-help-content">
      <p><strong>In My20i:</strong> sign in, go to <strong>Manage Reseller → API</strong> and find or generate the <strong>General API key</strong>. Open <a href="https://my.20i.com/reseller/api" target="_blank" rel="noopener noreferrer">20i's API page</a> or consult <a href="https://docs.20i.com/api/20i-api-documentation" target="_blank" rel="noopener noreferrer">20i's API guide</a>.</p>
      <p>Paste the raw General API key into the connection form below, choose a name, enable explicit checks, and save. Next use <strong>Check saved 20i connection</strong>, then <strong>Refresh package inventory</strong>. The General API key is different from your CajunWebPro administrator password and from any separate StackCP client authentication key. Do not paste credentials into support chats.</p>
    </div>
  </details>
  <details class="provider-hub-help">
    <summary>What is the difference between a hosting package, a package type and a client link?</summary>
    <div class="provider-hub-help-content">
      <p><strong>Hosting package:</strong> an existing hosted service returned by the package-list API. <strong>Package type:</strong> the reported plan/type reference associated with an existing package. <strong>Client link:</strong> your local mapping between a verified 20i service and a CajunWebPro client. Creating that link does not order a new package, transfer provider ownership, or create billing.</p>
      <p>To prepare a sellable service, use the Service catalog to review an imported hosting-type draft, add your own prices, terms, description and image, then wait for separately verified provider ordering and publication support.</p>
    </div>
  </details>
  <details class="provider-hub-help">
    <summary>Why can the connection say “verified” while some features are unavailable?</summary>
    <div class="provider-hub-help-content">
      <p>The key check proves only that 20i accepted a particular <em>read</em> at a recorded time. Cloud servers, domain registration, mailbox creation, StackCP access, billing and provider mutations require separate permissions, setup, observations or review. A missing or partial observation is not proof that a 20i service does not exist.</p>
      <p>If a feature says <strong>Prepare</strong>, it usually creates local history tables rather than contacting 20i. <strong>Refresh</strong> typically reads provider data. <strong>Link</strong> creates a local client association. Actions marked <strong>Approve</strong>, <strong>Apply</strong>, <strong>Create</strong> or <strong>Change</strong> should be reviewed carefully because their effects may differ.</p>
    </div>
  </details>
</section>
<div class="provider-hub-group-heading" id="twentyi-connection"><span class="eyebrow">01 / CONNECT</span><h2>Connection &amp; API key</h2><p>Manage your saved key, test one read, and review connection history. Nothing in this section places an order.</p></div>
<section class="workspace-card"><h2>Your 20i account</h2>
<p>Owners can save a General API key and explicitly check whether 20i accepts it for the package-list read. This uses your live 20i account.</p>
<p>Hosting, domains, VPS, billing and SSH controls still need their own integration and verification.</p>
<?php if($record): ?><p><?=badge((int)$record['enabled']===1?'enabled':'disabled')?> · Saved version <?=(int)$record['revision']?> · <?=esc($record['has_credentials']?'General API key saved':'No General API key saved')?></p><?php endif; ?>
<form method="post" data-provider-editor>
<?php admin_action('provider_settings_save'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=$revision?>">
<?php admin_input('label','Connection name',$values['label'],'text','required maxlength="120"');
admin_input('general_key',($record&&$record['has_credentials'])?'Replacement General API key (blank keeps saved key)':'General API key','','password',(($record&&$record['has_credentials'])?'':'required ').'maxlength="4096" autocomplete="new-password"'); ?>
<p class="hint">Enter the raw General API key from 20i. It is never shown again or refilled after an error. Reenter a changed key before resubmitting.</p>
<?php admin_check('enabled','Allow explicit checks of this saved connection',$values['enabled']);
admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Save 20i settings</button>
</form></section>
<?php if($record): ?>
<section class="workspace-card" id="twentyi-check"><h2>Check the saved connection</h2>
<p>This makes one read request to 20i. A successful check confirms that read at the recorded time. Saving settings does not run a check.</p>
<?php if((int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post" data-provider-check><?php admin_action('provider_settings_check'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Check saved 20i connection</button></form>
<?php else: ?><p>Save a General API key and allow explicit checks first.</p><?php endif; ?>
<p class="hint">Wait 30 seconds between checks. Each owner can start up to 20 checks per hour.</p></section>
<details class="workspace-card provider-hub-utility"><summary>Advanced connection maintenance · enable, disable or clear saved key</summary><div class="provider-hub-utility-body"><h2>Connection availability</h2>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<?php if((int)$record['enabled']===1): ?><button class="btn outline" name="action" value="provider_settings_disable">Disable connection checks</button>
<?php elseif($record['has_credentials']): ?><button class="btn outline" name="action" value="provider_settings_enable">Allow connection checks</button><?php endif; ?>
<?php if($record['has_credentials']): ?><button class="btn outline" name="action" value="provider_settings_clear">Clear saved key and disable</button><?php endif; ?>
</form><p class="hint">Clearing removes the saved General API key and keeps the profile and history. Keep a recoverable backup of your database and private provider-master.key together.</p></div></details>
<?php $checkPage=max(1,min((int)($_GET['check_page']??1),100000));$attempts=provider_checks_history($profileId,$checkPage); ?>
<details class="workspace-card provider-hub-utility" <?=isset($_GET['check_page'])?'open':''?>><summary>Past connection checks and diagnostics</summary><div class="provider-hub-utility-body" data-provider-history><h2>Connection checks</h2>
<?php if(!$attempts): ?><p>No connection check has been recorded.</p><?php endif; ?>
<?php foreach(array_slice($attempts,0,20) as $attempt): ?>
<article class="workspace-card"><h3><?=esc($attempt['result_code']==='connected'?'Read verified':($attempt['state']===null?'Incomplete':ucfirst(str_replace('_',' ',$attempt['state']))))?></h3>
<p><?=esc($attempt['started_at'])?> UTC · <?=esc($attempt['actor_name']??'Former owner')?> · Saved version <?=(int)$attempt['profile_revision']?><?=((int)$attempt['profile_revision']!==(int)$record['revision'])?' · Historical settings':''?></p>
<p><?=esc(provider_checks_message($attempt['result_code']))?></p></article>
<?php endforeach; ?>
<nav aria-label="Connection check pages"><?php if($checkPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['check_page'=>$checkPage-1]))?>">Newer checks</a><?php endif; ?><?php if(count($attempts)>20): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['check_page'=>$checkPage+1]))?>">Older checks</a><?php endif; ?></nav></div></details>
<?php $historyPage=max(1,min((int)($_GET['history_page']??1),100000));$history=provider_settings_history($profileId,$historyPage); ?>
<details class="workspace-card provider-hub-utility" <?=isset($_GET['history_page'])?'open':''?>><summary>Saved key and connection change history</summary><div class="provider-hub-utility-body"><h2>Settings history</h2>
<?php foreach(array_slice($history,0,20) as $snapshot): ?><article><h3>Version <?=(int)$snapshot['revision']?> · <?=esc($snapshot['label'])?></h3><p><?=esc($snapshot['recorded_at'])?> UTC · <?=esc($snapshot['actor_name']??'Former owner')?> · <?=esc(ucfirst($snapshot['action']))?> · <?=esc((int)$snapshot['enabled']===1?'Checks enabled':'Checks disabled')?> · <?=esc((int)$snapshot['has_credentials']===1?'Key was saved':'No key saved')?></p></article><?php endforeach; ?>
<nav aria-label="Settings history pages"><?php if($historyPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['history_page'=>$historyPage-1]))?>">Newer settings</a><?php endif; ?><?php if(count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['history_page'=>$historyPage+1]))?>">Older settings</a><?php endif; ?></nav></div></details>
<?php endif; ?>
<div class="provider-hub-group-heading" id="twentyi-hosting"><span class="eyebrow">02 / HOSTING</span><h2>Hosting packages &amp; client links</h2><p>Refresh an observed package list, review assigned clients and prepare unpublished catalog drafts. No hosting purchase or payment happens here.</p><p><a href="<?=esc(admin_url('catalog'))?>">Open Service catalog</a> to work with reviewed hosting-type drafts.</p></div>
<section class="workspace-card" data-provider-inventory><h2>Hosting package inventory</h2>
<p>An explicit refresh performs one authenticated package-list read against the saved 20i profile and keeps the validated result. Observations are scoped to this saved profile and never claim to be complete account coverage.</p>
<?php if(!$record): ?>
<p>Save your 20i connection above before refreshing inventory.</p>
<?php elseif(!provider_inventory_ready()): ?>
<p>Prepare durable inventory storage first. Back up your database before adding these tables.</p>
<form method="post"><?php admin_action('provider_inventory_prepare'); ?><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare inventory storage</button></form>
<?php else:
$latest=provider_inventory_latest($profileId);$observation=provider_inventory_observation($profileId);
$packages=$observation?provider_inventory_packages($observation['id']):[];
$links=[];foreach(provider_inventory_links($profileId) as $link)$links[(int)$link['package_id']]=$link;
$clients=provider_inventory_clients();
?>
<?php if($latest): ?><p>Last refresh: <?=badge($latest['state'])?> <?=esc($latest['started_at'])?> UTC · <?=esc($latest['actor_name']??'Former owner')?> · Saved version <?=(int)$latest['profile_revision']?><?php if($latest['reason']!==''): ?> · <?=esc(str_replace('_',' ',$latest['reason']))?><?php endif; ?></p>
<?php if($observation&&(int)$observation['profile_revision']!==(int)$record['revision']): ?><p class="hint">The saved observation below was made under an earlier settings version; refresh under the current version before relying on it.</p><?php endif; ?>
<?php else: ?><p>No inventory refresh has been recorded.</p><?php endif; ?>
<?php if((int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post" data-provider-refresh><?php admin_action('provider_inventory_refresh'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Refresh package inventory</button></form>
<p class="hint">One bounded request per refresh. Wait 30 seconds between refreshes; each owner can start up to 20 per hour.</p>
<?php else: ?><p>Save a General API key and allow explicit checks before refreshing inventory.</p><?php endif; ?>
<?php if($observation): ?>
<h3>Observed packages<?=$observation['id']!==($latest['id']??null)?' — earlier than the latest refresh':''?></h3>
<p><?=esc($observation['started_at'])?> UTC · <?=esc($observation['state'])?> · coverage <?=esc($observation['coverage'])?><?php if($observation['skipped']>0): ?> · <?=(int)$observation['skipped']?> skipped<?php endif; ?></p>
<?php if($observation['state']==='partial'): ?><p class="hint">Some reported packages were skipped because they did not match the documented shape. Absence from a partial observation does not prove a package was removed.</p><?php endif; ?>
<?php foreach($packages as $package): $linked=$links[(int)$package['package_id']]??null; ?>
<article class="workspace-card"><h3><?=esc($package['name'])?> <span class="workspace-status">#<?=(int)$package['package_id']?></span></h3>
<p><?=esc($package['package_type_name']??'Type unreported')?> · <?=esc((int)$package['enabled']===1?'Enabled':'Disabled')?> · Created <?=esc($package['created_at'])?><?php if($package['names']): ?> · <?=esc(implode(', ',$package['names']))?><?php endif; ?></p>
<?php if($package['package_labels']||$package['stack_users']): ?><p class="hint"><?php if($package['package_labels']): ?>Labels: <?=esc(implode(', ',$package['package_labels']))?><?php endif; ?><?php if($package['stack_users']): ?> Stack users: <?=esc(implode(', ',$package['stack_users']))?><?php endif; ?></p><?php endif; ?>
<?php if($linked): $linkStale=!provider_inventory_link_context_matches($linked,$record,$observation); ?><p><?=badge('linked')?> <?=esc($linked['client_name'])?> · linked <?=esc($linked['linked_at'])?> UTC by <?=esc($linked['linked_by_name']??'Former owner')?><?=$linkStale?' · '.badge('requires re-approval'):''?></p>
<?php if($linkStale): ?><p class="hint">Provider settings changed after this link was approved, so the client view is suspended. Verify this package still belongs to the intended client under the current provider account, then re-approve it.</p>
<?php if((int)$observation['profile_revision']===(int)$record['revision']): ?><form method="post"><?php admin_action('provider_inventory_relink'); ?><input type="hidden" name="link_id" value="<?=esc($linked['id'])?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Re-approve link</button></form><?php else: ?><p class="hint">Refresh the inventory under the current settings before re-approving.</p><?php endif; ?>
<?php endif; ?>
<form method="post"><?php admin_action('provider_inventory_unlink'); ?><input type="hidden" name="link_id" value="<?=esc($linked['id'])?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Unlink service</button></form>
<?php elseif($clients): ?><form method="post"><?php admin_action('provider_inventory_link'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><input type="hidden" name="package_id" value="<?=(int)$package['package_id']?>">
<label>Client account<select name="client_id" required><?php foreach($clients as $client): ?><option value="<?=(int)$client['id']?>"><?=esc($client['name'].' · '.$client['email'])?></option><?php endforeach; ?></select></label>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Link to client</button></form>
<?php endif; ?></article>
<?php endforeach; ?>
<?php $observedIds=[];foreach($packages as $package)$observedIds[(int)$package['package_id']]=true;
$staleLinks=[];foreach($links as $linked)if(!isset($observedIds[(int)$linked['package_id']]))$staleLinks[]=$linked;
if($staleLinks): ?><h3>Linked services not in the latest observation</h3>
<p class="hint">These mappings were approved earlier but the package was absent from the last good read. Absence does not prove the package was removed; refresh again or unlink the mapping.</p>
<?php foreach($staleLinks as $linked): ?><article class="workspace-card"><h3>Service #<?=(int)$linked['package_id']?></h3>
<p><?=badge('linked')?> <?=esc($linked['client_name'])?> · linked <?=esc($linked['linked_at'])?> UTC by <?=esc($linked['linked_by_name']??'Former owner')?><?=(int)$linked['profile_revision']!==(int)$record['revision']?' · '.badge('requires re-approval'):''?></p>
<?php if((int)$linked['profile_revision']!==(int)$record['revision']): ?><p class="hint">Provider settings changed after this link was approved. It can be re-approved only if the package appears in a current observation; otherwise remove it.</p><?php endif; ?>
<form method="post"><?php admin_action('provider_inventory_unlink'); ?><input type="hidden" name="link_id" value="<?=esc($linked['id'])?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Unlink service</button></form></article>
<?php endforeach; ?><?php endif; ?>
<?php $domainLinks=[];foreach(provider_inventory_domain_links($profileId) as $dl)$domainLinks[$dl['domain']]=$dl;
$observedDomains=[];foreach($packages as $package)foreach($package['names'] as $name){$d=strtolower(trim((string)$name));if($d!=='')$observedDomains[$d][(int)$package['package_id']]=true;}
ksort($observedDomains);
if($observedDomains||$domainLinks): ?><h3>Observed domains</h3>
<p class="hint">Domain links assign one observed hosting name to a client independently of the hosting package. These links do not establish registrar ownership or renewal rights. Moving to another package requires removing the old link and explicitly approving the new binding.</p>
<?php foreach($observedDomains as $d=>$pkgIds): foreach(array_keys($pkgIds) as $pkgId): $linked=$domainLinks[$d]??null;$bindingChanged=$linked&&(int)$linked['package_id']!==$pkgId;$linkStale=$linked&&!provider_inventory_link_context_matches($linked,$record,$observation); ?>
<article class="workspace-card"><h3><?=esc($d)?> <span class="workspace-status">package #<?=$pkgId?></span></h3>
<?php if($linked): ?><p><?=badge($bindingChanged?'different package':($linkStale?'requires re-approval':'linked'))?> <?=esc($linked['client_name'])?> · Approved package #<?=(int)$linked['package_id']?> · approved settings version <?=(int)$linked['profile_revision']?> · Current settings version <?=(int)$record['revision']?> · linked <?=esc($linked['linked_at'])?> UTC by <?=esc($linked['linked_by_name']??'Former owner')?></p>
<?php if($bindingChanged): ?><p class="hint">This domain is observed on package #<?=$pkgId?>, but its approval remains on package #<?=(int)$linked['package_id']?>. This package is not approved for that client. Unlink the old binding, verify the intended client and current provider context, then explicitly link the new package.</p>
<?php elseif($linkStale): ?><p class="hint">The approved provider context does not match this current observation, so this link requires re-approval. Verify the domain still belongs to the intended client, then re-approve it.</p>
<?php if((int)$observation['profile_revision']===(int)$record['revision']): ?><form method="post"><?php admin_action('provider_inventory_domain_relink'); ?><input type="hidden" name="link_id" value="<?=esc($linked['id'])?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Re-approve domain link</button></form><?php else: ?><p class="hint">Refresh the inventory under the current settings before re-approving.</p><?php endif; ?>
<?php endif; ?>
<form method="post"><?php admin_action('provider_inventory_domain_unlink'); ?><input type="hidden" name="link_id" value="<?=esc($linked['id'])?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Unlink domain</button></form>
<?php elseif($clients): ?><form method="post"><?php admin_action('provider_inventory_domain_link'); ?>
<input type="hidden" name="profile_id" value="<?=esc($profileId)?>"><input type="hidden" name="revision" value="<?=(int)$record['revision']?>"><input type="hidden" name="package_id" value="<?=$pkgId?>"><input type="hidden" name="domain" value="<?=esc($d)?>">
<label>Client account<select name="client_id" required><?php foreach($clients as $client): ?><option value="<?=(int)$client['id']?>"><?=esc($client['name'].' · '.$client['email'])?></option><?php endforeach; ?></select></label>
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Link domain to client</button></form>
<?php endif; ?></article>
<?php endforeach; endforeach; ?>
<?php $staleDomains=array_diff(array_keys($domainLinks),array_keys($observedDomains));if($staleDomains): ?><h4>Linked domains not in the latest observation</h4>
<p class="hint">These domain mappings were approved earlier but the name is absent from the last good read. Absence does not prove the domain was removed; refresh again or unlink the mapping.</p>
<?php foreach($staleDomains as $d): $linked=$domainLinks[$d]; ?><article class="workspace-card"><h3><?=esc($d)?> · Approved package #<?=(int)$linked['package_id']?></h3>
<p><?=badge('linked')?> <?=esc($linked['client_name'])?> · linked <?=esc($linked['linked_at'])?> UTC by <?=esc($linked['linked_by_name']??'Former owner')?><?=(int)$linked['profile_revision']!==(int)$record['revision']?' · '.badge('requires re-approval'):''?></p>
<form method="post"><?php admin_action('provider_inventory_domain_unlink'); ?><input type="hidden" name="link_id" value="<?=esc($linked['id'])?>"><?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Unlink domain</button></form></article>
<?php endforeach; ?><?php endif; ?>
<?php endif; ?>
<?php $events=provider_inventory_link_events($profileId);if($events): ?><h3>Link history</h3><?php foreach(array_slice($events,0,20) as $event): ?><p><?=esc($event['recorded_at'])?> UTC · <?=esc(match($event['event']){'link'=>'Linked','relink'=>'Re-approved',default=>'Unlinked'})?> package #<?=(int)$event['package_id']?><?=$event['client_name']?' · '.esc($event['client_name']):''?> · <?=esc($event['actor_name']??'Former owner')?></p><?php endforeach; ?><?php endif; ?>
<?php $domainEvents=provider_inventory_domain_link_events($profileId);if($domainEvents): ?><h3>Domain link history</h3><?php foreach(array_slice($domainEvents,0,20) as $event): ?><p><?=esc($event['recorded_at'])?> UTC · <?=esc(match($event['event']){'link'=>'Linked','relink'=>'Re-approved',default=>'Unlinked'})?> <?=esc($event['domain'])?><?=$event['client_name']?' · '.esc($event['client_name']):''?> · <?=esc($event['actor_name']??'Former owner')?></p><?php endforeach; ?><?php endif; ?>
<?php endif; ?>
<?php $refreshPage=max(1,min((int)($_GET['refresh_page']??1),100000));$history=provider_inventory_history($profileId,$refreshPage);if($history): ?><h3>Refresh history</h3>
<?php foreach(array_slice($history,0,20) as $row): ?><p><?=esc($row['started_at'])?> UTC · <?=badge($row['state'])?><?=$row['reason']!==''?' · '.esc(str_replace('_',' ',$row['reason'])):''?><?php if(in_array($row['state'],['complete','partial'],true)): ?> · <?=(int)$row['package_count']?> packages<?=$row['skipped']>0?', '.(int)$row['skipped'].' skipped':''?><?php endif; ?> · saved version <?=(int)$row['profile_revision']?> · <?=esc($row['actor_name']??'Former owner')?></p><?php endforeach; ?>
<nav aria-label="Refresh history pages"><?php if($refreshPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['refresh_page'=>$refreshPage-1]))?>">Newer refreshes</a><?php endif; ?><?php if(count($history)>20): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['refresh_page'=>$refreshPage+1]))?>">Older refreshes</a><?php endif; ?></nav>
<?php endif; ?>
<?php endif; ?></section>
<?php require CWP_CODE_ROOT.'/admin/views/provider_schedule.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_cloud.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_cloud_providers.php'; ?>
<section class="workspace-card"><h2>Service capabilities</h2><p>A successful connection check confirms access to the hosting list. It does not confirm that every service is available or approved for use.</p>
<h3>Additional services still to be connected</h3><ul><li>Automatic hosting plan discovery and account balance</li><li>Hosting plan changes and deletion</li><li>Domain registration, renewals and transfers</li><li>VPS ordering and power controls</li><li>Backup restoration and SSH access</li></ul></section>

<?php require CWP_CODE_ROOT.'/admin/views/provider_domain_search.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_registration_periods.php'; ?>

<?php require CWP_CODE_ROOT.'/admin/views/provider_mailboxes.php'; ?>

<?php require CWP_CODE_ROOT.'/admin/views/provider_registrar.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/domain_contracts.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/domain_expiry.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/domain_renewals.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/service_observations.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_package_php.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_document_roots.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_package_force_ssl.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_directory_listing.php'; ?>
<?php provider_force_ssl_controls_admin_render(); ?>
<?php provider_directory_indexing_controls_admin_render(); ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_package_wordpress_installed.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_forwarding.php'; ?>
<?php provider_cloud_site_requests_admin_render(); ?>

<?php provider_stackcp_admin_render(); ?>
<?php provider_password_reset_template_admin_render(); ?>
<?php provider_stackcp_access_admin_render(); ?>
