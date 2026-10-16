<?php
/** Local unread conversation facts derived from retained history and viewer cursors. No outbox. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/ticket_views.php';
function support_tickets_notification_actor(string $audience):array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Return to your own activity outside client support.');
    if($audience==='admin')return support_tickets_staff_identity(support_tickets_staff(true,false));
    if($audience!=='client')throw new InvalidArgumentException('Invalid ticket activity audience.');
    return support_tickets_portal_identity((int)($_SESSION['client_id']??0),null,false,false);
}
function support_tickets_notification_event(SupportTicketDocument $doc,string $id,array $actor):array {
    $data=$doc->data();if($actor['type']==='client'){
        foreach($doc->forClient($actor['client_id'])['events'] as $event)if($event['id']===$id)return ['ticket_id'=>$data['id'],'event_id'=>$id,'sequence'=>$event['sequence'],'at'=>$event['at'],'actor'=>$event['actor'],'kind'=>$event['kind'],'subject'=>$data['subject']];
    }else{
        if($data['opening']['id']===$id)return ['ticket_id'=>$data['id'],'event_id'=>$id,'sequence'=>0,'at'=>$data['created_at'],'actor'=>$data['created_by'],'kind'=>'opened','subject'=>$data['subject']];
        foreach($data['events'] as $i=>$event)if($event['id']===$id)return ['ticket_id'=>$data['id'],'event_id'=>$id,'sequence'=>$i+1,'at'=>$event['at'],'actor'=>$event['actor'],'kind'=>$event['command'],'subject'=>$data['subject']];
    }
    throw new InvalidArgumentException('Ticket update unavailable.');
}
function support_tickets_notification_page(string $audience,string $before='',int $limit=20):array {
    if($limit<1||$limit>100)throw new InvalidArgumentException('Invalid ticket activity limit.');
    return support_tickets_locked(function()use($audience,$before,$limit):array {
        $actor=support_tickets_notification_actor($audience);$staff=$actor['type']==='staff';$id=$staff?$actor['staff_id']:$actor['client_id'];$cursor=null;
        if($before!==''){
            if(!preg_match('/\A([a-f0-9]{32}):([a-f0-9]{32})\z/',$before,$match))throw new InvalidArgumentException('Invalid ticket activity cursor.');
            $row=support_tickets_row($match[1]);if(!$staff&&(int)$row['client_id']!==$id)throw new RuntimeException('Ticket update unavailable.');
            support_tickets_client_row((int)$row['client_id']);$cursor=support_tickets_notification_event(support_tickets_validate($row),$match[2],$actor);
        }
        $reads=$staff?'support_ticket_staff_reads':'support_ticket_client_reads';$viewer=$staff?'user_id':'client_id';$seq=$staff?'e.revision':'e.public_revision';$zero=$staff?-1:0;
        $join=' FROM '.table('support_tickets').' t JOIN '.table('studio_clients').' c ON c.id=t.client_id LEFT JOIN '.table($reads).' r ON r.ticket_id=t.id AND r.'.$viewer.'=?';$scope='c.active=1'.($staff?'':' AND t.client_id=?');$args=[$id];if(!$staff)$args[]=$id;
        $opening='SELECT t.id AS ticket_id,JSON_UNQUOTE(JSON_EXTRACT(t.initial_json,\'$.opening.id\')) AS event_id,t.created_epoch AS epoch,'.($staff?0:1).' AS sequence'.$join.' WHERE '.$scope.' AND COALESCE(r.revision,'.$zero.')<'.($staff?0:1).' AND '.($staff?'(t.created_staff_id IS NULL OR t.created_staff_id<>?)':'t.created_staff_id IS NOT NULL');if($staff)$args[]=$id;
        $events='SELECT t.id AS ticket_id,e.id AS event_id,e.recorded_epoch AS epoch,'.$seq.' AS sequence'.$join.' JOIN '.table('support_ticket_events').' e ON e.ticket_id=t.id WHERE '.$scope.' AND '.$seq.'>COALESCE(r.revision,'.$zero.') AND '.($staff?'(e.actor_staff_id IS NULL OR e.actor_staff_id<>?)':"e.actor_staff_id IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(e.input_json,'$.command')) IN ('reply','status')");$args[]=$id;if(!$staff)$args[]=$id;else $args[]=$id;
        $sql='SELECT * FROM ('.$opening.' UNION ALL '.$events.') ticket_updates';if($cursor){$sql.=' WHERE epoch<? OR (epoch=? AND (sequence<? OR (sequence=? AND event_id<?)))';array_push($args,$cursor['at'],$cursor['at'],$cursor['sequence'],$cursor['sequence'],$cursor['event_id']);}
        $rows=all($sql.' ORDER BY epoch DESC,sequence DESC,event_id DESC LIMIT '.($limit+1),$args);$items=[];$lastId=null;$doc=null;
        foreach($rows as $row){
            if($row['ticket_id']!==$lastId){$doc=support_tickets_validate(support_tickets_row($row['ticket_id']));$lastId=$row['ticket_id'];}
            $event=support_tickets_notification_event($doc,$row['event_id'],$actor);$read=support_tickets_read_state($doc,$actor);
            if($event['sequence']!==(int)$row['sequence']||$event['at']!==(int)$row['epoch']||$event['sequence']<=$read['revision']||$event['actor']===$actor['ref'])throw new RuntimeException('Ticket activity history is inconsistent.');
            $items[]=$event;
        }
        $more=count($items)>$limit;if($more)array_pop($items);$last=$items?end($items):null;return ['items'=>$items,'next'=>$more?$last['ticket_id'].':'.$last['event_id']:''];
    });
}
/** Compose the existing project bell with ticket facts without changing either read model. */
function workspace_activity_summary(string $audience):array {
    $saved=array_intersect_key($_SESSION,array_flip(['last_activity','client_last']));
    try{
        $available=false;$count=0;$projects=false;$ticketAccess=false;
        try{activity_actor($audience);$projects=true;}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
        try{support_tickets_locked(fn()=>support_tickets_notification_actor($audience));$ticketAccess=true;}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
        if($projects){$count=activity_count($audience);$available=true;}
        if($ticketAccess){$tickets=support_tickets_notification_page($audience,'',100);$count+=count($tickets['items']);$available=true;}
        if(!$available)throw new RuntimeException('Workspace activity unavailable.');return ['count'=>min(100,$count),'capped'=>$count>=100,'url'=>activity_url($audience)];
    }finally{foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$saved))$_SESSION[$key]=$saved[$key];else unset($_SESSION[$key]);}}
}
