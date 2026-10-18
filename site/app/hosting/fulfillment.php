<?php
/** Dedicated order authorization around the existing provider gateway and hosting history. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/operations.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_fulfillment_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/hosting_fulfillment_migrate.php';
use CajunWebPro\Foundation\Hosting\OperationResult as HostingFulfillmentResult;
use CajunWebPro\Foundation\Provider\{OperationResult as HostingProviderResult,ProviderOperation as HostingProviderOperation};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport as HostingFixedTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy as HostingEndpointPolicy;

function hosting_fulfillment_require():void {
    hosting_order_links_require();hosting_operations_require_ready();hosting_storage_require('reconciliation');hosting_storage_require('fulfillment');
    if(!provider_operations_ready())throw new RuntimeException('The existing provider operation ledger is unavailable.');
}
/** Explicit owner review, not an automated provider-catalog or entitlement assertion. */
function hosting_fulfillment_review(array $review,string $domain):array {
    service_plans_keys($review,['account_type','defaults','costs','confirmation']);
    if($review['account_type']!==true||$review['defaults']!==true||$review['costs']!==true||$review['confirmation']!=='CREATE '.$domain)throw new InvalidArgumentException('Review the exact provider account/type, package defaults and costs, and confirm the accepted domain.');
    return ['account_type'=>true,'defaults'=>true,'costs'=>true,'confirmation'=>'CREATE '.$domain];
}
function hosting_fulfillment_commercial(array $assessment):array {
    if(!$assessment['eligible'])throw new RuntimeException('Current client consent and verified payment or explicit manual fulfillment are required.');
    return ['basis'=>$assessment['basis'],'payment_review_sha256'=>$assessment['payment']['review_sha256'],'manual_event_id'=>$assessment['basis']==='owner-manual-policy'?$assessment['manual']['event_id']:null];
}
function hosting_fulfillment_input(string $orderId,int $client,int $revision,string $orderHash,string $linkHash,array $review):string {
    return service_plans_json(['order_id'=>$orderId,'client_id'=>$client,'order_revision'=>$revision,'order_sha256'=>$orderHash,'link_sha256'=>$linkHash,'review'=>$review]);
}
function hosting_fulfillment_audit_detail(array $row):string {
    return 'Order '.$row['order_id'].' account '.$row['account_id'].' dispatch '.$row['id'].' sha256 '.$row['binding_sha256'];
}
/** Original request, commercial snapshot and authorization remain immutable after cancellation. */
function hosting_fulfillment_validate(array $row,ServiceOrderDocument $order,array $link):array {
    if(!db()->inTransaction())throw new LogicException('Fulfillment validation needs its owning transaction.');
    try{
        if(strlen($row['binding_json'])>262144||hash('sha256',$row['binding_json'])!==$row['binding_sha256'])throw new RuntimeException('Approval digest differs.');
        $b=json_decode($row['binding_json'],true,24,JSON_THROW_ON_ERROR);
        service_plans_keys($b,['format','id','order_id','order_revision','order_sha256','link_sha256','account_id','client_id','actor_id','created_at','expires_at','dispatch','dispatch_sha256','provider_key','request','credential_sha256','commercial','configuration_review','review']);
        $saved=hosting_order_links_validate($link,$order);$original=$saved['binding'];
        $prefix=hosting_order_links_prefix($order,$b['order_revision'],$b['order_sha256']);
        $review=hosting_fulfillment_review($b['review'],$prefix->state()['domain_name']);
        if(service_plans_json($b)!==$row['binding_json']||$b['format']!==1||$b['id']!==$row['id']||$b['order_id']!==$row['order_id']||$b['order_id']!==$order->data()['id']||$b['account_id']!==$row['account_id']||$b['account_id']!==$link['account_id']||$b['client_id']!==$order->data()['client_id']||$b['actor_id']!==(int)$row['actor_id']||$b['created_at']!==(int)$row['created_epoch']||$b['expires_at']!==$b['created_at']+900||$b['link_sha256']!==$link['binding_sha256']||$b['credential_sha256']!==$original['credential_sha256']||$b['request']!==$original['request']||$b['configuration_review']!=='owner-panel-review'||$b['provider_key']!=='hosting-order:'.$row['id'])throw new RuntimeException('Approval identity differs.');
        if($row['input_json']!==hosting_fulfillment_input($b['order_id'],$b['client_id'],$b['order_revision'],$b['order_sha256'],$b['link_sha256'],$review))throw new RuntimeException('Approval input differs.');
        service_plans_keys($b['commercial'],['basis','payment_review_sha256','manual_event_id']);service_orders_hash($b['commercial']['payment_review_sha256']);
        $manual=$prefix->manualPolicy($b['created_at']);
        if(!in_array($b['commercial']['basis'],['verified-payment','owner-manual-policy'],true)||($b['commercial']['basis']==='verified-payment'&&$b['commercial']['manual_event_id']!==null)||($b['commercial']['basis']==='owner-manual-policy'&&(!$manual||$manual['event_id']!==$b['commercial']['manual_event_id'])))throw new RuntimeException('Original commercial approval differs.');
        $dispatch=one('SELECT * FROM '.table('hosting_dispatches').' WHERE id=?',[$row['id']]);
        if(!$dispatch||$dispatch['account_id']!==$row['account_id']||$dispatch['operation']!=='hosting.provision'||(int)$dispatch['actor_id']!==$b['actor_id']||$dispatch['binding_json']!==hosting_records_json($b['dispatch'])||hash('sha256',$dispatch['binding_json'])!==$b['dispatch_sha256']||(int)$dispatch['started_epoch']!==$b['created_at']||$b['dispatch']['profile_id']!==$original['profile_id']||$b['dispatch']['profile_revision']!==$original['profile_revision'])throw new RuntimeException('Original hosting dispatch differs.');
        hosting_operations_validate(hosting_records_row($row['account_id'],$b['client_id']));
        $result=one('SELECT result_json FROM '.table('hosting_operation_results').' WHERE id=?',[$row['id']]);
        if($result){
            $data=json_decode($result['result_json'],true,16,JSON_THROW_ON_ERROR);
            if($data['outcome']==='accepted_pending'){
                $provider=one('SELECT operation,status,external_id FROM '.table('provider_operations').' WHERE idempotency_key=?',[$b['provider_key']]);
                if(!$provider||$provider['operation']!=='packages.create'||$provider['status']!=='accepted_pending'||$provider['external_id']!==$data['resource_ref'])throw new RuntimeException('Original provider acknowledgement is missing or differs.');
                require_once CWP_CODE_ROOT.'/app/hosting/fulfillment_confirmation.php';hosting_fulfillment_confirmation_history($b,$data);
            }
        }
        $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['hosting_records.fulfillment_approved',hosting_fulfillment_audit_detail($row)]);
        if(count($audit)!==1||(int)$audit[0]['user_id']!==$b['actor_id'])throw new RuntimeException('Original fulfillment approval is missing.');
        return $b;
    }catch(Throwable $e){if($e instanceof PDOException)throw $e;throw new RuntimeException('Original hosting fulfillment history is unavailable. Restore the original database before continuing.',0,$e);}
}
/** Every order-derived provisioning attempt must retain its dedicated approval. */
function hosting_fulfillment_history(ServiceOrderDocument $order,array $link):void {
    $seen=[];
    $rows=all('SELECT * FROM '.table('hosting_fulfillments').' WHERE order_id=? LIMIT 129',[$order->data()['id']]);
    if(count($rows)>128)throw new RuntimeException('Hosting fulfillment history exceeds its bound.');
    foreach($rows as $row){hosting_fulfillment_validate($row,$order,$link);$seen[$row['id']]=true;}
    foreach(all('SELECT id FROM '.table('hosting_dispatches').' WHERE account_id=? AND operation=? LIMIT 129',[$link['account_id'],'hosting.provision']) as $dispatch)if(!isset($seen[$dispatch['id']]))throw new RuntimeException('An original order fulfillment approval is missing. Restore its history; do not resend the request.');
    $audits=all('SELECT detail FROM '.table('audit').' WHERE action=? AND detail LIKE ? LIMIT 129',['hosting_records.fulfillment_approved','Order '.$order->data()['id'].' account %']);
    if(count($audits)!==count($seen))throw new RuntimeException('Original order fulfillment audit history differs. Restore the original database.');
}
/** Only a newly committed request returns dispatch authority; replay never does. */
function hosting_fulfillment_claim(string $orderId,int $client,int $revision,string $orderHash,string $linkHash,string $request,array $review,#[\SensitiveParameter] string $password):?array {
    service_plans_id($request);service_orders_hash($orderHash);service_orders_hash($linkHash);
    return recurring_billing_locked(function()use($orderId,$client,$revision,$orderHash,$linkHash,$request,$review,$password):?array {
        $actor=service_orders_owner($password);hosting_records_owner();hosting_fulfillment_require();hosting_records_client($client,true);
        $order=service_orders_validate(service_orders_row($orderId));
        if($order->data()['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        $prefix=hosting_order_links_prefix($order,$revision,$orderHash);$review=hosting_fulfillment_review($review,$prefix->state()['domain_name']);
        $link=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=? FOR UPDATE',[$orderId]);
        if(!$link||$link['binding_sha256']!==$linkHash)throw new RuntimeException('Review the original hosting service mapping.');
        $mapping=hosting_order_links_validate($link,$order)['binding'];hosting_fulfillment_history($order,$link);
        $input=hosting_fulfillment_input($orderId,$client,$revision,$orderHash,$linkHash,$review);
        $old=one('SELECT * FROM '.table('hosting_fulfillments').' WHERE id=?',[$request]);
        if($old){
            if($old['input_json']!==$input||(int)$old['actor_id']!==(int)$actor['id'])throw new RuntimeException('This fulfillment identity was already used.');
            hosting_fulfillment_validate($old,$order,$link);return null;
        }
        if($order->revision()!==$revision||$order->fingerprint()!==$orderHash)throw new RuntimeException('Review the current accepted order before provisioning.');
        $commercial=hosting_fulfillment_commercial(service_orders_assess($order,time()));
        [$providerActor,$profile]=provider_checks_authority($mapping['profile_id'],$mapping['profile_revision'],$password);
        if((int)$providerActor['id']!==(int)$actor['id']||hash('sha256',$profile['credentials'])!==$mapping['credential_sha256'])throw new RuntimeException('The original reviewed provider context changed.');
        $account=hosting_records_row($link['account_id'],$client,true);$journal=hosting_operations_validate($account);
        if($journal->account()->status()!=='pending'||$journal->account()->pendingAttempt()!==null||$journal->account()->awaitingReconciliation())throw new RuntimeException('This hosting attempt is already started or requires explicit recovery.');
        $claim=hosting_operations_fence_locked($account,$request,'hosting.provision',[],$mapping['profile_id'],$mapping['profile_revision'],(int)$actor['id']);$at=$claim['binding']['started_epoch'];
        $b=['format'=>1,'id'=>$request,'order_id'=>$orderId,'order_revision'=>$revision,'order_sha256'=>$orderHash,'link_sha256'=>$linkHash,'account_id'=>$link['account_id'],'client_id'=>$client,'actor_id'=>(int)$actor['id'],'created_at'=>$at,'expires_at'=>$at+900,'dispatch'=>$claim['binding'],'dispatch_sha256'=>$claim['fingerprint'],'provider_key'=>'hosting-order:'.$request,'request'=>$mapping['request'],'credential_sha256'=>$mapping['credential_sha256'],'commercial'=>$commercial,'configuration_review'=>'owner-panel-review','review'=>$review];
        $bytes=service_plans_json($b);$row=['id'=>$request,'order_id'=>$orderId,'account_id'=>$link['account_id'],'actor_id'=>(int)$actor['id'],'created_epoch'=>$at,'input_json'=>$input,'binding_json'=>$bytes,'binding_sha256'=>hash('sha256',$bytes)];
        query('INSERT INTO '.table('hosting_fulfillments').' (id,order_id,account_id,actor_id,created_epoch,input_json,binding_json,binding_sha256) VALUES (?,?,?,?,?,?,?,?)',array_values($row));
        hosting_records_audit((int)$actor['id'],'fulfillment_approved',hosting_fulfillment_audit_detail($row));
        return hosting_fulfillment_validate($row,$order,$link);
    });
}
/** Called under the existing staff mutex before decryption, credential resolution and every send. */
function hosting_fulfillment_authorize(array $binding):array {
    return staff_transaction(function()use($binding):array {
        hosting_fulfillment_require();$actor=service_orders_owner();hosting_records_owner();hosting_records_client($binding['client_id'],true);
        if((int)$actor['id']!==$binding['actor_id']||time()>=$binding['expires_at'])throw new RuntimeException('Fulfillment approval expired or current authority changed.');
        $order=service_orders_validate(service_orders_row($binding['order_id']));
        $link=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$binding['order_id']]);
        $row=one('SELECT * FROM '.table('hosting_fulfillments').' WHERE id=?',[$binding['id']]);
        if(!$link||!$row||hosting_fulfillment_validate($row,$order,$link)!==$binding)throw new RuntimeException('Original fulfillment context changed.');
        hosting_fulfillment_history($order,$link);
        if($order->revision()!==$binding['order_revision']||$order->fingerprint()!==$binding['order_sha256']||hosting_fulfillment_commercial(service_orders_assess($order,time()))!==$binding['commercial'])throw new RuntimeException('Current order consent or commercial evidence changed.');
        [, $profile]=provider_checks_authority($binding['dispatch']['profile_id'],$binding['dispatch']['profile_revision']);
        if(hash('sha256',$profile['credentials'])!==$binding['credential_sha256'])throw new RuntimeException('Original provider credentials changed.');
        if(one('SELECT id FROM '.table('hosting_operation_results').' WHERE id=?',[$binding['id']]))throw new RuntimeException('The original attempt already has a result.');
        return $profile;
    });
}
/** Preserve bounded classifications only; a provider acknowledgement is never an active service. */
function hosting_fulfillment_result(array $binding,HostingProviderResult $result,?int $recordedAt=null):HostingFulfillmentResult {
    $subject=$binding['account_id'];$attempt=$binding['dispatch']['attempt_ref'];$at=$recordedAt??time();
    if($result->operation==='packages.create'){
        if($result->status===HostingProviderResult::ACCEPTED_PENDING&&$result->externalId!==null)return HostingFulfillmentResult::acceptedPending('hosting.provision',$result->externalId,$at,$subject,$attempt);
        if($result->status===HostingProviderResult::FAILED)return HostingFulfillmentResult::failed('hosting.provision','provider_rejected_request',true,$at,$subject,$attempt);
    }
    return HostingFulfillmentResult::timeout('hosting.provision',$at,$subject,$attempt);
}
/** Exact retained ledger proof; never reconstruct a missing or unresolved provider response. */
function hosting_fulfillment_retained_result(array $binding,array $provider,string $fingerprint):HostingFulfillmentResult {
    $claimed=(int)$provider['claimed_epoch'];$resolved=(int)$provider['resolved_epoch'];
    if($provider['idempotency_key']!==$binding['provider_key']||!hash_equals($fingerprint,$provider['fingerprint'])||$provider['operation']!=='packages.create'||(int)$provider['attempts']!==1||$claimed<$binding['created_at']||$resolved<$claimed||$resolved>time())throw new RuntimeException('The retained provider result does not match the original approved request.');
    $result=match($provider['status']){
        'accepted_pending'=>$provider['external_id']!==null?HostingProviderResult::acceptedPending('packages.create',$provider['external_id']):throw new RuntimeException('The original assigned package identity is missing.'),
        'failed'=>$provider['external_id']===null?HostingProviderResult::failed('packages.create','provider_rejected_request'):throw new RuntimeException('The original failed result is inconsistent.'),
        'uncertain'=>$provider['external_id']===null?HostingProviderResult::uncertain('packages.create'):throw new RuntimeException('The original unknown result is inconsistent.'),
        default=>throw new RuntimeException('The original provider request has no recoverable recorded outcome. Do not resend it.'),
    };
    return hosting_fulfillment_result($binding,$result,$resolved);
}
/**
 * Owner-reviewed local recovery only. No resolver, transport, gateway execution or ledger claim.
 * Recover a recorded fact even after commercial consent expires; this grants no new purchase.
 */
function hosting_fulfillment_recover(string $orderId,int $client,string $request,string $approvalHash,string $providerHash,#[\SensitiveParameter] string $password):string {
    service_plans_id($request);service_orders_hash($approvalHash);service_orders_hash($providerHash);
    return recurring_billing_locked(function()use($orderId,$client,$request,$approvalHash,$providerHash,$password):string {
        $actor=service_orders_owner($password);hosting_records_owner();hosting_fulfillment_require();hosting_records_client($client,false);
        $order=service_orders_validate(service_orders_row($orderId));
        if($order->data()['client_id']!==$client)throw new RuntimeException('Hosting order unavailable.');
        $link=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$orderId]);
        $approval=one('SELECT * FROM '.table('hosting_fulfillments').' WHERE id=? AND order_id=?',[$request,$orderId]);
        if(!$link||!$approval||!hash_equals($approval['binding_sha256'],$approvalHash))throw new RuntimeException('Review the original fulfillment approval before recovery.');
        $binding=hosting_fulfillment_validate($approval,$order,$link);hosting_fulfillment_history($order,$link);
        $provider=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=? FOR UPDATE',[$binding['provider_key']]);
        if(!$provider||!hash_equals(hash('sha256',service_plans_json($provider)),$providerHash))throw new RuntimeException('The reviewed original provider result is unavailable or changed.');
        [, $profile]=provider_checks_authority($binding['dispatch']['profile_id'],$binding['dispatch']['profile_revision'],$password);
        if(!hash_equals($binding['credential_sha256'],hash('sha256',$profile['credentials'])))throw new RuntimeException('Restore the original reviewed provider context before recovery.');
        $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
        try{
            $identity=$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($secret);
            $fingerprint=\CajunWebPro\Foundation\Provider\ProviderGateway::requestFingerprint('twenty-i',$identity,new HostingProviderOperation('packages.create',$binding['provider_key'],$binding['request']['params']));
        }finally{unset($secret);}
        $result=hosting_fulfillment_retained_result($binding,$provider,$fingerprint);
        $old=one('SELECT result_json FROM '.table('hosting_operation_results').' WHERE id=?',[$request]);
        if($old){
            $prior=json_decode($old['result_json'],true,16,JSON_THROW_ON_ERROR);
            if($prior['outcome']!==$result->outcome||$prior['resource_ref']!==$result->resourceRef)throw new RuntimeException('An existing hosting result must be preserved; use its evidence reconciliation path.');
            return 'already_recorded';
        }
        hosting_operations_record_locked($request,$binding['dispatch_sha256'],$result);
        hosting_records_audit((int)$actor['id'],'fulfillment_recovered','Order '.$orderId.' dispatch '.$request.' provider result '.$provider['id'].' sha256 '.$providerHash);
        return $result->outcome;
    });
}
/**
 * Application service used by the owner approval form; request input cannot override its dependencies.
 * Resolver/perform are fixed implementation dependencies, never HTTP/configuration inputs.
 * Local replay cannot reconstruct a dispatch claim or resend an incomplete/unknown purchase.
 */
function hosting_fulfillment_execute(string $orderId,int $client,int $revision,string $orderHash,string $linkHash,string $request,array $review,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Provider fulfillment cannot inherit a transaction.');
    $binding=hosting_fulfillment_claim($orderId,$client,$revision,$orderHash,$linkHash,$request,$review,$password);
    if($binding===null)return ['state'=>'replayed','attempt_started'=>false];
    $operation=new HostingProviderOperation('packages.create',$binding['provider_key'],$binding['request']['params']);
    try{$address=HostingEndpointPolicy::addresses([($resolver??[HostingFixedTransport::class,'resolve'])()])[0];}
    catch(Throwable $e){
        if($e instanceof PDOException)throw $e;
        $result=HostingFulfillmentResult::failed('hosting.provision','endpoint_unavailable_before_send',true,time(),$binding['account_id'],$binding['dispatch']['attempt_ref']);
        hosting_operations_record($binding['id'],$binding['dispatch_sha256'],$result);return ['state'=>'failed','attempt_started'=>false];
    }
    return staff_locked(function()use($binding,$operation,$address,$perform):array {
        $authorize=static function()use($binding):void{hosting_fulfillment_authorize($binding);};$enteredGateway=false;
        try{
            $profile=hosting_fulfillment_authorize($binding);
            $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
            try{$enteredGateway=true;$provider=($perform??'provider_operations_perform')($profile,$secret,$address,$authorize,$operation);}
            finally{unset($secret);}
            $result=$provider instanceof HostingProviderResult?hosting_fulfillment_result($binding,$provider):HostingFulfillmentResult::timeout('hosting.provision',time(),$binding['account_id'],$binding['dispatch']['attempt_ref']);
        }catch(Throwable $e){
            if($e instanceof PDOException)throw $e;
            $result=$enteredGateway?HostingFulfillmentResult::timeout('hosting.provision',time(),$binding['account_id'],$binding['dispatch']['attempt_ref']):HostingFulfillmentResult::failed('hosting.provision','authorization_or_credential_refused_before_send',true,time(),$binding['account_id'],$binding['dispatch']['attempt_ref']);
        }
        // Record reality even if access was revoked after the provider accepted the request.
        hosting_operations_record($binding['id'],$binding['dispatch_sha256'],$result);
        return ['state'=>$result->outcome,'attempt_started'=>$enteredGateway];
    });
}
