<?php
/** Software is a section of the existing CajunWebPro client account. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_orders.php';

function software_portal_render(?array $client,string $error=''):never{
    if(!$client||support_current())fail_page('Sign in to your own account to manage software.',403);
    site_head('My Software & Licenses','portal');support_banner();
    echo '<section class="section"><div class="container"><p><a href="'.esc(studio_url()).'">Client workspace</a> / My Software</p><h1>Software & Licenses</h1>';
    show_flash();
    if($error!=='')echo '<p class="notice error">'.esc($error).'</p>';
    if(!software_commerce_ready()){
        echo '<p>Software purchases are not enabled yet. Your existing hosting, domains and project records remain available.</p>';
    }else{
        $clientId=(int)$client['id'];
        echo '<h2>My licenses</h2><div class="workspace-grid">';
        $licenses=all('SELECT l.*, e.license_type,e.licensee,e.expires_at FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.customer_id=? AND l.is_master=0 ORDER BY l.id DESC LIMIT 100',[$clientId]);
        foreach($licenses as $lic){
            $expired=$lic['expires_at']!==null&&$lic['expires_at']<=gmdate('Y-m-d H:i:s');
            $status=!(int)$lic['is_active']?'Inactive':($expired?'Expired':'Active');
            echo '<article class="workspace-card"><h3>'.esc($lic['product_id']).'</h3><p>'.esc(ucfirst($lic['license_type'])).' license · '.esc($status).'</p><p>Licensee: '.esc($lic['licensee']).'</p><p>Key: <code>'.esc($lic['license_key']).'</code></p>';
            if($lic['expires_at'])echo '<p>Expires: '.esc($lic['expires_at']).' UTC</p>';
            $domains=license_domains((int)$lic['id']);
            echo '<h4>Approved domains</h4>';
            foreach($domains as $d)if((int)$d['is_active']){
                echo '<p>'.esc($d['domain']).' <small>Active</small></p><form method="post">'.support_csrf_field().'<input type="hidden" name="action" value="license_domain_replace"><input type="hidden" name="license_id" value="'.(int)$lic['id'].'"><input type="hidden" name="old_domain" value="'.esc($d['domain']).'"><label>Move to another domain<input name="new_domain" required placeholder="example.com"></label><button class="btn outline">Replace domain</button></form>';
            }
            echo '<p class="hint">Changing domains revokes the old installation at its next valid license check.</p></article>';
        }
        if(!$licenses)echo '<p>No software licenses issued to this account yet.</p>';
        echo '</div><h2>Purchased downloads</h2>';
        $assets=all('SELECT DISTINCT a.id,a.sku,a.version,a.title FROM '.table('software_assets').' a JOIN '.table('website_licenses').' l ON l.product_id=a.sku JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.customer_id=? AND l.is_active=1 AND l.is_master=0 AND a.is_published=1 AND (e.expires_at IS NULL OR e.expires_at>UTC_TIMESTAMP()) ORDER BY a.sku,a.version DESC LIMIT 100',[$clientId]);
        foreach($assets as $a)echo '<p><a href="'.esc(site_url('api/software-download.php?id='.$a['id'])).'">'.esc($a['title'].' · '.$a['version']).' — Download ZIP</a></p>';
        if(!$assets)echo '<p>No eligible downloads available yet.</p>';
        echo '<h2>Orders and invoices</h2>';
        $orders=all('SELECT o.*,i.status,i.amount_cents,i.reference FROM '.table('software_orders').' o JOIN '.table('studio_invoices').' i ON i.id=o.invoice_id WHERE o.client_id=? ORDER BY o.id DESC LIMIT 100',[$clientId]);
        foreach($orders as $o)echo '<p>'.esc($o['sku']).' · '.esc($o['domain']).' · '.esc($o['reference']).' · '.esc($o['status']).' · $'.esc(number_format($o['amount_cents']/100,2)).' <a href="'.esc(studio_url(['invoice'=>$o['invoice_id']])).'">Invoice</a></p>';
        echo '<h2>Order software</h2>';
        foreach(catalog_public() as $p)if(software_order_offer($p)){
            echo '<article class="workspace-card"><h3>'.esc($p['title']).'</h3><p>'.esc($p['summary']).'</p><p>$'.esc(number_format($p['amount_cents']/100,2)).' · '.esc(catalog_terms()[$p['term']]).'</p>';
            echo '<form method="post">'.support_csrf_field().'<input type="hidden" name="action" value="software_order_buy"><input type="hidden" name="product_id" value="'.(int)$p['id'].'"><input type="hidden" name="revision" value="'.(int)$p['revision'].'"><input type="hidden" name="request_key" value="'.bin2hex(random_bytes(32)).'"><label>Installation domain<input type="text" name="domain" placeholder="example.com" required></label><label>Licenses<input type="number" name="quantity" min="1" max="25" value="1" required></label>';
            if($p['term']!=='once')echo '<label><input type="checkbox" name="subscribe" value="1" required> I authorize the recurring '.esc($p['term']).' subscription at the listed price, subject to the agreed terms.</label>';
            echo '<button class="btn gold">Create order & invoice</button></form></article>';
        }
    }
    echo '</div></section>';site_foot(false);exit;
}
