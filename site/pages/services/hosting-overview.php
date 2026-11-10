<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
site_head('Hosting','hosting','Compare CajunWebPro Shared Hosting and review the current VPS availability boundary.');
?>
<section class="page-hero"><div class="container"><span class="eyebrow">HOSTING</span><h1>Choose the right home<br><span class="gold-text">for what you are building.</span></h1><p>Compare published Shared Hosting plans or review the current VPS service boundary. Every purchase path keeps price, payment and setup as separately verified steps.</p><div class="button-row"><a class="btn gold" href="<?=esc(page_url('shared-hosting'))?>">Compare Shared Hosting <?=icon('arrow')?></a><a class="btn outline" href="<?=esc(page_url('vps-hosting'))?>">Review VPS Hosting</a></div></div></section>
<section class="section"><div class="container"><div class="three-cards">
<article class="content-card"><span class="eyebrow">SHARED HOSTING</span><h2>Published plans you can compare.</h2><p>Review exact monthly or annual prices, included services, limits and support before continuing to your client workspace.</p><a class="text-link" href="<?=esc(page_url('shared-hosting'))?>">View Shared Hosting <?=icon('arrow')?></a></article>
<article class="content-card"><span class="eyebrow">VPS HOSTING</span><h2>A separate service contract.</h2><p>VPS Hosting is not relabeled Shared Hosting. Online purchase remains unavailable until the retail terms and provider fulfillment contract are qualified.</p><a class="text-link" href="<?=esc(page_url('vps-hosting'))?>">Review VPS availability <?=icon('arrow')?></a></article>
<article class="content-card"><span class="eyebrow">EXISTING CLIENTS</span><h2>Follow the exact service state.</h2><p>Your portal keeps invoices, payment review and hosting setup distinct. Provider acceptance is not shown as an active service until authenticated inventory confirms it.</p><a class="text-link" href="<?=esc(site_url('portal/index.php'))?>">Open Client Portal <?=icon('user')?></a></article>
</div></div></section>
<?php site_foot();

