<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/provider_catalog_import_schema.php';

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
    $marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_import_version']);
    $count=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_import_count']);
    $witness=(int)query('SELECT COUNT(*) FROM '.table('audit')." WHERE action LIKE 'catalog.provider_import.%'")->fetchColumn();
    $exists=provider_catalog_import_exists();
    if(!$marker){
        if($count||$witness||($exists&&(int)query('SELECT COUNT(*) FROM '.table('catalog_provider_imports'))->fetchColumn()!==0)) throw new RuntimeException('Imported product history needs recovery. Restore its original tables and settings.');
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
    return one('SELECT id FROM '.table('catalog_provider_imports').' WHERE media_id=? LIMIT 1',[$id])!==null;
}

function provider_catalog_import_record(int $productId): ?array {
    if(!provider_catalog_import_ready())return null;
    $row=one('SELECT * FROM '.table('catalog_provider_imports').' WHERE product_id=?',[$productId]);
    if(!$row)return null;
    provider_catalog_import_validate_record($row);return $row;
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
