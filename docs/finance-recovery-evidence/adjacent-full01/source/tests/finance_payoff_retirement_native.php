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
    foreach((($argv[3]??'')==='owner_exit'?[]:['opaque','profile','manual']) as $caller){
        foreach(['normal','cancel_timeout','lost_cancel_reply','cancel_error','still_active','wrong_readback','already_expired','already_canceled','already_terminated'] as $scenario){
            $name=$caller.'-'.$scenario;$offer=fep_seed($name,500);$license=(int)$offer['license_id'];
            query('UPDATE '.table('license_financing')." SET state='active' WHERE license_id=?",[$license]);
            $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
            $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);$invoices=[];
            for($n=0;$n<3;$n++){
                $period=$terms->period($n);$issued=license_finance_issue_period($offer['id'],$n,strtotime($period['start'].' 12:00:00 UTC'));
                $invoices[]=(int)$issued['invoice_id'];
            }
            $before=fep_snapshot();$fepCalls=[];$paymentEvidence=[];$providerScenario=$scenario;$postReservation=null;
            $pay=static function()use($invoices,&$paymentEvidence):void{
                $session=$_SESSION;rb_staff();
                try{
                    foreach($invoices as $invoice){
                        $row=ledger_invoice($invoice);$entry=\FinancePayoffRetirement\ledger_receive($invoice,1000,'bank','Synthetic payoff '.$invoice,
                            gmdate('Y-m-d'),hash('sha256','payoff-'.$invoice),ledger_review_hash($row));
                        $paymentEvidence[]=['invoice'=>$invoice,'entry'=>$entry,'balance'=>ledger_balance(ledger_invoice($invoice))];
                    }
                }finally{$_SESSION=$session;}
            };
            if($caller!=='manual')$postReservation=$pay;
            $result=$caller==='opaque'
                ?\FinancePayoffRetirement\license_finance_subscription_enroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic')
                :\FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
            if($caller==='manual'){
                fep_ok($name.' enrollment precedes payoff',$result==='pending_first_payment');$pay();
                $result=(string)license_finance_subscription_row($offer['id'])['status'];
            }
            $sub=license_finance_subscription_row($offer['id']);$plan=license_financing_plan($license);
            $allow=in_array($scenario,['normal','already_expired','already_canceled','already_terminated'],true);
            fep_ok($name.' verified retirement or retained review',$result===($allow?'paid_off':'review')&&$sub['status']===$result);
            fep_ok($name.' retains exact gateway contract',$sub['gateway_subscription_id']===(string)$fepGatewayId&&(int)$sub['amount_cents']===1000&&(int)$sub['total_occurrences']===3);
            fep_ok($name.' records real committed owner receipts',count($paymentEvidence)===3&&array_sum(array_column(array_column($paymentEvidence,'balance'),'due_cents'))===0);
            fep_ok($name.' permanent license payoff survives cancellation result',$plan['state']==='paid_off'&&$plan['paid_off_at']!==null);
            $after=fep_snapshot();fep_ok($name.' preserves license identity domains and rights',$before['website_licenses']===$after['website_licenses']&&$before['license_domains']===$after['license_domains']);
            $cancels=array_values(array_filter($fepCalls,fn($c)=>$c['method']==='ARBCancelSubscriptionRequest'));
            fep_ok($name.' cancels only nonterminal verified identity',count($cancels)===(str_starts_with($scenario,'already_')||$scenario==='wrong_readback'?0:1));
            fep_ok($name.' network runs outside SQL and retains cancellation identity',!array_filter($fepCalls,fn($c)=>$c['transaction']||($c['method']==='ARBCancelSubscriptionRequest'&&!$c['retained'])));
            fep_ok($name.' releases transaction and mutex',!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40)])->fetchColumn()===1);
            // Readback uncertainty has a retry path, with no second ARB create.
            if(!$allow){
                $providerScenario='normal';$beforeRetry=fep_snapshot();$callsBefore=count($fepCalls);
                if($caller==='profile'){
                    rb_staff();$retry=\FinancePayoffRetirement\license_finance_subscription_reconcile($offer['id'],(string)$sub['gateway_subscription_id'],$password);rb_client();
                    fep_ok($name.' owner reconciliation completes retirement',$retry==='paid_off');
                }else{
                    $summary=\FinancePayoffRetirement\license_finance_scheduler_run();
                    fep_ok($name.' scheduler retries paid-off billing',$summary['state']==='complete'&&license_finance_subscription_row($offer['id'])['status']==='paid_off');
                }
                $afterRetry=fep_snapshot();fep_ok($name.' retry preserves ledger and permanent payoff',$beforeRetry['invoice_payments']===$afterRetry['invoice_payments']&&$beforeRetry['license_financing']===$afterRetry['license_financing']);
                fep_ok($name.' retry never creates overlapping billing',!array_filter(array_slice($fepCalls,$callsBefore),fn($c)=>$c['method']==='ARBCreateSubscriptionRequest'));
            }
            $observations[]=['caller'=>$caller,'scenario'=>$scenario,'outcome'=>$result,'payments'=>$paymentEvidence,'provider_calls'=>$fepCalls];
        }
    }
    require __DIR__.'/finance_payoff_retirement_guards.php';
}catch(Throwable $e){fep_ok('fixture exception: '.get_class($e).' '.$e->getMessage(),false);}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
$sources=[];foreach(['app/billing/finance_subscription.php','app/billing/finance_authorize_net.php','app/licenses/financing.php','app/licenses/finance_installments.php'] as $path)$sources[$path]=hash_file('sha256',$root.'/site/'.$path);
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'cases'=>$observations,'php'=>PHP_VERSION,
    'database'=>query('SELECT VERSION()')->fetchColumn(),'sources'=>$sources,'expected_preparation_diagnostics'=>$expectedPreparationDiagnostics??0,'separate_writer_commits'=>$retirementWriterCommits??0,
    'scope'=>'Exact source bodies; canonical SQL, signed agreement, actual owner ledger_receive commits and license synchronization. Synthetic future-period invoices and ARB transport/mode; actual adapter readback and cancellation orchestration. No real provider, credentials, production settings, HTTP or independent approval.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
