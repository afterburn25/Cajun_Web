<?php
/** Current-authorized, atomic ticket storage. No route, mailbox or provider dispatch. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/support/ticket_documents.php';

require_once CWP_CODE_ROOT.'/database/schema/support_tickets_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/support_tickets_migrate.php';

function support_tickets_ready():bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['support_tickets_schema_version'])['value']??'')==='1';}
function support_tickets_require_ready():void {if(!support_tickets_ready())throw new RuntimeException('An owner must prepare support tickets first.');}
function support_tickets_locked(callable $work):mixed {
    if(db()->inTransaction())throw new LogicException('Ticket operations cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction($work));
}
function support_tickets_touch(callable $work,bool $touch):mixed {
    $saved=[];foreach(['last_activity','client_last'] as $key)if(array_key_exists($key,$_SESSION))$saved[$key]=$_SESSION[$key];
    try{return $work();}finally{if(!$touch)foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$saved))$_SESSION[$key]=$saved[$key];else unset($_SESSION[$key]);}}
}
function support_tickets_staff(bool $ready=true,bool $touch=true):array {
    return support_tickets_touch(function()use($ready):array {
        if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Return to the staff desk before managing tickets.');
        $actor=admin_user();
        if(!$actor||!staff_ready()||!studio_ready()||!staff_can('support.tickets',$actor))throw new RuntimeException('Current support-ticket access is required.');
        if($ready)support_tickets_require_ready();return $actor;
    },$touch);
}
function support_tickets_staff_identity(array $actor):array {return ['type'=>'staff','ref'=>'staff:'.$actor['id'],'staff_id'=>(int)$actor['id'],'client_id'=>null,'support_id'=>null];}
function support_tickets_client_row(int $id,bool $active=true):array {
    support_tickets_number($id);$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?'.(db()->inTransaction()?' FOR UPDATE':''),[$id]);
    if(!$client||($active&&(int)$client['active']!==1))throw new RuntimeException('Client unavailable.');return $client;
}
/** Support is always the real staff identity and never falls back to client login keys. */
function support_tickets_portal_identity(int $client,?string $support=null,bool $write=false,bool $touch=true):array {
    return support_tickets_touch(function()use($client,$support,$write,$touch):array {
        support_tickets_require_ready();if(!studio_ready()||!staff_ready())throw new RuntimeException('Support tickets are unavailable.');
        if($support!==null||support_current()||array_key_exists('support',$_GET)){
            if($support===null||$support==='')throw new RuntimeException('Current support session is required.');
            $ctx=support_check($support,$touch);
            if(!$ctx||(int)$ctx['client']['id']!==$client||!staff_can('support.tickets',$ctx['actor'])||($write&&!staff_can('support.write',$ctx['actor'])))throw new RuntimeException('Current support-session ticket access is required.');
            return ['type'=>'support','ref'=>'staff:'.$ctx['actor']['id'],'staff_id'=>(int)$ctx['actor']['id'],'client_id'=>null,'support_id'=>$ctx['session']['id']];
        }
        if((int)($_SESSION['client_id']??0)!==$client)throw new RuntimeException('Sign in as this ticket client.');
        $row=support_tickets_client_row($client);$now=time();
        if($now-(int)($_SESSION['client_last']??0)>1800||$now-(int)($_SESSION['client_login']??0)>28800||!hash_equals($row['auth_version'],(string)($_SESSION['client_auth']??'')))throw new RuntimeException('Your client session is no longer current.');
        if($touch)$_SESSION['client_last']=$now;
        return ['type'=>'client','ref'=>'client:'.$client,'staff_id'=>null,'client_id'=>$client,'support_id'=>null];
    },$touch);
}
function support_tickets_audit(int $actor,string $action,string $detail):void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'support_tickets.'.$action,$detail,gmdate('Y-m-d H:i:s')]);}
function support_tickets_row(string $id):array {
    support_tickets_id($id);$row=one('SELECT * FROM '.table('support_tickets').' WHERE id=?'.(db()->inTransaction()?' FOR UPDATE':''),[$id]);
    if(!$row)throw new RuntimeException('Ticket unavailable.');return $row;
}
function support_tickets_actor_ref(mixed $staff,mixed $client):string {
    if(($staff===null)===($client===null))throw new RuntimeException('Ticket attribution is inconsistent.');
    return $staff!==null?'staff:'.(int)$staff:'client:'.(int)$client;
}
function support_tickets_index(SupportTicketDocument $doc):array {
    $d=$doc->data();$model=$doc->model();$public=$doc->forClient($d['client_id']);$last=$d['events']?$d['events'][array_key_last($d['events'])]['at']:$d['created_at'];
    return ['revision'=>$doc->revision(),'public_revision'=>$doc->publicRevision(),'status'=>$model->status(),'assignee_id'=>$model->assigneeRef()===null?null:(int)substr($model->assigneeRef(),6),'public_updated_epoch'=>$public['updated_at'],'staff_updated_epoch'=>$last];
}
/** Retained rows and hash-linked events must agree with the immutable conversation. */
function support_tickets_validate(array $row):SupportTicketDocument {
    $initial=SupportTicketDocument::fromJson($row['initial_json']);$doc=SupportTicketDocument::fromJson($row['journal_json']);$data=$doc->data();$start=$data;$start['events']=[];
    if($initial->revision()!==0||$initial->data()!==$start||$data['id']!==$row['id']||$data['client_id']!==(int)$row['client_id']||$data['created_at']!==(int)$row['created_epoch']||$data['created_by']!==support_tickets_actor_ref($row['created_staff_id'],$row['created_client_id'])||!hash_equals($doc->fingerprint(),$row['journal_sha256']))throw new RuntimeException('Saved ticket history is inconsistent.');
    foreach(support_tickets_index($doc) as $key=>$value){$saved=$row[$key];if($saved!==null&&is_int($value))$saved=(int)$saved;if($value!==$saved)throw new RuntimeException('Saved ticket projection is inconsistent.');}
    $rows=all('SELECT * FROM '.table('support_ticket_events').' WHERE ticket_id=? ORDER BY revision LIMIT '.(SupportTicketDocument::MAX_EVENTS+1),[$row['id']]);
    if(count($rows)!==count($data['events']))throw new RuntimeException('A ticket event is missing.');
    $prefix=$start;$before=$initial->fingerprint();$public=1;
    foreach($data['events'] as $i=>$event){
        $saved=$rows[$i];$audience=$saved['audience'];$actor=support_tickets_actor_ref($saved['actor_staff_id'],$saved['actor_client_id']);
        if(!in_array($audience,['staff','client','support'],true)||($audience==='client')!==($saved['actor_client_id']!==null)||($audience==='support')!==($saved['support_session_id']!==null))throw new RuntimeException('Ticket event audience is inconsistent.');
        if($audience==='support'){support_tickets_id($saved['support_session_id']);if($event['command']!=='reply')throw new RuntimeException('Support attribution is inconsistent.');}
        $submitted=$audience==='staff'?$i:$public;
        $input=support_tickets_json(['audience'=>$audience,'revision'=>$submitted,'command'=>$event['command'],'arguments'=>$event['arguments'],'support_id'=>$saved['support_session_id']]);
        if(in_array($event['command'],['reply','status'],true))$public++;
        $prefix['events'][]=$event;$after=hash('sha256',support_tickets_json($prefix));
        if($saved['id']!==$event['id']||(int)$saved['revision']!==$i+1||(int)$saved['public_revision']!==$public||(int)$saved['submitted_revision']!==$submitted||$actor!==$event['actor']||(int)$saved['recorded_epoch']!==$event['at']||$saved['input_json']!==$input||!hash_equals($before,$saved['before_sha256'])||!hash_equals($after,$saved['after_sha256']))throw new RuntimeException('Saved ticket event is inconsistent.');
        $before=$after;
    }
    return $doc;
}
/** Links identify context only. Resolve current ownership again before exposing any destination. */
function support_tickets_related(int $client,array $links,bool $strict=false):array {
    $resolved=[];
    foreach($links as $link){
        $row=match($link['kind']){
            'project'=>one('SELECT id,title FROM '.table('studio_jobs').' WHERE id=? AND client_id=?',[$link['id'],$client]),
            'invoice'=>one('SELECT i.id,i.title,j.id AS job_id FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id WHERE i.id=? AND j.client_id=?',[$link['id'],$client]),
            'subscription'=>(function_exists('service_plans_ready')&&service_plans_ready())?one('SELECT * FROM '.table('service_subscriptions').' WHERE id=? AND client_id=?',[$link['id'],$client]):null,
            default=>null,
        };
        if($row&&$link['kind']==='subscription'){if(!function_exists('service_plans_validate'))continue;service_plans_validate($row,true);$row=['id'=>$row['id'],'title'=>'Service agreement'];}
        if(!$row){if($strict)throw new RuntimeException('Related client work is unavailable.');continue;}
        $resolved[]=$link+['title'=>$row['title']]+($link['kind']==='invoice'?['job_id'=>(int)$row['job_id']]:[]);
    }
    return $resolved;
}
function support_tickets_create_locked(string $id,int $client,array $actor,string $subject,string $message,string $body,array $links):SupportTicketDocument {
    if(!db()->inTransaction())throw new LogicException('Ticket creation requires its transaction.');
    support_tickets_client_row($client);support_tickets_id($id);
    $old=one('SELECT * FROM '.table('support_tickets').' WHERE id=? FOR UPDATE',[$id]);
    $doc=SupportTicketDocument::create($id,$client,$actor['ref'],$old?(int)$old['created_epoch']:time(),$subject,$message,$body,$links);
    if($actor['type']==='staff'&&$links){$user=admin_user();if(!$user||!staff_can('clients.manage',$user)||($user['role']!=='owner'&&array_filter($links,fn($link)=>$link['kind']==='subscription')))throw new RuntimeException('Current access to related client work is required.');}
    support_tickets_related($client,$doc->data()['links'],true);
    if($old){$current=support_tickets_validate($old);if($old['initial_json']!==$doc->json())throw new RuntimeException('This ticket request identity was already used.');return $current;}
    $at=$doc->data()['created_at'];
    query('INSERT INTO '.table('support_tickets').' (id,client_id,created_staff_id,created_client_id,created_epoch,initial_json,journal_json,journal_sha256,revision,public_revision,status,assignee_id,public_updated_epoch,staff_updated_epoch) VALUES (?,?,?,?,?,?,?,?,0,1,\'open\',NULL,?,?)',[$id,$client,$actor['staff_id'],$actor['client_id'],$at,$doc->json(),$doc->json(),$doc->fingerprint(),$at,$at]);
    support_tickets_audit($actor['staff_id']??0,'created','Ticket '.$id.' client '.$client.' author '.$actor['ref']);return $doc;
}
function support_tickets_staff_create(string $id,int $client,string $subject,string $message,string $body,array $links=[]):SupportTicketDocument {
    $doc=support_tickets_locked(function()use($id,$client,$subject,$message,$body,$links):SupportTicketDocument {$actor=support_tickets_staff_identity(support_tickets_staff());return support_tickets_create_locked($id,$client,$actor,$subject,$message,$body,$links);});
    require_once CWP_CODE_ROOT.'/app/notifications/feed.php';try{notification_emit('client',$client,'support-reply','ticket:'.$id,'Support conversation opened · '.$doc->data()['subject'],'The studio opened a conversation for you.','ticket-open-client:'.$id);}catch(Throwable $e){report_error($e);}
    return $doc;
}
function support_tickets_client_create(string $id,int $client,string $subject,string $message,string $body,array $links=[],?string $support=null):array {
    $view=support_tickets_locked(function()use($id,$client,$subject,$message,$body,$links,$support):array {$actor=support_tickets_portal_identity($client,$support,true);if($actor['type']!=='client')throw new RuntimeException('Create support conversations from the staff desk.');return support_tickets_create_locked($id,$client,$actor,$subject,$message,$body,$links)->forClient($client);});
    if($support===null){require_once CWP_CODE_ROOT.'/app/notifications/feed.php';try{foreach(notification_staff_ids('support.tickets') as $sid)notification_emit('staff',$sid,'support-reply','ticket:'.$id,'New support conversation · '.$subject,'A client opened a support conversation.','ticket-open:'.$id.':'.$sid);}catch(Throwable $e){report_error($e);}}
    return $view;
}
function support_tickets_assignee(int $id):void {
    support_tickets_number($id);$user=one('SELECT * FROM '.table('users').' WHERE id=? FOR UPDATE',[$id]);$profile=$user?staff_profile($user):null;
    if(!$profile||$profile['password_hash']===''||($profile['role']!=='owner'&&!in_array('support.tickets',json_decode($profile['permissions'],true),true)))throw new RuntimeException('Choose a current staff member with ticket access.');
}
/** A client submits its public revision; intervening staff-only events do not invalidate it. */
function support_tickets_append_locked(array $row,array $actor,int $revision,string $id,string $command,array $args):SupportTicketDocument {
    if(!db()->inTransaction())throw new LogicException('Ticket changes require their transaction.');
    support_tickets_id($id);if($revision<0||$revision>SupportTicketDocument::MAX_EVENTS+1)throw new InvalidArgumentException('Invalid ticket revision.');
    $doc=support_tickets_validate($row);$input=support_tickets_json(['audience'=>$actor['type'],'revision'=>$revision,'command'=>$command,'arguments'=>$args,'support_id'=>$actor['support_id']]);
    $prior=one('SELECT * FROM '.table('support_ticket_events').' WHERE id=?',[$id]);
    if($prior){if($prior['ticket_id']!==$row['id']||support_tickets_actor_ref($prior['actor_staff_id'],$prior['actor_client_id'])!==$actor['ref']||$prior['input_json']!==$input)throw new RuntimeException('This ticket request identity was already used.');return $doc;}
    $expected=$actor['type']==='staff'?$doc->revision():$doc->publicRevision();
    if($revision!==$expected)throw new RuntimeException('This conversation changed. Review the current ticket before submitting again.');
    if($command==='assign'){
        support_tickets_fields($args,['staff_id']);support_tickets_number($args['staff_id']);support_tickets_assignee($args['staff_id']);
    }
    $index=support_tickets_index($doc);$next=$doc->append($id,$command,$actor['ref'],max(time(),$index['staff_updated_epoch']),$args);$updated=support_tickets_index($next);$event=$next->data()['events'][array_key_last($next->data()['events'])];
    query('INSERT INTO '.table('support_ticket_events').' (id,ticket_id,actor_staff_id,actor_client_id,support_session_id,audience,revision,public_revision,submitted_revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$row['id'],$actor['staff_id'],$actor['client_id'],$actor['support_id'],$actor['type'],$updated['revision'],$updated['public_revision'],$revision,$event['at'],$input,$doc->fingerprint(),$next->fingerprint()]);
    $changed=query('UPDATE '.table('support_tickets').' SET journal_json=?,journal_sha256=?,revision=?,public_revision=?,status=?,assignee_id=?,public_updated_epoch=?,staff_updated_epoch=? WHERE id=? AND revision=? AND journal_sha256=?',[$next->json(),$next->fingerprint(),...array_values($updated),$row['id'],$doc->revision(),$doc->fingerprint()]);
    if($changed->rowCount()!==1)throw new RuntimeException('This ticket changed.');
    support_tickets_audit($actor['staff_id']??0,'changed','Ticket '.$row['id'].' request '.$id.' author '.$actor['ref'].' '.$command.' revision '.$updated['revision'].($actor['support_id']?' support '.$actor['support_id']:''));
    if($actor['support_id'])support_audit(['id'=>$actor['support_id'],'actor_id'=>$actor['staff_id'],'client_id'=>$row['client_id']],'ticket.reply','ticket',$row['id']);
    return $next;
}
function support_tickets_staff_change(string $ticket,int $revision,string $request,string $command,array $args):SupportTicketDocument {
    $doc=support_tickets_locked(function()use($ticket,$revision,$request,$command,$args):SupportTicketDocument {
        $actor=support_tickets_staff_identity(support_tickets_staff());$row=support_tickets_row($ticket);support_tickets_client_row((int)$row['client_id']);
        return support_tickets_append_locked($row,$actor,$revision,$request,$command,$args);
    });
    if(in_array($command,['reply','status'],true)){$row=support_tickets_row($ticket);require_once CWP_CODE_ROOT.'/app/notifications/feed.php';try{notification_emit('client',(int)$row['client_id'],'support-reply','ticket:'.$ticket,($command==='reply'?'New reply · ':'Status updated · ').$doc->data()['subject'],$command==='reply'?substr((string)($args['body']??''),0,300):'The studio set the conversation status to '.$args['status'].'.','ticket-event:'.$ticket.':'.$request);}catch(Throwable $e){report_error($e);}}
    return $doc;
}
function support_tickets_client_change(string $ticket,int $client,int $revision,string $request,string $command,array $args,?string $support=null):array {
    $view=support_tickets_locked(function()use($ticket,$client,$revision,$request,$command,$args,$support):array {
        $actor=support_tickets_portal_identity($client,$support,true);
        if(!in_array($command,$actor['type']==='support'?['reply']:['reply','status'],true))throw new RuntimeException('This action requires the staff desk.');
        $row=support_tickets_row($ticket);if((int)$row['client_id']!==$client)throw new RuntimeException('Ticket unavailable.');
        return support_tickets_append_locked($row,$actor,$revision,$request,$command,$args)->forClient($client);
    });
    if($support===null){$row=support_tickets_row($ticket);$subject=support_tickets_validate($row)->data()['subject']??'Support conversation';require_once CWP_CODE_ROOT.'/app/notifications/feed.php';try{foreach(notification_staff_ids('support.tickets') as $sid)notification_emit('staff',$sid,'support-reply','ticket:'.$ticket,($command==='reply'?'Client replied · ':'Client updated status · ').$subject,substr((string)($args['body']??'The client updated the conversation status.'),0,300),'ticket-event:'.$ticket.':'.$request.':'.$sid);}catch(Throwable $e){report_error($e);}}
    return $view;
}
