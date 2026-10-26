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
    query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Other Buyer','sw-other@example.invalid','disabled',str_repeat('c',32),$now]);
    require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_orders.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_charge.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_fulfill.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_notices.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_payment_review.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_subscription_enroll.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_subscription_cancel.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_asset_access.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_usage.php';
    swok(!software_commerce_ready(),'Not ready until owner explicitly prepares');
    swok(software_grant_verdict(['id'=>999,'is_active'=>1,'is_master'=>0],'legacy.example.com'),
        'Pre-commerce install preserves legacy v3 validation');
    software_commerce_prepare($password);
    swok(software_commerce_ready(),'Owner preparation creates new v3 storage');
    swok(software_commerce_table_present('software_product_policies')
        &&software_commerce_column_present('software_entitlements','updates_until')
        &&software_commerce_column_present('software_entitlements','max_installations')
        &&software_commerce_column_present('software_entitlements','policy_revision'),
        'Fresh v3 storage includes product policy and entitlement snapshot metadata');
    save_setting('software_commerce_version','');
    swdenied(fn()=>software_grant_verdict(['id'=>999,'is_active'=>1,'is_master'=>0],'legacy.example.com'),
        'Existing entitlement storage without readiness marker is inconclusive, not authorized');

    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_product_policies'.chr(96));
    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_charge_attempts'.chr(96));
    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_payment_reversals'.chr(96));
    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_payment_disputes'.chr(96));
    $entitlementTable=chr(96).config()['db']['prefix'].'software_entitlements'.chr(96);
    db()->exec('ALTER TABLE '.$entitlementTable.' DROP COLUMN policy_revision, DROP COLUMN max_installations, DROP COLUMN updates_until, DROP COLUMN purpose');
    save_setting('software_commerce_version','1');
    software_commerce_prepare($password);
    swok(software_commerce_ready(),'Published branch v1 storage upgrades additively to v3');
    swok(software_commerce_table_present('software_charge_attempts')
        &&software_commerce_table_present('software_payment_reversals')
        &&software_commerce_table_present('software_payment_disputes')
        &&software_commerce_table_present('software_product_policies'),
        'V1 to v3 upgrade restores payment review and software policy tables');
    foreach(['purpose','updates_until','max_installations','policy_revision'] as $column){
        swok(software_commerce_column_present('software_entitlements',$column),
            'V1 to v3 upgrade adds entitlement column '.$column);
    }

    $migrateKey=license_create(1,'SOFTWARE-MIGRATE',1,false,null);
    $migrateLicense=(int)one('SELECT id FROM '.table('website_licenses').' WHERE license_key=?',[$migrateKey])['id'];
    $migrateExpiry=gmdate('Y-m-d H:i:s',time()+86400*20);
    query('INSERT INTO '.table('software_entitlements').' (license_id,license_type,licensee,expires_at,updates_until,max_installations,policy_revision,issued_by,purpose,invoice_id,unit_number,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$migrateLicense,'temporary','Migrated Buyer',$migrateExpiry,null,2,0,1,'',990001,1,$now]);
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,gateway_subscription_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?,?)',
        [990001,1,'990001001',1,100,'active',$now,$now]);

    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_product_policies'.chr(96));
    db()->exec('ALTER TABLE '.$entitlementTable.' DROP COLUMN policy_revision, DROP COLUMN max_installations, DROP COLUMN updates_until');
    save_setting('software_commerce_version','2');
    software_commerce_prepare($password);
    swok(software_commerce_ready(),'Published branch v2 storage upgrades additively to v3');
    swok(software_commerce_table_present('software_product_policies')
        &&software_commerce_column_present('software_entitlements','updates_until')
        &&software_commerce_column_present('software_entitlements','max_installations')
        &&software_commerce_column_present('software_entitlements','policy_revision'),
        'V2 to v3 upgrade adds product policy and entitlement snapshot metadata');
    $migratedRights=one('SELECT updates_until,max_installations,policy_revision FROM '.table('software_entitlements').' WHERE license_id=?',[$migrateLicense]);
    swok(($migratedRights['updates_until']??'')===$migrateExpiry
        &&(int)($migratedRights['max_installations']??0)===2
        &&(int)($migratedRights['policy_revision']??-1)===0,
        'V2 recurring entitlement keeps paid-through update access and legacy two-install defaults');
    software_commerce_prepare($password);
    swok(software_commerce_ready(),'V3 preparation can be repeated safely');
    swdenied(fn()=>software_commerce_prepare('wrong-password'),'Wrong owner password refused');

    $private=software_asset_private_root();
    $nonZip=$private.DIRECTORY_SEPARATOR.'synthetic-private.txt';
    file_put_contents($nonZip,'Private non-ZIP acceptance fixture');
    swok(software_asset_resolve_path('synthetic-private.txt')===null,
        'Private asset resolver refuses non-ZIP files even inside storage');
    $zipPath=$private.DIRECTORY_SEPARATOR.'synthetic-private.zip';
    $zip=new ZipArchive();
    if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Native ZIP fixture failed.');
    $zip->addFromString('README.txt','Synthetic private software archive');
    $zip->close();
    swok(software_asset_resolve_path('synthetic-private.zip')===realpath($zipPath),
        'Private asset resolver accepts a contained ZIP file');
    query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['SOFTWARE-TEST','Internal test software','software','draft','once','USD',2499,0,null,'','Synthetic','','Terms',0,1,$now,$now]);

    $assetId=software_asset_publish('SOFTWARE-TEST','1.0.0','Native verified software ZIP',$zipPath,$password);
    $assetRow=one('SELECT sha256,byte_size,file_path FROM '.table('software_assets').' WHERE id=?',[$assetId]);
    swok(is_array($assetRow)&&$assetRow['sha256']===hash_file('sha256',$zipPath)
        &&(int)$assetRow['byte_size']===filesize($zipPath)
        &&$assetRow['file_path']==='synthetic-private.zip',
        'Owner publication records immutable relative path, checksum and size');
    swdenied(fn()=>software_asset_publish('SOFTWARE-TEST','1.0.0','Native verified software ZIP',$zipPath,$password),
        'Published software version cannot be overwritten by a repeated publication');

    $nestedPath=$private.DIRECTORY_SEPARATOR.'synthetic-nested.zip';
    $nested=new ZipArchive();
    if($nested->open($nestedPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Nested ZIP fixture failed.');
    $nested->addFromString('payload.zip','Synthetic nested archive marker');
    $nested->close();
    swdenied(fn()=>software_asset_publish('SOFTWARE-TEST','1.0.1','Nested ZIP must fail',$nestedPath,$password),
        'Software publisher rejects nested ZIP members');

    $unsafePath=$private.DIRECTORY_SEPARATOR.'synthetic-unsafe.zip';
    $unsafe=new ZipArchive();
    if($unsafe->open($unsafePath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Unsafe ZIP fixture failed.');
    $unsafe->addFromString('../escape.txt','Synthetic traversal marker');
    $unsafe->close();
    swdenied(fn()=>software_asset_publish('SOFTWARE-TEST','1.0.2','Traversal ZIP must fail',$unsafePath,$password),
        'Software publisher rejects parent-directory ZIP paths');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_assets').' WHERE sku=?',['SOFTWARE-TEST'])->fetchColumn()===1,
        'Rejected package publications create no extra software asset rows');

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
    $orphanKey=license_create(1,'NEXUSCORE',1,false,null);
    $orphan=one('SELECT id,is_active,is_master FROM '.table('website_licenses').' WHERE license_key=?',[$orphanKey]);
    swdenied(fn()=>software_grant_verdict($orphan,'orphan.example.com'),
        'Reduced API license row cannot authorize registered software with missing entitlement history');
    license_register_domain((int)$orphan['id'],'orphan.example.com',1);
    swdenied(fn()=>software_grant_replace_domain((int)$orphan['id'],'orphan.example.com','orphan-moved.example.com',1),
        'Orphan registered software cannot mutate domains without entitlement history');
    swok((bool)one('SELECT id FROM '.table('license_domains').' WHERE license_id=? AND domain=? AND is_active=1',
        [(int)$orphan['id'],'orphan.example.com']),
        'Rejected orphan software domain mutation preserves the original active domain');
    $hash=hash('sha256','synthetic software order');
    $buyer=['id'=>1,'name'=>'Synthetic Buyer'];
    $invoice=software_order_place($buyer,$product,1,2,'new.example.com',$hash,false);
    swok($invoice===software_order_place($buyer,$product,1,2,'new.example.com',$hash,false),'Order retry reuses invoice');
    $identityInput=[
        'sku'=>'NEXUSCORE-RENAMED','title'=>'Test software','family'=>'software','status'=>'published','term'=>'once',
        'currency'=>'USD','amount'=>'24.99','setup'=>'0.00','cost'=>'','provider_ref'=>'','summary'=>'Synthetic',
        'features'=>'','terms'=>'Terms','sort_order'=>'0'
    ];
    swdenied(fn()=>catalog_save($product,1,$identityInput),
        'Purchased software SKU cannot be renamed after order creation');
    $identityInput['sku']='NEXUSCORE';$identityInput['family']='addon';
    swdenied(fn()=>catalog_save($product,1,$identityInput),
        'Purchased software product cannot be converted out of software family');
    $identity=one('SELECT sku,family,revision FROM '.table('catalog_products').' WHERE id=?',[$product]);
    swok(($identity['sku']??'')==='NEXUSCORE'&&($identity['family']??'')==='software'&&(int)($identity['revision']??0)===1,
        'Rejected software identity edits leave catalog revision unchanged');

    query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['WEBSITE-LEGACY-SW','Legacy prefixed software','addon','published','once','USD',1500,0,null,'','Synthetic legacy','','Terms',0,1,$now,$now]);
    $legacyProduct=(int)db()->lastInsertId();
    $legacyInvoice=software_order_place($buyer,$legacyProduct,1,1,'legacy-software.example.com',
        hash('sha256','legacy prefixed software order'),false);
    swok($legacyInvoice>0,'Legacy prefixed catalog product remains orderable as software');
    $legacyIdentity=[
        'sku'=>'LEGACY-RENAMED','title'=>'Legacy prefixed software','family'=>'addon','status'=>'published','term'=>'once',
        'currency'=>'USD','amount'=>'15.00','setup'=>'0.00','cost'=>'','provider_ref'=>'','summary'=>'Synthetic legacy',
        'features'=>'','terms'=>'Terms','sort_order'=>'0'
    ];
    swdenied(fn()=>catalog_save($legacyProduct,1,$legacyIdentity),
        'Purchased legacy-prefixed software SKU cannot be renamed out of compatibility identity');
    swdenied(fn()=>software_order_place($buyer,$product,1,1,'new.example.com',
        hash('sha256','synthetic one-time recurring-consent mismatch'),true),
        'One-time software refuses recurring billing consent');
    swdenied(fn()=>software_order_place($buyer,$product,1,1,'new.example.com',$hash,false),'Changed order cannot reuse request');
    $invalidAttempt=hash('sha256','native-invalid-local-token');
    swdenied(fn()=>software_charge_run($buyer,$invoice,$invalidAttempt,'CARD_NUMBER','not-a-token'),
        'Malformed local one-time token is refused before gateway attempt reservation');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_charge_attempts').' WHERE invoice_id=?',[$invoice])->fetchColumn()===0
        && !one('SELECT id FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice]),
        'Malformed one-time token leaves no charge fence or attempt history');

    query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['SOFTWARE-MONTHLY','Monthly test software','software','published','month','USD',9900,0,null,'','Synthetic recurring','','Terms',0,1,$now,$now]);
    $monthlyProduct=(int)db()->lastInsertId();
    $monthlyInvoice=software_order_place($buyer,$monthlyProduct,1,1,'monthly.example.com',
        hash('sha256','native monthly software order'),true);
    swdenied(fn()=>software_subscription_enroll($buyer,$monthlyInvoice,'CARD_NUMBER','not-a-token'),
        'Malformed local subscription token is refused before enrollment reservation');
    swok(!one('SELECT id FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$monthlyInvoice]),
        'Malformed subscription token leaves no subscription attempt row');

    $attemptOne=hash('sha256','native-declined-charge-1');
    $attemptTwo=hash('sha256','native-declined-charge-2');
    swok(software_charge_prepare($invoice,1,$attemptOne)===(int)ledger_invoice($invoice)['amount_cents'],
        'First one-time card attempt is fenced to the invoice amount');
    software_charge_mark($invoice,'declined');
    swok(software_charge_prepare($invoice,1,$attemptTwo)===(int)ledger_invoice($invoice)['amount_cents'],
        'Conclusive decline permits a fresh card attempt');
    software_charge_mark($invoice,'sandbox_test','500000001');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_charge_attempts').' WHERE invoice_id=?',[$invoice])->fetchColumn()===2,
        'Declined and retried card attempts are both retained');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_charge_attempts').' WHERE invoice_id=? AND status=?',[$invoice,'declined'])->fetchColumn()===1,
        'Original declined attempt remains immutable history');
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
    save_setting('email_mode','disabled');
    swok(!software_delivery_notice_send($invoice),
        'Disabled mail transport leaves software delivery notice retryable');
    $notice=one('SELECT status,attempts,sent_at,last_error FROM '.table('software_notices').' WHERE invoice_id=?',[$invoice]);
    swok(($notice['status']??'')==='pending'&&(int)($notice['attempts']??0)===1&&($notice['sent_at']??null)===null,
        'Unaccepted software email is not falsely marked sent');

    swok(software_asset_customer_authorized(1,'NEXUSCORE'),'Purchasing customer is authorized for matching software assets');
    swok(!software_asset_customer_authorized(2,'NEXUSCORE'),'Different customer cannot use another account software entitlement');
    $usageLicense=(int)one('SELECT license_id FROM '.table('software_entitlements').' WHERE invoice_id=? ORDER BY unit_number LIMIT 1',[$invoice])['license_id'];
    software_usage_record($usageLicense,'new.example.com');
    software_usage_record($usageLicense,'new.example.com');
    $usage=one('SELECT check_count,last_seen FROM '.table('software_usage').' WHERE license_id=? AND domain=?',
        [$usageLicense,'new.example.com']);
    swok((int)$usage['check_count']===1,'Repeated validation inside one minute is write-bucketed');
    query('UPDATE '.table('software_usage').' SET last_seen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE) WHERE license_id=? AND domain=?',
        [$usageLicense,'new.example.com']);
    software_usage_record($usageLicense,'new.example.com');
    $usage=one('SELECT check_count FROM '.table('software_usage').' WHERE license_id=? AND domain=?',
        [$usageLicense,'new.example.com']);
    swok((int)$usage['check_count']===2,'Validation after the one-minute bucket advances observed usage once');
    $issued=one('SELECT license_id FROM '.table('software_entitlements').' WHERE invoice_id=? ORDER BY unit_number LIMIT 1',[$invoice]);
    software_grant_replace_domain((int)$issued['license_id'],'new.example.com','moved.example.com',1);
    swok((bool)one('SELECT id FROM '.table('license_domains').' WHERE license_id=? AND domain=? AND is_active=1',
        [(int)$issued['license_id'],'moved.example.com']),'First customer software domain move succeeds');
    swdenied(fn()=>software_grant_replace_domain((int)$issued['license_id'],'moved.example.com','again.example.com',1),
        'Immediate repeated software domain move is blocked by cooldown');
    query('UPDATE '.table('software_charges').' SET status=?,transaction_id=?,updated_at=? WHERE invoice_id=?',
        ['paid','600000001',$now,$invoice]);
    $refund=['kind'=>'refund','transaction_id'=>'700000001','original_transaction_id'=>'600000001','amount_cents'=>500];
    swok(software_payment_reversal_apply($refund)==='one_time_reversal_review',
        'Verified refund moves one-time payment to review');
    swok(one('SELECT status FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice])['status']==='reversal_review',
        'One-time reversal review state is persisted');
    swok(software_payment_reversal_apply($refund)==='replay',
        'Exact repeated refund event is idempotent');
    swdenied(fn()=>software_payment_reversal_apply([
        'kind'=>'refund','transaction_id'=>'700000001','original_transaction_id'=>'600000001','amount_cents'=>499
    ]),'Reused reversal transaction ID with conflicting amount is refused');
    swok(software_payment_reversal_apply(['kind'=>'refund','transaction_id'=>'700000002','original_transaction_id'=>'600000001','amount_cents'=>250])==='one_time_reversal_review',
        'Distinct partial refund against same charge is retained');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_payment_reversals').' WHERE original_transaction_id=?',['600000001'])->fetchColumn()===2,
        'Both distinct refund transactions are preserved');
    swdenied(fn()=>software_payment_reversal_apply([
        'kind'=>'refund','transaction_id'=>'700000003','original_transaction_id'=>'600000001',
        'amount_cents'=>(int)$payment['amount_cents']
    ]),'Cumulative one-time refunds cannot exceed the verified original payment');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_payment_reversals').' WHERE original_transaction_id=?',['600000001'])->fetchColumn()===2,
        'Rejected over-refund creates no reversal receipt');
    swok(software_payment_dispute_record('600000001','MSP-CASE-001','Verified cardholder dispute received from merchant service provider',1)==='one_time_dispute_review',
        'Owner can record verified merchant dispute against one-time payment');
    swok(software_payment_dispute_record('600000001','MSP-CASE-001','Verified cardholder dispute received from merchant service provider',1)==='replay',
        'Repeated merchant dispute case is idempotent');
    swdenied(fn()=>software_payment_dispute_record('600000001','MSP-CASE-001',
        'Conflicting merchant dispute evidence must not overwrite retained history',1),
        'Reused dispute case reference with conflicting evidence is refused');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_payment_disputes').' WHERE case_reference=?',['MSP-CASE-001'])->fetchColumn()===1,
        'Merchant dispute evidence is retained once');
    swok(one('SELECT status FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice])['status']==='dispute_review',
        'Merchant dispute places one-time charge into review');
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
        [900001,1,1,2499,'creating',$now,$now]);
    swok(software_subscription_enroll_finalize(900001,'223456789','pending_first_payment')==='pending_first_payment',
        'ARB enrollment finalizer stores verified gateway id and pending state');
    $finalized=one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[900001]);
    swok(($finalized['gateway_subscription_id']??'')==='223456789'&&($finalized['status']??'')==='pending_first_payment',
        'ARB enrollment finalizer persists verified gateway identity');

    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
        [900002,1,1,2499,'review',$now,$now]);
    swok(software_subscription_enroll_finalize(900002,'323456789','pending_first_payment')==='review',
        'Concurrent local review wins over ARB enrollment success');
    $raced=one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[900002]);
    swok(($raced['gateway_subscription_id']??'')==='323456789'&&($raced['status']??'')==='review',
        'Concurrent review still retains external ARB subscription id for reconciliation');
    swdenied(fn()=>software_subscription_enroll_finalize(900002,'423456789','pending_first_payment'),
        'Conflicting gateway subscription id is refused instead of overwritten');

    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,gateway_subscription_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?,?)',
        [$invoice,1,'123456789',1,(int)$payment['amount_cents'],'active',$now,$now]);
    $subscriptionId=(int)one('SELECT id FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice])['id'];
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['cancelling',$subscriptionId]);
    swok(software_subscription_cancel_finalize($subscriptionId,1,$invoice)==='cancelled',
        'Cancellation finalizer confirms stored cancelled state');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['review',$subscriptionId]);
    swok(software_subscription_cancel_finalize($subscriptionId,1,$invoice)==='review',
        'Cancellation finalizer preserves concurrent review state');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['active',$subscriptionId]);
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
    swdenied(fn()=>software_payment_reversal_apply([
        'kind'=>'refund','transaction_id'=>'700000098','original_transaction_id'=>'600000099',
        'amount_cents'=>(int)$payment['amount_cents']+1
    ]),'Recurring refund cannot exceed the verified period payment');
    swok(software_payment_reversal_apply(['kind'=>'void','transaction_id'=>'700000099','original_transaction_id'=>'600000099','amount_cents'=>null])==='subscription_reversal_review',
        'Recurring payment reversal moves subscription to review');
    swok(one('SELECT status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice])['status']==='review',
        'Recurring reversal review state is persisted');
    echo 'Software commerce native: '.$n.' checks passed'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
