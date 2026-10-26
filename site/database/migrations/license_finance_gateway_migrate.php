<?php
/** Owner-gated financed payment gateway storage. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/license_finance_gateway_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';

function license_finance_gateway_ready():bool{
    if(setting('license_finance_gateway_schema_version')!=='1')return false;
    foreach(array_keys(license_finance_gateway_schema(config()['db']['prefix'])) as $name){
        if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
            [config()['db']['prefix'].$name]))return false;
    }
    return true;
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
    catalog_validate_schema('license_finance_subscription_receipts',$schema['license_finance_subscription_receipts'],[
        'gateway_transaction_id'=>['gateway_transaction_id'],'invoice_id'=>['invoice_id'],'offer_period'=>['offer_id','period_index']
    ]);
}
function license_finance_gateway_prepare(#[\SensitiveParameter]string $password):void{
    if(db()->inTransaction())throw new LogicException('Finance gateway setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        if(support_current())throw new RuntimeException('Leave support mode before finance gateway setup.');
        $actor=staff_owner($password);
        if(!license_finance_offers_ready())throw new RuntimeException('Prepare client finance agreements first.');
        if(!payments_ready()||!ledger_ready())throw new RuntimeException('Prepare payment links and invoice ledger first.');
        $version=(string)(setting('license_finance_gateway_schema_version')??'');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unknown finance gateway storage version.');
        $schema=license_finance_gateway_schema(config()['db']['prefix']);$present=[];
        foreach($schema as $name=>$sql)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))$present[]=$name;
        if($version==='1'&&count($present)!==count($schema))throw new RuntimeException('Established finance gateway storage is incomplete. Restore the original database.');
        if($version===''&&$present)throw new RuntimeException('Unrecognized finance gateway storage exists. No data was adopted.');
        foreach($schema as $name=>$sql)if(!in_array($name,$present,true))db()->exec($sql);
        license_finance_gateway_validate_schema();
        staff_transaction(function()use($actor):void{
            if(setting('license_finance_gateway_schema_version')!=='1'){
                foreach(array_keys(license_finance_gateway_schema(config()['db']['prefix'])) as $name){
                    if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized finance gateway records exist. No data was adopted.');
                }
                save_setting('license_finance_gateway_schema_version','1');
                license_audit((int)$actor['id'],'financing.gateway.setup','Financed payment gateway storage verified');
            }
        });
    });
}
