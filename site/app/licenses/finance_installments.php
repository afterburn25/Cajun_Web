<?php
/** Financed installment issuance and shared-host scheduled evaluation. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
require_once CWP_CODE_ROOT.'/database/migrations/license_finance_gateway_migrate.php';

use CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms;
use CajunWebPro\Foundation\RecurringBilling\RecurringBillingSchedule;

function license_finance_schedule_managed(string $scheduleId):bool{
    if(!license_finance_offers_ready())return false;
    return (bool)one('SELECT id FROM '.table('license_finance_offers').' WHERE schedule_id=? AND status=? LIMIT 1',[$scheduleId,'signed']);
}
function license_finance_offer_for_schedule(string $scheduleId,bool $lock=false):array{
    if(!license_finance_offers_ready())throw new RuntimeException('Finance agreements are not prepared.');
    $row=one('SELECT * FROM '.table('license_finance_offers').' WHERE schedule_id=? AND status=?'.($lock?' FOR UPDATE':''),[$scheduleId,'signed']);
    if(!$row)throw new RuntimeException('Signed finance agreement not found for this installment schedule.');
    license_finance_offer_terms($row);
    return $row;
}
function license_finance_offer_for_subscription(string $gatewayId,bool $lock=false):array{
    if(!preg_match('/^[0-9]{1,25}$/D',$gatewayId))throw new InvalidArgumentException('Invalid financing subscription ID.');
    $row=one('SELECT o.* FROM '.table('license_finance_subscriptions').' s JOIN '.table('license_finance_offers').' o ON o.id=s.offer_id WHERE s.gateway_subscription_id=?'.($lock?' FOR UPDATE':''),[$gatewayId]);
    if(!$row)throw new RuntimeException('Financing subscription is not linked to a signed agreement.');
    return $row;
}
/** Caller owns transaction. Mirrors recurring_billing_issue(), but authority is the signed financing agreement. */
function license_finance_issue_period_tx(array $offer,RecurringBillingSchedule $doc,int $periodIndex,int $at):array{
    if(!db()->inTransaction())throw new LogicException('Financing installment issuance requires its transaction.');
    if((string)$offer['status']!=='signed'||$periodIndex<0||$periodIndex>=(int)$offer['installments']){
        throw new RuntimeException('Financing installment is outside the signed agreement.');
    }
    $terms=RecurringBillingTerms::fromArray($doc->data()['terms']);$t=$terms->data();
    if($t['id']!==$offer['schedule_id']||(int)$t['client_id']!==(int)$offer['client_id']||(int)$t['project_id']!==(int)$offer['job_id']){
        throw new RuntimeException('Financing installment schedule ownership changed.');
    }
    $plan=one('SELECT * FROM '.table('license_financing').' WHERE license_id=? FOR UPDATE',[$offer['license_id']]);
    if(!$plan||(string)$plan['schedule_id']!==(string)$offer['schedule_id'])throw new RuntimeException('Financing license binding is missing.');
    if($plan['paid_off_at']!==null)return ['invoice_id'=>0,'paid_off'=>true];
    $existing=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index=? FOR UPDATE',
        [$offer['schedule_id'],$periodIndex]);
    if($existing){
        $invoice=recurring_billing_invoice_validate($existing,$doc);
        return ['invoice_id'=>(int)$invoice['id'],'replayed'=>true,'paid_off'=>false];
    }
    if(one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? LIMIT 1',
        ['recurring_billing.issued','staff:%; Schedule '.$offer['schedule_id'].' period '.$periodIndex.' invoice %'])){
        throw new RuntimeException('Financing installment has retained issuance history without its binding. Restore the original database.');
    }
    $assessment=$doc->assessment($periodIndex,$at);
    if(!$assessment['eligible'])throw new RuntimeException('This financing installment period is not eligible for invoicing yet.');
    $period=$assessment['period'];
    if((int)$period['amount_cents']!==(int)license_finance_offer_terms($offer)['installment_amount_cents']){
        throw new RuntimeException('Financing installment amount differs from the signed agreement.');
    }
    $actor='staff:'.(int)$offer['created_by'];$request=substr(hash('sha256','finance-period|'.$offer['id'].'|'.$periodIndex),0,32);
    $runningRevision=$doc->revision();$runningHash=$doc->fingerprint();
    $input=recurring_billing_invoice_input($offer['schedule_id'],(int)$offer['client_id'],$runningRevision,
        $runningHash,$periodIndex,$actor);
    $now=gmdate('Y-m-d H:i:s',$at);$reference='RI-'.strtoupper(bin2hex(random_bytes(12)));
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',
        [$offer['job_id'],$reference,$t['title'],$period['amount_cents'],$period['currency'],
         recurring_billing_invoice_instructions($offer['schedule_id'],$period),$now]);
    $invoice=(int)db()->lastInsertId();
    $snapshot=recurring_billing_json(['period'=>$period,'invoice'=>recurring_billing_invoice_snapshot(one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$invoice]))]);
    query('INSERT INTO '.table('recurring_billing_invoices').' (id,schedule_id,period_index,invoice_id,staff_id,issued_epoch,schedule_revision,schedule_sha256,request_sha256,snapshot_json,snapshot_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$request,$offer['schedule_id'],$periodIndex,$invoice,(int)$offer['created_by'],$at,$runningRevision,
         $runningHash,$input,$snapshot,hash('sha256',$snapshot)]);
    recurring_billing_audit($actor,'issued','Schedule '.$offer['schedule_id'].' period '.$periodIndex.' invoice '.$invoice);
    license_audit(0,'financing.installment.issued','Finance agreement '.$offer['id'].' period '.$periodIndex.' invoice '.$invoice);
    return ['invoice_id'=>$invoice,'replayed'=>false,'paid_off'=>false];
}
function license_finance_issue_period(string $offerId,int $periodIndex,?int $at=null):array{
    $at??=time();
    return staff_locked(fn()=>staff_transaction(function()use($offerId,$periodIndex,$at):array{
        $offer=license_finance_offer_row($offerId,null,true);
        if((string)$offer['status']!=='signed')throw new RuntimeException('Finance agreement is not signed.');
        $schedule=recurring_billing_row((string)$offer['schedule_id']);$doc=recurring_billing_validate($schedule);
        return license_finance_issue_period_tx($offer,$doc,$periodIndex,$at);
    }));
}
function license_finance_issue_due(string $offerId,?int $at=null):array{
    $at??=time();$issued=[];
    $preview=license_finance_offer_row($offerId);
    $prestate=license_financing_sync((int)$preview['license_id'],gmdate('Y-m-d',$at));
    if(($prestate['state']??null)==='pending_initial'){
        return ['issued'=>[],'state'=>'pending_initial','paid_off_at'=>null];
    }
    staff_locked(function()use($offerId,$at,&$issued):void{
        staff_transaction(function()use($offerId,$at,&$issued):void{
            $offer=license_finance_offer_row($offerId,null,true);
            if((string)$offer['status']!=='signed')return;
            $plan=one('SELECT * FROM '.table('license_financing').' WHERE license_id=? FOR UPDATE',[$offer['license_id']]);
            if(!$plan||$plan['paid_off_at']!==null)return;
            $schedule=recurring_billing_row((string)$offer['schedule_id']);$doc=recurring_billing_validate($schedule);
            if($doc->phase()!=='running')throw new RuntimeException('Signed financing schedule is not running.');
            $terms=RecurringBillingTerms::fromArray($doc->data()['terms']);$current=$terms->periodAt($at);
            if($current===null)return;
            $last=min((int)$current['index'],(int)$offer['installments']-1);
            for($period=0;$period<=$last;$period++){
                $result=license_finance_issue_period_tx($offer,$doc,$period,$at);
                if((int)($result['invoice_id']??0)>0)$issued[]=(int)$result['invoice_id'];
            }
        });
    });
    $plan=license_financing_sync((int)license_finance_offer_row($offerId)['license_id'],gmdate('Y-m-d',$at));
    return ['issued'=>array_values(array_unique($issued)),'state'=>$plan['state']??null,'paid_off_at'=>$plan['paid_off_at']??null];
}
function license_finance_scheduler_run(?int $at=null):array{
    $at??=time();
    if(!license_finance_offers_ready()||license_financing_feature_state()!==true)return ['state'=>'not_prepared','offers'=>0,'invoices'=>0,'suspended'=>0,'paid_off'=>0,'errors'=>0];
    $cursor=(string)setting('finance_scheduler_cursor','');
    if($cursor!==''&&!preg_match('/^[a-f0-9]{32}$/D',$cursor))$cursor='';
    $unfinished='f.paid_off_at IS NULL';
    if(license_finance_gateway_ready())$unfinished='('.$unfinished.' OR EXISTS (SELECT 1 FROM '.table('license_finance_subscriptions').
        " s WHERE s.offer_id=o.id AND s.status NOT IN ('paid_off','cancelled','sandbox_test')))";
    $rows=all('SELECT o.id FROM '.table('license_finance_offers').' o JOIN '.table('license_financing').' f ON f.license_id=o.license_id WHERE o.status=? AND '.$unfinished.' AND o.id>? ORDER BY o.id LIMIT 100',['signed',$cursor]);
    if(!$rows&&$cursor!==''){
        $cursor='';$rows=all('SELECT o.id FROM '.table('license_finance_offers').' o JOIN '.table('license_financing').' f ON f.license_id=o.license_id WHERE o.status=? AND '.$unfinished.' ORDER BY o.id LIMIT 100',['signed']);
    }
    $summary=['state'=>'complete','offers'=>0,'invoices'=>0,'suspended'=>0,'paid_off'=>0,'errors'=>0];
    $last=$cursor;
    foreach($rows as $row){$last=(string)$row['id'];
        try{
            $result=license_finance_issue_due((string)$row['id'],$at);$summary['offers']++;$summary['invoices']+=count($result['issued']);
            if($result['state']==='suspended')$summary['suspended']++;
            if($result['state']==='paid_off'){
                $summary['paid_off']++;
                if(license_finance_gateway_ready()){
                    require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
                    $sub=license_finance_subscription_row((string)$row['id']);
                    if($sub&&!in_array((string)$sub['status'],['paid_off','cancelled','sandbox_test'],true)
                        &&license_finance_subscription_payoff_retire((string)$row['id'])!=='paid_off'){
                        $summary['errors']++;$summary['state']='partial';
                    }
                }
            }
            if(license_finance_gateway_ready()){
                require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
                $retiring=license_finance_subscription_row((string)$row['id']);
                if($retiring&&(string)$retiring['status']==='retirement_pending'
                    &&license_finance_subscription_replacement_retire((string)$row['id'])==='review'){
                    $summary['errors']++;$summary['state']='partial';
                }
                $sub=one('SELECT status FROM '.table('license_finance_subscriptions').' WHERE offer_id=?',[$row['id']]);
                if(license_financing_recovery_link_allowed((string)($sub['status']??''))){
                    $offer=license_finance_offer_row((string)$row['id']);
                    foreach($result['issued'] as $invoiceId){
                        $invoice=ledger_invoice((int)$invoiceId);$balance=ledger_balance($invoice);
                        if($invoice['status']==='issued'&&(int)$balance['due_cents']>0){
                            payments_financing_request((int)$invoiceId,(int)$offer['created_by'],(int)$offer['payment_link_days'],(string)$offer['signature_evidence_hash']);
                        }
                    }
                }
            }
        }catch(Throwable $e){
            if($e instanceof PDOException)throw $e;
            report_error($e);$summary['errors']++;$summary['state']='partial';
        }
    }
    if($rows){
        save_setting('finance_scheduler_cursor',count($rows)<100?'':$last);
    }elseif($cursor!==''){
        save_setting('finance_scheduler_cursor','');
    }
    $observed=time();
    save_setting('finance_scheduler_last_run',(string)$observed);
    save_setting('finance_scheduler_last_state',(string)$summary['state']);
    save_setting('finance_scheduler_last_errors',(string)$summary['errors']);
    if($summary['state']==='complete')save_setting('finance_scheduler_last_success',(string)$observed);
    return $summary;
}
