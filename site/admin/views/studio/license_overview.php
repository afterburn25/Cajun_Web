<?php
/** Read-only orientation for the existing owner-controlled licensing workflow. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$catalogPrepared=catalog_ready();
?>
<section class="workspace-card" id="licensing-overview" aria-labelledby="licensing-overview-title">
<h2 id="licensing-overview-title">Software licensing workspace</h2>
<p>Create software products, issue customer licenses, manage installation domains and publish versioned downloads. Website financing is a separate workflow below.</p>
<div class="workspace-grid" aria-label="Licensing setup status">
<article><h3>1. License storage</h3><p data-license-readiness="base"><?=$ready?'Prepared':'Setup required'?></p><p>Existing website licenses and domain history.</p></article>
<article><h3>2. Software catalog</h3><p data-license-readiness="catalog"><?=$catalogPrepared?'Prepared':'Setup required'?></p><p>Product SKUs, prices and publication.</p></article>
<article><h3>3. Software entitlements</h3><p data-license-readiness="software"><?=$softwareReady?'Prepared':'Setup required'?></p><p>Standard, trial, temporary and internal master licenses.</p></article>
</div>
<?php if(!$ready): ?><p class="notice info"><strong>Next step:</strong> prepare licensing storage below. Existing customer and license records are retained by the guarded setup process.</p>
<?php elseif(!$catalogPrepared): ?><p class="notice info"><strong>Next step:</strong> prepare the service catalog, then return here to prepare software commerce.</p>
<?php elseif(!$softwareReady): ?><p class="notice info"><strong>Next step:</strong> choose Prepare software commerce below. This enables software records; it does not activate card payments.</p>
<?php else: ?><p class="notice info"><strong>Licensing storage is prepared.</strong> Select a registered software product and customer in Create a license. Publish the product and its download separately when they are ready for customers.</p><?php endif; ?>
<nav class="button-row" aria-label="Licensing workspace sections">
<a class="btn outline" href="<?=esc(admin_url('catalog',['product_family'=>'software']))?>">Software products &amp; prices</a>
<?php if($ready): ?><a class="btn gold" href="#license-create">Create a license</a><a class="btn outline" href="#license-register">License register</a><a class="btn outline" href="#licensing-financing">Website financing</a><?php endif; ?>
<?php if($ready&&$softwareReady): ?><a class="btn outline" href="#software-policies">Software policies</a><a class="btn outline" href="#software-downloads">Downloads &amp; releases</a><a class="btn outline" href="#software-payments">Payment settings</a><?php endif; ?>
</nav>
<p class="hint">For a new product, choose the Software product family in the catalog. Standard licenses do not expire; trial and temporary licenses need an expiration. Internal master keys remain owner-only and must not be delivered to customers.</p>
<p class="hint">Customer purchases, licenses and authorized downloads appear in My Software in the client portal. Administrator client-review access does not become a customer purchase session. Live card charging remains separately gated; a prepared license database does not mean checkout is live.</p>
</section>
