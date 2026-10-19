<?php
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site');
require CWP_CODE_ROOT.'/app/hosting/catalog_offer_setup_document.php';
$checks=[];
function hps_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function hps_bad(string $name,callable $call):void {try{$call();}catch(InvalidArgumentException|RuntimeException|TypeError|JsonException){hps_ok($name,true);return;}hps_ok($name,false);}
try {
    $product=['id'=>42,'revision'=>3,'sku'=>'HOST-811','title'=>'Reviewed hosting','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount_cents'=>2500,'setup_cents'=>0,'summary'=>'Hosting for one website.','features'=>'One website','terms'=>'Reviewed published terms','media_id'=>str_repeat('e',32)];
    $import=['id'=>7,'product_id'=>42,'profile_id'=>str_repeat('a',32),'source_revision'=>1,'refresh_id'=>str_repeat('2',32),'source_key_fingerprint'=>hash('sha256','synthetic inventory HMAC'),'type_ref'=>'811','type_name'=>'Original type name','source_hash'=>''];
    $import['source_hash']=hash('sha256',json_encode([$import['profile_id'],$import['source_revision'],$import['refresh_id'],$import['source_key_fingerprint'],$import['type_ref'],$import['type_name']],JSON_THROW_ON_ERROR));
    $observation=['refresh_id'=>str_repeat('f',32),'profile_id'=>$import['profile_id'],'profile_revision'=>3,'key_fingerprint'=>$import['source_key_fingerprint'],'observed_epoch'=>120,'types'=>[['id'=>'99','label'=>'Other type'],['id'=>'811','label'=>'Current provider label']]];
    $profile=['id'=>$import['profile_id'],'revision'=>3,'enabled'=>true,'credential_sha256'=>hash('sha256','synthetic encrypted bytes'),'key_fingerprint'=>$import['source_key_fingerprint']];
    $owner=['id'=>1,'auth_version'=>'owner-auth-version'];
    $review=hosting_catalog_setup_review($product,$import,$observation,$profile,$owner,2);
    hps_ok('Review preserves exact saved product and current provider label',$review['product']===$product&&$review['package']===['type'=>'811','label'=>'Current provider label']);
    hps_ok('Review exposes only product package and opaque digest',array_keys($review)===['product','package','review_sha256']&&strlen($review['review_sha256'])===64);
    $bytes=service_plans_json($review);
    foreach ([$profile['credential_sha256'],$profile['key_fingerprint'],$profile['id'],$owner['auth_version']] as $n=>$private) hps_ok('Private authority field stays out of review '.$n,!str_contains($bytes,$private));
    hps_ok('Same exact review is stable',hosting_catalog_setup_review($product,$import,$observation,$profile,$owner,2)===$review);
    hps_ok('Image replaced then restored still invalidates the old review',hosting_catalog_setup_review($product,$import,$observation,$profile,$owner,4)['review_sha256']!==$review['review_sha256']);
    foreach (['title'=>'Renamed hosting','revision'=>4,'amount_cents'=>2600,'term'=>'year','summary'=>'Changed summary','features'=>'Changed features','terms'=>'Changed terms','media_id'=>null] as $key=>$value) {
        hps_ok('Changed saved '.$key.' invalidates setup',hosting_catalog_setup_review(array_replace($product,[$key=>$value]),$import,$observation,$profile,$owner,2)['review_sha256']!==$review['review_sha256']);
    }
    foreach (['refresh_id'=>str_repeat('3',32),'observed_epoch'=>121,'types'=>[['id'=>'811','label'=>'New current label']]] as $key=>$value) {
        hps_ok('Changed catalog '.$key.' invalidates setup',hosting_catalog_setup_review($product,$import,array_replace($observation,[$key=>$value]),$profile,$owner,2)['review_sha256']!==$review['review_sha256']);
    }
    hps_ok('Changed owner authentication invalidates setup',hosting_catalog_setup_review($product,$import,$observation,$profile,['id'=>1,'auth_version'=>'changed-auth'],2)['review_sha256']!==$review['review_sha256']);
    hps_ok('Changed owner identity invalidates setup',hosting_catalog_setup_review($product,$import,$observation,$profile,['id'=>2,'auth_version'=>$owner['auth_version']],2)['review_sha256']!==$review['review_sha256']);
    hps_ok('Changed encrypted credential bytes invalidate setup',hosting_catalog_setup_review($product,$import,$observation,array_replace($profile,['credential_sha256'=>hash('sha256','changed ciphertext')]),$owner,2)['review_sha256']!==$review['review_sha256']);
    foreach (['id'=>str_repeat('b',32),'revision'=>4,'enabled'=>false,'key_fingerprint'=>hash('sha256','other key')] as $key=>$value) {
        hps_bad('Reject mismatched current profile '.$key,fn()=>hosting_catalog_setup_review($product,$import,$observation,array_replace($profile,[$key=>$value]),$owner,2));
    }
    hps_bad('Reject another imported product',fn()=>hosting_catalog_setup_review(array_replace($product,['id'=>43]),$import,$observation,$profile,$owner,2));
    hps_bad('Reject removed provider type',fn()=>hosting_catalog_setup_review($product,$import,array_replace($observation,['types'=>[['id'=>'99','label'=>'Other type']]]),$profile,$owner,2));
    hps_bad('Reject forged original import hash',fn()=>hosting_catalog_setup_review($product,array_replace($import,['type_ref'=>'99']),$observation,$profile,$owner,2));
    foreach ([-1,2147483648] as $revision) hps_bad('Reject invalid image revision '.$revision,fn()=>hosting_catalog_setup_review($product,$import,$observation,$profile,$owner,$revision));
    $details=['inclusions'=>['One website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during business hours'];
    $terms=hosting_catalog_setup_terms($product,$details);
    hps_ok('Saved product supplies exact price name currency and cadence',$terms===['name'=>'Reviewed hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD',...$details]);
    hps_ok('Yearly product supplies annual plan cadence',hosting_catalog_setup_terms(array_replace($product,['term'=>'year']),$details)['cadence']==='annually');
    foreach (['name'=>'Forged','price'=>1,'currency'=>'EUR','cadence'=>'annually','type'=>'99'] as $key=>$value) hps_bad('Reject posted commercial or provider override '.$key,fn()=>hosting_catalog_setup_terms($product,$details+[$key=>$value]));
    foreach (['title'=>str_repeat('x',121),'amount_cents'=>0,'term'=>'once','currency'=>'EUR','setup_cents'=>100,'status'=>'active','family'=>'software'] as $key=>$value) hps_bad('Reject unsupported saved product '.$key,fn()=>hosting_catalog_setup_terms(array_replace($product,[$key=>$value]),$details));
    hps_bad('Reject unstructured inclusion list',fn()=>hosting_catalog_setup_terms($product,array_replace($details,['inclusions'=>'included'])));
    hps_bad('Reject negative service limit',fn()=>hosting_catalog_setup_terms($product,array_replace($details,['limits'=>['websites'=>-1]])));
    $config=['reseller_id'=>10,'label'=>'Owner reviewed package','region'=>'Owner reviewed region','limits'=>['websites'=>1,'mailboxes'=>0],'cost'=>null];
    $mapped=hosting_catalog_setup_configuration($import,$config);
    hps_ok('Imported type is added to explicit mapping',$mapped['type']==='811'&&$mapped['reseller_id']===10&&$mapped['region']===$config['region']);
    hps_ok('Zero limit and unknown cost remain literal',$mapped['limits']['mailboxes']===0&&$mapped['cost']===null);
    foreach (['type'=>'99','profile_id'=>str_repeat('b',32),'price'=>1] as $key=>$value) hps_bad('Reject mapping identity or retail override '.$key,fn()=>hosting_catalog_setup_configuration($import,$config+[$key=>$value]));
    foreach (['reseller_id'=>0,'limits'=>[],'region'=>'','cost'=>['amount'=>'4.50']] as $key=>$value) hps_bad('Reject incomplete provider review '.$key,fn()=>hosting_catalog_setup_configuration($import,array_replace($config,[$key=>$value])));
    $cost=['amount'=>'7.5000','currency'=>'GBP','period_months'=>12,'tax'=>'unknown','basis'=>'shared-account','reference'=>'Reviewed account statement'];
    hps_ok('Exact private cost stays separate from retail amount',hosting_catalog_setup_configuration($import,array_replace($config,['cost'=>$cost]))['cost']===$cost&&$terms['price']===2500);
    $plan=ServicePlanDocument::create(str_repeat('1',32),1,200,$terms)->append(str_repeat('2',32),'activate',1,200,[]);
    hps_ok('Copied terms create an active matching existing service plan',$plan->model()->status()==='active'&&$plan->model()->name===$product['title']&&$plan->model()->priceAmount===$product['amount_cents']);
} catch (Throwable $error) {$failure=['class'=>get_class($error),'message'=>$error->getMessage()];}
$result=['passed'=>!isset($failure),'checks'=>$checks,'failure'=>$failure??null,'scope'=>'Pure imported-plan review, copied terms and explicit provider mapping; no SQL or provider execution'];
$path=$argv[1]??(getenv('CWP_TEST_RESULT')?:__DIR__.'/../qa/hosting-catalog-plan-setup-document-results.json');
if (!is_dir(dirname($path))) mkdir(dirname($path),0777,true);
file_put_contents($path,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo ($result['passed']?'PASS':'FAIL').': '.count($checks).' imported hosting plan document checks'.PHP_EOL;
if (isset($failure)) fwrite(STDERR,$failure['class'].': '.$failure['message'].PHP_EOL);
exit($result['passed']?0:1);
