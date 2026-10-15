<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
if(isset($_GET['agreement'])) {
    $id=service_plans_form_id($_GET,'agreement');$doc=service_plans_subscription_get($id,(int)$client['id'],true);
    echo '<p class="workspace-status">'.esc(service_plans_agreement_label($doc)).'</p>';
    service_plans_terms_view(ServicePlanDocument::fromJson(service_plans_json($doc->data()['plan'])));
    service_plans_agreement_controls($doc,false);service_plans_agreement_history($doc);service_orders_ui($doc,false);return;
}
$page=service_plans_page(service_plans_ui_page(),true,(int)$client['id']);
foreach($page['items'] as $item){
    $doc=$item['document'];$plan=$doc->model()->plan;
    echo '<section class="workspace-card"><h2>'.esc($plan->name).'</h2><p class="workspace-status">'.esc(service_plans_agreement_label($doc)).'</p><p>$'.esc(gmoney($plan->priceAmount)).' USD · '.esc(service_plans_cadences()[$plan->cadence]).'</p><a class="btn gold" href="'.esc(studio_url(['agreement'=>$doc->data()['id']])).'">Review agreement</a></section>';
}
if(!$page['items'])echo '<section class="workspace-card"><h2>No agreements on this page</h2><p>The studio will place a service offer here when it is ready for your review.</p></section>';
service_plans_ui_pagination($page,fn($p)=>studio_url(['agreements'=>1,'p'=>$p]));
