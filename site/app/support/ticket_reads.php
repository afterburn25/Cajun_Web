<?php
/** Viewer-specific read cursors. Merely obtaining a projection never marks it read. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/tickets.php';

function support_tickets_read_state(SupportTicketDocument $doc,array $actor):array {
    if(!in_array($actor['type'],['staff','client'],true))throw new LogicException('Support sessions do not own client read state.');
    $staff=$actor['type']==='staff';$id=$staff?$actor['staff_id']:$actor['client_id'];$data=$doc->data();
    $name=$staff?'support_ticket_staff_reads':'support_ticket_client_reads';$field=$staff?'user_id':'client_id';
    $row=one('SELECT * FROM '.table($name).' WHERE ticket_id=? AND '.$field.'=?',[$data['id'],$id]);
    $maximum=$staff?$doc->revision():$doc->publicRevision();$seen=$row?(int)$row['revision']:($staff?-1:0);
    if($row){support_tickets_id($row['id']);support_tickets_time((int)$row['recorded_epoch']);if($seen<($staff?0:1)||$seen>$maximum)throw new RuntimeException('Saved ticket read state is inconsistent.');}
    $unread=0;
    if($staff){
        if($seen<0&&$data['created_by']!==$actor['ref'])$unread++;
        foreach($data['events'] as $i=>$event)if($i+1>$seen&&$event['actor']!==$actor['ref'])$unread++;
    }else foreach($doc->forClient($id)['events'] as $event)if($event['sequence']>$seen&&$event['actor']!==$actor['ref'])$unread++;
    return ['revision'=>$seen,'unread'=>$unread];
}
function support_tickets_mark_locked(SupportTicketDocument $doc,array $actor,int $revision):array {
    if(!db()->inTransaction())throw new LogicException('Read state requires its transaction.');
    $staff=$actor['type']==='staff';$maximum=$staff?$doc->revision():$doc->publicRevision();
    if($revision<($staff?0:1)||$revision>$maximum)throw new InvalidArgumentException('Read only a displayed ticket revision.');
    $state=support_tickets_read_state($doc,$actor);if($revision<=$state['revision'])return $state;
    $name=$staff?'support_ticket_staff_reads':'support_ticket_client_reads';$field=$staff?'user_id':'client_id';$viewer=$staff?$actor['staff_id']:$actor['client_id'];$id=$doc->data()['id'];
    query('INSERT INTO '.table($name).' (id,ticket_id,'.$field.',revision,recorded_epoch) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE revision=VALUES(revision),recorded_epoch=VALUES(recorded_epoch)',[bin2hex(random_bytes(16)),$id,$viewer,$revision,time()]);
    support_tickets_audit($actor['staff_id']??0,'read','Ticket '.$id.' viewer '.$actor['ref'].' revision '.$revision);
    return support_tickets_read_state($doc,$actor);
}
function support_tickets_staff_mark(string $id,int $revision):array {
    return support_tickets_locked(function()use($id,$revision):array {
        $actor=support_tickets_staff_identity(support_tickets_staff(true,false));$doc=support_tickets_validate(support_tickets_row($id));
        return support_tickets_mark_locked($doc,$actor,$revision);
    });
}
function support_tickets_client_mark(string $id,int $client,int $revision,?string $support=null):array {
    return support_tickets_locked(function()use($id,$client,$revision,$support):array {
        $actor=support_tickets_portal_identity($client,$support,false,false);
        if($actor['type']!=='client')throw new RuntimeException('Support sessions cannot mark client conversations read.');
        $row=support_tickets_row($id);if((int)$row['client_id']!==$client)throw new RuntimeException('Ticket unavailable.');
        return support_tickets_mark_locked(support_tickets_validate($row),$actor,$revision);
    });
}
