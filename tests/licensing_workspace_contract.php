<?php
/** Rendering and source-route regression only; the installed browser suite tests actual SQL/HTTP. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
$owner=true;$catalogPreparedTest=false;$checks=[];
function esc(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function icon(string $s):string{return '';}
function site_url(string $p=''):string{return '/fixture/'.$p;}
function admin_url(string $v='dashboard',array $q=[]):string{return '/fixture/admin/index.php?'.http_build_query(['view'=>$v]+$q);}
function mail_accounts_access(array $u):bool{return false;}
function mail_notices_access(array $u):bool{return false;}
function staff_view_permission(string $v):?string{return $v==='studio-licenses'?'staff.manage':'website.manage';}
function staff_can(string $p,?array $u=null):bool{return $p!=='staff.manage'||$GLOBALS['owner'];}
function catalog_ready():bool{return $GLOBALS['catalogPreparedTest'];}
function check(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
require CWP_CODE_ROOT.'/includes/layout/admin.php';
try{
    ob_start();admin_navigation('studio-licenses',['id'=>1],0);$nav=ob_get_clean();
    check('Owner navigation exposes actual licensing route',str_contains($nav,'view=studio-licenses')&&str_contains($nav,'Licenses &amp; software'));
    check('Licensing navigation group is expanded and selected',str_contains($nav,'data-nav-group="licensing" open data-active="true"')&&str_contains($nav,'aria-current="page"'));
    $owner=false;ob_start();admin_navigation('dashboard',['id'=>2],0);$nav=ob_get_clean();
    check('Non-owner navigation does not expose owner licensing',!str_contains($nav,'view=studio-licenses'));
    foreach([[false,false,false],[true,false,false],[true,true,false],[true,true,true]] as $i=>[$ready,$catalogPreparedTest,$softwareReady]){
        ob_start();require CWP_CODE_ROOT.'/admin/views/studio/license_overview.php';$html=ob_get_clean();
        check('Readiness state '.$i.' always links to real software product filter',str_contains($html,'product_family=software'));
        check('Readiness state '.$i.' never asks for an administrator password',!str_contains($html,'type="password"'));
        check('Readiness state '.$i.' only links to rendered license section',str_contains($html,'href="#license-create"')===$ready);
        check('Readiness state '.$i.' only links to prepared software release section',str_contains($html,'href="#software-downloads"')===($ready&&$softwareReady));
    }
    $router=file_get_contents(CWP_CODE_ROOT.'/portal/controllers/router.php');
    $auth=strpos($router,"require CWP_CODE_ROOT.'/portal/views/auth.php';");
    foreach(['software_portal_render($client,$error)','license_finance_portal_render($client,$_GET[\'finance\'],$error)'] as $route){
        $position=strpos($router,$route);check('Authenticated portal route wired: '.$route,$position!==false&&$position>$auth&&$position<strpos($router,"require CWP_CODE_ROOT.'/portal/views/workspace.php';"));
    }
    check('Malformed and mixed license routes rejected',str_contains($router,'Invalid software or financing request.')&&str_contains($router,'$softwareRequested&&$financeRequested'));
    $admin=file_get_contents(CWP_CODE_ROOT.'/admin/controllers/router.php');
    check('License administration rejects support query contexts',str_contains($admin,"in_array(\$view,['studio-clients','studio-licenses'],true) && (support_current() || array_key_exists('support',\$_GET))"));
    check('License responses use private no-store caching',str_contains($admin,"if(\$view==='studio-licenses'||")&&str_contains($admin,"header('Cache-Control: no-store, private')"));
}catch(Throwable $e){$error=$e->getMessage();}
echo json_encode(['passed'=>!isset($error),'scope'=>'Actual navigation/readiness rendering with policy doubles plus source-route contracts; not SQL or browser acceptance','checks'=>$checks,'error'=>$error??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($error)?1:0);
