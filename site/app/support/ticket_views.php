<?php
/** Current-authorized bounded lists and public-only portal detail. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/ticket_reads.php';

function support_tickets_staff_get(string $id,bool $touch=true):array {
    return support_tickets_locked(function()use($id,$touch):array {
        $user=support_tickets_staff(true,$touch);$actor=support_tickets_staff_identity($user);$doc=support_tickets_validate(support_tickets_row($id));$data=$doc->data();$client=support_tickets_client_row($data['client_id'],false);
        $links=$data['links'];if(!staff_can('clients.manage',$user))$links=[];elseif($user['role']!=='owner')$links=array_values(array_filter($links,fn($link)=>$link['kind']!=='subscription'));
        return ['document'=>$doc,'client'=>['id'=>(int)$client['id'],'name'=>$client['name'],'active'=>(int)$client['active']===1],'related'=>support_tickets_related($data['client_id'],$links),'read'=>support_tickets_read_state($doc,$actor)];
    });
}
function support_tickets_client_get(string $id,int $client,?string $support=null,bool $touch=true):array {
    return support_tickets_locked(function()use($id,$client,$support,$touch):array {
        $actor=support_tickets_portal_identity($client,$support,false,$touch);$row=support_tickets_row($id);
        if((int)$row['client_id']!==$client)throw new RuntimeException('Ticket unavailable.');
        $doc=support_tickets_validate($row);$result=$doc->forClient($client);
        // Agreement viewing/consent is reserved to the actual client in the existing portal.
        $links=$doc->data()['links'];if($actor['type']==='support')$links=array_values(array_filter($links,fn($link)=>$link['kind']!=='subscription'));
        $result['related']=support_tickets_related($client,$links);
        if($actor['type']==='client')$result['read']=support_tickets_read_state($doc,$actor);
        return $result;
    });
}
function support_tickets_page_bounds(int $page,?string $status):void {
    if($page<0||$page>10000)throw new InvalidArgumentException('Choose an available ticket page.');
    if($status!==null&&!in_array($status,['open','pending','resolved','closed'],true))throw new InvalidArgumentException('Choose an available ticket status.');
}
function support_tickets_staff_page(int $page=0,?string $status=null,?int $client=null,?int $assignee=null,bool $touch=true):array {
    support_tickets_page_bounds($page,$status);if($client!==null)support_tickets_number($client);if($assignee!==null)support_tickets_number($assignee);
    return support_tickets_locked(function()use($page,$status,$client,$assignee,$touch):array {
        $actor=support_tickets_staff_identity(support_tickets_staff(true,$touch));$where=[];$args=[];
        foreach(['status'=>$status,'client_id'=>$client,'assignee_id'=>$assignee] as $field=>$value)if($value!==null){$where[]=$field.'=?';$args[]=$value;}
        $rows=all('SELECT id FROM '.table('support_tickets').($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY staff_updated_epoch DESC,id DESC LIMIT 11 OFFSET '.($page*10),$args);$items=[];
        foreach(array_slice($rows,0,10) as $row){
            $doc=support_tickets_validate(support_tickets_row($row['id']));$data=$doc->data();$clientRow=support_tickets_client_row($data['client_id'],false);$index=support_tickets_index($doc);
            $items[]=['id'=>$data['id'],'subject'=>$data['subject'],'client_id'=>$data['client_id'],'client_name'=>$clientRow['name'],'status'=>$index['status'],'assignee_id'=>$index['assignee_id'],'revision'=>$index['revision'],'updated_at'=>$index['staff_updated_epoch'],'read'=>support_tickets_read_state($doc,$actor)];
        }
        return ['items'=>$items,'more'=>count($rows)>10,'page'=>$page];
    });
}
function support_tickets_client_page(int $client,int $page=0,?string $status=null,?string $support=null,bool $touch=true):array {
    support_tickets_page_bounds($page,$status);
    return support_tickets_locked(function()use($client,$page,$status,$support,$touch):array {
        $actor=support_tickets_portal_identity($client,$support,false,$touch);$args=[$client];if($status!==null)$args[]=$status;
        $rows=all('SELECT id FROM '.table('support_tickets').' WHERE client_id=?'.($status!==null?' AND status=?':'').' ORDER BY public_updated_epoch DESC,id DESC LIMIT 11 OFFSET '.($page*10),$args);$items=[];
        foreach(array_slice($rows,0,10) as $row){
            $doc=support_tickets_validate(support_tickets_row($row['id']));$item=$doc->forClient($client);unset($item['events']);
            if($actor['type']==='client')$item['read']=support_tickets_read_state($doc,$actor);$items[]=$item;
        }
        return ['items'=>$items,'more'=>count($rows)>10,'page'=>$page];
    });
}
