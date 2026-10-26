<?php
/** Software lives inside the single, authenticated CajunWebPro client portal. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_orders.php';

function software_portal_render(?array $client,string $error=''):never{
    if(!$client||support_current())fail_page('Sign in to your own account to manage software.',403);
    header('Cache-Control: private, no-store');
    site_head('My Software & Licenses','portal');support_banner();
    echo '<section class="section"><div class="container">';
    echo '<p><a href="'.esc(studio_url()).'">Client workspace</a> / My Software</p><h1>Software & Licenses</h1>';
    show_flash();
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    $gateway=null;
    if(!software_commerce_ready()){
        echo '<p>Software purchases have not been enabled. Your existing hosting, domains and project records remain available.</p>';
    }else{
        $clientId=(int)$client['id'];
        echo '<h2>My licenses</h2><div class="workspace-grid">';
        $licenses=all('SELECT l.*,e.license_type,e.licensee,e.expires_at FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.customer_id=? AND l.is_master=0 ORDER BY l.id DESC LIMIT 100',[$clientId]);
        foreach($licenses as $lic){
            $expired=$lic['expires_at']!==null && $lic['expires_at']<=gmdate('Y-m-d H:i:s');
            $status=!(int)$lic['is_active']?'Inactive':($expired?'Expired':'Active');
            echo '<article class="workspace-card"><h3>'.esc($lic['product_id']).'</h3>';
            echo '<p>'.esc(ucfirst($lic['license_type'])).' · '.esc($status).'</p>';
            echo '<p>Licensed to: '.esc($lic['licensee']).'</p>';
            echo '<p>Key: <code>'.esc($lic['license_key']).'</code></p>';
            if($lic['expires_at']!==null)echo '<p>Expires '.esc($lic['expires_at']).' UTC</p>';
            echo '<h4>Installation domains</h4>';
            foreach(license_domains((int)$lic['id']) as $domain){
                if(!(int)$domain['is_active'])continue;
                echo '<p>'.esc($domain['domain']).' · Active</p>';
                echo '<form method="post">'.support_csrf_field();
                echo '<input type="hidden" name="action" value="license_domain_replace">';
                echo '<input type="hidden" name="license_id" value="'.(int)$lic['id'].'">';
                echo '<input type="hidden" name="old_domain" value="'.esc($domain['domain']).'">';
                echo '<label>Move this installation<input name="new_domain" maxlength="253" required placeholder="new-domain.com"></label>';
                echo '<button class="btn outline" type="submit">Replace domain</button></form>';
            }
            echo '<p class="hint">Replacing the domain deauthorizes the previous installation at its next successful license check.</p>';
            require_once CWP_CODE_ROOT.'/app/licenses/software_usage.php';
            $observations=software_usage_for_customer((int)$lic['id'],$clientId);
            if($observations){
                echo '<h4>Last successful license checks</h4>';
                foreach($observations as $ob)echo '<p>'.esc($ob['domain']).' · '.esc($ob['last_seen']).' UTC</p>';
            }
            echo '</article>';
        }
        if(!$licenses)echo '<p>No issued software licenses yet.</p>';
        echo '</div><h2>Purchased downloads</h2>';
        $assets=all('SELECT DISTINCT a.id,a.sku,a.version,a.title FROM '.table('software_assets').' a JOIN '.table('website_licenses').' l ON l.product_id=a.sku JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.customer_id=? AND l.is_active=1 AND l.is_master=0 AND a.is_published=1 AND (e.expires_at IS NULL OR e.expires_at>UTC_TIMESTAMP()) ORDER BY a.sku,a.version DESC LIMIT 100',[$clientId]);
        foreach($assets as $asset){
            echo '<p><a href="'.esc(site_url('api/software-download.php?id='.$asset['id'])).'">'.esc($asset['title'].' · v'.$asset['version']).' — Download ZIP</a></p>';
        }
        if(!$assets)echo '<p>No eligible software downloads yet.</p>';
        echo '<h2>Software orders and invoices</h2>';
        try{
            require_once CWP_CODE_ROOT.'/app/billing/authorize_net_client.php';
            $gateway=software_anet_settings();
        }catch(Throwable){$gateway=null;}
        $orders=all('SELECT o.*,i.status,i.amount_cents,i.reference FROM '.table('software_orders').' o JOIN '.table('studio_invoices').' i ON i.id=o.invoice_id WHERE o.client_id=? ORDER BY o.id DESC LIMIT 100',[$clientId]);
        foreach($orders as $order){
            echo '<article class="workspace-card"><h3>'.esc($order['sku']).' · '.esc($order['reference']).'</h3>';
            echo '<p>Domain: '.esc($order['domain']).' · '.esc($order['status']);
            echo ' · $'.esc(number_format((int)$order['amount_cents']/100,2));
            echo ' · <a href="'.esc(studio_url(['invoice'=>$order['invoice_id']])).'">Invoice</a></p>';
            if(in_array($order['billing_term'],['month','year'],true)){
                $sub=one('SELECT status,gateway_subscription_id FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$order['invoice_id']]);
                if($sub && in_array($sub['status'],['pending_first_payment','active'],true)){
                    echo '<form method="post">'.support_csrf_field();
                    echo '<input type="hidden" name="action" value="software_subscription_cancel">';
                    echo '<input type="hidden" name="invoice_id" value="'.(int)$order['invoice_id'].'">';
                    echo '<p class="hint">Cancellation stops future gateway charges; already-paid license access continues through its paid period.</p>';
                    echo '<button class="btn outline" type="submit">Cancel future renewal payments</button></form>';
                }
            }
            if($order['status']==='issued' && $gateway && $gateway['client_key']!==''){
                $recurring=in_array($order['billing_term'],['month','year'],true);
                $prior=$recurring?
                    one('SELECT status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$order['invoice_id']]):
                    one('SELECT status FROM '.table('software_charges').' WHERE invoice_id=?',[$order['invoice_id']]);
                if($prior){
                    echo '<p class="notice info">Payment status: '.esc($prior['status']).'. Contact billing before attempting another charge.</p>';
                }else{
                    echo '<form method="post" data-authorize-net data-login="'.esc($gateway['login']).'" data-client-key="'.esc($gateway['client_key']).'">';
                    echo support_csrf_field();
                    echo '<input type="hidden" name="action" value="'.($recurring?'software_subscription_enroll':'software_charge').'">';
                    echo '<input type="hidden" name="invoice_id" value="'.(int)$order['invoice_id'].'">';
                    echo '<input type="hidden" name="request_key" value="'.bin2hex(random_bytes(32)).'">';
                    echo '<input type="hidden" name="descriptor"><input type="hidden" name="payment_token">';
                    echo '<label>Card number<input data-card-number autocomplete="cc-number" inputmode="numeric" minlength="13" maxlength="23" required></label>';
                    echo '<label>Expiration month (MM)<input data-card-month autocomplete="cc-exp-month" inputmode="numeric" pattern="[0-9]{2}" maxlength="2" required></label>';
                    echo '<label>Expiration year (YY)<input data-card-year autocomplete="cc-exp-year" inputmode="numeric" pattern="[0-9]{2}" maxlength="2" required></label>';
                    echo '<label>Security code<input data-card-code autocomplete="cc-csc" inputmode="numeric" minlength="3" maxlength="4" required></label>';
                    echo '<p class="hint">Card details are sent directly to Authorize.net for tokenization and are not included in this form submission.</p>';
                    if($recurring)echo '<p class="hint">Your '.esc($order['billing_term']).' subscription bills on the gateway schedule. Creating a subscription does not authorize software until its first charge succeeds.</p>';
                    if($gateway['mode']==='sandbox')echo '<p class="notice info">Sandbox test only. This cannot settle a real invoice or issue a paid license.</p>';
                    echo '<p role="alert" data-payment-error></p>';
                    echo '<button class="btn gold" type="submit">'.($recurring?'Enroll in recurring billing':'Pay invoice securely').'</button></form>';
                }
            }elseif($order['status']==='issued'){
                echo '<p class="hint">Online card checkout is currently disabled. Your invoice remains available.</p>';
            }
            echo '</article>';
        }
        echo '<h2>Purchase software</h2>';
        foreach(catalog_public() as $offer){
            if(!software_order_offer($offer))continue;
            echo '<article class="workspace-card"><h3>'.esc($offer['title']).'</h3><p>'.esc($offer['summary']).'</p>';
            echo '<p>$'.esc(number_format((int)$offer['amount_cents']/100,2)).' · '.esc(catalog_terms()[$offer['term']]).'</p>';
            echo '<form method="post">'.support_csrf_field().'<input type="hidden" name="action" value="software_order_buy">';
            echo '<input type="hidden" name="product_id" value="'.(int)$offer['id'].'">';
            echo '<input type="hidden" name="revision" value="'.(int)$offer['revision'].'">';
            echo '<input type="hidden" name="request_key" value="'.bin2hex(random_bytes(32)).'">';
            echo '<label>Installation domain<input name="domain" maxlength="253" required placeholder="example.com"></label>';
            echo '<label>Licenses<input name="quantity" type="number" min="1" max="25" value="1" required></label>';
            if($offer['term']!=='once')echo '<label><input name="subscribe" type="checkbox" value="1" required> I authorize the '.esc($offer['term']).' subscription and understand recurring charges and cancellation terms.</label>';
            echo '<button class="btn gold" type="submit">Create invoice</button></form></article>';
        }
    }
    echo '</div></section>';
    if($gateway){
        $external=$gateway['mode']==='sandbox'?'https://jstest.authorize.net/v1/Accept.js':'https://js.authorize.net/v1/Accept.js';
        echo '<script defer src="'.esc($external).'"></script>';
        echo '<script defer src="'.esc(site_url('assets/js/authorize-net-checkout.js')).'"></script>';
    }
    site_foot(false);
    exit;
}
