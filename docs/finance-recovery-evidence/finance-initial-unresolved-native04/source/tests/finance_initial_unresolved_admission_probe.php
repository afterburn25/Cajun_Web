<?php
/** Independent FIN initial-admission diagnostic. Actual native source; synthetic ARB only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_PORT')!=='33317')exit(2);
$probeSource=file_get_contents(__DIR__.'/finance_initial_receipts_native.php');
$probeStart=strpos($probeSource,"\ntry{\n");
$probeEnd=strpos($probeSource,'    $fepGatewayId=860000;',$probeStart);
if($probeStart===false||$probeEnd===false)throw new RuntimeException('Retained initial fixture setup boundary changed.');
eval(substr($probeSource,5,$probeStart-5));
$GLOBALS['cwp_config']['storage']=(string)getenv('CWP_TEST_PRIVATE_STORAGE');
$_SERVER['DOCUMENT_ROOT']=$root.'/site';
function probe_snapshot():array{
    $out=fep_snapshot();
    foreach(['payment_requests','license_finance_charges','license_finance_charge_attempts','license_finance_replacement_attempts','license_finance_collection_reviews'] as $tableName){
        $out[$tableName]=all('SELECT * FROM '.table($tableName).' ORDER BY id');
    }
    return $out;
}
$observations=[];
try{
    eval(substr($probeSource,$probeStart+6,$probeEnd-($probeStart+6)));
    rb_staff();license_finance_recovery_prepare($password);payments_master(true);
    $GLOBALS['fepGatewayId']=89000000;
    foreach(['uncertain','declined'] as $state){
        $offer=fep_seed('independent-initial-'.$state,500);
        license_financing_sync((int)$offer['license_id']);
        $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
        $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
        $period=$terms->period(1);
        $invoice=(int)license_finance_issue_period($offer['id'],1,strtotime($period['start'].' 12:00:00 UTC'))['invoice_id'];
        rb_staff();$link=payments_financing_request($invoice,1,3,(string)$offer['signature_evidence_hash']);
        $request=one('SELECT * FROM '.table('payment_requests').' WHERE id=?',[$link['id']]);
        $key=hash('sha256','independent-initial-'.$state.'-'.$invoice);
        $prepared=license_finance_charge_prepare($request,$key);
        $marked=license_finance_charge_mark($invoice,$state,null,$key);
        $attempt=one('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$key]);
        fep_ok($state.' uses real charge prepare and mark on an unpaid signed period',
            $marked&&$prepared['invoice']===$invoice&&(int)$prepared['amount_cents']===1000
            &&$attempt['status']===$state&&license_finance_subscription_row($offer['id'])===null
            &&ledger_balance(ledger_invoice($invoice))['paid_cents']===0);
        $GLOBALS['provider']=[];$GLOBALS['fepCalls']=[];$GLOBALS['providerScenario']='normal';$GLOBALS['oldGateway']='';
        $GLOBALS['postReservation']=$GLOBALS['afterOldRetirement']=$GLOBALS['afterCandidateRead']=null;
        rb_client();$before=probe_snapshot();$error=null;$result=null;
        try{$result=\FinancePayoffRetirement\license_finance_subscription_enroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');}
        catch(Throwable $caught){$error=get_class($caught).': '.$caught->getMessage();}
        $after=probe_snapshot();$sub=license_finance_subscription_row($offer['id']);$calls=$GLOBALS['fepCalls'];
        $creates=count(array_filter($calls,fn($call)=>$call['method']==='ARBCreateSubscriptionRequest'));
        $retainedAttempt=one('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$key]);
        if($state==='uncertain'){
            fep_ok('unresolved initial period attempt refuses every new ARB create',$creates===0&&$sub===null);
            fep_ok('unresolved initial period refusal preserves all financial records',$before===$after);
            fep_ok('unresolved one-time outcome remains retained',$retainedAttempt===$attempt);
        }else{
            fep_ok('explicitly declined attempt permits lawful initial enrollment',
                $error===null&&$result==='pending_first_payment'&&$creates===1&&$sub!==null
                &&$GLOBALS['provider'][(string)$sub['gateway_subscription_id']]['status']==='active');
            fep_ok('declined positive control retains its exact earlier attempt',$retainedAttempt===$attempt);
        }
        fep_ok($state.' does not invent an installment receipt',ledger_balance(ledger_invoice($invoice))['paid_cents']===0);
        fep_ok($state.' releases native mutex and keeps provider I/O outside transactions',
            !db()->inTransaction()&&!array_filter($calls,fn($call)=>$call['transaction'])
            &&(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.config()['db']['prefix']),0,40)])->fetchColumn()===1);
        $observations[]=['state'=>$state,'invoice'=>$invoice,'result'=>$result,'error'=>$error,'creates'=>$creates,
            'calls'=>$calls,'attempt_status_before'=>$attempt['status'],'attempt_status_after'=>$retainedAttempt['status'],
            'subscription'=>$sub,'financial_before_sha256'=>hash('sha256',json_encode($before,JSON_THROW_ON_ERROR)),
            'financial_after_sha256'=>hash('sha256',json_encode($after,JSON_THROW_ON_ERROR)),
            'changed_tables'=>array_keys(array_filter($after,fn($rows,$name)=>$rows!==$before[$name],ARRAY_FILTER_USE_BOTH))];
    }
}catch(Throwable $error){fep_ok('fixture exception: '.get_class($error).' '.$error->getMessage(),false);}
$failed=count(array_filter($checks,fn($check)=>!$check['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'cases'=>$observations,
    'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),
    'subscription_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),
    'scope'=>'Two isolated native admission scenarios on reviewer-owned port33317: actual signed agreement, invoice issuance, financing payment request, charge prepare/mark and initial caller. Existing source bodies and native schema; only ARB transport/mode and future signed invoice timing are synthetic. No real charge, provider, HTTP or original assertion changes.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
