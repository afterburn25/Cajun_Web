<?php
/** Authenticated exact-ID confirmation using the existing bounded inventory reader. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment.php';
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead as HostingConfirmationRead;

/** The caller owns the transaction. Cancellation cannot erase a purchased service. */
function hosting_fulfillment_confirmation_context(string $orderId,int $client,string $dispatchId,string $approvalHash,?int $actorId=null,#[\SensitiveParameter] ?string $password=null):array {
    if(!db()->inTransaction())throw new LogicException('Hosting confirmation needs its owning transaction.');
    $actor=service_orders_owner($password);hosting_records_owner();hosting_fulfillment_require();hosting_records_client($client,true);
    if($actorId!==null&&(int)$actor['id']!==$actorId)throw new RuntimeException('Current hosting confirmation access changed.');
    $order=service_orders_validate(service_orders_row($orderId));
    if($order->data()['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
    $link=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$orderId]);
    $approval=one('SELECT * FROM '.table('hosting_fulfillments').' WHERE id=? AND order_id=?',[$dispatchId,$orderId]);
    if(!$link||!$approval||!hash_equals($approval['binding_sha256'],$approvalHash))throw new RuntimeException('Review the original hosting approval before checking status.');
    hosting_fulfillment_history($order,$link);$binding=hosting_fulfillment_validate($approval,$order,$link);
    [$providerActor,$profile]=provider_checks_authority($binding['dispatch']['profile_id'],$binding['dispatch']['profile_revision'],$password);
    if((int)$providerActor['id']!==(int)$actor['id']||!hash_equals($binding['credential_sha256'],hash('sha256',$profile['credentials'])))throw new RuntimeException('Restore the original provider connection before confirming this service.');
    $result=one('SELECT result_json FROM '.table('hosting_operation_results').' WHERE id=?',[$dispatchId]);
    $data=$result?json_decode($result['result_json'],true,16,JSON_THROW_ON_ERROR):null;
    if(!$data||$data['outcome']!=='accepted_pending'||!is_string($data['resource_ref'])||!preg_match('/\A[1-9][0-9]{0,17}\z/',$data['resource_ref']))throw new RuntimeException('Only an original accepted package number can be confirmed. Recover a missing result first.');
    $journal=hosting_operations_validate(hosting_records_row($link['account_id'],$client));$account=$journal->account();
    $done=$account->providerRef()===$data['resource_ref'];
    if(!$done&&(!$account->awaitingReconciliation()||$account->pendingAttempt()!==$binding['dispatch']['attempt_ref']||$account->acknowledgedResourceRef()!==$data['resource_ref']))throw new RuntimeException('This original hosting attempt is no longer awaiting confirmation.');
    return ['actor'=>(int)$actor['id'],'binding'=>$binding,'result'=>$data,'profile'=>$profile,'done'=>$done];
}

/** Exact stored read and bounded package fields. No account-wide absence claim is made. */
function hosting_fulfillment_confirmation_proof(array $binding,array $result,string $refreshId):array {
    service_plans_id($refreshId);
    if(!provider_inventory_ready())throw new RuntimeException('Original provider inventory history is unavailable. Prepare or restore it before confirmation.');
    $refresh=one('SELECT f.id,f.profile_id,f.profile_revision,f.actor_id,f.started_epoch,f.operation,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.key_fingerprint FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$refreshId]);
    if(!$refresh||$refresh['profile_id']!==$binding['dispatch']['profile_id']||(int)$refresh['profile_revision']!==$binding['dispatch']['profile_revision']||$refresh['operation']!==HostingConfirmationRead::PACKAGES_LIST||(int)$refresh['started_epoch']<$result['at'])throw new RuntimeException('The saved status check does not match this original provider request.');
    foreach(['refresh_started'=>'Refresh '.$refreshId.' profile '.$refresh['profile_id'].' revision '.$refresh['profile_revision'],'refresh_finished'=>'Refresh '.$refreshId.' '.$refresh['state'].($refresh['reason']!==''?' '.$refresh['reason']:'')] as $action=>$detail){
        $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['provider.'.$action,$detail]);
        if(count($audit)!==1||(int)$audit[0]['user_id']!==(int)$refresh['actor_id'])throw new RuntimeException('Original authenticated status read history is missing. Restore it before continuing.');
    }
    $finished=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$refresh['finished_at'],new DateTimeZone('UTC'));
    if(!$finished||$finished->format('Y-m-d H:i:s')!==$refresh['finished_at']||$finished->getTimestamp()<(int)$refresh['started_epoch']||$finished->getTimestamp()>time())throw new RuntimeException('The original status check time is unavailable.');
    $package=one('SELECT package_id,name,names,enabled,created_at,created_epoch,type_ref FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$refreshId,$result['resource_ref']]);
    $observed=provider_inventory_observed($refresh['state']);
    if($observed&&($refresh['scope']!=='twenty-i|'.$refresh['profile_id'].'|'.HostingConfirmationRead::PACKAGES_LIST||!preg_match('/\A[a-f0-9]{64}\z/',$refresh['key_fingerprint'])))throw new RuntimeException('The original authenticated inventory context is unavailable.');
    $matches=$observed&&$package&&(string)$package['package_id']===$result['resource_ref']&&(int)$package['enabled']===1&&(string)$package['type_ref']===$binding['request']['params']['type']&&strtolower($package['name'])===$binding['request']['params']['domain_name'];
    if($matches){
        $names=json_decode($package['names'],true,16,JSON_THROW_ON_ERROR);
        $matches=is_array($names)&&array_is_list($names)&&in_array($binding['request']['params']['domain_name'],array_map(static fn($name)=>is_string($name)?strtolower($name):'',$names),true);
    }
    $reason=match(true){!$observed=>'read_unavailable',!$package=>'not_observed',(int)$package['enabled']!==1=>'not_enabled',!$matches=>'details_differ',default=>'confirmed'};
    $data=['refresh'=>$refresh,'package'=>$package];
    return ['data'=>$data,'sha256'=>hash('sha256',service_plans_json($data)),'observed_at'=>$finished->getTimestamp(),'outcome'=>$matches?'applied':'unknown','reason'=>$reason];
}

/** Retain and revalidate original proof after later refreshes, rotation or order cancellation. */
function hosting_fulfillment_confirmation_history(array $binding,array $result):void {
    foreach(all('SELECT observation_json FROM '.table('hosting_observations').' WHERE dispatch_id=? LIMIT 34',[$binding['id']]) as $row){
        $data=json_decode($row['observation_json'],true,16,JSON_THROW_ON_ERROR);
        if(!preg_match('/\Aprovider-refresh:([a-f0-9]{32})\z/',$data['evidence_ref'],$match))throw new RuntimeException('Restore the original authenticated hosting confirmation proof.');
        $proof=hosting_fulfillment_confirmation_proof($binding,$result,$match[1]);
        $detail='Order '.$binding['order_id'].' dispatch '.$binding['id'].' refresh '.$match[1].' '.$proof['reason'].' sha256 '.$proof['sha256'];
        $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['hosting_records.fulfillment_checked',$detail]);
        if(count($audit)!==1||(int)$audit[0]['user_id']!==(int)$proof['data']['refresh']['actor_id'])throw new RuntimeException('Original hosting status audit is missing. Restore its history.');
        if($proof['sha256']!==$data['evidence_sha256']||$proof['observed_at']!==$data['observed_at']||$proof['outcome']!==$data['outcome']||$data['resource_ref']!==($proof['outcome']==='applied'?$result['resource_ref']:null))throw new RuntimeException('Original hosting confirmation evidence changed. Restore its history.');
    }
}

function hosting_fulfillment_confirmation_prepare(#[\SensitiveParameter] string $password):void {
    staff_locked(function()use($password):void {
        service_orders_owner($password);hosting_records_password($password);hosting_fulfillment_require();
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_inventory_schema_version'])['value']??'';
        if($version===''&&one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',['provider.inventory_prepared']))throw new RuntimeException('Restore the original provider inventory marker before preparing status history.');
        provider_inventory_migrate($password);
    });
}

/** One explicit authenticated read. Internal seams cannot be selected through HTTP or settings. */
function hosting_fulfillment_confirm(string $orderId,int $client,string $dispatchId,string $approvalHash,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $read=null):string {
    service_plans_id($dispatchId);service_orders_hash($approvalHash);
    $context=recurring_billing_locked(fn()=>hosting_fulfillment_confirmation_context($orderId,$client,$dispatchId,$approvalHash,null,$password));
    if($context['done'])return 'already_confirmed';
    if(!provider_inventory_ready())throw new RuntimeException('Prepare provider status history first.');
    $binding=$context['binding'];$actor=$context['actor'];
    $scope=static function()use($orderId,$client,$dispatchId,$approvalHash,$actor):void {
        $check=static function()use($orderId,$client,$dispatchId,$approvalHash,$actor):void {
            $current=hosting_fulfillment_confirmation_context($orderId,$client,$dispatchId,$approvalHash,$actor);
            if($current['done'])throw new RuntimeException('This service was already confirmed.');
        };
        if(db()->inTransaction())$check();else recurring_billing_locked($check);
    };
    $receipt=provider_inventory_refresh_attempt($binding['dispatch']['profile_id'],$binding['dispatch']['profile_revision'],$password,$resolver,$read,HostingConfirmationRead::PACKAGES_LIST,$scope);
    return recurring_billing_locked(function()use($orderId,$client,$dispatchId,$approvalHash,$password,$actor,$receipt):string {
        $current=hosting_fulfillment_confirmation_context($orderId,$client,$dispatchId,$approvalHash,$actor,$password);
        if($current['done'])return 'already_confirmed';
        if($receipt['state']==='access_changed')throw new RuntimeException('Access changed during the status check. No hosting confirmation was recorded.');
        $b=$current['binding'];$result=$current['result'];$proof=hosting_fulfillment_confirmation_proof($b,$result,$receipt['id']);
        if($proof['observed_at']<time()-300)throw new RuntimeException('This status check is too old. Request a fresh observation.');
        if(provider_inventory_observed($receipt['state'])){
            $profile=$current['profile'];$secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
            try{if(!hash_equals(provider_inventory_fingerprint($secret),$proof['data']['refresh']['key_fingerprint']))throw new RuntimeException('The status check used a different credential context.');}
            finally{unset($secret);}
        }
        if($proof['outcome']==='applied'){
            $foreign=one('SELECT id FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=? AND client_id<>? LIMIT 1',[$b['dispatch']['profile_id'],$result['resource_ref'],$client]);
            if($foreign)throw new RuntimeException('This package has a conflicting client assignment. Review its ownership before confirming.');
        }
        hosting_reconciliation_record_locked($dispatchId,$b['dispatch_sha256'],$receipt['id'],['outcome'=>$proof['outcome'],'observed_at'=>$proof['observed_at'],'resource_ref'=>$proof['outcome']==='applied'?$result['resource_ref']:null,'evidence_ref'=>'provider-refresh:'.$receipt['id'],'evidence_sha256'=>$proof['sha256']]);
        hosting_records_audit($actor,'fulfillment_checked','Order '.$orderId.' dispatch '.$dispatchId.' refresh '.$receipt['id'].' '.$proof['reason'].' sha256 '.$proof['sha256']);
        return $proof['reason'];
    });
}
