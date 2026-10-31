<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Errors retain original nonsecret identity and choices, never a freshly substituted review. */
function provider_catalog_variant_form(array $data, bool $available): void {
    $retry=($_POST['action']??null)==='catalog_import_variant_create';
    $product=$data['selected_product']??[];
    $fresh=['variant_request_id'=>$available?bin2hex(random_bytes(16)):'','source_product_id'=>(string)($product['id']??''),'source_revision'=>(string)($product['revision']??''),'variant_review_sha256'=>(string)($data['review_sha256']??''),'sku'=>'','term'=>''];
    $values=$fresh;
    if($retry)foreach($fresh as $key=>$unused)$values[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
    $target=$product['id']??null;
    $action=is_int($target)&&$target>0?admin_url('catalog',['id'=>$target]):admin_url('catalog');
    ?><form method="post" action="<?=esc($action)?>" data-catalog-variant-create><?php
    admin_action('catalog_import_variant_create');
    foreach(['variant_request_id','source_product_id','source_revision','variant_review_sha256'] as $field)studio_hidden($field,$values[$field]);
    ?><div class="workspace-grid"><div><?php admin_input('sku','New product code (SKU)',$values['sku'],'text','required maxlength="60" autocomplete="off"');?></div><div><?php
    $periods=[''=>'Choose a period','month'=>'One month of service','year'=>'One year of service'];
    if(!array_key_exists($values['term'],$periods))$periods[$values['term']]='Previously entered: '.$values['term'];
    admin_select('term','Service period',$periods,$values['term']);
    ?></div></div><p>The new draft starts without a price, image or service terms. Review those details before publishing its hosting offer.</p><?php
    admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');
    admin_check('confirm','Create this billing option as a draft for review.',false);
    ?><button class="btn gold"<?= $available?'':' disabled' ?>>Create billing option</button></form><?php
}

function provider_catalog_variant_admin_view(array $product,array $user): void {
    if(!staff_can('staff.manage',$user)||support_current()!==null||array_key_exists('support',$_GET))return;
    $retry=($_POST['action']??null)==='catalog_import_variant_create';
    try {
        provider_catalog_variant_owner();
        if(!provider_catalog_import_record((int)$product['id']))return;
        $data=provider_catalog_variant_preview((int)$product['id']);
    }catch(InvalidArgumentException|RuntimeException $e){
        if($e instanceof PDOException)throw $e;
        ?><section class="workspace-card" data-catalog-variants><h2>Billing options</h2><p class="notice warning" role="alert"><?=esc($e->getMessage())?></p><?php
        if($retry){echo '<p>Your original entries remain below. Reload this product when its saved details are available.</p><p><a href="'.esc(admin_url('catalog',['id'=>(int)$product['id']])).'">Reload product review</a></p>';provider_catalog_variant_form(['selected_product'=>['id'=>(int)$product['id']]],false);}
        echo '</section>';return;
    }
    ?><section class="workspace-card" data-catalog-variants><h2>Billing options</h2><p>Offer the same hosting plan with separate monthly and annual prices. Each billing option has its own product code, price, image and hosting offer.</p><?php
    if(!$data['ready']){
        ?><form method="post" action="<?=esc(admin_url('catalog',['id'=>(int)$product['id']]))?>" data-catalog-variant-prepare><?php
        admin_action('catalog_import_variant_prepare');studio_hidden('source_product_id',(int)$product['id']);
        admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');
        admin_check('confirm','Enable additional billing options for imported hosting products.',false);
        ?><button class="btn outline">Enable billing options</button></form><?php
        if($retry){echo '<p>Your previous selection is retained until billing options are ready.</p><p><a href="'.esc(admin_url('catalog',['id'=>(int)$product['id']])).'">Reload product review</a></p>';provider_catalog_variant_form($data,false);}
        echo '</section>';return;
    }
    ?><p>Hosting plan: <a href="<?=esc(admin_url('catalog',['id'=>(int)$data['origin_product']['id']]))?>"><?=esc($data['origin_product']['title'])?></a></p><div class="table-wrap"><table><thead><tr><th>Product</th><th>Service period</th><th>Price</th><th>Status</th></tr></thead><tbody><?php
    foreach($data['variants'] as $option){
        $price=$option['amount_cents']===null?'Not configured':'$'.gmoney((int)$option['amount_cents']).' USD';
        ?><tr><td><a href="<?=esc(admin_url('catalog',['id'=>(int)$option['product_id']]))?>"><?=esc($option['sku'].' · '.$option['title'])?></a></td><td><?=esc(catalog_terms()[$option['term']]??$option['term'])?></td><td><?=esc($price)?></td><td><?=esc($option['status'])?></td></tr><?php
    }
    ?></tbody></table></div><details class="catalog-variant-create"<?= $retry?' open':'' ?>><summary>Add a billing option</summary><?php
    $available=true;
    if($retry){
        $available=($_POST['source_product_id']??null)===(string)$data['selected_product']['id']
            &&($_POST['source_revision']??null)===(string)$data['selected_product']['revision']
            &&($_POST['variant_review_sha256']??null)===$data['review_sha256'];
        if(!$available)echo '<p class="notice warning" role="alert">These product details changed. Your original entries remain below; reload the product review before creating this option.</p>';
        echo '<p><a href="'.esc(admin_url('catalog',['id'=>(int)$product['id']])).'">Reload product review</a></p>';
    }
    provider_catalog_variant_form($data,$available);?></details></section><?php
}
