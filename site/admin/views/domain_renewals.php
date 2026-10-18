<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewals_views.php';provider_settings_manager();
?>
<section class="workspace-card" id="domain-renewal-requests"><h2>Domain renewal requests</h2><p>Review client requests and save a message for the client or decline. Requests do not purchase a renewal. Confirm pricing, eligible terms and registry requirements separately before making an offer.</p>
<?php try{$renewalReady=domain_renewals_ready();$renewalHistory=$renewalReady?domain_renewals_history(null,max(1,(int)($_GET['renewal_page']??1))):[];}
catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p></section>';return;}
if(!$renewalReady): ?><form method="post"><?php admin_action('provider_inventory_renewal_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Enable renewal requests</button></form><p class="hint">Requires prepared registered-domain inventory and client accounts. No provider call is made.</p>
<?php else: ?>
<?php if(!$renewalHistory): ?><p>No renewal requests on this page.</p><?php endif; ?>
<?php foreach($renewalHistory as $renewalDoc)domain_renewals_render_history($renewalDoc,true);$renewalPage=max(1,min(10000,(int)($_GET['renewal_page']??1))); ?>
<nav class="button-row" aria-label="Renewal request pages"><?php if($renewalPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['renewal_page'=>$renewalPage-1]))?>#domain-renewal-requests">Newer requests</a><?php endif;?><?php if(count($renewalHistory)===20&&$renewalPage<10000): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['renewal_page'=>$renewalPage+1]))?>#domain-renewal-requests">Older requests</a><?php endif;?></nav>
<?php endif;?></section>
