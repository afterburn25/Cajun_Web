<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<section class="workspace-card" data-client-domain-settings>
<h2>Client domain search</h2>
<p>Allow signed-in clients to check the domain extensions you offer. Searches share the existing provider limits and do not purchase or reserve a domain.</p>
<?php if(!provider_domain_search_ready()): ?>
<p>Prepare owner domain search first.</p>
<?php else:
try{$clientSearchReady=provider_domain_client_family();$clientSearchPolicy=$clientSearchReady?provider_domain_client_policy():null;}
catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';$clientSearchReady=false;$clientSearchPolicy=null;}
if(!$clientSearchReady): ?>
<form method="post"><?php admin_action('provider_inventory_domain_search_client_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare client domain search</button></form>
<?php else:
$clientSearchRevision=$clientSearchPolicy['revision']??0;$clientSearchEnabled=$clientSearchPolicy['enabled']??false;
if(($_POST['action']??'')==='provider_inventory_domain_search_client_configure'){
    $postedRevision=$_POST['client_search_revision']??null;
    if(is_string($postedRevision)&&preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$postedRevision)&&(int)$postedRevision<=2147483647)$clientSearchRevision=(int)$postedRevision;
    $clientSearchEnabled=($_POST['client_search_enabled']??'')==='1';
}
?>
<form method="post"><?php admin_action('provider_inventory_domain_search_client_configure'); ?>
<input type="hidden" name="client_search_revision" value="<?=$clientSearchRevision?>">
<?php admin_check('client_search_enabled','Allow clients to check domain availability',$clientSearchEnabled);admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?>
<p class="hint">Enable owner lookups and save your offered extensions first. Approve client search again after changing the connection or search settings. Disabling it keeps previous searches.</p>
<button class="btn gold">Save client domain search</button></form>
<?php endif;endif; ?>
</section>
