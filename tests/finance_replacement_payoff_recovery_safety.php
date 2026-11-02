<?php
/** Required failure, recovery, accounting, and populated-upgrade cases. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);

function frg_audit_failure(string $action,callable $work):mixed{
    global $prefix;
    if(!preg_match('/^[a-z.]+$/D',$action))throw new LogicException('Unsafe synthetic audit action.');
    $trigger=chr(96).$prefix.'frg_reject_audit'.chr(96);
    query('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit').
        " FOR EACH ROW BEGIN IF NEW.action='license.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic finance recovery audit rollback'; END IF; END");
    try{return $work();}finally{query('DROP TRIGGER '.$trigger);}
}
function frg_safe_snapshot():array{
    return fep_snapshot()+['attempts'=>all('SELECT * FROM '.table('license_finance_replacement_attempts').' ORDER BY id'),
        'collections'=>all('SELECT * FROM '.table('license_finance_collection_reviews').' ORDER BY id'),
        'audit'=>all('SELECT * FROM '.table('audit').' ORDER BY id')];
}
function frg_suspended_seed(string $name):array{
    [$offer,$old,$invoices]=frg_seed($name);
    $GLOBALS['frgProvider'][$old]['status']='suspended';
    \FinanceRecovery\license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.suspended',$old);
    return [$offer,$old,$invoices];
}
function frg_prepared_request(array $offer,int $invoice):array{
    // Canonical retained request metadata, without creating or exercising an
    // HTTP payment token. Admission below uses the real current request reader.
    $row=payments_invoice($invoice);$key=hash('sha256','frg-request-'.$invoice);
    query('INSERT INTO '.table('payment_requests').' (invoice_id,job_id,client_id,actor_id,request_key,request_hash,invoice_hash,link_hash,link_secret,purpose,status,amount_cents,currency,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$invoice,$row['job_id'],$offer['client_id'],1,$key,hash('sha256','frg-request-body-'.$invoice),payments_invoice_hash($row),
         hash('sha256','synthetic-unavailable-http-token-'.$invoice),'synthetic-no-http-token','financing','prepared',
         ledger_balance($row)['due_cents'],'USD',time()+86400,gmdate('Y-m-d H:i:s')]);
    return one('SELECT * FROM '.table('payment_requests').' WHERE id=?',[(int)db()->lastInsertId()]);
}
function frg_recovery_safety_cases():void{
    global $observations,$password;
    [$offer,$old,$invoices]=frg_suspended_seed('durable-reservation');
    $contract=\FinanceRecovery\license_finance_subscription_replacement_contract($offer);
    $original=license_finance_subscription_row($offer['id']);$dispatch=null;$writer=null;
    $GLOBALS['frgDuringCreate']=static function()use($offer,$invoices,&$dispatch,&$writer):void{
        $dispatch=license_finance_recovery_open($offer['id']);
        $writer=frg_receipt_process(array_map(fn($invoice)=>[$invoice,1000],$invoices),'durable-reservation-payoff');
    };
    $GLOBALS['frgScenario']='lost_create_reply';rb_client();
    $result=\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');
    $new=(string)$GLOBALS['frgNextGateway'];$attempt=license_finance_recovery_open($offer['id']);
    fep_ok('FIN-01 durable reservation exists before external create reply',
        $dispatch&&$dispatch['state']==='dispatching'&&$dispatch['dispatched_at']!==null
        &&$dispatch['old_gateway_subscription_id']===$old&&$dispatch['gateway_subscription_id']===null);
    fep_ok('FIN-01 reservation retains exact original signed contract and predecessor',
        json_decode($dispatch['contract_json'],true)===$contract
        &&json_decode($dispatch['predecessor_json'],true)===license_finance_recovery_predecessor($original)
        &&hash('sha256',$dispatch['contract_json'])===$dispatch['contract_sha256']);
    fep_ok('FIN-01 reservation retains only nonsecret provider context',
        $dispatch['environment']==='production'&&$dispatch['account_hash']===hash('sha256','synthetic-finance-account')
        &&(int)$dispatch['gateway_revision']===1
        &&!str_contains(json_encode($dispatch),'synthetic-key')&&!str_contains(json_encode($dispatch),'COMMON.ACCEPT'));
    fep_ok('FIN-01 committed payoff cannot close unknown successor reservation',
        $result==='review'&&$attempt['state']==='unknown'&&$attempt['open_offer_id']===$offer['id']
        &&(int)$attempt['retirement_required']===1&&count($writer['entries'])===3);
    $before=frg_safe_snapshot();$summary=\FinanceRecovery\license_finance_scheduler_run();
    fep_ok('FIN-01 scheduler reports unresolved successor after permanent payoff',
        $summary['state']==='partial'&&license_finance_recovery_open($offer['id'])['id']===$attempt['id']
        &&license_financing_plan((int)$offer['license_id'])['paid_off_at']!==null);
    $saved=license_finance_recovery_open($offer['id']);
    foreach(['failed','suspended','cancelled','expired'] as $event){
        \FinanceRecovery\license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.'.$event,$old);
    }
    fep_ok('FIN-01 delayed predecessor events preserve independent unknown obligation',license_finance_recovery_open($offer['id'])===$saved);
    $before=frg_safe_snapshot();
    $GLOBALS['frgScenario']='normal';$GLOBALS['frgGatewayContext']=['login'=>'different-synthetic-account'];$start=count($GLOBALS['frgCalls']);rb_staff();$denied=false;
    try{\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$new,$password);}catch(RuntimeException){$denied=true;}
    fep_ok('FIN-01 recovery rejects a different merchant before gateway I/O',$denied&&count($GLOBALS['frgCalls'])===$start&&license_finance_recovery_open($offer['id'])===$saved);
    $GLOBALS['frgGatewayContext']=['revision'=>2];
    $recovered=\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$new,$password);
    fep_ok('FIN-01 same-account key revision permits exact post-payoff recovery',$recovered==='paid_off'&&$GLOBALS['frgProvider'][$new]['status']==='canceled'&&license_finance_recovery_open($offer['id'])===null);
    $after=frg_safe_snapshot();$closed=one('SELECT * FROM '.table('license_finance_replacement_attempts').' WHERE id=?',[$attempt['id']]);
    fep_ok('FIN-01 recovery retains original reservation and committed accounting',
        $closed['contract_json']===$saved['contract_json']&&(int)$closed['gateway_revision']===1&&$closed['state']==='bound'
        &&$before['invoice_payments']===$after['invoice_payments']&&$before['license_financing']===$after['license_financing']);
    $observations[]=['family'=>'FIN durable reservation','dispatch'=>$dispatch,'writer'=>$writer,'closed'=>$closed,'scheduler'=>$summary];

    foreach(['financing.replacement.observed','financing.subscription.replaced'] as $action){
        [$offer,$old,$invoices]=frg_suspended_seed('audit-'.str_replace('.','-',$action));$proof=null;
        $GLOBALS['frgDuringCreate']=static function()use($invoices,$action,&$proof):void{
            $proof=frg_receipt_process(array_map(fn($invoice)=>[$invoice,1000],$invoices),'audit-'.$action);
        };
        rb_client();
        $result=frg_audit_failure($action,fn()=>\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic'));
        $new=(string)$GLOBALS['frgNextGateway'];$open=license_finance_recovery_open($offer['id']);$sub=license_finance_subscription_row($offer['id']);
        fep_ok('FIN-01 '.$action.' rollback retains unresolved reservation',
            $result==='review'&&$open&&$open['open_offer_id']===$offer['id']&&$sub['gateway_subscription_id']===$old
            &&license_finance_subscription_history_row($old)===null);
        fep_ok('FIN-01 '.$action.' rollback preserves independently committed payoff',
            count($proof['entries'])===3&&license_financing_plan((int)$offer['license_id'])['paid_off_at']!==null
            &&$GLOBALS['frgProvider'][$new]['status']==='active');
        fep_ok('FIN-01 '.$action.' retains known ID whenever its observation committed',
            $action==='financing.replacement.observed'?$open['gateway_subscription_id']===null:$open['gateway_subscription_id']===$new);
        $retained=frg_safe_snapshot();$start=count($GLOBALS['frgCalls']);$refused=false;
        try{\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');}catch(Throwable){$refused=true;}
        fep_ok('FIN-01 '.$action.' rollback prevents another create',$refused&&count($GLOBALS['frgCalls'])===$start);
        rb_staff();
        if($open['gateway_subscription_id']===null)$recovered=\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$new,$password);
        else{$summary=\FinanceRecovery\license_finance_scheduler_run();$recovered=license_finance_subscription_row($offer['id'])['status'];}
        fep_ok('FIN-01 '.$action.' owner or scheduler finishes exact successor recovery',
            $recovered==='paid_off'&&$GLOBALS['frgProvider'][$new]['status']==='canceled'&&license_finance_recovery_open($offer['id'])===null);
        fep_ok('FIN-01 '.$action.' recovery never duplicates payoff receipts',$retained['invoice_payments']===fep_snapshot()['invoice_payments']);
        $observations[]=['family'=>'FIN-01 audit rollback','action'=>$action,'writer'=>$proof,'retained_attempt'=>$open,'recovered'=>$recovered];
    }

    [$offer,$old,$invoices]=frg_suspended_seed('unsent-reservation');
    $contract=\FinanceRecovery\license_finance_subscription_replacement_contract($offer);
    \FinanceRecovery\license_finance_subscription_replacement_reserve($offer['id'],$contract,1);
    $open=license_finance_recovery_open($offer['id']);$start=count($GLOBALS['frgCalls']);rb_staff();
    $summary=\FinanceRecovery\license_finance_scheduler_run();
    fep_ok('FIN-01 scheduler safely closes only a proven unsent reservation',
        $open['dispatched_at']===null&&license_finance_recovery_open($offer['id'])===null
        &&license_finance_subscription_row($offer['id'])['status']==='billing_suspended'&&$GLOBALS['frgProvider'][$old]['status']==='canceled');
    fep_ok('FIN-01 unsent recovery performs no create',!array_filter(array_slice($GLOBALS['frgCalls'],$start),fn($call)=>$call['method']==='ARBCreateSubscriptionRequest'));

    foreach(['cancel_timeout','cancel_error','lost_cancel_reply','still_active','readback_timeout','wrong_readback','disabled','audit_retirement'] as $fault){
        [$offer,$gateway,$invoices]=frg_seed('receipt-fault-'.$fault,true);
        $work=fn()=>frg_receipt_process([[$invoices[1],500]],'receipt-fault-'.$fault,
            in_array($fault,['disabled','audit_retirement'],true)?'normal':$fault,$fault==='disabled');
        $proof=$fault==='audit_retirement'?frg_audit_failure('financing.subscription.replacement.retired',$work):$work();
        $sub=license_finance_subscription_row($offer['id']);$snapshot=fep_snapshot();$start=count($GLOBALS['frgCalls']);
        fep_ok('FIN-02 '.$fault.' keeps committed receipt and exact retirement obligation',
            count($proof['entries'])===1&&ledger_balance(ledger_invoice($invoices[1]))['paid_cents']===500
            &&$sub['status']==='retirement_pending'&&$sub['gateway_subscription_id']===$gateway);
        fep_ok('FIN-02 '.$fault.' never attempts I/O in the receipt transaction or staff mutex',
            !array_filter($proof['calls'],fn($call)=>$call['transaction']||$call['mutex_owned']));
        if($fault==='disabled')fep_ok('FIN-02 disabled settings retain hold without transport',$proof['calls']===[]);
        foreach(['failed','suspended','cancelled','expired'] as $event){
            \FinanceRecovery\license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.'.$event,$gateway);
        }
        fep_ok('FIN-02 '.$fault.' delayed lifecycle cannot clear retirement',license_finance_subscription_row($offer['id'])===$sub);
        $refused=false;rb_client();
        try{\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');}catch(Throwable){$refused=true;}
        fep_ok('FIN-02 '.$fault.' forbids replacement while retirement is unresolved',$refused&&count($GLOBALS['frgCalls'])===$start);
        $GLOBALS['frgScenario']='normal';rb_staff();
        if(in_array($fault,['disabled','audit_retirement'],true)){
            $summary=\FinanceRecovery\license_finance_scheduler_run();$recovered=license_finance_subscription_row($offer['id'])['status'];
        }else $recovered=\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$gateway,$password);
        fep_ok('FIN-02 '.$fault.' owner or scheduler retries exact terminal verification',
            $recovered==='billing_suspended'&&$GLOBALS['frgProvider'][$gateway]['status']==='canceled');
        fep_ok('FIN-02 '.$fault.' retry preserves original receipts and license state',
            $snapshot['invoice_payments']===fep_snapshot()['invoice_payments']&&$snapshot['license_financing']===fep_snapshot()['license_financing']);
        if($fault==='disabled'){
            $beforeRetry=fep_snapshot();$beforeCreates=count(array_filter($GLOBALS['frgCalls'],fn($call)=>$call['method']==='ARBCreateSubscriptionRequest'));
            \FinanceRecovery\license_finance_scheduler_run();
            fep_ok('FIN-02 '.$fault.' repeated scheduler recovery is idempotent',
                fep_snapshot()===$beforeRetry&&count(array_filter($GLOBALS['frgCalls'],fn($call)=>$call['method']==='ARBCreateSubscriptionRequest'))===$beforeCreates);
        }
        $observations[]=['family'=>'FIN-02 cancellation failure','fault'=>$fault,'writer'=>$proof,'recovered'=>$recovered];
    }

    foreach(['cancel_timeout','lost_cancel_reply'] as $fault){
        [$offer,$gateway,$invoices]=frg_seed('repeated-receipt-'.$fault,true);
        $before=fep_snapshot();$start=count($GLOBALS['frgCalls']);$proofs=[];
        $proofs[]=frg_receipt_process([[$invoices[1],300]],'repeated-first-'.$fault,$fault);
        fep_ok('FIN-02 repeated '.$fault.' first receipt attempts immediate retirement',
            license_finance_subscription_row($offer['id'])['status']==='retirement_pending'
            &&count(array_filter($proofs[0]['calls'],fn($call)=>$call['method']==='ARBCancelSubscriptionRequest'))===1
            &&$GLOBALS['frgProvider'][$gateway]['status']===($fault==='lost_cancel_reply'?'canceled':'active'));
        $proofs[]=frg_receipt_process([[$invoices[2],1000]],'repeated-later-'.$fault,$fault);
        fep_ok('FIN-02 repeated '.$fault.' later receipt uses a fresh truthful readback',
            license_finance_subscription_row($offer['id'])['status']===($fault==='lost_cancel_reply'?'billing_suspended':'retirement_pending')
            &&count(array_filter($proofs[1]['calls'],fn($call)=>$call['method']==='ARBCancelSubscriptionRequest'))===($fault==='lost_cancel_reply'?0:1)
            &&count(array_filter($proofs[1]['calls'],fn($call)=>$call['method']==='ARBGetSubscriptionRequest'))>=1);
        $proofs[]=frg_receipt_process([[$invoices[1],700]],'repeated-payoff-'.$fault,$fault);
        $sub=license_finance_subscription_row($offer['id']);$plan=license_financing_plan((int)$offer['license_id']);
        fep_ok('FIN-02 repeated '.$fault.' final payoff retains the truthful terminal or review state',
            $sub['status']===($fault==='lost_cancel_reply'?'paid_off':'review')&&$sub['gateway_subscription_id']===$gateway
            &&$plan['state']==='paid_off'&&$plan['paid_off_at']!==null);
        $calls=array_slice($GLOBALS['frgCalls'],$start);$after=fep_snapshot();
        fep_ok('FIN-02 repeated '.$fault.' records exactly three receipts without another create',
            count($after['invoice_payments'])===count($before['invoice_payments'])+3
            &&count(array_unique(array_merge(...array_column($proofs,'entries'))))===3
            &&!array_filter($calls,fn($call)=>$call['method']==='ARBCreateSubscriptionRequest'));
        fep_ok('FIN-02 repeated '.$fault.' all retirement I/O stays outside transaction and mutex',
            !array_filter($calls,fn($call)=>$call['transaction']||$call['mutex_owned']));
        $GLOBALS['frgScenario']='normal';rb_staff();
        $recovered=\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$gateway,$password);
        fep_ok('FIN-02 repeated '.$fault.' explicit recovery preserves receipts and permanent payoff',
            $recovered==='paid_off'&&$GLOBALS['frgProvider'][$gateway]['status']==='canceled'
            &&$after['invoice_payments']===fep_snapshot()['invoice_payments']
            &&$after['license_financing']===fep_snapshot()['license_financing']);
        $observations[]=['family'=>'FIN-02 repeated receipt retirement','fault'=>$fault,'writers'=>$proofs,'recovered'=>$recovered];
    }

    [$offer,$gateway,$invoices]=frg_seed('receipt-audit-rollback');rb_staff();$before=frg_safe_snapshot();$error=null;
    frg_audit_failure('financing.subscription.receipt.hold',function()use($invoices,&$error):void{
        try{\FinanceRecovery\ledger_receive($invoices[0],500,'bank','Synthetic receipt audit rollback',gmdate('Y-m-d'),
            hash('sha256','receipt-audit-rollback'),ledger_review_hash(ledger_invoice($invoices[0])));}catch(Throwable $caught){$error=$caught;}
    });
    fep_ok('FIN-02 receipt audit failure rolls back receipt and hold atomically',
        $error instanceof PDOException&&frg_safe_snapshot()===$before&&ledger_balance(ledger_invoice($invoices[0]))['paid_cents']===0);
    fep_ok('FIN-02 receipt audit rollback leaves healthy provider contract unchanged',$GLOBALS['frgProvider'][$gateway]['status']==='active'&&!db()->inTransaction());

    foreach([false,true] as $active){
        [$offer,$gateway,$invoices]=frg_seed('paid-prefix-'.($active?'active':'pending'),$active);$period=$active?1:0;
        $proof=frg_receipt_process([[$invoices[$period],1000]],'paid-prefix-'.$period);$before=fep_snapshot();rb_client();
        $result=\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');
        $new=(string)$GLOBALS['frgNextGateway'];$sub=license_finance_subscription_row($offer['id']);
        fep_ok('FIN-02 complete paid prefix '.$period.' admits only the reduced remaining contract',
            $result==='pending_first_payment'&&(int)$sub['period_offset']===$period+1&&(int)$sub['total_occurrences']===2-$period
            &&$sub['gateway_subscription_id']===$new&&$new!==$gateway);
        fep_ok('FIN-02 complete paid prefix '.$period.' retains retired predecessor and receipts',
            $GLOBALS['frgProvider'][$gateway]['status']==='canceled'&&license_finance_subscription_history_row($gateway)!==null
            &&$before['invoice_payments']===fep_snapshot()['invoice_payments']);
    }

    $request=null;
    $GLOBALS['frgBeforeInitial']=static function(array $offer,array $invoices)use(&$request):void{
        $request=frg_prepared_request($offer,$invoices[2]);
    };
    [$offer,$gateway,$invoices]=frg_seed('prepared-competing-charge');$beforeCount=(int)query('SELECT COUNT(*) FROM '.table('license_finance_charge_attempts'))->fetchColumn();$refused=false;
    try{\FinanceRecovery\license_finance_charge_prepare($request,hash('sha256','competing-healthy'));}catch(RuntimeException){$refused=true;}
    fep_ok('FIN-02 existing healthy ARB fences a previously prepared separate charge',
        $refused&&(int)query('SELECT COUNT(*) FROM '.table('license_finance_charge_attempts'))->fetchColumn()===$beforeCount);
    $proof=frg_receipt_process([[$invoices[0],1000]],'competing-disabled','normal',true);$refused=false;
    try{\FinanceRecovery\license_finance_charge_prepare($request,hash('sha256','competing-hold'));}catch(RuntimeException){$refused=true;}
    fep_ok('FIN-02 unresolved retirement fences an unchanged later-period payment link',
        $refused&&payments_request_state(one('SELECT * FROM '.table('payment_requests').' WHERE id=?',[$request['id']]))==='prepared'
        &&(int)query('SELECT COUNT(*) FROM '.table('license_finance_charge_attempts'))->fetchColumn()===$beforeCount);
    rb_staff();\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$gateway,$password);
    $prepared=\FinanceRecovery\license_finance_charge_prepare($request,hash('sha256','competing-after-retirement'));
    fep_ok('FIN-02 terminal readback permits an otherwise legitimate separate charge',
        $prepared['invoice']===$invoices[2]&&(int)$prepared['amount_cents']===1000&&$GLOBALS['frgProvider'][$gateway]['status']==='canceled');
    $start=count($GLOBALS['frgCalls']);$refused=false;rb_client();
    try{\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');}catch(RuntimeException){$refused=true;}
    fep_ok('FIN-02 an admitted separate charge prevents a competing replacement create',$refused&&count($GLOBALS['frgCalls'])===$start);

    frg_collection_cases();
    frg_migration_cases();
}

function frg_collection_cases():void{
    global $observations,$password;
    foreach(['before_lock','already_committed','accounting_audit','paid_prefix'] as $timing){
        [$offer,$gateway,$invoices]=frg_seed('external-collection-'.$timing,true);$writer=null;
        if($timing==='before_lock')$GLOBALS['frgBeforeLock']=static function()use($invoices,$timing,&$writer):void{
            $writer=frg_receipt_process([[$invoices[1],500]],'collection-'.$timing,'normal',true);
        };
        else $writer=frg_receipt_process([[$invoices[1],$timing==='paid_prefix'?1000:500]],'collection-'.$timing,'normal',true);
        $transaction=(string)(995000000+$invoices[1]);$details=frg_settlement($offer,$gateway,1,$transaction);
        $beforePayments=all('SELECT * FROM '.table('invoice_payments').' ORDER BY id');$error=null;$result=null;
        $work=function()use($details,$transaction,&$error,&$result):void{
            try{$result=\FinanceRecovery\license_finance_subscription_settle($details,$transaction);}catch(Throwable $caught){$error=$caught;}
        };
        if($timing==='accounting_audit')frg_audit_failure('financing.collection.review',$work);else $work();
        $review=one('SELECT * FROM '.table('license_finance_collection_reviews').' WHERE gateway_transaction_id=?',[$transaction]);
        if($timing==='accounting_audit'){
            fep_ok('FIN-02 collection audit rollback leaves no partial accounting observation',$error instanceof PDOException&&$review===null);
            fep_ok('FIN-02 collection audit rollback preserves the committed external receipt',all('SELECT * FROM '.table('invoice_payments').' ORDER BY id')===$beforePayments);
            $error=null;$work();$review=one('SELECT * FROM '.table('license_finance_collection_reviews').' WHERE gateway_transaction_id=?',[$transaction]);
        }
        fep_ok('FIN-02 '.$timing.' preserves a verified full external collection for accounting',
            $error===null&&$result==='finance_subscription_review_required'&&$review
            &&$review['gateway_subscription_id']===$gateway&&(int)$review['invoice_id']===$invoices[1]
            &&(int)$review['amount_cents']===1000&&$review['state']==='needs_accounting');
        fep_ok('FIN-02 '.$timing.($timing==='paid_prefix'?' preserves the fully paid prefix':' never double counts the partial receipt'),
            ledger_balance(ledger_invoice($invoices[1]))['paid_cents']===($timing==='paid_prefix'?1000:500)
            &&!one('SELECT id FROM '.table('license_finance_subscription_receipts').' WHERE gateway_transaction_id=?',[$transaction]));
        $before=frg_safe_snapshot();rb_staff();$again=\FinanceRecovery\license_finance_subscription_settle($details,$transaction,true,$password);
        fep_ok('FIN-02 '.$timing.' verified event replay is idempotent',$again==='finance_subscription_review_required'&&frg_safe_snapshot()===$before);
        \FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$gateway,$password);
        fep_ok('FIN-02 '.$timing.' retirement preserves the separate accounting obligation',
            $GLOBALS['frgProvider'][$gateway]['status']==='canceled'
            &&one('SELECT * FROM '.table('license_finance_collection_reviews').' WHERE gateway_transaction_id=?',[$transaction])===$review);
        $request=frg_prepared_request($offer,$invoices[$timing==='paid_prefix'?2:1]);$start=count($GLOBALS['frgCalls']);$refused=false;
        try{\FinanceRecovery\license_finance_charge_prepare($request,hash('sha256','unallocated-'.$transaction));}
        catch(RuntimeException $error){$refused=str_contains($error->getMessage(),'retained external financing collection');}
        fep_ok('FIN-02 '.$timing.' unresolved external collection prevents another charge',
            $refused&&!one('SELECT id FROM '.table('license_finance_charge_attempts').' WHERE invoice_id=?',[$request['invoice_id']])&&count($GLOBALS['frgCalls'])===$start);
        $refused=false;
        try{\FinanceRecovery\license_finance_subscription_replacement_contract($offer);}
        catch(RuntimeException $error){$refused=str_contains($error->getMessage(),'retained external financing collection');}
        fep_ok('FIN-02 '.$timing.' unresolved external collection fences replacement admission',$refused);
        $observations[]=['family'=>'FIN-02 late collection','timing'=>$timing,'writer'=>$writer,'collection'=>$review,'result'=>$result];
    }
}

function frg_migration_cases():void{
    global $prefix,$password,$observations;
    $originalPrefix=$prefix;$originalConfig=$GLOBALS['cwp_config'];$originalSettings=$GLOBALS['cwp_settings']??null;$session=$_SESSION;
    try{
        $prefix='frg_m_'.substr(hash('sha256',$originalPrefix),0,8).'_';
        $GLOBALS['cwp_config']['db']['prefix']=$prefix;unset($GLOBALS['cwp_settings']);
        foreach(schema_statements($prefix)+service_plans_schema($prefix)+recurring_billing_schema($prefix)+license_financing_schema($prefix)+license_finance_offers_schema($prefix)+license_finance_gateway_schema($prefix) as $sql)query($sql);
        foreach(['studio_schema_version'=>'2','staff_schema_version'=>'1','payments_schema_version'=>'2','support_schema_version'=>'1','service_plans_schema_version'=>'1','recurring_billing_schema_version'=>'1','invoice_ledger_schema_version'=>'1','license_financing_schema_version'=>'2','license_finance_offers_schema_version'=>'1','license_finance_gateway_schema_version'=>'3'] as $key=>$value)save_setting($key,$value);
        $now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Synthetic migration owner','migration@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('a',32),$now]);staff_seed_owner(1);
        for($id=1;$id<=2;$id++){
            query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Synthetic migration client '.$id,'migration'.$id.'@example.invalid',str_repeat((string)$id,32),$now]);
            query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$id,'Synthetic migration project '.$id,'Retained migration fixture','design','',$now,$now]);
        }
        $GLOBALS['frgSkipRecoveryPreparation']=true;
        [$offer,$gateway,$invoices]=frg_seed('legacy-migration');
        // Explicit populated upgrade fixture for an older ambiguous replacement,
        // not a functional transition or inferred historical reservation.
        query('UPDATE '.table('license_finance_subscriptions').' SET status=? WHERE offer_id=?',['review',$offer['id']]);
        query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
            ['Synthetic migration administrator','migration-admin@example.invalid',one('SELECT password_hash FROM '.table('users').' WHERE id=1')['password_hash'],str_repeat('b',32),$now]);
        query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,?,?)',[2,'administrator',1,'[]']);
        $before=fep_snapshot();$auditCount=(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn();rb_staff(2);$refused=false;
        try{license_finance_recovery_prepare($password);}catch(RuntimeException){$refused=true;}
        fep_ok('FIN recovery preparation requires current owner authority before any schema or marker write',
            $refused&&!license_finance_recovery_ready()&&setting('license_finance_recovery_schema_pending')===''
            &&!license_finance_gateway_table_present('license_finance_replacement_attempts')&&fep_snapshot()===$before
            &&(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn()===$auditCount);
        rb_staff();db()->beginTransaction();$refused=false;
        try{license_finance_recovery_prepare($password);}catch(LogicException){$refused=true;}
        finally{db()->rollBack();}
        fep_ok('FIN recovery preparation refuses an inherited transaction without DDL or history mutation',
            $refused&&!license_finance_gateway_table_present('license_finance_replacement_attempts')&&fep_snapshot()===$before);
        // Reproduce a crash after the first empty additive table was created.
        save_setting('license_finance_recovery_schema_pending','1');
        query(license_finance_recovery_schema($prefix)['license_finance_replacement_attempts']);
        license_finance_recovery_prepare($password);
        fep_ok('FIN recovery preparation resumes only explicitly marked empty additive storage',
            license_finance_recovery_ready()&&setting('license_finance_recovery_schema_pending')===''&&fep_snapshot()===$before);
        fep_ok('FIN migration adds recovery v1 to populated gateway v3 without changing history',
            license_finance_recovery_ready()&&setting('license_finance_gateway_schema_version')==='3'&&fep_snapshot()===$before);
        fep_ok('FIN migration never fabricates a legacy replacement contract',
            (int)query('SELECT COUNT(*) FROM '.table('license_finance_replacement_attempts'))->fetchColumn()===0);
        $start=count($GLOBALS['frgCalls']);$refused=false;
        try{\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],(string)((int)$gateway+9000),$password);}
        catch(RuntimeException $error){$refused=str_contains($error->getMessage(),'no retained original contract');}
        fep_ok('FIN legacy ambiguous replacement requires explicit manual reconciliation',
            $refused&&count($GLOBALS['frgCalls'])===$start&&fep_snapshot()===$before);
        $GLOBALS['frgSkipRecoveryPreparation']=false;
        [$newOffer,$old,$newInvoices]=frg_suspended_seed('populated-recovery');
        \FinanceRecovery\license_finance_subscription_replacement_reserve($newOffer['id'],null,1);
        $history=frg_safe_snapshot();rb_staff();license_finance_gateway_prepare($password);
        fep_ok('FIN repeated owner preparation preserves populated recovery and original v3 history',
            frg_safe_snapshot()===$history&&license_finance_recovery_ready());
        foreach(['unknown_version','unmarked_tables','populated_interruption','missing_table'] as $fault){
            $backup=chr(96).$prefix.'recovery_collection_backup'.chr(96);
            if($fault==='unknown_version')save_setting('license_finance_recovery_schema_version','9');
            elseif($fault==='unmarked_tables'){save_setting('license_finance_recovery_schema_version','');save_setting('license_finance_recovery_schema_pending','');}
            elseif($fault==='populated_interruption'){save_setting('license_finance_recovery_schema_version','');save_setting('license_finance_recovery_schema_pending','1');}
            else query('RENAME TABLE '.table('license_finance_collection_reviews').' TO '.$backup);
            $error=null;$base=fep_snapshot();
            try{license_finance_recovery_prepare($password);}catch(Throwable $caught){$error=$caught;}
            fep_ok('FIN migration '.$fault.' refuses adoption and preserves established finance data',
                $error instanceof RuntimeException&&!($error instanceof PDOException)&&fep_snapshot()===$base);
            if($fault==='missing_table')query('RENAME TABLE '.$backup.' TO '.table('license_finance_collection_reviews'));
            save_setting('license_finance_recovery_schema_version','1');save_setting('license_finance_recovery_schema_pending','');
        }
        $observations[]=['family'=>'FIN populated migration','prefix'=>$prefix,'legacy_subscription'=>$before['license_finance_subscriptions'],'retained_attempts'=>$history['attempts']];
    }finally{
        $GLOBALS['frgSkipRecoveryPreparation']=false;$prefix=$originalPrefix;$GLOBALS['cwp_config']=$originalConfig;
        if($originalSettings===null)unset($GLOBALS['cwp_settings']);else $GLOBALS['cwp_settings']=$originalSettings;
        $_SESSION=$session;
    }
}
