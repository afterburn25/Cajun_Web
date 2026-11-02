<?php
/** Actual application admission/reservation and ledger, with synthetic signed financing data. No gateway. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$prefix=(string)($argv[1]??'');$port=(int)getenv('CWP_TEST_PORT');
if(!preg_match('/^fep_[a-z0-9_]{3,12}_$/D',$prefix)||getenv('CWP_TEST_HOST')!=='127.0.0.1'||$port<1||$port>65535)exit(2);
if(!getenv('CWP_TEST_USER')||!getenv('CWP_TEST_PASSWORD'))exit(2);
define('CWP_BOOT',true);$root=isset($argv[2])?realpath($argv[2]):dirname(__DIR__);
if(!$root||!is_file($root.'/site/app/billing/finance_subscription.php'))exit(2);
require $root.'/site/includes/bootstrap.php';require $root.'/site/database/schema/base.php';
require $root.'/site/app/billing/finance_subscription.php';
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','key'=>str_repeat('synthetic-enrollment-',4),
    'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>$prefix]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SESSION=[];$_GET=[];
// Reuse only established synthetic source-document seed helpers, not their test runner.
$seed=file_get_contents($root.'/tests/recurring_billing_native.php');$bodies=[];
foreach(['rb_id','rb_staff','rb_client','rb_fields','rb_historical_source'] as $name){
    if(!preg_match('/^function '.$name.'\(.*?(?=^function |^if\(|\z)/ms',$seed,$m))throw new RuntimeException('Missing seed helper '.$name);
    $bodies[]=rtrim($m[0]);
}
eval(implode("\n",$bodies));
$checks=[];$cases=[];require __DIR__.'/finance_payoff_retirement_boundaries.php';
function fep_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function fep_snapshot():array{
    $r=[];foreach(['website_licenses','license_domains','license_financing','license_finance_offers','studio_invoices','invoice_payments','recurring_billing_schedules','recurring_billing_events','license_finance_subscriptions','license_finance_subscription_history'] as $t)$r[$t]=all('SELECT * FROM '.table($t).' ORDER BY id');return $r;
}
function fep_seed(string $name,int $initial,bool $paid=true):array{
    global $password,$expectedPreparationDiagnostics;static $id=1000;$id+=100;
    if($initial>0)$expectedPreparationDiagnostics=($expectedPreparationDiagnostics??0)+1;
    rb_staff();rb_historical_source($id,1000);$fields=rb_fields($id+10,$id);recurring_billing_offer($fields);
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('website_licenses').' (license_key,customer_id,product_id,created_at,updated_at) VALUES (?,1,?,?,?)',['synthetic-'.$name,'website_core',$now,$now]);$license=(int)db()->lastInsertId();
    query('INSERT INTO '.table('license_domains').' (license_id,domain,registered_at) VALUES (?,?,?)',[$license,$name.'.example.invalid',$now]);
    $offer=license_finance_offer_create($license,$fields['id'],'Synthetic '.$name,3000+$initial,$initial,3000,0,0,3,7,3,3,[],
        'Synthetic agreement for isolated enrollment regression only.',$password);
    // Signature is real; payment-link preparation is deliberately unavailable.
    // Receipts below are synthetic retained ledger fixtures, never gateway observations.
    save_setting('payments_schema_version','');
    rb_client();license_finance_offer_sign($offer['id'],1,'Synthetic signer',true,true);
    save_setting('payments_schema_version','2');
    if($initial){
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (1,?,?,?,?,?,?)',
            ['FIA-'.strtoupper(substr($offer['id'],0,20)),'Synthetic initial payment',$initial,'USD','Initial payment for signed finance agreement '.$offer['id'].'. Financing terms and electronic acceptance are retained in the client portal.',$now]);
        $invoice=(int)db()->lastInsertId();
        query('UPDATE '.table('license_finance_offers').' SET activation_invoice_id=? WHERE id=?',[$invoice,$offer['id']]);
        if($paid){ledger_insert(ledger_invoice($invoice),1,'receipt',$initial,'external','Synthetic verified native fixture',gmdate('Y-m-d'));
        query('UPDATE '.table('studio_invoices')." SET status='paid' WHERE id=?",[$invoice]);}
    }
    return license_finance_offer_row($offer['id'],1);
}
try{
    foreach(schema_statements($prefix)+service_plans_schema($prefix)+recurring_billing_schema($prefix)+license_financing_schema($prefix)+license_finance_offers_schema($prefix)+license_finance_gateway_schema($prefix) as $sql)query($sql);
    foreach(['studio_schema_version'=>'2','staff_schema_version'=>'1','payments_schema_version'=>'2','support_schema_version'=>'1','service_plans_schema_version'=>'1','recurring_billing_schema_version'=>'1','invoice_ledger_schema_version'=>'1','license_financing_schema_version'=>'2','license_finance_offers_schema_version'=>'1','license_finance_gateway_schema_version'=>'3'] as $k=>$v)save_setting($k,$v);
    $password='Synthetic enrollment owner passphrase';$now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Synthetic owner','owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('a',32),$now]);staff_seed_owner(1);
    for($id=1;$id<=2;$id++){
        query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Synthetic client '.$id,'client'.$id.'@example.invalid',str_repeat((string)$id,32),$now]);
        query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$id,'Synthetic project '.$id,'Preserved project','design','',$now,$now]);
    }


    $fepGatewayId=860000;$fepMutation=null;$provider=[];$observations=[];

    $source=file_get_contents($root.'/site/app/billing/finance_subscription.php');
    if(!preg_match('/^function license_finance_subscription_settle\(.*?(?=^function |\z)/ms',$source,$match))throw new RuntimeException('Missing settlement source');
    eval('namespace FinancePayoffRetirement; use \\RuntimeException; use \\InvalidArgumentException; use \\Throwable; '.$match[0]);
    foreach(['normal','cancel_timeout','lost_cancel_reply','already_expired','wrong_amount'] as $scenario){
        $offer=fep_seed('settlement-'.$scenario,500);$license=(int)$offer['license_id'];
        query('UPDATE '.table('license_financing')." SET state='active' WHERE license_id=?",[$license]);
        $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
        $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);$invoices=[];
        for($n=0;$n<3;$n++){
            $period=$terms->period($n);$issued=license_finance_issue_period($offer['id'],$n,strtotime($period['start'].' 12:00:00 UTC'));$invoices[]=(int)$issued['invoice_id'];
        }
        $providerScenario=$scenario;$postReservation=null;$fepCalls=[];
        $result=\FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
        fep_ok($scenario.' creates exact initial subscription',$result==='pending_first_payment');
        $gateway=(string)license_finance_subscription_row($offer['id'])['gateway_subscription_id'];$before=fep_snapshot();
        for($n=0;$n<3;$n++){
            $transaction=(string)(880000+$invoices[$n]);
            $details=['messages'=>['resultCode'=>'Ok'],'transaction'=>['transId'=>$transaction,'transactionStatus'=>'settledSuccessfully',
                'authAmount'=>'10.00','subscription'=>['id'=>$gateway,'payNum'=>(string)($n+1)],'order'=>['invoiceNumber'=>license_finance_anet_subscription_ref($offer['id'])]]];
            if($scenario==='wrong_amount'&&$n===2){
                $prior=fep_snapshot();$bad=$details;$bad['transaction']['authAmount']='10.01';$error=null;
                try{\FinancePayoffRetirement\license_finance_subscription_settle($bad,$transaction);}catch(Throwable $e){$error=$e;}
                fep_ok('wrong amount cannot record a payment or trigger retirement',$error!==null&&fep_snapshot()===$prior&&license_financing_plan($license)['paid_off_at']===null);
            }
            $result=\FinancePayoffRetirement\license_finance_subscription_settle($details,$transaction);
            fep_ok($scenario.' verified installment '.($n+1).' commits',$result==='finance_subscription_payment_recorded'&&ledger_balance(ledger_invoice($invoices[$n]))['due_cents']===0);
        }
        $sub=license_finance_subscription_row($offer['id']);$plan=license_financing_plan($license);
        $uncertain=in_array($scenario,['cancel_timeout','lost_cancel_reply'],true);
        fep_ok($scenario.' cancellation outcome does not erase verified final payment',$sub['status']===($uncertain?'review':'paid_off')&&$plan['state']==='paid_off'&&$plan['paid_off_at']!==null);
        fep_ok($scenario.' retains exactly three verified transaction mappings',(int)query('SELECT COUNT(*) FROM '.table('license_finance_subscription_receipts').' WHERE offer_id=?',[$offer['id']])->fetchColumn()===3);
        $after=fep_snapshot();fep_ok($scenario.' preserves license and domain rights',$before['website_licenses']===$after['website_licenses']&&$before['license_domains']===$after['license_domains']);
        $start=count($fepCalls);$replay=\FinancePayoffRetirement\license_finance_subscription_settle($details,$transaction);
        fep_ok($scenario.' delayed duplicate cannot reopen paid-off billing',$replay==='finance_subscription_review_required'&&fep_snapshot()===$after&&count($fepCalls)===$start);
        $observations[]=['scenario'=>$scenario,'state'=>$sub['status'],'provider_calls'=>$fepCalls];
    }
}catch(Throwable $e){fep_ok('fixture exception: '.get_class($e).' '.$e->getMessage(),false);}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'cases'=>$observations,'php'=>PHP_VERSION,
    'database'=>query('SELECT VERSION()')->fetchColumn(),'subscription_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),
    'scope'=>'Actual recurring settlement, native ledger and payment mappings, final payoff and retirement. Synthetic verified gateway responses, transport and future-period invoice fixtures; no real provider or webhook signatures.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
