<?php
declare(strict_types=1);
/** Actual read/controller/render bodies over explicit in-memory SQL and publication models. */
if(PHP_SAPI!=='cli-server'||getenv('CWP_BILLING_HTTP_FIXTURE')!=='1'||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(404);exit;}
$root=realpath((string)getenv('CWP_BILLING_SOURCE'));$private=realpath((string)getenv('CWP_BILLING_TEMP'));
if(!$root||!$private||!is_file($root.'/site/pages/services/hosting.php')||!is_dir($private.'/sessions')){http_response_code(500);exit;}
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$root.'/site');define('CWP_ROOT',$private);
require CWP_CODE_ROOT.'/includes/security/input.php';require CWP_CODE_ROOT.'/includes/security/csrf.php';require CWP_CODE_ROOT.'/includes/security/http.php';
ini_set('display_errors','0');session_save_path($private.'/sessions');session_name('cwp_billing_http');session_start();security_headers();
/** Execute exact named declarations, without production bootstrap/SQL/credential configuration. */
function hcb_load(string $path,array $names,bool $class=false):void {
    $tokens=token_get_all((string)file_get_contents(CWP_CODE_ROOT.'/'.$path),TOKEN_PARSE);$found=[];
    for($i=0;$i<count($tokens);$i++){
        if(!is_array($tokens[$i])||$tokens[$i][0]!==($class?T_CLASS:T_FUNCTION))continue;
        $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(!isset($tokens[$j])||!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||!in_array($tokens[$j][1],$names,true))continue;
        $name=$tokens[$j][1];$code='';$depth=0;$opened=false;
        for($k=$i;$k<count($tokens);$k++){$t=$tokens[$k];$code.=is_array($t)?$t[1]:$t;
            if($t==='{'||(is_array($t)&&in_array($t[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true))){$depth++;$opened=true;}
            elseif($t==='}'){$depth--;if($opened&&$depth===0)break;}}
        if(isset($found[$name])||!$opened||$depth!==0)throw new LogicException('Ambiguous fixture declaration.');
        eval(($class?'final ':'').$code);$found[$name]=true;$i=$k;
    }
    if(count($found)!==count($names))throw new LogicException('Missing fixture declaration.');
}
hcb_load('app/hosting/catalog_offers_context.php',['HostingCatalogRead'],true);
hcb_load('app/hosting/catalog_offers.php',['hosting_catalog_public']);
hcb_load('app/plans/forms.php',['service_plans_form_text','service_plans_form_number','service_plans_form_id','service_plans_form_hash','service_plans_form_confirm']);
hcb_load('app/plans/documents.php',['service_plans_actor','service_plans_id','service_plans_keys']);
hcb_load('app/plans/records.php',['service_plans_current_client','service_plans_client_row','service_plans_require_ready']);
hcb_load('app/hosting/catalog_offers_document.php',['hosting_checkout_policy']);
hcb_load('app/hosting/catalog_checkout.php',['hosting_checkout_reject_contact','hosting_checkout_primary_client','hosting_checkout_require']);
hcb_load('app/marketing/growth.php',['gmoney']);
hcb_load('includes/urls.php',['page_url']);
hcb_load('portal/controllers/hosting_catalog.php',['hosting_catalog_portal_query','hosting_catalog_portal_values','hosting_catalog_portal_attempt','hosting_catalog_portal_action','hosting_catalog_portal_form','hosting_catalog_portal_render']);
hcb_load('portal/controllers/hosting_checkout.php',['hosting_checkout_portal_values','hosting_checkout_portal_selection','hosting_checkout_portal_hidden','hosting_checkout_portal_render']);
require CWP_CODE_ROOT.'/app/hosting/catalog_billing.php';
// Layout declarations contain no bootstrap; loading this actual file is intentional.
require CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php';
function hcb_count(string $name):void {$_SESSION['counts'][$name]=(int)($_SESSION['counts'][$name]??0)+1;}
function hcb_write(string $name):never {file_put_contents(CWP_ROOT.'/forbidden-calls.log',$name."\n",FILE_APPEND|LOCK_EX);throw new LogicException('Business writes are forbidden in this fixture.');}
function query(mixed ...$args):never {hcb_write('sql');}
function hosting_catalog_request(mixed ...$args):never {hcb_write('request');}
function hosting_catalog_close_request(mixed ...$args):never {hcb_write('withdrawal');}
function hosting_checkout_preview(mixed ...$args):never {hcb_write('review');}
function hosting_checkout_confirm(mixed ...$args):never {hcb_write('invoice');}
function provider_operations_perform(mixed ...$args):never {hcb_write('provider');}
function db():object {return new class{function inTransaction():bool{return ($GLOBALS['hcb_depth']??0)>0;}};}
function recurring_billing_locked(callable $work):mixed {if(($GLOBALS['hcb_depth']??0)!==0)throw new LogicException('Unexpected nested scope.');$GLOBALS['hcb_depth']=1;try{return $work();}finally{$GLOBALS['hcb_depth']=0;}}
function recurring_billing_touch(callable $work,bool $ready=true):mixed{return $work();}
function hosting_checkout_locked(callable $work):mixed{hosting_checkout_reject_contact();return recurring_billing_locked($work);}
function hosting_catalog_guard():void {if(!db()->inTransaction())throw new LogicException('Read outside modeled owning transaction.');}
function table(string $name):string{return 'model_'.$name;}
function catalog_ready():bool{return true;}
function service_plans_ready():bool{return true;}
function staff_ready():bool{return true;}
function studio_ready():bool{return true;}
function provider_settings_ready():bool{return true;}
function provider_catalog_import_ready():bool{return true;}
function provider_package_types_ready():bool{return true;}
function provider_plan_catalog_state():array{return ['ready'=>true];}
function hosting_checkout_ready():bool{return true;}
function hosting_catalog_ready():bool {hcb_count('history_read');if(($_SESSION['scenario']??'')==='unavailable')throw new RuntimeException('PRIVATE_HISTORY_DIAGNOSTIC');return true;}
function provider_package_types_observation(array $source):array{return ['observed_epoch'=>time(),'types'=>[]];}
function provider_catalog_import_records_in_scope(array $ids):array {return array_map(static fn($id)=>['product_id'=>$id],$ids);}
function provider_catalog_import_origins(array $records):void {hcb_count('origin_read');}
function support_current():?array{return !empty($_SESSION['resolved_support'])?['client'=>['id'=>1]]:null;}
function report_error(Throwable $error):string{hcb_count('safe_errors');return 'synthetic-reference';}
function show_flash():void{}
function site_url(string $path=''):string{return '/'.$path;}
function studio_url(array $query=[]):string{return '/portal/index.php'.($query?'?'.http_build_query($query):'');}
function icon(string $name):string{return '<span aria-hidden="true">→</span>';}
function site_head(string $title,string $page='',string $description=''):void{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title>';
    foreach(['site','premium','growth','hosting-comparison']as$css)echo '<link rel="stylesheet" href="/assets/css/'.$css.'.css">';
    echo '</head><body><main id="main-content">';
}
function site_foot(bool $cta=true):void{echo '</main></body></html>';}
/** Forty-seven independently selected current offers; annual products sort beyond the first twenty. */
function hcb_offers():array{
    $policy=['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>'America/Chicago','alignment'=>'day-of-month','review_ttl_seconds'=>900];
    $out=[];for($n=1;$n<=47;$n++){$annual=$n>24;$id=100+$n;$cadence=$annual?'annually':'monthly';
        $out[]=['product_id'=>$id,'publication_id'=>sprintf('%032x',$id),'publication_sha256'=>hash('sha256','publication-'.$id),'currency'=>'USD','cadence'=>$cadence,'amount_cents'=>$annual?12000+$n:1200+$n,'title'=>'Plan '.$id.' <img src=x onerror=alert(1)>','summary'=>'Independent '.$cadence.' price','support'=>'Studio <b>support</b>','image_id'=>str_repeat($annual?'b':'a',32),'inclusions'=>['One website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'checkout_policy'=>$policy];}
    return $out;
}
function all(string $sql,array $args=[]):array{
    hcb_count('sql_read');
    if(str_starts_with($sql,'SELECT * FROM model_provider_profiles '))return [['id'=>'synthetic-profile','revision'=>1,'enabled'=>1,'credentials'=>'synthetic-encrypted-value']];
    if(str_starts_with($sql,'SELECT id,title FROM model_studio_jobs '))return [['id'=>11,'title'=>'Client project <script>private()</script>']];
    if(str_starts_with($sql,'SELECT p.id,p.product_id FROM model_hosting_catalog_publications ')){
        if(!preg_match('/ LIMIT 21 OFFSET ([0-9]+)\z/',$sql,$m))throw new LogicException('Unknown page SQL.');
        $offers=hcb_offers();
        if(str_contains($sql,'c.term=?')){if(count($args)!==1||!in_array($args[0],['month','year'],true))throw new LogicException('Unknown period binding.');$period=$args[0]==='month'?'monthly':'annually';$offers=array_values(array_filter($offers,static fn($r)=>$r['cadence']===$period));}
        elseif($args!==[])throw new LogicException('Unexpected unfiltered query arguments.');
        if(($_SESSION['scenario']??'')==='annual-empty')$offers=array_values(array_filter($offers,static fn($r)=>$r['cadence']!=='annually'));
        $_SESSION['last_selection']=['sql'=>$sql,'args'=>$args,'offset'=>(int)$m[1]];
        return array_map(static fn($r)=>['id'=>$r['publication_id'],'product_id'=>$r['product_id']],array_slice($offers,(int)$m[1],21));
    }
    throw new LogicException('Unmodeled read query.');
}
function one(string $sql,array $args=[]):?array{
    hcb_count('sql_read');
    if(str_starts_with($sql,'SELECT f.*,r.state,')&&str_contains($sql,'model_provider_refreshes'))return ['id'=>'synthetic-observation','state'=>'complete','profile_revision'=>1,'profile_id'=>'synthetic-profile','key_fingerprint'=>'synthetic-fingerprint'];
    if(str_starts_with($sql,'SELECT * FROM model_studio_clients WHERE id=?'))return in_array($args[0]??null,[1,2],true)?['id'=>$args[0],'active'=>1,'auth_version'=>'client-auth-'.$args[0],'name'=>'Synthetic client']:null;
    throw new LogicException('Unmodeled single-row query.');
}
function hosting_catalog_publication(string $id):object{
    foreach(hcb_offers()as$offer)if($offer['publication_id']===$id)return new class($offer){
        function __construct(private array $offer){}
        function fingerprint():string{return $this->offer['publication_sha256'];}
        function data():array{return ['product'=>['id'=>$this->offer['product_id']]];}
        function publicProjection(array $scope,int $now):array{if(($_SESSION['scenario']??'')==='one-unavailable'&&$this->offer['product_id']===125)throw new RuntimeException('Synthetic unavailable publication');return $this->offer;}
    };
    throw new RuntimeException('Publication unavailable.');
}
function hosting_checkout_publication(string $id):object{return hosting_catalog_publication($id);}
function hosting_catalog_read_scope(array $ids):array{return HostingCatalogRead::forProducts($ids);}
function hosting_catalog_current_context(object $publication,array $scope):array{return $scope;}
function hosting_catalog_client_requests(int $client,int $page=1):array{hcb_count('request_read');return ['requests'=>[],'has_more'=>false];}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/health'){echo (string)getenv('CWP_BILLING_HEALTH');exit;}
if(preg_match('#\A/assets/css/(site|premium|growth|hosting-comparison)\.css\z#',(string)$path,$m)){header('Content-Type: text/css');readfile(CWP_CODE_ROOT.'/assets/css/'.$m[1].'.css');exit;}
if($path==='/media/index.php'){if(!in_array($_GET['id']??'',[str_repeat('a',32),str_repeat('b',32)],true)){http_response_code(404);exit;}header('Content-Type: image/svg+xml');echo '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="144"><rect width="240" height="144" fill="'.($_GET['id']===str_repeat('a',32)?'#334477':'#997733').'"/></svg>';exit;}
if($path==='/__seed'){
    $mode=$_GET['mode']??'';$_SESSION=['csrf'=>bin2hex(random_bytes(32)),'counts'=>[]];
    if(in_array($mode,['client','client2','support','contact-null','stale'],true)){$id=$mode==='client2'?2:1;$_SESSION+=['client_id'=>$id,'client_auth'=>'client-auth-'.$id,'client_login'=>time(),'client_last'=>time()];}
    if($mode==='support')$_SESSION['resolved_support']=true;if($mode==='contact-null')$_SESSION['contact_id']=null;if($mode==='stale')$_SESSION['client_auth']='old-auth';
    header('Content-Type: application/json');echo json_encode(['csrf'=>csrf()]);exit;
}
if($path==='/__scenario'){if(!in_array($_GET['name']??null,['','unavailable','annual-empty','one-unavailable'],true)){http_response_code(400);exit;}$_SESSION['scenario']=$_GET['name'];echo '{"set":true}';exit;}
if($path==='/__state'){header('Content-Type: application/json');echo json_encode(['counts'=>(object)($_SESSION['counts']??[]),'forbidden_calls'=>is_file(CWP_ROOT.'/forbidden-calls.log')?file(CWP_ROOT.'/forbidden-calls.log',FILE_IGNORE_NEW_LINES):[],'selection'=>$_SESSION['last_selection']??null,'business'=>['invoices'=>0,'publications'=>47,'purchases'=>0,'requests'=>0,'provider_calls'=>0]]);exit;}
if($path==='/hosting'||($path==='/index.php'&&($_GET['page']??null)==='hosting')){
    $source=(string)file_get_contents(CWP_CODE_ROOT.'/pages/services/hosting.php');
    if(substr_count($source,'declare(strict_types=1);')!==1)throw new LogicException('Public strict declaration changed.');
    $source=str_replace('declare(strict_types=1);','',$source); // Fixture itself is already strict; eval starts in template mode.
    foreach(["require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';","require_once CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php';"]as$require){if(substr_count($source,$require)!==1)throw new LogicException('Public bootstrap boundary changed.');$source=str_replace($require,'',$source);}
    eval('?>'.$source);exit;
}
if($path==='/portal/index.php'){
    // Explicit modeled authenticated route; actual controller/current-client/checkout guards execute next.
    if(!isset($_SESSION['client_id']))fail_page('Sign in to your client portal.',403);
    // Follow the production controller's exact checkout branch without its bootstrap require.
    if(($_GET['hosting']??null)==='checkout')hosting_checkout_portal_render(['id'=>(int)$_SESSION['client_id']],'');
    hosting_catalog_portal_render(['id'=>(int)$_SESSION['client_id']],'');
}
http_response_code(404);
