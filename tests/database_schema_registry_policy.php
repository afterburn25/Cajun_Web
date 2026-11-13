<?php
/** Pure coverage and state-classification contract for Version & database. */
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/settings/database_schemas.php';
$checks=[];$failure=null;
function ds_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
try{
    $registry=database_schema_registry();
    ds_ok('Registry has one descriptor for every version marker',count($registry)===82&&array_keys($registry)===array_keys(database_schema_versions()));
    $migrations=database_schema_migrations();$unmapped=array_values(array_diff(array_keys(database_schema_versions()),array_keys($migrations)));sort($unmapped);$separate=array_keys(database_schema_separate_setups());sort($separate);
    ds_ok('Unified workflow maps every pure migration and leaves six disclosed feature setups separate',count($migrations)===76&&$unmapped===['delivery_schema_version','provider_stackcp_access_schema_version','provider_stackcp_assignment_schema_version','provider_stackcp_auth_schema_version','registration_drafts_schema_version','support_schema_version']&&$separate===$unmapped);
    $metadataValid=true;foreach($migrations as $marker=>$migration){$metadataValid=$metadataValid&&isset($registry[$marker])&&is_file(CWP_CODE_ROOT.'/'.$migration['file'])&&is_string($migration['function'])&&$migration['function']!==''&&in_array($migration['arguments'],['none','password','owner'],true)&&array_is_list($migration['depends']);foreach([...$migration['depends'],...($migration['effects']??[])] as $related)$metadataValid=$metadataValid&&isset($registry[$related]);}
    ds_ok('Every unified migration has a shipping source, supported dispatcher contract and registered dependencies',$metadataValid);
    $allMissing=[];foreach($registry as $marker=>$entry)$allMissing[$marker]=database_schema_classify($entry,'');$plannable=true;
    foreach(array_keys($migrations) as $marker){try{$candidate=database_schema_plan([$marker],$allMissing);$plannable=$plannable&&in_array($marker,array_column($candidate['rows'],'marker'),true);}catch(RuntimeException $e){$plannable=$plannable&&$marker==='support_tickets_schema_version'&&str_contains($e->getMessage(),'Support must be prepared through its feature setup');}}
    ds_ok('Every mapped feature has an acyclic dependency plan or its disclosed support-policy prerequisite',$plannable);
    $found=[];$root=dirname(__DIR__).'/site';
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){if($file->getExtension()!=='php'||$file->getPathname()===CWP_CODE_ROOT.'/app/settings/database_schemas.php')continue;$source=file_get_contents($file->getPathname());preg_match_all("/(?:setting|save_setting)\\(['\"]([a-z0-9_]+_version)['\"]/",$source,$matches);foreach($matches[1] as $marker)$found[$marker]=true;}
    ksort($found);$expected=database_schema_versions();ksort($expected);
    foreach(['provider_stackcp_auth_schema_version','provider_stackcp_assignment_schema_version'] as $dynamicMarker)$found[$dynamicMarker]=true;
    ksort($found);
    ds_ok('Every persisted feature version referenced by shipping source is registered',array_keys($found)===array_keys($expected));
    foreach($registry as $marker=>$entry){
        ds_ok('Descriptor is bounded and internally consistent: '.$marker,$entry['marker']===$marker&&$entry['current']!==''&&$entry['label']!==''&&$entry['group']!=='');
        $current=database_schema_classify($entry,$entry['current']);
        ds_ok('Current marker is current and action-free: '.$marker,$current['state']==='current'&&!$current['needs_update']);
        $missing=database_schema_classify($entry,'');
        ds_ok('Missing marker is separated as required or optional: '.$marker,in_array($missing['state'],['required','optional'],true)&&$missing['needs_update']===$entry['required']);
        $future=database_schema_classify($entry,'999');
        ds_ok('Unknown marker is visible and never auto-updated: '.$marker,$future['state']==='unsupported'&&!$future['needs_update']);
    }
    foreach(database_schema_upgrade_sources() as $marker=>$versions)foreach($versions as $version){$row=database_schema_classify($registry[$marker],$version);ds_ok('Known predecessor is updateable: '.$marker.' '.$version,$row['state']==='update'&&$row['needs_update']);}
    foreach(database_schema_recovery_sources() as $marker=>$versions)foreach($versions as $version){$row=database_schema_classify($registry[$marker],$version);ds_ok('Interrupted marker is recoverable: '.$marker.' '.$version,$row['state']==='interrupted'&&$row['needs_update']);}
    ds_ok('Grouping keeps provider, billing, mail and domain families distinct',
        $registry['provider_settings_schema_version']['group']==='Provider'&&$registry['payments_schema_version']['group']==='Billing'&&$registry['mail_accounts_schema_version']['group']==='Mail'&&$registry['domain_renewals_schema_version']['group']==='Domains');
    $rows=[];foreach($registry as $marker=>$entry)$rows[$marker]=database_schema_classify($entry,$entry['current']);
    $summary=database_schema_summary($rows);ds_ok('All-current summary needs no update and has no blocker',$summary['current']===82&&$summary['all_current']&&$summary['needs_update']===0&&$summary['blocking']===0);
    $rows['studio_schema_version']=database_schema_classify($registry['studio_schema_version'],'');
    $rows['contacts_schema_version']=database_schema_classify($registry['contacts_schema_version'],'');
    $rows['license_schema_version']=database_schema_classify($registry['license_schema_version'],'2');
    $rows['provider_settings_schema_version']=database_schema_classify($registry['provider_settings_schema_version'],'99');
    $summary=database_schema_summary($rows);ds_ok('Summary separates required, optional, update and unsupported states',$summary['required']===1&&$summary['optional']===1&&$summary['update']===1&&$summary['unsupported']===1&&$summary['needs_update']===2&&$summary['blocking']===1&&!$summary['all_current']);
    $groups=database_schema_groups($rows);ds_ok('Grouped rows retain all registered entries',array_sum(array_map('count',$groups))===82&&isset($groups['Provider'],$groups['Billing'],$groups['Mail'],$groups['Domains'],$groups['Customer experience']));
    $currentRows=[];foreach($registry as $marker=>$entry)$currentRows[$marker]=database_schema_classify($entry,$entry['current']);
    $currentRows['license_schema_version']=database_schema_classify($registry['license_schema_version'],'2');
    $currentRows['software_commerce_version']=database_schema_classify($registry['software_commerce_version'],'2');
    $plan=database_schema_plan(['software_commerce_version','license_schema_version'],$currentRows);
    ds_ok('Dependency plan orders licensing before software commerce',$plan['order']===['license_schema_version','software_commerce_version']);
    $reordered=database_schema_plan(['license_schema_version','software_commerce_version'],$currentRows);
    ds_ok('Review hash is stable across request ordering',$reordered['order']===$plan['order']&&hash_equals($plan['review_hash'],$reordered['review_hash']));
    $currentRows['service_plans_checkout_schema_version']=database_schema_classify($registry['service_plans_checkout_schema_version'],'preparing-1');
    $currentRows['recurring_billing_checkout_schema_version']=database_schema_classify($registry['recurring_billing_checkout_schema_version'],'preparing-1');
    $checkout=database_schema_plan(['recurring_billing_checkout_schema_version'],$currentRows);
    ds_ok('Interrupted recurring checkout closes over service checkout first',$checkout['order']===['service_plans_checkout_schema_version','recurring_billing_checkout_schema_version']);
    $dispatchRows=$currentRows;foreach(['payments_schema_version','invoice_ledger_schema_version','domain_renewal_dispatch_schema_version'] as $marker)$dispatchRows[$marker]=database_schema_classify($registry[$marker],'');
    $dispatch=database_schema_plan(['domain_renewal_dispatch_schema_version'],$dispatchRows);
    ds_ok('Domain renewal dispatch closes over payment and ledger storage first',$dispatch['order']===['payments_schema_version','invoice_ledger_schema_version','domain_renewal_dispatch_schema_version']);
    $gatewayRows=$currentRows;$gatewayRows['license_finance_gateway_schema_version']=database_schema_classify($registry['license_finance_gateway_schema_version'],'2');$gatewayRows['license_finance_recovery_schema_version']=database_schema_classify($registry['license_finance_recovery_schema_version'],'');
    $gateway=database_schema_plan(['license_finance_gateway_schema_version'],$gatewayRows);
    ds_ok('Plan discloses the finance gateway recovery-storage side effect',$gateway['order']===['license_finance_gateway_schema_version']&&array_column($gateway['rows'],'marker')===['license_finance_gateway_schema_version','license_finance_recovery_schema_version']);
    $changed=$currentRows;$changed['license_schema_version']=database_schema_classify($registry['license_schema_version'],'1');
    ds_ok('Changed installed version changes exact review hash',!hash_equals($plan['review_hash'],database_schema_plan(['software_commerce_version','license_schema_version'],$changed)['review_hash']));
    foreach([
        'Current selection is refused'=>fn()=>database_schema_plan(['studio_schema_version'],$currentRows),
        'Unknown selection is refused'=>fn()=>database_schema_plan(['unregistered_schema_version'],$currentRows),
        'Duplicate selection is refused'=>fn()=>database_schema_plan(['license_schema_version','license_schema_version'],$currentRows),
        'Unsupported selection is refused'=>function()use($currentRows,$registry){$bad=$currentRows;$bad['license_schema_version']=database_schema_classify($registry['license_schema_version'],'99');database_schema_plan(['license_schema_version'],$bad);},
        'Separately managed missing dependency is refused'=>function()use($currentRows,$registry){$bad=$currentRows;$bad['support_schema_version']=database_schema_classify($registry['support_schema_version'],'');$bad['support_tickets_schema_version']=database_schema_classify($registry['support_tickets_schema_version'],'');database_schema_plan(['support_tickets_schema_version'],$bad);},
        'Unsupported side effect is refused before planning'=>function()use($gatewayRows,$registry){$bad=$gatewayRows;$bad['license_finance_recovery_schema_version']=database_schema_classify($registry['license_finance_recovery_schema_version'],'99');database_schema_plan(['license_finance_gateway_schema_version'],$bad);},
    ] as $name=>$operation){$refused=false;try{$operation();}catch(InvalidArgumentException|RuntimeException){$refused=true;}ds_ok($name,$refused);}
    $executionRows=$currentRows;$executionRows['license_schema_version']=database_schema_classify($registry['license_schema_version'],'2');$executionRows['software_commerce_version']=database_schema_classify($registry['software_commerce_version'],'2');
    $executionPlan=database_schema_plan(['software_commerce_version','license_schema_version'],$executionRows);$calls=[];
    $snapshot=function()use(&$executionRows):array{return $executionRows;};
    $runner=function(string $marker)use(&$executionRows,&$calls,$registry):void{$calls[]=$marker;$executionRows[$marker]=database_schema_classify($registry[$marker],$registry[$marker]['current']);};
    $outcomes=[];$result=database_schema_execute_reviewed($executionPlan['requested'],$executionPlan['review_hash'],$snapshot,$runner,function(string $outcome,string $marker,array $row,?Throwable $error)use(&$outcomes):void{$outcomes[]=[$outcome,$marker,$row['state'],$error?->getMessage()];});
    ds_ok('Exact reviewed plan executes once in dependency order',$calls===['license_schema_version','software_commerce_version']&&$result['applied']===$calls);
    ds_ok('Each reviewed step records start then validated completion',$outcomes===[['started','license_schema_version','update',null],['completed','license_schema_version','update',null],['started','software_commerce_version','update',null],['completed','software_commerce_version','update',null]]);
    $calls=[];$stale=$executionRows;$stale['license_schema_version']=database_schema_classify($registry['license_schema_version'],'1');
    $refused=false;try{database_schema_execute_reviewed($executionPlan['requested'],$executionPlan['review_hash'],fn()=>$stale,function()use(&$calls):void{$calls[]='called';});}catch(RuntimeException){$refused=true;}
    ds_ok('Stale review is refused before a migration runs',$refused&&$calls===[]);
    $unexpected=$currentRows;$unexpected['license_schema_version']=database_schema_classify($registry['license_schema_version'],'2');$unexpectedPlan=database_schema_plan(['license_schema_version'],$unexpected);
    $refused=false;try{database_schema_execute_reviewed($unexpectedPlan['requested'],$unexpectedPlan['review_hash'],function()use(&$unexpected):array{return $unexpected;},function()use(&$unexpected,$registry):void{$unexpected['license_schema_version']=database_schema_classify($registry['license_schema_version'],'3');$unexpected['contacts_schema_version']=database_schema_classify($registry['contacts_schema_version'],'');});}catch(RuntimeException){$refused=true;}
    ds_ok('Unreviewed registered marker change is detected',$refused);
    $incomplete=$currentRows;$incomplete['license_schema_version']=database_schema_classify($registry['license_schema_version'],'2');$incompletePlan=database_schema_plan(['license_schema_version'],$incomplete);
    $failedOutcomes=[];$refused=false;try{database_schema_execute_reviewed($incompletePlan['requested'],$incompletePlan['review_hash'],fn()=>$incomplete,fn()=>null,function(string $outcome,string $marker,array $row,?Throwable $error)use(&$failedOutcomes):void{$failedOutcomes[]=[$outcome,$marker,$error?->getMessage()];});}catch(RuntimeException){$refused=true;}
    ds_ok('Step that does not reach its reviewed target is refused',$refused&&count($failedOutcomes)===2&&$failedOutcomes[0][0]==='started'&&$failedOutcomes[1][0]==='failed'&&str_contains($failedOutcomes[1][2],'did not reach'));
    $effectRows=$gatewayRows;$effectPlan=database_schema_plan(['license_finance_gateway_schema_version'],$effectRows);$effectCalls=[];
    $effectResult=database_schema_execute_reviewed($effectPlan['requested'],$effectPlan['review_hash'],function()use(&$effectRows):array{return $effectRows;},function(string $marker)use(&$effectRows,&$effectCalls,$registry):void{$effectCalls[]=$marker;$effectRows[$marker]=database_schema_classify($registry[$marker],$registry[$marker]['current']);$effectRows['license_finance_recovery_schema_version']=database_schema_classify($registry['license_finance_recovery_schema_version'],'1');});
    ds_ok('Reviewed indirect effect may change with its declared step',$effectCalls===['license_finance_gateway_schema_version']&&count($effectResult['rows'])===2);
    $recoveryRows=$gatewayRows;$recoveryPlan=database_schema_plan(['license_finance_recovery_schema_version'],$recoveryRows);
    ds_ok('Selecting recovery closes over the gateway without a redundant recovery step',$recoveryPlan['order']===['license_finance_gateway_schema_version']&&array_column($recoveryPlan['rows'],'marker')===['license_finance_gateway_schema_version','license_finance_recovery_schema_version']);
    $mailRows=$currentRows;foreach(['mail_accounts_schema_version','mail_inbox_schema_version','mail_messages_schema_version','mail_notices_schema_version'] as $marker)$mailRows[$marker]=database_schema_classify($registry[$marker],'');
    $mailPlan=database_schema_plan(['mail_notices_schema_version'],$mailRows);
    ds_ok('Mail notice setup closes over accounts, inbox and messages in dependency order',$mailPlan['order']===['mail_accounts_schema_version','mail_inbox_schema_version','mail_messages_schema_version','mail_notices_schema_version']);
    $backupRows=$currentRows;$backupRows['backups_schema_version']=database_schema_classify($registry['backups_schema_version'],'1');$backupPlan=database_schema_plan(['backups_schema_version'],$backupRows);
    ds_ok('Review rows disclose non-database side effects',str_contains($backupPlan['rows'][0]['note'],'private backup directory'));
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>$failure===null,'executions'=>count($checks),'checks'=>$checks,'failure'=>$failure]).PHP_EOL;
exit($failure===null?0:1);
