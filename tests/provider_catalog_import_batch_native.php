<?php
/** Real disposable InnoDB batch imports; synthetic read transport only, no provider network. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_CATALOG_BATCH')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$port=filter_var(getenv('CWP_TEST_PORT')?:'33306',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);if($port===false)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/app/providers/catalog_import.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventoryProjector,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes;
$root=dirname(__DIR__);$private=$root.'/test-runtime/provider-catalog-batch-private';$qa=$root.'/qa';$report=$qa.'/provider-catalog-import-batch-native-results.json';
$checks=[];$reportReady=false;$password='Synthetic batch catalog owner';$secret='SYNTHETIC_BATCH_GENERAL_KEY';$calls=0;$status=200;
$types=array_map(static fn(int $id)=>['id'=>$id,'label'=>'Batch hosting '.$id,'image'=>'https://private.invalid/not-imported','price'=>999],range(1,125));
function pcbn_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pcbn_deny(string $name,callable $call):void{try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;pcbn_ok($name,true);return;}pcbn_ok($name,false);}
function pcbn_path(string $path):void{for($p=$path;;$p=$parent){if(is_link($p))throw new RuntimeException('Fixture paths must have no symbolic links.');$parent=dirname($p);if($parent===$p)break;}}
function pcbn_actor(int $id=1):void{$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_settings']);}
function pcbn_state():string{
    global $private;$rows=[];foreach(['catalog_products','catalog_revisions','catalog_provider_imports','media','audit'] as $name)$rows[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');
    $rows['count']=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_import_count']);$files=[];
    foreach(glob($private.'/media/*')?:[] as $file){if(is_link($file)||!is_file($file))throw new RuntimeException('Unexpected fixture media entry.');$files[basename($file)]=hash_file('sha256',$file);}ksort($files);
    return hash('sha256',json_encode([$rows,$files],JSON_THROW_ON_ERROR));
}
function pcbn_read(array $profile,string $key,string $address,callable $authorize,string $operation):InventorySnapshot{
    global $calls,$secret,$status,$types;$authorize();if($key!==$secret||$address!=='8.8.8.8'||db()->inTransaction())throw new LogicException('Synthetic transport context is invalid.');$calls++;
    $scope='twenty-i|'.$profile['id'].'|'.$operation;
    if($operation===InventoryRead::PACKAGES_LIST){$body=json_encode([['id'=>41,'typeRef'=>1,'packageTypeName'=>'Original inventory hosting','name'=>'synthetic.example.invalid','names'=>['synthetic.example.invalid'],'enabled'=>true,'created'=>'2024-01-02T03:04:05Z','packageLabels'=>[],'stackUsers'=>[],'productSpec'=>null]],JSON_THROW_ON_ERROR);return InventoryProjector::packagesList(new TransportResponse(200,[],$body),time(),$scope);}
    if($operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unexpected fixture read.');
    return PackageTypes::project(new TransportResponse($status,[],json_encode($types,JSON_THROW_ON_ERROR)),time(),$scope);
}
function pcbn_wait(string $profile):void{$last=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();$wait=max(0,$last+31-time());if($wait>31)throw new RuntimeException('Fixture clock is unexpectedly ahead.');if($wait)sleep($wait);}
function pcbn_refresh(string $profile,int $revision=1,?callable $resolver=null):string{global $password;pcbn_wait($profile);return provider_inventory_refresh($profile,$revision,$password,$resolver??static fn()=>'8.8.8.8','pcbn_read',InventoryRead::PACKAGE_TYPES);}
function pcbn_batch(string $hash,int $offset=0):array{global $password;return provider_catalog_import_batch($hash,'month','BATCH',$password,'package.types',$offset);}
try{
    pcbn_path($private);pcbn_path($qa);pcbn_path($report);
    if(file_exists($private)||is_link($private)||file_exists($report)||is_link($report))throw new RuntimeException('Preserve the previous batch fixture evidence.');
    umask(0077);if(!is_dir($qa)&&!mkdir($qa,0700,true))throw new RuntimeException('Cannot prepare fixture report directory.');$reportReady=true;
    $GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/provider-catalog-batch','storage'=>$private,'key'=>str_repeat('synthetic-catalog-batch-',4),'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pcbci_']];
    // The service may map this selected loopback port to internal3306; no @@port equality.
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,6)=BINARY ? LIMIT 1',['pcbci_']))throw new RuntimeException('Preserve the existing pcbci_ namespace.');
    pcbn_ok('Native batch begins in exact empty disposable database namespace',query('SELECT DATABASE()')->fetchColumn()==='cwp_test');
    if(!mkdir($private,0700,true)||!mkdir($private.'/media',0700))throw new RuntimeException('Cannot prepare private batch fixture storage.');
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath($root.'/site');start_session();
    foreach(schema_statements('pcbci_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','4');
    foreach([1,2] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Batch staff '.$id,'batch'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat($id===1?'a':'b',32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[2,json_encode(array_keys(staff_permissions()))]);pcbn_actor();catalog_migrate($password);provider_catalog_import_prepare($password);provider_settings_migrate($password);$profile=provider_settings_save('',0,'Synthetic batch fixture',true,$secret,$password);provider_inventory_migrate($password);provider_package_types_prepare($password);
    $engines=all('SELECT TABLE_NAME,ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,6)=BINARY ?',['pcbci_']);
    pcbn_ok('Batch preparation uses real InnoDB tables and current owner markers',$engines!==[]&&!array_filter($engines,static fn($r)=>strtolower($r['ENGINE'])!=='innodb')&&provider_catalog_import_ready()&&provider_package_types_ready());
    pcbn_ok('Real existing-package wrapper retains one synthetic inventory type',provider_inventory_refresh($profile,1,$password,static fn()=>'8.8.8.8','pcbn_read')==='complete'&&$calls===1);
    $legacy=provider_catalog_import_preview();$product=provider_catalog_import_create($legacy['hash'],'1','OWNER-EXISTING','year',$password);
    $edit=['sku'=>'OWNER-EXISTING','title'=>'Owner custom title','family'=>'hosting','status'=>'draft','term'=>'year','currency'=>'USD','amount'=>'18.25','setup'=>'2.50','cost'=>'1.00','provider_ref'=>'private owner note','summary'=>'Owner description','features'=>'Owner features','terms'=>'Owner written terms','sort_order'=>'7'];catalog_save($product,1,$edit);
    // Local bytes/reference fixture only; genuine multipart upload remains in the unchanged HTTP suite.
    $media=str_repeat('c',32);$pixels=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZWQAAAAASUVORK5CYII=',true);write_private_file($private.'/media/'.$media.'.php',CWP_GUARD.$pixels);
    query('INSERT INTO '.table('media').' (id,filename,mime,bytes,width,height,original_name,created_at) VALUES (?,?,?,?,?,?,?,?)',[$media,$media.'.php','image/png',strlen($pixels),1,1,'synthetic-native.png',gmdate('Y-m-d H:i:s')]);query('UPDATE '.table('catalog_provider_imports').' SET media_id=?,media_revision=1 WHERE product_id=?',[$media,$product]);
    $originalProduct=one('SELECT * FROM '.table('catalog_products').' WHERE id=?',[$product]);$originalImport=provider_catalog_import_record($product);$originalImage=hash_file('sha256',$private.'/media/'.$media.'.php');$inventory=all('SELECT * FROM '.table('provider_observation_packages').' ORDER BY id');
    pcbn_ok('Real direct-catalog wrapper retains all125 synthetic distinct types',pcbn_refresh($profile)==='complete'&&$calls===2);
    $preview=provider_catalog_import_preview(1,'package.types');$last=provider_catalog_import_preview(7,'package.types');$hash=$preview['hash'];
    pcbn_ok('Preview retains twenty-row pages while batch sees all125 types',$preview['total_types']===125&&count($preview['types'])===20&&count($last['types'])===5&&$last['hash']===$hash);
    $shared=array_values(array_filter($preview['types'],static fn($t)=>$t['type_ref']==='1'))[0];pcbn_ok('Cross-source original provenance is retained before batch',$shared['origin_operation']==='packages.list'&&$shared['origin_name']==='Original inventory hosting'&&$shared['product_id']===$product);
    $before=pcbn_state();$beforeCalls=$calls;
    pcbn_deny('Batch requires current owner password',fn()=>provider_catalog_import_batch($hash,'month','BATCH','wrong'));
    $_GET['support']='synthetic';try{pcbn_deny('Batch rejects support context',fn()=>pcbn_batch($hash));}finally{unset($_GET['support']);}
    pcbn_actor(2);pcbn_deny('Batch rejects ordinary fully privileged administrator',fn()=>pcbn_batch($hash));pcbn_actor();
    pcbn_deny('Batch refuses stale preview hash',fn()=>pcbn_batch(str_repeat('f',64)));
    pcbn_ok('Authority and stale-review refusals leave complete business media audit state exact',pcbn_state()===$before&&$calls===$beforeCalls);
    $collision=catalog_save(0,0,array_replace($edit,['sku'=>'BATCH-99','title'=>'Separate existing product']));$before=pcbn_state();
    pcbn_deny('Deterministic SKU collision beyond first fifty prevents every insertion',fn()=>pcbn_batch($hash));pcbn_ok('SKU collision is atomic and makes no provider call',pcbn_state()===$before&&$calls===$beforeCalls);
    catalog_save($collision,1,array_replace($edit,['sku'=>'UNRELATED-LOCAL','title'=>'Separate existing product']));
    $before=pcbn_state();db()->exec('SET @pcbci_audit_hits=0');db()->exec('CREATE TRIGGER pcbci_batch_audit_failure BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='catalog.provider_import.create' THEN SET @pcbci_audit_hits=COALESCE(@pcbci_audit_hits,0)+1; IF @pcbci_audit_hits=3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic batch audit failure'; END IF; END IF; END");$failed=false;
    try{pcbn_batch($hash);}catch(PDOException $e){$failed=$e->getCode()==='45000';}finally{db()->exec('DROP TRIGGER pcbci_batch_audit_failure');}
    pcbn_ok('Actual audit trigger observes three inserted draft attempts before failure',$failed&&(int)query('SELECT @pcbci_audit_hits')->fetchColumn()===3);
    pcbn_ok('Actual InnoDB rollback restores entire batch products revisions provenance counts media and audits',pcbn_state()===$before&&!db()->inTransaction()&&provider_catalog_import_ready());
    $first=pcbn_batch($hash);pcbn_ok('First fifty-index window imports49 missing types and preserves one existing type',$first['created_count']===49&&count($first['created_ids'])===49&&$first['existing_count']===1&&$first['remaining_count']===75&&$first['next_offset']===50);
    $before=pcbn_state();$repeat=pcbn_batch($hash);pcbn_ok('Exact incomplete form retry imports no unseen next batch',$repeat['created_count']===0&&$repeat['existing_count']===50&&$repeat['remaining_count']===75&&$repeat['next_offset']===50&&pcbn_state()===$before);
    $second=pcbn_batch($hash,$first['next_offset']);$third=pcbn_batch($hash,$second['next_offset']);pcbn_ok('Explicit continuation imports50 then25 and honestly completes entire saved document',$second['created_count']===50&&$second['remaining_count']===25&&$third['created_count']===25&&$third['remaining_count']===0&&$third['next_offset']===null);
    $before=pcbn_state();$repeat=pcbn_batch($hash);pcbn_ok('Completed repeat creates no duplicate or audit history',$repeat['created_count']===0&&$repeat['existing_count']===125&&$repeat['remaining_count']===0&&pcbn_state()===$before);
    pcbn_ok('All batches preserve existing custom prices title terms image and original inventory provenance',one('SELECT * FROM '.table('catalog_products').' WHERE id=?',[$product])===$originalProduct&&provider_catalog_import_record($product)===$originalImport&&hash_file('sha256',$private.'/media/'.$media.'.php')===$originalImage);
    $imports=all('SELECT * FROM '.table('catalog_provider_imports').' ORDER BY id');$new=all('SELECT p.* FROM '.table('catalog_products').' p JOIN '.table('catalog_provider_imports').' i ON i.product_id=p.id WHERE p.id<>?',[$product]);
    pcbn_ok('All125 identities have one product each and124 exact direct-catalog sources',count($imports)===125&&count(array_unique(array_column($imports,'type_ref')))===125&&count(array_filter($imports,static fn($r)=>$r['refresh_id']===$preview['refresh_id']))===124);
    pcbn_ok('New products have exact deterministic SKU and blank unpriced draft fields',count($new)===124&&!array_filter($new,static fn($r)=>$r['family']!=='hosting'||$r['status']!=='draft'||$r['term']!=='month'||$r['amount_cents']!==null||$r['cost_cents']!==null||$r['summary']!==''||$r['features']!==''||$r['terms']!==''));
    foreach($imports as $r)if((int)$r['product_id']!==$product&&one('SELECT sku FROM '.table('catalog_products').' WHERE id=?',[$r['product_id']])['sku']!=='BATCH-'.$r['type_ref'])throw new RuntimeException('Deterministic SKU differs');
    pcbn_ok('New deterministic codes match exact provider type IDs',true);
    pcbn_ok('Import path performs zero provider calls and leaves installed packages invoices and publication unchanged',$calls===$beforeCalls&&all('SELECT * FROM '.table('provider_observation_packages').' ORDER BY id')===$inventory&&(int)query('SELECT COUNT(*) FROM '.table('studio_invoices'))->fetchColumn()===0&&catalog_public()===[]);
    $status=503;pcbn_ok('A newer genuine failed catalog read is retained',pcbn_refresh($profile)==='unavailable');$before=pcbn_state();$beforeCalls=$calls;
    pcbn_deny('Newest failed source blocks batch instead of falling back',fn()=>pcbn_batch($hash));pcbn_ok('Failed-newest refusal preserves all imports and media',pcbn_state()===$before&&$calls===$beforeCalls);
    $status=200;$pending=false;
    $result=pcbn_refresh($profile,1,function()use(&$pending,$hash):string{pcbn_deny('Newest pending source blocks batch before synthetic transport',fn()=>pcbn_batch($hash));$pending=true;return '8.8.8.8';});
    pcbn_ok('Pending intent then completes one actual read lifecycle',$pending&&$result==='complete'&&$calls===$beforeCalls+1);
    $current=provider_catalog_import_preview(1,'package.types');$before=pcbn_state();pcbn_deny('A newer complete observation invalidates captured batch hash',fn()=>pcbn_batch($hash));pcbn_ok('Old-review refusal does not touch completed imports',pcbn_state()===$before);
    $again=pcbn_batch($current['hash']);pcbn_ok('Fresh review of same types preserves completed products',$again['created_count']===0&&$again['existing_count']===125&&pcbn_state()===$before);
    $secret='SYNTHETIC_BATCH_REPLACEMENT_KEY';provider_settings_save($profile,1,'Synthetic batch fixture changed',true,$secret,$password);pcbn_ok('Replacement credentials record their own fresh catalog',pcbn_refresh($profile,2)==='complete');$changed=provider_catalog_import_preview(1,'package.types');$before=pcbn_state();$beforeCalls=$calls;
    pcbn_deny('Earlier credential originals are not claimed as current imports',fn()=>pcbn_batch($changed['hash']));pcbn_ok('Credential refusal preserves original source prices and images without another request',pcbn_state()===$before&&$calls===$beforeCalls&&provider_catalog_import_record($product)===$originalImport&&hash_file('sha256',$private.'/media/'.$media.'.php')===$originalImage);
    pcbn_ok('Fixture performs exactly five explicitly requested synthetic reads',$calls===5);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected native batch failure','passed'=>false];$failure=['class'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine()];}
$passed=!isset($failure)&&$checks!==[]&&!array_filter($checks,static fn($c)=>$c['passed']!==true);
$bytes=json_encode(['passed'=>$passed,'scope'=>'Actual disposable Linux InnoDB and protected local media with synthetic transport; no provider network, genuine image upload, HTTP/browser or publication acceptance.','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
if($reportReady){$fp=fopen($report,'xb');if(!$fp)throw new RuntimeException('Refusing report replacement.');try{if(fwrite($fp,$bytes)!==strlen($bytes))throw new RuntimeException('Incomplete native report.');}finally{fclose($fp);}}
echo $bytes;exit($passed?0:1);
