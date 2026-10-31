<?php
/** Actual batch controller/view with modeled authority and import service; no SQL, provider or HTTP. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
require CWP_CODE_ROOT.'/includes/security/input.php';
require CWP_CODE_ROOT.'/includes/urls.php';
require CWP_CODE_ROOT.'/includes/layout/admin.php';
require CWP_CODE_ROOT.'/admin/views/catalog_import.php';
$GLOBALS['cwp_config']=['site_url'=>'https://studio.synthetic.invalid/subfolder'];
$checks=[];$trace=[];$calls=[];$flashes=[];$csrfAllowed=true;$ownerAllowed=true;$support=false;$batchFailure=false;$previewFailure=false;
$importsReady=true;$catalogReady=true;$inventoryReady=true;$profile=['id'=>'synthetic-profile','revision'=>3];$preview=[];$batchResult=[];
final class PcbvRedirect extends Exception {public function __construct(public string $destination){parent::__construct('Captured redirect.');}}
function pcbv_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
/** Extract only complete named production functions; engine errors are never treated as expected refusals. */
function pcbv_load(string $file,string $name):void {
    $source=file_get_contents($file);$needle='function '.$name.'(';if(substr_count($source,$needle)!==1)throw new LogicException('Production function boundary changed.');
    $tokens=token_get_all('<?php '.substr($source,strpos($source,$needle)));$code='';$depth=0;$opened=false;
    foreach($tokens as $token){
        if(is_array($token)&&$token[0]===T_OPEN_TAG)continue;
        $code.=is_array($token)?$token[1]:$token;
        if($token==='{'){$opened=true;$depth++;}
        elseif($token==='}'&&--$depth===0&&$opened){eval($code);return;}
    }
    throw new LogicException('Incomplete production function.');
}
function support_current():bool {global $support;return $support;}
function staff_can(string $permission,array $user):bool {return $permission==='staff.manage'&&($user['role']??'')==='owner';}
function check_csrf():void {global $trace,$csrfAllowed;$trace[]='csrf';if(!$csrfAllowed)throw new InvalidArgumentException('Synthetic CSRF refusal.');}
function csrf_field():string {return '<input type="hidden" name="csrf" value="synthetic-token">';}
function provider_catalog_import_owner():array {global $trace,$ownerAllowed,$support;$trace[]='owner';if(!$ownerAllowed||$support)throw new RuntimeException('Synthetic owner refusal.');return ['id'=>7,'role'=>'owner'];}
function provider_catalog_import_batch(string $hash,string $term,string $prefix,string $password,string $source,int $offset):array {
    global $trace,$calls,$batchFailure,$batchResult;$trace[]='batch';$calls[]=[$hash,$term,$prefix,$password,$source,$offset];
    if($batchFailure)throw new RuntimeException('Saved catalog changed. Reload and review it.');return $batchResult;
}
function flash(string $text,string $type='success'):void {global $trace,$flashes;$trace[]='flash';$flashes[]=[$text,$type];}
function redirect(string $destination):never {global $trace;$trace[]='redirect';throw new PcbvRedirect($destination);}
function provider_catalog_import_ready():bool {global $importsReady;return $importsReady;}
function provider_package_types_ready():bool {global $catalogReady;return $catalogReady;}
function provider_inventory_ready():bool {global $inventoryReady;return $inventoryReady;}
function provider_settings_profile():?array {global $profile;return $profile;}
function provider_catalog_import_preview(int $page,string $source):array {
    global $trace,$preview,$previewFailure;$trace[]=['preview',$page,$source];
    if($previewFailure)throw new RuntimeException('Unavailable <catalog> & private-shaped text.');return $preview;
}
function pcbv_action(array $post,bool $csrf=true,bool $owner=true,bool $fail=false,bool $asSupport=false):array {
    global $trace,$calls,$flashes,$csrfAllowed,$ownerAllowed,$batchFailure,$support;
    $trace=[];$calls=[];$flashes=[];$csrfAllowed=$csrf;$ownerAllowed=$owner;$batchFailure=$fail;$support=$asSupport;$_POST=$post;$_GET=[];
    $destination=null;$error=null;
    try{catalog_admin_action('catalog_import_batch',['id'=>7,'role'=>'owner']);}
    catch(PcbvRedirect $e){$destination=$e->destination;}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$error=$e->getMessage();}
    return ['trace'=>$trace,'calls'=>$calls,'flashes'=>$flashes,'destination'=>$destination,'error'=>$error];
}
function pcbv_render(array $query=[],array $post=[],string $role='owner',bool $asSupport=false):string {
    global $trace,$support,$ownerAllowed;$trace=[];$support=$asSupport;$ownerAllowed=true;$_GET=$query;$_POST=$post;
    ob_start();try{provider_catalog_import_admin_view(['id'=>7,'role'=>$role]);return ob_get_contents();}finally{ob_end_clean();}
}
/** Read actual rendered controls without requiring the optional DOM extension in minimal PHP. */
function pcbv_form(string $html,string $action):string {
    preg_match_all('~<form\b[^>]*>.*?</form>~s',$html,$forms);
    $found=array_values(array_filter($forms[0],static fn($form)=>str_contains($form,'name="action" value="'.esc($action).'"')));
    if(count($found)!==1)throw new LogicException('Expected one rendered action form.');return $found[0];
}
function pcbv_input(string $form,string $name):string {
    if(preg_match('~<input\b[^>]*\bname="'.preg_quote($name,'~').'"[^>]*\bvalue="([^"]*)"[^>]*>~s',$form,$m)!==1)throw new LogicException('Expected rendered input.');
    return html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
}
function pcbv_selected(string $form,string $name):string {
    if(preg_match('~<select\b[^>]*\bname="'.preg_quote($name,'~').'"[^>]*>(.*?)</select>~s',$form,$m)!==1)throw new LogicException('Expected rendered select.');
    if(preg_match('~<option\b[^>]*\bvalue="([^"]*)"\s+selected(?:\s|>)~s',$m[1],$selected)!==1)throw new LogicException('Expected selected option.');
    return html_entity_decode($selected[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
}
try{
    pcbv_load(CWP_CODE_ROOT.'/admin/controllers/catalog.php','catalog_admin_action');
    pcbv_load(CWP_CODE_ROOT.'/admin/controllers/studio.php','studio_hidden');
    pcbv_load(CWP_CODE_ROOT.'/app/marketing/growth.php','gint');
    pcbv_load(CWP_CODE_ROOT.'/app/billing/catalog.php','catalog_terms');
    $hash=str_repeat('a',64);$freshHash=str_repeat('b',64);$password='  synthetic private password  ';
    $post=['action'=>'catalog_import_batch','confirm'=>'1','preview_hash'=>$hash,'import_source'=>'package.types','import_offset'=>'50','term'=>'year','sku_prefix'=>'HOST','owner_password'=>$password];
    $batchResult=['created_count'=>7,'existing_count'=>3,'remaining_count'=>8,'next_offset'=>60];
    $r=pcbv_action($post);
    pcbv_ok('Actual action checks CSRF and owner before mutation',$r['trace']===['csrf','owner','batch','flash','redirect']);
    pcbv_ok('Exact review source offset term prefix and untrimmed password reach import',$r['calls']===[[$hash,'year','HOST',$password,'package.types',50]]);
    pcbv_ok('Redirect uses returned next offset rather than a guessed batch size',$r['destination']===admin_url('catalog',['import_source'=>'package.types','batch_term'=>'year','batch_prefix'=>'HOST','import_offset'=>60]));
    pcbv_ok('Result text uses returned created preserved and remaining counts',$r['flashes']===[['7 draft(s) created. 3 existing product(s) preserved. 8 type(s) remain to import.','success']]);
    pcbv_ok('Response location and flash never carry password or preview hash',!str_contains($r['destination'],$password)&&!str_contains($r['destination'],$hash)&&!str_contains(json_encode($r['flashes'],JSON_THROW_ON_ERROR),'private password'));
    $r=pcbv_action($post,csrf:false);
    pcbv_ok('Invalid CSRF prevents owner and batch calls',$r['trace']===['csrf']&&$r['calls']===[]&&$r['destination']===null&&$r['flashes']===[]);
    $r=pcbv_action($post,owner:false);
    pcbv_ok('Owner refusal prevents batch mutation',$r['trace']===['csrf','owner']&&$r['calls']===[]&&$r['destination']===null);
    $r=pcbv_action($post,asSupport:true);
    pcbv_ok('Support authority refusal prevents batch mutation',$r['trace']===['csrf','owner']&&$r['calls']===[]&&$r['destination']===null);
    foreach(['','0',[]] as $i=>$confirm){$bad=$post;$bad['confirm']=$confirm;$r=pcbv_action($bad);pcbv_ok('Explicit confirmation required before mutation '.$i,$r['calls']===[]&&$r['destination']===null&&$r['flashes']===[]&&$r['error']!==null);}
    $r=pcbv_action($post,fail:true);
    pcbv_ok('Import refusal does not fabricate success counts or redirect',$r['trace']===['csrf','owner','batch']&&$r['destination']===null&&$r['flashes']===[]&&$r['error']==='Saved catalog changed. Reload and review it.');
    pcbv_ok('Refused request retains exact safe form identity for rendering',$_POST===$post);
    foreach(['preview_hash','import_source','term','sku_prefix','owner_password','import_offset'] as $field){$bad=$post;$bad[$field]=['nested'];$r=pcbv_action($bad);pcbv_ok('Malformed '.$field.' cannot reach the import service',$r['calls']===[]&&$r['destination']===null&&$r['error']!==null);}
    foreach(['-1','8193','1.5'] as $offset){$bad=$post;$bad['import_offset']=$offset;$r=pcbv_action($bad);pcbv_ok('Invalid batch offset is refused '.$offset,$r['calls']===[]&&$r['destination']===null&&$r['error']!==null);}
    $batchResult=['created_count'=>0,'existing_count'=>2,'remaining_count'=>0,'next_offset'=>null];$saved=$post;$saved['import_source']='packages.list';$saved['import_offset']='8192';
    $r=pcbv_action($saved);
    pcbv_ok('Saved inventory source and upper valid offset remain exact',$r['calls']===[[$hash,'year','HOST',$password,'packages.list',8192]]);
    pcbv_ok('Completed batch redirects without an offset but retains choices',$r['destination']===admin_url('catalog',['import_source'=>'packages.list','batch_term'=>'year','batch_prefix'=>'HOST']));
    $missing=$post;unset($missing['import_source'],$missing['import_offset']);$r=pcbv_action($missing);
    pcbv_ok('Missing optional batch source and offset use direct catalog and zero',$r['calls']===[[$hash,'year','HOST',$password,'package.types',0]]);

    $preview=['observed_at'=>'2026-10-18 12:00:00','unnamed'=>0,'total_types'=>71,'hash'=>$freshHash,'has_more'=>true,'types'=>[
        ['name'=>'Safe <script>alert(1)</script> & type','type_ref'=>'12','product_id'=>null],
        ['name'=>'Saved product','type_ref'=>'13','product_id'=>7,'earlier_credentials'=>false],
    ]];
    $html=pcbv_render(['import_source'=>'package.types','batch_term'=>'month','batch_prefix'=>'NEXT','import_offset'=>'50']);$form=pcbv_form($html,'catalog_import_batch');
    pcbv_ok('Fresh batch form binds latest preview hash and selected source',pcbv_input($form,'preview_hash')===$freshHash&&pcbv_input($form,'import_source')==='package.types');
    pcbv_ok('Continuation URL retains batch choices in actual controls',pcbv_input($form,'import_offset')==='50'&&pcbv_input($form,'sku_prefix')==='NEXT'&&pcbv_selected($form,'term')==='month');
    pcbv_ok('Fresh batch requires confirmation and an empty current password',pcbv_input($form,'owner_password')===''&&str_contains($form,'name="confirm" value="1" >')&&str_contains($form,'autocomplete="current-password"'));
    pcbv_ok('Actual form includes CSRF and bounded batch action',pcbv_input($form,'csrf')==='synthetic-token'&&pcbv_input($form,'action')==='catalog_import_batch');
    pcbv_ok('Returned provider text is escaped in the actual renderer',str_contains($html,'Safe &lt;script&gt;alert(1)&lt;/script&gt; &amp; type')&&!str_contains($html,'<script>'));
    pcbv_ok('Batch scope states drafts across pages and preservation of earlier edits',str_contains($html,'batches of up to 50 types across all catalog pages')&&str_contains($html,'Already imported products keep their names, prices, terms and images.'));
    pcbv_ok('View distinguishes local images from unavailable provider artwork and VPS import',str_contains($html,'Images are not copied from 20i.')&&str_contains($html,'Upload or replace each product’s image')&&str_contains($html,'Automatic 20i artwork and VPS catalog import are not available'));
    pcbv_ok('View leaves price and feature entry with the owner',str_contains($html,'Prices and features stay blank until you enter them.')&&str_contains($html,'Prices and provider images are not imported.'));
    pcbv_ok('Existing draft links remain escaped local subfolder URLs',str_contains($html,esc(admin_url('catalog',['id'=>7])))&&str_contains($html,esc(admin_url('hosting-offers'))));

    $retry=$post;$retry['import_source']='packages.list';$retry['sku_prefix']='OLD"><img src=x onerror=alert(1)>';$retry['import_offset']='100';$retry['term']='year';
    $html=pcbv_render(['import_source'=>'package.types','batch_term'=>'month','batch_prefix'=>'NEW','import_offset'=>'0'],$retry);$form=pcbv_form($html,'catalog_import_batch');
    pcbv_ok('Failed batch source wins over changed query choice',pcbv_input($form,'import_source')==='packages.list'&&in_array(['preview',1,'packages.list'],$trace,true));
    pcbv_ok('Failed batch hash is retained instead of silently rebased',pcbv_input($form,'preview_hash')===$hash&&!str_contains($form,$freshHash));
    pcbv_ok('Failed offset term and prefix remain exact',pcbv_input($form,'import_offset')==='100'&&pcbv_selected($form,'term')==='year'&&pcbv_input($form,'sku_prefix')===$retry['sku_prefix']);
    pcbv_ok('Failed prefix is escaped and cannot create an element',str_contains($form,'OLD&quot;&gt;&lt;img')&&!str_contains($html,'<img src=x'));
    pcbv_ok('Failed password is blank in every rendered form',!str_contains($html,$password)&&!preg_match('~name="owner_password"[^>]*value="[^"]+"~',$html));
    pcbv_ok('Failed explicit confirmation is retained',str_contains($form,'name="confirm" value="1" checked'));
    $retry['confirm']='0';$retry['preview_hash']='OLD"><svg onload=alert(1)>';$retry['import_offset']='bad"><svg onload=alert(2)>';
    $html=pcbv_render([],$retry);$form=pcbv_form($html,'catalog_import_batch');
    pcbv_ok('Invalid retained identity fields remain escaped for correction',pcbv_input($form,'preview_hash')===$retry['preview_hash']&&pcbv_input($form,'import_offset')===$retry['import_offset']&&!str_contains($html,'<svg'));
    pcbv_ok('Unconfirmed retry does not become confirmed',str_contains($form,'name="confirm" value="1" >'));
    foreach(['administrator','client'] as $role){$html=pcbv_render([],[],$role);pcbv_ok('Non-owner sees no import controls '.$role,$html===''&&$trace===[]);}
    $html=pcbv_render([],[],asSupport:true);pcbv_ok('Resolved support sees no import controls',$html===''&&$trace===[]);
    $html=pcbv_render(['support'=>''],[]);pcbv_ok('Even unresolved support query suppresses import controls',$html===''&&$trace===[]);
    $html=pcbv_render(['import_source'=>'foreign<script>']);pcbv_ok('Unknown source cannot create batch controls or leak raw input',!str_contains($html,'catalog_import_batch')&&!str_contains($html,'foreign<script>')&&!array_filter($trace,'is_array'));
    $previewFailure=true;$html=pcbv_render(['import_source'=>'package.types']);
    pcbv_ok('Unavailable preview creates no actionable batch and escapes its error',!str_contains($html,'catalog_import_batch')&&str_contains($html,'Unavailable &lt;catalog&gt; &amp; private-shaped text.'));$previewFailure=false;
    $preview['total_types']=0;$preview['types']=[];$html=pcbv_render(['import_source'=>'package.types']);
    pcbv_ok('Empty observed catalog has no batch form',!str_contains($html,'catalog_import_batch')&&str_contains($html,'No named hosting types'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected batch form/action failure: '.$e->getMessage(),'passed'=>false];}
$passed=!array_filter($checks,static fn($row)=>$row['passed']!==true);
echo json_encode(['passed'=>$passed,'scope'=>'Actual catalog import view and extracted controller action with modeled CSRF, authority, saved preview and batch service. Real form/URL/input helpers. No SQL, provider, HTTP, browser or importer mutation acceptance.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
