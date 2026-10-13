<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function ledger_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!payments_ready())throw new RuntimeException('Enable payment preparation first.');
        foreach(ledger_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                catalog_validate_schema($name,$sql,['request_key'=>['request_key'],'reversal_of'=>['reversal_of']]);
                foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['external_hash','invoice_hash','request_key','request_hash'],true)&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible invoice payment identifiers.');
            }else db()->exec($sql);
        }
        staff_transaction(function() use($actor): void {
            if(!ledger_ready()){
                if((int)query('SELECT COUNT(*) FROM '.table('invoice_payments'))->fetchColumn())throw new RuntimeException('Unrecognized invoice payment records exist. No data was adopted.');
                $last=0;$count=0;
                do{
                    $rows=all('SELECT id FROM '.table('studio_invoices')." WHERE status='paid' AND id>? ORDER BY id LIMIT 100",[$last]);
                    foreach($rows as $r){
                        $i=ledger_invoice((int)$r['id']);$last=(int)$i['id'];
                        if($i['currency']!=='USD'||(int)$i['amount_cents']<1||(int)$i['amount_cents']>99999999)throw new RuntimeException('A legacy paid invoice is outside the supported USD range. No balances were imported.');
                        ledger_insert($i,(int)$actor['id'],'legacy',(int)$i['amount_cents'],'legacy',$i['paid_note']?:'Legacy paid balance imported; no new payment verified',$i['paid_at']?substr($i['paid_at'],0,10):'');$count++;
                    }
                }while(count($rows)===100);
                save_setting('invoice_ledger_schema_version','1');payments_audit((int)$actor['id'],'ledger.setup','Imported '.$count.' legacy paid balances; no new payments or gateway evidence');
            }else payments_audit((int)$actor['id'],'ledger.setup','Schema verified; retained balances unchanged');
        });
    });
}
/** Append under the shared mutex/transaction. There is deliberately no entry update/delete operation. */
