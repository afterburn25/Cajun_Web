<?php
/** Native owner approval and recovery cards on the existing service agreement page. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment_views.php';

function hosting_fulfillment_ui_hidden(string $action,array $fields):void {
    foreach($fields as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc(service_orders_ui_value($action,$key,(string)$value,64)).'">';
}
function hosting_fulfillment_ui(ServiceOrderDocument $order):void {
    $v=hosting_fulfillment_view($order->data()['id'],$order->data()['client_id']);$order=$v['order'];$agreement=$order->data()['agreement']['id'];
    echo '<section class="workspace-card" data-hosting-fulfillment><h2>Provider setup</h2>';
    if(!$v['ready']){
        echo '<p>Prepare the local approval and result history before requesting provider setup. This step preserves existing records and does not contact the provider.</p><form method="post">';service_orders_ui_fields('service_plans_order_prepare_fulfillment',$agreement,$order);
        service_plans_confirmation('Prepare hosting approval and recovery history.',true);echo '<button class="btn gold">Prepare setup history</button></form></section>';return;
    }
    if($v['dispatch']){
        echo '<p>Original attempt: <span class="hosting-service-reference">'.esc($v['dispatch']['id']).'</span></p><p>Approved '.esc(gmdate('Y-m-d H:i:s',$v['dispatch']['created_at'])).' UTC.</p>';
        if($v['dispatch']['package_id']!==null)echo '<p>Provider package number: '.esc($v['dispatch']['package_id']).'</p>';
    }
    if(!$v['profile_current'])echo '<p class="notice warning">The original provider connection changed. Restore and review that connection before setup or result recovery.</p>';
    if($v['mode']==='approve'){
        if(!$v['eligible']){echo '<p>Current client consent and verified payment or an active manual fulfillment policy are required before setup.</p></section>';return;}
        if(!$v['profile_current']){echo '</section>';return;}
        $action='service_plans_order_fulfill_service';$domain=$order->state()['domain_name'];$phrase='CREATE '.$domain;
        echo '<p>Request hosting for <strong>'.esc($domain).'</strong> using '.esc($v['package']['label']).' (type '.esc($v['package']['provider_type']).') in reseller account '.esc((string)$v['reseller_id']).'.</p>';
        echo '<p>This sends a setup request to 20i. Check this exact package type in My20i first: its defaults can buy paid options, send welcome email, install applications and grant SSH access. The review below records your checks; automatic package availability verification is not yet available.</p><form method="post">';service_orders_ui_fields($action,$agreement,$order);
        hosting_fulfillment_ui_hidden($action,['order_hash'=>$order->fingerprint(),'link_hash'=>$v['link_hash']]);
        foreach(['account_type'=>'I verified this account and package type are available for this order.','defaults'=>'I reviewed and approve the package defaults, including email, applications and access grants.','costs'=>'I reviewed and approve provider costs and any automatic paid options.'] as $key=>$label)echo '<label class="check"><input type="checkbox" name="review_'.esc($key).'" value="1" required> '.esc($label).'</label>';
        echo '<label>Type '.esc($phrase).' to confirm<input name="provision_confirmation" maxlength="270" autocomplete="off" value="'.esc(service_orders_ui_value($action,'provision_confirmation','',270)).'" required></label>';
        service_plans_confirmation('Send this exact hosting setup request. Acceptance will still require service confirmation.',true);echo '<button class="btn gold">Request hosting setup</button></form>';
    }elseif($v['mode']==='recover'){
        echo '<p>The original provider response is saved, but its local hosting result was not completed. Verify and record that saved response without sending another request.</p><p>Recorded response: '.esc(match($v['dispatch']['outcome']){'accepted_pending'=>'Accepted; service confirmation pending','failed'=>'Definite rejection',default=>'Outcome unknown'}).'.</p>';
        if($v['profile_current']){
            $action='service_plans_order_recover_fulfillment';echo '<form method="post">';service_orders_ui_fields($action,$agreement,$order);
            hosting_fulfillment_ui_hidden($action,['dispatch_id'=>$v['dispatch']['id'],'approval_hash'=>$v['dispatch']['approval_hash'],'provider_hash'=>$v['dispatch']['provider_hash']]);
            service_plans_confirmation('Verify and record only this original saved result. Do not send another provider request.',true);echo '<button class="btn gold">Recover saved result</button></form>';
        }
    }elseif($v['mode']==='failed'){
        echo '<p>The setup attempt failed. Its request and response history are retained.</p>';
        if($v['eligible']&&$v['profile_current']){
            $action='service_plans_order_retry_fulfillment';echo '<form method="post">';service_orders_ui_fields($action,$agreement,$order);hosting_fulfillment_ui_hidden($action,['hosting_revision'=>$v['hosting_revision']]);
            service_plans_confirmation('Prepare a new approval after this definite failure. This step sends no provider request.',true);echo '<button class="btn outline">Prepare another approval</button></form>';
        }
    }else{
        echo '<p>'.esc(match($v['mode']){'pending_confirmation'=>'20i accepted the request and assigned a package number. The service remains pending until an authenticated check confirms that exact package.','unknown'=>'The provider outcome is unknown. Keep this original attempt for investigation; another setup request is blocked.','unresolved'=>'The original attempt has no completed local result. Preserve its history and investigate the provider response before continuing. Another setup request is blocked.','active'=>'The hosting service has been confirmed. Its original setup history is retained.',default=>'The existing hosting lifecycle and original setup history are retained.'}).'</p>';
    }
    echo '</section>';
}
