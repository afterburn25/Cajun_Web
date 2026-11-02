<?php
/** Actual adapter/settings bodies with synthetic credential unsealing and curl. */
declare(strict_types=1);
namespace FinanceRecoveryAdapter {
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
function payments_unseal(string $mode,string $payload):array{return json_decode($payload,true,16,JSON_THROW_ON_ERROR);}
function software_anet_production_enabled():bool{return true;}
function license_finance_anet_production_enabled():bool{return \setting('finance_gateway_production_reviewed')==='1';}
function function_exists(string $name):bool{return $name==='curl_init'||\function_exists($name);}
const CURLOPT_POST=1,CURLOPT_POSTFIELDS=2,CURLOPT_RETURNTRANSFER=3,CURLOPT_HTTPHEADER=4,CURLOPT_TIMEOUT=5,
    CURLOPT_CONNECTTIMEOUT=6,CURLOPT_SSL_VERIFYPEER=7,CURLOPT_SSL_VERIFYHOST=8,CURLOPT_FOLLOWLOCATION=9,CURLINFO_HTTP_CODE=10;
function curl_init(string $url):object{$GLOBALS['frgAdapterCalls'][]=['url'=>$url,'transaction'=>\db()->inTransaction()];return new \stdClass();}
function curl_setopt_array(object $handle,array $options):bool{$GLOBALS['frgAdapterBody']=json_decode($options[CURLOPT_POSTFIELDS],true,32,JSON_THROW_ON_ERROR);return true;}
function curl_exec(object $handle):string{return '{"messages":{"resultCode":"Ok"},"subscriptionId":"999999"}';}
function curl_getinfo(object $handle,int $field):int{return 200;}
function curl_close(object $handle):void{}
$frgAdapterSource=file_get_contents($root.'/site/app/billing/finance_authorize_net.php');
foreach(['license_finance_anet_settings','license_finance_anet_execute'] as $frgAdapterName){
    if(!preg_match('/^function '.$frgAdapterName.'\(.*?(?=^function |\z)/ms',$frgAdapterSource,$frgAdapterMatch))throw new \RuntimeException('Missing actual adapter body.');
    eval('namespace FinanceRecoveryAdapter; use \RuntimeException; use \InvalidArgumentException; '.$frgAdapterMatch[0]);
}
}
namespace {
function frg_gateway_writer(string $change):array{
    global $prefix,$root;
    $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/finance_replacement_payoff_authority_native.php',$prefix,$root,'gateway-writer',$change],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start gateway settings writer.');
    fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0||$stderr!=='')throw new RuntimeException('Gateway settings writer failed: '.$stderr);
    $proof=json_decode($stdout,true,16,JSON_THROW_ON_ERROR);
    if($proof['pid']===getmypid()||$proof['connection']===(int)query('SELECT CONNECTION_ID()')->fetchColumn()||!$proof['committed']){
        throw new RuntimeException('Gateway settings writer did not commit independently.');
    }
    return $proof;
}
function frg_gateway_cases():void{
    global $password,$observations;
    $originalMode=setting('software_gateway_mode','');$originalReview=setting('finance_gateway_production_reviewed','');
    foreach(['disabled','mode_changed','gate_disabled','account_changed','credential_rotated'] as $change){
        $credentials=json_encode(['api_login_id'=>'synthetic-adapter-account','transaction_key'=>'synthetic-adapter-key-1','public_client_key'=>'synthetic-adapter-public'],JSON_THROW_ON_ERROR);
        foreach(['production','sandbox'] as $mode)query('INSERT INTO '.table('payment_gateways').' (environment,credentials,revision,validation,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE credentials=VALUES(credentials),revision=VALUES(revision),validation=VALUES(validation),updated_at=VALUES(updated_at)',
            [$mode,$credentials,1,'authenticated',gmdate('Y-m-d H:i:s')]);
        save_setting('software_gateway_mode','production');save_setting('finance_gateway_production_reviewed','1');
        $context=\FinanceRecoveryAdapter\license_finance_anet_settings();
        $proof=frg_gateway_writer($change);$GLOBALS['frgAdapterCalls']=[];$GLOBALS['frgAdapterBody']=null;$error=null;$reply=null;
        try{$reply=\FinanceRecoveryAdapter\license_finance_anet_execute('ARBCreateSubscriptionRequest',['refId'=>'synthetic-admission'],$context);}
        catch(Throwable $caught){$error=$caught;}
        if($change==='credential_rotated'){
            fep_ok('FIN actual adapter accepts current credentials for the retained same account',
                $error===null&&$reply['subscriptionId']==='999999'&&count($GLOBALS['frgAdapterCalls'])===1
                &&$GLOBALS['frgAdapterBody']['ARBCreateSubscriptionRequest']['merchantAuthentication']['transactionKey']==='synthetic-adapter-key-2');
        }else{
            fep_ok('FIN actual adapter '.$change.' refuses before even synthetic curl',
                $error instanceof RuntimeException&&$GLOBALS['frgAdapterCalls']===[]&&$GLOBALS['frgAdapterBody']===null);
        }
        $observations[]=['family'=>'FIN actual adapter admission','change'=>$change,'writer'=>$proof,
            'synthetic_curl_count'=>count($GLOBALS['frgAdapterCalls']),'error'=>$error?get_class($error).': '.$error->getMessage():null,
            'source_sha256'=>hash_file('sha256',CWP_CODE_ROOT.'/app/billing/finance_authorize_net.php')];
    }
    save_setting('software_gateway_mode',(string)$originalMode);save_setting('finance_gateway_production_reviewed',(string)$originalReview);
    unset($GLOBALS['frgAdapterBody']);
    foreach(['disabled','mode_changed'] as $change){
        [$offer,$old,$invoices]=frg_suspended_seed('before-dispatch-'.$change);
        $GLOBALS['frgAfterTerminal']=static function()use($change):void{
            if($change==='disabled')$GLOBALS['frgDisabled']=true;else $GLOBALS['frgGatewayContext']=['mode'=>'sandbox'];
        };
        $start=count($GLOBALS['frgCalls']);rb_client();
        $result=\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');
        $attempt=license_finance_recovery_open($offer['id']);$calls=array_slice($GLOBALS['frgCalls'],$start);
        fep_ok('FIN '.$change.' between retirement and dispatch prevents create',
            $result==='review'&&!array_filter($calls,fn($call)=>$call['method']==='ARBCreateSubscriptionRequest'));
        fep_ok('FIN '.$change.' retains unsent reservation and actual predecessor retirement',
            $attempt&&$attempt['state']==='prepared'&&$attempt['dispatched_at']===null
            &&$attempt['predecessor_terminal_status']==='canceled'&&$GLOBALS['frgProvider'][$old]['status']==='canceled');
        $GLOBALS['frgDisabled']=false;$GLOBALS['frgGatewayContext']=[];rb_staff();
        $recovered=\FinanceRecovery\license_finance_subscription_reconcile($offer['id'],$old,$password);
        fep_ok('FIN '.$change.' owner can recover the exact unsent obligation',
            $recovered==='billing_suspended'&&license_finance_recovery_open($offer['id'])===null&&license_finance_subscription_row($offer['id'])['gateway_subscription_id']===$old);
    }
    [$offer,$old,$invoices]=frg_suspended_seed('repeat-account');rb_client();
    \FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');
    $first=(string)license_finance_subscription_row($offer['id'])['gateway_subscription_id'];
    $GLOBALS['frgProvider'][$first]['status']='suspended';
    \FinanceRecovery\license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.suspended',$first);
    $before=frg_safe_snapshot();$start=count($GLOBALS['frgCalls']);$GLOBALS['frgGatewayContext']=['login'=>'different-synthetic-account'];$refused=false;
    try{\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');}catch(RuntimeException){$refused=true;}
    fep_ok('FIN second replacement preserves the prior bound account before reservation or I/O',
        $refused&&count($GLOBALS['frgCalls'])===$start&&frg_safe_snapshot()===$before);
    $GLOBALS['frgGatewayContext']=['revision'=>2];
    $result=\FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'],1,'COMMON.ACCEPT.INAPP.PAYMENT','synthetic');
    fep_ok('FIN second replacement permits same-account credential rotation',
        $result==='pending_first_payment'&&(int)license_finance_subscription_row($offer['id'])['replacement_count']===2
        &&license_finance_subscription_history_row($first)['terminal_status']==='canceled');
}
}
