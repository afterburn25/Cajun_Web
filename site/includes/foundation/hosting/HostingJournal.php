<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\Hosting;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/HostingAccount.php';
use InvalidArgumentException;
use JsonException;

/**
 * Versioned, bounded command journal. Reconstructs the canonical model without
 * PHP object deserialization, reflection, arbitrary methods or provider calls.
 * This is not persistence, authorization, a signature or a dispatch capability.
 */
final readonly class HostingJournal
{
    public const MAX_EVENTS=512;
    public const MAX_BYTES=1048576;
    private function __construct(private string $json) {}

    public static function create(string $id,HostingPackage $package,string $clientRef,int $createdAt):self
    {
        $limits=$package->limits;ksort($limits,SORT_STRING);
        $document=['format'=>1,'id'=>$id,'client_ref'=>$clientRef,'created_at'=>$createdAt,
            'package'=>['sku'=>$package->sku,'label'=>$package->label,'provider_type'=>$package->providerPackageType,'region'=>$package->region,'limits'=>$limits],
            'events'=>[]];
        return self::fromJson(self::encode($document),$id,$clientRef);
    }

    /** Expected identity comes from trusted storage selection, never the saved JSON alone. */
    public static function fromJson(string $json,string $expectedId,string $expectedClientRef):self
    {
        if(strlen($json)>self::MAX_BYTES)throw new InvalidArgumentException('Hosting journal is too large.');
        try{$document=json_decode($json,true,16,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);}
        catch(JsonException){throw new InvalidArgumentException('Invalid hosting journal JSON.');}
        self::keys($document,['format','id','client_ref','created_at','package','events']);
        if($document['format']!==1)throw new InvalidArgumentException('Unsupported hosting journal format.');
        self::identity($document['id']);self::identity($document['client_ref']);self::integer($document['created_at']);
        if($document['id']!==$expectedId||$document['client_ref']!==$expectedClientRef)throw new InvalidArgumentException('Hosting journal identity does not match the selected account.');
        self::package($document['package']);
        if(!is_array($document['events'])||!array_is_list($document['events'])||count($document['events'])>self::MAX_EVENTS)throw new InvalidArgumentException('Invalid hosting journal event list.');
        if(self::encode($document)!==$json)throw new InvalidArgumentException('Hosting journal must use its canonical encoding.');
        self::replay($document);
        return new self($json);
    }

    public function json():string{return $this->json;}
    /** Integrity comparison only; this hash does not authenticate a writer or provider result. */
    public function fingerprint():string{return hash('sha256',$this->json);}
    public function revision():int{return count($this->document()['events']);}
    /** A fresh independent model: callers cannot change the retained journal by mutating it. */
    public function account():HostingAccount{return self::replay($this->document());}

    /** Stable event replay is idempotent; changed reuse is refused. Never performs an external action. */
    public function append(string $id,string $command,string $actor,int $at,array $arguments=[]):self
    {
        $document=$this->document();$event=['id'=>$id,'command'=>$command,'actor'=>$actor,'at'=>$at,'arguments'=>$arguments];
        self::event($event,$document['created_at']);
        // Canonical serialization detaches any caller-held references before storage.
        $event=json_decode(self::encode($event),true,16,JSON_THROW_ON_ERROR);
        foreach($document['events'] as $existing)if($existing['id']===$id){
            if($existing!==$event)throw new InvalidArgumentException('Hosting event identity was already used.');
            return $this;
        }
        $document['events'][]=$event;
        return self::fromJson(self::encode($document),$document['id'],$document['client_ref']);
    }

    private function document():array{return json_decode($this->json,true,16,JSON_THROW_ON_ERROR);}
    private static function encode(array $value):string
    {
        try{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
        catch(JsonException){throw new InvalidArgumentException('Hosting journal requires valid UTF-8 values.');}
    }
    private static function keys(mixed $value,array $keys):void
    {
        if(!is_array($value)||array_keys($value)!==$keys)throw new InvalidArgumentException('Invalid hosting journal fields.');
    }
    private static function text(mixed $value,int $max=256):void
    {
        if(!is_string($value)||$value===''||strlen($value)>$max||trim($value)!==$value||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1F\x7F]/',$value))throw new InvalidArgumentException('Invalid hosting journal text.');
    }
    private static function identity(mixed $value):void
    {
        if(!is_string($value)||!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/',$value))throw new InvalidArgumentException('Invalid hosting journal identity.');
    }
    private static function integer(mixed $value):void
    {
        if(!is_int($value)||$value<0)throw new InvalidArgumentException('Hosting journal requires a nonnegative integer.');
    }
    private static function nullableText(mixed $value):void{if($value!==null)self::text($value);}
    private static function boolean(mixed $value):void{if(!is_bool($value))throw new InvalidArgumentException('Hosting journal requires an exact boolean.');}
    private static function package(mixed $value):HostingPackage
    {
        self::keys($value,['sku','label','provider_type','region','limits']);
        foreach(['sku','label','provider_type','region'] as $key)self::text($value[$key]);
        if(!is_array($value['limits'])||!$value['limits']||count($value['limits'])>64)throw new InvalidArgumentException('Invalid hosting journal quotas.');
        foreach($value['limits'] as $key=>$limit){
            if(!is_string($key)||!preg_match('/\A[A-Za-z][A-Za-z0-9._-]{0,63}\z/',$key))throw new InvalidArgumentException('Invalid hosting quota name.');
            if($limit!==null)self::integer($limit);
        }
        $limits=$value['limits'];ksort($limits,SORT_STRING);
        if($limits!==$value['limits'])throw new InvalidArgumentException('Hosting quotas must be in canonical order.');
        return new HostingPackage($value['sku'],$value['label'],$value['provider_type'],$value['region'],$limits);
    }
    private static function result(mixed $value,int $created,int $at):OperationResult
    {
        self::keys($value,['operation','outcome','at','subject_ref','receipt','resource_ref','error','retryable','attempt_ref']);
        foreach(['operation','outcome','subject_ref','attempt_ref'] as $key)self::text($value[$key]);
        foreach(['receipt','resource_ref','error'] as $key)self::nullableText($value[$key]);
        self::integer($value['at']);self::boolean($value['retryable']);
        if($value['at']<$created||$value['at']>$at)throw new InvalidArgumentException('Provider observation time is outside this journal event.');
        return match($value['outcome']){
            'accepted_pending'=>$value['receipt']===null&&$value['resource_ref']!==null&&$value['error']===null&&$value['retryable']===false
                ?OperationResult::acceptedPending($value['operation'],$value['resource_ref'],$value['at'],$value['subject_ref'],$value['attempt_ref']):throw new InvalidArgumentException('Invalid pending provisioning observation.'),
            'applied'=>$value['receipt']!==null&&$value['error']===null&&$value['retryable']===false
                ?OperationResult::applied($value['operation'],$value['receipt'],$value['at'],$value['subject_ref'],$value['resource_ref'],$value['attempt_ref']):throw new InvalidArgumentException('Invalid applied observation.'),
            'failed'=>$value['error']!==null&&$value['receipt']===null&&$value['resource_ref']===null
                ?OperationResult::failed($value['operation'],$value['error'],$value['retryable'],$value['at'],$value['subject_ref'],$value['attempt_ref']):throw new InvalidArgumentException('Invalid failed observation.'),
            'timeout'=>$value['error']===null&&$value['receipt']===null&&$value['resource_ref']===null&&$value['retryable']===false
                ?OperationResult::timeout($value['operation'],$value['at'],$value['subject_ref'],$value['attempt_ref']):throw new InvalidArgumentException('Invalid timeout observation.'),
            default=>throw new InvalidArgumentException('Unknown provider observation.'),
        };
    }
    private static function event(mixed $event,int $created):void
    {
        self::keys($event,['id','command','actor','at','arguments']);
        if(!is_string($event['id'])||!preg_match('/\A[a-f0-9]{32}\z/',$event['id']))throw new InvalidArgumentException('Invalid hosting event identity.');
        self::identity($event['actor']);self::integer($event['at']);
        if($event['at']<$created)throw new InvalidArgumentException('Hosting event precedes account creation.');
        $keys=match($event['command']){
            'begin_provisioning','retry_provision','open_dispute','close_dispute','resume','cancel_order','request_cancellation','retry_deprovision'=>[],
            'record_provision','record_suspend','record_resume','record_deprovision'=>['result'],
            'reconcile','reconcile_without_dispatch'=>['applied_remotely','resource_ref','evidence','attempt_ref'],
            'link_domain'=>['domain'],'set_grace'=>['until'],'suspend'=>['reason'],'approve_deprovision'=>['retention_confirmed'],
            default=>throw new InvalidArgumentException('Unknown hosting journal command.'),
        };
        self::keys($event['arguments'],$keys);$args=$event['arguments'];
        if(array_key_exists('result',$args))self::result($args['result'],$created,$event['at']);
        foreach(['domain','reason','evidence','attempt_ref'] as $key)if(array_key_exists($key,$args))self::text($args[$key]);
        foreach(['applied_remotely','retention_confirmed'] as $key)if(array_key_exists($key,$args))self::boolean($args[$key]);
        if(array_key_exists('resource_ref',$args))self::nullableText($args['resource_ref']);
        if(array_key_exists('until',$args))self::integer($args['until']);
    }
    private static function replay(array $document):HostingAccount
    {
        $account=new HostingAccount($document['id'],self::package($document['package']),$document['client_ref'],$document['created_at']);
        $seen=[];$last=$document['created_at'];$pendingSince=$last;
        foreach($document['events'] as $event){
            self::event($event,$document['created_at']);
            if(isset($seen[$event['id']])||$event['at']<$last)throw new InvalidArgumentException('Duplicate or out-of-order hosting event.');
            $seen[$event['id']]=true;$last=$event['at'];$args=$event['arguments'];$actor=$event['actor'];$at=$event['at'];
            $priorAttempt=$account->pendingAttempt();
            if(isset($args['result'])&&$args['result']['at']<$pendingSince)throw new InvalidArgumentException('Provider observation precedes its dispatched attempt.');
            // Closed dispatch table: stored data never selects a PHP method or class.
            match($event['command']){
                'begin_provisioning'=>$account->beginProvisioning($actor,$at),
                'record_provision'=>$account->provisionResult(self::result($args['result'],$document['created_at'],$at),$actor,$at),
                'retry_provision'=>$account->retry($actor,$at),
                'reconcile'=>$account->reconcileTimeout($actor,$at,$args['applied_remotely'],$args['resource_ref'],$args['evidence'],$args['attempt_ref']),
                'reconcile_without_dispatch'=>$account->reconcileWithoutDispatch($actor,$at,$args['applied_remotely'],$args['resource_ref'],$args['evidence'],$args['attempt_ref']),
                'link_domain'=>$account->linkDomain($args['domain'],$actor,$at),
                'set_grace'=>$account->setGraceUntil($args['until'],$actor,$at),
                'open_dispute'=>$account->openDispute($actor,$at),'close_dispute'=>$account->closeDispute($actor,$at),
                'suspend'=>$account->suspend($actor,$at,$args['reason']),
                'record_suspend'=>$account->suspendResult(self::result($args['result'],$document['created_at'],$at),$actor,$at),
                'resume'=>$account->beginResume($actor,$at),
                'record_resume'=>$account->resumeResult(self::result($args['result'],$document['created_at'],$at),$actor,$at),
                'cancel_order'=>$account->cancelOrder($actor,$at),'request_cancellation'=>$account->requestCancellation($actor,$at),
                'approve_deprovision'=>$account->approveDeprovision($actor,$args['retention_confirmed'],$at),
                'record_deprovision'=>$account->deprovisionResult(self::result($args['result'],$document['created_at'],$at),$actor,$at),
                'retry_deprovision'=>$account->retryDeprovision($actor,$at),
            };
            if($account->pendingAttempt()!==null&&$account->pendingAttempt()!==$priorAttempt)$pendingSince=$at;
        }
        return $account;
    }
}
