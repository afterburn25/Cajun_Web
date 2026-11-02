<?php
/** Explicit local cross-schema supplement. CI calls the portable helper from its native suites. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_FK_METADATA')!=='1'||getenv('CWP_TEST_DATABASE')!=='cwp_fk_metadata_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||!(int)getenv('CWP_TEST_PORT'))exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';
$GLOBALS['cwp_config']=['db'=>['host'=>'127.0.0.1','port'=>(int)getenv('CWP_TEST_PORT'),'name'=>'cwp_fk_metadata_test','user'=>(string)getenv('CWP_TEST_USER'),'pass'=>(string)getenv('CWP_TEST_PASSWORD'),'prefix'=>'fkml_l_']];
require_once __DIR__.'/foreign_key_metadata_native_cases.php';
$checks=[];$failure=null;$scope=null;$output=__DIR__.'/../qa/foreign-key-metadata-native.json';
if(file_exists($output)){fwrite(STDERR,"Refusing to overwrite native evidence\n");exit(2);}
try {$scope=fkm_run_native_cases(['plans','recurring'],'cwp_fk_metadata_foreign','fkml_');}
catch(Throwable $e){$failure=['class'=>get_class($e),'message'=>$e instanceof PDOException?'Database fixture operation failed':$e->getMessage(),'line'=>$e->getLine()];if($e instanceof PDOException)$failure+=['sqlstate'=>$e->errorInfo[0]??null,'driver_code'=>$e->errorInfo[1]??null];}
$report=['scope'=>'Local native cross-schema supplement, including all portable cases; no provider I/O','passed'=>$failure===null,'failure'=>$failure,'checks'=>$checks,'coverage'=>$scope,'php'=>PHP_VERSION,'database_port'=>(int)getenv('CWP_TEST_PORT'),'completed_at_utc'=>gmdate('Y-m-d\TH:i:s\Z')];
@mkdir(__DIR__.'/../qa',0700,true);file_put_contents($output,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo json_encode(['passed'=>$report['passed'],'checks'=>count($checks),'failure'=>$failure],JSON_THROW_ON_ERROR)."\n";exit($failure===null?0:1);
