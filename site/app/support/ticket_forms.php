<?php
/** Strict form parsing and safe redisplay; authority belongs to the native adapter. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/ticket_documents.php';
function support_tickets_form_text(array $data,string $name,int $max,bool $multiline=false,bool $optional=false):string {
    $value=$data[$name]??'';if(!is_string($value))throw new InvalidArgumentException('Invalid ticket form field.');
    if($multiline)$value=str_replace(["\r\n","\r"],"\n",$value);
    if($optional&&$value==='')return '';
    support_tickets_text($value,$max,$multiline);return $value;
}
function support_tickets_form_id(array $data,string $name):string {$value=$data[$name]??null;support_tickets_id($value);return $value;}
function support_tickets_form_number(array $data,string $name,int $maximum=2147483647,int $minimum=0):int {
    $value=$data[$name]??null;if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$value)||strlen($value)>strlen((string)$maximum)||(int)$value>$maximum||(int)$value<$minimum)throw new InvalidArgumentException('Invalid ticket form number.');return (int)$value;
}
function support_tickets_form_intent(array $data):array {return ['ticket_id'=>support_tickets_form_id($data,'ticket_id'),'request_id'=>support_tickets_form_id($data,'request_id'),'revision'=>support_tickets_form_number($data,'revision',SupportTicketDocument::MAX_EVENTS+1)];}
function support_tickets_form_confirm(array $data):void {if(($data['confirm']??null)!=='1')throw new InvalidArgumentException('Confirm the ticket status change before submitting.');}
function support_tickets_form_links(array $data):array {
    $links=[];foreach(['invoice','project','subscription'] as $kind){$value=$data['related_'.$kind]??'';if($value==='')continue;
        if($kind==='subscription')support_tickets_id($value);else support_tickets_form_number(['id'=>$value],'id',2147483647,1);
        $links[]=['kind'=>$kind,'id'=>$value];
    }return $links;
}
function support_tickets_form_values(array $data):array {
    $out=[];foreach(['ticket_id'=>128,'request_id'=>128,'opening_id'=>128,'revision'=>40,'client_id'=>40,'staff_id'=>40,'subject'=>720,'body'=>65536,'status'=>64,'related_invoice'=>40,'related_project'=>40,'related_subscription'=>128] as $key=>$max){$value=$data[$key]??'';$out[$key]=is_string($value)?substr($value,0,$max):'';}return $out;
}
function support_tickets_ui_intent(string $ticket,int $revision,string $action):array {
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&($_POST['action']??null)===$action)return array_intersect_key(support_tickets_form_values($_POST),array_flip(['ticket_id','request_id','revision']));
    return ['ticket_id'=>$ticket,'request_id'=>bin2hex(random_bytes(16)),'revision'=>$revision];
}
function support_tickets_history_page(array $events,array $query):array {
    if(!$events)throw new InvalidArgumentException('Ticket history unavailable.');$last=intdiv(count($events)-1,30);$page=array_key_exists('history',$query)?support_tickets_form_number($query,'history',17):$last;
    if(array_key_exists('focus',$query)){$id=support_tickets_form_id($query,'focus');$found=null;foreach($events as $index=>$event)if($event['id']===$id){$found=$index;break;}if($found===null)throw new InvalidArgumentException('Ticket update unavailable.');$page=intdiv($found,30);}
    if($page>$last)throw new InvalidArgumentException('Ticket history page unavailable.');return ['events'=>array_slice($events,$page*30,30),'page'=>$page,'last'=>$last];
}
