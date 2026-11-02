<?php
/** Actual portal forms/renderers with explicit in-memory authority/storage seams. No SQL or HTTP. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site');
require CWP_CODE_ROOT.'/app/hosting/catalog_checkout_document.php';
require CWP_CODE_ROOT.'/app/hosting/catalog_checkout_billing_document.php';
require CWP_CODE_ROOT.'/app/hosting/catalog_billing.php';
// The extracted recurring renderer uses this exact class alias in its source file.
class_alias(CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::class,'RecurringBillingTerms');
$sourceFragments=[];
/** Load only the named actual function, without booting the application or opening a DB. */
function hcv_load_function(string $path,string $name,bool $omitCheckoutInclude=false):void {
    global $sourceFragments;
    $tokens=token_get_all(file_get_contents($path));$count=count($tokens);$raw=null;
    for($i=0;$i<$count;$i++){
        if(!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION)continue;
        $j=$i+1;while($j<$count&&is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$j++;
        if(!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||$tokens[$j][1]!==$name)continue;
        $raw='';$depth=0;$entered=false;
        for($k=$i;$k<$count;$k++){$t=$tokens[$k];$raw.=is_array($t)?$t[1]:$t;if($t==='{'){$depth++;$entered=true;}elseif($t==='}'&&--$depth===0&&$entered)break;}
        break;
    }
    if($raw===null)throw new RuntimeException('Missing tested function '.$name);
    $executed=$raw;
    if($omitCheckoutInclude){$include="require_once CWP_CODE_ROOT.'/portal/controllers/hosting_checkout.php';";if(substr_count($raw,$include)!==1)throw new RuntimeException('Unexpected checkout include seam.');$executed=str_replace($include,'/* Exact checkout functions already loaded by this test. */',$raw);}
    $sourceFragments[]=['file'=>basename($path),'function'=>$name,'source_sha256'=>hash('sha256',$raw),'executed_sha256'=>hash('sha256',$executed),'redundant_include_omitted'=>$omitCheckoutInclude];
    eval($executed);
}
foreach([
    'portal/controllers/hosting_checkout.php'=>['hosting_checkout_portal_values','hosting_checkout_portal_project','hosting_checkout_portal_action','hosting_checkout_portal_selection','hosting_checkout_portal_review','hosting_checkout_portal_hidden'],
    'app/plans/forms.php'=>['service_plans_form_text','service_plans_form_number','service_plans_form_id','service_plans_form_hash','service_plans_form_confirm'],
    'app/billing/recurring_billing_forms.php'=>['recurring_ui_number','recurring_ui_hex','recurring_ui_query','recurring_ui_limits'],
    'includes/layout/hosting_catalog.php'=>['hosting_catalog_card'],
    'includes/layout/plans.php'=>['service_plans_cadences','service_plans_terms_view','service_plans_agreement_history'],
    'includes/layout/recurring_billing.php'=>['recurring_ui_money','recurring_ui_hidden','recurring_ui_input','recurring_ui_pages','recurring_ui_terms','recurring_ui_command_label','recurring_ui_invoice_card','recurring_ui_detail'],
    'app/hosting/catalog_checkout.php'=>['hosting_checkout_reject_contact','hosting_checkout_projection'],
    'portal/controllers/hosting_catalog.php'=>['hosting_catalog_portal_query','hosting_catalog_portal_action','hosting_catalog_portal_form'],
] as $file=>$names)foreach($names as $name)hcv_load_function(CWP_CODE_ROOT.'/'.$file,$name,$name==='hosting_catalog_portal_action');
// Reuse the existing pure commercial fixture, without executing its standalone suite.
hcv_load_function(dirname(CWP_CODE_ROOT).'/tests/hosting_checkout_document.php','co_fixture');
set_error_handler(static function(int $level,string $message,string $file,int $line):never{throw new ErrorException($message,0,$level,$file,$line);});

$checks=[];$calls=[];$model=[];$_SESSION=[];$_POST=[];$_GET=[];$_SERVER['REQUEST_METHOD']='POST';
function ok(string $n,bool $p):void{global $checks;$checks[]=['name'=>$n,'passed'=>$p];if(!$p)throw new RuntimeException($n);}
function bad(string $n,callable $f):void{try{$f();}catch(InvalidArgumentException|RuntimeException|LogicException){ok($n,true);return;}ok($n,false);}
function esc(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function gmoney(int $c):string{return number_format($c/100,2,'.',',');}
function studio_url(array $q=[]):string{return '/portal/index.php'.($q?'?'.http_build_query($q):'');}
function site_url(string $s):string{return '/'.$s;}
function csrf_field():string{return '<input type="hidden" name="csrf" value="SYNTHETIC-CSRF">';}
function posted(string $key,int $limit):string{return service_plans_form_text($_POST,$key,$limit);}
function studio_ready():bool{return true;}
function support_current():?array{return null;}
function studio_job(int $id,int $client):never{throw new LogicException('Unexpected legacy project fallthrough.');}
function report_error(Throwable $e):string{return 'MODELED-ERROR';}
function run_actions():string{$supportRequested=false;$supportId='';$client=['id'=>1];$jobId=0;$error='';require CWP_CODE_ROOT.'/portal/controllers/actions.php';return $error;}
function check_csrf():void{$GLOBALS['calls'][]='csrf';if(!($GLOBALS['model']['csrf']??true))throw new RuntimeException('Wrong CSRF.');}
function consume_rate(string $key,int $n,int $seconds):bool{$GLOBALS['calls'][]=['rate',$key,$n,$seconds];return $GLOBALS['model']['rate']??true;}
function hosting_checkout_primary_client(int $id):array{hosting_checkout_reject_contact();$GLOBALS['calls'][]='primary';if($id!==1||($GLOBALS['model']['support']??false))throw new RuntimeException('Primary authority refused.');return ['id'=>1];}
function hosting_checkout_locked(callable $f):mixed{hosting_checkout_reject_contact();return $f();}
function hosting_checkout_require():void{$GLOBALS['calls'][]='ready';}
function hosting_checkout_preview(string $id,string $pub,string $hash,int $client,array $project):array{hosting_checkout_primary_client($client);$GLOBALS['calls'][]=['preview',$id,$pub,$hash,$client,$project];return ['request_id'=>$id,'review_sha256'=>$GLOBALS['model']['review']->fingerprint()];}
function hosting_checkout_confirm(string $id,string $hash,int $client,bool $confirmed):array{hosting_checkout_primary_client($client);$GLOBALS['calls'][]=['confirm',$id,$hash,$client,$confirmed];return [];}
final class ProbeRedirect extends Exception{}
function redirect(string $url):never{$GLOBALS['redirect']=$url;throw new ProbeRedirect();}
function table(string $name):string{return '`'.$name.'`';}
function all(string $sql,array $args=[]):array{$GLOBALS['calls'][]=['all',$sql,$args];if(!str_contains($sql,'WHERE client_id=? AND LOCATE(?,title)>0')||$args[0]!==1)throw new LogicException('Wrong owned query.');return $GLOBALS['model']['projects'];}
function one(string $sql,array $args=[]):?array{$GLOBALS['calls'][]=['one',$sql,$args];if($args!==[$GLOBALS['model']['review']->data()['request_id'],1])return null;if(str_contains($sql,'`hosting_checkout_reviews`'))return ['publication_id'=>$GLOBALS['model']['pub']->data()['id']];if(str_contains($sql,'`hosting_checkout_results`'))return $GLOBALS['model']['result'];throw new LogicException('Unknown query.');}
function hosting_checkout_publication(string $id):HostingCatalogPublication{if($id!==$GLOBALS['model']['pub']->data()['id'])throw new RuntimeException('Unavailable publication.');return $GLOBALS['model']['pub'];}
function hosting_catalog_read_scope(array $ids):array{return [];}
function hosting_catalog_current_context(HostingCatalogPublication $pub,array $scope):array{return $GLOBALS['model']['context'];}
function hosting_checkout_review_row(array $row):HostingCheckoutReview{return $GLOBALS['model']['review'];}
function hosting_checkout_project_facts(array $p,int $client):array{return $p['mode']==='existing'?[$p['id'],$client]:[null,null];}
function hosting_checkout_result_row(array $row):array{return $GLOBALS['model']['validated_result'];}
function render_view(string $path,array $state):string{extract($state,EXTR_SKIP);ob_start();require $path;return ob_get_clean();}
function hcv_render(callable $render):string{ob_start();try{$render();return ob_get_contents();}finally{ob_end_clean();}}
function links(string $html):array{preg_match_all('/<a[^>]*href="([^"]+)"[^>]*>([^<]+)<\/a>/',$html,$m,PREG_SET_ORDER);$out=[];foreach($m as $r)$out[html_entity_decode($r[2])]=html_entity_decode($r[1]);return $out;}
try{
    $at=time();$f=co_fixture($at);$plan=$f['plan'];$r=HostingCheckoutReview::create(str_repeat('3',32),$f['pub'],1,['mode'=>'existing','id'=>1],$at,$f['c']);
    $model=['review'=>$r,'pub'=>$f['pub'],'context'=>$f['c'],'result'=>null,'projects'=>array_map(fn($i)=>['id'=>$i,'title'=>'Project <script>X</script> '.$i],range(1,21))];
    $post=['action'=>'hosting_catalog_checkout_preview','request_id'=>str_repeat('3',32),'publication_id'=>$f['pub']->data()['id'],'publication_sha256'=>$f['pub']->fingerprint(),'project_mode'=>'existing','project_id'=>'1','password'=>'PRIVATE_PASSWORD','client_id'=>'999','review_sha256'=>str_repeat('a',64)];
    $v=hosting_checkout_portal_values($post);ok('Only seven closed nonsecret strings retained',array_keys($v)===['action','request_id','publication_id','publication_sha256','review_sha256','project_mode','project_id']&&!in_array('PRIVATE_PASSWORD',$v,true));
    foreach([['bad'],str_repeat('a',33),"bad\0id","\xff"] as $badid){ok('Malformed redisplay identity is not interpreted as another ID',hosting_checkout_portal_values(['request_id'=>$badid])['request_id']==='');}
    ok('Existing project parsed without client-supplied owner',hosting_checkout_portal_project($post)===['mode'=>'existing','id'=>1]);
    ok('New project instruction excludes supplied existing ID',hosting_checkout_portal_project(['project_mode'=>'new-hosting','project_id'=>'999'])===['mode'=>'new-hosting']);
    bad('Unknown project mode refuses',fn()=>hosting_checkout_portal_project(['project_mode'=>'other']));bad('Leading-zero project refuses',fn()=>hosting_checkout_portal_project(['project_mode'=>'existing','project_id'=>'01']));
    $_POST=$post;$calls=[];hosting_checkout_portal_action($post['action'],['id'=>1]);
    ok('Preview starts with CSRF before rate or persistence',$calls[0]==='csrf');$last=end($calls);ok('Preview sends exact original identity/project and server client',$last===['preview',$post['request_id'],$post['publication_id'],$post['publication_sha256'],1,['mode'=>'existing','id'=>1]]);
    $_GET=['hosting'=>'checkout'];$calls=[];ok('Actual outer actions control flow accepts returning preview without legacy job fallthrough',run_actions()==='');ok('Delegated preview retained for checkout render',isset($GLOBALS['hosting_checkout_review']));
    $model['csrf']=false;$calls=[];bad('Bad CSRF prevents action',fn()=>hosting_checkout_portal_action($post['action'],['id'=>1]));ok('Bad CSRF performs no rate or service call',$calls===['csrf']);$model['csrf']=true;
    foreach(['contact_id','contact_auth'] as $key){$_SESSION=[$key=>'present'];$calls=[];bad('Contact provenance blocked '.$key,fn()=>hosting_checkout_portal_action($post['action'],['id'=>1]));ok('Contact refusal precedes rate and service '.$key,$calls===['csrf']);}$_SESSION=[];
    $model['support']=true;bad('Modeled downstream support authority refuses preview',fn()=>hosting_checkout_portal_action($post['action'],['id'=>1]));$model['support']=false;
    $_POST=['action'=>'hosting_catalog_checkout_confirm','request_id'=>$post['request_id'],'review_sha256'=>$r->fingerprint(),'confirm'=>'1','publication_id'=>'ignored','project_id'=>'999'];$calls=[];
    try{hosting_checkout_portal_action($_POST['action'],['id'=>1]);}catch(ProbeRedirect){}
    ok('Confirm sends only exact request/review/server client/consent',end($calls)===['confirm',$post['request_id'],$r->fingerprint(),1,true]);ok('Result redirect avoids receipt route collision',$GLOBALS['redirect']===studio_url(['hosting'=>'checkout','checkout_result'=>$post['request_id']]));
    $_POST['confirm']='0';bad('Explicit agreement required',fn()=>hosting_checkout_portal_action($_POST['action'],['id'=>1]));$_POST['confirm']='1';$model['rate']=false;bad('Rate refusal prevents confirmation',fn()=>hosting_checkout_portal_action($_POST['action'],['id'=>1]));$model['rate']=true;
    $calls=[];$saved=hosting_checkout_portal_review($post['request_id'],$r->fingerprint(),1);ok('Saved review exposes exact safe review hash',$saved['review']['review_sha256']===$r->fingerprint());ok('Primary authority checked before saved SQL reads',$calls[0]==='primary');
    $calls=[];bad('Wrong hash refuses rather than replacing displayed fingerprint',fn()=>hosting_checkout_portal_review($post['request_id'],str_repeat('0',64),1));ok('Wrong hash does not look up result or current offer',count(array_filter($calls,fn($x)=>is_array($x)&&$x[0]==='one'))===1);
    bad('Foreign client cannot read review',fn()=>hosting_checkout_portal_review($post['request_id'],$r->fingerprint(),2));
    $selection=hosting_checkout_portal_selection(['id'=>1],$v,['project_search'=>'<script>','p'=>'2']);ok('Project page returns20 plus has-more witness',count($selection['projects'])===20&&$selection['has_more']&&$selection['page']===2);$read=end($calls);ok('Owned project query retains bound search and closed offset',str_contains($read[1],'LIMIT 21 OFFSET 20')&&$read[2]===[1,'<script>']);
    $state=['result'=>null,'review'=>$saved['review'],'unavailable'=>false,'attempt'=>null,'data'=>[],'values'=>$v];
    $html=render_view(CWP_CODE_ROOT.'/portal/views/hosting_checkout.php',$state);
    ok('Review has exact hash, confirmation and CSRF',str_contains($html,'name="review_sha256" value="'.$r->fingerprint().'"')&&str_contains($html,'name="confirm" value="1" required')&&str_contains($html,'SYNTHETIC-CSRF'));
    ok('Private publication provenance never appears',!str_contains($html,'PRIVATE_')&&!str_contains($html,'mapping_json')&&!str_contains($html,'publisher'));
    ok('Review explicitly says unpaid invoice and verified-payment owner setup',str_contains($html,'creates an unpaid invoice')&&str_contains($html,'verified payment and owner approval'));
    $hostile=$state;$hostile['review']['offer']['title']='<img src=x onerror=alert(1)>';$hostileHtml=render_view(CWP_CODE_ROOT.'/portal/views/hosting_checkout.php',$hostile);
    ok('Hostile offer title is escaped by actual shared card',str_contains($hostileHtml,'&lt;img src=x onerror=alert(1)&gt;')&&!str_contains($hostileHtml,'<img src=x'));
    bad('Hidden field helper refuses private or undeclared field',fn()=>hosting_checkout_portal_hidden(['password'=>'PRIVATE_PASSWORD']));
    $state['review']=null;$state['data']=$selection;$html=render_view(CWP_CODE_ROOT.'/portal/views/hosting_checkout.php',$state);
    ok('Project title and search text escaped',!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'));
    ok('Selection keeps submitted request and publication hash',str_contains($html,'name="request_id" value="'.$post['request_id'].'"')&&str_contains($html,$post['publication_sha256']));
    $state['values']['project_id']='999';$html=render_view(CWP_CODE_ROOT.'/portal/views/hosting_checkout.php',$state);ok('Off-page submitted project retained disabled without choosing another',str_contains($html,'value="999" selected disabled>Previously selected project'));
    $state['unavailable']=true;$state['attempt']=hosting_checkout_portal_values(['action'=>'hosting_catalog_checkout_confirm','request_id'=>$post['request_id'],'review_sha256'=>str_repeat('0',64),'password'=>'PRIVATE_PASSWORD']);$html=render_view(CWP_CODE_ROOT.'/portal/views/hosting_checkout.php',$state);
    ok('Failed intent retains exact rejected fingerprint and disabled action',str_contains($html,str_repeat('0',64))&&str_contains($html,'disabled>Review required')&&!str_contains($html,'PRIVATE_PASSWORD'));
    $hostile=$state;$hostile['attempt']['request_id']='"><script>X</script>';$hostileHtml=render_view(CWP_CODE_ROOT.'/portal/views/hosting_checkout.php',$hostile);
    ok('Hostile original hidden retry value is escaped',str_contains($hostileHtml,'&quot;&gt;&lt;script&gt;X&lt;/script&gt;')&&!str_contains($hostileHtml,'<script>'));
    $result=['request_id'=>$post['request_id'],'project_id'=>1,'agreement_id'=>str_repeat('4',32),'schedule_id'=>str_repeat('7',32),'invoice_id'=>123,'invoice_reference'=>'INV-<script>','invoice_status'=>'issued','amount_cents'=>2500,'currency'=>'USD','accepted_at'=>$at,'replayed'=>true,'automatic_charge'=>false,'provider_activation'=>false];
    $model['result']=['id'=>$post['request_id']];$model['validated_result']=['document'=>$result,'invoice'=>['reference'=>$result['invoice_reference'],'status'=>'issued','amount_cents'=>2500,'currency'=>'USD']];$model['context']['publication']['enabled']=false;
    $completed=hosting_checkout_portal_review($post['request_id'],$r->fingerprint(),1);
    ok('Completed exact retry returns only safe retained result despite current withdrawal',$completed===['result'=>$result]);
    bad('Completed result still refuses a different submitted review hash',fn()=>hosting_checkout_portal_review($post['request_id'],str_repeat('0',64),1));
    $state['result']=$result;
    $html=render_view(CWP_CODE_ROOT.'/portal/views/hosting_checkout.php',$state);$targets=links($html);parse_str(parse_url($targets['Billing schedule'],PHP_URL_QUERY),$query);$parsed=recurring_ui_query($query,false);
    ok('Corrected actual link selects exact project and schedule',$parsed['job']===1&&$parsed['schedule']===$result['schedule_id']&&$query['recurring']==='1');
    ok('Other result links retain exact invoice/project/agreement',$targets['View invoice']==='/portal/index.php?invoice=123'&&$targets['View project']==='/portal/index.php?job=1'&&$targets['Service agreement']==='/portal/index.php?agreement='.$result['agreement_id']);
    ok('Result projection text escapes invoice reference',str_contains($html,'INV-&lt;script&gt;')&&!str_contains($html,'INV-<script>'));
    // Actual linked history renderers, with genuine pure documents and explicitly synthetic view data.
    $historyAt=(new DateTimeImmutable('2027-06-15T12:00:00Z'))->getTimestamp();$hf=co_fixture($historyAt);
    $hr=HostingCheckoutReview::create(str_repeat('3',32),$hf['pub'],1,['mode'=>'existing','id'=>1],$historyAt,$hf['c']);
    $hc=HostingCheckoutConsent::create($hr,$hr->fingerprint(),true,$hf['pub'],$hf['c'],1,1,1,$historyAt);
    $agreement=ServiceSubscriptionDocument::createCheckout(str_repeat('4',32),$hc,$hf['pub'],1,str_repeat('5',32),str_repeat('6',32));
    $terms=hosting_checkout_billing_terms(str_repeat('7',32),$agreement);
    $schedule=CajunWebPro\Foundation\RecurringBilling\RecurringBillingSchedule::createCheckout($terms,str_repeat('8',32));
    $agreementHtml=hcv_render(fn()=>service_plans_agreement_history($agreement));
    ok('Checkout agreement creation names the actual confirming client',str_contains($agreementHtml,'Confirmed 2027-06-15 12:00:00 UTC by Client #1.')&&!str_contains($agreementHtml,'by the studio'));
    ok('Checkout service consent is rendered as client confirmation',str_contains($agreementHtml,'<strong>Service terms confirmed at checkout</strong> · Client · 2027-06-15 12:00:00 UTC'));
    ok('Checkout activation is rendered as automatic rather than a studio action',str_contains($agreementHtml,'<strong>Agreement activated after confirmation</strong> · Automatic checkout · 2027-06-15 12:00:00 UTC')&&!str_contains($agreementHtml,' · Studio · '));
    ok('Checkout agreement history exposes no internal command or retained private source',!str_contains($agreementHtml,'checkout_activate')&&!str_contains($agreementHtml,'system:hosting-checkout')&&!str_contains($agreementHtml,'PRIVATE_')&&!str_contains($agreementHtml,'mapping_json'));
    $endedAgreement=$agreement->append(str_repeat('9',32),'cancel','client:1',$historyAt+10,['reason'=>'End <script>hosting</script>']);
    $endedAgreementHtml=hcv_render(fn()=>service_plans_agreement_history($endedAgreement));
    ok('Later client cancellation preserves checkout attribution and escapes its reason',str_contains($endedAgreementHtml,'UTC by Client #1.')&&str_contains($endedAgreementHtml,'<strong>Agreement ended</strong> · Client · ')&&str_contains($endedAgreementHtml,'End &lt;script&gt;hosting&lt;/script&gt;')&&!str_contains($endedAgreementHtml,'<script>'));
    $legacyAgreement=ServiceSubscriptionDocument::create(str_repeat('a',32),1,7,$historyAt,$hf['plan']);
    $legacyAgreement=$legacyAgreement->append(str_repeat('b',32),'consent','client:1',$historyAt,['terms_hash'=>$hf['plan']->model()->termsHash()])->append(str_repeat('c',32),'activate','staff:7',$historyAt,[]);
    $legacyAgreementHtml=hcv_render(fn()=>service_plans_agreement_history($legacyAgreement));
    $legacyExpected='<section class="workspace-card"><h2>Agreement history</h2><p>Offered 2027-06-15 12:00:00 UTC by the studio.</p><ol><li><strong>Service terms accepted</strong> · Client · 2027-06-15 12:00:00 UTC</li><li><strong>Agreement activated</strong> · Studio · 2027-06-15 12:00:00 UTC</li></ol><p class="hint">Accepting service terms does not authorize automatic payments. Review invoices and payment arrangements separately.</p></section>';
    ok('Legacy agreement history output remains byte exact',$legacyAgreementHtml===$legacyExpected);
    $historyView=['staff'=>false,'document'=>$schedule,'project'=>['id'=>1],'client'=>['id'=>1,'active'=>true],
        'source'=>['state'=>'active','plan'=>$hf['plan']],'assessment'=>$schedule->assessment(0,$historyAt),'selected_invoice'=>null,'current'=>false,
        'history'=>['items'=>$schedule->data()['events'],'page'=>0,'more'=>false],'invoices'=>['items'=>[],'page'=>0,'more'=>false]];
    $billingHtml=hcv_render(fn()=>recurring_ui_detail($historyView));
    ok('Actual checkout billing detail renders without warnings and names confirming client',str_contains($billingHtml,'Confirmed by Client #1 on 2027-06-15 12:00:00 UTC.')&&!str_contains($billingHtml,'Offered by Studio'));
    ok('Checkout billing event renders the client confirmation and original period',str_contains($billingHtml,'<strong>Billing terms confirmed at checkout</strong> · Client #1 · 2027-06-15 12:00:00 UTC')&&str_contains($billingHtml,'Effective 2027-06-15 (UTC).'));
    ok('Checkout billing history hides internal command and private retained source',!str_contains($billingHtml,'checkout_accept')&&!str_contains($billingHtml,'PRIVATE_')&&!str_contains($billingHtml,'checkout_origin'));
    $endedSchedule=$schedule->append(str_repeat('e',32),'end','client:1',$historyAt+10,['period_index'=>1,'reason'=>'End <script>billing</script>']);
    $endedView=$historyView;$endedView['document']=$endedSchedule;$endedView['source']['state']='cancelled';$endedView['assessment']=$endedSchedule->assessment(0,$historyAt+10);$endedView['history']['items']=$endedSchedule->data()['events'];
    $endedBillingHtml=hcv_render(fn()=>recurring_ui_detail($endedView));
    ok('Later ended billing preserves confirming creator and escapes the client reason',str_contains($endedBillingHtml,'Confirmed by Client #1 on 2027-06-15 12:00:00 UTC.')&&str_contains($endedBillingHtml,'End &lt;script&gt;billing&lt;/script&gt;')&&!str_contains($endedBillingHtml,'<script>'));
    $legacyData=$terms->data();unset($legacyData['checkout_origin']);$legacyData['format']=1;$legacyData['anchor']='2027-06-16';$legacyData['source_subscription']=['id'=>$legacyAgreement->data()['id'],'revision'=>2,'sha256'=>$legacyAgreement->fingerprint()];
    $legacyTerms=RecurringBillingTerms::fromArray($legacyData);
    $legacySchedule=CajunWebPro\Foundation\RecurringBilling\RecurringBillingSchedule::create($legacyTerms,7,$historyAt)
        ->append(str_repeat('f',32),'accept','client:1',$historyAt,['terms_sha256'=>$legacyTerms->fingerprint()])
        ->append(str_repeat('1',32),'activate','staff:7',$historyAt,['period_index'=>0]);
    $legacyView=$historyView;$legacyView['document']=$legacySchedule;$legacyView['assessment']=$legacySchedule->assessment(0,$historyAt);$legacyView['history']['items']=$legacySchedule->data()['events'];
    $legacyBillingHtml=hcv_render(fn()=>recurring_ui_detail($legacyView));
    $legacyHistory='<section class="workspace-card"><h2>Billing history</h2><p>Offered by Studio #7 on 2027-06-15 12:00:00 UTC.</p><ol><li><strong>Accept these billing terms</strong> · Client #1 · 2027-06-15 12:00:00 UTC</li><li><strong>Start billing</strong> · Studio #7 · 2027-06-15 12:00:00 UTC<p>Effective 2027-06-16 (UTC).</p></li></ol></section>';
    ok('Legacy billing creator and event history remain byte exact',str_contains($legacyBillingHtml,$legacyHistory));
    ok('Every legacy billing command label remains unchanged',array_map('recurring_ui_command_label',['accept','decline','withdraw','activate','pause','resume','end','issue'])===['Accept these billing terms','Decline these billing terms','Withdraw this offer','Start billing','Pause billing','Resume billing','End future billing','Issue this period invoice']);
    echo json_encode(['passed'=>true,'checks'=>$checks,'source_fragments'=>$sourceFragments,'scope'=>'Modeled render/form/policy, no browser/HTTP/database'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>['class'=>get_class($e),'message'=>$e->getMessage(),'line'=>$e->getLine()]],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";exit(1);}
