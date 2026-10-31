<?php
/** Explicit owner setup for financed-site license enforcement. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/license_financing_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/support/sessions.php';

function license_financing_storage_table_present(string $name):bool{
    return (bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
        [config()['db']['prefix'].$name]);
}
function license_financing_validate_index(string $table,string $key,array $fields,bool $unique=false):void{
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($table)) as $index){
        if((string)$index['Key_name']!==$key)continue;
        if(((int)$index['Non_unique']===0)!==$unique||$index['Sub_part']!==null||$index['Index_type']!=='BTREE'){
            throw new RuntimeException('Incompatible financed-license index. Preserve the database for recovery.');
        }
        $actual[(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    ksort($actual);
    if(array_values($actual)!==$fields)throw new RuntimeException('Incompatible financed-license index. Preserve the database for recovery.');
}
function license_financing_validate_schema():void{
    $schema=license_financing_schema(config()['db']['prefix']);
    catalog_validate_schema('license_financing',$schema['license_financing'],
        ['license_lookup'=>['license_id'],'schedule_lookup'=>['schedule_id']]);
    license_financing_validate_index('license_financing','state_lookup',['state']);
    $columns=array_column(all('SHOW FULL COLUMNS FROM '.table('license_financing')),'Field');
    $expected=['id','license_id','schedule_id','terms_sha256','installments','grace_days','state','paid_off_at',
        'allow_resale','allow_distribution','exclusive_resale','exclusive_distribution','created_by','created_at','updated_at'];
    if($columns!==$expected)throw new RuntimeException('Incompatible financed-license columns. Preserve the database for recovery.');
    catalog_validate_schema('license_financing_rights_grants',$schema['license_financing_rights_grants'],
        ['invoice_once'=>['invoice_id']]);
    license_financing_validate_index('license_financing_rights_grants','license_history',['license_id','id']);
    $grantColumns=array_column(all('SHOW FULL COLUMNS FROM '.table('license_financing_rights_grants')),'Field');
    $grantExpected=['id','license_id','invoice_id','allow_resale','allow_distribution','exclusive_resale',
        'exclusive_distribution','evidence','granted_by','created_at'];
    if($grantColumns!==$grantExpected)throw new RuntimeException('Incompatible financed-license rights-grant columns. Preserve the database for recovery.');
}

function license_financing_prepare(#[\SensitiveParameter]string $password):void{
    if(db()->inTransaction())throw new LogicException('Financing setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        if(support_current())throw new RuntimeException('Leave support mode before financing setup.');
        $actor=staff_owner($password);
        if(!license_ready())throw new RuntimeException('Prepare website licensing first.');
        recurring_billing_require();
        $version=(string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['license_financing_schema_version'])['value']??'');
        if(!in_array($version,['','1','2'],true))throw new RuntimeException('Unknown financed-license storage version.');
        $pending=(string)(setting('license_financing_schema_pending')??'');
        if(!in_array($pending,['','2'],true))throw new RuntimeException('Unknown financed-license setup history.');
        $schema=license_financing_schema(config()['db']['prefix']);
        $present=license_financing_storage_table_present('license_financing');
        $grantsPresent=license_financing_storage_table_present('license_financing_rights_grants');
        if(in_array($version,['1','2'],true)&&!$present)throw new RuntimeException('Established financed-license storage is missing. Restore the original database.');
        if($version==='2'&&!$grantsPresent)throw new RuntimeException('Established financing rights-grant storage is missing. Restore the original database.');
        if($version===''&&$pending===''&&($present||$grantsPresent))throw new RuntimeException('Unrecognized financed-license storage exists. No data was adopted.');
        if($version===''&&$pending===''){
            staff_transaction(function()use($actor):void{
                save_setting('license_financing_schema_pending','2');
                license_audit((int)$actor['id'],'financing.setup.begin','Financed-site licensing v2 preparation started');
            });
            $pending='2';
        }
        if($version===''&&$pending==='2'){
            foreach(['license_financing','license_financing_rights_grants'] as $name){
                if(license_financing_storage_table_present($name)
                    &&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0){
                    throw new RuntimeException('Interrupted financed-license preparation contains data. Preserve and review the database.');
                }
            }
        }
        if(!$present)db()->exec($schema['license_financing']);
        if(!$grantsPresent)db()->exec($schema['license_financing_rights_grants']);
        license_financing_validate_schema();
        staff_transaction(function()use($actor,$version):void{
            if((int)query('SELECT COUNT(*) FROM '.table('license_financing'))->fetchColumn()!==0
                &&!in_array($version,['1','2'],true)){
                throw new RuntimeException('Unrecognized financed-license records exist. No data was adopted.');
            }
            save_setting('license_financing_schema_version','2');
            save_setting('license_financing_schema_pending','');
            license_audit((int)$actor['id'],$version==='1'?'financing.upgrade':'financing.setup',
                $version==='1'
                    ?'Financed-site licensing upgraded additively from v1 to v2 rights evidence storage'
                    :'Additive financed-site license v2 storage verified');
        });
    });
}
