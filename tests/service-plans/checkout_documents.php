<?php
/** Pure checkout subscription histories; synthetic terms, actor facts and project IDs only. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../../site');
require CWP_CODE_ROOT.'/app/plans/documents.php';
$checks=[];
function cs_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function cs_bad(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|LogicException|JsonException|TypeError){cs_ok($name,true);return;}cs_ok($name,false);}
function cs_id(int $n):string {return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
cs_ok('Standalone legacy include does not require checkout types',!class_exists('HostingCheckoutConsent',false)&&!function_exists('db'));
require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout_document.php';
function cs_fixture(int $at,string $cadence='monthly',string $timezone='UTC',string $alignment='day-of-month',int $ttl=900,?int $mappingExpiry=null,bool $large=false):array {
    $terms=['name'=>'Reviewed shared hosting','cadence'=>$cadence,'price'=>2500,'currency'=>'USD','inclusions'=>['One reviewed website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during stated business hours'];
    if($large){$terms['inclusions']=array_fill(0,32,str_repeat('x',1000));$terms['exclusions']=array_fill(0,32,str_repeat('y',1000));}
    $plan=ServicePlanDocument::create(str_repeat('b',32),1,$at-50,$terms);
    if($large)for($i=0;$i<6;$i++)$plan=$plan->append(cs_id(1000+$i),'replace_draft',1,$at-49+$i,['terms'=>$terms]);
    $plan=$plan->append(str_repeat('c',32),'activate',1,$at-40,[]);
    $p=['id'=>42,'revision'=>3,'sku'=>'HOST-811','title'=>$terms['name'],'family'=>'hosting','status'=>'draft','term'=>$cadence==='monthly'?'month':'year','currency'=>'USD','amount_cents'=>2500,'setup_cents'=>0,'summary'=>'Reviewed hosting for one website.','features'=>'PRIVATE_DRAFT_FEATURES','terms'=>'PRIVATE_DRAFT_TERMS','media_id'=>str_repeat('e',32)];
    $i=['id'=>7,'product_id'=>42,'profile_id'=>str_repeat('a',32),'source_revision'=>1,'refresh_id'=>str_repeat('2',32),'source_key_fingerprint'=>hash('sha256','private inventory HMAC'),'type_ref'=>'811','type_name'=>'Original observed label','source_hash'=>''];
    $i['source_hash']=hash('sha256',json_encode([$i['profile_id'],$i['source_revision'],$i['refresh_id'],$i['source_key_fingerprint'],$i['type_ref'],$i['type_name']],JSON_THROW_ON_ERROR));
    $o=['refresh_id'=>str_repeat('f',32),'profile_id'=>$i['profile_id'],'profile_revision'=>3,'key_fingerprint'=>$i['source_key_fingerprint'],'observed_epoch'=>$at-20,'types'=>[['id'=>'811','label'=>'Current observed type']]];
    $m=['format'=>1,'id'=>str_repeat('d',32),'plan_id'=>$plan->data()['id'],'revision'=>1,'previous_id'=>null,'previous_sha256'=>null,'plan_revision'=>$plan->revision(),'plan_sha256'=>$plan->fingerprint(),'profile_id'=>$o['profile_id'],'profile_revision'=>3,'credential_sha256'=>hash('sha256','private ciphertext'),'created_by'=>1,'owner_auth'=>'PRIVATE_MAPPING_OWNER_AUTH','created_at'=>$at-30,'expires_at'=>$mappingExpiry??$at+86400*7,'enabled'=>true,'configuration'=>['reseller_id'=>42,'type'=>'811','label'=>'PRIVATE_PROVIDER_LABEL','region'=>'PRIVATE_REGION','limits'=>['websites'=>1],'cost'=>['amount'=>'7.5000','currency'=>'GBP','period_months'=>12,'tax'=>'unknown','basis'=>'shared-account','reference'=>'PRIVATE_WHOLESALE_REFERENCE']],'evidence'=>'owner-account-review'];
    $c=['publication'=>['id'=>str_repeat('1',32),'sha256'=>null,'enabled'=>true],'product'=>['id'=>42,'revision'=>3,'sha256'=>hash('sha256',service_plans_json($p))],
        'plan'=>['id'=>$m['plan_id'],'revision'=>$plan->revision(),'sha256'=>$plan->fingerprint(),'status'=>'active'],'mapping'=>['id'=>$m['id'],'sha256'=>hash('sha256',service_plans_json($m))],
        'profile'=>['id'=>$o['profile_id'],'revision'=>3,'enabled'=>true,'credential_sha256'=>$m['credential_sha256'],'key_fingerprint'=>$o['key_fingerprint']],
        'catalog'=>['refresh_id'=>$o['refresh_id'],'state'=>'complete','profile_id'=>$o['profile_id'],'profile_revision'=>3,'key_fingerprint'=>$o['key_fingerprint'],'observed_epoch'=>$o['observed_epoch'],'types_sha256'=>hosting_catalog_types_hash($o['types'])],
        'publisher'=>['id'=>2,'role'=>'owner','enabled'=>true,'auth_version'=>'PRIVATE_PUBLISHER_AUTH'],'mapping_reviewer'=>['id'=>1,'role'=>'owner','enabled'=>true,'auth_version'=>$m['owner_auth']],'support_session'=>false];
    $policy=['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>$timezone,'alignment'=>$alignment,'review_ttl_seconds'=>$ttl];
    $legacy=HostingCatalogPublication::create(str_repeat('1',32),$at-10,$p,$i,$o,$plan,service_plans_json($m),$c);
    $pub=HostingCatalogPublication::createCheckout(str_repeat('1',32),$at-10,$p,$i,$o,$plan,service_plans_json($m),$c,$policy);$c['publication']['sha256']=$pub->fingerprint();
    return compact('at','terms','plan','p','i','o','m','c','policy','legacy','pub');
}
try {
    $at=1801396800;$f=cs_fixture($at);extract($f);
    $legacy=ServiceSubscriptionDocument::create(cs_id(1),9,2,$at,$plan);
    cs_ok('Legacy creation retains format1 exact stored byte shape',$legacy->json()===service_plans_json(['format'=>1,'id'=>cs_id(1),'client_id'=>9,'created_by'=>2,'created_at'=>$at,'plan'=>$plan->data(),'events'=>[]]));
    $legacy=$legacy->append(cs_id(2),'consent','client:9',$at+1,['terms_hash'=>$plan->model()->termsHash()]);
    cs_bad('Legacy actual client still cannot activate',fn()=>$legacy->append(cs_id(3),'activate','client:9',$at+1,[]));
    cs_bad('Legacy automatic checkout actor cannot activate',fn()=>$legacy->append(cs_id(3),'checkout_activate','system:hosting-checkout',$at+1,[]));
    $legacy=$legacy->append(cs_id(3),'activate','staff:2',$at+2,[]);
    cs_ok('Legacy staff activation and historical bytes remain valid',ServiceSubscriptionDocument::fromJson($legacy->json())->json()===$legacy->json()&&$legacy->model()->history()[0]['actor']==='staff:2');
    $r=HostingCheckoutReview::create(cs_id(10),$pub,9,['mode'=>'new-hosting'],$at,$c);
    $consent=HostingCheckoutConsent::create($r,$r->fingerprint(),true,$pub,$c,9,null,null,$at+1);
    $sub=ServiceSubscriptionDocument::createCheckout(cs_id(11),$consent,$pub,51,cs_id(12),cs_id(13));$d=$sub->data();
    cs_ok('Checkout records actual client creator and retained owner authorizer',$d['created_by_client']===9&&!array_key_exists('created_by',$d)&&$d['checkout']['authorizer']===$pub->data()['publisher']);
    cs_ok('Checkout starts consents and locally activates with distinct truthful actors',array_column($sub->model()->history(),'actor')===['client:9','client:9','system:hosting-checkout']&&array_column($sub->model()->history(),'action')===['start','consent','activate']&&$sub->model()->state()==='active');
    cs_ok('Plan price service terms and composite consent remain exactly bound',$sub->model()->agreedPrice===2500&&$sub->model()->consent()['termsHash']===$plan->model()->termsHash()&&$d['events'][0]['arguments']['checkout_consent_sha256']===$consent->fingerprint()&&$d['plan']===$plan->data());
    cs_ok('Retained publication consent request replay identity and resolved project remain exact',$d['checkout']['publication_json']===$pub->json()&&$d['checkout']['consent_json']===$consent->json()&&$d['checkout']['request_id']===cs_id(10)&&$d['checkout']['request_input_sha256']===$consent->inputHash()&&$d['checkout']['project_id']===51);
    cs_ok('Historical replay needs neither current credential nor current clock',ServiceSubscriptionDocument::fromJson($sub->json())->json()===$sub->json()&&!function_exists('db')&&!function_exists('provider_inventory_refresh'));
    cs_ok('Exact repeated checkout composition is deterministic',ServiceSubscriptionDocument::createCheckout(cs_id(11),$consent,$pub,51,cs_id(12),cs_id(13))->json()===$sub->json());
    cs_bad('Checkout creation rejects repeated initial event identity',fn()=>ServiceSubscriptionDocument::createCheckout(cs_id(11),$consent,$pub,51,cs_id(12),cs_id(12)));
    cs_bad('Resolved new-project fact must be positive',fn()=>ServiceSubscriptionDocument::createCheckout(cs_id(11),$consent,$pub,0,cs_id(12),cs_id(13)));
    $er=HostingCheckoutReview::create(cs_id(20),$pub,9,['mode'=>'existing','id'=>52],$at,$c);
    $ec=HostingCheckoutConsent::create($er,$er->fingerprint(),true,$pub,$c,9,52,9,$at+1);
    cs_ok('Existing project selection is preserved',ServiceSubscriptionDocument::createCheckout(cs_id(21),$ec,$pub,52,cs_id(22),cs_id(23))->data()['checkout']['project_id']===52);
    cs_bad('Existing project cannot be silently substituted',fn()=>ServiceSubscriptionDocument::createCheckout(cs_id(21),$ec,$pub,51,cs_id(22),cs_id(23)));
    cs_bad('Legacy request-only publication cannot substitute for checkout',fn()=>ServiceSubscriptionDocument::createCheckout(cs_id(11),$consent,$f['legacy'],51,cs_id(12),cs_id(13)));
    foreach(['client_id'=>10,'created_by_client'=>2,'created_at'=>$at+2,'format'=>1,'id'=>'invalid','created_by'=>2] as $key=>$value){$bad=$d;$bad[$key]=$value;cs_bad('Changed checkout outer binding refuses '.$key,fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($bad)));}
    foreach(['request_id'=>cs_id(30),'request_input_sha256'=>str_repeat('0',64),'publication_id'=>cs_id(31),'publication_sha256'=>str_repeat('0',64),'consent_sha256'=>str_repeat('0',64),'authorizer'=>['id'=>1,'auth_version'=>'other'],'project_id'=>-1,'publication_json'=>$pub->json().' ','consent_json'=>$consent->json().' '] as $key=>$value){$bad=$d;$bad['checkout'][$key]=$value;cs_bad('Changed retained checkout binding refuses '.$key,fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($bad)));}
    $bad=$d;$bad['plan']['terms']['price']=2600;cs_bad('A separately parseable different plan cannot replace accepted terms',fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($bad)));
    $different=HostingCheckoutConsent::create($r,$r->fingerprint(),true,$pub,$c,9,null,null,$at+2);$bad=$d;$bad['checkout']['consent_json']=$different->json();$bad['checkout']['consent_sha256']=$different->fingerprint();
    cs_bad('Rehashed later consent cannot retain earlier creation and event times',fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($bad)));
    foreach([['actor','staff:2'],['at',$at+2],['command','activate'],['arguments',[]]] as $n=>[$key,$value]){$bad=$d;$bad['events'][0][$key]=$value;cs_bad('Fixed client consent prefix refuses mutation '.$n,fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($bad)));}
    foreach([['actor','staff:2'],['actor','client:9'],['at',$at+2],['command','activate'],['arguments',['consent_event_id'=>cs_id(99),'checkout_consent_sha256'=>$consent->fingerprint()]] ] as $n=>[$key,$value]){$bad=$d;$bad['events'][1][$key]=$value;cs_bad('Fixed automatic effect prefix refuses mutation '.$n,fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($bad)));}
    foreach([[],[$d['events'][0]],[$d['events'][1],$d['events'][0]]] as $n=>$events){$bad=$d;$bad['events']=$events;cs_bad('Missing or reordered fixed prefix refuses '.$n,fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($bad)));}
    cs_bad('Later automatic activation cannot be introduced',fn()=>$sub->append(cs_id(40),'checkout_activate','system:hosting-checkout',$at+2,$d['events'][1]['arguments']));
    cs_bad('Later system actor cannot impersonate staff pause',fn()=>$sub->append(cs_id(40),'pause','system:hosting-checkout',$at+2,[]));
    cs_bad('Later client still cannot pause',fn()=>$sub->append(cs_id(40),'pause','client:9',$at+2,[]));
    cs_bad('Other client cannot cancel',fn()=>$sub->append(cs_id(40),'cancel','client:10',$at+2,['reason'=>'End service']));
    cs_bad('Later event cannot reuse initial consent ID',fn()=>$sub->append(cs_id(12),'pause','staff:2',$at+2,[]));
    cs_bad('Later event cannot precede consent',fn()=>$sub->append(cs_id(40),'pause','staff:2',$at,[]));
    $paused=$sub->append(cs_id(40),'pause','staff:2',$at+2,[]);$resumed=$paused->append(cs_id(41),'resume','staff:1',$at+3,[]);$cancelled=$resumed->append(cs_id(42),'cancel','client:9',$at+4,['reason'=>'End hosting']);
    cs_ok('Ordinary later lifecycle preserves the checkout prefix and original consent',$cancelled->model()->state()==='cancelled'&&array_slice($cancelled->data()['events'],0,2)===$d['events']&&$cancelled->model()->consent()===$sub->model()->consent());
    cs_bad('Cancelled checkout subscription cannot resume',fn()=>$cancelled->append(cs_id(43),'resume','staff:1',$at+5,[]));
    $e=$d['events'][1];cs_ok('Exact activation replay is a no-op even after cancellation',$cancelled->append($e['id'],$e['command'],$e['actor'],$e['at'],$e['arguments'])->json()===$cancelled->json());
    $copy=$sub->model();$copy->plan->retire();cs_ok('Returned mutable models cannot alter retained checkout bytes',$sub->model()->plan->subscribable());
    cs_bad('Unknown top-level fields refuse',fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json($d+['paid'=>true])));
    cs_bad('Duplicate format fields refuse',fn()=>ServiceSubscriptionDocument::fromJson(str_replace('"format":2,','"format":2,"format":2,',$sub->json())));
    cs_bad('Noncanonical top-level order refuses',fn()=>ServiceSubscriptionDocument::fromJson(service_plans_json(array_reverse($d,true))));
    cs_bad('Noncanonical checkout whitespace refuses',fn()=>ServiceSubscriptionDocument::fromJson(' '.$sub->json()));
    cs_bad('Legacy maximum-byte bound remains unchanged',fn()=>ServiceSubscriptionDocument::fromJson('{"format":1,'.str_repeat(' ',ServiceSubscriptionDocument::MAX_BYTES)));
    cs_bad('Separate checkout byte bound refuses oversized input',fn()=>ServiceSubscriptionDocument::fromJson('{"format":2,'.str_repeat(' ',ServiceSubscriptionDocument::MAX_CHECKOUT_BYTES)));
    $lf=cs_fixture($at,'monthly','UTC','day-of-month',900,null,true);$lr=HostingCheckoutReview::create(cs_id(50),$lf['pub'],9,['mode'=>'new-hosting'],$at,$lf['c']);$lc=HostingCheckoutConsent::create($lr,$lr->fingerprint(),true,$lf['pub'],$lf['c'],9,null,null,$at+1);
    $large=ServiceSubscriptionDocument::createCheckout(cs_id(51),$lc,$lf['pub'],51,cs_id(52),cs_id(53));
    cs_ok('Bounded nested checkout can exceed legacy size without relaxing format1',strlen($large->json())>ServiceSubscriptionDocument::MAX_BYTES&&strlen($large->json())<ServiceSubscriptionDocument::MAX_CHECKOUT_BYTES&&ServiceSubscriptionDocument::fromJson($large->json())->json()===$large->json());
    $full=$d;for($i=2;$i<ServiceSubscriptionDocument::MAX_EVENTS-1;$i++)$full['events'][]=['id'=>cs_id(100+$i),'command'=>$i%2===0?'pause':'resume','actor'=>'staff:2','at'=>$at+$i,'arguments'=>[]];
    $bounded=ServiceSubscriptionDocument::fromJson(service_plans_json($full));
    cs_bad('Checkout history retains the original final cancellation reservation',fn()=>$bounded->append(cs_id(900),'resume','staff:2',$at+300,[]));
    $ended=$bounded->append(cs_id(901),'cancel','client:9',$at+300,['reason'=>'End retained service']);
    cs_ok('Client can cancel at the event bound while keeping exact composite consent',$ended->revision()===256&&$ended->data()['checkout']===$d['checkout']&&$ended->model()->state()==='cancelled');
    cs_bad('Checkout cannot exceed original event bound',fn()=>$ended->append(cs_id(902),'cancel','client:9',$at+301,['reason'=>'Again']));
    $report=['passed'=>true,'php'=>PHP_VERSION,'scope'=>'Pure subscription document and synthetic retained checkout bytes only; no native authority, SQL, project ownership, invoice, payment, provider or browser acceptance.','checks'=>$checks];
} catch(Throwable $e) {$report=['passed'=>false,'php'=>PHP_VERSION,'checks'=>$checks,'failure'=>get_class($e).': '.$e->getMessage()];}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
exit($report['passed']?0:1);
