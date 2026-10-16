<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/ticket_notifications.php';
require_once CWP_CODE_ROOT.'/includes/layout/tickets.php';
function support_tickets_activity_section(string $audience,array $feed):void {
    $staff=$audience==='admin';echo '<section aria-labelledby="ticket-updates-title"><h2 id="ticket-updates-title">Unread support conversations</h2><p>Open an update to read the conversation. Read status changes only when you choose to mark the conversation read.</p>';
    foreach($feed['items'] as $event){$url=$staff?admin_url('ticket',['ticket'=>$event['ticket_id'],'focus'=>$event['event_id']]):studio_url(['ticket'=>$event['ticket_id'],'focus'=>$event['event_id']]);$url.='#ticket-event-'.$event['event_id'];$label=match($event['kind']){'opened'=>'Conversation opened','reply'=>str_starts_with($event['actor'],'staff:')?'Studio reply':'Client reply','note'=>'Private staff note','assign'=>'Private assignment','status'=>'Ticket status updated'};
        echo '<article class="workspace-card ticket-summary" data-ticket-event="'.esc($event['event_id']).'"><span class="eyebrow">'.esc($label).'</span><h3><a href="'.esc($url).'">'.esc($event['subject']).'</a></h3><p>'.esc(gmdate('Y-m-d H:i:s',$event['at'])).' UTC</p><form method="post" action="'.esc($staff?admin_url('ticket',['ticket'=>$event['ticket_id']]):studio_url(['ticket'=>$event['ticket_id']])).'">';support_tickets_intent_fields($staff?'tickets_read':'client_tickets_read',['ticket_id'=>$event['ticket_id'],'revision'=>$event['sequence']]);echo '<button class="btn outline ticket-read">Mark conversation through this update read</button></form></article>';
    }
    if(!$feed['items'])echo '<p>No unread support conversations.</p>';if($feed['next']!=='')echo '<p><a class="btn outline" href="'.esc(activity_url($audience,['tickets_before'=>$feed['next']])).'">Older ticket updates</a></p>';echo '</section>';
}
function support_tickets_activity_only(string $audience,array $feed,string $error):never {
    if($audience==='admin')admin_head('Workspace activity','activity',support_tickets_staff());else{site_head('Workspace activity','portal');echo '<section class="section"><div class="container"><h1>Workspace activity</h1>';show_flash();}
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';support_tickets_activity_section($audience,$feed);
    if($audience==='admin'&&!activity_ready()&&staff_can('staff.manage')){echo '<section class="workspace-card"><h2>Enable project updates too</h2><p>Add project messages, reviews and files to this activity center.</p><form method="post">';admin_action('activity_setup');admin_input('password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn outline">Enable project activity</button></form></section>';}
    if($audience==='admin')admin_foot();else{echo '</div></section>';site_foot(false);}exit;
}
