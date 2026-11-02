<?php
/** Focused pure checkout behavior; all identities, times and authority facts are synthetic. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site');
require __DIR__.'/../site/app/hosting/catalog_checkout_document.php';
require CWP_CODE_ROOT.'/includes/foundation/recurring-billing/RecurringBillingSchedule.php';
use CajunWebPro\Foundation\BillingCalendar\BillingCalendar;
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
$checks=[];
function co_ok(string $name,bool $passed): void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function co_bad(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException|TypeError|JsonException){co_ok($name,true);return;}co_ok($name,false);}
function co_epoch(string $date):int {return (new DateTimeImmutable($date))->getTimestamp();}
function co_fixture(int $at,string $cadence='monthly',string $timezone='UTC',string $alignment='day-of-month',int $ttl=900,?int $mappingExpiry=null):array {
    $terms=['name'=>'Reviewed shared hosting','cadence'=>$cadence,'price'=>2500,'currency'=>'USD','inclusions'=>['One reviewed website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during stated business hours'];
    $plan=ServicePlanDocument::create(str_repeat('b',32),1,$at-50,$terms)->append(str_repeat('c',32),'activate',1,$at-49,[]);
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
function co_review(array $f,array $project=['mode'=>'new-hosting']):HostingCheckoutReview {return HostingCheckoutReview::create(str_repeat('3',32),$f['pub'],9,$project,$f['at'],$f['c']);}
function co_consent(HostingCheckoutReview $r,array $f,int $at,?int $project=null,?int $owner=null):HostingCheckoutConsent {return HostingCheckoutConsent::create($r,$r->fingerprint(),true,$f['pub'],$f['c'],9,$project,$owner,$at);}
try {
    $f=co_fixture(co_epoch('2027-01-31T12:00:00Z'));extract($f);$r=co_review($f);$d=$r->data();$consent=co_consent($r,$f,$at+1);
    $old=$pub->data();unset($old['checkout_policy']);$old['format']=1;
    co_ok('Version1 publication bytes are the unchanged original document',service_plans_json($old)===$legacy->json()&&HostingCatalogPublication::fromJson($legacy->json())->json()===$legacy->json());
    $lc=$c;$lc['publication']['sha256']=$legacy->fingerprint();$lr=HostingCatalogRequest::create(str_repeat('4',32),$legacy,['id'=>9,'active'=>true,'support_session'=>false],$at,$lc);
    co_ok('Version1 request bytes and request-only public fields remain exact',$lr->json()===service_plans_json(['format'=>1,'id'=>str_repeat('4',32),'publication_id'=>$legacy->data()['id'],'publication_sha256'=>$legacy->fingerprint(),'client_id'=>9,'quantity'=>1,'created_at'=>$at])&&!array_key_exists('checkout_policy',$legacy->publicProjection($lc,$at)));
    co_bad('A legacy request-only publication cannot become checkout implicitly',fn()=>HostingCheckoutReview::create(str_repeat('3',32),$legacy,9,['mode'=>'new-hosting'],$at,$lc));
    co_ok('Version2 publication retains exact private source and explicit opt-in',HostingCatalogPublication::fromJson($pub->json())->json()===$pub->json()&&$pub->checkoutPolicy()===$policy&&$pub->data()['mapping_json']===$legacy->data()['mapping_json']);
    $reordered=array_reverse($policy,true);$pc=$c;$pc['publication']['sha256']=null;
    co_ok('Owner policy input has one canonical closed order',HostingCatalogPublication::createCheckout(str_repeat('1',32),$at-10,$p,$i,$o,$plan,service_plans_json($m),$pc,$reordered)->json()===$pub->json());
    foreach(['mode'=>'manual-review','client'=>'contact','project'=>'any','collection'=>'card','first_period'=>'prorated','requires_verified_payment'=>false,'requires_owner_provisioning'=>false,'timezone'=>'+05:30','alignment'=>'nearest-day','review_ttl_seconds'=>901,'arbitrary_authority'=>true] as $k=>$v)co_bad('Unsupported policy refuses '.$k,fn()=>hosting_checkout_policy(array_replace($policy,[$k=>$v])));
    foreach([0,-1,1.5,'60'] as $n=>$v)co_bad('Invalid review lifetime type or range '.$n,fn()=>hosting_checkout_policy(array_replace($policy,['review_ttl_seconds'=>$v])));
    co_ok('One-second and fifteen-minute policy bounds are accepted',hosting_checkout_policy(array_replace($policy,['review_ttl_seconds'=>1]))['review_ttl_seconds']===1&&hosting_checkout_policy($policy)['review_ttl_seconds']===900);
    $missing=$pub->data();unset($missing['checkout_policy']);co_bad('Version2 publication cannot lose its policy',fn()=>HostingCatalogPublication::fromJson(service_plans_json($missing)));
    $extra=$legacy->data();$extra['checkout_policy']=$policy;co_bad('Version1 publication cannot acquire checkout by an extra field',fn()=>HostingCatalogPublication::fromJson(service_plans_json($extra)));
    co_ok('Review binds original publication exact plan stable request and client',$d['publication_sha256']===$pub->fingerprint()&&$d['plan']['json']===$plan->json()&&$d['plan']['sha256']===$plan->fingerprint()&&$d['request_id']===str_repeat('3',32)&&$d['client_id']===9&&$d['quantity']===1);
    co_ok('First invoice facts are full January31 period due today without proration',$d['billing']['first_period']===['index'=>0,'start'=>'2027-01-31','end'=>'2027-02-28','due'=>'2027-01-31','next_start'=>'2027-02-28']&&$d['billing']['amount_cents']===2500&&$d['billing']['setup_cents']===0);
    co_ok('Effects retain unpaid invoice and required verified payment plus owner fulfillment',$d['effects']===['agreement'=>'activate-after-consent','first_invoice'=>'unpaid','automatic_charge'=>false,'provider_activation'=>false,'requires_verified_payment'=>true,'requires_owner_provisioning'=>true]);
    $safe=$r->publicProjection($c,9,null,null,$at);$display=service_plans_json($safe);
    co_ok('Client payload has only the closed commercial projection',array_keys($safe)===['request_id','review_sha256','offer','project','billing','effects','reviewed_at','expires_at']&&!str_contains($display,'plan_json')&&!str_contains($display,'mapping_json')&&!str_contains($display,'"plan":')&&!str_contains($display,'"client_id":'));
    foreach(['PRIVATE_DRAFT_FEATURES','PRIVATE_DRAFT_TERMS','PRIVATE_WHOLESALE_REFERENCE','PRIVATE_MAPPING_OWNER_AUTH','PRIVATE_PUBLISHER_AUTH','PRIVATE_PROVIDER_LABEL','PRIVATE_REGION',$m['credential_sha256'],$i['source_key_fingerprint'],$plan->json()] as $n=>$private)co_ok('Commercial projection excludes private retained value '.$n,!str_contains($display,$private));
    co_ok('Rendered terms come from the exact plan and local image reference',$safe['offer']['inclusions']===$terms['inclusions']&&$safe['offer']['support']===$terms['support']&&$safe['offer']['image_id']===$p['media_id']);
    foreach([['mode'=>'existing'],['mode'=>'existing','id'=>0],['mode'=>'existing','id'=>'1'],['mode'=>'new-hosting','id'=>1],['mode'=>'new-hosting','title'=>'Arbitrary project'],['mode'=>'foreign']] as $n=>$v)co_bad('Malformed project instruction refused '.$n,fn()=>co_review($f,$v));
    co_bad('Invalid client ID refused before review',fn()=>HostingCheckoutReview::create(str_repeat('3',32),$pub,0,['mode'=>'new-hosting'],$at,$c));
    $existing=co_review($f,['mode'=>'existing','id'=>51]);$accepted=co_consent($existing,$f,$at+2,51,9);
    co_ok('Existing same-client project is bound without generating another project',$accepted->review()->data()['project']===['mode'=>'existing','id'=>51]);
    co_bad('Foreign locked project ownership refuses consent',fn()=>co_consent($existing,$f,$at+2,51,10));
    co_bad('Substituted locked project refuses consent',fn()=>co_consent($existing,$f,$at+2,52,9));
    co_bad('Missing locked project refuses consent',fn()=>co_consent($existing,$f,$at+2));
    co_bad('New-project instruction refuses substitution before acceptance',fn()=>co_consent($r,$f,$at+2,51,9));
    co_bad('Different authenticated client refuses consent',fn()=>HostingCheckoutConsent::create($r,$r->fingerprint(),true,$pub,$c,10,null,null,$at+1));
    co_bad('Unchecked composite terms refuse consent',fn()=>HostingCheckoutConsent::create($r,$r->fingerprint(),false,$pub,$c,9,null,null,$at+1));
    co_bad('Different displayed composite hash refuses consent',fn()=>HostingCheckoutConsent::create($r,str_repeat('0',64),true,$pub,$c,9,null,null,$at+1));
    co_bad('Acceptance before review refuses',fn()=>co_consent($r,$f,$at-1));
    co_bad('Exact TTL expiry boundary refuses',fn()=>co_consent($r,$f,$at+900));
    co_ok('Last second before TTL expiration can be confirmed',co_consent($r,$f,$at+899)->data()['accepted_at']===$at+899);
    foreach([['publication','enabled',false],['product','revision',4],['plan','status','retired'],['mapping','sha256',str_repeat('0',64)],['profile','enabled',false],['profile','credential_sha256',str_repeat('0',64)],['catalog','state','pending'],['catalog','state','failed'],['publisher','enabled',false],['publisher','auth_version','revoked'],['mapping_reviewer','role','administrator']] as $n=>[$section,$key,$value]){
        $changed=$f;$changed['c'][$section][$key]=$value;co_bad('Confirmation reload rejects changed source or authority '.$n,fn()=>co_consent($r,$changed,$at+1));
    }
    $changed=$f;$changed['c']['support_session']=true;co_bad('Existing current-context support exclusion remains enforced',fn()=>co_consent($r,$changed,$at+1));
    $changed=$f;$changed['c']['allow_checkout']=true;co_bad('Arbitrary authority input cannot bypass the closed current context',fn()=>co_consent($r,$changed,$at+1));
    foreach(['format'=>1,'kind'=>'invoice','request_id'=>'bad','publication_sha256'=>str_repeat('0',64),'client_id'=>0,'quantity'=>2,'expires_at'=>$at+901] as $key=>$value){$bad=$d;$bad[$key]=$value;co_bad('Tampered review field refused '.$key,fn()=>HostingCheckoutReview::fromJson(service_plans_json($bad),$pub));}
    foreach([['billing','amount_cents',2501],['billing','currency','GBP'],['billing','anchor','2027-02-01'],['billing','due_after_days',1],['effects','requires_verified_payment',false],['effects','automatic_charge',true],['plan','sha256',str_repeat('0',64)]] as $n=>[$section,$key,$value]){$bad=$d;$bad[$section][$key]=$value;co_bad('Tampered commercial or plan fact refused '.$n,fn()=>HostingCheckoutReview::fromJson(service_plans_json($bad),$pub));}
    foreach(['index'=>1,'start'=>'2027-01-30','end'=>'2027-03-01','due'=>'2027-02-01','next_start'=>'2027-03-01'] as $key=>$value){$bad=$d;$bad['billing']['first_period'][$key]=$value;co_bad('Tampered first-period calendar refuses '.$key,fn()=>HostingCheckoutReview::fromJson(service_plans_json($bad),$pub));}
    $different=$d;$different['request_id']=str_repeat('5',32);$differentReview=HostingCheckoutReview::fromJson(service_plans_json($different),$pub);
    co_ok('A separately valid request identity has a distinct commercial fingerprint',$differentReview->fingerprint()!==$r->fingerprint());
    co_bad('A changed request identity cannot reuse the originally displayed hash',fn()=>HostingCheckoutConsent::create($differentReview,$r->fingerprint(),true,$pub,$c,9,null,null,$at+1));
    $differentClient=$d;$differentClient['client_id']=10;$clientReview=HostingCheckoutReview::fromJson(service_plans_json($differentClient),$pub);
    co_ok('A separately valid client identity has a distinct review fingerprint',$clientReview->fingerprint()!==$r->fingerprint());
    co_bad('Original authenticated client cannot confirm a rewritten client identity',fn()=>$clientReview->assertForConfirmation($pub,$c,9,null,null,$at+1));
    co_bad('Review whitespace is not canonical',fn()=>HostingCheckoutReview::fromJson(' '.$r->json(),$pub));
    co_bad('Unknown review fields are refused',fn()=>HostingCheckoutReview::fromJson(service_plans_json($d+['invoice_paid'=>true]),$pub));
    co_bad('Oversized review is refused',fn()=>HostingCheckoutReview::fromJson(str_repeat(' ',HostingCheckoutReview::MAX_BYTES+1),$pub));
    $night=co_fixture(co_epoch('2027-03-14T04:59:50Z'),'monthly','America/New_York');$nr=co_review($night);
    co_ok('Review expires at local midnight before nominal TTL',$nr->data()['billing']['anchor']==='2027-03-13'&&$nr->data()['expires_at']===co_epoch('2027-03-14T05:00:00Z'));
    co_bad('Date rollover refuses without silently rebasing the billing period',fn()=>co_consent($nr,$night,co_epoch('2027-03-14T05:00:00Z')));
    $shift=co_fixture(co_epoch('2027-09-05T23:59:50-03:00'),'monthly','America/Santiago');$shiftReview=co_review($shift);
    co_ok('A skipped midnight today does not extend review into tomorrow',$shiftReview->data()['expires_at']===co_epoch('2027-09-06T00:00:00-03:00'));
    co_ok('Santiago last same-date second remains confirmable',co_consent($shiftReview,$shift,$shift['at']+9)->data()['accepted_at']===$shift['at']+9);
    co_bad('Santiago next-date confirmation refuses even after midnight normalization',fn()=>co_consent($shiftReview,$shift,$shift['at']+10));
    $rewritten=co_consent($shiftReview,$shift,$shift['at']+1)->data();$rewritten['accepted_at']=$shift['at']+20;
    co_bad('Historical consent refuses a rewritten next-local-date acceptance',fn()=>HostingCheckoutConsent::fromJson(service_plans_json($rewritten),$shift['pub']));
    $apia=co_fixture(co_epoch('2011-12-29T23:59:50-10:00'),'monthly','Pacific/Apia');$apiaReview=co_review($apia);
    co_ok('Skipped calendar day ends review at the next actual local date',$apiaReview->data()['expires_at']===co_epoch('2011-12-31T00:00:00+14:00')&&$apiaReview->data()['billing']['first_period']['start']==='2011-12-29');
    co_bad('Apia skipped-date boundary cannot silently rebase consent',fn()=>co_consent($apiaReview,$apia,$apia['at']+10));
    co_ok('Review on the next actual Apia day uses that explicitly reviewed date',co_review(co_fixture($apia['at']+20,'monthly','Pacific/Apia'))->data()['billing']['anchor']==='2011-12-31');
    $dst=co_fixture(co_epoch('2027-03-14T06:59:59Z'),'monthly','America/New_York');$dr=co_review($dst);
    co_ok('DST spring transition preserves the same local date and full period',co_consent($dr,$dst,$dst['at']+2)->review()->data()['billing']['first_period']['end']==='2027-04-14');
    $fall=co_fixture(co_epoch('2027-11-07T05:59:59Z'),'monthly','America/New_York');co_ok('DST repeated hour remains an epoch-bounded review',co_consent(co_review($fall),$fall,$fall['at']+2)->data()['accepted_at']===$fall['at']+2);
    $leap=co_fixture(co_epoch('2028-02-29T12:00:00Z'),'annually');$ly=co_review($leap);co_ok('Annual leap-day first full period ends exclusively next February28',$ly->data()['billing']['first_period']['end']==='2029-02-28'&&$ly->data()['billing']['amount_cents']===2500);
    $calendar=new BillingCalendar('2028-02-29','annually','day-of-month');co_ok('Unchanged original-anchor calendar returns leap day in the fourth year',$calendar->period(4)['start']==='2032-02-29');
    $monthEnd=co_fixture($at,'monthly','UTC','month-end');co_ok('Month-end policy uses the unchanged calendar',$r->data()['billing']['first_period']===co_review($monthEnd)->data()['billing']['first_period']);
    $notEnd=co_fixture(co_epoch('2027-01-30T12:00:00Z'),'monthly','UTC','month-end');co_bad('Month-end policy never rebases a non-month-end checkout',fn()=>co_review($notEnd));
    $short=co_fixture($at,'monthly','UTC','day-of-month',900,$at+10);co_ok('Mapping expiration bounds review lifetime',co_review($short)->data()['expires_at']===$at+10);co_bad('Mapping expiry refuses at exact boundary',fn()=>co_consent(co_review($short),$short,$at+10));
    co_ok('Historical consent survives later expiry and credential changes without reauthorization',HostingCheckoutConsent::fromJson($consent->json(),$pub)->json()===$consent->json());
    $consent->assertClient(9);co_bad('Historical consent cannot be read as another client',fn()=>$consent->assertClient(10));
    $later=co_consent($r,$f,$at+2);co_ok('Replay input retains all reviewed terms but excludes acceptance instant',$later->inputHash()===$consent->inputHash()&&$later->fingerprint()!==$consent->fingerprint());
    co_ok('Changed project under the same stable request has different replay input',$accepted->inputHash()!==$consent->inputHash());
    $newReview=HostingCheckoutReview::create(str_repeat('3',32),$pub,9,['mode'=>'new-hosting'],$at+3,$c);co_ok('Re-review under same request cannot silently replace old consent input',co_consent($newReview,$f,$at+4)->inputHash()!==$consent->inputHash());
    foreach(['request_id'=>str_repeat('6',32),'client_id'=>10,'review_sha256'=>str_repeat('0',64),'accepted_at'=>$at+900,'format'=>1,'extra'=>'bad'] as $key=>$value){$bad=$consent->data();$bad[$key]=$value;co_bad('Tampered consent binding refused '.$key,fn()=>HostingCheckoutConsent::fromJson(service_plans_json($bad),$pub));}
    co_bad('Noncanonical consent whitespace refused',fn()=>HostingCheckoutConsent::fromJson($consent->json().' ',$pub));
    co_ok('Historical consent contains no client-password dependency',!str_contains($consent->json(),'client_auth')&&!str_contains($consent->json(),'password'));
    $rt=RecurringBillingTerms::fromArray(['format'=>1,'id'=>str_repeat('7',32),'client_id'=>9,'project_id'=>51,'source_subscription'=>['id'=>str_repeat('8',32),'revision'=>1,'sha256'=>str_repeat('a',64)],'title'=>'Legacy billing','terms'=>'Exact legacy terms','currency'=>'USD','amount_cents'=>2500,'cadence'=>'monthly','anchor'=>'2027-01-31','alignment'=>'day-of-month','timezone'=>'UTC','due_after_days'=>0,'collection'=>'invoice-only']);
    $schedule=RecurringBillingSchedule::create($rt,1,$at-5)->append(str_repeat('9',32),'accept','client:9',$at-4,['terms_sha256'=>$rt->fingerprint()]);
    co_bad('Existing format1 schedule still refuses same-day activation',fn()=>$schedule->append(str_repeat('a',32),'activate','staff:1',$at,['period_index'=>0]));
    co_ok('Existing format1 schedule retains its future-period activation path',$schedule->append(str_repeat('a',32),'activate','staff:1',$at,['period_index'=>1])->phase()==='running');
    co_bad('Checkout publication cannot create a legacy request',fn()=>HostingCatalogRequest::create(str_repeat('4',32),$pub,['id'=>9,'active'=>true,'support_session'=>false],$at,$c));
    $legacyShaped=HostingCatalogRequest::fromJson(service_plans_json(['format'=>1,'id'=>str_repeat('4',32),'publication_id'=>$pub->data()['id'],'publication_sha256'=>$pub->fingerprint(),'client_id'=>9,'quantity'=>1,'created_at'=>$at]));
    co_bad('Stored legacy-shaped request cannot convert a checkout publication',fn()=>$legacyShaped->assertPublication($pub));
    co_ok('Pure checkout loading introduces no database or provider-call function',!function_exists('db')&&!function_exists('provider_inventory_refresh'));
    $report=['passed'=>true,'scope'=>'Pure synthetic documents and exact unchanged billing calendar only; no native authority, SQL, invoice, payment, provider, HTTP or checkout acceptance.','php'=>PHP_VERSION,'checks'=>$checks];
}catch(Throwable $e){$report=['passed'=>false,'scope'=>'Pure synthetic checkout documents only.','php'=>PHP_VERSION,'checks'=>$checks,'failure'=>get_class($e).': '.$e->getMessage()];}
if(isset($argv[1]))file_put_contents($argv[1],json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo ($report['passed']?'PASS':'FAIL').': '.count($checks).' hosting checkout document checks'.PHP_EOL;
if(!$report['passed']){fwrite(STDERR,$report['failure'].PHP_EOL);exit(1);}
