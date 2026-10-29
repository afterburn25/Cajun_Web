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
$_SERVER['DOCUMENT_ROOT']=realpath(dirname($root));
$operation=$argv[1];$value=$argv[2];
$password='Synthetic owner passphrase 2026';

require_once CWP_CODE_ROOT.'/database/migrations/license_migrate.php';
require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
require_once CWP_CODE_ROOT.'/app/licenses/bundle.php';
require_once CWP_CODE_ROOT.'/app/projects/deliveries.php';
require_once CWP_CODE_ROOT.'/app/licenses/finance_installments.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
require_once CWP_CODE_ROOT.'/app/billing/payments.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger.php';

function finance_fixture_owner():void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=1');
    if(!$u)throw new RuntimeException('Synthetic owner unavailable.');
    $_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    unset($GLOBALS['cwp_support']);$_GET=[];
}
function finance_fixture_client():void{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');
    if(!$c)throw new RuntimeException('Synthetic finance client unavailable.');
    $_SESSION=['client_id'=>1,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];
    unset($GLOBALS['cwp_support']);$_GET=[];
}
function finance_fixture_id(string $seed):string{return substr(hash('sha256','finance-browser|'.$seed),0,32);}
function finance_fixture_private_storage(string $root):void{
    $private=realpath((string)(config()['storage']??''));
    $document=realpath(dirname($root));
    if(!$private||!is_dir($private)||!$document
        ||str_starts_with(str_replace('\\','/',$private).'/',str_replace('\\','/',$document).'/')){
        throw new RuntimeException('Synthetic finance fixture requires the installed private storage to remain outside the document root.');
    }
}
function finance_fixture_source(string $case):string{
    if(!in_array($case,['initial','standard'],true))throw new InvalidArgumentException('Unknown financing fixture source.');
    $sid=finance_fixture_id('service-subscription-'.$case);
    if(one('SELECT id FROM '.table('service_subscriptions').' WHERE id=?',[$sid]))return $sid;
    $at=1570000000;$planId=finance_fixture_id('service-plan-'.$case);
    $terms=['name'=>'Finance browser care','cadence'=>'monthly','price'=>2500,'currency'=>'USD',
        'inclusions'=>['Financed website'],'exclusions'=>[],'limits'=>[],'support'=>'Business hours'];
    $p0=ServicePlanDocument::create($planId,1,$at,$terms);
    $p=$p0->append(finance_fixture_id('plan-activate-'.$case),'activate',1,$at+1,[]);
    query('INSERT INTO '.table('service_plans').' (id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,1,?,?,?,?,1)',
        [$planId,$at,$p0->json(),$p->json(),$p->fingerprint()]);
    $pe=$p->data()['events'][0];
    query('INSERT INTO '.table('service_plan_events').' (id,plan_id,actor_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,1,1,?,?,?,?)',
        [$pe['id'],$planId,$pe['at'],service_plans_json(['revision'=>0,'command'=>'activate','arguments'=>[]]),$p0->fingerprint(),$p->fingerprint()]);
    $s0=ServiceSubscriptionDocument::create($sid,1,1,$at+2,$p);
    $s1=$s0->append(finance_fixture_id('subscription-consent-'.$case),'consent','client:1',$at+3,['terms_hash'=>$p->model()->termsHash()]);
    $s=$s1->append(finance_fixture_id('subscription-activate-'.$case),'activate','staff:1',$at+4,[]);
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
function finance_fixture_sandbox_gateway(string $password):void{
    if(!license_finance_gateway_ready())license_finance_gateway_prepare($password);
    $gateway=payments_gateway('sandbox');
    payments_save('sandbox',(int)$gateway['revision'],[
        'api_login_id'=>'SyntheticBrowser',
        'transaction_key'=>'1234567890ABCDEF',
        'signature_key'=>str_repeat('ab',64),
        'public_client_key'=>'Synthetic_Finance_Browser_Key_2026',
    ],$password);
    save_setting('software_gateway_mode','sandbox');
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
if(in_array($operation,['setup','setup-initial'],true)){
    finance_fixture_private_storage($root);
    if(!staff_ready())staff_migrate(1,$password);
    finance_fixture_owner();
    if(!payments_ready())payments_migrate($password);
    if(!ledger_ready())ledger_migrate($password);
    if(!service_plans_ready())service_plans_migrate($password);
    if(!recurring_billing_ready())recurring_billing_migrate($password);
    if(!license_ready())license_migrate($password);
    if(!recurring_billing_ready()||!service_plans_ready()||!ledger_ready()||!payments_ready()){
        throw new RuntimeException('Financing browser fixture could not prepare recurring billing prerequisites.');
    }
    if(license_financing_feature_state()===null)license_financing_prepare($password);
    if(!license_finance_offers_ready())license_finance_offers_prepare($password);
    $initialCase=$operation==='setup-initial';
    $title=$initialCase?'Browser financed website with down payment':'Browser financed website';
    $domain=$initialCase?'finance-initial.example':'finance-browser.example';
    $existing=one('SELECT * FROM '.table('license_finance_offers').' WHERE title=?',[$title]);
    if($existing){
        $license=one('SELECT license_key FROM '.table('website_licenses').' WHERE id=?',[(int)$existing['license_id']]);
        if(!$license)throw new RuntimeException('Synthetic financed license is missing.');
        echo json_encode(['offer_id'=>$existing['id'],'license_id'=>(int)$existing['license_id'],
            'license_key'=>$license['license_key'],'domain'=>$domain],JSON_THROW_ON_ERROR);exit;
    }
    $jobId=1;
    if($initialCase){
        query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
            [1,'Initial payment financed project','Synthetic financed project isolating the unpaid initial payment scenario.','build','[]',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
        $jobId=(int)db()->lastInsertId();
    }
    $source=finance_fixture_source($initialCase?'initial':'standard');$scheduleId=finance_fixture_id($initialCase?'schedule-initial':'schedule');
    $sourceDoc=service_plans_validate(service_plans_row($source,true),true);
    $fields=['format'=>1,'id'=>$scheduleId,'client_id'=>1,'project_id'=>$jobId,
        'source_subscription'=>['id'=>$source,'revision'=>$sourceDoc->revision(),'sha256'=>$sourceDoc->fingerprint()],
        'title'=>'Browser website financing','terms'=>'Synthetic browser financing terms.','currency'=>'USD',
        'amount_cents'=>2500,'cadence'=>'monthly','anchor'=>gmdate('Y-m-d',time()+4*86400),
        'alignment'=>'day-of-month','timezone'=>'UTC','due_after_days'=>0,'collection'=>'invoice-only'];
    recurring_billing_offer($fields);
    $key=license_create(1,$initialCase?'WEBSITE-FINANCE-INITIAL':'WEBSITE-FINANCE-BROWSER',1,false,null);
    $license=license_get($key);license_register_domain((int)$license['id'],$domain,1);
    $offer=license_finance_offer_create((int)$license['id'],$scheduleId,$title,
        $initialCase?12500:10000,$initialCase?2500:0,10000,0,0,4,5,7,7,
        ['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],
        'Synthetic browser financing agreement. Issued unpaid installments beyond the five-day grace period may suspend this website. Full payoff permanently removes only the payment lock; resale and distribution remain prohibited.',
        $password);
    if($initialCase){
        finance_fixture_client();
        $signed=license_finance_offer_sign((string)$offer['id'],1,'Alice Initial Payment',true,true);
        $offer=$signed['offer'];
    }
    echo json_encode(['offer_id'=>$offer['id'],'license_id'=>(int)$license['id'],'license_key'=>$key,
        'domain'=>$domain],JSON_THROW_ON_ERROR);exit;
}
if(!preg_match('/^[a-f0-9]{32}$/D',$value))exit(2);
$offer=license_finance_offer_row($value);$licenseId=(int)$offer['license_id'];
if($operation==='release'){
    finance_fixture_owner();
    if(!delivery_ready())delivery_migrate($password);
    $version='finance-browser-lock-1.0.0';
    $release=one('SELECT * FROM '.table('website_releases').' WHERE job_id=? AND version=?',
        [$offer['job_id'],$version]);
    if(!$release){
        $dir=rtrim((string)config()['storage'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'finance-browser';
        if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Synthetic finance delivery directory unavailable.');
        $zipPath=$dir.DIRECTORY_SEPARATOR.'financed-project-release.zip';
        $zip=new ZipArchive();
        if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Synthetic finance release ZIP creation failed.');
        $zip->addFromString('HANDOVER.md',"# Handover\nSynthetic financed project release for installed acceptance.\n");
        $zip->addFromString('LICENSES.md',"# Licenses\nSynthetic acceptance package; no third-party assets.\n");
        $zip->addFromString('index.html',"<!doctype html><title>Financed delivery acceptance</title>\n");
        $zip->close();
        $id=delivery_upload((int)$offer['job_id'],[
            'version'=>$version,
            'title'=>'Financed browser project release',
            'scope'=>'static',
            'notes'=>'Synthetic financed project release used to verify payment-dependent client delivery access.',
            'source_revision'=>'',
        ],$zipPath,hash('sha256','finance-browser-release|'.$offer['id']));
        $release=delivery_record($id);
        delivery_publish($id,delivery_review_hash($release),$password);
        $release=delivery_record($id);
    }
    if($release['status']!=='published')throw new RuntimeException('Synthetic financed project release was not published.');
    $deliveries=realpath((string)config()['storage']).DIRECTORY_SEPARATOR.'deliveries';
    $releaseFile=$deliveries.DIRECTORY_SEPARATOR.'release-'.$release['id'].'.php';
    if(!is_dir($deliveries)||!is_file($releaseFile)||!chmod($deliveries,0755)||!chmod($releaseFile,0644)){
        throw new RuntimeException('Synthetic financed project release could not be shared with the web server.');
    }
    echo json_encode([
        'release_id'=>(string)$release['id'],
        'sha256'=>(string)$release['sha256'],
        'bytes'=>(int)$release['bytes'],
        'version'=>(string)$release['version']
    ],JSON_THROW_ON_ERROR);exit;
}
if($operation==='bundle'){
    $stored=one('SELECT * FROM '.table('license_bundles').' WHERE license_id=? AND title=? ORDER BY id DESC LIMIT 1',
        [$licenseId,'Financed browser website bundle']);
    if(!$stored){
        $dir=rtrim((string)config()['storage'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'finance-browser';
        if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Synthetic finance bundle directory unavailable.');
        $file=$dir.DIRECTORY_SEPARATOR.'financed-site-'.$licenseId.'.zip';
        if(!is_file($file)){
            $zip=new ZipArchive();
            if($zip->open($file,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Synthetic finance bundle creation failed.');
            $zip->addFromString('README.txt',"Synthetic financed website bundle.\n");
            $zip->close();
        }
        license_bundle_store($licenseId,$file,'Financed browser website bundle');
        $stored=one('SELECT * FROM '.table('license_bundles').' WHERE license_id=? AND title=? ORDER BY id DESC LIMIT 1',
            [$licenseId,'Financed browser website bundle']);
    }
    if(!$stored)throw new RuntimeException('Synthetic finance bundle record unavailable.');
    echo json_encode(['bundle_id'=>(int)$stored['id'],'sha256'=>(string)$stored['sha256'],'bytes'=>(int)filesize($stored['file_path'])],JSON_THROW_ON_ERROR);exit;
}
if($operation==='gateway-off'){
    save_setting('software_gateway_mode','disabled');
    echo json_encode(['mode'=>'disabled'],JSON_THROW_ON_ERROR);exit;
}
if($operation==='late'){
    $schedule=recurring_billing_row((string)$offer['schedule_id']);$doc=recurring_billing_validate($schedule);
    $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);$p=$terms->period(0);
    $at=strtotime($p['start'].' 12:00:00 UTC');if($at===false)throw new RuntimeException('Synthetic period timestamp invalid.');
    $issued=license_finance_issue_period($value,0,$at);
    $late=(new DateTimeImmutable($p['due'].'T00:00:00',new DateTimeZone('UTC')))->modify('+6 days')->format('Y-m-d');
    $plan=license_financing_sync($licenseId,$late);
    echo json_encode(['invoice_id'=>(int)$issued['invoice_id'],'state'=>$plan['state'],'today'=>$late],JSON_THROW_ON_ERROR);exit;
}
if($operation==='gateway-suspended'){
    finance_fixture_sandbox_gateway($password);
    $schedule=recurring_billing_row((string)$offer['schedule_id']);$doc=recurring_billing_validate($schedule);
    $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);$p=$terms->period(0);
    $now=gmdate('Y-m-d H:i:s');
    $sub=one('SELECT id FROM '.table('license_finance_subscriptions').' WHERE offer_id=?',[$offer['id']]);
    if(!$sub){
        query('INSERT INTO '.table('license_finance_subscriptions').
            ' (offer_id,gateway_subscription_id,customer_profile_id,payment_profile_id,interval_months,amount_cents,total_occurrences,period_offset,replacement_count,start_date,status,created_at,updated_at)'.
            ' VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$offer['id'],'830001','930001','940001',1,(int)license_finance_offer_terms($offer)['installment_amount_cents'],
             (int)$offer['installments'],0,0,$p['start'],'billing_suspended',$now,$now]);
    }else{
        query('UPDATE '.table('license_finance_subscriptions').' SET gateway_subscription_id=?,customer_profile_id=?,payment_profile_id=?,status=?,updated_at=? WHERE id=?',
            ['830001','930001','940001','billing_suspended',$now,$sub['id']]);
    }
    $late=(new DateTimeImmutable($p['due'].'T00:00:00',new DateTimeZone('UTC')))->modify('+6 days');
    save_setting('finance_scheduler_cursor','');
    $summary=license_finance_scheduler_run($late->getTimestamp());
    $request=one('SELECT p.id,p.invoice_id,p.status,p.amount_cents FROM '.table('payment_requests').' p '.
        'JOIN '.table('recurring_billing_invoices').' r ON r.invoice_id=p.invoice_id '.
        'WHERE r.schedule_id=? AND r.period_index=0 AND p.purpose=? ORDER BY p.id DESC LIMIT 1',
        [$offer['schedule_id'],'financing']);
    if(!$request||$request['status']!=='prepared')throw new RuntimeException('Synthetic finance recovery request was not prepared.');
    echo json_encode([
        'subscription_status'=>'billing_suspended','gateway_subscription_id'=>'830001',
        'request_id'=>(int)$request['id'],'invoice_id'=>(int)$request['invoice_id'],
        'amount_cents'=>(int)$request['amount_cents'],'scheduler'=>$summary
    ],JSON_THROW_ON_ERROR);exit;
}
if($operation==='catchup'){
    $map=one('SELECT invoice_id FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index=0',[$offer['schedule_id']]);
    if(!$map)throw new RuntimeException('Synthetic overdue installment is missing.');
    finance_fixture_payment((int)$map['invoice_id'],'Catch-up browser payment');
    query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND purpose=? AND status='prepared'",
        [(int)$map['invoice_id'],'financing']);
    $plan=license_financing_sync($licenseId,gmdate('Y-m-d'));
    echo json_encode(['state'=>$plan['state'],'paid_off_at'=>$plan['paid_off_at']],JSON_THROW_ON_ERROR);exit;
}
if($operation==='payoff'){
    $schedule=recurring_billing_row((string)$offer['schedule_id']);$doc=recurring_billing_validate($schedule);
    $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);$last=$terms->period(3);
    $at=strtotime($last['start'].' 12:00:00 UTC');if($at===false)throw new RuntimeException('Synthetic payoff timestamp invalid.');
    license_finance_issue_due($value,$at);
    $lastPayment=0;$lastInvoice=0;
    foreach(all('SELECT invoice_id FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? ORDER BY period_index',[$offer['schedule_id']]) as $row){
        $lastInvoice=(int)$row['invoice_id'];$lastPayment=finance_fixture_payment($lastInvoice,'Payoff browser payment '.$lastInvoice);
    }
    $plan=license_financing_sync($licenseId,gmdate('Y-m-d',$at+86400));
    echo json_encode(['state'=>$plan['state'],'paid_off_at'=>$plan['paid_off_at'],'last_payment'=>$lastPayment,'last_invoice'=>$lastInvoice],JSON_THROW_ON_ERROR);exit;
}
if($operation==='force-inactive-paid-off'){
    $plan=license_financing_plan($licenseId);
    if(!$plan||$plan['state']!=='paid_off'||$plan['paid_off_at']===null){
        throw new RuntimeException('Synthetic inactive-payoff fixture requires a paid-off plan.');
    }
    query('UPDATE '.table('website_licenses').' SET is_active=0,updated_at=? WHERE id=?',
        [gmdate('Y-m-d H:i:s'),$licenseId]);
    echo json_encode(['license_id'=>$licenseId,'is_active'=>0],JSON_THROW_ON_ERROR);exit;
}
if($operation==='rights-invoice'){
    $title='Browser commercial rights addendum';
    $invoice=one('SELECT id,status FROM '.table('studio_invoices').' WHERE job_id=? AND title=? ORDER BY id DESC LIMIT 1',
        [$offer['job_id'],$title]);
    if(!$invoice){
        $now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',
            [$offer['job_id'],'RIGHTS-'.strtoupper(substr($offer['id'],0,10)),$title,5000,'USD',
             'Separate paid commercial-rights addendum for browser acceptance.',$now]);
        $invoice=['id'=>(int)db()->lastInsertId(),'status'=>'issued'];
    }
    $payment=finance_fixture_payment((int)$invoice['id'],'Commercial rights browser payment');
    echo json_encode(['invoice_id'=>(int)$invoice['id'],'payment_id'=>$payment],JSON_THROW_ON_ERROR);exit;
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
