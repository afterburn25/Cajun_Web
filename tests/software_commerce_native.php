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
    require_once CWP_CODE_ROOT.'/app/billing/software_subscription_settle.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_subscription_enroll.php';
    require_once CWP_CODE_ROOT.'/app/billing/software_subscription_cancel.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_asset_access.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';
    require_once CWP_CODE_ROOT.'/app/licenses/bundle.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_usage.php';
    swok(!software_commerce_ready(),'Not ready until owner explicitly prepares');
    swok(software_grant_verdict(['id'=>999,'is_active'=>1,'is_master'=>0],'legacy.example.com'),
        'Pre-commerce install preserves legacy v3 validation');
    software_commerce_prepare($password);
    swok(software_commerce_ready(),'Owner preparation creates new v3 storage');
    swok(software_commerce_table_present('software_product_policies')
        &&software_commerce_column_present('software_entitlements','updates_until')
        &&software_commerce_column_present('software_entitlements','max_installations')
        &&software_commerce_column_present('software_entitlements','policy_revision')
        &&software_commerce_column_present('software_assets','released_at')
        &&software_commerce_index_present('software_assets','release_lookup'),
        'Fresh v3 storage includes product policy, entitlement snapshots and immutable release metadata');
    $repairAssetTable=chr(96).config()['db']['prefix'].'software_assets'.chr(96);
    db()->exec('ALTER TABLE '.$repairAssetTable.' DROP INDEX release_lookup, DROP COLUMN released_at');
    swok(!software_commerce_ready(),'V3 marker alone does not report ready when release metadata is incomplete');
    software_commerce_prepare($password);
    swok(software_commerce_ready()
        &&software_commerce_column_present('software_assets','released_at')
        &&software_commerce_index_present('software_assets','release_lookup'),
        'Owner preparation repairs incomplete v3 release metadata additively');

    $v3Schema=software_commerce_schema(config()['db']['prefix']);
    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_payment_disputes'.chr(96));
    swok(!software_commerce_ready(),
        'V3 readiness fails closed when a non-core commerce history table is missing');
    swdenied(fn()=>software_commerce_prepare($password),
        'Owner preparation refuses to silently recreate a missing established v3 history table');
    db()->exec($v3Schema['software_payment_disputes']);
    swok(software_commerce_ready(),
        'V3 readiness returns only after the complete commerce table set is restored');

    save_setting('software_commerce_version','');
    swdenied(fn()=>software_grant_verdict(['id'=>999,'is_active'=>1,'is_master'=>0],'legacy.example.com'),
        'Existing entitlement storage without readiness marker is inconclusive, not authorized');
    swdenied(fn()=>software_grant_register_domain(999,'blocked-add.example',1),
        'Incomplete software readiness blocks add-domain mutation instead of falling back to legacy');
    swdenied(fn()=>software_grant_deactivate_domain(999,'blocked-remove.example',1),
        'Incomplete software readiness blocks deactivate-domain mutation instead of falling back to legacy');
    swdenied(fn()=>software_grant_replace_domain(999,'old.example','new.example',1),
        'Incomplete software readiness blocks replacement mutation instead of falling back to legacy');

    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_product_policies'.chr(96));
    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_charge_attempts'.chr(96));
    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_payment_reversals'.chr(96));
    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_payment_disputes'.chr(96));
    $entitlementTable=chr(96).config()['db']['prefix'].'software_entitlements'.chr(96);
    $assetTable=chr(96).config()['db']['prefix'].'software_assets'.chr(96);
    db()->exec('ALTER TABLE '.$entitlementTable.' DROP COLUMN policy_revision, DROP COLUMN max_installations, DROP COLUMN updates_until, DROP COLUMN purpose');
    db()->exec('ALTER TABLE '.$assetTable.' DROP INDEX release_lookup, DROP COLUMN released_at');
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
    swok(software_commerce_column_present('software_assets','released_at')
        &&software_commerce_index_present('software_assets','release_lookup'),
        'V1 to v3 upgrade adds immutable release timestamp metadata');

    $migrateKey=license_create(1,'SOFTWARE-MIGRATE',1,false,null);
    $migrateLicense=(int)one('SELECT id FROM '.table('website_licenses').' WHERE license_key=?',[$migrateKey])['id'];
    $migrateExpiry=gmdate('Y-m-d H:i:s',time()+86400*20);
    query('INSERT INTO '.table('software_entitlements').' (license_id,license_type,licensee,expires_at,updates_until,max_installations,policy_revision,issued_by,purpose,invoice_id,unit_number,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$migrateLicense,'temporary','Migrated Buyer',$migrateExpiry,null,2,0,1,'',990001,1,$now]);
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,gateway_subscription_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?,?)',
        [990001,1,'990001001',1,100,'active',$now,$now]);

    db()->exec('DROP TABLE '.chr(96).config()['db']['prefix'].'software_product_policies'.chr(96));
    db()->exec('ALTER TABLE '.$entitlementTable.' DROP COLUMN policy_revision, DROP COLUMN max_installations, DROP COLUMN updates_until');
    db()->exec('ALTER TABLE '.$assetTable.' DROP INDEX release_lookup, DROP COLUMN released_at');
    save_setting('software_commerce_version','2');
    software_commerce_prepare($password);
    swok(software_commerce_ready(),'Published branch v2 storage upgrades additively to v3');
    swok(software_commerce_table_present('software_product_policies')
        &&software_commerce_column_present('software_entitlements','updates_until')
        &&software_commerce_column_present('software_entitlements','max_installations')
        &&software_commerce_column_present('software_entitlements','policy_revision')
        &&software_commerce_column_present('software_assets','released_at')
        &&software_commerce_index_present('software_assets','release_lookup'),
        'V2 to v3 upgrade adds product policy, entitlement snapshots and immutable release metadata');
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
    $assetRow=one('SELECT sha256,byte_size,file_path,released_at,created_at FROM '.table('software_assets').' WHERE id=?',[$assetId]);
    swok(is_array($assetRow)&&$assetRow['sha256']===hash_file('sha256',$zipPath)
        &&(int)$assetRow['byte_size']===filesize($zipPath)
        &&$assetRow['file_path']==='synthetic-private.zip'
        &&$assetRow['released_at']!==null&&$assetRow['released_at']===$assetRow['created_at'],
        'Customer-visible publication records immutable path, checksum, size and first release time');
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
    swok(software_asset_member_name('folder/file.txt')==='folder/file.txt'
        &&software_asset_member_name('folder/')==='folder/'
        &&software_asset_member_name('C:/escape.txt')===null
        &&software_asset_member_name('./relative.txt')===null
        &&software_asset_member_name('folder//ambiguous.txt')===null
        &&software_asset_member_name('folder/trailing. ')===null
        &&software_asset_member_name('folder/CON.txt')===null
        &&software_asset_member_name("control\nname.txt")===null,
        'Software publisher accepts only canonical portable ZIP member names');
    $collisionPath=$private.DIRECTORY_SEPARATOR.'synthetic-collision.zip';
    $collision=new ZipArchive();
    if($collision->open($collisionPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Collision ZIP fixture failed.');
    $collision->addFromString('Readme.txt','first case');
    $collision->addFromString('README.txt','second case');
    $collision->close();
    swdenied(fn()=>software_asset_publish('SOFTWARE-TEST','1.0.2','Case collision ZIP must fail',$collisionPath,$password),
        'Software publisher rejects case-colliding extraction paths');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_assets').' WHERE sku=?',['SOFTWARE-TEST'])->fetchColumn()===1,
        'Rejected package publications create no extra software asset rows');

    $privateAsset=software_asset_publish('SOFTWARE-TEST','1.0.3','Owner-private software ZIP',$zipPath,$password,false);
    $privateBefore=one('SELECT sku,version,title,file_path,sha256,byte_size,is_published,released_at,created_at FROM '.table('software_assets').' WHERE id=?',[$privateAsset]);
    swok((int)$privateBefore['is_published']===0&&$privateBefore['released_at']===null,
        'Owner-private software release has no customer release timestamp');
    swdenied(fn()=>software_asset_visibility($privateAsset,true,'wrong-password'),
        'Software release visibility requires serialized owner reauthentication');
    swok((int)one('SELECT is_published FROM '.table('software_assets').' WHERE id=?',[$privateAsset])['is_published']===0,
        'Rejected visibility change leaves owner-private release hidden');
    swok(software_asset_visibility($privateAsset,true,$password),
        'Owner can make a verified private release customer-visible');
    $privateAfter=one('SELECT sku,version,title,file_path,sha256,byte_size,is_published,released_at,created_at FROM '.table('software_assets').' WHERE id=?',[$privateAsset]);
    $immutableBefore=$privateBefore;$immutableAfter=$privateAfter;
    unset($immutableBefore['is_published'],$immutableBefore['released_at'],$immutableAfter['is_published'],$immutableAfter['released_at']);
    swok((int)$privateAfter['is_published']===1&&$privateAfter['released_at']!==null&&$immutableBefore===$immutableAfter,
        'First customer publication stamps release time without changing immutable ZIP identity');
    $firstRelease=(string)$privateAfter['released_at'];
    swok(software_asset_visibility($privateAsset,false,$password),'Owner can hide a customer release without erasing release history');
    swok(software_asset_visibility($privateAsset,true,$password),'Owner can restore a customer release');
    $privateRestored=one('SELECT is_published,released_at FROM '.table('software_assets').' WHERE id=?',[$privateAsset]);
    swok((int)$privateRestored['is_published']===1&&$privateRestored['released_at']===$firstRelease,
        'Hide and restore preserves the immutable first customer release time');

    db()->beginTransaction();
    $trial=software_grant_create(1,'SOFTWARE-TEST','trial','Synthetic Buyer','www.example.com',gmdate('Y-m-d H:i:s',time()+86400),1);
    db()->commit();
    $lic=license_get($trial['key']);
    swok(software_grant_verdict($lic,'example.com'),'Trial authorizes its registered domain');
    query('UPDATE '.table('software_entitlements').' SET expires_at=? WHERE license_id=?',
        [gmdate('Y-m-d H:i:s',time()-86400),$trial['id']]);
    swok(!software_grant_verdict($lic,'example.com'),'Expired trial denied');
    swok(license_bundle_is_software_entitlement((int)$trial['id']),
        'Software entitlement is recognized by legacy-bundle guard');
    swdenied(fn()=>license_bundle_store((int)$trial['id'],$zipPath,'Legacy software bundle must fail'),
        'Software entitlement cannot be stored through legacy website bundle path');
    db()->beginTransaction();
    try{
        software_grant_create(1,'SOFTWARE-TEST','master','Internal QA',null,null,1,null,null,'',false,null,$password);
        db()->rollBack();
        swok(false,'Master requires recorded purpose');
    }catch(InvalidArgumentException $expected){
        if(db()->inTransaction())db()->rollBack();
        swok(true,'Master requires recorded purpose');
    }
    db()->beginTransaction();
    try{
        software_grant_create(1,'SOFTWARE-TEST','master','Internal QA',null,null,1,null,null,
            'Owner-approved release validation',false,null,'wrong-password');
        db()->rollBack();
        swok(false,'Master requires owner password inside grant service');
    }catch(InvalidArgumentException $expected){
        if(db()->inTransaction())db()->rollBack();
        swok(true,'Master requires owner password inside grant service');
    }
    db()->beginTransaction();
    try{
        software_grant_create(1,'SOFTWARE-TEST','master','Internal QA',null,null,2,null,null,
            'Owner-approved release validation',false,null,$password);
        db()->rollBack();
        swok(false,'Master issuer must match reauthenticated owner');
    }catch(RuntimeException $expected){
        if(db()->inTransaction())db()->rollBack();
        swok(true,'Master issuer must match reauthenticated owner');
    }
    db()->beginTransaction();
    $master=software_grant_create(1,'SOFTWARE-TEST','master','Internal QA',null,null,1,null,null,
        'Owner-approved release validation',false,null,$password);
    db()->commit();
    swok(software_grant_verdict(license_get($master['key']),'other.example.com'),'Owner master supports any domain');
    swdenied(fn()=>software_grant_staff_register_domain((int)$master['id'],'master-should-not-register.example.com',1),
        'Owner cannot attach installation domains to a software master license');
    $masterMeta=one('SELECT purpose,updates_until,max_installations FROM '.table('software_entitlements').' WHERE license_id=?',[$master['id']]);
    swok(($masterMeta['purpose']??'')==='Owner-approved release validation','Master purpose is persisted for owner review');
    swok($masterMeta['updates_until']===null&&(int)$masterMeta['max_installations']===25,
        'New internal master snapshots unrestricted version and seat policy');
    query('UPDATE '.table('software_entitlements').' SET updates_until=? WHERE license_id=?',
        ['2000-01-01 00:00:00',$master['id']]);
    swok(software_grant_version_verdict(license_get($master['key']),'1.0.0')===true,
        'Internal master ignores stale customer update cutoff for published versions');
    $masterPrivateAsset=software_asset_publish('SOFTWARE-TEST','1.0.4','Internal QA staged ZIP',$zipPath,$password,false);
    swok(software_grant_version_verdict(license_get($master['key']),'1.0.4')===true,
        'Internal master validates an owner-private staged release');
    swok(software_grant_version_verdict($lic,'1.0.4')===null,
        'Ordinary customer license cannot discover an owner-private staged release');
    swok((int)one('SELECT is_published FROM '.table('software_assets').' WHERE id=?',[$masterPrivateAsset])['is_published']===0,
        'Master QA validation does not change staged release visibility');
    query('UPDATE '.table('software_entitlements').' SET updates_until=NULL WHERE license_id=?',[$master['id']]);
    query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['NEXUSCORE','Test software','software','published','once','USD',2499,0,null,'','Synthetic','','Terms',0,1,$now,$now]);
    $product=(int)db()->lastInsertId();
    swok(software_policy_add_months(new DateTimeImmutable('2026-01-31 12:34:56',new DateTimeZone('UTC')),1)==='2026-02-28 12:34:56',
        'Software update month arithmetic clamps month-end safely');
    $policy=software_product_policy_save($product,0,'nexus-core','SOFTWARE-TEST',12,1,$password);
    swok($policy['revision']===1&&$policy['module_key']==='nexus-core'
        &&$policy['dependency_skus']===['SOFTWARE-TEST']
        &&$policy['update_months']===12&&$policy['max_installations']===1,
        'Owner software product policy records module dependency updates and install limit');
    swdenied(fn()=>software_product_policy_save($product,0,'stale-write','',0,2,$password),
        'Stale software product policy revision cannot overwrite current policy');
    swdenied(fn()=>software_product_policy_save($product,1,'bad-password','',0,2,'wrong-password'),
        'Software product policy save reauthenticates the owner inside the serialized mutation');
    swdenied(fn()=>software_grant_create(2,'NEXUSCORE','trial','Other Buyer','manual-dependent.example.com',
        gmdate('Y-m-d H:i:s',time()+86400),1),
        'Owner-issued ordinary software grant cannot bypass missing dependency entitlement');

    foreach([
        ['SOFTWARE-DEP-A','Dependency graph A'],
        ['SOFTWARE-DEP-B','Dependency graph B']
    ] as [$depSku,$depTitle]){
        query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$depSku,$depTitle,'software','published','once','USD',1200,0,null,'','Synthetic dependency graph','','Terms',0,1,$now,$now]);
    }
    $depA=(int)one('SELECT id FROM '.table('catalog_products').' WHERE sku=?',['SOFTWARE-DEP-A'])['id'];
    $depB=(int)one('SELECT id FROM '.table('catalog_products').' WHERE sku=?',['SOFTWARE-DEP-B'])['id'];
    $depPolicy=software_product_policy_save($depA,0,'dep-a','SOFTWARE-DEP-B',0,1,$password);
    swok($depPolicy['dependency_skus']===['SOFTWARE-DEP-B'],
        'Acyclic software dependency policy is accepted');
    swdenied(fn()=>software_product_policy_save($depB,0,'dep-b','SOFTWARE-DEP-A',0,1,$password),
        'Circular software dependency policy is refused');
    swok(!one('SELECT product_id FROM '.table('software_product_policies').' WHERE product_id=?',[$depB]),
        'Rejected circular dependency creates no policy row');

    $historicSnapshot=json_encode([
        'sku'=>'SOFTWARE-DEP-A',
        'software_policy'=>[
            'module_key'=>'dep-a',
            'dependency_skus'=>['SOFTWARE-DEP-B'],
            'update_months'=>0,
            'max_installations'=>1,
            'revision'=>1
        ]
    ],JSON_THROW_ON_ERROR);
    query('INSERT INTO '.table('software_orders').' (client_id,invoice_id,product_id,sku,quantity,domain,request_key,request_hash,snapshot,billing_term,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [1,980001,$depA,'SOFTWARE-DEP-A',1,'historic-dependency.example',
         hash('sha256','historic dependency request'),hash('sha256','historic dependency hash'),
         $historicSnapshot,'once',$now]);
    $depARevision2=software_product_policy_save($depA,1,'dep-a','',0,1,$password);
    swok($depARevision2['revision']===2&&$depARevision2['dependency_skus']===[],
        'Owner may remove a dependency from future software offers');
    $depRename=[
        'sku'=>'SOFTWARE-DEP-B-RENAMED','title'=>'Dependency graph B','family'=>'software','status'=>'published','term'=>'once',
        'currency'=>'USD','amount'=>'12.00','setup'=>'0.00','cost'=>'','provider_ref'=>'','summary'=>'Synthetic dependency graph',
        'features'=>'','terms'=>'Terms','sort_order'=>'0'
    ];
    swdenied(fn()=>catalog_save($depB,1,$depRename),
        'Software dependency SKU captured in an immutable purchase snapshot cannot be renamed');
    swok(one('SELECT sku,revision FROM '.table('catalog_products').' WHERE id=?',[$depB])===
        ['sku'=>'SOFTWARE-DEP-B','revision'=>1],
        'Rejected dependency SKU rename leaves the referenced product unchanged');

    $orphanKey=license_create(1,'NEXUSCORE',1,false,null);
    $orphan=one('SELECT id,is_active,is_master FROM '.table('website_licenses').' WHERE license_key=?',[$orphanKey]);
    swok(license_bundle_is_software_entitlement((int)$orphan['id']),
        'Orphan key for a registered software product still fails closed against legacy bundles');
    swdenied(fn()=>license_bundle_store((int)$orphan['id'],$zipPath,'Orphan software bundle must fail'),
        'Orphan registered software key cannot be stored as a legacy website bundle');
    swdenied(fn()=>software_grant_verdict($orphan,'orphan.example.com'),
        'Reduced API license row cannot authorize registered software with missing entitlement history');
    license_register_domain((int)$orphan['id'],'orphan.example.com',1);
    swdenied(fn()=>software_grant_replace_domain((int)$orphan['id'],'orphan.example.com','orphan-moved.example.com',1),
        'Orphan registered software cannot mutate domains without entitlement history');
    swok((bool)one('SELECT id FROM '.table('license_domains').' WHERE license_id=? AND domain=? AND is_active=1',
        [(int)$orphan['id'],'orphan.example.com']),
        'Rejected orphan software domain mutation preserves the original active domain');
    swdenied(fn()=>software_grant_staff_register_domain((int)$orphan['id'],'orphan-owner-add.example.com',1),
        'Owner domain add also fails closed for orphan software entitlement history');
    swdenied(fn()=>software_grant_staff_deactivate_domain((int)$orphan['id'],'orphan.example.com',1),
        'Owner domain deactivation also fails closed for orphan software entitlement history');
    swdenied(fn()=>software_grant_staff_replace_domain((int)$orphan['id'],'orphan.example.com','orphan-owner-move.example.com',1),
        'Owner domain replacement also fails closed for orphan software entitlement history');
    db()->beginTransaction();
    $dependencyGrant=software_grant_create(1,'SOFTWARE-TEST','standard','Synthetic Buyer','dependency.example.com',null,1);
    db()->commit();
    swok(software_customer_has_valid_product(1,'SOFTWARE-TEST'),
        'Customer dependency check recognizes a valid ordinary software entitlement');
    swdenied(fn()=>software_grant_register_domain((int)$dependencyGrant['id'],'foreign-add.example.com',2),
        'Foreign customer cannot add a software installation through the service directly');
    swdenied(fn()=>software_grant_deactivate_domain((int)$dependencyGrant['id'],'dependency.example.com',2),
        'Foreign customer cannot deactivate a software installation through the service directly');
    swdenied(fn()=>software_grant_replace_domain((int)$dependencyGrant['id'],'dependency.example.com','foreign-move.example.com',2),
        'Foreign customer cannot replace a software installation through the service directly');
    swok(one('SELECT domain,is_active FROM '.table('license_domains').' WHERE license_id=? AND domain=?',
        [(int)$dependencyGrant['id'],'dependency.example.com'])===['domain'=>'dependency.example.com','is_active'=>1]
        &&!one('SELECT id FROM '.table('license_domains').' WHERE license_id=? AND domain IN (?,?)',
            [(int)$dependencyGrant['id'],'foreign-add.example.com','foreign-move.example.com']),
        'Rejected foreign software-domain mutations leave ownership state unchanged');
    db()->beginTransaction();
    $manualDependent=software_grant_create(1,'NEXUSCORE','trial','Synthetic Buyer','manual-dependent.example.com',
        gmdate('Y-m-d H:i:s',time()+86400),1);
    db()->commit();
    swok((bool)one('SELECT license_id FROM '.table('software_entitlements').' WHERE license_id=? AND license_type=?',
        [$manualDependent['id'],'trial']),
        'Owner-issued dependent software grant succeeds after dependency entitlement is valid');
    swok(!software_customer_has_valid_product(2,'SOFTWARE-TEST'),
        'Software dependency entitlement is tenant-isolated');

    $hash=hash('sha256','synthetic software order');
    $buyer=['id'=>1,'name'=>'Synthetic Buyer'];
    $otherBuyer=['id'=>2,'name'=>'Other Buyer'];

    query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['SOFTWARE-RECONCILE','Charge reconciliation test','software','published','once','USD',4200,0,null,'','Synthetic charge reconciliation','','Terms',0,1,$now,$now]);
    $reconcileProduct=(int)db()->lastInsertId();
    $reconcileInvoice=software_order_place($buyer,$reconcileProduct,1,1,'reconcile.example.com',
        hash('sha256','software reconcile order'),false,0);
    $reconcileRequest=hash('sha256','software reconcile attempt');
    $reconcileCents=software_charge_prepare($reconcileInvoice,1,$reconcileRequest);
    swok($reconcileCents===4200,'Uncertain-charge fixture reserves the exact software invoice amount');
    software_charge_mark($reconcileInvoice,'uncertain','650000001');
    swdenied(fn()=>software_charge_reconcile_finalize($reconcileInvoice,'650000001',$reconcileCents,'wrong-password'),
        'Paid reconciliation finalizer refuses incorrect owner password inside the mutation boundary');
    swok(one('SELECT status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$reconcileInvoice])
        ===['status'=>'uncertain','transaction_id'=>'650000001']
        &&one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$reconcileInvoice])===['status'=>'issued']
        &&(int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$reconcileInvoice])->fetchColumn()===0,
        'Rejected owner proof leaves uncertain charge, invoice and entitlements unchanged');
    swok(software_charge_reconcile_finalize($reconcileInvoice,'650000001',$reconcileCents,$password)==='paid',
        'Owner reconciliation finalizes an independently verified uncertain charge');
    $reconciledCharge=one('SELECT status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$reconcileInvoice]);
    $reconciledAttempt=one('SELECT status,transaction_id FROM '.table('software_charge_attempts').' WHERE request_key=?',[$reconcileRequest]);
    swok($reconciledCharge===['status'=>'paid','transaction_id'=>'650000001']
        &&$reconciledAttempt===['status'=>'paid','transaction_id'=>'650000001']
        &&one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$reconcileInvoice])===['status'=>'paid'],
        'Reconciliation atomically aligns charge attempt, charge and invoice states');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$reconcileInvoice])->fetchColumn()===1,
        'Reconciled payment safely fulfills exactly one purchased entitlement');
    swok(software_charge_reconcile_finalize($reconcileInvoice,'650000001',$reconcileCents,$password)==='already_paid'
        &&(int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$reconcileInvoice])->fetchColumn()===1,
        'Reconciliation replay is idempotent and does not mint a duplicate license');
    swok((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=? AND detail LIKE ?',
        ['license.payment.reconcile','Software invoice '.$reconcileInvoice.' reconciled to verified Authorize.net transaction 650000001%'])->fetchColumn()===1,
        'Owner reconciliation records one immutable audit event');

    $webhookInvoice=software_order_place($buyer,$reconcileProduct,1,1,'webhook-one-time.example.com',
        hash('sha256','software one-time webhook review order'),false,0);
    $webhookRequest=hash('sha256','software one-time webhook review attempt');
    $webhookCents=software_charge_prepare($webhookInvoice,1,$webhookRequest);
    $webhookDetails=[
        'messages'=>['resultCode'=>'Ok'],
        'transaction'=>[
            'transId'=>'650000003',
            'transactionType'=>'authCaptureTransaction',
            'transactionStatus'=>'capturedPendingSettlement',
            'authAmount'=>'42.00',
            'order'=>['invoiceNumber'=>'CWP-'.$webhookInvoice]
        ]
    ];
    swok(software_charge_capture_webhook_review($webhookDetails,'650000003')==='one_time_review',
        'Signed one-time capture readback is fenced for owner review instead of ARB settlement');
    $webhookCharge=one('SELECT status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$webhookInvoice]);
    $webhookAttempt=one('SELECT status,transaction_id FROM '.table('software_charge_attempts').' WHERE request_key=?',[$webhookRequest]);
    swok($webhookCharge===['status'=>'review','transaction_id'=>'650000003']
        &&$webhookAttempt===['status'=>'review','transaction_id'=>'650000003']
        &&one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$webhookInvoice])===['status'=>'issued']
        &&(int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$webhookInvoice])->fetchColumn()===0,
        'One-time capture webhook retains transaction identity without paying or fulfilling the invoice');
    swok(software_charge_reconcile_finalize($webhookInvoice,'650000003',$webhookCents,$password)==='paid',
        'Owner can finalize the exact one-time webhook review after independent reconciliation');
    swok(software_charge_capture_webhook_review($webhookDetails,'650000003')==='one_time_replay'
        &&(int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$webhookInvoice])->fetchColumn()===1,
        'Paid one-time capture webhook replay is acknowledged without duplicate fulfillment');
    $sandboxBefore=one('SELECT status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$webhookInvoice]);
    swok(software_sandbox_capture_verify($webhookDetails,'650000003')==='sandbox_payment_verified',
        'Sandbox one-time capture shape is verified against the exact local invoice and amount');
    swok(one('SELECT status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$webhookInvoice])===$sandboxBefore,
        'Sandbox one-time capture verification does not mutate paid state');
    $sandboxWrong=$webhookDetails;$sandboxWrong['transaction']['authAmount']='41.99';
    swdenied(fn()=>software_sandbox_capture_verify($sandboxWrong,'650000003'),
        'Sandbox one-time capture refuses amount mismatch instead of simulating payment');

    $webhookMismatchInvoice=software_order_place($buyer,$reconcileProduct,1,1,'webhook-mismatch.example.com',
        hash('sha256','software one-time webhook mismatch order'),false,0);
    $webhookMismatchRequest=hash('sha256','software one-time webhook mismatch attempt');
    software_charge_prepare($webhookMismatchInvoice,1,$webhookMismatchRequest);
    $crossInvoiceDetails=$webhookDetails;
    $crossInvoiceDetails['transaction']['order']['invoiceNumber']='CWP-'.$webhookMismatchInvoice;
    swok(software_charge_capture_webhook_review($crossInvoiceDetails,'650000003')==='one_time_conflict',
        'Gateway transaction already assigned to another invoice is classified as a permanent webhook conflict');
    swok(one('SELECT status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$webhookMismatchInvoice])
        ===['status'=>'processing','transaction_id'=>null],
        'Cross-invoice webhook conflict does not rewrite the unrelated local charge');
    $mismatchDetails=[
        'messages'=>['resultCode'=>'Ok'],
        'transaction'=>[
            'transId'=>'650000004',
            'transactionType'=>'authCaptureTransaction',
            'transactionStatus'=>'capturedPendingSettlement',
            'authAmount'=>'41.99',
            'order'=>['invoiceNumber'=>'CWP-'.$webhookMismatchInvoice]
        ]
    ];
    swok(software_charge_capture_webhook_review($mismatchDetails,'650000004')==='one_time_mismatch',
        'One-time capture amount mismatch is retained for review rather than treated as a subscription');
    swok(one('SELECT status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$webhookMismatchInvoice])
        ===['status'=>'review','transaction_id'=>'650000004']
        &&one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$webhookMismatchInvoice])===['status'=>'issued']
        &&(int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$webhookMismatchInvoice])->fetchColumn()===0,
        'Mismatched one-time webhook cannot mark invoice paid or issue software');

    $reconcileInvoiceTwo=software_order_place($buyer,$reconcileProduct,1,1,'reconcile-two.example.com',
        hash('sha256','software reconcile second order'),false,0);
    $reconcileRequestTwo=hash('sha256','software reconcile second attempt');
    $reconcileCentsTwo=software_charge_prepare($reconcileInvoiceTwo,1,$reconcileRequestTwo);
    software_charge_mark($reconcileInvoiceTwo,'uncertain');
    swdenied(fn()=>software_charge_reconcile_finalize($reconcileInvoiceTwo,'650000001',$reconcileCentsTwo,$password),
        'Gateway transaction already assigned to another software invoice cannot be reused');
    swok(one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$reconcileInvoiceTwo])===['status'=>'issued']
        &&(int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$reconcileInvoiceTwo])->fetchColumn()===0,
        'Rejected reconciliation leaves the second invoice unpaid and unfulfilled');

    query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['SOFTWARE-UPGRADE','Paid upgrade test','software','published','once','USD',3500,0,null,'','Synthetic paid upgrade','','Terms',0,1,$now,$now]);
    $upgradeProduct=(int)db()->lastInsertId();
    $upgradePolicy=software_product_policy_save($upgradeProduct,0,'upgrade-test','',6,1,$password);
    db()->beginTransaction();
    $upgradeTrial=software_grant_create(1,'SOFTWARE-UPGRADE','trial','Synthetic Buyer','upgrade.example.com',
        gmdate('Y-m-d H:i:s',time()+86400*7),1);
    db()->commit();
    $beforeUpgrade=one('SELECT l.id,l.license_key,l.created_at,e.license_type,e.expires_at,e.issued_by,e.created_at AS entitlement_created,d.id AS domain_id,d.registered_at FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id JOIN '.table('license_domains').' d ON d.license_id=l.id AND d.is_active=1 WHERE l.id=?',[$upgradeTrial['id']]);
    $upgradeInvoice=software_order_place($buyer,$upgradeProduct,1,1,'upgrade.example.com',
        hash('sha256','paid trial upgrade order'),false,(int)$upgradePolicy['revision']);
    $upgradePayment=ledger_invoice($upgradeInvoice);
    db()->beginTransaction();
    ledger_insert($upgradePayment,0,'gateway',(int)$upgradePayment['amount_cents'],'authorize_net',
        'Synthetic verified paid upgrade',gmdate('Y-m-d'));
    query('UPDATE '.table('studio_invoices').' SET status=?,paid_at=? WHERE id=?',
        ['paid',gmdate('Y-m-d H:i:s'),$upgradeInvoice]);
    db()->commit();
    software_fulfill_paid_order($upgradeInvoice);
    software_fulfill_paid_order($upgradeInvoice);
    $afterUpgrade=one('SELECT l.id,l.license_key,l.created_at,e.license_type,e.expires_at,e.updates_until,e.max_installations,e.policy_revision,e.issued_by,e.invoice_id,e.unit_number,e.created_at AS entitlement_created,d.id AS domain_id,d.registered_at FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id JOIN '.table('license_domains').' d ON d.license_id=l.id AND d.is_active=1 WHERE l.id=?',[$upgradeTrial['id']]);
    swok((int)$afterUpgrade['id']===(int)$beforeUpgrade['id']
        &&$afterUpgrade['license_key']===$beforeUpgrade['license_key']
        &&$afterUpgrade['created_at']===$beforeUpgrade['created_at']
        &&$afterUpgrade['entitlement_created']===$beforeUpgrade['entitlement_created']
        &&(int)$afterUpgrade['issued_by']===(int)$beforeUpgrade['issued_by']
        &&(int)$afterUpgrade['domain_id']===(int)$beforeUpgrade['domain_id']
        &&$afterUpgrade['registered_at']===$beforeUpgrade['registered_at'],
        'Verified paid upgrade preserves trial key issuer creation and domain history');
    swok($afterUpgrade['license_type']==='standard'&&$afterUpgrade['expires_at']===null
        &&$afterUpgrade['updates_until']!==null&&(int)$afterUpgrade['max_installations']===1
        &&(int)$afterUpgrade['policy_revision']===(int)$upgradePolicy['revision']
        &&(int)$afterUpgrade['invoice_id']===$upgradeInvoice&&(int)$afterUpgrade['unit_number']===1,
        'Verified paid upgrade converts trial entitlement to purchased standard rights');
    swok((int)query('SELECT COUNT(*) FROM '.table('website_licenses').' WHERE customer_id=? AND product_id=?',[1,'SOFTWARE-UPGRADE'])->fetchColumn()===1,
        'Paid trial upgrade and fulfillment replay do not mint a second key');
    swok((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=? AND detail LIKE ?',
        ['license.software.upgrade','Software license '.(int)$upgradeTrial['id'].' converted trial -> standard%'])->fetchColumn()===1,
        'Paid trial upgrade retains one immutable conversion audit on replay');

    query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['SOFTWARE-UPGRADE-AMB','Ambiguous upgrade test','software','published','once','USD',3600,0,null,'','Synthetic ambiguous upgrade','','Terms',0,1,$now,$now]);
    $ambProduct=(int)db()->lastInsertId();
    foreach(['Ambiguous Trial A','Ambiguous Trial B'] as $trialName){
        db()->beginTransaction();
        software_grant_create(1,'SOFTWARE-UPGRADE-AMB','trial',$trialName,'ambiguous-upgrade.example.com',
            gmdate('Y-m-d H:i:s',time()+86400*7),1);
        db()->commit();
    }
    $ambInvoice=software_order_place($buyer,$ambProduct,1,1,'ambiguous-upgrade.example.com',
        hash('sha256','ambiguous paid trial upgrade order'),false,0);
    $ambPayment=ledger_invoice($ambInvoice);
    db()->beginTransaction();
    ledger_insert($ambPayment,0,'gateway',(int)$ambPayment['amount_cents'],'authorize_net',
        'Synthetic ambiguous paid upgrade',gmdate('Y-m-d'));
    query('UPDATE '.table('studio_invoices').' SET status=?,paid_at=? WHERE id=?',
        ['paid',gmdate('Y-m-d H:i:s'),$ambInvoice]);
    db()->commit();
    swdenied(fn()=>software_fulfill_paid_order($ambInvoice),
        'Ambiguous matching trial licenses stop paid fulfillment for owner review');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' e JOIN '.table('website_licenses').' l ON l.id=e.license_id WHERE l.customer_id=? AND l.product_id=? AND e.invoice_id IS NULL AND e.license_type=?',
        [1,'SOFTWARE-UPGRADE-AMB','trial'])->fetchColumn()===2
        &&(int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$ambInvoice])->fetchColumn()===0,
        'Ambiguous paid upgrade leaves both original trials untouched and mints no paid key');

    swdenied(fn()=>software_order_place($otherBuyer,$product,1,1,'other.example.com',
        hash('sha256','missing dependency order'),false,1),
        'Customer without required software dependency cannot create the order');
    $invoice=software_order_place($buyer,$product,1,2,'new.example.com',$hash,false,1);
    $ordered=one('SELECT * FROM '.table('software_orders').' WHERE invoice_id=?',[$invoice]);
    $orderedPolicy=software_order_policy_snapshot($ordered);
    swok($orderedPolicy['revision']===1&&$orderedPolicy['update_months']===12
        &&$orderedPolicy['max_installations']===1&&$orderedPolicy['dependency_skus']===['SOFTWARE-TEST'],
        'Software order stores the exact entitlement policy disclosed at purchase');
    $policy2=software_product_policy_save($product,1,'nexus-core','SOFTWARE-TEST',0,3,$password);
    swok($policy2['revision']===2&&$policy2['update_months']===0&&$policy2['max_installations']===3,
        'Owner can publish a later policy revision after an order is created');
    swok($invoice===software_order_place($buyer,$product,1,2,'new.example.com',$hash,false,1),
        'Exact order retry reuses its original invoice after policy changes');
    swdenied(fn()=>software_order_place($buyer,$product,1,1,'stale-policy.example.com',
        hash('sha256','stale policy checkout'),false,1),
        'New checkout with a stale disclosed policy revision is refused');
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

    license_deactivate_domain((int)$dependencyGrant['id'],'dependency.example.com',1);
    swdenied(fn()=>software_charge_prepare($invoice,1,hash('sha256','dependency-missing-charge')),
        'Missing required software dependency blocks charge reservation');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_charge_attempts').' WHERE invoice_id=?',[$invoice])->fetchColumn()===0,
        'Dependency refusal creates no gateway charge attempt');
    license_register_domain((int)$dependencyGrant['id'],'dependency.example.com',1);

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
    license_deactivate_domain((int)$dependencyGrant['id'],'dependency.example.com',1);
    swdenied(fn()=>software_fulfill_paid_order($invoice),
        'Paid software does not mint licenses while its snapshotted dependency is missing');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$invoice])->fetchColumn()===0,
        'Dependency failure after payment leaves paid order unfulfilled for owner review');
    license_register_domain((int)$dependencyGrant['id'],'dependency.example.com',1);
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

    $rights=all('SELECT license_id,updates_until,max_installations,policy_revision FROM '.table('software_entitlements').' WHERE invoice_id=? ORDER BY unit_number',[$invoice]);
    swok(count($rights)===2
        &&array_reduce($rights,fn($ok,$r)=>$ok&&$r['updates_until']!==null
            &&(int)$r['max_installations']===1&&(int)$r['policy_revision']===1,true),
        'Paid units snapshot the purchased update window and one-install policy');
    $cutoff=(string)$rights[0]['updates_until'];
    $future=(new DateTimeImmutable($cutoff,new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d H:i:s');
    $usageLicense=(int)$rights[0]['license_id'];
    swdenied(fn()=>license_register_domain($usageLicense,'second-install.example.com',1),
        'One-install software entitlement rejects a second concurrent installation');
    query('INSERT INTO '.table('license_domains').' (license_id,domain,is_active,registered_at) VALUES (?,?,1,?)',
        [$usageLicense,'forced-overseat.example.com',$now]);
    $usageLicenseRow=one('SELECT * FROM '.table('website_licenses').' WHERE id=?',[$usageLicense]);
    swok(!software_grant_verdict($usageLicenseRow,'new.example.com'),
        'Runtime validation denies a software license whose active installations exceed its snapshot limit');
    query('DELETE FROM '.table('license_domains').' WHERE license_id=? AND domain=?',[$usageLicense,'forced-overseat.example.com']);

    query('INSERT INTO '.table('software_assets').' (sku,version,title,file_path,sha256,byte_size,is_published,released_at,created_at) VALUES (?,?,?,?,?,?,1,?,?)',
        ['NEXUSCORE','1.0.0','Inside update window','synthetic-private.zip',str_repeat('a',64),22,$now,$now]);
    query('INSERT INTO '.table('software_assets').' (sku,version,title,file_path,sha256,byte_size,is_published,released_at,created_at) VALUES (?,?,?,?,?,?,1,?,?)',
        ['NEXUSCORE','2.0.0','Outside update window','synthetic-private.zip',str_repeat('b',64),22,$future,$now]);
    query('INSERT INTO '.table('software_assets').' (sku,version,title,file_path,sha256,byte_size,is_published,released_at,created_at) VALUES (?,?,?,?,?,?,1,?,?)',
        ['NEXUSCORE','3.0.0','Staged before cutoff, released after','synthetic-private.zip',str_repeat('c',64),22,$future,$now]);
    $insideAsset=one('SELECT * FROM '.table('software_assets').' WHERE sku=? AND version=?',['NEXUSCORE','1.0.0']);
    swok((bool)software_asset_entitlement_authorized(1,(int)$rights[0]['license_id'],$insideAsset),
        'Exact purchased entitlement authorizes its matching release');
    $secondLicense=(int)$rights[1]['license_id'];
    swok((bool)software_asset_entitlement_authorized(1,$secondLicense,$insideAsset),
        'Second purchased unit independently authorizes its matching release');
    query('UPDATE '.table('software_orders').' SET quantity=1 WHERE invoice_id=?',[$invoice]);
    swok(software_asset_entitlement_authorized(1,$secondLicense,$insideAsset)===null,
        'Detached entitlement unit cannot authorize a download beyond purchased quantity');
    $secondLicenseRow=one('SELECT * FROM '.table('website_licenses').' WHERE id=?',[$secondLicense]);
    swdenied(fn()=>software_grant_verdict($secondLicenseRow,'new.example.com'),
        'Runtime validation is inconclusive when entitlement unit exceeds purchased quantity');
    query('UPDATE '.table('software_orders').' SET quantity=2,client_id=2 WHERE invoice_id=?',[$invoice]);
    swok(software_asset_entitlement_authorized(1,(int)$rights[0]['license_id'],$insideAsset)===null,
        'Entitlement cannot authorize a download when its purchase ownership is inconsistent');
    swdenied(fn()=>software_grant_verdict($usageLicenseRow,'new.example.com'),
        'Runtime validation is inconclusive when entitlement purchase owner is inconsistent');
    query('UPDATE '.table('software_orders').' SET client_id=1 WHERE invoice_id=?',[$invoice]);
    query('UPDATE '.table('studio_invoices').' SET status=? WHERE id=?',['issued',$invoice]);
    swdenied(fn()=>software_grant_verdict($usageLicenseRow,'new.example.com'),
        'Purchased entitlement is inconclusive when its originating invoice is no longer trusted paid history');
    swdenied(fn()=>software_asset_entitlement_authorized(1,$usageLicense,$insideAsset),
        'Private download treats an untrusted paid-order history as inconclusive and refuses access');
    query('UPDATE '.table('studio_invoices').' SET status=? WHERE id=?',['paid',$invoice]);
    swok(software_grant_version_verdict($usageLicenseRow,'1.0.0')===true,
        'Version-aware validation permits a release inside the update window');
    swok(software_grant_version_verdict($usageLicenseRow,'2.0.0')===false,
        'Version-aware validation denies a known release outside the update window');
    swok(software_grant_version_verdict($usageLicenseRow,'3.0.0')===false,
        'Private staging date cannot make a post-cutoff customer release eligible');
    $stagedLate=one('SELECT * FROM '.table('software_assets').' WHERE sku=? AND version=?',['NEXUSCORE','3.0.0']);
    swok(software_asset_entitlement_authorized(1,$usageLicense,$stagedLate)===null,
        'Exact download authorization uses first customer release time, not staging time');
    swok(software_grant_version_verdict($usageLicenseRow,'9.9.9')===null,
        'Version-aware validation treats an unknown release as inconclusive');

    $unchanged=one('SELECT updates_until,max_installations,policy_revision FROM '.table('software_entitlements').' WHERE license_id=?',[$usageLicense]);
    swok(($unchanged['updates_until']??null)===$cutoff
        &&(int)$unchanged['max_installations']===1&&(int)$unchanged['policy_revision']===1,
        'Existing entitlement rights do not change when product policy changes');

    db()->beginTransaction();
    $futureGrant=software_grant_create(1,'NEXUSCORE','standard','Synthetic Buyer','policy-three.example.com',null,1);
    db()->commit();
    $futureRights=one('SELECT updates_until,max_installations,policy_revision FROM '.table('software_entitlements').' WHERE license_id=?',[$futureGrant['id']]);
    swok($futureRights['updates_until']===null&&(int)$futureRights['max_installations']===3
        &&(int)$futureRights['policy_revision']===2,
        'New grant snapshots the latest lifetime-update three-install policy');
    license_register_domain((int)$futureGrant['id'],'policy-three-b.example.com',1);
    license_register_domain((int)$futureGrant['id'],'policy-three-c.example.com',1);
    swdenied(fn()=>license_register_domain((int)$futureGrant['id'],'policy-three-d.example.com',1),
        'Three-install entitlement rejects a fourth concurrent installation');

    swok(software_grant_lifecycle((int)$futureGrant['id'])['state']==='active',
        'New software entitlement begins in active lifecycle state');
    software_grant_lifecycle_transition((int)$futureGrant['id'],'suspend','Temporary owner support hold',1);
    swok(software_grant_lifecycle((int)$futureGrant['id'])['state']==='suspended'
        &&(int)one('SELECT is_active FROM '.table('website_licenses').' WHERE id=?',[$futureGrant['id']])['is_active']===0,
        'Owner can temporarily suspend software without deleting its entitlement');
    swdenied(fn()=>software_grant_register_domain((int)$futureGrant['id'],'suspended-change.example.com',1),
        'Suspended customer cannot change software installations');
    software_grant_lifecycle_transition((int)$futureGrant['id'],'resume','Temporary owner hold cleared',1);
    swok((int)one('SELECT is_active FROM '.table('website_licenses').' WHERE id=?',[$futureGrant['id']])['is_active']===1,
        'Owner resume restores the same software entitlement');
    swdenied(fn()=>software_grant_lifecycle_transition((int)$futureGrant['id'],'revoke','Owner revocation test',1,'wrong-password'),
        'Software revocation requires correct owner reauthentication');
    software_grant_lifecycle_transition((int)$futureGrant['id'],'revoke','Owner revocation test',1,$password);
    swok(software_grant_lifecycle((int)$futureGrant['id'])['state']==='revoked',
        'Owner can distinctly revoke a software license');
    swdenied(fn()=>software_grant_register_domain((int)$futureGrant['id'],'revoked-change.example.com',1),
        'Revoked customer cannot change software installations');
    software_grant_lifecycle_transition((int)$futureGrant['id'],'restore','Owner-approved restoration',1,$password);
    swok(software_grant_lifecycle((int)$futureGrant['id'])['state']==='active',
        'Owner restoration returns the same revoked key to active state');

    query('INSERT INTO '.table('license_domains').' (license_id,domain,is_active,registered_at,deactivated_at) VALUES (?,?,0,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 200 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 190 DAY))',
        [$usageLicense,'stale-observation.example.com']);
    query('INSERT INTO '.table('software_usage').' (license_id,domain,first_seen,last_seen,check_count) VALUES (?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 200 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 181 DAY),7)',
        [$usageLicense,'stale-observation.example.com']);
    query('INSERT INTO '.table('license_domains').' (license_id,domain,is_active,registered_at,deactivated_at) VALUES (?,?,0,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 220 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 200 DAY))',
        [$futureGrant['id'],'fully-inactive-stale.example.com']);
    query('INSERT INTO '.table('software_usage').' (license_id,domain,first_seen,last_seen,check_count) VALUES (?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 220 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 190 DAY),9)',
        [$futureGrant['id'],'fully-inactive-stale.example.com']);
    software_usage_record($usageLicense,'new.example.com');
    swok(!one('SELECT id FROM '.table('software_usage').' WHERE license_id=? AND domain=?',
        [$usageLicense,'stale-observation.example.com']),
        'Validation observation retention purges inactive domains older than 180 days');
    swok(!one('SELECT id FROM '.table('software_usage').' WHERE license_id=? AND domain=?',
        [$futureGrant['id'],'fully-inactive-stale.example.com']),
        'Unrelated successful validation prunes stale observations from a fully inactive license');
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

    query('INSERT INTO '.table('software_usage').' (license_id,domain,first_seen,last_seen,check_count) VALUES (?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 220 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 181 DAY),3)', 
        [(int)$futureGrant['id'],'policy-three.example.com']);
    query('INSERT INTO '.table('license_domains').' (license_id,domain,is_active,registered_at,deactivated_at) VALUES (?,?,0,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 220 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 200 DAY))',
        [(int)$futureGrant['id'],'owner-view-stale.example.com']);
    query('INSERT INTO '.table('software_usage').' (license_id,domain,first_seen,last_seen,check_count) VALUES (?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 220 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 181 DAY),4)',
        [(int)$futureGrant['id'],'owner-view-stale.example.com']);
    $ownerUsage=software_usage_recent((int)$futureGrant['id'],10);
    swok((bool)array_filter($ownerUsage,static fn($row)=>($row['domain']??'')==='policy-three.example.com'),
        'Owner usage view retains old observations for still-active installations');
    swok(!(bool)array_filter($ownerUsage,static fn($row)=>($row['domain']??'')==='owner-view-stale.example.com'),
        'Owner usage view applies the 180-day inactive-domain retention boundary');
    swok(software_usage_for_customer((int)$futureGrant['id'],2)===[],
        'Customer usage helper does not expose another customer software observations');

    $issued=one('SELECT license_id FROM '.table('software_entitlements').' WHERE invoice_id=? ORDER BY unit_number LIMIT 1',[$invoice]);
    software_grant_replace_domain((int)$issued['license_id'],'new.example.com','moved.example.com',1);
    swok((bool)one('SELECT id FROM '.table('license_domains').' WHERE license_id=? AND domain=? AND is_active=1',
        [(int)$issued['license_id'],'moved.example.com']),'First customer software domain move succeeds');
    swdenied(fn()=>software_grant_replace_domain((int)$issued['license_id'],'moved.example.com','again.example.com',1),
        'Immediate repeated software domain move is blocked by cooldown');
    swok(software_grant_staff_replace_domain((int)$issued['license_id'],'moved.example.com','owner-reviewed.example.com',1),
        'Owner-reviewed support change may override the customer domain cooldown');
    swdenied(fn()=>software_grant_staff_register_domain((int)$issued['license_id'],'owner-extra.example.com',1),
        'Owner-reviewed domain add still cannot exceed the purchased installation cap');
    swok(one('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1',[(int)$issued['license_id']])
        ===['domain'=>'owner-reviewed.example.com'],
        'Owner cooldown override preserves exactly one active installation');
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
    swdenied(fn()=>software_payment_dispute_record('600000001','MSP-CASE-001',
        'Verified cardholder dispute received from merchant service provider','wrong-password'),
        'Merchant dispute mutation refuses incorrect owner password inside the service');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_payment_disputes').' WHERE case_reference=?',['MSP-CASE-001'])->fetchColumn()===0,
        'Rejected owner proof creates no merchant dispute evidence');
    swok(software_payment_dispute_record('600000001','MSP-CASE-001','Verified cardholder dispute received from merchant service provider',$password)==='one_time_dispute_review',
        'Owner can record verified merchant dispute against one-time payment');
    swok(software_payment_dispute_record('600000001','MSP-CASE-001','Verified cardholder dispute received from merchant service provider',$password)==='replay',
        'Repeated merchant dispute case is idempotent');
    swdenied(fn()=>software_payment_dispute_record('600000001','MSP-CASE-001',
        'Conflicting merchant dispute evidence must not overwrite retained history',$password),
        'Reused dispute case reference with conflicting evidence is refused');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_payment_disputes').' WHERE case_reference=?',['MSP-CASE-001'])->fetchColumn()===1,
        'Merchant dispute evidence is retained once');
    swok(one('SELECT status FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice])['status']==='dispute_review',
        'Merchant dispute places one-time charge into review');
    $lateCapture=[
        'messages'=>['resultCode'=>'Ok'],
        'transaction'=>[
            'transId'=>'600000001',
            'transactionType'=>'authCaptureTransaction',
            'transactionStatus'=>'settledSuccessfully',
            'authAmount'=>software_anet_amount((int)$payment['amount_cents']),
            'order'=>['invoiceNumber'=>'CWP-'.$invoice]
        ]
    ];
    swok(software_charge_capture_webhook_review($lateCapture,'600000001')==='one_time_review',
        'Late capture webhook acknowledges sticky dispute review without reopening payment');
    swok(one('SELECT status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice])
        ===['status'=>'dispute_review','transaction_id'=>'600000001'],
        'Late capture webhook cannot downgrade dispute review state');
    swdenied(fn()=>software_charge_reconcile_finalize($invoice,'600000001',(int)$payment['amount_cents'],$password),
        'Owner paid reconciliation remains blocked after refund or dispute review');
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
        [900001,1,1,2499,'creating',$now,$now]);
    swok(software_subscription_enroll_finalize(900001,'223456789','pending_first_payment')==='pending_first_payment',
        'ARB enrollment finalizer stores verified gateway id and pending state');
    $finalized=one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[900001]);
    swok(($finalized['gateway_subscription_id']??'')==='223456789'&&($finalized['status']??'')==='pending_first_payment',
        'ARB enrollment finalizer persists verified gateway identity');
    $sandboxSubDetails=[
        'messages'=>['resultCode'=>'Ok'],
        'transaction'=>[
            'transId'=>'650009001',
            'transactionStatus'=>'capturedPendingSettlement',
            'authAmount'=>'24.99',
            'subscription'=>['id'=>'223456789'],
            'order'=>['invoiceNumber'=>'CWP-900001']
        ]
    ];
    swok(software_sandbox_capture_verify($sandboxSubDetails,'650009001')==='sandbox_subscription_verified',
        'Sandbox recurring capture shape is verified against stored subscription identity amount and invoice');
    swok(one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[900001])===$finalized,
        'Sandbox recurring capture verification does not advance subscription state');

    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
        [900002,1,1,2499,'review',$now,$now]);
    swok(software_subscription_enroll_finalize(900002,'323456789','pending_first_payment')==='review',
        'Concurrent local review wins over ARB enrollment success');
    $raced=one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[900002]);
    swok(($raced['gateway_subscription_id']??'')==='323456789'&&($raced['status']??'')==='review',
        'Concurrent review still retains external ARB subscription id for reconciliation');
    swdenied(fn()=>software_subscription_enroll_finalize(900002,'423456789','pending_first_payment'),
        'Conflicting gateway subscription id is refused instead of overwritten');

    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
        [900003,1,1,2499,'creating',$now,$now]);
    software_subscription_enroll_mark_review(900003,'423456790');
    swok(one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[900003])
        ===['gateway_subscription_id'=>'423456790','status'=>'review'],
        'Interrupted ARB finalization retains the known gateway subscription id for owner reconciliation');
    software_subscription_enroll_mark_review(900003,'423456790');
    swok(one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[900003])
        ===['gateway_subscription_id'=>'423456790','status'=>'review'],
        'Repeated ARB uncertainty marking is idempotent for the same gateway id');
    swdenied(fn()=>software_subscription_enroll_mark_review(900003,'423456791'),
        'ARB uncertainty marker refuses to overwrite a different retained gateway id');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE invoice_id=?',['pending_first_payment',900003]);
    software_subscription_enroll_mark_review(900003,'423456790');
    swok(one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[900003])
        ===['gateway_subscription_id'=>'423456790','status'=>'pending_first_payment'],
        'Late uncertainty handling does not downgrade an already-finalized subscription');

    $reconcileMonthly=software_order_place($buyer,$monthlyProduct,1,1,'monthly-reconcile.example.com',
        hash('sha256','monthly enrollment reconcile'),true);
    $reconcileMonthlyInvoice=ledger_invoice($reconcileMonthly);
    $reconcileMonthlyOrder=one('SELECT created_at FROM '.table('software_orders').' WHERE invoice_id=?',[$reconcileMonthly]);
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
        [$reconcileMonthly,1,1,(int)$reconcileMonthlyInvoice['amount_cents'],'review',$reconcileMonthlyOrder['created_at'],$now]);
    swdenied(fn()=>software_subscription_reconcile_finalize($reconcileMonthly,'423456780','active',false,'wrong-password'),
        'ARB reconciliation finalizer refuses incorrect owner password inside the mutation boundary');
    swok(one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$reconcileMonthly])
        ===['gateway_subscription_id'=>null,'status'=>'review'],
        'Rejected ARB owner proof leaves local subscription identity and state unchanged');
    swok(software_subscription_reconcile_finalize($reconcileMonthly,'423456780','active',false,$password)==='pending_first_payment',
        'Owner ARB reconciliation links an exact active gateway subscription without granting paid access');
    swok(one('SELECT gateway_subscription_id,status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$reconcileMonthly])
        ===['gateway_subscription_id'=>'423456780','status'=>'pending_first_payment'],
        'ARB reconciliation persists exact gateway identity and pending-first-payment state');
    swok(software_subscription_reconcile_finalize($reconcileMonthly,'423456780','active',false,$password)==='already_linked',
        'ARB enrollment reconciliation replay is idempotent');
    swdenied(fn()=>software_subscription_reconcile_finalize($reconcileMonthly,'423456781','active',false,$password),
        'Reconciled local subscription refuses a conflicting gateway id');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$reconcileMonthly])->fetchColumn()===0,
        'ARB enrollment reconciliation never fulfills software before the first verified charge');

    $cancelledMonthly=software_order_place($buyer,$monthlyProduct,1,1,'monthly-cancelled.example.com',
        hash('sha256','monthly cancelled reconcile'),true);
    $cancelledInvoice=ledger_invoice($cancelledMonthly);
    $cancelledOrder=one('SELECT created_at FROM '.table('software_orders').' WHERE invoice_id=?',[$cancelledMonthly]);
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
        [$cancelledMonthly,1,1,(int)$cancelledInvoice['amount_cents'],'review',$cancelledOrder['created_at'],$now]);
    swok(software_subscription_reconcile_finalize($cancelledMonthly,'423456782','canceled',false,$password)==='cancelled',
        'ARB reconciliation records a gateway-cancelled enrollment without granting access');
    swok((int)query('SELECT COUNT(*) FROM '.table('software_entitlements').' WHERE invoice_id=?',[$cancelledMonthly])->fetchColumn()===0,
        'Cancelled ARB reconciliation mints no entitlement');

    $suspendedMonthly=software_order_place($buyer,$monthlyProduct,1,1,'monthly-suspended.example.com',
        hash('sha256','monthly suspended reconcile'),true);
    $suspendedInvoice=ledger_invoice($suspendedMonthly);
    $suspendedOrder=one('SELECT created_at FROM '.table('software_orders').' WHERE invoice_id=?',[$suspendedMonthly]);
    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
        [$suspendedMonthly,1,1,(int)$suspendedInvoice['amount_cents'],'review',$suspendedOrder['created_at'],$now]);
    swok(software_subscription_reconcile_finalize($suspendedMonthly,'423456783','suspended',false,$password)==='review',
        'ARB reconciliation keeps a suspended gateway subscription in owner review');
    swdenied(fn()=>software_subscription_reconcile_finalize($suspendedMonthly,'423456780','active',false,$password),
        'Gateway subscription id already linked to another invoice cannot be reused');

    query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,gateway_subscription_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?,?)',
        [$invoice,1,'123456789',1,(int)$payment['amount_cents'],'active',$now,$now]);
    $subscriptionId=(int)one('SELECT id FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice])['id'];
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['cancelling',$subscriptionId]);
    swdenied(fn()=>software_subscription_cancel_finalize($subscriptionId,2,$invoice),
        'Cancellation finalizer refuses a foreign customer even after gateway cancellation');
    swok(one('SELECT status FROM '.table('software_subscriptions').' WHERE id=?',[$subscriptionId])===['status'=>'cancelling'],
        'Rejected cancellation ownership leaves the local subscription pending finalization');
    swok(software_subscription_cancel_finalize($subscriptionId,1,$invoice)==='cancelled',
        'Cancellation finalizer confirms stored cancelled state');
    swok(software_subscription_cancel_mark_review($subscriptionId)==='cancelled'
        &&one('SELECT status FROM '.table('software_subscriptions').' WHERE id=?',[$subscriptionId])===['status'=>'cancelled'],
        'Late cancellation error handling cannot overwrite a concurrent cancelled state');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['cancelling',$subscriptionId]);
    swok(software_subscription_cancel_mark_review($subscriptionId)==='review'
        &&one('SELECT status FROM '.table('software_subscriptions').' WHERE id=?',[$subscriptionId])===['status'=>'review'],
        'Cancellation error moves only the still-cancelling state into review');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['review',$subscriptionId]);
    swok(software_subscription_cancel_finalize($subscriptionId,1,$invoice)==='review',
        'Cancellation finalizer preserves concurrent review state');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['active',$subscriptionId]);
    swok(software_subscription_lifecycle_review('net.authorize.customer.subscription.failed','123456789')==='subscription_payment_failed',
        'Failed gateway subscription records a recoverable payment failure');
    swok(one('SELECT status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice])['status']==='payment_failed',
        'Payment-failed subscription state is persisted distinctly from disputes');
    swok(software_subscription_lifecycle_review('net.authorize.customer.subscription.suspended','123456789')==='subscription_billing_suspended',
        'Gateway suspension records a temporary billing suspension');
    swok(one('SELECT status FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice])['status']==='billing_suspended',
        'Billing-suspended state is persisted for automatic payment recovery');
    swok(software_subscription_post_payment_status('billing_suspended',false,false)==='active',
        'Exactly verified renewal is allowed to resume billing suspension');
    swok(software_subscription_lifecycle_review('net.authorize.customer.subscription.cancelled','123456789')==='subscription_cancelled',
        'Cancellation can stop future renewal from a billing-suspended state');
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
