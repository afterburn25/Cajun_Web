<?php
/** Disposable packaged checkout bridge: explicit current synthetic refresh, safe state reads, contact-context negative only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOSTING_CHECKOUT_HTTP')!=='1')exit(2);
$operation=$argv[1]??'';if(!in_array($operation,['prepare','state','contact-context','billing-state'],true))exit(2);
set_exception_handler(static function(Throwable $e)use($operation):never{
    $safe=['operation'=>$operation,'class'=>get_class($e),'message'=>'Disposable fixture precondition or application guard failed.','file'=>basename($e->getFile()),'line'=>$e->getLine()];
    if($e instanceof PDOException){$s=$e->errorInfo[0]??null;$c=$e->errorInfo[1]??null;$safe['sqlstate']=is_string($s)&&preg_match('/\A[A-Z0-9]{5}\z/',$s)?$s:null;$safe['driver_code']=is_int($c)?$c:null;}
    fwrite(STDERR,json_encode($safe,JSON_THROW_ON_ERROR).PHP_EOL);exit(1);
});
set_error_handler(static function(int $n,string $m,string $f,int $l):bool{if(!(error_reporting()&$n))return false;throw new ErrorException('Fixture PHP diagnostic',0,$n,$f,$l);});
$port=filter_var(getenv('CWP_TEST_PORT'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
if($port===false||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')throw new RuntimeException('Assigned disposable loopback database required');
$root=realpath(dirname(__DIR__));$site=$root.'/test-runtime/hosting-checkout-web/hosting-checkout';$private=$root.'/test-runtime/hosting-checkout-private';$sessions=$root.'/test-runtime/hosting-checkout-browser-sessions';
foreach([$site,$private,$sessions]as$p){if(!is_dir($p)||realpath($p)!==$p)throw new RuntimeException('Exact private runtime required');for($q=$p;$q!==dirname($q);$q=dirname($q))if(is_link($q))throw new RuntimeException('Linked runtime refused');}
if(!is_file($private.'/provider-master.key')||is_link($private.'/provider-master.key')||(fileperms($private.'/provider-master.key')&0077)!==0)throw new RuntimeException('Original protected provider key required');
$reportFile=$root.'/qa/hosting-checkout-native-results.json';if(!is_file($reportFile)||is_link($reportFile)||filesize($reportFile)>262144)throw new RuntimeException('Bounded original native report required');$raw=file_get_contents($reportFile);$expected=getenv('CWP_CHECKOUT_NATIVE_REPORT_SHA256');
if(!is_string($expected)||!preg_match('/\A[a-f0-9]{64}\z/',$expected)||!hash_equals($expected,hash('sha256',$raw)))throw new RuntimeException('Original native report differs');
$report=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$checks=$report['checks']??[];
if(($report['passed']??null)!==true||isset($report['error'])||count($checks)!==79||count(array_unique(array_column($checks,'name')))!==79||array_filter($checks,static fn(array$c):bool=>($c['passed']??null)!==true))throw new RuntimeException('All corrected native outcomes required');
define('CWP_BOOT',true);define('CWP_ROOT',$site);require $site.'/includes/bootstrap.php';require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes;
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-checkout','storage'=>$private,'key'=>str_repeat('synthetic-checkout-local-',4),'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hcc_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$site;
ini_set('session.save_path',$sessions);
function hch_id(int$n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function hch_refresh_ready_at(int$started,int$now):int{if($now<1||$started<1||$started>$now||$started>PHP_INT_MAX-30)throw new RuntimeException('Unexpected retained refresh time');return $started+30;}
function hch_count(string$name):int{return(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function hch_rows(bool$financialOnly=false):string{
    $data=[];
    foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,4)=BINARY ? ORDER BY TABLE_NAME',['hcc_'])as$t){
        if(!preg_match('/\Ahcc_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unknown table');$n=substr($t['name'],4);
        if($financialOnly){if(!preg_match('/\A(?:service_|recurring_|payment_)/',$n)&&!in_array($n,['studio_jobs','studio_invoices','invoice_payments','hosting_checkout_reviews','hosting_checkout_results'],true))continue;}
        elseif(!preg_match('/\A(?:service_|recurring_|payment_|hosting_|catalog_|provider_)/',$n)&&!in_array($n,['studio_jobs','studio_invoices','invoice_payments'],true))continue;
        $values=array_map(static fn(array$r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$t['name'].'`'));sort($values,SORT_STRING);$data[$n]=$values;
    }
    // Business witnesses are included; login, rate and support audit effects intentionally are not.
    $where=$financialOnly?"action LIKE 'hosting_checkout.%' OR action LIKE 'recurring_billing.%' OR action LIKE 'service_plans.%'":"action LIKE 'hosting_checkout.%' OR action LIKE 'hosting_catalog.%' OR action LIKE 'recurring_billing.%' OR action LIKE 'service_plans.%'";
    $where.=" OR action LIKE 'payments.%'";
    $data['business_audits']=array_map(static fn(array$r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM '.table('audit').' WHERE '.$where.' ORDER BY id'));
    return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
}
function hch_read(array$row,string$key,string$address,callable$authorize,string$operation):InventorySnapshot{
    global $calls;$authorize();if($key!=='SYNTHETIC_CHECKOUT_PROVIDER_KEY'||$address!=='8.8.8.8'||$operation!==InventoryRead::PACKAGE_TYPES)throw new LogicException('Unknown synthetic read');$calls++;
    return PackageTypes::project(new TransportResponse(200,[],'[{"id":7001,"label":"Synthetic checkout hosting"}]'),time(),'twenty-i|'.$row['id'].'|'.$operation);
}
$marker=$private.'/checkout-http-prepared.json';
if($operation==='contact-context'){
    if(!is_file($marker)||is_link($marker))throw new RuntimeException('Prepare first');$input=json_decode(stream_get_contents(STDIN,1024),true,4,JSON_THROW_ON_ERROR);$sid=$input['session_id']??null;
    if(!is_string($sid)||!preg_match('/\A[A-Za-z0-9,-]{16,128}\z/',$sid))throw new RuntimeException('Existing session identity required');$file=$sessions.'/sess_'.$sid;
    if(!is_file($file)||is_link($file)||realpath($file)!==$file)throw new RuntimeException('Existing isolated session required');session_id($sid);start_session();
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');if(($_SESSION['client_id']??null)!==1||!is_string($_SESSION['client_auth']??null)||!hash_equals($c['auth_version'],$_SESSION['client_auth'])||isset($_SESSION['uid'])||array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Original primary login required');
    $_SESSION['contact_id']=900001;$_SESSION['contact_auth']=str_repeat('f',32);session_write_close();echo json_encode(['contact_context_set'=>true,'scope'=>'Negative established-key session probe only; not a contact login'],JSON_THROW_ON_ERROR);exit;
}
start_session();$owner=one('SELECT * FROM '.table('users').' WHERE id=1');$password='Synthetic native checkout owner';
if(!$owner||$owner['email']!=='checkout-owner@example.invalid'||!password_verify($password,$owner['password_hash']))throw new RuntimeException('Original synthetic owner required');
foreach([1,2]as$id){$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);if(!$c||$c['email']!=='checkout-client'.$id.'@example.invalid'||!password_verify('Synthetic checkout client',$c['password_hash']))throw new RuntimeException('Original clients required');}
$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];
if($operation==='prepare'){
    foreach([$marker,$site.'/config.php',$site.'/.installed.php']as$p)if(file_exists($p)||is_link($p))throw new RuntimeException('Preserve prior continuation');
    $initial=hosting_checkout_locked(function():array{
        hosting_checkout_require();$a=hosting_checkout_result_row(one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=?',[hch_id(30)]));$b=hosting_checkout_result_row(one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=?',[hch_id(40)]));
        if(hch_count('hosting_checkout_results')!==2||hch_count('studio_invoices')!==2||hch_count('invoice_payments')!==1||hch_count('payment_attempts')!==0||$a['invoice']['status']!=='paid'||$b['invoice']['status']!=='issued'||!attempts_ready())throw new RuntimeException('Expected native graph differs');
        $old=provider_plan_catalog_lookup(hch_id(10));$pub=hosting_checkout_publication(hch_id(22));$p=provider_settings_load($old['profile_id']);if((int)$p['revision']!==2||(int)$p['enabled']!==0)throw new RuntimeException('Expected native final connection differs');
        return['financial_sha256'=>hch_rows(true),'profile'=>$p['id'],'product'=>$pub->data()['product']['id'],'plan'=>hch_id(1),'mapping'=>$old,'unpaid_invoice'=>(int)$b['invoice']['id']];
    });
    provider_settings_state($initial['profile'],2,'enable',$password);$calls=0;
    // Honor the real profile cooldown without editing retained intent timestamps or retrying a refresh.
    if(db()->inTransaction())throw new LogicException('Cooldown wait must be outside a transaction.');
    $latest=filter_var(query('SELECT MAX(started_epoch) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$initial['profile']])->fetchColumn(),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($latest===false)throw new RuntimeException('Original refresh time required');
    $readyAt=hch_refresh_ready_at($latest,time());$deadline=hrtime(true)+35000000000;
    while(time()<$readyAt){if(hrtime(true)>=$deadline)throw new RuntimeException('Bounded real cooldown wait expired');usleep(100000);}
    if(provider_inventory_refresh($initial['profile'],3,$password,static fn()=>'8.8.8.8','hch_read',InventoryRead::PACKAGE_TYPES)!=='complete'||$calls!==1)throw new RuntimeException('Closed refresh failed');
    $plan=recurring_billing_locked(static fn()=>service_plans_validate(service_plans_row($initial['plan'],false),false));
    provider_plan_catalog_save(hch_id(900),$initial['plan'],$plan->revision(),$plan->fingerprint(),hch_id(10),$initial['profile'],3,$initial['mapping']['configuration'],7,true,$password);
    if(hosting_checkout_locked(static fn():string=>hch_rows(true))!==$initial['financial_sha256'])throw new RuntimeException('Continuation changed prior commercial history');
    $safe=['prepared'=>true,'prefix'=>'hcc_','product_id'=>$initial['product'],'plan_id'=>$initial['plan'],'mapping_id'=>hch_id(900),'unpaid_invoice'=>$initial['unpaid_invoice'],'native_report_sha256'=>$expected,'prior_financial_sha256'=>$initial['financial_sha256'],'synthetic_refresh_calls'=>$calls,'native_end_state_sealed'=>false];
    foreach([$site.'/config.php'=>'<?php return '.var_export(config(),true).';',$site.'/.installed.php'=>CWP_GUARD.'synthetic installed guard',$marker=>json_encode($safe,JSON_THROW_ON_ERROR)]as$p=>$bytes){$f=fopen($p,'x');if(!$f)throw new RuntimeException('Existing private output');try{if(fwrite($f,$bytes)!==strlen($bytes))throw new RuntimeException('Incomplete private output');}finally{fclose($f);}if(!chmod($p,0600))throw new RuntimeException('Private output permissions failed');}
    echo json_encode($safe,JSON_THROW_ON_ERROR);exit;
}
if(!is_file($marker)||is_link($marker))throw new RuntimeException('Prepare first');
if($operation==='billing-state'){
    // Separate bounded read contract; the original state response below is unchanged.
    $result=hosting_checkout_locked(function():array{
        hosting_checkout_require();
        if(!provider_catalog_import_ready())throw new RuntimeException('Original catalog required');
        $variantsReady=provider_catalog_variant_ready();
        $counts=[];foreach(['reviews'=>'hosting_checkout_reviews','results'=>'hosting_checkout_results','jobs'=>'studio_jobs','invoices'=>'studio_invoices','receipts'=>'invoice_payments','attempts'=>'payment_attempts','publications'=>'hosting_catalog_publications','products'=>'catalog_products','media'=>'media','plans'=>'service_plans','plan_events'=>'service_plan_events','mappings'=>'provider_plan_catalog','provider_refreshes'=>'provider_refreshes','provider_checks'=>'provider_checks']as$key=>$name){
            $counts[$key]=hch_count($name);if($counts[$key]>128)throw new RuntimeException('Disposable billing fixture exceeded its bound');
        }
        $counts['variants']=$variantsReady?hch_count('catalog_provider_variants'):0;
        if($counts['variants']>128)throw new RuntimeException('Disposable variants exceeded their bound');
        $products=[];
        foreach(all('SELECT id,revision,sku,title,term,amount_cents,status FROM '.table('catalog_products').' ORDER BY id LIMIT 129')as$p){
            $id=(int)$p['id'];$origin=provider_catalog_import_record($id);if(!$origin)continue;
            $canonical=provider_catalog_import_canonical_record($origin);
            $variant=$variantsReady?one('SELECT id FROM '.table('catalog_provider_variants').' WHERE product_id=?',[$id]):null;
            $products[(string)$id]=['id'=>$id,'revision'=>(int)$p['revision'],'sku'=>$p['sku'],'title'=>$p['title'],'term'=>$p['term'],'amount_cents'=>$p['amount_cents']===null?null:(int)$p['amount_cents'],'status'=>$p['status'],
                'canonical_import_id'=>(int)$canonical['id'],'canonical_product_id'=>(int)$canonical['product_id'],'variant_id'=>$variant['id']??null,'media_id'=>$origin['media_id'],'media_revision'=>(int)$origin['media_revision']];
        }
        $publications=[];foreach(all('SELECT id FROM '.table('hosting_catalog_publications').' ORDER BY seq LIMIT 129')as$row){
            $publication=hosting_catalog_publication($row['id']);$plan=ServicePlanDocument::fromJson($publication->data()['plan_json']);
            $publications[$row['id']]=$publication->historicalProjection()+['plan_id'=>$plan->data()['id']];
        }
        $reviews=[];foreach(all('SELECT * FROM '.table('hosting_checkout_reviews').' ORDER BY id LIMIT 129')as$row){
            $review=hosting_checkout_review_row($row);$r=$review->data();
            $reviews[$row['id']]=['request_id'=>$r['request_id'],'review_sha256'=>$review->fingerprint(),'publication_id'=>$r['publication_id'],'publication_sha256'=>$r['publication_sha256'],'client_id'=>$r['client_id'],'project'=>$r['project'],'billing'=>$r['billing']];
        }
        $results=[];foreach(all('SELECT * FROM '.table('hosting_checkout_results').' ORDER BY id LIMIT 129')as$row){
            $validated=hosting_checkout_result_row($row);$d=$validated['document'];
            $map=one('SELECT snapshot_json FROM '.table('recurring_billing_invoices').' WHERE id=?',[$d['issuance_id']]);
            $snapshot=json_decode($map['snapshot_json'],true,8,JSON_THROW_ON_ERROR);
            $results[$row['id']]=hosting_checkout_projection($validated,true)+['publication_id'=>$d['publication_id'],'publication_sha256'=>$d['publication_sha256'],'client_id'=>$d['client_id'],'billing'=>$reviews[$row['id']]['billing'],'issued_period'=>$snapshot['period']];
        }
        // Hash original financial rows without exporting their contents. Fixed namespace, no caller selectors.
        $retained=[];$total=0;
        foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,4)=BINARY ? ORDER BY TABLE_NAME',['hcc_'])as$t){
            if(!preg_match('/\Ahcc_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unknown table');$n=substr($t['name'],4);
            if(!preg_match('/\A(?:service_|recurring_|payment_)/',$n)&&!in_array($n,['studio_jobs','studio_invoices','invoice_payments','hosting_checkout_reviews','hosting_checkout_results','catalog_provider_imports','hosting_catalog_publications','media'],true))continue;
            $rows=all('SELECT * FROM `'.$t['name'].'` LIMIT 129');$total+=count($rows);
            if(count($rows)>128||$total>1500)throw new RuntimeException('Disposable retained history exceeded its bound');
            $hashes=array_map(static fn(array$r):string=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$rows);sort($hashes,SORT_STRING);$retained[$n]=$hashes;
        }
        $audits=all('SELECT * FROM '.table('audit')." WHERE action LIKE 'hosting_checkout.%' OR action LIKE 'hosting_catalog.%' OR action LIKE 'recurring_billing.%' OR action LIKE 'service_plans.%' OR action LIKE 'payments.%' ORDER BY id LIMIT 129");
        if(count($audits)>128)throw new RuntimeException('Disposable business audit exceeded its bound');
        $hashes=array_map(static fn(array$r):string=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$audits);sort($hashes,SORT_STRING);$retained['business_audits']=$hashes;
        $io=[];foreach(['provider_refreshes','provider_refresh_results','provider_checks','provider_check_results','provider_operations','provider_operation_grants']as$name){
            $exists=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['hcc_'.$name])!==null;
            if(!$exists){if(!in_array($name,['provider_operations','provider_operation_grants'],true))throw new RuntimeException('Original provider read history missing');$io[$name]=['exists'=>false,'rows'=>[]];continue;}
            $rows=all('SELECT * FROM '.table($name).' LIMIT 129');if(count($rows)>128)throw new RuntimeException('Disposable provider history exceeded its bound');
            $hashes=array_map(static fn(array$r):string=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),$rows);sort($hashes,SORT_STRING);$io[$name]=['exists'=>true,'rows'=>$hashes];
        }
        return['counts'=>$counts,'variants_ready'=>$variantsReady,'products'=>$products,'publications'=>$publications,'reviews'=>$reviews,'results'=>$results,'retained_rows'=>$retained,'provider_io_sha256'=>hash('sha256',json_encode($io,JSON_THROW_ON_ERROR))];
    });
    $encoded=json_encode($result,JSON_THROW_ON_ERROR);if(strlen($encoded)>262144)throw new RuntimeException('Disposable billing state exceeded its output bound');echo $encoded;exit;
}
$result=hosting_checkout_locked(function():array{
    hosting_checkout_require();$rows=[];foreach(all('SELECT * FROM '.table('hosting_checkout_results').' ORDER BY id')as$r){$v=hosting_checkout_result_row($r);$rows[$r['id']]=hosting_checkout_projection($v,true);}
    $latest=one('SELECT id,document_sha256 FROM '.table('hosting_catalog_publications').' ORDER BY seq DESC LIMIT 1');
    return['business_sha256'=>hch_rows(),'counts'=>['reviews'=>hch_count('hosting_checkout_reviews'),'results'=>hch_count('hosting_checkout_results'),'jobs'=>hch_count('studio_jobs'),'invoices'=>hch_count('studio_invoices'),'receipts'=>hch_count('invoice_payments'),'attempts'=>hch_count('payment_attempts'),'publications'=>hch_count('hosting_catalog_publications')],'latest_publication'=>$latest,'results'=>$rows,
        'setup_counts'=>['plans'=>hch_count('service_plans'),'plan_events'=>hch_count('service_plan_events'),'mappings'=>hch_count('provider_plan_catalog'),'provider_refreshes'=>hch_count('provider_refreshes'),'provider_checks'=>hch_count('provider_checks')]];
});echo json_encode($result,JSON_THROW_ON_ERROR);
