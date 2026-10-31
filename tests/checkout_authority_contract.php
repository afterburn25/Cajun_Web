<?php
/** Exercise exact fixture scoping; this test never connects to SQL. */
declare(strict_types=1);
require __DIR__.'/current_owner_authority.php';
$checks=[];
function scope_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function scope_env():void {foreach(['CWP_TEST_DATABASE'=>'cwp_checkout_test','CWP_TEST_HOST'=>'127.0.0.1','CWP_TEST_PORT'=>'43319','CWP_TEST_CHECKOUT_STORAGE'=>'1','CWP_TEST_RECURRING_CHECKOUT_STORAGE'=>'0'] as $k=>$v)putenv($k.'='.$v);}
try {
 scope_env();$base=['db'=>['name'=>'cwp_checkout_test','host'=>'127.0.0.1','port'=>43319,'prefix'=>'cscp_main_'],'storage'=>'','key'=>''];
 scope_ok('Exact service checkout SQL fixture admitted',cwp_test_sql_only_checkout($base));
 foreach([['name','production'],['host','example.invalid'],['port',3306],['prefix','production_']] as [$field,$value]){$cfg=$base;$cfg['db'][$field]=$value;scope_ok('Reject unapproved '.$field,!cwp_test_sql_only_checkout($cfg));}
 foreach(['storage','key'] as $field){$cfg=$base;$cfg[$field]='private';scope_ok('SQL-only scope cannot skip nonempty '.$field,!cwp_test_sql_only_checkout($cfg));unset($cfg[$field]);scope_ok('SQL-only scope requires explicit '.$field,!cwp_test_sql_only_checkout($cfg));}
 foreach(['CWP_TEST_DATABASE'=>'other','CWP_TEST_HOST'=>'localhost','CWP_TEST_PORT'=>'3306','CWP_TEST_CHECKOUT_STORAGE'=>'0'] as $key=>$value){scope_env();putenv($key.'='.$value);scope_ok('Exact opt-in enforced for '.$key,!cwp_test_sql_only_checkout($base));}
 scope_env();putenv('CWP_TEST_CHECKOUT_STORAGE=0');putenv('CWP_TEST_RECURRING_CHECKOUT_STORAGE=1');$cfg=$base;$cfg['db']['prefix']='crbc_main_';scope_ok('Exact recurring SQL fixture admitted',cwp_test_sql_only_checkout($cfg));scope_ok('Recurring flag cannot authorize service namespace',!cwp_test_sql_only_checkout($base));
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual fixture-scoping function only; no SQL or application changes','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
