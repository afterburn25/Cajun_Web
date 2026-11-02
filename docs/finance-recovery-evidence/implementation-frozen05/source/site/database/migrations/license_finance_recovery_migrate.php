<?php
/** Owner-prepared addition to the retained financing gateway v3 schema. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/license_finance_recovery_schema.php';

function license_finance_recovery_ready():bool{
    if(setting('license_finance_recovery_schema_version')!=='1')return false;
    foreach(array_keys(license_finance_recovery_schema(config()['db']['prefix'])) as $name){
        if(!license_finance_gateway_table_present($name))return false;
    }
    return true;
}
function license_finance_recovery_validate():void{
    $schema=license_finance_recovery_schema(config()['db']['prefix']);
    catalog_validate_schema('license_finance_replacement_attempts',$schema['license_finance_replacement_attempts'],[
        'open_offer_id'=>['open_offer_id'],'gateway_subscription_id'=>['gateway_subscription_id']
    ]);
    license_finance_gateway_validate_index('license_finance_replacement_attempts','offer_lookup',['offer_id','created_at']);
    catalog_validate_schema('license_finance_collection_reviews',$schema['license_finance_collection_reviews'],[
        'gateway_transaction_id'=>['gateway_transaction_id']
    ]);
    license_finance_gateway_validate_index('license_finance_collection_reviews','offer_lookup',['offer_id','id']);
}
function license_finance_recovery_prepare(#[\SensitiveParameter]string $password):void{
    if(db()->inTransaction())throw new LogicException('Finance recovery setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        if(support_current())throw new RuntimeException('Leave support mode before finance recovery setup.');
        $actor=staff_owner($password);
        if(!license_finance_gateway_ready())throw new RuntimeException('Prepare financing gateway v3 storage first.');
        $version=(string)(setting('license_finance_recovery_schema_version')??'');
        $pending=(string)(setting('license_finance_recovery_schema_pending')??'');
        if(!in_array($version,['','1'],true)||!in_array($pending,['','1'],true))throw new RuntimeException('Unknown finance recovery storage history.');
        $schema=license_finance_recovery_schema(config()['db']['prefix']);
        if($version==='1'){
            foreach(array_keys($schema) as $name)if(!license_finance_gateway_table_present($name)){
                throw new RuntimeException('Established finance recovery storage is incomplete. Restore the original database.');
            }
            license_finance_recovery_validate();return;
        }
        foreach(array_keys($schema) as $name)if($pending===''&&license_finance_gateway_table_present($name)){
            throw new RuntimeException('Unrecognized finance recovery storage exists. No data was adopted.');
        }
        if($pending==='')staff_transaction(function()use($actor):void{
            save_setting('license_finance_recovery_schema_pending','1');
            license_audit((int)$actor['id'],'financing.recovery.setup.begin','Financing recovery v1 preparation started');
        });
        foreach($schema as $name=>$sql){
            if(license_finance_gateway_table_present($name)){
                if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0){
                    throw new RuntimeException('Interrupted finance recovery preparation contains data. Preserve and review the database.');
                }
            }else db()->exec($sql);
        }
        license_finance_recovery_validate();
        staff_transaction(function()use($actor,$schema):void{
            foreach(array_keys($schema) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0){
                throw new RuntimeException('Unrecognized financing recovery records exist. No data was adopted.');
            }
            // Older ambiguous replacements have no provable original reservation.
            // Preserve every existing row; never invent a historical contract.
            save_setting('license_finance_recovery_schema_version','1');
            save_setting('license_finance_recovery_schema_pending','');
            license_audit((int)$actor['id'],'financing.recovery.setup','Financing recovery v1 verified; existing subscription history preserved without inferred reservations');
        });
    });
}
