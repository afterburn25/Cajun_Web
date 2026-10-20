<?php
/** Disposable Linux SQL billing selection; synthetic catalog read, no provider write or payment. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_HOSTING_BILLING')!=='1'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
if(count($argv)!==1)exit(2);
$port=filter_var(getenv('CWP_TEST_PORT')?:'33306',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);if($port===false)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/catalog_offer_setup.php';
require_once __DIR__.'/../site/app/hosting/catalog_checkout.php';
require_once __DIR__.'/../site/app/providers/catalog_variants.php';
require_once __DIR__.'/../site/app/hosting/catalog_billing.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes;
const HCB_PREFIX='hcbn_';
const HCB_PASSWORD='Synthetic hosting billing selector owner';
$root=dirname(__DIR__);$private=$root.'/test-runtime/hosting-catalog-billing-private';$qa=$root.'/qa';$reportPath=$qa.'/hosting-catalog-billing-native-results.json';
$secret='SYNTHETIC_HOSTING_BILLING_KEY';$checks=[];$stage='preflight';$reportReady=false;$dns=0;$reads=0;$readStatus=200;
$types=[['id'=>7001,'label'=>'Observed shared hosting type']];
$input=['sku'=>'BILLING-MONTH','title'=>'Owner monthly hosting','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount'=>'25.00','setup'=>'0.00','cost'=>'9.87','provider_ref'=>'PRIVATE_BILLING_REFERENCE','summary'=>'Actual monthly offer','features'=>'Owner features','terms'=>'Owner terms','sort_order'=>'100'];
$details=['inclusions'=>['One maintained website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during stated business hours'];
$configuration=['reseller_id'=>42,'label'=>'Owner reviewed account','region'=>'Owner reviewed region','limits'=>['websites'=>1],'cost'=>['amount'=>'9.87','currency'=>'USD','period_months'=>1,'tax'=>'unknown','basis'=>'per-package','reference'=>'PRIVATE_BILLING_COST']];
function hcb_id(int $n):string{return sprintf('%032x',$n);}
function hcb_ok(string $name,bool $passed):void{global $checks;if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate fixture label.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException('Named billing selector assertion failed.');}
function hcb_path(string $path):void{for($p=$path;;$p=$parent){if(is_link($p))throw new RuntimeException('Fixture path has a symbolic link.');$parent=dirname($p);if($parent===$p)break;}}
function hcb_owner(int $id=1):void{$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);if(!$u)throw new LogicException('Fixture owner missing.');$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);}
function hcb_client(int $id=1):void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);if(!$c)throw new LogicException('Fixture client missing.');$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);}
function hcb_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function hcb_lock_key():string{return 'cwp-staff-'.substr(hash('sha256','cwp_test|'.HCB_PREFIX),0,40);}
function hcb_mutex():bool{return (int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[hcb_lock_key()])->fetchColumn()===1;}
function hcb_config(int $port):void{global $private;$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-billing','storage'=>$private,'key'=>str_repeat('synthetic-hosting-billing-',4),'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>HCB_PREFIX]];}
function hcb_rows(string $name):array{if(!preg_match('/\A[a-z][a-z0-9_]+\z/D',$name))throw new LogicException('Unexpected fixture table.');$rows=array_map(static fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM '.table($name)));sort($rows,SORT_STRING);return $rows;}
function hcb_snapshot():array{
    global $private;$tables=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(HCB_PREFIX),HCB_PREFIX]) as $t){$name=substr($t['TABLE_NAME'],strlen(HCB_PREFIX));$tables[$name]=hcb_rows($name);}
    $files=[];if(is_dir($private))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS)) as $file){if($file->isLink()||!$file->isFile())throw new LogicException('Unexpected private entry.');$files[str_replace('\\','/',substr($file->getPathname(),strlen($private)+1))]=hash_file('sha256',$file->getPathname());}ksort($files);return ['tables'=>$tables,'files'=>$files];
}
function hcb_state():string{return hash('sha256',json_encode(hcb_snapshot(),JSON_THROW_ON_ERROR));}
function hcb_effects():array{global $dns,$reads;return [$dns,$reads,hcb_state()];}
function hcb_retained(array $before):bool{$now=hcb_snapshot();foreach($before['tables'] as $name=>$rows){if($name==='settings')continue;if(!array_key_exists($name,$now['tables'])||array_diff($rows,$now['tables'][$name]))return false;}foreach($before['files'] as $path=>$hash)if(($now['files'][$path]??null)!==$hash)return false;return true;}
function hcb_resolve():string{global $dns;if(db()->inTransaction()||hcb_mutex())throw new LogicException('Resolver holds mutation guard.');$dns++;return '8.8.8.8';}
function hcb_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation):InventorySnapshot{global $secret,$reads,$readStatus,$types;$authorize();if(db()->inTransaction()||!hcb_mutex()||$key!==$secret||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unexpected synthetic catalog boundary.');$reads++;return PackageTypes::project(new TransportResponse($readStatus,[],json_encode($types,JSON_THROW_ON_ERROR)),time(),'twenty-i|'.$profile['id'].'|'.$operation);}
function hcb_refresh(string $profile):array{if(db()->inTransaction()||hcb_mutex())throw new LogicException('Cooldown holds mutation guard.');$last=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();if($last>time())throw new RuntimeException('Future refresh timestamp.');$until=hrtime(true)+32000000000;while(time()<$last+30){if(hrtime(true)>$until)throw new RuntimeException('Catalog cooldown exceeded.');usleep(100000);}return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],HCB_PASSWORD,'hcb_resolve','hcb_read',InventoryRead::PACKAGE_TYPES);}
function hcb_policy():array{return ['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>'UTC','alignment'=>'day-of-month','review_ttl_seconds'=>900];}
function hcb_deny(string $name,callable $call):void{$caught=false;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException|TypeError $e){if($e instanceof PDOException)throw $e;$caught=true;}hcb_ok($name,$caught);}
function hcb_publish(int $n,int $product,bool $checkout=true):HostingCatalogPublication{
    global $details,$configuration;$review=hosting_catalog_setup_preview($product);
    $setup=hosting_catalog_setup_create(hcb_id($n),hcb_id($n+1),hcb_id($n+2),$product,$review['review_sha256'],$details,$configuration,7,HCB_PASSWORD);
    $policy=$checkout?hcb_policy():null;$preview=hosting_catalog_preview(hcb_id($n+3),$product,$setup['plan_id'],$policy);
    return hosting_catalog_publish(hcb_id($n+3),$product,$setup['plan_id'],$preview['review_sha256'],HCB_PASSWORD,$policy);
}
function hcb_variant(int $n,int $canonical,string $sku,string $term,string $amount,int $sort,bool $checkout=true):HostingCatalogPublication{
    global $input;$review=provider_catalog_variant_preview($canonical);
    $product=provider_catalog_variant_create(hcb_id($n),$canonical,$review['selected_product']['revision'],$review['review_sha256'],$sku,$term,HCB_PASSWORD);
    catalog_save($product,1,array_replace($input,['sku'=>$sku,'title'=>$sku,'term'=>$term,'amount'=>$amount,'sort_order'=>(string)$sort]));
    return hcb_publish($n+1,$product,$checkout);
}
function hcb_checkout(int $n,array $card):array{
    $review=hosting_checkout_preview(hcb_id($n),$card['publication_id'],$card['publication_sha256'],1,['mode'=>'existing','id'=>1]);
    return [$review,hosting_checkout_confirm(hcb_id($n),$review['review_sha256'],1,true)];
}
function hcb_card(string $id,string $billing):array{
    for($page=1;$page<=3;$page++){$result=hosting_catalog_public($page,$billing);foreach($result['offers'] as $card)if($card['publication_id']===$id)return $card;if(!$result['has_more'])break;}
    throw new RuntimeException('Exact selected publication is absent from its billing page.');
}
try{
    foreach([$private,$qa,$reportPath] as $path)hcb_path($path);if(file_exists($private)||file_exists($reportPath))throw new RuntimeException('Preserve existing billing selector evidence.');
    umask(0077);if(!is_dir($qa)&&!mkdir($qa,0700,true))throw new RuntimeException('Cannot prepare QA directory.');$reportReady=true;hcb_config($port);
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? LIMIT 1',[strlen(HCB_PREFIX),HCB_PREFIX]))throw new RuntimeException('Preserve existing billing selector namespace.');
    hcb_ok('Exact isolated database and fresh billing namespace are selected',query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&config()['db']['prefix']===HCB_PREFIX);
    if(!mkdir($private,0700,true)||!mkdir($private.'/media',0700))throw new RuntimeException('Cannot prepare private fixture directory.');
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath($root.'/site');start_session();
    $stage='canonical-prerequisites';foreach(schema_statements(HCB_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','4');
    foreach([1,2] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Billing staff '.$id,'billing-staff'.$id.'@example.invalid',password_hash(HCB_PASSWORD,PASSWORD_DEFAULT),str_repeat(dechex($id),32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (2,\'administrator\',1,?)',[json_encode(array_keys(staff_permissions()),JSON_THROW_ON_ERROR)]);
    foreach([1,2] as $id){query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Billing client '.$id,'billing-client'.$id.'@example.invalid',password_hash('Synthetic billing client',PASSWORD_DEFAULT),str_repeat($id===1?'c':'d',32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,'Billing client project '.$id,'Synthetic project','',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);}
    hcb_owner();catalog_migrate(HCB_PASSWORD);provider_catalog_import_prepare(HCB_PASSWORD);provider_settings_migrate(HCB_PASSWORD);$profile=provider_settings_save('',0,'Synthetic billing selector',true,$secret,HCB_PASSWORD);provider_inventory_migrate(HCB_PASSWORD);provider_package_types_prepare(HCB_PASSWORD);service_plans_migrate(HCB_PASSWORD);provider_plan_catalog_prepare(HCB_PASSWORD);hosting_catalog_prepare(HCB_PASSWORD);
    payments_migrate(HCB_PASSWORD);attempts_migrate(HCB_PASSWORD);ledger_migrate(HCB_PASSWORD);service_plans_checkout_migrate(HCB_PASSWORD);recurring_billing_migrate(HCB_PASSWORD);recurring_billing_checkout_migrate(HCB_PASSWORD);hosting_checkout_prepare(HCB_PASSWORD);
    $source=hcb_refresh($profile);hcb_ok('Actual package type refresh records one synthetic read',$source['state']==='complete'&&$dns===1&&$reads===1);
    $import=provider_catalog_import_preview(1,'package.types');$canonical=provider_catalog_import_create($import['hash'],'7001','BILLING-MONTH','month',HCB_PASSWORD,'package.types');catalog_save($canonical,1,$input);
    $monthly=hcb_publish(100,$canonical);$monthlyCard=hcb_card($monthly->data()['id'],'all');hcb_client();[$oldReview,$oldCheckout]=hcb_checkout(110,$monthlyCard);hcb_owner();
    hcb_ok('Original monthly purchase has one unpaid invoice before annual offers',$oldCheckout['amount_cents']===2500&&$oldCheckout['invoice_status']==='issued'&&!$oldCheckout['automatic_charge']&&!$oldCheckout['provider_activation']&&hcb_count('studio_invoices')===1);
    $original=hcb_snapshot();$originalInvoice=one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$oldCheckout['invoice_id']]);$originalPublication=$monthly->json();
    provider_catalog_variant_prepare(HCB_PASSWORD);$annuals=[];
    for($i=1;$i<=21;$i++)$annuals[]=hcb_variant(1000+$i*10,$canonical,'BILLING-YEAR-'.sprintf('%02d',$i),'year',number_format((27000+$i*100)/100,2,'.',''),$i);
    hcb_ok('Real catalog publishes21 annual products ahead of one monthly product',hcb_count('catalog_provider_imports')===1&&hcb_count('catalog_provider_variants')===21&&hcb_count('hosting_catalog_publications')===22&&(int)one('SELECT sort_order FROM '.table('catalog_products').' WHERE id=?',[$canonical])['sort_order']===100);
    $stage='filter-before-pagination';$_SESSION=[];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);$before=hcb_effects();
    $publicQuery=hosting_catalog_billing_query(['billing'=>'monthly','hosting_page'=>'1'],'hosting_page');$portalQuery=hosting_catalog_billing_query(['billing'=>'annually','p'=>'2']);
    $all=hosting_catalog_public();$allExplicit=hosting_catalog_public(1,'all');$all2=hosting_catalog_public(2,'all');$months=hosting_catalog_public($publicQuery['page'],$publicQuery['billing']);$months2=hosting_catalog_public(2,'monthly');$years=hosting_catalog_public(1,'annually');$years2=hosting_catalog_public($portalQuery['page'],$portalQuery['billing']);$years3=hosting_catalog_public(3,'annually');
    $annualIds=array_map(static fn($p)=>$p->data()['id'],$annuals);
    hcb_ok('Default all selection preserves original sorted first20 offers',$all===$allExplicit&&count($all['offers'])===20&&$all['has_more']&&!$all['unavailable']&&array_column($all['offers'],'publication_id')===array_slice($annualIds,0,20));
    hcb_ok('Monthly filter applies before pagination past21 preceding annual offers',count($months['offers'])===1&&!$months['has_more']&&!$months['unavailable']&&$months['offers'][0]['publication_id']===$monthly->data()['id']&&$months['offers'][0]['cadence']==='monthly');
    hcb_ok('Annual first page contains exactly20 matching offers and a real continuation',array_column($years['offers'],'publication_id')===array_slice($annualIds,0,20)&&$years['has_more']&&!$years['unavailable']);
    hcb_ok('Annual second page contains only the21st matching offer',array_column($years2['offers'],'publication_id')===[$annualIds[20]]&&!$years2['has_more']&&!$years2['unavailable']);
    hcb_ok('All second page preserves product order across both billing periods',array_column($all2['offers'],'publication_id')===[$annualIds[20],$monthly->data()['id']]&&!$all2['has_more']);
    hcb_ok('Beyond each filtered end stays empty without fallback',$months2===['offers'=>[],'has_more'=>false,'unavailable'=>false]&&$years3===$months2);
    hcb_ok('Public SQL browse writes no row or private file and performs no provider read',hcb_effects()===$before&&$_SESSION===[]);
    $json=json_encode([$all,$months,$years2],JSON_THROW_ON_ERROR);hcb_ok('Period projection exposes no provider key or private cost fields',!str_contains($json,$secret)&&!str_contains($json,'PRIVATE_BILLING_')&&!str_contains($json,'credential')&&!str_contains($json,'key_fingerprint'));
    $stage='invalid-filter-authority';
    foreach(['month','year','Monthly','annually ',' weekly',''] as $invalid){hcb_deny('Public listing rejects invalid period '.json_encode($invalid),fn()=>hosting_catalog_public(1,$invalid));hcb_deny('Query parser rejects invalid period '.json_encode($invalid),fn()=>hosting_catalog_billing_query(['billing'=>$invalid]));}
    hcb_deny('Array billing query refuses before SQL or authority',fn()=>hosting_catalog_billing_query(['billing'=>['monthly']]));
    hcb_deny('Array direct billing argument refuses before SQL or authority',fn()=>hosting_catalog_public(1,['monthly']));
    foreach([0,-1,100001] as $invalid)hcb_deny('Public listing refuses out-of-bounds page '.$invalid,fn()=>hosting_catalog_public($invalid,'monthly'));
    hcb_deny('Anonymous monthly filter confers no checkout authority',fn()=>hosting_checkout_preview(hcb_id(2010),$monthlyCard['publication_id'],$monthlyCard['publication_sha256'],1,['mode'=>'existing','id'=>1]));
    hcb_ok('Malformed filters and unauthenticated checkout preserve every original effect',hcb_effects()===$before&&hcb_count('studio_invoices')===1);
    $stage='exact-selected-checkout';hcb_client();$monthlySelected=hosting_catalog_public(1,'monthly')['offers'][0];$annualSelected=hosting_catalog_public(2,'annually')['offers'][0];
    hcb_ok('Filtered cards retain exact independently published amounts and IDs',$monthlySelected['publication_id']===$monthly->data()['id']&&$monthlySelected['publication_sha256']===$monthly->fingerprint()&&$monthlySelected['amount_cents']===2500&&$annualSelected['publication_id']===$annualIds[20]&&$annualSelected['publication_sha256']===$annuals[20]->fingerprint()&&$annualSelected['amount_cents']===29100);
    $before=hcb_effects();hcb_deny('Monthly ID with annual hash cannot create checkout review',fn()=>hosting_checkout_preview(hcb_id(2020),$monthlySelected['publication_id'],$annualSelected['publication_sha256'],1,['mode'=>'existing','id'=>1]));
    hcb_deny('Annual ID with forged hash cannot create checkout review',fn()=>hosting_checkout_preview(hcb_id(2021),$annualSelected['publication_id'],str_repeat('0',64),1,['mode'=>'existing','id'=>1]));
    hcb_client(2);hcb_deny('Another signed-in client cannot impersonate selected checkout owner',fn()=>hosting_checkout_preview(hcb_id(2022),$annualSelected['publication_id'],$annualSelected['publication_sha256'],1,['mode'=>'existing','id'=>1]));hcb_client();
    hcb_ok('Selection tampering and client mismatch create no review or invoice',hcb_effects()===$before);
    [$yearReview,$yearResult]=hcb_checkout(2030,$annualSelected);[$monthReview,$monthResult]=hcb_checkout(2031,$monthlySelected);
    foreach(['annual'=>[$yearReview,$yearResult,$annualSelected,2030,'annually',29100],'monthly'=>[$monthReview,$monthResult,$monthlySelected,2031,'monthly',2500]] as $name=>[$review,$result,$card,$id,$cadence,$amount]){
        $saved=one('SELECT * FROM '.table('hosting_checkout_reviews').' WHERE id=?',[hcb_id($id)]);$agreement=service_plans_validate(service_plans_row($result['agreement_id'],true),true);$invoiceMap=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE invoice_id=?',[$result['invoice_id']]);$period=json_decode($invoiceMap['snapshot_json'],true,8,JSON_THROW_ON_ERROR)['period'];
        hcb_ok('Selected '.$name.' consent and invoice retain exact offer hash price and cadence',$saved['publication_id']===$card['publication_id']&&$saved['publication_sha256']===$card['publication_sha256']&&$review['billing']['amount_cents']===$amount&&$review['billing']['cadence']===$cadence&&$result['amount_cents']===$amount&&$result['invoice_status']==='issued'&&!$result['automatic_charge']&&!$result['provider_activation']&&$agreement->model()->agreedPrice===$amount&&$agreement->model()->plan->cadence===$cadence&&$period['start']===$review['billing']['first_period']['start']&&$period['end']===$review['billing']['first_period']['end']);
        $before=hcb_effects();$again=hosting_checkout_confirm(hcb_id($id),$review['review_sha256'],1,true);hcb_ok('Selected '.$name.' exact confirmation replay preserves original invoice',$again['replayed']&&$again['invoice_id']===$result['invoice_id']&&hcb_effects()===$before);
    }
    hcb_ok('Independent selected periods produce distinct invoices without touching old purchase',$yearResult['invoice_id']!==$monthResult['invoice_id']&&hcb_count('studio_invoices')===3&&one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$oldCheckout['invoice_id']])===$originalInvoice);
    $stage='exact-selected-request';hcb_owner();$requestMonth=hcb_variant(3000,$canonical,'BILLING-REQUEST-MONTH','month','31.00',200,false);$requestYear=hcb_variant(3010,$canonical,'BILLING-REQUEST-YEAR','year','319.00',201,false);hcb_client();
    $monthRequestCard=hcb_card($requestMonth->data()['id'],'monthly');$yearRequestCard=hcb_card($requestYear->data()['id'],'annually');$before=hcb_effects();
    hcb_deny('Monthly request ID cannot use annual selected hash',fn()=>hosting_catalog_request(hcb_id(3100),$monthRequestCard['publication_id'],$yearRequestCard['publication_sha256'],1));
    hcb_deny('Checkout publication cannot bypass consent through request endpoint',fn()=>hosting_catalog_request(hcb_id(3101),$monthlySelected['publication_id'],$monthlySelected['publication_sha256'],1));
    hcb_ok('Request selection tampering preserves business rows and provider counters',hcb_effects()===$before);
    foreach(['monthly'=>[$monthRequestCard,3110,3100],'annually'=>[$yearRequestCard,3111,31900]] as $cadence=>[$card,$id,$amount]){
        $request=hosting_catalog_request(hcb_id($id),$card['publication_id'],$card['publication_sha256'],1);$data=$request->data();$history=hosting_catalog_client_requests(1);$rows=array_values(array_filter($history['requests'],static fn($row)=>$row['id']===hcb_id($id)));
        hcb_ok('Filtered '.$cadence.' request retains exact published identity amount and cadence',$data['publication_id']===$card['publication_id']&&$data['publication_sha256']===$card['publication_sha256']&&count($rows)===1&&$rows[0]['status']==='pending'&&$rows[0]['offer']['publication_id']===$card['publication_id']&&$rows[0]['offer']['publication_sha256']===$card['publication_sha256']&&$rows[0]['offer']['amount_cents']===$amount&&$rows[0]['offer']['cadence']===$cadence);
        $before=hcb_effects();hcb_ok('Filtered '.$cadence.' request retry is inert',hosting_catalog_request(hcb_id($id),$card['publication_id'],$card['publication_sha256'],1)->json()===$request->json()&&hcb_effects()===$before);
    }
    $before=hcb_effects();hosting_catalog_public(1,'all');hosting_catalog_public(1,'monthly');hosting_catalog_public(1,'annually');hosting_catalog_public(2,'annually');hosting_catalog_client_requests(1);
    hcb_ok('Browsing after requests and invoices remains entirely read-only',hcb_effects()===$before&&hcb_count('hosting_catalog_requests')===2&&hcb_count('studio_invoices')===3);
    $old=hosting_checkout_confirm(hcb_id(110),$oldReview['review_sha256'],1,true);
    hcb_ok('Original monthly invoice agreement publication and private bytes survive all selections',$old['replayed']&&$old['invoice_id']===$oldCheckout['invoice_id']&&hcb_retained($original)&&$monthly->json()===$originalPublication&&one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$oldCheckout['invoice_id']])===$originalInvoice);
    hcb_ok('Entire fixture performs one synthetic catalog read and zero payment or provider dispatch',$dns===1&&$reads===1&&hcb_count('invoice_payments')===0&&hcb_count('payment_requests')===0&&hcb_count('payment_attempts')===0&&!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY TABLE_NAME=?',[HCB_PREFIX.'provider_operations']));
    $stage='complete';$report=['passed'=>true,'checks'=>$checks,'scope'=>'Actual isolated SQL monthly/annual publication filtering before pagination and exact client checkout/request retention; one synthetic catalog read, no browser, provider write or payment','runtime'=>['php'=>PHP_VERSION,'database'=>(string)query('SELECT VERSION()')->fetchColumn()]];
}catch(Throwable $e){$report=['passed'=>false,'checks'=>$checks,'scope'=>'Native billing selector fixture stopped; only completed named outcomes apply','stage'=>$stage,'error'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null]];}
$bytes=json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";if($reportReady&&is_dir($qa)&&!is_link($qa))file_put_contents($reportPath,$bytes);echo $bytes;if(!$report['passed'])exit(1);
