<?php
/** Owner-gated financed payment gateway storage. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/license_finance_gateway_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';

function license_finance_gateway_table_present(string $name):bool{
    return (bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
        [config()['db']['prefix'].$name]);
}
function license_finance_gateway_column_present(string $tableName,string $column):bool{
    return (bool)one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
        [config()['db']['prefix'].$tableName,$column]);
}
function license_finance_gateway_ready():bool{
    if(setting('license_finance_gateway_schema_version')!=='3')return false;
    foreach(array_keys(license_finance_gateway_schema(config()['db']['prefix'])) as $name){
        if(!license_finance_gateway_table_present($name))return false;
    }
    return license_finance_gateway_column_present('license_finance_subscriptions','period_offset')
        &&license_finance_gateway_column_present('license_finance_subscriptions','replacement_count');
}
function license_finance_gateway_validate_schema():void{
    $schema=license_finance_gateway_schema(config()['db']['prefix']);
    catalog_validate_schema('license_finance_charges',$schema['license_finance_charges'],[
        'request_id'=>['request_id'],'invoice_id'=>['invoice_id'],'request_key'=>['request_key'],'transaction_id'=>['transaction_id']
    ]);
    catalog_validate_schema('license_finance_charge_attempts',$schema['license_finance_charge_attempts'],[
        'request_key'=>['request_key'],'transaction_id'=>['transaction_id']
    ]);
    catalog_validate_schema('license_finance_subscriptions',$schema['license_finance_subscriptions'],[
        'offer_id'=>['offer_id'],'gateway_subscription_id'=>['gateway_subscription_id']
    ]);
    catalog_validate_schema('license_finance_subscription_history',$schema['license_finance_subscription_history'],[
        'gateway_subscription_id'=>['gateway_subscription_id'],'offer_lookup'=>['offer_id','id']
    ]);
    catalog_validate_schema('license_finance_subscription_receipts',$schema['license_finance_subscription_receipts'],[
        'gateway_transaction_id'=>['gateway_transaction_id'],'invoice_id'=>['invoice_id'],'offer_period'=>['offer_id','period_index']
    ]);
    catalog_validate_schema('license_finance_reversals',$schema['license_finance_reversals'],[
        'gateway_transaction_id'=>['gateway_transaction_id'],
        'original_lookup'=>['original_transaction_id','id'],
        'invoice_lookup'=>['invoice_id','id'],
        'offer_lookup'=>['offer_id','id']
    ]);
}
function license_finance_gateway_upgrade_v2(array $schema):void{
    if(!license_finance_gateway_table_present('license_finance_subscription_history')){
        db()->exec($schema['license_finance_subscription_history']);
    }
    $table=chr(96).config()['db']['prefix'].'license_finance_subscriptions'.chr(96);
    if(!license_finance_gateway_column_present('license_finance_subscriptions','period_offset')){
        db()->exec('ALTER TABLE '.$table.' ADD COLUMN period_offset INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_occurrences');
    }
    if(!license_finance_gateway_column_present('license_finance_subscriptions','replacement_count')){
        db()->exec('ALTER TABLE '.$table.' ADD COLUMN replacement_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER period_offset');
    }
}
function license_finance_gateway_upgrade_v3(array $schema):void{
    if(!license_finance_gateway_table_present('license_finance_reversals')){
        db()->exec($schema['license_finance_reversals']);
    }
}
function license_finance_gateway_prepare(#[\SensitiveParameter]string $password):void{
    if(db()->inTransaction())throw new LogicException('Finance gateway setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        if(support_current())throw new RuntimeException('Leave support mode before finance gateway setup.');
        $actor=staff_owner($password);
        if(!license_finance_offers_ready())throw new RuntimeException('Prepare client finance agreements first.');
        if(!payments_ready()||!ledger_ready())throw new RuntimeException('Prepare payment links and invoice ledger first.');
        $version=(string)(setting('license_finance_gateway_schema_version')??'');
        if(!in_array($version,['','1','2','3'],true))throw new RuntimeException('Unknown finance gateway storage version.');
        $schema=license_finance_gateway_schema(config()['db']['prefix']);
        $v1=['license_finance_charges','license_finance_charge_attempts','license_finance_subscriptions','license_finance_subscription_receipts'];

        if($version===''){
            foreach(array_keys($schema) as $name){
                if(license_finance_gateway_table_present($name)){
                    throw new RuntimeException('Unrecognized finance gateway storage exists. No data was adopted.');
                }
            }
            foreach($schema as $sql)db()->exec($sql);
            license_finance_gateway_validate_schema();
            staff_transaction(function()use($actor):void{
                foreach(array_keys(license_finance_gateway_schema(config()['db']['prefix'])) as $name){
                    if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0){
                        throw new RuntimeException('Unrecognized finance gateway records exist. No data was adopted.');
                    }
                }
                save_setting('license_finance_gateway_schema_version','3');
                license_audit((int)$actor['id'],'financing.gateway.setup','Financed payment gateway v3 storage verified with reversal history');
            });
            return;
        }

        foreach($v1 as $name){
            if(!license_finance_gateway_table_present($name)){
                throw new RuntimeException('Established finance gateway storage is incomplete. Restore the original database.');
            }
        }
        if($version==='1'){
            license_finance_gateway_upgrade_v2($schema);
            license_finance_gateway_upgrade_v3($schema);
            license_finance_gateway_validate_schema();
            staff_transaction(function()use($actor):void{
                save_setting('license_finance_gateway_schema_version','3');
                license_audit((int)$actor['id'],'financing.gateway.upgrade',
                    'Financed payment gateway upgraded from v1 to v3 with replacement and reversal history');
            });
            return;
        }
        if($version==='2'){
            license_finance_gateway_upgrade_v3($schema);
            license_finance_gateway_validate_schema();
            staff_transaction(function()use($actor):void{
                save_setting('license_finance_gateway_schema_version','3');
                license_audit((int)$actor['id'],'financing.gateway.upgrade',
                    'Financed payment gateway upgraded from v2 to v3 reversal history');
            });
            return;
        }

        foreach(array_keys($schema) as $name){
            if(!license_finance_gateway_table_present($name)){
                throw new RuntimeException('Established finance gateway v3 storage is incomplete. Restore the original database.');
            }
        }
        license_finance_gateway_validate_schema();
    });
}
