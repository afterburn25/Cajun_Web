<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';
require_once CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php';
header('Cache-Control: no-store');
try {$hostingOffers=hosting_catalog_public();}
catch(Throwable $error){report_error($error);$hostingOffers=['offers'=>[],'has_more'=>false,'unavailable'=>true];}
site_head('Shared hosting','hosting','Compare shared hosting plans, published prices and support from CajunWebPro.');
?>
<section class="page-hero"><div class="container"><span class="eyebrow">SHARED HOSTING</span><h1>A home for your website.<br><span class="gold-text">A plan you can compare.</span></h1><p>See the price, included services and support side by side. Choose the plan that fits, then review your exact terms in your client portal.</p><div class="button-row"><a class="btn gold" href="#hosting-plans">Compare plans <?=icon('arrow')?></a><a class="btn outline" href="<?=esc(site_url('portal/index.php'))?>">Client Portal <?=icon('user')?></a></div></div></section>
<section class="section hosting-comparison-section" id="hosting-plans"><div class="container"><span class="eyebrow">YOUR WEBSITE, YOUR CHOICE</span><h2>Clear plans. Side by side.</h2><?php hosting_catalog_comparison($hostingOffers); ?></div></section>
<section class="section"><div class="container"><div class="three-cards">
<article class="content-card"><span class="eyebrow">01 / REQUIREMENTS</span><h2>Start with the website.</h2><p>Share your website platform, storage needs, expected traffic and any applications or mailboxes you rely on. Availability, resource limits, included work and pricing are confirmed in your written proposal.</p></article>
<article class="content-card"><span class="eyebrow">02 / MOVING A SITE</span><h2>Plan the changeover.</h2><p>Keep your current hosting, domain and email services active while we review access, backups and the move. Any migration, DNS changes and testing are agreed before work begins.</p></article>
<article class="content-card"><span class="eyebrow">03 / ONGOING CARE</span><h2>Know what is included.</h2><p>Discuss who handles updates, backups and support, along with renewal terms and response expectations. Sending an inquiry does not activate hosting, place an order or create a subscription.</p></article>
</div><article class="content-card growth-care"><span class="eyebrow">ALREADY A CLIENT?</span><h2>Your existing services stay in your workspace.</h2><p>Sign in to the Client Portal to review the services and controls made available to your account. Contact the studio if you need help with an existing hosting service.</p><a class="text-link" href="<?=esc(page_url('contact'))?>">Contact the studio <?=icon('arrow')?></a></article></div></section>
<?php site_foot();
