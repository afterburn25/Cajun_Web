<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/includes/layout/admin.php';
require_once CWP_CODE_ROOT.'/app/providers/client_stackcp_views.php';
$_SESSION=['csrf'=>str_repeat('a',64)];$_GET=[];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';$checks=[];
$GLOBALS['cwp_config']=['site_url'=>'https://portal.example.test'];
function csv_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function csv_html(callable $render):string {ob_start();try {$render();return ob_get_contents();}finally {ob_end_clean();}}
function csv_inputs(string $html):array {preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/',$html,$matches,PREG_SET_ORDER);$out=[];foreach ($matches as $row) $out[$row[1]][]=html_entity_decode($row[2],ENT_QUOTES|ENT_HTML5,'UTF-8');return $out;}
set_error_handler(static function(int $level,string $message):never {throw new ErrorException($message,0,$level);});
$item=['profile_id'=>str_repeat('b',32),'package_id'=>2501,'client_name'=>'Client <one>','package_name'=>'Hosting <one>','stack_identity'=>'stack-user:12','enabled'=>false,'can_enable'=>true,'stale'=>false,'permission_id'=>str_repeat('c',32),'review_proof'=>str_repeat('d',64)];
$review=['available'=>true,'admission_id'=>str_repeat('e',32),'expires_epoch'=>1890000300,'review_proof'=>str_repeat('f',64),'previous_unknown'=>false];
try {
    $html=csv_html(fn()=>provider_client_stackcp_permission_render($item));$inputs=csv_inputs($html);
    csv_ok('Owner permission names and package titles are escaped',str_contains($html,'Client &lt;one&gt;')&&str_contains($html,'Hosting &lt;one&gt;')&&!str_contains($html,'Client <one>'));
    csv_ok('Owner enables one exact package and current review',$inputs['action']===['provider_inventory_client_stackcp_permission']&&$inputs['profile_id']===[$item['profile_id']]&&$inputs['package_id']===['2501']&&$inputs['enabled']===['1']&&$inputs['review_proof']===[$item['review_proof']]);
    csv_ok('Owner permission requires blank password and current CSRF',$inputs['current_password']===['']&&$inputs['csrf']===[str_repeat('a',64)]&&str_contains($html,'autocomplete="current-password"'));
    csv_ok('Whole account delegation requires unchecked explicit consent',str_contains($html,'primary client to open this whole StackCP account')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html));
    $enabled=$item;$enabled['enabled']=true;$html=csv_html(fn()=>provider_client_stackcp_permission_render($enabled));$inputs=csv_inputs($html);
    csv_ok('Disable records disabled state and no remote signout promise',$inputs['enabled']===['0']&&str_contains($html,'Existing provider sessions will stay signed in.'));
    $stale=$enabled;$stale['stale']=true;$stale['can_enable']=false;$html=csv_html(fn()=>provider_client_stackcp_permission_render($stale));
    csv_ok('Stale enabled policy still exposes disable form',str_contains($html,'needs a new owner review')&&str_contains($html,'Disable client access'));
    $unavailable=$item;$unavailable['can_enable']=false;$html=csv_html(fn()=>provider_client_stackcp_permission_render($unavailable));
    csv_ok('Missing current provider checks cannot enable',!str_contains($html,'<form')&&str_contains($html,'refresh the membership and grants'));
    $_POST=['action'=>'provider_inventory_client_stackcp_permission','profile_id'=>$item['profile_id'],'package_id'=>'2501','review_proof'=>str_repeat('1',64),'enabled'=>'1','confirmed'=>'1','current_password'=>'SYNTHETIC_PRIVATE_PASSWORD'];
    $html=csv_html(fn()=>provider_client_stackcp_permission_render($item));$inputs=csv_inputs($html);
    csv_ok('Failed owner POST retains original review',$inputs['review_proof']===[str_repeat('1',64)]);
    csv_ok('Failed owner POST clears password and consent',!str_contains($html,'SYNTHETIC_PRIVATE_PASSWORD')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html)&&$inputs['current_password']===['']);
    $_POST=[];$html=csv_html(fn()=>provider_client_stackcp_launch_render($item,$review,['items'=>[]]));$inputs=csv_inputs($html);
    csv_ok('Client launch includes CSRF and only explicit action',$inputs['action']===['client_stackcp_open']&&$inputs['csrf']===[str_repeat('a',64)]);
    csv_ok('Client launch binds permission admission expiry and proof',$inputs['permission_id']===[$item['permission_id']]&&$inputs['admission_id']===[$review['admission_id']]&&$inputs['expires_epoch']===['1890000300']&&$inputs['review_proof']===[$review['review_proof']]);
    csv_ok('Client form opens protected new tab',str_contains($html,'target="_blank" rel="noopener noreferrer"')&&str_contains($html,'method="post"'));
    $linked=$item;$linked['link_id']=str_repeat('5',32);
    $pagedHtml=csv_html(fn()=>provider_client_stackcp_launch_render($linked,$review,['items'=>[]],2,$linked['link_id']));
    csv_ok('Client form keeps validated page and exact service link in action',str_contains($pagedHtml,'hosting_panel=1&amp;panel_page=2&amp;service_link='.$linked['link_id']));
    foreach (['<script>',str_repeat('6',32)] as $invalidLink) {
        $denied=false;try {csv_html(fn()=>provider_client_stackcp_launch_render($linked,$review,['items'=>[]],2,$invalidLink));}catch(InvalidArgumentException){$denied=true;}
        csv_ok('Client form refuses foreign or malformed route context '.$invalidLink,$denied);
    }
    csv_ok('Client uses primary session without collecting another password',!str_contains($html,'type="password"')&&!isset($inputs['client_id'])&&!isset($inputs['actor_id']));
    csv_ok('Client whole account and fresh permission checks are visible',str_contains($html,'whole account')&&str_contains($html,'checked before creating a session'));
    csv_ok('Client approval expiry and one use are visible',str_contains($html,'five minutes')&&str_contains($html,'only once'));
    csv_ok('Client launch cannot echo a remote URL or token',!str_contains($html,'PHPSESSID')&&!str_contains($html,'access_token')&&!str_contains($html,'www.stackcp.com'));
    $unknown=$review;$unknown['previous_unknown']=true;$html=csv_html(fn()=>provider_client_stackcp_launch_render($item,$unknown,['items'=>[]]));
    csv_ok('Unknown prior result has prominent warning and new consent',str_contains($html,'role="alert"')&&str_contains($html,'A provider session may already exist')&&str_contains($html,'even though an earlier session may exist')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html));
    $original=['action'=>'client_stackcp_open','permission_id'=>$item['permission_id'],'admission_id'=>str_repeat('2',32),'expires_epoch'=>'1880000300','review_proof'=>str_repeat('3',64),'confirmed'=>'1'];
    $_POST=$original;$_SERVER['REQUEST_METHOD']='POST';$html=csv_html(fn()=>provider_client_stackcp_launch_render($item,$review,['items'=>[]]));$inputs=csv_inputs($html);
    csv_ok('Failed client POST retains its captured form',$inputs['admission_id']===[$original['admission_id']]&&$inputs['expires_epoch']===[$original['expires_epoch']]&&$inputs['review_proof']===[$original['review_proof']]);
    csv_ok('Failed client POST explains reload and clears consent',str_contains($html,'keeps its original approval')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html));
    $retry=provider_client_stackcp_retry_review($item);$retryHtml=csv_html(fn()=>provider_client_stackcp_launch_render($item,$retry,['items'=>[]]));
    csv_ok('POST retry review contains only original captured fields',$retry['available']&&$retry['retry']&&$retry['admission_id']===$original['admission_id']&&$retry['review_proof']===$original['review_proof']&&$retry['expires_epoch']===$original['expires_epoch']);
    csv_ok('Retry consent addresses original whole account attempt and possible previous session',str_contains($retryHtml,'original whole-account access attempt')&&str_contains($retryHtml,'an earlier session may already exist')&&!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$retryHtml));
    foreach (['permission_id','admission_id','expires_epoch','review_proof'] as $field) foreach ([['invalid'],'',str_repeat('x',100),"bad\0value"] as $index=>$bad) {
        $_POST=$original;$_POST[$field]=$bad;$html=csv_html(fn()=>provider_client_stackcp_launch_render($item,$review,['items'=>[]]));$inputs=csv_inputs($html);
        csv_ok('Malformed client '.$field.' disables without renewal '.$index,$inputs[$field]===['']&&$inputs['review_proof']===['']&&str_contains($html,' disabled'));
        csv_ok('Malformed retry cannot reconstruct '.$field.' '.$index,provider_client_stackcp_retry_review($item)['available']===false);
    }
    $_POST=$original;$_POST['permission_id']=str_repeat('4',32);$html=csv_html(fn()=>provider_client_stackcp_launch_render($item,$review,['items'=>[]]));
    csv_ok('Sibling permission cannot acquire a fresh proof',csv_inputs($html)['review_proof']===['']&&str_contains($html,' disabled'));
    $_POST=[];$_SERVER['REQUEST_METHOD']='GET';$html=csv_html(fn()=>provider_client_stackcp_launch_render($item,['available'=>false],['items'=>[]]));
    csv_ok('Unavailable client grant has no launch form',!str_contains($html,'<form')&&!str_contains($html,'client_stackcp_open'));
    foreach (['handoff_attempted','unknown','not_issued','interrupted','bad<script>'] as $state) {
        $html=csv_html(fn()=>provider_client_stackcp_launch_render($item,['available'=>false],['items'=>[['created_epoch'=>1760000000,'state'=>$state,'reason'=>'PRIVATE_PROVIDER_REPLY']]]));
        csv_ok('History finite escaped state '.$state,str_contains($html,esc(provider_stackcp_launch_state_label($state)))&&!str_contains($html,'PRIVATE_PROVIDER_REPLY')&&!str_contains($html,'<script>'));
    }
    csv_ok('Plain client session is not rejected',!provider_client_stackcp_session_blocked());
    foreach (['uid','contact_id','contact_auth'] as $key) foreach ([null,'',0,'invalid'] as $index=>$raw) {
        $_SESSION[$key]=$raw;csv_ok('Raw identity marker blocks before normalization '.$key.' '.$index,provider_client_stackcp_session_blocked());unset($_SESSION[$key]);
    }
    $_GET['support']='';csv_ok('Even empty raw support marker blocks',provider_client_stackcp_session_blocked());$_GET=[];
    $GLOBALS['cwp_support']=['client'=>['id'=>1]];csv_ok('Resolved support context blocks',provider_client_stackcp_session_blocked());unset($GLOBALS['cwp_support']);
} catch (Throwable $error) {$checks[]=['name'=>'Unexpected renderer exception','passed'=>false,'error'=>get_class($error).': '.$error->getMessage()];}
$passed=!array_filter($checks,static fn(array $check):bool=>!$check['passed']);
$out=['passed'=>$passed,'scope'=>'Actual owner/client pure renderers, form retention and raw identity guards with synthetic projections. No database, authenticated HTTP route, provider or browser execution.','checks'=>$checks];
$path=$argv[1]??__DIR__.'/../qa/client-stackcp-views-default.json';if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
file_put_contents($path,json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);echo json_encode(['passed'=>$passed,'checks'=>count($checks)]).PHP_EOL;exit($passed?0:1);
