<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';

/**
 * Client-facing view of explicitly owner-linked 20i services. Only mappings
 * the owner approved appear; each row shows the package's last good observed
 * state. Absence from an observation is displayed honestly and never implies
 * deletion, cancellation or suspension.
 */
function provider_services_portal_render(): never {
    $client=studio_client();if(!$client)fail_page('Sign in to view your services.',403);
    if(support_current())support_record('portal.view','services',(string)$client['id']);
    site_head('Your hosting services','portal');support_banner();
    echo '<section class="section"><div class="container"><span class="eyebrow">YOUR SERVICES</span><h1>Hosting services</h1><div class="button-row"><a class="btn outline" href="'.esc(studio_url()).'">All projects</a></div>';
    if(!provider_inventory_ready()) {
        echo '<p>Your services will appear here once the studio enables hosting inventory.</p></div></section>';site_foot(false);exit;
    }
    $services=provider_inventory_client_services((int)$client['id']);
    $domains=provider_inventory_client_domains((int)$client['id']);
    if(!$services&&!$domains)echo '<p>No hosting services are linked to your account yet. Contact the studio if you expected one.</p>';
    echo '<div class="workspace-grid">';
    foreach($services as $service) {
        $package=$service['package'];echo '<article class="workspace-card">';
        if($service['context_changed']) {
            echo '<h2>Hosting service #'.(int)$service['link']['package_id'].'</h2><p>The studio changed its provider settings since this service was approved. Details are hidden until the studio confirms this service under the current settings. Contact the studio if this stays unavailable.</p>';
        } elseif($package) {
            echo '<h2>'.esc($package['name']).'</h2><p>'.esc($package['package_type_name']??'Hosting package').' · '.esc((int)$package['enabled']===1?'Active':'Disabled').' · Linked '.esc($service['link']['linked_at']).' UTC</p>';
            if($package['names'])echo '<p>'.esc(implode(', ',$package['names'])).'</p>';
            echo '<p class="hint">Observed '.esc($service['observed_at']).' UTC. Status reflects the studio\'s last confirmed read.</p>';
        } else {
            echo '<h2>Hosting service #'.(int)$service['link']['package_id'].'</h2><p>'.esc($service['observation']===null?'No observation is available yet.':'This service was not present in the last confirmed inventory read.').' Contact the studio for details.</p>';
        }
        echo '</article>';
    }
    foreach($domains as $domain) {
        echo '<article class="workspace-card"><h2>'.esc($domain['domain']).'</h2>';
        if($domain['context_changed']) {
            echo '<p>The studio changed its provider settings since this domain was approved. Details are hidden until the studio confirms it under the current settings. Contact the studio if this stays unavailable.</p>';
        } elseif($domain['present']) {
            echo '<p>Hosted domain · package #'.(int)$domain['package']['package_id'].' · Linked '.esc($domain['link']['linked_at']).' UTC</p>';
            echo '<p class="hint">This hosting link does not establish domain registration or renewal rights.</p>';
            echo '<p class="hint">Observed '.esc($domain['observed_at']).' UTC. Status reflects the studio\'s last confirmed read.</p>';
        } else {
            echo '<p>'.esc($domain['observed_at']===null?'No observation is available yet.':'This domain was not present in the last confirmed inventory read.').' Contact the studio for details.</p>';
        }
        echo '</article>';
    }
    echo '</div></section>';site_foot(false);exit;
}
