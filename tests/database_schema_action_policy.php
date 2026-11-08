<?php
/** Owner/session boundary for the unified database workflow; no SQL execution. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
$GLOBALS['db_policy']=['support'=>false,'binding'=>'','valid'=>true,'staff'=>false,'owner_calls'=>0,'role'=>'owner','audit_fail'=>false,'audit'=>[]];
function support_current():?array{return $GLOBALS['db_policy']['support']?['session'=>['id'=>'synthetic']]:null;}
function support_binding():string{return $GLOBALS['db_policy']['binding'];}
function require_admin():array{return ['id'=>7,'name'=>'Synthetic owner'];}
function admin_session_matches(array $actor):bool{return $GLOBALS['db_policy']['valid']&&$actor['id']===7;}
function staff_ready():bool{return $GLOBALS['db_policy']['staff'];}
function staff_owner(string $password):array{$GLOBALS['db_policy']['owner_calls']++;if($password!=='')throw new RuntimeException('Redundant password');return ['id'=>7,'name'=>'Current owner','role'=>$GLOBALS['db_policy']['role']];}
function table(string $name):string{return 'cwp_'.$name;}
function query(string $sql,array $params=[]):mixed{if($GLOBALS['db_policy']['audit_fail'])throw new RuntimeException('Synthetic audit failure');$GLOBALS['db_policy']['audit'][]=$params;return null;}
require CWP_CODE_ROOT.'/app/settings/database_schemas.php';
$checks=[];$failure=null;
function da_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function da_refuses(callable $operation):bool{try{$operation();}catch(RuntimeException){return true;}return false;}
try{
    da_ok('Current legacy administrator can prepare the initial staff migration',database_schema_current_owner()['id']===7&&$GLOBALS['db_policy']['owner_calls']===0);
    $GLOBALS['db_policy']['staff']=true;da_ok('Prepared staff storage revalidates current owner without another password',database_schema_current_owner()['id']===7&&$GLOBALS['db_policy']['owner_calls']===1);
    $GLOBALS['db_policy']['role']='administrator';da_ok('Delegated staff management cannot substitute for active owner authority',da_refuses('database_schema_current_owner'));$GLOBALS['db_policy']['role']='owner';
    $GLOBALS['db_policy']['support']=true;da_ok('Active client review is refused',da_refuses('database_schema_current_owner'));$GLOBALS['db_policy']['support']=false;
    $GLOBALS['db_policy']['binding']='bound';da_ok('Bound resumable client review is refused',da_refuses('database_schema_current_owner'));$GLOBALS['db_policy']['binding']='';
    $GLOBALS['db_policy']['valid']=false;da_ok('Revoked administrator session is refused',da_refuses('database_schema_current_owner'));
    $hash=str_repeat('a',64);$_SESSION['database_schema_review']=['requested'=>['license_schema_version','payments_schema_version'],'review_hash'=>$hash];
    da_ok('Exact stored review binds selected order and hash',database_schema_bound_review(['license_schema_version','payments_schema_version'],$hash)['requested']===['license_schema_version','payments_schema_version']);
    da_ok('Reordered apply request cannot bypass the displayed review',da_refuses(fn()=>database_schema_bound_review(['payments_schema_version','license_schema_version'],$hash)));
    da_ok('Changed review hash is refused',da_refuses(fn()=>database_schema_bound_review(['license_schema_version','payments_schema_version'],str_repeat('b',64))));
    da_ok('Duplicate or structured selections cannot bypass the displayed review',da_refuses(fn()=>database_schema_bound_review(['license_schema_version','license_schema_version'],$hash))&&da_refuses(fn()=>database_schema_bound_review([['license_schema_version']],$hash)));
    unset($_SESSION['database_schema_review']);da_ok('Missing stored review is refused',da_refuses(fn()=>database_schema_bound_review(['license_schema_version'],$hash)));
    $row=['label'=>'License','installed'=>'2','current'=>'3'];database_schema_record_step('started','license_schema_version',$row,$hash);
    da_ok('Step start is durably recorded before migration',count($GLOBALS['db_policy']['audit'])===1&&$GLOBALS['db_policy']['audit'][0][1]==='database.update.started');
    $GLOBALS['db_policy']['audit_fail']=true;da_ok('Unavailable start history refuses migration',da_refuses(fn()=>database_schema_record_step('started','license_schema_version',$row,$hash)));
    da_ok('Unavailable completion history cannot report success',da_refuses(fn()=>database_schema_record_step('completed','license_schema_version',$row,$hash)));
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>$failure===null,'executions'=>count($checks),'checks'=>$checks,'failure'=>$failure]).PHP_EOL;exit($failure===null?0:1);
