<?php
/** Closed destination and actual login dispatch with authentication/CSRF/redirect seams; no SQL or HTTP. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
require CWP_CODE_ROOT.'/includes/security/input.php';
require CWP_CODE_ROOT.'/includes/urls.php';
require CWP_CODE_ROOT.'/app/hosting/catalog_login.php';
$GLOBALS['cwp_config']=['site_url'=>'https://studio.synthetic.invalid/subfolder'];
$checks=[];$trace=[];$redirects=[];$credentials=[];$supportContext=null;$csrfAllowed=true;$workspaceReady=true;$loginAllowed=true;
function hcl_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function support_current():?array {global $supportContext;return $supportContext;}
function check_csrf():void {global $trace,$csrfAllowed;$trace[]='csrf';if(!$csrfAllowed)throw new InvalidArgumentException('Synthetic CSRF denied.');}
function support_check_csrf(string $id):void {global $trace,$csrfAllowed;$trace[]='support_csrf';if(!$csrfAllowed)throw new InvalidArgumentException('Synthetic support CSRF denied.');}
function studio_ready():bool {global $trace,$workspaceReady;$trace[]='ready';return $workspaceReady;}
function studio_login(string $email,string $password):void {
    global $trace,$credentials,$loginAllowed;$trace[]='login';$credentials[]=[$email,$password];
    if(!$loginAllowed)throw new InvalidArgumentException('Email or password not recognized.');
    $_SESSION['client_id']=17;
}
/** Capture the response location. The actual login branch ends here; no real header/session I/O. */
function redirect(string $url):void {global $trace,$redirects;$trace[]='redirect';$redirects[]=$url;}
function support_record(string $action,string $surface,string $identity,string $status):void {global $trace;$trace[]='support_blocked';}
function report_error(Throwable $error):string {global $trace;$trace[]='unexpected:'.get_class($error);return 'synthetic-error';}
function hcl_action(array $query,array $post=[],bool $login=true,bool $csrf=true,bool $ready=true,?array $support=null,string $method='POST'):array {
    global $trace,$redirects,$credentials,$supportContext,$csrfAllowed,$workspaceReady,$loginAllowed;
    $trace=[];$redirects=[];$credentials=[];$supportContext=$support;$csrfAllowed=$csrf;$workspaceReady=$ready;$loginAllowed=$login;
    $_GET=$query;$_POST=$post+['action'=>'login','email'=>'  OWNER@EXAMPLE.INVALID  ','password'=>'  synthetic password  '];$_SESSION=[];$_SERVER['REQUEST_METHOD']=$method;
    $error='';$supportRequested=array_key_exists('support',$_GET);$supportId=is_string($_GET['support']??null)?$_GET['support']:'';$client=null;$jobId=0;
    require CWP_CODE_ROOT.'/portal/controllers/actions.php';
    return ['trace'=>$trace,'redirects'=>$redirects,'credentials'=>$credentials,'error'=>$error,'session'=>$_SESSION];
}
try{
    // The real URL builder is one standalone function; retain its support behavior rather than recreate it.
    $lines=preg_split('/\r?\n/',file_get_contents(CWP_CODE_ROOT.'/app/clients/workspace.php'));
    $urlFunctions=array_values(array_filter($lines,static fn($line)=>str_starts_with($line,'function studio_url(')));
    if(count($urlFunctions)!==1)throw new LogicException('Studio URL source boundary changed.');eval($urlFunctions[0]);
    $id=str_repeat('a',32);$hash=str_repeat('b',64);$selected=['hosting'=>'checkout','offer'=>$id,'offer_sha256'=>$hash];
    hcl_ok('Exact selected offer and digest survive normalization',hosting_catalog_login_destination($selected)===$selected);
    $private=['return'=>'https://foreign.invalid','redirect'=>'//foreign.invalid','next'=>'/admin','url'=>'javascript:alert(1)','job'=>'99','invoice'=>'123','request_id'=>str_repeat('c',32),'checkout_result'=>str_repeat('d',32),'project_mode'=>'existing','project_id'=>'7','current_password'=>'PRIVATE'];
    hcl_ok('Only the three closed checkout keys survive',hosting_catalog_login_destination($selected+$private)===$selected);
    hcl_ok('Unrelated destinations default to workspace',hosting_catalog_login_destination($private)===[]);
    foreach(['requests','https://foreign.invalid','//foreign.invalid',[],null] as $i=>$kind)hcl_ok('Unknown hosting destination is discarded '.$i,hosting_catalog_login_destination(['hosting'=>$kind,'offer'=>$id,'offer_sha256'=>$hash])===[]);
    foreach(['',str_repeat('a',31),str_repeat('a',33),str_repeat('A',32),$id."\n",['nested'],17] as $i=>$bad){
        hcl_ok('Invalid offer falls back to local offers '.$i,hosting_catalog_login_destination(['hosting'=>'checkout','offer'=>$bad,'offer_sha256'=>$hash])===['hosting'=>'offers']);
    }
    foreach(['',str_repeat('b',63),str_repeat('b',65),str_repeat('B',64),$hash."\n",['nested'],17] as $i=>$bad){
        hcl_ok('Invalid digest falls back to local offers '.$i,hosting_catalog_login_destination(['hosting'=>'checkout','offer'=>$id,'offer_sha256'=>$bad])===['hosting'=>'offers']);
    }
    foreach(['',null,[],false,'synthetic'] as $i=>$support)hcl_ok('Any support query key discards hosting destination '.$i,hosting_catalog_login_destination($selected+['support'=>$support])===[]);
    foreach(['1'=>1,'42'=>42,'100000'=>100000] as $input=>$page)hcl_ok('Offers pagination admits bounded page '.$page,hosting_catalog_login_destination(['hosting'=>'offers','p'=>(string)$input]+$private)===['hosting'=>'offers','p'=>$page]);
    foreach(['0','01','-1','100001','9999999','1.0',"1\n",[],null,42] as $i=>$page)hcl_ok('Invalid offers page defaults to one '.$i,hosting_catalog_login_destination(['hosting'=>'offers','p'=>$page])===['hosting'=>'offers','p'=>1]);
    $expected=site_url('portal/index.php').'?'.http_build_query($selected);
    $result=hcl_action($selected+$private);
    hcl_ok('Actual login branch preserves exact offer and hash after success',$result['redirects']===[$expected]&&$result['error']==='');
    hcl_ok('CSRF and login precede the destination response',$result['trace']===['csrf','ready','login','redirect']&&$result['session']['client_id']===17);
    hcl_ok('Existing email normalization and raw password semantics are preserved',$result['credentials']===[['owner@example.invalid','  synthetic password  ']]);
    hcl_ok('Login response never exposes private or external destination fields',!str_contains($expected,'PRIVATE')&&!str_contains($result['redirects'][0],'foreign.invalid')&&!str_contains($result['redirects'][0],'invoice='));
    $result=hcl_action($selected,login:false);
    hcl_ok('Failed login never redirects or authenticates',$result['redirects']===[]&&$result['session']===[]&&$result['trace']===['csrf','ready','login']&&$result['error']==='Email or password not recognized.');
    hcl_ok('Failed login leaves the selected query available for another attempt',$_GET===$selected);
    $result=hcl_action($selected,csrf:false);
    hcl_ok('Invalid CSRF prevents authentication and redirect',$result['credentials']===[]&&$result['redirects']===[]&&$result['trace']===['csrf']&&$result['error']==='Synthetic CSRF denied.');
    $result=hcl_action($selected,ready:false);
    hcl_ok('Unavailable workspace prevents authentication and redirect',$result['credentials']===[]&&$result['redirects']===[]&&$result['trace']===['csrf','ready']&&$result['error']!=='');
    $result=hcl_action($selected,['email'=>['nested']]);
    hcl_ok('Malformed login input never reaches authentication or redirect',$result['credentials']===[]&&$result['redirects']===[]&&$result['error']!=='');
    $result=hcl_action($selected+['support'=>'synthetic'],support:['session'=>['id'=>'synthetic'],'actor'=>['id'=>2]]);
    hcl_ok('Resolved support action cannot authenticate or redirect into checkout',$result['credentials']===[]&&$result['redirects']===[]&&$result['trace']===['support_csrf','ready','support_blocked']&&$result['error']!=='');
    foreach([[$private,[]],[['hosting'=>'checkout','offer'=>'bad','offer_sha256'=>$hash],['hosting'=>'offers']],[['hosting'=>'offers','p'=>'42']+$private,['hosting'=>'offers','p'=>42]]] as $i=>[$query,$destination]){
        $result=hcl_action($query);$local=site_url('portal/index.php').($destination?'?'.http_build_query($destination):'');
        hcl_ok('Actual login discards unapproved destination data '.$i,$result['redirects']===[$local]&&$result['error']==='');
    }
    $result=hcl_action([],['offer'=>$id,'offer_sha256'=>$hash,'hosting'=>'checkout','return'=>'https://foreign.invalid']);
    hcl_ok('POST fields cannot replace the query-bound destination',$result['redirects']===[site_url('portal/index.php')]);
    $result=hcl_action($selected,method:'GET');
    hcl_ok('GET does not execute login or a redirect',$result['trace']===[]&&$result['redirects']===[]&&$result['session']===[]);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected hosting login failure: '.$e->getMessage(),'passed'=>false];}
$passed=!array_filter($checks,static fn($row)=>$row['passed']!==true);
echo json_encode(['passed'=>$passed,'scope'=>'Real closed destination and portal login action branch with modeled authentication, CSRF and redirect; no SQL, session regeneration, HTTP/browser or checkout acceptance.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;exit($passed?0:1);
