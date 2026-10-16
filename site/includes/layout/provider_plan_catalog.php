<?php
/** First-party owner catalog configuration and selected-plan preparation. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog.php';
require_once CWP_CODE_ROOT.'/app/plans/forms.php';

function provider_plan_catalog_form(string $action,string $plan):void {
    service_plans_form_confirm($_POST);$password=service_plans_form_text($_POST,'current_password',200);
    if(service_plans_form_id($_POST,'plan_id')!==$plan)throw new InvalidArgumentException('Reload the selected retail plan.');
    if($action==='service_plans_catalog_setup'){provider_plan_catalog_prepare($password);return;}
    if($action!=='service_plans_catalog_save')throw new InvalidArgumentException('Unknown provider catalog action.');
    if(($_POST['reviewed_defaults']??null)!=='1')throw new InvalidArgumentException('Review the provider account, package defaults and cost evidence.');
    $limits=[];foreach(['websites','disk_mb','bandwidth_mb','mailboxes','databases'] as $key)if(service_plans_form_text($_POST,'limit_'.$key,10)!=='')$limits[$key]=service_plans_form_number($_POST,'limit_'.$key,2147483647);
    $cost=null;$costKnown=service_plans_form_text($_POST,'cost_known',1);
    if(!in_array($costKnown,['0','1'],true))throw new InvalidArgumentException('Choose whether provider cost is known.');
    if($costKnown==='1')$cost=['amount'=>service_plans_form_text($_POST,'cost_amount',14),'currency'=>service_plans_form_text($_POST,'cost_currency',3),'period_months'=>service_plans_form_number($_POST,'cost_period',120),'tax'=>service_plans_form_text($_POST,'cost_tax',8),'basis'=>service_plans_form_text($_POST,'cost_basis',14),'reference'=>service_plans_form_text($_POST,'cost_reference',120,true)];
    $configuration=['reseller_id'=>service_plans_form_number($_POST,'reseller_id',2147483647),'type'=>service_plans_form_text($_POST,'package_type',18),'label'=>service_plans_form_text($_POST,'package_label',120,true),'region'=>service_plans_form_text($_POST,'package_region',80,true),'limits'=>$limits,'cost'=>$cost];
    $previous=service_plans_form_text($_POST,'previous_id',32);if($previous!=='')service_plans_id($previous);
    provider_plan_catalog_save(service_plans_form_id($_POST,'catalog_id'),$plan,service_plans_form_number($_POST,'plan_revision',256),service_plans_form_hash($_POST,'plan_hash'),$previous?:null,service_plans_form_id($_POST,'profile_id'),service_plans_form_number($_POST,'profile_revision',2147483647),$configuration,service_plans_form_number($_POST,'review_days',30),($_POST['catalog_enabled']??null)==='1',$password);
}
function provider_plan_catalog_value(string $key,string $fallback='',int $max=200):string {
    if(($_POST['action']??null)!=='service_plans_catalog_save')return $fallback;
    // Rendering a rejected POST must not rethrow the original parse failure.
    $value=$_POST[$key]??'';if(!is_string($value)||preg_match('//u',$value)!==1)return '';
    $value=substr(str_replace("\0",'',$value),0,$max);
    while($value!==''&&preg_match('//u',$value)!==1)$value=substr($value,0,-1);
    return $value;
}
function provider_plan_catalog_readiness_ui(array $status,string $plan,bool $detail=false):void {
    echo '<div data-catalog-readiness="'.esc($status['code']).'"><p><strong>20i mapping: '.esc($status['code']==='ready'?'ready for local preparation':'review required').'</strong></p><p>'.esc($status['message']).'</p>';
    if(in_array($status['code'],['connection_setup','connection_missing','connection_changed','connection_disabled','credentials_missing','credentials_changed'],true))echo '<p><a href="'.esc(admin_url('provider-settings')).'">Review provider connection</a></p>';
    if(!$detail)echo '<p><a href="'.esc(admin_url('service-plan',['record'=>$plan])).'#provider-catalog">Review provider mapping</a></p>';
    echo '</div>';
}
/** Already authorized and validated immutable display projection; never a form or current grant. */
function provider_plan_catalog_review_ui(array $ctx,string $plan):void {
    if(!$ctx['selected'])return;
    $review=$ctx['selected'];$configuration=$review['configuration'];
    echo '<section class="workspace-card" data-catalog-review="'.(int)$review['revision'].'"><h3>Mapping review version '.(int)$review['revision'].'</h3><p>'.($review['current']?'Latest saved review. Its current readiness is shown above.':'Historical review. This does not replace the latest mapping or authorize new preparation.').'</p><p>Saved by owner account '.(int)$review['created_by'].' on '.esc(gmdate('Y-m-d H:i',$review['created_at'])).' UTC · reviewed through '.esc(gmdate('Y-m-d H:i',$review['expires_at'])).' UTC · retail plan version '.(int)$review['plan_revision'].'.</p><p>Saved choice: '.($review['enabled']?'available for new preparation':'withdrawn from new preparation').'.</p>';
    echo '<p><strong>'.esc($configuration['label']).'</strong> · reseller '.(int)$configuration['reseller_id'].' · type '.esc($configuration['type']).' · '.esc($configuration['region']).'</p><h4>Provider limits recorded in this review</h4><ul>';
    foreach(['websites'=>'Websites','disk_mb'=>'Disk space (MB)','bandwidth_mb'=>'Monthly transfer (MB)','mailboxes'=>'Mailboxes','databases'=>'Databases'] as $key=>$label)echo '<li>'.esc($label).': '.esc(array_key_exists($key,$configuration['limits'])?(string)$configuration['limits'][$key]:'not recorded').'</li>';
    $cost=$configuration['cost'];echo '</ul><h4>Private provider cost evidence</h4><p>'.($cost?esc($cost['amount'].' '.$cost['currency'].' per '.$cost['period_months'].' month(s), '.$cost['basis'].', tax '.$cost['tax'].' — '.$cost['reference']):'Not recorded').'. This is owner-entered evidence, not a provider quote or the client’s retail price.</p></section>';
    echo '<details data-catalog-history><summary>Choose a mapping review</summary><ol>';
    foreach(array_reverse($ctx['history']) as $entry)echo '<li><a href="'.esc(admin_url('service-plan',['record'=>$plan,'catalog_revision'=>$entry['revision']])).'#provider-catalog">Version '.(int)$entry['revision'].'</a> · '.esc($entry['enabled']?'enabled':'withdrawn').' · '.esc(gmdate('Y-m-d H:i',$entry['created_at'])).' UTC · '.esc($entry['configuration']['label']).'</li>';
    echo '</ol></details>';
}
function provider_plan_catalog_ui(ServicePlanDocument $plan):void {
    $revision=array_key_exists('catalog_revision',$_GET)?service_plans_form_number($_GET,'catalog_revision',128):null;
    $ctx=provider_plan_catalog_context($plan->data()['id'],$revision);$profile=$ctx['profile'];$current=$ctx['current'];
    echo '<section class="workspace-card" id="provider-catalog" data-provider-plan-catalog><h2>20i package mapping</h2><p>Reuse the provider package reviewed for this exact retail plan. Provider costs are private reference evidence; they never replace the client’s price or create a charge.</p>';
    provider_plan_catalog_readiness_ui($ctx['readiness'],$plan->data()['id'],true);
    if($ctx['readiness']['code']==='connection_setup'){echo '</section>';return;}
    if(!$ctx['ready']){echo '<form method="post">';admin_action('service_plans_catalog_setup');echo '<input type="hidden" name="plan_id" value="'.esc($plan->data()['id']).'">';service_plans_confirmation('Prepare empty provider mapping history.',true);echo '<button class="btn gold">Prepare provider mappings</button></form></section>';return;}
    if($current){
        echo '<p>Latest mapping version '.(int)$current['revision'].' · reviewed through '.esc(gmdate('Y-m-d H:i',$current['expires_at'])).' UTC.</p>';
    }
    provider_plan_catalog_review_ui($ctx,$plan->data()['id']);
    if($plan->model()->status()!=='active'||!$profile||(int)$profile['enabled']!==1||!$profile['has_credentials']){echo '<p>A published retail plan and enabled provider connection are required to save a new review.</p></section>';return;}
    $config=$current['configuration']??[];$cost=$config['cost']??[];
    echo '<p>Check the package type, location, limits, welcome mail, automatic apps, SSH keys and Website Turbo in My20i. This saved owner review does not automatically discover offerings or verify provider entitlement.</p><form method="post">';admin_action('service_plans_catalog_save');
    foreach(['plan_id'=>$plan->data()['id'],'plan_revision'=>(string)$plan->revision(),'plan_hash'=>$plan->fingerprint(),'catalog_id'=>bin2hex(random_bytes(16)),'previous_id'=>$current['id']??'','profile_id'=>$profile['id'],'profile_revision'=>(string)$profile['revision']] as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc(provider_plan_catalog_value($key,$value)).'">';
    foreach(['reseller_id'=>['Reseller account number',(string)($config['reseller_id']??''),10],'package_type'=>['Provider package type number',$config['type']??'',18],'package_label'=>['Reviewed package name',$config['label']??'',120],'package_region'=>['Reviewed hosting location',$config['region']??'',80],'review_days'=>['Review expires after days (1–30)','7',2]] as $key=>[$label,$value,$max]){admin_input($key,$label,provider_plan_catalog_value($key,$value,$max),'text','required maxlength="'.$max.'"');}
    echo '<fieldset><legend>Known provider limits</legend><p>Enter at least one. Blank is unknown; zero means zero.</p>';
    foreach(['websites'=>'Websites','disk_mb'=>'Disk space (MB)','bandwidth_mb'=>'Monthly transfer (MB)','mailboxes'=>'Mailboxes','databases'=>'Databases'] as $key=>$label)admin_input('limit_'.$key,$label,provider_plan_catalog_value('limit_'.$key,(string)($config['limits'][$key]??''),10),'number','min="0" max="2147483647" step="1"');
    echo '</fieldset><fieldset><legend>Private provider cost evidence</legend>';
    foreach(['cost_known'=>['Cost availability',['0'=>'Not recorded','1'=>'Record reviewed cost'],$cost?'1':'0'],'cost_tax'=>['Tax treatment',['unknown'=>'Unknown','included'=>'Included','excluded'=>'Excluded'],$cost['tax']??'unknown'],'cost_basis'=>['Cost basis',['per-package'=>'Per hosting package','shared-account'=>'Shared reseller account cost'],$cost['basis']??'per-package']] as $key=>[$label,$options,$fallback]){echo '<label>'.esc($label).'<select name="'.esc($key).'">';foreach($options as $value=>$text)echo '<option value="'.esc((string)$value).'"'.(provider_plan_catalog_value($key,$fallback)===(string)$value?' selected':'').'>'.esc($text).'</option>';echo '</select></label>';}
    foreach(['cost_amount'=>['Amount in major currency units (example: 12.50)',$cost['amount']??'',14],'cost_currency'=>['Three-letter currency (example: GBP)',$cost['currency']??'',3],'cost_period'=>['Period in whole months',$cost?(string)$cost['period_months']:'',3],'cost_reference'=>['Reference for your account review',$cost['reference']??'',120]] as $key=>[$label,$value,$max])admin_input($key,$label,provider_plan_catalog_value($key,$value,$max),'text','maxlength="'.$max.'"');
    echo '</fieldset><label class="check"><input type="checkbox" name="catalog_enabled" value="1"'.(provider_plan_catalog_value('catalog_enabled',$current&& !$current['enabled']?'':'1')==='1'?' checked':'').'> Make this review available for new hosting preparation</label><label class="check"><input type="checkbox" name="reviewed_defaults" value="1" required> I reviewed this provider account, package defaults, limits and cost evidence.</label>';service_plans_confirmation('Save this exact mapping version; preserve all previous reviews.',true);echo '<button class="btn gold">Save provider mapping review</button></form>';
    echo '</section>';
}
function provider_plan_catalog_order_ui(ServiceOrderDocument $order):void {
    $ctx=recurring_billing_locked(function()use($order):?array {
        service_orders_owner();if(!provider_plan_catalog_state()['ready'])return null;$o=$order->data();$a=$o['agreement'];$source=service_orders_agreement($a['id'],$o['client_id'],$a['revision'],$a['sha256'],$a['terms_hash'])['prefix'];$plan=$source->data()['plan']['id'];$mapping=provider_plan_catalog_current($plan);
        if(!$mapping)return null;try{provider_plan_catalog_order($mapping,$order,true);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;return ['plan'=>$plan,'mapping'=>null];}return ['plan'=>$plan,'mapping'=>$mapping];
    });
    if(!$ctx)return;
    if(!$ctx['mapping']){echo '<p>The saved provider mapping needs review. <a href="'.esc(admin_url('service-plan',['record'=>$ctx['plan']])).'">Review this plan’s mapping</a>.</p>';return;}
    $mapping=$ctx['mapping'];echo '<form method="post" data-catalog-preparation><h3>Use the saved provider mapping</h3><p>'.esc($mapping['configuration']['label']).' · Type '.esc($mapping['configuration']['type']).' · '.esc($mapping['configuration']['region']).'</p>';service_orders_ui_fields('service_plans_order_map_catalog',$order->data()['agreement']['id'],$order);
    foreach(['order_hash'=>$order->fingerprint(),'account_id'=>bin2hex(random_bytes(16)),'catalog_id'=>$mapping['id'],'catalog_hash'=>$mapping['sha256']] as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc(service_orders_ui_value('service_plans_order_map_catalog',$key,$value,64)).'">';
    echo '<p>This saves the reviewed package as a local service record. Provider setup still requires its separate current review and authorization.</p>';service_plans_confirmation('Use this exact saved mapping for the client’s accepted domain and agreed retail plan.',true);echo '<button class="btn gold">Prepare from saved mapping</button></form>';
}
