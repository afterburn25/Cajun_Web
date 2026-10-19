<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/includes/layout/admin.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_launch_views.php';
$_SESSION=['csrf'=>str_repeat('a',64)];$checks=[];
function slv_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function slv_html(array $view,array $history=[]):string {ob_start();try {provider_stackcp_launch_render($view,['items'=>$history]);return ob_get_contents();}finally {ob_end_clean();}}
function slv_inputs(string $html):array {preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/',$html,$matches,PREG_SET_ORDER);$out=[];foreach ($matches as $row) $out[$row[1]][]=html_entity_decode($row[2],ENT_QUOTES|ENT_HTML5,'UTF-8');return $out;}
set_error_handler(static function(int $level,string $message):never {throw new ErrorException($message,0,$level);});
$review=['available'=>true,'reason'=>'','admission_id'=>str_repeat('b',32),'profile_id'=>str_repeat('c',32),'package_id'=>2501,'expires_epoch'=>1890000300,'review_proof'=>str_repeat('d',64),'stack_identity'=>'stack-user:123','previous_unknown'=>false,'previous_state'=>'none'];
try {
    $_POST=[];$html=slv_html($review);$inputs=slv_inputs($html);
    slv_ok('One explicit launch action with actual CSRF',$inputs['action']===['provider_inventory_stackcp_launch_open']&&$inputs['csrf']===[str_repeat('a',64)]);
    slv_ok('Original review fields rendered exactly',$inputs['admission_id']===[$review['admission_id']]&&$inputs['review_proof']===[$review['review_proof']]&&$inputs['profile_id']===[$review['profile_id']]&&$inputs['package_id']===['2501']&&$inputs['expires_epoch']===['1890000300']);
    slv_ok('Explicit blank current-password confirmation',$inputs['current_password']===['']&&str_contains($html,'autocomplete="current-password"'));
    slv_ok('New-tab form has opener and referrer protection',str_contains($html,'method="post" target="_blank" rel="noopener noreferrer"'));
    slv_ok('Confirmation starts unchecked',!preg_match('/<input[^>]*name="confirmed"[^>]*\bchecked\b/',$html));
    slv_ok('Whole account and local-revocation limitations visible',str_contains($html,'whole account')&&str_contains($html,'does not sign out an existing provider session'));
    slv_ok('Short-lived single-use approval explained',str_contains($html,'five minutes')&&str_contains($html,'only once'));
    slv_ok('No secret-bearing destination in form',!str_contains($html,'PHPSESSID')&&!str_contains($html,'access_token')&&!str_contains($html,'www.stackcp.com'));
    slv_ok('Committed handoff state does not claim browser delivery',provider_stackcp_launch_state_label('handoff_attempted')==='Handoff prepared; browser delivery and remote sign-in are not confirmed');
    $x=$review;$x['stack_identity']='</strong><script>INJECTED</script>';$html=slv_html($x);
    slv_ok('Projected identity is escaped',!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'));
    $x=$review;$x['previous_unknown']=true;$x['previous_state']='unknown';$html=slv_html($x);
    slv_ok('Previous unknown outcome is prominent',str_contains($html,'role="alert"')&&str_contains($html,'A provider session may already exist'));
    slv_ok('Unknown outcome requires explicit new-session consent',str_contains($html,'Create a NEW StackCP session even though the previous session may exist.')&&!str_contains($html,'checked'));
    foreach (['unprepared','refresh_required','capacity'] as $reason) {
        $x=$review;$x['available']=false;$x['reason']=$reason;$html=slv_html($x);
        slv_ok($reason.' never renders a launch form',!str_contains($html,'<form')&&!str_contains($html,'provider_inventory_stackcp_launch_open'));
        if ($reason==='capacity') slv_ok('Capacity gives accurate next action',str_contains($html,'reached its capacity')&&!str_contains($html,'Refresh the package'));
    }
    $original=['action'=>'provider_inventory_stackcp_launch_open','admission_id'=>str_repeat('e',32),'profile_id'=>$review['profile_id'],'package_id'=>'2501','expires_epoch'=>'1880000300','review_proof'=>str_repeat('f',64),'confirmed'=>'1','current_password'=>'SYNTHETIC_PRIVATE_PASSWORD'];
    $_POST=$original;$html=slv_html($review);$inputs=slv_inputs($html);
    slv_ok('Rejected POST retains old nonce expiry and proof',$inputs['admission_id']===[$original['admission_id']]&&$inputs['expires_epoch']===[$original['expires_epoch']]&&$inputs['review_proof']===[$original['review_proof']]);
    slv_ok('Rejected POST clears password and consent',!str_contains($html,'SYNTHETIC_PRIVATE_PASSWORD')&&$inputs['current_password']===['']&&!str_contains($html,'checked'));
    slv_ok('Rejected POST explains reload requirement',str_contains($html,'keeps the original approval'));
    foreach (['admission_id','profile_id','package_id','expires_epoch','review_proof'] as $field) {
        foreach ([['invalid'],'',str_repeat('x',100),"bad\0value"] as $index=>$bad) {
            $_POST=$original;$_POST[$field]=$bad;$inputs=slv_inputs(slv_html($review));
            slv_ok('Malformed '.$field.' never mints replacement '.$index,$inputs[$field]===['']&&$inputs['review_proof']!==[$review['review_proof']]);
        }
    }
    foreach (['profile_id'=>str_repeat('1',32),'package_id'=>'2502'] as $field=>$value) {
        $_POST=$original;$_POST[$field]=$value;$html=slv_html($review);$inputs=slv_inputs($html);
        slv_ok('Foreign '.$field.' cannot submit a fresh approval',$inputs['review_proof']===['']&&str_contains($html,' disabled'));
    }
    $_POST=[];
    foreach (['handoff_attempted','unknown','not_issued','interrupted','unrecognized'] as $state) {
        $html=slv_html($review,[['state'=>$state,'created_epoch'=>1760000000,'reason'=>'SYNTHETIC_UNSAFE_DETAIL']]);
        slv_ok('History exposes finite '.$state.' label only',str_contains($html,esc(provider_stackcp_launch_state_label($state)))&&!str_contains($html,'SYNTHETIC_UNSAFE_DETAIL')&&str_contains($html,'2025-10-09 08:53:20 UTC'));
    }
} catch (Throwable $error) {$checks[]=['name'=>'Unexpected renderer exception','passed'=>false,'error'=>get_class($error).': '.$error->getMessage()];}
$passed=!array_filter($checks,static fn(array $check):bool=>!$check['passed']);
$out=['passed'=>$passed,'scope'=>'Actual pure launch renderer and retained form parsing with synthetic projections. No SQL, authenticated route, CSS, provider or browser execution.','checks'=>$checks];
$path=$argv[1]??__DIR__.'/../qa/stackcp-launch-views-default.json';if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
file_put_contents($path,json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode(['passed'=>$passed,'checks'=>count($checks)]).PHP_EOL;exit($passed?0:1);
