<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$record=provider_settings_profile();$profileId=$record['id']??'';
$values=$record??['label'=>'Studio 20i','revision'=>0,'enabled'=>0];$revision=(int)$values['revision'];
require_once CWP_CODE_ROOT.'/app/providers/workspace.php';
$cloudAssignmentFocused=($_GET['focus']??null)==='cloud-assignment';
if($cloudAssignmentFocused){
    ?><section class="workspace-card" data-cloud-assignment-focus><h2>Cloud assignments</h2><p>Review cloud observations, client assignments and their recorded history. This focused page does not load unrelated website or package controls.</p><a class="btn outline" href="<?=esc(admin_url('provider-settings'))?>">All 20i workspaces</a></section><?php
    require CWP_CODE_ROOT.'/admin/views/provider_cloud.php';
    return;
}
if(($_POST['action']??'')==='provider_settings_save'&&($_POST['profile_id']??'')===$profileId){
    if(is_string($_POST['label']??null))$values['label']=substr($_POST['label'],0,632);
    $values['enabled']=($_POST['enabled']??'')==='1';
    if(is_string($_POST['revision']??null)&&ctype_digit($_POST['revision']))$revision=(int)$_POST['revision'];
}

$section=provider_workspace_section($_GET);
$sections=provider_workspace_sections();
$hasKey=$record&&!empty($record['has_credentials']);
$enabled=$hasKey&&(int)$record['enabled']===1;
$checks=$record?provider_checks_history($profileId,1):[];
$lastCheck=$checks[0]??null;
$verified=$enabled&&$lastCheck&&(int)$lastCheck['profile_revision']===(int)$record['revision']&&($lastCheck['result_code']??'')==='connected';
$connectionState=!$hasKey?'Add your API key':(!$enabled?'Connection paused':($verified?'Last read verified':'Connection check needed'));
$nextTitle=!$hasKey?'Connect your 20i account':(!$enabled?'Enable your saved connection':(!$verified?'Verify your saved connection':'Load the plans you created in 20i'));
$nextUrl=$verified?admin_url('catalog',['import_source'=>'package.types']):admin_url('provider-settings',['section'=>'connection']);
?>
<section class="workspace-card provider-console" aria-labelledby="provider-console-title" data-provider-hub>
 <div class="provider-console-heading"><div><span class="eyebrow">HOSTING &amp; DOMAINS</span><h2 id="provider-console-title">20i workspace</h2><p>One task at a time. Your plans, existing services and storefront have separate, clearly marked steps.</p></div><span class="provider-console-state"><?=esc($connectionState)?></span></div>
 <nav class="provider-console-nav" aria-label="20i workspaces">
 <?php foreach($sections as $key=>$label): ?><a href="<?=esc(admin_url('provider-settings',['section'=>$key]))?>" <?=$section===$key?'aria-current="page"':''?>><?=esc($label)?></a><?php endforeach; ?>
 </nav>
 <div class="provider-console-shortcuts"><a href="<?=esc(admin_url('catalog',['import_source'=>'package.types']))?>">Import 20i plans</a><a href="<?=esc(admin_url('catalog'))?>#catalog-products">Manage products &amp; prices</a><a href="<?=esc(admin_url('hosting-offers'))?>">Publish hosting offers</a><a href="<?=esc(page_url('hosting'))?>" target="_blank" rel="noopener">View hosting page ↗</a></div>
</section>
<?php if($section==='overview'): ?>
<section class="workspace-card provider-console-next"><div><span class="eyebrow">NEXT STEP</span><h2><?=esc($nextTitle)?></h2><p><?=esc($verified?'Read the package-type catalog, then select plans for your local products. You do not need to create a customer hosting account first.':'Save and verify your General API key before loading your hosting plans. Saving a key does not import products.')?></p></div><a class="btn gold" href="<?=esc($nextUrl)?>">Continue →</a></section>
<div class="provider-console-steps">
 <article class="workspace-card"><span class="eyebrow">01 / CONNECT</span><h3>Connect once</h3><p>Save your General API key and check the connection. Use your signed-in owner session; no repeat password entry.</p><a class="btn outline" href="<?=esc(admin_url('provider-settings',['section'=>'connection']))?>">Connection settings</a></article>
 <article class="workspace-card"><span class="eyebrow">02 / IMPORT PLANS</span><h3>Find your 20i plans</h3><p>Read the plans you created in My20i, including plans that have no customer hosting accounts yet. Import selected plans as local drafts.</p><a class="btn outline" href="<?=esc(admin_url('catalog',['import_source'=>'package.types']))?>">Open plan importer</a></article>
 <article class="workspace-card"><span class="eyebrow">03 / SET PRICES</span><h3>Prepare your products</h3><p>Review names, descriptions, images, monthly or annual prices and terms. Reimporting must not replace your edits.</p><a class="btn outline" href="<?=esc(admin_url('catalog'))?>#catalog-products">Manage local products</a></article>
 <article class="workspace-card"><span class="eyebrow">04 / PUBLISH</span><h3>Connect the storefront</h3><p>Review the product, provider mapping and service plan in Hosting offers. Publication is an explicit action; importing alone does not publish or order hosting.</p><a class="btn outline" href="<?=esc(admin_url('hosting-offers'))?>">Review hosting offers</a></article>
</div>
<section class="workspace-card provider-console-help"><h3>Already have customer websites at 20i?</h3><p><strong>Plans</strong> are what you offer for sale. <strong>Existing services</strong> are hosting accounts already created. Their inventory is separate from the plan catalog.</p><a href="<?=esc(admin_url('provider-settings',['section'=>'services']))?>">View existing services and client links →</a><details><summary>Where to find your key and advanced tools</summary><p>Use the General API key from My20i. Your administrator password is not an API key. Cloud, domains, website controls and StackCP have their own workspaces above; open only the area you need.</p><p><a href="https://my.20i.com/reseller/api" target="_blank" rel="noopener noreferrer">Open My20i API settings ↗</a></p></details></section>
<?php else:
    if($section==='cloud'||$section==='website')provider_cloud_site_request_confirmation_render();
    // Literal mapping is deliberate: request input never forms an include path.
    $views=['connection'=>'connection.php','services'=>'services.php','cloud'=>'cloud.php',
        'domains'=>'domains.php','website'=>'website.php','advanced'=>'advanced.php'];
    require CWP_CODE_ROOT.'/admin/views/provider_workspace/'.$views[$section];
endif;
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
<p><a class="btn outline" href="<?=esc(admin_url('https-requests'))?>">Review client HTTPS requests</a></p>
<?php provider_directory_indexing_controls_admin_render(); ?>
<p><a class="btn outline" href="<?=esc(admin_url('directory-requests'))?>">Review client directory listing requests</a></p>
<?php require CWP_CODE_ROOT.'/admin/views/provider_package_wordpress_installed.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_forwarding.php'; ?>

<?php provider_stackcp_admin_render(); ?>
<?php provider_stackcp_access_admin_render(); ?>
