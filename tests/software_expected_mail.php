<?php
/** Exactly scoped disabled-mail diagnostics; unexpected errors still fail native acceptance. */
declare(strict_types=1);
function cwp_test_disabled_software_notice(int $invoice,callable $work):mixed {
    if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'
        ||config()['db']['prefix']!=='swci_'||setting('email_mode','disabled')!=='disabled'||db()->inTransaction()){
        throw new AssertionError('Expected-mail diagnostic is restricted to the disabled synthetic software fixture.');
    }
    $before=one('SELECT attempts FROM '.table('software_notices').' WHERE invoice_id=?',[$invoice]);
    $path=tempnam(sys_get_temp_dir(),'cwp-software-fault-');
    if($path===false)throw new AssertionError('Could not capture expected software notice diagnostic.');
    $old=ini_get('error_log');
    try {
        if(ini_set('error_log',$path)===false)throw new AssertionError('Could not isolate expected software notice diagnostic.');
        $result=$work();
    } finally {
        ini_set('error_log',$old===false?'':$old);$log=(string)file_get_contents($path);unlink($path);
    }
    $lines=preg_split('/\r?\n/',trim($log));
    $expected=count($lines)===1&&preg_match('/^(?:\[[^\r\n]+\] )?CWP \[[A-F0-9]{8}\] RuntimeException code=0 in software_notices\.php:50$/D',$lines[0])===1;
    $notice=one('SELECT status,attempts,sent_at,last_error FROM '.table('software_notices').' WHERE invoice_id=?',[$invoice]);
    $retained=$notice&&$notice['status']==='pending'&&(int)$notice['attempts']==(int)($before['attempts']??0)+1
        &&$notice['sent_at']===null&&$notice['last_error']==='Delivery failed; retry after mail configuration review'
        &&setting('email_mode','disabled')==='disabled'&&!db()->inTransaction();
    if(!$expected||!$retained)throw new AssertionError('Disabled software notice did not produce its one expected diagnostic and retryable outbox state.');
    $GLOBALS['cwp_expected_software_diagnostics'][]=['invoice'=>$invoice,'expected'=>true,'status'=>$notice['status'],'attempts'=>(int)$notice['attempts'],'diagnostic'=>trim($log)];
    swok(true,'Disabled delivery records exactly one expected diagnostic and retains its retryable notice');
    return $result;
}
