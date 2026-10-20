<?php
/** Real isolated checkout/order history and read-only purchase lists; no provider writes or payments. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOSTING_PURCHASES')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1'||count($argv)!==1)exit(2);
$port=filter_var(getenv('CWP_TEST_PORT')?:'33306',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);if($port===false)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/hosting/catalog_offers.php';require_once __DIR__.'/../site/app/hosting/catalog_checkout.php';
require_once __DIR__.'/../site/app/hosting/checkout_purchases.php';require_once __DIR__.'/../site/app/hosting/order_views.php';
require_once __DIR__.'/../site/app/clients/accounts.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes;
const HCP_PREFIX='hcpci_';
const HCP_PASSWORD='Synthetic hosting purchases owner';
$root=dirname(__DIR__);$private=$root.'/test-runtime/hosting-checkout-purchases-private';$qa=$root.'/qa';$report=$qa.'/hosting-checkout-purchases-native-results.json';
$secret='SYNTHETIC_PURCHASE_INBOX_PROVIDER_KEY';$checks=[];$stage='preflight';$reportReady=false;$dns=0;$reads=0;
function hcp_id(int $n):string{return sprintf('%032x',$n);}
function hcp_ok(string $name,bool $passed):void{global $checks;if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate native label.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException('Named purchase assertion failed.');}
function hcp_deny(string $name,callable $call):void{$caught=false;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$caught=true;}hcp_ok($name,$caught);}
function hcp_path(string $path):void{for($p=$path;;$p=$parent){if(is_link($p))throw new RuntimeException('Fixture path contains a link.');$parent=dirname($p);if($parent===$p)break;}}
function hcp_owner(int $id=1):void{$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);if(!$u)throw new LogicException('Fixture staff missing.');$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);}
function hcp_client(int $id=1):void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);if(!$c)throw new LogicException('Fixture client missing.');$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);}
function hcp_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|'.HCP_PREFIX),0,40);return (int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
/** Exact business rows and protected files. No login/audit exclusions are needed for these local reads. */
function hcp_state():string{
    global $private;$data=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(HCP_PREFIX),HCP_PREFIX]) as $t){$name=$t['TABLE_NAME'];if(!preg_match('/\Ahcpci_[a-z0-9_]+\z/D',$name))throw new LogicException('Unknown fixture table.');$rows=array_map(static fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$name.'`'));sort($rows,SORT_STRING);$data[$name]=$rows;}
    $files=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS)) as $f){if($f->isLink()||!$f->isFile())throw new LogicException('Unknown private entry.');$files[str_replace('\\','/',substr($f->getPathname(),strlen($private)+1))]=hash_file('sha256',$f->getPathname());}ksort($files);
    return hash('sha256',json_encode([$data,$files],JSON_THROW_ON_ERROR));
}
function hcp_effects():array{global $dns,$reads;return [$dns,$reads,hcp_state()];}
function hcp_resolve():string{global $dns;if(db()->inTransaction()||hcp_mutex())throw new LogicException('Resolver holds mutation guard.');$dns++;return '8.8.8.8';}
function hcp_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation):InventorySnapshot{
    global $secret,$reads;$authorize();if(db()->inTransaction()||!hcp_mutex()||$key!==$secret||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unexpected synthetic boundary.');$reads++;
    return PackageTypes::project(new TransportResponse(200,[],'[{"id":7001,"label":"Synthetic purchase hosting"}]'),time(),'twenty-i|'.$profile['id'].'|'.$operation);
}
function hcp_keys(array $value,array $expected):bool{$keys=array_keys($value);sort($keys);sort($expected);return $keys===$expected;}
function hcp_rows(array $page):array{if(!hcp_keys($page,['items','page','has_more','ready'])||!is_array($page['items'])||!array_is_list($page['items'])||!is_int($page['page'])||!is_bool($page['has_more'])||!is_bool($page['ready']))throw new LogicException('Unexpected purchase page shape.');return array_column($page['items'],null,'request_id');}
function hcp_safe(array $row,bool $owner):bool{
    $keys=['request_id','created_epoch','offer_title','invoice','project','agreement','order'];if($owner)$keys[]='client';
    return hcp_keys($row,$keys)&&hcp_keys($row['invoice'],['id','reference','status','amount_cents','currency'])&&hcp_keys($row['project'],['id','title'])&&hcp_keys($row['agreement'],['id','state'])&&($row['order']===null||hcp_keys($row['order'],['id','phase','domain_name']))&&(!$owner||hcp_keys($row['client'],['id','name']));
}
function hcp_buy(int $id,HostingCatalogPublication $pub,int $client,array $project):array{hcp_client($client);$review=hosting_checkout_preview(hcp_id($id),$pub->data()['id'],$pub->fingerprint(),$client,$project);return hosting_checkout_confirm(hcp_id($id),$review['review_sha256'],$client,true);}
/** Restore only byte-exact rows saved immediately before a deliberate loss probe. */
function hcp_restore(string $name,array $row):void{
    if(!in_array($name,['service_orders','service_order_events','audit','hosting_checkout_results','settings'],true))throw new LogicException('Unknown restoration table.');
    foreach(array_keys($row) as $field)if(!preg_match('/\A[a-z_][a-z0-9_]*\z/D',$field))throw new LogicException('Unknown restoration field.');
    query('INSERT INTO '.table($name).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
}
function hcp_loss(string $label,string $table,string $field,mixed $id):void{
    if(!in_array($table,['service_order_events','audit','hosting_checkout_results','settings'],true)||!in_array($field,['id','name'],true))throw new LogicException('Unknown loss probe.');
    hcp_owner();$before=hcp_effects();$row=one('SELECT * FROM '.table($table).' WHERE '.$field.'=?',[$id]);if(!$row)throw new LogicException('Original loss witness absent.');
    if(query('DELETE FROM '.table($table).' WHERE '.$field.'=?',[$id])->rowCount()!==1)throw new LogicException('Loss probe did not remove one row.');
    try{$damaged=hcp_effects();hcp_deny($label.' refuses owner inbox',fn()=>hosting_purchases_owner_page());hcp_client();hcp_deny($label.' refuses client inbox',fn()=>hosting_purchases_client_page(1));hcp_ok($label.' is never repaired by a read',hcp_effects()===$damaged);}finally{hcp_restore($table,$row);unset($GLOBALS['cwp_settings']);}
    hcp_owner();hcp_ok($label.' exact original restoration preserves every row and file',hcp_effects()===$before);
}
try{
    foreach([$private,$qa,$report] as $path)hcp_path($path);
    if(file_exists($private)||is_link($private)||file_exists($report)||is_link($report))throw new RuntimeException('Preserve existing purchase fixture evidence.');
    umask(0077);if(!is_dir($qa)&&!mkdir($qa,0700,true))throw new RuntimeException('Cannot prepare QA path.');$reportReady=true;
    $GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-purchases','storage'=>$private,'key'=>str_repeat('synthetic-purchase-inbox-',4),'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>HCP_PREFIX]];
    // The DSN uses the selected host-mapped port; the server may report internal port 3306.
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? LIMIT 1',[strlen(HCP_PREFIX),HCP_PREFIX]))throw new RuntimeException('Preserve existing purchase namespace.');
    hcp_ok('Exact disposable database and previously unused purchase namespace selected',query('SELECT DATABASE()')->fetchColumn()==='cwp_test');
    if(!mkdir($private,0700,true))throw new RuntimeException('Cannot create protected fixture storage.');
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath($root.'/site');start_session();
    $stage='identity';foreach(schema_statements(HCP_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','4');
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Purchase staff '.$id,'purchase-staff'.$id.'@example.invalid',password_hash(HCP_PASSWORD,PASSWORD_DEFAULT),str_repeat(dechex($id),32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'owner',3=>'administrator'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()),JSON_THROW_ON_ERROR)]);
    foreach([1,2,3] as $id){query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Purchase client '.$id,'purchase-client'.$id.'@example.invalid',password_hash('Synthetic purchase client',PASSWORD_DEFAULT),str_repeat(dechex($id+9),32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,'Existing purchase project '.$id,'Synthetic project','',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);}
    $stage='catalog';hcp_owner();catalog_migrate(HCP_PASSWORD);provider_catalog_import_prepare(HCP_PASSWORD);provider_settings_migrate(HCP_PASSWORD);
    $profile=provider_settings_save('',0,'Synthetic purchase account',true,$secret,HCP_PASSWORD);provider_inventory_migrate(HCP_PASSWORD);provider_package_types_prepare(HCP_PASSWORD);
    hcp_ok('Real saved package-type observation uses one closed synthetic read',provider_inventory_refresh($profile,1,HCP_PASSWORD,'hcp_resolve','hcp_read',InventoryRead::PACKAGE_TYPES)==='complete'&&$dns===1&&$reads===1);
    $preview=provider_catalog_import_preview(1,'package.types');$product=provider_catalog_import_create($preview['hash'],'7001','PURCHASE-HOSTING','month',HCP_PASSWORD,'package.types');
    $input=['sku'=>'PURCHASE-HOSTING','title'=>'Reviewed purchase hosting','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount'=>'25.00','setup'=>'0.00','cost'=>'9.87','provider_ref'=>'PRIVATE_PROVIDER_REFERENCE','summary'=>'One reviewed hosting service.','features'=>'PRIVATE_DRAFT_FEATURES','terms'=>'PRIVATE_DRAFT_TERMS','sort_order'=>'0'];catalog_save($product,1,$input);
    service_plans_migrate(HCP_PASSWORD);$terms=['name'=>'Reviewed purchase hosting','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['One reviewed website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Email during stated hours'];
    $plan=service_plans_create(hcp_id(1),$terms,HCP_PASSWORD);$plan=service_plans_change(hcp_id(1),0,hcp_id(2),'activate',[],HCP_PASSWORD);provider_plan_catalog_prepare(HCP_PASSWORD);
    $config=['reseller_id'=>42,'type'=>'7001','label'=>'Synthetic reviewed account','region'=>'Reviewed region','limits'=>['websites'=>1],'cost'=>['amount'=>'9.87','currency'=>'USD','period_months'=>1,'tax'=>'unknown','basis'=>'per-package','reference'=>'PRIVATE_COST_REFERENCE']];provider_plan_catalog_save(hcp_id(10),hcp_id(1),1,$plan->fingerprint(),null,$profile,1,$config,7,true,HCP_PASSWORD);
    hosting_catalog_prepare(HCP_PASSWORD);payments_migrate(HCP_PASSWORD);attempts_migrate(HCP_PASSWORD);ledger_migrate(HCP_PASSWORD);service_plans_checkout_migrate(HCP_PASSWORD);recurring_billing_migrate(HCP_PASSWORD);recurring_billing_checkout_migrate(HCP_PASSWORD);
    $stage='unprepared';$before=hcp_effects();$page=hosting_purchases_owner_page();hcp_ok('Unprepared inbox is explicit and does not enable checkout storage',$page===['items'=>[],'page'=>1,'has_more'=>false,'ready'=>false]&&hcp_effects()===$before);
    hosting_checkout_prepare(HCP_PASSWORD);$before=hcp_effects();$page=hosting_purchases_owner_page();hcp_ok('Explicit setup yields an empty ready read-only inbox',$page===['items'=>[],'page'=>1,'has_more'=>false,'ready'=>true]&&hcp_effects()===$before);
    $engines=all('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(HCP_PREFIX),HCP_PREFIX]);hcp_ok('Actual fixture tables use InnoDB',$engines!==[]&&!array_filter($engines,static fn($r)=>strtolower($r['ENGINE'])!=='innodb'));
    $policy=['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>'UTC','alignment'=>'day-of-month','review_ttl_seconds'=>900];
    $review=hosting_catalog_preview(hcp_id(20),$product,hcp_id(1),$policy);$pub=hosting_catalog_publish(hcp_id(20),$product,hcp_id(1),$review['review_sha256'],HCP_PASSWORD,$policy);
    $stage='actual-checkout';$first=hcp_buy(30,$pub,1,['mode'=>'existing','id'=>1]);$second=hcp_buy(31,$pub,1,['mode'=>'new-hosting']);$foreign=hcp_buy(32,$pub,2,['mode'=>'existing','id'=>2]);
    hcp_client();hosting_checkout_preview(hcp_id(33),$pub->data()['id'],$pub->fingerprint(),1,['mode'=>'existing','id'=>1]);
    $before=hcp_effects();hcp_owner();$ownerPage=hosting_purchases_owner_page();$owner=hcp_rows($ownerPage);
    hcp_ok('Owner inbox contains all three actual completed checkouts but excludes unconfirmed review',count($owner)===3&&isset($owner[hcp_id(30)],$owner[hcp_id(31)],$owner[hcp_id(32)])&&!isset($owner[hcp_id(33)])&&$ownerPage['ready']&&!$ownerPage['has_more']);
    $valid=true;foreach([$first,$second,$foreign] as $result){$row=$owner[$result['request_id']];$invoice=one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$result['invoice_id']]);$job=one('SELECT * FROM '.table('studio_jobs').' WHERE id=?',[$result['project_id']]);$valid=$valid&&hcp_safe($row,true)&&$row['created_epoch']===$result['accepted_at']&&$row['offer_title']==='Reviewed purchase hosting'&&$row['invoice']===['id'=>$result['invoice_id'],'reference'=>$invoice['reference'],'status'=>'issued','amount_cents'=>2500,'currency'=>'USD']&&$row['project']===['id'=>$result['project_id'],'title'=>$job['title']]&&$row['agreement']===['id'=>$result['agreement_id'],'state'=>'active']&&$row['client']['id']===(int)$job['client_id']&&$row['order']===null;}
    hcp_ok('Owner rows expose exact retained invoice project agreement and client identities',$valid);
    hcp_client();$own=hcp_rows(hosting_purchases_client_page(1));hcp_ok('Primary client sees only their two completed purchases',count($own)===2&&isset($own[hcp_id(30)],$own[hcp_id(31)])&&!isset($own[hcp_id(32)]));
    hcp_ok('Client rows have a closed safe shape without owner-only client metadata',!array_filter($own,static fn($r)=>!hcp_safe($r,false))&&!str_contains(json_encode($own,JSON_THROW_ON_ERROR),'PRIVATE_')&&!str_contains(json_encode($own,JSON_THROW_ON_ERROR),$secret));
    hcp_client(2);$other=hcp_rows(hosting_purchases_client_page(2));hcp_ok('Other primary client receives only their original checkout',array_keys($other)===[hcp_id(32)]);
    hcp_client(3);hcp_ok('Primary client without purchases receives no other client data',hosting_purchases_client_page(3)['items']===[]);
    hcp_owner();hcp_ok('Second owner reads the same historical purchases after their own authentication',(function()use($owner):bool{hcp_owner(2);$same=hcp_rows(hosting_purchases_owner_page());hcp_owner();return $same===$owner;})());
    hcp_ok('Empty later page preserves explicit page and ready state',hosting_purchases_owner_page(2)===['items'=>[],'page'=>2,'has_more'=>false,'ready'=>true]);
    hcp_ok('All owner and primary-client list reads preserve every business row file and provider counter',hcp_effects()===$before&&!db()->inTransaction()&&!hcp_mutex());
    $stage='authority';$before=hcp_effects();hcp_client();hcp_deny('Primary client cannot call owner purchase list',fn()=>hosting_purchases_owner_page());hcp_deny('Primary client cannot select another client list',fn()=>hosting_purchases_client_page(2));
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;hcp_deny('Retained contact provenance refuses before normalization '.$key,fn()=>hosting_purchases_client_page(1));hcp_ok('Contact marker remains present after refusal '.$key,array_key_exists($key,$_SESSION));unset($_SESSION[$key]);}
    hcp_owner(3);hcp_deny('Fully permissioned administrator is not a financial owner',fn()=>hosting_purchases_owner_page());hcp_owner();hcp_deny('Owner cannot impersonate a client by supplying its ID',fn()=>hosting_purchases_client_page(1));
    $_GET['support']='synthetic';hcp_deny('Support route refuses owner purchase list',fn()=>hosting_purchases_owner_page());unset($_GET['support']);hcp_client();$_GET['support']='synthetic';hcp_deny('Support route refuses client purchase list',fn()=>hosting_purchases_client_page(1));unset($_GET['support']);
    $_SESSION=[];hcp_deny('Anonymous session cannot list owner purchases',fn()=>hosting_purchases_owner_page());hcp_deny('Anonymous session cannot list client purchases',fn()=>hosting_purchases_client_page(1));
    hcp_owner();hcp_deny('Zero page cannot select purchase history',fn()=>hosting_purchases_owner_page(0));hcp_client();hcp_deny('Excessive page cannot select client history',fn()=>hosting_purchases_client_page(1,100001));
    hcp_ok('All authority refusals are read-only',hcp_effects()===$before);
    hcp_owner();support_migrate(HCP_PASSWORD);support_policy(1,true,HCP_PASSWORD);$support=support_start(1);$GLOBALS['cwp_support']=support_resolve($support);hcp_ok('Actual owner support context resolves for the intended client',(int)($GLOBALS['cwp_support']['client']['id']??0)===1);$before=hcp_effects();
    hcp_deny('Actual support session cannot list owner purchases',fn()=>hosting_purchases_owner_page());hcp_deny('Actual support session cannot list primary-client purchases',fn()=>hosting_purchases_client_page(1));hcp_ok('Support refusal creates no business or provider effects',hcp_effects()===$before);support_end($support);hcp_owner();
    $stage='older-invoice';$addon=catalog_save(0,0,array_replace($input,['sku'=>'PURCHASE-UNRELATED','title'=>'Unrelated additional service','family'=>'addon','status'=>'published','term'=>'once','provider_ref'=>'','features'=>'Separate service','terms'=>'Separate agreed terms']));
    $newer=[];for($n=1;$n<=51;$n++)$newer[]=catalog_issue(1,$addon,1,1,'Synthetic unrelated invoice',hash('sha256','purchase-inbox-unrelated-'.$n));
    $latest=array_map('intval',array_column(all('SELECT i.id FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id WHERE j.client_id=1 AND i.amount_cents=2500 AND i.currency=? AND i.status IN (?,?) ORDER BY i.id DESC LIMIT 50',['USD','issued','paid']),'id'));
    hcp_ok('Fifty-one real unrelated same-price invoices push the original checkout outside latest fifty',count($newer)===51&&min($newer)>$first['invoice_id']&&count($latest)===50&&!in_array($first['invoice_id'],$latest,true));
    service_orders_migrate(HCP_PASSWORD);$before=hcp_effects();$context=service_orders_context($first['agreement_id'],1);$choices=$context['choices'];
    hcp_ok('Checkout order selection finds only its original invoice beyond latest fifty',count($choices)===1&&$choices[0]['id']===$first['invoice_id']&&$context['more']===false&&$choices[0]['sha256']===recurring_billing_locked(fn()=>service_orders_invoice($context['source'],$first['invoice_id']))['sha256']);
    $page=hcp_rows(hosting_purchases_owner_page());hcp_ok('Purchase invoice identity is unchanged by newer unrelated invoices',$page[hcp_id(30)]['invoice']['id']===$first['invoice_id']&&hcp_effects()===$before);
    $stage='actual-order';$order=service_orders_offer(hcp_id(50),$first['agreement_id'],1,$context['source']->revision(),$context['source']->fingerprint(),$choices[0]['id'],$choices[0]['sha256'],HCP_PASSWORD);$before=hcp_effects();
    hcp_ok('Owner inbox projects the real offered order without creating fulfillment',hcp_rows(hosting_purchases_owner_page())[hcp_id(30)]['order']===['id'=>hcp_id(50),'phase'=>'offered','domain_name'=>null]&&hcp_effects()===$before);
    hcp_client();$order=service_orders_decide(hcp_id(50),1,0,hcp_id(51),'accept',['domain_name'=>'purchase.example','review_sha256'=>$order->fingerprint()],true);$before=hcp_effects();
    hcp_ok('Client inbox projects the actual accepted domain on the original purchase',hcp_rows(hosting_purchases_client_page(1))[hcp_id(30)]['order']===['id'=>hcp_id(50),'phase'=>'accepted','domain_name'=>'purchase.example']&&hcp_effects()===$before);
    hcp_client(2);hcp_ok('Another client never receives the first client order or domain',!str_contains(json_encode(hosting_purchases_client_page(2),JSON_THROW_ON_ERROR),'purchase.example'));
    $stage='lost-history';hcp_loss('Missing original accepted order event','service_order_events','id',hcp_id(51));
    $audit=one('SELECT * FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['service_orders.offered','staff:1; Order '.hcp_id(50).' agreement %']);if(!$audit)throw new LogicException('Original order audit absent.');hcp_loss('Missing original order audit','audit','id',$audit['id']);
    hcp_loss('Missing completed checkout result','hosting_checkout_results','id',hcp_id(30));hcp_loss('Missing established checkout marker','settings','name','hosting_checkout_version');hcp_loss('Missing established order marker','settings','name','service_orders_schema_version');
    hcp_owner();$before=hcp_effects();$savedOrder=one('SELECT * FROM '.table('service_orders').' WHERE id=?',[hcp_id(50)]);$savedEvent=one('SELECT * FROM '.table('service_order_events').' WHERE id=?',[hcp_id(51)]);
    query('DELETE FROM '.table('service_order_events').' WHERE id=?',[hcp_id(51)]);query('DELETE FROM '.table('service_orders').' WHERE id=?',[hcp_id(50)]);
    try{$damaged=hcp_effects();hcp_deny('Lost order and event cannot look like an unoffered checkout',fn()=>hosting_purchases_owner_page());hcp_client();hcp_deny('Client list refuses original order loss with retained audit',fn()=>hosting_purchases_client_page(1));hcp_ok('Paired order loss is not reconstructed by purchase reads',hcp_effects()===$damaged);}finally{hcp_restore('service_orders',$savedOrder);hcp_restore('service_order_events',$savedEvent);}
    hcp_owner();hcp_ok('Exact order and event restoration recovers original byte state',hcp_effects()===$before);
    foreach(['journal_sha256'=>str_repeat('0',64),'client_id'=>2] as $field=>$value){$before=hcp_effects();query('UPDATE '.table('service_orders').' SET '.$field.'=? WHERE id=?',[$value,hcp_id(50)]);try{$damaged=hcp_effects();hcp_deny('Corrupt original order '.$field.' refuses owner list',fn()=>hosting_purchases_owner_page());hcp_client();hcp_deny('Corrupt original order '.$field.' refuses client list',fn()=>hosting_purchases_client_page(1));hcp_ok('Corrupt order '.$field.' remains untouched by read',hcp_effects()===$damaged);}finally{query('UPDATE '.table('service_orders').' SET '.$field.'=? WHERE id=?',[$savedOrder[$field],hcp_id(50)]);hcp_owner();}hcp_ok('Original order '.$field.' restoration is exact',hcp_effects()===$before);}
    $stage='current-auth';hcp_client();$oldSession=$_SESSION;hcp_owner();$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');studio_client_save(1,$client['auth_version'],$client['name'],$client['email'],true);$_SESSION=$oldSession;$before=hcp_effects();hcp_deny('Real client-auth rotation rejects the old purchase session',fn()=>hosting_purchases_client_page(1));hcp_ok('Revoked session refusal does not change purchase history',hcp_effects()===$before);hcp_client();hcp_ok('Fresh primary-client session still reads the retained purchases',count(hosting_purchases_client_page(1)['items'])===2);
    hcp_owner();$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');studio_client_save(1,$client['auth_version'],$client['name'],$client['email'],false);hcp_client();$before=hcp_effects();hcp_deny('Inactive client cannot read purchase history through a fresh token',fn()=>hosting_purchases_client_page(1));hcp_ok('Inactive refusal preserves business and provider history',hcp_effects()===$before);hcp_owner();$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');studio_client_save(1,$client['auth_version'],$client['name'],$client['email'],true);
    $oldOwner=$_SESSION;staff_account_save(1,'Purchase owner renamed','purchase-staff1@example.invalid',HCP_PASSWORD,'');$_SESSION=$oldOwner;$before=hcp_effects();hcp_deny('Real owner-auth rotation rejects the old inbox session',fn()=>hosting_purchases_owner_page());hcp_ok('Revoked owner refusal preserves every business record',hcp_effects()===$before);hcp_owner();
    $stage='historical-availability';hosting_catalog_withdraw(hcp_id(60),$pub->data()['id'],$pub->fingerprint(),'Owner withdrew this offer from future purchases.',HCP_PASSWORD);provider_settings_state($profile,1,'disable',HCP_PASSWORD);$before=hcp_effects();
    hcp_ok('Owner can still inspect all completed purchases after withdrawal and connection disable',count(hosting_purchases_owner_page()['items'])===3);hcp_client();hcp_ok('Client keeps original purchase and accepted domain after offer availability changes',hcp_rows(hosting_purchases_client_page(1))[hcp_id(30)]['order']['domain_name']==='purchase.example');
    hcp_ok('Historical listing never refreshes provider inventory or alters stored history',hcp_effects()===$before&&$dns===1&&$reads===1);
    hcp_ok('No receipt or payment attempt is created by the fixture',(int)query('SELECT COUNT(*) FROM '.table('invoice_payments'))->fetchColumn()===0&&(int)query('SELECT COUNT(*) FROM '.table('payment_attempts'))->fetchColumn()===0);
    hcp_ok('Native read wrappers release their transaction and shared mutex',!db()->inTransaction()&&!hcp_mutex());
}catch(Throwable $e){$failure=['stage'=>$stage,'class'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine()];if($e instanceof PDOException&&preg_match('/\A[A-Z0-9]{5}\z/D',(string)($e->errorInfo[0]??'')))$failure['sqlstate']=$e->errorInfo[0];}
$passed=!isset($failure)&&$checks!==[]&&!array_filter($checks,static fn($c)=>$c['passed']!==true);
$bytes=json_encode(['passed'=>$passed,'scope'=>'Isolated Linux InnoDB purchase graph and local read-only owner/client projections; one synthetic package-type read, no provider write, payment, HTTP or browser execution','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if($reportReady){$fp=fopen($report,'xb');if(!$fp)exit(2);try{if(fwrite($fp,$bytes)!==strlen($bytes))exit(2);}finally{fclose($fp);}}
echo $bytes;exit($passed?0:1);
