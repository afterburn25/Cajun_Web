<?php
/** Internal read snapshots for hosting offers. No owner impersonation or credential decryption. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers_document.php';
require_once __DIR__.'/catalog_offers_storage.php';
require_once CWP_CODE_ROOT.'/app/providers/catalog_import.php';
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog.php';

/** One guarded read scope for at most twenty products; callers never accept this value from HTTP. */
function hosting_catalog_read_scope(array $productIds): array {
    hosting_catalog_guard();
    if (!array_is_list($productIds) || !$productIds || count($productIds)>20 || count(array_unique($productIds,SORT_REGULAR))!==count($productIds)) throw new InvalidArgumentException('Choose one page of hosting offers.');
    foreach ($productIds as $id) service_plans_actor($id);
    if (!hosting_catalog_ready() || !catalog_ready() || !service_plans_ready() || !staff_ready() || !provider_settings_ready() || !provider_catalog_import_ready() || !provider_package_types_ready() || !provider_plan_catalog_state()['ready']) throw new RuntimeException('Hosting offers are not ready for new requests.');
    $profiles=all('SELECT * FROM '.table('provider_profiles').' WHERE provider_code=? AND environment=? LIMIT 2',['twenty-i','live']);
    if (count($profiles)!==1 || (int)$profiles[0]['enabled']!==1 || $profiles[0]['credentials']==='') throw new RuntimeException('The reviewed hosting connection is unavailable.');
    $profile=$profiles[0];
    // This is the same newest-intent rule used by the owner-only catalog view, with no actor mutation.
    $source=one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.operation=? AND f.profile_id=? ORDER BY f.seq DESC LIMIT 1',['package.types',$profile['id']]);
    if (!$source || ($source['state']??null)!=='complete' || (int)$source['profile_revision']!==(int)$profile['revision']) throw new RuntimeException('Review the newest complete hosting catalog before publishing offers.');
    $observation=provider_package_types_observation($source);
    $records=all('SELECT * FROM '.table('catalog_provider_imports').' WHERE product_id IN ('.implode(',',array_fill(0,count($productIds),'?')).')',$productIds);
    if (count($records)!==count($productIds)) throw new RuntimeException('An original hosting import is unavailable.');
    provider_catalog_import_origins($records);
    $imports=[];foreach ($records as $record) $imports[(int)$record['product_id']]=$record;
    return ['profile'=>['id'=>$profile['id'],'revision'=>(int)$profile['revision'],'enabled'=>true,'credential_sha256'=>hash('sha256',$profile['credentials']),'key_fingerprint'=>$source['key_fingerprint']],
        'observation'=>['refresh_id'=>$source['id'],'profile_id'=>$source['profile_id'],'profile_revision'=>(int)$source['profile_revision'],'key_fingerprint'=>$source['key_fingerprint'],'observed_epoch'=>$observation['observed_epoch'],'types'=>$observation['types']],
        'imports'=>$imports];
}

/** The current owner's saved role/authentication revision is a fact, not the requesting session. */
function hosting_catalog_review_actor(int $id): array {
    hosting_catalog_guard();service_plans_actor($id);
    $user=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $actor=$user?staff_profile($user):null;
    if (!$actor || $actor['role']!=='owner' || (int)$actor['enabled']!==1 || $actor['password_hash']==='') throw new RuntimeException('An original hosting reviewer is no longer an active owner.');
    return ['id'=>(int)$actor['id'],'role'=>'owner','enabled'=>true,'auth_version'=>$actor['auth_version']];
}

/** Return strict publication inputs. Existing source/audit validation precedes these projections. */
function hosting_catalog_sources(int $productId,string $planId,int $publisherId,array $scope): array {
    hosting_catalog_guard();service_plans_actor($productId);service_plans_id($planId);
    $record=$scope['imports'][$productId]??null;
    if (!$record) throw new LogicException('The hosting product is outside this guarded read scope.');
    $row=one('SELECT '.catalog_public_fields().' ,status FROM '.table('catalog_products').' WHERE id=?',[$productId]);
    if (!$row) throw new RuntimeException('The original hosting product is unavailable.');
    $product=[];
    foreach (['id','revision','sku','title','family','status','term','currency','amount_cents','setup_cents','summary','features','terms'] as $key) $product[$key]=in_array($key,['id','revision','amount_cents','setup_cents'],true)&&$row[$key]!==null?(int)$row[$key]:$row[$key];
    $product['media_id']=$record['media_id'];hosting_catalog_product($product);
    $revision=one('SELECT snapshot FROM '.table('catalog_revisions').' WHERE product_id=? AND revision=?',[$productId,$product['revision']]);
    $snapshot=$revision?json_decode($revision['snapshot'],true,16,JSON_THROW_ON_ERROR):null;
    if (!is_array($snapshot)) throw new RuntimeException('The original product revision is unavailable.');
    foreach ($product as $key=>$value) if (!in_array($key,['id','revision','media_id'],true) && (!array_key_exists($key,$snapshot) || $snapshot[$key]!==$value)) throw new RuntimeException('The hosting product differs from its saved revision.');
    $import=[];
    foreach (['id','product_id','profile_id','source_revision','refresh_id','source_key_fingerprint','type_ref','type_name','source_hash'] as $key) $import[$key]=in_array($key,['id','product_id','source_revision'],true)?(int)$record[$key]:$record[$key];
    hosting_catalog_import($import);
    $plan=service_plans_validate(service_plans_row($planId));
    $mapping=provider_plan_catalog_current($planId);
    if (!$mapping) throw new RuntimeException('Choose a reviewed hosting mapping for this plan.');
    $mappingRow=one('SELECT binding_json,binding_sha256 FROM '.table('provider_plan_catalog').' WHERE id=?',[$mapping['id']]);
    if (!$mappingRow || $mappingRow['binding_sha256']!==$mapping['sha256'] || hash('sha256',$mappingRow['binding_json'])!==$mapping['sha256']) throw new RuntimeException('The original provider mapping is unavailable.');
    $observed=hosting_catalog_observation($scope['observation'],$import['type_ref']);
    $context=['publication'=>null,
        'product'=>['id'=>$productId,'revision'=>$product['revision'],'sha256'=>hash('sha256',service_plans_json($product))],
        'plan'=>['id'=>$planId,'revision'=>$plan->revision(),'sha256'=>$plan->fingerprint(),'status'=>$plan->model()->status()],
        'mapping'=>['id'=>$mapping['id'],'sha256'=>$mapping['sha256']],
        'profile'=>$scope['profile'],
        'catalog'=>['refresh_id'=>$observed['refresh_id'],'state'=>'complete','profile_id'=>$observed['profile_id'],'profile_revision'=>$observed['profile_revision'],'key_fingerprint'=>$observed['key_fingerprint'],'observed_epoch'=>$observed['observed_epoch'],'types_sha256'=>$observed['types_sha256']],
        'publisher'=>hosting_catalog_review_actor($publisherId),
        'mapping_reviewer'=>hosting_catalog_review_actor($mapping['created_by']),
        'support_session'=>support_current()!==null || array_key_exists('support',$_GET)];
    return ['product'=>$product,'import'=>$import,'observation'=>$scope['observation'],'plan'=>$plan,'mapping_json'=>$mappingRow['binding_json'],'context'=>$context];
}

/** Existing publication eligibility is read from current SQL, never inferred from a displayed card. */
function hosting_catalog_current_context(HostingCatalogPublication $publication,array $scope): array {
    hosting_catalog_guard();$document=$publication->data();
    $plan=ServicePlanDocument::fromJson($document['plan_json']);
    $sources=hosting_catalog_sources($document['product']['id'],$plan->data()['id'],$document['publisher']['id'],$scope);
    $latest=one('SELECT id,document_sha256 FROM '.table('hosting_catalog_publications').' WHERE product_id=? ORDER BY seq DESC LIMIT 1',[$document['product']['id']]);
    $withdrawn=one('SELECT id FROM '.table('hosting_catalog_withdrawals').' WHERE publication_id=? LIMIT 1',[$document['id']])!==null;
    $sources['context']['publication']=['id'=>$latest['id']??null,'sha256'=>$latest['document_sha256']??null,'enabled'=>!$withdrawn];
    return $sources['context'];
}
