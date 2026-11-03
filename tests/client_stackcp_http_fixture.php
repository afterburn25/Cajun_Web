<?php
declare(strict_types=1);
/** Loopback-only HTTP wiring fixture. Business/session lookups below are explicit models, never application SQL. */
if (PHP_SAPI !== 'cli-server' || getenv('CWP_CLIENT_STACKCP_HTTP_FIXTURE') !== '1'
    || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(404); exit; }
$root=realpath((string)getenv('CWP_CLIENT_STACKCP_SOURCE'));
$private=realpath((string)getenv('CWP_CLIENT_STACKCP_TEMP'));
if (!$root || !$private || !is_file($root.'/site/portal/controllers/client_stackcp.php') || !is_dir($private.'/sessions')) { http_response_code(500); exit; }
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$root.'/site');define('CWP_ROOT',$private);
require CWP_CODE_ROOT.'/includes/security/input.php';
require CWP_CODE_ROOT.'/includes/security/csrf.php';
require CWP_CODE_ROOT.'/includes/security/http.php';
ini_set('display_errors','0');session_save_path($private.'/sessions');session_name('cwp_client_stackcp_http');session_start();security_headers();

/** Execute exact named production function bodies, without loading their database-bearing includes. */
function cch_load(string $path,array $names):void {
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
cch_load('includes/layout/admin.php',['admin_input','admin_check','admin_action']);
cch_load('app/providers/stackcp_prerequisite_views.php',['provider_stackcp_post_value','provider_stackcp_hidden','provider_stackcp_password']);
cch_load('app/providers/stackcp_launch_views.php',['provider_stackcp_launch_state_label']);
cch_load('app/providers/client_stackcp_views.php',['provider_client_stackcp_session_blocked','provider_client_stackcp_page_number','provider_client_stackcp_form_values','provider_client_stackcp_retry_review','provider_client_stackcp_permission_render','provider_client_stackcp_owner_render','provider_client_stackcp_launch_render']);
cch_load('portal/controllers/client_stackcp.php',['provider_client_stackcp_requested','provider_client_stackcp_route_preflight','provider_client_stackcp_client_action','provider_client_stackcp_client_render']);
cch_load('admin/controllers/provider_client_stackcp.php',['provider_client_stackcp_admin_action']);
cch_load('admin/controllers/providers.php',['provider_inventory_admin_action']);
require CWP_CODE_ROOT.'/app/providers/stackcp_launch_http.php';

function cch_count(string $key):void {$_SESSION['counts'][$key]=(int)($_SESSION['counts'][$key]??0)+1;}
function support_current():?array {return $GLOBALS['cwp_support']??null;}
function studio_ready():bool {return true;}
function studio_client():?array {
    cch_count('normalizations');
    // A deliberately hostile normalizer proves the earlier raw-marker guard really runs first.
    unset($_SESSION['contact_id'],$_SESSION['contact_auth'],$_SESSION['uid']);
    return isset($_SESSION['client_id'])?['id'=>$_SESSION['client_id'],'active'=>1,'auth_version'=>str_repeat('a',32)]:null;
}
function provider_settings_manager():array {
    cch_count('owner_checks');
    if(($_SESSION['role']??'')!=='owner'||support_current()!==null||array_key_exists('support',$_GET)||array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('An active owner account is required for provider settings.');
    return ['id'=>1,'role'=>'owner'];
}
function require_admin():array {return provider_settings_manager();}
function staff_action_permission(string $action):?string {return 'providers.manage';}
function staff_require(string $permission):void {provider_settings_manager();}
function db():object {return new class {public function inTransaction():bool {return false;}};}
function query(mixed ...$args):never {throw new LogicException('SQL is forbidden in this HTTP fixture.');}
function one(mixed ...$args):never {throw new LogicException('SQL is forbidden in this HTTP fixture.');}
function report_error(Throwable $error):string {cch_count('reported_errors');return 'synthetic-reference';}
function flash(string $message,string $type='success'):void {$_SESSION['flash']=$message;}
function studio_url(array $params=[]):string {return '/portal'.($params?'?'.http_build_query($params):'');}
function admin_url(string $view,array $params=[]):string {return '/owner?'.http_build_query(['view'=>$view]+$params);}
function site_head(string $title,string $area=''):void {echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title><body>';}
function site_foot(bool $value=false):void {echo '</body></html>';}
function cch_item(int $page=1):array {return ['profile_id'=>str_repeat('b',32),'package_id'=>$page===2?2502:2501,'package_name'=>$page===2?'Synthetic second hosting':'Synthetic hosting','link_id'=>str_repeat($page===2?'e':'a',32),'client_name'=>'Synthetic primary client','stack_identity'=>'stack-user:12','permission_id'=>str_repeat($page===2?'f':'c',32),'review_proof'=>str_repeat('d',64),'enabled'=>false,'can_enable'=>true,'stale'=>false];}
function provider_client_stackcp_owner_snapshot(int $page):array {cch_count('owner_snapshots');return ['ready'=>true,'items'=>[cch_item()],'has_more'=>false];}
function provider_client_stackcp_client_snapshot(int $clientId,int $page,?string $link):array {
    cch_count('client_snapshots');if($clientId!==($_SESSION['client_id']??null))throw new RuntimeException('Sign in directly.');
    $item=$link===str_repeat('e',32)?cch_item(2):cch_item($page);
    return ['ready'=>true,'items'=>$clientId===1&&($link===null||$link===$item['link_id'])?[$item]:[],'has_more'=>$page===1&&$link===null];
}
function provider_client_stackcp_review(string $permission,int $clientId):array {
    cch_count('reviews');if($clientId!==1||!in_array($permission,[str_repeat('c',32),str_repeat('f',32)],true))throw new RuntimeException('Unavailable.');
    $review=['available'=>true,'admission_id'=>bin2hex(random_bytes(16)),'expires_epoch'=>time()+300,'review_proof'=>bin2hex(random_bytes(32)),'previous_unknown'=>false];
    $_SESSION['reviews'][$review['admission_id']]=$review+['permission_id'=>$permission];return $review;
}
function provider_client_stackcp_history(string $permission,int $clientId):array {
    cch_count('histories');return ['items'=>empty($_SESSION['consumed'])?[]:[['created_epoch'=>1760000000,'state'=>'handoff_attempted']]];
}
/** Explicit modeled business boundary: this fixture does not claim native authority or single-send SQL acceptance. */
function provider_client_stackcp_open(string $admission,string $permission,int $clientId,int $expiry,string $proof,bool $confirmed,callable $deliver):array {
    cch_count('opens');
    if(provider_client_stackcp_session_blocked()||$clientId!==1||($_SESSION['client_id']??null)!==1||!in_array($permission,[str_repeat('c',32),str_repeat('f',32)],true))throw new RuntimeException('This hosting panel is unavailable.');
    $original=$_SESSION['reviews'][$admission]??null;
    if(!$original||$original['permission_id']!==$permission||$original['expires_epoch']!==$expiry||$original['review_proof']!==$proof||!$confirmed)throw new RuntimeException('Review and confirm the original approval.');
    if(isset($_SESSION['consumed'][$admission]))return ['state'=>'handoff_attempted'];
    $_SESSION['consumed'][$admission]=true;cch_count('synthetic_deliveries');$deliver('https://www.stackcp.com/login/sso?PHPSESSID=synthetic-session%2Bonly');
    throw new LogicException('Delivery must exit.');
}
function provider_client_stackcp_prepare(string $password):void {provider_settings_manager();if($password!=='Synthetic owner password')throw new InvalidArgumentException('Your current password is not correct.');cch_count('prepare_writes');}
function provider_client_stackcp_permission_change(string $profile,int $package,string $proof,bool $enabled,bool $confirmed,string $password):void {
    provider_settings_manager();cch_count('permission_calls');
    if($password!=='Synthetic owner password')throw new InvalidArgumentException('Your current password is not correct.');
    if(!$confirmed||$profile!==str_repeat('b',32)||$package!==2501||$proof!==str_repeat('d',64))throw new RuntimeException('Review the original owner permission.');
    cch_count('permission_writes');
}

$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/__seed'){
    $mode=$_GET['mode']??'';$_SESSION=['csrf'=>bin2hex(random_bytes(32)),'counts'=>[],'reviews'=>[],'consumed'=>[]];
    if(in_array($mode,['client','other','contact-null','contact-auth-empty','uid-null','resolved-support'],true))$_SESSION['client_id']=$mode==='other'?2:1;
    if($mode==='owner')$_SESSION['role']='owner';if($mode==='staff')$_SESSION['role']='administrator';
    if($mode==='contact-null')$_SESSION['contact_id']=null;if($mode==='contact-auth-empty')$_SESSION['contact_auth']='';if($mode==='uid-null')$_SESSION['uid']=null;if($mode==='resolved-support')$_SESSION['resolved_support']=true;
    header('Content-Type: application/json');echo json_encode(['seeded'=>true,'csrf'=>csrf()]);exit;
}
if(!empty($_SESSION['resolved_support']))$GLOBALS['cwp_support']=['client'=>['id'=>1]];
if($path==='/__state'){header('Content-Type: application/json');echo json_encode(['counts'=>(object)($_SESSION['counts']??[]),'consumed'=>count($_SESSION['consumed']??[])]);exit;}
if($path==='/health'){echo (string)getenv('CWP_CLIENT_STACKCP_HEALTH');exit;}
$error='';
if($path==='/portal'){
    provider_client_stackcp_route_preflight();
    $client=studio_client();$supportRequested=array_key_exists('support',$_GET);$supportId='';$jobId=0;
    require CWP_CODE_ROOT.'/portal/controllers/actions.php';
    if(!$client){http_response_code(401);echo 'Primary client sign-in required';exit;}
    provider_client_stackcp_client_render($client,$error);
}
if($path==='/owner'){
    require CWP_CODE_ROOT.'/admin/controllers/actions.php';
    try{provider_settings_manager();}catch(RuntimeException){http_response_code(403);echo 'Owner required';exit;}
    site_head('Client hosting access');if($error!=='')echo '<p role="alert">'.esc($error).'</p>';provider_client_stackcp_owner_render();site_foot();exit;
}
http_response_code(404);echo 'Unavailable';
