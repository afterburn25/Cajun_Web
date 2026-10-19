<?php
/** Actual purchase markup/controllers with modeled reader/session seams. No SQL, HTTP or provider I/O. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site');
require CWP_CODE_ROOT.'/app/hosting/checkout_purchases_views.php';
function hpv_load(string $path,string $name): void {
    $tokens=token_get_all(file_get_contents($path));$n=count($tokens);
    for($i=0;$i<$n;$i++) {
        if(!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION)continue;
        $j=$i+1;while($j<$n&&is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$j++;
        if(!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||$tokens[$j][1]!==$name)continue;
        $text='';$depth=0;$started=false;
        for($k=$i;$k<$n;$k++) {$t=$tokens[$k];$text.=is_array($t)?$t[1]:$t;if($t==='{'){$depth++;$started=true;}elseif($t==='}'&&--$depth===0&&$started)break;}
        eval($text);return;
    }
    throw new LogicException('Missing actual controller function '.$name);
}
hpv_load(CWP_CODE_ROOT.'/admin/controllers/hosting_purchases.php','hosting_purchases_admin_data');
foreach(['hosting_purchases_portal_access','hosting_purchases_portal_data'] as $fn)hpv_load(CWP_CODE_ROOT.'/portal/controllers/hosting_purchases.php',$fn);
hpv_load(CWP_CODE_ROOT.'/admin/controllers/payments.php','payments_admin_render');
hpv_load(CWP_CODE_ROOT.'/includes/layout/ledger.php','ledger_admin_render');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never {throw new ErrorException($message,0,$severity,$file,$line);});
$checks=[];$calls=[];$support=null;$user=['id'=>4,'role'=>'owner'];$readerFailure=false;$_SESSION=[];$_GET=[];$_POST=[];
function hpv_ok(string $name,bool $passed): void {global $checks;foreach($checks as $c)if($c['name']===$name)throw new LogicException('Duplicate check name.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function hpv_refuse(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException){hpv_ok($name,true);return;}hpv_ok($name,false);}
function esc(string $s): string {return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function gmoney(int $c): string {return number_format($c/100,2,'.',',');}
function admin_url(string $view='dashboard',array $q=[]): string {return '/subfolder/admin/index.php?'.http_build_query(['view'=>$view]+$q);}
function studio_url(array $q=[]): string {return '/subfolder/portal/index.php'.($q?'?'.http_build_query($q):'');}
function require_admin(): array {return $GLOBALS['user'];}
function support_current(): ?array {return $GLOBALS['support'];}
function staff_can(string $permission,array $user): bool {return true;}
function ledger_ready(): bool {return true;}
function admin_head(string $title,string $view,array $user): void {$GLOBALS['calls'][]=['admin-head',$view];}
function staff_locked(callable $fn): mixed {return $fn();}
final class HpvInvoiceReached extends Exception {}
function ledger_invoice(int $id): never {$GLOBALS['calls'][]=['ledger-invoice',$id];throw new HpvInvoiceReached();}
function hosting_purchases_owner_page(int $page=1): array {$GLOBALS['calls'][]=['owner',$page];if($GLOBALS['readerFailure'])throw new RuntimeException('PRIVATE_STORAGE_ERROR');return $GLOBALS['page'];}
function hosting_purchases_client_page(int $client,int $page=1): array {$GLOBALS['calls'][]=['client',$client,$page];if($GLOBALS['readerFailure'])throw new RuntimeException('PRIVATE_STORAGE_ERROR');return $GLOBALS['page'];}
function hpv_render(?array $page,bool $owner): string {ob_start();try{hosting_purchases_ui_render($page,$owner);return ob_get_contents();}finally{ob_end_clean();}}
function hpv_view(string $path,?array $page): string {ob_start();try{require $path;return ob_get_contents();}finally{ob_end_clean();}}
function hpv_links(string $html): array {preg_match_all('/<a\b[^>]*href="([^"]+)"[^>]*>([^<]+)<\/a>/',$html,$m,PREG_SET_ORDER);$out=[];foreach($m as $a)$out[html_entity_decode($a[2],ENT_QUOTES,'UTF-8')]=html_entity_decode($a[1],ENT_QUOTES,'UTF-8');return $out;}
try {
    $row=['request_id'=>str_repeat('a',32),'created_epoch'=>1760000000,'offer_title'=>'Saved <script>alert("offer")</script> & plan',
        'invoice'=>['id'=>7,'reference'=>'INV-7 <tag>','status'=>'issued','amount_cents'=>2500,'currency'=>'USD'],
        'project'=>['id'=>99,'title'=>'Original <project> & website'],'agreement'=>['id'=>str_repeat('b',32),'state'=>'active'],'order'=>null,
        'client'=>['id'=>42,'name'=>'Private client <name>'],'private_cost'=>'COST_SENTINEL','mapping_json'=>'MAPPING_SENTINEL','credentials'=>'SECRET_SENTINEL','url'=>'https://outside.invalid/'];
    $page=['items'=>[$row],'page'=>1,'has_more'=>false,'ready'=>true];
    hpv_ok('Default page is one',hosting_purchases_ui_page([])===1);
    hpv_ok('Exact maximum page accepted',hosting_purchases_ui_page(['p'=>'100000'])===100000);
    foreach(['0','01','100001','-1','1.0','1 ',' 1','1e2',1,['1']] as $i=>$bad)hpv_refuse('Noncanonical page rejected '.$i,fn()=>hosting_purchases_ui_page(['p'=>$bad]));
    $owner=hpv_render($page,true);$client=hpv_render($page,false);$ol=hpv_links($owner);$cl=hpv_links($client);
    hpv_ok('Owner uses exact agreement destination',$ol['Review setup']===admin_url('service-agreement',['record'=>$row['agreement']['id']]));
    hpv_ok('Old original invoice ID remains the exact owner link',$ol['View invoice']===admin_url('invoice-payments',['invoice_id'=>7]));
    parse_str(parse_url($ol['View invoice'],PHP_URL_QUERY),$_GET);$calls=[];$reached=false;
    ob_start();try{payments_admin_render($_GET['view'],$user,'');}catch(HpvInvoiceReached){$reached=true;}finally{ob_end_clean();}$_GET=[];
    hpv_ok('Generated invoice link reaches exact invoice through actual payments and ledger controllers',$reached&&$calls===[['admin-head','invoice-payments'],['ledger-invoice',7]]);
    hpv_ok('Owner exact original project link',$ol['View project']===admin_url('studio-job',['id'=>99]));
    hpv_ok('Client exact agreement invoice and project links',$cl===['View agreement'=>studio_url(['agreement'=>$row['agreement']['id']]),'View invoice'=>studio_url(['invoice'=>7]),'View project'=>studio_url(['job'=>99])]);
    hpv_ok('Saved labels are escaped as text',str_contains($owner,'Saved &lt;script&gt;alert(&quot;offer&quot;)&lt;/script&gt; &amp; plan')&&str_contains($owner,'INV-7 &lt;tag&gt;')&&str_contains($owner,'Original &lt;project&gt; &amp; website')&&!str_contains($owner,'<script>'));
    hpv_ok('Owner sees escaped client label',str_contains($owner,'Private client &lt;name&gt;'));
    hpv_ok('Client does not render owner client label',!str_contains($client,'Private client')&&!str_contains($client,'<dt>Client</dt>'));
    foreach(['COST_SENTINEL','MAPPING_SENTINEL','SECRET_SENTINEL','outside.invalid'] as $s)hpv_ok('Private or arbitrary field excluded '.$s,!str_contains($owner,$s)&&!str_contains($client,$s));
    hpv_ok('Read-only markup has no forms passwords or hidden input',preg_match('/<(?:form|input|textarea|select)\b/i',$owner.$client)===0);
    hpv_ok('Agreement and payment are separately labeled',str_contains($client,'<dt>Service agreement</dt><dd>Active agreement</dd>')&&str_contains($client,'USD · Awaiting payment'));
    $page['items'][0]['order']=['id'=>str_repeat('c',32),'phase'=>'offered','domain_name'=>null];$offered=hpv_render($page,false);
    hpv_ok('Offered client has existing website-address agreement link',hpv_links($offered)['Add website address']===studio_url(['agreement'=>$row['agreement']['id']]));
    hpv_ok('Owner offered row explains the client decision',str_contains(hpv_render($page,true),'The client can review this hosting offer and add their website address'));
    $page['items'][0]['order']=['id'=>str_repeat('c',32),'phase'=>'accepted','domain_name'=>'example.invalid<script>'];$accepted=hpv_render($page,false);
    hpv_ok('Accepted order links existing progress',hpv_links($accepted)['View hosting progress']===studio_url(['agreement'=>$row['agreement']['id']]));
    hpv_ok('Accepted is never presented as active hosting',str_contains($accepted,'Hosting request accepted')&&!str_contains($accepted,'Hosting is active')&&!str_contains($accepted,'Active hosting')&&str_contains($accepted,'does not mean the service is active'));
    hpv_ok('Domain is escaped without an arbitrary hyperlink',str_contains($accepted,'example.invalid&lt;script&gt;')&&!str_contains($accepted,'href="example'));
    foreach(['cancelled'=>'Hosting request cancelled','declined'=>'Hosting offer declined','withdrawn'=>'Hosting offer withdrawn'] as $phase=>$label){$page['items'][0]['order']['phase']=$phase;$html=hpv_render($page,false);hpv_ok('Closed setup phase stays visible '.$phase,str_contains($html,$label)&&!isset(hpv_links($html)['Add website address']));}
    $page['items'][0]['order']=null;$page['items'][0]['agreement']['state']='cancelled';$ended=hpv_render($page,true);
    hpv_ok('Ended agreement does not advertise new setup',str_contains($ended,'Ended agreement')&&str_contains($ended,'No hosting setup offered')&&!isset(hpv_links($ended)['Review setup'])&&!str_contains($ended,'offer hosting setup.'));
    $page['items'][0]['order']=['id'=>str_repeat('c',32),'phase'=>'offered','domain_name'=>null];
    hpv_ok('Inactive agreement does not promise address acceptance',!isset(hpv_links(hpv_render($page,false))['Add website address']));
    $empty=['items'=>[],'page'=>1,'has_more'=>false,'ready'=>true];$emptyHtml=hpv_render($empty,false);$unavailable=hpv_render(null,false);$notReady=hpv_render(array_replace($empty,['ready'=>false]),false);
    hpv_ok('Genuine empty history is distinct from unavailable',str_contains($emptyHtml,'No purchases on this page')&&!str_contains($unavailable,'No purchases')&&str_contains($unavailable,'role="alert"'));
    hpv_ok('Unprepared state does not claim missing or empty history',str_contains($notReady,'history is not ready')&&!str_contains($notReady,'No purchases'));
    $pagination=array_replace($empty,['page'=>2,'has_more'=>true]);$links=hpv_links(hpv_render($pagination,false));
    hpv_ok('Client paging stays on exact route',($links['Previous']??null)===studio_url(['hosting_purchases'=>1,'p'=>1])&&($links['Next']??null)===studio_url(['hosting_purchases'=>1,'p'=>3]));
    $links=hpv_links(hpv_render($pagination,true));hpv_ok('Owner paging stays on exact route',($links['Previous']??null)===admin_url('hosting-purchases',['p'=>1])&&($links['Next']??null)===admin_url('hosting-purchases',['p'=>3]));
    hpv_ok('Last bounded page never creates an invalid next link',!isset(hpv_links(hpv_render(array_replace($pagination,['page'=>100000]),false))['Next']));
    $invalid=$row;$invalid['agreement']['id']='https://outside.invalid';hpv_refuse('Malformed agreement cannot become a link',fn()=>hosting_purchases_ui_links($invalid,true));
    $invalid=$row;$invalid['invoice']['id']='7';hpv_refuse('Untyped invoice identity is not silently normalized',fn()=>hosting_purchases_ui_links($invalid,false));
    $page=$empty;$calls=[];$_POST=['client_id'=>'900','invoice_id'=>'901','action'=>'service_plans_order_offer'];
    hpv_ok('Owner wrapper returns exact native projection',hosting_purchases_admin_data(['p'=>'2','client_id'=>'900'])===$page&&$calls===[['owner',2]]);
    foreach(['administrator','support','editor'] as $role){$user['role']=$role;$calls=[];hpv_refuse('Non-owner wrapper refuses '.$role,fn()=>hosting_purchases_admin_data([]));hpv_ok('Non-owner makes no purchase read '.$role,$calls===[]);}$user['role']='owner';
    $support=['id'=>'support'];$calls=[];hpv_refuse('Owner support session refused',fn()=>hosting_purchases_admin_data([]));hpv_ok('Owner support refusal occurs before reader',$calls===[]);$support=null;
    $calls=[];hpv_refuse('Owner support query refused',fn()=>hosting_purchases_admin_data(['support'=>'']));hpv_ok('Owner support query makes no read',$calls===[]);
    $calls=[];hpv_ok('Client wrapper uses authenticated row not posted client',hosting_purchases_portal_data(['id'=>'42'],['hosting_purchases'=>'1','p'=>'3','client_id'=>'900'])===$page&&$calls===[['client',42,3]]);
    foreach(['contact_id','contact_auth'] as $key){$_SESSION=[$key=>null];$calls=[];hpv_refuse('Contact provenance refused '.$key,fn()=>hosting_purchases_portal_data(['id'=>42],['hosting_purchases'=>'1']));hpv_ok('Contact refused before purchase reader '.$key,$calls===[]);}$_SESSION=[];
    $_GET=['support'=>''];$calls=[];hpv_refuse('Client support URL refused',fn()=>hosting_purchases_portal_data(['id'=>42],['hosting_purchases'=>'1']));hpv_ok('Client support URL makes no read',$calls===[]);$_GET=[];
    $support=['id'=>'s'];hpv_refuse('Active client support refused',fn()=>hosting_purchases_portal_access());$support=null;
    foreach([['hosting_purchases'=>'2'],['hosting_purchases'=>['1']],['hosting_purchases'=>'1','p'=>'0']] as $i=>$query){$calls=[];hpv_refuse('Invalid client route refuses '.$i,fn()=>hosting_purchases_portal_data(['id'=>42],$query));hpv_ok('Invalid route performs no read '.$i,$calls===[]);}
    foreach([0,'042',['42'],true] as $i=>$id)hpv_refuse('Malformed authenticated client row refuses '.$i,fn()=>hosting_purchases_portal_data(['id'=>$id],['hosting_purchases'=>'1']));
    $readerFailure=true;hpv_refuse('Native reader failure is not converted to successful empty data',fn()=>hosting_purchases_portal_data(['id'=>42],['hosting_purchases'=>'1']));$readerFailure=false;
    $view=hpv_view(CWP_CODE_ROOT.'/portal/views/hosting_purchases.php',$empty);hpv_ok('Actual client view offers existing offers requests and purchase links',count(hpv_links($view))===3&&str_contains($view,'aria-current="page"'));
    $view=hpv_view(CWP_CODE_ROOT.'/admin/views/hosting_purchases.php',$empty);hpv_ok('Actual owner view links offers legacy requests and purchases',count(hpv_links($view))===3&&str_contains($view,'aria-current="page"'));
    $result=['invoice_id'=>7,'invoice_reference'=>'INV-7','amount_cents'=>2500,'currency'=>'USD','invoice_status'=>'issued','project_id'=>99,'agreement_id'=>str_repeat('b',32),'schedule_id'=>str_repeat('d',32)];
    ob_start();require CWP_CODE_ROOT.'/portal/views/hosting_checkout.php';$resultHtml=ob_get_clean();
    hpv_ok('Actual checkout result adds purchase history without removing four original links',count(hpv_links($resultHtml))===5&&(hpv_links($resultHtml)['My hosting purchases']??null)===studio_url(['hosting_purchases'=>1]));
    // Follow the actual inherited setup-card link through the real controller bodies.
    // Pure documents are real; the native context and later progress renderer are modeled.
    require CWP_CODE_ROOT.'/app/hosting/order_document.php';
    hpv_load(CWP_CODE_ROOT.'/includes/layout/service_orders.php','service_orders_ui');
    final class HpvOrderCardReached extends Exception {}
    final class HpvWrongInvoiceRoute extends Exception {}
    function service_orders_ready(): bool {return true;}
    function service_orders_context(string $agreement,int $client,bool $asClient=false): array {
        $GLOBALS['order_contexts'][]=[$agreement,$client,$asClient];
        return ['document'=>$GLOBALS['order_document'],'assessment'=>null,'warning'=>null];
    }
    function hosting_order_links_ui(ServiceOrderDocument $order,bool $owner,?array $assessment): never {throw new HpvOrderCardReached();}
    function payments_ready(): bool {return false;}
    function staff_ready(): bool {return false;}
    function admin_foot(): never {throw new HpvWrongInvoiceRoute();}
    $terms=['name'=>'Saved setup invoice','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['One website'],'exclusions'=>[],'limits'=>[],'support'=>'Studio support'];
    $plan=ServicePlanDocument::create(str_repeat('1',32),4,100,$terms)->append(str_repeat('2',32),'activate',4,101,[]);
    $agreement=ServiceSubscriptionDocument::create(str_repeat('3',32),42,4,102,$plan);
    $agreement=$agreement->append(str_repeat('4',32),'consent','client:42',103,['terms_hash'=>$plan->model()->termsHash()])->append(str_repeat('5',32),'activate','staff:4',104,[]);
    $order_document=ServiceOrderDocument::create(str_repeat('6',32),42,4,105,
        ['id'=>$agreement->data()['id'],'revision'=>$agreement->revision(),'sha256'=>$agreement->fingerprint(),'terms_hash'=>$plan->model()->termsHash()],
        ['id'=>7,'project_id'=>99,'sha256'=>str_repeat('a',64),'amount_cents'=>2500,'currency'=>'USD','basis'=>'initial-recurring-period','schedule_id'=>str_repeat('7',32),'period_index'=>0]);
    $order_document=$order_document->append(str_repeat('8',32),'accept','client:42',106,['domain_name'=>'setup.example','review_sha256'=>$order_document->fingerprint()]);
    $originalDocuments=[$agreement->json(),$order_document->json()];$order_contexts=[];$setupCards=[];
    foreach([true,false] as $asOwner) {
        $stopped=false;ob_start();
        try {service_orders_ui($agreement,$asOwner);} catch(HpvOrderCardReached) {$stopped=true;}
        finally {$setupCards[$asOwner?'owner':'client']=ob_get_clean();}
        if(!$stopped)throw new LogicException('Expected the actual setup card before later progress forms.');
    }
    $ownerSetup=hpv_links($setupCards['owner']);$clientSetup=hpv_links($setupCards['client']);
    parse_str(parse_url($ownerSetup['Review invoice'],PHP_URL_QUERY),$_GET);$calls=[];$reached=false;
    ob_start();try {payments_admin_render($_GET['view'],$user,'');}
    catch(HpvInvoiceReached) {$reached=true;} catch(HpvWrongInvoiceRoute) {}
    finally {ob_end_clean();}$_GET=[];
    hpv_ok('Actual owner setup Review invoice reaches its original selected invoice',$reached&&$calls===[['admin-head','invoice-payments'],['ledger-invoice',7]]);
    hpv_ok('Owner setup card retains the exact invoice-payments route and query',$ownerSetup['Review invoice']===admin_url('invoice-payments',['invoice_id'=>7]));
    hpv_ok('Client setup Review invoice retains its existing owned portal destination',$clientSetup['Review invoice']===studio_url(['invoice'=>7]));
    hpv_ok('Setup link rendering preserves exact documents and owning context',$originalDocuments===[$agreement->json(),$order_document->json()]&&$order_contexts===[[$agreement->data()['id'],42,false],[$agreement->data()['id'],42,true]]);
    echo json_encode(['passed'=>true,'count'=>count($checks),'checks'=>$checks,'scope'=>'Actual PHP renderer and extracted controller functions; modeled reader/session seams, no SQL or HTTP.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) {
    while(ob_get_level()>0)ob_end_clean();
    echo json_encode(['passed'=>false,'count'=>count($checks),'checks'=>$checks,'error'=>$e->getMessage()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit(1);
}
