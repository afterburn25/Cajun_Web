<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once __DIR__.'/../site/includes/layout/admin.php';
require_once __DIR__.'/../site/app/providers/subdomain_add_views.php';
$_SESSION=['csrf'=>str_repeat('a',64)];$checks=[];
function sdv_check(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function sdv_render(array $view,bool $history=false):string {ob_start();try {if($history)provider_subdomain_add_render_history($view);else provider_subdomain_add_render_review($view);return ob_get_contents();}finally {ob_end_clean();}}
function sdv_inputs(string $html):array {preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/',$html,$matches,PREG_SET_ORDER);$out=[];foreach($matches as $match)$out[$match[1]][]=html_entity_decode($match[2],ENT_QUOTES|ENT_HTML5,'UTF-8');return $out;}
set_error_handler(static function(int $level,string $message):never {throw new ErrorException($message,0,$level);});
$review=['ready'=>true,'reason'=>'','link_id'=>str_repeat('b',32),'profile_id'=>str_repeat('c',32),'package_id'=>2001,'package_name'=>'example.com','parent_domains'=>['example.com','docs.example.com'],'request_id'=>str_repeat('d',32),'review_proof'=>'NEW_SIGNED_REVIEW','expires_epoch'=>1760000300,'observed_epoch'=>1760000000];
$history=['id'=>str_repeat('e',32),'parent_domain'=>'example.com','subdomain'=>'shop.example.com','created_at'=>'2025-10-09 08:53:20','status'=>'unknown','can_verify'=>true,'last_check'=>null,'recovery_ledger_sha256'=>null];
try {
    $_POST=[];$html=sdv_render($review);$inputs=sdv_inputs($html);
    sdv_check('Actual add form has only its typed action',$inputs['action']===['provider_inventory_subdomain_add']);
    sdv_check('Actual form includes existing CSRF protection',$inputs['csrf']===[str_repeat('a',64)]);
    sdv_check('Add form binds exact request, assignment and signed review',$inputs['request_id']===[$review['request_id']]&&$inputs['link_id']===[$review['link_id']]&&$inputs['review_proof']===[$review['review_proof']]);
    sdv_check('Current owner password is blank and required',$inputs['current_password']===['']&&str_contains($html,'autocomplete="current-password"'));
    sdv_check('Explicit consent is required and unchecked',str_contains($html,'name="confirmed" value="1" required')&&!str_contains($html,' checked'));
    sdv_check('Only explicitly supplied attached parents are listed',substr_count($html,'<option ')===2&&str_contains($html,'value="docs.example.com"'));
    sdv_check('Full child hostname, one-label scope and expiry are explained',str_contains($html,'Full subdomain name')&&str_contains($html,'immediately below')&&str_contains($html,'expires at'));
    sdv_check('Form does not provide deletion, root folder, URL or provider payload fields',!preg_match('/name="(?:rem|delete|path|url|payload)"/',$html));

    $_POST=['action'=>'provider_inventory_subdomain_add','link_id'=>$review['link_id'],'request_id'=>str_repeat('f',32),'review_proof'=>'ORIGINAL_SIGNED_REVIEW','parent_domain'=>'docs.example.com','subdomain'=>'api.docs.example.com','current_password'=>'PRIVATE_PASSWORD','confirmed'=>'1'];
    $html=sdv_render($review);$inputs=sdv_inputs($html);
    sdv_check('Failed submission retains original nonce and review',$inputs['request_id']===[str_repeat('f',32)]&&$inputs['review_proof']===['ORIGINAL_SIGNED_REVIEW']&&!str_contains($html,'NEW_SIGNED_REVIEW'));
    sdv_check('Failed submission retains child and selected parent',$inputs['subdomain']===['api.docs.example.com']&&str_contains($html,'value="docs.example.com" selected'));
    sdv_check('Failed submission never restores password or consent',!str_contains($html,'PRIVATE_PASSWORD')&&!str_contains($html,' checked'));
    sdv_check('Failed submission explains review renewal',str_contains($html,'original request details are retained'));
    foreach (['request_id'=>['bad',['array'],str_repeat('x',40)],'review_proof'=>[['array'],str_repeat('x',4097)]] as $field=>$invalids) {
        $saved=$_POST[$field];
        foreach($invalids as $index=>$value){$_POST[$field]=$value;$inputs=sdv_inputs(sdv_render($review));sdv_check('Malformed captured '.$field.' '.$index.' cannot receive a fresh review',$inputs[$field]===['']);}
        $_POST[$field]=$saved;
    }
    $_POST['parent_domain']='old.example.com';$html=sdv_render($review);
    sdv_check('Former parent is retained for correction without changing its identity',str_contains($html,'value="old.example.com" selected')&&str_contains($html,'review required'));
    $_POST['subdomain']='"><script>secret()</script>';$html=sdv_render($review);
    sdv_check('Submitted hostname is escaped',!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'));
    $_POST=[];$unavailable=$review;$unavailable['ready']=false;$unavailable['reason']='Refresh current website folders.';
    $html=sdv_render($unavailable);sdv_check('Unavailable review has no add form and links to prerequisites',!str_contains($html,'<form')&&str_contains($html,'href="#document-roots"'));
    $_POST=['action'=>'provider_inventory_subdomain_add','link_id'=>$review['link_id'],'parent_domain'=>'example.com','subdomain'=>'shop.example.com','current_password'=>'PRIVATE_PASSWORD'];
    $html=sdv_render($unavailable);sdv_check('Changed context retains entered names without an approval form or password',str_contains($html,'value="shop.example.com" readonly')&&str_contains($html,'value="example.com" readonly')&&!str_contains($html,'<form')&&!str_contains($html,'PRIVATE_PASSWORD'));$_POST=[];

    foreach (['unknown','pending','interrupted'] as $status) {
        $item=$history;$item['status']=$status;$html=sdv_render($item,true);$inputs=sdv_inputs($html);
        sdv_check($status.' history offers only a separate read',$inputs['action']===['provider_inventory_subdomain_verify']&&!str_contains($html,'provider_inventory_subdomain_add'));
        sdv_check($status.' history does not infer unsent from absence',str_contains($html,'does not establish that the original request was unsent'));
    }
    $item=$history;$item['can_verify']=false;$html=sdv_render($item,true);
    sdv_check('Changed authority retains history without a read form',!str_contains($html,'<form')&&str_contains($html,'shop.example.com'));
    $item['status']='observed';$html=sdv_render($item,true);
    sdv_check('Observed name does not claim working DNS or website',str_contains($html,'DNS, certificate and website availability have not been checked'));
    $item['status']='not_sent';$html=sdv_render($item,true);
    sdv_check('Pre-dispatch refusal is separately labeled',str_contains($html,'stopped before provider dispatch')&&!str_contains($html,'Subdomain requested;'));
    $item=$history;$item['last_check']=['disposition'=>'different','recorded_epoch'=>1760000000];$html=sdv_render($item,true);
    sdv_check('Absent follow-up remains reported absence with its date',str_contains($html,'Name not reported')&&str_contains($html,'2025-10-09 08:53:20 UTC'));
    $item['last_check']['disposition']='unavailable';sdv_check('Unavailable follow-up is distinct from absent name',str_contains(sdv_render($item,true),'No usable result'));
    $item=$history;$item['recovery_ledger_sha256']=str_repeat('7',64);$html=sdv_render($item,true);$inputs=sdv_inputs($html);
    sdv_check('Recovery uses exact saved attempt and separate action',$inputs['action']===['provider_inventory_subdomain_recover']&&$inputs['ledger_sha256']===[str_repeat('7',64)]);
    sdv_check('Recovery needs fresh consent and blank password',str_contains($html,'name="confirmed" value="1" required')&&$inputs['current_password']===['']&&!str_contains($html,' checked'));
    sdv_check('Recovery clearly does not resend',str_contains($html,'without sending another request'));
    foreach (['subdomain','parent_domain','created_at'] as $field) {$item=$history;$item[$field]='<script>alert(1)</script>';sdv_check('History escapes '.$field,!str_contains(sdv_render($item,true),'<script>'));}
    sdv_check('Unrecognized status never appears as confirmed',provider_subdomain_add_label('unexpected')==='Subdomain requested; result is not yet confirmed');
} catch(Throwable $error) {$checks[]=['name'=>'Unexpected renderer failure','passed'=>false,'error'=>get_class($error).': '.$error->getMessage()];}
$passed=!array_filter($checks,fn($check)=>!$check['passed']);
$report=['passed'=>$passed,'scope'=>'Actual PHP form/history renderers with synthetic projections; no SQL, authenticated application, provider or browser execution.','checks'=>$checks];
$path=$argv[1]??__DIR__.'/../qa/subdomain-add-views-results.json';if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode(['passed'=>$passed,'checks'=>count($checks)]).PHP_EOL;exit($passed?0:1);
