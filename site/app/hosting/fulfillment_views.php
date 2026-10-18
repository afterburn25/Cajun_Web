<?php
/** Owner forms around the existing order execution and retained-result recovery services. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment_confirmation.php';
require_once CWP_CODE_ROOT.'/app/plans/forms.php';

/** Read-only readiness; lost established history is never a fresh setup invitation. */
function hosting_fulfillment_setup_ready():bool {
    $ready=true;
    foreach(['operations','reconciliation','fulfillment'] as $family){
        $s=hosting_storage_state($family);
        if($s['version']==='1'&&$s['complete'])continue;
        if($s['version']!==''||one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',[$s['setup_action']]))throw new RuntimeException('Established hosting fulfillment history is unavailable. Restore the original database before continuing.');
        $ready=false;
    }
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_operations_schema_version'])['value']??'';
    if(!provider_operations_ready()){
        if($version==='4'||($version===''&&one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',['provider.operations_setup'])))throw new RuntimeException('Original provider operation history is unavailable. Restore the original database before continuing.');
        $ready=false;
    }
    return $ready;
}
function hosting_fulfillment_prepare(#[\SensitiveParameter] string $password):void {
    staff_locked(function()use($password):void {
        service_orders_owner($password);hosting_records_password($password);provider_settings_owner($password);hosting_order_links_require();
        hosting_fulfillment_setup_ready();
        foreach(['operations','reconciliation','fulfillment'] as $family)hosting_storage_prepare($family);
        provider_operations_migrate($password);hosting_operations_migrate($password);hosting_reconciliation_migrate($password);hosting_fulfillment_migrate($password);
    });
}
/** Private owner projection only; never return raw credentials, provider messages or request JSON. */
function hosting_fulfillment_view(string $orderId,int $client):array {
    return recurring_billing_locked(function()use($orderId,$client):array {
        service_orders_owner();hosting_records_owner();hosting_order_links_require();
        $order=service_orders_validate(service_orders_row($orderId));
        if($order->data()['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        $link=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$orderId]);
        if(!$link)throw new RuntimeException('Prepare this order’s hosting record before fulfillment.');
        $saved=hosting_order_links_validate($link,$order);$binding=$saved['binding'];$journal=$saved['journal'];
        $ready=hosting_fulfillment_setup_ready();$profileCurrent=false;
        try{[, $profile]=provider_checks_authority($binding['profile_id'],$binding['profile_revision']);$profileCurrent=hash_equals($binding['credential_sha256'],hash('sha256',$profile['credentials']));}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
        $view=['ready'=>$ready,'confirmation_ready'=>provider_inventory_ready(),'order'=>$order,'link_hash'=>$link['binding_sha256'],'account_id'=>$link['account_id'],'profile_current'=>$profileCurrent,'package'=>$binding['package'],'reseller_id'=>$binding['request']['params']['resellerId'],'eligible'=>service_orders_assess($order,time())['eligible'],'mode'=>'prepare','hosting_revision'=>$journal->revision(),'dispatch'=>null];
        if(!$ready)return $view;
        hosting_fulfillment_require();hosting_fulfillment_history($order,$link);$journal=hosting_operations_validate(hosting_records_row($link['account_id'],$client));
        $view['hosting_revision']=$journal->revision();$account=$journal->account();
        $view['mode']=match($account->status()){'pending'=>'approve','failed'=>'failed','active'=>'active',default=>'history'};
        $dispatch=one('SELECT * FROM '.table('hosting_dispatches').' WHERE account_id=? AND operation=? ORDER BY revision DESC LIMIT 1',[$link['account_id'],'hosting.provision']);
        if(!$dispatch)return $view;
        $approval=one('SELECT * FROM '.table('hosting_fulfillments').' WHERE id=?',[$dispatch['id']]);
        if(!$approval)throw new RuntimeException('Restore the original hosting fulfillment approval.');
        $b=hosting_fulfillment_validate($approval,$order,$link);
        $result=one('SELECT result_json FROM '.table('hosting_operation_results').' WHERE id=?',[$dispatch['id']]);
        $view['dispatch']=['id'=>$dispatch['id'],'approval_hash'=>$approval['binding_sha256'],'created_at'=>$b['created_at'],'outcome'=>null,'package_id'=>null,'provider_hash'=>null,'confirmation'=>null];
        if($result){
            $r=json_decode($result['result_json'],true,16,JSON_THROW_ON_ERROR);$view['dispatch']['outcome']=$r['outcome'];$view['dispatch']['package_id']=$r['resource_ref'];
            if($r['outcome']==='accepted_pending'){
                $latest=one('SELECT observation_json FROM '.table('hosting_observations').' WHERE dispatch_id=? ORDER BY recorded_epoch DESC,id DESC LIMIT 1',[$dispatch['id']]);
                if($latest){$proof=json_decode($latest['observation_json'],true,16,JSON_THROW_ON_ERROR);$savedProof=hosting_fulfillment_confirmation_proof($b,$r,substr($proof['evidence_ref'],17));$view['dispatch']['confirmation']=['at'=>$savedProof['observed_at'],'reason'=>$savedProof['reason']];}
            }
            if($account->awaitingReconciliation()&&$account->pendingAttempt()===$dispatch['attempt_ref'])$view['mode']=$r['outcome']==='accepted_pending'?'pending_confirmation':'unknown';
        }elseif($account->pendingAttempt()===$dispatch['attempt_ref']){
            $view['mode']='unresolved';
            $p=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$b['provider_key']]);
            if($p&&$p['operation']==='packages.create'&&in_array($p['status'],['accepted_pending','failed','uncertain'],true)&&$p['resolved_epoch']!==null){
                $view['mode']='recover';$view['dispatch']['provider_hash']=hash('sha256',service_plans_json($p));$view['dispatch']['outcome']=$p['status'];
                // A candidate shown for review is verified again, with its original fingerprint, on POST.
                if($p['status']==='accepted_pending'&&is_string($p['external_id'])&&preg_match('/\A[1-9][0-9]{0,17}\z/',$p['external_id']))$view['dispatch']['package_id']=$p['external_id'];
            }
        }
        return $view;
    });
}
/** Test dependencies are internal call arguments; request input cannot select a transport or resolver. */
function hosting_fulfillment_form_action(string $command,ServiceOrderDocument $order,int $client,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):void {
    service_plans_form_confirm($_POST);
    if($command==='prepare_fulfillment'){hosting_fulfillment_prepare($password);return;}
    if($command==='prepare_confirmation'){hosting_fulfillment_confirmation_prepare($password);return;}
    if($command==='confirm_fulfillment'){
        hosting_fulfillment_confirm($order->data()['id'],$client,service_plans_form_id($_POST,'dispatch_id'),service_plans_form_hash($_POST,'approval_hash'),$password,$resolver,$perform);return;
    }
    if($command==='fulfill_service'){
        $review=['account_type'=>($_POST['review_account_type']??null)==='1','defaults'=>($_POST['review_defaults']??null)==='1','costs'=>($_POST['review_costs']??null)==='1','confirmation'=>service_plans_form_text($_POST,'provision_confirmation',270,true)];
        hosting_fulfillment_execute($order->data()['id'],$client,service_plans_form_number($_POST,'order_revision',127),service_plans_form_hash($_POST,'order_hash'),service_plans_form_hash($_POST,'link_hash'),service_plans_form_id($_POST,'event_id'),$review,$password,$resolver,$perform);return;
    }
    if($command==='recover_fulfillment'){
        hosting_fulfillment_recover($order->data()['id'],$client,service_plans_form_id($_POST,'dispatch_id'),service_plans_form_hash($_POST,'approval_hash'),service_plans_form_hash($_POST,'provider_hash'),$password);return;
    }
    if($command==='retry_fulfillment'){
        $view=hosting_fulfillment_view($order->data()['id'],$client);
        if($view['mode']!=='failed'||!$view['eligible'])throw new RuntimeException('Review this eligible order and its definite failed attempt before preparing a retry.');
        hosting_operations_retry_provision($view['account_id'],$client,service_plans_form_number($_POST,'hosting_revision',255),service_plans_form_id($_POST,'event_id'),$password);return;
    }
    throw new InvalidArgumentException('Unknown hosting fulfillment action.');
}
