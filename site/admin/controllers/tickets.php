<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/ticket_choices.php';
require_once CWP_CODE_ROOT.'/includes/layout/tickets.php';
function support_tickets_admin_action(string $action):never {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('Submit the ticket form to continue.');check_csrf();
    if($action==='tickets_setup'){support_tickets_migrate(posted('current_password',200));flash('Support tickets are ready.');redirect(admin_url('tickets'));}
    $actor=support_tickets_staff();
    if($action==='tickets_create'){
        $id=support_tickets_form_id($_POST,'ticket_id');$client=support_tickets_form_number($_POST,'client_id',2147483647,1);
        if(!is_string($_GET['client']??null)||$_GET['client']!==(string)$client)throw new InvalidArgumentException('Return to the originally selected client before saving this draft.');
        if(!consume_rate('ticket-create-staff-'.$actor['id'],20,300))throw new RuntimeException('Please wait before opening more conversations.');
        support_tickets_staff_create($id,$client,support_tickets_form_text($_POST,'subject',180),support_tickets_form_id($_POST,'opening_id'),support_tickets_form_text($_POST,'body',16384,true),support_tickets_form_links($_POST));
        flash('Client conversation created.');redirect(admin_url('ticket',['ticket'=>$id]));
    }
    $id=support_tickets_form_id($_POST,'ticket_id');if(!is_string($_GET['ticket']??null)||$_GET['ticket']!==$id)throw new InvalidArgumentException('Reload the conversation you intend to change.');
    if($action==='tickets_read'){support_tickets_staff_mark($id,support_tickets_form_number($_POST,'revision',SupportTicketDocument::MAX_EVENTS));flash('Your conversation read status was saved.');redirect(admin_url('ticket',['ticket'=>$id]));}
    $intent=support_tickets_form_intent($_POST);$command=match($action){'tickets_reply'=>'reply','tickets_note'=>'note','tickets_assign'=>'assign','tickets_status'=>'status',default=>throw new InvalidArgumentException('Unknown ticket action.')};
    $args=match($command){'reply','note'=>['body'=>support_tickets_form_text($_POST,'body',16384,true)],'assign'=>['staff_id'=>support_tickets_form_number($_POST,'staff_id',2147483647,1)],'status'=>['status'=>support_tickets_form_text($_POST,'status',16)]};
    if($command==='status')support_tickets_form_confirm($_POST);
    if(!consume_rate('ticket-change-staff-'.$actor['id'],40,300))throw new RuntimeException('Please wait before sending more ticket changes.');
    support_tickets_staff_change($id,$intent['revision'],$intent['request_id'],$command,$args);flash($command==='note'?'Private staff note saved.':'Conversation updated.');redirect(admin_url('ticket',['ticket'=>$id]));
}
function support_tickets_admin_render(string $view,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{$actor=support_tickets_staff(false);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;fail_page('Ticket access is unavailable for this session.',403);}
    admin_head('Client support tickets','tickets',$actor);if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(!support_tickets_ready()){
        echo '<section class="workspace-card"><h2>Prepare support tickets</h2><p>Keep client conversations and private staff notes together, with separate read status for each person. Existing projects and invoices are preserved.</p>';
        if($actor['role']==='owner'){echo '<form method="post">';admin_action('tickets_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable support tickets</button></form>';}
        else echo '<p>Ask an owner to prepare support tickets.</p>';echo '</section>';admin_foot();exit;
    }
    try{require CWP_CODE_ROOT.'/admin/views/tickets.php';}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';}
    admin_foot();exit;
}
