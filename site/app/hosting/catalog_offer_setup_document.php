<?php
/** Pure terms for finishing an imported hosting offer. No writes or provider transport. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers_document.php';

/** The owner sees one exact product/catalog review; private fingerprints leave only as a digest. */
function hosting_catalog_setup_review(array $product,array $import,array $observation,array $profile,array $owner,int $mediaRevision): array {
    $product=hosting_catalog_product($product);$import=hosting_catalog_import($import);
    $observed=hosting_catalog_observation($observation,$import['type_ref']);
    service_plans_keys($profile,['id','revision','enabled','credential_sha256','key_fingerprint']);
    service_plans_id($profile['id']);service_plans_actor($profile['revision']);
    hosting_catalog_hash($profile['credential_sha256']);hosting_catalog_hash($profile['key_fingerprint']);
    hosting_catalog_actor($owner);
    if ($mediaRevision<0 || $mediaRevision>2147483647) throw new InvalidArgumentException('Invalid imported image revision.');
    if ($import['product_id']!==$product['id'] || $import['profile_id']!==$observed['profile_id']
        || !hash_equals($import['source_key_fingerprint'],$observed['key_fingerprint'])
        || $profile['id']!==$observed['profile_id'] || $profile['revision']!==$observed['profile_revision']
        || $profile['enabled']!==true || !hash_equals($profile['key_fingerprint'],$observed['key_fingerprint'])) {
        throw new RuntimeException('Review this imported product under the current 20i connection and catalog.');
    }
    $review=['format'=>1,'purpose'=>'imported-hosting-plan','product'=>$product,'import'=>$import,
        'observation'=>$observed,'profile'=>$profile,'owner'=>$owner,'media_revision'=>$mediaRevision];
    return ['product'=>$product,'package'=>['type'=>$observed['selected']['id'],'label'=>$observed['selected']['label']],
        'review_sha256'=>hash('sha256',service_plans_json($review))];
}

/** Commercial fields always come from the validated saved product, never the submitted form. */
function hosting_catalog_setup_terms(array $product,array $details): array {
    $product=hosting_catalog_product($product);
    service_plans_keys($details,['inclusions','exclusions','limits','support']);
    return service_plans_terms(['name'=>$product['title'],'cadence'=>['month'=>'monthly','year'=>'annually'][$product['term']],
        'price'=>$product['amount_cents'],'currency'=>$product['currency'],...$details]);
}

/** The imported type is authoritative; account defaults, location, limits and cost remain owner reviewed. */
function hosting_catalog_setup_configuration(array $import,array $configuration): array {
    $import=hosting_catalog_import($import);
    service_plans_keys($configuration,['reseller_id','label','region','limits','cost']);
    return provider_plan_catalog_config(['reseller_id'=>$configuration['reseller_id'],'type'=>$import['type_ref'],
        'label'=>$configuration['label'],'region'=>$configuration['region'],'limits'=>$configuration['limits'],'cost'=>$configuration['cost']]);
}
