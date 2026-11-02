<?php
/** Exercise the real action dispatcher with dependency doubles; no SQL/network. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
$csrfValid=true;$permitted=true;$context=false;$checks=[];
function posted(string $name,int $max):string{return substr((string)($_POST[$name]??''),0,$max);}
function check_csrf():void{if(!$GLOBALS['csrfValid'])throw new RuntimeException('CSRF_REFUSED');}
function require_admin():array{return ['id'=>1,'role'=>'owner'];}
function staff_action_permission(string $action):?string{return 'staff.manage';}
function staff_require(string $permission):array{if(!$GLOBALS['permitted'])throw new RuntimeException('PERMISSION_REFUSED');return require_admin();}
function support_current():?array{return $GLOBALS['context']?['client'=>['id'=>1]]:null;}
function studio_admin_action(string $action,array $user):never{throw new RuntimeException('HANDLER_REACHED');}
function db():object{return new class{public function inTransaction():bool{return false;}};}
function assert_boundary(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
try{
 $_SERVER['REQUEST_METHOD']='POST';
 foreach(['studio_license_create','studio_domain_add','software_license_lifecycle'] as $actionName){
  foreach([false,true] as $inSupport){
   $_POST=['action'=>$actionName];$_GET=$inSupport?['support'=>'invalid']:[];$error='';
   require CWP_CODE_ROOT.'/admin/controllers/actions.php';
   assert_boundary($actionName.($inSupport?' refused before handler in support':' retains ordinary authenticated dispatch'),$error===($inSupport?'Leave client support before managing licenses or software.':'HANDLER_REACHED'));
  }
 }
 $_POST=['action'=>'studio_license_create'];$_GET=['support'=>[]];$error='';require CWP_CODE_ROOT.'/admin/controllers/actions.php';
 assert_boundary('Array support marker is also refused',$error==='Leave client support before managing licenses or software.');
 $_GET=[];$context=true;$error='';require CWP_CODE_ROOT.'/admin/controllers/actions.php';
 assert_boundary('Resolved support context is refused without query marker',$error==='Leave client support before managing licenses or software.');
 $csrfValid=false;$error='';require CWP_CODE_ROOT.'/admin/controllers/actions.php';assert_boundary('CSRF remains the first action check',$error==='CSRF_REFUSED');
 $csrfValid=true;$permitted=false;$error='';require CWP_CODE_ROOT.'/admin/controllers/actions.php';assert_boundary('Permission check remains required',$error==='PERMISSION_REFUSED');
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Real action dispatcher with dependency doubles; installed HTTP tests separately prove persisted-state refusal','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($failure)?1:0);
