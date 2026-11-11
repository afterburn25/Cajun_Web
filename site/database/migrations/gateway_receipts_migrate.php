<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/gateway_receipts_schema.php';

function gateway_receipts_prepare(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Gateway receipt setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=payments_owner($password);if(!payments_ready()||!ledger_ready())throw new RuntimeException('Prepare payments and invoice receipts first.');
        foreach(gateway_receipts_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            gateway_receipts_validate_schema();
        }
        staff_transaction(function()use($actor):void {
            if(setting('gateway_receipts_schema_version')!=='1'&&one('SELECT id FROM '.table('gateway_receipts').' LIMIT 1'))throw new RuntimeException('Unrecognized gateway receipts exist. Restore their original schema marker.');
            save_setting('gateway_receipts_schema_version','1');payments_audit((int)$actor['id'],'gateway_receipts.setup','Additive verified gateway receipt history version 1');
        });
    });
}
