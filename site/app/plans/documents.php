<?php
/** Bounded canonical documents over the reviewed service-plan models; no database or authority grant. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/service-plans/ServicePlan.php';
require_once CWP_CODE_ROOT.'/includes/foundation/service-plans/PlanSubscription.php';
use CajunWebPro\Foundation\ServicePlans\{ServicePlan,PlanSubscription};

function service_plans_json(array $value):string {return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function service_plans_keys(array $value,array $keys):void {
    $actual=array_keys($value);sort($actual);sort($keys);
    if($actual!==$keys)throw new InvalidArgumentException('Unrecognized service-plan fields.');
}
function service_plans_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Invalid service-plan identity.');}
function service_plans_actor(mixed $id):void {if(!is_int($id) || $id<1 || $id>2147483647)throw new InvalidArgumentException('Invalid service-plan actor.');}
function service_plans_time(mixed $at):void {if(!is_int($at) || $at<0 || $at>253402300799)throw new InvalidArgumentException('Invalid service-plan time.');}
/** Application prices use the existing USD invoice range; no amount or term is invented. */
function service_plans_terms(array $terms):array {
    service_plans_keys($terms,['name','cadence','price','currency','inclusions','exclusions','limits','support']);
    foreach(['name','cadence','currency','support'] as $key)if(!is_string($terms[$key]))throw new InvalidArgumentException('Plan text must be a plain string.');
    if(strlen($terms['name'])>120 || !is_int($terms['price']) || $terms['price']<0 || $terms['price']>99999999 || $terms['currency']!=='USD')throw new InvalidArgumentException('Plan needs a bounded name and supported USD amount.');
    foreach(['inclusions','exclusions','limits'] as $key)if(!is_array($terms[$key]) || count($terms[$key])>32)throw new InvalidArgumentException('Plan terms exceed their bound.');
    // Preserve literal human terms; normalize only the order of named numeric meters.
    $limits=$terms['limits'];ksort($limits);
    $plan=ServicePlan::draft(str_repeat('0',32),$terms['name'],$terms['cadence'],$terms['price'],$terms['currency'],$terms['inclusions'],$terms['exclusions'],$limits,$terms['support'],'staff:1');
    return ['name'=>$plan->name,'cadence'=>$plan->cadence,'price'=>$plan->priceAmount,'currency'=>$plan->currency,'inclusions'=>$plan->inclusions,'exclusions'=>$plan->exclusions,'limits'=>$plan->limits,'support'=>$plan->supportExpectation];
}
function service_plans_model(string $id,int $owner,array $terms):ServicePlan {
    return ServicePlan::draft($id,$terms['name'],$terms['cadence'],$terms['price'],$terms['currency'],$terms['inclusions'],$terms['exclusions'],$terms['limits'],$terms['support'],'staff:'.$owner);
}
/** Mutable models are reconstructed for each read; callers never receive retained internal state. */
final readonly class ServicePlanDocument {
    public const MAX_EVENTS=256;
    public const MAX_BYTES=524288;
    private function __construct(private string $bytes){}
    public static function create(string $id,int $owner,int $at,array $terms):self {
        service_plans_id($id);service_plans_actor($owner);service_plans_time($at);
        return self::fromJson(service_plans_json(['format'=>1,'id'=>$id,'created_by'=>$owner,'created_at'=>$at,'terms'=>service_plans_terms($terms),'events'=>[]]));
    }
    public static function fromJson(string $bytes):self {
        if(strlen($bytes)>self::MAX_BYTES)throw new InvalidArgumentException('Service-plan history is too large.');
        $d=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($d))throw new InvalidArgumentException('Invalid service-plan document.');
        service_plans_keys($d,['format','id','created_by','created_at','terms','events']);
        if($d['format']!==1 || !is_string($d['id']) || !is_array($d['terms']) || !is_array($d['events']) || !array_is_list($d['events']) || count($d['events'])>self::MAX_EVENTS)throw new InvalidArgumentException('Invalid service-plan document.');
        service_plans_id($d['id']);service_plans_actor($d['created_by']);service_plans_time($d['created_at']);
        if(service_plans_terms($d['terms'])!==$d['terms'] || service_plans_json($d)!==$bytes)throw new InvalidArgumentException('Noncanonical service-plan document.');
        $model=self::replay($d);
        // Small fixed transition records must still fit after the last accepted draft edit.
        $reserve=match($model->status()){ServicePlan::DRAFT=>1024,ServicePlan::ACTIVE=>512,default=>0};
        if(strlen($bytes)>self::MAX_BYTES-$reserve)throw new InvalidArgumentException('Plan history needs space for publication and retirement. Create a new draft for more edits.');
        return new self($bytes);
    }
    private static function replay(array $d):ServicePlan {
        $plan=service_plans_model($d['id'],$d['created_by'],$d['terms']);$seen=[];$last=$d['created_at'];
        foreach($d['events'] as $index=>$event){
            if(!is_array($event))throw new InvalidArgumentException('Invalid plan event.');
            service_plans_keys($event,['id','command','actor','at','arguments']);
            if(!is_string($event['id']) || !is_string($event['command']) || !is_array($event['arguments']))throw new InvalidArgumentException('Invalid plan event.');
            service_plans_id($event['id']);service_plans_actor($event['actor']);service_plans_time($event['at']);
            if(isset($seen[$event['id']]) || $event['at']<$last)throw new InvalidArgumentException('Invalid plan event order or identity.');
            $seen[$event['id']]=true;$last=$event['at'];
            if($event['command']==='replace_draft'){
                if($index>=self::MAX_EVENTS-2)throw new LogicException('Draft history is full. Publish or retire this plan; create a new draft for further edits.');
                service_plans_keys($event['arguments'],['terms']);
                if($plan->status()!==ServicePlan::DRAFT || !is_array($event['arguments']['terms']))throw new LogicException('Only a draft plan can change its terms.');
                $terms=service_plans_terms($event['arguments']['terms']);
                if($terms!==$event['arguments']['terms'])throw new InvalidArgumentException('Noncanonical draft terms.');
                $plan=service_plans_model($d['id'],$d['created_by'],$terms);
            }else{
                if($event['arguments']!==[])throw new InvalidArgumentException('Invalid plan action arguments.');
                match($event['command']){'activate'=>$plan->activate(),'retire'=>$plan->retire(),default=>throw new InvalidArgumentException('Unknown plan action.')};
            }
        }
        return $plan;
    }
    public function append(string $id,string $command,int $actor,int $at,array $arguments):self {
        $d=$this->data();$event=['id'=>$id,'command'=>$command,'actor'=>$actor,'at'=>$at,'arguments'=>$arguments];
        foreach($d['events'] as $old)if($old['id']===$id){if($old!==$event)throw new InvalidArgumentException('Plan event identity already used.');return $this;}
        $d['events'][]=$event;return self::fromJson(service_plans_json($d));
    }
    public function prefix(int $revision):self {
        if($revision<0 || $revision>$this->revision())throw new InvalidArgumentException('Invalid plan revision.');
        $d=$this->data();$d['events']=array_slice($d['events'],0,$revision);return self::fromJson(service_plans_json($d));
    }
    public function data():array {return json_decode($this->bytes,true,32,JSON_THROW_ON_ERROR);}
    public function json():string {return $this->bytes;}
    public function fingerprint():string {return hash('sha256',$this->bytes);}
    public function revision():int {return count($this->data()['events']);}
    public function model():ServicePlan {return self::replay($this->data());}
}

/** Pin the whole reviewed active plan prefix, never a live mutable plan reference. */
final readonly class ServiceSubscriptionDocument {
    public const MAX_EVENTS=256;
    public const MAX_BYTES=1048576;
    /** Embedded publication and composite consent are separately bounded before replay. */
    public const MAX_CHECKOUT_BYTES=4194304;
    private function __construct(private string $bytes){}
    public static function create(string $id,int $client,int $owner,int $at,ServicePlanDocument $plan):self {
        return self::fromJson(service_plans_json(['format'=>1,'id'=>$id,'client_id'=>$client,'created_by'=>$owner,'created_at'=>$at,'plan'=>$plan->data(),'events'=>[]]));
    }
    public static function fromJson(string $bytes):self {
        if(strlen($bytes)>self::MAX_BYTES && (strlen($bytes)>self::MAX_CHECKOUT_BYTES || !str_starts_with($bytes,'{"format":2,')))throw new InvalidArgumentException('Subscription history is too large.');
        $d=json_decode($bytes,true,40,JSON_THROW_ON_ERROR);
        if(!is_array($d))throw new InvalidArgumentException('Invalid subscription document.');
        if(($d['format']??null)===2){self::replayCheckout($d,$bytes);return new self($bytes);}
        service_plans_keys($d,['format','id','client_id','created_by','created_at','plan','events']);
        if($d['format']!==1 || !is_string($d['id']) || !is_array($d['plan']) || !is_array($d['events']) || !array_is_list($d['events']) || count($d['events'])>self::MAX_EVENTS || service_plans_json($d)!==$bytes)throw new InvalidArgumentException('Invalid subscription document.');
        service_plans_id($d['id']);service_plans_actor($d['client_id']);service_plans_actor($d['created_by']);service_plans_time($d['created_at']);
        self::replay($d);return new self($bytes);
    }
    private static function replay(array $d):PlanSubscription {
        if($d['format']===2)return self::replayCheckout($d,service_plans_json($d));
        $plan=ServicePlanDocument::fromJson(service_plans_json($d['plan']));$planData=$plan->data();$events=$planData['events'];$lastPlan=$events?$events[array_key_last($events)]['at']:$planData['created_at'];
        if($d['created_at']<$lastPlan)throw new InvalidArgumentException('Subscription predates its offered plan.');
        $model=PlanSubscription::start($d['id'],$plan->model(),'client:'.$d['client_id'],'staff:'.$d['created_by'],gmdate('Y-m-d\TH:i:s\Z',$d['created_at']));
        return self::replayEvents($d,$model);
    }
    /** The checkout-only prefix is validated in full before any events are skipped here. */
    private static function replayEvents(array $d,PlanSubscription $model,array $seen=[],int $skip=0):PlanSubscription {
        foreach($d['events'] as $index=>$event){
            if($index<$skip)continue;
            if(!is_array($event))throw new InvalidArgumentException('Invalid subscription event.');
            service_plans_keys($event,['id','command','actor','at','arguments']);
            if(!is_string($event['id']) || !is_string($event['actor']) || !is_string($event['command']) || !is_array($event['arguments']) || !preg_match('/\A(staff|client):([1-9][0-9]{0,9})\z/',$event['actor'],$actor) || (int)$actor[2]>2147483647)throw new InvalidArgumentException('Invalid subscription event.');
            service_plans_id($event['id']);service_plans_time($event['at']);
            if(isset($seen[$event['id']]) || ($actor[1]==='client' && $event['actor']!=='client:'.$d['client_id']))throw new InvalidArgumentException('Invalid subscription event actor or identity.');
            $seen[$event['id']]=true;$at=gmdate('Y-m-d\TH:i:s\Z',$event['at']);$args=$event['arguments'];
            // Always reserve a final event for ending service without discarding history.
            if($index>=self::MAX_EVENTS-1 && !in_array($event['command'],['cancel','abandon'],true))throw new LogicException('Subscription history is full. End this enrollment before offering a new agreement.');
            if($event['command']==='consent'){
                service_plans_keys($args,['terms_hash']);
                if(!is_string($args['terms_hash']) || !preg_match('/\A[a-f0-9]{64}\z/',$args['terms_hash']))throw new InvalidArgumentException('Invalid agreed terms.');
                $model->recordConsent($event['actor'],$args['terms_hash'],$at);
            }elseif($event['command']==='cancel'){
                service_plans_keys($args,['reason']);
                if(!is_string($args['reason']) || trim($args['reason'])==='' || strlen($args['reason'])>1000 || !preg_match('//u',$args['reason']))throw new InvalidArgumentException('Cancellation needs a bounded reason.');
                $model->cancel($event['actor'],$at,$args['reason']);
            }else{
                if($args!==[])throw new InvalidArgumentException('Invalid subscription action arguments.');
                if(in_array($event['command'],['activate','pause','resume'],true) && $actor[1]!=='staff')throw new InvalidArgumentException('This subscription action requires staff.');
                // The application requires explicit agreement even for a one-time plan.
                if($event['command']==='activate' && $model->consent()===null)throw new LogicException('Client agreement is required before activation.');
                match($event['command']){'activate'=>$model->activate($event['actor'],$at),'pause'=>$model->pause($event['actor'],$at),'resume'=>$model->resume($event['actor'],$at),'abandon'=>$model->abandon($event['actor'],$at),default=>throw new InvalidArgumentException('Unknown subscription action.')};
            }
        }
        return $model;
    }

    /** Pure local composition only. Native storage must verify retained consent, actors and project ownership. */
    public static function createCheckout(string $id,HostingCheckoutConsent $consent,HostingCatalogPublication $publication,int $projectId,string $consentEventId,string $activationEventId):self {
        $c=$consent->data();$r=$consent->review()->data();$p=$publication->data();
        $checkout=['request_id'=>$c['request_id'],'request_input_sha256'=>$consent->inputHash(),'publication_id'=>$p['id'],'publication_sha256'=>$publication->fingerprint(),
            'publication_json'=>$publication->json(),'consent_sha256'=>$consent->fingerprint(),'consent_json'=>$consent->json(),
            'project_id'=>$projectId,'authorizer'=>$p['publisher']];
        $plan=ServicePlanDocument::fromJson($r['plan']['json']);
        $events=self::checkoutEvents($consentEventId,$activationEventId,$c['client_id'],$c['accepted_at'],$plan->model()->termsHash(),$consent->fingerprint());
        return self::fromJson(service_plans_json(['format'=>2,'id'=>$id,'client_id'=>$c['client_id'],'created_by_client'=>$c['client_id'],
            'created_at'=>$c['accepted_at'],'plan'=>$plan->data(),'checkout'=>$checkout,'events'=>$events]));
    }
    private static function checkoutEvents(string $consentId,string $activationId,int $client,int $at,string $termsHash,string $consentHash):array {
        service_plans_id($consentId);service_plans_id($activationId);
        if($consentId===$activationId)throw new InvalidArgumentException('Checkout events need distinct identities.');
        return [
            ['id'=>$consentId,'command'=>'consent','actor'=>'client:'.$client,'at'=>$at,'arguments'=>['terms_hash'=>$termsHash,'checkout_consent_sha256'=>$consentHash]],
            ['id'=>$activationId,'command'=>'checkout_activate','actor'=>'system:hosting-checkout','at'=>$at,'arguments'=>['consent_event_id'=>$consentId,'checkout_consent_sha256'=>$consentHash]]
        ];
    }
    /** Historical structural validation is not current owner/client authority or proof of retained SQL rows. */
    private static function replayCheckout(array $d,string $bytes):PlanSubscription {
        if(strlen($bytes)>self::MAX_CHECKOUT_BYTES)throw new InvalidArgumentException('Checkout subscription history is too large.');
        service_plans_keys($d,['format','id','client_id','created_by_client','created_at','plan','checkout','events']);
        if($d['format']!==2 || !is_string($d['id']) || !is_array($d['plan']) || !is_array($d['checkout']) || !is_array($d['events'])
            || !array_is_list($d['events']) || count($d['events'])<2 || count($d['events'])>self::MAX_EVENTS)throw new InvalidArgumentException('Invalid checkout subscription.');
        service_plans_id($d['id']);service_plans_actor($d['client_id']);service_plans_actor($d['created_by_client']);service_plans_time($d['created_at']);
        $x=$d['checkout'];service_plans_keys($x,['request_id','request_input_sha256','publication_id','publication_sha256','publication_json','consent_sha256','consent_json','project_id','authorizer']);
        foreach(['publication_json','consent_json'] as $key)if(!is_string($x[$key]))throw new InvalidArgumentException('Invalid retained checkout document.');
        service_plans_actor($x['project_id']);
        // Localized loading keeps all legacy standalone document includes independent of checkout files.
        require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout_document.php';
        $publication=HostingCatalogPublication::fromJson($x['publication_json']);
        $consent=HostingCheckoutConsent::fromJson($x['consent_json'],$publication);
        $p=$publication->data();$c=$consent->data();$r=$consent->review()->data();
        $plan=ServicePlanDocument::fromJson($r['plan']['json']);
        $expected=['request_id'=>$c['request_id'],'request_input_sha256'=>$consent->inputHash(),'publication_id'=>$p['id'],'publication_sha256'=>$publication->fingerprint(),
            'publication_json'=>$publication->json(),'consent_sha256'=>$consent->fingerprint(),'consent_json'=>$consent->json(),
            'project_id'=>$x['project_id'],'authorizer'=>$p['publisher']];
        if($x!==$expected || $d['client_id']!==$c['client_id'] || $d['created_by_client']!==$c['client_id'] || $d['created_at']!==$c['accepted_at']
            || $d['plan']!==$plan->data() || ($r['project']['mode']==='existing' && $x['project_id']!==$r['project']['id'])) {
            throw new InvalidArgumentException('Checkout agreement differs from its retained client consent, publication or project.');
        }
        $canonical=['format'=>2,'id'=>$d['id'],'client_id'=>$c['client_id'],'created_by_client'=>$c['client_id'],'created_at'=>$c['accepted_at'],
            'plan'=>$plan->data(),'checkout'=>$expected,'events'=>$d['events']];
        if(service_plans_json($canonical)!==$bytes)throw new InvalidArgumentException('Noncanonical checkout subscription.');
        foreach([0,1] as $index)if(!is_array($d['events'][$index]) || !is_string($d['events'][$index]['id']??null))throw new InvalidArgumentException('Invalid checkout event prefix.');
        $prefix=self::checkoutEvents($d['events'][0]['id'],$d['events'][1]['id'],$c['client_id'],$c['accepted_at'],$plan->model()->termsHash(),$consent->fingerprint());
        if(array_slice($d['events'],0,2)!==$prefix)throw new InvalidArgumentException('Checkout needs exact client consent followed by its automatic local activation.');
        $at=gmdate('Y-m-d\TH:i:s\Z',$c['accepted_at']);$client='client:'.$c['client_id'];
        $model=PlanSubscription::start($d['id'],$plan->model(),$client,$client,$at);
        $model->recordConsent($client,$plan->model()->termsHash(),$at);
        $model->activate('system:hosting-checkout',$at);
        return self::replayEvents($d,$model,array_fill_keys(array_column($prefix,'id'),true),2);
    }
    public function append(string $id,string $command,string $actor,int $at,array $arguments):self {
        $d=$this->data();$event=['id'=>$id,'command'=>$command,'actor'=>$actor,'at'=>$at,'arguments'=>$arguments];
        foreach($d['events'] as $old)if($old['id']===$id){if($old!==$event)throw new InvalidArgumentException('Subscription event identity already used.');return $this;}
        $d['events'][]=$event;return self::fromJson(service_plans_json($d));
    }
    public function data():array {return json_decode($this->bytes,true,40,JSON_THROW_ON_ERROR);}
    public function json():string {return $this->bytes;}
    public function fingerprint():string {return hash('sha256',$this->bytes);}
    public function revision():int {return count($this->data()['events']);}
    public function model():PlanSubscription {return self::replay($this->data());}
}
