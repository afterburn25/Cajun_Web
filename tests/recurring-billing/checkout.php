<?php
/** Pure recurring checkout binding and calendar; all identities and clocks are synthetic. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../../site');
require CWP_CODE_ROOT.'/app/hosting/catalog_checkout_billing_document.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms as Terms,RecurringBillingSchedule as Schedule};
$checks=[];
function rb_ok(string $name,bool $passed): void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function rb_bad(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException|TypeError|JsonException){rb_ok($name,true);return;}rb_ok($name,false);}
function rb_epoch(string $date):int {return (new DateTimeImmutable($date))->getTimestamp();}
function rb_fixture(int $at,string $cadence='monthly',string $timezone='UTC',string $alignment='day-of-month',int $ttl=900,?int $mappingExpiry=null):array {
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
function rb_review(array $f,array $project=['mode'=>'new-hosting']):HostingCheckoutReview {return HostingCheckoutReview::create(str_repeat('3',32),$f['pub'],9,$project,$f['at'],$f['c']);}
function rb_consent(HostingCheckoutReview $r,array $f,int $at,?int $project=null,?int $owner=null):HostingCheckoutConsent {return HostingCheckoutConsent::create($r,$r->fingerprint(),true,$f['pub'],$f['c'],9,$project,$owner,$at);}
function rb_id(int $n):string {return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function rb_sub(array $f,?HostingCheckoutConsent $consent=null,int $project=51):ServiceSubscriptionDocument {
    $r=rb_review($f);$consent??=rb_consent($r,$f,$f['at']+1);
    return ServiceSubscriptionDocument::createCheckout(rb_id(70),$consent,$f['pub'],$project,rb_id(71),rb_id(72));
}
function rb_bound(Terms $t,ServiceSubscriptionDocument $s):bool {hosting_checkout_billing_assert($t,$s);return true;}
try {
    $f=rb_fixture(rb_epoch('2027-01-31T12:00:00Z'));$sub=rb_sub($f);$at=$sub->data()['created_at'];
    $terms=hosting_checkout_billing_terms(rb_id(80),$sub);$d=$terms->data();
    $r=HostingCheckoutConsent::fromJson($sub->data()['checkout']['consent_json'],$f['pub'])->review()->data();
    rb_ok('Terms pin the exact two-event subscription prefix',$d['source_subscription']===['id'=>rb_id(70),'revision'=>2,'sha256'=>$sub->fingerprint()]);
    rb_ok('Amount currency cadence timezone anchor due and project derive from exact composite',$d['amount_cents']===$r['billing']['amount_cents']&&$d['currency']===$r['billing']['currency']&&$d['cadence']===$r['billing']['cadence']&&$d['timezone']===$r['billing']['timezone']&&$d['anchor']===$r['billing']['anchor']&&$d['due_after_days']===0&&$d['project_id']===51);
    $o=$d['checkout_origin'];$c=HostingCheckoutConsent::fromJson($sub->data()['checkout']['consent_json'],$f['pub']);
    rb_ok('Origin binds request replay input publication review consent and accepted instant',$o===['request_id'=>$c->data()['request_id'],'request_input_sha256'=>$c->inputHash(),'publication_id'=>$f['pub']->data()['id'],'publication_sha256'=>$f['pub']->fingerprint(),'review_sha256'=>$c->review()->fingerprint(),'consent_sha256'=>$c->fingerprint(),'accepted_at'=>$at]);
    rb_ok('Display text comes from retained safe title and summary',$d['title']===$f['pub']->historicalProjection()['title']&&str_starts_with($d['terms'],$f['pub']->historicalProjection()['summary']));
    rb_ok('Billing document omits private provider price credential owner and draft terms',!str_contains($terms->json(),'PRIVATE_')&&!str_contains($terms->json(),'mapping_json')&&!str_contains($terms->json(),'owner_auth')&&!str_contains($terms->json(),'plan_json'));
    rb_ok('Canonical terms preserve exact historical bytes and binding',Terms::fromJson($terms->json())->json()===$terms->json()&&rb_bound($terms,$sub));
    $reversed=array_reverse($d,true);$reversed['checkout_origin']=array_reverse($o,true);
    rb_ok('Array input canonicalizes closed origin ordering',Terms::fromArray($reversed)->json()===$terms->json());
    rb_bad('Noncanonical terms JSON refuses whitespace',fn()=>Terms::fromJson($terms->json().' '));
    foreach(['amount_cents'=>2600,'title'=>'Other title','terms'=>'Other service terms','client_id'=>10,'project_id'=>52,'cadence'=>'annually','timezone'=>'Etc/UTC','alignment'=>'month-end'] as $key=>$value){$bad=$d;$bad[$key]=$value;rb_bad('Valid structural commercial substitution cannot bind '.$key,fn()=>hosting_checkout_billing_assert(Terms::fromArray($bad),$sub));}
    foreach(['currency'=>'GBP','due_after_days'=>1,'anchor'=>'2027-02-01','amount_cents'=>0,'cadence'=>'quarterly','collection'=>'automatic'] as $key=>$value){$bad=$d;$bad[$key]=$value;rb_bad('Unsupported checkout structural term refuses '.$key,fn()=>Terms::fromArray($bad));}
    foreach(['id'=>rb_id(90),'sha256'=>str_repeat('0',64)] as $key=>$value){$bad=$d;$bad['source_subscription'][$key]=$value;rb_bad('Different retained subscription cannot bind '.$key,fn()=>hosting_checkout_billing_assert(Terms::fromArray($bad),$sub));}
    $bad=$d;$bad['source_subscription']['revision']=3;rb_bad('Later revision cannot substitute for fixed active prefix',fn()=>Terms::fromArray($bad));
    foreach(['request_id'=>rb_id(91),'request_input_sha256'=>str_repeat('0',64),'publication_id'=>rb_id(92),'publication_sha256'=>str_repeat('0',64),'review_sha256'=>str_repeat('0',64),'consent_sha256'=>str_repeat('0',64),'accepted_at'=>$at+1] as $key=>$value){$bad=$d;$bad['checkout_origin'][$key]=$value;rb_bad('Different valid origin cannot bind '.$key,fn()=>hosting_checkout_billing_assert(Terms::fromArray($bad),$sub));}
    foreach(['request_id'=>'BAD','accepted_at'=>(string)$at,'consent_sha256'=>'bad','extra'=>'value'] as $key=>$value){$bad=$d;$bad['checkout_origin'][$key]=$value;rb_bad('Malformed origin refuses '.$key,fn()=>Terms::fromArray($bad));}
    $later=$sub->append(rb_id(73),'pause','staff:2',$at+1,[]);
    rb_bad('Creation cannot silently use a later subscription revision',fn()=>hosting_checkout_billing_terms(rb_id(80),$later));
    rb_ok('Historical binding reconstructs exact prefix after later pause',rb_bound($terms,$later));
    $endedSub=$later->append(rb_id(74),'cancel','client:9',$at+2,['reason'=>'End later service']);
    rb_ok('Historical source binding survives later cancellation',rb_bound($terms,$endedSub));
    $other=$sub->data();$other['id']=rb_id(99);rb_bad('Another valid subscription identity cannot reuse terms',fn()=>hosting_checkout_billing_assert($terms,ServiceSubscriptionDocument::fromJson(service_plans_json($other))));
    $schedule=Schedule::createCheckout($terms,rb_id(81));$sd=$schedule->data();$first=$sd['events'][0];
    rb_ok('Checkout schedule records actual client creator and exact accepted time',$sd['format']===2&&$sd['created_by_client']===9&&!array_key_exists('created_by',$sd)&&$sd['created_at']===$at);
    rb_ok('Fixed initial event records client consent and automatic local period zero start',$first===['id'=>rb_id(81),'command'=>'checkout_accept','actor'=>'client:9','at'=>$at,'arguments'=>['terms_sha256'=>$terms->fingerprint(),'consent_sha256'=>$o['consent_sha256'],'period_index'=>0,'effect'=>'automatic-local-period-zero-start']]);
    rb_ok('Period zero becomes eligible immediately at actual acceptance',$schedule->phase()==='running'&&$schedule->revision()===1&&$schedule->assessment(0,$at)['eligible']===true&&$schedule->assessment(0,$at)['period']['due']==='2027-01-31');
    rb_bad('Assessment cannot precede accepted instant',fn()=>$schedule->assessment(0,$at-1));
    rb_ok('Future period remains ineligible before its real calendar start',$schedule->assessment(1,$at)['reason']==='not-started');
    rb_ok('Initial composition and historical parsing are exact',Schedule::createCheckout($terms,rb_id(81))->json()===$schedule->json()&&Schedule::fromJson($schedule->json())->json()===$schedule->json());
    rb_ok('Exact fixed-prefix replay does not append', $schedule->append($first['id'],$first['command'],$first['actor'],$first['at'],$first['arguments'])->json()===$schedule->json());
    rb_bad('Changed fixed-prefix replay refuses',fn()=>$schedule->append($first['id'],$first['command'],$first['actor'],$at+1,$first['arguments']));
    rb_bad('A second checkout_accept command cannot be appended',fn()=>$schedule->append(rb_id(82),$first['command'],$first['actor'],$at,$first['arguments']));
    rb_bad('Legacy creation cannot wrap checkout terms',fn()=>Schedule::create($terms,2,$at));
    $bad=['format'=>1,'terms'=>$d,'created_by'=>2,'created_at'=>$at,'events'=>[]];rb_bad('Historical legacy envelope cannot wrap checkout terms',fn()=>Schedule::fromJson(service_plans_json($bad)));
    foreach(['created_by_client'=>2,'created_at'=>$at+1,'created_by'=>2] as $key=>$value){$bad=$sd;$bad[$key]=$value;rb_bad('Changed checkout schedule creator binding refuses '.$key,fn()=>Schedule::fromJson(service_plans_json($bad)));}
    foreach([['actor','staff:2'],['actor','system:hosting-checkout'],['actor','client:10'],['at',$at-1],['at',$at+1],['command','activate'],['arguments',[]]] as $n=>[$key,$value]){$bad=$sd;$bad['events'][0][$key]=$value;rb_bad('Changed checkout prefix refuses '.$n,fn()=>Schedule::fromJson(service_plans_json($bad)));}
    foreach(['terms_sha256'=>str_repeat('0',64),'consent_sha256'=>str_repeat('0',64),'period_index'=>1,'effect'=>'owner-override'] as $key=>$value){$bad=$sd;$bad['events'][0]['arguments'][$key]=$value;rb_bad('Changed initial decision effect refuses '.$key,fn()=>Schedule::fromJson(service_plans_json($bad)));}
    foreach([[],[$first,$first]] as $n=>$events){$bad=$sd;$bad['events']=$events;rb_bad('Missing or duplicated initial prefix refuses '.$n,fn()=>Schedule::fromJson(service_plans_json($bad)));}
    rb_bad('Legacy activate cannot restart checkout schedule',fn()=>$schedule->append(rb_id(82),'activate','staff:2',$at,['period_index'=>0]));
    rb_bad('Pause cannot change the already started first period',fn()=>$schedule->append(rb_id(82),'pause','staff:2',$at,['period_index'=>0,'reason'=>'Pause']));
    rb_bad('Future pause cannot be backdated before consent',fn()=>$schedule->append(rb_id(82),'pause','staff:2',$at-1,['period_index'=>1,'reason'=>'Pause']));
    $paused=$schedule->append(rb_id(82),'pause','staff:2',$at+1,['period_index'=>1,'reason'=>'Later pause']);
    rb_bad('Resume cannot target the current period',fn()=>$paused->append(rb_id(83),'resume','staff:2',$at+2,['period_index'=>0,'reason'=>'Resume']));
    $resumed=$paused->append(rb_id(83),'resume','staff:2',$at+2,['period_index'=>2,'reason'=>'Later resume']);
    rb_bad('Another client cannot end future checkout billing',fn()=>$resumed->append(rb_id(84),'end','client:10',$at+3,['period_index'=>3,'reason'=>'End']));
    rb_bad('End cannot erase the already started period',fn()=>$resumed->append(rb_id(84),'end','client:9',$at+3,['period_index'=>0,'reason'=>'End']));
    $ended=$resumed->append(rb_id(84),'end','client:9',$at+3,['period_index'=>3,'reason'=>'Later end']);
    foreach([0=>'eligible',1=>'paused',2=>'eligible',3=>'ended'] as $index=>$reason)rb_ok('Existing calendar lifecycle retains period '.$index,$ended->assessment($index,rb_epoch('2028-01-01T00:00:00Z'))['reason']===$reason);
    rb_ok('Fixed-prefix retry after later lifecycle preserves entire history',$ended->append($first['id'],$first['command'],$first['actor'],$first['at'],$first['arguments'])->json()===$ended->json());
    $legacy=$d;$legacy['format']=1;unset($legacy['checkout_origin']);$legacy['source_subscription']['revision']=3;
    $lt=Terms::fromArray($legacy);rb_ok('Format1 terms still round trip byte exactly',Terms::fromJson($lt->json())->json()===$lt->json());
    rb_bad('Explicit checkout command cannot promote legacy terms',fn()=>Schedule::createCheckout($lt,rb_id(90)));
    $ls=Schedule::create($lt,2,$at)->append(rb_id(91),'accept','client:9',$at,['terms_sha256'=>$lt->fingerprint()]);
    rb_bad('Format1 activation on today remains refused',fn()=>$ls->append(rb_id(92),'activate','staff:2',$at,['period_index'=>0]));
    rb_ok('Format1 accepted terms remain ineligible for early invoice',$ls->assessment(0,$at)['reason']==='not-activated');
    $lf=$ls->append(rb_id(92),'activate','staff:2',$at,['period_index'=>1]);
    rb_ok('Format1 future activation still refuses early period invoice',$lf->assessment(1,$at)['reason']==='not-started');
    rb_bad('Format1 never accepts checkout_accept as an override',fn()=>$ls->append(rb_id(93),'checkout_accept','client:9',$at,$first['arguments']));
    foreach([
        ['2027-09-05T23:59:50-03:00','America/Santiago','2027-09-05'],
        ['2011-12-29T23:59:50-10:00','Pacific/Apia','2011-12-29'],
        ['2027-03-14T01:59:50-05:00','America/New_York','2027-03-14'],
        ['2027-11-07T01:59:50-04:00','America/New_York','2027-11-07'],
        ['2027-02-01T00:00:10+14:00','Pacific/Kiritimati','2027-02-01'],
    ] as $n=>[$time,$zone,$date]){
        $df=rb_fixture(rb_epoch($time),'monthly',$zone);$ds=rb_sub($df);$dt=hosting_checkout_billing_terms(rb_id(100+$n),$ds);$dr=Schedule::createCheckout($dt,rb_id(110+$n));
        rb_ok('DST or UTC date boundary preserves accepted local date '.$n,$dt->data()['anchor']===$date&&$dt->period(0)['due']===$date&&$dr->assessment(0,$ds->data()['created_at'])['eligible']);
    }
    $nf=rb_fixture(rb_epoch('2027-09-05T23:59:50-03:00'),'monthly','America/Santiago');$nr=rb_review($nf);
    rb_bad('Local midnight cannot rebase the original checkout consent',fn()=>rb_consent($nr,$nf,$nf['at']+10));
    $af=rb_fixture(rb_epoch('2028-02-29T12:00:00Z'),'annually','UTC','month-end');$as=rb_sub($af);$at2=hosting_checkout_billing_terms(rb_id(120),$as);
    rb_ok('Annual leap-day period uses unchanged calendar',$at2->period(0)['end']==='2029-02-28'&&$at2->period(4)['start']==='2032-02-29');
    rb_ok('Pure derivation does not load database or invoice writers',!function_exists('db')&&!function_exists('recurring_billing_issue'));
    $report=['passed'=>true,'scope'=>'Synthetic pure documents and unchanged calendar; no SQL, authentication, invoice, payment, provider, HTTP or end-to-end acceptance.','php'=>PHP_VERSION,'checks'=>$checks];
} catch(Throwable $e) {$report=['passed'=>false,'scope'=>'Pure document test only','php'=>PHP_VERSION,'checks'=>$checks,'error'=>get_class($e).': '.$e->getMessage()];}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($report['passed']?0:1);
