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
$seed=file_get_contents(__DIR__.'/recurring_billing_native.php');$bodies=[];
foreach(['rb_id','rb_staff','rb_client','rb_fields','rb_historical_source'] as $name){
    if(!preg_match('/^function '.$name.'\(.*?(?=^function |^if\(|\z)/ms',$seed,$m))throw new RuntimeException('Missing seed helper '.$name);
    $bodies[]=rtrim($m[0]);
}
eval(implode("\n",$bodies));
$checks=[];$cases=[];require __DIR__.'/finance_enrollment_payoff_boundaries.php';
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
function fep_portal(array $row,array $financePlan):string{
    global $root;$terms=license_finance_offer_terms($row);$financeSub=null;
    $financeGateway=['mode'=>'sandbox','login'=>'synthetic','client_key'=>'synthetic'];
    $source=file_get_contents($root.'/site/portal/views/financing.php');
    $start=strpos($source,'        $financePaidOff=');$end=strpos($source,"    }elseif(\$state==='declined')",$start);
    if($start===false||$end===false)throw new RuntimeException('Portal enrollment fragment missing');
    ob_start();try{eval(substr($source,$start,$end-$start));return ob_get_contents();}finally{ob_end_clean();}
}
function fep_sandbox(array $offer,string $name):void{
    $invoice=ledger_invoice((int)$offer['activation_invoice_id']);$now=gmdate('Y-m-d H:i:s');
    $key=hash('sha256','sandbox-fixture-'.$offer['id']);$transaction=(string)(800000+(int)$invoice['id']);
    query('INSERT INTO '.table('payment_requests').' (invoice_id,job_id,client_id,actor_id,request_key,request_hash,invoice_hash,purpose,status,amount_cents,currency,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$invoice['id'],$invoice['job_id'],1,1,$key,$key,ledger_invoice_hash($invoice),'financing','prepared',500,'USD',time()+86400,$now]);$request=(int)db()->lastInsertId();
    query('INSERT INTO '.table('license_finance_charges').' (request_id,invoice_id,offer_id,amount_cents,status,request_key,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
        [$request,$invoice['id'],$offer['id'],500,'sandbox_test',$key,$transaction,$now,$now]);
    query('INSERT INTO '.table('license_finance_charge_attempts').' (invoice_id,amount_cents,status,request_key,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
        [$invoice['id'],500,'sandbox_test',$key,$transaction,$now,$now]);
    switch($name){
        case 'sandbox_missing_attempt':query('UPDATE '.table('license_finance_charge_attempts').' SET request_key=? WHERE request_key=?',[hash('sha256','other-'.$key),$key]);break;
        case 'sandbox_stale_charge':query('UPDATE '.table('license_finance_charges')." SET status='processing' WHERE request_key=?",[$key]);break;
        case 'sandbox_stale_attempt':query('UPDATE '.table('license_finance_charge_attempts')." SET status='review' WHERE request_key=?",[$key]);break;
        case 'sandbox_wrong_offer':$other=one('SELECT id FROM '.table('license_finance_offers').' WHERE id<>? LIMIT 1',[$offer['id']]);query('UPDATE '.table('license_finance_charges').' SET offer_id=? WHERE request_key=?',[$other['id'],$key]);break;
        case 'sandbox_wrong_amount':query('UPDATE '.table('license_finance_charge_attempts').' SET amount_cents=501 WHERE request_key=?',[$key]);break;
        case 'sandbox_wrong_transaction':query('UPDATE '.table('license_finance_charge_attempts').' SET transaction_id=? WHERE request_key=?',[(string)(900000+(int)$invoice['id']),$key]);break;
        case 'sandbox_reversed_initial':
            $receipt=ledger_insert($invoice,1,'receipt',500,'external','Synthetic original receipt',gmdate('Y-m-d'));
            ledger_insert($invoice,1,'reversal',500,'external','Synthetic full reversal',gmdate('Y-m-d'),null,null,null,$receipt);break;
        case 'sandbox_support':$_GET['support']='synthetic';break;
    }
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
    $fepWriterCommits=0;$fepGatewayId=700000;$fepMutation=null;
    $c=config()['db'];$writer=new PDO('mysql:host=127.0.0.1;port='.$c['port'].';dbname=cwp_test;charset=utf8mb4',$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $write=static function(string $sql,array $args=[])use($writer):void{$writer->prepare($sql)->execute($args);};
    foreach(['reserve','opaque','profile'] as $caller){
      foreach(['paid','zero','stale_active_payoff','stale_grace_payoff','late_payoff','zero_payoff','one_paid','partial_paid','profile_unpaid','profile_reversed','profile_suspended','profile_paid_off','profile_inactive_client','profile_owner_changed','profile_schedule_changed','profile_owner_session','sandbox_valid','sandbox_missing_attempt','sandbox_stale_charge','sandbox_stale_attempt','sandbox_wrong_offer','sandbox_wrong_amount','sandbox_wrong_transaction','sandbox_reversed_initial','sandbox_paid_off','sandbox_payoff','sandbox_late_payoff','sandbox_support'] as $name){
        if((str_starts_with($name,'profile_')||str_starts_with($name,'sandbox_'))&&$caller!=='profile')continue;
        $initial=in_array($name,['zero','zero_payoff'],true)?0:500;
        $offer=fep_seed($caller.'-'.$name,$initial,$name!=='profile_unpaid'&&!str_starts_with($name,'sandbox_'));$license=(int)$offer['license_id'];
        query('UPDATE '.table('license_financing')." SET state=? WHERE license_id=?",[$name==='stale_grace_payoff'?'grace':'active',$license]);
        if(str_starts_with($name,'sandbox_'))fep_sandbox($offer,$name);
        if($name==='sandbox_paid_off')query('UPDATE '.table('license_financing')." SET state='paid_off',paid_off_at=? WHERE license_id=?",[$now,$license]);
        $contract=license_finance_subscription_contract($offer);$invoices=[];
        if(in_array($name,['stale_active_payoff','stale_grace_payoff','late_payoff','zero_payoff','one_paid','partial_paid','sandbox_payoff','sandbox_late_payoff'],true)){
            $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
            $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
            for($n=0;$n<3;$n++){
                $period=$terms->period($n);$issued=license_finance_issue_period($offer['id'],$n,strtotime($period['start'].' 12:00:00 UTC'));
                $invoices[]=ledger_invoice((int)$issued['invoice_id']);
            }
        }
        $pay=static function()use($writer,$write,$invoices,$name):void{
            global $fepWriterCommits;
            $writer->beginTransaction();
            try{
                foreach($invoices as $n=>$i){
                    if(in_array($name,['one_paid','partial_paid'],true)&&$n>0)break;
                    $amount=$name==='partial_paid'?250:(int)$i['amount_cents'];
                    $write('INSERT INTO '.table('invoice_payments').' (invoice_id,job_id,client_id,actor_id,kind,amount_cents,currency,method,private_note,received_on,invoice_hash,invoice_reference,invoice_title,invoice_total_cents,client_name,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        [$i['id'],$i['job_id'],$i['client_id'],1,'receipt',$amount,'USD','external','Synthetic separately committed payoff',gmdate('Y-m-d'),ledger_invoice_hash($i),$i['reference'],$i['title'],$i['amount_cents'],$i['client_name'],gmdate('Y-m-d H:i:s')]);
                    $write('UPDATE '.table('studio_invoices').' SET status=? WHERE id=?',[$amount===(int)$i['amount_cents']?'paid':'issued',$i['id']]);
                }
                $writer->commit();$fepWriterCommits++;
            }catch(Throwable $e){if($writer->inTransaction())$writer->rollBack();throw $e;}
            $GLOBALS['fepBefore']=fep_snapshot();
        };
        if($invoices&&!in_array($name,['late_payoff','sandbox_late_payoff'],true))$pay();
        switch($name){
            case 'profile_reversed':
                $i=ledger_invoice((int)$offer['activation_invoice_id']);$receipt=one('SELECT id FROM '.table('invoice_payments').' WHERE invoice_id=?',[$i['id']]);
                ledger_insert($i,1,'reversal',500,'external','Synthetic full reversal',gmdate('Y-m-d'),null,null,null,(int)$receipt['id']);
                query('UPDATE '.table('studio_invoices')." SET status='issued' WHERE id=?",[$i['id']]);break;
            case 'profile_suspended':query('UPDATE '.table('license_financing')." SET state='suspended' WHERE license_id=?",[$license]);break;
            case 'profile_paid_off':query('UPDATE '.table('license_financing')." SET state='paid_off',paid_off_at=? WHERE license_id=?",[$now,$license]);break;
            case 'profile_inactive_client':query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');break;
            case 'profile_owner_changed':query('UPDATE '.table('website_licenses').' SET customer_id=2 WHERE id=?',[$license]);break;
            case 'profile_schedule_changed':query('UPDATE '.table('license_financing').' SET terms_sha256=? WHERE license_id=?',[str_repeat('f',64),$license]);break;
            case 'profile_owner_session':rb_staff();break;
        }
        $fepCalls=[];$fepReadback=null;$fepBefore=fep_snapshot();$error=null;$result=null;
        if(in_array($name,['late_payoff','sandbox_late_payoff'],true)){
            if($caller==='reserve'){
                \FinanceEnrollmentPayoff\license_finance_subscription_enrollment_offer($offer['id'],1);
                $pay();
            }else $fepMutation=$pay;
        }
        try{
            $result=match($caller){
                'reserve'=>\FinanceEnrollmentPayoff\license_finance_subscription_reserve($offer,$contract,[],1),
                'opaque'=>\FinanceEnrollmentPayoff\license_finance_subscription_enroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic'),
                'profile'=>\FinanceEnrollmentPayoff\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001'])
            };
        }catch(Throwable $e){if($e instanceof PDOException)throw $e;$error=get_class($e).': '.$e->getMessage();}
        $allowed=in_array($name,['paid','zero','profile_owner_session','sandbox_valid'],true);$label=$caller.' '.$name;
        fep_ok($label.' admission outcome',$allowed?$error===null:$error!==null);
        $sub=license_finance_subscription_row($offer['id']);
        fep_ok($label.' local subscription admission',$allowed?$sub!==null:$sub===null);
        fep_ok($label.' ARB requests',$fepCalls===($allowed&&$caller!=='reserve'?['ARBCreateSubscriptionRequest','ARBGetSubscriptionRequest']:[]));
        $after=fep_snapshot();$before=$fepBefore;
        if($allowed){unset($after['license_finance_subscriptions'],$before['license_finance_subscriptions']);}
        fep_ok($label.' preserves financial license domain and rights records',$before===$after);
        $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40);
        fep_ok($label.' releases transaction and mutex',!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',[$key])->fetchColumn()===1);
        if($invoices){
            $plan=license_financing_plan($license);$paid=0;
            foreach($invoices as $i){$b=ledger_balance(ledger_invoice((int)$i['id']));if((int)$b['due_cents']===0)$paid++;}
            fep_ok($label.' committed writer leaves original projection stale',$plan['paid_off_at']===null&&$plan['state']===($name==='stale_grace_payoff'?'grace':'active')&&$paid===(in_array($name,['one_paid','partial_paid'],true)?($name==='one_paid'?1:0):3));
        }
        $cases[]=['caller'=>$caller,'name'=>$name,'allowed'=>$allowed,'error'=>$error,'provider_calls'=>$fepCalls];
        query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');$fepMutation=null;$_GET=[];
      }
    }
}catch(Throwable $e){$checks[]=['name'=>'fixture exception: '.get_class($e).' '.$e->getMessage(),'passed'=>false];}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'cases'=>$cases,'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),
    'subscription_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),'portal_sha256'=>hash_file('sha256',$root.'/site/portal/views/financing.php'),
    'financing_sha256'=>hash_file('sha256',$root.'/site/app/licenses/financing.php'),'offers_sha256'=>hash_file('sha256',$root.'/site/app/licenses/financing_offers.php'),
    'writer_commits'=>$fepWriterCommits,'expected_preparation_diagnostics'=>$expectedPreparationDiagnostics??0,
    'scope'=>'Exact source enrollment/reservation functions with actual SQL, canonical signed schedules, invoices, ledger and separately committed PDO payoff writers. Only gateway settings, transport and readback are synthetic namespace boundaries. No HTTP or real payment claim.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
