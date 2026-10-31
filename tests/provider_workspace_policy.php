<?php
/** Pure workspace routing and bound local-catalog query tests. No IO or database. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/app/providers/workspace.php';require __DIR__.'/../site/app/billing/catalog_browse.php';
function admin_url(string $v,array $p=[]):string{return '/admin/index.php?'.http_build_query(['view'=>$v]+$p);}
function catalog_families():array{return ['hosting'=>'Hosting','web-design'=>'Web design','software'=>'Software','addon'=>'Additional service','maintenance'=>'Maintenance','domain'=>'Domain','vps'=>'VPS'];}
$checks=[];
function pwc(string $name,bool $pass):void{$GLOBALS['checks'][]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function pwd(string $name,callable $f):void{try{$f();}catch(InvalidArgumentException){pwc($name,true);return;}pwc($name,false);}
try{
    pwc('Default workspace is overview',provider_workspace_section([])==='overview');
    foreach(array_keys(provider_workspace_sections()) as $section)pwc('Allowlisted workspace '.$section,provider_workspace_section(['section'=>$section])===$section);
    foreach(['../../config','Connection','',[],['connection'],42,null] as $i=>$value)pwc('Untrusted workspace falls back '.$i,provider_workspace_section(['section'=>$value])==='overview');
    pwc('Existing cloud-assignment focus is preserved',provider_workspace_section(['focus'=>'cloud-assignment'])==='cloud');
    $_GET=['section'=>'connection'];pwc('Connection action returns to connection workspace',provider_workspace_url()==='/admin/index.php?view=provider-settings&section=connection');
    $b=catalog_admin_browse([]);pwc('Default product page is bounded with no filter',$b['page']===1&&$b['offset']===0&&$b['params']===[]&&$b['where']==='');
    $b=catalog_admin_browse(['product_search'=>'  A%_!  ','product_family'=>'hosting','product_status'=>'draft','product_page'=>'2']);
    pwc('Search wildcard characters are escaped as literal text',$b['params']===['%A!%!_!!%','%A!%!_!!%','hosting','draft']);
    pwc('Filter SQL is closed fixed fragments',$b['where']===" WHERE (sku LIKE ? ESCAPE '!' OR title LIKE ? ESCAPE '!') AND family=? AND status=?");
    pwc('Product pages are fixed 50-row windows',$b['offset']===50);
    $unsafe=catalog_admin_browse(['product_search'=>"' OR 1=1 --"]);pwc('Search text cannot enter SQL structure',!str_contains($unsafe['where'],'1=1')&&str_contains($unsafe['params'][0],'1=1'));
    $u=catalog_admin_browse_url($b,3);pwc('Pagination preserves filters and anchors to product table',str_contains($u,'product_family=hosting')&&str_contains($u,'product_status=draft')&&str_contains($u,'product_page=3')&&str_ends_with($u,'#catalog-products'));
    foreach(['0','01','-1','1 OR 1=1','10001',1,[],null] as $i=>$value)pwd('Invalid product page denied '.$i,fn()=>catalog_admin_browse(['product_page'=>$value]));
    foreach(['*','all','../hosting',[]] as $i=>$value)pwd('Unknown family denied '.$i,fn()=>catalog_admin_browse(['product_family'=>$value]));
    foreach(['enabled','pending',[]] as $i=>$value)pwd('Unknown publication status denied '.$i,fn()=>catalog_admin_browse(['product_status'=>$value]));
    foreach([str_repeat('x',121),"line\nfeed",[],"\xff"] as $i=>$value)pwd('Invalid or excessive search denied '.$i,fn()=>catalog_admin_browse(['product_search'=>$value]));
    pwd('Invalid generated next page denied',fn()=>catalog_admin_browse_url($b,10001));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];}
$pass=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$pass,'scope'=>'Pure local routing and SQL-fragment/parameter generation; actual database execution not tested','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($pass?0:1);
