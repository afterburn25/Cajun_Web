<?php
/** Disposable financed-license installed-browser fixture. Never contacts a gateway. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||count($argv)!==3)exit(2);
$root=realpath(__DIR__.'/../test-runtime/web/existing');
if(!$root||!is_file($root.'/includes/bootstrap.php')||!is_file($root.'/config.php'))exit(2);
define('CWP_BOOT',true);
require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
if(config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='ci_')exit(2);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/existing/index.php','REQUEST_METHOD'=>'GET'];
$operation=$argv[1];$value=$argv[2];
$password='Synthetic owner passphrase 2026';

require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
require_once CWP_CODE_ROOT.'/app/licenses/finance_installments.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger.php';

function finance_fixture_owner():void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=1');
    if(!$u)throw new RuntimeException('Synthetic owner unavailable.');
    $_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    unset($GLOBALS['cwp_support']);$_GET=[];
}
function finance_fixture_id(string $seed):string{return substr(hash('sha256','finance-browser|'.$seed),0,32);}
function finance_fixture_source():string{
    $sid=finance_fixture_id('service-subscription');
    if(one('SELECT id FROM '.table('service_subscriptions').' WHERE id=?',[$sid]))return $sid;
    $at=1570000000;$planId=finance_fixture_id('service-plan');
    $terms=['name'=>'Finance browser care','cadence'=>'monthly','price'=>2500,'currency'=>'USD',
        'inclusions'=>['Financed website'],'exclusions'=>[],'limits'=>[],'support'=>'Business hours'];
    $p0=ServicePlanDocument::create($planId,1,$at,$terms);
    $p=$p0->append(finance_fixture_id('plan-activate'),'activate',1,$at+1,[]);
    query('INSERT INTO '.table('service_plans').' (id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,1,?,?,?,?,1)',
        [$planId,$at,$p0->json(),$p->json(),$p->fingerprint()]);
    $pe=$p->data()['events'][0];
    query('INSERT INTO '.table('service_plan_events').' (id,plan_id,actor_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,1,1,?,?,?,?)',
        [$pe['id'],$planId,$pe['at'],service_plans_json(['revision'=>0,'command'=>'activate','arguments'=>[]]),$p0->fingerprint(),$p->fingerprint()]);
    $s0=ServiceSubscriptionDocument::create($sid,1,1,$at+2,$p);
    $s1=$s0->append(finance_fixture_id('subscription-consent'),'consent','client:1',$at+3,['terms_hash'=>$p->model()->termsHash()]);
    $s=$s1->append(finance_fixture_id('subscription-activate'),'activate','staff:1',$at+4,[]);
    query('INSERT INTO '.table('service_subscriptions').' (id,plan_id,plan_revision,plan_sha256,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,1,?,1,1,?,?,?,?,2)',
        [$sid,$planId,$p->fingerprint(),$at+2,$s0->json(),$s->json(),$s->fingerprint()]);
    foreach([[$s0,$s1,null,1],[$s1,$s,1,null]] as [$before,$after,$staff,$client]){
        $e=$after->data()['events'][array_key_last($after->data()['events'])];
        query('INSERT INTO '.table('service_subscription_events').' (id,subscription_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',
            [$e['id'],$sid,$staff,$client,$after->revision(),$e['at'],
             service_plans_json(['revision'=>$before->revision(),'command'=>$e['command'],'arguments'=>$e['arguments']]),
             $before->fingerprint(),$after->fingerprint()]);
    }
    return $sid;
}
function finance_fixture_payment(int $invoice,string $reference):int{
    $i=ledger_invoice($invoice);$balance=ledger_balance($i);
    if($i['status']==='paid'&&(int)$balance['due_cents']===0){
        $p=one('SELECT id FROM '.table('invoice_payments').' WHERE invoice_id=? AND kind=? ORDER BY id DESC LIMIT 1',[$invoice,'manual']);
        return (int)($p['id']??0);
    }
    if((int)$balance['due_cents']<1)throw new RuntimeException('Synthetic financing invoice has no payable balance.');
    db()->beginTransaction();
    try{
        $id=ledger_insert($i,1,'manual',(int)$balance['due_cents'],'synthetic',$reference,gmdate('Y-m-d'),
            hash('sha256','finance-fixture-pay|'.$invoice.'|'.$reference),hash('sha256',$reference),
            hash('sha256','finance-fixture-external|'.$invoice.'|'.$reference));
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
            ['paid','Synthetic financing browser payment '.$id,gmdate('Y-m-d H:i:s'),$invoice]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    license_financing_sync_by_invoice($invoice);
    return $id;
}

finance_fixture_owner();
if($operation==='setup'){
    if(!recurring_billing_ready()||!service_plans_ready()||!ledger_ready()||!payments_ready()){
        throw new RuntimeException('Financing browser fixture requires recurring billing, service plans, payments and ledger.');
    }
    if(license_financing_feature_state()===null)license_financing_prepare($password);
    if(!license_finance_offers_ready())license_finance_offers_prepare($password);
    $existing=one('SELECT * FROM '.table('license_finance_offers').' WHERE title=?',['Browser financed website']);
    if($existing){
        $license=one('SELECT license_key FROM '.table('website_licenses').' WHERE id=?',[(int)$existing['license_id']]);
        if(!$license)throw new RuntimeException('Synthetic financed license is missing.');
        echo json_encode(['offer_id'=>$existing['id'],'license_id'=>(int)$existing['license_id'],
            'license_key'=>$license['license_key'],'domain'=>'finance-browser.example'],JSON_THROW_ON_ERROR);exit;
    }
    $source=finance_fixture_source();$scheduleId=finance_fixture_id('schedule');
    $sourceDoc=service_plans_validate(service_plans_row($source,true),true);
    $fields=['format'=>1,'id'=>$scheduleId,'client_id'=>1,'project_id'=>1,
        'source_subscription'=>['id'=>$source,'revision'=>$sourceDoc->revision(),'sha256'=>$sourceDoc->fingerprint()],
        'title'=>'Browser website financing','terms'=>'Synthetic browser financing terms.','currency'=>'USD',
        'amount_cents'=>2500,'cadence'=>'monthly','anchor'=>gmdate('Y-m-d',time()+4*86400),
        'alignment'=>'day-of-month','timezone'=>'UTC','due_after_days'=>0,'collection'=>'invoice-only'];
    recurring_billing_offer($fields);
    $key=license_create(1,'WEBSITE-FINANCE-BROWSER',1,false,null);
    $license=license_get($key);license_register_domain((int)$license['id'],'finance-browser.example',1);
    $offer=license_finance_offer_create((int)$license['id'],$scheduleId,'Browser financed website',
        10000,0,10000,0,0,4,5,7,7,
        ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
        'Synthetic browser financing agreement. Issued unpaid installments beyond the five-day grace period may suspend this website. Full payoff permanently removes only the payment lock; resale and distribution remain prohibited.',
        $password);
    echo json_encode(['offer_id'=>$offer['id'],'license_id'=>(int)$license['id'],'license_key'=>$key,
        'domain'=>'finance-browser.example'],JSON_THROW_ON_ERROR);exit;
}
if(!preg_match('/^[a-f0-9]{32}$/D',$value))exit(2);
$offer=license_finance_offer_row($value);$licenseId=(int)$offer['license_id'];
if($operation==='late'){
    $schedule=recurring_billing_row((string)$offer['schedule_id']);$doc=recurring_billing_validate($schedule);
    $terms=RecurringBillingTerms::fromArray($doc->data()['terms']);$p=$terms->period(0);
    $at=strtotime($p['start'].' 12:00:00 UTC');if($at===false)throw new RuntimeException('Synthetic period timestamp invalid.');
    $issued=license_finance_issue_period($value,0,$at);
    $late=(new DateTimeImmutable($p['due'].'T00:00:00',new DateTimeZone('UTC')))->modify('+6 days')->format('Y-m-d');
    $plan=license_financing_sync($licenseId,$late);
    echo json_encode(['invoice_id'=>(int)$issued['invoice_id'],'state'=>$plan['state'],'today'=>$late],JSON_THROW_ON_ERROR);exit;
}
if($operation==='catchup'){
    $map=one('SELECT invoice_id FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index=0',[$offer['schedule_id']]);
    if(!$map)throw new RuntimeException('Synthetic overdue installment is missing.');
    finance_fixture_payment((int)$map['invoice_id'],'Catch-up browser payment');
    $plan=license_financing_sync($licenseId,gmdate('Y-m-d'));
    echo json_encode(['state'=>$plan['state'],'paid_off_at'=>$plan['paid_off_at']],JSON_THROW_ON_ERROR);exit;
}
if($operation==='payoff'){
    $schedule=recurring_billing_row((string)$offer['schedule_id']);$doc=recurring_billing_validate($schedule);
    $terms=RecurringBillingTerms::fromArray($doc->data()['terms']);$last=$terms->period(3);
    $at=strtotime($last['start'].' 12:00:00 UTC');if($at===false)throw new RuntimeException('Synthetic payoff timestamp invalid.');
    license_finance_issue_due($value,$at);
    $lastPayment=0;$lastInvoice=0;
    foreach(all('SELECT invoice_id FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? ORDER BY period_index',[$offer['schedule_id']]) as $row){
        $lastInvoice=(int)$row['invoice_id'];$lastPayment=finance_fixture_payment($lastInvoice,'Payoff browser payment '.$lastInvoice);
    }
    $plan=license_financing_sync($licenseId,gmdate('Y-m-d',$at+86400));
    echo json_encode(['state'=>$plan['state'],'paid_off_at'=>$plan['paid_off_at'],'last_payment'=>$lastPayment,'last_invoice'=>$lastInvoice],JSON_THROW_ON_ERROR);exit;
}
if($operation==='reverse-after-payoff'){
    $p=one('SELECT p.* FROM '.table('invoice_payments').' p JOIN '.table('recurring_billing_invoices').' r ON r.invoice_id=p.invoice_id WHERE r.schedule_id=? AND p.kind=? ORDER BY r.period_index DESC,p.id DESC LIMIT 1',
        [$offer['schedule_id'],'manual']);
    if(!$p)throw new RuntimeException('Synthetic payoff payment unavailable.');
    $i=ledger_invoice((int)$p['invoice_id']);
    $id=ledger_reverse((int)$p['id'],'Synthetic correction after permanent financing payoff',
        hash('sha256','finance-browser-reversal|'.$p['id']),ledger_review_hash($i),$password);
    $plan=license_financing_sync($licenseId);
    echo json_encode(['reversal_id'=>$id,'state'=>$plan['state'],'paid_off_at'=>$plan['paid_off_at']],JSON_THROW_ON_ERROR);exit;
}
exit(2);
