<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
header('Cache-Control: no-store');
site_head('VPS hosting','vps-hosting','Review CajunWebPro VPS Hosting availability and consultation options.');
?>
<section class="page-hero"><div class="container"><span class="eyebrow">VPS HOSTING</span><h1>Dedicated resources need<br><span class="gold-text">a dedicated contract.</span></h1><p>VPS Hosting online purchase is not currently available. We will not substitute a Shared Hosting package or create a paid server without qualified retail terms, provider evidence and explicit fulfillment controls.</p><div class="button-row"><a class="btn gold" href="<?=esc(page_url('consultation'))?>">Discuss VPS requirements <?=icon('arrow')?></a><a class="btn outline" href="<?=esc(page_url('shared-hosting'))?>">Compare Shared Hosting</a></div></div></section>
<section class="section"><div class="container narrow"><article class="content-card"><span class="eyebrow">CURRENT AVAILABILITY</span><h2>Consultation only — no online VPS checkout.</h2><p>We can review compute, memory, storage, operating system, network and management requirements with you. A consultation does not take payment, reserve infrastructure or authorize provider provisioning.</p><p class="notice">No VPS product cards are published because a complete VPS retail and provider fulfillment contract has not yet been accepted.</p></article></div></section>
<?php site_foot();

