<?php
/** Execute the actual updater session boundary with modeled rows; no SQL or file update. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/includes/auth/admin.php';require CWP_CODE_ROOT.'/app/staff/accounts.php';
$checks=[];$model=[];
function table(string $s):string{return $s;}
function setting(string $s):string{return $s==='staff_schema_version'?'1':'';}
function one(string $sql,array $args=[]):?array {
    if(str_contains($sql,'FROM users'))return $GLOBALS['model']['user'];
    if(str_contains($sql,'FROM staff_profiles'))return $GLOBALS['model']['staff'];
    throw new LogicException('Unexpected modeled read');
}
function support_current():?array{return $GLOBALS['model']['support'];}
function us_reset():array {
    $u=['id'=>7,'email'=>'synthetic@example.invalid','auth_version'=>str_repeat('a',32),'password_hash'=>'unused'];
    $GLOBALS['model']=['user'=>$u,'staff'=>['role'=>'owner','enabled'=>1,'permissions'=>'[]'],'support'=>null];
    $_SESSION=['uid'=>7,'auth_version'=>$u['auth_version'],'last_activity'=>time(),'login_at'=>time()];$_GET=[];
    return admin_user();
}
function us_check(string $name,bool $ok):void{$GLOBALS['checks'][]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function us_deny(string $name,callable $f):void{try{$f();}catch(RuntimeException){us_check($name,true);return;}us_check($name,false);}
try {
    $s=file_get_contents(__DIR__.'/../tools/update/owner-update.php');
    $start=strpos($s,'function structure_update_actor(');$end=$start===false?false:strpos($s,"\n}\n",$start);
    us_check('Actual updater defines one bounded session-authorization function',$start!==false&&$end!==false&&substr_count($s,'function structure_update_actor(')===1);
    eval(substr($s,$start,$end+3-$start)); // Execute only this exact repository function, not the updater entrypoint.
    $actor=us_reset();us_check('Signed-in owner requires no password argument',structure_update_actor($actor)['id']===7);
    us_deny('Missing actor is rejected',fn()=>structure_update_actor(null));
    $actor=us_reset();$_SESSION=[];us_deny('Signed-out owner cannot reuse captured actor',fn()=>structure_update_actor($actor));
    $actor=us_reset();$_SESSION['last_activity']=time()-1801;us_deny('Idle-expired owner is rejected',fn()=>structure_update_actor($actor));
    $actor=us_reset();$_SESSION['login_at']=time()-28801;us_deny('Absolute-expired owner is rejected',fn()=>structure_update_actor($actor));
    $actor=us_reset();$GLOBALS['model']['user']['auth_version']=str_repeat('b',32);us_deny('Revoked owner is rejected',fn()=>structure_update_actor($actor));
    $actor=us_reset();$GLOBALS['model']['staff']['enabled']=0;us_deny('Disabled owner is rejected',fn()=>structure_update_actor($actor));
    $actor=us_reset();$actor['role']='administrator';us_deny('Nonowner actor is rejected',fn()=>structure_update_actor($actor));
    $actor=us_reset();$GLOBALS['model']['staff']['role']='administrator';us_deny('Current owner role is re-read even without a version change',fn()=>structure_update_actor($actor));
    $actor=us_reset();$_GET['support']='';us_deny('Support query is rejected',fn()=>structure_update_actor($actor));
    $actor=us_reset();$GLOBALS['model']['support']=['synthetic'=>true];us_deny('Active support context is rejected',fn()=>structure_update_actor($actor));
    $actor=us_reset();$actor['id']=8;us_deny('Wrong actor identity is rejected',fn()=>structure_update_actor($actor));
    $actor=us_reset();$actor['auth_version']=str_repeat('b',32);us_deny('Obsolete captured actor version is rejected',fn()=>structure_update_actor($actor));
    us_check('Initial sign-in keeps the original password verification',substr_count($s,'password_verify(')===1&&str_contains($s,"!password_verify(\$password,\$user['password_hash'])"));
    $csrf=strpos($s,'check_csrf();');$guard=strpos($s,'$actor=structure_update_actor($actor);');$backup=strpos($s,"if(posted('backup_confirm',1)!=='1')");$mutation=strpos($s,'$dir=structure_store();');
    us_check('CSRF and backup confirmation remain before update mutations',$csrf!==false&&$guard!==false&&$backup!==false&&$mutation!==false&&$csrf<$guard&&$guard<$backup&&$backup<$mutation);
    us_check('Password input appears only in the signed-out branch',preg_match('/<\?php if\(!\$actor\):\?>(?:(?!<\?php else:).)*name="owner_password"(?:(?!<\?php else:).)*<\?php else:\?>/s',$s)===1&&substr_count($s,'name="owner_password"')===1);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected policy failure: '.$e->getMessage(),'passed'=>false];}
$passed=$checks!==[]&&!array_filter($checks,fn($c)=>$c['passed']!==true);
echo json_encode(['passed'=>$passed,'scope'=>'Actual updater authorization function and actual admin-session functions with modeled database rows; source checks for initial login/form/CSRF/backup guards. Not a real HTTP/SQL update.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;exit($passed?0:1);
