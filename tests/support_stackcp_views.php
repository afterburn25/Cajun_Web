<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/includes/layout/admin.php';
require_once CWP_CODE_ROOT.'/app/providers/support_stackcp_views.php';
use CajunWebPro\Foundation\Access\{AccessContext,PermissionPolicy};
$_SESSION=['csrf'=>str_repeat('a',64)];$_GET=[];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';$checks=[];
$GLOBALS['cwp_config']=['site_url'=>'https://portal.example.test'];
$supportId=str_repeat('1',32);$GLOBALS['cwp_support']=['session'=>['id'=>$supportId]];
function ssv_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function ssv_html(callable $render):string{ob_start();try{$render();return ob_get_contents();}finally{ob_end_clean();}}
function ssv_inputs(string $html):array{preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/',$html,$matches,PREG_SET_ORDER);$out=[];foreach($matches as $row)$out[$row[1]][]=html_entity_decode($row[2],ENT_QUOTES|ENT_HTML5,'UTF-8');return $out;}
set_error_handler(static function(int $level,string $message):never{throw new ErrorException($message,0,$level);});
$item=['profile_id'=>str_repeat('b',32),'package_id'=>2501,'client_name'=>'Client <one>','staff_id'=>2,'staff_name'=>'Staff <two>','package_name'=>'Hosting <one>','stack_identity'=>'stack-user:12','enabled'=>false,'can_enable'=>true,'stale'=>false,'permission_id'=>str_repeat('c',32),'review_proof'=>str_repeat('d',64),'link_id'=>str_repeat('5',32)];
$review=['available'=>true,'admission_id'=>str_repeat('e',32),'expires_epoch'=>1890000300,'review_proof'=>str_repeat('f',64),'previous_unknown'=>false];
try{
    $html=ssv_html(fn()=>provider_support_stackcp_permission_render($item));$inputs=ssv_inputs($html);
    ssv_ok('Owner permission escapes actual staff client and package names',str_contains($html,'Staff &lt;two&gt;')&&str_contains($html,'Client &lt;one&gt;')&&str_contains($html,'Hosting &lt;one&gt;')&&!str_contains($html,'<two>'));
    ssv_ok('Owner delegates one exact staff package and review',$inputs['action']===['provider_support_stackcp_permission']&&$inputs['profile_id']===[$item['profile_id']]&&$inputs['package_id']===['2501']&&$inputs['staff_id']===['2']&&$inputs['enabled']===['1']&&$inputs['review_proof']===[$item['review_proof']]);
    ssv_ok('Owner form uses current CSRF and empty password',$inputs['csrf']===[str_repeat('a',64)]&&$inputs['current_password']===['']&&str_contains($html,'autocomplete="current-password"'));
    ssv_ok('Owner whole user delegation requires unchecked explicit consent',str_contains($html,'named staff member to open the whole StackCP account')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html));
    $enabled=$item;$enabled['enabled']=true;$enabled['stale']=true;$enabled['can_enable']=false;
    $html=ssv_html(fn()=>provider_support_stackcp_permission_render($enabled));
    ssv_ok('Stale permission remains revocable without promising remote signout',ssv_inputs($html)['enabled']===['0']&&str_contains($html,'Disable hosting support')&&str_contains($html,'Existing provider sessions will stay signed in.'));
    $unavailable=$item;$unavailable['can_enable']=false;$html=ssv_html(fn()=>provider_support_stackcp_permission_render($unavailable));
    ssv_ok('Unavailable staff delegation cannot submit',!str_contains($html,'<form')&&str_contains($html,'support permission'));
    $ownerPost=['action'=>'provider_support_stackcp_permission','profile_id'=>$item['profile_id'],'package_id'=>'2501','staff_id'=>'2','review_proof'=>str_repeat('2',64),'enabled'=>'1','confirmed'=>'1','current_password'=>'SYNTHETIC_PRIVATE_PASSWORD'];
    $_POST=$ownerPost;$html=ssv_html(fn()=>provider_support_stackcp_permission_render($item));
    ssv_ok('Rejected owner POST retains original proof',ssv_inputs($html)['review_proof']===[$ownerPost['review_proof']]);
    ssv_ok('Rejected owner POST clears password and consent',!str_contains($html,'SYNTHETIC_PRIVATE_PASSWORD')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html));
    foreach(['profile_id'=>str_repeat('3',32),'package_id'=>'2502','staff_id'=>'3','enabled'=>'0'] as $key=>$value){$_POST=$ownerPost;$_POST[$key]=$value;$html=ssv_html(fn()=>provider_support_stackcp_permission_render($item));ssv_ok('Changed owner selector cannot inherit new proof '.$key,ssv_inputs($html)['review_proof']===['']&&str_contains($html,' disabled'));}
    $_POST=[];$html=ssv_html(fn()=>provider_support_stackcp_launch_render($item,$review,['items'=>[]],$supportId));$inputs=ssv_inputs($html);
    ssv_ok('Support launch has exact support CSRF and action',$inputs['action']===['support_stackcp_open']&&$inputs['csrf']===[support_csrf_token($supportId)]);
    ssv_ok('Support form binds exact session permission admission expiry proof',$inputs['support_id']===[$supportId]&&$inputs['permission_id']===[$item['permission_id']]&&$inputs['admission_id']===[$review['admission_id']]&&$inputs['expires_epoch']===['1890000300']&&$inputs['review_proof']===[$review['review_proof']]);
    ssv_ok('Support form uses protected new tab and exact route',str_contains($html,'target="_blank" rel="noopener noreferrer"')&&str_contains($html,'support='.$supportId.'&amp;support_hosting_panel=1'));
    ssv_ok('Support launch takes identity only from authenticated context',!isset($inputs['staff_id'])&&!isset($inputs['client_id'])&&!isset($inputs['actor_id'])&&!str_contains($html,'type="password"'));
    ssv_ok('Whole user scope and local expiry limitations visible',str_contains($html,'whole account as staff support')&&str_contains($html,'does not sign out an existing StackCP session')&&str_contains($html,'single-use')&&str_contains($html,'when this support session ends'));
    ssv_ok('Support launch never renders provider URL or token',!str_contains($html,'PHPSESSID')&&!str_contains($html,'access_token')&&!str_contains($html,'www.stackcp.com'));
    $html=ssv_html(fn()=>provider_support_stackcp_launch_render($item,$review,['items'=>[]],$supportId,2,$item['link_id']));
    ssv_ok('Support service route preserves exact session page and link',str_contains($html,'support='.$supportId.'&amp;support_hosting_panel=1&amp;support_panel_page=2&amp;service_link='.$item['link_id']));
    foreach(['<script>',str_repeat('6',32)] as $bad){$denied=false;try{ssv_html(fn()=>provider_support_stackcp_launch_render($item,$review,['items'=>[]],$supportId,2,$bad));}catch(InvalidArgumentException){$denied=true;}ssv_ok('Malformed or foreign service route refuses '.$bad,$denied);}
    foreach([0,501] as $page){$denied=false;try{ssv_html(fn()=>provider_support_stackcp_launch_render($item,$review,['items'=>[]],$supportId,$page));}catch(InvalidArgumentException){$denied=true;}ssv_ok('Out of range support page refuses '.$page,$denied);}
    $unknown=$review;$unknown['previous_unknown']=true;$html=ssv_html(fn()=>provider_support_stackcp_launch_render($item,$unknown,['items'=>[]],$supportId));
    ssv_ok('Unknown prior outcome requires new unchecked explicit consent',str_contains($html,'role="alert"')&&str_contains($html,'A provider session may already exist')&&str_contains($html,'even though an earlier provider session may exist')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html));
    $post=['action'=>'support_stackcp_open','support_id'=>$supportId,'permission_id'=>$item['permission_id'],'admission_id'=>str_repeat('2',32),'expires_epoch'=>'1880000300','review_proof'=>str_repeat('3',64),'confirmed'=>'1'];
    $_POST=$post;$_SERVER['REQUEST_METHOD']='POST';$html=ssv_html(fn()=>provider_support_stackcp_launch_render($item,$review,['items'=>[]],$supportId));$inputs=ssv_inputs($html);
    ssv_ok('Rejected support submission retains original admission and proof',$inputs['admission_id']===[$post['admission_id']]&&$inputs['expires_epoch']===[$post['expires_epoch']]&&$inputs['review_proof']===[$post['review_proof']]);
    $retry=provider_support_stackcp_retry_review($item,$supportId);$html=ssv_html(fn()=>provider_support_stackcp_launch_render($item,$retry,['items'=>[]],$supportId));
    ssv_ok('Retry review reconstructs only original signed fields',$retry['available']&&$retry['retry']&&$retry['admission_id']===$post['admission_id']&&$retry['review_proof']===$post['review_proof']&&$retry['support_id']===$supportId);
    ssv_ok('Retry clears consent and explains original attempt',str_contains($html,'original whole-account support attempt')&&str_contains($html,'keeps the original approval')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html));
    foreach(['support_id','permission_id','admission_id','expires_epoch','review_proof'] as $key)foreach([['bad'],'',str_repeat('x',100),"bad\0value"] as $index=>$bad){$_POST=$post;$_POST[$key]=$bad;$html=ssv_html(fn()=>provider_support_stackcp_launch_render($item,$review,['items'=>[]],$supportId));$inputs=ssv_inputs($html);ssv_ok('Malformed support form disables without renewal '.$key.' '.$index,$inputs[$key]===['']&&$inputs['review_proof']===['']&&str_contains($html,' disabled'));ssv_ok('Malformed retry unavailable '.$key.' '.$index,!provider_support_stackcp_retry_review($item,$supportId)['available']);}
    foreach(['support_id','permission_id'] as $key){$_POST=$post;$_POST[$key]=str_repeat('4',32);ssv_ok('Cross identity retry unavailable '.$key,!provider_support_stackcp_retry_review($item,$supportId)['available']);}
    $_POST=[];$_SERVER['REQUEST_METHOD']='GET';$html=ssv_html(fn()=>provider_support_stackcp_launch_render($item,['available'=>false],['items'=>[]],$supportId));
    ssv_ok('Unavailable support grant has no form',!str_contains($html,'<form')&&!str_contains($html,'support_stackcp_open'));
    foreach(['handoff_attempted','unknown','not_issued','interrupted','bad<script>'] as $state){$html=ssv_html(fn()=>provider_support_stackcp_launch_render($item,['available'=>false],['items'=>[['created_epoch'=>1760000000,'state'=>$state,'reason'=>'PRIVATE_PROVIDER_REPLY']]],$supportId));ssv_ok('History shows finite safe state '.$state,str_contains($html,esc(provider_stackcp_launch_state_label($state)))&&!str_contains($html,'PRIVATE_PROVIDER_REPLY')&&!str_contains($html,'<script>'));}
    $policy=new PermissionPolicy();$version=str_repeat('7',32);
    foreach([['administrator',[],true,false],['administrator',['support.login'],true,false],['administrator',['support.login','support.stackcp'],true,true],['owner',[],true,true],['administrator',['support.stackcp'],false,false]] as $index=>[$role,$permissions,$active,$expected]){ssv_ok('Separate actual foundation staff permission case '.$index,$policy->allows(new AccessContext('2','staff',$role,$active,$version,$version,$permissions),'support.stackcp')===$expected);}
    ssv_ok('New permission is exposed in administrator account editor',isset(staff_permissions()['support.stackcp']));
    ssv_ok('Support owner policy actions require owner management permission',staff_action_permission('provider_support_stackcp_prepare')==='staff.manage'&&staff_action_permission('provider_support_stackcp_permission')==='staff.manage');
}catch(Throwable $error){$checks[]=['name'=>'Unexpected renderer exception','passed'=>false,'error'=>get_class($error).': '.$error->getMessage()];}
restore_error_handler();$passed=!in_array(false,array_column($checks,'passed'),true);
$out=['passed'=>$passed,'scope'=>'Actual owner/support renderers, rejected form retention, support CSRF generation and foundation permission decisions with synthetic projections. No database, authenticated HTTP, provider or installed acceptance.','checks'=>$checks];
$path=$argv[1]??__DIR__.'/../qa/support-stackcp-views-default.json';if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
file_put_contents($path,json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);echo json_encode(['passed'=>$passed,'checks'=>count($checks)]).PHP_EOL;exit($passed?0:1);
