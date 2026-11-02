<?php
/** Auto-prepend evidence hook for disposable CI only; contains no application behavior. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
register_shutdown_function(function():void{
 $script=basename($_SERVER['SCRIPT_FILENAME']??'');
 if(!preg_match('/^[a-z_]+_native\.php$/D',$script))return;
 $out=__DIR__.'/../qa/legacy-authority';@mkdir($out,0700,true);
 file_put_contents($out.'/'.$script.'.authority.json',json_encode(['source_sha'=>getenv('CWP_SOURCE_SHA'),'checks'=>$GLOBALS['cwp_current_authority_evidence']??[],'expected_diagnostics'=>$GLOBALS['cwp_expected_software_diagnostics']??[]],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);
});
