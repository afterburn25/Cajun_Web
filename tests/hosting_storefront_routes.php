<?php
/** Public category route/render contract. No database, browser, payment or provider work. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
$checks=[];$heads=[];
function hsr_check(string $name,bool $passed):void {$GLOBALS['checks'][]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function esc(mixed $value):string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function page_url(string $page=''):string {return '/index.php'.($page===''?'':'?page='.rawurlencode($page));}
function site_url(string $path=''):string {return '/'.ltrim($path,'/');}
function icon(string $name):string {return '<span data-icon="'.esc($name).'"></span>';}
function site_head(string $title,string $active='home',string $description=''):void {$GLOBALS['heads'][]=[$title,$active,$description];echo '<main data-active="'.esc($active).'">';}
function site_foot(bool $cta=true):void {echo '</main>';}
function hsr_render(string $path):string {ob_start();try{require CWP_CODE_ROOT.'/'.$path;return (string)ob_get_contents();}finally{ob_end_clean();}}
try {
    $hub=hsr_render('pages/services/hosting-overview.php');
    hsr_check('Hosting overview is its own public page',($heads[0]??null)[1]==='hosting'&&str_contains($hub,'Choose the right home'));
    hsr_check('Overview exposes separate Shared and VPS destinations',str_contains($hub,page_url('shared-hosting'))&&str_contains($hub,page_url('vps-hosting')));
    hsr_check('Overview keeps payment and setup as separate verified steps',str_contains($hub,'price, payment and setup as separately verified steps'));

    $vps=hsr_render('pages/services/vps-hosting.php');
    hsr_check('VPS route is explicit consultation-only availability',($heads[1]??null)[1]==='vps-hosting'&&str_contains($vps,'Consultation only — no online VPS checkout.'));
    hsr_check('VPS refuses Shared substitution and paid provisioning claims',str_contains($vps,'will not substitute a Shared Hosting package')&&str_contains($vps,'does not take payment, reserve infrastructure or authorize provider provisioning'));
    hsr_check('VPS page contains no checkout or payment form',!str_contains($vps,'<form')&&!str_contains($vps,'hosting=checkout')&&!str_contains($vps,'name="payment'));
    hsr_check('VPS offers consultation and a separate Shared comparison',str_contains($vps,page_url('consultation'))&&str_contains($vps,page_url('shared-hosting')));

    $controller=(string)file_get_contents(CWP_CODE_ROOT.'/pages/controller.php');
    hsr_check('Front controller allowlists all three hosting routes',str_contains($controller,"'hosting','shared-hosting','vps-hosting'")&&str_contains($controller,"\$page==='hosting'")&&str_contains($controller,"\$page==='shared-hosting'")&&str_contains($controller,"\$page==='vps-hosting'"));
    hsr_check('Front controller routes each category to a distinct source page',str_contains($controller,"pages/services/hosting-overview.php")&&str_contains($controller,"pages/services/hosting.php")&&str_contains($controller,"pages/services/vps-hosting.php"));
    $shared=(string)file_get_contents(CWP_CODE_ROOT.'/pages/services/hosting.php');
    hsr_check('Shared page reads only the Shared category',str_contains($shared,"hosting_catalog_public(\$hostingBrowse['page'],\$hostingBrowse['billing'],'shared-hosting')")&&str_contains($shared,"site_head('Shared hosting','shared-hosting'"));

    $layout=(string)file_get_contents(CWP_CODE_ROOT.'/includes/layout/public.php');
    hsr_check('Public navigation uses native details with Overview Shared and VPS links',str_contains($layout,'<details class="nav-dropdown')&&str_contains($layout,"page_url('hosting')")&&str_contains($layout,"page_url('shared-hosting')")&&str_contains($layout,"page_url('vps-hosting')"));
    $css=(string)file_get_contents(CWP_CODE_ROOT.'/assets/css/premium.css');
    hsr_check('Closed native hosting menu is hidden without JavaScript',str_contains($css,'.cwp-site .nav-dropdown:not([open])>div {display:none;}'));
    $storefront=(string)file_get_contents(CWP_CODE_ROOT.'/app/hosting/storefront_categories.php');
    $offers=(string)file_get_contents(CWP_CODE_ROOT.'/app/hosting/catalog_offers_context.php');
    hsr_check('Storefront readiness produces only a private entrypoint-local read scope',str_contains($storefront,'final class HostingStorefrontReadScope')&&str_contains($storefront,'private function __construct()')&&str_contains($storefront,'public static function open(): ?self'));
    hsr_check('Product context requires a validated storefront scope instead of rescanning history',str_contains($storefront,'function hosting_storefront_product_context(int $product,HostingStorefrontReadScope $scope): array')&&!str_contains(substr($storefront,strpos($storefront,'function hosting_storefront_product_context'),strpos($storefront,'function hosting_storefront_link_publication')-strpos($storefront,'function hosting_storefront_product_context')),'hosting_storefront_ready()'));
    hsr_check('Catalog read scope carries the validated storefront proof across one offer page',str_contains($offers,'private static function scope(array $productIds,HostingStorefrontReadScope $storefront): array')&&str_contains($offers,"'imports'=>\$imports,'storefront'=>\$storefront")&&str_contains($offers,'self::scope($productIds,$storefront)'));
    hsr_check('Offer composition refuses a forged scope and reuses the validated proof',str_contains($offers,'if (!$storefrontScope instanceof HostingStorefrontReadScope)')&&str_contains($offers,'hosting_storefront_product_context($productId,$storefrontScope)'));
    echo json_encode(['passed'=>true,'scope'=>'Actual public hub/VPS rendering and exact route/navigation source; no SQL, browser, payment, provider, fulfillment or deployment acceptance.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    echo json_encode(['passed'=>false,'scope'=>'Actual public hub/VPS rendering and exact route/navigation source; no SQL, browser, payment, provider, fulfillment or deployment acceptance.','failure'=>$error->getMessage(),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
