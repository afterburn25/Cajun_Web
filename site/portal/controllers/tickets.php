<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/ticket_choices.php';
require_once CWP_CODE_ROOT.'/includes/layout/tickets.php';
function support_tickets_portal_action(string $action,array $client):never {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('Submit the ticket form to continue.');$ctx=support_current();$support=$ctx?$ctx['session']['id']:null;if($support!==null)support_check_csrf($support);else check_csrf();
    $clientId=(int)$client['id'];$id=support_tickets_form_id($_POST,'ticket_id');
    if($action==='client_tickets_create'){
        if($support!==null)throw new RuntimeException('Open support conversations from the staff desk.');
        if(!consume_rate('ticket-create-client-'.$clientId,20,300))throw new RuntimeException('Please wait before opening more conversations.');
        support_tickets_client_create($id,$clientId,support_tickets_form_text($_POST,'subject',180),support_tickets_form_id($_POST,'opening_id'),support_tickets_form_text($_POST,'body',16384,true),support_tickets_form_links($_POST));flash('Your conversation was created.');redirect(studio_url(['ticket'=>$id]));
    }
    if(!is_string($_GET['ticket']??null)||$_GET['ticket']!==$id)throw new InvalidArgumentException('Reload the conversation you intend to change.');
    if($action==='client_tickets_read'){support_tickets_client_mark($id,$clientId,support_tickets_form_number($_POST,'revision',SupportTicketDocument::MAX_EVENTS+1,1),$support);flash('Your conversation read status was saved.');redirect(studio_url(['ticket'=>$id]));}
    $intent=support_tickets_form_intent($_POST);$command=match($action){'client_tickets_reply'=>'reply','client_tickets_status'=>'status',default=>throw new InvalidArgumentException('Unknown ticket action.')};
    if($command==='status')support_tickets_form_confirm($_POST);$args=$command==='reply'?['body'=>support_tickets_form_text($_POST,'body',16384,true)]:['status'=>support_tickets_form_text($_POST,'status',16)];
    if(!consume_rate('ticket-change-'.($ctx?'support-'.$ctx['actor']['id']:'client-'.$clientId),40,300))throw new RuntimeException('Please wait before sending more ticket changes.');
    support_tickets_client_change($id,$clientId,$intent['revision'],$intent['request_id'],$command,$args,$support);flash($support?'Reply saved as Studio support.':'Your conversation was updated.');redirect(studio_url(['ticket'=>$id]));
}
function support_tickets_portal_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');$ctx=support_current();$support=$ctx?$ctx['session']['id']:null;
    try{support_tickets_locked(fn()=>support_tickets_portal_identity((int)$client['id'],$support));}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Support tickets are unavailable for this session.',403);}
    site_head('Your support conversations','portal');support_banner();echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR CLIENT WORKSPACE</span><h1>Support <span class="gold-text">conversations</span></h1><div class="ticket-toolbar"><a class="btn outline" href="'.esc(studio_url()).'">All projects</a><a class="btn outline" href="'.esc(studio_url(['tickets'=>1])).'">All tickets</a>';if(!$support)echo '<a class="btn gold" href="'.esc(studio_url(['ticket_new'=>1])).'">Open a conversation</a>';echo '</div></div></section><section class="section"><div class="container">';show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    try{require CWP_CODE_ROOT.'/portal/views/tickets.php';}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';}
    echo '</div></section>';site_foot(false);exit;
}
