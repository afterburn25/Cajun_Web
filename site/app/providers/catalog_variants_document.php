<?php
/** Local retail identities derived from an unchanged canonical hosting import. No provider I/O. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function provider_catalog_variant_keys(array $value,array $keys): void {
    $actual=array_keys($value);sort($actual);sort($keys);
    if($actual!==$keys)throw new InvalidArgumentException('Invalid hosting billing option fields.');
}
function provider_catalog_variant_id(mixed $id): void {
    if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/',$id)!==1)throw new InvalidArgumentException('Use the original billing option identity.');
}
function provider_catalog_variant_hash(mixed $hash): void {
    if(!is_string($hash)||preg_match('/\A[a-f0-9]{64}\z/',$hash)!==1)throw new InvalidArgumentException('Review the current hosting product first.');
}
function provider_catalog_variant_number(mixed $number): void {
    if(!is_int($number)||$number<1||$number>4294967295)throw new InvalidArgumentException('Invalid hosting product identity or revision.');
}
function provider_catalog_variant_text(mixed $text,int $max): void {
    if(!is_string($text)||$text===''||strlen($text)>$max||preg_match('//u',$text)!==1||preg_match('/[\x00-\x1f\x7f]/',$text))throw new InvalidArgumentException('Invalid saved hosting product text.');
}
function provider_catalog_variant_json(array $value): string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}

function provider_catalog_variant_input(string $id,int $selected,int $revision,string $review,string $sku,string $term): array {
    provider_catalog_variant_id($id);provider_catalog_variant_number($selected);provider_catalog_variant_number($revision);provider_catalog_variant_hash($review);
    if(preg_match('/\A[A-Z0-9][A-Z0-9_-]{1,59}\z/',$sku)!==1||!in_array($term,['month','year'],true))throw new InvalidArgumentException('Enter an uppercase SKU and choose monthly or annual billing.');
    return ['id'=>$id,'selected_product_id'=>$selected,'selected_revision'=>$revision,'review_sha256'=>$review,'sku'=>$sku,'term'=>$term];
}
/** The canonical physical import identity never changes to the child's product identity. */
function provider_catalog_variant_origin(array $record): array {
    $keys=['id','product_id','profile_id','source_revision','refresh_id','source_key_fingerprint','type_ref','type_name','source_hash'];
    $origin=[];foreach($keys as $key)$origin[$key]=in_array($key,['id','product_id','source_revision'],true)?(int)($record[$key]??0):($record[$key]??null);
    foreach(['id','product_id','source_revision'] as $key)provider_catalog_variant_number($origin[$key]);
    provider_catalog_variant_id($origin['profile_id']);provider_catalog_variant_id($origin['refresh_id']);
    provider_catalog_variant_hash($origin['source_key_fingerprint']);provider_catalog_variant_hash($origin['source_hash']);
    provider_catalog_variant_text($origin['type_name'],120);
    if(!is_string($origin['type_ref'])||preg_match('/\A[1-9][0-9]{0,17}\z/',$origin['type_ref'])!==1)throw new InvalidArgumentException('Invalid original hosting type.');
    $hash=hash('sha256',json_encode([$origin['profile_id'],$origin['source_revision'],$origin['refresh_id'],$origin['source_key_fingerprint'],$origin['type_ref'],$origin['type_name']],JSON_THROW_ON_ERROR));
    if(!hash_equals($hash,$origin['source_hash']))throw new InvalidArgumentException('The original hosting import differs.');
    return $origin;
}
function provider_catalog_variant_document(array $input,array $origin,int $productId,array $actor,int $at): array {
    provider_catalog_variant_keys($input,['id','selected_product_id','selected_revision','review_sha256','sku','term']);
    if(provider_catalog_variant_input($input['id'],$input['selected_product_id'],$input['selected_revision'],$input['review_sha256'],$input['sku'],$input['term'])!==$input)throw new InvalidArgumentException('Invalid billing option input.');
    provider_catalog_variant_keys($origin,['id','product_id','profile_id','source_revision','refresh_id','source_key_fingerprint','type_ref','type_name','source_hash']);
    if(provider_catalog_variant_origin($origin)!==$origin)throw new InvalidArgumentException('Invalid original hosting import.');
    provider_catalog_variant_number($productId);provider_catalog_variant_keys($actor,['id','auth_version']);provider_catalog_variant_number($actor['id']);provider_catalog_variant_text($actor['auth_version'],128);
    if($productId===$origin['product_id']||$productId===$input['selected_product_id']||$at<1||$at>253402300799)throw new InvalidArgumentException('Invalid billing option creation.');
    return ['version'=>1,'input'=>$input,'origin'=>$origin,'product_id'=>$productId,'actor'=>$actor,'created_epoch'=>$at];
}
function provider_catalog_variant_parse(string $bytes): array {
    if(strlen($bytes)>8192)throw new InvalidArgumentException('The billing option record exceeds its bound.');
    $doc=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);
    if(!is_array($doc))throw new InvalidArgumentException('Invalid billing option history.');
    provider_catalog_variant_keys($doc,['version','input','origin','product_id','actor','created_epoch']);
    if($doc['version']!==1||!is_array($doc['input'])||!is_array($doc['origin'])||!is_array($doc['actor'])||!is_int($doc['created_epoch']))throw new InvalidArgumentException('Invalid billing option history.');
    $validated=provider_catalog_variant_document($doc['input'],$doc['origin'],$doc['product_id'],$doc['actor'],$doc['created_epoch']);
    if(provider_catalog_variant_json($validated)!==$bytes)throw new InvalidArgumentException('Noncanonical billing option history.');
    return $validated;
}
function provider_catalog_variant_replay(array $doc,array $input,array $actor): int {
    if($doc['input']!==$input||$doc['actor']!==$actor)throw new RuntimeException('This billing option identity was already used with different details or owner access.');
    return $doc['product_id'];
}
function provider_catalog_variant_draft(array $input,array $origin): array {
    return ProviderCatalogImportDocument::fromRows([['type_ref'=>$origin['type_ref'],'package_type_name'=>$origin['type_name']]])->draft($origin['type_ref'],$input['sku'],$input['term']);
}
