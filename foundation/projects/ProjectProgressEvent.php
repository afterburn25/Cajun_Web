<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\Projects;
require_once __DIR__.'/ProjectSnapshot.php';

/** Immutable public fact. Current authorization, uniqueness and atomic storage belong to the adapter. */
final readonly class ProjectProgressEvent {
    public const MAX_BYTES=262144;
    private const FIELDS=['format','id','project_id','client_id','revision','kind','staff_id','occurred_at','before_sha256','before','after','changed_fields'];
    private function __construct(private string $bytes){}
    private static function encode(array $data):string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    private static function number(mixed $value,bool $zero=false):void {
        if(!is_int($value)||$value<($zero?0:1)||$value>2147483647)throw new \InvalidArgumentException('Invalid progress account or revision.');
    }
    private static function id(mixed $value):void {
        if(!is_string($value)||preg_match('/\A[a-f0-9]{32}\z/',$value)!==1)throw new \InvalidArgumentException('Invalid progress event identity.');
    }
    private static function digest(mixed $value):void {
        if(!is_string($value)||preg_match('/\A[a-f0-9]{64}\z/',$value)!==1)throw new \InvalidArgumentException('Invalid progress fingerprint.');
    }
    public static function baseline(string $id,int $project,int $client,int $staff,int $at,ProjectSnapshot $snapshot):self {
        return self::fromJson(self::encode(['format'=>1,'id'=>$id,'project_id'=>$project,'client_id'=>$client,'revision'=>0,'kind'=>'tracking_started','staff_id'=>$staff,'occurred_at'=>$at,'before_sha256'=>null,'before'=>null,'after'=>$snapshot->data(),'changed_fields'=>[]]));
    }
    public function update(string $id,int $staff,int $at,string $expected,ProjectSnapshot $after):self {
        self::digest($expected);
        if(!hash_equals($this->fingerprint(),$expected))throw new \LogicException('Project progress changed. Review the current project before trying again.');
        $before=$this->snapshot();$d=$this->data();
        $event=self::fromJson(self::encode(['format'=>1,'id'=>$id,'project_id'=>$d['project_id'],'client_id'=>$d['client_id'],'revision'=>$d['revision']+1,'kind'=>'updated','staff_id'=>$staff,'occurred_at'=>$at,'before_sha256'=>$expected,'before'=>$before->data(),'after'=>$after->data(),'changed_fields'=>$after->changesFrom($before)]));
        $event->assertFollows($this);return $event;
    }
    /** Parsing validates a single record; callers must also verify its retained predecessor. */
    public static function fromJson(string $bytes):self {
        if(strlen($bytes)>self::MAX_BYTES)throw new \InvalidArgumentException('Progress event is too large.');
        $d=json_decode($bytes,true,8,JSON_THROW_ON_ERROR);
        if(!is_array($d)||array_keys($d)!==self::FIELDS||$d['format']!==1||self::encode($d)!==$bytes)throw new \InvalidArgumentException('Invalid or noncanonical progress event.');
        self::id($d['id']);foreach(['project_id','client_id','staff_id'] as $key)self::number($d[$key]);self::number($d['revision'],true);
        if(!is_int($d['occurred_at'])||$d['occurred_at']<0||$d['occurred_at']>253402300799)throw new \InvalidArgumentException('Invalid progress time.');
        if(!is_array($d['after']))throw new \InvalidArgumentException('Invalid progress snapshot.');
        $after=ProjectSnapshot::fromArray($d['after']);
        if($d['kind']==='tracking_started'){
            if($d['revision']!==0||$d['before_sha256']!==null||$d['before']!==null||$d['changed_fields']!==[])throw new \InvalidArgumentException('A tracking baseline cannot invent earlier changes.');
        }elseif($d['kind']==='updated'){
            if($d['revision']<1||!is_array($d['before']))throw new \InvalidArgumentException('Invalid progress update.');
            self::digest($d['before_sha256']);$before=ProjectSnapshot::fromArray($d['before']);$changes=$after->changesFrom($before);
            if(!$changes||$d['changed_fields']!==$changes)throw new \InvalidArgumentException('Progress must record exactly the changed public fields.');
        }else throw new \InvalidArgumentException('Unknown progress event kind.');
        return new self($bytes);
    }
    public function assertFollows(self $previous):void {
        $d=$this->data();$p=$previous->data();
        if($d['kind']!=='updated'||$d['id']===$p['id']||$d['project_id']!==$p['project_id']||$d['client_id']!==$p['client_id']||$d['revision']!==$p['revision']+1||$d['occurred_at']<$p['occurred_at']||$d['before']!==$p['after']||!hash_equals($previous->fingerprint(),$d['before_sha256']))throw new \LogicException('Saved progress history is inconsistent.');
    }
    /** Validate the complete bounded page supplied by storage, including global IDs in that page. */
    public static function verifyChain(array $events,?self $preceding=null):void {
        if(!array_is_list($events)||count($events)<1||count($events)>100)throw new \InvalidArgumentException('Choose a bounded nonempty progress page.');
        $seen=$preceding===null?[]:[$preceding->data()['id']=>true];$previous=$preceding;
        foreach($events as $event){
            if(!$event instanceof self)throw new \InvalidArgumentException('Invalid progress event.');
            $d=$event->data();
            if(isset($seen[$d['id']]))throw new \LogicException('Duplicate progress event identity.');
            $seen[$d['id']]=true;
            if($previous===null){if($d['kind']!=='tracking_started')throw new \LogicException('Progress history needs its actual baseline or preceding event.');}
            else $event->assertFollows($previous);
            $previous=$event;
        }
    }
    public function json():string {return $this->bytes;}
    public function data():array {return json_decode($this->bytes,true,8,JSON_THROW_ON_ERROR);}
    public function fingerprint():string {return hash('sha256',$this->bytes);}
    public function snapshot():ProjectSnapshot {return ProjectSnapshot::fromArray($this->data()['after']);}
    /** The adapter must still recheck live ownership. Internal author/account/hash data is excluded. */
    public function forClient(int $client):array {
        $d=$this->data();if($client!==$d['client_id'])throw new \InvalidArgumentException('Project progress unavailable.');
        return ['id'=>$d['id'],'revision'=>$d['revision'],'kind'=>$d['kind'],'occurred_at'=>$d['occurred_at'],'before'=>$d['before'],'after'=>$d['after'],'changed_fields'=>$d['changed_fields']];
    }
}
