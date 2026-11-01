<?php
/** Rendering, isolated real support refusal, and source-route regression; installed tests cover SQL/HTTP. */
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
/** Read the complete real refusal branch without assuming its protected-view list is closed. */
function licensing_support_view_guard(string $source):string{
    $tokens=token_get_all($source);$matches=[];
    $text=static fn(array|string $token):string=>is_array($token)?$token[1]:$token;
    $skip=static function(int &$index)use($tokens):void{
        while(isset($tokens[$index])&&is_array($tokens[$index])&&in_array($tokens[$index][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$index++;
    };
    foreach($tokens as $start=>$token){
        if(!is_array($token)||$token[0]!==T_IF)continue;
        $end=$start+1;$skip($end);if(($tokens[$end]??null)!=='(')continue;
        $depth=0;
        do{
            $part=$tokens[$end++];
            if($part==='(')$depth++;
            if($part===')')$depth--;
        }while($depth>0&&isset($tokens[$end]));
        if($depth!==0)continue;
        $skip($end);if(($tokens[$end]??null)!=='{')continue;
        $depth=0;
        do{
            $part=$tokens[$end++];
            if($part==='{')$depth++;
            if($part==='}')$depth--;
        }while($depth>0&&isset($tokens[$end]));
        if($depth!==0)continue;
        $branch=implode('',array_map($text,array_slice($tokens,$start,$end-$start)));
        if(str_contains($branch,'Leave client support before using client administration.'))$matches[]=$branch;
    }
    if(count($matches)!==1)throw new RuntimeException('Expected one real client-administration support refusal branch.');
    return $matches[0];
}
/** Execute the branch unchanged in an isolated PHP process so its actual 403/exit is observed. */
function licensing_support_view_result(string $branch,string $view,array $query,bool $resolved):array{
    $fixture=var_export(['view'=>$view,'query'=>$query,'resolved'=>$resolved],true);
    $script='$fixture='.$fixture.';' . <<<'PHP'
        $view=$fixture['view'];$_GET=$fixture['query'];$user=['id'=>1,'role'=>'owner'];
        $continued=false;$headed=false;$footed=false;ob_start();
        function support_current():?array{return $GLOBALS['fixture']['resolved']?['client'=>['id'=>1]]:null;}
        function admin_head(string $title,string $view,array $user):void{$GLOBALS['headed']=true;}
        function admin_foot():void{$GLOBALS['footed']=true;}
        register_shutdown_function(static function():void{
            $body=ob_get_clean();
            echo json_encode(['status'=>http_response_code()?:200,'continued'=>$GLOBALS['continued'],
                'headed'=>$GLOBALS['headed'],'footed'=>$GLOBALS['footed'],'body'=>$body],JSON_THROW_ON_ERROR);
        });
PHP;
    $script.=$branch.';$continued=true;';
    $process=proc_open([PHP_BINARY,'-r',$script],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot execute isolated support-view guard.');
    fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$diagnostics=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    if($status!==0||$diagnostics!=='')throw new RuntimeException('Support-view guard execution failed: '.$diagnostics);
    return json_decode($output,true,512,JSON_THROW_ON_ERROR);
}
function licensing_support_view_denied(array $result):bool{
    return $result['status']===403&&!$result['continued']&&$result['headed']&&$result['footed']
        &&str_contains($result['body'],'Leave client support before using client administration.');
}
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
    $supportGuard=licensing_support_view_guard($admin);
    check('License administration rejects support query contexts',licensing_support_view_denied(licensing_support_view_result($supportGuard,'studio-licenses',['support'=>'invalid'],false)));
    foreach(['studio-clients','studio-licenses','proposals','proposal'] as $protectedView){
        $ordinary=licensing_support_view_result($supportGuard,$protectedView,[],false);
        check($protectedView.' remains available outside support',$ordinary['status']===200&&$ordinary['continued']&&!$ordinary['headed']&&!$ordinary['footed']&&$ordinary['body']==='');
        foreach([
            ['resolved support',[],true],
            ['invalid support token',['support'=>'invalid'],false],
            ['empty support token',['support'=>''],false],
            ['array support marker',['support'=>[]],false],
            ['zero support marker',['support'=>'0'],false],
        ] as [$label,$query,$resolved]){
            check($protectedView.' refuses '.$label.' with HTTP 403 before rendering',licensing_support_view_denied(licensing_support_view_result($supportGuard,$protectedView,$query,$resolved)));
        }
    }
    check('License responses use private no-store caching',str_contains($admin,"if(\$view==='studio-licenses'||")&&str_contains($admin,"header('Cache-Control: no-store, private')"));
}catch(Throwable $e){$error=$e->getMessage();}
echo json_encode(['passed'=>!isset($error),'scope'=>'Actual navigation/readiness rendering with policy doubles, isolated real support refusal branch and source-route contracts; not installed SQL or HTTP/browser acceptance','checks'=>$checks,'error'=>$error??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($error)?1:0);
