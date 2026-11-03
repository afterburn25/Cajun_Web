<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
define('CWP_BOOT', true);
define('CWP_CODE_ROOT', dirname(__DIR__).'/site');
set_error_handler(static function(int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
$checks = []; $calls = []; $denyActor = false; $badCsrf = false; $rateAllowed = true; $serviceError = ''; $support = false;
$_SESSION = []; $_POST = []; $_GET = [];
function esc(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function admin_url(string $view = 'dashboard', array $params = []): string { return 'https://studio.synthetic.invalid/subfolder/admin/index.php?'.http_build_query(['view' => $view] + $params); }
function studio_url(array $params = []): string { return 'https://studio.synthetic.invalid/subfolder/portal/index.php'.($params ? '?'.http_build_query($params) : ''); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="fresh-csrf">'; }
function support_current(): bool { return $GLOBALS['support']; }
function registered_nameserver_changes_summary_render(array $view): void { $GLOBALS['calls'][] = ['owner_summary', $view]; echo '<section data-existing-owner-summary>'.esc($view['id']).'</section>'; }
function registered_nameserver_requests_actor(?int $client, ?string $password = null): array { $GLOBALS['calls'][] = ['actor', $client]; if ($GLOBALS['denyActor']) throw new RuntimeException('Direct identity required.'); return ['kind'=>$client===null?'owner':'client','id' => $client ?? 1,'auth_sha256'=>str_repeat('7',64)]; }
function provider_settings_manager(): array { $GLOBALS['calls'][]=['owner_shell'];return ['id'=>1,'name'=>'Actual owner','role'=>'owner']; }
function registered_nameserver_requests_screen(?int $client=null,int $page=1,?string $detail=null): array { $GLOBALS['calls'][]=['screen',[$client,$page,$detail]];if($GLOBALS['serviceError']!=='')throw new RuntimeException($GLOBALS['serviceError']);return $GLOBALS['renderScreen']; }
function admin_head(string $title,string $view,array $user): void { if(($user['name']??null)!=='Actual owner'||($user['role']??null)!=='owner')throw new RuntimeException('Shell received canonical actor instead of actual owner.');$GLOBALS['calls'][]=['admin_head',$user]; }
function site_head(string $title,string $view): void { $GLOBALS['calls'][]=['site_head']; }
function show_flash(): void {}
class RnruRenderStop extends Exception {}
function admin_foot(): never { throw new RnruRenderStop(); }
function site_foot(bool $value): never { throw new RnruRenderStop(); }
function check_csrf(): void { $GLOBALS['calls'][] = ['csrf']; if ($GLOBALS['badCsrf']) throw new RuntimeException('CSRF refused.'); }
function consume_rate(string $key, int $limit, int $seconds): bool { $GLOBALS['calls'][] = ['rate', $key, $limit, $seconds]; return $GLOBALS['rateAllowed']; }
function rnru_mutate(string $kind, array $args): array { $GLOBALS['calls'][] = [$kind, $args]; if ($GLOBALS['serviceError'] !== '') throw new RuntimeException($GLOBALS['serviceError']); return []; }
function registered_nameserver_requests_create(...$args): array { return rnru_mutate('create', $args); }
function registered_nameserver_requests_change(...$args): array { return rnru_mutate('change', $args); }
function registered_nameserver_requests_approve(...$args): array { return rnru_mutate('approve', $args); }
function registered_nameserver_requests_prepare(...$args): array { return rnru_mutate('prepare', $args); }
function flash(string $value): void { $GLOBALS['calls'][] = ['flash', $value]; }
class RnruRedirect extends Exception { public function __construct(public string $url) { parent::__construct('redirect'); } }
function redirect(string $url): never { throw new RnruRedirect($url); }
function rnru_check(string $name, bool $passed): void { $GLOBALS['checks'][] = ['name' => $name, 'passed' => $passed]; if (!$passed) throw new RuntimeException($name); }
function rnru_html(callable $fn): string { ob_start(); try { $fn(); return (string)ob_get_contents(); } finally { ob_end_clean(); } }
function rnru_inputs(string $html): array { preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/', $html, $rows, PREG_SET_ORDER); $out=[]; foreach($rows as $row)$out[$row[1]][]=html_entity_decode($row[2], ENT_QUOTES|ENT_HTML5,'UTF-8');return $out; }
function rnru_refuse(string $name, callable $fn): void { try {$fn();} catch(InvalidArgumentException|RuntimeException $e) {rnru_check($name, true);return;} rnru_check($name, false); }
function rnru_reset(): void { $GLOBALS['calls']=[];$GLOBALS['denyActor']=false;$GLOBALS['badCsrf']=false;$GLOBALS['rateAllowed']=true;$GLOBALS['serviceError']='';$GLOBALS['support']=false;$_GET=[];$_SESSION=[]; }
function rnru_action(callable $fn): string { try {$fn();} catch(RnruRedirect $e){return $e->url;} throw new RuntimeException('Expected a redirect.'); }
function rnru_kinds(): array { return array_column($GLOBALS['calls'],0); }
function rnru_args(string $kind): array { foreach($GLOBALS['calls'] as $call)if($call[0]===$kind)return $call[1];throw new RuntimeException('Missing actual controller call.'); }
function rnru_render_controller(callable $fn): string { return rnru_html(static function()use($fn):void {try{$fn();}catch(RnruRenderStop){return;}throw new RuntimeException('Renderer did not reach its footer.');}); }
try {
    require CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests_views.php';
    // Load complete unchanged function declarations, omitting only dependency/bootstrap requires.
    foreach (['admin','portal'] as $area) {
        $source = file_get_contents(CWP_CODE_ROOT.'/'.$area.'/controllers/registered_nameserver_requests.php');
        $start = strpos($source, "\nfunction ");
        if ($start === false) throw new RuntimeException('Controller declarations missing.');
        eval(substr($source, $start + 1));
    }
    $id=str_repeat('a',32);$link=str_repeat('b',32);$hash=str_repeat('c',64);$event=str_repeat('d',32);$dispatch=str_repeat('e',32);
    $option=['link_id'=>$link,'domain'=>'example.test','review_sha256'=>$hash];
    $item=['id'=>$id,'domain'=>'example.test','desired_nameservers'=>['ns1.new.test','ns2.new.test'],'created_epoch'=>1760000000,'state'=>'pending','latest_event'=>$id,'request_sha256'=>$hash,'current_assignment'=>true,'can_cancel'=>true,'can_decline'=>true,'note'=>'','owner_request_id'=>null,'last_check'=>null,'approval'=>null,'dispatch'=>null];
    $screen=['ready'=>true,'options'=>[$option],'items'=>[$item],'page'=>1,'has_more'=>false];
    $html=rnru_html(fn()=>registered_nameserver_requests_render($screen,false));$inputs=rnru_inputs($html);
    rnru_check('Client renders local request and pending withdrawal only',($inputs['action']??[])===['nameserver_request_create','nameserver_request_cancel']);
    rnru_check('Client creation uses exact assignment and opaque review digest',($inputs['link_id']??[])===[$link]&&($inputs['review_sha256']??[])===[$hash]);
    rnru_check('Every client mutation contains the current CSRF field',($inputs['csrf']??[])===['fresh-csrf','fresh-csrf']);
    rnru_check('Independent request and cancel identities are bounded and distinct',preg_match('/\A[a-f0-9]{32}\z/',$inputs['request_id'][0])===1&&preg_match('/\A[a-f0-9]{32}\z/',$inputs['event_id'][0])===1&&$inputs['event_id'][0]!==$inputs['request_id'][0]);
    rnru_check('Client form does not claim send or require a provider refresh',str_contains($html,'Submitting a request does not send a change.')&&!str_contains($html,'Refresh nameservers before submitting'));
    rnru_check('Client decisions require fresh unchecked confirmations',substr_count($html,'name="confirmed" value="1" required')===2&&!str_contains($html,' checked'));
    rnru_check('Client has no password, old set, send or verification fields',!isset($inputs['current_password'],$inputs['old-ns'])&&!str_contains($html,'provider_nameserver_change_')&&!str_contains($html,'provider_nameserver_request_'));
    $poison=$screen;$poison['items'][0]['domain']='evil"<script>alert(1)</script>';$poison['items'][0]['note']='note</p><img src=x onerror=x>';$poison['items'][0]['desired_nameservers']=['ns<script>.invalid'];$poison['items'][0]['approval']=['review_sha256'=>$hash,'old_nameservers'=>['PRIVATE_OLD'],'observed_epoch'=>1760000000];$poison['items'][0]['dispatch']=['id'=>'PRIVATE_DISPATCH'];$poison['items'][0]['owner_request_id']='PRIVATE_OWNER';$poison['items'][0]['secret']='PRIVATE_SECRET';
    $html=rnru_html(fn()=>registered_nameserver_requests_render($poison,false));
    rnru_check('All displayed client text is escaped',!str_contains($html,'<script>')&&!str_contains($html,'<img src=x')&&str_contains($html,'&lt;script&gt;'));
    rnru_check('Client renderer ignores owner/private fields even when present',!str_contains($html,'PRIVATE_'));
    foreach(['pending','cancelled','declined','dispatched','not_sent','interrupted','uncertain','observed'] as $state) {
        $row=$item;$row['state']=$state;$row['can_cancel']=false;$row['can_decline']=false;
        $html=rnru_html(fn()=>registered_nameserver_requests_ui_item($row,false,[]));
        rnru_check('Safe '.$state.' label renders without raw state or send form',str_contains($html,esc(registered_nameserver_requests_ui_label($state)))&&!str_contains($html,'<form'));
    }
    $row=$item;$row['state']='uncertain';$row['can_cancel']=false;$row['current_assignment']=false;
    foreach(['different','unavailable','observed'] as $status) {
        $row['last_check']=['disposition'=>$status,'nameservers'=>$status==='unavailable'?null:['ns.current.test'],'recorded_epoch'=>1760000030];
        $html=rnru_html(fn()=>registered_nameserver_requests_ui_item($row,false,[]));
        rnru_check('Client '.$status.' readback is dated and cannot authorize resend',str_contains($html,'2025-10-09 08:53:50 UTC')&&!str_contains($html,'<form')&&str_contains($html,'assignment or connection changed'));
    }
    $unready=$screen;$unready['ready']=false;$unready['options']=[];$unready['items']=[];
    $html=rnru_html(fn()=>registered_nameserver_requests_render($unready,false));rnru_check('Unprepared client page contains no setup or mutation form',!str_contains($html,'<form')&&str_contains($html,'not enabled'));
    $html=rnru_html(fn()=>registered_nameserver_requests_render($unready,true));$inputs=rnru_inputs($html);rnru_check('Unprepared owner page offers dedicated setup with blank password',($inputs['action']??[])===['provider_nameserver_request_prepare']&&($inputs['current_password']??[])===['']);
    $submitted=['action'=>'nameserver_request_create','request_id'=>$id,'link_id'=>$link,'review_sha256'=>str_repeat('f',64),'desired_nameservers'=>"ns1.new.test\nns2.new.test",'confirmed'=>'1','current_password'=>'PRIVATE_PASSWORD','old-ns'=>'PRIVATE_OLD'];
    $retry=registered_nameserver_requests_ui_retry($submitted,false);
    rnru_check('Retry whitelist excludes password, confirmation and old set',!isset($retry['current_password'],$retry['confirmed'],$retry['old-ns'])&&$retry['review_sha256']===$submitted['review_sha256']);
    $html=rnru_html(fn()=>registered_nameserver_requests_render($screen,false,$retry));$inputs=rnru_inputs($html);
    rnru_check('Stale create keeps original request and review with disabled submit',($inputs['request_id'][0]??null)===$id&&($inputs['review_sha256']??[])===[$submitted['review_sha256']]&&str_contains($html,' disabled>Request nameserver review'));
    rnru_check('Stale create keeps complete typed list and clears confirmation',str_contains($html,esc($submitted['desired_nameservers']))&&!str_contains($html,' checked'));
    $html=rnru_html(fn()=>registered_nameserver_requests_render(null,false,$retry));rnru_check('Unavailable history preserves local retry without setup or read',str_contains($html,esc($submitted['desired_nameservers']))&&str_contains($html,' disabled')&&!str_contains($html,'provider_nameserver_request_prepare'));
    $owner=$screen;$owner['options']=[];$owner['items'][0]['approval']=['review_sha256'=>$hash,'old_nameservers'=>['ns.old.test'],'observed_epoch'=>1760000000];
    $html=rnru_html(fn()=>registered_nameserver_requests_render($owner,true));$inputs=rnru_inputs($html);
    rnru_check('Owner approval and decline are separate dedicated forms',($inputs['action']??[])===['provider_nameserver_request_decline','provider_nameserver_request_approve']);
    rnru_check('Owner displays whole old set but never posts it',str_contains($html,'ns.old.test')&&!isset($inputs['old_nameservers'],$inputs['old-ns'],$inputs['ns'],$inputs['desired_nameservers']));
    rnru_check('Owner approval binds original request, latest event and exact review',($inputs['request_sha256']??[])===[$hash]&&($inputs['approval_review_sha256']??[])===[$hash]&&($inputs['expected_event']??[])===[$id,$id]);
    rnru_check('Owner passwords remain blank and confirmations clear',($inputs['current_password']??[])===['','']&&!str_contains($html,' checked'));
    $approvalRetry=registered_nameserver_requests_ui_retry(['action'=>'provider_nameserver_request_approve','request_id'=>$id,'expected_event'=>$event,'request_sha256'=>str_repeat('f',64),'dispatch_id'=>$dispatch,'approval_review_sha256'=>str_repeat('9',64),'current_password'=>'PRIVATE_PASSWORD','reviewed'=>'1'],true);
    $html=rnru_html(fn()=>registered_nameserver_requests_render($owner,true,$approvalRetry));$inputs=rnru_inputs($html);
    rnru_check('Stale owner approval preserves dispatch ID and all original hashes',($inputs['dispatch_id']??[])===[$dispatch]&&($inputs['request_sha256']??[])===[$approvalRetry['request_sha256']]&&($inputs['approval_review_sha256']??[])===[$approvalRetry['approval_review_sha256']]);
    rnru_check('Stale owner approval cannot silently approve a regenerated review',str_contains($html,' disabled>Approve and request change')&&($inputs['expected_event'][1]??null)===$event&&!str_contains($html,'PRIVATE_PASSWORD'));
    $html=rnru_html(fn()=>registered_nameserver_requests_render(null,true,$approvalRetry));$inputs=rnru_inputs($html);rnru_check('Unavailable owner page retains approval locally without generating setup',($inputs['dispatch_id']??[])===[$dispatch]&&($inputs['current_password']??[])===['']&&!str_contains($html,'provider_nameserver_request_prepare'));
    $declineRetry=registered_nameserver_requests_ui_retry(['action'=>'provider_nameserver_request_decline','request_id'=>$id,'expected_event'=>$id,'event_id'=>$event,'note'=>'Client-visible <reason>'],true);
    $owner['items'][0]['can_decline']=false;$owner['items'][0]['approval']=null;$owner['items'][0]['state']='declined';
    $html=rnru_html(fn()=>registered_nameserver_requests_render($owner,true,$declineRetry));rnru_check('Changed disposition preserves unsaved decline note and event without active submit',str_contains($html,'Client-visible &lt;reason&gt;')&&str_contains($html,'value="'.$event.'"')&&str_contains($html,' disabled>Reload to continue'));
    $owner['items'][0]['dispatch']=['id'=>$dispatch];$calls=[];$html=rnru_html(fn()=>registered_nameserver_requests_render($owner,true));rnru_check('Owner exact item delegates its exact dispatch to existing history renderer',rnru_kinds()===['owner_summary']&&rnru_args('owner_summary')===['id'=>$dispatch]);
    $owner['page']=2;$owner['has_more']=true;$html=rnru_html(fn()=>registered_nameserver_requests_render($owner,true));rnru_check('Owner pagination uses actual fixed admin route and bounded next page',str_contains($html,'view=nameserver-requests&amp;request_page=3')&&str_contains($html,'request_page=1'));
    rnru_check('Exact client detail URL stays under actual portal route',registered_nameserver_requests_ui_url(false,$id)==='https://studio.synthetic.invalid/subfolder/portal/index.php?nameserver_requests=1&request_page=1&request_id='.$id);
    foreach([' '.$id, strtoupper($id), str_repeat('a',33), ['x']] as $bad)rnru_refuse('Unnormalized identity refused '.count($checks),fn()=>registered_nameserver_requests_ui_id(['request_id'=>$bad],'request_id'));
    foreach(['0','01','10001','https://bad.invalid',[]] as $bad)rnru_refuse('Invalid page refused '.count($checks),fn()=>registered_nameserver_requests_ui_query(['request_page'=>$bad]));
    rnru_refuse('Oversized desired input is refused rather than truncated',fn()=>registered_nameserver_requests_ui_desired(['desired_nameservers'=>str_repeat('a',16385)]));
    rnru_refuse('Malformed UTF8 is refused before mutation',fn()=>registered_nameserver_requests_ui_desired(['desired_nameservers'=>"\xc3\x28"]));
    rnru_refuse('Control character in desired input is refused',fn()=>registered_nameserver_requests_ui_desired(['desired_nameservers'=>"ns.test\0x"]));
    rnru_check('Line parser preserves actual set membership for service validation',registered_nameserver_requests_ui_desired(['desired_nameservers'=>" ns1.test\r\nns2.test\n\n"])===['ns1.test','ns2.test']);
    rnru_reset();$_POST=$submitted;$_POST['review_sha256']=$hash;$_POST['client_id']='999';$_POST['ns']='FORGED';
    $url=rnru_action(fn()=>registered_nameserver_requests_client_action('nameserver_request_create',['id'=>7]));
    rnru_check('Actual client controller orders actor and CSRF before rate and service',rnru_kinds()===['actor','csrf','rate','create','flash']);
    rnru_check('Client mutation forwards real client and exact closed arguments',rnru_args('create')===[$id,7,$link,$hash,['ns1.new.test','ns2.new.test'],true]);
    rnru_check('Client successful POST redirects to exact new request detail',str_contains($url,'nameserver_requests=1')&&str_ends_with($url,'request_id='.$id));
    rnru_check('Client rate key binds actual client ID and 20 per hour',$calls[2]===['rate','nameserver-request-client-7',20,3600]);
    rnru_reset();$badCsrf=true;rnru_refuse('Client CSRF failure never reaches mutation',fn()=>registered_nameserver_requests_client_action('nameserver_request_create',['id'=>7]));rnru_check('CSRF refusal has no quota or service call',rnru_kinds()===['actor','csrf']);
    rnru_reset();$denyActor=true;$_POST=['request_id'=>[]];rnru_refuse('Authority is checked before malformed posted fields',fn()=>registered_nameserver_requests_client_action('nameserver_request_create',['id'=>7]));rnru_check('Denied actor cannot trigger parsing or CSRF',rnru_kinds()===['actor']);
    foreach(['contact_id','contact_auth'] as $key) {rnru_reset();$_SESSION[$key]=null;rnru_refuse($key.' provenance rejects before identity normalization',fn()=>registered_nameserver_requests_client_action('nameserver_request_create',['id'=>7]));rnru_check($key.' performs zero authority/service calls',rnru_kinds()===[]);}
    rnru_reset();$support=true;rnru_refuse('Active support cannot use client request handler',fn()=>registered_nameserver_requests_client_action('nameserver_request_create',['id'=>7]));
    rnru_reset();$_GET['support']='';rnru_refuse('Support URL marker also refuses direct client handler',fn()=>registered_nameserver_requests_client_action('nameserver_request_create',['id'=>7]));
    rnru_reset();$_POST=$submitted;$_POST['confirmed']='';rnru_refuse('Absent explicit client confirmation prevents mutation',fn()=>registered_nameserver_requests_client_action('nameserver_request_create',['id'=>7]));rnru_check('Unconfirmed request is never created',!in_array('create',rnru_kinds(),true));
    rnru_reset();$_POST=['request_id'=>$id,'expected_event'=>$id,'event_id'=>$event,'confirmed'=>'1','note'=>'FORGED'];rnru_action(fn()=>registered_nameserver_requests_client_action('nameserver_request_cancel',['id'=>7]));rnru_check('Client cancellation forwards immutable head and empty note only',rnru_args('change')===[$id,$id,$event,'cancel','',7]);
    rnru_reset();$_POST=['request_id'=>$id,'expected_event'=>$id,'request_sha256'=>$hash,'dispatch_id'=>$dispatch,'approval_review_sha256'=>$hash,'reviewed'=>'1','current_password'=>' exact password ','old-ns'=>'FORGED'];$url=rnru_action(fn()=>registered_nameserver_requests_admin_action('provider_nameserver_request_approve'));
    rnru_check('Owner approval forwards exact origin and password without old-set override',rnru_args('approve')===[$id,$id,$hash,$dispatch,$hash,true,' exact password ']);
    rnru_check('Owner mutation orders actual actor and CSRF before service',rnru_kinds()===['actor','csrf','approve','flash']);
    rnru_check('Owner approval redirects to original client request detail',str_contains($url,'view=nameserver-requests')&&str_ends_with($url,'request_id='.$id));
    rnru_reset();$serviceError='Incorrect current password.';rnru_refuse('Owner password failure propagates without redirect or success flash',fn()=>registered_nameserver_requests_admin_action('provider_nameserver_request_approve'));rnru_check('Failed approval never flashes success',!in_array('flash',rnru_kinds(),true));
    rnru_reset();$badCsrf=true;rnru_refuse('Owner CSRF failure cannot enter approval',fn()=>registered_nameserver_requests_admin_action('provider_nameserver_request_approve'));rnru_check('Owner CSRF rejection leaves service untouched',rnru_kinds()===['actor','csrf']);
    rnru_reset();$_POST['reviewed']='';rnru_refuse('Owner unchecked approval cannot enter service',fn()=>registered_nameserver_requests_admin_action('provider_nameserver_request_approve'));rnru_check('Owner unreviewed submission has no approval call',!in_array('approve',rnru_kinds(),true));
    rnru_reset();$_POST=['request_id'=>$id,'expected_event'=>$id,'event_id'=>$event,'note'=>'Client reason','reviewed'=>'1','current_password'=>'pw'];rnru_action(fn()=>registered_nameserver_requests_admin_action('provider_nameserver_request_decline'));rnru_check('Owner decline passes dedicated command and original head',rnru_args('change')===[$id,$id,$event,'decline','Client reason',null,'pw']);
    rnru_reset();$_POST=['current_password'=>'pw'];rnru_action(fn()=>registered_nameserver_requests_admin_action('provider_nameserver_request_prepare'));rnru_check('Owner preparation invokes only its dedicated preparation API',rnru_kinds()===['actor','csrf','prepare','flash']&&rnru_args('prepare')===['pw']);
    rnru_reset();$_POST=[];$renderScreen=$screen;$html=rnru_render_controller(fn()=>registered_nameserver_requests_admin_render(''));
    rnru_check('Actual owner render passes full account to admin shell after actor admission',rnru_kinds()===['actor','owner_shell','screen','admin_head']&&rnru_args('admin_head')['name']==='Actual owner');
    rnru_reset();$_POST=$submitted;$renderScreen=$screen;$html=rnru_render_controller(fn()=>registered_nameserver_requests_client_render(['id'=>7],'Invalid nameserver list.'));
    rnru_check('Failed new intake reloads options rather than nonexistent request detail',rnru_args('screen')===[7,1,null]&&str_contains($html,'Invalid nameserver list.'));
    rnru_reset();$_POST=$declineRetry;$serviceError='Saved history unavailable.';$html=rnru_render_controller(fn()=>registered_nameserver_requests_admin_render('Password error.'));
    rnru_check('Failed history render performs one screen read and retains local review',count(array_filter(rnru_kinds(),fn($k)=>$k==='screen'))===1&&str_contains($html,'Client-visible &lt;reason&gt;')&&str_contains($html,'value="'.$event.'"'));
    rnru_check('History-error fallback cannot offer setup or active approval',!str_contains($html,'provider_nameserver_request_prepare')&&!str_contains($html,'data-nameserver-request-approve')&&str_contains($html,' disabled'));
    $portal=file_get_contents(CWP_CODE_ROOT.'/portal/controllers/router.php');$actions=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/actions.php');$accounts=file_get_contents(CWP_CODE_ROOT.'/app/staff/accounts.php');
    rnru_check('Integrated portal provenance guard runs before studio_client normalization',strpos($portal,'registered_nameserver_requests_portal_guard();')!==false&&strpos($portal,'registered_nameserver_requests_portal_guard();')<strpos($portal,'$client=studio_client();'));
    rnru_check('Integrated owner route retains original verify and recover prefix dispatch',str_contains($actions,"if(str_starts_with(\$action,'provider_nameserver_change_'))registered_nameserver_changes_admin_action(\$action);")&&str_contains($actions,"if(str_starts_with(\$action,'provider_nameserver_request_'))registered_nameserver_requests_admin_action(\$action);"));
    rnru_check('Integrated new owner view and actions retain staff-management gate',str_contains($accounts,"if(\$view==='nameserver-requests')return 'staff.manage';")&&str_contains($accounts,"if(str_starts_with(\$action,'provider_nameserver_request_'))return 'staff.manage';"));
    echo json_encode(['passed'=>true,'count'=>count($checks),'scope'=>'Actual new renderer and controller declarations with modeled service/session/CSRF boundaries; no SQL, HTTP, provider or installed acceptance.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) { echo json_encode(['passed'=>false,'count'=>count($checks),'error'=>get_class($e).': '.$e->getMessage(),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n"; exit(1); }
