<?php
/** Escaped first-party initial hosting order cards on existing agreement routes. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/order_views.php';
function service_orders_ui_value(string $action,string $key,string $fallback='',int $limit=500):string {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||($_POST['action']??null)!==$action)return $fallback;
    $v=$_POST[$key]??'';return is_string($v)&&strlen($v)<=$limit&&preg_match('//u',$v)&&!str_contains($v,"\0")?$v:'';
}
function service_orders_ui_fields(string $action,string $agreement,?ServiceOrderDocument $doc=null):void {
    echo csrf_field().'<input type="hidden" name="action" value="'.esc($action).'"><input type="hidden" name="agreement_id" value="'.esc($agreement).'">';
    if($doc)foreach(['order_id'=>$doc->data()['id'],'order_revision'=>(string)$doc->revision(),'event_id'=>bin2hex(random_bytes(16))] as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc(service_orders_ui_value($action,$key,$value,64)).'">';
}
function service_orders_ui(ServiceSubscriptionDocument $agreement,bool $owner):void {
    $source=$agreement->data();$id=$source['id'];$client=$source['client_id'];$prefix=$owner?'service_plans_order_':'service_agreements_order_';
    if(!service_orders_ready()){
        if(!$owner)return;
        echo '<section class="workspace-card"><h2>Hosting orders</h2><p>Connect this client’s accepted service agreement to its initial invoice and hosting domain.</p><form method="post">';
        service_orders_ui_fields($prefix.'setup',$id);service_plans_confirmation('Prepare hosting order history while preserving existing agreements and invoices.',true);
        echo '<button class="btn gold">Prepare hosting orders</button></form></section>';return;
    }
    $ctx=service_orders_context($id,$client,!$owner);$doc=$ctx['document'];
    if(!$doc){
        if(!$owner)return;
        echo '<section class="workspace-card"><h2>Offer initial shared hosting</h2><p>Select the existing invoice that covers this agreement’s initial hosting service. The client will review the invoice and confirm the hosting domain.</p>';
        if(!$ctx['choices'])echo '<p>Activate the client’s accepted agreement and prepare its matching invoice. Recurring hosting uses period zero of an accepted, active billing schedule. A previously used invoice cannot fund another initial order.</p>';
        else {
            $action=$prefix.'offer';echo '<form method="post">';service_orders_ui_fields($action,$id);
            foreach(['order_id'=>bin2hex(random_bytes(16)),'source_revision'=>(string)$agreement->revision(),'source_hash'=>$agreement->fingerprint()] as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc(service_orders_ui_value($action,$key,$value,64)).'">';
            echo '<label>Initial hosting invoice<select name="invoice_choice" required><option value="">Choose the hosting invoice</option>';
            foreach($ctx['choices'] as $i){$value=$i['id'].':'.$i['sha256'];echo '<option value="'.esc($value).'"'.(service_orders_ui_value($action,'invoice_choice','',80)===$value?' selected':'').'>'.esc($i['reference'].' · '.$i['title'].' · $'.gmoney($i['amount_cents']).' USD').'</option>';}
            echo '</select></label>';service_plans_confirmation('This invoice is for this client’s initial shared-hosting service under the displayed agreement.',true);echo '<button class="btn gold">Offer hosting order</button></form>';
        }
        if($ctx['more'])echo '<p>Only the 50 newest matching invoices were considered. Review the invoice history before proceeding.</p>';
        echo '</section>';return;
    }
    $d=$doc->data();$state=$doc->state();$phase=$state['phase'];$invoice=$d['invoice'];
    $label=match($phase){'offered'=>'Awaiting your hosting confirmation','accepted'=>'Hosting request accepted','cancelled'=>'Hosting request cancelled','declined'=>'Hosting offer declined',default=>'Hosting offer withdrawn'};
    echo '<section class="workspace-card" data-service-order><h2>Initial shared hosting</h2><p class="workspace-status">'.esc($label).'</p><p>Invoice #'.(int)$invoice['id'].' · $'.esc(gmoney($invoice['amount_cents'])).' '.esc($invoice['currency']).'</p>';
    $url=$owner?admin_url('payments',['invoice'=>$invoice['id']]):studio_url(['invoice'=>$invoice['id']]);
    echo '<a class="btn outline" href="'.esc($url).'">Review invoice</a>';
    if($state['domain_name'])echo '<p><strong>Hosting domain:</strong> '.esc($state['domain_name']).'</p>';
    if($ctx['warning'])echo '<p class="notice warning">'.esc($ctx['warning']).'</p>';
    if($phase==='accepted'){
        echo '<p>Setup is pending studio fulfillment. A hosting request is separate from an active hosting service.</p>';
        if($owner&&$ctx['assessment']){
            $a=$ctx['assessment'];echo '<p>'.($a['eligible']?'Commercial review is ready. Provider mapping and setup authorization must still be verified.':'Commercial review needs attention: '.esc(implode(', ',array_map(fn($s)=>str_replace('-',' ',$s),$a['blockers'])))).'</p>';
            if($a['manual'])echo '<p>Manual fulfillment policy expires '.esc(gmdate('Y-m-d H:i:s',$a['manual']['expires_at'])).' UTC. Reason: '.esc($a['manual']['reason']).'</p>';
        }
    }
    echo '<p class="hint">This records an initial hosting request. It does not register the domain, authorize automatic payment, or delete existing services. Ending the request preserves invoices and history.</p></section>';
    $commands=match($phase){'offered'=>$owner?['withdraw'=>'Withdraw hosting offer']:['accept'=>'Confirm hosting request','decline'=>'Decline hosting offer'],'accepted'=>$owner?['allow_manual'=>'Permit manual fulfillment','revoke_manual'=>'Revoke manual permission','cancel'=>'Cancel hosting request']:['cancel'=>'Cancel hosting request'],default=>[]};
    if($state['manual']===null)unset($commands['revoke_manual']);
    foreach($commands as $command=>$label){
        $action=$prefix.$command;echo '<section class="workspace-card"><h3>'.esc($label).'</h3><form method="post">';service_orders_ui_fields($action,$id,$doc);
        if($command==='accept'){
            echo '<input type="hidden" name="review_sha256" value="'.esc(service_orders_ui_value($action,'review_sha256',$doc->fingerprint(),64)).'"><label>Hosting domain<input name="domain_name" maxlength="254" value="'.esc(service_orders_ui_value($action,'domain_name','',254)).'" placeholder="example.com" required></label><p>Enter the full domain you want to host. Confirm the service terms and linked invoice above.</p>';
        }else{
            echo '<label>Reason<textarea name="reason" maxlength="500" rows="3" required>'.esc(service_orders_ui_value($action,'reason')).'</textarea></label>';
            if($command==='allow_manual'){
                $expires=service_orders_ui_value($action,'expires_at',(string)(time()+3600),10);
                echo '<input type="hidden" name="expires_at" value="'.esc($expires).'"><p>Allow this specific order to proceed without complete verified receipt evidence for up to one hour from this form’s preparation. This does not mark the invoice paid or permit dispatch while a payment outcome is unresolved.</p>';
            }
        }
        service_plans_confirmation($command==='accept'?'I confirm this hosting domain, service agreement and initial invoice.':'I have reviewed and confirm this hosting order decision.',$owner);
        echo '<button class="btn gold">'.esc($label).'</button></form></section>';
    }
    echo '<section class="workspace-card"><h3>Hosting request history</h3><ol>';
    foreach($d['events'] as $event)echo '<li>'.esc(match($event['command']){'accept'=>'Client confirmed hosting','decline'=>'Client declined hosting','withdraw'=>'Studio withdrew hosting offer','cancel'=>'Hosting request cancelled','allow_manual'=>'Studio approved a temporary fulfillment policy',default=>'Studio revoked its fulfillment policy'}).' · '.esc(gmdate('Y-m-d H:i:s',$event['at'])).' UTC</li>';
    echo '</ol></section>';
}
