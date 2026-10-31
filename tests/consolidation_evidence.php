<?php
/** Evidence only, auto-prepended to explicitly selected disposable SQL fixtures. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||!in_array(getenv('CWP_TEST_DATABASE'),['cwp_test','cwp_checkout_test'],true))exit(2);
$script=basename($_SERVER['SCRIPT_FILENAME']??'');
if($script!==getenv('CWP_CONSOLIDATION_CASE').'.php'||!preg_match('/^[a-z_]+_native\.php$/D',$script))exit(2);
register_shutdown_function(static function()use($script):void {
 $checks=$GLOBALS['checks']??$GLOBALS['results']??[];
 $out=__DIR__.'/../qa/consolidation-native';@mkdir($out,0700,true);
 file_put_contents($out.'/'.$script.'.json',json_encode(['source_sha'=>getenv('CWP_SOURCE_SHA'),'script'=>$script,'checks'=>$checks,'authority'=>$GLOBALS['cwp_current_authority_evidence']??[],'expected_diagnostics'=>$GLOBALS['cwp_expected_software_diagnostics']??[],'final_error'=>error_get_last()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);
});
