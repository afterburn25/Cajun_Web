<?php
/** Native order setup forms and deliberately small client progress projection. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/order_links.php';
require_once CWP_CODE_ROOT.'/app/hosting/operations.php';
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment.php';
require_once CWP_CODE_ROOT.'/app/plans/forms.php';

/** GET never prepares storage. Missing established history is not an empty setup state. */
function hosting_order_links_context(string $orderId,int $client,bool $asClient=false):array {
    return recurring_billing_locked(function()use($orderId,$client,$asClient):array {
        if($asClient)service_plans_current_client($client);else {service_orders_owner();hosting_records_owner(false);}
        service_orders_require();$order=service_orders_validate(service_orders_row($orderId));
        if($order->data()['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        $origin=one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? LIMIT 1',['hosting_records.order_linked','Order '.$orderId.' account %']);
        $ready=true;
        foreach(['records','order_links'] as $family){
            $s=hosting_storage_state($family);
            if($s['version']==='1'&&$s['complete'])continue;
            if($origin||$s['version']!==''||one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',[$s['setup_action']]))throw new RuntimeException('Established hosting history is unavailable. Restore the original database before continuing.');
            $ready=false;
        }
        $row=$ready?one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$orderId]):null;
        if(!$row&&$origin)throw new RuntimeException('The original hosting association is missing. Restore the original database before continuing.');
        $progress=null;
        if($row){
            $saved=hosting_order_links_validate($row,$order);$journal=$saved['journal'];$b=$saved['binding'];
            // Later lifecycle states must also retain their exact dispatch/result evidence.
            if($journal->revision()>0){
                hosting_operations_require_ready();$journal=hosting_operations_validate(hosting_records_row($row['account_id'],$client));
                if(one('SELECT id FROM '.table('hosting_dispatches').' WHERE account_id=? LIMIT 1',[$row['account_id']])){
                    hosting_fulfillment_require();hosting_fulfillment_history($order,$row);
                }
            }
            $progress=['account_id'=>$row['account_id'],'created_at'=>$b['created_at'],'status'=>$journal->account()->status(),'order_cancelled'=>$order->state()['phase']==='cancelled'];
            if(in_array($progress['status'],['provisioning','failed'],true)&&$journal->account()->awaitingReconciliation()&&$journal->account()->acknowledgedResourceRef()===null)$progress['status']='unknown';
            if(!$asClient){
                $progress['package']=$b['package'];$progress['reseller_id']=$b['request']['params']['resellerId'];
                $progress['profile_current']=false;
                try{[, $profile]=provider_checks_authority($b['profile_id'],$b['profile_revision']);$progress['profile_current']=hash_equals($b['credential_sha256'],hash('sha256',$profile['credentials']));}
                catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
            }
        }
        // Never return credentials, provider identities, quotas or private policy text to a client.
        if($asClient)return ['ready'=>$ready,'progress'=>$progress];
        $profile=provider_settings_ready()?provider_settings_profile():null;
        $catalogOrigin=$row===null?hosting_catalog_agreement_origin($order->data()['agreement']['id'],$client):null;
        return ['ready'=>$ready,'progress'=>$progress,'profile'=>$profile,'order'=>$order,'catalog_origin'=>$catalogOrigin];
    });
}

/** Explicit password/confirmation POST only; interrupted fresh preparation is repeatable. */
function hosting_order_links_prepare(#[\SensitiveParameter] string $password):void {
    staff_locked(function()use($password):void {
        service_orders_owner($password);hosting_records_password($password,false);provider_settings_owner($password);
        // Check both families before any DDL so a missing association cannot cause partial repair.
        hosting_storage_prepare('records');hosting_storage_prepare('order_links');
        hosting_records_migrate($password);hosting_order_links_migrate($password);
    });
}

/** Named numeric units, no raw provider JSON and no invented/unbounded quota. */
function hosting_order_links_limit_fields():array {
    return ['websites'=>'Websites','disk_mb'=>'Disk space (MB)','bandwidth_mb'=>'Monthly transfer (MB)','mailboxes'=>'Mailboxes','databases'=>'Databases'];
}
function hosting_order_links_form_package(ServiceOrderDocument $order,array $input):\CajunWebPro\Foundation\Hosting\HostingPackage {
    $limits=[];
    foreach(hosting_order_links_limit_fields() as $name=>$label){
        $key='package_'.$name;$value=service_plans_form_text($input,$key,10,true);
        if($value!=='')$limits[$name]=service_plans_form_number([$key=>$value],$key,2147483647);
    }
    $type=service_plans_form_text($input,'package_type',18,true);
    if(!preg_match('/\A[1-9][0-9]{0,17}\z/',$type))throw new InvalidArgumentException('Enter the numeric package type shown in your provider account.');
    return new \CajunWebPro\Foundation\Hosting\HostingPackage('order-'.$order->data()['id'],service_plans_form_text($input,'package_label',120,true),$type,service_plans_form_text($input,'package_region',80,true),$limits);
}
function hosting_order_links_form_action(string $command,ServiceOrderDocument $order,int $client,#[\SensitiveParameter] string $password):void {
    service_plans_form_confirm($_POST);
    if($command==='prepare_service'){hosting_order_links_prepare($password);return;}
    if($command==='map_catalog'){
        $id=service_plans_form_id($_POST,'catalog_id');$mapping=recurring_billing_locked(function()use($id,$order):array {service_orders_owner();$m=provider_plan_catalog_lookup($id);provider_plan_catalog_order($m,$order,true);return $m;});
        if($mapping['sha256']!==service_plans_form_hash($_POST,'catalog_hash'))throw new RuntimeException('Reload the exact provider mapping.');
        hosting_order_links_create($order->data()['id'],$client,service_plans_form_number($_POST,'order_revision',127),service_plans_form_hash($_POST,'order_hash'),service_plans_form_id($_POST,'account_id'),provider_plan_catalog_package($mapping['configuration'],$order->data()['id']),$mapping['profile_id'],$mapping['profile_revision'],$mapping['configuration']['reseller_id'],$password,$id);return;
    }
    if($command!=='map_service')throw new InvalidArgumentException('Unknown hosting preparation action.');
    if(($_POST['mapping_only']??null)!=='1')throw new InvalidArgumentException('Confirm this is a reviewed local mapping and does not verify provider entitlement.');
    hosting_order_links_create($order->data()['id'],$client,service_plans_form_number($_POST,'order_revision',127),service_plans_form_hash($_POST,'order_hash'),service_plans_form_id($_POST,'account_id'),hosting_order_links_form_package($order,$_POST),service_plans_form_id($_POST,'profile_id'),service_plans_form_number($_POST,'profile_revision',2147483647),service_plans_form_number($_POST,'reseller_id',2147483647),$password);
}
