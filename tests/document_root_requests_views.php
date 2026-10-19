<?php
declare(strict_types=1);
/** Actual view/controller bodies; explicit business models, no SQL or provider calls. */
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
final class FolderUiBoundary extends Exception { public function __construct(public string $kind,public mixed $value=null) { parent::__construct($kind); } }
function fui_functions(string $file): void {
    $tokens=token_get_all((string)file_get_contents($file),TOKEN_PARSE); $count=count($tokens);
    for($i=0;$i<$count;$i++) {
        if(!is_array($tokens[$i]) || $tokens[$i][0]!==T_FUNCTION) continue;
        $text='';$depth=0;$started=false;
        for(;$i<$count;$i++) { $t=$tokens[$i]; $s=is_array($t)?$t[1]:$t; $text.=$s;
            if($s==='{') {$depth++;$started=true;} elseif($s==='}') $depth--;
            if($started && $depth===0) break;
        }
        eval($text);
    }
}
function esc(mixed $s):string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function admin_url(string $view='',array $args=[]):string { return '/admin/index.php?'.http_build_query(['view'=>$view]+$args); }
function studio_url(array $args=[]):string { return '/portal/index.php?'.http_build_query($args); }
function csrf_field():string { return '<input type="hidden" name="csrf" value="modeled-csrf">'; }
function redirect(string $url):never { throw new FolderUiBoundary('redirect',$url); }
function fail_page(string $message,int $status):never { throw new FolderUiBoundary('fail',[$status,$message]); }
function flash(string $message):void { $GLOBALS['flashes'][]=$message; }
function show_flash():void { $GLOBALS['flash_reads']++; }
function support_current():?array { return $GLOBALS['support']; }
function provider_settings_manager(bool $ready=true):array { $GLOBALS['manager_ready'][]=$ready; if($GLOBALS['manager_error']) throw $GLOBALS['manager_error'];return ['id'=>1,'name'=>'Studio Owner']; }
function admin_head(string $title,string $view,array $user):void { echo '<main><h1>'.esc($title).'</h1>'; }
function admin_foot():never { throw new FolderUiBoundary('finished'); }
function site_head(string $title,string $view):void { echo '<main>'; }
function site_foot(bool $flag):never { throw new FolderUiBoundary('finished'); }
function provider_document_root_requests_actor(?int $clientId,?string $password=null):array { $GLOBALS['calls'][]=['actor',[$clientId,$password]]; if($GLOBALS['actor_error']) throw $GLOBALS['actor_error']; return ['id'=>$clientId??1]; }
function provider_document_root_requests_screen(?int $clientId=null,int $page=1,?string $id=null):array { $GLOBALS['calls'][]=['screen',[$clientId,$page,$id]];if($GLOBALS['screen_error'])throw $GLOBALS['screen_error'];return $GLOBALS['screen']; }
function provider_document_root_requests_prepare(...$args):void { $GLOBALS['calls'][]=['prepare',$args]; }
function provider_document_root_requests_create(...$args):void { $GLOBALS['calls'][]=['create',$args]; }
function provider_document_root_requests_change(...$args):void { $GLOBALS['calls'][]=['change',$args]; }
function provider_document_root_requests_approve(...$args):void { $GLOBALS['calls'][]=['approve',$args]; }
function provider_document_root_requests_verify(...$args):void { $GLOBALS['calls'][]=['verify',$args]; }
function provider_document_root_requests_recover(...$args):void { $GLOBALS['calls'][]=['recover',$args]; }
foreach(['app/providers/document_root_requests_views.php','admin/controllers/document_root_requests.php','portal/controllers/document_root_requests.php'] as $path) fui_functions(CWP_CODE_ROOT.'/'.$path);
function fui_reset():void { $_GET=[];$_POST=[];$_SESSION=[];$_SERVER['REQUEST_METHOD']='GET';$GLOBALS['calls']=[];$GLOBALS['flashes']=[];$GLOBALS['flash_reads']=0;$GLOBALS['manager_ready']=[];$GLOBALS['manager_error']=null;$GLOBALS['actor_error']=null;$GLOBALS['screen_error']=null;$GLOBALS['support']=null;$GLOBALS['screen']=fui_screen(); }
function fui_item():array { return ['id'=>str_repeat('a',32),'client'=>['id'=>8,'name'=>'Avery <Studio> & Co.'],'link_id'=>str_repeat('b',32),'package_id'=>2801,'package_name'=>'Client hosting','name'=>'portfolio.example.test','before_path'=>'../saved/<old> & café','desired_path'=>'public_html/new-portfolio','created_epoch'=>1792396800,'state'=>'pending','latest_event'=>str_repeat('c',32),'request_sha256'=>str_repeat('d',64),'current_assignment'=>true,'can_cancel'=>true,'can_decline'=>true,'note'=>'','owner_request_id'=>null,'last_check'=>null,'approval'=>['request_id'=>str_repeat('e',32),'anchor_id'=>str_repeat('f',32),'binding_sha256'=>str_repeat('1',64),'before_path'=>'public_html/current-owner-map','observed_epoch'=>1792396900],'approval_unavailable'=>false,'dispatch'=>null]; }
function fui_screen():array { $i=fui_item();return ['ready'=>true,'options'=>[['link_id'=>$i['link_id'],'package_id'=>2801,'package_name'=>'Client hosting','review_sha256'=>str_repeat('2',64),'entries'=>[['name'=>$i['name'],'path'=>$i['before_path']],['name'=>'empty.example.test','path'=>'']],'observed_epoch'=>1792396700]],'items'=>[$i],'page'=>1,'has_more'=>false]; }
function fui_html(array $screen,bool $owner=false,array $route=['page'=>1,'request'=>null]):string { ob_start();try{provider_document_root_requests_render($screen,$owner,$route);return (string)ob_get_contents();}finally{ob_end_clean();} }
function fui_call(callable $fn):array { ob_start();$boundary=null;$error=null;try{$fn();}catch(FolderUiBoundary $e){$boundary=$e;}catch(Throwable $e){$error=$e;}finally{$html=(string)ob_get_clean();}return [$html,$boundary,$error]; }
function fui_form(string $html,string $marker):string { if(!preg_match('/<form\b[^>]*\b'.preg_quote($marker,'/').'\b[^>]*>.*?<\/form>/s',$html,$m))return '';return $m[0]; }
function fui_value(string $html,string $key):?string { if(!preg_match('/<input\b[^>]*name="'.preg_quote($key,'/').'"[^>]*value="([^"]*)"/s',$html,$m))return null;return html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); }
$checks=[];
function fui_check(string $name,bool $passed):void { global $checks;foreach($checks as $check)if($check['name']===$name)throw new RuntimeException('Duplicate check');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name); }

fui_reset();
if(($argv[1]??'')==='--render') {
    $dir=$argv[2]??'';if($dir===''||file_exists($dir))throw new RuntimeException('Fresh output directory required');mkdir($dir,0777,true);
    $scenes=[];
    foreach(['Client-Pending','Owner-Review','Owner-Readback','Client-Observed'] as $scene) {
        fui_reset();$s=fui_screen();$owner=str_starts_with($scene,'Owner-');
        if(!$owner) {$s['items'][0]['approval']=null;$s['items'][0]['client']=null;}
        if($scene==='Owner-Readback'||$scene==='Client-Observed') {$s['options']=[];$s['items'][0]['state']=$owner?'unknown':'observed';$s['items'][0]['can_cancel']=false;$s['items'][0]['can_decline']=false;$s['items'][0]['approval']=null;$s['items'][0]['owner_request_id']=str_repeat('e',32);$s['items'][0]['last_check']=['disposition'=>$owner?'different':'observed','recorded_epoch'=>1792397000];$s['items'][0]['dispatch']=$owner?['can_verify'=>true,'recovery_ledger_sha256'=>null,'last_check'=>['path'=>'public_html/previous-folder']]:null;}
        $s['items'][0]['name']=str_repeat('long-hostname-',7).'example.test';$s['items'][0]['before_path']='../'.str_repeat('opaque-観測-<saved>&',12);$s['options'][0]['entries'][0]['path']=$s['items'][0]['before_path'];
        if($scene!=='Client-Pending')$s['options']=[];
        $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Modeled website folder requests</title>';
        $styles=$owner?['site','admin','mail-notices','growth']:['site','premium','growth','mail-notices'];
        foreach($styles as $style)$html.='<link rel="stylesheet" href="/assets/css/'.$style.'.css">';
        $html.='</head><body class="'.($owner?'admin-body':'cwp-site is-inner').'"><main class="container section"><p class="hint">Synthetic layout preview · application renderer · '.esc(trim((string)file_get_contents(dirname(__DIR__).'/VERSION'))).'</p><h1>Website folder requests</h1>'.fui_html($s,$owner).'</main></body></html>';
        file_put_contents($dir.'/'.$scene.'.html',$html);$scenes[]=['name'=>$scene,'file'=>$scene.'.html','owner'=>$owner];
    }
    echo json_encode(['scenes'=>$scenes],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";exit;
}
try {
    $s=fui_screen();$item=$s['items'][0];$client=fui_html($s);$owner=fui_html($s,true);
    fui_check('Client form uses exact explicit folder route',str_contains($client,'action="/portal/index.php?folder_requests=1&amp;folder_page=1"'));
    fui_check('Client form includes CSRF field',fui_value(fui_form($client,'data-folder-request-create'),'csrf')==='modeled-csrf');
    fui_check('Client hidden assignment and review are exact',fui_value($client,'link_id')===$item['link_id']&&fui_value($client,'review_sha256')===str_repeat('2',64));
    fui_check('New client request has one exact generated identity',preg_match('/\A[a-f0-9]{32}\z/',fui_value($client,'request_id')??'')===1);
    fui_check('Client selects only shared hostnames',str_contains($client,'<option value="portfolio.example.test">portfolio.example.test</option>')&&str_contains($client,'<option value="empty.example.test">'));
    fui_check('Shared opaque paths are escaped without normalization',str_contains($client,'../saved/&lt;old&gt; &amp; café'));
    fui_check('Empty saved path is distinguishable',str_contains($client,'Empty saved text value'));
    fui_check('Client never receives owner approval forms or proof',!str_contains($client,'data-folder-request-approve')&&!str_contains($client,'binding_sha256')&&!str_contains($client,'current_password'));
    fui_check('Client cannot see requesting client identity',!str_contains($client,'Requested by'));
    fui_check('Owner requesting client identity is escaped',str_contains($owner,'Requested by Avery &lt;Studio&gt; &amp; Co. · Client #8'));
    fui_check('Owner original and current paths remain separate',str_contains($owner,'../saved/&lt;old&gt; &amp; café')&&str_contains($owner,'public_html/current-owner-map'));
    fui_check('Owner approval exact original review identities',fui_value(fui_form($owner,'data-folder-request-approve'),'anchor_id')===str_repeat('f',32)&&fui_value(fui_form($owner,'data-folder-request-approve'),'binding_sha256')===str_repeat('1',64));
    fui_check('Owner attests eligibility and existing destination',str_contains($owner,'requested destination folder already exists')&&str_contains($owner,'Managed WordPress'));
    fui_check('Owner password blank and consent unchecked',!preg_match('/type="password"[^>]*value=/',$owner)&&!str_contains($owner,' checked'));
    fui_check('Readback limitation never promises protection',str_contains($client,'does not prove that website routing works or that files are protected'));
    fui_check('History card exposes safe status and exact identity',str_contains($client,'data-folder-request-item="'.$item['id'].'" data-folder-request-state="pending"'));
    $empty=$s;$empty['options'][0]['entries']=[];fui_check('Empty complete map has no create form',!str_contains(fui_html($empty),'data-folder-request-create'));
    $absent=$s;$absent['ready']=false;fui_check('Client absent state offers no preparation mutation',!str_contains(fui_html($absent),'<form')&&str_contains(fui_html($absent),'has not enabled'));
    fui_check('Owner absent state exposes explicit preparation',str_contains(fui_html($absent,true),'data-folder-request-prepare'));
    $unavailable=$s;$unavailable['items'][0]['approval']=null;$unavailable['items'][0]['approval_unavailable']=true;fui_check('Unavailable approval explains safe next step',str_contains(fui_html($unavailable,true),'refresh website folders in connection settings'));
    foreach(['pending','cancelled','declined','interrupted','not_sent','unknown','observed'] as $state)fui_check('Known state '.$state.' has a specific label',provider_document_root_requests_label($state)!=='Status unavailable');
    $history=$s;$history['options']=[];$history['items'][0]['current_assignment']=false;$history['items'][0]['approval']=null;$history['items'][0]['can_cancel']=false;
    fui_check('Changed client authority hides original shared path',!str_contains(fui_html($history),'../saved/')&&str_contains(fui_html($history),'public_html/new-portfolio'));
    $history['items'][0]['last_check']=['disposition'=>'different','recorded_epoch'=>1792397000,'path'=>'PRIVATE-FRESH-PATH'];
    fui_check('Client follow-up ignores arbitrary path fields',!str_contains(fui_html($history),'PRIVATE-FRESH-PATH'));
    $history['items'][0]['state']='observed';fui_check('Observed result remains historical not causal',str_contains(fui_html($history),'does not prove that the request caused the change'));
    $dispatch=$s;$dispatch['items'][0]['state']='unknown';$dispatch['items'][0]['approval']=null;$dispatch['items'][0]['can_decline']=false;$dispatch['items'][0]['dispatch']=['can_verify'=>false,'recovery_ledger_sha256'=>null,'last_check'=>null];
    fui_check('Unknown without finished ledger has no readback form',!str_contains(fui_html($dispatch,true),'data-folder-request-verify'));
    $dispatch['items'][0]['dispatch']['can_verify']=true;fui_check('Eligible unknown has separate readback form',str_contains(fui_html($dispatch,true),'data-folder-request-verify'));
    $dispatch['items'][0]['state']='interrupted';fui_check('Interrupted without ledger hash has no recovery',!str_contains(fui_html($dispatch,true),'data-folder-request-recover'));
    $dispatch['items'][0]['dispatch']['recovery_ledger_sha256']=str_repeat('9',64);fui_check('Interrupted exact ledger recovery is explicit and local',str_contains(fui_html($dispatch,true),'data-folder-request-recover')&&str_contains(fui_html($dispatch,true),'without resending'));
    $dispatch['items'][0]['current_assignment']=false;fui_check('Changed authority hides owner follow-up mutations',!str_contains(fui_html($dispatch,true),'data-folder-request-recover'));
    $_GET=['folder_page'=>'2','request'=>$item['id']];fui_check('Valid exact route survives',provider_document_root_requests_route()===['page'=>2,'request'=>$item['id']]);
    foreach([[], '0','501','01','1x'] as $bad){$_GET=['folder_page'=>$bad,'request'=>['x']];fui_check('Invalid route safely defaults '.json_encode($bad),provider_document_root_requests_route()===['page'=>1,'request'=>null]);}
    $s['page']=2;$s['has_more']=true;$paged=fui_html($s,false,['page'=>2,'request'=>null]);fui_check('Pagination retains folder route',str_contains($paged,'folder_requests=1&amp;folder_page=1')&&str_contains($paged,'folder_requests=1&amp;folder_page=3'));
    fui_check('Detail return link drops only exact selection',str_contains(fui_html($s,true,['page'=>2,'request'=>$item['id']]),'href="/admin/index.php?view=folder-requests&amp;folder_page=2">All requests'));
    fui_reset();$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'folder_request_create','request_id'=>str_repeat('3',32),'link_id'=>$item['link_id'],'review_sha256'=>str_repeat('4',64),'name'=>'invalid <host>','desired_path'=>' /not-valid/../'.str_repeat('x',1024),'confirmed'=>'1','current_password'=>'DO-NOT-ECHO'];
    $retry=fui_html(fui_screen());$form=fui_form($retry,'data-folder-request-create');
    foreach(['request_id','link_id','review_sha256','desired_path'] as $key)fui_check('Failed create retains raw '.$key,fui_value($form,$key)===$_POST[$key]);
    fui_check('Failed invalid hostname retained as escaped selected choice',str_contains($form,'invalid &lt;host&gt;'));
    fui_check('Failed POST clears consent and never echoes password',!str_contains($retry,' checked')&&!str_contains($retry,'DO-NOT-ECHO'));
    $unavailable=fui_screen();$unavailable['options']=[];$retry=fui_html($unavailable);fui_check('Lost option retains original form without fresh admission',str_contains($retry,'data-folder-request-create')&&str_contains($retry,'disabled')&&fui_value($retry,'request_id')===$_POST['request_id']);
    foreach([str_repeat('x',1025),['path'],' xx '] as $bad){$_POST['desired_path']=$bad;[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_post('desired_path',1024));fui_check('Raw path parser '.(is_array($bad)?'array':strlen($bad)),is_array($bad)||strlen($bad)>1024 ? $e instanceof InvalidArgumentException : $e===null&&provider_document_root_requests_post('desired_path',1024)===' xx ');}
    fui_reset();$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'provider_folder_request_approve','request_id'=>$item['id'],'expected_event'=>str_repeat('5',32),'request_sha256'=>str_repeat('6',64),'owner_request_id'=>str_repeat('7',32),'anchor_id'=>str_repeat('8',32),'binding_sha256'=>str_repeat('9',64),'reviewed'=>'1','current_password'=>'DO-NOT-ECHO'];
    $retry=fui_html(fui_screen(),true);$form=fui_form($retry,'data-folder-request-approve');foreach(['request_id','expected_event','request_sha256','owner_request_id','anchor_id','binding_sha256'] as $key)fui_check('Failed approval retains exact '.$key,fui_value($form,$key)===$_POST[$key]);
    fui_check('Failed approval does not replace fresh map or consent',!str_contains($retry,'public_html/current-owner-map')&&!str_contains($retry,' checked')&&!str_contains($retry,'DO-NOT-ECHO'));
    $unavailable=fui_screen();$unavailable['items'][0]['approval']=null;$unavailable['items'][0]['approval_unavailable']=true;fui_check('Failed original approval survives unavailable new preview',fui_value(fui_form(fui_html($unavailable,true),'data-folder-request-approve'),'anchor_id')===$_POST['anchor_id']);
    $_POST['name']='tampered-host';$_POST['desired_path']='tampered-path';$_GET=['folder_page'=>'2'];[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_admin_action($_POST['action']));$args=$GLOBALS['calls'][1][1];
    fui_check('Approval forwards only exact retained signed identities', $GLOBALS['calls'][1][0]==='approve'&&$args===[$item['id'],str_repeat('5',32),str_repeat('6',64),str_repeat('7',32),str_repeat('8',32),str_repeat('9',64),true,'DO-NOT-ECHO']);
    fui_check('Successful owner action returns exact detail and page',$b?->kind==='redirect'&&$b->value==='/admin/index.php?view=folder-requests&folder_page=2&request='.$item['id']);
    fui_reset();$_POST=['request_id'=>$item['id'],'link_id'=>$item['link_id'],'review_sha256'=>str_repeat('2',64),'name'=>'portfolio.example.test','desired_path'=>'public_html/a','confirmed'=>'1'];[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_client_action('folder_request_create',['id'=>8]));
    fui_check('Create uses primary client plus exact immutable request choices',$GLOBALS['calls']===[['actor',[8,null]],['create',[$item['id'],8,$item['link_id'],str_repeat('2',64),'portfolio.example.test','public_html/a',true]]]);
    $_POST['confirmed']=['1'];$GLOBALS['calls']=[];fui_call(fn()=>provider_document_root_requests_client_action('folder_request_create',['id'=>8]));fui_check('Malformed client confirmation never becomes true',$GLOBALS['calls'][1][1][6]===false);
    $_POST['desired_path']=str_repeat('x',1025);$GLOBALS['calls']=[];[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_client_action('folder_request_create',['id'=>8]));fui_check('Oversized request is refused before create',$e instanceof InvalidArgumentException&&count($GLOBALS['calls'])===1);
    $GLOBALS['actor_error']=new RuntimeException('Primary client required');$_POST=['request_id'=>[]];[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_client_action('folder_request_create',['id'=>8]));fui_check('Actor refusal precedes malformed POST parsing',$e?->getMessage()==='Primary client required');
    fui_reset();$_POST=['request_id'=>$item['id'],'expected_event'=>str_repeat('c',32),'event_id'=>str_repeat('4',32),'note'=>'ignored'];fui_call(fn()=>provider_document_root_requests_client_action('folder_request_cancel',['id'=>8]));fui_check('Client withdrawal cannot pass owner note or credentials',$GLOBALS['calls'][1]===['change',[$item['id'],str_repeat('c',32),str_repeat('4',32),'cancel','',8]]);
    fui_reset();$_POST=['request_id'=>$item['id'],'expected_event'=>str_repeat('c',32),'event_id'=>str_repeat('4',32),'note'=>' Keep raw note ','current_password'=>'pw'];fui_call(fn()=>provider_document_root_requests_admin_action('provider_folder_request_decline'));fui_check('Owner decline forwards raw note for service validation',$GLOBALS['calls'][1]===['change',[$item['id'],str_repeat('c',32),str_repeat('4',32),'decline',' Keep raw note ',null,'pw']]);
    fui_reset();$_POST=['request_id'=>$item['id'],'owner_request_id'=>str_repeat('e',32),'current_password'=>'pw'];fui_call(fn()=>provider_document_root_requests_admin_action('provider_folder_request_verify'));fui_check('Owner readback forwards reciprocal request pair',$GLOBALS['calls'][1]===['verify',[$item['id'],str_repeat('e',32),'pw']]);
    $GLOBALS['calls']=[];$_POST['ledger_sha256']=str_repeat('9',64);[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_admin_action('provider_folder_request_recover'));fui_check('Recovery confirmation required before service',$e instanceof InvalidArgumentException&&count($GLOBALS['calls'])===1);
    $_POST['confirmed']='1';$GLOBALS['calls']=[];fui_call(fn()=>provider_document_root_requests_admin_action('provider_folder_request_recover'));fui_check('Recovery exact ledger and pair preserved',$GLOBALS['calls'][1]===['recover',[$item['id'],str_repeat('e',32),str_repeat('9',64),'pw']]);
    fui_reset();$_POST=['current_password'=>'pw'];fui_call(fn()=>provider_document_root_requests_admin_action('provider_folder_request_prepare'));fui_check('Prepare is explicit owner mutation',$GLOBALS['calls'][1]===['prepare',['pw']]);
    fui_reset();$GLOBALS['screen_error']=new RuntimeException('An owner must prepare provider settings first.');[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_admin_render(''));fui_check('Cold owner shell uses manager false and friendly unavailable content',$GLOBALS['manager_ready']===[false]&&$b?->kind==='finished'&&str_contains($h,'Open 20i connection settings'));
    fui_check('Cold fallback points to actual provider settings route',str_contains($h,'/admin/index.php?view=provider-settings'));
    $GLOBALS['screen_error']=new PDOException('PRIVATE SQL DETAILS');[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_admin_render(''));fui_check('PDO content is private and keeps owner shell',$b?->kind==='finished'&&!str_contains($h,'PRIVATE SQL DETAILS')&&str_contains($h,'temporarily unavailable'));
    foreach(['contact_id','contact_auth','support'] as $key){fui_reset();$_SESSION[$key]=null;[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_admin_render(''));fui_check('Raw owner '.$key.' presence refuses before manager',$b?->value[0]===403&&$GLOBALS['manager_ready']===[]);}
    fui_reset();$GLOBALS['manager_error']=new RuntimeException('Owner only');[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_admin_render(''));fui_check('Nonowner shell refuses 403',$b?->value[0]===403);
    fui_reset();$GLOBALS['actor_error']=new RuntimeException('No client');[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_client_render(['id'=>8],''));fui_check('Client renderer actor refusal becomes 403',$b?->value[0]===403);
    fui_reset();$GLOBALS['screen_error']=new PDOException('PRIVATE SQL DETAILS');[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_client_render(['id'=>8],''));fui_check('Client screen database failure is private and retryable',$b?->kind==='finished'&&!str_contains($h,'PRIVATE SQL DETAILS')&&str_contains($h,'Reload current request details'));
    fui_reset();[$h,$b,$e]=fui_call(fn()=>provider_document_root_requests_client_render(['id'=>8],'Invalid <input>'));fui_check('Actual portal view calls shared renderer and escapes error',$b?->kind==='finished'&&str_contains($h,'data-folder-requests')&&str_contains($h,'Invalid &lt;input&gt;'));
    $result=['passed'=>true,'scope'=>'Actual extracted PHP views/controllers with explicit business models; no SQL, installed HTTP or provider calls','checks'=>$checks];
} catch(Throwable $e) {$result=['passed'=>false,'scope'=>'Actual extracted PHP views/controllers with explicit business models','checks'=>$checks,'error_class'=>get_class($e),'error'=>$e->getMessage()];}
$json=json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
if(isset($argv[1])) { $parent=dirname($argv[1]);if(!is_dir($parent))mkdir($parent,0777,true);file_put_contents($argv[1],$json); }
echo $json;exit($result['passed']?0:1);
