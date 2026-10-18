<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/registration_period_views.php';
$periodPage=is_string($_GET['period_history_page']??null)&&ctype_digit($_GET['period_history_page'])?max(1,min((int)$_GET['period_history_page'],100000)):1;
$catalogPage=is_string($_GET['period_catalog_page']??null)&&ctype_digit($_GET['period_catalog_page'])?max(1,min((int)$_GET['period_catalog_page'],100000)):1;
?>
<section class="workspace-card" id="registration-periods" data-registration-periods>
<h2>Domain registration periods</h2>
<p>Save the provider's registration-year options with one explicit read. Clients see only extensions currently offered through their enabled domain-search policy.</p>
<?php if(!$record): ?><p>Save a 20i connection first.</p>
<?php else:
try{$periodContext=provider_registration_period_owner_context($record['id'],$periodPage,$catalogPage);}
catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p></section>';return;}
if($periodContext['state']==='not_prepared'): ?>
<p>Back up your database, then prepare the saved observation history. Preparation does not contact 20i.</p>
<form method="post"><?php admin_action('provider_inventory_period_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Prepare registration periods</button></form>
<?php else:
provider_registration_period_rows($periodContext);
$periodProfile=$record['id'];$periodRevision=(string)$record['revision'];
if(($_POST['action']??null)==='provider_inventory_period_refresh'){
    $periodProfile=is_string($_POST['period_profile']??null)?substr($_POST['period_profile'],0,32):'';
    $periodRevision=is_string($_POST['period_revision']??null)?substr($_POST['period_revision'],0,10):'';
}
if((int)$record['enabled']===1&&$record['has_credentials']): ?>
<form method="post" data-registration-period-refresh><?php admin_action('provider_inventory_period_refresh'); ?>
<input type="hidden" name="period_profile" value="<?=esc($periodProfile)?>"><input type="hidden" name="period_revision" value="<?=esc($periodRevision)?>">
<?php admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Refresh registration options</button></form>
<p class="hint">This uses the saved General API key for one read. The normal inventory cooldown and account limits apply.</p>
<?php else: ?><p>Enable the saved connection with its General API key before refreshing.</p><?php endif; ?>
<?php if(($periodContext['total']??0)>50): ?><p>Showing up to 50 extensions from this saved observation.</p><nav aria-label="Registration extension pages">
<?php if($catalogPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['period_catalog_page'=>$catalogPage-1]))?>#registration-periods">Previous extensions</a><?php endif; ?>
<?php if($periodContext['more_catalog']??false): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['period_catalog_page'=>$catalogPage+1]))?>#registration-periods">More extensions</a><?php endif; ?></nav><?php endif; ?>
<?php if($periodContext['history']): ?><h3>Registration option history</h3>
<?php foreach($periodContext['history'] as $periodHistory): ?><p><?=esc($periodHistory['at'])?> UTC · <?=esc($periodHistory['state'])?> · saved settings version <?=(int)$periodHistory['revision']?><?php if(in_array($periodHistory['state'],['complete','partial'],true)): ?> · <?=(int)$periodHistory['count']?> extensions, <?=(int)$periodHistory['skipped']?> skipped<?php endif; ?></p><?php endforeach; ?>
<nav aria-label="Registration period history pages"><?php if($periodPage>1): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['period_history_page'=>$periodPage-1]))?>#registration-periods">Newer lookups</a><?php endif; ?><?php if($periodContext['more']): ?><a class="btn outline" href="<?=esc(admin_url('provider-settings',['period_history_page'=>$periodPage+1]))?>#registration-periods">Older lookups</a><?php endif; ?></nav>
<?php endif; endif; endif; ?></section>
