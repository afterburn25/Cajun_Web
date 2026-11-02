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


    $observations=[];$ownerCommits=0;
    $scenarios=['normal','before_create','during_create','during_readback','readback_timeout','wrong_readback','cancel_error','cancel_timeout','lost_cancel_reply','still_active'];
    foreach($scenarios as $scenario){
        $offer=fep_seed('replace-'.$scenario,500);$license=(int)$offer['license_id'];
        query('UPDATE '.table('license_financing')." SET state='active' WHERE license_id=?",[$license]);
        $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
        $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);$invoices=[];
        for($n=0;$n<3;$n++){
            $period=$terms->period($n);$issued=license_finance_issue_period($offer['id'],$n,strtotime($period['start'].' 12:00:00 UTC'));$invoices[]=(int)$issued['invoice_id'];
        }
        $providerScenario='normal';$oldGateway='';$postReservation=null;$afterOldRetirement=null;$afterCandidateRead=null;$fepCalls=[];
        $created=\FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
        if($created!=='pending_first_payment')throw new RuntimeException('Initial fixture enrollment failed');
        $oldGateway=(string)license_finance_subscription_row($offer['id'])['gateway_subscription_id'];$provider[$oldGateway]['status']='suspended';
        query('UPDATE '.table('license_finance_subscriptions')." SET status='billing_suspended' WHERE offer_id=?",[$offer['id']]);
        $before=fep_snapshot();$paymentEvidence=[];
        $pay=static function()use($offer,$invoices,&$paymentEvidence,&$ownerCommits):void{
            $sub=license_finance_subscription_row($offer['id']);
            if($sub['status']!=='replacing'||db()->inTransaction())throw new RuntimeException('Committed replacement reservation boundary not reached');
            $session=$_SESSION;rb_staff();
            try{
                foreach($invoices as $invoice){
                    $row=ledger_invoice($invoice);$entry=\FinancePayoffRetirement\ledger_receive($invoice,1000,'bank','Synthetic replacement payoff '.$invoice,
                        gmdate('Y-m-d'),hash('sha256','replacement-payoff-'.$invoice),ledger_review_hash($row));
                    $paymentEvidence[]=['entry'=>$entry,'balance'=>ledger_balance(ledger_invoice($invoice))];$ownerCommits++;
                }
            }finally{$_SESSION=$session;}
        };
        if($scenario==='before_create')$afterOldRetirement=$pay;
        elseif($scenario==='during_readback')$afterCandidateRead=$pay;
        elseif($scenario!=='normal')$postReservation=$pay;
        $providerScenario=$scenario;
        $outcome=\FinancePayoffRetirement\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');
        $new=(string)$fepGatewayId;$sub=license_finance_subscription_row($offer['id']);$plan=license_financing_plan($license);$after=fep_snapshot();
        $uncertain=in_array($scenario,['readback_timeout','wrong_readback','cancel_error','cancel_timeout','lost_cancel_reply','still_active'],true);
        $expected=$scenario==='normal'?'pending_first_payment':($uncertain?'review':'paid_off');
        fep_ok($scenario.' expected replacement outcome',$outcome===$expected&&$sub['status']===$expected);
        fep_ok($scenario.' exact retained gateway identity',$sub['gateway_subscription_id']===$new);
        fep_ok($scenario.' correct old-subscription retirement',$provider[$oldGateway]['status']==='canceled');
        fep_ok($scenario.' preserves license identity domains and rights',$before['website_licenses']===$after['website_licenses']&&$before['license_domains']===$after['license_domains']);
        fep_ok($scenario.' verified payoff remains permanent',$scenario==='normal'?$plan['state']==='active'&&$plan['paid_off_at']===null:$plan['state']==='paid_off'&&$plan['paid_off_at']!==null);
        fep_ok($scenario.' owner payments remain committed',$scenario==='normal'?$paymentEvidence===[]:count($paymentEvidence)===3&&array_sum(array_column(array_column($paymentEvidence,'balance'),'due_cents'))===0);
        if($scenario==='before_create'){
            fep_ok($scenario.' avoids creating an unnecessary replacement',$new===$oldGateway&&count(array_filter($fepCalls,fn($c)=>$c['method']==='ARBCreateSubscriptionRequest'))===1);
            fep_ok($scenario.' retains original history without invented replacement',(int)$sub['replacement_count']===0&&license_finance_subscription_history_row($oldGateway)===null);
        }else{
            $history=license_finance_subscription_history_row($oldGateway);
            fep_ok($scenario.' retains verifiable predecessor history',$history&&$history['terminal_status']==='canceled'&&$history['replaced_by_gateway_subscription_id']===$new&&(int)$sub['replacement_count']===1);
            fep_ok($scenario.' retains the exact signed replacement contract',(int)$sub['amount_cents']===1000&&(int)$sub['total_occurrences']===3&&(int)$sub['period_offset']===0&&$sub['start_date']===$terms->period(0)['start']);
        }
        fep_ok($scenario.' does not claim cancellation without proof',$uncertain?$outcome==='review':($scenario==='normal'?$provider[$new]['status']==='active':$provider[$new]['status']==='canceled'));
        $start=count($fepCalls);$refused=false;
        try{\FinancePayoffRetirement\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');}catch(Throwable){$refused=true;}
        fep_ok($scenario.' rejects a second create',$refused&&count($fepCalls)===$start);
        fep_ok($scenario.' releases transaction and mutex',!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40)])->fetchColumn()===1);
        if($uncertain){
            $providerScenario='normal';$baseline=fep_snapshot();$callStart=count($fepCalls);$error=null;
            rb_staff();try{$retry=\FinancePayoffRetirement\license_finance_subscription_reconcile($offer['id'],$new,$password);}catch(Throwable $e){$error=$e;$retry=null;}finally{rb_client();}
            fep_ok($scenario.' owner can reconcile the retained candidate',$error===null&&$retry==='paid_off'&&license_finance_subscription_row($offer['id'])['gateway_subscription_id']===$new&&$provider[$new]['status']==='canceled');
            $current=fep_snapshot();fep_ok($scenario.' reconciliation preserves payments and permanent payoff',$baseline['invoice_payments']===$current['invoice_payments']&&$baseline['license_financing']===$current['license_financing']);
            fep_ok($scenario.' reconciliation never creates another subscription',!array_filter(array_slice($fepCalls,$callStart),fn($c)=>$c['method']==='ARBCreateSubscriptionRequest'));
        }
        $observations[]=['scenario'=>$scenario,'outcome'=>$outcome,'old'=>$oldGateway,'new'=>$new,'calls'=>$fepCalls];
    }
    // The shared reader also runs before the owner starts a reconciliation
    // transaction. These positive controls must work without nested locks.
    foreach(['pending_first_payment','review'] as $state){
        $offer=fep_seed('reader-'.$state,500);$providerScenario='normal';$oldGateway='';$postReservation=null;$afterOldRetirement=null;$afterCandidateRead=null;$fepCalls=[];
        \FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
        query('UPDATE '.table('license_finance_subscriptions').' SET status=? WHERE offer_id=?',[$state,$offer['id']]);
        $before=fep_snapshot();$error=null;$due=null;
        try{$due=\FinancePayoffRetirement\license_finance_subscription_payoff_due($offer);}catch(Throwable $e){$error=$e;}
        fep_ok($state.' outside-transaction reader verifies unpaid ledger',$error===null&&$due===false);
        fep_ok($state.' read leaves financial and license records unchanged',fep_snapshot()===$before);
        $nested=staff_locked(fn()=>staff_transaction(fn()=>\FinancePayoffRetirement\license_finance_subscription_payoff_due($offer,true)));
        fep_ok($state.' reader reuses caller transaction',$nested===false&&!db()->inTransaction());
        $gateway=(string)license_finance_subscription_row($offer['id'])['gateway_subscription_id'];$error=null;rb_staff();
        try{$result=\FinancePayoffRetirement\license_finance_subscription_reconcile($offer['id'],$gateway,$password);}catch(Throwable $e){$error=$e;$result=null;}finally{rb_client();}
        fep_ok($state.' ordinary owner reconciliation stays available',$error===null&&$result===($state==='review'?'pending_first_payment':'already_linked'));
        fep_ok($state.' owner read releases locks',(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40)])->fetchColumn()===1&&!db()->inTransaction());
    }
}catch(Throwable $e){fep_ok('fixture exception: '.get_class($e).' '.$e->getMessage(),false);}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'cases'=>$observations,'owner_commits'=>$ownerCommits,'php'=>PHP_VERSION,
    'database'=>query('SELECT VERSION()')->fetchColumn(),'subscription_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),
    'scope'=>'Exact caller/finalizer/retirement/reconciliation functions and canonical native storage. Actual owner ledger_receive commits at synthetic provider boundaries; real readback validation. Synthetic signed future invoices, initial receipts and provider transport/mode. No real gateway, production flag, HTTP or external approval.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
