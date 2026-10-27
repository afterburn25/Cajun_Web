<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
require_once CWP_CODE_ROOT.'/app/licenses/financing.php';
require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
require_once CWP_CODE_ROOT.'/database/migrations/license_finance_gateway_migrate.php';
$softwareReady=software_commerce_ready();
$financingReady=license_financing_feature_state()===true;
$financeOffersReady=license_finance_offers_ready();
$financeGatewayReady=license_finance_gateway_ready();
$ready = license_ready() && license_invoice_cutover_ready();
?>
<?php if(!$ready): ?>
<section class="workspace-card"><h2>Prepare website licensing</h2><p>Prepare or upgrade licensing storage and preserve the boundary for older invoice reviews. Existing licenses, domains and bundles are retained. No external service is contacted.</p>
<form method="post"><?php admin_action('studio_license_setup'); ?><?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Prepare licensing storage</button></form></section>
<?php return; endif; ?>
<?php
if(!license_storage_ready()){
    echo '<div class="notice warning">Website license storage is unavailable. Preserve the original database and complete its reviewed upgrade before managing licenses.</div>';
    return;
}
$softwareEntitlementTable=(bool)one(
    'SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
    [config()['db']['prefix'].'software_entitlements']
);

$licenses = $ready ? all('SELECT l.*, c.name as customer_name FROM '.table('website_licenses').' l LEFT JOIN '.table('studio_clients').' c ON l.customer_id=c.id ORDER BY l.created_at DESC LIMIT 100') : [];
$customers = $ready ? array_column(all('SELECT id,name FROM '.table('studio_clients').' ORDER BY name'),'name','id') : [];
// First-class software-family products may use any valid SKU; retain legacy prefixed products.
$products = [];
$legacyProducts=['restaurant'=>'Restaurant Website','salon'=>'Salon Website','generic'=>'Generic Business'];
try {
    foreach(all('SELECT sku,title,family FROM '.table('catalog_products')." WHERE family='software' OR sku LIKE 'WEBSITE-%' OR sku LIKE 'SOFTWARE-%' OR sku LIKE 'APP-%' ORDER BY title") as $p){
        $products[$p['sku']]=$p['title'].' ('.$p['sku'].')';
        if(($p['family']??'')!=='software'&&preg_match('/^WEBSITE-[A-Z0-9_-]+$/D',(string)$p['sku'])===1){
            $legacyProducts[$p['sku']]=$p['title'].' ('.$p['sku'].')';
        }
    }
} catch (Throwable) {}
if(!$softwareReady)$products = $legacyProducts;
$softwarePolicyProducts=[];
if($softwareReady){
    require_once CWP_CODE_ROOT.'/app/licenses/software_products.php';
    try{$softwarePolicyProducts=all('SELECT id,sku,title,family FROM '.table('catalog_products')." WHERE family='software' OR sku LIKE 'WEBSITE-%' OR sku LIKE 'SOFTWARE-%' OR sku LIKE 'APP-%' ORDER BY title LIMIT 100");}
    catch(Throwable){$softwarePolicyProducts=[];}
}
$financeCandidates=$financeOffersReady?license_finance_offer_candidates():[];
$financeOffers=$financeOffersReady?license_finance_offer_list_admin():[];
$financeChargeReviews=$financeGatewayReady?all('SELECT ch.invoice_id,ch.status,ch.transaction_id,o.title,c.name FROM '.table('license_finance_charges').' ch JOIN '.table('license_finance_offers').' o ON o.id=ch.offer_id JOIN '.table('studio_clients').' c ON c.id=o.client_id WHERE ch.status IN (?,?,?) ORDER BY ch.id DESC LIMIT 30',['processing','uncertain','review']):[];
$financeSubscriptionReviews=$financeGatewayReady?all('SELECT s.offer_id,s.gateway_subscription_id,s.status,o.title,c.name FROM '.table('license_finance_subscriptions').' s JOIN '.table('license_finance_offers').' o ON o.id=s.offer_id JOIN '.table('studio_clients').' c ON c.id=o.client_id WHERE s.status IN (?,?,?,?,?,?,?) ORDER BY s.id DESC LIMIT 30',['creating','replacing','review','payment_failed','billing_suspended','cancelled','reversal_recovery']):[];
$financeMailAccounts=[];
try{
    if(mail_accounts_ready()&&mail_drafts_ready())foreach(array_slice(mail_accounts_assigned(1),0,20) as $a)if($a['can_send'])$financeMailAccounts[]=$mail_drafts_account($a['id']);
}catch(Throwable){}

if(isset($_SESSION['license_key_created'])){echo '<div class="notice info">License key created (displayed once): <code>'.esc($_SESSION['license_key_created']).'</code></div>';unset($_SESSION['license_key_created']);}
?>

<?php if(!$softwareReady): ?>
<section class="workspace-card"><h2>Enable advanced software licensing</h2><p>Add trial, temporary, master and paid software tracking, customer purchases, payment checks and private downloads. Existing licenses and invoices are retained.</p><form method="post"><?php admin_action('software_commerce_setup');admin_input('current_password','Current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Prepare software commerce</button></form></section>
<?php endif; ?>
<?php if(!$financingReady): ?>
<section class="workspace-card"><h2>Enable financed-site licensing</h2><p>Prepare website-license payment enforcement. Only issued unpaid installments beyond the agreed grace period may suspend a site; full payoff permanently removes the payment lock.</p>
<?php if(recurring_billing_ready()): ?><form method="post"><?php admin_action('studio_license_financing_setup');admin_input('current_password','Owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Prepare financed-site licensing</button></form><?php else: ?><p class="notice info">Prepare recurring billing and the invoice ledger first.</p><?php endif; ?></section>
<?php elseif(!$financeOffersReady): ?>
<section class="workspace-card"><h2>Enable client finance agreements</h2><p>Add tailored finance terms, authenticated electronic signatures, portal links, and payment handoff.</p>
<?php if(recurring_billing_ready()&&payments_ready()&&ledger_ready()): ?><form method="post"><?php admin_action('studio_license_finance_offers_setup');admin_input('current_password','Owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Prepare finance agreements</button></form><?php else: ?><p class="notice info">Prepare recurring billing, payment links, and invoice payments first.</p><?php endif; ?></section>
<?php endif; ?>

<?php if($financeOffersReady): ?>
<section class="workspace-card"><h2>Create customer financing agreement</h2>
<p>The selected recurring schedule supplies the installment amount, cadence, start date, and due interval. Published finance terms are immutable; withdraw and reissue to change them.</p>
<?php if(!$financeCandidates): ?><p class="notice info">No offered installment schedules are available.</p><?php else: ?>
<form method="post"><?php admin_action('studio_license_finance_offer_create'); ?>
<label>Website license<select name="license_id" required><option value="">Choose license…</option><?php foreach($licenses as $x): if((int)($x['is_master']??0)===1)continue; ?><option value="<?=(int)$x['id']?>"><?=esc(($x['customer_name']??'Unknown').' · '.$x['product_id'].' · #'.$x['id'])?></option><?php endforeach; ?></select></label>
<label>Installment schedule<select name="schedule_id" required><option value="">Choose schedule…</option><?php foreach($financeCandidates as $x): ?><option value="<?=esc($x['id'])?>"><?=esc($x['client_name'].' · '.$x['project_title'].' · $'.number_format($x['amount_cents']/100,2).' '.$x['cadence'].' · '.$x['anchor'])?></option><?php endforeach; ?></select></label>
<?php admin_input('finance_title','Agreement title','Website financing agreement','text','required minlength="3" maxlength="190"'); ?>
<?php admin_input('cash_price','Cash price','','text','required inputmode="decimal" maxlength="20" placeholder="6500.00"'); ?>
<?php admin_input('initial_payment','Initial / down payment','','text','required inputmode="decimal" maxlength="20" placeholder="650.00"'); ?>
<?php admin_input('amount_financed','Amount financed','','text','required inputmode="decimal" maxlength="20" placeholder="5850.00"'); ?>
<?php admin_input('finance_charge','Finance charge','','text','required inputmode="decimal" maxlength="20"'); ?>
<?php admin_input('apr_percent','Stated APR (%)','','text','required inputmode="decimal" maxlength="8" placeholder="12.00"'); ?>
<?php admin_input('installments','Installments','24','number','required min="1" max="360"'); ?>
<?php admin_input('grace_days','Grace days','10','number','required min="0" max="90"'); ?>
<?php admin_input('offer_days','Signature window (days)','14','number','required min="1" max="30"'); ?>
<?php admin_input('payment_link_days','Payment-link validity (days)','14','number','required min="1" max="30"'); ?>
<label>Custom agreement terms<textarea name="agreement_text" rows="11" minlength="20" maxlength="12000" required>This agreement finances the identified website project under the displayed payment schedule. The website license remains bound to the customer account and approved domain while financing is outstanding. An actually issued installment that remains unpaid beyond the agreed grace period may temporarily suspend the licensed website until the overdue obligation is paid. Once every required financing installment is fully paid, the financing payment lock is permanently removed. Paying off financing does not grant resale, redistribution, sublicensing, white-label distribution, or source-code distribution unless expressly granted below. Domain changes in the authenticated client portal do not transfer the license to another customer.</textarea></label>
<label class="check"><input type="checkbox" name="allow_resale" value="1">Grant resale rights</label>
<label class="check"><input type="checkbox" name="allow_distribution" value="1">Grant distribution rights</label>
<label class="check"><input type="checkbox" name="exclusive_resale" value="1">Exclusive resale</label>
<label class="check"><input type="checkbox" name="exclusive_distribution" value="1">Exclusive distribution</label>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?><button class="btn gold">Create finance agreement</button></form>
<p class="hint">Cash price − initial payment must equal amount financed. Installment amount × count must equal amount financed + finance charge. The stated APR and agreement language require final legal/compliance review before production use.</p>
<?php endif; ?></section>

<section class="workspace-card"><h2>Finance agreement history</h2>
<?php if(!$financeOffers): ?><p class="muted">No finance agreements yet.</p><?php endif; ?>
<?php foreach($financeOffers as $o): $ot=license_finance_offer_terms($o);$os=license_finance_offer_status($o); ?>
<article class="workspace-version"><h3><?=esc($o['title'].' · '.$o['client_name'])?></h3><p><?=esc($o['project_title'])?> · <strong><?=esc($os)?></strong> · <?=esc(license_finance_apr_label((int)$o['apr_basis_points']))?> APR · <?=(int)$o['installments']?> × $<?=esc(number_format((int)$ot['installment_amount_cents']/100,2))?></p>
<p>Portal: <code><?=esc(license_finance_offer_portal_url($o['id']))?></code></p><p>Terms: <code><?=esc($o['terms_sha256'])?></code></p>
<?php if($o['signed_at']!==null): ?><p>Signed by <?=esc((string)$o['signed_name'])?> · <?=esc((string)$o['signed_email'])?> · <?=esc((string)$o['signed_at'])?> UTC</p><?php endif; ?>
<?php if($os==='offered'): ?><form method="post"><?php admin_action('studio_license_finance_offer_withdraw'); ?><input type="hidden" name="finance_id" value="<?=esc($o['id'])?>"><?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Withdraw unsigned offer</button></form><?php endif; ?>
<?php if(in_array($os,['offered','signed'],true)&&$financeMailAccounts): ?><form method="post"><?php admin_action('studio_license_finance_offer_email'); ?><input type="hidden" name="finance_id" value="<?=esc($o['id'])?>"><label>Send from<select name="sender" required><?php foreach($financeMailAccounts as $m): ?><option value="<?=esc($m['id'].':'.$m['revision'])?>"><?=esc($m['label'].' · '.$m['address'])?></option><?php endforeach; ?></select></label><button class="btn outline">Prepare agreement email</button></form><?php endif; ?>
</article><?php endforeach; ?></section>
<?php endif; ?>

<?php if($financeOffersReady&&!$financeGatewayReady): ?>
<section class="workspace-card"><h2>Prepare financing card processing</h2><p>Create durable one-time charge and finite-subscription fencing. This setup does not enable production charging.</p><form method="post"><?php admin_action('studio_license_finance_gateway_setup');admin_input('current_password','Owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Prepare finance gateway state</button></form></section>
<?php elseif($financeGatewayReady): ?>
<section class="workspace-card"><h2>Financing payment status</h2><p>Unified Authorize.Net mode: <strong><?=esc((string)setting('software_gateway_mode','disabled'))?></strong>.</p>
<?php $fCode=defined('CWP_FINANCE_PRODUCTION_PAYMENTS')&&CWP_FINANCE_PRODUCTION_PAYMENTS===true;$fReview=setting('finance_gateway_production_reviewed')==='1'; ?>
<p><strong><?=$fCode?'Finance code gate enabled':'Finance code gate locked'?></strong> · <strong><?=$fReview?'Owner review recorded':'Owner review not recorded'?></strong>.</p>
<p>Shared-host scheduler: <code><?=esc(CWP_CODE_ROOT.'/app/jobs/finance.php')?></code>. Run it regularly so installments, grace, suspension, and payoff continue without an interactive session.</p>
<form method="post"><?php admin_action('studio_license_finance_gateway_review'); ?><label class="check"><input type="checkbox" name="confirm" value="1" <?=$fReview?'checked':''?>>Financing-production review completed; I understand this alone does not enable live charging.</label><?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Save financing production review</button></form></section>
<?php endif; ?>
<?php if($financeGatewayReady): ?>
<section class="workspace-card"><h2>Financing payment reconciliation</h2>
<p>Use these only after independently locating the exact transaction/subscription in Authorize.Net. CajunWebPro re-fetches gateway facts before changing local state.</p>
<h3>One-time finance charges</h3>
<?php if(!$financeChargeReviews): ?><p class="muted">No financing one-time charges require review.</p><?php endif; ?>
<?php foreach($financeChargeReviews as $r): ?><div class="workspace-version"><p><?=esc($r['name'].' · '.$r['title'])?> · invoice <?=(int)$r['invoice_id']?> · <?=esc($r['status'])?></p>
<form method="post"><?php admin_action('studio_license_finance_charge_reconcile'); ?><input type="hidden" name="invoice_id" value="<?=(int)$r['invoice_id']?>"><?php admin_input('transaction_id','Verified transaction ID',(string)($r['transaction_id']??''),'text','required pattern="[0-9]{5,30}" maxlength="30"'); ?><?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Reconcile finance charge</button></form></div><?php endforeach; ?>
<h3>Financing subscriptions</h3>
<?php if(!$financeSubscriptionReviews): ?><p class="muted">No financing subscriptions require review.</p><?php endif; ?>
<?php foreach($financeSubscriptionReviews as $r): ?><div class="workspace-version"><p><?=esc($r['name'].' · '.$r['title'])?> · <?=esc($r['status'])?></p>
<form method="post"><?php admin_action('studio_license_finance_subscription_reconcile'); ?><input type="hidden" name="finance_id" value="<?=esc($r['offer_id'])?>"><?php admin_input('gateway_subscription_id','Verified subscription ID',(string)($r['gateway_subscription_id']??''),'text','required pattern="[0-9]{1,25}" maxlength="25"'); ?><?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Reconcile subscription</button></form></div><?php endforeach; ?>
<h3>Missed recurring transaction</h3><form method="post"><?php admin_action('studio_license_finance_subscription_charge_reconcile'); ?><?php admin_input('transaction_id','Verified recurring transaction ID','','text','required pattern="[0-9]{5,30}" maxlength="30"'); ?><?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?><button class="btn outline">Reconcile recurring payment</button></form>
</section>
<?php endif; ?>
<?php if($softwareReady): ?>
<section class="workspace-card">
<h2>Authorize.net payment configuration</h2>
<p>Checkout uses the existing encrypted payment credentials. Sandbox transactions can be tested but will not unlock real licenses. Production charging remains disabled until reviewed and approved.</p>
<p>Current checkout mode: <strong><?=esc((string)setting('software_gateway_mode','disabled'))?></strong></p>
<p>Authorize.net webhook endpoint: <code><?=esc(site_url('api/software-webhook.php'))?></code></p>
<p class="hint">Handled by this build: verified capture transactions, refund/void reversals, and subscription lifecycle notifications. Configure the merchant account to send only to the HTTPS endpoint shown above.</p>
<?php
$productionCodeGate=defined('CWP_SOFTWARE_PRODUCTION_PAYMENTS')&&CWP_SOFTWARE_PRODUCTION_PAYMENTS===true;
$productionOwnerReview=setting('software_gateway_production_reviewed')==='1';
?>
<p>Production activation prerequisites:
<strong><?=($productionCodeGate?'Code gate enabled':'Code gate locked')?></strong> ·
<strong><?=($productionOwnerReview?'Owner review recorded':'Owner review not recorded')?></strong>.
Both must be present before production settings can be read; this page still offers only disabled/sandbox modes.</p>
<form method="post"><?php admin_action('software_gateway_select'); ?>
<label>Payment mode<select name="gateway_mode">
<option value="disabled">Disabled — do not take card payments</option>
<option value="sandbox">Sandbox — test without fulfilling paid orders</option>
</select></label>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Save software payment mode</button></form>
</section>
<section class="workspace-card">
<h2>Customer domain replacement policy</h2>
<p>Set how long a customer must wait before moving the same software license again. Use 0 only if you intentionally want no cooldown.</p>
<form method="post"><?php admin_action('software_domain_policy'); ?>
<?php admin_input('domain_cooldown_hours','Cooldown hours',(string)setting('software_domain_change_cooldown_hours','24'),'number','required min="0" max="720"'); ?>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Save domain policy</button></form>
</section>
<?php if(staff_can('staff.manage')): ?>
<section class="workspace-card">
<h2>Software entitlement policies</h2>
<p>Configure rights that are snapshotted when a new software license is issued. Changing a policy does not rewrite existing customer entitlements. Update months 0 means lifetime updates while the license itself remains valid.</p>
<?php if(!$softwarePolicyProducts): ?><p class="muted">Create a software catalog product before defining entitlement policy.</p><?php endif; ?>
<?php foreach($softwarePolicyProducts as $policyProduct): $policy=software_product_policy((int)$policyProduct['id']); ?>
<form method="post" style="margin-bottom:1.5rem">
<?php admin_action('software_product_policy'); ?>
<input type="hidden" name="product_id" value="<?=(int)$policyProduct['id']?>">
<input type="hidden" name="revision" value="<?=(int)$policy['revision']?>">
<h3><?=esc($policyProduct['title'].' · '.$policyProduct['sku'])?></h3>
<?php admin_input('module_key','Module key',(string)$policy['module_key'],'text','maxlength="80" placeholder="optional-module-key"'); ?>
<?php admin_input('dependencies','Dependency SKUs',implode(', ',$policy['dependency_skus']),'text','maxlength="1200" placeholder="SOFTWARE-BASE, SOFTWARE-ADDON"'); ?>
<label>Update entitlement months
<input type="number" name="update_months" min="0" max="120" required value="<?=(int)$policy['update_months']?>">
</label>
<label>Maximum concurrent installations
<input type="number" name="max_installations" min="1" max="25" required value="<?=(int)$policy['max_installations']?>">
</label>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<p class="hint">Current policy revision <?=(int)$policy['revision']?>. Existing issued licenses retain their prior revision, update cutoff, and installation limit.</p>
<button class="btn gold">Save software policy</button>
</form>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php endif; ?>
<div class="workspace-grid">
<section class="workspace-card">
<h2>Create a license</h2>
<form method="post">
<?php admin_action('studio_license_create'); ?>
<label for="customer_id">Customer</label>
<select name="customer_id" id="customer_id" required>
<option value="">Choose a customer…</option>
<?php foreach($customers as $id=>$name): ?>
<option value="<?= esc($id) ?>"><?= esc($name) ?></option>
<?php endforeach; ?>
</select>
<label for="product_id">Product</label>
<select name="product_id" id="product_id" required>
<option value="">Choose a product…</option>
<?php foreach($products as $key=>$name): ?>
<option value="<?= esc($key) ?>"><?= esc($name) ?></option>
<?php endforeach; ?>
</select>
<?php if($softwareReady): ?>
<label>Licensee name<input type="text" name="licensee_name" maxlength="190" required placeholder="Customer or organization"></label>
<label>License type<select name="license_type"><option value="standard">Standard — perpetual</option><option value="trial">Trial — expires</option><option value="temporary">Temporary — expires</option><?php if(staff_can('staff.manage')): ?><option value="master">Master — internal staff only</option><?php endif; ?></select></label>
<label>Installation domain (required except internal master)<input type="text" name="domain" maxlength="253" placeholder="example.com"></label>
<label>Expiration in UTC (required for trial and temporary)<input type="datetime-local" name="expires_at"></label>
<?php if(staff_can('staff.manage')): ?>
<?php admin_input('master_purpose','Master-license purpose','','text','minlength="10" maxlength="200" placeholder="Internal QA, release validation, or other owner-approved purpose"'); ?>
<?php admin_input('current_password','Owner password for master licenses','','password','autocomplete="current-password"'); ?>
<?php endif; ?>
<p class="hint">Master keys require an owner-reviewed purpose, can authorize any domain, and must never be bundled or sent to customers. Normal licenses are bound to an installation domain.</p>
<?php else: ?>
<?php if(staff_can('staff.manage')): ?><label><input type="checkbox" name="master" value="1"> Master key — internal testing only</label><?php endif; ?>
<?php endif; ?>
<button class="btn gold">Generate license key</button>
</form>
</section>
<?php if($softwareReady): ?>
<section class="workspace-card">
<h2>Create a legacy website license</h2>
<p>Use this only for the original Website Core license types. Software-family products use the advanced software form above so update, installation, dependency, and version entitlements are recorded.</p>
<form method="post">
<?php admin_action('studio_license_create'); ?>
<label for="legacy_customer_id">Customer</label>
<select name="customer_id" id="legacy_customer_id" required>
<option value="">Choose a customer…</option>
<?php foreach($customers as $id=>$name): ?>
<option value="<?= esc($id) ?>"><?= esc($name) ?></option>
<?php endforeach; ?>
</select>
<label for="legacy_product_id">Legacy website product</label>
<select name="product_id" id="legacy_product_id" required>
<option value="">Choose a legacy website product…</option>
<?php foreach($legacyProducts as $key=>$name): ?>
<option value="<?= esc($key) ?>"><?= esc($name) ?></option>
<?php endforeach; ?>
</select>
<?php if(staff_can('staff.manage')): ?>
<label><input type="checkbox" name="master" value="1"> Master key — internal testing only</label>
<?php endif; ?>
<button class="btn outline">Generate legacy website key</button>
</form>
</section>
<?php endif; ?>
</div>

<?php if($softwareReady && staff_can('staff.manage')): ?>
<section class="workspace-card"><h2>Publish a software download</h2>
<p>Place a versioned release ZIP in the server's private storage first; never expose a direct public file path or ZIP-inside-ZIP. This form validates the archive and records its SHA-256.</p>
<form method="post"><?php admin_action('software_asset_publish'); ?>
<?php admin_input('sku','Registered software product SKU','','text','required maxlength="60"'); ?>
<?php admin_input('version','Release version','','text','required maxlength="40"'); ?>
<?php admin_input('title','Download title','','text','required maxlength="120"'); ?>
<?php admin_input('private_path','Absolute path under private storage','','text','required maxlength="500"'); ?>
<label><input type="checkbox" name="customer_visible" value="1" checked> Make this version available to entitled customers immediately</label>
<p class="hint">Uncheck to register and verify the immutable ZIP as owner-private. Visibility can be changed later; the recorded file, checksum, size, SKU and version remain immutable.</p>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Record verified ZIP</button></form>
<?php $softwareAssets=all('SELECT id,sku,version,title,sha256,byte_size,is_published,released_at,created_at FROM '.table('software_assets').' ORDER BY id DESC LIMIT 50'); ?>
<?php if($softwareAssets): ?><h3>Versioned software releases</h3><?php endif; ?>
<?php foreach($softwareAssets as $asset): ?>
<div class="workspace-card">
<p><strong><?=esc($asset['title'])?></strong> · <?=esc($asset['sku'])?> v<?=esc($asset['version'])?> · <?=number_format((int)$asset['byte_size'])?> bytes</p>
<p><?=((int)$asset['is_published']===1?'Customer-visible':'Owner-private')?> · SHA-256 <code><?=esc($asset['sha256'])?></code> · recorded <?=esc($asset['created_at'])?> UTC<?php if($asset['released_at']!==null): ?> · first customer release <?=esc($asset['released_at'])?> UTC<?php else: ?> · never customer-released<?php endif; ?></p>
<form method="post"><?php admin_action('software_asset_visibility'); ?>
<input type="hidden" name="asset_id" value="<?=(int)$asset['id']?>">
<input type="hidden" name="customer_visible" value="<?=((int)$asset['is_published']===1?'0':'1')?>">
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn outline"><?=((int)$asset['is_published']===1?'Make owner-private':'Make customer-visible')?></button>
</form>
</div>
<?php endforeach; ?>
</section>
<?php
$notices=all('SELECT n.*,COALESCE(o.sku,origin.sku) AS sku,c.name FROM '.table('software_notices').' n LEFT JOIN '.table('software_orders').' o ON o.invoice_id=n.invoice_id LEFT JOIN '.table('software_subscription_receipts').' r ON r.invoice_id=n.invoice_id LEFT JOIN '.table('software_orders').' origin ON origin.invoice_id=r.subscription_invoice_id JOIN '.table('studio_clients').' c ON c.id=n.client_id WHERE COALESCE(o.id,origin.id) IS NOT NULL ORDER BY n.id DESC LIMIT 30');
?>
<section class="workspace-card"><h2>Reconcile a paid software order</h2>
<p>Retry only an independently verified fully paid invoice. Duplicate licenses and duplicate email notices are refused or reused by the fulfillment service.</p>
<form method="post"><?php admin_action('software_order_fulfill_retry'); ?>
<?php admin_input('invoice_id','Paid software invoice ID','','number','required min="1"'); ?>
<?php admin_input('current_password','Owner password','','password','required'); ?>
<button class="btn outline">Recheck paid order fulfillment</button></form></section>
<section class="workspace-card"><h2>Customer software delivery notices</h2>
<?php foreach($notices as $n): ?>
<div class="workspace-card">
<p><?=esc($n['name'].' · '.$n['sku'])?> · Invoice <?=(int)$n['invoice_id']?> · <?=esc($n['status'])?> · attempts <?=(int)$n['attempts']?> / 10</p>
<?php if($n['last_attempt']!==null): ?><p class="hint">Last attempt <?=esc($n['last_attempt'])?> UTC<?php if($n['last_error']!==''): ?> · <?=esc($n['last_error'])?><?php endif; ?></p><?php endif; ?>
<?php if($n['status']!=='sent'&&(int)$n['attempts']<10): ?>
<form method="post"><?php admin_action('software_notice_retry'); ?>
<input type="hidden" name="invoice_id" value="<?=(int)$n['invoice_id']?>">
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn outline">Retry delivery email</button></form>
<?php elseif($n['status']!=='sent'): ?>
<p class="notice info">Retry budget exhausted. Review mail configuration before resetting the budget.</p>
<form method="post"><?php admin_action('software_notice_reset'); ?>
<input type="hidden" name="invoice_id" value="<?=(int)$n['invoice_id']?>">
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn outline">Reset retry budget</button></form>
<?php endif; ?>
</div>
<?php endforeach; ?>
</section>
<?php
$chargeReviews=all('SELECT ch.invoice_id,ch.status,ch.transaction_id,o.sku,c.name FROM '.table('software_charges').' ch JOIN '.table('software_orders').' o ON o.invoice_id=ch.invoice_id JOIN '.table('studio_clients').' c ON c.id=ch.client_id WHERE ch.status IN (?,?,?) ORDER BY ch.id DESC LIMIT 30',
    ['processing','uncertain','review']);
$reversals=all('SELECT r.*,COALESCE(direct_order.sku,subscription_order.sku) AS sku,c.name FROM '.table('software_payment_reversals').' r LEFT JOIN '.table('software_orders').' direct_order ON direct_order.invoice_id=r.invoice_id LEFT JOIN '.table('software_orders').' subscription_order ON subscription_order.invoice_id=r.subscription_invoice_id LEFT JOIN '.table('studio_clients').' c ON c.id=COALESCE(direct_order.client_id,subscription_order.client_id) ORDER BY r.id DESC LIMIT 30');
$disputes=all('SELECT d.*,COALESCE(direct_order.sku,subscription_order.sku) AS sku,c.name FROM '.table('software_payment_disputes').' d LEFT JOIN '.table('software_orders').' direct_order ON direct_order.invoice_id=d.invoice_id LEFT JOIN '.table('software_orders').' subscription_order ON subscription_order.invoice_id=d.subscription_invoice_id LEFT JOIN '.table('studio_clients').' c ON c.id=COALESCE(direct_order.client_id,subscription_order.client_id) ORDER BY d.id DESC LIMIT 30');
$subscriptionReviews=all('SELECT s.invoice_id,s.gateway_subscription_id,s.status,o.sku,c.name FROM '.table('software_subscriptions').' s JOIN '.table('software_orders').' o ON o.invoice_id=s.invoice_id JOIN '.table('studio_clients').' c ON c.id=s.client_id WHERE s.status IN (?,?,?,?) ORDER BY s.id DESC LIMIT 30',['creating','payment_failed','billing_suspended','review']);
$subscriptionChargeCandidates=all('SELECT s.invoice_id,s.gateway_subscription_id,s.status,o.sku,c.name FROM '.table('software_subscriptions').' s JOIN '.table('software_orders').' o ON o.invoice_id=s.invoice_id JOIN '.table('studio_clients').' c ON c.id=s.client_id WHERE s.gateway_subscription_id IS NOT NULL AND s.status IN (?,?,?,?,?,?,?) ORDER BY s.id DESC LIMIT 30',['pending_first_payment','active','payment_failed','billing_suspended','cancelling','cancelled','review']);
?>
<section class="workspace-card"><h2>Uncertain one-time charge reconciliation</h2>
<p>Use this only after confirming the transaction ID in Authorize.net. CajunWebPro re-fetches the gateway transaction and requires the exact invoice number and amount before any paid transition. Sandbox verification never fulfills software.</p>
<?php if(!$chargeReviews): ?><p class="muted">No one-time software charges currently require reconciliation.</p><?php endif; ?>
<?php foreach($chargeReviews as $r): ?>
<div class="workspace-card">
<p><?=esc($r['name'].' · '.$r['sku'])?> · Invoice <?=(int)$r['invoice_id']?> · local state <?=esc($r['status'])?><?php if($r['transaction_id']!==null): ?> · recorded transaction <?=esc($r['transaction_id'])?><?php endif; ?></p>
<form method="post"><?php admin_action('software_charge_reconcile'); ?>
<input type="hidden" name="invoice_id" value="<?=(int)$r['invoice_id']?>">
<?php admin_input('transaction_id','Verified Authorize.net transaction ID',(string)($r['transaction_id']??''),'text','required pattern="[0-9]{5,30}" maxlength="30"'); ?>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Reconcile verified charge</button>
</form>
</div>
<?php endforeach; ?>
</section>
<section class="workspace-card"><h2>Merchant dispute / chargeback review</h2>
<p>Credit-card disputes are recorded from independently verified merchant-service-provider evidence. Recording a case places the linked payment or subscription into review; it does not automatically revoke paid-through access.</p>
<form method="post"><?php admin_action('software_dispute_record'); ?>
<?php admin_input('original_transaction_id','Original gateway transaction ID','','text','required pattern="[0-9]{5,30}" maxlength="30"'); ?>
<?php admin_input('case_reference','Merchant dispute case reference','','text','required minlength="5" maxlength="100"'); ?>
<?php admin_input('evidence','Verified MSP evidence / disposition','','text','required minlength="10" maxlength="500"'); ?>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Record dispute for review</button></form>
<?php if(!$disputes): ?><p class="muted">No merchant disputes recorded.</p><?php endif; ?>
<?php foreach($disputes as $d): ?>
<p><?=esc(($d['name']??'Unknown customer').' · '.($d['sku']??'Unknown software'))?> · case <?=esc($d['case_reference'])?> · original <?=esc($d['original_transaction_id'])?> · <?=esc($d['evidence'])?></p>
<?php endforeach; ?>
</section>
<section class="workspace-card"><h2>Payment reversal review</h2>
<p>Verified refunds and voids are recorded here for owner review. CajunWebPro does not automatically revoke already-paid access from a reversal notification.</p>
<?php if(!$reversals): ?><p class="muted">No verified software payment reversals recorded.</p><?php endif; ?>
<?php foreach($reversals as $r): ?>
<p><?=esc(($r['name']??'Unknown customer').' · '.($r['sku']??'Unknown software'))?> · <?=esc($r['kind'])?> · event <?=esc($r['gateway_transaction_id'])?> · original <?=esc($r['original_transaction_id'])?><?php if($r['amount_cents']!==null): ?> · $<?=esc(number_format((int)$r['amount_cents']/100,2))?><?php endif; ?></p>
<?php endforeach; ?>
</section>
<section class="workspace-card"><h2>Recurring transaction reconciliation</h2>
<p>If a signed recurring-payment webhook was missed or arrived before the local ARB identity was linked, enter the verified Authorize.net transaction ID here. CajunWebPro re-fetches transaction details and requires the exact subscription ID, original invoice reference, and amount. Sandbox readback never grants paid access.</p>
<?php if(!$subscriptionChargeCandidates): ?><p class="muted">No linked software subscriptions are available for recurring transaction reconciliation.</p><?php endif; ?>
<?php foreach($subscriptionChargeCandidates as $r): ?>
<div class="workspace-card">
<p><?=esc($r['name'].' · '.$r['sku'])?> · Original invoice <?=(int)$r['invoice_id']?> · local state <?=esc($r['status'])?> · Gateway subscription <?=esc((string)$r['gateway_subscription_id'])?></p>
<form method="post"><?php admin_action('software_subscription_charge_reconcile'); ?>
<input type="hidden" name="invoice_id" value="<?=(int)$r['invoice_id']?>">
<?php admin_input('transaction_id','Verified recurring transaction ID','','text','required pattern="[0-9]{5,30}" maxlength="30"'); ?>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Reconcile recurring transaction</button>
</form>
</div>
<?php endforeach; ?>
</section>
<section class="workspace-card"><h2>Subscription payment review</h2>
<p>Interrupted enrollment, failed, suspended, terminated, or reversed recurring payments remain here until an owner resolves the billing state. Paid-through access is not silently revoked.</p>
<?php if(!$subscriptionReviews): ?><p class="muted">No software subscriptions currently require review.</p><?php endif; ?>
<?php foreach($subscriptionReviews as $r): ?>
<div class="workspace-card">
<p><?=esc($r['name'].' · '.$r['sku'])?> · Original invoice <?=(int)$r['invoice_id']?> · local state <?=esc($r['status'])?><?php if($r['gateway_subscription_id']!==null): ?> · Gateway subscription <?=esc((string)$r['gateway_subscription_id'])?><?php endif; ?></p>
<form method="post"><?php admin_action('software_subscription_reconcile'); ?>
<input type="hidden" name="invoice_id" value="<?=(int)$r['invoice_id']?>">
<?php admin_input('gateway_subscription_id','Verified Authorize.net subscription ID',(string)($r['gateway_subscription_id']??''),'text','required pattern="[0-9]{1,25}" maxlength="25"'); ?>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Reconcile gateway subscription</button>
</form>
</div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<h2>License register</h2>
<div class="workspace-grid">
<?php foreach($licenses as $lic): ?>
<?php
$domains=license_domains((int)$lic['id']);
$history=all('SELECT action,detail,created_at FROM '.table('audit').' WHERE action LIKE ? AND (detail LIKE ? OR detail LIKE ?) ORDER BY id DESC LIMIT 20',
    ['license.%','License '.(int)$lic['id'].' %','Software license '.(int)$lic['id'].' %']);
$isSoftwareEntitlement=$softwareEntitlementTable
    &&(bool)one('SELECT license_id FROM '.table('software_entitlements').' WHERE license_id=?',[$lic['id']]);
$meta=null;$observations=[];$lifecycle=null;$financePlan=$financingReady?license_financing_plan((int)$lic['id']):null;
if($softwareReady&&$isSoftwareEntitlement){
    $meta=one('SELECT license_type,licensee,expires_at,updates_until,max_installations,policy_revision,purpose,issued_by,invoice_id,unit_number FROM '.table('software_entitlements').' WHERE license_id=?',[$lic['id']]);
    if($meta){require_once CWP_CODE_ROOT.'/app/licenses/software_usage.php';require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';$observations=software_usage_recent((int)$lic['id'],10);$lifecycle=software_grant_lifecycle((int)$lic['id']);}
}
?>
<section class="workspace-card">
<dl>
<dt><strong>License key</strong></dt>
<dd><code><?= esc(substr($lic['license_key'],0,8)) ?>…</code></dd>
<dt><strong>Customer</strong></dt>
<dd><?= esc($lic['customer_name'] ?? 'Unknown') ?></dd>
<dt><strong>Product</strong></dt>
<dd><?= esc($lic['product_id']) ?></dd>
<dt><strong>Status</strong></dt><?php $ls=$lifecycle['state']??((int)$lic['is_active']===1?'active':'suspended');$ll=match($ls){'active'=>'Active','suspended'=>'Suspended','revoked'=>'Revoked',default=>'Inactive'}; ?><dd><span style="color:<?=$ls==='active'?'green':'red'?>"><?=esc($ll)?></span><?= (int)($lic['is_master']??0) ? ' <span style="color:#b8860b">MASTER — any domain</span>' : '' ?></dd>
<?php if($isSoftwareEntitlement&&!$softwareReady): ?>
<dt><strong>Software storage</strong></dt><dd><span style="color:#b8860b">Entitlement detected but software-commerce readiness is incomplete. Preserve the database and complete the reviewed recovery before changing software-specific rights.</span></dd>
<?php endif; ?>
<?php if($meta): ?>
<dt><strong>Type / licensee</strong></dt><dd><?=esc($meta['license_type'])?> · <?=esc($meta['licensee'])?></dd>
<dt><strong>Expires</strong></dt><dd><?=esc($meta['expires_at']??'Never')?></dd>
<dt><strong>Update entitlement</strong></dt><dd><?=(int)($lic['is_master']??0)===1
    ?'Internal master · all recorded versions, including owner-private staging'
    :($meta['updates_until']===null?'Lifetime while license is valid':'Through '.esc($meta['updates_until']).' UTC')?> · policy revision <?=(int)$meta['policy_revision']?></dd>
<dt><strong>Installation limit</strong></dt><dd><?=(int)($lic['is_master']??0)===1
    ?'Internal master · seat limit not applied'
    :(int)$meta['max_installations'].' concurrent installation'.((int)$meta['max_installations']===1?'':'s')?></dd>
<?php
$releaseCutoff=(int)($lic['is_master']??0)===1?null:$meta['updates_until'];
$eligibleReleases=(int)($lic['is_master']??0)===1
    ?all('SELECT version,title,released_at FROM '.table('software_assets').' WHERE sku=? ORDER BY id DESC LIMIT 5',[$lic['product_id']])
    :all('SELECT version,title,released_at FROM '.table('software_assets').' WHERE sku=? AND is_published=1 AND released_at IS NOT NULL AND (? IS NULL OR released_at<=?) ORDER BY released_at DESC,id DESC LIMIT 5',
        [$lic['product_id'],$releaseCutoff,$releaseCutoff]);
?>
<dt><strong><?=(int)($lic['is_master']??0)===1?'Recorded releases':'Eligible customer releases'?></strong></dt>
<dd><?php if(!$eligibleReleases): ?><?=(int)($lic['is_master']??0)===1?'No recorded releases':'None published within this entitlement'?><?php else: ?><?php foreach($eligibleReleases as $index=>$release): ?><?=$index?' · ':''?>v<?=esc($release['version'])?><?php endforeach; ?><?php endif; ?></dd>
<dt><strong>Issuance source</strong></dt>
<dd><?php if((int)$meta['invoice_id']>0): ?>Verified invoice #<?=(int)$meta['invoice_id']?><?=($meta['unit_number']!==null?' · unit '.(int)$meta['unit_number']:'')?><?php else: ?>Owner/staff issued<?php endif; ?> · issuer <?=((int)$meta['issued_by']>0?'staff #'.(int)$meta['issued_by']:'system fulfillment')?></dd>
<dt><strong>Registered installations</strong></dt><dd><?=count(array_filter($domains,static fn($d)=>(int)$d['is_active']===1))?> active domain<?=count(array_filter($domains,static fn($d)=>(int)$d['is_active']===1))===1?'':'s'?><?php if($observations): ?> · <?=count($observations)?> observed validation domain<?=count($observations)===1?'':'s'?><?php endif; ?></dd>
<dt><strong>Last successful validation</strong></dt><dd><?=$observations?esc($observations[0]['last_seen']).' UTC':'No successful checks observed'?> <span class="muted">(observed check, not proof software is currently running)</span></dd>
<?php if($lifecycle&&$lifecycle['state']!=='active'): ?><dt><strong>Lifecycle reason</strong></dt><dd><?=esc($lifecycle['reason']!==''?$lifecycle['reason']:'No retained reason')?></dd><?php endif; ?>
<?php if($meta['license_type']==='master'&&staff_can('staff.manage')): ?><dt><strong>Internal purpose</strong></dt><dd><?=esc($meta['purpose'])?></dd><?php endif; ?>
<?php endif; ?>
<?php if($financePlan): ?><dt><strong>Financing</strong></dt><dd><?=esc(ucwords(str_replace('_',' ',(string)$financePlan['state'])))?> · <?=(int)$financePlan['installments']?> installments · <?=(int)$financePlan['grace_days']?>-day grace<?= $financePlan['paid_off_at']!==null?' · permanently paid off '.esc($financePlan['paid_off_at']).' UTC':'' ?></dd><dt><strong>Commercial rights</strong></dt><dd><?=esc(license_financing_rights_label($financePlan))?></dd><?php endif; ?>
<dt><strong>Created</strong></dt>
<dd><?= esc($lic['created_at']) ?></dd>
</dl>

<h3>Registered domain history</h3>
<?php if($domains): ?>
<ul>
<?php foreach($domains as $d): ?>
<li>
<?= esc($d['domain']) ?> · registered <?=esc($d['registered_at'])?> UTC<?php if(!(int)$d['is_active']&&$d['deactivated_at']!==null): ?> · deactivated <?=esc($d['deactivated_at'])?> UTC<?php endif; ?>
<?php if((int)$d['is_active']): ?>
<span style="color:green">Active</span>
<?php if(!$financePlan||$financePlan['paid_off_at']===null): ?>
<form method="post" style="display:inline">
<?php admin_action('studio_domain_deactivate'); ?>
<input type="hidden" name="license_id" value="<?= esc($lic['id']) ?>">
<input type="hidden" name="domain" value="<?= esc($d['domain']) ?>">
<button class="btn outline" type="submit">Deactivate</button>
</form>
<?php endif; ?>
<form method="post" style="display:inline">
<?php admin_action('studio_domain_replace'); ?>
<input type="hidden" name="license_id" value="<?= esc($lic['id']) ?>">
<input type="hidden" name="old_domain" value="<?= esc($d['domain']) ?>">
<input type="text" name="new_domain" placeholder="new-domain.com" style="width:13em" required>
<button class="btn outline" type="submit">Replace</button>
</form>
<?php else: ?>
<span style="color:red">Deactivated</span>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p class="muted">No domains registered</p>
<?php endif; ?>

<form method="post">
<?php admin_action('studio_domain_add'); ?>
<input type="hidden" name="license_id" value="<?= esc($lic['id']) ?>">
<label for="domain_<?= esc($lic['id']) ?>">Add domain</label>
<input type="text" name="domain" id="domain_<?= esc($lic['id']) ?>" placeholder="example.com" required>
<button class="btn outline">Add domain</button>
</form>

<?php if($financePlan&&!$isSoftwareEntitlement&&!(int)($lic['is_master']??0)): ?><h3>Financing controls</h3><form method="post" style="display:inline"><?php admin_action('studio_license_financing_sync'); ?><input type="hidden" name="license_id" value="<?=esc($lic['id'])?>"><button class="btn outline">Refresh financing state</button></form>
<form method="post"><?php admin_action('studio_license_financing_rights'); ?><input type="hidden" name="license_id" value="<?=esc($lic['id'])?>"><label class="check"><input type="checkbox" name="allow_resale" value="1" <?=(int)$financePlan['allow_resale']===1?'checked':''?>>Resale rights</label><label class="check"><input type="checkbox" name="allow_distribution" value="1" <?=(int)$financePlan['allow_distribution']===1?'checked':''?>>Distribution rights</label><label class="check"><input type="checkbox" name="exclusive_resale" value="1" <?=(int)$financePlan['exclusive_resale']===1?'checked':''?>>Exclusive resale</label><label class="check"><input type="checkbox" name="exclusive_distribution" value="1" <?=(int)$financePlan['exclusive_distribution']===1?'checked':''?>>Exclusive distribution</label><label>Separate paid commercial-rights invoice ID<input type="number" name="rights_invoice_id" min="1" required></label><label>Agreement/payment evidence<input name="evidence" minlength="10" maxlength="200" required placeholder="Invoice purpose / signed rights addendum / payment evidence"></label><p class="hint">The evidence invoice must belong to this customer, be fully paid, and be separate from normal financing installments or the original down payment.</p><label>Owner password<input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn outline">Grant additional commercial rights</button></form><?php endif; ?>
<?php if($history): ?>
<h3>License history</h3>
<ul>
<?php foreach($history as $event): ?>
<li><?=esc($event['created_at'])?> UTC · <?=esc($event['action'])?> · <?=esc($event['detail'])?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if($meta&&$observations): ?>
<h3>Recent successful validation observations</h3>
<ul>
<?php foreach($observations as $ob): ?>
<li><?=esc($ob['domain'])?> · first <?=esc($ob['first_seen'])?> UTC · last <?=esc($ob['last_seen'])?> UTC · <?=number_format((int)$ob['check_count'])?> bucketed check<?=((int)$ob['check_count']===1?'':'s')?></li>
<?php endforeach; ?>
</ul>
<p class="hint">Validation observations are rate-bucketed evidence of successful checks, not live usage analytics.</p>
<?php endif; ?>

<?php if(!$isSoftwareEntitlement): ?>
<?php $bundles=all('SELECT * FROM '.table('license_bundles').' WHERE license_id=? ORDER BY created_at DESC',[(int)$lic['id']]); ?>
<?php if($bundles): ?>
<h3>Generated bundles</h3>
<ul>
<?php foreach($bundles as $b): ?>
<li><?= esc($b['title']) ?> — <code><?= esc(basename($b['file_path'])) ?></code> <small><?= esc($b['created_at']) ?></small></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php else: ?>
<p class="hint">Software releases use the immutable versioned ZIP registry above; legacy Website Core bundles are intentionally unavailable for this entitlement.</p>
<?php endif; ?>

<?php if($isSoftwareEntitlement&&$softwareReady&&$lifecycle): ?><h3>Software license lifecycle</h3>
<?php if($lifecycle['state']==='active'): ?><form method="post"><?php admin_action('software_license_lifecycle'); ?><input type="hidden" name="id" value="<?=esc($lic['id'])?>"><input type="hidden" name="transition" value="suspend"><label>Suspension reason<input name="reason" minlength="5" maxlength="200" required></label><button class="btn outline">Suspend temporarily</button></form><form method="post"><?php admin_action('software_license_lifecycle'); ?><input type="hidden" name="id" value="<?=esc($lic['id'])?>"><input type="hidden" name="transition" value="revoke"><label>Revocation reason<input name="reason" minlength="5" maxlength="200" required></label><label>Owner password<input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn outline">Revoke license</button></form>
<?php elseif($lifecycle['state']==='suspended'): ?><form method="post"><?php admin_action('software_license_lifecycle'); ?><input type="hidden" name="id" value="<?=esc($lic['id'])?>"><input type="hidden" name="transition" value="resume"><label>Resume reason<input name="reason" minlength="5" maxlength="200" required></label><button class="btn gold">Resume license</button></form><form method="post"><?php admin_action('software_license_lifecycle'); ?><input type="hidden" name="id" value="<?=esc($lic['id'])?>"><input type="hidden" name="transition" value="revoke"><label>Revocation reason<input name="reason" minlength="5" maxlength="200" required></label><label>Owner password<input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn outline">Revoke license</button></form>
<?php else: ?><form method="post"><?php admin_action('software_license_lifecycle'); ?><input type="hidden" name="id" value="<?=esc($lic['id'])?>"><input type="hidden" name="transition" value="restore"><label>Restore reason<input name="reason" minlength="5" maxlength="200" required></label><label>Owner password<input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn gold">Restore license</button></form><?php endif; ?><p class="hint">Suspension is reversible; revocation/restoration requires owner reauthentication. Key/domain/history are preserved.</p>
<?php else: ?>
<?php if($lic['is_active']): ?><?php if(!(int)($lic['is_master']??0) && !$isSoftwareEntitlement): ?><form method="post"><?php admin_action('studio_license_bundle'); ?><input type="hidden" name="id" value="<?=esc($lic['id'])?>"><label>Create bundle<select name="industry" required><option value="">Choose industry…</option><option value="restaurant">Restaurant</option><option value="salon">Salon</option><option value="generic">Generic Business</option></select></label><button class="btn gold">Generate bundle</button></form><?php endif; ?>
<?php if($financePlan&&$financePlan['paid_off_at']!==null): ?>
<p class="hint"><strong>Permanently unlocked:</strong> this financed license is fully paid and cannot be deactivated. Domain binding and commercial-rights restrictions still apply.</p>
<?php else: ?><form method="post"><?php admin_action('studio_license_deactivate'); ?><input type="hidden" name="id" value="<?=esc($lic['id'])?>"><button class="btn outline">Deactivate license</button></form><?php endif; ?>
<?php else: ?><form method="post"><?php admin_action('studio_license_reactivate'); ?><input type="hidden" name="id" value="<?=esc($lic['id'])?>"><button class="btn outline">Reactivate license</button></form><?php endif; ?>
<?php endif; ?></section>
<?php endforeach; ?>
</div>
<?php if(!$licenses): ?>
<p class="muted">No licenses created yet.</p>
<?php endif; ?>

<?php if(staff_can('staff.manage')): ?>
<section class="workspace-card"><h2>Review an older website invoice</h2><p>Use the original purchase records to decide whether this paid invoice already received a license. Matching customer or product names alone do not establish that history.</p>
<form method="post"><?php admin_action('studio_license_legacy_review'); ?>
<?php admin_input('invoice_id','Original paid invoice ID','','number','required min="1"'); ?>
<?php admin_input('license_id','Existing license ID, or 0 to deliberately create a new license','0','number','required min="0"'); ?>
<?php admin_input('evidence','Purchase record or reason for this exact decision','','text','required minlength="10" maxlength="200"'); ?>
<?php admin_input('current_password','Your current administrator password','','password','required autocomplete="current-password"'); ?>
<label class="check"><input type="checkbox" name="confirm" value="1" required>I verified this exact invoice and license decision against the original purchase records. Linking an existing license preserves its active or revoked status.</label><button class="btn gold">Record invoice review</button></form><p>No download is built, license reactivated, payment changed or email sent by this review.</p></section>
<?php endif; ?>
