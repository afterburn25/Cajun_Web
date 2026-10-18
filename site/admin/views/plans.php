<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
echo '<div class="button-row"><a class="btn outline" href="'.esc(admin_url('service-plans')).'">All plans</a><a class="btn outline" href="'.esc(admin_url('service-agreements')).'">Client agreements</a><a class="btn gold" href="'.esc(admin_url('service-plan')).'">Create a plan</a></div><p>Offer an exact service scope and price. Client agreement is separate from automatic payment authorization.</p>';
if($view==='service-plans' || $view==='service-agreements') {
    $subscriptions=$view==='service-agreements';$page=service_plans_page(service_plans_ui_page(),$subscriptions);
    $mappingSummaries=$subscriptions?[]:provider_plan_catalog_summaries(array_map(fn($item)=>$item['document']->data()['id'],$page['items']));
    foreach($page['items'] as $item) {
        $doc=$item['document'];$model=$doc->model();$plan=$subscriptions?$model->plan:$model;
        echo '<section class="workspace-card"><h2>'.esc($plan->name).'</h2><p>'.esc($subscriptions?$item['client_name'].' · '.service_plans_agreement_label($doc):ucfirst($plan->status())).'</p><p>$'.esc(gmoney($plan->priceAmount)).' USD · '.esc(service_plans_cadences()[$plan->cadence]).'</p><a class="btn outline" href="'.esc(admin_url($subscriptions?'service-agreement':'service-plan',['record'=>$doc->data()['id']])).'">Review details</a>';
        if(!$subscriptions)provider_plan_catalog_readiness_ui($mappingSummaries[$doc->data()['id']],$doc->data()['id']);
        echo '</section>';
    }
    if(!$page['items'])echo '<div class="workspace-card"><p>No '.($subscriptions?'service agreements':'plans').' on this page.</p></div>';
    service_plans_ui_pagination($page,fn($p)=>admin_url($view,['p'=>$p]));return;
}
if($view==='service-agreement') {
    $id=service_plans_form_id($_GET,'record');$item=service_plans_owner_agreement($id);$doc=$item['document'];
    echo '<p><strong>'.esc($item['client']['name']).'</strong> · '.esc(service_plans_agreement_label($doc)).'</p>';
    if(!$item['client']['active'])echo '<div class="notice warning">This client account is inactive. Existing agreements can still be ended or paused; activation and resumption require an active client account.</div>';
    service_plans_terms_view(ServicePlanDocument::fromJson(service_plans_json($doc->data()['plan'])));
    require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements.php';require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements_views.php';
    $renewalContext=domain_renewal_agreements_context($id,(int)$doc->data()['client_id'],false);if($renewalContext!==null)domain_renewal_agreements_card($renewalContext);
    service_plans_agreement_controls($doc,true,$item['client']['active'],$renewalContext);service_plans_agreement_history($doc);if($renewalContext===null)service_orders_ui($doc,true);return;
}
$id=service_plans_form_text($_GET,'record',32);$doc=$id===''?null:service_plans_get($id);$model=$doc?->model();
if(!$doc || $model->status()==='draft' || ($_POST['action']??null)==='service_plans_edit') {
    $action=$doc?'service_plans_edit':'service_plans_create';
    if(($_POST['action']??null)===$action)$values=service_plans_form_values($_POST);
    elseif($model)$values=['name'=>$model->name,'cadence'=>$model->cadence,'price'=>gmoney($model->priceAmount),'inclusions'=>implode("\n",$model->inclusions),'exclusions'=>implode("\n",$model->exclusions),'limits'=>implode("\n",array_map(fn($k,$v)=>$k.' = '.$v,array_keys($model->limits),$model->limits)),'support'=>$model->supportExpectation];
    else $values=[];
    echo '<section class="workspace-card"><h2>'.($doc?'Edit draft terms':'Create draft terms').'</h2>';
    if($error!=='' && ($_POST['action']??null)===$action)echo '<p class="notice warning">Your unsaved text is preserved below. If the saved version changed, reload and review it before submitting another change.</p>';
    if($doc && $model->status()!=='draft')echo '<p class="notice warning">This saved plan is no longer a draft. Copy your unsaved text into a new plan to propose different terms.</p>';
    echo '<form method="post">';
    if($doc)service_plans_intent_fields($action,service_plans_ui_intent($doc,$action),csrf_field());
    else {$newId=($_POST['action']??null)===$action?($values['record_id']??''):bin2hex(random_bytes(16));admin_action($action);echo '<input type="hidden" name="record_id" value="'.esc($newId).'">';}
    service_plans_terms_fields($values);admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold"'.($doc && $model->status()!=='draft'?' disabled':'').'>Save draft</button></form></section>';
}
if(!$doc)return;
service_plans_terms_view($doc);echo '<p>Saved version '.(int)$doc->revision().' · '.esc(ucfirst($model->status())).'</p>';
if(in_array($model->status(),['draft','active'],true)) {
    $action=$model->status()==='draft'?'service_plans_publish':'service_plans_retire';$label=$model->status()==='draft'?'Publish these terms':'Retire this plan';
    echo '<section class="workspace-card"><h2>'.esc($label).'</h2><p>'.($model->status()==='draft'?'Published terms cannot be edited. Create a new plan for future price or scope changes.':'Retirement prevents new offers and preserves existing client agreements.').'</p><form method="post">';service_plans_intent_fields($action,service_plans_ui_intent($doc,$action),csrf_field());service_plans_confirmation('I have reviewed and confirm this saved plan version.',true);echo '<button class="btn gold">'.esc($label).'</button></form></section>';
}
if($model->subscribable()) {
    $search=service_plans_form_text($_GET,'client_search',190,true);$choices=service_plans_client_choices($search);
    echo '<section class="workspace-card"><h2>Offer these terms to a client</h2><form method="get"><input type="hidden" name="view" value="service-plan"><input type="hidden" name="record" value="'.esc($id).'">';admin_input('client_search','Find an active client by name or email',$search,'search','maxlength="190"');echo '<button class="btn outline">Find clients</button></form>';
    if($choices['more'])echo '<p class="hint">Showing the first 25 matches. Narrow the name or email to find another client.</p>';
    if(!$choices['items'])echo '<p>No active client matches. Add or activate a client in Client workspace first.</p>';
    else {
        $action='service_plans_offer';$values=service_plans_form_values($_POST);$hash=($_POST['action']??null)===$action?$values['plan_hash']:$doc->fingerprint();
        echo '<form method="post">';service_plans_intent_fields($action,service_plans_ui_intent($doc,$action),csrf_field());echo '<input type="hidden" name="plan_hash" value="'.esc($hash).'"><label>Client<select name="client_id" required><option value="">Choose the client</option>';
        foreach($choices['items'] as $c)echo '<option value="'.(int)$c['id'].'"'.(($values['client_id']??'')===(string)$c['id']?' selected':'').'>'.esc($c['name'].' · '.$c['email']).'</option>';
        echo '</select></label>';service_plans_confirmation('I confirm this client and the exact saved terms shown above.',true);echo '<button class="btn gold">Offer service agreement</button></form>';
    }
    echo '</section>';
}
echo '<section class="workspace-card"><h2>Plan history</h2><p>Created '.esc(gmdate('Y-m-d H:i:s',$doc->data()['created_at'])).' UTC.</p><ol>';
foreach($doc->data()['events'] as $event)echo '<li>'.esc(match($event['command']){'replace_draft'=>'Draft terms updated','activate'=>'Terms published','retire'=>'Plan retired'}).' · owner '.(int)$event['actor'].' · '.esc(gmdate('Y-m-d H:i:s',$event['at'])).' UTC</li>';
echo '</ol></section>';

provider_plan_catalog_ui($doc);
