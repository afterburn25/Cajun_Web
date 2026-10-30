<?php
/** Actual pure renderer and policy parser. No database, HTTP, provider or browser execution. */
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',__DIR__.'/../site');
define('CWP_VERSION','synthetic-render');
$GLOBALS['cwp_config']=['site_url'=>'/nested'];
require CWP_CODE_ROOT.'/includes/urls.php';
require CWP_CODE_ROOT.'/app/hosting/catalog_offers_document.php';
require CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php';
set_error_handler(static function(int $level,string $message,string $file,int $line):never {throw new ErrorException($message,0,$level,$file,$line);});
function esc(string $value):string {return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function gmoney(int $cents):string {return number_format($cents/100,2,'.',',');}
function db():never {throw new LogicException('Renderer must not open the database.');}
function query(mixed ...$args):never {throw new LogicException('Renderer must not query.');}
function all(mixed ...$args):never {throw new LogicException('Renderer must not query.');}
function one(mixed ...$args):never {throw new LogicException('Renderer must not query.');}
$checks=[];
function hc_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function hc_render(array $page):string {ob_start();try {hosting_catalog_comparison($page);return ob_get_contents();}finally{ob_end_clean();}}
function hc_refuse(string $name,array $page):void {try{hc_render($page);}catch(InvalidArgumentException){hc_ok($name,true);return;}hc_ok($name,false);}
function hc_offer(int $id=1,bool $checkout=true):array {
    $offer=['publication_id'=>sprintf('%032x',$id),'publication_sha256'=>hash('sha256','offer-'.$id),'product_id'=>$id,'product_revision'=>1,'sku'=>'PLAN-'.$id,
        'title'=>'Studio '.$id,'summary'=>'Hosting for a focused website.','currency'=>'USD','amount_cents'=>1900,'cadence'=>'monthly',
        'image_id'=>str_repeat('a',32),'inclusions'=>['Website hosting','Daily backup review'],'exclusions'=>['Website redesign'],
        'limits'=>['websites'=>1,'mailboxes'=>0],'support'=>'Email support during published business hours.'];
    if($checkout)$offer['checkout_policy']=hosting_checkout_policy(['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,'timezone'=>'America/Chicago','alignment'=>'day-of-month','review_ttl_seconds'=>900]);
    return $offer;
}
function hc_page(array $offers,bool $unavailable=false,bool $more=false):array {return ['offers'=>$offers,'unavailable'=>$unavailable,'has_more'=>$more];}

// Synthetic browser fixture only. No application bootstrap, account data or server is used.
if (($argv[1]??null)==='--render') {
    $count=$argv[2]??'';
    if (!in_array($count,['2','3','4'],true)||count($argv)!==3) throw new InvalidArgumentException('Choose two, three or four synthetic plans.');
    $offers=[];
    for($i=1;$i<=(int)$count;$i++) {
        $offer=hc_offer($i);$offer['title']=['Launch','Business','Studio','Scale'][$i-1];
        $offer['amount_cents']=[900,1900,2900,4900][$i-1];$offer['limits']['websites']=$i;$offers[]=$offer;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Synthetic hosting comparison</title>';
    foreach(['site.css','premium.css','growth.css','hosting-comparison.css'] as $css) echo '<link rel="stylesheet" href="'.esc(asset($css)).'">';
    echo '</head><body class="cwp-site is-inner"><main><section class="section hosting-comparison-section" id="hosting-plans"><div class="container"><span class="eyebrow">YOUR WEBSITE, YOUR CHOICE</span><h2>Clear plans. Side by side.</h2>';
    hosting_catalog_comparison(hc_page($offers));echo '</div></section></main></body></html>';exit;
}

try {
    $offer=hc_offer();$page=hc_page([$offer]);$before=serialize($page);$html=hc_render($page);
    hc_ok('Rendering preserves the supplied current page',serialize($page)===$before);
    hc_ok('One offer creates one native comparison table',substr_count($html,'<table ')===1);
    hc_ok('Table has a visible caption and semantic column headers',str_contains($html,'<caption id="hosting-compare-1-caption">Shared hosting plan comparison</caption>')&&substr_count($html,'scope="col"')===2);
    hc_ok('Comparison is a labelled keyboard-focusable scroll region',str_contains($html,'role="region" aria-labelledby="hosting-compare-1-caption" aria-describedby="hosting-compare-1-help" tabindex="0"'));
    hc_ok('Price is exact cents and original monthly period',str_contains($html,'>$19.00</strong>')&&str_contains($html,'USD per month'));
    hc_ok('Scope support and limits have row headers',str_contains($html,'scope="row">Included')&&str_contains($html,'scope="row">Not included')&&str_contains($html,'scope="row">Support')&&str_contains($html,'scope="row">Websites'));
    hc_ok('Supplied inclusions exclusions and support are rendered',str_contains($html,'Daily backup review')&&str_contains($html,'Website redesign')&&str_contains($html,$offer['support']));
    hc_ok('Zero limits remain zero',str_contains($html,'scope="row">Mailboxes</th><td>0</td>'));
    hc_ok('Public image keeps the established browser selector',str_contains($html,'class="hosting-catalog-image hosting-comparison-image"'));
    hc_ok('Product image is local media with decorative alt and dimensions',str_contains($html,'src="/nested/media/index.php?id='.str_repeat('a',32).'" alt="" width="240" height="144"'));
    preg_match_all('/href="([^"]+)"/',$html,$links);$checkout=[];
    foreach($links[1] as $link){$url=html_entity_decode($link,ENT_QUOTES,'UTF-8');parse_str(parse_url($url,PHP_URL_QUERY)??'',$query);if(($query['hosting']??null)==='checkout')$checkout[]=$query;}
    hc_ok('Per-offer checkout link carries only exact publication and hash',$checkout===[['hosting'=>'checkout','offer'=>$offer['publication_id'],'offer_sha256'=>$offer['publication_sha256']]]);
    hc_ok('Checkout link has a unique descriptive accessible name',str_contains($html,'aria-label="Review Studio 1 and first invoice"'));
    hc_ok('Checkout explains unpaid invoice and separate activation',str_contains($html,'first unpaid invoice')&&str_contains($html,'verified payment and owner approval')&&str_contains($html,'America/Chicago'));
    hc_ok('Comparison introduces no form or automatic action',!str_contains($html,'<form')&&!str_contains($html,'<script'));
    $annual=hc_offer(2);$annual['cadence']='annually';$annual['amount_cents']=19900;$annual['limits']=['websites'=>3,'disk_mb'=>1024];$annual['image_id']=null;
    $pair=hc_render(hc_page([$offer,$annual]));
    hc_ok('Mixed cadences retain full prices without a monthly conversion',str_contains($pair,'>$199.00</strong>')&&str_contains($pair,'USD per year')&&!str_contains($pair,'$16.58'));
    hc_ok('Different named limits align with an explicit missing value',str_contains($pair,'scope="row">Disk mb</th><td><span class="hosting-comparison-muted">Not specified</span></td><td>1024</td>'));
    hc_ok('Absent image introduces no invented or remote artwork',substr_count($pair,'<img ')===1);
    hc_ok('Missing limits are not presented as unlimited',str_contains($pair,'A missing limit is not an unlimited allowance.'));
    $legacy=hc_offer(3,false);$legacyHtml=hc_render(hc_page([$legacy]));
    hc_ok('Request-only offer keeps its existing portal path',str_contains($legacyHtml,'/nested/portal/index.php?hosting=offers')&&!str_contains($legacyHtml,'hosting=checkout'));
    hc_ok('Request-only offer does not promise an immediate invoice',str_contains($legacyHtml,"We\u{2019}ll review it with you")&&!str_contains($legacyHtml,'first unpaid invoice'));
    $monthEnd=$offer;$monthEnd['checkout_policy']['alignment']='month-end';
    hc_ok('Month-end confirmation boundary is visible',str_contains(hc_render(hc_page([$monthEnd])),'Confirmation is available on the last day of a month.'));
    $none=$legacy;$none['inclusions']=[];$none['exclusions']=[];$none['limits']=[];
    $noneHtml=hc_render(hc_page([$none]));
    hc_ok('Empty scope arrays show honest unspecified content',substr_count($noneHtml,'No additional items listed.')===2&&str_contains($noneHtml,'No separately metered limits listed.'));
    foreach([2,4,5,20] as $count){
        $offers=[];for($i=1;$i<=$count;$i++)$offers[]=hc_offer($i);$rendered=hc_render(hc_page($offers));
        hc_ok($count.' offers remain present in groups of at most four',substr_count($rendered,'<table ')===(int)ceil($count/4)&&substr_count($rendered,'>Choose this plan</a>')===$count);
        preg_match_all('/id="([^"]+)"/',$rendered,$ids);
        hc_ok($count.' offers produce unique caption and help IDs',count($ids[1])===count(array_unique($ids[1])));
    }
    $more=hc_render(hc_page([$offer],false,true));
    hc_ok('Remaining offers link to the existing second portal page',str_contains($more,'hosting=offers&amp;p=2'));
    $empty=hc_render(hc_page([]));$unavailable=hc_render(hc_page([],true));$partial=hc_render(hc_page([$offer],true));
    hc_ok('Unprepared empty state offers consultation without a fake plan',str_contains($empty,'No shared hosting plans are published yet.')&&!str_contains($empty,'<table')&&str_contains($empty,'page=consultation'));
    hc_ok('Unavailable state is distinct and contains no internal error',str_contains($unavailable,'We cannot show hosting plans right now.')&&!str_contains($unavailable,'<table'));
    hc_ok('A partial page keeps its valid offer and visible warning',str_contains($partial,'Some hosting plans are temporarily unavailable.')&&str_contains($partial,'Choose this plan'));
    $hostile=$offer;$hostile['title']='Plan <script>alert("x")</script>';$hostile['summary']='<img src=x onerror=alert(1)>';$hostile['support']="Support <svg/onload=alert(1)>\nNext line";$hostile['inclusions']=['<b>Not HTML</b>'];$hostile['exclusions']=['<iframe src=x>'];$hostile['limits']=['<script>'=>0];
    $hostile['plan_json']='PRIVATE-PLAN';$hostile['mapping_json']='PRIVATE-MAPPING';$hostile['credential']='PRIVATE-CREDENTIAL';$hostile['cost_cents']=12345;$hostile['provider_ref']='PRIVATE-REF';
    $safe=hc_render(hc_page([$hostile]));
    hc_ok('Hostile title summary and link label are escaped',!str_contains($safe,'<script>')&&!str_contains($safe,'<img src=x')&&str_contains($safe,'&lt;script&gt;'));
    hc_ok('Scope support and limit labels are escaped',!str_contains($safe,'<svg/')&&!str_contains($safe,'<iframe')&&!str_contains($safe,'<b>Not HTML')&&str_contains($safe,'&lt;b&gt;Not HTML'));
    hc_ok('Private fields are never projected',!str_contains($safe,'PRIVATE-')&&!str_contains($safe,'12345'));
    hc_ok('Public body contains no provider administration links',!str_contains($safe,'admin/')&&!str_contains($safe,'api.20i.com'));
    hc_refuse('More than the supplied twenty-offer page refuses',hc_page(array_fill(0,21,$offer)));
    hc_refuse('Associative offer collections refuse',hc_page(['foreign'=>$offer]));
    hc_refuse('Nonboolean availability refuses',['offers'=>[],'unavailable'=>1,'has_more'=>false]);
    foreach(['publication_id'=>'bad','publication_sha256'=>str_repeat('a',63),'image_id'=>'https://example.invalid/a.png','currency'=>'EUR','cadence'=>'weekly','amount_cents'=>0,'limits'=>['disk_mb'=>-1],'inclusions'=>[new stdClass()],'checkout_policy'=>null] as $field=>$value){$bad=$offer;$bad[$field]=$value;hc_refuse('Invalid '.$field.' refuses before rendering',hc_page([$bad]));}
    $badPolicy=$offer;$badPolicy['checkout_policy']['requires_verified_payment']=false;hc_refuse('Payment policy cannot be weakened by presentation input',hc_page([$badPolicy]));
    ob_start();hosting_catalog_card($offer,'h2');$card=ob_get_clean();
    hc_ok('Original portal and history card API still renders',str_contains($card,'<h2>Studio 1</h2>')&&str_contains($card,'<details><summary>Plan terms</summary>'));
    $report=['passed'=>true,'checks'=>$checks,'count'=>count($checks),'scope'=>'Actual renderer and pure policy validation; Actual asset and URL helpers; escaping/money helpers modeled. No SQL, HTTP, browser or visual acceptance.','source_sha256'=>hash_file('sha256',CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php')];
    echo json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $error){echo json_encode(['passed'=>false,'checks'=>$checks,'error'=>get_class($error).': '.$error->getMessage()],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";exit(1);}
