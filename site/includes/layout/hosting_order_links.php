<?php
/** Hosting preparation and honest progress inside the existing agreement workspace. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/order_link_views.php';
require_once CWP_CODE_ROOT.'/includes/layout/hosting_fulfillment.php';
require_once CWP_CODE_ROOT.'/includes/layout/provider_plan_catalog.php';

function hosting_order_links_ui(ServiceOrderDocument $order,bool $owner,?array $assessment):void {
    $ctx=hosting_order_links_context($order->data()['id'],$order->data()['client_id'],!$owner);$p=$ctx['progress'];
    if($p){
        $label=match($p['status']){'pending'=>'Hosting record prepared','provisioning'=>'Hosting setup in progress','unknown'=>'Hosting setup outcome needs review','active'=>'Hosting service active','failed'=>'Hosting setup needs attention','suspended'=>'Hosting service suspended','cancelled'=>'Hosting service cancelled','deprovisioned'=>'Hosting service removed',default=>'Hosting service needs review'};
        echo '<section class="workspace-card" data-hosting-progress><h2>Hosting setup progress</h2><p class="workspace-status">'.esc($label).'</p><p>Service reference: <span class="hosting-service-reference">'.esc($p['account_id']).'</span></p><p>Record prepared '.esc(gmdate('Y-m-d H:i:s',$p['created_at'])).' UTC.</p>';
        if($p['order_cancelled'])echo '<p>Your hosting request is cancelled. Its service record and invoice history are retained. Cancellation of a request does not delete an existing website.</p>';
        elseif($p['status']==='pending')echo '<p>The studio has prepared your service record. Provider setup has not started; this is not an active hosting service.</p>';
        elseif($p['status']==='active')echo '<p>The provider confirmed this service enabled at its last setup check. Website content, DNS propagation and SSL are checked separately.</p>';
        elseif($p['status']==='unknown')echo '<p>The setup outcome is uncertain. The studio must review the original attempt before any further setup request.</p>';
        if($owner){
            echo '<p>Reviewed package: '.esc($p['package']['label']).' · Type '.esc($p['package']['provider_type']).' · '.esc($p['package']['region']).'</p>';
            if($p['status']==='pending')echo '<p>Provider package availability and configuration still require verification before setup. No provider request was sent by this preparation form.</p>';
            if(!$p['profile_current'])echo '<p class="notice warning">The saved provider connection changed. Review its original mapping before any setup.</p>';
        }
        echo '</section>';if($owner)hosting_fulfillment_ui($order);return;
    }
    if(!$owner||$order->state()['phase']!=='accepted')return;
    $profile=$ctx['profile'];
    echo '<section class="workspace-card" data-hosting-preparation><h2>Prepare hosting service</h2>';
    if(!$profile||!(int)$profile['enabled']||!$profile['has_credentials']){
        echo '<p>Save an enabled provider connection before preparing this service.</p><a class="btn outline" href="'.esc(admin_url('provider-settings')).'">Provider connection</a></section>';return;
    }
    if(!$ctx['ready']){
        echo '<p>Prepare local hosting history to connect this order to its service record. Existing orders, invoices and services are preserved.</p><form method="post">';service_orders_ui_fields('service_plans_order_prepare_service',$order->data()['agreement']['id'],$order);
        service_plans_confirmation('Prepare local hosting history for this accepted order.',true);echo '<button class="btn gold">Prepare hosting history</button></form></section>';return;
    }
    if(!$assessment||!$assessment['eligible']){echo '<p>Complete the commercial review before creating this order’s hosting record.</p></section>';return;}
    $catalogOrigin=$ctx['catalog_origin']??null;
    provider_plan_catalog_order_ui($order,$catalogOrigin);
    if($catalogOrigin!==null){
        echo '<p>This order keeps the provider mapping from its original hosting offer. <a href="'.esc(admin_url('studio-job',['id'=>$catalogOrigin['job_id']])).'">View original project</a>.</p></section>';return;
    }
    $action='service_plans_order_map_service';
    echo '<p>Record the package and location reviewed in your provider account. This prepares a local service record; provider availability, defaults and setup authorization still require verification.</p><p>Check package defaults such as Website Turbo, welcome email, application installation and SSH keys in My20i. A package type can apply these automatically.</p><form method="post">';service_orders_ui_fields($action,$order->data()['agreement']['id'],$order);
    foreach(['order_hash'=>$order->fingerprint(),'account_id'=>bin2hex(random_bytes(16)),'profile_id'=>$profile['id'],'profile_revision'=>(string)$profile['revision']] as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc(service_orders_ui_value($action,$key,$value,64)).'">';
    foreach(['reseller_id'=>['Reseller account number',10],'package_type'=>['Provider package type number',18],'package_label'=>['Reviewed package name',120],'package_region'=>['Reviewed hosting location',80]] as $key=>[$label,$max])echo '<label>'.esc($label).'<input name="'.esc($key).'" maxlength="'.$max.'" value="'.esc(service_orders_ui_value($action,$key,'',$max)).'" required></label>';
    echo '<fieldset><legend>Reviewed provider limits</legend><p>Enter at least one known numeric limit. Leave unknown limits blank; 0 means zero, not unlimited. These values do not change the client’s agreed price or terms.</p>';
    foreach(hosting_order_links_limit_fields() as $key=>$label){$name='package_'.$key;echo '<label>'.esc($label).'<input type="number" min="0" max="2147483647" step="1" name="'.esc($name).'" value="'.esc(service_orders_ui_value($action,$name,'',10)).'"></label>';}
    echo '</fieldset><label class="check"><input type="checkbox" name="mapping_only" value="1" required> I reviewed these details in the provider account. This local mapping does not verify entitlement or authorize provider setup.</label>';
    service_plans_confirmation('Prepare one hosting record for this client’s accepted domain and exact order.',true);echo '<button class="btn gold">Save hosting preparation</button></form></section>';
}
