<?php
/** One owner-confirmed transaction: imported product -> active plan and reviewed mapping. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers.php';
require_once __DIR__.'/catalog_offer_setup_document.php';

/** Internal snapshot, always rebuilt inside the current owning transaction. */
function hosting_catalog_setup_snapshot(int $productId,array $actor): array {
    hosting_catalog_guard();
    $scope=hosting_catalog_read_scope([$productId]);
    $source=hosting_catalog_product_source($productId,$scope);
    if ($scope['observation']['observed_epoch']>time()) throw new RuntimeException('The saved hosting catalog is dated in the future. Check the server clock.');
    $review=hosting_catalog_setup_review($source['product'],$source['import'],$scope['observation'],$scope['profile'],
        ['id'=>(int)$actor['id'],'auth_version'=>$actor['auth_version']],
        hosting_catalog_integer($scope['imports'][$productId]['media_revision'],2147483647));
    return ['review'=>$review,'source'=>$source,'profile'=>$scope['profile']];
}

function hosting_catalog_setup_preview(int $productId): array {
    return recurring_billing_locked(function()use($productId):array {
        $actor=provider_catalog_import_owner();$owner=service_plans_owner();
        if ((int)$actor['id']!==(int)$owner['id']) throw new RuntimeException('Sign in again as the current owner.');
        return hosting_catalog_setup_snapshot($productId,$actor)['review'];
    });
}

/** Retries reuse all three original identities; publication and checkout remain separate explicit actions. */
function hosting_catalog_setup_create(string $planId,string $activationEventId,string $mappingId,int $productId,string $reviewSha,array $details,array $configuration,int $days,#[\SensitiveParameter] string $password): array {
    foreach ([$planId,$activationEventId,$mappingId] as $id) service_plans_id($id);
    if (count(array_unique([$planId,$activationEventId,$mappingId]))!==3) throw new InvalidArgumentException('Use the three original distinct setup identities.');
    service_plans_actor($productId);hosting_catalog_hash($reviewSha);
    if ($days<1 || $days>30) throw new InvalidArgumentException('Review mappings for 1 to 30 days.');
    return recurring_billing_locked(function()use($planId,$activationEventId,$mappingId,$productId,$reviewSha,$details,$configuration,$days,$password):array {
        $actor=provider_catalog_import_owner($password);$owner=service_plans_password($password);
        if ((int)$actor['id']!==(int)$owner['id']) throw new RuntimeException('Sign in again as the current owner.');
        // Authenticate the saved key against the newest retained response; no network request occurs here.
        provider_catalog_import_context('package.types');
        $snapshot=hosting_catalog_setup_snapshot($productId,$actor);
        if (!hash_equals($snapshot['review']['review_sha256'],$reviewSha)) throw new RuntimeException('This product, image, catalog or owner review changed. Reload and review the current details.');
        $terms=hosting_catalog_setup_terms($snapshot['source']['product'],$details);
        $config=hosting_catalog_setup_configuration($snapshot['source']['import'],$configuration);
        $existing=[one('SELECT id FROM '.table('service_plans').' WHERE id=?',[$planId])!==null,
            one('SELECT id FROM '.table('service_plan_events').' WHERE id=?',[$activationEventId])!==null,
            one('SELECT id FROM '.table('provider_plan_catalog').' WHERE id=?',[$mappingId])!==null];
        if (count(array_filter($existing))!==0 && count(array_filter($existing))!==3) throw new RuntimeException('These setup identities have incomplete or different history. Review the existing service plan before continuing.');
        service_plans_create_locked($planId,$terms,$password);
        $plan=service_plans_append_locked(service_plans_row($planId,false,true),0,$activationEventId,'activate',(int)$actor['id'],[]);
        if (!$plan instanceof ServicePlanDocument || $plan->revision()!==1 || $plan->model()->status()!=='active') throw new RuntimeException('This plan has changed since setup. Review its current version in Service plans.');
        $profile=$snapshot['profile'];
        $mapping=provider_plan_catalog_save_locked($mappingId,$planId,$plan->revision(),$plan->fingerprint(),null,
            $profile['id'],$profile['revision'],$config,$days,true,$password);
        provider_plan_catalog_eligible($mapping,time());
        return ['product_id'=>$productId,'plan_id'=>$planId,'mapping_id'=>$mappingId];
    });
}
