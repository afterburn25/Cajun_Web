<?php
/** Actual views and controller bodies; only native identity/storage and shell boundaries are modeled. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',getenv('CWP_TEST_CODE_ROOT')?:dirname(__DIR__).'/site');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
$checks=[];$calls=[];$support=false;$denyActor=false;$badCsrf=false;$rateAllowed=true;$failure=null;$coldSettings=false;$denyOwner=false;$_POST=[];$_GET=[];$_SESSION=[];
function esc(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url(string $view='dashboard',array $params=[]):string{return '/subfolder/admin/index.php?'.http_build_query(['view'=>$view]+$params);}
function studio_url(array $params=[]):string{return '/subfolder/portal/index.php'.($params?'?'.http_build_query($params):'');}
function csrf_field():string{return '<input type="hidden" name="csrf" value="fresh-csrf">';}
function support_csrf_field():string{return csrf_field();}
function support_current():bool{return $GLOBALS['support'];}
function provider_subdomain_requests_actor(?int $client,?string $password=null):array{$GLOBALS['calls'][]=['actor',$client];if($GLOBALS['denyActor'])throw new RuntimeException('Direct identity required.');if($client===null&&$GLOBALS['coldSettings'])throw new RuntimeException('An owner must prepare provider settings first.');return ['kind'=>$client===null?'owner':'client','id'=>$client??4];}
function provider_settings_manager(bool $ready=true):array{$GLOBALS['calls'][]=['owner',$ready];if($GLOBALS['denyOwner'])throw new RuntimeException('PRIVATE_AUTH_REASON');if($ready&&$GLOBALS['coldSettings'])throw new RuntimeException('An owner must prepare provider settings first.');return ['id'=>4,'role'=>'owner','name'=>'Actual owner'];}
function check_csrf():void{$GLOBALS['calls'][]=['csrf'];if($GLOBALS['badCsrf'])throw new RuntimeException('CSRF refused.');}
function consume_rate(string $key,int $limit,int $seconds):bool{$GLOBALS['calls'][]=['rate',[$key,$limit,$seconds]];return $GLOBALS['rateAllowed'];}
function sur_mutate(string $kind,array $args):array{$GLOBALS['calls'][]=[$kind,$args];if($GLOBALS['failure'])throw $GLOBALS['failure'];return [];}
function provider_subdomain_requests_prepare(...$args):void{sur_mutate('prepare',$args);}
function provider_subdomain_requests_create(...$args):array{return sur_mutate('create',$args);}
function provider_subdomain_requests_change(...$args):array{return sur_mutate('change',$args);}
function provider_subdomain_requests_approve(...$args):array{return sur_mutate('approve',$args);}
function provider_subdomain_requests_verify(...$args):array{return sur_mutate('verify',$args);}
function provider_subdomain_requests_recover(...$args):array{return sur_mutate('recover',$args);}
function provider_subdomain_requests_screen(?int $client=null,int $page=1,?string $detail=null):array{$GLOBALS['calls'][]=['screen',[$client,$page,$detail]];if($GLOBALS['failure'])throw $GLOBALS['failure'];return $GLOBALS['screen'];}
function flash(string $message):void{$GLOBALS['calls'][]=['flash',$message];}
class SurRedirect extends Exception{public function __construct(public string $url){parent::__construct('redirect');}}
class SurStop extends Exception{}
class SurDenied extends Exception{public function __construct(public int $status){parent::__construct('denied');}}
function redirect(string $url):never{throw new SurRedirect($url);}
function fail_page(string $message,int $status):never{throw new SurDenied($status);}
function admin_head(string $title,string $view,array $owner):void{if($owner!==['id'=>4,'role'=>'owner','name'=>'Actual owner'])throw new LogicException('Wrong owner shell.');}
function site_head(string $title,string $view):void{}
function show_flash():void{}
function admin_foot():never{throw new SurStop();}
function site_foot(bool $arg):never{throw new SurStop();}
function login_head(string $title):void{}
function login_foot():never{throw new SurStop();}
function studio_ready():bool{return true;}
function sur_ok(string $name,bool $passed):void{foreach($GLOBALS['checks'] as $row)if($row['name']===$name)throw new LogicException('Duplicate test label.');$GLOBALS['checks'][]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function sur_html(callable $fn):string{ob_start();try{$fn();return (string)ob_get_contents();}finally{ob_end_clean();}}
function sur_render(callable $fn):string{return sur_html(static function()use($fn):void{try{$fn();}catch(SurStop){return;}throw new LogicException('Footer not reached.');});}
function sur_inputs(string $html):array{preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/',$html,$rows,PREG_SET_ORDER);$out=[];foreach($rows as $row)$out[$row[1]][]=html_entity_decode($row[2],ENT_QUOTES|ENT_HTML5,'UTF-8');return $out;}
function sur_refuse(string $label,callable $fn):void{try{$fn();}catch(InvalidArgumentException|RuntimeException){sur_ok($label,true);return;}sur_ok($label,false);}
function sur_reset():void{$GLOBALS['calls']=[];$GLOBALS['support']=false;$GLOBALS['denyActor']=false;$GLOBALS['badCsrf']=false;$GLOBALS['rateAllowed']=true;$GLOBALS['failure']=null;$GLOBALS['coldSettings']=false;$GLOBALS['denyOwner']=false;$_GET=[];$_SESSION=[];}
function sur_action(callable $fn):string{try{$fn();}catch(SurRedirect $e){return $e->url;}throw new LogicException('Expected fixed redirect.');}
function sur_args(string $kind):array{foreach($GLOBALS['calls'] as $call)if($call[0]===$kind)return $call[1];throw new LogicException('Missing service call.');}
function sur_kinds():array{return array_column($GLOBALS['calls'],0);}
try{
    require CWP_CODE_ROOT.'/app/providers/subdomain_requests_views.php';
    foreach(['portal','admin'] as $area){$text=file_get_contents(CWP_CODE_ROOT.'/'.$area.'/controllers/subdomain_requests.php');$start=strpos($text,"\nfunction ");if($start===false)throw new LogicException('Missing controller declarations.');eval(substr($text,$start+1));}
    $id=str_repeat('a',32);$link=str_repeat('b',32);$hash=str_repeat('c',64);$event=str_repeat('d',32);$dispatch=str_repeat('e',32);$proof='original.signed-proof';
    $option=['link_id'=>$link,'package_id'=>321,'parent_domain'=>'example.test','review_sha256'=>$hash];
    $item=['id'=>$id,'package_id'=>321,'parent_domain'=>'example.test','subdomain'=>'shop.example.test','created_epoch'=>1760000000,'state'=>'pending','latest_event'=>$id,'request_sha256'=>$hash,'current_assignment'=>true,'can_cancel'=>true,'can_decline'=>true,'note'=>'','owner_request_id'=>null,'last_check'=>null,'approval'=>null,'dispatch'=>null];
    $screen=['ready'=>true,'options'=>[$option],'items'=>[$item],'page'=>1,'has_more'=>false];
    $html=sur_html(fn()=>provider_subdomain_requests_render($screen,false));$fields=sur_inputs($html);
    sur_ok('Client gets local create and pending cancellation only',$fields['action']===['subdomain_request_create','subdomain_request_cancel']);
    sur_ok('Client request binds exact assigned parent and review',$fields['parent_domain']===['example.test']&&$fields['link_id']===[$link]&&$fields['review_sha256']===[$hash]);
    sur_ok('Every form uses fresh CSRF and fixed client route',$fields['csrf']===['fresh-csrf','fresh-csrf']&&substr_count($html,'action="/subfolder/portal/index.php?subdomain_requests=1&amp;request_page=1"')===2);
    sur_ok('New local request and cancellation identities are canonical distinct values',preg_match('/\A[a-f0-9]{32}\z/',$fields['request_id'][0])===1&&preg_match('/\A[a-f0-9]{32}\z/',$fields['event_id'][0])===1&&$fields['request_id'][0]!==$fields['event_id'][0]);
    sur_ok('Client sees no provider credentials approval proof or send form',!str_contains($html,'current_password')&&!str_contains($html,'review_proof')&&!str_contains($html,'provider_subdomain_request_')&&str_contains($html,'does not send a provider change'));
    sur_ok('Client confirmation is explicit and unchecked',substr_count($html,'name="confirmed" value="1" required')===2&&preg_match('/<input\b[^>]*\schecked(?:\s|=|>)/i',$html)===0);
    $poison=$screen;$poison['items'][0]['subdomain']='<script>alert(1)</script>';$poison['items'][0]['note']='</p><img src=x onerror=x>';$poison['items'][0]['approval']=['review_proof'=>'PRIVATE_PROOF'];$poison['items'][0]['dispatch']=['id'=>'PRIVATE_DISPATCH'];$poison['items'][0]['owner_request_id']='PRIVATE_OWNER';$poison['items'][0]['private']='PRIVATE_KEY';
    $html=sur_html(fn()=>provider_subdomain_requests_render($poison,false));
    sur_ok('Displayed client names and notes are escaped',!str_contains($html,'<script>')&&!str_contains($html,'<img src=x')&&str_contains($html,'&lt;script&gt;'));
    sur_ok('Client renderer ignores owner-only and private fields',!str_contains($html,'PRIVATE_'));
    foreach(['pending','cancelled','declined','interrupted','not_sent','unknown','observed'] as $state){$row=$item;$row['state']=$state;$row['can_cancel']=false;$row['can_decline']=false;$html=sur_html(fn()=>provider_subdomain_requests_ui_item($row,false));sur_ok('Honest '.$state.' status without a send form',str_contains($html,esc(provider_subdomain_requests_ui_label($state)))&&!str_contains($html,'<form'));}
    $row=$item;$row['state']='observed';$row['can_cancel']=false;$row['last_check']=['disposition'=>'observed','recorded_epoch'=>1760000030];$row['current_assignment']=false;
    $html=sur_html(fn()=>provider_subdomain_requests_ui_item($row,false));sur_ok('Observed status remains scoped and changed assignment remains visible',str_contains($html,'DNS, certificate and website availability have not been checked')&&str_contains($html,'assignment or connection changed')&&str_contains($html,'2025-10-09 08:53:50 UTC'));
    foreach(['absent','unavailable'] as $state){$row['state']='unknown';$row['last_check']['disposition']=$state;$html=sur_html(fn()=>provider_subdomain_requests_ui_item($row,false));sur_ok('Readback '.$state.' cannot claim readiness',!str_contains($html,'ready to use.</strong>')&&!str_contains($html,'<form')&&str_contains($html,'does not confirm'));}
    $unready=['ready'=>false,'options'=>[],'items'=>[],'page'=>1,'has_more'=>false];$html=sur_html(fn()=>provider_subdomain_requests_render($unready,false));sur_ok('Unprepared client has no setup authority',!str_contains($html,'<form')&&str_contains($html,'not enabled'));
    $html=sur_html(fn()=>provider_subdomain_requests_render($unready,true));$fields=sur_inputs($html);sur_ok('Owner explicitly enables intake with blank password',$fields['action']===['provider_subdomain_request_prepare']&&$fields['current_password']===['']&&str_contains($html,'name="reviewed"')&&preg_match('/<input\b[^>]*\schecked(?:\s|=|>)/i',$html)===0);
    $posted=['action'=>'subdomain_request_create','request_id'=>$id,'link_id'=>$link,'parent_domain'=>'example.test','subdomain'=>'Shop.example.test','review_sha256'=>$hash,'confirmed'=>'1','current_password'=>'PASSWORD_SENTINEL','url'=>'https://outside.invalid'];
    $retry=provider_subdomain_requests_ui_retry($posted,false);sur_ok('Retry whitelist excludes password confirmation and destination',!isset($retry['current_password'],$retry['confirmed'],$retry['url'])&&$retry['subdomain']==='Shop.example.test');
    $html=sur_html(fn()=>provider_subdomain_requests_render($screen,false,$retry));$fields=sur_inputs($html);sur_ok('Failed create retains exact original ID names and review without normalization',$fields['request_id'][0]===$id&&$fields['subdomain']===['Shop.example.test']&&$fields['review_sha256']===[$hash]&&!str_contains($html,'PASSWORD_SENTINEL'));
    $changed=$screen;$changed['options'][0]['review_sha256']=str_repeat('f',64);$html=sur_html(fn()=>provider_subdomain_requests_render($changed,false,$retry));sur_ok('Changed intake review keeps original hash and disables replacement request',str_contains($html,'value="'.$hash.'"')&&!str_contains($html,'value="'.str_repeat('f',64).'"')&&str_contains($html,'disabled>Request subdomain review'));
    $html=sur_html(fn()=>provider_subdomain_requests_render(null,false,$retry));sur_ok('Unavailable intake preserves entered name in a disabled form',str_contains($html,'Shop.example.test')&&str_contains($html,'disabled>Request subdomain review')&&!str_contains($html,'provider_subdomain_request_prepare'));
    $owner=$screen;$owner['options']=[];$owner['items'][0]['approval']=['request_id'=>$dispatch,'review_proof'=>$proof,'expires_epoch'=>1760000300,'observed_epoch'=>1760000000];
    $html=sur_html(fn()=>provider_subdomain_requests_render($owner,true));$fields=sur_inputs($html);sur_ok('Owner has separate decline and approve forms',$fields['action']===['provider_subdomain_request_decline','provider_subdomain_request_approve']);
    sur_ok('Approval binds original request event and fresh PR234 review',$fields['owner_request_id']===[$dispatch]&&$fields['review_proof']===[$proof]&&$fields['request_sha256']===[$hash]&&$fields['expected_event']===[$id,$id]);
    sur_ok('Owner never posts editable package or requested hostname overrides',!isset($fields['subdomain'],$fields['parent_domain'],$fields['package_id'],$fields['client_id']));
    sur_ok('Every owner password is blank and each approval is unchecked',$fields['current_password']===['','']&&preg_match('/<input\b[^>]*\schecked(?:\s|=|>)/i',$html)===0);
    $approvalPost=['action'=>'provider_subdomain_request_approve','request_id'=>$id,'expected_event'=>$id,'request_sha256'=>$hash,'owner_request_id'=>str_repeat('f',32),'review_proof'=>'prior.signed-proof','current_password'=>'PASSWORD_SENTINEL','reviewed'=>'1'];
    $approvalRetry=provider_subdomain_requests_ui_retry($approvalPost,true);$html=sur_html(fn()=>provider_subdomain_requests_render($owner,true,$approvalRetry));$fields=sur_inputs($html);
    sur_ok('Password-error retry preserves old proof and operation ID rather than replacing nonce',$fields['owner_request_id']===[$approvalPost['owner_request_id']]&&$fields['review_proof']===['prior.signed-proof']&&str_contains($html,'original approval is retained')&&!str_contains($html,'PASSWORD_SENTINEL')&&!str_contains($html,'disabled>Approve and request subdomain'));
    $stale=$owner;$stale['items'][0]['latest_event']=$event;$html=sur_html(fn()=>provider_subdomain_requests_render($stale,true,$approvalRetry));sur_ok('Changed request event disables retained approval',str_contains($html,'disabled>Approve and request subdomain')&&str_contains($html,'value="prior.signed-proof"'));
    $html=sur_html(fn()=>provider_subdomain_requests_render(null,true,$approvalRetry));sur_ok('Read failure does not lose original approval or fabricate a fresh form',str_contains($html,'value="prior.signed-proof"')&&str_contains($html,'disabled>Reload to continue')&&!str_contains($html,'provider_subdomain_request_prepare'));
    $cancelRetry=provider_subdomain_requests_ui_retry(['action'=>'subdomain_request_cancel','request_id'=>$id,'expected_event'=>$event,'event_id'=>$dispatch],false);$html=sur_html(fn()=>provider_subdomain_requests_render($screen,false,$cancelRetry));sur_ok('Stale cancellation retains original event and is disabled',str_contains($html,'value="'.$event.'"')&&str_contains($html,'value="'.$dispatch.'"')&&str_contains($html,'disabled>Withdraw request'));
    $declineRetry=provider_subdomain_requests_ui_retry(['action'=>'provider_subdomain_request_decline','request_id'=>$id,'expected_event'=>$id,'event_id'=>$dispatch,'note'=>'Client-visible <reason>'],true);$closed=$owner;$closed['items'][0]['can_decline']=false;$closed['items'][0]['approval']=null;$closed['items'][0]['state']='declined';
    $html=sur_html(fn()=>provider_subdomain_requests_render($closed,true,$declineRetry));sur_ok('No-longer-pending decline retains note and event without active submission',str_contains($html,'Client-visible &lt;reason&gt;')&&str_contains($html,'value="'.$dispatch.'"')&&str_contains($html,'disabled>Reload to continue'));
    $sent=$closed;$sent['items'][0]['state']='unknown';$sent['items'][0]['dispatch']=['id'=>$dispatch,'can_verify'=>true,'recovery_ledger_sha256'=>null];$html=sur_html(fn()=>provider_subdomain_requests_render($sent,true));$fields=sur_inputs($html);sur_ok('Owner follow-up uses exact client and original operation IDs',$fields['action']===['provider_subdomain_request_verify']&&$fields['request_id']===[$id]&&$fields['owner_request_id']===[$dispatch]&&str_contains($html,'does not send the add request again'));
    $sent['items'][0]['dispatch']['recovery_ledger_sha256']=$hash;$html=sur_html(fn()=>provider_subdomain_requests_render($sent,true));$fields=sur_inputs($html);sur_ok('Recovery replaces verification and requires explicit saved-outcome review',$fields['action']===['provider_subdomain_request_recover']&&$fields['ledger_sha256']===[$hash]&&str_contains($html,'name="reviewed"'));
    $html=sur_html(fn()=>provider_subdomain_requests_render($sent,false));sur_ok('Client never sees owner verification or recovery capability',!str_contains($html,'provider_subdomain_request_')&&!str_contains($html,'ledger_sha256')&&!str_contains($html,$dispatch));
    $paged=$unready;$paged['ready']=true;$paged['page']=2;$paged['has_more']=true;$html=sur_html(fn()=>provider_subdomain_requests_render($paged,true));sur_ok('Pagination uses bounded real owner route',str_contains($html,'view=subdomain-requests&amp;request_page=3')&&str_contains($html,'view=subdomain-requests&amp;request_page=1'));
    sur_ok('Client detail URL is fixed and exact',provider_subdomain_requests_ui_url(false,$id)==='/subfolder/portal/index.php?subdomain_requests=1&request_page=1&request_id='.$id);
    foreach([' '.$id,strtoupper($id),str_repeat('a',33),['a']] as $n=>$bad)sur_refuse('Exact ID refusal '.$n,fn()=>provider_subdomain_requests_ui_id(['request_id'=>$bad],'request_id'));
    foreach(['0','01','10001','https://outside.invalid',[]] as $n=>$bad)sur_refuse('Exact page refusal '.$n,fn()=>provider_subdomain_requests_ui_query(['request_page'=>$bad]));
    sur_refuse('Oversized name is rejected rather than truncated',fn()=>provider_subdomain_requests_ui_text(['subdomain'=>str_repeat('a',254)],'subdomain',253));
    sur_refuse('Invalid UTF8 rejected before mutation',fn()=>provider_subdomain_requests_ui_text(['subdomain'=>"\xc3\x28"],'subdomain',253));
    sur_ok('Name parser never trims or lowercases incoming text',provider_subdomain_requests_ui_text(['subdomain'=>' Shop.Example.test '],'subdomain',253)===' Shop.Example.test ');
    sur_reset();$_POST=$posted;$url=sur_action(fn()=>provider_subdomain_requests_client_action($posted['action'],['id'=>42]));sur_ok('Actual client action forwards exact contract and authenticated client',sur_args('create')===[$id,42,$link,$hash,'example.test','Shop.example.test',true]&&$url===provider_subdomain_requests_ui_url(false,$id));
    sur_ok('Actor and CSRF checks precede rate and mutation',array_slice(sur_kinds(),0,4)===['actor','csrf','rate','create']);
    foreach(['actor','csrf','rate'] as $boundary){sur_reset();$_POST=$posted;$denyActor=$boundary==='actor';$badCsrf=$boundary==='csrf';$rateAllowed=$boundary!=='rate';sur_refuse('Client '.$boundary.' failure stops mutation',fn()=>provider_subdomain_requests_client_action($posted['action'],['id'=>42]));sur_ok('No create on '.$boundary.' refusal',!in_array('create',sur_kinds(),true));}
    sur_reset();$_POST=$posted;$_POST['subdomain']=str_repeat('a',254);sur_refuse('Actual create refuses oversized field without truncation',fn()=>provider_subdomain_requests_client_action($posted['action'],['id'=>42]));sur_ok('Oversized create performs no service mutation',!in_array('create',sur_kinds(),true));
    sur_reset();$_POST=$posted;$_POST['action']=' '.$posted['action'];sur_refuse('Trimmed action alias is not accepted by new controller',fn()=>provider_subdomain_requests_client_action($posted['action'],['id'=>42]));
    sur_reset();$_POST=['action'=>'subdomain_request_cancel','request_id'=>$id,'expected_event'=>$id,'event_id'=>$event,'confirmed'=>'1','note'=>'ignored'];sur_action(fn()=>provider_subdomain_requests_client_action($_POST['action'],['id'=>42]));sur_ok('Cancellation forwards exact event and empty note',sur_args('change')===[$id,$id,$event,'cancel','',42]);
    sur_reset();$_POST=$approvalPost;sur_action(fn()=>provider_subdomain_requests_admin_action($_POST['action']));sur_ok('Actual approval forwards original operation proof and raw password',sur_args('approve')===[$id,$id,$hash,$approvalPost['owner_request_id'],'prior.signed-proof',true,'PASSWORD_SENTINEL']);
    sur_reset();$_POST=$approvalPost;$badCsrf=true;sur_refuse('Owner CSRF refusal stops approval',fn()=>provider_subdomain_requests_admin_action($_POST['action']));sur_ok('Owner CSRF failed before native approval',sur_kinds()===['actor','csrf']);
    sur_reset();$_POST=$approvalPost;unset($_POST['reviewed']);sur_refuse('Owner approval requires explicit confirmation',fn()=>provider_subdomain_requests_admin_action($_POST['action']));
    sur_reset();$_POST=['action'=>'provider_subdomain_request_decline','request_id'=>$id,'expected_event'=>$id,'event_id'=>$event,'note'=>'  Reviewed reason  ','reviewed'=>'1','current_password'=>' pw '];sur_action(fn()=>provider_subdomain_requests_admin_action($_POST['action']));sur_ok('Decline forwards exact note and password without silent normalization',sur_args('change')===[$id,$id,$event,'decline','  Reviewed reason  ',null,' pw ']);
    sur_reset();$_POST=['action'=>'provider_subdomain_request_prepare','reviewed'=>'1','current_password'=>' pw '];sur_action(fn()=>provider_subdomain_requests_admin_action($_POST['action']));sur_ok('Preparation is explicit owner password action',sur_args('prepare')===[' pw ']&&array_slice(sur_kinds(),0,3)===['actor','csrf','prepare']);
    foreach(['verify','recover'] as $kind){sur_reset();$_POST=['action'=>'provider_subdomain_request_'.$kind,'request_id'=>$id,'owner_request_id'=>$dispatch,'ledger_sha256'=>$hash,'reviewed'=>'1','current_password'=>'pw'];$url=sur_action(fn()=>provider_subdomain_requests_admin_action($_POST['action']));sur_ok('Exact '.$kind.' wrapper returns to original client request',sur_args($kind)===($kind==='verify'?[$id,$dispatch,'pw']:[$id,$dispatch,$hash,'pw'])&&$url===provider_subdomain_requests_ui_url(true,$id));}
    foreach(['contact_id','contact_auth'] as $key){sur_reset();$_SESSION[$key]=null;$_POST=$posted;sur_refuse('Contact provenance rejected '.$key,fn()=>provider_subdomain_requests_client_action($posted['action'],['id'=>42]));sur_ok('Contact stops before identity normalization '.$key,$calls===[]);}
    sur_reset();$_GET=['support'=>''];sur_refuse('Support query rejected before client normalization',fn()=>provider_subdomain_requests_portal_guard());
    sur_reset();$support=true;sur_refuse('Active support context rejected',fn()=>provider_subdomain_requests_portal_guard());
    sur_reset();$_POST=$approvalPost;$failure=new RuntimeException('Original approval expired.');sur_refuse('Service refusal does not redirect or flash success',fn()=>provider_subdomain_requests_admin_action($_POST['action']));sur_ok('Failed approval preserves raw POST and no success flash',$_POST===$approvalPost&&!in_array('flash',sur_kinds(),true));
    sur_reset();$_POST=$approvalPost;$failure=new PDOException('PRIVATE_SQL_PATH');$html=sur_render(fn()=>provider_subdomain_requests_admin_render(''));
    sur_ok('Actual owner render failure retains proof and suppresses private SQL details',str_contains($html,'prior.signed-proof')&&str_contains($html,'could not be loaded')&&!str_contains($html,'PRIVATE_SQL_PATH')&&!str_contains($html,'PASSWORD_SENTINEL'));
    sur_ok('Owner failed render selects exact retained request',sur_args('screen')===[null,1,$id]);
    sur_reset();$_POST=$posted;$failure=new RuntimeException('Saved request history needs review.');$html=sur_render(fn()=>provider_subdomain_requests_client_render(['id'=>42],''));sur_ok('Actual client render failure preserves entered name and original intent',str_contains($html,'Shop.example.test')&&str_contains($html,'value="'.$id.'"')&&str_contains($html,'disabled>Request subdomain review'));
    // Execute the actual early route guard and actual anonymous login view, without booting SQL.
    $router=file_get_contents(CWP_CODE_ROOT.'/portal/controllers/router.php');$start=strpos($router,"\$subdomainRequestRoute=array_key_exists('subdomain_requests',\$_GET)||");$end=strpos($router,"\nif(array_key_exists('forwarding',\$_GET)",$start?:0);
    if($start===false||$end===false)throw new LogicException('Missing current early portal guard.');$guard=substr($router,$start,$end-$start);
    sur_ok('Actual direct-client guard precedes studio_client resolution',$start<strpos($router,'$client=studio_client();'));
    sur_reset();$_GET=['subdomain_requests'=>'1'];$_POST=[];eval($guard);$client=null;$error='';$invite='';$html=sur_render(static function()use($client,$error,$invite):void{require CWP_CODE_ROOT.'/portal/views/auth.php';});sur_ok('Anonymous request query reaches actual sign-in form',str_contains($html,'name="action" value="login"')&&$calls===[]);
    foreach(['query','post'] as $mode){sur_reset();$_SESSION=['contact_id'=>null];$_GET=$mode==='query'?['subdomain_requests'=>'1']:[];$_POST=$mode==='post'?['action'=>'subdomain_request_create']:[];$denied=false;try{eval($guard);}catch(SurDenied $e){$denied=$e->status===403;}sur_ok('Actual early guard denies contact '.$mode.' route before authentication',$denied&&$calls===[]);}
    sur_reset();$_GET=['support'=>'','subdomain_requests'=>'1'];$_POST=[];$denied=false;try{eval($guard);}catch(SurDenied $e){$denied=$e->status===403;}sur_ok('Actual early support route guard refuses before resolving support',$denied);
    sur_reset();$_GET=[];$_POST=['action'=>'subdomain_request_create'];eval($guard);sur_ok('POST-only request route retains the dedicated inline-error renderer',$subdomainRequestRoute===true&&str_contains($router,'if($subdomainRequestRoute)provider_subdomain_requests_client_render($client,$error);'));
    $adminRouter=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/router.php');$adminActions=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/actions.php');$staff=file_get_contents(CWP_CODE_ROOT.'/app/staff/accounts.php');
    sur_ok('Root owner route and permission seams are actually wired',str_contains($adminRouter,"if(\$view==='subdomain-requests')provider_subdomain_requests_admin_render(\$error);")&&str_contains($adminActions,"if(str_starts_with(\$action,'provider_subdomain_request_'))provider_subdomain_requests_admin_action(\$action);")&&str_contains($staff,"if(\$view==='subdomain-requests')return 'staff.manage';")&&str_contains($staff,"if(str_starts_with(\$action,'provider_subdomain_request_'))return 'staff.manage';"));
    $identified=$owner;$identified['items'][0]['client']=['id'=>42,'name'=>'Private client <img src=x onerror=alert(1)> & Sons'];
    $html=sur_html(fn()=>provider_subdomain_requests_render($identified,true));
    sur_ok('Owner sees escaped requesting client name and exact client number',str_contains($html,'data-subdomain-request-client>Requested by <strong>Private client &lt;img src=x onerror=alert(1)&gt; &amp; Sons</strong> · Client #42')&&!str_contains($html,'<img src=x')&&!str_contains($html,'client_id='));
    $html=sur_html(fn()=>provider_subdomain_requests_render($identified,false));
    sur_ok('Client renderer never exposes owner-only requesting-client label',!str_contains($html,'data-subdomain-request-client')&&!str_contains($html,'Private client')&&!str_contains($html,'Client #42'));
    sur_reset();$_POST=[];$coldSettings=true;$html=sur_render(fn()=>provider_subdomain_requests_admin_render(''));
    sur_ok('Cold provider settings show safe owner shell and connection link without intake form',str_contains($html,'An owner must prepare provider settings first.')&&str_contains($html,'href="/subfolder/admin/index.php?view=provider-settings"')&&str_contains($html,'Review 20i connection')&&!str_contains($html,'data-subdomain-request-prepare')&&$calls===[['owner',false],['actor',null]]);
    foreach(['identity','contact_id','contact_auth','support_active','support_query'] as $kind){
        sur_reset();$_POST=[];$denyOwner=$kind==='identity';if(str_starts_with($kind,'contact_'))$_SESSION[$kind]=null;if($kind==='support_active')$support=true;if($kind==='support_query')$_GET['support']='';
        $denied=false;try{provider_subdomain_requests_admin_render('');}catch(SurDenied $e){$denied=$e->status===403;}
        sur_ok('Owner render '.$kind.' is403 before screen or form',$denied&&!in_array('actor',sur_kinds(),true)&&!in_array('screen',sur_kinds(),true));
    }
    foreach(['identity','contact_id','contact_auth','support_active','support_query'] as $kind){
        sur_reset();$_POST=[];$denyActor=$kind==='identity';if(str_starts_with($kind,'contact_'))$_SESSION[$kind]=null;if($kind==='support_active')$support=true;if($kind==='support_query')$_GET['support']='';
        $denied=false;try{provider_subdomain_requests_client_render(['id'=>42],'');}catch(SurDenied $e){$denied=$e->status===403;}
        sur_ok('Client render '.$kind.' is403 before screen or form',$denied&&!in_array('screen',sur_kinds(),true));
    }
    $report=['passed'=>true,'count'=>count($checks),'checks'=>$checks,'scope'=>'Actual PHP render/controller and early router source with modeled native/session boundaries; no SQL, provider, installed HTTP or delivery acceptance.'];
}catch(Throwable $e){while(ob_get_level()>0)ob_end_clean();$report=['passed'=>false,'count'=>count($checks),'checks'=>$checks,'error'=>$e->getMessage()];}
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(isset($argv[1])){if(!is_dir(dirname($argv[1])))mkdir(dirname($argv[1]),0777,true);file_put_contents($argv[1],$json);}
echo $json;exit($report['passed']===true?0:1);
