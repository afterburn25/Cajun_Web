<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/app/staff/accounts.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/staff-test-config.php';
$actor=(int)$argv[1];$target=(int)$argv[2];$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$actor]);$t=one('SELECT * FROM '.table('users').' WHERE id=?',[$target]);
$_SESSION=['uid'=>$actor,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
file_put_contents(__DIR__.'/../test-runtime/staff-ready-'.$actor,'ready');
try{staff_save($target,$t['name'],$t['email'],'owner',false,[],'Synthetic staff owner passphrase');echo 'accepted';}catch(Throwable $e){echo 'rejected';}
