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



    $ownerCommits=0;$scenarios=[['untouched','before',[],'normal'],['prefix_one','before',[[0,1000]],'normal'],['prefix_two','before',[[0,1000],[1,1000]],'normal']];
    foreach(['before','reservation','after_retirement','during_create','during_readback'] as $timing){
        foreach(['partial_first'=>[[0,500]],'paid_later'=>[[1,1000]],'partial_later'=>[[1,500]]] as $name=>$payments)$scenarios[]=[$name,$timing,$payments,'normal'];
        if($timing!=='before')$scenarios[]=['paid_first',$timing,[[0,1000]],'normal'];
    }
    foreach(['readback_timeout','wrong_readback','cancel_error','cancel_timeout','lost_cancel_reply','still_active'] as $fault)$scenarios[]=['partial_first','during_create',[[0,500]],$fault];
    foreach($scenarios as [$kind,$timing,$payments,$fault]){
        $name=$kind.'-'.$timing.'-'.$fault;$offer=fep_seed('receipt-'.$name,500);$license=(int)$offer['license_id'];
        query('UPDATE '.table('license_financing')." SET state='active' WHERE license_id=?",[$license]);
        $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
        $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);$invoices=[];
        for($n=0;$n<3;$n++){$period=$terms->period($n);$issued=license_finance_issue_period($offer['id'],$n,strtotime($period['start'].' 12:00:00 UTC'));$invoices[]=(int)$issued['invoice_id'];}
        $providerScenario='normal';$oldGateway='';$postReservation=null;$afterOldRetirement=null;$afterCandidateRead=null;$fepCalls=[];
        $created=\FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
        if($created!=='pending_first_payment')throw new RuntimeException('Initial enrollment failed');
        $oldGateway=(string)license_finance_subscription_row($offer['id'])['gateway_subscription_id'];$provider[$oldGateway]['status']='suspended';
        query('UPDATE '.table('license_finance_subscriptions')." SET status='billing_suspended' WHERE offer_id=?",[$offer['id']]);
        $before=fep_snapshot();$retainedPayments=[];
        $pay=static function()use($invoices,$payments,$name,&$ownerCommits,&$retainedPayments):void{
            if(db()->inTransaction())throw new RuntimeException('Payment attempted during caller transaction');
            $session=$_SESSION;rb_staff();
            try{foreach($payments as [$period,$amount]){
                $invoice=$invoices[$period];\FinancePayoffRetirement\ledger_receive($invoice,$amount,'bank','Synthetic receipt '.$name,gmdate('Y-m-d'),hash('sha256','receipt-'.$invoice),ledger_review_hash(ledger_invoice($invoice)));$ownerCommits++;
            }}finally{$_SESSION=$session;}
            $retainedPayments=all('SELECT * FROM '.table('invoice_payments').' ORDER BY id');
        };
        $contract=license_finance_subscription_replacement_contract($offer);
        if(in_array($timing,['before','reservation'],true))$pay();
        elseif($timing==='after_retirement')$afterOldRetirement=$pay;
        elseif($timing==='during_create')$postReservation=$pay;
        else $afterCandidateRead=$pay;
        $providerScenario=$fault;$callStart=count($fepCalls);$error=null;$result=null;
        try{
            $result=$timing==='reservation'
                ?\FinancePayoffRetirement\license_finance_subscription_replacement_reserve($offer['id'],$contract,1)
                :\FinancePayoffRetirement\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');
        }catch(Throwable $e){$error=get_class($e).': '.$e->getMessage();}
        $calls=array_slice($fepCalls,$callStart);$sub=license_finance_subscription_row($offer['id']);$after=fep_snapshot();$plan=license_financing_plan($license);
        $allowed=in_array($kind,['untouched','prefix_one','prefix_two'],true);$createdCandidate=in_array($timing,['during_create','during_readback'],true);$uncertain=$fault!=='normal';
        if($allowed){
            $offset=$kind==='untouched'?0:($kind==='prefix_one'?1:2);
            fep_ok($name.' admits only the untouched remaining contract',$result==='pending_first_payment'&&(int)$sub['period_offset']===$offset&&(int)$sub['total_occurrences']===3-$offset);
        }elseif($createdCandidate){
            fep_ok($name.' retires or visibly retains the obsolete candidate',$uncertain?$result==='review'&&$sub['status']==='retirement_pending':$result==='billing_suspended'&&$sub['status']==='billing_suspended'&&$provider[(string)$fepGatewayId]['status']==='canceled');
        }else{
            fep_ok($name.' refuses create when remaining receipts changed',!array_filter($calls,fn($c)=>$c['method']==='ARBCreateSubscriptionRequest')&&($error!==null||$result==='review'));
        }
        fep_ok($name.' retains actual owner receipts',$retainedPayments===$after['invoice_payments']);
        fep_ok($name.' preserves license and domains',$before['website_licenses']===$after['website_licenses']&&$before['license_domains']===$after['license_domains']);
        fep_ok($name.' does not invent permanent payoff',$plan['state']==='active'&&$plan['paid_off_at']===null);
        fep_ok($name.' never performs provider calls in a transaction',!array_filter($calls,fn($c)=>$c['transaction']));
        fep_ok($name.' releases transaction and mutex',!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40)])->fetchColumn()===1);
        if($createdCandidate){
            $history=license_finance_subscription_history_row($oldGateway);$new=(string)$fepGatewayId;
            fep_ok($name.' retains candidate identity and predecessor history',$sub['gateway_subscription_id']===$new&&$history&&$history['terminal_status']==='canceled'&&$history['replaced_by_gateway_subscription_id']===$new);
            fep_ok($name.' original subscription remains terminal',$provider[$oldGateway]['status']==='canceled');
            $start=count($fepCalls);$refused=false;
            try{\FinancePayoffRetirement\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');}catch(Throwable){$refused=true;}
            // A full consecutive prefix may be safely re-enrolled after the
            // obsolete candidate is canceled; the call above exercises that
            // positive continuation while all partial/later cases must refuse it.
            if($kind==='paid_first'&&!$uncertain){
                fep_ok($name.' corrected next contract excludes paid prefix',(int)license_finance_subscription_row($offer['id'])['period_offset']===1);
            }else fep_ok($name.' prevents duplicate create',$refused&&count($fepCalls)===$start);
            if($uncertain){
                $providerScenario='normal';$start=count($fepCalls);$snapshot=fep_snapshot();$error=null;
                if($fault==='readback_timeout'){
                    $denied=false;rb_staff();try{\FinancePayoffRetirement\license_finance_subscription_reconcile($offer['id'],$new,'incorrect owner password');}catch(Throwable){$denied=true;}finally{rb_client();}
                    fep_ok($name.' incorrect owner password never reaches gateway',$denied&&count($fepCalls)===$start);
                    fep_ok($name.' denied owner preserves retained candidate',fep_snapshot()===$snapshot);
                }
                $automatic=in_array($fault,['cancel_error','lost_cancel_reply','still_active'],true);
                if($automatic){
                    $summary=\FinancePayoffRetirement\license_finance_scheduler_run();$retry=license_finance_subscription_row($offer['id'])['status'];
                    if($summary['state']!=='complete')$error='Scheduler did not complete';
                }else{
                    rb_staff();try{$retry=\FinancePayoffRetirement\license_finance_subscription_reconcile($offer['id'],$new,$password);}catch(Throwable $e){$error=get_class($e).': '.$e->getMessage();$retry=null;}finally{rb_client();}
                }
                fep_ok($name.($automatic?' scheduler':' owner').' can finish known-candidate retirement',$error===null&&$retry==='billing_suspended'&&$provider[$new]['status']==='canceled');
                $afterRetry=fep_snapshot();fep_ok($name.' retry preserves receipts and payoff state',$snapshot['invoice_payments']===$afterRetry['invoice_payments']&&$snapshot['license_financing']===$afterRetry['license_financing']);
                fep_ok($name.' retry does not create another subscription',!array_filter(array_slice($fepCalls,$start),fn($c)=>$c['method']==='ARBCreateSubscriptionRequest'));
            }
        }
        $observations[]=['case'=>$name,'result'=>$result,'error'=>$error,'calls'=>$calls];
    }
}catch(Throwable $e){fep_ok('fixture exception: '.get_class($e).' '.$e->getMessage(),false);}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'cases'=>$observations,'owner_commits'=>$ownerCommits,'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),'source_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),'scope'=>'Actual owner ledger receipts before reservation and at synthetic provider boundaries. Real native transactions, signed schedules, readback validation and cancellation. No real gateway, production settings, HTTP or independent approval.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
