<?php
/** Separate connection for the actual native licensing mutex regression. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||($argv[1]??'')!=='licup_')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require_once CWP_CODE_ROOT.'/app/licenses/legacy.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='licup_';$GLOBALS['cwp_config']['storage']='';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];start_session();
$u=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
try{license_resolve_legacy_invoice(900,1,true,'Contending original purchase review','Synthetic license owner passphrase');throw new LogicException('Competing owner unexpectedly changed a license.');}
catch(RuntimeException $e){if($e instanceof PDOException)throw$e;if($e->getMessage()!=='Another account change is in progress. Please retry.')throw$e;echo json_encode(['blocked'=>true,'reason'=>$e->getMessage()],JSON_THROW_ON_ERROR);}
