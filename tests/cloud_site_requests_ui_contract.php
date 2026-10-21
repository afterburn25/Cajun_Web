<?php
/** Actual new view/controller bodies; authority and service calls are modeled, no SQL/network. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../site');
set_error_handler(static function(int $n,string $m,string $f,int $l):never{throw new ErrorException($m,0,$n,$f,$l);});
require CWP_CODE_ROOT.'/includes/security/input.php';
$checks=[];$calls=[];$csrf=true;$authorized=true;$rate=true;$failure=null;
class CloudUiRedirect extends Exception {public function __construct(public string $url){parent::__construct('Synthetic redirect');}}
function csrf_field():string{return '<input type="hidden" name="csrf" value="synthetic-csrf">';}
function check_csrf():void{$GLOBALS['calls'][]=['csrf'];if(!$GLOBALS['csrf'])throw new RuntimeException('Synthetic CSRF refusal');}
function studio_url(array $p=[]):string{return '/subfolder/portal/index.php'.($p?'?'.http_build_query($p):'');}
function admin_url(string $view='dashboard',array $p=[]):string{return '/subfolder/admin/index.php?'.http_build_query(['view'=>$view]+$p);}
function flash(string $s):void{$GLOBALS['calls'][]=['flash',$s];}
function redirect(string $url):never{throw new CloudUiRedirect($url);}
function consume_rate(string $key,int $count,int $seconds):bool{$GLOBALS['calls'][]=['rate',$key,$count,$seconds];return $GLOBALS['rate'];}
function provider_cloud_site_requests_actor(?int $clientId,?string $password=null):array{$GLOBALS['calls'][]=['actor',$clientId,$password];if(!$GLOBALS['authorized'])throw new RuntimeException('Synthetic current session refusal');return ['kind'=>$clientId===null?'owner':'client'];}
function provider_cloud_site_requests_create(...$args):array{$GLOBALS['calls'][]=['create',...$args];if($GLOBALS['service_failure']??false)throw new RuntimeException('Synthetic input failure');return [];}
function provider_cloud_site_requests_change(...$args):array{$GLOBALS['calls'][]=['change',...$args];if($GLOBALS['service_failure']??false)throw new RuntimeException('Synthetic input failure');return [];}
function provider_cloud_site_requests_dispatch(...$args):object{$GLOBALS['calls'][]=['dispatch',...$args];if($GLOBALS['service_failure']??false)throw new RuntimeException('Synthetic input failure');return (object)['status'=>'uncertain'];}
function provider_cloud_site_requests_prepare(...$args):void{$GLOBALS['calls'][]=['prepare',...$args];}
function cu_check(string $name,bool $passed):void{$GLOBALS['checks'][]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new LogicException($name);}
function cu_deny(string $name,callable $fn):void{$denied=false;try{$fn();}catch(InvalidArgumentException|RuntimeException){$denied=true;}cu_check($name,$denied);}
function cu_html(callable $fn):string{ob_start();try{$fn();return (string)ob_get_contents();}finally{ob_end_clean();}}
function cu_call(callable $fn):string{try{$fn();}catch(CloudUiRedirect $r){return $r->url;}throw new LogicException('No redirect after successful action');}
function cu_reset(array $post):void{$_POST=$post;$GLOBALS['calls']=[];$GLOBALS['csrf']=true;$GLOBALS['authorized']=true;$GLOBALS['rate']=true;$GLOBALS['service_failure']=false;}
function cu_events(string $name):array{return array_values(array_filter($GLOBALS['calls'],static fn($c)=>$c[0]===$name));}

try{
    require CWP_CODE_ROOT.'/app/providers/cloud_site_requests_views.php';
    // Omit only require_once dependency lines; every actual controller function body is executed unchanged.
    foreach(['/admin/controllers/provider_cloud_site_requests.php','/portal/controllers/cloud_site_requests.php'] as $file){
        $source=file_get_contents(CWP_CODE_ROOT.$file);
        foreach(["require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests.php';\n","require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests_views.php';\n"] as $line){cu_check('Actual controller dependency line appears once '.$file.' '.sha1($line),substr_count($source,$line)===1);$source=str_replace($line,'',$source);}
        cu_check('Actual controller has canonical PHP opening '.$file,str_starts_with($source,"<?php\n"));
        eval(substr($source,5));
    }
    $id=str_repeat('a',32);$link=str_repeat('b',32);$event=str_repeat('c',32);$context=str_repeat('d',64);$grant=str_repeat('e',32);$dispatch=str_repeat('f',32);
    $option=['binding'=>['link_id'=>$link,'credential_sha256'=>'PRIVATE_CREDENTIAL','event_proof'=>'PRIVATE_PROOF','server_id'=>99],'context'=>$context,'label'=>'Cloud <server> & name'];
    $row=['id'=>$id,'domain'=>'example.invalid','aliases'=>['alias.invalid'],'server_id'=>99,'server_label'=>'Cloud <server>','created_at'=>'2026-10-18 20:00 UTC','stage'=>'pending','status'=>'pending','note'=>'','latest_event'=>$event,'can_cancel'=>true,'can_decline'=>true,'can_dispatch'=>true,'dispatch_context'=>$context,'grant_id'=>'PRIVATE_GRANT','package_id'=>null,'confirmation'=>null,'reason'=>''];
    $create=['action'=>'cloud_website_request_create','request_id'=>$id,'link_id'=>$link,'context'=>$context,'domain'=>'example.invalid','aliases'=>"alias.invalid\r\nsecond.invalid",'request_confirm'=>'1','client_id'=>'999'];
    $cancel=['action'=>'cloud_website_request_cancel','request_id'=>$id,'expected_event'=>$event,'event_id'=>$dispatch,'confirm'=>'1'];
    $send=['action'=>'provider_inventory_cloud_request_dispatch','request_id'=>$id,'expected_event'=>$event,'dispatch_id'=>$dispatch,'grant_id'=>$grant,'dispatch_context'=>$context,'reviewed'=>'1','current_password'=>'  Synthetic owner passphrase  '];
    $decline=['action'=>'provider_inventory_cloud_request_decline','request_id'=>$id,'expected_event'=>$event,'event_id'=>$dispatch,'confirm'=>'1','note'=>'Please contact the studio.','current_password'=>'owner-pass'];
    $html=cu_html(fn()=>provider_cloud_site_requests_render([$option],[$row],false,true));
    cu_check('Client current assignment displays exact domain form and opaque context',str_contains($html,'name="context" value="'.$context.'"')&&str_contains($html,'name="link_id" value="'.$link.'"')&&str_contains($html,'Submit for review'));
    cu_check('Client view does not serialize private assignment or grant data',!str_contains($html,'PRIVATE_')&&!str_contains($html,'credential_sha256')&&!str_contains($html,'event_proof'));
    cu_check('Client view escapes server text',str_contains($html,'Cloud &lt;server&gt; &amp; name')&&!str_contains($html,'<server>'));
    cu_check('Client has cancellation but no owner mutation forms',str_contains($html,'value="cloud_website_request_cancel"')&&!str_contains($html,'provider_inventory_cloud_request_')&&!str_contains($html,'type="password"'));
    cu_check('Client review consent is unchecked',!preg_match('/<input[^>]*\bchecked\b/',$html)&&str_contains($html,'name="request_confirm" value="1" required'));
    cu_check('Request anchor is stable and appears once',substr_count($html,'id="cloud-website-request-'.$id.'"')===1);
    cu_check('Form text states request is not payment or server purchase',str_contains($html,'does not purchase a server or take payment')&&str_contains($html,'The studio will check current server information, domain permission and capacity before creation.'));
    $retry=$create+['current_password'=>'PRIVATE_PASSWORD','credentials'=>'PRIVATE_CONFIG'];$retry['domain']='x" autofocus onfocus="evil';$retry['aliases']='</textarea><script>evil</script>';
    $saved=provider_cloud_site_requests_retry($retry);$html=cu_html(fn()=>provider_cloud_site_requests_render([$option],[],false,true,1,$saved));
    cu_check('Safe failed client request retains exact request ID and escaped input',str_contains($html,'name="request_id" value="'.$id.'"')&&str_contains($html,'x&quot; autofocus onfocus=&quot;evil')&&str_contains($html,'&lt;/textarea&gt;&lt;script&gt;evil&lt;/script&gt;'));
    cu_check('Failed client input cannot inject markup or passwords',!str_contains($html,'<script>')&&!str_contains($html,'PRIVATE_')&&!isset($saved['current_password'],$saved['credentials'],$saved['request_confirm']));
    $stale=$create;$stale['context']=str_repeat('0',64);$html=cu_html(fn()=>provider_cloud_site_requests_render([$option],[],false,true,1,$stale));
    cu_check('Stale request context suppresses replacement form',str_contains($html,'These details changed')&&!str_contains($html,'<form'));
    $html=cu_html(fn()=>provider_cloud_site_requests_render([],[],false,true,1,$create));
    cu_check('Lost assignment retains safe unsubmitted domains without fresh approval',str_contains($html,'data-cloud-website-unsaved')&&str_contains($html,'example.invalid')&&str_contains($html,'alias.invalid')&&!str_contains($html,'<form')&&str_contains($html,'Ask the studio to review your assigned server.')&&!str_contains($html,'refresh the cloud server list'));
    $invalid=$create;$invalid['request_id']='bad';$html=cu_html(fn()=>provider_cloud_site_requests_render([$option],[],false,true,1,$invalid));
    cu_check('Invalid retained request ID cannot silently become fresh request',!str_contains($html,'<form'));
    $html=cu_html(fn()=>provider_cloud_site_requests_render([],[$row],true,true,1,$send));
    cu_check('Owner dispatch reuses exact saved dispatch and grant IDs',str_contains($html,'name="dispatch_id" value="'.$dispatch.'"')&&str_contains($html,'name="grant_id" value="'.$grant.'"'));
    cu_check('Owner form shows explicit domain authority and capacity review',str_contains($html,'authority for every requested domain')&&str_contains($html,'server has capacity'));
    cu_check('Every owner password is blank and confirmation unchecked',substr_count($html,'type="password"')===substr_count($html,'type="password" name="current_password" value=""')&&!str_contains($html,'Synthetic owner passphrase')&&!preg_match('/<input[^>]*\bchecked\b/',$html));
    $old=$send;$old['expected_event']=str_repeat('0',32);$html=cu_html(fn()=>provider_cloud_site_requests_render([],[$row],true,true,1,$old));
    cu_check('Changed latest event suppresses owner mutation forms',str_contains($html,'These details changed')&&!str_contains($html,'<form'));
    $old=$send;$old['dispatch_context']=str_repeat('0',64);$html=cu_html(fn()=>provider_cloud_site_requests_render([],[$row],true,true,1,$old));
    cu_check('Changed owner dispatch context cannot rebase failed approval',!str_contains($html,'<form'));
    $retryDecline=$decline;$retryDecline['note']='<script>review</script>';$html=cu_html(fn()=>provider_cloud_site_requests_render([],[$row],true,true,1,$retryDecline));
    cu_check('Failed decline retains safe note and original event identity',str_contains($html,'&lt;script&gt;review&lt;/script&gt;')&&str_contains($html,'name="event_id" value="'.$dispatch.'"'));
    foreach(['failed'=>'Creation failed','uncertain'=>'Creation outcome is uncertain','claimed'=>'Creation outcome needs review','interrupted'=>'Creation outcome needs review','cancelled'=>'Cancelled','declined'=>'Declined'] as $state=>$copy){$closed=array_replace($row,['status'=>$state,'can_cancel'=>false,'can_decline'=>false,'can_dispatch'=>false]);$html=cu_html(fn()=>provider_cloud_site_requests_render([],[$closed],true,true));cu_check('Historical '.$state.' is honest and cannot resubmit',str_contains($html,$copy)&&!str_contains($html,'<form'));}
    $accepted=array_replace($row,['status'=>'accepted_pending','can_cancel'=>false,'can_decline'=>false,'can_dispatch'=>false,'package_id'=>'14701','grant_id'=>$grant]);$html=cu_html(fn()=>provider_cloud_site_requests_render([],[$accepted],true,true));
    cu_check('Accepted owner request links to independent confirmation',str_contains($html,'Returned package: 14701')&&str_contains($html,'cloud_website_confirm='.$grant.'#cloud-website-confirmation')&&!str_contains($html,'<form'));
    cu_check('Exact confirmation URL preserves subfolder and provider view',provider_cloud_site_requests_confirmation_url($grant)==='/subfolder/admin/index.php?view=provider-settings&cloud_website_confirm='.$grant.'#cloud-website-confirmation');
    foreach([null,'',strtoupper($grant),$grant.'0','https://external.invalid'] as $bad){$badRow=array_replace($accepted,['grant_id'=>$bad]);$html=cu_html(fn()=>provider_cloud_site_requests_render([],[$badRow],true,true));cu_check('Invalid or absent accepted grant has no guessed confirmation link '.json_encode($bad),!str_contains($html,'cloud_website_confirm=')&&!str_contains($html,'Review package confirmation'));}
    cu_deny('Confirmation URL helper rejects arbitrary grant text',fn()=>provider_cloud_site_requests_confirmation_url('../external'));
    $accepted['confirmation']=['assigned'=>true,'confirmed_at'=>'now','status'=>'PRIVATE_STATUS'];$html=cu_html(fn()=>provider_cloud_site_requests_render([],[$accepted],false,true));
    cu_check('Confirmed client projection links to hosting without leaking private grant package IDs',str_contains($html,'Allocated package observed and assigned.')&&str_contains($html,'services=1')&&!str_contains($html,'PRIVATE_')&&!str_contains($html,'14701'));
    $accepted['confirmation']['assigned']=false;$html=cu_html(fn()=>provider_cloud_site_requests_render([],[$accepted],false,true));
    cu_check('Changed assignment is historical and not a current hosting-service link',str_contains($html,'assignment has changed')&&!str_contains($html,'services=1'));
    $html=cu_html(fn()=>provider_cloud_site_requests_render([],[],false,false));cu_check('Unprepared client page has no mutation form',str_contains($html,'not available yet')&&!str_contains($html,'<form'));
    $html=cu_html(fn()=>provider_cloud_site_requests_render([],[],true,false));cu_check('Unprepared owner gets password-only explicit preparation',str_contains($html,'value="provider_inventory_cloud_request_prepare"')&&str_contains($html,'type="password"')&&!str_contains($html,'Create requested website'));
    $rows=[];for($i=0;$i<20;$i++)$rows[]=array_replace($row,['id'=>str_pad(dechex($i+1),32,'0',STR_PAD_LEFT),'can_cancel'=>false,'can_dispatch'=>false,'can_decline'=>false]);
    $html=cu_html(fn()=>provider_cloud_site_requests_render([],$rows,true,true,2));cu_check('Owner pagination preserves provider view and fixed history anchor',str_contains($html,'view=provider-settings&amp;cloud_websites_page=1#cloud-website-requests')&&str_contains($html,'cloud_websites_page=3#cloud-website-requests'));
    cu_check('Distinct history rows have unique anchors',preg_match_all('/id="cloud-website-request-([a-f0-9]{32})"/',$html,$ids)===20&&count(array_unique($ids[1]))===20);
    cu_check('Page parser rejects arrays and noncanonical page text',provider_cloud_site_requests_page(['cloud_websites_page'=>[]])===1&&provider_cloud_site_requests_page(['cloud_websites_page'=>' 2'])===1&&provider_cloud_site_requests_page(['cloud_websites_page'=>'2'])===2);
    cu_reset($create);$url=cu_call(fn()=>provider_cloud_site_requests_client_action($create['action'],['id'=>7]));
    cu_check('Actual client controller forwards exact domain and aliases with real client ID',cu_events('create')===[['create',$id,7,$link,$context,'example.invalid',['alias.invalid','second.invalid'],true]]);
    cu_check('Client success returns to fixed local request anchor',$url==='/subfolder/portal/index.php?cloud_websites=1#cloud-website-request-'.$id);
    cu_check('Client CSRF and current authority precede creation',array_column(array_slice($calls,0,3),0)===['csrf','actor','rate']&&cu_events('actor')===[['actor',7,null]]);
    cu_check('Client action rate limit is bounded by actual client',cu_events('rate')===[['rate','cloud-website-request-client-7',20,3600]]);
    foreach(['csrf','authorized','rate'] as $gate){cu_reset($create);$GLOBALS[$gate]=false;cu_deny('Client '.$gate.' denial cannot mutate',fn()=>provider_cloud_site_requests_client_action($create['action'],['id'=>7]));cu_check('No client mutation after '.$gate.' denial',cu_events('create')===[]&&cu_events('change')===[]);}
    foreach(['request_id'=>$id.'0','context'=>$context.'0','link_id'=>' '.$link,'domain'=>str_repeat('x',254),'aliases'=>[],'request_confirm'=>'11'] as $key=>$value){cu_reset(array_replace($create,[$key=>$value]));cu_deny('Raw client field refuses malformed or oversized '.$key,fn()=>provider_cloud_site_requests_client_action($create['action'],['id'=>7]));cu_check('Rejected '.$key.' does not reach service',cu_events('create')===[]);}
    cu_reset(array_replace($create,['domain'=>' example.invalid ']));cu_call(fn()=>provider_cloud_site_requests_client_action($create['action'],['id'=>7]));cu_check('Controller never trims submitted domain into approval',cu_events('create')[0][5]===' example.invalid ');
    cu_deny('Blank alias lines are not silently removed',fn()=>provider_cloud_site_requests_input_aliases(['aliases'=>"one.invalid\n\ntwo.invalid"]));
    cu_deny('More than20 aliases refused',fn()=>provider_cloud_site_requests_input_aliases(['aliases'=>implode("\n",array_fill(0,21,'one.invalid'))]));
    cu_reset($cancel);cu_call(fn()=>provider_cloud_site_requests_client_action($cancel['action'],['id'=>7]));cu_check('Cancellation binds original request event and actual client',cu_events('change')===[['change',$id,$event,$dispatch,'cancel','',7]]);
    cu_reset($send);$url=cu_call(fn()=>provider_cloud_site_requests_admin_action($send['action']));cu_check('Owner dispatch forwards exact captured approval and untrimmed password',cu_events('dispatch')===[['dispatch',$id,$event,$dispatch,$grant,$context,true,'  Synthetic owner passphrase  ']]);
    cu_check('Owner CSRF and actual password authority precede dispatch',array_column(array_slice($calls,0,2),0)===['csrf','actor']&&cu_events('actor')===[['actor',null,'  Synthetic owner passphrase  ']]);
    cu_check('Owner outcome redirects to fixed local history only',$url==='/subfolder/admin/index.php?view=provider-settings#cloud-website-requests');
    cu_check('Uncertain dispatch does not flash success or activation',str_contains(cu_events('flash')[0][1],'Review its outcome')&&!str_contains(cu_events('flash')[0][1],'created successfully'));
    foreach(['csrf','authorized'] as $gate){cu_reset($send);$GLOBALS[$gate]=false;cu_deny('Owner '.$gate.' refusal cannot dispatch',fn()=>provider_cloud_site_requests_admin_action($send['action']));cu_check('No owner dispatch after '.$gate.' refusal',cu_events('dispatch')===[]);}
    foreach(['reviewed'=>'','grant_id'=>$grant.'0','dispatch_id'=>[],'dispatch_context'=>' '.$context,'expected_event'=>'bad','current_password'=>str_repeat('x',201)] as $key=>$value){cu_reset(array_replace($send,[$key=>$value]));cu_deny('Owner rejects exact raw '.$key.' input',fn()=>provider_cloud_site_requests_admin_action($send['action']));cu_check('No dispatch for invalid '.$key,cu_events('dispatch')===[]);}
    cu_reset($send);$GLOBALS['service_failure']=true;cu_deny('Owner service failure remains visible instead of redirecting',fn()=>provider_cloud_site_requests_admin_action($send['action']));cu_check('Failed dispatch retains original POST and never flashes success',$_POST===$send&&cu_events('flash')===[]);
    cu_reset($decline);cu_call(fn()=>provider_cloud_site_requests_admin_action($decline['action']));cu_check('Decline passes exact event note and owner password',cu_events('change')===[['change',$id,$event,$dispatch,'decline','Please contact the studio.',null,'owner-pass']]);
    cu_reset(['current_password'=>'owner-pass']);cu_call(fn()=>provider_cloud_site_requests_admin_action('provider_inventory_cloud_request_prepare'));cu_check('Explicit preparation calls only own storage through owner authority',cu_events('prepare')===[['prepare','owner-pass']]&&cu_events('actor')===[['actor',null,'owner-pass']]&&cu_events('dispatch')===[]);
    cu_reset($send);cu_deny('Unrelated admin action never calls a mutation',fn()=>provider_cloud_site_requests_admin_action('provider_inventory_arbitrary'));cu_check('Unknown action leaves service untouched',cu_events('actor')===[]&&cu_events('dispatch')===[]);
    $cloudWebsiteUnavailable=true;ob_start();require CWP_CODE_ROOT.'/portal/views/cloud_site_requests.php';$html=ob_get_clean();cu_check('Actual portal view fails closed without requiring undefined ready data',str_contains($html,'temporarily unavailable')&&!str_contains($html,'<form'));
}catch(Throwable $error){$failure=['class'=>$error::class,'message'=>$error->getMessage(),'line'=>$error->getLine()];}
echo json_encode(['passed'=>$failure===null,'scope'=>'Actual new renderer and controller function bodies with modeled CSRF/session/authority/service/redirect seams. No SQL, provider, HTTP, browser or installed acceptance.','checks'=>$checks,'failure'=>$failure],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($failure===null?0:1);
