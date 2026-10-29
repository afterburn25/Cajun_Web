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
$checks=[];$cases=[];require __DIR__.'/finance_initial_receipts_boundaries.php';
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

require_once $root.'/site/app/billing/finance_charge.php';
if(($argv[3]??'')==='writer'){
    $job=json_decode(base64_decode((string)($argv[4]??''),true),true,16,JSON_THROW_ON_ERROR);$invoice=(int)$job['invoice'];
    $offer=license_finance_offer_for_invoice($invoice);$sub=license_finance_subscription_row($offer['id']);
    if(!$sub||$sub['status']!=='creating'||db()->inTransaction())throw new RuntimeException('Separate writer did not observe a committed initial reservation');
    rb_staff();
    if($job['kind']==='gateway')license_finance_charge_record_paid($invoice,(string)$job['transaction'],(int)$job['amount'],(string)$job['attempt']);
    else ledger_receive($invoice,(int)$job['amount'],'bank','Synthetic separate-process enrollment receipt',gmdate('Y-m-d'),hash('sha256','initial-receipt-'.$invoice),ledger_review_hash(ledger_invoice($invoice)));
    echo json_encode(['connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn(),'balance'=>ledger_balance(ledger_invoice($invoice)),'committed'=>!db()->inTransaction()],JSON_THROW_ON_ERROR);exit;
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



    $writerProofs=[];
    $scenarios=[];
    foreach(['opaque','profile'] as $caller){
        foreach(['during_create','during_readback'] as $timing)foreach(['partial','one_paid','prepared_gateway'] as $kind)$scenarios[]=[$caller,$kind,$timing,'normal'];
        foreach(['readback_timeout','wrong_readback','cancel_error','cancel_timeout','lost_cancel_reply','still_active','audit_failure'] as $fault)$scenarios[]=[$caller,'partial','during_create',$fault];
        foreach(['missing_create_id','lost_create_reply'] as $fault)foreach(['partial','none'] as $kind)$scenarios[]=[$caller,$kind,'during_create',$fault];
        $scenarios[]=[$caller,'none','during_create','normal'];
    }
    foreach($scenarios as [$caller,$kind,$timing,$fault]){
        $name=$caller.'-'.$kind.'-'.$timing.'-'.$fault;$offer=fep_seed('initial-'.$name,500);$license=(int)$offer['license_id'];
        query('UPDATE '.table('license_financing')." SET state='active' WHERE license_id=?",[$license]);
        $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));$terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
        $index=$kind==='partial'?0:1;$issued=license_finance_issue_period($offer['id'],$index,strtotime($terms->period($index)['start'].' 12:00:00 UTC'));$invoice=(int)$issued['invoice_id'];$amount=$kind==='none'?0:($kind==='partial'?500:1000);
        $job=['invoice'=>$invoice,'amount'=>$amount,'kind'=>$kind==='prepared_gateway'?'gateway':'manual'];
        if($kind==='prepared_gateway'){
            $now=gmdate('Y-m-d H:i:s');$key=hash('sha256','initial-prepared-'.$invoice);$attempt=hash('sha256','initial-attempt-'.$invoice);$row=ledger_invoice($invoice);
            query('INSERT INTO '.table('payment_requests').' (invoice_id,job_id,client_id,actor_id,request_key,request_hash,invoice_hash,purpose,status,amount_cents,currency,expires_at,created_at) VALUES (?,1,1,1,?,?,?,?,?,?,?,?,?)',[$invoice,$key,hash('sha256','synthetic-request-'.$invoice),payments_invoice_hash($row),'financing','prepared',1000,'USD',time()+86400,$now]);
            $request=one('SELECT * FROM '.table('payment_requests').' WHERE id=?',[(int)db()->lastInsertId()]);$prepared=license_finance_charge_prepare($request,$attempt);
            if($prepared['amount_cents']!==1000||$prepared['create_profile'])throw new RuntimeException('Previously prepared installment attempt mismatch');
            $job+=['transaction'=>(string)(880000000+$invoice),'attempt'=>$attempt];
        }
        $providerScenario=$fault;$oldGateway='';$postReservation=null;$afterOldRetirement=null;$afterCandidateRead=null;$fepCalls=[];$proof=null;
        $connection=(int)query('SELECT CONNECTION_ID()')->fetchColumn();
        $work=static function()use($job,$prefix,$root,&$proof):void{
            if(db()->inTransaction())throw new RuntimeException('Parent retained its transaction across provider I/O');
            $process=proc_open([PHP_BINARY,__FILE__,$prefix,$root,'writer',base64_encode(json_encode($job,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process))throw new RuntimeException('Could not start separate payment writer');fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
            if($exit!==0||$stderr!=='')throw new RuntimeException('Separate payment writer failed: '.$stderr);
            $proof=json_decode($stdout,true,16,JSON_THROW_ON_ERROR);
        };
        if($kind!=='none'){
            if($timing==='during_create')$postReservation=$work;else $afterCandidateRead=$work;
        }
        $before=fep_snapshot();$result=$caller==='opaque'
            ?\FinancePayoffRetirement\license_finance_subscription_enroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic')
            :\FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
        $sub=license_finance_subscription_row($offer['id']);$new=(string)$fepGatewayId;$after=fep_snapshot();$plan=license_financing_plan($license);
        $unknown=in_array($fault,['missing_create_id','lost_create_reply'],true);$uncertain=$fault!=='normal'&&!$unknown;
        $safe=$unknown?$result==='review'&&$sub['status']==='review'&&$sub['gateway_subscription_id']===null:
            ($uncertain?$result==='review'&&$sub['status']==='retirement_pending':
            ($kind==='none'?$result==='pending_first_payment'&&$provider[$new]['status']==='active':$result==='billing_suspended'&&$sub['status']==='billing_suspended'&&$provider[$new]['status']==='canceled'));
        fep_ok($name.' retains the required cancellation or unknown-result state',$safe);
        fep_ok($name.' actual writer uses a different native connection',$kind==='none'?$proof===null:($proof&&$proof['committed']&&$proof['connection']!==$connection));
        fep_ok($name.' preserves the exact committed receipt',(int)ledger_balance(ledger_invoice($invoice))['paid_cents']===$amount);
        fep_ok($name.' retains exactly the known gateway identity',$unknown?$sub['gateway_subscription_id']===null:$sub['gateway_subscription_id']===$new);
        fep_ok($name.' preserves license identity and domains',$before['website_licenses']===$after['website_licenses']&&$before['license_domains']===$after['license_domains']);
        fep_ok($name.' does not claim full payoff',$plan['state']==='active'&&$plan['paid_off_at']===null);
        fep_ok($name.' releases parent transaction and mutex',!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40)])->fetchColumn()===1);
        if($kind==='prepared_gateway'){
            $charge=one('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice]);$attemptRow=one('SELECT * FROM '.table('license_finance_charge_attempts').' WHERE request_key=?',[$job['attempt']]);
            fep_ok($name.' preserves paid charge and attempt identity',$charge['status']==='paid'&&$charge['transaction_id']===$job['transaction']&&$attemptRow['status']==='paid'&&$attemptRow['transaction_id']===$job['transaction']);
        }
        $start=count($fepCalls);$refused=false;
        try{
            if($caller==='opaque')\FinancePayoffRetirement\license_finance_subscription_enroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');
            else \FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
        }catch(Throwable){$refused=true;}
        fep_ok($name.' retry cannot create another subscription',$refused&&count($fepCalls)===$start);
        if($unknown||$uncertain){
            $providerScenario='normal';$start=count($fepCalls);$snapshot=fep_snapshot();$error=null;
            rb_staff();try{$retry=\FinancePayoffRetirement\license_finance_subscription_reconcile($offer['id'],$new,$password);}catch(Throwable $e){$error=get_class($e).': '.$e->getMessage();$retry=null;}finally{rb_client();}
            $expected=$kind==='none'?'pending_first_payment':'billing_suspended';
            fep_ok($name.' owner recovers the existing candidate safely',$error===null&&$retry===$expected&&license_finance_subscription_row($offer['id'])['gateway_subscription_id']===$new&&$provider[$new]['status']===($kind==='none'?'active':'canceled'));
            $afterRetry=fep_snapshot();fep_ok($name.' recovery preserves financial and license records',$snapshot['invoice_payments']===$afterRetry['invoice_payments']&&$snapshot['license_financing']===$afterRetry['license_financing']&&$snapshot['website_licenses']===$afterRetry['website_licenses']);
            fep_ok($name.' recovery never creates an additional ARB',!array_filter(array_slice($fepCalls,$start),fn($c)=>$c['method']==='ARBCreateSubscriptionRequest'));
        }
        $writerProofs[]=['case'=>$name,'parent_connection'=>$connection,'writer'=>$proof,'result'=>$result,'calls'=>$fepCalls];
    }
}catch(Throwable $e){fep_ok('fixture exception: '.get_class($e).' '.$e->getMessage(),false);}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'writers'=>$writerProofs,'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),'source_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),'scope'=>'Actual initial callers, separate PHP processes/native connections, real owner ledger_receive and prepared charge_record_paid commits, native transaction rollback under injected audit failure. Synthetic provider transport/mode, signed future invoice and request-token metadata.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
