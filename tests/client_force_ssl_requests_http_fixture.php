<?php
declare(strict_types=1);
/** Loopback-only actual UI/dispatcher fixture. Identity and business persistence below are explicit models. */
if (PHP_SAPI !== 'cli-server' || getenv('CWP_HTTPS_REQUEST_HTTP_FIXTURE') !== '1'
    || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(404); exit; }
$root=realpath((string)getenv('CWP_HTTPS_REQUEST_SOURCE'));
$private=realpath((string)getenv('CWP_HTTPS_REQUEST_TEMP'));
if (!$root || !$private || !is_file($root.'/site/portal/controllers/force_ssl_requests.php') || !is_dir($private.'/sessions')) { http_response_code(500); exit; }
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$root.'/site');define('CWP_ROOT',$private);
require CWP_CODE_ROOT.'/includes/security/input.php';
require CWP_CODE_ROOT.'/includes/security/csrf.php';
require CWP_CODE_ROOT.'/includes/security/http.php';
ini_set('display_errors','0');session_save_path($private.'/sessions');session_name('cwp_https_request_http');session_start();security_headers();

/** Extract and execute exact function bodies; no rewritten production predicate or database bootstrap. */
function cfs_load(string $path, ?array $names=null):void {
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
cfs_load('app/providers/force_ssl_requests_views.php');
cfs_load('portal/controllers/force_ssl_requests.php');
cfs_load('admin/controllers/force_ssl_requests.php');
cfs_load('app/providers/force_ssl_requests.php',['provider_force_ssl_requests_session_blocked']);

/** Execute the actual router's early block, while retaining proof that it precedes normalization. */
function cfs_router_preflight():void {
    $source=(string)file_get_contents(CWP_CODE_ROOT.'/portal/controllers/router.php');
    $start='$httpsRequestRoute=';$end="require_once CWP_CODE_ROOT.'/portal/controllers/directory_listing.php';";
    if(substr_count($source,$start)!==1||substr_count($source,$end)!==1)throw new LogicException('Ambiguous router boundary.');
    $a=strpos($source,$start);$b=strpos($source,$end);
    if($a===false||$b===false||$b<$a||$b>strpos($source,'$client=studio_client()')||$b>strpos($source,'$context=support_resolve('))throw new LogicException('Raw route gate moved after normalization.');
    eval(substr($source,$a,$b-$a));
}

function cfs_count(string $key):void {$_SESSION['counts'][$key]=(int)($_SESSION['counts'][$key]??0)+1;}
/** Isolated synthetic JSON store, not application SQL or an implementation of production transactions. */
function cfs_store(?callable $change=null):array {
    $handle=fopen(CWP_ROOT.'/model.json','c+');if(!$handle||!flock($handle,LOCK_EX))throw new RuntimeException('Model storage unavailable.');
    try{
        $raw=stream_get_contents($handle);$s=$raw!==''?json_decode($raw,true,512,JSON_THROW_ON_ERROR):['ready'=>false,'items'=>[],'proposals'=>[],'calls'=>[],'writes'=>['prepare'=>0,'request'=>0,'cancel'=>0,'decline'=>0,'admission'=>0,'check'=>0,'recover'=>0],'provider_calls'=>0];
        if($change!==null){$change($s);rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($s,JSON_THROW_ON_ERROR));fflush($handle);}
        return $s;
    }finally{flock($handle,LOCK_UN);fclose($handle);}
}
function cfs_call(string $name,array $args):void {cfs_count($name);cfs_store(static function(array &$s)use($name,$args){$s['calls'][]=['name'=>$name,'args'=>$args];});}
function support_current():?array {return $GLOBALS['cwp_support']??null;}
function studio_ready():bool {return true;}
function studio_client():?array {
    cfs_count('normalizations');
    // Hostile normalization makes the actual earlier raw-marker guard observable.
    unset($_SESSION['contact_id'],$_SESSION['contact_auth'],$_SESSION['uid']);
    return isset($_SESSION['client_id'])?['id'=>$_SESSION['client_id'],'active'=>1]:null;
}
function provider_settings_manager(bool $ready=true):array {
    cfs_count('owner_checks');
    cfs_count($ready?'owner_ready_checks':'owner_shell_checks');
    if(($_SESSION['role']??'')!=='owner'||support_current()!==null||array_key_exists('support',$_GET))throw new RuntimeException('An active owner account is required.');
    if($ready&&($_SESSION['scenario']??'')==='cold-provider')throw new RuntimeException('An owner must prepare provider settings first.');
    return ['id'=>1,'name'=>'Synthetic owner','role'=>'owner'];
}
function require_admin():array {return provider_settings_manager(false);}
function staff_action_permission(string $action):?string {return 'providers.manage';}
function staff_require(string $permission):void {provider_settings_manager(false);}
function db():object {return new class {public function inTransaction():bool{return false;}};}
function query(mixed ...$args):never {throw new LogicException('SQL is forbidden in this HTTP fixture.');}
function one(mixed ...$args):never {throw new LogicException('SQL is forbidden in this HTTP fixture.');}
function all(mixed ...$args):never {throw new LogicException('SQL is forbidden in this HTTP fixture.');}
function report_error(Throwable $error):string {cfs_count('reported_errors');return 'synthetic-reference';}
function flash(string $message,string $type='success'):void {$_SESSION['flash']=$message;}
function show_flash():void {if(isset($_SESSION['flash'])){echo '<p class="notice">'.esc($_SESSION['flash']).'</p>';unset($_SESSION['flash']);}}
function studio_url(array $params=[]):string {return '/portal'.($params?'?'.http_build_query($params):'');}
function admin_url(string $view,array $params=[]):string {return '/owner?'.http_build_query(['view'=>$view]+$params);}
function cfs_head(string $title,bool $owner):void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title>';
    foreach(['site',$owner?'admin':'premium','growth'] as $css)echo '<link rel="stylesheet" href="/assets/css/'.$css.'.css">';
    echo '</head><body'.($owner?' class="admin-body"':'').'>';
}
function site_head(string $title,string $area=''):void {cfs_head($title,false);}
function site_foot(bool $unused=false):void {echo '</body></html>';}
function admin_head(string $title,string $view,array $actor):void {cfs_head($title,true);echo '<main class="admin-main" id="admin-main"><h1>'.esc($title).'</h1>';}
function admin_foot():void {echo '</main></body></html>';}

/** Explicit modeled authority/business boundary; actual route/controller/HTML code is above. */
function provider_force_ssl_requests_actor(?int $clientId,?string $password=null):array {
    cfs_count('actors');
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||array_key_exists('support',$_SESSION)||array_key_exists('support',$_GET)||array_key_exists('support',$_POST)||support_current()!==null)throw new RuntimeException('Sign in directly to manage HTTPS requests.');
    if($clientId===null)return provider_settings_manager();
    if(provider_force_ssl_requests_session_blocked()||($_SESSION['client_id']??null)!==$clientId||($_SESSION['mode']??'')==='stale-client')throw new RuntimeException('Sign in directly to your primary client account.');
    return ['id'=>$clientId];
}
function cfs_password(string $password):void {if($password!=='Synthetic HTTPS request owner passphrase')throw new InvalidArgumentException('Your current password is not correct.');}
function cfs_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the original request.');}
function cfs_option():array {return ['link_id'=>str_repeat('a',32),'package_id'=>3101,'package_name'=>'Synthetic <img src=x onerror=alert(1)> hosting','review_sha256'=>str_repeat('b',64),'shared_value'=>false,'observed_epoch'=>1760000000];}
function provider_force_ssl_requests_prepare(string $password):void {
    provider_force_ssl_requests_actor(null);cfs_call('prepare',[]);cfs_password($password);
    cfs_store(static function(array &$s){$s['ready']=true;$s['writes']['prepare']++;});
}
function provider_force_ssl_requests_screen(?int $clientId=null,int $page=1,?string $detailId=null):array {
    provider_force_ssl_requests_actor($clientId);cfs_count('screens');
    if(($_SESSION['scenario']??'')==='screen-pdo')throw new PDOException('PRIVATE_DATABASE_DIAGNOSTIC');
    if(($_SESSION['scenario']??'')==='corrupt')throw new RuntimeException('Saved request history is unavailable.');
    $s=cfs_store();$items=[];
    foreach($s['items'] as $row){
        if($clientId!==null&&$row['client_id']!==$clientId)continue;
        if($detailId!==null&&$detailId!==$row['id'])continue;
        $pending=$row['state']==='pending';$current=($_SESSION['scenario']??'')!=='assignment-stale';
        $approval=null;
        if($clientId===null&&$pending&&$current&&($_SESSION['scenario']??'')!=='approval-unavailable'){
            // A screen can propose an unused random approval. Failed POST must not render it as replacement consent.
            $approval=['request_id'=>bin2hex(random_bytes(16)),'anchor_id'=>str_repeat('c',32),'binding_sha256'=>str_repeat('d',64),'before_value'=>false,'desired'=>$row['desired'],'observed_epoch'=>1760000000];
            cfs_store(static function(array &$s)use($approval,$row){$s['proposals'][$approval['request_id']]=['request_id'=>$row['id']]+$approval;});
        }
        $items[]=['id'=>$row['id'],'link_id'=>str_repeat('a',32),'package_id'=>3101,'package_name'=>cfs_option()['package_name'],'desired'=>$row['desired'],'created_epoch'=>1760000001,'state'=>$row['state'],'latest_event'=>$row['latest_event'],'request_sha256'=>hash('sha256',$row['id']),'current_assignment'=>$current,'can_cancel'=>$pending&&$current,'can_decline'=>$pending&&$current,'note'=>$row['note'],'client'=>$clientId===null?['id'=>$row['client_id'],'name'=>'Client <svg onload=alert(2)>']:null,'owner_request_id'=>$clientId===null?$row['owner_request_id']:null,'approval'=>$approval,'approval_unavailable'=>$approval===null&&$pending?'Refresh the saved HTTPS setting before approval.':null,'dispatch'=>$clientId===null&&$row['owner_request_id']!==null?['recovery_ledger_sha256'=>str_repeat('e',64),'can_verify'=>true]:null,'last_check'=>$row['state']==='observed'?['disposition'=>'observed','recorded_epoch'=>1760000010]:null];
    }
    if($detailId!==null&&$items===[])throw new RuntimeException('Force SSL request unavailable.');
    return ['ready'=>$s['ready'],'options'=>$detailId===null&&$clientId===1&&($_SESSION['scenario']??'')!=='options-unavailable'?[cfs_option()]:[],'items'=>$items,'page'=>$page,'has_more'=>$detailId===null&&$page===1];
}
function provider_force_ssl_requests_create(string $id,int $clientId,string $link,string $review,bool $desired,bool $confirmed):array {
    provider_force_ssl_requests_actor($clientId);cfs_call('create',[$id,$clientId,$link,$review,$desired,$confirmed]);cfs_id($id);
    if(!$confirmed)throw new InvalidArgumentException('Confirm that you want the studio to review this change.');
    if($clientId!==1||$link!==str_repeat('a',32)||$review!==str_repeat('b',64)||($_SESSION['scenario']??'')==='options-unavailable')throw new RuntimeException('This hosting review is no longer available.');
    cfs_store(static function(array &$s)use($id,$clientId,$desired){
        if(isset($s['items'][$id])){if($s['items'][$id]['desired']!==$desired)throw new RuntimeException('Original request differs.');return;}
        $s['items'][$id]=['id'=>$id,'client_id'=>$clientId,'desired'=>$desired,'state'=>'pending','latest_event'=>$id,'note'=>'','owner_request_id'=>null];$s['writes']['request']++;
    });return [];
}
function provider_force_ssl_requests_change(string $id,string $expected,string $event,string $command,string $note,?int $clientId=null,string $password=''):array {
    provider_force_ssl_requests_actor($clientId);cfs_call('change',[$id,$expected,$event,$command,$note,$clientId]);cfs_id($id);cfs_id($event);if($clientId===null)cfs_password($password);
    cfs_store(static function(array &$s)use($id,$expected,$event,$command,$note,$clientId){
        $r=$s['items'][$id]??null;if(!$r||$r['latest_event']!==$expected||$r['state']!=='pending'||($clientId!==null&&$r['client_id']!==$clientId))throw new RuntimeException('Review the current original request.');
        if(($clientId!==null&&$command!=='cancel')||($clientId===null&&$command!=='decline'))throw new RuntimeException('Decision unavailable.');
        $s['items'][$id]['state']=$command==='cancel'?'cancelled':'declined';$s['items'][$id]['latest_event']=$event;$s['items'][$id]['note']=$note;$s['writes'][$command]++;
    });return [];
}
function provider_force_ssl_requests_approve(string $id,string $expected,string $sha,string $ownerId,string $anchor,string $binding,bool $confirmed,string $password):array {
    provider_force_ssl_requests_actor(null);cfs_call('approve',[$id,$expected,$sha,$ownerId,$anchor,$binding,$confirmed]);cfs_password($password);
    if(!$confirmed)throw new InvalidArgumentException('Confirm this original owner review.');
    if(in_array($_SESSION['scenario']??'',['approval-unavailable','assignment-stale'],true))throw new RuntimeException('Refresh the saved HTTPS setting before approval.');
    cfs_store(static function(array &$s)use($id,$expected,$sha,$ownerId,$anchor,$binding){
        $r=$s['items'][$id]??null;$p=$s['proposals'][$ownerId]??null;
        if(!$r||!$p||$p['request_id']!==$id||$sha!==hash('sha256',$id)||$p['anchor_id']!==$anchor||$p['binding_sha256']!==$binding)throw new RuntimeException('The original approval is no longer valid.');
        if($r['owner_request_id']===$ownerId)return;
        if($r['state']!=='pending'||$r['latest_event']!==$expected)throw new RuntimeException('Review the current original request.');
        $s['items'][$id]['owner_request_id']=$ownerId;$s['items'][$id]['latest_event']=$ownerId;$s['items'][$id]['state']='unknown';$s['writes']['admission']++;
    });return [];
}
function provider_force_ssl_requests_verify(string $id,string $ownerId,string $password):array {
    provider_force_ssl_requests_actor(null);cfs_call('verify',[$id,$ownerId]);cfs_password($password);
    cfs_store(static function(array &$s)use($id,$ownerId){if(($s['items'][$id]['owner_request_id']??null)!==$ownerId)throw new RuntimeException('Original approval differs.');$s['items'][$id]['state']='observed';$s['writes']['check']++;});return [];
}
function provider_force_ssl_requests_recover(string $id,string $ownerId,string $ledger,string $password):array {
    provider_force_ssl_requests_actor(null);cfs_call('recover',[$id,$ownerId,$ledger]);cfs_password($password);
    cfs_store(static function(array &$s)use($id,$ownerId,$ledger){if(($s['items'][$id]['owner_request_id']??null)!==$ownerId||$ledger!==str_repeat('e',64))throw new RuntimeException('Original completion differs.');$s['items'][$id]['state']='unknown';$s['writes']['recover']++;});return [];
}

$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/health'){echo (string)getenv('CWP_HTTPS_REQUEST_HEALTH');exit;}
if(preg_match('#\A/assets/css/(site|admin|premium|growth)\.css\z#',(string)$path,$m)){header('Content-Type: text/css');readfile(CWP_CODE_ROOT.'/assets/css/'.$m[1].'.css');exit;}
if($path==='/__seed'){
    $mode=$_GET['mode']??'';$_SESSION=['csrf'=>bin2hex(random_bytes(32)),'counts'=>[],'mode'=>$mode];
    if(in_array($mode,['client','other','contact-null','contact-auth-empty','uid-null','resolved-support','stale-client'],true))$_SESSION['client_id']=$mode==='other'?2:1;
    if(in_array($mode,['owner','owner-contact-null','owner-contact-auth-empty','owner-support-null','owner-resolved-support'],true))$_SESSION['role']='owner';if($mode==='staff')$_SESSION['role']='administrator';
    if($mode==='owner-contact-null')$_SESSION['contact_id']=null;if($mode==='owner-contact-auth-empty')$_SESSION['contact_auth']='';if($mode==='owner-support-null')$_SESSION['support']=null;if($mode==='owner-resolved-support')$_SESSION['resolved_support']=true;
    if($mode==='contact-null')$_SESSION['contact_id']=null;if($mode==='contact-auth-empty')$_SESSION['contact_auth']='';if($mode==='uid-null')$_SESSION['uid']=null;if($mode==='resolved-support')$_SESSION['resolved_support']=true;
    header('Content-Type: application/json');echo json_encode(['seeded'=>true,'csrf'=>csrf()]);exit;
}
if(!empty($_SESSION['resolved_support']))$GLOBALS['cwp_support']=['client'=>['id'=>1]];
if($path==='/__scenario'){
    $scenario=$_GET['name']??'';if(!in_array($scenario,['','approval-unavailable','assignment-stale','options-unavailable','corrupt','screen-pdo','interrupted','cold-provider'],true)){http_response_code(400);exit;}
    if($scenario==='interrupted')cfs_store(static function(array &$s){foreach($s['items']as&$r)if($r['state']==='unknown')$r['state']='interrupted';});
    else $_SESSION['scenario']=$scenario;
    header('Content-Type: application/json');echo '{"set":true}';exit;
}
if($path==='/__state'){header('Content-Type: application/json');echo json_encode(['counts'=>(object)($_SESSION['counts']??[]),'model'=>cfs_store()]);exit;}
$error='';
if($path==='/portal'){
    cfs_router_preflight();$client=studio_client();$supportRequested=array_key_exists('support',$_GET);$supportId='';$jobId=0;
    require CWP_CODE_ROOT.'/portal/controllers/actions.php';
    if(!$client){http_response_code(401);echo 'Primary client sign-in required';exit;}
    provider_force_ssl_requests_client_render($client,$error);
}
if($path==='/owner'){
    require CWP_CODE_ROOT.'/admin/controllers/actions.php';
    provider_force_ssl_requests_admin_render($error);
}
http_response_code(404);echo 'Unavailable';
