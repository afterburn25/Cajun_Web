<?php
/** Closed owner form fields and rendering for imported hosting plan setup. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/plans/forms.php';
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog_document.php';

function hosting_catalog_setup_fields(): array {
    return ['product_id'=>10,'plan_id'=>32,'activation_event_id'=>32,'mapping_id'=>32,'review_sha256'=>64,
        'inclusions'=>33000,'exclusions'=>33000,'limits'=>33000,'support'=>1000,
        'reseller_id'=>10,'package_label'=>120,'package_region'=>80,'review_days'=>2,
        'limit_websites'=>10,'limit_disk_mb'=>10,'limit_bandwidth_mb'=>10,'limit_mailboxes'=>10,'limit_databases'=>10,
        'cost_known'=>1,'cost_amount'=>14,'cost_currency'=>3,'cost_period'=>3,'cost_tax'=>8,'cost_basis'=>14,'cost_reference'=>120];
}
/** Keep bounded original text; never truncate into a valid identity or retain secrets/consent. */
function hosting_catalog_setup_values(array $input): array {
    $values=[];
    foreach (hosting_catalog_setup_fields() as $key=>$max) {
        $value=$input[$key]??'';
        $values[$key]=is_string($value)&&strlen($value)<=$max&&preg_match('//u',$value)===1&&!str_contains($value,"\0")?$value:'';
    }
    return $values;
}
function hosting_catalog_setup_intent(array $input): array {
    service_plans_form_confirm($input);
    if (($input['reviewed_defaults']??null)!=='1') throw new InvalidArgumentException('Review the provider account, package defaults, limits and cost evidence.');
    foreach (['name','title','price','amount_cents','setup_cents','currency','cadence','term','type','package_type','profile_id','profile_revision'] as $key) {
        if (array_key_exists($key,$input)) throw new InvalidArgumentException('The product price and provider selection come from the saved draft. Reload this form.');
    }
    $ids=[];
    foreach (['plan_id','activation_event_id','mapping_id'] as $key) $ids[$key]=service_plans_form_id($input,$key);
    if (count(array_unique(array_values($ids)))!==3) throw new InvalidArgumentException('Reload and review the original plan setup.');
    $product=service_plans_form_number($input,'product_id',2147483647);service_plans_actor($product);
    $limits=[];
    foreach (service_plans_form_lines($input,'limits') as $line) {
        if (!preg_match('/\A([a-z][a-z0-9_]{0,63})\s*=\s*(0|[1-9][0-9]{0,9})\z/',$line,$match)) throw new InvalidArgumentException('Write each service limit as name = whole number, such as websites = 1.');
        if (array_key_exists($match[1],$limits)) throw new InvalidArgumentException('Give each service limit a unique name.');
        $limits[$match[1]]=service_plans_form_number(['cap'=>$match[2]],'cap',2147483647);
    }
    ksort($limits);
    $details=['inclusions'=>service_plans_form_lines($input,'inclusions'),'exclusions'=>service_plans_form_lines($input,'exclusions'),
        'limits'=>$limits,'support'=>service_plans_form_text($input,'support',1000,true)];
    $providerLimits=[];
    foreach (['websites','disk_mb','bandwidth_mb','mailboxes','databases'] as $key) {
        if (service_plans_form_text($input,'limit_'.$key,10)!=='') $providerLimits[$key]=service_plans_form_number($input,'limit_'.$key,2147483647);
    }
    if (!$providerLimits) throw new InvalidArgumentException('Record at least one known provider limit.');
    ksort($providerLimits);
    $costKnown=service_plans_form_text($input,'cost_known',1);
    if (!in_array($costKnown,['0','1'],true)) throw new InvalidArgumentException('Choose whether provider cost is known.');
    $cost=null;
    if ($costKnown==='1') $cost=provider_plan_catalog_cost(['amount'=>service_plans_form_text($input,'cost_amount',14),
        'currency'=>service_plans_form_text($input,'cost_currency',3),'period_months'=>service_plans_form_number($input,'cost_period',120),
        'tax'=>service_plans_form_text($input,'cost_tax',8),'basis'=>service_plans_form_text($input,'cost_basis',14),
        'reference'=>service_plans_form_text($input,'cost_reference',120,true)]);
    $reseller=service_plans_form_number($input,'reseller_id',2147483647);service_plans_actor($reseller);
    $configuration=['reseller_id'=>$reseller,'label'=>provider_plan_catalog_text(service_plans_form_text($input,'package_label',120,true),120),
        'region'=>provider_plan_catalog_text(service_plans_form_text($input,'package_region',80,true),80),'limits'=>$providerLimits,'cost'=>$cost];
    $days=service_plans_form_number($input,'review_days',30);
    if ($days<1) throw new InvalidArgumentException('Review the provider setup for 1 to 30 days.');
    return $ids+['product_id'=>$product,'review_sha256'=>service_plans_form_hash($input,'review_sha256'),'details'=>$details,'configuration'=>$configuration,'days'=>$days];
}
/** A fresh GET may start a review; a failed POST must keep every original identity. */
function hosting_catalog_setup_screen(?array $product,array $post): array {
    $retry=($post['action']??null)==='hosting_catalog_finish_plan';
    $values=hosting_catalog_setup_values($retry?$post:[]);
    $screen=['product'=>$product,'preview'=>null,'values'=>$values,'retry'=>$retry,'available'=>false,'notice'=>''];
    if ($product===null&&!$retry) return $screen;
    try {
        $productId=$retry?service_plans_form_number($post,'product_id',2147483647):(int)$product['id'];
        service_plans_actor($productId);
        $preview=hosting_catalog_setup_preview($productId);
        $screen['product']=$preview['product'];$screen['preview']=$preview;
        if ($retry) {
            $review=service_plans_form_hash($post,'review_sha256');
            if (!hash_equals($review,$preview['review_sha256'])) throw new RuntimeException('Stale review.');
            $ids=[];foreach (['plan_id','activation_event_id','mapping_id'] as $key) $ids[]=service_plans_form_id($post,$key);
            if (count(array_unique($ids))!==3) throw new RuntimeException('Invalid setup identity.');
        } else {
            $screen['values']=hosting_catalog_setup_values(['product_id'=>(string)$productId,'review_sha256'=>$preview['review_sha256'],
                'plan_id'=>bin2hex(random_bytes(16)),'activation_event_id'=>bin2hex(random_bytes(16)),'mapping_id'=>bin2hex(random_bytes(16)),
                'package_label'=>$preview['package']['label'],'review_days'=>'7','cost_known'=>'0','cost_tax'=>'unknown','cost_basis'=>'per-package']);
        }
        $screen['available']=true;
    } catch (InvalidArgumentException|RuntimeException|LogicException $e) {
        if ($e instanceof PDOException) throw $e;
        $screen['notice']=$retry?'These details changed or are unavailable. Your entries are kept below. Reload and review the product before trying again.':'Set a monthly or yearly price without a setup fee, then check service plans, provider mappings and the saved 20i catalog before finishing this offer.';
    }
    return $screen;
}
function hosting_catalog_setup_select(string $name,string $label,array $options,string $value): void {
    if (!array_key_exists($value,$options)) $options=[$value=>$value===''?'Choose an option':'Previously entered: '.$value]+$options;
    admin_select($name,$label,$options,$value);
}
/** Receives only the owner projection. Rendering does not read SQL, credentials or the provider. */
function hosting_catalog_setup_form(array $screen): void {
    if ($screen['product']===null&&!$screen['retry']) return;
    $v=$screen['values'];$product=$screen['product'];$preview=$screen['preview'];
    echo '<section class="workspace-card" id="finish-hosting-offer" data-hosting-plan-setup><h2>Finish hosting offer</h2><p>Create a matching service plan and save your reviewed 20i setup here. You will preview and publish the offer separately.</p>';
    if ($product!==null) {
        echo '<p><strong>'.esc($product['title']).'</strong> · '.esc($product['sku']).'</p>';
        if (is_int($product['amount_cents'])&&$product['amount_cents']>0&&in_array($product['term'],['month','year'],true)) echo '<p data-hosting-setup-price>$'.esc(gmoney($product['amount_cents'])).' '.esc($product['currency']).' per '.esc($product['term']).'. The service plan uses this saved name, price and billing period.</p>';
    }
    if ($preview!==null) echo '<p>Imported 20i package: <strong>'.esc($preview['package']['label']).'</strong> · Type '.esc($preview['package']['type']).'. Its provider connection and package type are fixed by this product.</p>';
    if ($screen['notice']!=='') echo '<p class="notice warning" role="alert">'.esc($screen['notice']).'</p>';
    echo '<div class="button-row"><a href="'.esc(admin_url('catalog')).'">Edit product and image</a><a href="'.esc(admin_url('provider-settings')).'">Review 20i connection</a><a href="'.esc(admin_url('service-plans')).'">Review service plans and mappings</a>';
    if ($product!==null) echo '<a href="'.esc(admin_url('hosting-offers',['product_id'=>$product['id']])).'#finish-hosting-offer">Reload product review</a>';
    echo '</div><form method="post" data-hosting-finish-plan>';admin_action('hosting_catalog_finish_plan');
    foreach (['product_id','plan_id','activation_event_id','mapping_id','review_sha256'] as $key) echo '<input type="hidden" name="'.esc($key).'" value="'.esc($v[$key]).'">';
    echo '<fieldset><legend>Terms clients will see</legend><p>Write one inclusion or exclusion per line. These terms will be shown with the plan comparison and client agreement.</p>';
    admin_textarea('inclusions','Included services',$v['inclusions'],4,33000);
    admin_textarea('exclusions','Excluded services',$v['exclusions'],3,33000);
    admin_textarea('limits','Service limits — one name = whole number per line',$v['limits'],3,33000);
    echo '<p class="hint">For example, websites = 1. These are your service promises to clients.</p>';
    admin_textarea('support','Support expectations',$v['support'],3,1000);
    echo '</fieldset><fieldset><legend>Review the 20i setup</legend><p>Check your reseller account, hosting location, package defaults, welcome mail, automatic apps, SSH keys and Website Turbo in My20i. These entries record your review; they do not create hosting.</p>';
    foreach (['reseller_id'=>['Reseller account number',10],'package_label'=>['Reviewed package name',120],'package_region'=>['Reviewed hosting location',80],'review_days'=>['Review expires after days (1–30)',2]] as $key=>[$label,$max]) admin_input($key,$label,$v[$key],'text','required maxlength="'.$max.'"');
    echo '<p>Enter at least one known provider limit. Blank means unknown; zero means zero.</p>';
    foreach (['websites'=>'Websites','disk_mb'=>'Disk space (MB)','bandwidth_mb'=>'Monthly transfer (MB)','mailboxes'=>'Mailboxes','databases'=>'Databases'] as $key=>$label) admin_input('limit_'.$key,$label,$v['limit_'.$key],'number','min="0" max="2147483647" step="1"');
    echo '</fieldset><details><summary>Optional private provider cost</summary><p>For your account review only. This never changes the client price or creates a charge.</p>';
    hosting_catalog_setup_select('cost_known','Cost availability',['0'=>'Not recorded','1'=>'Record reviewed cost'],$v['cost_known']);
    hosting_catalog_setup_select('cost_tax','Tax treatment',['unknown'=>'Unknown','included'=>'Included','excluded'=>'Excluded'],$v['cost_tax']);
    hosting_catalog_setup_select('cost_basis','Cost basis',['per-package'=>'Per hosting package','shared-account'=>'Shared reseller account cost'],$v['cost_basis']);
    foreach (['cost_amount'=>['Amount (example: 12.50)',14],'cost_currency'=>['Currency (example: GBP)',3],'cost_period'=>['Period in whole months',3],'cost_reference'=>['Account review reference',120]] as $key=>[$label,$max]) admin_input($key,$label,$v[$key],'text','maxlength="'.$max.'"');
    echo '</details><label class="check"><input type="checkbox" name="reviewed_defaults" value="1" required> I reviewed this provider account, package defaults, limits and cost evidence.</label>';
    admin_input('current_password','Your current password','','password','required autocomplete="current-password"');
    echo '<label class="check"><input type="checkbox" name="confirm" value="1" required> Create this matching service plan and save the reviewed hosting setup.</label><button class="btn gold"'.($screen['available']?'':' disabled').'>Create matching plan</button></form></section>';
}
