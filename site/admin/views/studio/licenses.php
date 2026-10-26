<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
$softwareReady=software_commerce_ready();
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

$licenses = $ready ? all('SELECT l.*, c.name as customer_name FROM '.table('website_licenses').' l LEFT JOIN '.table('studio_clients').' c ON l.customer_id=c.id ORDER BY l.created_at DESC LIMIT 100') : [];
$customers = $ready ? array_column(all('SELECT id,name FROM '.table('studio_clients').' ORDER BY name'),'name','id') : [];
// First-class software-family products may use any valid SKU; retain legacy prefixed products.
$products = [];
try { foreach(all('SELECT sku,title FROM '.table('catalog_products')." WHERE family='software' OR sku LIKE 'WEBSITE-%' OR sku LIKE 'SOFTWARE-%' OR sku LIKE 'APP-%' ORDER BY title") as $p) $products[$p['sku']]=$p['title'].' ('.$p['sku'].')'; } catch (Throwable) {}
if(!$softwareReady){
    $products += ['restaurant'=>'Restaurant Website','salon'=>'Salon Website','generic'=>'Generic Business'];
}

if(isset($_SESSION['license_key_created'])){echo '<div class="notice info">License key created (displayed once): <code>'.esc($_SESSION['license_key_created']).'</code></div>';unset($_SESSION['license_key_created']);}
?>

<?php if(!$softwareReady): ?>
<section class="workspace-card"><h2>Enable advanced software licensing</h2><p>Add trial, temporary, master and paid software tracking, customer purchases, payment checks and private downloads. Existing licenses and invoices are retained.</p><form method="post"><?php admin_action('software_commerce_setup');admin_input('current_password','Current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Prepare software commerce</button></form></section>
<?php endif; ?>
<?php if($softwareReady): ?>
<section class="workspace-card">
<h2>Authorize.net payment configuration</h2>
<p>Checkout uses the existing encrypted payment credentials. Sandbox transactions can be tested but will not unlock real licenses. Production charging remains disabled until reviewed and approved.</p>
<p>Current checkout mode: <strong><?=esc((string)setting('software_gateway_mode','disabled'))?></strong></p>
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
</div>

<?php if($softwareReady && staff_can('staff.manage')): ?>
<section class="workspace-card"><h2>Publish a software download</h2>
<p>Place a versioned release ZIP in the server's private storage first; never expose a direct public file path or ZIP-inside-ZIP. This form validates the archive and records its SHA-256.</p>
<form method="post"><?php admin_action('software_asset_publish'); ?>
<?php admin_input('sku','Registered software product SKU','','text','required maxlength="60"'); ?>
<?php admin_input('version','Release version','','text','required maxlength="40"'); ?>
<?php admin_input('title','Download title','','text','required maxlength="120"'); ?>
<?php admin_input('private_path','Absolute path under private storage','','text','required maxlength="500"'); ?>
<?php admin_input('current_password','Owner password','','password','required autocomplete="current-password"'); ?>
<button class="btn gold">Publish verified ZIP</button></form></section>
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
<p><?=esc($n['name'].' · '.$n['sku'])?> · Invoice <?=(int)$n['invoice_id']?> · <?=esc($n['status'])?></p>
<?php if($n['status']!=='sent'): ?>
<form method="post"><?php admin_action('software_notice_retry'); ?>
<input type="hidden" name="invoice_id" value="<?=(int)$n['invoice_id']?>">
<?php admin_input('current_password','Owner password','','password','required'); ?>
<button class="btn outline">Retry delivery email</button></form>
<?php endif; ?>
<?php endforeach; ?>
</section>
<?php
$reversals=all('SELECT r.*,COALESCE(direct_order.sku,subscription_order.sku) AS sku,c.name FROM '.table('software_payment_reversals').' r LEFT JOIN '.table('software_orders').' direct_order ON direct_order.invoice_id=r.invoice_id LEFT JOIN '.table('software_orders').' subscription_order ON subscription_order.invoice_id=r.subscription_invoice_id LEFT JOIN '.table('studio_clients').' c ON c.id=COALESCE(direct_order.client_id,subscription_order.client_id) ORDER BY r.id DESC LIMIT 30');
$subscriptionReviews=all('SELECT s.invoice_id,s.gateway_subscription_id,s.status,o.sku,c.name FROM '.table('software_subscriptions').' s JOIN '.table('software_orders').' o ON o.invoice_id=s.invoice_id JOIN '.table('studio_clients').' c ON c.id=s.client_id WHERE s.status=? ORDER BY s.id DESC LIMIT 30',['review']);
?>
<section class="workspace-card"><h2>Payment reversal review</h2>
<p>Verified refunds and voids are recorded here for owner review. CajunWebPro does not automatically revoke already-paid access from a reversal notification.</p>
<?php if(!$reversals): ?><p class="muted">No verified software payment reversals recorded.</p><?php endif; ?>
<?php foreach($reversals as $r): ?>
<p><?=esc(($r['name']??'Unknown customer').' · '.($r['sku']??'Unknown software'))?> · <?=esc($r['kind'])?> · event <?=esc($r['gateway_transaction_id'])?> · original <?=esc($r['original_transaction_id'])?><?php if($r['amount_cents']!==null): ?> · $<?=esc(number_format((int)$r['amount_cents']/100,2))?><?php endif; ?></p>
<?php endforeach; ?>
</section>
<section class="workspace-card"><h2>Subscription payment review</h2>
<p>Failed, suspended, terminated, or reversed recurring payments remain here until an owner resolves the billing state. Paid-through access is not silently revoked.</p>
<?php if(!$subscriptionReviews): ?><p class="muted">No software subscriptions currently require review.</p><?php endif; ?>
<?php foreach($subscriptionReviews as $r): ?>
<p><?=esc($r['name'].' · '.$r['sku'])?> · Original invoice <?=(int)$r['invoice_id']?> · Gateway subscription <?=esc((string)$r['gateway_subscription_id'])?></p>
<?php endforeach; ?>
</section>
<?php endif; ?>
<h2>Active licenses</h2>
<div class="workspace-grid">
<?php foreach($licenses as $lic): ?>
<section class="workspace-card">
<dl>
<dt><strong>License key</strong></dt>
<dd><code><?= esc(substr($lic['license_key'],0,8)) ?>…</code></dd>
<dt><strong>Customer</strong></dt>
<dd><?= esc($lic['customer_name'] ?? 'Unknown') ?></dd>
<dt><strong>Product</strong></dt>
<dd><?= esc($lic['product_id']) ?></dd>
<dt><strong>Status</strong></dt>
<dd><?= $lic['is_active'] ? '<span style="color:green">Active</span>' : '<span style="color:red">Inactive</span>' ?><?= (int)($lic['is_master']??0) ? ' <span style="color:#b8860b">MASTER — any domain</span>' : '' ?></dd>
<?php if($softwareReady):$meta=one('SELECT license_type,licensee,expires_at,purpose FROM '.table('software_entitlements').' WHERE license_id=?',[$lic['id']]);if($meta): ?><dt><strong>Type / licensee</strong></dt><dd><?=esc($meta['license_type'])?> · <?=esc($meta['licensee'])?></dd><dt><strong>Expires</strong></dt><dd><?=esc($meta['expires_at']??'Never')?></dd><?php if($meta['license_type']==='master'&&staff_can('staff.manage')): ?><dt><strong>Internal purpose</strong></dt><dd><?=esc($meta['purpose'])?></dd><?php endif; ?><?php endif; endif; ?>
<dt><strong>Created</strong></dt>
<dd><?= esc($lic['created_at']) ?></dd>
</dl>

<h3>Registered domains</h3>
<?php $domains = license_domains((int)$lic['id']); ?>
<?php if($domains): ?>
<ul>
<?php foreach($domains as $d): ?>
<li>
<?= esc($d['domain']) ?>
<?php if((int)$d['is_active']): ?>
<span style="color:green">Active</span>
<form method="post" style="display:inline">
<?php admin_action('studio_domain_deactivate'); ?>
<input type="hidden" name="license_id" value="<?= esc($lic['id']) ?>">
<input type="hidden" name="domain" value="<?= esc($d['domain']) ?>">
<button class="btn outline" type="submit">Deactivate</button>
</form>
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

<?php $bundles=all('SELECT * FROM '.table('license_bundles').' WHERE license_id=? ORDER BY created_at DESC',[(int)$lic['id']]); ?>
<?php if($bundles): ?>
<h3>Generated bundles</h3>
<ul>
<?php foreach($bundles as $b): ?>
<li><?= esc($b['title']) ?> — <code><?= esc(basename($b['file_path'])) ?></code> <small><?= esc($b['created_at']) ?></small></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if($lic['is_active']): ?>
<?php if(!(int)($lic['is_master']??0)): ?>
<form method="post">
<?php admin_action('studio_license_bundle'); ?>
<input type="hidden" name="id" value="<?= esc($lic['id']) ?>">
<label for="industry_<?= esc($lic['id']) ?>">Create bundle</label>
<select name="industry" id="industry_<?= esc($lic['id']) ?>" required>
<option value="">Choose industry…</option>
<option value="restaurant">Restaurant</option>
<option value="salon">Salon</option>
<option value="generic">Generic Business</option>
</select>
<button class="btn gold" type="submit">Generate bundle</button>
</form>
<?php endif; ?>
<form method="post">
<?php admin_action('studio_license_deactivate'); ?>
<input type="hidden" name="id" value="<?= esc($lic['id']) ?>">
<button class="btn outline" type="submit">Deactivate license</button>
</form>
<?php else: ?>
<form method="post">
<?php admin_action('studio_license_reactivate'); ?>
<input type="hidden" name="id" value="<?= esc($lic['id']) ?>">
<button class="btn outline" type="submit">Reactivate license</button>
</form>
<?php endif; ?>
</section>
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
