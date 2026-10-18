<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');

// Exact production dispatcher/parser bodies; authentication, CSRF, locking and
// service functions below are explicit models. No SQL, HTTP or provider runs.
$path=CWP_CODE_ROOT.'/app/providers/php_controls_http.php';$source=file_get_contents($path);
$marker='/** Exact form values:';$start=strpos($source,$marker);
if ($start===false || substr_count($source,$marker)!==1 || substr_count($source,'function provider_php_controls_http_action(')!==1) throw new RuntimeException('Unexpected HTTP helper source.');
eval(substr($source,$start));
$routerPath=CWP_CODE_ROOT.'/portal/controllers/router.php';$router=file_get_contents($routerPath);
$gateStart=strpos($router,'$phpControlsRequested=');$gateEnd=strpos($router,'$phpControlsReview=null;');
if ($gateStart===false || $gateEnd===false || $gateEnd<=$gateStart || substr_count($router,'$phpControlsRequested=')!==1) throw new RuntimeException('Unexpected portal early-gate source.');
$earlyGate=substr($router,$gateStart,$gateEnd-$gateStart);
$modeledPdo=!class_exists('PDOException');
if ($modeledPdo) { class PDOException extends RuntimeException {} }
set_error_handler(static function(int $severity,string $message,string $file,int $line):never { throw new ErrorException($message,0,$severity,$file,$line); });
$checks=[];$env=[];
final class PhHttpExit extends RuntimeException {}
function ph_id(int $n):string { return str_pad(dechex($n),32,'0',STR_PAD_LEFT); }
function ph_hash(int $n):string { return str_pad(dechex($n),64,'0',STR_PAD_LEFT); }
function ph_reset(bool $owner=true):void {
    $GLOBALS['env']=['events'=>[],'calls'=>[],'csrf'=>true,'owner'=>$owner,'client'=>7,'active'=>true,'auth'=>true,'support'=>false,'lock'=>0,'tx'=>false,'ready'=>true,'record'=>true,'record_link'=>ph_id(1),'access'=>true,'read_state'=>'complete','review_hash'=>ph_hash(1),'review_error'=>null,'result'=>['phase'=>'attempted','readback'=>'pending'],'service_error'=>null];
    $_SERVER=['REQUEST_METHOD'=>'POST'];$_SESSION=['client_id'=>7];
    $_GET=$owner?['view'=>'provider-php-controls','service_link'=>ph_id(1)]:['php_controls'=>'1','service_link'=>ph_id(1)];
    $_POST=['action'=>'php_controls_change','link_id'=>ph_id(1),'request_id'=>ph_id(2),'current_read_id'=>ph_id(3),'options_read_id'=>ph_id(4),'desired'=>'PHP 8.3','review_sha256'=>ph_hash(1),'confirmed'=>'1','current_password'=>'synthetic current password','kind'=>'current','event_id'=>ph_id(5),'expected_permission_id'=>'','enabled'=>'1','ledger_sha256'=>ph_hash(6)];
}
function ph_event(string $name):void { $GLOBALS['env']['events'][]=$name; }
function ph_call(string $name,array $args):void { $GLOBALS['env']['calls'][]=['name'=>$name,'args'=>$args]; }
function ph_calls(string $name):array { return array_values(array_filter($GLOBALS['env']['calls'],static fn(array $c):bool=>$c['name']===$name)); }
function ph_scope():void { if ($GLOBALS['env']['lock']<1 || !$GLOBALS['env']['tx']) throw new LogicException('Modeled authority must run under both guards.'); }
function check_csrf():void { ph_event('csrf');if (!$GLOBALS['env']['csrf']) throw new RuntimeException('Modeled CSRF refused.'); }
function support_current():mixed { ph_event('support');return $GLOBALS['env']['support']?['client_id'=>7]:null; }
function fail_page(string $message,int $status):never { ph_event('fail:'.$status);throw new PhHttpExit($message,$status); }
function provider_settings_manager():array { ph_event('owner');if (!$GLOBALS['env']['owner'] || $GLOBALS['env']['support'] || array_key_exists('support',$_GET)) throw new RuntimeException('Modeled owner refused.');return ['id'=>1]; }
function staff_locked(callable $fn):mixed { $GLOBALS['env']['lock']++;ph_event('lock');try{return $fn();}finally{$GLOBALS['env']['lock']--;ph_event('unlock');} }
function staff_transaction(callable $fn):mixed {
    if ($GLOBALS['env']['lock']<1 || $GLOBALS['env']['tx']) throw new LogicException('Invalid modeled transaction.');
    $GLOBALS['env']['tx']=true;ph_event('begin');try{return $fn();}finally{$GLOBALS['env']['tx']=false;ph_event('end');}
}
function provider_php_controls_primary_client(int $id):array {
    ph_scope();ph_event('primary');
    if ($id!==$GLOBALS['env']['client'] || ($_SESSION['client_id']??null)!==$id || !$GLOBALS['env']['active'] || !$GLOBALS['env']['auth']) throw new RuntimeException('Modeled current client refused.');
    return ['id'=>$id];
}
function provider_php_controls_lifecycle_id(string $id):void { if (!preg_match('/\A[0-9a-f]{32}\z/D',$id)) throw new InvalidArgumentException('Invalid modeled opaque ID.'); }
function provider_php_controls_prepare(string $password):void { ph_call('prepare',func_get_args()); }
function provider_php_controls_lifecycle_prepare(string $password):void { ph_call('lifecycle_prepare',func_get_args()); }
function provider_php_controls_delegate(string $link,string $expected,string $event,bool $enabled,string $hash,string $password):void { ph_call('delegate',func_get_args()); }
function provider_php_controls_read_owner(string $link,string $kind,string $password):array { ph_call('read_owner',func_get_args());return ['id'=>ph_id(10),'kind'=>$kind,'state'=>$GLOBALS['env']['read_state'],'reason'=>null]; }
function provider_php_controls_read_client(int $client,string $link,string $kind):array { ph_call('read_client',func_get_args());return ['id'=>ph_id(10),'kind'=>$kind,'state'=>$GLOBALS['env']['read_state'],'reason'=>null]; }
function ph_review(string $current,string $options,string $desired):array {
    if ($GLOBALS['env']['review_error']!==null) throw $GLOBALS['env']['review_error'];
    return ['review_sha256'=>$GLOBALS['env']['review_hash'],'current_read_id'=>$current,'options_read_id'=>$options,'before_value'=>'8.2','desired_value'=>$desired,'label'=>'PHP 8.3','handler'=>'fpm','current_observed_epoch'=>100,'options_observed_epoch'=>101];
}
function provider_php_controls_review_owner(string $link,string $current,string $options,string $desired):array { ph_call('review_owner',func_get_args());return ph_review($current,$options,$desired); }
function provider_php_controls_review_client(int $client,string $link,string $current,string $options,string $desired):array { ph_call('review_client',func_get_args());return ph_review($current,$options,$desired); }
function ph_change(bool $confirmed,string $password):array {
    if (!$confirmed || $password!=='synthetic current password') throw new RuntimeException('Modeled deliberate confirmation/password refused.');
    if ($GLOBALS['env']['service_error']!==null) throw $GLOBALS['env']['service_error'];
    return $GLOBALS['env']['result'];
}
function provider_php_controls_change_owner(string $request,string $link,string $current,string $options,string $desired,string $hash,bool $confirmed,string $password):array { ph_call('change_owner',func_get_args());return ph_change($confirmed,$password); }
function provider_php_controls_change_client(int $client,string $request,string $link,string $current,string $options,string $desired,string $hash,bool $confirmed,string $password):array { ph_call('change_client',func_get_args());return ph_change($confirmed,$password); }
function provider_php_controls_lifecycle_ready():bool { ph_scope();ph_event('ready');return $GLOBALS['env']['ready']; }
function provider_php_controls_lifecycle_get(string $kind,string $id):?array { ph_scope();ph_call('get',func_get_args());return $GLOBALS['env']['record']?['document'=>['review'=>['context'=>['binding'=>['link_id'=>$GLOBALS['env']['record_link']]]]]]:null; }
function provider_php_controls_change_access(array $doc,?int $client):void { ph_scope();ph_call('access',func_get_args());if (!$GLOBALS['env']['access']) throw new RuntimeException('Modeled original request access refused.'); }
function provider_php_controls_verify_owner(string $request,string $password):array { if ($GLOBALS['env']['tx'] || $GLOBALS['env']['lock']!==0) throw new LogicException('Wrapper boundary must follow closed binding transaction.');ph_call('verify_owner',func_get_args());return $GLOBALS['env']['result']; }
function provider_php_controls_verify_client(int $client,string $request,string $password):array { if ($GLOBALS['env']['tx'] || $GLOBALS['env']['lock']!==0) throw new LogicException('Wrapper boundary must follow closed binding transaction.');ph_call('verify_client',func_get_args());return $GLOBALS['env']['result']; }
function provider_php_controls_recover_owner(string $request,string $hash,string $password):array { ph_call('recover_owner',func_get_args());return $GLOBALS['env']['result']; }
function ph_ok(bool $condition,string $name):void { $GLOBALS['checks'][]=['name'=>$name,'passed'=>$condition];if (!$condition) throw new RuntimeException('Assertion failed: '.$name); }
function ph_refuse(callable $fn,string $name,string $type=RuntimeException::class):void {
    $caught=false;try{$fn();}catch(Throwable $e){if (!$e instanceof $type) throw $e;$caught=true;}
    ph_ok($caught,$name);
}
function ph_action(bool $owner=true,?string $action=null,?int $client=7):array { return provider_php_controls_http_action($owner,$owner?null:$client,$action??$_POST['action']); }
function ph_screen():array { return ['link_id'=>ph_id(1),'can_select'=>true,'delegation'=>['enable'=>['expected_permission_id'=>'','review_sha256'=>ph_hash(1)],'disable'=>['expected_permission_id'=>'','review_sha256'=>ph_hash(1)]]]; }
function ph_no_service(string $label):void { ph_ok($GLOBALS['env']['calls']===[],$label); }

try {
    ph_reset();ph_ok(provider_php_controls_http_text(['x'=>" PHP 8.3\t"],'x',128)===" PHP 8.3\t",'Parser preserves raw whitespace and case');
    ph_ok(provider_php_controls_http_text(['x'=>'0'],'x',1)==='0','Parser preserves literal zero');
    ph_ok(provider_php_controls_http_text([],'x',1,true)==='','Parser permits explicitly optional missing field');
    foreach (['array'=>[],'integer'=>83,'boolean'=>true,'object'=>(object)[]] as $kind=>$value) ph_refuse(fn()=>provider_php_controls_http_text(['x'=>$value],'x',128),'Parser rejects '.$kind.' without coercion',InvalidArgumentException::class);
    ph_refuse(fn()=>provider_php_controls_http_text([],'x',128),'Parser rejects required missing field',InvalidArgumentException::class);
    ph_refuse(fn()=>provider_php_controls_http_text(['x'=>str_repeat('a',129)],'x',128),'Parser enforces byte bound',InvalidArgumentException::class);
    ph_ok(provider_php_controls_http_text(['x'=>str_repeat('a',128)],'x',128)===str_repeat('a',128),'Parser accepts exact byte bound');

    ph_ok($gateEnd<strpos($router,'support_resolve($supportId)')&&$gateEnd<strpos($router,'$client=studio_client();'),'Actual portal gate precedes support resolution and client normalization');
    ph_ok(strpos($router,"require_once CWP_CODE_ROOT.'/portal/controllers/php_controls.php';")<$gateStart,'Portal loads the actual PHP gate before invoking it');
    foreach (['GET key','POST prefix'] as $case) {
        ph_reset(false);$_SESSION['contact_auth']=null;
        if ($case==='GET key') {$_SERVER['REQUEST_METHOD']='GET';$_POST=[];} else unset($_GET['php_controls']);
        ph_refuse(static function()use($earlyGate):void{eval($earlyGate);ph_event('later-normalization');},'Actual early route slice refuses contact on '.$case,PhHttpExit::class);
        ph_ok(!in_array('later-normalization',$env['events'],true),'Contact gate stops before modeled normalization on '.$case);
    }
    ph_reset(false);$_GET['support']='';ph_refuse(static function()use($earlyGate):void{eval($earlyGate);},'Actual early route slice refuses support query',PhHttpExit::class);
    ph_reset(false);$_GET=[];$_POST=['action'=>'unrelated'];$_SESSION['contact_id']=7;eval($earlyGate);ph_ok($env['events']===[],'Early PHP gate leaves unrelated portal routes unchanged');
    ph_reset(false);eval($earlyGate);ph_ok($env['calls']===[]&&!in_array('primary',$env['events'],true),'Early gate performs no service or client normalization');

    ph_reset();$_SERVER['REQUEST_METHOD']='GET';ph_refuse(fn()=>ph_action(),'GET refuses before CSRF/auth/service',InvalidArgumentException::class);ph_ok($env['events']===[]&&$env['calls']===[],'GET causes no modeled boundary calls');
    ph_reset();$env['csrf']=false;ph_refuse(fn()=>ph_action(),'Bad CSRF refuses dispatch');ph_ok($env['events']===['csrf']&&$env['calls']===[],'CSRF precedes all identity and service work');
    foreach (['portal route'=>['php_controls'=>'1','service_link'=>ph_id(1)],'other owner route'=>['view'=>'provider-settings','service_link'=>ph_id(1)],'array route'=>['view'=>['provider-php-controls'],'service_link'=>ph_id(1)]] as $name=>$get) {ph_reset();$_GET=$get;ph_refuse(fn()=>ph_action(),'Owner rejects '.$name,InvalidArgumentException::class);ph_no_service('No service for '.$name);}
    ph_reset(false);$_GET=['view'=>'provider-php-controls','service_link'=>ph_id(1)];ph_refuse(fn()=>ph_action(false),'Client rejects admin route',InvalidArgumentException::class);ph_no_service('Cross client route has no service');
    ph_reset(false);$_GET['php_controls']=1;ph_refuse(fn()=>ph_action(false),'Client route flag requires exact string',InvalidArgumentException::class);
    ph_reset();$_POST['link_id']=ph_id(99);ph_refuse(fn()=>ph_action(),'Cross-link POST refuses',InvalidArgumentException::class);ph_no_service('Cross-link POST never reaches service');
    ph_reset();$_GET['service_link']='not-a-link';$_POST['link_id']='not-a-link';ph_refuse(fn()=>ph_action(),'Malformed link refuses',InvalidArgumentException::class);
    ph_reset();ph_refuse(fn()=>ph_action(true,'php_controls_unknown'),'Unknown prefixed action refuses',InvalidArgumentException::class);ph_no_service('Unknown action never reaches service');
    ph_reset();$env['owner']=false;ph_refuse(fn()=>ph_action(),'Non-owner staff is refused by modeled manager');ph_no_service('Denied staff makes no service call');
    foreach (['contact_id','contact_auth'] as $key) {ph_reset(false);$_SESSION[$key]=null;ph_refuse(fn()=>ph_action(false),'Null retained '.$key.' is refused before primary normalization',PhHttpExit::class);ph_ok(!in_array('primary',$env['events'],true)&&$env['calls']===[],'No normalization/service for '.$key);}
    foreach (['query','active'] as $kind) {ph_reset(false);if($kind==='query')$_GET['support']='';else$env['support']=true;ph_refuse(fn()=>ph_action(false),'Client '.$kind.' support context refuses',PhHttpExit::class);ph_no_service('Support '.$kind.' makes no service call');}
    ph_reset(false);ph_refuse(fn()=>ph_action(false,null,null),'Missing authenticated client refuses');
    foreach (['different id','inactive','revoked auth'] as $kind) {ph_reset(false);if($kind==='different id')$_SESSION['client_id']=8;elseif($kind==='inactive')$env['active']=false;else$env['auth']=false;ph_refuse(fn()=>ph_action(false),'Current primary-client guard rejects '.$kind);ph_ok($env['lock']===0&&!$env['tx']&&$env['calls']===[],'Client refusal closes local guards: '.$kind);}
    foreach (['prepare','delegate','recover'] as $kind) {ph_reset(false);ph_refuse(fn()=>ph_action(false,'php_controls_'.$kind),'Client cannot '.$kind,InvalidArgumentException::class);ph_no_service('Client '.$kind.' has no service invocation');}

    ph_reset();$r=ph_action(true,'php_controls_prepare');ph_ok(array_column($env['calls'],'name')===['prepare','lifecycle_prepare']&&$env['calls'][0]['args']===['synthetic current password']&&$env['calls'][1]['args']===['synthetic current password'],'Owner setup calls the two existing password wrappers in order');
    ph_reset();ph_action(true,'php_controls_delegate');ph_ok(ph_calls('delegate')[0]['args']===[ph_id(1),'',ph_id(5),true,ph_hash(1),'synthetic current password'],'Grant preserves captured permission event and hash');
    ph_reset();$_POST['enabled']='0';ph_action(true,'php_controls_delegate');ph_ok(ph_calls('delegate')[0]['args'][3]===false,'Revoke stays an explicit false choice');
    foreach (['enabled','confirmed'] as $field) {ph_reset();$_POST[$field]='x';ph_refuse(fn()=>ph_action(true,'php_controls_delegate'),'Delegate rejects invalid '.$field,InvalidArgumentException::class);ph_no_service('Invalid delegation '.$field.' has no service call');}
    ph_reset();$_POST['resolver']='posted';$_POST['transport']='posted';$_POST['perform']='posted';ph_action(true,'php_controls_read');ph_ok(ph_calls('read_owner')[0]['args']===[ph_id(1),'current','synthetic current password'],'Owner read supplies only fixed link kind and password');
    ph_reset(false);$_POST['kind']='available';$_POST['client_id']='99';$_POST['transport']='posted';ph_action(false,'php_controls_read');ph_ok(ph_calls('read_client')[0]['args']===[7,ph_id(1),'available'],'Client read uses authenticated ID and no posted dependency');
    ph_reset();$_POST['kind']='other';ph_refuse(fn()=>ph_action(true,'php_controls_read'),'Unknown read kind refuses before read',InvalidArgumentException::class);ph_no_service('Unknown read kind never calls reader');
    ph_reset();$env['read_state']='unavailable';$r=ph_action(true,'php_controls_read');ph_ok($r['tone']==='error'&&str_contains($r['message'],'did not return usable'),'Unavailable read is not reported as complete');
    ph_reset();$_POST['desired']=" PHP 8.3\t";$r=ph_action(true,'php_controls_review');ph_ok(ph_calls('review_owner')[0]['args']===[ph_id(1),ph_id(3),ph_id(4)," PHP 8.3\t"]&&ph_calls('change_owner')===[],'Review preserves exact option and never calls change');
    ph_ok($r['kind']==='review'&&$r['review']['request_id']===ph_id(2)&&!isset($r['review']['current_password']),'Review retains request ID but no password');
    ph_reset(false);$r=ph_action(false,'php_controls_review');ph_ok(ph_calls('review_client')[0]['args']===[7,ph_id(1),ph_id(3),ph_id(4),'PHP 8.3'],'Client review uses actual selected identity');
    ph_reset();$_POST['resolver']='posted';$_POST['perform']='posted';ph_action();ph_ok(ph_calls('change_owner')[0]['args']===[ph_id(2),ph_id(1),ph_id(3),ph_id(4),'PHP 8.3',ph_hash(1),true,'synthetic current password'],'Change supplies captured values confirmation/password and no dependency');
    ph_reset(false);ph_action(false);ph_ok(ph_calls('change_client')[0]['args']===[7,ph_id(2),ph_id(1),ph_id(3),ph_id(4),'PHP 8.3',ph_hash(1),true,'synthetic current password'],'Client change carries real ID and current password');
    ph_reset();$_POST['confirmed']='0';ph_refuse(fn()=>ph_action(),'False deliberate confirmation is rejected by modeled real-service boundary');ph_ok(ph_calls('change_owner')[0]['args'][6]===false,'Dispatcher does not upgrade false confirmation');
    ph_reset();unset($_POST['confirmed']);ph_refuse(fn()=>ph_action(),'Missing change confirmation refuses before service',InvalidArgumentException::class);ph_no_service('Missing confirmation has no change call');
    ph_reset();$_POST['current_password']='';ph_refuse(fn()=>ph_action(),'Empty password stays empty at modeled password guard');ph_ok(ph_calls('change_owner')[0]['args'][7]==='','No password substitution');
    ph_reset();$_POST['request_id']='bad';ph_refuse(fn()=>ph_action(),'Invalid request identity refuses',InvalidArgumentException::class);ph_no_service('Malformed request never calls service');
    ph_reset();$_POST['desired']=['8.3'];ph_refuse(fn()=>ph_action(),'Array option refuses',InvalidArgumentException::class);ph_no_service('Array option never calls service');

    foreach (['not ready','missing request','wrong link','lost access'] as $case) {
        ph_reset();if($case==='not ready')$env['ready']=false;elseif($case==='missing request')$env['record']=false;elseif($case==='wrong link')$env['record_link']=ph_id(99);else$env['access']=false;
        ph_refuse(fn()=>ph_action(true,'php_controls_verify'),'Verification refuses '.$case);
        ph_ok(ph_calls('verify_owner')===[]&&ph_calls('recover_owner')===[]&&$env['lock']===0&&!$env['tx'],'Verification guard closes without external action: '.$case);
    }
    ph_reset();$env['result']['readback']='observed';$r=ph_action(true,'php_controls_verify');ph_ok(ph_calls('get')[0]['args']===['request',ph_id(2)]&&ph_calls('access')[0]['args'][1]===null&&ph_calls('verify_owner')[0]['args']===[ph_id(2),'synthetic current password']&&$r['tone']==='success','Owner verify binds exact request/link before fixed wrapper');
    ph_reset(false);ph_action(false,'php_controls_verify');ph_ok(ph_calls('access')[0]['args'][1]===7&&ph_calls('verify_client')[0]['args']===[7,ph_id(2),'synthetic current password'],'Client verify preserves actual requester and explicit password');
    ph_reset();$r=ph_action(true,'php_controls_recover');ph_ok(ph_calls('recover_owner')[0]['args']===[ph_id(2),ph_hash(6),'synthetic current password']&&ph_calls('change_owner')===[]&&str_contains($r['message'],'does not send another change'),'Recovery keeps exact reviewed ledger hash and no change invocation');
    ph_reset();$_POST['confirmed']='0';ph_refuse(fn()=>ph_action(true,'php_controls_recover'),'Recovery requires explicit confirmation',InvalidArgumentException::class);ph_ok(ph_calls('recover_owner')===[],'Unconfirmed recovery never calls recovery wrapper');
    ph_reset();$env['record_link']=ph_id(99);ph_refuse(fn()=>ph_action(true,'php_controls_recover'),'Recovery rejects cross-link request');ph_ok(ph_calls('recover_owner')===[],'Cross-link recovery never invokes wrapper');

    foreach (['observed'=>'later check reported the requested','different'=>'different PHP version','unavailable'=>'could not confirm'] as $state=>$needle) ph_ok(str_contains(provider_php_controls_http_result(['readback'=>$state,'phase'=>'attempted']),$needle),'Result wording preserves '.$state.' scope');
    ph_ok(provider_php_controls_http_result(['readback'=>'pending','phase'=>'not_sent'])==='The change request was not sent.','Not-sent result is explicit');
    ph_ok(str_contains(provider_php_controls_http_result(['readback'=>'pending','phase'=>'attempted']),'Check the PHP version separately'),'Attempt acknowledgement never claims observed change');
    ph_reset();$r=ph_action();ph_ok($r['tone']==='error'&&str_contains($r['message'],'recorded'),'Pending attempt remains an unresolved notice');
    ph_reset();$env['service_error']=new TypeError('synthetic engine failure');ph_refuse(fn()=>ph_action(),'Dispatcher propagates unexpected engine errors',TypeError::class);
    ph_reset();$env['service_error']=new PDOException('synthetic storage failure');ph_refuse(fn()=>ph_action(),'Dispatcher propagates storage errors',PDOException::class);

    ph_reset();$screen=ph_screen();$original=['original'=>'review'];$_SERVER['REQUEST_METHOD']='GET';[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,$original);ph_ok($review===$original&&$retry===[]&&$env['calls']===[],'GET retry pass is inert');
    ph_reset();$screen=ph_screen();$_POST['action']='unrelated';[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($review===null&&$retry===[]&&$env['calls']===[],'Unrelated POST is not reconstructed');
    ph_reset();$screen=ph_screen();$_POST['current_password']='incorrect';[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($review['request_id']===ph_id(2)&&$review['review_sha256']===ph_hash(1)&&$review['current_read_id']===ph_id(3)&&$review['options_read_id']===ph_id(4),'Retry retains exact approved request/read IDs and hash');
    ph_ok($retry===['request_id'=>ph_id(2),'desired'=>'PHP 8.3']&&!str_contains(json_encode([$review,$retry]),'incorrect')&&ph_calls('change_owner')===[],'Retry exposes only safe selection and never resends or retains password');
    ph_reset(false);$screen=ph_screen();[$review,$retry]=provider_php_controls_http_retry(false,7,$screen,null);ph_ok(ph_calls('review_client')[0]['args'][0]===7&&$review['request_id']===ph_id(2),'Client retry uses supplied authenticated client identity');
    ph_reset();$screen=ph_screen();$env['review_hash']=ph_hash(99);[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($review===null&&$retry===['request_id'=>ph_id(2),'desired'=>'PHP 8.3'],'Changed review hash suppresses approval while retaining safe selection');
    ph_reset();$screen=ph_screen();$env['review_error']=new RuntimeException('stale latest read');[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($review===null&&ph_calls('change_owner')===[],'Stale read refusal cannot become a fresh approval');
    ph_reset();$screen=ph_screen();$screen['can_select']=false;[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($review===null&&$env['calls']===[],'Unavailable current context cannot reconstruct approval');
    ph_reset();$screen=ph_screen();$screen['link_id']=ph_id(99);[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,$original);ph_ok($review===null&&$retry===[]&&$env['calls']===[],'Changed screen link clears captured review');
    ph_reset();$screen=ph_screen();$_POST['link_id']=ph_id(99);[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,$original);ph_ok($review===null&&$retry===[],'Cross-link retry is cleared');
    ph_reset();$screen=ph_screen();$_POST['action']='php_controls_review';$_POST['desired']=" PHP 8.3\t";[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($review===null&&$retry['desired']===" PHP 8.3\t"&&$env['calls']===[],'Failed selection preserves exact raw option without approving');
    ph_reset();$screen=ph_screen();$_POST['action']='php_controls_delegate';[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($retry['delegation']===['enabled'=>true,'expected_permission_id'=>'','event_id'=>ph_id(5),'review_sha256'=>ph_hash(1)]&&$env['calls']===[],'Delegation retry retains exact event hash and expected permission');
    ph_reset();$screen=ph_screen();$_POST['action']='php_controls_delegate';$_POST['expected_permission_id']=ph_id(99);[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($retry===[]&&$screen['delegation']['enable']===null&&$screen['delegation']['disable']===null,'Changed permission suppresses both replacement grant forms');
    ph_reset();$screen=ph_screen();$_POST['action']='php_controls_delegate';$_POST['review_sha256']=ph_hash(99);[$review,$retry]=provider_php_controls_http_retry(true,null,$screen,null);ph_ok($retry===[]&&$screen['delegation']['enable']===null&&$screen['delegation']['disable']===null,'Changed delegation hash cannot silently acquire fresh approval');
    ph_reset();$screen=ph_screen();$env['review_error']=new PDOException('synthetic unavailable storage');ph_refuse(function()use(&$screen){provider_php_controls_http_retry(true,null,$screen,null);},'Retry preserves database failure',PDOException::class);
    ph_reset();$screen=ph_screen();$env['review_error']=new TypeError('synthetic engine failure');ph_refuse(function()use(&$screen){provider_php_controls_http_retry(true,null,$screen,null);},'Retry preserves engine failure',TypeError::class);
    $passed=true;
} catch (Throwable $e) { $passed=false;$failure=['class'=>get_class($e),'message'=>$e->getMessage()]; }
echo json_encode(['passed'=>$passed,'checks'=>$checks,'count'=>count($checks),'scope'=>'Exact production dispatcher/parser bodies and early portal route slice with modeled authentication, CSRF, mutex, transaction and service seams; no SQL, HTTP, browser, provider or live authority execution.','source_sha256'=>hash('sha256',$source),'portal_router_sha256'=>hash('sha256',$router),'early_gate_sha256'=>hash('sha256',$earlyGate),'pdo_exception_modeled'=>$modeledPdo,'failure'=>$failure??null],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($passed?0:1);
