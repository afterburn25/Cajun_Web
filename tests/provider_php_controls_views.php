<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/security/input.php';
require __DIR__.'/../site/includes/security/csrf.php';
require __DIR__.'/../site/app/providers/php_controls_views.php';
set_error_handler(static function(int $level,string $message,string $file,int $line):never {throw new ErrorException($message,0,$level,$file,$line);});
$_SESSION=['csrf'=>str_repeat('c',64)];$checks=[];
function pcv_ok(string $name,bool $value):void { $GLOBALS['checks'][]=['name'=>$name,'passed'=>$value]; }
function pcv_id(int $n):string {return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function pcv_hash(int $n):string {return str_pad(dechex($n),64,'0',STR_PAD_LEFT);}
function pcv_render(array $screen,bool $owner=true,?array $review=null,array $retry=[],?string $url=null):string {
    ob_start();try {provider_php_controls_render($screen,$owner,$url??('/admin/index.php?view=provider-php-controls&service_link='.$screen['link_id']),$review,$retry);return ob_get_contents();} finally {ob_end_clean();}
}
function pcv_attrs(string $tag):array {
    preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_-]*)="([^"]*)"/',$tag,$matches,PREG_SET_ORDER);$out=[];
    foreach ($matches as $m) $out[$m[1]]=html_entity_decode($m[2],ENT_QUOTES|ENT_HTML5,'UTF-8');return $out;
}
function pcv_inputs(string $html):array {
    preg_match_all('/<input\b[^>]*>/',$html,$matches);$out=[];
    foreach ($matches[0] as $tag) {$a=pcv_attrs($tag);if (isset($a['name'])) $out[$a['name']][]=$a['value']??null;}return $out;
}
function pcv_forms(string $html,string $action):array {
    preg_match_all('/<form\b[^>]*>.*?<\/form>/s',$html,$matches);return array_values(array_filter($matches[0],static fn($form)=>(pcv_inputs($form)['action']??[])===[$action]));
}
function pcv_refuse(string $name,callable $fn):void {
    try {$fn();pcv_ok($name,false);} catch (InvalidArgumentException) {pcv_ok($name,true);}
}
function pcv_screen():array {
    return ['link_id'=>pcv_id(1),'setup'=>'ready','access'=>true,'blocked'=>false,'can_select'=>true,
        'current'=>['id'=>pcv_id(2),'state'=>'complete','reason'=>'','finished_epoch'=>1792300000,'value'=>'php82','usable'=>true],
        'available'=>['id'=>pcv_id(3),'state'=>'complete','reason'=>'','finished_epoch'=>1792300040,'options'=>[
            ['value'=>'php82','label'=>'PHP 8.2','handler'=>'Original handler'],['value'=>'php84','label'=>'PHP 8.4','handler'=>'Alternate handler']],'usable'=>true],
        'delegation'=>['enabled'=>false,'current'=>false,'enable'=>['expected_permission_id'=>'','review_sha256'=>pcv_hash(4)],'disable'=>null],
        'history'=>[],'page'=>1,'has_more'=>false];
}
function pcv_review():array {
    return ['review_sha256'=>pcv_hash(5),'current_read_id'=>pcv_id(2),'options_read_id'=>pcv_id(3),
        'before_value'=>'php82','desired_value'=>'php84','label'=>'PHP 8.4','handler'=>'Alternate handler',
        'current_observed_epoch'=>1792300000,'options_observed_epoch'=>1792300040,'request_id'=>pcv_id(6)];
}
function pcv_history(int $id,string $phase,string $readback):array {
    return ['id'=>pcv_id($id),'link_id'=>pcv_id(1),'before_value'=>'php82','desired_value'=>'php84','approved_epoch'=>1792300100,
        'phase'=>$phase,'readback'=>$readback,'observed_value'=>in_array($readback,['observed','different'],true)?($readback==='observed'?'php84':'php83'):null,
        'readback_epoch'=>$readback==='pending'?null:1792300200,'recovered_from_ledger'=>false,'can_verify'=>$phase==='attempted' && $readback!=='observed','recovery_sha256'=>null];
}
try {
    $s=pcv_screen();$html=pcv_render($s);$inputs=pcv_inputs($html);
    pcv_ok('Ready owner has two separate observation forms',count(pcv_forms($html,'php_controls_read'))===2 && $inputs['kind']===['current','available']);
    pcv_ok('Selection only reviews and does not submit a change',count(pcv_forms($html,'php_controls_review'))===1 && pcv_forms($html,'php_controls_change')===[]);
    pcv_ok('Each owner form carries current website and real CSRF',count($inputs['action'])===count($inputs['csrf']) && array_unique($inputs['csrf'])===[str_repeat('c',64)] && array_unique($inputs['link_id'])===[$s['link_id']]);
    pcv_ok('Owner read and delegation passwords are blank',$inputs['current_password']===['','','']);
    pcv_ok('Initial review identity is a canonical opaque value',preg_match('/\A[a-f0-9]{32}\z/',$inputs['request_id'][0])===1);
    pcv_ok('Initial selection does not silently choose a version',str_contains($html,'<option value="" selected>Choose a version</option>'));
    $client=pcv_render($s,false);$ci=pcv_inputs($client);
    pcv_ok('Client read has no owner password field',!isset($ci['current_password']));
    pcv_ok('Client cannot receive delegation or prepare forms',pcv_forms($client,'php_controls_delegate')===[] && pcv_forms($client,'php_controls_prepare')===[] && !str_contains($client,'data-php-delegation'));
    foreach (['prerequisites','delegation','lifecycle'] as $setup) {
        $x=$s;$x['setup']=$setup;$x['current']=null;$x['available']=null;$x['delegation']=null;
        $h=pcv_render($x);pcv_ok($setup.' setup suppresses read review and change forms',pcv_forms($h,'php_controls_read')===[] && pcv_forms($h,'php_controls_review')===[] && pcv_forms($h,'php_controls_change')===[]);
        pcv_ok($setup.' owner setup action matches available prerequisites',count(pcv_forms($h,'php_controls_prepare'))===($setup==='prerequisites'?0:1));
        $h=pcv_render($x,false);pcv_ok($setup.' client receives studio help without preparation controls',str_contains($h,'Contact the studio') && !str_contains($h,'<form'));
    }
    $x=$s;$x['current']=null;$x['available']=null;$h=pcv_render($x);
    pcv_ok('Missing observations are explicit without invented choices',str_contains($h,'No current version check') && str_contains($h,'No available versions check') && !str_contains($h,'<select'));
    foreach (['pending','unavailable','unknown'] as $state) {
        $x=$s;foreach (['current','available'] as $kind) {$x[$kind]['state']=$state;$x[$kind]['usable']=false;$x[$kind]['finished_epoch']=$state==='pending'?null:1792300040;if ($kind==='current') $x[$kind]['value']=null;else $x[$kind]['options']=null;}
        $h=pcv_render($x);pcv_ok($state.' latest observation cannot offer a selection',!str_contains($h,'<select') && pcv_forms($h,'php_controls_change')===[]);
        pcv_ok($state.' observation has honest completion wording',str_contains($h,$state==='pending'?'latest check has not finished':'could not be checked'));
    }
    $x=$s;$x['available']['options']=[];$h=pcv_render($x);
    pcv_ok('Complete empty options are explicit and unselectable',str_contains($h,'returned no selectable versions') && !str_contains($h,'<select'));
    foreach (['current','available'] as $kind) {$x=$s;$x[$kind]['usable']=false;$h=pcv_render($x);pcv_ok($kind.' stale complete read stays visible but cannot admit choice',str_contains($h,'saved result cannot be used') && !str_contains($h,'<select'));}
    $x=$s;$x['access']=false;$h=pcv_render($x,false);
    pcv_ok('Lost client access suppresses every mutation form',!str_contains($h,'<form') && str_contains($h,'Contact the studio'));
    $x=$s;$x['blocked']=true;$h=pcv_render($x);
    pcv_ok('Unresolved resource suppresses change selection without hiding separate reads',!str_contains($h,'<select') && count(pcv_forms($h,'php_controls_read'))===2 && str_contains($h,'It will not be sent again'));
    $retained=['request_id'=>pcv_id(9),'desired'=>'php84','current_password'=>'PRIVATE_PASSWORD','binding'=>'PRIVATE_BINDING'];
    $h=pcv_render($s,true,null,$retained);$f=pcv_forms($h,'php_controls_review')[0];$in=pcv_inputs($f);
    pcv_ok('Safe selection retry retains exact request identity',$in['request_id']===[pcv_id(9)]);
    pcv_ok('Safe selection retry retains exact desired value',str_contains($f,'<option value="php84" selected>'));
    pcv_ok('Arbitrary retry and posted passwords never render',!str_contains($h,'PRIVATE_PASSWORD') && !str_contains($h,'PRIVATE_BINDING'));
    $x=$s;$x['available']['options']=[['value'=>'8','label'=>'Eight','handler'=>'one'],['value'=>'08','label'=>'Zero eight','handler'=>'two'],['value'=>'8 ','label'=>'Eight with suffix','handler'=>'three']];
    foreach (['8','08','8 '] as $value) {$h=pcv_render($x,true,null,['request_id'=>pcv_id(9),'desired'=>$value]);preg_match_all('/<option\b[^>]* selected>.*?<\/option>/s',$h,$m);pcv_ok('Exact option selection preserves distinct string '.json_encode($value),count($m[0])===1 && pcv_attrs($m[0][0])['value']===$value);}
    $h=pcv_render($s,true,null,['request_id'=>pcv_id(9),'desired'=>'PHP84']);
    pcv_ok('Unknown retry choice has no coercion or fallback',str_contains($h,'previous choice is no longer available') && str_contains($h,'<option value="" selected>') && pcv_inputs($h)['request_id']===[pcv_id(9)]);
    foreach (['',[],str_repeat('x',32)] as $bad) pcv_refuse('Malformed retained request identity refuses '.json_encode($bad),fn()=>pcv_render($s,true,null,['request_id'=>$bad]));
    $r=pcv_review();$h=pcv_render($s,true,$r);$f=pcv_forms($h,'php_controls_change')[0];$in=pcv_inputs($f);
    pcv_ok('Review page separates confirmation from choice',pcv_forms($h,'php_controls_review')===[] && count(pcv_forms($h,'php_controls_change'))===1);
    pcv_ok('Confirmation retains exact original review fields',$in['request_id']===[$r['request_id']] && $in['current_read_id']===[$r['current_read_id']] && $in['options_read_id']===[$r['options_read_id']] && $in['review_sha256']===[$r['review_sha256']] && $in['desired']===[$r['desired_value']]);
    pcv_ok('Confirmation requires unchecked deliberate consent and blank password',$in['confirmed']===['1'] && $in['current_password']===[''] && !str_contains($f,' checked'));
    pcv_ok('Review visibly shows version handler and both exact observation times',str_contains($h,'Previous value') && str_contains($h,'Alternate handler') && str_contains($h,gmdate('Y-m-d H:i:s',$r['current_observed_epoch']).' UTC') && str_contains($h,gmdate('Y-m-d H:i:s',$r['options_observed_epoch']).' UTC'));
    $h=pcv_render($s,false,$r,['current_password'=>'PRIVATE_PASSWORD']);$f=pcv_forms($h,'php_controls_change')[0];
    pcv_ok('Client confirmation requires its own blank current-password input',pcv_inputs($f)['current_password']===[''] && !str_contains($h,'PRIVATE_PASSWORD'));
    foreach (['current','available'] as $kind) {$x=$s;$x[$kind]['id']=pcv_id(42);$h=pcv_render($x,true,$r);pcv_ok('Stale '.$kind.' review remains visible without replacement confirmation',str_contains($h,'data-php-review') && str_contains($h,'Reload and review the version') && pcv_forms($h,'php_controls_change')===[]);}
    $x=$s;$x['blocked']=true;$h=pcv_render($x,true,$r);pcv_ok('Blocked resource cannot re-confirm an old review',pcv_forms($h,'php_controls_change')===[]);
    $payload='" ><script>PRIVATE_SCRIPT</script>&';$x=$s;$x['current']['value']=$payload;$x['available']['options']=[['value'=>$payload,'label'=>$payload,'handler'=>$payload]];
    $h=pcv_render($x,true,null,['desired'=>$payload,'request_id'=>pcv_id(9)]);preg_match('/<option\b[^>]* selected>/',$h,$m);
    pcv_ok('Option label handler and current value are escaped',!str_contains($h,'<script>') && str_contains($h,'&lt;script&gt;'));
    pcv_ok('Escaping retains exact raw option value for form submission',pcv_attrs($m[0])['value']===$payload);
    $r=pcv_review();foreach (['before_value','desired_value','label','handler'] as $key) $r[$key]=$payload;$r['private_context']='PRIVATE_CONTEXT';$h=pcv_render($s,true,$r);
    pcv_ok('Review display and hidden selected value are escaped without private extras',!str_contains($h,'<script>') && !str_contains($h,'PRIVATE_CONTEXT') && pcv_inputs(pcv_forms($h,'php_controls_change')[0])['desired']===[$payload]);
    $x=$s;$x['private_context']='PRIVATE_CONTEXT';$x['available']['private_response']='PRIVATE_RESPONSE';$x['available']['reason']='PRIVATE_ERROR';$x['available']['state']='unavailable';$x['available']['options']=null;$x['available']['usable']=false;$h=pcv_render($x);
    pcv_ok('Private projection extras and unknown error text cannot leak',!str_contains($h,'PRIVATE_CONTEXT') && !str_contains($h,'PRIVATE_RESPONSE') && !str_contains($h,'PRIVATE_ERROR'));
    $x=$s;$x['delegation']=['enabled'=>true,'current'=>false,'enable'=>null,'disable'=>['expected_permission_id'=>pcv_id(11),'review_sha256'=>pcv_hash(11)]];$x['access']=false;
    $retry=['delegation'=>['enabled'=>false,'expected_permission_id'=>pcv_id(11),'event_id'=>pcv_id(12),'review_sha256'=>pcv_hash(11)],'current_password'=>'PRIVATE_PASSWORD'];$h=pcv_render($x,true,null,$retry);$f=pcv_forms($h,'php_controls_delegate')[0];$in=pcv_inputs($f);
    pcv_ok('Stale permission is not presented as current',str_contains($h,'saved permission is no longer current') && !str_contains($h,'currently has permission'));
    pcv_ok('Revocation remains reachable without change access',$in['enabled']===['0'] && $in['expected_permission_id']===[pcv_id(11)]);
    pcv_ok('Permission password retry retains exact event and review',$in['event_id']===[pcv_id(12)] && $in['review_sha256']===[pcv_hash(11)] && $in['current_password']===[''] && !str_contains($h,'PRIVATE_PASSWORD'));
    $bad=$retry;$bad['delegation']['review_sha256']=pcv_hash(99);pcv_refuse('Changed delegation retry cannot silently replace its review',fn()=>pcv_render($x,true,null,$bad));
    $x['delegation']['disable']=null;$h=pcv_render($x,true,null,$retry);pcv_ok('Controller-suppressed stale permission forms stay suppressed',pcv_forms($h,'php_controls_delegate')===[]);
    $x=$s;$x['delegation']['enabled']=true;$x['delegation']['current']=true;$h=pcv_render($x);pcv_ok('Current permission explicitly differs from old saved permission',str_contains($h,'currently has permission') && !str_contains($h,'no longer current'));
    $x=$s;$x['history']=[pcv_history(20,'pending','pending'),pcv_history(21,'not_sent','pending'),pcv_history(22,'attempted','pending'),pcv_history(23,'attempted','different'),pcv_history(24,'attempted','unavailable'),pcv_history(25,'attempted','observed')];
    $x['history'][0]['recovery_sha256']=pcv_hash(20);$x['history'][5]['recovered_from_ledger']=true;$h=pcv_render($x);
    foreach (['Awaiting a result record','Not sent','Change attempted','Awaiting a check','Value differs','Could not check','Requested value observed'] as $label) pcv_ok('History accurately labels '.$label,str_contains($h,$label));
    pcv_ok('Attempted response alone is not called success',str_contains($h,'An attempted change is not proof') && str_contains($h,'does not prove what caused'));
    pcv_ok('Recovered terminal is distinguished from sending',str_contains($h,'This did not resend the change'));
    pcv_ok('Only eligible attempted histories have verify forms',count(pcv_forms($h,'php_controls_verify'))===3);
    pcv_ok('Owner recovery requires exact reviewed ledger and deliberate confirmation',count(pcv_forms($h,'php_controls_recover'))===1 && pcv_inputs(pcv_forms($h,'php_controls_recover')[0])['ledger_sha256']===[pcv_hash(20)] && pcv_inputs(pcv_forms($h,'php_controls_recover')[0])['confirmed']===['1']);
    $hc=pcv_render($x,false);pcv_ok('Client can verify its projected requests but never recover history',count(pcv_forms($hc,'php_controls_verify'))===3 && pcv_forms($hc,'php_controls_recover')===[]);
    preg_match_all('/\bid="([^"]+)"/',$h,$ids);pcv_ok('Rendered history anchors are unique',count($ids[1])===count(array_unique($ids[1])));
    pcv_ok('History contains no resend or automatic submit control',!str_contains($h,'Resend') && !str_contains($h,'<script') && !str_contains($h,'onload='));
    $x['history'][0]['before_value']=$payload;$x['history'][0]['desired_value']=$payload;$x['history'][3]['observed_value']=$payload;$h=pcv_render($x);pcv_ok('Historical values are escaped',!str_contains($h,'<script>') && str_contains($h,'&lt;script&gt;'));
    $x['history'][0]['link_id']=pcv_id(99);pcv_refuse('Cross-website history fails presentation instead of linking another service',fn()=>pcv_render($x));
    $x=$s;$x['page']=3;$x['has_more']=true;$url='https://studio.example/sub/admin/index.php?view=provider-php-controls&service_link='.$s['link_id'].'&php_page=3';$h=pcv_render($x,true,null,[],$url);
    pcv_ok('Controller absolute own-site destination is retained and escaped',str_contains($h,'action="'.esc($url).'"'));
    pcv_ok('Pagination keeps website and replaces page without duplication',str_contains($h,'php_page=2#php-controls-history') && str_contains($h,'php_page=4#php-controls-history') && !str_contains($h,'php_page=3&amp;php_page'));
    foreach (['javascript:alert(1)','//foreign.example/path','https://user:pass@example.test/','/admin.php#fragment',"/admin.php\r\nX:yes",'/\\foreign.example'] as $url) pcv_refuse('Unsafe action destination refused '.json_encode($url),fn()=>pcv_render($s,true,null,[],$url));
    $h=pcv_render($s,true,null,[],'?view=provider-php-controls&service_link='.$s['link_id']);pcv_ok('Fixed query-relative route supported',str_contains($h,'action="?view=provider-php-controls&amp;service_link='));
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected rendering error','passed'=>false,'error'=>get_class($e).': '.$e->getMessage(),'line'=>$e->getLine()];}
$passed=!array_filter($checks,static fn($c)=>$c['passed']!==true);
$report=['passed'=>$passed,'count'=>count($checks),'scope'=>'Actual renderer and real CSRF helper with synthetic authorized screen projections. No SQL, authority, provider, HTTP, CSS layout or browser execution. Warnings fail.','source_sha256'=>hash_file('sha256',__DIR__.'/../site/app/providers/php_controls_views.php'),'checks'=>$checks];
$path=$argv[1]??__DIR__.'/../qa/provider-php-controls-views-default.json';
if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
echo json_encode(['passed'=>$passed,'count'=>count($checks)]).PHP_EOL;exit($passed?0:1);
