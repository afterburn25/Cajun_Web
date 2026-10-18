<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';
require_once CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php';
header('Cache-Control: no-store');
try {$hostingOffers=hosting_catalog_public();}
catch(Throwable $error){report_error($error);$hostingOffers=['offers'=>[],'has_more'=>false,'unavailable'=>true];}
site_head('Hosting','hosting','Discuss hosting, moving an existing website, and ongoing care with CajunWebPro.');
?>
<section class="page-hero"><div class="container"><span class="eyebrow">A HOME FOR YOUR WEBSITE</span><h1>Hosting shaped around<br><span class="gold-text">what you need.</span></h1><p>Your website, domain and email each need a clear plan. Tell us what you run today and what needs to change so we can review the right next step.</p><div class="button-row"><a class="btn gold" href="<?=esc(page_url('consultation'))?>">Discuss hosting <?=icon('arrow')?></a><a class="btn outline" href="<?=esc(site_url('portal/index.php'))?>">Client Portal <?=icon('user')?></a></div></div></section>
<section class="section"><div class="container"><span class="eyebrow">HOSTING PLANS</span><h2>Choose the right home for your website.</h2><?php hosting_catalog_public_cards($hostingOffers); ?></div></section>
<section class="section"><div class="container"><div class="three-cards">
<article class="content-card"><span class="eyebrow">01 / REQUIREMENTS</span><h2>Start with the website.</h2><p>Share your website platform, storage needs, expected traffic and any applications or mailboxes you rely on. Availability, resource limits, included work and pricing are confirmed in your written proposal.</p></article>
<article class="content-card"><span class="eyebrow">02 / MOVING A SITE</span><h2>Plan the changeover.</h2><p>Keep your current hosting, domain and email services active while we review access, backups and the move. Any migration, DNS changes and testing are agreed before work begins.</p></article>
<article class="content-card"><span class="eyebrow">03 / ONGOING CARE</span><h2>Know what is included.</h2><p>Discuss who handles updates, backups and support, along with renewal terms and response expectations. Sending an inquiry does not activate hosting, place an order or create a subscription.</p></article>
</div><article class="content-card growth-care"><span class="eyebrow">ALREADY A CLIENT?</span><h2>Your existing services stay in your workspace.</h2><p>Sign in to the Client Portal to review the services and controls made available to your account. Contact the studio if you need help with an existing hosting service.</p><a class="text-link" href="<?=esc(page_url('contact'))?>">Contact the studio <?=icon('arrow')?></a></article></div></section>
<?php site_foot();
