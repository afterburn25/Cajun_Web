<?php
/** Internal read snapshots for hosting offers. No owner impersonation or credential decryption. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers_document.php';
require_once __DIR__.'/catalog_offers_storage.php';
require_once __DIR__.'/catalog_billing.php';
require_once CWP_CODE_ROOT.'/app/providers/catalog_import.php';
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog.php';

/** One guarded read scope for at most twenty products; callers never accept this value from HTTP. */
/** Read composition only: no cached readiness survives an entrypoint, transaction or write.
 * Every public page still validates all retained hosting history once: total cost is O(history).
 */
final class HostingCatalogRead {
    private function __construct() {}
    private static function products(array $productIds): void {
        hosting_catalog_guard();
        if (!array_is_list($productIds) || !$productIds || count($productIds)>20 || count(array_unique($productIds,SORT_REGULAR))!==count($productIds)) throw new InvalidArgumentException('Choose one page of hosting offers.');
        foreach ($productIds as $id) service_plans_actor($id);
    }
    /** Mutation callers retain their original fresh guard and full history validation. */
    public static function forProducts(array $productIds): array {
        self::products($productIds);
        if (!hosting_catalog_ready()) throw new RuntimeException('Hosting offers are not ready for new requests.');
        return self::scope($productIds);
    }
    /** Only called synchronously after this entrypoint validated history under its owning guard. */
    private static function scope(array $productIds): array {
        if (!catalog_ready() || !service_plans_ready() || !staff_ready() || !provider_settings_ready() || !provider_catalog_import_ready() || !provider_package_types_ready() || !provider_plan_catalog_state()['ready']) throw new RuntimeException('Hosting offers are not ready for new requests.');
        $profiles=all('SELECT * FROM '.table('provider_profiles').' WHERE provider_code=? AND environment=? LIMIT 2',['twenty-i','live']);
        if (count($profiles)!==1 || (int)$profiles[0]['enabled']!==1 || $profiles[0]['credentials']==='') throw new RuntimeException('The reviewed hosting connection is unavailable.');
        $profile=$profiles[0];
        // This is the same newest-intent rule used by the owner-only catalog view, with no actor mutation.
        $source=one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.operation=? AND f.profile_id=? ORDER BY f.seq DESC LIMIT 1',['package.types',$profile['id']]);
        if (!$source || ($source['state']??null)!=='complete' || (int)$source['profile_revision']!==(int)$profile['revision']) throw new RuntimeException('Review the newest complete hosting catalog before publishing offers.');
        $observation=provider_package_types_observation($source);
        $records=provider_catalog_import_records_in_scope($productIds);
        if (count($records)!==count($productIds)) throw new RuntimeException('An original hosting import is unavailable.');
        provider_catalog_import_origins($records);
        $imports=[];foreach ($records as $record) $imports[(int)$record['product_id']]=$record;
        return ['profile'=>['id'=>$profile['id'],'revision'=>(int)$profile['revision'],'enabled'=>true,'credential_sha256'=>hash('sha256',$profile['credentials']),'key_fingerprint'=>$source['key_fingerprint']],
            'observation'=>['refresh_id'=>$source['id'],'profile_id'=>$source['profile_id'],'profile_revision'=>(int)$source['profile_revision'],'key_fingerprint'=>$source['key_fingerprint'],'observed_epoch'=>$observation['observed_epoch'],'types'=>$observation['types']],
            'imports'=>$imports];
    }
    public static function publicPage(int $page=1,string $billing='all'): array {
        if ($page<1 || $page>100000) throw new InvalidArgumentException('Choose an available hosting page.');
        $billing=hosting_catalog_billing($billing);
        return recurring_billing_locked(function()use($page,$billing):array {
            if (!hosting_catalog_ready()) return ['offers'=>[],'has_more'=>false,'unavailable'=>false];
            $publications=table('hosting_catalog_publications');$withdrawals=table('hosting_catalog_withdrawals');$products=table('catalog_products');
            // Select the period before pagination; later matching offers must remain reachable.
            $filter=$billing==='all'?'':' AND BINARY c.term=?';
            $parameters=$billing==='all'?[]:[['monthly'=>'month','annually'=>'year'][$billing]];
            $rows=all("SELECT p.id,p.product_id FROM $publications p JOIN $products c ON c.id=p.product_id LEFT JOIN $withdrawals w ON w.publication_id=p.id WHERE c.status='draft' AND c.revision=p.product_revision AND w.id IS NULL AND NOT EXISTS (SELECT 1 FROM $publications n WHERE n.product_id=p.product_id AND n.seq>p.seq)$filter ORDER BY c.sort_order,c.id LIMIT 21 OFFSET ".(($page-1)*20),$parameters);
            $more=count($rows)>20;$rows=array_slice($rows,0,20);
            if (!$rows) return ['offers'=>[],'has_more'=>$more,'unavailable'=>false];
            $productIds=array_map(static fn(array $row):int=>(int)$row['product_id'],$rows);
            self::products($productIds);
            $scope=self::scope($productIds);
            $offers=[];$unavailable=false;
            foreach ($rows as $row) {
                $publication=hosting_catalog_publication($row['id']);
                try {
                    $offer=$publication->publicProjection(hosting_catalog_current_context($publication,$scope),time());
                    if ($billing!=='all' && ($offer['cadence']??null)!==$billing) throw new RuntimeException('The hosting billing period changed.');
                    $offers[]=$offer;
                }
                catch (RuntimeException $error) {if ($error instanceof PDOException) throw $error;$unavailable=true;}
            }
            return ['offers'=>$offers,'has_more'=>$more,'unavailable'=>$unavailable];
        });
    }
}

function hosting_catalog_read_scope(array $productIds): array {
    return HostingCatalogRead::forProducts($productIds);
}

/** The current owner's saved role/authentication revision is a fact, not the requesting session. */
function hosting_catalog_review_actor(int $id): array {
    hosting_catalog_guard();service_plans_actor($id);
    $user=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $actor=$user?staff_profile($user):null;
    if (!$actor || $actor['role']!=='owner' || (int)$actor['enabled']!==1 || $actor['password_hash']==='') throw new RuntimeException('An original hosting reviewer is no longer an active owner.');
    return ['id'=>(int)$actor['id'],'role'=>'owner','enabled'=>true,'auth_version'=>$actor['auth_version']];
}

/** Validate a product and its exact saved import within an already authenticated read scope. */
function hosting_catalog_product_source(int $productId,array $scope): array {
    hosting_catalog_guard();service_plans_actor($productId);
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
    return ['product'=>$product,'import'=>$import];
}

/** Return strict publication inputs. Existing source/audit validation precedes these projections. */
function hosting_catalog_sources(int $productId,string $planId,int $publisherId,array $scope): array {
    hosting_catalog_guard();service_plans_actor($productId);service_plans_id($planId);
    $source=hosting_catalog_product_source($productId,$scope);$product=$source['product'];$import=$source['import'];
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
