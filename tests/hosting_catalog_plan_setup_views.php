<?php
/** Actual parsers/controller/rendering; modeled SQL, current authority and coordinator. No DB/HTTP. */
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site');
require CWP_CODE_ROOT.'/app/hosting/catalog_offer_setup_views.php';
require CWP_CODE_ROOT.'/includes/layout/admin.php';
$checks=[];$fragments=[];$calls=[];$model=[];
function hsv_load(string $file,string $name): void {
    $tokens=token_get_all(file_get_contents(CWP_CODE_ROOT.'/'.$file));$raw=null;
    for ($i=0;$i<count($tokens);$i++) {
        if (!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION) continue;
        $j=$i+1;while (is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)) $j++;
        if (!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||$tokens[$j][1]!==$name) continue;
        $raw='';$depth=0;$entered=false;
        for ($k=$i;$k<count($tokens);$k++) {$t=$tokens[$k];$raw.=is_array($t)?$t[1]:$t;if ($t==='{') {$depth++;$entered=true;} elseif ($t==='}'&&--$depth===0&&$entered) break;}
        break;
    }
    if ($raw===null) throw new LogicException('Missing actual function '.$name);
    $GLOBALS['fragments'][]=['file'=>$file,'function'=>$name,'sha256'=>hash('sha256',$raw)];eval($raw);
}
foreach (['hosting_catalog_admin_owner','hosting_catalog_admin_actions','hosting_catalog_admin_values','hosting_catalog_admin_page','hosting_catalog_admin_positive','hosting_catalog_admin_intent','hosting_catalog_admin_plan_matches','hosting_catalog_admin_data','hosting_catalog_admin_selected_plan'] as $name) hsv_load('app/hosting/catalog_offer_views.php',$name);
foreach (['hosting_catalog_admin_action','hosting_catalog_admin_retry','hosting_catalog_admin_fields','hosting_catalog_admin_confirmation','hosting_catalog_admin_preserved_submission','hosting_catalog_admin_pagination'] as $name) hsv_load('admin/controllers/hosting_catalog.php',$name);
// Actual selector predicate; readiness below models the existing canonical-only store.
hsv_load('app/providers/catalog_import_storage.php','provider_catalog_import_product_predicate');
set_error_handler(static function(int $level,string $message,string $file,int $line): never {throw new ErrorException($message,0,$level,$file,$line);});
function hsv_ok(string $name,bool $passed): void {$GLOBALS['checks'][]=['name'=>$name,'passed'=>$passed];if (!$passed) throw new RuntimeException($name);}
function hsv_bad(string $name,callable $call): void {try {$call();} catch (InvalidArgumentException|RuntimeException|LogicException $e) {if ($e instanceof PDOException) throw $e;hsv_ok($name,true);return;}hsv_ok($name,false);}
function hsv_render(callable $call): string {ob_start();try {$call();return ob_get_contents();}finally {ob_end_clean();}}
function esc(mixed $value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url(string $page='',array $query=[]): string {return '/studio/admin.php?'.http_build_query(['view'=>$page]+$query);}
function csrf_field(): string {return '<input type="hidden" name="csrf" value="synthetic-csrf">';}
function check_csrf(): void {$GLOBALS['calls'][]='csrf';if (!($GLOBALS['model']['csrf']??true)) throw new RuntimeException('Bad CSRF.');}
function service_plans_owner(bool $unused=true): array {$GLOBALS['calls'][]='plan-owner';if (($GLOBALS['model']['support']??false)||!($GLOBALS['model']['owner']??true)) throw new RuntimeException('Current owner required.');return ['id'=>1];}
function provider_catalog_import_owner(): array {$GLOBALS['calls'][]='import-owner';return ['id'=>1];}
function hosting_catalog_setup_preview(int $id): array {$GLOBALS['calls'][]=['setup-preview',$id];if (($GLOBALS['model']['preview_error']??false)==='pdo') throw new PDOException('PRIVATE_DATABASE_DIAGNOSTIC');if ($GLOBALS['model']['preview_error']??false) throw new RuntimeException('PRIVATE_INTERNAL_DIAGNOSTIC');if ($id!==7) throw new InvalidArgumentException('Unknown product.');return $GLOBALS['model']['preview'];}
function hosting_catalog_setup_create(string $plan,string $activation,string $mapping,int $product,string $review,array $details,array $configuration,int $days,string $password): array {
    $GLOBALS['calls'][]=['create',$plan,$activation,$mapping,$product,$review,$details,$configuration,$days,$password];
    if (!($GLOBALS['model']['owner_session_current']??true)) throw new RuntimeException('Current owner session revoked.');
    if ($GLOBALS['model']['create_error']??false) throw new RuntimeException('Saved review changed.');
    return ['product_id'=>$product,'plan_id'=>$plan,'mapping_id'=>$mapping];
}
function flash(string $message): void {$GLOBALS['calls'][]=['flash',$message];}
final class HsvRedirect extends Exception {}
function redirect(string $url): never {$GLOBALS['redirect']=$url;throw new HsvRedirect();}
function recurring_billing_locked(callable $call): mixed {$GLOBALS['calls'][]='lock';return $call();}
function hosting_catalog_ready(): bool {return true;}
function provider_catalog_import_ready(): bool {return true;}
function provider_catalog_variant_ready(): bool {return false;}
function catalog_ready(): bool {return true;}
function service_plans_require_ready(): void {}
function table(string $name): string {return '`'.$name.'`';}
function all(string $sql,array $params=[]): array {
    $GLOBALS['calls'][]=['all',$sql,$params];
    if (str_contains($sql,'`catalog_products`')) return [];
    if (str_contains($sql,'`service_plans`')) return $GLOBALS['model']['page_rows']??[];
    throw new LogicException('Unexpected modeled page query.');
}
function one(string $sql,array $params=[]): ?array {
    $GLOBALS['calls'][]=['one',$sql,$params];
    if (str_contains($sql,'`catalog_products`')) return $GLOBALS['model']['product'];
    if (str_contains($sql,'`hosting_catalog_publications`')) return null;
    if ($sql==='SELECT * FROM `service_plans` WHERE id=?') return $GLOBALS['model']['plans'][$params[0]]??null;
    throw new LogicException('Unexpected modeled exact query.');
}
function service_plans_validate(array $row): ServicePlanDocument {return ServicePlanDocument::fromJson($row['journal_json']);}
function hsv_post(): array {
    return ['action'=>'hosting_catalog_finish_plan','product_id'=>'7','plan_id'=>str_repeat('1',32),'activation_event_id'=>str_repeat('2',32),'mapping_id'=>str_repeat('3',32),'review_sha256'=>str_repeat('a',64),
        'inclusions'=>"Managed hosting\nOne website",'exclusions'=>'Domain registration','limits'=>"websites = 1\nsupport_requests = 4",'support'=>'Email support within two business days',
        'reseller_id'=>'18','package_label'=>'Owner-reviewed shared package','package_region'=>'UK','review_days'=>'7','limit_websites'=>'1','limit_disk_mb'=>'0','limit_bandwidth_mb'=>'','limit_mailboxes'=>'5','limit_databases'=>'1',
        'cost_known'=>'1','cost_amount'=>'12.5000','cost_currency'=>'GBP','cost_period'=>'1','cost_tax'=>'unknown','cost_basis'=>'shared-account','cost_reference'=>'Reviewed account statement',
        'reviewed_defaults'=>'1','confirm'=>'1','current_password'=>'owner-secret'];
}
try {
    $product=['id'=>7,'revision'=>2,'sku'=>'HOST-07','title'=>'Shared <Gold>','family'=>'hosting','status'=>'draft','term'=>'month','currency'=>'USD','amount_cents'=>2500,'setup_cents'=>0,'summary'=>'Owner description','features'=>'Private draft wording','terms'=>'Private notes','media_id'=>str_repeat('e',32)];
    $model=['product'=>$product,'preview'=>['product'=>$product,'package'=>['type'=>'901','label'=>'Observed <Shared>'],'review_sha256'=>str_repeat('a',64)],'plans'=>[]];
    $_POST=hsv_post();$_GET=[];$_SERVER['REQUEST_METHOD']='POST';
    $intent=hosting_catalog_admin_intent('hosting_catalog_finish_plan',$_POST);
    hsv_ok('Setup action is admitted without removing existing publication or request actions',in_array('hosting_catalog_finish_plan',hosting_catalog_admin_actions(),true)&&in_array('hosting_catalog_publish',hosting_catalog_admin_actions(),true)&&in_array('hosting_catalog_convert',hosting_catalog_admin_actions(),true));
    hsv_ok('Intent retains five exact identities and digest',$intent['product_id']===7&&$intent['plan_id']===$_POST['plan_id']&&$intent['activation_event_id']===$_POST['activation_event_id']&&$intent['mapping_id']===$_POST['mapping_id']&&$intent['review_sha256']===$_POST['review_sha256']);
    hsv_ok('Details contain only client service terms',array_keys($intent['details'])===['inclusions','exclusions','limits','support']&&$intent['details']['limits']===['support_requests'=>4,'websites'=>1]);
    hsv_ok('Provider configuration excludes imported type and profile',array_keys($intent['configuration'])===['reseller_id','label','region','limits','cost']&&$intent['configuration']['reseller_id']===18);
    hsv_ok('Blank provider limits stay unknown and zero stays zero',$intent['configuration']['limits']===['databases'=>1,'disk_mb'=>0,'mailboxes'=>5,'websites'=>1]);
    hsv_ok('Private cost uses exact decimal and reviewed period',$intent['configuration']['cost']['amount']==='12.5000'&&$intent['configuration']['cost']['period_months']===1);
    $unknown=$_POST;$unknown['cost_known']='0';hsv_ok('Explicit unknown cost does not submit leftover evidence',hosting_catalog_setup_intent($unknown)['configuration']['cost']===null);
    foreach (['confirm','reviewed_defaults'] as $field) {$bad=$_POST;unset($bad[$field]);hsv_bad('Explicit owner review required '.$field,fn()=>hosting_catalog_setup_intent($bad));}
    foreach (['price','currency','name','term','package_type','profile_id'] as $field) {$bad=$_POST;$bad[$field]='OVERRIDE';hsv_bad('Posted authoritative override refused '.$field,fn()=>hosting_catalog_setup_intent($bad));}
    foreach (['0','31','01'] as $days) {$bad=$_POST;$bad['review_days']=$days;hsv_bad('Review day bound refuses '.$days,fn()=>hosting_catalog_setup_intent($bad));}
    foreach (['websites = 1' . "\n" . 'websites = 2','broken','websites = 2147483648'] as $n=>$limits) {$bad=$_POST;$bad['limits']=$limits;hsv_bad('Service limits parse without ambiguous coercion '.$n,fn()=>hosting_catalog_setup_intent($bad));}
    $bad=$_POST;$bad['activation_event_id']=$bad['plan_id'];hsv_bad('Setup identities must be distinct',fn()=>hosting_catalog_setup_intent($bad));
    $bad=$_POST;$bad['support']=str_repeat('x',1001);hsv_bad('Overlong terms are rejected without truncation',fn()=>hosting_catalog_setup_intent($bad));
    $bad=$_POST;$bad['reseller_id']='18.0';hsv_bad('Provider account rejects numeric coercion',fn()=>hosting_catalog_setup_intent($bad));
    $bad=$_POST;foreach (['websites','disk_mb','bandwidth_mb','mailboxes','databases'] as $key) $bad['limit_'.$key]='';hsv_bad('At least one provider limit required',fn()=>hosting_catalog_setup_intent($bad));
    $values=hosting_catalog_setup_values($_POST);
    hsv_ok('Retry values exclude password and both confirmations',array_intersect_key($values,array_flip(['current_password','confirm','reviewed_defaults']))===[]&&!in_array('owner-secret',$values,true));
    $bad=$_POST;$bad['plan_id']=str_repeat('1',33);hsv_ok('Overlong identity is not truncated to a valid ID',hosting_catalog_setup_values($bad)['plan_id']==='');
    $calls=[];try {hosting_catalog_admin_action('hosting_catalog_finish_plan');}catch (HsvRedirect) {}
    hsv_ok('Actual controller checks CSRF and current owner before coordinator',array_slice($calls,0,3)===['csrf','plan-owner','import-owner']);
    $create=$calls[3];hsv_ok('Actual controller forwards complete exact service call',$create===['create',$intent['plan_id'],$intent['activation_event_id'],$intent['mapping_id'],7,$intent['review_sha256'],$intent['details'],$intent['configuration'],7,'owner-secret']);
    hsv_ok('Success redirect selects exact created product and plan',$GLOBALS['redirect']===admin_url('hosting-offers',['product_id'=>7,'plan_id'=>$intent['plan_id']]));
    unset($_POST['current_password']);$calls=[];try {hosting_catalog_admin_action('hosting_catalog_finish_plan');}catch (HsvRedirect) {}
    $emptyCreate=array_values(array_filter($calls,fn($x)=>is_array($x)&&$x[0]==='create'));
    hsv_ok('Signed-in owner submits without a password field and reaches same coordinator',count($emptyCreate)===1&&end($emptyCreate[0])==='');
    $model['csrf']=false;$calls=[];hsv_bad('CSRF refusal precedes coordinator',fn()=>hosting_catalog_admin_action('hosting_catalog_finish_plan'));hsv_ok('Bad CSRF performs no authority or mutation calls',$calls===['csrf']);$model['csrf']=true;
    foreach (['owner','support'] as $field) {$model[$field]=$field==='support';$calls=[];hsv_bad('Current authority refuses '.$field,fn()=>hosting_catalog_admin_action('hosting_catalog_finish_plan'));hsv_ok('Denied authority performs no setup write '.$field,!array_filter($calls,fn($x)=>is_array($x)&&$x[0]==='create'));unset($model[$field]);}
    $_SERVER['REQUEST_METHOD']='GET';$calls=[];hsv_bad('GET never creates a plan',fn()=>hosting_catalog_admin_action('hosting_catalog_finish_plan'));hsv_ok('GET refusal makes no helper calls',$calls===[]);$_SERVER['REQUEST_METHOD']='POST';
    $_POST['current_password']='wrong-password';$model['owner_session_current']=false;$calls=[];unset($GLOBALS['redirect']);hsv_bad('Coordinator revoked-session failure remains an error',fn()=>hosting_catalog_admin_action('hosting_catalog_finish_plan'));hsv_ok('Failure has no success flash or redirect',!isset($GLOBALS['redirect'])&&!array_filter($calls,fn($x)=>is_array($x)&&$x[0]==='flash'));
    $screen=hosting_catalog_setup_screen($product,$_POST);$html=hsv_render(fn()=>hosting_catalog_setup_form($screen));
    hsv_ok('Revoked-session failure preserves the complete safe submitted values',$screen['values']===hosting_catalog_setup_values($_POST)&&$screen['available']);
    hsv_ok('All stable hidden identities remain unchanged on retry',array_reduce(['product_id','plan_id','activation_event_id','mapping_id','review_sha256'],fn($ok,$key)=>$ok&&str_contains($html,'name="'.$key.'" value="'.$_POST[$key].'"'),true));
    hsv_ok('Repeated password input absent and consent never checked',!str_contains($html,'name="current_password"')&&!str_contains($html,'wrong-password')&&!str_contains($html,' checked'));
    hsv_ok('Actual form includes CSRF and separates publication',str_contains($html,'synthetic-csrf')&&str_contains($html,'preview and publish the offer separately')&&!str_contains($html,'name="action" value="hosting_catalog_publish"'));
    hsv_ok('No commercial override or provider identity input is rendered',!preg_match('/name="(?:name|title|price|currency|term|cadence|type|package_type|profile_id|profile_revision)"/',$html));
    hsv_ok('Commercial summary and observed package are escaped',str_contains($html,'Shared &lt;Gold&gt;')&&str_contains($html,'Observed &lt;Shared&gt;')&&str_contains($html,'25.00 USD per month')&&!str_contains($html,'Private draft wording'));
    $hostile=$_POST;$hostile['support']='</textarea><script>attack</script>';$hostile['cost_reference']='"><script>cost</script>';$hostile['cost_tax']='broken';
    $hostileHtml=hsv_render(fn()=>hosting_catalog_setup_form(hosting_catalog_setup_screen($product,$hostile)));
    hsv_ok('Service and private reference input escape injection',!str_contains($hostileHtml,'<script>')&&str_contains($hostileHtml,'&lt;script&gt;attack')&&str_contains($hostileHtml,'&quot;&gt;&lt;script&gt;cost'));
    hsv_ok('Invalid select retry remains visible rather than choosing another value',str_contains($hostileHtml,'value="broken" selected')&&str_contains($hostileHtml,'Previously entered: broken'));
    $fresh=hosting_catalog_setup_screen($product,[]);hsv_ok('Fresh review has new distinct IDs and current digest',count(array_unique(array_intersect_key($fresh['values'],array_flip(['plan_id','activation_event_id','mapping_id']))))===3&&$fresh['values']['review_sha256']===str_repeat('a',64));
    $model['preview']['review_sha256']=str_repeat('b',64);$stale=hosting_catalog_setup_screen($product,$_POST);$staleHtml=hsv_render(fn()=>hosting_catalog_setup_form($stale));
    hsv_ok('Stale retry preserves original digest and inputs and disables submission',!$stale['available']&&$stale['values']===$screen['values']&&str_contains($staleHtml,'name="review_sha256" value="'.str_repeat('a',64).'"')&&str_contains($staleHtml,'disabled>Create matching plan'));
    hsv_ok('Stale review offers an explicit fresh review link',str_contains($staleHtml,'Reload product review')&&str_contains($staleHtml,'These details changed or are unavailable'));
    $model['preview_error']=true;$unavailable=hosting_catalog_setup_screen($product,$_POST);$unavailableHtml=hsv_render(fn()=>hosting_catalog_setup_form($unavailable));
    hsv_ok('Unavailable setup preserves rejected form without exposing internal failure',!$unavailable['available']&&$unavailable['values']===$screen['values']&&!str_contains($unavailableHtml,'PRIVATE_INTERNAL_DIAGNOSTIC')&&str_contains($unavailableHtml,'Email support within two business days'));
    $fallback=hsv_render(fn()=>hosting_catalog_admin_preserved_submission());hsv_ok('Outer page failure also retains setup values and form',str_contains($fallback,'data-hosting-finish-plan')&&str_contains($fallback,'name="mapping_id" value="'.$_POST['mapping_id'].'"'));
    $empty=hosting_catalog_setup_screen(null,[]);hsv_ok('No selected product has no generated form identities',!$empty['available']&&$empty['values']['plan_id']===''&&hsv_render(fn()=>hosting_catalog_setup_form($empty))==='');
    unset($model['preview_error']);$model['preview']['review_sha256']=str_repeat('a',64);
    $terms=['name'=>$product['title'],'cadence'=>'monthly','price'=>2500,'currency'=>'USD',...$intent['details']];
    $plan=ServicePlanDocument::create($intent['plan_id'],1,1700000000,$terms)->append($intent['activation_event_id'],'activate',1,1700000001,[]);
    $model['plans'][$intent['plan_id']]=['journal_json'=>$plan->json()];
    $calls=[];$data=hosting_catalog_admin_data(['product_id'=>'7','plan_id'=>$intent['plan_id'],'plan_search'=>'different search','plan_page'=>'12']);
    hsv_ok('Exact new plan is selected independently of empty distant search page',$data['selected_plan_id']===$intent['plan_id']&&count($data['plans'])===1&&$data['plans'][0]['id']===$intent['plan_id']);
    hsv_ok('Selected-plan lookup binds exact ID without interpolating it',in_array(['one','SELECT * FROM `service_plans` WHERE id=?',[$intent['plan_id']]],$calls,true));
    $model['page_rows']=[['journal_json'=>$plan->json()]];$data=hosting_catalog_admin_data(['product_id'=>'7','plan_id'=>$intent['plan_id']]);hsv_ok('Already visible exact plan is not duplicated',count($data['plans'])===1);
    $changed=$product;$changed['amount_cents']=2600;hsv_ok('Changed product price does not select mismatched plan',hosting_catalog_admin_selected_plan($changed,$intent['plan_id'])===null);
    $retired=$plan->append(str_repeat('4',32),'retire',1,1700000002,[]);$model['plans'][$intent['plan_id']]=['journal_json'=>$retired->json()];hsv_ok('Retired exact plan cannot be selected',hosting_catalog_admin_selected_plan($product,$intent['plan_id'])===null);
    $model['page_rows']=[];$data=hosting_catalog_admin_data(['product_id'=>'7','plan_id'=>$intent['plan_id']]);hsv_ok('Unavailable match gives local notice and preserves page',$data['selected_plan_id']===''&&$data['plan_notice']!==''&&$data['product']['id']===7);
    $model['plans'][$intent['plan_id']]=['journal_json'=>$plan->json()];$data=hosting_catalog_admin_data(['product_id'=>'7','plan_id'=>$intent['plan_id']]);
    $_POST=[];$values=hosting_catalog_admin_values([]);$setup=hosting_catalog_setup_screen($product,[]);$preview=null;$previewError='';
    $page=hsv_render(function()use($data,$values,$setup,$preview,$previewError){require CWP_CODE_ROOT.'/admin/views/hosting_catalog.php';});
    hsv_ok('Actual offers view keeps original chooser and explicit preview action',str_contains($page,'data-hosting-plan-setup')&&str_contains($page,'name="action" value="hosting_catalog_preview"')&&str_contains($page,'value="'.$intent['plan_id'].'" selected'));
    hsv_ok('Creating a plan never renders an automatic publish submission',!str_contains($page,'name="action" value="hosting_catalog_publish"'));
    hsv_ok('Actual offers view renders the setup section once',substr_count($page,'id="finish-hosting-offer"')===1);
    $_POST=hsv_post();$model['preview_error']=true;$setup=hosting_catalog_setup_screen(null,$_POST);$data['ready']=false;
    $notReady=hsv_render(function()use($data,$values,$setup,$preview,$previewError){require CWP_CODE_ROOT.'/admin/views/hosting_catalog.php';});
    hsv_ok('Unprepared history page retains failed setup form alongside preparation',str_contains($notReady,'Enable hosting offers')&&str_contains($notReady,'name="mapping_id" value="'.$_POST['mapping_id'].'"')&&str_contains($notReady,'disabled>Create matching plan'));
    $data['ready']=true;$data['product']=null;
    $noProduct=hsv_render(function()use($data,$values,$setup,$preview,$previewError){require CWP_CODE_ROOT.'/admin/views/hosting_catalog.php';});
    hsv_ok('Missing selected product still retains rejected setup fields',str_contains($noProduct,'data-hosting-finish-plan')&&str_contains($noProduct,'Email support within two business days'));
    $model['preview_error']='pdo';$pdoPropagated=false;
    try {hosting_catalog_setup_screen(null,$_POST);} catch (PDOException) {$pdoPropagated=true;}
    hsv_ok('Read failure sentinel is a real PDO exception rather than ordinary unavailability',$pdoPropagated);
    $calls=[];$localFallback=hsv_render(fn()=>hosting_catalog_admin_preserved_submission());
    hsv_ok('Last-resort redisplay performs zero reads even while preview would throw PDO',$calls===[]&&str_contains($localFallback,'data-hosting-finish-plan')&&str_contains($localFallback,'disabled>Create matching plan'));
    hsv_ok('Read-failure fallback preserves identity and terms without password or diagnostic',str_contains($localFallback,'name="review_sha256" value="'.$_POST['review_sha256'].'"')&&str_contains($localFallback,'Email support within two business days')&&!str_contains($localFallback,'name="current_password"')&&!str_contains($localFallback,'PRIVATE_DATABASE_DIAGNOSTIC'));
    $result=['passed'=>true,'scope'=>'Actual source parsers, controller, page and HTML rendering with modeled current authority, SQL and setup coordinator; no database, HTTP, browser or provider execution.','checks'=>$checks,'count'=>count($checks),'source_fragments'=>$fragments];
} catch (Throwable $e) {$result=['passed'=>false,'scope'=>'Actual-source modeled UI test; no SQL or HTTP.','checks'=>$checks,'count'=>count($checks),'error'=>get_class($e).': '.$e->getMessage(),'line'=>$e->getLine(),'source_fragments'=>$fragments];}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($result['passed']?0:1);
