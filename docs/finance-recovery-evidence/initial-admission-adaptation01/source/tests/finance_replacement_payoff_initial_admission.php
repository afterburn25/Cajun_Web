<?php
/** Current initial admission versus explicit historical admitted-result recovery. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);

/** Fixture-only historical row, never a substitute for current admission. */
function frg_legacy_initial_reservation(array $offer,array $contract,array $profiles):void{
    if(db()->inTransaction()||license_finance_subscription_row((string)$offer['id'])!==null){
        throw new LogicException('Legacy initial fixture must start without a current reservation.');
    }
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('license_finance_subscriptions').' (offer_id,gateway_subscription_id,customer_profile_id,payment_profile_id,interval_months,amount_cents,total_occurrences,period_offset,replacement_count,start_date,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$offer['id'],null,$profiles['customer_profile_id']??'',$profiles['payment_profile_id']??'',$contract['months'],$contract['amount_cents'],$contract['occurrences'],
         (int)($contract['period_offset']??0),0,$contract['start_date'],'creating',$now,$now]);
}

function frg_initial_unenrolled_seed(string $name):array{
    $GLOBALS['frgProvider']=$GLOBALS['frgCalls']=$GLOBALS['frgDiagnostics']=[];
    $GLOBALS['frgScenario']='normal';$GLOBALS['frgDisabled']=false;$GLOBALS['frgGatewayContext']=[];
    $GLOBALS['frgBeforeLock']=$GLOBALS['frgAfterTerminal']=$GLOBALS['frgDuringCreate']=null;
    $offer=fep_seed('initial-admission-'.$name,500);
    license_financing_sync((int)$offer['license_id'],null,true);
    $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
    $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
    $issued=license_finance_issue_period($offer['id'],1,strtotime($terms->period(1)['start'].' 12:00:00 UTC'));
    return [$offer,(int)$issued['invoice_id'],license_finance_subscription_contract($offer)];
}

function frg_initial_admission_cases():void{
    global $observations,$password,$prefix;
    require_once CWP_CODE_ROOT.'/app/billing/finance_charge.php';
    foreach(['opaque','profile'] as $caller){
        foreach(['processing','uncertain','review','declined'] as $state){
            $name='FIN-ADMISSION initial '.$caller.' '.$state;
            [$offer,$invoice]=frg_initial_unenrolled_seed($caller.'-'.$state);
            $request=frg_prepared_request($offer,$invoice);$key=hash('sha256','initial-admission-'.$caller.'-'.$state.'-'.$invoice);
            \FinanceRecovery\license_finance_charge_prepare($request,$key);
            if($state!=='processing'&&!license_finance_charge_mark($invoice,$state,null,$key))throw new RuntimeException('Initial admission fixture mark failed.');
            $attempt=one('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$key]);
            $charge=one('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice]);
            $before=fep_snapshot();$error=null;$result=null;rb_client();
            try{$result=$caller==='opaque'
                ?\FinanceRecovery\license_finance_subscription_enroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic')
                :\FinanceRecovery\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);}
            catch(Throwable $caught){$error=$caught;}
            $sub=license_finance_subscription_row($offer['id']);$creates=array_filter($GLOBALS['frgCalls'],fn($call)=>$call['method']==='ARBCreateSubscriptionRequest');
            fep_ok($name.($state==='declined'?' permits resolved declined control':' refuses a competing initial subscription'),$state==='declined'
                ?$error===null&&$result==='pending_first_payment'&&count($creates)===1&&(int)$sub['total_occurrences']===3
                :$error instanceof RuntimeException&&str_contains($error->getMessage(),'unresolved outcome')&&$sub===null&&$GLOBALS['frgCalls']===[]);
            $after=fep_snapshot();
            fep_ok($name.' preserves charge identity and every existing receipt',
                $attempt===one('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$key])
                &&$charge===one('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice])
                &&$before['invoice_payments']===$after['invoice_payments']);
            fep_ok($name.' preserves signed ownership and releases financial serialization',
                $before['website_licenses']===$after['website_licenses']&&$before['license_domains']===$after['license_domains']
                &&$before['license_finance_offers']===$after['license_finance_offers']&&!db()->inTransaction()
                &&(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40)])->fetchColumn()===1);
            $observations[]=['family'=>'FIN initial admission','caller'=>$caller,'attempt_state'=>$state,'result'=>$result,
                'error'=>$error?get_class($error).': '.$error->getMessage():null,'calls'=>$GLOBALS['frgCalls']];
        }
    }

    [$offer,$invoice,$contract]=frg_initial_unenrolled_seed('legacy-uncertain-result');
    $request=frg_prepared_request($offer,$invoice);$key=hash('sha256','legacy-uncertain-initial-'.$invoice);
    \FinanceRecovery\license_finance_charge_prepare($request,$key);license_finance_charge_mark($invoice,'uncertain',null,$key);
    $profiles=['customer_profile_id'=>'1001','payment_profile_id'=>'2001'];
    frg_legacy_initial_reservation($offer,$contract,$profiles);
    $gateway=(string)++$GLOBALS['frgNextGateway'];
    $provider=license_finance_anet_arb_profile_request($offer['id'],$contract['amount_cents'],$contract['start_date'],$contract['months'],$contract['occurrences'],'1001','2001');
    $GLOBALS['frgProvider'][$gateway]=$provider['subscription']+['status'=>'active'];
    $attempt=one('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$key]);$before=fep_snapshot();rb_staff();
    $result=\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$gateway,$password);
    fep_ok('FIN-ADMISSION legacy admitted result retains and retires the existing candidate',
        $result==='billing_suspended'&&license_finance_subscription_row($offer['id'])['gateway_subscription_id']===$gateway
        &&$GLOBALS['frgProvider'][$gateway]['status']==='canceled'
        &&!array_filter($GLOBALS['frgCalls'],fn($call)=>$call['method']==='ARBCreateSubscriptionRequest'));
    fep_ok('FIN-ADMISSION legacy recovery does not resolve or duplicate the uncertain charge',
        $attempt===one('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$key])
        &&$before['invoice_payments']===fep_snapshot()['invoice_payments']
        &&$before['license_financing']===fep_snapshot()['license_financing']);
    $observations[]=['family'=>'FIN initial legacy admitted outcome','result'=>$result,'calls'=>$GLOBALS['frgCalls'],
        'scope'=>'Explicit historical creating row and existing synthetic provider candidate; current owner reconciliation/finalizer, no new create admission.'];
}
