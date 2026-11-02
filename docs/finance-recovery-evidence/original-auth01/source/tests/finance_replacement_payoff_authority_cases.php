<?php
/** FIN-AUTH-01: actual client sessions, native committed revocation, synthetic ARB. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);

function frg_authority_writer(string $change):array{
    global $prefix,$root;
    if(!in_array($change,['version','deactivated'],true)||db()->inTransaction())throw new LogicException('Invalid authority writer boundary.');
    $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/finance_replacement_payoff_authority_native.php',$prefix,$root,'auth-writer',$change],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start authority revocation writer.');
    fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0||$stderr!=='')throw new RuntimeException('Authority writer failed: '.$stderr);
    $proof=json_decode($stdout,true,16,JSON_THROW_ON_ERROR);
    if($proof['pid']===getmypid()||$proof['connection']===(int)query('SELECT CONNECTION_ID()')->fetchColumn()||!$proof['committed']){
        throw new RuntimeException('Authority writer did not commit independently.');
    }
    return $proof;
}
function frg_authority_cases():void{
    global $password,$observations;
    $GLOBALS['fepGatewayId']=87000000;
    foreach(['healthy','stale_version','deactivated','idle_expired','login_expired','foreign_session','before_dispatch_revoked','after_dispatch_revoked'] as $kind){
        $savedClient=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');
        query('UPDATE '.table('studio_clients').' SET active=1,auth_version=? WHERE id=1',[str_repeat('1',32)]);
        $offer=fep_seed('authority-'.$kind,500);
        $GLOBALS['provider']=[];$GLOBALS['fepCalls']=[];$GLOBALS['providerScenario']='normal';$GLOBALS['oldGateway']='';
        $GLOBALS['postReservation']=$GLOBALS['afterOldRetirement']=$GLOBALS['afterCandidateRead']=null;
        \FinancePayoffRetirement\license_finance_subscription_create_profile($offer['id'],['customer_profile_id'=>'1001','payment_profile_id'=>'2001']);
        $old=(string)license_finance_subscription_row($offer['id'])['gateway_subscription_id'];$GLOBALS['oldGateway']=$old;$GLOBALS['provider'][$old]['status']='suspended';
        license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.suspended',$old);rb_client();$proof=null;
        if($kind==='stale_version')$proof=frg_authority_writer('version');
        elseif($kind==='deactivated')$proof=frg_authority_writer('deactivated');
        elseif($kind==='idle_expired')$_SESSION['client_last']=time()-1801;
        elseif($kind==='login_expired')$_SESSION['client_login']=time()-28801;
        elseif($kind==='foreign_session')rb_client(2);
        elseif($kind==='before_dispatch_revoked')$GLOBALS['afterOldRetirement']=static function()use(&$proof):void{$proof=frg_authority_writer('version');};
        elseif($kind==='after_dispatch_revoked')$GLOBALS['postReservation']=static function()use(&$proof):void{$proof=frg_authority_writer('version');};
        $before=fep_snapshot();$start=count($GLOBALS['fepCalls']);$result=null;$error=null;
        try{$result=\FinancePayoffRetirement\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');}
        catch(Throwable $caught){$error=get_class($caught).': '.$caught->getMessage();}
        $calls=array_slice($GLOBALS['fepCalls'],$start);$sub=license_finance_subscription_row($offer['id']);$after=fep_snapshot();
        if(in_array($kind,['healthy','after_dispatch_revoked'],true)){
            fep_ok('FIN-AUTH-01 '.$kind.' retains a legitimately admitted external outcome',
                $error===null&&$result==='pending_first_payment'&&$sub['gateway_subscription_id']!==$old
                &&$GLOBALS['provider'][$old]['status']==='canceled'&&$GLOBALS['provider'][(string)$sub['gateway_subscription_id']]['status']==='active');
        }elseif($kind==='before_dispatch_revoked'){
            fep_ok('FIN-AUTH-01 revocation before create stops the successor request',
                $proof!==null&&!array_filter($calls,fn($call)=>$call['method']==='ARBCreateSubscriptionRequest')&&$sub['gateway_subscription_id']===$old);
            fep_ok('FIN-AUTH-01 revocation retains already completed predecessor retirement',
                $GLOBALS['provider'][$old]['status']==='canceled'&&$before['invoice_payments']===$after['invoice_payments']);
        }else{
            fep_ok('FIN-AUTH-01 '.$kind.' refuses before any provider request',$error!==null&&$calls===[]);
            fep_ok('FIN-AUTH-01 '.$kind.' preserves financial reservation and license records',$after===$before);
        }
        fep_ok('FIN-AUTH-01 '.$kind.' releases native financial lock and transaction',
            !db()->inTransaction()&&(int)query('SELECT IS_FREE_LOCK(?)',['cwp-staff-'.substr(hash('sha256','cwp_test|'.config()['db']['prefix']),0,40)])->fetchColumn()===1);
        $observations[]=['family'=>'FIN-AUTH-01','case'=>$kind,'result'=>$result,'error'=>$error,'writer'=>$proof,'calls'=>$calls,'retained_subscription'=>$sub];
        query('UPDATE '.table('studio_clients').' SET active=?,auth_version=? WHERE id=1',[$savedClient['active'],$savedClient['auth_version']]);
        rb_client();
    }
}
