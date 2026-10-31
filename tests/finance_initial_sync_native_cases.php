<?php
/** Included by the canonical native enrollment fixture; never runs against an installed customer database. */
declare(strict_types=1);
if(!defined('CWP_BOOT')||PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$syncCases=['paid','zero','other_agreement','foreign_customer','wrong_amount','wrong_currency','status_only','signature','initial_scalar','inactive_client','owner_changed','missing_ledger','missing_invoice','partial','reversed','suspended','underpaid_recovery','paid_off_reversed','paid_off_missing_invoice','paid_off_other_invoice','paid_off_missing_ledger','paid_off_owner_deactivated'];
foreach($syncCases as $kind){
    $name='sync_'.$kind;$offer=fie_seed($name,$kind==='zero'?0:500);$invoice=(int)$offer['activation_invoice_id'];$license=(int)$offer['license_id'];
    $paidOff=str_starts_with($kind,'paid_off_');$expectError=in_array($kind,['other_agreement','foreign_customer','wrong_amount','wrong_currency','status_only','signature','initial_scalar','inactive_client','owner_changed'],true);
    $expectedState=$paidOff?'paid_off':(in_array($kind,['missing_ledger','missing_invoice','partial','reversed','underpaid_recovery'],true)?'pending_initial':($kind==='suspended'?'suspended':'active'));
    if($paidOff)query('UPDATE '.table('license_financing')." SET state='paid_off',paid_off_at=? WHERE license_id=?",[gmdate('Y-m-d H:i:s'),$license]);
    elseif(in_array($kind,['partial','reversed','initial_scalar'],true))license_financing_sync($license);
    elseif(in_array($kind,['suspended','underpaid_recovery'],true))query('UPDATE '.table('license_financing')." SET state='suspended' WHERE license_id=?",[$license]);
    // The target agreement really has an unpaid balance before a different paid
    // invoice is substituted: retain a full reversal of its own original receipt.
    if(in_array($kind,['other_agreement','foreign_customer','wrong_amount','wrong_currency','reversed','partial','underpaid_recovery','paid_off_reversed','paid_off_other_invoice'],true)){
        $receipt=one('SELECT id FROM '.table('invoice_payments').' WHERE invoice_id=? AND kind=?',[$invoice,'receipt']);
        ledger_insert(ledger_invoice($invoice),1,'reversal',$kind==='partial'?250:500,'external','Synthetic sync reversal',gmdate('Y-m-d'),null,null,null,(int)$receipt['id']);
        query('UPDATE '.table('studio_invoices')." SET status='issued' WHERE id=?",[$invoice]);
    }
    $targetBalance=$invoice?ledger_balance(ledger_invoice($invoice)):null;
    switch($kind){
        case 'other_agreement':case 'paid_off_other_invoice':
            $donor=one('SELECT activation_invoice_id FROM '.table('license_finance_offers').' WHERE title=?',['Synthetic paid']);
            query('UPDATE '.table('license_finance_offers').' SET activation_invoice_id=? WHERE id=?',[$donor['activation_invoice_id'],$offer['id']]);break;
        case 'foreign_customer':case 'wrong_amount':case 'wrong_currency':
            $cents=$kind==='wrong_amount'?501:500;$currency=$kind==='wrong_currency'?'EUR':'USD';$job=$kind==='foreign_customer'?2:1;
            query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',
                [$job,'FIA-'.strtoupper(bin2hex(random_bytes(10))),'Synthetic mismatched initial invoice',$cents,$currency,'Initial payment for signed finance agreement '.$offer['id'].'. Financing terms and electronic acceptance are retained in the client portal.',gmdate('Y-m-d H:i:s')]);
            $other=(int)db()->lastInsertId();ledger_insert(ledger_invoice($other),1,'receipt',$cents,'external','Synthetic mismatched receipt',gmdate('Y-m-d'));
            query('UPDATE '.table('studio_invoices')." SET status='paid' WHERE id=?",[$other]);
            query('UPDATE '.table('license_finance_offers').' SET activation_invoice_id=? WHERE id=?',[$other,$offer['id']]);break;
        case 'status_only':query('UPDATE '.table('invoice_payments').' SET amount_cents=0 WHERE invoice_id=?',[$invoice]);break;
        case 'signature':query('UPDATE '.table('license_finance_offers').' SET signed_terms_sha256=? WHERE id=?',[str_repeat('f',64),$offer['id']]);break;
        case 'initial_scalar':query('UPDATE '.table('license_finance_offers').' SET initial_payment_cents=0 WHERE id=?',[$offer['id']]);break;
        case 'inactive_client':query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');break;
        case 'owner_changed':query('UPDATE '.table('website_licenses').' SET customer_id=2 WHERE id=?',[$license]);break;
        case 'missing_ledger':case 'paid_off_missing_ledger':save_setting('invoice_ledger_schema_version','');break;
        case 'missing_invoice':case 'paid_off_missing_invoice':query('UPDATE '.table('license_finance_offers').' SET activation_invoice_id=NULL WHERE id=?',[$offer['id']]);break;
        case 'paid_off_owner_deactivated':query('UPDATE '.table('website_licenses').' SET is_active=0 WHERE id=?',[$license]);break;
    }
    $before=fie_snapshot();$error=null;$result=null;
    try{$result=license_financing_sync($license,null,$kind==='underpaid_recovery');}catch(Throwable $e){if($e instanceof PDOException)throw $e;$error=$e->getMessage();}
    $after=fie_snapshot();$expected=$before;
    if(!$expectError){
        foreach($expected['license_financing'] as &$expectedPlan){
            if((int)$expectedPlan['license_id']!==$license)continue;
            if($expectedPlan['state']!==$expectedState){
                $expectedPlan['state']=$expectedState;
                foreach($after['license_financing'] as $actualPlan)if((int)$actualPlan['license_id']===$license)$expectedPlan['updated_at']=$actualPlan['updated_at'];
            }
        }unset($expectedPlan);
    }
    fie_ok($name.' expected verification outcome',($error!==null)===$expectError);
    fie_ok($name.' access state outcome',$expectError?$result===null:($result['state']??null)===$expectedState);
    fie_ok($name.' preserves all other financial, license, domain and rights records',$after===$expected);
    fie_ok($name.' closes its transaction',!db()->inTransaction());
    $cases[]=['name'=>$name,'error'=>$error,'result_state'=>$result['state']??null,'expected_state'=>$expectError?null:$expectedState,'target_original_balance'=>$targetBalance];
    query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');save_setting('invoice_ledger_schema_version','1');
}
