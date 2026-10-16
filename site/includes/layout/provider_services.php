<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/registrar.php';

require_once CWP_CODE_ROOT.'/app/providers/cloud_sites.php';

/**
 * Client-facing view of explicitly owner-linked 20i services. Only mappings
 * the owner approved appear; each row shows the package's last good observed
 * state. Absence from an observation is displayed honestly and never implies
 * deletion, cancellation or suspension.
 */
function provider_services_portal_render(): never {
    $client=studio_client();if(!$client)fail_page('Sign in to view your services.',403);
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    if(support_current())support_record('portal.view','services',(string)$client['id']);
    site_head('Your hosting services','portal');support_banner();
    echo '<section class="section"><div class="container"><span class="eyebrow">YOUR SERVICES</span><h1>Hosting services</h1><div class="button-row"><a class="btn outline" href="'.esc(studio_url()).'">All projects</a></div>';
    if(!provider_inventory_ready()) {
        echo '<p>Your services will appear here once the studio enables hosting inventory.</p></div></section>';site_foot(false);exit;
    }
    if(function_exists('provider_mailbox_client_navigation')&&!support_current()&&provider_mailbox_client_navigation((int)$client['id']))echo '<a class="btn outline" href="'.esc(studio_url(['hosting_mail'=>1])).'">Hosting mailboxes</a>';
    if(!support_current())echo '<a class="btn outline" href="'.esc(studio_url(['domain_expiry'=>1])).'">Expiry observations</a> <a class="btn outline" href="'.esc(studio_url(['domain_dates'=>1])).'">Renewal dates</a> <a class="btn outline" href="'.esc(studio_url(['domain_renewals'=>1])).'">Domain renewal requests</a><a class="btn outline" href="'.esc(studio_url(['service_history'=>1])).'">Service history</a><a class="btn outline" href="'.esc(studio_url(['forwarding'=>1])).'">Email forwarding requests</a>';
    $services=provider_inventory_client_services((int)$client['id']);
    $domains=provider_inventory_client_domains((int)$client['id']);
    $cloudServices=provider_cloud_client_services((int)$client['id']);
    if(!$services&&!$domains&&!$cloudServices)echo '<p>No hosting services are linked to your account yet. Contact the studio if you expected one.</p>';
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
        if(!support_current()&&!$service['context_changed'])echo '<a class="btn outline" href="'.esc(studio_url(['php_version'=>1,'service_link'=>$service['link']['id']])).'">Website PHP version</a>';
        if(!support_current()&&!$service['context_changed'])echo '<a class="btn outline" href="'.esc(studio_url(['document_roots'=>1,'service_link'=>$service['link']['id']])).'">Website folders</a>';
        if(!support_current()&&!$service['context_changed'])echo '<a class="btn outline" href="'.esc(studio_url(['force_ssl'=>1,'service_link'=>$service['link']['id']])).'">Force SSL flag</a>';
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
            echo '<p>'.esc($domain['observed_at']===null?'No observation is available yet.':'The studio could not confirm this domain on its approved hosting package. Details are hidden while the studio reviews the link.').' Contact the studio for details.</p>';
        }
        echo '</article>';
    }
    foreach($cloudServices as $cloud) {
        echo '<article class="workspace-card" data-client-cloud><h2>'.esc($cloud['server']['name']??('Cloud server #'.$cloud['server_id'])).'</h2>';
        if($cloud['context_changed'])echo '<p>The studio needs to review this cloud assignment under its current provider settings. Server details are hidden until the assignment is confirmed.</p>';
        elseif(!$cloud['server'])echo '<p>This server was not present in the latest saved cloud observation. This does not confirm cancellation or deletion. Contact the studio for an update.</p>';
        else {
            $server=$cloud['server'];echo '<p>Cloud server #'.(int)$server['id'].' · Location '.esc($server['location']??'not reported').' · Specification '.esc($server['product_spec']??'not reported').'</p>';
            if($server['configuration']!==null){echo '<dl>';foreach(['CpuCores'=>'CPU cores','RamMb'=>'Memory (MB)','OsDiskSizeGb'=>'OS disk (GB)','DataDiskSizeGb'=>'Data disk (GB)'] as $key=>$label)echo '<dt>'.esc($label).'</dt><dd>'.esc($server['configuration'][$key]===null?'Not reported':(string)$server['configuration'][$key]).'</dd>';echo '</dl>';}
            echo '<p class="hint">Observed '.esc($cloud['observed_at']).' UTC. These are saved provider specifications, not a live health check. Billing and renewal terms are separate.</p>';
            foreach(provider_cloud_sites_client_history((int)$server['id']) as $request)echo '<p data-client-cloud-request><strong>'.esc($request['domain']).'</strong> · '.esc($request['status']).' · '.esc($request['created_at']).' UTC</p>';
        }
        echo '</article>';
    }
    require CWP_CODE_ROOT.'/portal/views/registered_domains.php';
    echo '</div></section>';site_foot(false);exit;
}
