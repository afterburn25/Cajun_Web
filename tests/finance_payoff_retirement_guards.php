<?php
/** Additional native authorization/snapshot guards; included in the isolated fixture. */
declare(strict_types=1);
if(!defined('CWP_BOOT')||PHP_SAPI!=='cli')exit(2);
if(!function_exists('FinancePayoffRetirement\license_finance_subscription_payoff_retire'))return;
foreach((($argv[3]??'')==='owner_exit'?['revoked_owner']:['unpaid','status_only','cross_customer','wrong_password','changed_snapshot']) as $guard){
    $offer=fep_seed('guard-'.$guard,500);$license=(int)$offer['license_id'];
    query('UPDATE '.table('license_financing')." SET state='active' WHERE license_id=?",[$license]);
    $providerScenario='normal';$postReservation=null;$fepCalls=[];
    $created=\FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
    fep_ok($guard.' valid initial enrollment remains available',$created==='pending_first_payment');
    if($guard==='status_only')query('UPDATE '.table('license_financing')." SET state='paid_off' WHERE license_id=?",[$license]);
    if(in_array($guard,['cross_customer','revoked_owner','wrong_password','changed_snapshot'],true)){
        // Set up verified historical payoff through canonical invoice/ledger
        // helpers, then compute the permanent projection with the real sync.
        $doc=recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
        $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
        for($n=0;$n<3;$n++){
            $period=$terms->period($n);$issued=license_finance_issue_period($offer['id'],$n,strtotime($period['start'].' 12:00:00 UTC'));
            $invoice=ledger_invoice((int)$issued['invoice_id']);
            ledger_insert($invoice,1,'receipt',1000,'external','Synthetic historical payoff',gmdate('Y-m-d'));
            query('UPDATE '.table('studio_invoices')." SET status='paid' WHERE id=?",[$invoice['id']]);
        }
        license_financing_sync($license,null,true);
    }
    if($guard==='cross_customer')query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=1');
    $secret=null;
    if($guard==='wrong_password'){rb_staff();$secret='Incorrect synthetic owner password';}
    if($guard==='revoked_owner'){rb_staff();$secret=$password;query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');}
    $retirementMutation=null;
    if($guard==='changed_snapshot')$retirementMutation=static function()use($offer):void{
        // Committed independent PDO writer after terminal provider readback.
        $cfg=config()['db'];$writer=new PDO('mysql:host=127.0.0.1;port='.$cfg['port'].';dbname=cwp_test;charset=utf8mb4',$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $statement=$writer->prepare('UPDATE '.table('license_finance_subscriptions').' SET customer_profile_id=? WHERE offer_id=?');
        $statement->execute(['9999',$offer['id']]);$GLOBALS['retirementWriterCommits']=($GLOBALS['retirementWriterCommits']??0)+1;
    };
    $before=fep_snapshot();$start=count($fepCalls);
    if($guard==='revoked_owner'){
        // require_admin() deliberately exits after its real 303 login redirect.
        // This mode runs in a separate PHP process so denial cannot erase the
        // other scenarios' report. PHP closes its DB session on process exit.
        register_shutdown_function(static function()use($before,$start,$offer):void{
            global $checks,$fepCalls,$root;
            $after=fep_snapshot();
            fep_ok('revoked owner exits through actual login redirect',http_response_code()===303&&error_get_last()===null);
            fep_ok('revoked owner makes no provider request',array_slice($fepCalls,$start)===[]);
            fep_ok('revoked owner preserves subscription and payments',$before['license_finance_subscriptions']===$after['license_finance_subscriptions']&&$before['invoice_payments']===$after['invoice_payments']);
            fep_ok('revoked owner preserves permanent financing payoff',$before['license_financing']===$after['license_financing']);
            $failed=count(array_filter($checks,fn($c)=>!$c['passed']));
            echo json_encode(['passed'=>$failed===0,'count'=>count($checks),'failed'=>$failed,'checks'=>$checks,'php'=>PHP_VERSION,
                'database'=>query('SELECT VERSION()')->fetchColumn(),'subscription_sha256'=>hash_file('sha256',$root.'/site/app/billing/finance_subscription.php'),
                'scope'=>'Separate native PHP process; actual disabled-owner session check and login redirect. Synthetic agreement, historical receipts and provider setup. No real gateway or HTTP browser.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
        });
    }

    $result=\FinancePayoffRetirement\license_finance_subscription_payoff_retire($offer['id'],$secret);
    $after=fep_snapshot();$newCalls=array_slice($fepCalls,$start);$sub=license_finance_subscription_row($offer['id']);
    fep_ok($guard.' cannot falsely finalize payoff retirement',$result==='review'&&$sub['status']!== 'paid_off');
    fep_ok($guard.' preserves ledger and financing',$before['invoice_payments']===$after['invoice_payments']&&$before['license_financing']===$after['license_financing']);
    if($guard==='changed_snapshot'){
        fep_ok($guard.' preserves newer committed snapshot',$sub['customer_profile_id']==='9999'&&count($newCalls)===3);
    }else{
        fep_ok($guard.' does not call provider or alter subscription',$newCalls===[]&&$before['license_finance_subscriptions']===$after['license_finance_subscriptions']);
    }
    query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=1');query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');rb_client();
}
