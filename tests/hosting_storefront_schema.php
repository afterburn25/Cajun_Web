<?php
/** Pure storefront schema and document policy; no SQL or external action. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/hosting/catalog_offers_document.php';
require CWP_CODE_ROOT.'/database/schema/hosting_storefront_schema.php';
$checks=[];$failure=null;
function hss_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function hss_bad(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|RuntimeException|TypeError|JsonException){hss_ok($name,true);return;}hss_ok($name,false);}
try {
    $schema=hosting_storefront_schema('hss_');
    hss_ok('Separate schema has immutable category, classification, and publication-index histories',array_keys($schema)===['hosting_storefront_categories','hosting_storefront_classifications','hosting_storefront_publications']);
    hss_ok('Category schema has ordered revisions and owner authority',str_contains($schema['hosting_storefront_categories'],'UNIQUE slug_revision(slug,revision)')&&str_contains($schema['hosting_storefront_categories'],'FOREIGN KEY(created_by)'));
    hss_ok('Classification schema binds retained product revision and optional category revision',str_contains($schema['hosting_storefront_classifications'],'FOREIGN KEY(product_id,product_revision)')&&str_contains($schema['hosting_storefront_classifications'],'FOREIGN KEY(category_slug,category_revision)'));
    hss_ok('Publication index binds exact offer, classification, category, and product before pagination',str_contains($schema['hosting_storefront_publications'],'FOREIGN KEY(id)')&&str_contains($schema['hosting_storefront_publications'],'FOREIGN KEY(classification_id)')&&str_contains($schema['hosting_storefront_publications'],'INDEX category_seq(category_slug,seq)'));
    hss_ok('Schema contains no destructive cascade',!str_contains(strtoupper(implode("\n",$schema)),'CASCADE'));
    hss_ok('Schema index contracts are closed',array_keys(hosting_storefront_indexes('hosting_storefront_categories'))===['PRIMARY','category_seq','slug_revision','slug_seq','created_by']&&array_keys(hosting_storefront_indexes('hosting_storefront_classifications'))===['PRIMARY','classification_seq','product_seq','product_revision','category_revision','created_by']&&array_keys(hosting_storefront_indexes('hosting_storefront_publications'))===['PRIMARY','storefront_publication_seq','product_seq','category_seq','category_revision','classification_id','created_by']);
    $shared=['format'=>1,'id'=>str_repeat('a',32),'slug'=>'shared-hosting','revision'=>1,'label'=>'Shared Hosting','summary'=>'Shared plans.','required_family'=>'hosting','purchase_mode'=>'online','created_by'=>1,'created_at'=>100];
    $sharedJson=hosting_storefront_category_document($shared);hss_ok('Shared category round trips canonically',hosting_storefront_category_data($sharedJson)===$shared);
    $vps=array_replace($shared,['id'=>str_repeat('b',32),'slug'=>'vps-hosting','label'=>'VPS Hosting','required_family'=>'vps','purchase_mode'=>'unavailable']);
    hss_ok('VPS category is retained only as truthfully unavailable',hosting_storefront_category_data(hosting_storefront_category_document($vps))['purchase_mode']==='unavailable');
    hss_bad('VPS online purchase is refused without a contract',fn()=>hosting_storefront_category_document(array_replace($vps,['purchase_mode'=>'online'])));
    $bound=['format'=>1,'id'=>str_repeat('c',32),'product_id'=>42,'product_revision'=>3,'state'=>'bound','category_id'=>$shared['id'],'category_slug'=>'shared-hosting','category_revision'=>1,'category_sha256'=>hash('sha256',$sharedJson),'source'=>'twentyi-addweb-migration','created_by'=>1,'created_at'=>101];
    hss_ok('Explicit Shared migration binding round trips canonically',hosting_storefront_classification_data(hosting_storefront_classification_document($bound))===$bound);
    $review=array_replace($bound,['id'=>str_repeat('d',32),'state'=>'review','category_id'=>null,'category_slug'=>null,'category_revision'=>null,'category_sha256'=>null,'source'=>'migration-review']);
    hss_ok('Unknown products retain explicit manual-review history',hosting_storefront_classification_data(hosting_storefront_classification_document($review))===$review);
    hss_bad('Review state cannot smuggle a category',fn()=>hosting_storefront_classification_document(array_replace($review,['category_slug'=>'shared-hosting'])));
    hss_bad('Title inference is not a classification source',fn()=>hosting_storefront_classification_document(array_replace($bound,['source'=>'title-inference'])));
    hss_bad('Noncanonical classification bytes refuse',fn()=>hosting_storefront_classification_data(hosting_storefront_classification_document($bound).' '));
    $publication=['format'=>1,'id'=>str_repeat('e',32),'publication_sha256'=>str_repeat('f',64),'product_id'=>42,'category_id'=>$shared['id'],'category_slug'=>'shared-hosting','category_revision'=>1,'category_sha256'=>hash('sha256',$sharedJson),'classification_id'=>$bound['id'],'classification_sha256'=>hash('sha256',hosting_storefront_classification_document($bound)),'source_format'=>3,'created_by'=>1,'created_at'=>102];
    hss_ok('Publication index document round trips exact immutable identities',hosting_storefront_publication_data(hosting_storefront_publication_document($publication))===$publication);
    hss_bad('Publication index refuses unsupported source format',fn()=>hosting_storefront_publication_document(array_replace($publication,['source_format'=>4])));
} catch(Throwable $e) {$failure=$e::class.': '.$e->getMessage();}
echo json_encode(['passed'=>$failure===null,'checks'=>$checks,'failure'=>$failure],JSON_THROW_ON_ERROR).PHP_EOL;exit($failure===null?0:1);

