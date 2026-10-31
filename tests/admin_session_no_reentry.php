<?php
/** Actual session, role and CSRF guards with synthetic database rows; no SQL/server/provider IO. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/includes/auth/admin.php';
require CWP_CODE_ROOT.'/app/staff/accounts.php';
require CWP_CODE_ROOT.'/includes/security/csrf.php';
require CWP_CODE_ROOT.'/includes/layout/admin.php';
$checks=[];$model=[];
function esc(mixed $s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function table(string $s):string{return $s;}
function setting(string $s):string{return $s==='staff_schema_version'?'1':'';}
function one(string $sql,array $args=[]):?array {
    if(str_contains($sql,'FROM users'))return $GLOBALS['model']['user'];
    if(str_contains($sql,'FROM staff_profiles'))return $GLOBALS['model']['staff'];
    throw new LogicException('Unexpected modeled read: '.$sql);
}
function support_current():?array{return $GLOBALS['model']['support'];}
function admin_url(string $view=''):string{return '/admin/index.php?view='.$view;}
function redirect(string $url):never{throw new RuntimeException('Authentication required');}
function ns_reset():array {
    $u=['id'=>7,'auth_version'=>str_repeat('a',32),'password_hash'=>'unused-in-this-test','name'=>'Synthetic owner'];
    $GLOBALS['model']=['user'=>$u,'staff'=>['role'=>'owner','enabled'=>1,'permissions'=>'[]'],'support'=>null];
    $_SESSION=['uid'=>7,'auth_version'=>$u['auth_version'],'last_activity'=>time(),'login_at'=>time(),'csrf'=>str_repeat('a',64)];$_GET=[];$_POST=[];
    return $u;
}
function ns_check(string $name,bool $pass):void{$GLOBALS['checks'][]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function ns_deny(string $name,callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException){ns_check($name,true);return;}ns_check($name,false);}
function ns_render(callable $f):string{ob_start();try{$f();return ob_get_contents();}finally{ob_end_clean();}}
try {
    $a=ns_reset();ns_check('Valid current owner session matches exact actor',admin_session_matches($a));
    ns_check('Owner boundary accepts no repeated password',staff_owner('')['id']===7);
    ns_check('Legacy password argument is not an alternate authentication mechanism',staff_owner('irrelevant-legacy-value')['id']===7);
    ns_check('Different actor is denied',!admin_session_matches(array_replace($a,['id'=>8])));
    ns_check('Captured obsolete actor version is denied',!admin_session_matches(array_replace($a,['auth_version'=>'obsolete'])));
    ns_check('Actor missing version is denied',!admin_session_matches(['id'=>7]));
    ns_check('Empty actor version is denied',!admin_session_matches(['id'=>7,'auth_version'=>'']));
    ns_reset();$_SESSION=[];ns_check('Anonymous session is denied',!admin_session_matches($a));ns_deny('Anonymous owner action is denied',fn()=>staff_owner(''));
    ns_reset();$_SESSION['last_activity']=time()-1801;ns_check('Idle session is denied',!admin_session_matches($a));
    ns_reset();$_SESSION['login_at']=time()-28801;ns_check('Absolute-expired session is denied',!admin_session_matches($a));
    ns_reset();$GLOBALS['model']['user']['auth_version']='rotated';ns_check('Credential rotation revokes captured session',!admin_session_matches($a));
    ns_reset();$GLOBALS['model']['user']=null;ns_check('Deleted administrator is denied',!admin_session_matches($a));
    ns_reset();$GLOBALS['model']['staff']['enabled']=0;ns_check('Disabled administrator is denied',!admin_session_matches($a));
    ns_reset();$GLOBALS['model']['staff']['role']='administrator';ns_deny('Ordinary administrator cannot become owner by omitting password',fn()=>staff_owner(''));
    ns_reset();$GLOBALS['model']['staff']['permissions']='["unregistered.permission"]';ns_check('Malformed permission profile is denied',!admin_session_matches($a));
    ns_reset();$GLOBALS['model']['support']=['synthetic'=>true];ns_check('Support context cannot reuse owner session',!admin_session_matches($a));ns_deny('Owner boundary refuses support context',fn()=>staff_owner(''));
    ns_reset();$_GET['support']='';ns_check('Explicit support query is denied even when empty',!admin_session_matches($a));
    ns_reset();ns_deny('Missing CSRF remains denied',fn()=>check_csrf());$_POST=['csrf'=>'wrong'];ns_deny('Wrong CSRF remains denied',fn()=>check_csrf());
    $_POST=['csrf'=>str_repeat('a',64)];check_csrf();ns_check('Current CSRF remains accepted',true);
    foreach(['current_password','owner_password'] as $name)ns_check('Repeated input is absent from markup: '.$name,ns_render(fn()=>admin_input($name,'Current owner password','','password','required'))==='');
    foreach(['password','new_password','confirm_password','general_key','smtp_password','auth_client_key'] as $name)ns_check('Real sign-in/new credential field remains: '.$name,str_contains(ns_render(fn()=>admin_input($name,'Credential','','password','required')),'name="'.$name.'"'));
    $markup=ns_render(fn()=>admin_action('provider_settings_save'));
    ns_check('Action fields retain CSRF',str_contains($markup,'name="csrf"')&&str_contains($markup,'value="provider_settings_save"'));
    $root=dirname(__DIR__);$raw=[];$backend=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CWP_CODE_ROOT,FilesystemIterator::SKIP_DOTS)) as $p){
        if($p->getExtension()!=='php'||str_contains($p->getPathname(),'/foundation/')||str_contains($p->getPathname(),'/install/'))continue;
        $s=file_get_contents($p->getPathname());
        if(preg_match('/<input\\b[^>]*\\bname=["\x27](?:current_password|owner_password)["\x27]/',$s))$raw[]=$p->getPathname();
        if(str_contains($s,'password_verify('))$backend[]=str_replace(CWP_CODE_ROOT.'/','',$p->getPathname());
    }
    ns_check('No raw repeated-password fields remain in application views',$raw===[]);
    sort($backend);ns_check('Password verification remains in administrator and client login only',$backend===['admin/controllers/auth.php','app/clients/identity.php']);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected test failure: '.$e->getMessage(),'passed'=>false];}
$pass=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$pass,'scope'=>'Actual PHP session/role/CSRF/field code; database reads modeled; no native SQL or production actions','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($pass?0:1);
