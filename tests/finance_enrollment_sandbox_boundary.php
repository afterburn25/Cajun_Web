<?php
/** Execute the real sandbox exception with isolated settings/readiness boundaries. No gateway. */
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/site/app/billing/finance_subscription.php');
if(!preg_match('/^function license_finance_subscription_sandbox_initial\(.*?(?=^function |\z)/ms',$source,$match))throw new RuntimeException('Sandbox guard missing');
eval($match[0]);$mode='disabled';$ledgerReads=0;$checks=[];
function license_finance_anet_settings():array{global $mode;return ['mode'=>$mode];}
function ledger_ready():bool{global $ledgerReads;$ledgerReads++;return false;}
foreach(['disabled','production','sandbox'] as $mode){
    $ledgerReads=0;$result=license_finance_subscription_sandbox_initial([]);
    $checks[]=['name'=>$mode.' cannot admit an initial-payment exception','passed'=>$result===false];
    $checks[]=['name'=>$mode.' readiness boundary','passed'=>$ledgerReads===($mode==='sandbox'?1:0)];
}
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'source_sha256'=>hash('sha256',$source),
    'scope'=>'Exact sandbox exception function with synthetic mode and unavailable-ledger boundaries; no production setting or gateway is changed.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed?1:0);
