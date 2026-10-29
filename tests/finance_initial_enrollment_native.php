<?php
/** Actual application admission/reservation and ledger, with synthetic signed financing data. No gateway. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$prefix=(string)($argv[1]??'');$port=(int)getenv('CWP_TEST_PORT');
if(!preg_match('/^fie_[a-z0-9_]{3,12}_$/D',$prefix)||getenv('CWP_TEST_HOST')!=='127.0.0.1'||$port<1||$port>65535)exit(2);
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
$checks=[];$cases=[];
function fie_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function fie_snapshot():array{
    $r=[];foreach(['website_licenses','license_domains','license_financing','license_finance_offers','studio_invoices','invoice_payments','recurring_billing_schedules','recurring_billing_events','license_finance_subscriptions','license_finance_subscription_history'] as $t)$r[$t]=all('SELECT * FROM '.table($t).' ORDER BY id');return $r;
}
function fie_seed(string $name,int $initial):array{
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
        ledger_insert(ledger_invoice($invoice),1,'receipt',$initial,'external','Synthetic verified native fixture',gmdate('Y-m-d'));
        query('UPDATE '.table('studio_invoices')." SET status='paid' WHERE id=?",[$invoice]);
    }
    return license_finance_offer_row($offer['id'],1);
}
function fie_portal(array $row,array $financePlan):string{
    global $root;$terms=license_finance_offer_terms($row);$financeSub=null;
    $financeGateway=['mode'=>'sandbox','login'=>'synthetic','client_key'=>'synthetic'];
    $source=file_get_contents($root.'/site/portal/views/financing.php');
    $start=strpos($source,'        $financePaidOff=');$end=strpos($source,"    }elseif(\$state==='declined')",$start);
    if($start===false||$end===false)throw new RuntimeException('Portal enrollment fragment missing');
    ob_start();try{eval(substr($source,$start,$end-$start));return ob_get_contents();}finally{ob_end_clean();}
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
    $names=['paid','zero','partial','status_only','reversed','missing_ledger','missing_invoice','invoice_customer','invoice_project','invoice_amount','invoice_currency','invoice_hash','invoice_agreement','unsigned','signature_hash','terms_initial','terms_installments','license_customer','project_customer','plan_hash','plan_installments','paid_off','zero_paid_off','paid_off_inconsistent','suspended','inactive_client','revoked_session','expired_session','support','other_client','contract_changed','duplicate','late_reversal','late_payoff','late_revocation'];
    foreach($names as $name){
        $offer=fie_seed($name,in_array($name,['zero','zero_paid_off'],true)?0:500);$invoice=(int)$offer['activation_invoice_id'];$license=(int)$offer['license_id'];
        $admitted=license_finance_subscription_enrollment_offer($offer['id'],1);$contract=license_finance_subscription_contract($admitted);
        // A separate committed SQL writer changes authority/payment after preflight.
        $c=config()['db'];$writer=new PDO('mysql:host=127.0.0.1;port='.$c['port'].';dbname=cwp_test;charset=utf8mb4',$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $write=static function(string $sql,array $args=[])use($writer):void{$writer->prepare($sql)->execute($args);};
        switch($name){
            case 'partial':$write('UPDATE '.table('invoice_payments').' SET amount_cents=250 WHERE invoice_id=?',[$invoice]);$write('UPDATE '.table('studio_invoices')." SET status='issued' WHERE id=?",[$invoice]);break;
            case 'status_only':$write('UPDATE '.table('invoice_payments').' SET amount_cents=0 WHERE invoice_id=?',[$invoice]);break;
            case 'reversed':case 'late_reversal':
                $receipt=one('SELECT id FROM '.table('invoice_payments').' WHERE invoice_id=?',[$invoice]);
                ledger_insert(ledger_invoice($invoice),1,'reversal',500,'external','Synthetic reversal',gmdate('Y-m-d'),null,null,null,(int)$receipt['id']);
                $write('UPDATE '.table('studio_invoices')." SET status='issued' WHERE id=?",[$invoice]);break;
            case 'missing_ledger':save_setting('invoice_ledger_schema_version','');break;
            case 'missing_invoice':$write('UPDATE '.table('license_finance_offers').' SET activation_invoice_id=NULL WHERE id=?',[$offer['id']]);break;
            case 'invoice_customer':$write('UPDATE '.table('studio_invoices').' SET job_id=2 WHERE id=?',[$invoice]);break;
            case 'invoice_project':$write('UPDATE '.table('license_finance_offers').' SET job_id=2 WHERE id=?',[$offer['id']]);break;
            case 'invoice_amount':$write('UPDATE '.table('studio_invoices').' SET amount_cents=501 WHERE id=?',[$invoice]);break;
            case 'invoice_currency':$write('UPDATE '.table('studio_invoices')." SET currency='EUR' WHERE id=?",[$invoice]);break;
            case 'invoice_hash':$write('UPDATE '.table('studio_invoices')." SET title='Altered obligation' WHERE id=?",[$invoice]);break;
            case 'invoice_agreement':
                $donor=one('SELECT activation_invoice_id FROM '.table('license_finance_offers').' WHERE title=?',['Synthetic paid']);
                $write('UPDATE '.table('license_finance_offers').' SET activation_invoice_id=? WHERE id=?',[$donor['activation_invoice_id'],$offer['id']]);break;
            case 'unsigned':$write('UPDATE '.table('license_finance_offers')." SET status='offered' WHERE id=?",[$offer['id']]);break;
            case 'signature_hash':$write('UPDATE '.table('license_finance_offers').' SET signed_terms_sha256=? WHERE id=?',[str_repeat('f',64),$offer['id']]);break;
            case 'terms_initial':$write('UPDATE '.table('license_finance_offers').' SET initial_payment_cents=0 WHERE id=?',[$offer['id']]);break;
            case 'terms_installments':$write('UPDATE '.table('license_finance_offers').' SET installments=4 WHERE id=?',[$offer['id']]);break;
            case 'license_customer':$write('UPDATE '.table('website_licenses').' SET customer_id=2 WHERE id=?',[$license]);break;
            case 'project_customer':$write('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=1');break;
            case 'plan_hash':$write('UPDATE '.table('license_financing').' SET terms_sha256=? WHERE license_id=?',[str_repeat('f',64),$license]);break;
            case 'plan_installments':$write('UPDATE '.table('license_financing').' SET installments=4 WHERE license_id=?',[$license]);break;
            case 'paid_off':case 'zero_paid_off':case 'late_payoff':$write('UPDATE '.table('license_financing')." SET state='paid_off',paid_off_at=? WHERE license_id=?",[$now,$license]);break;
            case 'paid_off_inconsistent':$write('UPDATE '.table('license_financing').' SET paid_off_at=? WHERE license_id=?',[$now,$license]);break;
            case 'suspended':$write('UPDATE '.table('license_financing')." SET state='suspended' WHERE license_id=?",[$license]);break;
            case 'inactive_client':$write('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');break;
            case 'revoked_session':case 'late_revocation':$write('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[str_repeat('e',32)]);break;
            case 'expired_session':$_SESSION['client_last']=time()-1801;break;
            case 'support':$_GET['support']='synthetic';break;
            case 'other_client':rb_client(2);break;
            case 'contract_changed':$contract['occurrences']++;break;
            case 'duplicate':license_finance_subscription_reserve($admitted,$contract,[],1);break;
        }
        $before=fie_snapshot();$error=null;
        try{license_finance_subscription_reserve($admitted,$contract,[],1);}catch(Throwable $e){if($e instanceof PDOException)throw $e;$error=$e->getMessage();}
        $after=fie_snapshot();$allow=in_array($name,['paid','zero'],true);
        fie_ok($name.' admission outcome',($error===null)===$allow);
        if($allow){
            $sub=license_finance_subscription_row($offer['id']);
            fie_ok($name.' exact finite reservation',$sub&&$sub['status']==='creating'&&$sub['gateway_subscription_id']===null&&(int)$sub['amount_cents']===1000&&(int)$sub['total_occurrences']===3&&(int)$sub['period_offset']===0&&$sub['start_date']===$contract['start_date']);
            unset($before['license_finance_subscriptions'],$after['license_finance_subscriptions']);
        }
        fie_ok($name.' preserves retained financial and license records',$before===$after);
        $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.$prefix),0,40);
        fie_ok($name.' releases transaction and mutex',!db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',[$key])->fetchColumn()===1);
        if(in_array($name,['paid','zero','status_only','reversed','paid_off','zero_paid_off','paid_off_inconsistent'],true)){
            $html=fie_portal(license_finance_offer_row($offer['id'],1),license_financing_plan($license));
            fie_ok($name.' actual portal enrollment visibility',str_contains($html,'value="finance_subscription_enroll"')===$allow);
            if(!$allow)fie_ok($name.' portal does not assert verified initial payment',!str_contains($html,'Initial payment is verified.'));
        }
        $cases[]=['name'=>$name,'error'=>$error,'allowed'=>$allow];
        $write('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=1');$write('UPDATE '.table('studio_clients').' SET active=1,auth_version=? WHERE id=1',[str_repeat('1',32)]);save_setting('invoice_ledger_schema_version','1');$_GET=[];
    }
    require __DIR__.'/finance_initial_sync_native_cases.php';
}catch(Throwable $e){$checks[]=['name'=>'fixture exception: '.get_class($e).' '.$e->getMessage(),'passed'=>false];}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'cases'=>$cases,'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),
    'subscription_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),'portal_sha256'=>hash_file('sha256',$root.'/site/portal/views/financing.php'),
    'financing_sha256'=>hash_file('sha256',$root.'/site/app/licenses/financing.php'),'offers_sha256'=>hash_file('sha256',$root.'/site/app/licenses/financing_offers.php'),
    'expected_preparation_diagnostics'=>$expectedPreparationDiagnostics??0,
    'scope'=>'Actual application functions, canonical schema, signed offers, journal validation, session authority, ledger hashes/balances, SQL reservation and license synchronization/payoff boundary. Synthetic customer/receipt fixtures and a separate committed writer after preflight. Actual portal fragment with synthetic gateway display settings. No provider calls, HTTP, real payments or release approval.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
