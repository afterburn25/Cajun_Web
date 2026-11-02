<?php
/** Standalone exact-source FIN-AUTH reproduction; shared seed setup, no original assertions removed. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$frgAuthoritySource=file_get_contents(__DIR__.'/finance_replacement_payoff_native.php');
$frgAuthorityStart=strpos($frgAuthoritySource,"\ntry{\n");$frgAuthorityEnd=strpos($frgAuthoritySource,'    $fepGatewayId=860000;',$frgAuthorityStart);
if($frgAuthorityStart===false||$frgAuthorityEnd===false)throw new RuntimeException('Retained native seed boundary changed.');
eval(substr($frgAuthoritySource,5,$frgAuthorityStart-5));
require_once __DIR__.'/finance_replacement_payoff_authority_cases.php';
if(($argv[3]??'')==='auth-writer'){
    $change=(string)($argv[4]??'');if(!in_array($change,['version','deactivated'],true))exit(2);
    rb_staff();staff_locked(fn()=>staff_transaction(function()use($change):void{
        staff_owner('synthetic');
        if($change==='version')query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[str_repeat('9',32)]);
        else query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');
        license_audit(1,'financing.test.client.revoked','Synthetic native client revocation '.$change);
    }));
    echo json_encode(['pid'=>getmypid(),'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn(),'committed'=>!db()->inTransaction()],JSON_THROW_ON_ERROR);exit;
}
$observations=[];
try{
    eval(substr($frgAuthoritySource,$frgAuthorityStart+6,$frgAuthorityEnd-($frgAuthorityStart+6)));
    frg_authority_cases();
}catch(Throwable $error){fep_ok('FIN-AUTH fixture exception: '.get_class($error).' '.$error->getMessage(),false);}
$failed=count(array_filter($checks,fn($check)=>!$check['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'cases'=>$observations,
    'php'=>PHP_VERSION,'database'=>query('SELECT VERSION()')->fetchColumn(),
    'source_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),
    'scope'=>'Actual replacement callers, native signed agreements/current client records, shared native mutex/transactions, separate owner-authorized SQL revocation processes. Only ARB transport/mode and future signed invoice timing are synthetic. No real gateway or HTTP.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed===0?0:1);
