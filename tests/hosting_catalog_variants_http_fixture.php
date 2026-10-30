<?php
declare(strict_types=1);
/** Actual source UI/controller fixture; auth, persistence and publications below are explicit models. */
if(PHP_SAPI!=='cli-server'||getenv('CWP_VARIANTS_HTTP_FIXTURE')!=='1'||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(404);exit;}
$root=realpath((string)getenv('CWP_VARIANTS_SOURCE'));$private=realpath((string)getenv('CWP_VARIANTS_TEMP'));
if(!$root||!$private||!is_file($root.'/site/admin/views/catalog_variants.php')||!is_dir($private.'/sessions')){http_response_code(500);exit;}
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$root.'/site');define('CWP_ROOT',$private);
require CWP_CODE_ROOT.'/includes/security/input.php';require CWP_CODE_ROOT.'/includes/security/csrf.php';require CWP_CODE_ROOT.'/includes/security/http.php';
ini_set('display_errors','0');session_save_path($private.'/sessions');session_name('cwp_variants_http');session_start();security_headers();
/** Extract and execute exact function bodies; no rewritten production predicate or database bootstrap. */
function hcv_load(string $path, ?array $names=null):void {
    $tokens=token_get_all((string)file_get_contents(CWP_CODE_ROOT.'/'.$path),TOKEN_PARSE);$found=[];
    for($i=0;$i<count($tokens);$i++){
        if(!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION)continue;
        $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(!isset($tokens[$j])||!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING)continue;
        $name=$tokens[$j][1];if($names!==null&&!in_array($name,$names,true))continue;
        $code='';$depth=0;$opened=false;
        for($k=$i;$k<count($tokens);$k++){
            $token=$tokens[$k];$code.=is_array($token)?$token[1]:$token;
            if($token==='{'||(is_array($token)&&in_array($token[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true))){$depth++;$opened=true;}
            elseif($token==='}'){$depth--;if($opened&&$depth===0)break;}
        }
        if(isset($found[$name])||!$opened||$depth!==0)throw new LogicException('Ambiguous fixture source.');
        eval($code);$found[$name]=true;$i=$k;
    }
    if(!$found||($names!==null&&count($found)!==count($names)))throw new LogicException('Missing fixture source.');
}
hcv_load('admin/controllers/catalog.php');
hcv_load('admin/controllers/catalog_variants.php');
hcv_load('admin/views/catalog_variants.php');
hcv_load('includes/layout/admin.php',['admin_action','admin_input','admin_select','admin_check','admin_textarea']);
hcv_load('admin/controllers/studio.php',['studio_hidden']);
hcv_load('app/billing/catalog.php',['catalog_families','catalog_terms']);
hcv_load('app/marketing/growth.php',['gmoney','gint']);
hcv_load('app/providers/settings.php',['provider_settings_manager']);
hcv_load('app/providers/catalog_import.php',['provider_catalog_import_owner']);
hcv_load('app/providers/catalog_variants.php',['provider_catalog_variant_owner']);
hcv_load('app/staff/accounts.php',['staff_action_permission','staff_view_permission']);
hcv_load('app/plans/documents.php',['service_plans_keys']);
hcv_load('app/hosting/catalog_offers_document.php',['hosting_checkout_policy']);
hcv_load('includes/layout/hosting_catalog.php');

function hcv_count(string $name):void {$_SESSION['counts'][$name]=(int)($_SESSION['counts'][$name]??0)+1;}
function hcv_product(int $id,string $sku,string $term,?int $price,?string $image):array {
    return ['id'=>$id,'sku'=>$sku,'title'=>'Shared hosting <script>bad()</script>','family'=>'hosting','status'=>'draft','term'=>$term,'currency'=>'USD','amount_cents'=>$price,'setup_cents'=>0,'cost_cents'=>null,'provider_ref'=>'PRIVATE_PROVIDER_REFERENCE','summary'=>'Independent retail choice','features'=>'One website','terms'=>'Reviewed service terms','sort_order'=>0,'revision'=>1,'media_id'=>$image,'media_revision'=>0];
}
/** No application database: finite synthetic business store for controller argument/side-effect observations. */
function hcv_store(?callable $change=null):array {
    $h=fopen(CWP_ROOT.'/model.json','c+');if(!$h||!flock($h,LOCK_EX))throw new RuntimeException('Model unavailable.');
    try{$raw=stream_get_contents($h);$s=$raw!==''?json_decode($raw,true,512,JSON_THROW_ON_ERROR):['ready'=>false,'products'=>[101=>hcv_product(101,'BASE-MONTH','month',1200,str_repeat('a',32)),102=>hcv_product(102,'BASE-YEAR','year',12000,str_repeat('b',32))],'requests'=>[],'calls'=>[],'writes'=>['prepare'=>0,'create'=>0],'provider_calls'=>0,'invoices'=>0,'publications'=>2];
        if($change){$change($s);rewind($h);ftruncate($h,0);fwrite($h,json_encode($s,JSON_THROW_ON_ERROR));fflush($h);}return $s;
    }finally{flock($h,LOCK_UN);fclose($h);}
}
function hcv_call(string $name,array $args):void {hcv_count($name);hcv_store(static function(array &$s)use($name,$args){$s['calls'][]=['name'=>$name,'args'=>$args];});}
function support_current():?array {return !empty($_SESSION['resolved_support'])?['client'=>['id'=>1]]:null;}
function admin_user():?array {
    hcv_count('admin_user');$role=$_SESSION['role']??null;
    return $role?['id'=>1,'role'=>$role,'name'=>'Synthetic owner','auth_version'=>'synthetic-owner-auth','password_hash'=>'$2y$12$YLX6cWH84nCTjYSLPyWO7eujQIQxCmpP5brxHldO1yqLoqQGBOhxq']:null;
}
function staff_ready():bool{return true;}
function studio_ready():bool{return true;}
function staff_can(string $permission,?array $user=null):bool {$user??=admin_user();return ($user['role']??null)==='owner'||(($user['role']??null)==='administrator'&&$permission==='catalog.manage');}
function require_admin():array {$u=admin_user();if(!$u){http_response_code(403);echo 'Administrator access required';exit;}return $u;}
function staff_require(string $permission):void {if(!staff_can($permission))throw new RuntimeException('Permission unavailable.');}
function catalog_ready():bool{return true;}
function catalog_require(bool $clients=false):array {if(!staff_can('catalog.manage'))throw new RuntimeException('Catalog access unavailable.');return admin_user();}
function table(string $name):string {return 'model_'.$name;}
function db():object {return new class {function inTransaction():bool{return false;}};}
function query(mixed ...$args):never {throw new LogicException('Application SQL is forbidden in this fixture.');}
function one(string $sql,array $args=[]):?array {
    hcv_count('model_select');if($sql==='SELECT * FROM model_catalog_products WHERE id=?')return hcv_store()['products'][(int)$args[0]]??null;
    throw new LogicException('Unmodeled selected-product query.');
}
function all(string $sql,array $args=[]):array {
    hcv_count('model_select');if($sql==='SELECT * FROM model_catalog_products ORDER BY sort_order,id LIMIT 200')return array_values(hcv_store()['products']);
    if($sql==='SELECT r.*,u.name FROM model_catalog_revisions r LEFT JOIN model_users u ON u.id=r.actor_id WHERE r.product_id=? ORDER BY r.revision DESC LIMIT 20')return [];
    throw new LogicException('Unmodeled catalog projection query.');
}
function provider_catalog_import_admin_view(array $user):void {} // Existing discovery panel is outside this finite renderer fixture.
function provider_catalog_import_image_view(array $product,array $user):void {} // No upload/publish workflow is modeled.
function provider_catalog_import_record(int $id):?array {return isset(hcv_store()['products'][$id])?['product_id'=>$id,'type_ref'=>'1101']:null;}
function provider_catalog_variant_preview(int $id):array {
    provider_catalog_variant_owner();hcv_count('preview');
    if(($_SESSION['scenario']??'')==='unavailable')throw new RuntimeException('Saved hosting catalog is unavailable.');
    if(($_SESSION['scenario']??'')==='pdo')throw new PDOException('PRIVATE_DATABASE_DIAGNOSTIC');
    $s=hcv_store();$p=$s['products'][$id]??null;if(!$p)throw new RuntimeException('Imported product unavailable.');
    $projection=static fn($r)=>array_intersect_key($r,array_flip(['id','revision','sku','title','term']));
    return ['ready'=>$s['ready'],'selected_product'=>$projection($p),'origin_product'=>$projection($s['products'][101]),'review_sha256'=>hash('sha256','review|'.$id.'|'.$p['revision']),'variants'=>array_map(static fn($r)=>['product_id'=>$r['id']]+array_intersect_key($r,array_flip(['sku','title','term','amount_cents','status'])) ,array_values($s['products']))];
}
function provider_catalog_variant_prepare(string $password):void {
    provider_catalog_variant_owner($password);hcv_call('prepare',[]);hcv_store(static function(array &$s){$s['ready']=true;$s['writes']['prepare']++;});
}
function provider_catalog_variant_create(string $id,int $selected,int $revision,string $review,string $sku,string $term,string $password):int {
    provider_catalog_variant_owner($password);hcv_call('create',[$id,$selected,$revision,$review,$sku,$term]);
    if(!preg_match('/\A[a-f0-9]{32}\z/',$id)||!preg_match('/\A[A-Z0-9][A-Z0-9_-]{1,59}\z/',$sku)||!in_array($term,['month','year'],true))throw new InvalidArgumentException('Review the original billing option values.');
    if(($_SESSION['scenario']??'')==='unavailable')throw new RuntimeException('Saved hosting catalog is unavailable.');
    if(($_SESSION['scenario']??'')==='pdo')throw new PDOException('PRIVATE_DATABASE_DIAGNOSTIC');
    $result=0;hcv_store(static function(array &$s)use($id,$selected,$revision,$review,$sku,$term,&$result){
        $input=[$selected,$revision,$review,$sku,$term];
        if(isset($s['requests'][$id])){if($s['requests'][$id]['input']!==$input)throw new RuntimeException('Original billing option input differs.');$result=$s['requests'][$id]['product_id'];return;}
        $p=$s['products'][$selected]??null;
        if(!$s['ready']||!$p||$p['revision']!==$revision||!hash_equals(hash('sha256','review|'.$selected.'|'.$revision),$review))throw new RuntimeException('This product review changed. Reload before creating a billing option.');
        foreach($s['products'] as $existing)if($existing['sku']===$sku)throw new RuntimeException('That SKU already exists.');
        $result=max(array_keys($s['products']))+1;$p=hcv_product($result,$sku,$term,null,null);$p['summary']=$p['features']=$p['terms']='';
        $s['products'][$result]=$p;$s['requests'][$id]=['input'=>$input,'product_id'=>$result];$s['writes']['create']++;
    });return $result;
}
function report_error(Throwable $e):string {hcv_count('errors');return 'synthetic-reference';}
function flash(string $text,string $type='success'):void {$_SESSION['flash']=$text;}
function show_flash():void {if(isset($_SESSION['flash'])){echo '<p class="notice">'.esc($_SESSION['flash']).'</p>';unset($_SESSION['flash']);}}
function admin_url(string $view='dashboard',array $params=[]):string {return '/owner?'.http_build_query(['view'=>$view]+$params);}
function site_url(string $path=''):string{return '/'.$path;}
function page_url(string $page=''):string{return '/'.$page;}
function hcv_head(string $title,bool $admin):void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title>';
    foreach($admin?['site','admin','growth']:['site','premium','growth','hosting-comparison'] as $css)echo '<link rel="stylesheet" href="/assets/css/'.$css.'.css">';
    echo '</head><body'.($admin?' class="admin-body"':'').'><main class="'.($admin?'admin-main':'container').'" id="admin-main">';
}
function admin_head(string $title,string $view,array $user):void {hcv_head($title,true);echo '<h1>'.esc($title).'</h1>';show_flash();}
function admin_foot():void {echo '</main></body></html>';}
function hcv_offers():array {
    $policy=['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>'America/Chicago','alignment'=>'day-of-month','review_ttl_seconds'=>900];
    $offers=[];foreach([[101,1200,'monthly','a','c','d'],[102,12000,'annually','b','e','f']]as[$id,$price,$cadence,$image,$publication,$sha])$offers[]=['product_id'=>$id,'publication_id'=>str_repeat($publication,32),'publication_sha256'=>str_repeat($sha,64),'currency'=>'USD','cadence'=>$cadence,'amount_cents'=>$price,'title'=>'Hosting '.$cadence.' <img src=x onerror=alert(1)>','summary'=>'Independent retail choice','support'=>'Studio support','image_id'=>str_repeat($image,32),'inclusions'=>['One website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'checkout_policy'=>$policy];
    return $offers;
}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/health'){echo (string)getenv('CWP_VARIANTS_HEALTH');exit;}
if(preg_match('#\A/assets/css/(site|admin|premium|growth|hosting-comparison)\.css\z#',(string)$path,$m)){header('Content-Type: text/css');readfile(CWP_CODE_ROOT.'/assets/css/'.$m[1].'.css');exit;}
if($path==='/media/index.php'){
    if(!in_array($_GET['id']??'',[str_repeat('a',32),str_repeat('b',32)],true)){http_response_code(404);exit;}
    header('Content-Type: image/svg+xml');echo '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="144"><rect width="240" height="144" fill="'.($_GET['id']===str_repeat('a',32)?'#334477':'#997733').'"/></svg>';exit;
}
if($path==='/__seed'){
    $mode=$_GET['mode']??'';$_SESSION=['csrf'=>bin2hex(random_bytes(32)),'counts'=>[],'mode'=>$mode];
    if(in_array($mode,['owner','support','owner-contact-null','owner-client-null','owner-support-null'],true))$_SESSION['role']='owner';if($mode==='staff')$_SESSION['role']='administrator';if($mode==='client')$_SESSION['client_id']=1;if($mode==='support')$_SESSION['resolved_support']=true;if($mode==='owner-contact-null')$_SESSION['contact_id']=null;if($mode==='owner-client-null')$_SESSION['client_id']=null;if($mode==='owner-support-null')$_SESSION['support']=null;
    header('Content-Type: application/json');echo json_encode(['csrf'=>csrf()]);exit;
}
if($path==='/__scenario'){
    $name=$_GET['name']??'';if(!in_array($name,['','unavailable','pdo','edit-source','edit-child'],true)){http_response_code(400);exit;}
    if($name==='edit-source')hcv_store(static function(array &$s){$s['products'][101]['revision']++;});
    elseif($name==='edit-child')hcv_store(static function(array &$s){$s['products'][103]['amount_cents']=7777;$s['products'][103]['media_id']=str_repeat('b',32);$s['products'][103]['revision']++;});
    else $_SESSION['scenario']=$name;echo '{"set":true}';exit;
}
if($path==='/__state'){header('Content-Type: application/json');echo json_encode(['counts'=>(object)($_SESSION['counts']??[]),'model'=>hcv_store()]);exit;}
if($path==='/hosting'){hcv_head('Hosting comparison',false);hosting_catalog_comparison(['offers'=>hcv_offers(),'has_more'=>false,'unavailable'=>false]);admin_foot();exit;}
if($path==='/portal/index.php'){
    foreach(hcv_offers()as$offer)if(($_GET['hosting']??'')==='checkout'&&($_GET['offer']??'')===$offer['publication_id']&&($_GET['offer_sha256']??'')===$offer['publication_sha256']){header('Content-Type: application/json');echo json_encode(['scope'=>'Modeled destination only; no checkout authority','product_id'=>$offer['product_id'],'amount_cents'=>$offer['amount_cents'],'cadence'=>$offer['cadence']]);exit;}
    http_response_code(404);exit;
}
if($path==='/owner'){
    $error='';require CWP_CODE_ROOT.'/admin/controllers/actions.php';$user=require_admin();
    $view=is_string($_GET['view']??null)?$_GET['view']:'catalog';$permission=staff_view_permission($view);
    if($permission!==null&&!staff_can($permission,$user)){http_response_code(403);exit;}
    if($view!=='catalog'){http_response_code(404);exit;}catalog_admin_render('catalog',$user,$error);
}
http_response_code(404);
