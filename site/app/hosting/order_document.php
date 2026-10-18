<?php
/** Canonical initial shared-hosting orders. No provider execution, settlement or authority grant. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/plans/documents.php';

function service_orders_domain(string $value):string {
    $name=strtolower(trim($value));
    if(str_ends_with($name,'.'))$name=substr($name,0,-1);
    if(strlen($name)<3||strlen($name)>253||!str_contains($name,'.')||filter_var($name,FILTER_VALIDATE_IP))throw new InvalidArgumentException('Enter the full ASCII hosting domain name.');
    foreach(explode('.',$name) as $label)if(!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/',$label))throw new InvalidArgumentException('Enter a domain name without a URL, path, port or wildcard.');
    $last=substr($name,strrpos($name,'.')+1);
    if(!preg_match('/[a-z]/',$last))throw new InvalidArgumentException('The hosting domain needs a named suffix.');
    return $name;
}
function service_orders_hash(mixed $hash):void {
    if(!is_string($hash)||!preg_match('/\A[a-f0-9]{64}\z/',$hash))throw new InvalidArgumentException('Keep the exact reviewed order fingerprint.');
}
/** Existing source records supply these identities; the document alone cannot authenticate them. */
function service_orders_binding(array $agreement,array $invoice):void {
    service_plans_keys($agreement,['id','revision','sha256','terms_hash']);
    if(!is_string($agreement['id'])||!is_int($agreement['revision'])||$agreement['revision']<1||$agreement['revision']>ServiceSubscriptionDocument::MAX_EVENTS)throw new InvalidArgumentException('Invalid agreed service revision.');
    service_plans_id($agreement['id']);service_orders_hash($agreement['sha256']);service_orders_hash($agreement['terms_hash']);
    service_plans_keys($invoice,['id','project_id','sha256','amount_cents','currency','basis','schedule_id','period_index']);
    service_plans_actor($invoice['id']);service_plans_actor($invoice['project_id']);service_orders_hash($invoice['sha256']);
    if(!is_int($invoice['amount_cents'])||$invoice['amount_cents']<0||$invoice['amount_cents']>99999999||$invoice['currency']!=='USD')throw new InvalidArgumentException('Invalid agreed invoice amount.');
    if($invoice['basis']==='one-time'){
        if($invoice['schedule_id']!==null||$invoice['period_index']!==null)throw new InvalidArgumentException('A one-time order cannot invent a recurring period.');
    }elseif($invoice['basis']==='initial-recurring-period'){
        if(!is_string($invoice['schedule_id'])||$invoice['period_index']!==0)throw new InvalidArgumentException('Initial hosting requires the first agreed recurring invoice.');
        service_plans_id($invoice['schedule_id']);
    }else throw new InvalidArgumentException('Unknown initial order invoice basis.');
}
final readonly class ServiceOrderDocument {
    public const MAX_EVENTS=128;
    public const MAX_BYTES=131072;
    private function __construct(private string $bytes){}
    public static function create(string $id,int $client,int $owner,int $at,array $agreement,array $invoice):self {
        return self::fromJson(service_plans_json(['format'=>1,'id'=>$id,'client_id'=>$client,'created_by'=>$owner,'created_at'=>$at,'service'=>'shared-hosting','agreement'=>$agreement,'invoice'=>$invoice,'events'=>[]]));
    }
    public static function fromJson(string $bytes):self {
        if(strlen($bytes)>self::MAX_BYTES)throw new InvalidArgumentException('Order history is too large.');
        $d=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($d))throw new InvalidArgumentException('Invalid service order.');
        service_plans_keys($d,['format','id','client_id','created_by','created_at','service','agreement','invoice','events']);
        if($d['format']!==1||$d['service']!=='shared-hosting'||!is_string($d['id'])||!is_array($d['agreement'])||!is_array($d['invoice'])||!is_array($d['events'])||!array_is_list($d['events'])||count($d['events'])>self::MAX_EVENTS||service_plans_json($d)!==$bytes)throw new InvalidArgumentException('Noncanonical service order.');
        service_plans_id($d['id']);service_plans_actor($d['client_id']);service_plans_actor($d['created_by']);service_plans_time($d['created_at']);
        service_orders_binding($d['agreement'],$d['invoice']);$state=self::replay($d);
        if(!in_array($state['phase'],['cancelled','withdrawn','declined'],true)&&strlen($bytes)>self::MAX_BYTES-2048)throw new InvalidArgumentException('Order history needs room for cancellation.');
        return new self($bytes);
    }
    private static function replay(array $d):array {
        $initial=$d;$initial['events']=[];$offeredHash=hash('sha256',service_plans_json($initial));
        $state=['phase'=>'offered','domain_name'=>null,'accepted_at'=>null,'manual'=>null];
        $last=$d['created_at'];$seen=[];
        foreach($d['events'] as $index=>$e){
            if(!is_array($e))throw new InvalidArgumentException('Invalid order event.');
            service_plans_keys($e,['id','command','actor','at','arguments']);
            if(!is_string($e['id'])||!is_string($e['command'])||!is_string($e['actor'])||!is_array($e['arguments'])||!preg_match('/\A(staff|client):([1-9][0-9]{0,9})\z/',$e['actor'],$actor)||(int)$actor[2]>2147483647)throw new InvalidArgumentException('Invalid order event actor.');
            service_plans_id($e['id']);service_plans_time($e['at']);
            if(isset($seen[$e['id']])||$e['at']<$last||($actor[1]==='client'&&$e['actor']!=='client:'.$d['client_id']))throw new InvalidArgumentException('Invalid order event sequence or client.');
            if(in_array($state['phase'],['cancelled','withdrawn','declined'],true))throw new LogicException('An ended service order cannot change.');
            if($index>=self::MAX_EVENTS-1&&!in_array($e['command'],['cancel','withdraw','decline'],true))throw new LogicException('Order history is full. Cancel this order; its history stays retained.');
            $seen[$e['id']]=true;$last=$e['at'];$a=$e['arguments'];
            if($e['command']==='accept'){
                service_plans_keys($a,['domain_name','review_sha256']);
                service_orders_hash($a['review_sha256']);
                if($state['phase']!=='offered'||$actor[1]!=='client'||!is_string($a['domain_name'])||service_orders_domain($a['domain_name'])!==$a['domain_name']||!hash_equals($offeredHash,$a['review_sha256']))throw new LogicException('The selected client must accept the exact offered service and invoice with a canonical domain.');
                $state['phase']='accepted';$state['domain_name']=$a['domain_name'];$state['accepted_at']=$e['at'];
            }elseif($e['command']==='allow_manual'){
                service_plans_keys($a,['reason','expires_at']);self::reason($a['reason']);service_plans_time($a['expires_at']);
                if($state['phase']!=='accepted'||$actor[1]!=='staff'||$a['expires_at']<=$e['at']||$a['expires_at']>$e['at']+86400)throw new LogicException('An owner-reviewed manual policy must expire within one day of its approval.');
                $state['manual']=['event_id'=>$e['id'],'approved_by'=>$e['actor'],'approved_at'=>$e['at'],'expires_at'=>$a['expires_at'],'reason'=>$a['reason']];
            }elseif($e['command']==='revoke_manual'){
                service_plans_keys($a,['reason']);self::reason($a['reason']);
                if($state['phase']!=='accepted'||$actor[1]!=='staff'||$state['manual']===null)throw new LogicException('No manual fulfillment policy can be revoked.');
                $state['manual']=null;
            }elseif(in_array($e['command'],['cancel','withdraw','decline'],true)){
                service_plans_keys($a,['reason']);self::reason($a['reason']);
                if($e['command']==='withdraw'&&($state['phase']!=='offered'||$actor[1]!=='staff'))throw new LogicException('Only the owner may withdraw an unaccepted offer.');
                if($e['command']==='decline'&&($state['phase']!=='offered'||$actor[1]!=='client'))throw new LogicException('Only the selected client may decline this offer.');
                if($e['command']==='cancel'&&$state['phase']!=='accepted')throw new LogicException('Cancel an accepted order; withdraw an unaccepted offer.');
                $state['phase']=match($e['command']){'cancel'=>'cancelled','decline'=>'declined',default=>'withdrawn'};$state['manual']=null;
            }else throw new InvalidArgumentException('Unknown service order action.');
        }
        return $state;
    }
    private static function reason(mixed $reason):void {
        if(!is_string($reason)||strlen(trim($reason))<10||strlen($reason)>500||!preg_match('//u',$reason)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$reason))throw new InvalidArgumentException('Explain this decision in 10–500 characters.');
    }
    public function append(string $id,string $command,string $actor,int $at,array $arguments):self {
        $d=$this->data();$e=['id'=>$id,'command'=>$command,'actor'=>$actor,'at'=>$at,'arguments'=>$arguments];
        foreach($d['events'] as $old)if($old['id']===$id){if($old!==$e)throw new InvalidArgumentException('This order event identity was already used.');return $this;}
        $d['events'][]=$e;return self::fromJson(service_plans_json($d));
    }
    public function data():array{return json_decode($this->bytes,true,16,JSON_THROW_ON_ERROR);}
    public function json():string{return $this->bytes;}
    public function fingerprint():string{return hash('sha256',$this->bytes);}
    public function revision():int{return count($this->data()['events']);}
    public function state():array{return self::replay($this->data());}
    public function manualPolicy(int $at):?array {
        service_plans_time($at);$p=$this->state()['manual'];
        return $p!==null&&$p['approved_at']<=$at&&$at<$p['expires_at']?$p:null;
    }
}
