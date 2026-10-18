<?php
/** A separate CLI worker must lose admission while the parent owns dispatch. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$input=json_decode(stream_get_contents(STDIN),true,20,JSON_THROW_ON_ERROR);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/app/providers/force_ssl_controls.php';
$GLOBALS['cwp_config']=$input['config'];$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');
start_session();$u=one('SELECT * FROM '.table('users').' WHERE id=?',[3]);$_SESSION=['uid'=>3,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$dns=0;$sent=0;$refused=false;
try{provider_force_ssl_controls_change($input['request_id'],$input['link_id'],$input['anchor_id'],$input['binding_sha256'],true,true,$input['password'],function()use(&$dns):string{++$dns;return '8.8.8.8';},function()use(&$sent):never{++$sent;throw new LogicException('Contending worker reached provider dispatch.');});}
catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$refused=$e->getMessage()==='Another account change is in progress. Please retry.';}
echo json_encode(['refused_on_mutex'=>$refused,'dns'=>$dns,'sent'=>$sent,'actor_id'=>(int)$u['id']]);exit($refused&&$dns===0&&$sent===0?0:1);
