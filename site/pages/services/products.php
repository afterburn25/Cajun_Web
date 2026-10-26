<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
// Reuse the published service projection; private provider plans are not a public catalog.
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
$products = catalog_public();
site_head('Products','products','Explore published software, website products and services from CajunWebPro, with terms and pricing confirmed before purchase or work begins.');
?>
<section class="page-hero"><div class="container"><span class="eyebrow">PRODUCTS &amp; SERVICES</span><h1>Clear offers.<br><span class="gold-text">A considered next step.</span></h1><p>Browse the studio's published software, website, care and additional services. Each offer lists its scope and price; software license terms are confirmed before an order is created.</p></div></section>
<section class="section"><div class="container">
<?php if ($products): catalog_cards($products); else: ?>
<article class="content-card empty-card"><span class="eyebrow">LET'S TALK ABOUT YOUR PROJECT</span><h2>No products are currently published.</h2><p>You can still discuss a website, ongoing care or a specific requirement with the studio. We will confirm the scope and price in a proposal.</p><div class="button-row"><a class="btn gold" href="<?=esc(page_url('consultation'))?>">Request a consultation <?=icon('arrow')?></a><a class="btn outline" href="<?=esc(page_url('services'))?>">Explore Web Design <?=icon('monitor')?></a></div></article>
<?php endif; ?>
</div></section>
<?php site_foot();
