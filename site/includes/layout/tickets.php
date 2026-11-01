<?php
/** Shared escaped ticket forms and history. No authority or database access. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/ticket_forms.php';
function support_tickets_intent_fields(string $action,array $intent,bool $support=false):void {
    echo $support?support_csrf_field():csrf_field();echo '<input type="hidden" name="action" value="'.esc($action).'">';
    foreach($intent as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc((string)$value).'">';
}
function support_tickets_statuses(string $status,bool $staff):array {
    if(!$staff)return match($status){'open','pending'=>['closed'],'resolved'=>['open','closed'],'closed'=>[],default=>throw new InvalidArgumentException('Invalid ticket status.')};
    return match($status){'open'=>['pending','resolved','closed'],'pending'=>['open','resolved','closed'],'resolved'=>['open','closed'],'closed'=>[],default=>throw new InvalidArgumentException('Invalid ticket status.')};
}
function support_tickets_staff_events(SupportTicketDocument $doc):array {
    $data=$doc->data();$events=[['sequence'=>0,'id'=>$data['opening']['id'],'kind'=>'opened','actor'=>$data['created_by'],'at'=>$data['created_at'],'status'=>'open','body'=>$data['opening']['body']]];
    foreach($data['events'] as $i=>$event)$events[]=['sequence'=>$i+1,'id'=>$event['id'],'kind'=>$event['command'],'actor'=>$event['actor'],'at'=>$event['at'],'status'=>$event['arguments']['status']??'','body'=>$event['arguments']['body']??'','assignee'=>$event['arguments']['staff_id']??null];return $events;
}
function support_tickets_history_view(array $history,callable $url):void {
    echo '<section class="workspace-card ticket-history"><h2>Conversation history</h2><p class="hint">All times are UTC. Opening a conversation does not mark it read.</p>';
    foreach($history['events'] as $event){$private=in_array($event['kind'],['note','assign'],true);$label=match($event['kind']){'opened'=>'Conversation opened','reply'=>'Public reply','note'=>'Private staff note','assign'=>'Private assignment','status'=>'Status changed',default=>throw new LogicException('Unknown ticket event.')};
        echo '<article class="ticket-message'.($private?' ticket-private':'').'" id="ticket-event-'.esc($event['id']).'"><h3>'.esc($label).'</h3><p class="hint">'.esc(str_starts_with($event['actor'],'client:')?'Client':'Studio').($private?' · visible only to authorized staff':'').' · <time>'.esc(gmdate('Y-m-d H:i:s',$event['at'])).' UTC</time></p>';
        if($event['body']!=='')echo '<div class="ticket-body">'.nl2br(esc($event['body'])).'</div>';
        if($event['kind']==='status')echo '<p>'.esc(ucfirst($event['status'])).'</p>';
        if($event['kind']==='assign')echo '<p>Assigned to staff account #'.(int)$event['assignee'].'.</p>';echo '</article>';
    }
    echo '<nav class="button-row" aria-label="Conversation history pages">';if($history['page']>0)echo '<a class="btn outline" href="'.esc($url($history['page']-1)).'">Earlier updates</a>';if($history['page']<$history['last'])echo '<a class="btn outline" href="'.esc($url($history['page']+1)).'">Later updates</a>';echo '</nav></section>';
}
function support_tickets_related_view(array $links,bool $staff):void {
    if(!$links)return;echo '<section class="workspace-card"><h2>Related work</h2><ul>';
    foreach($links as $link){$url=$staff?match($link['kind']){'project'=>admin_url('studio-job',['id'=>$link['id']]),'invoice'=>admin_url('studio-job',['id'=>$link['job_id']]).'#invoice-'.$link['id'],'subscription'=>admin_url('service-agreement',['record'=>$link['id']])}:match($link['kind']){'project'=>studio_url(['job'=>$link['id']]),'invoice'=>studio_url(['invoice'=>$link['id']]),'subscription'=>studio_url(['agreement'=>$link['id']])};echo '<li><a href="'.esc($url).'">'.esc(ucfirst($link['kind']).' · '.$link['title']).'</a></li>';}
    echo '</ul></section>';
}
function support_tickets_link_fields(array $choices,array $values):void {
    foreach($choices as $kind=>$set){echo '<label>Related '.esc($kind).' (optional)<select name="related_'.esc($kind).'"><option value="">No related '.esc($kind).'</option>';$selected=$values['related_'.$kind]??'';$found=$selected==='';
        foreach($set['items'] as $item){$match=$selected===(string)$item['id'];$found=$found||$match;echo '<option value="'.esc((string)$item['id']).'"'.($match?' selected':'').'>'.esc($item['title']).'</option>';}
        if(!$found)echo '<option value="'.esc($selected).'" selected>Previously selected reference · review before saving</option>';echo '</select></label>';if($set['more'])echo '<p class="hint">Showing the latest 25 '.esc($kind).' records. You can also open a conversation without a related reference.</p>';
    }
}
function support_tickets_create_fields(array $values,array $choices):void {
    echo '<label>Subject<input name="subject" maxlength="180" required value="'.esc($values['subject']??'').'"></label><label>Your message<textarea name="body" maxlength="16384" rows="7" required>'.esc($values['body']??'').'</textarea></label>';support_tickets_link_fields($choices,$values);
}
function support_tickets_controls(string $id,int $revision,string $status,bool $staff,bool $support,bool $canWrite,array $staffChoices=[]):void {
    $values=support_tickets_form_values($_POST);$prefix=$staff?'tickets_':'client_tickets_';$commands=$staff?['reply'=>'Send a public reply','note'=>'Add a private staff note']:['reply'=>'Send a reply'];
    foreach($commands as $command=>$label){$action=$prefix.$command;$failed=($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&($_POST['action']??null)===$action;if((!$canWrite||$status==='closed')&&!$failed)continue;
        echo '<section class="workspace-card'.($command==='note'?' ticket-private':'').'"><h2>'.esc($label).'</h2><p>'.($command==='note'?'Private notes are visible only to staff with ticket access.':'This reply will be visible in the client conversation.').'</p>';
        if($failed)echo '<p class="notice warning">Your unsaved text and original request are preserved. Review any newer conversation updates before reloading to submit a new request.</p>';
        echo '<form method="post">';support_tickets_intent_fields($action,support_tickets_ui_intent($id,$revision,$action),$support);echo '<label>'.($command==='note'?'Private note':'Reply').'<textarea name="body" maxlength="16384" rows="5" required>'.esc($failed?$values['body']:'').'</textarea></label><button class="btn '.($command==='note'?'outline':'gold').'"'.(!$canWrite||$status==='closed'?' disabled':'').'>'.esc($label).'</button></form></section>';
    }
    if(!$canWrite||$status==='closed'||$support)return;
    if($staff){$action='tickets_assign';$intent=support_tickets_ui_intent($id,$revision,$action);echo '<section class="workspace-card"><h2>Assign this conversation</h2><form method="post">';support_tickets_intent_fields($action,$intent);echo '<label>Staff member<select name="staff_id" required><option value="">Choose an eligible staff member</option>';foreach($staffChoices as $choice)echo '<option value="'.(int)$choice['id'].'"'.(($_POST['action']??null)===$action&&$values['staff_id']===(string)$choice['id']?' selected':'').'>'.esc($choice['name']).'</option>';echo '</select></label><button class="btn outline">Assign conversation</button></form></section>';}
    $action=$prefix.'status';echo '<section class="workspace-card"><h2>Change ticket status</h2><form method="post">';support_tickets_intent_fields($action,support_tickets_ui_intent($id,$revision,$action));echo '<label>New status<select name="status" required><option value="">Choose a status</option>';foreach(support_tickets_statuses($status,$staff) as $value)echo '<option value="'.esc($value).'"'.(($_POST['action']??null)===$action&&$values['status']===$value?' selected':'').'>'.esc(ucfirst($value)).'</option>';echo '</select></label><p class="hint">Closed conversations cannot be reopened. Start a new conversation for further work.</p><label class="check"><input type="checkbox" name="confirm" value="1" required>I confirm this status change.</label><button class="btn outline">Save status</button></form></section>';
}
function support_tickets_read_form(string $id,int $revision,bool $staff):void {echo '<form method="post">';support_tickets_intent_fields($staff?'tickets_read':'client_tickets_read',['ticket_id'=>$id,'revision'=>$revision]);echo '<button class="btn outline ticket-read">Mark conversation through these displayed updates read</button></form>';}
