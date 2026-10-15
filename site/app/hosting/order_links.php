<?php
/** Atomic order-derived local service identity. Mapping is not provider entitlement or dispatch authority. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/orders.php';
require_once CWP_CODE_ROOT.'/app/hosting/records.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_order_links_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/hosting_order_links_migrate.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage};
use CajunWebPro\Foundation\Provider\ProviderOperation;
use CajunWebPro\Foundation\TwentyI\Operations\{TwentyIRequestBuilder,TwentyIOperationRegister};

/** Exact addWeb fields only. Provider package defaults can still trigger mail, apps or paid options. */
function hosting_order_links_request(ServiceOrderDocument $order,HostingPackage $package,int $reseller):array {
    if($order->state()['phase']!=='accepted'||$reseller<1)throw new InvalidArgumentException('Choose an accepted order and explicit reseller identity.');
    // This also applies the existing bounded package contract; no invented quotas or prices.
    HostingJournal::create($order->data()['id'],$package,'client:'.$order->data()['client_id'],0);
    $params=['resellerId'=>$reseller,'type'=>$package->providerPackageType,'domain_name'=>$order->state()['domain_name']];
    $request=(new TwentyIRequestBuilder(new TwentyIOperationRegister()))->build(new ProviderOperation('packages.create','order-mapping-review',$params));
    return ['operation'=>'packages.create','params'=>$params,'method'=>$request->method,'url'=>$request->url,'body'=>$request->body];
}
function hosting_order_links_require():void {service_orders_require();hosting_storage_require('records');hosting_storage_require('order_links');}
/** Exact historical prefix, independently validated by the retained native order history. */
function hosting_order_links_prefix(ServiceOrderDocument $current,int $revision,string $hash):ServiceOrderDocument {
    if($revision<1||$revision>$current->revision())throw new RuntimeException('Original order acceptance is missing.');
    $data=$current->data();$data['events']=array_slice($data['events'],0,$revision);$prefix=ServiceOrderDocument::fromJson(service_plans_json($data));
    if($prefix->fingerprint()!==$hash||$prefix->state()['phase']!=='accepted')throw new RuntimeException('Original accepted hosting order differs.');return $prefix;
}
function hosting_order_links_audit_detail(array $row):string {
    return 'Order '.$row['id'].' account '.$row['account_id'].' client '.$row['client_id'].' sha256 '.$row['binding_sha256'];
}
/** Caller owns current authority and the native transaction; this never repairs saved rows. */
function hosting_order_links_validate(array $row,ServiceOrderDocument $order):array {
    if(!db()->inTransaction())throw new LogicException('Order association validation needs its owning transaction.');
    try{
        if(strlen($row['binding_json'])>262144||hash('sha256',$row['binding_json'])!==$row['binding_sha256'])throw new RuntimeException('Binding digest differs.');
        $b=json_decode($row['binding_json'],true,24,JSON_THROW_ON_ERROR);
        if(!is_array($b)||!in_array($b['format']??null,[1,2],true))throw new RuntimeException('Unsupported association format.');
        service_plans_keys($b,[$b['format']===2?'catalog':'format',...($b['format']===2?['format']:[]),'order_id','order_revision','order_sha256','account_id','client_id','created_by','created_at','profile_id','profile_revision','credential_sha256','package','request','commercial']);
        if(service_plans_json($b)!==$row['binding_json']||!in_array($b['format'],[1,2],true)||$b['order_id']!==$row['id']||$b['order_id']!==$order->data()['id']||$b['account_id']!==$row['account_id']||$b['client_id']!==(int)$row['client_id']||$b['client_id']!==$order->data()['client_id']||$b['created_by']!==(int)$row['created_by']||$b['created_at']!==(int)$row['created_epoch']||$b['profile_id']!==$row['profile_id']||$b['profile_revision']!==(int)$row['profile_revision'])throw new RuntimeException('Binding identity differs.');
        service_orders_hash($b['credential_sha256']);
        $prefix=hosting_order_links_prefix($order,$b['order_revision'],$b['order_sha256']);$pd=$prefix->data();
        if($pd['events'][array_key_last($pd['events'])]['at']>$b['created_at'])throw new RuntimeException('Binding predates consent.');
        service_plans_keys($b['package'],['sku','label','provider_type','region','limits']);$p=$b['package'];
        $package=new HostingPackage($p['sku'],$p['label'],$p['provider_type'],$p['region'],$p['limits']);
        if($b['request']!==hosting_order_links_request($prefix,$package,$b['request']['params']['resellerId']))throw new RuntimeException('Saved provider request differs.');
        service_plans_keys($b['commercial'],['basis','payment_review_sha256','manual_event_id']);
        service_orders_hash($b['commercial']['payment_review_sha256']);
        $manual=$prefix->manualPolicy($b['created_at']);
        if(!in_array($b['commercial']['basis'],['verified-payment','owner-manual-policy'],true)||($b['commercial']['basis']==='owner-manual-policy'&&($manual===null||$b['commercial']['manual_event_id']!==$manual['event_id']))||($b['commercial']['basis']==='verified-payment'&&$b['commercial']['manual_event_id']!==null))throw new RuntimeException('Commercial snapshot differs.');
        if($b['format']===2){
            service_plans_keys($b['catalog'],['id','sha256']);$catalog=provider_plan_catalog_lookup($b['catalog']['id']);provider_plan_catalog_order($catalog,$prefix,false);
            if($catalog['sha256']!==$b['catalog']['sha256']||$catalog['profile_id']!==$b['profile_id']||$catalog['profile_revision']!==$b['profile_revision']||$catalog['credential_sha256']!==$b['credential_sha256']||$catalog['configuration']['reseller_id']!==$b['request']['params']['resellerId']||provider_plan_catalog_package($catalog['configuration'],$b['order_id'])!=$package)throw new RuntimeException('Original catalog selection differs.');
        }
        $historical=one('SELECT enabled,has_credentials FROM '.table('provider_revisions').' WHERE profile_id=? AND revision=?',[$b['profile_id'],$b['profile_revision']]);
        if(!$historical||(int)$historical['enabled']!==1||(int)$historical['has_credentials']!==1)throw new RuntimeException('Original provider revision is missing.');
        $account=hosting_records_row($row['account_id'],$b['client_id']);$journal=hosting_records_journal($account);
        $expected=HostingJournal::create($row['account_id'],$package,'client:'.$b['client_id'],$b['created_at']);
        if($account['initial_json']!==$expected->json()||(int)$account['created_by']!==$b['created_by'])throw new RuntimeException('Original local hosting terms differ.');
        $origin=all('SELECT user_id,detail FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['hosting_records.order_linked',hosting_order_links_audit_detail($row)]);
        if(count($origin)!==1||(int)$origin[0]['user_id']!==$b['created_by'])throw new RuntimeException('Original association audit is missing.');
        return ['binding'=>$b,'journal'=>$journal];
    }catch(Throwable $e){if($e instanceof PDOException)throw $e;throw new RuntimeException('Order-to-service history is inconsistent. Restore the original database before continuing.',0,$e);}
}
/** Owner-reviewed local preparation. One order, one newly created hosting identity, one atomic audit. */
function hosting_order_links_create(string $orderId,int $client,int $revision,string $orderHash,string $accountId,HostingPackage $package,string $profileId,int $profileRevision,int $reseller,#[\SensitiveParameter] string $password,?string $catalogId=null):array {
    hosting_records_id($accountId);service_orders_hash($orderHash);
    return recurring_billing_locked(function()use($orderId,$client,$revision,$orderHash,$accountId,$package,$profileId,$profileRevision,$reseller,$password,$catalogId):array {
        $actor=service_orders_owner($password);hosting_records_owner();hosting_order_links_require();service_plans_client_row($client);
        [$providerActor,$profile]=provider_checks_authority($profileId,$profileRevision,$password);
        if((int)$providerActor['id']!==(int)$actor['id'])throw new RuntimeException('Current owner changed.');
        $order=service_orders_validate(service_orders_row($orderId));
        if($order->data()['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        $prefix=hosting_order_links_prefix($order,$revision,$orderHash);
        $catalog=null;
        if($catalogId!==null){
            $catalog=provider_plan_catalog_lookup($catalogId);provider_plan_catalog_order($catalog,$prefix,true);
            if($catalog['profile_id']!==$profileId||$catalog['profile_revision']!==$profileRevision||$catalog['configuration']['reseller_id']!==$reseller||provider_plan_catalog_package($catalog['configuration'],$orderId)!=$package)throw new RuntimeException('Reload the exact provider catalog selection.');
        }
        $request=hosting_order_links_request($prefix,$package,$reseller);$pd=json_decode(HostingJournal::create($accountId,$package,'client:'.$client,0)->json(),true)['package'];
        $old=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=? FOR UPDATE',[$orderId]);
        if($old){
            $saved=hosting_order_links_validate($old,$order);$b=$saved['binding'];
            if($b['order_revision']!==$revision||$b['order_sha256']!==$orderHash||$b['account_id']!==$accountId||$b['created_by']!==(int)$actor['id']||$b['profile_id']!==$profileId||$b['profile_revision']!==$profileRevision||$b['credential_sha256']!==hash('sha256',$profile['credentials'])||$b['package']!==$pd||$b['request']!==$request)throw new RuntimeException('This order already has a different local service mapping.');
            if(($b['catalog']['id']??null)!==$catalogId)throw new RuntimeException('Original provider catalog selection differs.');
            return $saved;
        }
        if($order->revision()!==$revision||$order->fingerprint()!==$orderHash)throw new RuntimeException('Review the current accepted order before mapping it.');
        $assessment=service_orders_assess($order,time());
        if(!$assessment['eligible'])throw new RuntimeException('Verified payment or a current explicit manual policy is required.');
        if(one('SELECT id FROM '.table('hosting_records').' WHERE id=?',[$accountId])||one('SELECT id FROM '.table('audit').' WHERE action=? AND (detail LIKE ? OR detail LIKE ?) LIMIT 1',['hosting_records.order_linked','Order '.$orderId.' account %','% account '.$accountId.' client %']))throw new RuntimeException('Existing service or association history cannot be adopted. Restore missing history.');
        hosting_records_create_locked($accountId,$client,$package,(int)$actor['id']);$account=hosting_records_row($accountId,$client);
        $at=(int)$account['created_epoch'];$manual=$assessment['manual'];
        $b=['format'=>1,'order_id'=>$orderId,'order_revision'=>$revision,'order_sha256'=>$orderHash,'account_id'=>$accountId,'client_id'=>$client,'created_by'=>(int)$actor['id'],'created_at'=>$at,'profile_id'=>$profileId,'profile_revision'=>$profileRevision,'credential_sha256'=>hash('sha256',$profile['credentials']),'package'=>$pd,'request'=>$request,'commercial'=>['basis'=>$assessment['basis'],'payment_review_sha256'=>$assessment['payment']['review_sha256'],'manual_event_id'=>$assessment['basis']==='owner-manual-policy'?$manual['event_id']:null]];
        if($catalog){$b=['catalog'=>['id'=>$catalog['id'],'sha256'=>$catalog['sha256']]]+$b;$b['format']=2;}
        $bytes=service_plans_json($b);$row=['id'=>$orderId,'account_id'=>$accountId,'client_id'=>$client,'created_by'=>(int)$actor['id'],'created_epoch'=>$at,'profile_id'=>$profileId,'profile_revision'=>$profileRevision,'binding_json'=>$bytes,'binding_sha256'=>hash('sha256',$bytes)];
        query('INSERT INTO '.table('hosting_order_links').' (id,account_id,client_id,created_by,created_epoch,profile_id,profile_revision,binding_json,binding_sha256) VALUES (?,?,?,?,?,?,?,?,?)',array_values($row));
        hosting_records_audit((int)$actor['id'],'order_linked',hosting_order_links_audit_detail($row));
        return hosting_order_links_validate($row,$order);
    });
}
/** No generic lifecycle adapter may bypass commercial rechecks for an order-derived account. */
function hosting_order_links_require_unlinked(string $accountId):void {
    $s=hosting_storage_state('order_links');
    $origin=one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? LIMIT 1',['hosting_records.order_linked','% account '.$accountId.' client %']);
    if($s['version']!==''||$s['existing']!==[]||$origin||one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',[$s['setup_action']])){
        hosting_storage_require('order_links');
        if($origin||one('SELECT id FROM '.table('hosting_order_links').' WHERE account_id=?',[$accountId]))throw new RuntimeException('This account requires its dedicated order fulfillment and lifecycle authorization.');
    }
}
/** Current owner review; original mapping remains readable after financial or connection changes. */
function hosting_order_links_review(string $orderId,int $client):array {
    return recurring_billing_locked(function()use($orderId,$client):array {
        service_orders_owner();hosting_records_owner();hosting_order_links_require();
        $order=service_orders_validate(service_orders_row($orderId));
        if($order->data()['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        $row=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$orderId]);
        if(!$row)throw new RuntimeException('The original order association is unavailable.');
        $saved=hosting_order_links_validate($row,$order);$b=$saved['binding'];
        $commercial=service_orders_assess($order,time());$profileCurrent=false;
        try{
            [, $profile]=provider_checks_authority($b['profile_id'],$b['profile_revision']);
            $profileCurrent=hash_equals($b['credential_sha256'],hash('sha256',$profile['credentials']));
        }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
        return ['binding'=>$b,'order_phase'=>$order->state()['phase'],'service_state'=>$saved['journal']->account()->status(),'commercial'=>$commercial,'profile_current'=>$profileCurrent,'provider_entitlement_verified'=>false,'dispatch_authorized'=>false];
    });
}
