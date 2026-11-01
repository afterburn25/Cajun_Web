<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$clientId=(int)$client['id'];
if(array_key_exists('ticket_new',$_GET)){
    if($support!==null)throw new RuntimeException('Open conversations from the staff desk.');$failed=($_POST['action']??null)==='client_tickets_create';$values=$failed?support_tickets_form_values($_POST):[];
    echo '<section class="workspace-card"><h2>Start a conversation</h2><p>Tell us what you need help with. You can link this conversation to your web design project, invoice or service agreement.</p>';if($failed)echo '<p class="notice warning">Your unsaved text and original request are preserved below.</p>';
    echo '<form method="post">';support_tickets_intent_fields('client_tickets_create',['ticket_id'=>$failed?$values['ticket_id']:bin2hex(random_bytes(16)),'opening_id'=>$failed?$values['opening_id']:bin2hex(random_bytes(16))]);support_tickets_create_fields($values,support_tickets_link_choices($clientId,false));echo '<button class="btn gold">Create conversation</button></form></section>';return;
}
if(array_key_exists('ticket',$_GET)){
    $id=support_tickets_form_id($_GET,'ticket');$item=support_tickets_client_get($id,$clientId,$support);$history=support_tickets_history_page($item['events'],$_GET);
    echo '<section class="workspace-card ticket-summary"><span class="workspace-status">'.esc(ucfirst($item['status'])).'</span><h2>'.esc($item['subject']).'</h2>';if($support===null)echo '<p>'.(int)$item['read']['unread'].' unread updates</p>';if($item['status']==='closed')echo '<p>This conversation is closed. Open a new conversation for further work.</p>';echo '</section>';
    support_tickets_related_view($item['related'],false);support_tickets_history_view($history,fn($p)=>studio_url(['ticket'=>$id,'history'=>$p]));if($support===null)support_tickets_read_form($id,$history['events'][array_key_last($history['events'])]['sequence'],false);
    support_tickets_controls($id,$item['public_revision'],$item['status'],false,$support!==null,$support===null||support_can('support.write'));return;
}
$page=support_tickets_form_number(['p'=>$_GET['p']??'0'],'p',10000);$status=support_tickets_form_text($_GET,'status',16,false,true);$result=support_tickets_client_page($clientId,$page,$status?:null,$support);
echo '<form method="get" class="ticket-filters"><input type="hidden" name="tickets" value="1">';if($support!==null)echo '<input type="hidden" name="support" value="'.esc($support).'">';admin_select('status','Status',[''=>'All statuses','open'=>'Open','pending'=>'Pending','resolved'=>'Resolved','closed'=>'Closed'],$status);echo '<button class="btn outline">Filter conversations</button></form>';
foreach($result['items'] as $item){echo '<article class="workspace-card ticket-summary"><span class="workspace-status">'.esc(ucfirst($item['status'])).'</span><h2><a href="'.esc(studio_url(['ticket'=>$item['id']])).'">'.esc($item['subject']).'</a></h2><p>';if($support===null)echo (int)$item['read']['unread'].' unread updates · ';echo esc(gmdate('Y-m-d H:i:s',$item['updated_at'])).' UTC</p></article>';}
if(!$result['items'])echo '<section class="workspace-card"><h2>No conversations on this page</h2><p>Open a conversation when you need help with your website or services.</p></section>';
echo '<nav class="ticket-toolbar" aria-label="Ticket pages">';if($page>0)echo '<a class="btn outline" href="'.esc(studio_url(['tickets'=>1,'p'=>$page-1,'status'=>$status])).'">Previous</a>';if($result['more'])echo '<a class="btn outline" href="'.esc(studio_url(['tickets'=>1,'p'=>$page+1,'status'=>$status])).'">Next</a>';echo '</nav>';
