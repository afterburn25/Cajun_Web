<?php
/** Guided software onboarding checks, disposable MariaDB and private storage only. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';
$GLOBALS['cwp_config']['db']['prefix']='swon_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
start_session();$count=0;
if(($argv[1]??'')==='finish-worker'){
    $_SESSION=['uid'=>1,'auth_version'=>str_repeat('d',32),'login_at'=>time(),'last_activity'=>time()];
    require_once CWP_CODE_ROOT.'/app/licenses/software_onboarding.php';
    $key=$argv[2]??'';$title=$argv[3]??'';$version=$argv[4]??'';$receipt=$argv[5]??'';
    if(!preg_match('/^[a-f0-9]{64}$/D',$key)||!is_dir(dirname($receipt)))exit(2);
    file_put_contents($receipt.'.ready','ready');
    $deadline=microtime(true)+15;while(!is_file($receipt.'.go')&&microtime(true)<$deadline)usleep(20000);
    if(!is_file($receipt.'.go'))throw new RuntimeException('Concurrent onboarding start signal timed out.');
    try{$result=software_onboarding_finish($key,['product_id'=>'0','title'=>$title,'version'=>$version]);echo json_encode(['ok'=>true]+$result,JSON_THROW_ON_ERROR).PHP_EOL;}
    catch(Throwable $e){echo json_encode(['ok'=>false,'type'=>get_class($e),'message'=>$e->getMessage()],JSON_THROW_ON_ERROR).PHP_EOL;}
    exit;
}
function soon_ok(bool $pass,string $name):void{global $count;if(!$pass)throw new RuntimeException($name);$count++;echo 'PASS '.$name.PHP_EOL;}
function soon_deny(callable $work,string $name):void{try{$work();}catch(InvalidArgumentException|RuntimeException|LogicException){soon_ok(true,$name);return;}soon_ok(false,$name);}
function soon_zip(string $path,array $manifest,string $payload='payload'):void{
    $zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Could not create onboarding ZIP fixture.');
    $zip->addFromString('cajunwebpro.json',json_encode($manifest,JSON_THROW_ON_ERROR));$zip->addFromString('src/app.php','<?php // '.$payload);$zip->close();
}
try{
    foreach(schema_statements('swon_') as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    foreach(['studio_schema_version'=>'2','catalog_schema_version'=>'1','license_schema_version'=>'3','license_invoice_cutover'=>'0','invoice_ledger_schema_version'=>'1'] as $key=>$value)save_setting($key,$value);
    $now=gmdate('Y-m-d H:i:s');$password='Synthetic onboarding owner';
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Owner','onboarding-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('d',32),$now]);
    staff_seed_owner(1);$_SESSION=['uid'=>1,'auth_version'=>str_repeat('d',32),'login_at'=>time(),'last_activity'=>time()];
    require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
    require_once CWP_CODE_ROOT.'/app/licenses/software_onboarding.php';
    software_commerce_prepare($password);
    soon_ok(!software_onboarding_ready(),'Guided uploads require explicit owner preparation');
    software_onboarding_prepare();soon_ok(software_onboarding_ready(),'Owner preparation creates durable upload staging');
    software_onboarding_prepare();soon_ok(software_onboarding_ready(),'Guided upload preparation is idempotent');
    $requestTable=chr(96).config()['db']['prefix'].'software_upload_requests'.chr(96);db()->exec('ALTER TABLE '.$requestTable.' DROP INDEX asset_lookup');
    soon_ok(!software_onboarding_ready(),'Readiness fails closed when durable staging indexes are incomplete');
    soon_deny(fn()=>software_onboarding_prepare(),'Established incomplete staging is preserved for owner review');
    db()->exec('ALTER TABLE '.$requestTable.' ADD INDEX asset_lookup (asset_id)');soon_ok(software_onboarding_ready(),'Restored staging metadata returns readiness without changing data');
    soon_deny(fn()=>software_asset_insert_in_transaction('SOFTWARE-NOLOCK','1.0','No lock',[],'',false),'Asset composition refuses a caller without the owning transaction');
    soon_deny(fn()=>software_product_policy_save_in_transaction(1,0,'','',0,2,''),'Policy composition refuses a caller without the owning transaction');

    $temporary=sys_get_temp_dir().DIRECTORY_SEPARATOR.'cwp-onboarding-'.bin2hex(random_bytes(5));mkdir($temporary,0700,true);
    $first=$temporary.DIRECTORY_SEPARATOR.'first.zip';soon_zip($first,['title'=>'Native Onboard','version'=>'1.2.3','sku'=>'SOFTWARE-NATIVE-ONBOARD','module_key'=>'native-onboard','require'=>['php'=>'>=8.2']]);
    $request=software_onboarding_stage($first,'native-onboard.zip',false);
    soon_ok($request['state']==='review'&&$request['metadata']['title']==='Native Onboard'&&$request['metadata']['version']==='1.2.3','Supported metadata prefills name and version without code execution');
    soon_ok($request['metadata']['embedded_sku']==='SOFTWARE-NATIVE-ONBOARD'&&str_contains($request['metadata']['compatibility'],'php >=8.2'),'Embedded product identity and declared compatibility are preserved honestly');
    $saved=software_onboarding_finish($request['request_key'],['product_id'=>'0','title'=>'Native Onboard','version'=>'1.2.3']);
    $product=one('SELECT * FROM '.table('catalog_products').' WHERE id=?',[$saved['product_id']]);$asset=one('SELECT * FROM '.table('software_assets').' WHERE id=?',[$saved['asset_id']]);
    soon_ok($product['sku']==='SOFTWARE-NATIVE-ONBOARD'&&$product['status']==='draft'&&$product['amount_cents']===null&&$product['terms']==='','New software stays draft without invented price or terms');
    soon_ok((int)$asset['is_published']===0&&$asset['released_at']===null&&!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~',(string)$asset['file_path']),'Package is owner-private and stores only a relative private reference');
    $policy=software_product_policy((int)$product['id']);soon_ok($policy['module_key']==='native-onboard'&&$policy['max_installations']===2&&$policy['update_months']===0,'Default license settings and detected module key save atomically');
    soon_ok(software_asset_resolve_path((string)$asset['file_path'])!==null,'Saved private asset resolves to the exact retained ZIP');

    $concurrent=$temporary.DIRECTORY_SEPARATOR.'concurrent.zip';soon_zip($concurrent,['title'=>'Concurrent Product','version'=>'2.0.0']);
    $concurrentRequests=[software_onboarding_stage($concurrent,'concurrent-a.zip',false),software_onboarding_stage($concurrent,'concurrent-b.zip',false)];
    $race=$temporary.DIRECTORY_SEPARATOR.'race';$workers=[];
    foreach($concurrentRequests as $index=>$candidate){
        $receipt=$race.'-'.$index;
        $process=proc_open([PHP_BINARY,__FILE__,'finish-worker',$candidate['request_key'],'Concurrent Product','2.0.0',$receipt],
            [0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$receipt.'.out','w'],2=>['file',$receipt.'.err','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Concurrent onboarding worker did not start.');$workers[]=[$process,$receipt];
    }
    $ready=[];$deadline=microtime(true)+15;do{$ready=array_filter($workers,fn($worker)=>is_file($worker[1].'.ready'));if(count($ready)===2)break;usleep(20000);}while(microtime(true)<$deadline);
    if(count($ready)!==2)throw new RuntimeException('Concurrent onboarding workers did not become ready.');
    foreach($workers as $worker)file_put_contents($worker[1].'.go','go');$raceResults=[];
    foreach($workers as [$process,$receipt]){
        $deadline=microtime(true)+15;do{$status=proc_get_status($process);if(!$status['running'])break;usleep(20000);}while(microtime(true)<$deadline);
        if($status['running']){proc_terminate($process);throw new RuntimeException('Concurrent onboarding worker did not finish.');}
        proc_close($process);$stderr=file_get_contents($receipt.'.err');if($stderr!=='')throw new RuntimeException('Concurrent onboarding diagnostics: '.$stderr);
        $raceResults[]=json_decode(file_get_contents($receipt.'.out'),true,32,JSON_THROW_ON_ERROR);
    }
    soon_ok(count(array_filter($raceResults,fn($result)=>$result['ok']))===2&&count(array_filter($raceResults,fn($result)=>$result['reused']))===1,'Simultaneous creation serializes to one product and one immutable asset');
    soon_ok((int)query('SELECT COUNT(*) FROM '.table('catalog_products')." WHERE title='Concurrent Product'")->fetchColumn()===1&&(int)query('SELECT COUNT(*) FROM '.table('software_assets')." WHERE title='Concurrent Product'")->fetchColumn()===1,'Concurrent SKU creation leaves no duplicate database rows');

    $repeat=software_onboarding_stage($first,'native-onboard-again.zip',false);$same=software_onboarding_finish($repeat['request_key'],['product_id'=>'0','title'=>'Renamed display input','version'=>'1.2.3']);
    soon_ok($same['reused']&&$same['product_id']===$saved['product_id']&&$same['asset_id']===$saved['asset_id'],'Identical package replay resolves to the existing immutable product version');
    soon_ok((int)query('SELECT COUNT(*) FROM '.table('catalog_products')." WHERE sku='SOFTWARE-NATIVE-ONBOARD'")->fetchColumn()===1&&(int)query('SELECT COUNT(*) FROM '.table('software_assets')." WHERE sku='SOFTWARE-NATIVE-ONBOARD' AND version='1.2.3'")->fetchColumn()===1,'Replay creates no duplicate product or asset');

    $changed=$temporary.DIRECTORY_SEPARATOR.'changed.zip';soon_zip($changed,['title'=>'Native Onboard','version'=>'1.2.3','sku'=>'SOFTWARE-NATIVE-ONBOARD'],'different');
    $changedRequest=software_onboarding_stage($changed,'changed.zip',false);
    soon_deny(fn()=>software_onboarding_finish($changedRequest['request_key'],['product_id'=>(string)$saved['product_id'],'title'=>'Native Onboard','version'=>'1.2.3']),'Different bytes cannot replace an existing version');
    soon_ok((int)query('SELECT COUNT(*) FROM '.table('software_assets')." WHERE sku='SOFTWARE-NATIVE-ONBOARD'")->fetchColumn()===1,'Rejected replacement preserves the immutable package row');

    $next=$temporary.DIRECTORY_SEPARATOR.'next.zip';soon_zip($next,['title'=>'Native Onboard','version'=>'1.2.4','sku'=>'SOFTWARE-NATIVE-ONBOARD'],'next');
    $nextRequest=software_onboarding_stage($next,'next.zip',false);$nextSaved=software_onboarding_finish($nextRequest['request_key'],['product_id'=>(string)$saved['product_id'],'title'=>'Native Onboard','version'=>'1.2.4']);
    soon_ok(!$nextSaved['reused']&&(int)query('SELECT COUNT(*) FROM '.table('software_assets')." WHERE sku='SOFTWARE-NATIVE-ONBOARD'")->fetchColumn()===2,'Existing product accepts a distinct reviewed version without changing its SKU');

    $missing=$temporary.DIRECTORY_SEPARATOR.'missing.zip';soon_zip($missing,['title'=>'Missing Stage','version'=>'1.0.0']);$missingRequest=software_onboarding_stage($missing,'missing.zip',false);
    $missingFile=software_asset_resolve_path((string)$missingRequest['stored_path']);if($missingFile===null)throw new RuntimeException('Missing-file fixture did not stage.');unlink($missingFile);
    $productsBefore=(int)query('SELECT COUNT(*) FROM '.table('catalog_products'))->fetchColumn();$assetsBefore=(int)query('SELECT COUNT(*) FROM '.table('software_assets'))->fetchColumn();
    soon_deny(fn()=>software_onboarding_finish($missingRequest['request_key'],['product_id'=>'0','title'=>'Missing Stage','version'=>'1.0.0']),'Missing staged storage fails before any catalog mutation');
    soon_ok((int)query('SELECT COUNT(*) FROM '.table('catalog_products'))->fetchColumn()===$productsBefore&&(int)query('SELECT COUNT(*) FROM '.table('software_assets'))->fetchColumn()===$assetsBefore,'Missing storage leaves products and assets unchanged');

    $rollback=$temporary.DIRECTORY_SEPARATOR.'rollback.zip';soon_zip($rollback,['title'=>'Rollback Product','version'=>'1.0.0']);$rollbackRequest=software_onboarding_stage($rollback,'rollback.zip',false);
    $bad=$rollbackRequest['metadata'];$bad['module_key']=str_repeat('x',81);query('UPDATE '.table('software_upload_requests').' SET metadata=? WHERE id=?',[json_encode($bad,JSON_THROW_ON_ERROR),(int)$rollbackRequest['id']]);
    soon_deny(fn()=>software_onboarding_finish($rollbackRequest['request_key'],['product_id'=>'0','title'=>'Rollback Product','version'=>'1.0.0']),'Late policy validation rolls back the product and asset together');
    soon_ok(!one("SELECT id FROM ".table('catalog_products')." WHERE title='Rollback Product'")&&!one("SELECT id FROM ".table('software_assets')." WHERE title='Rollback Product'"),'Failed atomic save leaves no partial database rows');
    $rollbackRow=software_onboarding_request($rollbackRequest['request_key']);soon_ok($rollbackRow['state']==='review'&&$rollbackRow['last_error']!==''&&software_asset_resolve_path((string)$rollbackRow['stored_path'])!==null,'Failed save retains precise history and a recoverable staged request rather than an orphan');

    $expired=$temporary.DIRECTORY_SEPARATOR.'expired.zip';soon_zip($expired,['title'=>'Expired Stage','version'=>'1.0.0']);$expiredRequest=software_onboarding_stage($expired,'expired.zip',false);
    query('UPDATE '.table('software_upload_requests').' SET updated_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-172801),(int)$expiredRequest['id']]);
    soon_ok(software_onboarding_cleanup_expired()===1,'Expired unfinished private staging is removed under current owner authority');
    $expiredRow=one('SELECT state,last_error FROM '.table('software_upload_requests').' WHERE id=?',[(int)$expiredRequest['id']]);
    soon_ok($expiredRow['state']==='expired'&&$expiredRow['last_error']!==''&&software_asset_resolve_path((string)$expiredRequest['stored_path'])===null,'Expired cleanup retains useful history without orphaned bytes');

    soon_deny(fn()=>cwp_test_owner_revoked(fn()=>software_onboarding_finish($rollbackRequest['request_key'],['product_id'=>'0','title'=>'Denied','version'=>'1.0.1'])),'Revoked owner authority is rechecked at the mutation boundary');
    echo 'Software onboarding native: '.$count.' checks passed'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
