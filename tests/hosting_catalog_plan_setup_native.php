<?php
/** Actual disposable imported-product -> active plan + mapping; no provider write or publication. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOSTING_PLAN_SETUP')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1'||count($argv)!==1)exit(2);
$port=filter_var(getenv('CWP_TEST_PORT')?:'33306',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);if($port===false)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/app/hosting/catalog_offer_setup.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes;
const HPS_PREFIX='hpsci_';
const HPS_PASSWORD='Synthetic imported hosting plan owner';
$root=dirname(__DIR__);$private=$root.'/test-runtime/hosting-catalog-plan-setup-private';$qa=$root.'/qa';$report=$qa.'/hosting-catalog-plan-setup-native-results.json';
$secret='SYNTHETIC_IMPORTED_PLAN_KEY';$checks=[];$reportReady=false;$stage='preflight';$dns=0;$reads=0;$readStatus=200;
$types=[['id'=>7001,'label'=>'Observed monthly type'],['id'=>7002,'label'=>'Observed annual type'],['id'=>7003,'label'=>'Observed editable type'],['id'=>7004,'label'=>'Observed unpriced type']];
$details=['inclusions'=>['One maintained website'],'exclusions'=>['Website redesign'],'limits'=>['websites'=>1,'mailboxes'=>5],'support'=>'Reply within two business days'];
$configuration=['reseller_id'=>42,'label'=>'Owner reviewed account','region'=>'Owner reviewed region','limits'=>['websites'=>1,'mailboxes'=>5],
    'cost'=>['amount'=>'9.8700','currency'=>'USD','period_months'=>1,'tax'=>'unknown','basis'=>'per-package','reference'=>'Synthetic owner cost record']];
$input=['sku'=>'PLAN-MONTH','title'=>'Owner monthly hosting','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount'=>'25.00','setup'=>'0.00','cost'=>'9.87','provider_ref'=>'Synthetic private provider note','summary'=>'Owner description for monthly hosting.','features'=>'Owner features','terms'=>'Owner commercial terms','sort_order'=>'0'];
function hps_id(int $n):string{return sprintf('%032x',$n);}
function hps_ok(string $name,bool $passed):void{global $checks;if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate fixture label.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function hps_deny(string $name,callable $call):void{$caught=false;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$caught=true;}hps_ok($name,$caught);}
function hps_logic(string $name,callable $call):void{$caught=false;try{$call();}catch(LogicException $e){if($e::class!==LogicException::class)throw $e;$caught=true;}hps_ok($name,$caught);}
function hps_path(string $path):void{for($p=$path;;$p=$parent){if(is_link($p))throw new RuntimeException('Fixture path has a symbolic link.');$parent=dirname($p);if($parent===$p)break;}}
function hps_owner(int $id=1):void{$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);if(!$u)throw new LogicException('Fixture owner missing.');$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);}
function hps_client():void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');$_SESSION=['client_id'=>1,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);}
function hps_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|'.HPS_PREFIX),0,40);return (int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
/** Every original business row and protected file, excluding only automatic allocation metadata. */
function hps_state():string{
    global $private;$state=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(HPS_PREFIX),HPS_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Ahpsci_[a-z0-9_]+\z/D',$name))throw new LogicException('Unexpected fixture table.');$rows=array_map(static fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$name.'`'));sort($rows,SORT_STRING);$state[$name]=$rows;
    }
    $files=[];$walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS));foreach($walk as $file){if($file->isLink()||!$file->isFile())throw new LogicException('Unexpected private fixture entry.');$files[str_replace('\\','/',substr($file->getPathname(),strlen($private)+1))]=hash_file('sha256',$file->getPathname());}ksort($files);
    return hash('sha256',json_encode([$state,$files],JSON_THROW_ON_ERROR));
}
function hps_effects():array{global $dns,$reads;return [$dns,$reads,hps_state()];}
function hps_resolve():string{global $dns;if(db()->inTransaction()||hps_mutex())throw new LogicException('Resolver holds a mutation guard.');$dns++;return '8.8.8.8';}
function hps_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation):InventorySnapshot{
    global $secret,$reads,$readStatus,$types;$authorize();
    if(db()->inTransaction()||!hps_mutex()||$key!==$secret||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unexpected synthetic catalog boundary.');
    $reads++;return PackageTypes::project(new TransportResponse($readStatus,[],json_encode($types,JSON_THROW_ON_ERROR)),time(),'twenty-i|'.$profile['id'].'|'.$operation);
}
function hps_refresh(string $profile,?callable $resolver=null):array{
    if(db()->inTransaction()||hps_mutex())throw new LogicException('Read cooldown holds a mutation guard.');
    $last=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($last>time())throw new RuntimeException('Future refresh time.');$until=hrtime(true)+32000000000;while(time()<$last+30){if(hrtime(true)>$until)throw new RuntimeException('Catalog cooldown exceeded.');usleep(100000);}
    return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],HPS_PASSWORD,$resolver??'hps_resolve','hps_read',InventoryRead::PACKAGE_TYPES);
}
function hps_create(int $n,int $product,string $hash,?array $d=null,?array $c=null,int $days=7,string $password=HPS_PASSWORD):array{
    global $details,$configuration;return hosting_catalog_setup_create(hps_id($n),hps_id($n+1),hps_id($n+2),$product,$hash,$d??$details,$c??$configuration,$days,$password);
}
function hps_image(int $product,int $n):array{
    global $private;$id=hps_id($n);$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQC6gAAAF2AOe0MgrYAAAAAElFTkSuQmCC',true);if($png===false)throw new LogicException('Fixture image invalid.');
    write_private_file($private.'/media/'.$id.'.php',CWP_GUARD.$png);
    query('INSERT INTO '.table('media').' (id,filename,mime,bytes,width,height,original_name,created_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$id.'.php','image/png',strlen($png),1,1,'synthetic-plan.png',gmdate('Y-m-d H:i:s')]);
    query('UPDATE '.table('catalog_provider_imports').' SET media_id=?,media_revision=1 WHERE product_id=?',[$id,$product]);return ['id'=>$id,'sha256'=>hash_file('sha256',$private.'/media/'.$id.'.php')];
}
function hps_forbidden_counts():array{
    $out=[];foreach(['studio_invoices','catalog_invoice_snapshots','service_subscriptions','service_subscription_events',...hosting_catalog_tables()] as $name)$out[$name]=(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();return $out;
}
try{
    foreach([$private,$qa,$report] as $path)hps_path($path);
    if(file_exists($private)||is_link($private)||file_exists($report)||is_link($report))throw new RuntimeException('Preserve original plan setup fixture evidence.');
    umask(0077);if(!is_dir($qa)&&!mkdir($qa,0700,true))throw new RuntimeException('Cannot prepare QA directory.');$reportReady=true;
    $GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-plan-setup','storage'=>$private,'key'=>str_repeat('synthetic-imported-plan-',4),'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>HPS_PREFIX]];
    // CWP_TEST_PORT is the selected host-mapped endpoint, not the server's internal @@port.
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? LIMIT 1',[strlen(HPS_PREFIX),HPS_PREFIX]))throw new RuntimeException('Preserve existing plan setup namespace.');
    hps_ok('Native setup begins in exact disposable database and unused namespace',query('SELECT DATABASE()')->fetchColumn()==='cwp_test');
    if(!mkdir($private,0700,true)||!mkdir($private.'/media',0700))throw new RuntimeException('Cannot create protected fixture directory.');
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath($root.'/site');start_session();
    foreach(schema_statements(HPS_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','4');
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Plan setup staff '.$id,'plan-setup'.$id.'@example.invalid',password_hash(HPS_PASSWORD,PASSWORD_DEFAULT),str_repeat(dechex($id),32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'owner',3=>'administrator'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()),JSON_THROW_ON_ERROR)]);
    query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Plan setup client','plan-setup-client@example.invalid',password_hash(HPS_PASSWORD,PASSWORD_DEFAULT),str_repeat('c',32),gmdate('Y-m-d H:i:s')]);
    hps_owner();catalog_migrate(HPS_PASSWORD);provider_catalog_import_prepare(HPS_PASSWORD);provider_settings_migrate(HPS_PASSWORD);$profile=provider_settings_save('',0,'Synthetic imported plan',true,$secret,HPS_PASSWORD);provider_inventory_migrate(HPS_PASSWORD);provider_package_types_prepare(HPS_PASSWORD);service_plans_migrate(HPS_PASSWORD);provider_plan_catalog_prepare(HPS_PASSWORD);hosting_catalog_prepare(HPS_PASSWORD);
    $engines=all('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(HPS_PREFIX),HPS_PREFIX]);
    hps_ok('Actual prepared catalog plan mapping and hosting tables are InnoDB',$engines!==[]&&!array_filter($engines,static fn($r)=>strtolower($r['ENGINE'])!=='innodb')&&recurring_billing_locked(fn()=>hosting_catalog_ready())&&service_plans_ready()&&provider_plan_catalog_state()['ready']);
    $stage='import';$source=hps_refresh($profile);hps_ok('Actual package-types lifecycle retains four synthetic catalog types',$source['state']==='complete'&&$reads===1&&$dns===1);
    $catalog=provider_catalog_import_preview(1,'package.types');$products=[];
    foreach([1,2,3,4] as $n)$products[$n]=provider_catalog_import_create($catalog['hash'],(string)(7000+$n),'PLAN-'.$n,$n===2?'year':'month',HPS_PASSWORD,'package.types');
    hps_deny('Unpriced imported draft cannot become an active service plan',fn()=>hosting_catalog_setup_preview($products[4]));
    foreach([1,2,3] as $n)catalog_save($products[$n],1,array_replace($input,['sku'=>'PLAN-'.$n,'title'=>$n===2?'Owner annual hosting':($n===3?'Owner editable hosting':$input['title']),'term'=>$n===2?'year':'month','amount'=>$n===2?'270.00':'25.00']));
    $local=catalog_save(0,0,array_replace($input,['sku'=>'LOCAL-NO-IMPORT']));hps_deny('An ordinary local product cannot substitute for imported provenance',fn()=>hosting_catalog_setup_preview($local));
    // Existing local-image reference seam, not an HTTP upload. Removal below uses the actual image wrapper.
    $image=hps_image($products[1],900);hps_image($products[3],901);
    $before=hps_effects();$review=hosting_catalog_setup_preview($products[1]);$annual=hosting_catalog_setup_preview($products[2]);
    hps_ok('Owner preview is read-only and exposes the exact imported type and selected commercial terms',hps_effects()===$before&&$review['package']===['type'=>'7001','label'=>'Observed monthly type']&&$review['product']['id']===$products[1]&&$review['product']['amount_cents']===2500&&$review['product']['media_id']===$image['id']&&preg_match('/\A[a-f0-9]{64}\z/D',$review['review_sha256'])===1);
    $stage='authority-input';$before=hps_effects();
    hps_deny('Current owner password is required for setup',fn()=>hps_create(100,$products[1],$review['review_sha256'],password:'wrong'));
    $_GET['support']='synthetic';try{hps_deny('Support context cannot preview setup',fn()=>hosting_catalog_setup_preview($products[1]));hps_deny('Support context cannot create setup',fn()=>hps_create(100,$products[1],$review['review_sha256']));}finally{unset($_GET['support']);}
    hps_owner(3);hps_deny('Fully delegated administrator cannot create hosting plan setup',fn()=>hps_create(100,$products[1],$review['review_sha256']));hps_client();hps_deny('Actual primary client cannot create owner plan setup',fn()=>hps_create(100,$products[1],$review['review_sha256']));hps_owner();
    hps_deny('Forged product review hash cannot create setup',fn()=>hps_create(100,$products[1],str_repeat('f',64)));
    hps_deny('Another imported product cannot reuse this exact review',fn()=>hps_create(100,$products[2],$review['review_sha256']));
    hps_deny('Submitted commercial price cannot override saved imported product',fn()=>hps_create(100,$products[1],$review['review_sha256'],$details+['price'=>1]));
    hps_deny('Submitted provider type cannot override imported package type',fn()=>hps_create(100,$products[1],$review['review_sha256'],c:$configuration+['type'=>'7002']));
    hps_deny('Malformed service details refuse before any writer',fn()=>hps_create(100,$products[1],$review['review_sha256'],array_replace($details,['support'=>[]])));
    hps_deny('Malformed provider cost refuses before any writer',fn()=>hps_create(100,$products[1],$review['review_sha256'],c:array_replace($configuration,['cost'=>['amount'=>'free']])));
    hps_deny('Review duration outside existing mapping bounds refuses',fn()=>hps_create(100,$products[1],$review['review_sha256'],days:31));
    hps_deny('Three setup identities must be distinct',fn()=>hosting_catalog_setup_create(hps_id(100),hps_id(100),hps_id(102),$products[1],$review['review_sha256'],$details,$configuration,7,HPS_PASSWORD));
    db()->beginTransaction();try{hps_logic('Setup refuses an inherited transaction',fn()=>hps_create(100,$products[1],$review['review_sha256']));}finally{db()->rollBack();}
    hps_logic('Extracted plan writer cannot run without its owning guard',fn()=>service_plans_create_locked(hps_id(100),hosting_catalog_setup_terms($review['product'],$details),HPS_PASSWORD));
    hps_logic('Extracted mapping writer cannot run without its owning guard',fn()=>provider_plan_catalog_save_locked(hps_id(102),hps_id(100),1,str_repeat('a',64),null,$profile,1,provider_plan_catalog_config(['reseller_id'=>42,'type'=>'7001','label'=>$configuration['label'],'region'=>$configuration['region'],'limits'=>$configuration['limits'],'cost'=>$configuration['cost']]),7,true,HPS_PASSWORD));
    hps_ok('Authority and malformed-input refusals preserve every row and protected file with no provider work',hps_effects()===$before);
    $stage='create-replay';$forbidden=hps_forbidden_counts();$beforeReads=[$dns,$reads];$productRow=one('SELECT * FROM '.table('catalog_products').' WHERE id=?',[$products[1]]);$importRow=provider_catalog_import_record($products[1]);
    $result=hps_create(100,$products[1],$review['review_sha256']);$plan=service_plans_get(hps_id(100));$mapping=recurring_billing_locked(fn()=>provider_plan_catalog_current(hps_id(100)));
    $expectedTerms=['name'=>'Owner monthly hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>$details['inclusions'],'exclusions'=>$details['exclusions'],'limits'=>['mailboxes'=>5,'websites'=>1],'support'=>$details['support']];
    hps_ok('One owner operation creates active revision-one plan with exact saved monthly commercial terms',$result===['product_id'=>$products[1],'plan_id'=>hps_id(100),'mapping_id'=>hps_id(102)]&&$plan->revision()===1&&$plan->model()->status()==='active'&&$plan->data()['terms']===$expectedTerms);
    hps_ok('Activation keeps truthful owner and exact stable event identity',count($plan->data()['events'])===1&&$plan->data()['events'][0]['id']===hps_id(101)&&$plan->data()['events'][0]['actor']===1&&$plan->data()['events'][0]['command']==='activate');
    hps_ok('Mapping uses exact imported type and separately reviewed provider configuration',$mapping['configuration']['type']==='7001'&&$mapping['configuration']['reseller_id']===42&&$mapping['configuration']['label']===$configuration['label']&&$mapping['configuration']['region']===$configuration['region']&&$mapping['configuration']['limits']===['mailboxes'=>5,'websites'=>1]&&$mapping['configuration']['cost']===$configuration['cost']&&$mapping['plan_revision']===1&&$mapping['plan_sha256']===$plan->fingerprint()&&$mapping['profile_id']===$profile&&$mapping['profile_revision']===1&&$mapping['expires_at']===$mapping['created_at']+7*86400);
    hps_ok('Setup preserves original imported product image and provenance',one('SELECT * FROM '.table('catalog_products').' WHERE id=?',[$products[1]])===$productRow&&provider_catalog_import_record($products[1])===$importRow&&hash_file('sha256',$private.'/media/'.$image['id'].'.php')===$image['sha256']);
    hps_ok('Setup neither publishes invoices agreements nor calls the provider',hps_forbidden_counts()===$forbidden&&[$dns,$reads]===$beforeReads&&catalog_public()===[]);
    $waitUntil=hrtime(true)+3000000000;while(time()<=$mapping['created_at']){if(hrtime(true)>$waitUntil)throw new RuntimeException('Replay clock did not advance.');usleep(100000);}
    $before=hps_effects();$again=hps_create(100,$products[1],$review['review_sha256']);$same=recurring_billing_locked(fn()=>provider_plan_catalog_current(hps_id(100)));
    hps_ok('Exact three-identity retry returns original IDs and no duplicate or renewed expiry',$again===$result&&$same===$mapping&&hps_effects()===$before);
    hps_deny('Retry with changed plan details refuses',fn()=>hps_create(100,$products[1],$review['review_sha256'],array_replace($details,['support'=>'Different support'])));
    hps_deny('Retry with changed account configuration refuses',fn()=>hps_create(100,$products[1],$review['review_sha256'],c:array_replace($configuration,['region'=>'Different region'])));
    hps_deny('Retry cannot extend mapping expiry',fn()=>hps_create(100,$products[1],$review['review_sha256'],days:8));
    foreach(['plan'=>[110,101,102],'activation'=>[100,111,102],'mapping'=>[100,101,112]] as $changed=>$ids)hps_deny('Retry with changed '.$changed.' identity refuses',fn()=>hosting_catalog_setup_create(...[hps_id($ids[0]),hps_id($ids[1]),hps_id($ids[2]),$products[1],$review['review_sha256'],$details,$configuration,7,HPS_PASSWORD]));
    hps_ok('All replay mismatch refusals preserve complete original state',hps_effects()===$before);
    $year=hps_create(200,$products[2],$annual['review_sha256']);$yearPlan=service_plans_get($year['plan_id']);
    hps_ok('Annual saved product creates annual terms without a monthly price substitution',$yearPlan->data()['terms']['cadence']==='annually'&&$yearPlan->data()['terms']['price']===27000&&$yearPlan->data()['terms']['name']==='Owner annual hosting');
    $stage='stale-product-image';$editReview=hosting_catalog_setup_preview($products[3]);$row=one('SELECT * FROM '.table('catalog_products').' WHERE id=?',[$products[3]]);
    catalog_save($products[3],(int)$row['revision'],array_replace($input,['sku'=>'PLAN-3','title'=>'Revised owner hosting','amount'=>'31.25']));$before=hps_effects();hps_deny('Actual product edit invalidates the previously displayed setup review',fn()=>hps_create(300,$products[3],$editReview['review_sha256']));hps_ok('Stale product review writes no partial plan',hps_effects()===$before);
    $editReview=hosting_catalog_setup_preview($products[3]);$record=provider_catalog_import_record($products[3]);provider_catalog_import_image($products[3],$editReview['product']['revision'],(int)$record['media_revision'],true,HPS_PASSWORD);$before=hps_effects();
    hps_deny('Actual local-image removal invalidates the previously displayed setup review',fn()=>hps_create(300,$products[3],$editReview['review_sha256']));hps_ok('Stale image review writes no partial plan',hps_effects()===$before);
    $fresh=hosting_catalog_setup_preview($products[3]);$before=hps_effects();$key='cwp-staff-'.substr(hash('sha256','cwp_test|'.HPS_PREFIX),0,40);
    query('SET @hps_hit=0,@hps_plan=0,@hps_event=0,@hps_mapping=0,@hps_lock=0');
    db()->exec('CREATE TRIGGER hpsci_late_audit BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider_catalog.saved' AND NEW.detail LIKE 'Plan ".hps_id(300)." mapping %' THEN SET @hps_hit=@hps_hit+1; SET @hps_plan=(SELECT COUNT(*) FROM ".table('service_plans')." WHERE id='".hps_id(300)."' AND revision=1); SET @hps_event=(SELECT COUNT(*) FROM ".table('service_plan_events')." WHERE id='".hps_id(301)."'); SET @hps_mapping=(SELECT COUNT(*) FROM ".table('provider_plan_catalog')." WHERE id='".hps_id(302)."'); SET @hps_lock=(COALESCE(IS_USED_LOCK('".$key."'),0)=CONNECTION_ID()); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic late plan setup failure'; END IF; END");
    $failed=false;try{hps_create(300,$products[3],$fresh['review_sha256']);}catch(PDOException $e){if(($e->errorInfo[0]??null)!=='45000'||($e->errorInfo[1]??null)!==1644)throw $e;$failed=true;}finally{db()->exec('DROP TRIGGER hpsci_late_audit');unset($GLOBALS['cwp_settings']);}
    $w=one('SELECT @hps_hit AS hit,@hps_plan AS plan_seen,@hps_event AS event_seen,@hps_mapping AS mapping_seen,@hps_lock AS lock_seen');
    hps_ok('Real late audit failure sees plan activation and mapping under the owning lock',$failed&&!array_filter($w,static fn($v)=>(int)$v!==1));
    hps_ok('Late audit failure rolls back every plan event mapping pointer audit and protected-file change',hps_effects()===$before&&!db()->inTransaction()&&!hps_mutex());
    $retried=hps_create(300,$products[3],$fresh['review_sha256']);hps_ok('Exact retry after actual rollback creates the original three IDs once',$retried['plan_id']===hps_id(300)&&$retried['mapping_id']===hps_id(302)&&service_plans_get(hps_id(300))->data()['terms']['price']===3125);
    $stage='changed-plan-mapping';$plan=service_plans_get(hps_id(100));$replacement=provider_plan_catalog_save(hps_id(150),hps_id(100),$plan->revision(),$plan->fingerprint(),hps_id(102),$profile,1,$mapping['configuration'],7,true,HPS_PASSWORD);$before=hps_effects();
    hps_deny('Replaced current mapping prevents historical setup retry',fn()=>hps_create(100,$products[1],$review['review_sha256']));hps_ok('Replaced mapping refusal preserves both original mapping versions',hps_effects()===$before&&recurring_billing_locked(fn()=>provider_plan_catalog_current(hps_id(100)))['id']===$replacement['id']);
    $yearPlan=service_plans_change(hps_id(200),1,hps_id(203),'retire',[],HPS_PASSWORD);$before=hps_effects();hps_deny('Retired plan cannot be reactivated through setup retry',fn()=>hps_create(200,$products[2],$annual['review_sha256']));hps_ok('Retirement remains exact after rejected setup retry',hps_effects()===$before&&service_plans_get(hps_id(200))->model()->status()==='retired');
    $stage='current-catalog';$oldReview=hosting_catalog_setup_preview($products[3]);$readStatus=503;$failureRead=hps_refresh($profile);$before=hps_effects();
    hps_ok('Actual newest failed catalog read is retained',$failureRead['state']!=='complete');hps_deny('Preview cannot fall back behind a newest failed catalog read',fn()=>hosting_catalog_setup_preview($products[3]));hps_deny('Create cannot reuse a review behind a newest failed catalog read',fn()=>hps_create(400,$products[3],$oldReview['review_sha256']));hps_ok('Latest failure refusal preserves all plan and catalog history',hps_effects()===$before);
    $readStatus=200;$freshRead=hps_refresh($profile,static function()use($products,$oldReview):string{$before=hps_effects();hps_deny('Real pending catalog intent blocks setup preview',fn()=>hosting_catalog_setup_preview($products[3]));hps_deny('Real pending catalog intent blocks setup creation',fn()=>hps_create(400,$products[3],$oldReview['review_sha256']));hps_ok('Pending-read refusal makes no setup or provider effect',hps_effects()===$before);return hps_resolve();});
    $before=hps_effects();hps_ok('Actual fresh catalog read completes after pending admission probe',$freshRead['state']==='complete');hps_deny('New complete observation invalidates an earlier setup hash',fn()=>hps_create(400,$products[3],$oldReview['review_sha256']));hps_ok('Stale catalog review leaves all records unchanged',hps_effects()===$before);
    $current=hosting_catalog_setup_preview($products[3]);$fullTypes=$types;$types=array_values(array_filter($types,static fn($r)=>$r['id']!==7003));$omitted=hps_refresh($profile);$before=hps_effects();
    hps_ok('A real complete catalog can omit a previously imported type',$omitted['state']==='complete');hps_deny('Imported type missing from current complete catalog is unavailable',fn()=>hosting_catalog_setup_preview($products[3]));hps_deny('Missing current type cannot be created using an earlier review',fn()=>hps_create(400,$products[3],$current['review_sha256']));hps_ok('Missing-type refusal makes no mutation or provider call',hps_effects()===$before);
    $types=$fullTypes;$restored=hps_refresh($profile);hps_ok('Actual complete catalog restores the exact saved imported type',$restored['state']==='complete'&&hosting_catalog_setup_preview($products[3])['package']['type']==='7003');
    $stage='current-owner-credentials';$oldReview=hosting_catalog_setup_preview($products[3]);$oldSession=$_SESSION;staff_account_save(1,'Changed setup owner','plan-setup1@example.invalid',HPS_PASSWORD,'');$before=hps_effects();$_SESSION=$oldSession;
    hps_deny('Actual owner auth rotation invalidates the old authenticated session',fn()=>hps_create(400,$products[3],$oldReview['review_sha256']));hps_owner();hps_deny('Current owner must review again after auth rotation',fn()=>hps_create(400,$products[3],$oldReview['review_sha256']));hps_ok('Authority-change refusals leave existing plan history intact',hps_effects()===$before);
    $oldReview=hosting_catalog_setup_preview($products[3]);$secret='SYNTHETIC_REPLACEMENT_IMPORTED_PLAN_KEY';provider_settings_save($profile,1,'Changed synthetic imported plan',true,$secret,HPS_PASSWORD);$before=hps_effects();
    hps_deny('Changed provider profile blocks previous imported setup review',fn()=>hps_create(400,$products[3],$oldReview['review_sha256']));hps_ok('Changed-profile refusal has no setup effect',hps_effects()===$before);
    $replacementRead=hps_refresh($profile);$before=hps_effects();hps_ok('Replacement credentials obtain their own real catalog observation',$replacementRead['state']==='complete');hps_deny('New-key observation cannot silently reassign original import provenance',fn()=>hosting_catalog_setup_preview($products[3]));hps_ok('Original import survives replacement-key refusal without reassociation',hps_effects()===$before&&provider_catalog_import_record($products[1])===$importRow);
    $stage='final';hps_ok('Whole native journey leaves publication subscription and invoice stores empty',hps_forbidden_counts()===$forbidden&&catalog_public()===[]);
    hps_ok('Exactly six explicit synthetic catalog reads occurred and no setup API sent a provider request',$reads===6&&$dns===6);
    hps_ok('Every successful setup retains real plan and mapping history',recurring_billing_locked(function():bool{foreach([100,200,300] as $n){service_plans_validate(service_plans_row(hps_id($n)));if(!provider_plan_catalog_history(hps_id($n)))return false;}return true;}));
}catch(Throwable $e){$failure=['stage'=>$stage,'class'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine()];if($e instanceof PDOException&&preg_match('/\A[A-Z0-9]{5}\z/D',(string)($e->errorInfo[0]??'')))$failure['sqlstate']=$e->errorInfo[0];}
$passed=!isset($failure)&&$checks!==[]&&!array_filter($checks,static fn($c)=>$c['passed']!==true);
$bytes=json_encode(['passed'=>$passed,'scope'=>'Actual isolated Linux InnoDB and protected local storage with explicitly synthetic catalog reads; no live provider, publication, invoice, HTTP upload or browser acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if($reportReady){$fp=fopen($report,'xb');if(!$fp)exit(2);try{if(fwrite($fp,$bytes)!==strlen($bytes))exit(2);}finally{fclose($fp);}}
echo $bytes;exit($passed?0:1);
