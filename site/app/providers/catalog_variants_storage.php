<?php
/** Additive retail-origin storage. Canonical import rows and their audit remain untouched. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_variants_document.php';
require_once __DIR__.'/catalog_import_document.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_catalog_variant_schema.php';

function provider_catalog_variant_exists(): bool {
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'catalog_provider_variants'])!==null;
}
function provider_catalog_variant_schema_valid(): void {
    catalog_validate_schema('catalog_provider_variants',provider_catalog_variant_schema(config()['db']['prefix'])['catalog_provider_variants'],['product_id'=>['product_id']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table('catalog_provider_variants')) as $column){
        if(in_array($column['Field'],['id','document_sha256','media_id'],true)&&$column['Collation']!=='ascii_bin')throw new RuntimeException('Billing option identifiers need their original schema.');
        if($column['Default']!==null||$column['Extra']!=='')throw new RuntimeException('Billing option columns need their original schema.');
    }
    $indexes=[];foreach(all('SHOW INDEX FROM '.table('catalog_provider_variants')) as $index)$indexes[$index['Key_name']][(int)$index['Seq_in_index']]=[(string)$index['Column_name'],(int)$index['Non_unique'],$index['Sub_part']];
    $expected=['PRIMARY'=>[1=>['id',0,null]],'product_id'=>[1=>['product_id',0,null]],'canonical_import_id'=>[1=>['canonical_import_id',1,null]]];
    ksort($indexes);ksort($expected);if($indexes!==$expected)throw new RuntimeException('Billing option indexes need their original schema.');
}
function provider_catalog_variant_image_detail(int $product,int $revision,?string $media): string {
    return 'Product '.$product.' image revision '.$revision.' media '.($media??'none');
}
/** Validates one immutable creation, its physical canonical row and independent media history. */
function provider_catalog_variant_validate_record(array $row): array {
    $doc=provider_catalog_variant_parse($row['document_json']);$hash=hash('sha256',$row['document_json']);
    if($row['id']!==$doc['input']['id']||(int)$row['canonical_import_id']!==$doc['origin']['id']||(int)$row['canonical_product_id']!==$doc['origin']['product_id']||(int)$row['product_id']!==$doc['product_id']||(int)$row['actor_id']!==$doc['actor']['id']||!hash_equals($hash,$row['document_sha256'])||$row['created_at']!==gmdate('Y-m-d H:i:s',$doc['created_epoch']))throw new RuntimeException('Billing option history needs recovery.');
    $canonical=one('SELECT * FROM '.table('catalog_provider_imports').' WHERE id=?',[$doc['origin']['id']]);
    if(!$canonical||provider_catalog_variant_origin($canonical)!==$doc['origin'])throw new RuntimeException('The billing option original import needs recovery.');
    provider_catalog_import_validate_record($canonical);
    $selected=$doc['input']['selected_product_id'];
    if($selected!==$doc['origin']['product_id']){
        $parent=one('SELECT * FROM '.table('catalog_provider_variants').' WHERE product_id=?',[$selected]);
        if(!$parent||(int)$parent['canonical_import_id']!==$doc['origin']['id']||$selected>=$doc['product_id'])throw new RuntimeException('The billing option selected source needs recovery.');
    }
    if(!one('SELECT snapshot FROM '.table('catalog_revisions').' WHERE product_id=? AND revision=?',[$selected,$doc['input']['selected_revision']]))throw new RuntimeException('The billing option selected revision needs recovery.');
    if(one('SELECT id FROM '.table('catalog_provider_imports').' WHERE product_id=?',[$doc['product_id']]))throw new RuntimeException('A billing option cannot replace a canonical import.');
    $product=one('SELECT family,status FROM '.table('catalog_products').' WHERE id=?',[$doc['product_id']]);
    if(!$product||$product['family']!=='hosting'||!in_array($product['status'],['draft','retired'],true))throw new RuntimeException('The billing option product needs recovery.');
    $initial=one('SELECT snapshot FROM '.table('catalog_revisions').' WHERE product_id=? AND revision=1',[$doc['product_id']]);
    $snapshot=$initial?json_decode($initial['snapshot'],true,16,JSON_THROW_ON_ERROR):null;
    if(!is_array($snapshot)||$snapshot!==catalog_product_input(provider_catalog_variant_draft($doc['input'],$doc['origin'])))throw new RuntimeException('The billing option initial product needs recovery.');
    $detail='Variant '.$row['id'].' product '.$doc['product_id'].' source '.$hash;
    if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=? AND user_id=? AND detail=?',['catalog.provider_variant.create',$doc['actor']['id'],$detail])->fetchColumn()!==1)throw new RuntimeException('The billing option creation witness needs recovery.');
    $revision=filter_var($row['media_revision'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]]);
    if($revision===false||($row['media_id']!==null&&preg_match('/\A[a-f0-9]{32}\z/',$row['media_id'])!==1))throw new RuntimeException('The billing option image needs recovery.');
    $images=all('SELECT detail FROM '.table('audit').' WHERE action=? AND detail LIKE ? ORDER BY id',['catalog.provider_variant.image','Product '.$doc['product_id'].' image revision %']);
    if(count($images)!==$revision||($revision===0&&$row['media_id']!==null))throw new RuntimeException('The billing option image history needs recovery.');
    foreach($images as $offset=>$event)if(preg_match('/\AProduct '.$doc['product_id'].' image revision '.($offset+1).' media (?:none|[a-f0-9]{32})\z/',$event['detail'])!==1)throw new RuntimeException('The billing option image history needs recovery.');
    if($revision>0&&$images[$revision-1]['detail']!==provider_catalog_variant_image_detail($doc['product_id'],$revision,$row['media_id']))throw new RuntimeException('The billing option current image differs from its history.');
    return $doc;
}
/** No DDL or writes. Unknown, missing and partial established stores never become empty. */
function provider_catalog_variant_ready(): bool {
    $prefix=config()['db']['prefix'].'catalog_provider_variant';
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE ?',[$prefix.'%']) as $tableRow){$name=$tableRow['TABLE_NAME'];if(str_starts_with($name,$prefix)&&$name!==$prefix.'s')throw new RuntimeException('Unknown billing option tables need recovery.');}
    $settings=all('SELECT name,value FROM '.table('settings').' WHERE name LIKE ?',['provider_catalog_variant_%']);$values=[];
    foreach($settings as $setting){if(!in_array($setting['name'],['provider_catalog_variant_version','provider_catalog_variant_count'],true))throw new RuntimeException('Unknown billing option settings need recovery.');$values[$setting['name']]=$setting['value'];}
    $actions=all('SELECT action,COUNT(*) AS n FROM '.table('audit').' WHERE action LIKE ? GROUP BY action',['catalog.provider_variant.%']);$counts=[];
    foreach($actions as $action){if(!in_array($action['action'],['catalog.provider_variant.setup','catalog.provider_variant.create','catalog.provider_variant.image'],true))throw new RuntimeException('Unknown billing option history needs recovery.');$counts[$action['action']]=(int)$action['n'];}
    $exists=provider_catalog_variant_exists();if($exists)provider_catalog_variant_schema_valid();
    if(!isset($values['provider_catalog_variant_version'])){
        if($values||$counts||($exists&&(int)query('SELECT COUNT(*) FROM '.table('catalog_provider_variants'))->fetchColumn()!==0))throw new RuntimeException('Billing option history needs recovery. Restore its original table and settings.');
        return false;
    }
    if($values['provider_catalog_variant_version']!=='1'||!$exists||!isset($values['provider_catalog_variant_count'])||preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/',$values['provider_catalog_variant_count'])!==1||($counts['catalog.provider_variant.setup']??0)!==1)throw new RuntimeException('Billing option history needs recovery.');
    if(!provider_catalog_import_exists()||(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_import_version'])['value']??null)!=='1')throw new RuntimeException('The original import table or preparation needs recovery.');
    $total=(int)$values['provider_catalog_variant_count'];
    if((int)query('SELECT COUNT(*) FROM '.table('catalog_provider_variants'))->fetchColumn()!==$total||($counts['catalog.provider_variant.create']??0)!==$total)throw new RuntimeException('Billing option counts need recovery.');
    $seen=0;$images=0;$cursor=null;
    do{$rows=$cursor===null?all('SELECT * FROM '.table('catalog_provider_variants').' ORDER BY id LIMIT 64'):all('SELECT * FROM '.table('catalog_provider_variants').' WHERE id>? ORDER BY id LIMIT 64',[$cursor]);
        foreach($rows as $row){provider_catalog_variant_validate_record($row);$images+=(int)$row['media_revision'];$cursor=$row['id'];$seen++;}
    }while(count($rows)===64);
    if($seen!==$total||$images!==($counts['catalog.provider_variant.image']??0))throw new RuntimeException('Billing option history changed or has unmatched image events.');
    return true;
}
/** Same closed import shape, but id is the canonical import and product_id the authenticated retail target. */
function provider_catalog_variant_resolve(array $row): array {
    $doc=provider_catalog_variant_validate_record($row);
    $record=one('SELECT * FROM '.table('catalog_provider_imports').' WHERE id=?',[$doc['origin']['id']]);
    $record['product_id']=$doc['product_id'];$record['media_id']=$row['media_id'];$record['media_revision']=$row['media_revision'];
    return $record;
}
