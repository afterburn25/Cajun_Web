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
$checks=[];$cases=[];require __DIR__.'/finance_replacement_payoff_boundaries.php';
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



    $offer=fep_seed('pending-fence',500);$license=(int)$offer['license_id'];
    query('UPDATE '.table('license_financing')." SET state='active' WHERE license_id=?",[$license]);
    $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));$terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
    $first=license_finance_issue_period($offer['id'],0,strtotime($terms->period(0)['start'].' 12:00:00 UTC'));$invoice=(int)$first['invoice_id'];
    $providerScenario='normal';$oldGateway='';$postReservation=null;$afterOldRetirement=null;$afterCandidateRead=null;$fepCalls=[];
    \FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
    $oldGateway=(string)license_finance_subscription_row($offer['id'])['gateway_subscription_id'];$provider[$oldGateway]['status']='suspended';
    query('UPDATE '.table('license_finance_subscriptions')." SET status='billing_suspended' WHERE offer_id=?",[$offer['id']]);
    $postReservation=static function()use($invoice):void{
        $session=$_SESSION;rb_staff();try{\FinancePayoffRetirement\ledger_receive($invoice,500,'bank','Synthetic pending-state receipt',gmdate('Y-m-d'),hash('sha256','pending-'.$invoice),ledger_review_hash(ledger_invoice($invoice)));}finally{$_SESSION=$session;}
    };
    $providerScenario='cancel_timeout';$result=\FinancePayoffRetirement\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');$new=(string)$fepGatewayId;
    fep_ok('Known candidate remains pending after uncertain cancellation',$result==='review'&&license_finance_subscription_row($offer['id'])['status']==='retirement_pending');
    fep_ok('Actual partial receipt remains recorded',(int)ledger_balance(ledger_invoice($invoice))['paid_cents']===500);
    $start=count($fepCalls);$before=fep_snapshot();
    foreach(['failed','suspended','terminated','cancelled','expired'] as $event){
        query('UPDATE '.table('license_finance_subscriptions')." SET status='retirement_pending' WHERE offer_id=?",[$offer['id']]);
        $sub=license_finance_subscription_row($offer['id']);$audits=all('SELECT * FROM '.table('audit').' ORDER BY id');
        $eventResult=license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.'.$event,$new);
        fep_ok('Delayed '.$event.' cannot clear pending cancellation',$eventResult==='replay'&&license_finance_subscription_row($offer['id'])===$sub&&all('SELECT * FROM '.table('audit').' ORDER BY id')===$audits);
    }
    query('UPDATE '.table('license_finance_subscriptions')." SET status='retirement_pending' WHERE offer_id=?",[$offer['id']]);
    foreach([false,true] as $owner){$refused=false;try{$refused=license_financing_subscription_settlement_status('retirement_pending',$owner)===null;}catch(Throwable){}fep_ok(($owner?'Owner':'Automatic').' settlement explicitly refuses a pending candidate',$refused);}
    fep_ok('Pending cancellation cannot open a second payment path',!license_financing_recovery_link_allowed('retirement_pending'));
    fep_ok('Lifecycle observations never call the gateway',count($fepCalls)===$start);
    $after=fep_snapshot();fep_ok('Events preserve receipts, permanent-payoff evidence and license rights',$before['invoice_payments']===$after['invoice_payments']&&$before['license_financing']===$after['license_financing']&&$before['website_licenses']===$after['website_licenses']&&$before['license_domains']===$after['license_domains']);
    fep_ok('Event handling releases transaction and mutex',!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40)])->fetchColumn()===1);
    $reserved=license_finance_subscription_row($offer['id']);license_finance_subscription_mark_review($offer['id'],$new);
    fep_ok('Generic review cannot erase pending cancellation',license_finance_subscription_row($offer['id'])===$reserved);
    query('UPDATE '.table('license_finance_subscriptions')." SET status='retirement_pending' WHERE offer_id=?",[$offer['id']]);
    // Retained gateway receipts are explicitly synthetic. The actual reversal
    // service receives already-verified synthetic refund/void observations; no
    // gateway credential, real refund request or production setting is used.
    require_once $root.'/site/app/billing/finance_payment_review.php';
    foreach([1=>'960001',2=>'960002'] as $index=>$transaction){
        $issued=license_finance_issue_period($offer['id'],$index,strtotime($terms->period($index)['start'].' 12:00:00 UTC'));$id=(int)$issued['invoice_id'];
        ledger_insert(ledger_invoice($id),0,'gateway',1000,'authorize_net','Synthetic retained gateway receipt',gmdate('Y-m-d'),null,null,hash('sha256','authorize-net|'.$transaction));
        query('UPDATE '.table('studio_invoices')." SET status='paid' WHERE id=?",[$id]);
        query('INSERT INTO '.table('license_finance_subscription_receipts').' (offer_id,gateway_transaction_id,period_index,invoice_id,created_at) VALUES (?,?,?,?,?)',[$offer['id'],$transaction,$index,$id,gmdate('Y-m-d H:i:s')]);
        $refundInvoices[$index]=$id;
    }
    $partial=['kind'=>'refund','transaction_id'=>'970001','original_transaction_id'=>'960001','amount_cents'=>100];
    $reversed=license_finance_payment_reversal_apply($partial);
    fep_ok('Partial refund preserves cancellation obligation',$reversed==='finance_partial_refund_review'&&license_finance_subscription_row($offer['id'])['status']==='retirement_pending');
    $count=(int)query('SELECT COUNT(*) FROM '.table('license_finance_reversals'))->fetchColumn();
    fep_ok('Duplicate partial refund remains idempotent',license_finance_payment_reversal_apply($partial)==='replay'&&(int)query('SELECT COUNT(*) FROM '.table('license_finance_reversals'))->fetchColumn()===$count);
    query('UPDATE '.table('license_finance_subscriptions')." SET status='retirement_pending' WHERE offer_id=?",[$offer['id']]);
    $full=['kind'=>'refund','transaction_id'=>'970002','original_transaction_id'=>'960001','amount_cents'=>900];
    $reversed=license_finance_payment_reversal_apply($full);
    fep_ok('Full refund preserves cancellation obligation and reopens accounting',$reversed==='finance_refund_reversed'&&license_finance_subscription_row($offer['id'])['status']==='retirement_pending'&&(int)ledger_balance(ledger_invoice($refundInvoices[1]))['due_cents']===1000);
    $payments=all('SELECT * FROM '.table('invoice_payments').' ORDER BY id');
    fep_ok('Duplicate full refund does not duplicate ledger reversal',license_finance_payment_reversal_apply($full)==='replay'&&all('SELECT * FROM '.table('invoice_payments').' ORDER BY id')===$payments);
    query('UPDATE '.table('license_finance_subscriptions')." SET status='retirement_pending' WHERE offer_id=?",[$offer['id']]);
    $void=['kind'=>'void','transaction_id'=>'970003','original_transaction_id'=>'960002','amount_cents'=>null];
    $reversed=license_finance_payment_reversal_apply($void);
    fep_ok('Verified void preserves cancellation obligation and reopens accounting',$reversed==='finance_void_reversed'&&license_finance_subscription_row($offer['id'])['status']==='retirement_pending'&&(int)ledger_balance(ledger_invoice($refundInvoices[2]))['due_cents']===1000);
    $payments=all('SELECT * FROM '.table('invoice_payments').' ORDER BY id');
    fep_ok('Duplicate void does not duplicate ledger reversal',license_finance_payment_reversal_apply($void)==='replay'&&all('SELECT * FROM '.table('invoice_payments').' ORDER BY id')===$payments);
    query('UPDATE '.table('license_finance_subscriptions')." SET status='retirement_pending' WHERE offer_id=?",[$offer['id']]);
    $providerScenario='normal';$summary=\FinancePayoffRetirement\license_finance_scheduler_run();
    fep_ok('Scheduler finishes verified cancellation after delayed events',$summary['state']==='complete'&&license_finance_subscription_row($offer['id'])['status']==='billing_suspended'&&$provider[$new]['status']==='canceled');
    fep_ok('Verified cancellation does not grant payoff',license_financing_plan($license)['paid_off_at']===null&&(int)ledger_balance(ledger_invoice($invoice))['paid_cents']===500);
    $ordinary=fep_seed('ordinary-refund',500);
    query('UPDATE '.table('license_financing')." SET state='active' WHERE license_id=?",[$ordinary['license_id']]);
    $providerScenario='normal';$postReservation=null;$afterOldRetirement=null;$afterCandidateRead=null;
    \FinancePayoffRetirement\license_finance_subscription_create_profile($ordinary['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
    $ordinaryDoc=recurring_billing_validate(recurring_billing_row($ordinary['schedule_id']));$ordinaryTerms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($ordinaryDoc->data()['terms']);
    foreach([0=>'980001',1=>'980002'] as $index=>$transaction){
        $issued=license_finance_issue_period($ordinary['id'],$index,strtotime($ordinaryTerms->period($index)['start'].' 12:00:00 UTC'));$id=(int)$issued['invoice_id'];
        ledger_insert(ledger_invoice($id),0,'gateway',1000,'authorize_net','Synthetic ordinary gateway receipt',gmdate('Y-m-d'),null,null,hash('sha256','authorize-net|'.$transaction));
        query('UPDATE '.table('studio_invoices')." SET status='paid' WHERE id=?",[$id]);
        query('INSERT INTO '.table('license_finance_subscription_receipts').' (offer_id,gateway_transaction_id,period_index,invoice_id,created_at) VALUES (?,?,?,?,?)',[$ordinary['id'],$transaction,$index,$id,gmdate('Y-m-d H:i:s')]);$ordinaryInvoices[$index]=$id;
    }
    $ordinaryResult=license_finance_payment_reversal_apply(['kind'=>'refund','transaction_id'=>'990001','original_transaction_id'=>'980001','amount_cents'=>100]);
    fep_ok('Ordinary partial refunds still enter review',$ordinaryResult==='finance_partial_refund_review'&&license_finance_subscription_row($ordinary['id'])['status']==='review');
    $ordinaryResult=license_finance_payment_reversal_apply(['kind'=>'refund','transaction_id'=>'990002','original_transaction_id'=>'980001','amount_cents'=>900]);
    fep_ok('Ordinary full refunds still reopen recovery and accounting',$ordinaryResult==='finance_refund_reversed'&&license_finance_subscription_row($ordinary['id'])['status']==='reversal_recovery'&&(int)ledger_balance(ledger_invoice($ordinaryInvoices[0]))['due_cents']===1000);
    $ordinaryResult=license_finance_payment_reversal_apply(['kind'=>'void','transaction_id'=>'990003','original_transaction_id'=>'980002','amount_cents'=>null]);
    fep_ok('Ordinary voids still reopen recovery and accounting',$ordinaryResult==='finance_void_reversed'&&license_finance_subscription_row($ordinary['id'])['status']==='reversal_recovery'&&(int)ledger_balance(ledger_invoice($ordinaryInvoices[1]))['due_cents']===1000);
}catch(Throwable $e){fep_ok('fixture exception: '.get_class($e).' '.$e->getMessage(),false);}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));$sources=[];
foreach(['app/billing/finance_subscription.php','app/licenses/financing_policy.php','app/licenses/finance_installments.php','app/billing/finance_payment_review.php'] as $path)$sources[$path]=hash_file('sha256',$root.'/site/'.$path);
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),'sources'=>$sources,'scope'=>'Real lifecycle service, native SQL locks/audit, actual owner partial receipt and scheduler retry. Synthetic provider boundaries and signed future invoice.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
