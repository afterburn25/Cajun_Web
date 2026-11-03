<?php
declare(strict_types=1);
/** Loopback-only HTTP wiring fixture. Business/session lookups below are explicit models, never application SQL. */
if (PHP_SAPI !== 'cli-server' || getenv('CWP_SUPPORT_STACKCP_HTTP_FIXTURE') !== '1'
    || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(404); exit; }
$root=realpath((string)getenv('CWP_SUPPORT_STACKCP_SOURCE'));
$private=realpath((string)getenv('CWP_SUPPORT_STACKCP_TEMP'));
if (!$root || !$private || !is_file($root.'/site/portal/controllers/support_stackcp.php') || !is_dir($private.'/sessions')) { http_response_code(500); exit; }
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$root.'/site');define('CWP_ROOT',$private);
require CWP_CODE_ROOT.'/includes/security/input.php';
require CWP_CODE_ROOT.'/includes/security/csrf.php';
require CWP_CODE_ROOT.'/includes/security/http.php';
ini_set('display_errors','0');session_save_path($private.'/sessions');session_name('cwp_support_stackcp_http');session_start();security_headers();

/** Execute exact named production function bodies, without loading their database-bearing includes. */
function ssh_load(string $path,array $names):void {
    $tokens=token_get_all((string)file_get_contents(CWP_CODE_ROOT.'/'.$path),TOKEN_PARSE);$found=[];
    for($i=0;$i<count($tokens);$i++){
        if(!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION)continue;
        $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(!isset($tokens[$j])||!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||!in_array($tokens[$j][1],$names,true))continue;
        $name=$tokens[$j][1];$code='';$depth=0;$opened=false;
        for($k=$i;$k<count($tokens);$k++){
            $token=$tokens[$k];$code.=is_array($token)?$token[1]:$token;
            if($token==='{'||(is_array($token)&&in_array($token[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true))){$depth++;$opened=true;}
            elseif($token==='}'){$depth--;if($opened&&$depth===0)break;}
        }
        if(isset($found[$name])||!$opened||$depth!==0)throw new LogicException('Ambiguous fixture source.');
        eval($code);$found[$name]=true;$i=$k;
    }
    if(count($found)!==count($names))throw new LogicException('Missing fixture source.');
}

ssh_load('includes/layout/admin.php',['admin_input','admin_check','admin_action']);
ssh_load('app/providers/stackcp_prerequisite_views.php',['provider_stackcp_post_value','provider_stackcp_hidden','provider_stackcp_password']);
ssh_load('app/providers/stackcp_launch_views.php',['provider_stackcp_launch_state_label']);
ssh_load('app/providers/support_stackcp_views.php',['provider_support_stackcp_page_number','provider_support_stackcp_route','provider_support_stackcp_form_values','provider_support_stackcp_retry_review','provider_support_stackcp_permission_render','provider_support_stackcp_owner_render','provider_support_stackcp_launch_render']);
ssh_load('portal/controllers/support_stackcp.php',['provider_support_stackcp_requested','provider_support_stackcp_route_preflight','provider_support_stackcp_navigation','provider_support_stackcp_staff_action','provider_support_stackcp_staff_render']);
ssh_load('admin/controllers/provider_support_stackcp.php',['provider_support_stackcp_positive','provider_support_stackcp_admin_action']);
ssh_load('app/support/sessions.php',['support_current','support_csrf_token','support_csrf_field','support_check_csrf','support_binding']);
ssh_load('app/providers/support_stackcp_permissions.php',['provider_support_stackcp_actor']);
ssh_load('admin/controllers/support.php',['support_banner','support_unavailable']);
ssh_load('app/clients/identity.php',['studio_client']);
require CWP_CODE_ROOT.'/app/providers/stackcp_launch_http.php';

function ssh_count(string $key):void {$_SESSION['counts'][$key]=(int)($_SESSION['counts'][$key]??0)+1;}
function provider_stackcp_guard(bool $tx=false):void {ssh_count('modeled_guards');}
function provider_stackcp_launch_hex(string $value,int $length=32):void {if(preg_match('/\A[a-f0-9]{'.$length.'}\z/',$value)!==1)throw new InvalidArgumentException('Malformed identifier.');}
function provider_support_stackcp_principal(array $actor):array {return $actor;}
function studio_ready():bool {return true;}
function staff_can(string $permission,?array $user=null):bool {return in_array($permission,$user['permissions']??[],true);}
/** Current SQL/support policy lookup is modeled; the production actor validates its returned identities. */
function support_check(string $id,bool $touch=true):?array {
    ssh_count('support_checks');
    if(($_SESSION['uid']??null)!==7||$id!==str_repeat('a',32)||($_SESSION['ended']??false)||($_SESSION['expires']??0)<=time()||support_binding()===''||($_SESSION['mode']??'')==='binding-swapped')return null;
    return ['session'=>['id'=>$id,'actor_id'=>7,'client_id'=>1,'actor_version'=>str_repeat('7',32),'client_version'=>str_repeat('1',32),'binding_hash'=>support_binding(),'expires_at'=>$_SESSION['expires']],
        'actor'=>['id'=>7,'name'=>'Staff <img src=x onerror=alert(1)>','auth_version'=>str_repeat('7',32),'permissions'=>($_SESSION['mode']??'')==='no-permission'?['support.login']:['support.login','support.stackcp']],
        'client'=>['id'=>1,'name'=>'Client <script>bad()</script>','auth_version'=>str_repeat('1',32),'active'=>1]];
}
function support_resolve(string $id):?array {ssh_count('resolves');return support_check($id);}
function support_record(string $action,string $type='',string $object='',string $outcome='allowed'):void {ssh_count('support_audits');}
function provider_settings_manager(bool $ready=true):array {
    ssh_count('owner_checks');
    if(($_SESSION['role']??'')!=='owner'||support_current()!==null||array_key_exists('support',$_GET)||array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('An active owner account is required for provider settings.');
    return ['id'=>1,'role'=>'owner'];
}
function require_admin():array {return provider_settings_manager();}
function staff_action_permission(string $action):?string {return 'providers.manage';}
function staff_require(string $permission):void {provider_settings_manager();}
function db():object {return new class {public function inTransaction():bool {return false;}};}
function query(mixed ...$args):never {throw new LogicException('SQL is forbidden in this HTTP fixture.');}
function one(mixed ...$args):never {throw new LogicException('SQL is forbidden in this HTTP fixture.');}
function report_error(Throwable $error):string {ssh_count('reported_errors');return 'synthetic-reference';}
function flash(string $message,string $type='success'):void {$_SESSION['flash']=$message;}
function site_url(string $path):string {return '/'.$path;}
function studio_url(array $params=[]):string {return '/portal/index.php?'.http_build_query(['support'=>str_repeat('a',32)]+$params);}
function admin_url(string $view,array $params=[]):string {return '/owner?'.http_build_query(['view'=>$view]+$params);}
function site_head(string $title,string $area=''):void {echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title>';foreach(['site','premium','growth'] as $css)echo '<link rel="stylesheet" href="/css/'.$css.'.css">';echo '</head><body>';}
function site_foot(bool $value=false):void {echo '</body></html>';}
function login_head(string $title):void {site_head($title);}
function login_foot():void {site_foot();}
function ssh_item(int $page=1,int $staff=7):array {return ['profile_id'=>str_repeat('b',32),'package_id'=>$page===2?2502:2501,'package_name'=>$page===2?'Synthetic second hosting':'Hosting <script>bad()</script>','link_id'=>str_repeat($page===2?'e':'b',32),'client_name'=>'Client <script>bad()</script>','staff_id'=>$staff,'staff_name'=>'Staff <img src=x onerror=alert(1)>','stack_identity'=>'stack-user:12','permission_id'=>str_repeat($page===2?'f':'c',32),'review_proof'=>str_repeat('d',64),'enabled'=>($_SESSION['mode']??'')==='stale-owner','can_enable'=>true,'stale'=>($_SESSION['mode']??'')==='stale-owner'];}
function provider_support_stackcp_owner_snapshot(int $page,?int $staff=null):array {ssh_count('owner_snapshots');return ['ready'=>($_SESSION['mode']??'')!=='cold-owner','items'=>[ssh_item(1,$staff??7)],'has_more'=>false,'staff_choices'=>[['id'=>7,'name'=>'Support <staff>'],['id'=>8,'name'=>'Other staff']],'selected_staff_id'=>$staff??7];}
function provider_support_stackcp_staff_snapshot(string $id,int $page,?string $link):array {
    provider_support_stackcp_actor($id);ssh_count('staff_snapshots');
    $item=$link===str_repeat('e',32)?ssh_item(2):ssh_item($page);
    return ['ready'=>true,'items'=>($_SESSION['mode']??'')==='revoked'||($link!==null&&$link!==$item['link_id'])?[]:[$item],'has_more'=>$page===1&&$link===null];
}
function provider_support_stackcp_review(string $permission,string $id):array {
    provider_support_stackcp_actor($id);ssh_count('reviews');
    if(!in_array($permission,[str_repeat('c',32),str_repeat('f',32)],true))throw new RuntimeException('Unavailable.');
    $review=['available'=>true,'admission_id'=>bin2hex(random_bytes(16)),'expires_epoch'=>min(time()+300,$_SESSION['expires']),'review_proof'=>bin2hex(random_bytes(32)),'previous_unknown'=>($_SESSION['mode']??'')==='unknown'];
    $_SESSION['reviews'][$review['admission_id']]=$review+['permission_id'=>$permission,'support_id'=>$id];return $review;
}
function provider_support_stackcp_history(string $permission,string $id):array {provider_support_stackcp_actor($id);return ['items'=>empty($_SESSION['consumed'])?[]:[['created_epoch'=>1760000000,'state'=>'handoff_attempted']]];}
/** Explicit modeled single-use boundary: actual journal, gateway and SQL belong to native acceptance. */
function provider_support_stackcp_open(string $admission,string $permission,string $id,int $expiry,string $proof,bool $confirmed,callable $deliver):array {
    provider_support_stackcp_actor($id);ssh_count('opens');$original=$_SESSION['reviews'][$admission]??null;
    if(($_SESSION['mode']??'')==='revoked'||!$original||$original['permission_id']!==$permission||$original['support_id']!==$id||$original['expires_epoch']!==$expiry||$original['review_proof']!==$proof||!$confirmed)throw new RuntimeException('Review and confirm the original approval.');
    if(isset($_SESSION['consumed'][$admission]))return ['state'=>'handoff_attempted'];
    if($expiry<=time())throw new RuntimeException('This original review expired.');
    $_SESSION['consumed'][$admission]=true;ssh_count('synthetic_deliveries');$deliver('https://www.stackcp.com/login/sso?PHPSESSID=synthetic-session%2Bonly');throw new LogicException('Delivery must exit.');
}
function provider_support_stackcp_prepare(string $password):void {provider_settings_manager();if($password!=='Synthetic owner password')throw new InvalidArgumentException('Your current password is not correct.');ssh_count('prepare_writes');}
function provider_support_stackcp_permission_change(string $profile,int $package,int $staff,string $proof,bool $enabled,bool $confirmed,string $password):void {
    provider_settings_manager();ssh_count('permission_calls');
    if($password!=='Synthetic owner password')throw new InvalidArgumentException('Your current password is not correct.');
    if(!$confirmed||$profile!==str_repeat('b',32)||$package!==2501||!in_array($staff,[7,8],true)||$proof!==str_repeat('d',64)||$enabled===(($_SESSION['mode']??'')==='stale-owner'))throw new RuntimeException('Review the original owner permission.');
    ssh_count('permission_writes');$_SESSION['permission_target']=$staff;$_SESSION['permission_enabled']=$enabled;
}

$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(in_array($path,['/css/site.css','/css/premium.css','/css/growth.css'],true)){header('Content-Type: text/css');readfile(CWP_CODE_ROOT.'/assets'.$path);exit;}
if($path==='/__seed'){
    $mode=$_GET['mode']??'';$_SESSION=['mode'=>$mode,'csrf'=>bin2hex(random_bytes(32)),'counts'=>[],'reviews'=>[],'consumed'=>[],'expires'=>time()+900];
    if(!in_array($mode,['anonymous','client','owner','stale-owner','cold-owner','staff'],true)){$_SESSION['uid']=7;$_SESSION['support_binding']=str_repeat('7',64);}
    if(in_array($mode,['owner','stale-owner','cold-owner'],true))$_SESSION['role']='owner';if($mode==='staff')$_SESSION['role']='administrator';
    if(in_array($mode,['client','mixed-client'],true)){$_SESSION['client_id']=99;$_SESSION['client_auth']=str_repeat('9',32);}
    if($mode==='contact-null')$_SESSION['contact_id']=null;if($mode==='contact-auth-empty')$_SESSION['contact_auth']='';if($mode==='uid-null')$_SESSION['uid']=null;if($mode==='expired')$_SESSION['expires']=time()-1;
    header('Content-Type: application/json');echo json_encode(['seeded'=>true,'csrf'=>csrf()]);exit;
}
if($path==='/__invalidate'){
    $mode=$_GET['mode']??'';if(!in_array($mode,['expired','ended','binding-swapped','revoked'],true)){http_response_code(400);exit;}
    $_SESSION['mode']=$mode;if($mode==='expired')$_SESSION['expires']=time()-1;if($mode==='ended')$_SESSION['ended']=true;echo 'Changed model only';exit;
}
if($path==='/__state'){header('Content-Type: application/json');echo json_encode(['counts'=>(object)($_SESSION['counts']??[]),'consumed'=>count($_SESSION['consumed']??[]),'permission_target'=>$_SESSION['permission_target']??null,'permission_enabled'=>$_SESSION['permission_enabled']??null]);exit;}
if($path==='/health'){echo (string)getenv('CWP_SUPPORT_STACKCP_HEALTH');exit;}
$error='';
if($path==='/portal/index.php'){
    provider_support_stackcp_route_preflight();
    // Exact bounded production router segment owns support resolution, POST dispatcher and final renderer.
    $router=(string)file_get_contents(CWP_CODE_ROOT.'/portal/controllers/router.php');
    $start=strpos($router,"\$error='';\$supportRequested=");$end=strpos($router,"if(isset(\$_GET['activity']))",$start?:0);
    if($start===false||$end===false||substr_count($router,"\$error='';\$supportRequested=")!==1)throw new LogicException('Portal router boundary changed.');
    eval(substr($router,$start,$end-$start));throw new LogicException('Support route must exit.');
}
if($path==='/owner'){
    require CWP_CODE_ROOT.'/admin/controllers/actions.php';
    try{provider_settings_manager();}catch(RuntimeException){http_response_code(403);echo 'Owner required';exit;}
    site_head('Staff hosting support');if($error!=='')echo '<p role="alert">'.esc($error).'</p>';provider_support_stackcp_owner_render();site_foot();exit;
}
http_response_code(404);echo 'Unavailable';
