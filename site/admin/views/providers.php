<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$record=provider_settings_profile();$profileId=$record['id']??'';
$values=$record??['label'=>'Studio 20i','revision'=>0,'enabled'=>0];$revision=(int)$values['revision'];
require_once CWP_CODE_ROOT.'/app/providers/workspace.php';
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
