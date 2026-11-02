<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/catalog_variants.php';
require_once CWP_CODE_ROOT.'/admin/views/catalog_variants.php';

function provider_catalog_variant_post(string $name,int $limit): string {
    $value=$_POST[$name]??'';
    if(!is_string($value)||strlen($value)>$limit)throw new InvalidArgumentException('Reload the billing option form and review your entries.');
    return $value;
}
function provider_catalog_variant_post_id(string $name): int {
    $value=provider_catalog_variant_post($name,10);
    if(!preg_match('/\A[1-9][0-9]{0,9}\z/',$value)||(int)$value>2147483647)throw new InvalidArgumentException('Reload the original product before adding a billing option.');
    return (int)$value;
}
/** The existing catalog dispatcher supplies its normal CSRF and owner checks. */
function provider_catalog_variant_admin_action(string $action): never {
    provider_catalog_variant_owner();
    if(provider_catalog_variant_post('confirm',1)!=='1')throw new InvalidArgumentException('Confirm this billing option action before continuing.');
    $password=provider_catalog_variant_post('owner_password',1000);
    $productId=provider_catalog_variant_post_id('source_product_id');
    if($action==='catalog_import_variant_prepare'){
        provider_catalog_variant_prepare($password);
        flash('Additional billing options are ready. Choose a service period and product code.');
        redirect(admin_url('catalog',['id'=>$productId]));
    }
    if($action!=='catalog_import_variant_create')throw new InvalidArgumentException('Unknown billing option action.');
    $created=provider_catalog_variant_create(
        provider_catalog_variant_post('variant_request_id',32),$productId,
        provider_catalog_variant_post_id('source_revision'),provider_catalog_variant_post('variant_review_sha256',64),
        provider_catalog_variant_post('sku',60),provider_catalog_variant_post('term',10),$password
    );
    flash('Billing option saved as a separate draft. Set its price, description and image, then review its hosting offer.');
    redirect(admin_url('catalog',['id'=>$created]));
}
