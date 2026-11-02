<?php
/** Real PDO/InnoDB webhook regressions in the disposable recurring fixture. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);

// The child uses a separate database connection to prove serialized re-reading.
if(!defined('CWP_BOOT')){
    if(($argv[1]??'')!=='worker')exit(2);
    $prefix=getenv('CWP_RECURRING_TEST_PREFIX')?:'rbill_';
    if(!preg_match('/\Arbill_[a-z0-9_]{0,8}\z/',$prefix))exit(2);
    define('CWP_BOOT',true);
    require __DIR__.'/../site/includes/bootstrap.php';
    $GLOBALS['cwp_config']=['db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1',
        'port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test',
        'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>$prefix]];
    require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
    echo (int)query('SELECT CONNECTION_ID()')->fetchColumn()."\n";fflush(STDOUT);
    echo license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.failed',(string)($argv[2]??''));
    exit;
}
if(!function_exists('rb_ok')||!isset($offer['id']))exit(2);
require_once CWP_CODE_ROOT.'/app/billing/finance_payment_review.php';

function finance_native_ledger_snapshot():string{
    $snapshot=[];
    foreach(['website_licenses','license_domains','license_financing','license_finance_offers',
        'studio_invoices','invoice_payments','payment_requests','license_finance_subscription_history',
        'license_finance_subscription_receipts','license_finance_reversals'] as $name){
        $rows=array_map(static fn(array $row):string=>json_encode($row,JSON_THROW_ON_ERROR),all('SELECT * FROM '.table($name)));
        sort($rows);$snapshot[$name]=$rows;
    }
    return hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR));
}
$nativeLedgerBefore=finance_native_ledger_snapshot();
$nativeCapture=['messages'=>['resultCode'=>'Ok'],'transaction'=>[
    'transId'=>'660000001','transactionType'=>'authCaptureTransaction','transactionStatus'=>'settledSuccessfully',
    'authAmount'=>'12.50','order'=>['invoiceNumber'=>'CWP-42']]];
$nativeRefund=['messages'=>['resultCode'=>'Ok'],'transaction'=>[
    'transId'=>'770000001','transactionType'=>'refundTransaction','refTransId'=>'660000001','authAmount'=>'12.50']];
$nativeMarker=setting('license_finance_gateway_schema_version');
$nativeHeldTable='`'.config()['db']['prefix'].'held_finance_charges`';
db()->exec('RENAME TABLE '.table('license_finance_charges').' TO '.$nativeHeldTable);
try{
    save_setting('license_finance_gateway_schema_version','');
    rb_ok('Native software capture succeeds as unmatched with absent optional finance storage',
        license_finance_charge_webhook_review($nativeCapture,'660000001')==='finance_payment_unmatched');
    save_setting('license_finance_gateway_schema_version','3');
    rb_ok('Native incomplete finance storage also declines routing before absent table access',
        license_finance_charge_webhook_review($nativeCapture,'660000001')==='finance_payment_unmatched');
}finally{
    db()->exec('RENAME TABLE '.$nativeHeldTable.' TO '.table('license_finance_charges'));
    save_setting('license_finance_gateway_schema_version',(string)$nativeMarker);
}
$nativeMode=setting('software_gateway_mode','disabled');
save_setting('software_gateway_mode','production');
try{
    rb_ok('Native unrelated refund does not require finance production activation',
        license_finance_payment_reversal_review($nativeRefund,'770000001','net.authorize.payment.refund.created')==='finance_reversal_unmatched');
    $nativeVoid=$nativeRefund;$nativeVoid['transaction']['transactionType']='voidTransaction';
    rb_ok('Native unrelated void does not require finance production activation',
        license_finance_payment_reversal_review($nativeVoid,'770000001','net.authorize.payment.void.created')==='finance_reversal_unmatched');
}finally{save_setting('software_gateway_mode',(string)$nativeMode);}

$nativeGateway='991000045';$nativeOfferId=(string)$offer['id'];$nativeNow=gmdate('Y-m-d H:i:s');
rb_ok('Native lifecycle fixture never overwrites an existing subscription',
    license_finance_subscription_row($nativeOfferId)===null);
query('INSERT INTO '.table('license_finance_subscriptions').
    ' (offer_id,gateway_subscription_id,customer_profile_id,payment_profile_id,interval_months,amount_cents,total_occurrences,start_date,status,created_at,updated_at)'.
    ' VALUES (?,?,?,?,?,?,?,?,?,?,?)',
    [$nativeOfferId,$nativeGateway,'991000046','991000047',1,2500,4,gmdate('Y-m-d',time()+86400),'active',$nativeNow,$nativeNow]);
$nativeSubId=(int)db()->lastInsertId();
$nativeEvents=['failed'=>'payment_failed','suspended'=>'billing_suspended','terminated'=>'review','cancelled'=>'cancelled','expired'=>'cancelled'];
try{
    foreach(['review','paid_off','cancelled','reversal_recovery','replacing'] as $nativeState){
        foreach($nativeEvents as $nativeEvent=>$nativeTarget){
            query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',[$nativeState,'2000-01-01 00:00:00',$nativeSubId]);
            $nativeBefore=license_finance_subscription_row($nativeOfferId);$nativeAudit=rb_count('audit');
            $nativeResult=license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.'.$nativeEvent,$nativeGateway);
            rb_ok('Native '.$nativeState.' survives delayed '.$nativeEvent.' without row or audit mutation',
                $nativeResult==='replay'&&license_finance_subscription_row($nativeOfferId)===$nativeBefore
                &&rb_count('audit')===$nativeAudit&&!db()->inTransaction());
        }
    }
    query('UPDATE '.table('license_finance_subscriptions').' SET status=? WHERE id=?',['billing_suspended',$nativeSubId]);
    rb_ok('Native confirmed suspension survives delayed failure and retains catch-up eligibility',
        license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.failed',$nativeGateway)==='replay'
        &&license_financing_recovery_link_allowed((string)license_finance_subscription_row($nativeOfferId)['status']));
    foreach($nativeEvents as $nativeEvent=>$nativeTarget){
        query('UPDATE '.table('license_finance_subscriptions').' SET status=? WHERE id=?',['active',$nativeSubId]);
        $nativeAudit=rb_count('audit');
        rb_ok('Native active subscription records '.$nativeEvent.' and exactly one audit',
            license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.'.$nativeEvent,$nativeGateway)==='finance_subscription_'.$nativeTarget
            &&license_finance_subscription_row($nativeOfferId)['status']===$nativeTarget&&rb_count('audit')===$nativeAudit+1);
    }
    query('UPDATE '.table('license_finance_subscriptions').' SET status=? WHERE id=?',['active',$nativeSubId]);
    $nativeBefore=license_finance_subscription_row($nativeOfferId);$nativeAudit=rb_count('audit');
    rb_ok('Native audit failure rolls back lifecycle mutation atomically',
        rb_fail(fn()=>license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.suspended',$nativeGateway))
        &&license_finance_subscription_row($nativeOfferId)===$nativeBefore&&rb_count('audit')===$nativeAudit&&!db()->inTransaction());

    $nativeLock='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if((int)query('SELECT GET_LOCK(?,5)',[$nativeLock])->fetchColumn()!==1)throw new RuntimeException('Native test mutex unavailable.');
    $nativeProcess=null;$nativePipes=[];$nativeWaiting=false;
    try{
        $nativeProcess=proc_open([PHP_BINARY,__FILE__,'worker',$nativeGateway],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$nativePipes);
        if(!is_resource($nativeProcess))throw new RuntimeException('Native lifecycle worker unavailable.');
        fclose($nativePipes[0]);stream_set_timeout($nativePipes[1],10);stream_set_timeout($nativePipes[2],10);
        $nativeConnection=(int)trim((string)fgets($nativePipes[1]));
        for($nativeWait=0;$nativeWait<30;$nativeWait++){
            $nativeWorkerState=one('SELECT STATE FROM information_schema.PROCESSLIST WHERE ID=?',[$nativeConnection]);
            if(str_contains(strtolower((string)($nativeWorkerState['STATE']??'')),'user lock')){$nativeWaiting=true;break;}
            usleep(50000);
        }
        query('UPDATE '.table('license_finance_subscriptions').' SET status=? WHERE id=?',['replacing',$nativeSubId]);
    }finally{query('SELECT RELEASE_LOCK(?)',[$nativeLock]);}
    $nativeOutput=stream_get_contents($nativePipes[1]);$nativeError=stream_get_contents($nativePipes[2]);
    fclose($nativePipes[1]);fclose($nativePipes[2]);$nativeExit=proc_close($nativeProcess);
    rb_ok('Native independent lifecycle session waits for the shared account mutex',$nativeWaiting);
    rb_ok('Native delayed event re-reads the replacement fence after acquiring the mutex',
        $nativeExit===0&&$nativeError===''&&$nativeOutput==='replay'
        &&license_finance_subscription_row($nativeOfferId)['status']==='replacing');
    rb_ok('Native webhook routing and lifecycle leave all licenses agreements invoices and payments unchanged',
        finance_native_ledger_snapshot()===$nativeLedgerBefore);
}finally{
    query('DELETE FROM '.table('license_finance_subscriptions').' WHERE id=? AND offer_id=? AND gateway_subscription_id=?',
        [$nativeSubId,$nativeOfferId,$nativeGateway]);
}
