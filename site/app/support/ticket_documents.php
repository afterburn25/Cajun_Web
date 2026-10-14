<?php
/** Immutable ticket history over reviewed desk models. Pure validation, not an authority grant. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/desk/SupportTicket.php';
use CajunWebPro\Foundation\Desk\{SupportTicket,TicketMessage,TraceLink};

function support_tickets_json(array $data):string {
    return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
function support_tickets_fields(array $data,array $fields):void {
    if(array_keys($data)!==$fields)throw new InvalidArgumentException('Invalid ticket fields or field order.');
}
function support_tickets_id(mixed $id):void {
    if(!is_string($id)||!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Invalid ticket identity.');
}
function support_tickets_number(mixed $id):void {
    if(!is_int($id)||$id<1||$id>2147483647)throw new InvalidArgumentException('Invalid ticket account.');
}
function support_tickets_time(mixed $at):void {
    if(!is_int($at)||$at<0||$at>253402300799)throw new InvalidArgumentException('Invalid ticket time.');
}
function support_tickets_actor(mixed $actor,int $client):string {
    if(!is_string($actor)||!preg_match('/\A(staff|client):([1-9][0-9]{0,9})\z/',$actor,$m))throw new InvalidArgumentException('Invalid ticket author.');
    support_tickets_number((int)$m[2]);
    if($m[1]==='client'&&(int)$m[2]!==$client)throw new InvalidArgumentException('Ticket belongs to another client.');
    return $m[1];
}
function support_tickets_text(mixed $text,int $bytes,bool $multiline=false):void {
    $control=$multiline?'/[\x00-\x08\x0B-\x1F\x7F]/u':'/[\x00-\x1F\x7F]/u';
    if(!is_string($text)||strlen($text)>$bytes||trim($text)===''||preg_match('//u',$text)!==1||preg_match($control,$text))throw new InvalidArgumentException('Invalid or oversized ticket text.');
}

final readonly class SupportTicketDocument {
    public const MAX_EVENTS=512;
    public const MAX_BYTES=1048576;
    public const MAX_BODY=16384;
    private const CLOSE_RESERVE=1024;
    private function __construct(private string $bytes){}

    /** Related local records still require current ownership checks in the native adapter. */
    public static function create(string $id,int $client,string $actor,int $at,string $subject,string $messageId,string $body,array $links=[]):self {
        return self::fromJson(support_tickets_json(['format'=>1,'id'=>$id,'client_id'=>$client,'subject'=>$subject,'created_by'=>$actor,'created_at'=>$at,'opening'=>['id'=>$messageId,'body'=>$body],'links'=>$links,'events'=>[]]));
    }
    public static function fromJson(string $bytes):self {
        if(strlen($bytes)>self::MAX_BYTES)throw new InvalidArgumentException('Ticket history is too large.');
        $d=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($d))throw new InvalidArgumentException('Invalid ticket document.');
        support_tickets_fields($d,['format','id','client_id','subject','created_by','created_at','opening','links','events']);
        if($d['format']!==1||!is_array($d['opening'])||!is_array($d['links'])||!array_is_list($d['links'])||count($d['links'])>8||!is_array($d['events'])||!array_is_list($d['events'])||count($d['events'])>self::MAX_EVENTS||support_tickets_json($d)!==$bytes)throw new InvalidArgumentException('Invalid ticket document.');
        support_tickets_id($d['id']);support_tickets_number($d['client_id']);support_tickets_time($d['created_at']);
        support_tickets_actor($d['created_by'],$d['client_id']);support_tickets_text($d['subject'],180);
        support_tickets_fields($d['opening'],['id','body']);support_tickets_id($d['opening']['id']);support_tickets_text($d['opening']['body'],self::MAX_BODY,true);
        $result=self::replay($d);
        $openBytes=$bytes;
        if($result['model']->status()===SupportTicket::CLOSED){$prior=$d;array_pop($prior['events']);$openBytes=support_tickets_json($prior);}
        if(strlen($openBytes)>self::MAX_BYTES-self::CLOSE_RESERVE)throw new LogicException('Ticket history is full. Close this ticket and continue in a new one.');
        return new self($bytes);
    }
    /** Reconstruct both the mutable model and a projection with no hidden-event metadata. */
    private static function replay(array $d):array {
        $links=[];$lastLink='';
        foreach($d['links'] as $link){
            if(!is_array($link))throw new InvalidArgumentException('Invalid related ticket record.');
            support_tickets_fields($link,['kind','id']);
            if(!in_array($link['kind'],['invoice','project','subscription'],true)||!is_string($link['id']))throw new InvalidArgumentException('Unsupported related ticket record.');
            if($link['kind']==='subscription')support_tickets_id($link['id']);
            elseif(!preg_match('/\A[1-9][0-9]{0,9}\z/',$link['id'])||(int)$link['id']>2147483647)throw new InvalidArgumentException('Invalid related ticket record.');
            $key=$link['kind'].':'.$link['id'];
            if(strcmp($key,$lastLink)<=0)throw new InvalidArgumentException('Related records must be unique and ordered.');
            $lastLink=$key;$links[]=new TraceLink($link['kind'],$link['id']);
        }
        $model=new SupportTicket($d['id'],'client:'.$d['client_id'],$d['subject'],$links);
        $kind=support_tickets_actor($d['created_by'],$d['client_id']);
        $model->post(new TicketMessage($d['opening']['id'],$d['id'],$kind,$d['created_by'],$d['opening']['body'],$d['created_at']),$d['created_by'],$d['created_at']);
        $public=[['sequence'=>1,'id'=>$d['opening']['id'],'kind'=>'opened','actor'=>$d['created_by'],'at'=>$d['created_at'],'status'=>SupportTicket::OPEN,'body'=>$d['opening']['body']]];
        $seen=[$d['opening']['id']=>true];$last=$d['created_at'];
        foreach($d['events'] as $index=>$event){
            if(!is_array($event))throw new InvalidArgumentException('Invalid ticket event.');
            support_tickets_fields($event,['id','command','actor','at','arguments']);
            support_tickets_id($event['id']);support_tickets_time($event['at']);
            $author=support_tickets_actor($event['actor'],$d['client_id']);
            if(!is_string($event['command'])||!is_array($event['arguments'])||isset($seen[$event['id']])||$event['at']<$last)throw new InvalidArgumentException('Invalid ticket event order or identity.');
            $seen[$event['id']]=true;$last=$event['at'];$args=$event['arguments'];
            if($model->status()===SupportTicket::CLOSED)throw new LogicException('Closed tickets cannot change.');
            if($index>=self::MAX_EVENTS-1&&($event['command']!=='status'||$args!==['status'=>SupportTicket::CLOSED]))throw new LogicException('Ticket history is full. Only closing remains available.');
            switch($event['command']){
                case 'reply':
                case 'note':
                    support_tickets_fields($args,['body']);support_tickets_text($args['body'],self::MAX_BODY,true);
                    $internal=$event['command']==='note';
                    if($internal&&$author!=='staff')throw new LogicException('Only staff can add private notes.');
                    if(!$internal&&($model->status()===SupportTicket::RESOLVED||($author==='client'&&$model->status()===SupportTicket::PENDING)))$model->transition(SupportTicket::OPEN,$event['actor'],$event['at']);
                    $model->post(new TicketMessage($event['id'],$d['id'],$author,$event['actor'],$args['body'],$event['at'],$internal),$event['actor'],$event['at']);
                    if(!$internal)$public[]=['sequence'=>count($public)+1,'id'=>$event['id'],'kind'=>'reply','actor'=>$event['actor'],'at'=>$event['at'],'status'=>$model->status(),'body'=>$args['body']];
                    break;
                case 'assign':
                    support_tickets_fields($args,['staff_id']);support_tickets_number($args['staff_id']);
                    if($author!=='staff')throw new LogicException('Only staff can assign tickets.');
                    $model->assign('staff:'.$args['staff_id'],$event['actor'],$event['at']);break;
                case 'status':
                    support_tickets_fields($args,['status']);
                    if(!is_string($args['status']))throw new InvalidArgumentException('Invalid ticket status.');
                    if($author==='client'&&$args['status']!==SupportTicket::CLOSED&&!($model->status()===SupportTicket::RESOLVED&&$args['status']===SupportTicket::OPEN))throw new LogicException('Clients can close tickets or reopen a resolved ticket.');
                    $model->transition($args['status'],$event['actor'],$event['at']);
                    $public[]=['sequence'=>count($public)+1,'id'=>$event['id'],'kind'=>'status','actor'=>$event['actor'],'at'=>$event['at'],'status'=>$model->status(),'body'=>''];break;
                default:throw new InvalidArgumentException('Unknown ticket action.');
            }
        }
        return ['model'=>$model,'public'=>$public];
    }
    public function append(string $id,string $command,string $actor,int $at,array $arguments):self {
        $d=$this->data();$event=['id'=>$id,'command'=>$command,'actor'=>$actor,'at'=>$at,'arguments'=>$arguments];
        foreach($d['events'] as $old)if($old['id']===$id){if($old!==$event)throw new InvalidArgumentException('Ticket request identity was already used.');return $this;}
        $d['events'][]=$event;return self::fromJson(support_tickets_json($d));
    }
    /** Scoped projection: no internal notes, assignments, global revision/hash or private timestamps. */
    public function forClient(int $client):array {
        $d=$this->data();if($client!==$d['client_id'])throw new InvalidArgumentException('Ticket belongs to another client.');
        $state=self::replay($d);$events=$state['public'];
        return ['id'=>$d['id'],'subject'=>$d['subject'],'created_at'=>$d['created_at'],'status'=>$state['model']->status(),'public_revision'=>count($events),'updated_at'=>$events[array_key_last($events)]['at'],'events'=>$events];
    }
    public function prefix(int $revision):self {
        $d=$this->data();if($revision<0||$revision>count($d['events']))throw new InvalidArgumentException('Invalid ticket revision.');
        $d['events']=array_slice($d['events'],0,$revision);return self::fromJson(support_tickets_json($d));
    }
    public function data():array {return json_decode($this->bytes,true,16,JSON_THROW_ON_ERROR);}
    public function json():string {return $this->bytes;}
    public function fingerprint():string {return hash('sha256',$this->bytes);}
    public function revision():int {return count($this->data()['events']);}
    public function publicRevision():int {return count(self::replay($this->data())['public']);}
    public function model():SupportTicket {return self::replay($this->data())['model'];}
}
