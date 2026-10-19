<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/provider_catalog_import_schema.php';
require_once __DIR__.'/catalog_variants_storage.php';

function provider_catalog_import_exists(): bool {
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'catalog_provider_imports'])!==null;
}
function provider_catalog_import_schema_valid(): void {
    $sql=provider_catalog_import_schema(config()['db']['prefix'])['catalog_provider_imports'];
    catalog_validate_schema('catalog_provider_imports',$sql,['provider_type'=>['profile_id','type_ref'],'product_id'=>['product_id']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table('catalog_provider_imports')) as $column) if(in_array($column['Field'],['profile_id','type_ref','refresh_id','source_key_fingerprint','source_hash','media_id'],true)&&$column['Collation']!=='ascii_bin') throw new RuntimeException('Imported product identifiers need their original schema.');
}
/** Read-only. Missing established history never becomes a new empty catalog. */
function provider_catalog_import_ready(): bool {
    $variantsReady=provider_catalog_variant_ready();
    $marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_import_version']);
    $count=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_import_count']);
    $witness=(int)query('SELECT COUNT(*) FROM '.table('audit')." WHERE action LIKE 'catalog.provider_import.%'")->fetchColumn();
    $exists=provider_catalog_import_exists();
    if(!$marker){
        if($variantsReady||$count||$witness||($exists&&(int)query('SELECT COUNT(*) FROM '.table('catalog_provider_imports'))->fetchColumn()!==0)) throw new RuntimeException('Imported product history needs recovery. Restore its original tables and settings.');
        return false;
    }
    if($marker['value']!=='1'||!$exists||!$count||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$count['value'])) throw new RuntimeException('Imported product history needs recovery. Restore its original tables and settings.');
    provider_catalog_import_schema_valid();
    $rows=(int)query('SELECT COUNT(*) FROM '.table('catalog_provider_imports'))->fetchColumn();
    $events=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['catalog.provider_import.create'])->fetchColumn();
    if($rows!==(int)$count['value']||$events!==$rows||!one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',['catalog.provider_import.setup'])||one('SELECT i.id FROM '.table('catalog_provider_imports').' i LEFT JOIN '.table('catalog_products').' p ON p.id=i.product_id WHERE p.id IS NULL LIMIT 1')) throw new RuntimeException('Imported product history needs recovery. No products were recreated.');
    $cursor=null;$seen=0;
    do{
        $batch=$cursor===null?all('SELECT * FROM '.table('catalog_provider_imports').' ORDER BY id LIMIT 64'):all('SELECT * FROM '.table('catalog_provider_imports').' WHERE id>? ORDER BY id LIMIT 64',[$cursor]);
        foreach($batch as $record){provider_catalog_import_validate_record($record);$cursor=(int)$record['id'];$seen++;}
    }while(count($batch)===64);
    if($seen!==$rows)throw new RuntimeException('Imported product history changed during validation. Retry after reviewing it.');
    return true;
}

/** Housekeeping validates the complete store once before its deletion loop. */
function provider_catalog_image_used(string $id): bool {
    return one('SELECT id FROM '.table('catalog_provider_imports').' WHERE media_id=? LIMIT 1',[$id])!==null
        ||(provider_catalog_variant_exists()&&one('SELECT id FROM '.table('catalog_provider_variants').' WHERE media_id=? LIMIT 1',[$id])!==null);
}

function provider_catalog_import_record(int $productId): ?array {
    if(!provider_catalog_import_ready())return null;
    $row=one('SELECT * FROM '.table('catalog_provider_imports').' WHERE product_id=?',[$productId]);
    if(!$row){$variant=provider_catalog_variant_exists()?one('SELECT * FROM '.table('catalog_provider_variants').' WHERE product_id=?',[$productId]):null;return $variant?provider_catalog_variant_resolve($variant):null;}
    provider_catalog_import_validate_record($row);return $row;
}

/** Authenticate a normalized canonical-ID/retail-product-ID pair before using original provenance. */
function provider_catalog_import_canonical_record(array $record): array {
    return provider_catalog_import_canonical_record_in_scope($record,provider_catalog_variant_ready());
}
/** Internal same-transaction composition after readiness; every canonical/retail pair still authenticates its own rows and witnesses. */
function provider_catalog_import_canonical_record_in_scope(array $record,bool $variantsReady): array {
    provider_catalog_import_transaction();
    $canonical=one('SELECT * FROM '.table('catalog_provider_imports').' WHERE id=?',[$record['id']??null]);
    if(!$canonical)throw new RuntimeException('The original hosting import needs recovery.');
    provider_catalog_import_validate_record($canonical);
    if((int)$canonical['product_id']===(int)($record['product_id']??0)){
        if($canonical!=$record)throw new RuntimeException('The imported product record differs from its original source.');
        return $canonical;
    }
    if(!$variantsReady)throw new RuntimeException('The hosting billing option history is unavailable.');
    $variant=one('SELECT * FROM '.table('catalog_provider_variants').' WHERE product_id=?',[$record['product_id']??null]);
    if(!$variant||(int)$variant['canonical_import_id']!==(int)$canonical['id']||provider_catalog_variant_resolve($variant)!=$record)throw new RuntimeException('The billing option and original hosting import do not match.');
    return $canonical;
}
/** Fixed SQL composition only, never a supplied predicate or unchecked identifier. Readiness validates every origin first. */
function provider_catalog_import_product_predicate(string $alias): string {
    if(preg_match('/\A[a-z][a-z0-9_]{0,15}\z/',$alias)!==1)throw new InvalidArgumentException('Invalid internal product alias.');
    if(!provider_catalog_import_ready())return '0=1';
    $canonical='EXISTS (SELECT 1 FROM '.table('catalog_provider_imports').' pci WHERE pci.product_id='.$alias.'.id)';
    if(!provider_catalog_variant_ready())return $canonical;
    return '('.$canonical.' OR EXISTS (SELECT 1 FROM '.table('catalog_provider_variants').' pcv WHERE pcv.product_id='.$alias.'.id))';
}
/** One bounded source lookup used by existing publication snapshots. */
function provider_catalog_import_records(array $productIds): array {
    if(!provider_catalog_import_ready())return [];
    return provider_catalog_import_records_in_scope($productIds);
}
/** Only synchronous guarded callers which just validated import readiness may compose this lookup. No cached state survives a call. */
function provider_catalog_import_records_in_scope(array $productIds): array {
    provider_catalog_import_transaction();
    if(!array_is_list($productIds)||count($productIds)>20||count(array_unique($productIds,SORT_REGULAR))!==count($productIds))throw new InvalidArgumentException('Choose one page of hosting products.');
    $records=[];foreach($productIds as $productId){provider_catalog_variant_number($productId);$record=one('SELECT * FROM '.table('catalog_provider_imports').' WHERE product_id=?',[$productId]);
        if(!$record&&provider_catalog_variant_exists()){$row=one('SELECT * FROM '.table('catalog_provider_variants').' WHERE product_id=?',[$productId]);if($row)$record=provider_catalog_variant_resolve($row);}
        if($record)$records[]=$record;
    }
    return $records;
}
function provider_catalog_import_validate_record(array $row): void {
    if((int)$row['id']<1||(int)$row['product_id']<1||(int)$row['source_revision']<1||(int)$row['actor_id']<1||(int)$row['media_revision']<0)throw new RuntimeException('Imported product provenance needs recovery.');
    foreach(['profile_id','refresh_id'] as $key)if(!preg_match('/^[a-f0-9]{32}$/D',$row[$key]))throw new RuntimeException('Imported product provenance needs recovery.');
    if(!preg_match('/^[1-9][0-9]{0,17}$/D',$row['type_ref'])||!preg_match('/^[a-f0-9]{64}$/D',$row['source_key_fingerprint'])||!preg_match('/^[a-f0-9]{64}$/D',$row['source_hash'])||($row['media_id']!==null&&!preg_match('/^[a-f0-9]{32}$/D',$row['media_id'])))throw new RuntimeException('Imported product provenance needs recovery.');
    $hash=hash('sha256',json_encode([$row['profile_id'],(int)$row['source_revision'],$row['refresh_id'],$row['source_key_fingerprint'],$row['type_ref'],$row['type_name']],JSON_THROW_ON_ERROR));
    if(!hash_equals($hash,$row['source_hash']))throw new RuntimeException('Imported product provenance needs recovery.');
    $detail='Product '.$row['product_id'].' source '.$row['source_hash'];
    if(!one('SELECT id FROM '.table('audit').' WHERE action=? AND user_id=? AND detail=?',['catalog.provider_import.create',$row['actor_id'],$detail]))throw new RuntimeException('Imported product provenance needs recovery.');
}
