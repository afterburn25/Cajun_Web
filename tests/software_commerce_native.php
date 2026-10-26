<?php
/** Native software checks, disposable MariaDB only; no gateway contact. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';
$GLOBALS['cwp_config']['db']['prefix']='swci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
start_session();
$n=0;
function swok(bool $pass,string $name):void{global $n;if(!$pass)throw new RuntimeException($name);$n++;echo 'PASS '.$name.PHP_EOL;}
function swdenied(callable $work,string $name):void{try{$work();}catch(RuntimeException|InvalidArgumentException){swok(true,$name);return;}swok(false,$name);}
try{
    foreach(schema_statements('swci_') as $sql)db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    foreach(['studio_schema_version'=>'2','catalog_schema_version'=>'1','license_schema_version'=>'3','license_invoice_cutover'=>'0','invoice_ledger_schema_version'=>'1'] as $k=>$v)save_setting($k,$v);
    $password='Synthetic commerce owner';$now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Owner','sw-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('a',32),$now]);
    staff_seed_owner(1);
    $_SESSION=['uid'=>1,'auth_version'=>str_repeat('a',32),'login_at'=>time(),'last_activity'=>time()];
    query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Synthetic Buyer','sw-buyer@example.invalid','disabled',str_repeat('b',32),$now]);
    require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_orders.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_fulfill.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_payment_review.php';
    swok(!software_commerce_ready(),'Not ready until owner explicitly prepares');
    swok(software_grant_verdict(['id'=>999,'is_active'=>1,'is_master'=>0],'legacy.example.com'),
        'Pre-commerce install preserves legacy v3 validation');
    software_commerce_prepare($password);
    swok(software_commerce_ready(),'Owner preparation creates new storage');
    save_setting('software_commerce_version','');
    swdenied(fn()=>software_grant_verdict(['id'=>999,'is_active'=>1,'is_master'=>0],'legacy.example.com'),
        'Existing entitlement storage without readiness marker is inconclusive, not authorized');
    save_setting('software_commerce_version','1');
    software_commerce_prepare($password);
    swok(software_commerce_ready(),'Preparation can be repeated');
    swdenied(fn()=>software_commerce_prepare('wrong-password'),'Wrong owner password refused');
    db()->beginTransaction();
    $trial=software_grant_create(1,'SOFTWARE-TEST','trial','Synthetic Buyer','www.example.com',gmdate('Y-m-d H:i:s',time()+86400),1);
    db()->commit();
    $lic=license_get($trial['key']);
    swok(software_grant_verdict($lic,'example.com'),'Trial authorizes its registered domain');
    query('UPDATE '.table('software_entitlements').' SET expires_at=? WHERE license_id=?',
        [gmdate('Y-m-d H:i:s',time()-86400),$trial['id']]);
    swok(!software_grant_verdict($lic,'example.com'),'Expired trial denied');
    db()->beginTransaction();
    try{
        software_grant_create(1,'SOFTWARE-TEST','master','Internal QA',null,null,1);
        db()->rollBack();
        swok(false,'Master requires recorded purpose');
    }catch(InvalidArgumentException $expected){
        if(db()->inTransaction())db()->rollBack();
        swok(true,'Master requires recorded purpose');
    }
    db()->beginTransaction();
    $master=software_grant_create(1,'SOFTWARE-TEST','master','Internal QA',null,null,1,null,null,'Owner-approved release validation');
    db()->commit();
    swok(software_grant_verdict(license_get($master['key']),'other.example.com'),'Owner master supports any domain');
    $masterMeta=one('SELECT purpose FROM '.table('software_entitlements').' WHERE license_id=?',[$master['id']]);
    swok(($masterMeta['purpose']??'')==='Owner-approved release validation','Master purpose is persisted for owner review');
    query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['NEXUSCORE','Test software','software','published','once','USD',2499,0,null,'','Synthetic','','Terms',0,1,$now,$now]);
    $product=(int)db()->lastInsertId();
    $hash=hash('sha256','synthetic software order');
    $buyer=['id'=>1,'name'=>'Synthetic Buyer'];
    $invoice=software_order_place($buyer,$product,1,2,'new.example.com',$hash,false);
    swok($invoice===software_order_place($buyer,$product,1,2,'new.example.com',$hash,false),'Order retry reuses invoice');
    swdenied(fn()=>software_order_place($buyer,$product,1,1,'new.example.com',$hash,false),'Changed order cannot reuse request');
    swdenied(fn()=>software_fulfill_paid_order($invoice),'Unpaid software cannot be licensed');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$invoice])->fetchColumn()===0,'No unpaid entitlements minted');
    $payment=ledger_invoice($invoice);
    db()->beginTransaction();
    ledger_insert($payment,0,'gateway',(int)$payment['amount_cents'],'authorize_net','Synthetic gateway approval only',gmdate('Y-m-d'));
    query('UPDATE '.table('studio_invoices').' SET status=?,paid_at=? WHERE id=?',['paid',gmdate('Y-m-d H:i:s'),$invoice]);
    db()->commit();
    software_fulfill_paid_order($invoice);
    software_fulfill_paid_order($invoice);
    swok((int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$invoice])->fetchColumn()===2,'Paid two-unit invoice mints exactly two keys after retry');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_notices').' WHERE invoice_id=?',[$invoice])->fetchColumn()===1,'One delivery email outbox entry');
    $issued=one('SELECT license_id FROM '.table('software_entitlements').' WHERE invoice_id=? ORDER BY unit_number LIMIT 1',[$invoice]);
    software_grant_replace_domain((int)$issued['license_id'],'new.example.com','moved.example.com',1);
    swok((bool)one('SELECT id FROM '.table('license_domains').' WHERE license_id=? AND domain=? AND is_active=1',
        [(int)$issued['license_id'],'moved.example.com']),'First customer software domain move succeeds');
    swdenied(fn()=>software_grant_replace_domain((int)$issued['license_id'],'moved.example.com','again.example.com',1),
        'Immediate repeated software domain move is blocked by cooldown');
    query('INSERT INTO '.table('software_charges').' (invoice_id,client_id,amount_cents,status,request_key,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',
        [$invoice,1,(int)$payment['amount_cents'],'paid',hash('sha256','native-reversal-request'),'600000001',$now,$now]);
    swok(software_payment_reversal_apply(['kind'=>'refund','original_transaction_id'=>'600000001'])==='one_time_reversal_review',
        'Verified refund moves one-time payment to review');
    swok(one('SELECT status FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice])['status']==='reversal_review',
        'One-time reversal review state is persisted');
    swok(software_payment_reversal_apply(['kind'=>'refund','original_transaction_id'=>'600000001'])==='replay',
        'Repeated one-time reversal is idempotent');
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,gateway_subscription_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?,?)',
        [$invoice,1,'123456789',1,(int)$payment['amount_cents'],'active',$now,$now]);
    swok(software_subscription_lifecycle_review('net.authorize.customer.subscription.failed','123456789')==='subscription_review',
        'Failed gateway subscription enters review');
    swok(one('SELECT status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice])['status']==='review',
        'Subscription review is persisted');
    swok(software_subscription_lifecycle_review('net.authorize.customer.subscription.cancelled','123456789')==='replay',
        'Cancellation cannot erase an unresolved review state');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE invoice_id=?',['active',$invoice]);
    swok(software_subscription_lifecycle_review('net.authorize.customer.subscription.cancelled','123456789')==='subscription_cancelled',
        'Cancellation stops future renewal when no review is pending');
    swok(one('SELECT status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice])['status']==='cancelled',
        'Cancelled subscription state is persisted');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE invoice_id=?',['active',$invoice]);
    query('INSERT INTO '.table('software_subscription_receipts').' (subscription_invoice_id,gateway_transaction_id,invoice_id,created_at) VALUES (?,?,?,?)',
        [$invoice,'600000099',$invoice,$now]);
    swok(software_payment_reversal_apply(['kind'=>'void','original_transaction_id'=>'600000099'])==='subscription_reversal_review',
        'Recurring payment reversal moves subscription to review');
    swok(one('SELECT status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice])['status']==='review',
        'Recurring reversal review state is persisted');
    echo 'Software commerce native: '.$n.' checks passed'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
