<?php
/** Actual closed retail documents and legacy publication compatibility; no SQL or provider transport. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/providers/catalog_variants_document.php';
require CWP_CODE_ROOT.'/app/providers/catalog_import_document.php';
require CWP_CODE_ROOT.'/app/hosting/catalog_offer_setup_document.php';
$checks=[];
function cvd_ok(string $name,bool $passed): void {$GLOBALS['checks'][]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function cvd_bad(string $name,callable $call): void {try{$call();}catch(InvalidArgumentException|RuntimeException|TypeError|JsonException){cvd_ok($name,true);return;}cvd_ok($name,false);}
try{
    $origin=['id'=>3,'product_id'=>17,'profile_id'=>str_repeat('a',32),'source_revision'=>2,'refresh_id'=>str_repeat('b',32),'source_key_fingerprint'=>hash('sha256','synthetic key fingerprint'),'type_ref'=>'811','type_name'=>'Original hosting type','source_hash'=>''];
    $origin['source_hash']=hash('sha256',json_encode([$origin['profile_id'],$origin['source_revision'],$origin['refresh_id'],$origin['source_key_fingerprint'],$origin['type_ref'],$origin['type_name']],JSON_THROW_ON_ERROR));
    $input=provider_catalog_variant_input(str_repeat('c',32),17,4,hash('sha256','original reviewed selection'),'HOST-ANNUAL','year');$actor=['id'=>9,'auth_version'=>'original-owner-auth'];
    $doc=provider_catalog_variant_document($input,$origin,20,$actor,1700000000);$bytes=provider_catalog_variant_json($doc);
    cvd_ok('Canonical document retains original import and distinct retail product',provider_catalog_variant_parse($bytes)===$doc&&$doc['origin']['product_id']===17&&$doc['product_id']===20);
    cvd_ok('Exact completed input returns original product',provider_catalog_variant_replay($doc,$input,$actor)===20);
    foreach(['id'=>str_repeat('d',32),'selected_product_id'=>18,'selected_revision'=>5,'review_sha256'=>hash('sha256','different review'),'sku'=>'OTHER','term'=>'month'] as $key=>$value)cvd_bad('Replay refuses changed '.$key,fn()=>provider_catalog_variant_replay($doc,array_replace($input,[$key=>$value]),$actor));
    foreach(['id'=>10,'auth_version'=>'changed-owner'] as $key=>$value)cvd_bad('Replay refuses changed owner '.$key,fn()=>provider_catalog_variant_replay($doc,$input,array_replace($actor,[$key=>$value])));
    foreach(['','x','A'.str_repeat('a',31),str_repeat('a',33),str_repeat('a',31).' '] as $bad)cvd_bad('Identity refuses '.json_encode($bad),fn()=>provider_catalog_variant_input($bad,17,4,$input['review_sha256'],'HOST','year'));
    foreach([0,-1,4294967296] as $bad)cvd_bad('Product bound '.$bad,fn()=>provider_catalog_variant_input($input['id'],$bad,4,$input['review_sha256'],'HOST','year'));
    foreach(['x',str_repeat('f',63),str_repeat('F',64),str_repeat('f',65)] as $bad)cvd_bad('Review hash refuses '.strlen($bad).' '.$bad[0],fn()=>provider_catalog_variant_input($input['id'],17,4,$bad,'HOST','year'));
    foreach(['a','lowercase',' HAS-SPACE','HAS SPACE','HAS/SLASH',str_repeat('A',61),"HOST\n"] as $bad)cvd_bad('SKU refuses '.json_encode($bad),fn()=>provider_catalog_variant_input($input['id'],17,4,$input['review_sha256'],$bad,'year'));
    foreach(['once','annual','MONTH','',' year'] as $bad)cvd_bad('Period refuses '.json_encode($bad),fn()=>provider_catalog_variant_input($input['id'],17,4,$input['review_sha256'],'HOST',$bad));
    cvd_ok('Maximum SKU and IDs accepted explicitly',provider_catalog_variant_input($input['id'],4294967295,4294967295,$input['review_sha256'],str_repeat('A',60),'month')['term']==='month');
    cvd_bad('Creation cannot reuse canonical product',fn()=>provider_catalog_variant_document($input,$origin,17,$actor,1700000000));
    cvd_bad('Creation cannot replace selected child',fn()=>provider_catalog_variant_document(array_replace($input,['selected_product_id'=>19]),$origin,19,$actor,1700000000));
    cvd_bad('Creation extra request field rejected',fn()=>provider_catalog_variant_document($input+['price'=>1],$origin,20,$actor,1700000000));
    cvd_bad('Creation extra origin field rejected',fn()=>provider_catalog_variant_document($input,$origin+['image'=>'remote'],20,$actor,1700000000));
    cvd_bad('Creation extra actor field rejected',fn()=>provider_catalog_variant_document($input,$origin,20,$actor+['role'=>'owner'],1700000000));
    foreach(['profile_id'=>str_repeat('e',32),'refresh_id'=>str_repeat('f',32),'source_revision'=>3,'type_ref'=>'812','type_name'=>'Changed type'] as $key=>$value)cvd_bad('Historical source digest binds '.$key,fn()=>provider_catalog_variant_origin(array_replace($origin,[$key=>$value])));
    cvd_bad('Noncanonical JSON whitespace refused',fn()=>provider_catalog_variant_parse($bytes.' '));
    cvd_bad('Duplicate JSON keys refused',fn()=>provider_catalog_variant_parse(str_replace('"version":1','"version":1,"version":1',$bytes)));
    cvd_bad('Unknown document version refused',fn()=>provider_catalog_variant_parse(str_replace('"version":1','"version":2',$bytes)));
    cvd_bad('Unknown document field refused',fn()=>provider_catalog_variant_parse(substr($bytes,0,-1).',"extra":true}'));
    cvd_bad('Oversized document refused',fn()=>provider_catalog_variant_parse(str_repeat(' ',8193)));
    $draft=provider_catalog_variant_draft($input,$origin);
    cvd_ok('New option is independent unpriced unpublished draft',$draft===['sku'=>'HOST-ANNUAL','title'=>'Original hosting type','family'=>'hosting','status'=>'draft','term'=>'year','currency'=>'USD','amount'=>'','setup'=>'0.00','cost'=>'','provider_ref'=>'20i/observed-type/811','summary'=>'','features'=>'','terms'=>'','sort_order'=>'0']);
    cvd_ok('Draft has no inherited image or billing authority',!isset($draft['media_id'],$draft['checkout_policy'],$draft['mapping_id']));
    $normalized=$origin;$normalized['product_id']=20;
    cvd_ok('Legacy import projection accepts qualified canonical ID and retail product pair',hosting_catalog_import($normalized)===$normalized&&$normalized['id']===3&&$normalized['source_hash']===$origin['source_hash']);
    $product=['id'=>20,'revision'=>2,'sku'=>'HOST-ANNUAL','title'=>'Annual hosting','family'=>'hosting','status'=>'draft','term'=>'year','currency'=>'USD','amount_cents'=>24000,'setup_cents'=>0,'summary'=>'Annual website hosting','features'=>'','terms'=>'Reviewed terms','media_id'=>null];
    $observation=['refresh_id'=>str_repeat('d',32),'profile_id'=>$origin['profile_id'],'profile_revision'=>3,'key_fingerprint'=>$origin['source_key_fingerprint'],'observed_epoch'=>1700000001,'types'=>[['id'=>'811','label'=>'Current hosting label']]];
    $profile=['id'=>$origin['profile_id'],'revision'=>3,'enabled'=>true,'credential_sha256'=>hash('sha256','ciphertext'),'key_fingerprint'=>$origin['source_key_fingerprint']];
    $review=hosting_catalog_setup_review($product,$normalized,$observation,$profile,$actor,0);
    cvd_ok('Existing setup accepts actual variant product and canonical type',$review['product']===$product&&$review['package']===['type'=>'811','label'=>'Current hosting label']);
    cvd_bad('Unnormalized canonical product cannot impersonate child source',fn()=>hosting_catalog_setup_review($product,$origin,$observation,$profile,$actor,0));
    $details=['inclusions'=>['Hosting'],'exclusions'=>[],'limits'=>[],'support'=>'Business hours'];
    $terms=hosting_catalog_setup_terms($product,$details);
    cvd_ok('Existing plan terms use owner annual price and exact annual cadence',$terms['price']===24000&&$terms['cadence']==='annually');
    $monthly=hosting_catalog_setup_terms(array_replace($product,['term'=>'month','amount_cents'=>2500]),$details);
    cvd_ok('Monthly option remains independent without inferred annual discount',$monthly['price']===2500&&$monthly['cadence']==='monthly'&&$product['amount_cents']===24000);
    $result=['passed'=>true,'scope'=>'actual closed variant documents and existing setup functions; no SQL/provider execution','checks'=>$checks];
}catch(Throwable $e){$result=['passed'=>false,'checks'=>$checks,'failure'=>$e::class.': '.$e->getMessage()];}
$json=json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;if(isset($argv[1]))file_put_contents($argv[1],$json);echo $json;exit($result['passed']?0:1);
