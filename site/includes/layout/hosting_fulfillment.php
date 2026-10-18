<?php
/** Native owner approval and recovery cards on the existing service agreement page. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment_views.php';

function hosting_fulfillment_ui_hidden(string $action,array $fields):void {
    foreach($fields as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc(service_orders_ui_value($action,$key,(string)$value,64)).'">';
}
/** Saved owner-only assignment status; this card never grants access or submits a write. */
function hosting_fulfillment_ui_assignment(?array $assignment):void {
    if($assignment===null)return;
    $message=match($assignment['state']){
        'linked'=>'This package is linked to the expected client under the saved provider settings.',
        'unlinked'=>'Not linked. Refresh the inventory, review ownership, then select the expected client.',
        'different_client'=>'Linked to a different client. Review the assignment before changing it.',
        'connection_changed'=>'The provider connection changed. Refresh the inventory and review ownership.',
        'refresh_required'=>'No saved inventory under the current settings. Refresh to review this package.',
        'not_observed'=>'Not found in the saved inventory. Refresh to review; absence does not prove removal.',
        'stale'=>'This link needs re-approval. Refresh the inventory and review ownership.',
        'client_unavailable'=>'The expected client is unavailable or inactive. Review the client account.',
        default=>'Inventory storage is unavailable. Review the provider page.',
    };
    echo '<div data-hosting-client-handoff><h3>Client hosting access</h3><p>Expected client: <strong>'.esc($assignment['client_name']).'</strong> (#'.(int)$assignment['client_id'].'). Package #'.esc($assignment['package_id']).'.</p><p>'.esc($message).'</p>';
    if($assignment['observed_at']!==null)echo '<p class="hint">Last saved inventory: '.esc($assignment['observed_at']).' UTC. Saved observation only.</p>';
    echo '<a class="btn outline" href="'.esc($assignment['url']).'">Review client access</a></div>';
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
        if($v['dispatch']['confirmation']){
            $check=$v['dispatch']['confirmation'];$message=match($check['reason']){'confirmed'=>'Exact package confirmed enabled','not_observed'=>'Assigned package not observed; setup remains pending','not_enabled'=>'Assigned package is not enabled; setup remains pending','details_differ'=>'Observed package details differ; owner review is required',default=>'Status check unavailable; setup remains pending'};
            echo '<p class="notice info">'.esc($message).' — '.esc(gmdate('Y-m-d H:i:s',$check['at'])).' UTC.</p>';
        }
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
    }elseif($v['mode']==='pending_confirmation'){
        echo '<p>20i accepted the request. A status check must confirm this exact package number, enabled status, accepted domain and package type before the service is marked active. This checks provider status; it does not test website content, DNS propagation or SSL.</p>';
        if($v['profile_current']){
            $action=$v['confirmation_ready']?'service_plans_order_confirm_fulfillment':'service_plans_order_prepare_confirmation';
            echo '<form method="post">';service_orders_ui_fields($action,$agreement,$order);
            if($v['confirmation_ready'])hosting_fulfillment_ui_hidden($action,['dispatch_id'=>$v['dispatch']['id'],'approval_hash'=>$v['dispatch']['approval_hash']]);
            service_plans_confirmation($v['confirmation_ready']?'Read current provider status for this original setup request. Do not send another purchase.':'Prepare local provider status history. This setup step does not contact the provider.',true);
            echo '<button class="btn gold">'.($v['confirmation_ready']?'Check hosting status':'Prepare status checks').'</button></form>';
        }
    }elseif($v['mode']==='failed'){
        echo '<p>The setup attempt failed. Its request and response history are retained.</p>';
        if($v['eligible']&&$v['profile_current']){
            $action='service_plans_order_retry_fulfillment';echo '<form method="post">';service_orders_ui_fields($action,$agreement,$order);hosting_fulfillment_ui_hidden($action,['hosting_revision'=>$v['hosting_revision']]);
            service_plans_confirmation('Prepare a new approval after this definite failure. This step sends no provider request.',true);echo '<button class="btn outline">Prepare another approval</button></form>';
        }
    }else{
        echo '<p>'.esc(match($v['mode']){'pending_confirmation'=>'20i accepted the request and assigned a package number. The service remains pending until an authenticated check confirms that exact package.','unknown'=>'The provider outcome is unknown. Keep this original attempt for investigation; another setup request is blocked.','unresolved'=>'The original attempt has no completed local result. Preserve its history and investigate the provider response before continuing. Another setup request is blocked.','active'=>'The original package was observed enabled with the accepted domain and type. Its confirmation and setup history are retained.',default=>'The existing hosting lifecycle and original setup history are retained.'}).'</p>';
    }
    hosting_fulfillment_ui_assignment($v['assignment']??null);
    echo '</section>';
}
