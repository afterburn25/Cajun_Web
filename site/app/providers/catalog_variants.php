<?php
/** Owner-created retail billing options; no new provider import, transport, publication or charge. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_import.php';
require_once __DIR__.'/catalog_variants_storage.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_catalog_variant_migrate.php';

function provider_catalog_variant_owner(#[\SensitiveParameter] ?string $password=null): array {
    foreach(['client_id','client_auth','contact_id','contact_auth','support'] as $key)if(array_key_exists($key,$_SESSION))throw new RuntimeException('Use your current owner session to manage hosting billing options.');
    if(array_key_exists('support',$_GET))throw new RuntimeException('Leave support before managing hosting billing options.');
    return provider_catalog_import_owner($password);
}
function provider_catalog_variant_product(int $productId): array {
    provider_catalog_variant_number($productId);
    $product=one('SELECT id,revision,sku,title,family,status,term,amount_cents FROM '.table('catalog_products').' WHERE id=?',[$productId]);
    if(!$product||$product['family']!=='hosting'||!in_array($product['status'],['draft','retired'],true))throw new RuntimeException('Choose an imported hosting draft or retired product.');
    return $product;
}
/** Called under the existing owning lock/transaction; selected child products resolve the same canonical family. */
function provider_catalog_variant_snapshot(int $selectedId,array $actor): array {
    provider_catalog_import_transaction();
    $context=provider_catalog_import_context('package.types');
    $selected=provider_catalog_variant_product($selectedId);$record=provider_catalog_import_record($selectedId);
    if(!$record)throw new RuntimeException('Choose an imported hosting product.');
    $canonical=provider_catalog_import_canonical_record($record);
    provider_catalog_import_origins([$record]);$origin=provider_catalog_variant_origin($canonical);
    if($origin['profile_id']!==$context['profile_id']||!hash_equals($origin['source_key_fingerprint'],$context['source_key_fingerprint']))throw new RuntimeException('This hosting source belongs to earlier connection credentials.');
    $context['document']->type($origin['type_ref']);
    $review=hash('sha256',provider_catalog_variant_json(['selected_id'=>$selectedId,'selected_revision'=>(int)$selected['revision'],'media_revision'=>(int)$record['media_revision'],'origin'=>$origin,'current_source'=>$context['hash'],'actor'=>['id'=>(int)$actor['id'],'auth_version'=>$actor['auth_version']]]));
    return ['selected'=>$selected,'canonical'=>$canonical,'origin'=>$origin,'review_sha256'=>$review];
}
function provider_catalog_variant_preview(int $selectedId): array {
    return staff_locked(fn()=>staff_transaction(function()use($selectedId):array {
        $actor=provider_catalog_variant_owner();$snapshot=provider_catalog_variant_snapshot($selectedId,$actor);$selected=$snapshot['selected'];
        $ready=provider_catalog_variant_ready();$origin=provider_catalog_variant_product($snapshot['origin']['product_id']);
        $records=[$snapshot['canonical']];
        if($ready)foreach(all('SELECT * FROM '.table('catalog_provider_variants').' WHERE canonical_import_id=? ORDER BY product_id LIMIT 201',[$snapshot['origin']['id']]) as $row)$records[]=provider_catalog_variant_resolve($row);
        if(count($records)>201)throw new RuntimeException('This hosting type has reached its supported billing option limit.');
        $variants=[];foreach($records as $record){$product=provider_catalog_variant_product((int)$record['product_id']);$variants[]=['product_id'=>(int)$product['id'],'title'=>$product['title'],'sku'=>$product['sku'],'term'=>$product['term'],'amount_cents'=>$product['amount_cents']===null?null:(int)$product['amount_cents'],'media_id'=>$record['media_id'],'status'=>$product['status']];}
        return ['ready'=>$ready,'selected_product'=>['id'=>$selectedId,'revision'=>(int)$selected['revision'],'sku'=>$selected['sku'],'title'=>$selected['title'],'term'=>$selected['term']],
            'origin_product'=>['id'=>(int)$origin['id'],'sku'=>$origin['sku'],'title'=>$origin['title']],
            'review_sha256'=>$snapshot['review_sha256'],'variants'=>$variants];
    }));
}
/** Exact completed replay is inert, but still requires the original current owner and credentials. */
function provider_catalog_variant_replay_authority(array $doc,array $actor): void {
    if($doc['actor']!==['id'=>(int)$actor['id'],'auth_version'=>$actor['auth_version']])throw new RuntimeException('The original billing option owner access changed.');
    $profile=provider_settings_profile();
    if(!$profile||$profile['id']!==$doc['origin']['profile_id'])throw new RuntimeException('The original hosting connection is unavailable.');
    [, $row]=provider_inventory_authority($profile['id'],(int)$profile['revision']);
    $secret=provider_settings_unseal($row['id'],$row['environment'],$row['credentials']);
    if(!hash_equals(provider_inventory_fingerprint($secret),$doc['origin']['source_key_fingerprint']))throw new RuntimeException('The original hosting credentials changed.');
    unset($secret);
}
function provider_catalog_variant_create(string $requestId,int $selectedId,int $expectedRevision,string $reviewSha,string $sku,string $term,#[\SensitiveParameter] string $password): int {
    $input=provider_catalog_variant_input($requestId,$selectedId,$expectedRevision,$reviewSha,$sku,$term);
    return staff_locked(fn()=>staff_transaction(function()use($input,$password):int {
        $actor=provider_catalog_variant_owner($password);
        if(!provider_catalog_import_ready()||!provider_catalog_variant_ready())throw new RuntimeException('Enable hosting billing options first.');
        $old=one('SELECT * FROM '.table('catalog_provider_variants').' WHERE id=?',[$input['id']]);
        if($old){$doc=provider_catalog_variant_validate_record($old);$product=provider_catalog_variant_replay($doc,$input,['id'=>(int)$actor['id'],'auth_version'=>$actor['auth_version']]);provider_catalog_import_origins([provider_catalog_variant_resolve($old)]);provider_catalog_variant_replay_authority($doc,$actor);return $product;}
        $snapshot=provider_catalog_variant_snapshot($input['selected_product_id'],$actor);
        if((int)$snapshot['selected']['revision']!==$input['selected_revision']||!hash_equals($snapshot['review_sha256'],$input['review_sha256']))throw new RuntimeException('This product, source or owner review changed. Reload and review the current details.');
        $origin=$snapshot['origin'];
        if((int)query('SELECT COUNT(*) FROM '.table('catalog_provider_variants').' WHERE canonical_import_id=?',[$origin['id']])->fetchColumn()>=200)throw new RuntimeException('This hosting type has reached its supported billing option limit.');
        $count=(int)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_variant_count'])['value']);
        if($count>=999999999)throw new RuntimeException('Billing option history has reached its supported bound.');
        $product=catalog_save_in_transaction(0,0,provider_catalog_variant_draft($input,$origin));
        $doc=provider_catalog_variant_document($input,$origin,$product,['id'=>(int)$actor['id'],'auth_version'=>$actor['auth_version']],time());
        $bytes=provider_catalog_variant_json($doc);$hash=hash('sha256',$bytes);
        query('INSERT INTO '.table('catalog_provider_variants').' (id,canonical_import_id,canonical_product_id,product_id,document_json,document_sha256,actor_id,created_at,media_id,media_revision) VALUES (?,?,?,?,?,?,?,?,NULL,0)',[$input['id'],$origin['id'],$origin['product_id'],$product,$bytes,$hash,(int)$actor['id'],gmdate('Y-m-d H:i:s',$doc['created_epoch'])]);
        save_setting('provider_catalog_variant_count',(string)($count+1));
        catalog_audit((int)$actor['id'],'provider_variant.create','Variant '.$input['id'].' product '.$product.' source '.$hash);
        return $product;
    }));
}
