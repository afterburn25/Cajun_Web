<?php
/** Owner-approved additive setup for guided software uploads. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/software_onboarding_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
require_once CWP_CODE_ROOT.'/app/billing/catalog.php';

function software_onboarding_table_present():bool{
    return (bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
        [config()['db']['prefix'].'software_upload_requests']);
}
function software_onboarding_storage_valid():bool{
    if(!software_onboarding_table_present())return false;
    $required=['request_key','actor_id','state','stored_path','sha256','byte_size','metadata','product_id','asset_id','last_error','updated_at'];
    $rows=all('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?',
        [config()['db']['prefix'].'software_upload_requests']);
    $have=array_fill_keys(array_column($rows,'COLUMN_NAME'),true);
    foreach($required as $column)if(!isset($have[$column]))return false;
    $indexes=all('SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=?',
        [config()['db']['prefix'].'software_upload_requests']);
    $haveIndex=array_fill_keys(array_column($indexes,'INDEX_NAME'),true);
    foreach(['request_key','actor_state','product_lookup','asset_lookup'] as $index)if(!isset($haveIndex[$index]))return false;
    return true;
}
function software_onboarding_ready():bool{return setting('software_onboarding_version')==='1'&&software_onboarding_storage_valid();}
function software_onboarding_prepare():void{
    if(db()->inTransaction())throw new LogicException('Prepare guided software uploads outside a transaction.');
    staff_locked(function():void{
        if(support_current())throw new RuntimeException('Leave support mode before preparing software uploads.');
        $actor=staff_owner('');
        staff_require('catalog.manage');
        if(!catalog_ready()||!software_commerce_ready())throw new RuntimeException('Prepare the catalog and software commerce first.');
        $version=(string)setting('software_onboarding_version');
        $pending=(string)setting('software_onboarding_pending');
        if(!in_array($version,['','1'],true)||!in_array($pending,['','1'],true))throw new RuntimeException('Unknown software upload setup history.');
        if($version==='1'){
            if(!software_onboarding_ready())throw new RuntimeException('Guided software upload storage is incomplete; preserve and review it.');
            return;
        }
        $exists=software_onboarding_table_present();
        if($exists&&$pending!=='1')throw new RuntimeException('Unrecognized software upload table; preserve and review it.');
        staff_transaction(function()use($actor):void{
            save_setting('software_onboarding_pending','1');
            license_audit((int)$actor['id'],'software.onboarding.setup.begin','Guided software upload preparation started');
        });
        if(!$exists)db()->exec(software_onboarding_schema(config()['db']['prefix'])['software_upload_requests']);
        if(!software_onboarding_storage_valid())throw new RuntimeException('Guided software upload storage is incomplete; preserve and review it.');
        staff_transaction(function()use($actor):void{
            save_setting('software_onboarding_version','1');
            save_setting('software_onboarding_pending','');
            license_audit((int)$actor['id'],'software.onboarding.setup','Guided software uploads enabled; existing products and assets preserved');
        });
    });
}
