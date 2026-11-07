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
    ds_ok('Registry has one descriptor for every version marker',count($registry)===76&&array_keys($registry)===array_keys(database_schema_versions()));
    $found=[];$root=dirname(__DIR__).'/site';
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){if($file->getExtension()!=='php'||$file->getPathname()===CWP_CODE_ROOT.'/app/settings/database_schemas.php')continue;$source=file_get_contents($file->getPathname());preg_match_all("/(?:setting|save_setting)\\(['\"]([a-z0-9_]+_version)['\"]/",$source,$matches);foreach($matches[1] as $marker)$found[$marker]=true;}
    ksort($found);$expected=database_schema_versions();ksort($expected);
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
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>$failure===null,'checks'=>count($checks),'failure'=>$failure]).PHP_EOL;
exit($failure===null?0:1);
