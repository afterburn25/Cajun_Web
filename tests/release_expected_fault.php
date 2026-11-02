<?php
/** Capture only one explicitly injected fault; any additional diagnostic fails the case. */
declare(strict_types=1);
function rb_expected_report(callable $work,string $origin):mixed {
    if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!in_array($origin,['financing_offers.php','payments.php'],true))throw new LogicException('Synthetic fault scope only');
    $path=tempnam(sys_get_temp_dir(),'cwp-expected-');if($path===false)throw new RuntimeException('Could not capture expected fault');
    $old=ini_get('error_log');
    try{
        if(ini_set('error_log',$path)===false)throw new RuntimeException('Could not isolate expected diagnostic');
        $value=$work();
    }finally{ini_set('error_log',$old===false?'':$old);$log=(string)file_get_contents($path);unlink($path);}
    $lines=preg_split('/\r?\n/',trim($log));
    $expected=count($lines)===1&&preg_match('/(?:^|\] )CWP \[[A-F0-9]{8}\] RuntimeException code=0 in '.preg_quote($origin,'/').':[0-9]+$/D',$lines[0])===1;
    $GLOBALS['rb_expected_diagnostics'][]=['origin'=>$origin,'expected'=>$expected,'diagnostic'=>trim($log)];
    rb_ok('Injected unavailable infrastructure reports exactly one expected fault from '.$origin,$expected);
    return $value;
}
