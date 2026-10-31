<?php
/** Local-only synthetic rendering of actual templates. Never a production login or packaged site file. */
declare(strict_types=1);
if(getenv('CWP_UI_FIXTURE')!=='1'||($_SERVER['REQUEST_METHOD']??'GET')!=='GET'||(PHP_SAPI!=='cli'&&!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))){http_response_code(404);exit;}
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');define('CWP_ROOT',__DIR__.'/nonexistent-test-webroot');define('CWP_VERSION','1.26.0 · DEVELOPMENT PREVIEW');
set_error_handler(static function(int $n,string $s,string $f,int $l):never {throw new ErrorException($s,0,$n,$f,$l);});
require CWP_CODE_ROOT.'/includes/security/input.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';require CWP_CODE_ROOT.'/app/providers/workspace.php';
$_POST=[];$_SESSION=[];
function fixture_state():string{return is_string($_GET['scenario']??null)?$_GET['scenario']:'connected';}
function admin_url(string $view='dashboard',array $params=[]):string{return '/tests/provider_workspace_fixture.php?'.http_build_query(['view'=>$view,'scenario'=>fixture_state()]+$params);}
function page_url(string $page='home',array $params=[]):string{return '/tests/provider_workspace_fixture.php?'.http_build_query(['view'=>'public','page'=>$page]+$params);}
function site_url(string $path=''):string{return '/fixture-disabled/'.ltrim($path,'/');}
function asset(string $file):string{if($file==='logo.png')return '/site/images/branding/logo.png';if($file==='favicon.png')return '/site/images/icons/favicon.png';return '/site/assets/'.(str_ends_with($file,'.css')?'css/':'js/').$file;}
function icon(string $name):string{return '<span aria-hidden="true">◇</span>';}
function show_flash():void{echo '<p class="notice">DEVELOPMENT PREVIEW · Synthetic data · No provider connection or saved changes</p>';}
function csrf_field():string{return '<input type="hidden" name="csrf" value="synthetic-only">';}
function staff_can(string $permission,?array $user=null):bool{return true;}
function staff_view_permission(string $key):?string{return null;}
function mail_accounts_access(array $u):bool{return false;}
function mail_notices_access(array $u):bool{return false;}
function table(string $name):string{return $name;}
function one(string $sql,array $args=[]):?array{return ['n'=>0];}
function all(string $sql,array $args=[]):array {
    if(str_contains($sql,'FROM catalog_products'))return fixture_state()==='empty'?[]:[['id'=>12,'sku'=>'20I-701','title'=>'Starter hosting (synthetic)','status'=>'draft','term'=>'month','amount_cents'=>1500,'setup_cents'=>0,'revision'=>1,'family'=>'hosting']];
    throw new LogicException('Unexpected fixture query');
}
function provider_settings_manager():array{return ['id'=>1];}
function provider_settings_profile():?array {
    if(fixture_state()==='unconfigured')return null;
    return ['id'=>str_repeat('a',32),'revision'=>2,'label'=>'My 20i account','enabled'=>fixture_state()==='paused'?0:1,'has_credentials'=>true];
}
function provider_checks_history(string $id,int $page):array {
    if(!in_array(fixture_state(),['connected','stale'],true))return [];
    return [['profile_revision'=>fixture_state()==='stale'?1:2,'result_code'=>'connected','state'=>'complete','started_at'=>'2026-10-30 12:00:00','actor_name'=>'Synthetic owner']];
}
function provider_checks_message(?string $result):string{return 'The saved package-list read completed. This does not verify every provider feature.';}
function provider_settings_history(string $id,int $page):array{return [];}
function provider_inventory_ready():bool{return fixture_state()!=='unconfigured';}
function provider_cloud_site_request_confirmation_render():void{throw new LogicException('Unrequested advanced workspace rendered');}
function support_current():?array{return null;}
function provider_catalog_import_owner():array{return ['id'=>1];}
function provider_catalog_import_ready():bool{return fixture_state()!=='setup';}
function provider_package_types_ready():bool{return fixture_state()!=='catalog-setup';}
function provider_catalog_import_preview(int $page,string $source):array {
    if($source!=='package.types')throw new RuntimeException('Synthetic fixture only provides the package-type catalog, not existing hosting accounts.');
    return ['observed_at'=>'2026-10-30 12:00:00','hash'=>str_repeat('c',64),'unnamed'=>0,'page'=>$page,'has_more'=>false,'total_types'=>fixture_state()==='empty'?0:2,
        'types'=>fixture_state()==='empty'?[]:[['type_ref'=>'701','name'=>'Starter hosting (synthetic)','product_id'=>12,'earlier_credentials'=>false,'origin_operation'=>'package.types','origin_name'=>'Starter hosting'],['type_ref'=>'702','name'=>'Business hosting (synthetic)','product_id'=>null,'earlier_credentials'=>false]]];
}
function catalog_families():array{return ['web-design'=>'Web design','maintenance'=>'Website care','addon'=>'Additional service','software'=>'Software','hosting'=>'Hosting','domain'=>'Domain','vps'=>'VPS'];}
function catalog_terms():array{return ['once'=>'One-time service','month'=>'Monthly','year'=>'Annual'];}
function studio_hidden(string $n,mixed $v):void{echo '<input type="hidden" name="'.esc($n).'" value="'.esc($v).'">';}
function gint(array $data,string $key,int $min,int $max,int $default=0):int{return isset($data[$key])&&is_scalar($data[$key])?max($min,min($max,(int)$data[$key])):$default;}
function gmoney(?int $c):string{return $c===null?'':number_format($c/100,2,'.','');}
function catalog_ready():bool{return true;}
function staff_ready():bool{return true;}
function studio_ready():bool{return true;}
function provider_catalog_variant_admin_view(array $p,array $u):void{}
function fixture_function(string $path,string $name):void {
    $tokens=token_get_all(file_get_contents($path));
    for($i=0;$i<count($tokens);$i++){
        if(!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION)continue;
        $j=$i+1;while(is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$j++;
        if(!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||$tokens[$j][1]!==$name)continue;
        $body='';$depth=0;$started=false;
        for($k=$i;$k<count($tokens);$k++){$token=$tokens[$k];$body.=is_array($token)?$token[1]:$token;if($token==='{'){$depth++;$started=true;}elseif($token==='}'&&--$depth===0&&$started)break;}
        eval($body);return;
    }throw new LogicException('Actual renderer not found');
}
$user=['id'=>1,'name'=>'Test owner'];$view=$_GET['view']??'provider-settings';
if($view==='catalog'){
    require CWP_CODE_ROOT.'/admin/views/catalog_import.php';
    require CWP_CODE_ROOT.'/app/billing/catalog_browse.php';
    fixture_function(CWP_CODE_ROOT.'/admin/controllers/catalog.php','catalog_admin_render');
    catalog_admin_render('catalog',$user,'');
}
admin_head('20i workspace','provider-settings',$user);
require CWP_CODE_ROOT.'/admin/views/providers.php';
admin_foot();
