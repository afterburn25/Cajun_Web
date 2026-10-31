<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function support_admin_action(string $action): never {
    check_csrf();$actor=require_admin();
    if(!consume_rate('support-management-'.$actor['id'],30,900))throw new RuntimeException('Please wait before opening or changing more support sessions.');
    if($action==='support_migrate'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the backup and your client support-access policy.');
        support_migrate();flash('Owner account review is ready. Staff support access remains restricted until approved.');
    }elseif($action==='support_policy'){
        support_policy(gint($_POST,'client_id',1,2147483647),isset($_POST['allowed']));flash('Staff support policy saved. Existing sessions for this client were ended if restricted.');
    }elseif($action==='support_start'){
        $id=support_start(gint($_POST,'client_id',1,2147483647));redirect(site_url('portal/index.php?'.http_build_query(['support'=>$id])));
    }else throw new InvalidArgumentException('Unknown support action.');
    redirect(admin_url('support'));
}
function support_open_form(int $clientId): void {
    if(!support_can('support.login'))return;
    $owner=support_owner_review_allowed(admin_user());
    if(!$owner){
        $allowed=one('SELECT allowed FROM '.table('support_clients').' WHERE client_id=?',[$clientId]);
        if((int)($allowed['allowed']??0)!==1)return;
    }
    ?><form method="post" action="<?=esc(admin_url('support'))?>"><?php admin_action('support_start');?><input type="hidden" name="client_id" value="<?=(int)$clientId?>"><button class="btn gold"><?=$owner?'Open client portal (owner review)':'Open client portal'?></button></form><?php
}
function support_admin_render(array $user,string $error): never {
    admin_head('Client support','support',$user);
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(!staff_ready()||!studio_ready()){
        echo '<div class="notice info">Enable administrator accounts and the client workspace first. Owners can use Administrators and Version &amp; database.</div>';admin_foot();exit;
    }
    if(!support_ready()||setting('support_policy_confirmed')!=='1'){
        if(staff_can('staff.manage',$user)){?><section class="workspace-card"><h2>Enable support sessions</h2><p>Owner account review lets you open any active client portal with your existing admin session and an audit trail. Other staff remain restricted to clients approved for support. Explain owner administration and staff access in your service terms and privacy notice.</p><form method="post"><?php admin_action('support_migrate');admin_check('confirm','I have a current backup and will disclose owner review and restricted staff support access to clients.',false);?><button class="btn gold">Enable support sessions</button></form></section><?php }
        else echo '<p>Ask an owner to enable and configure client support.</p>';
        admin_foot();exit;
    }
    ?><p class="admin-intro">Owners can review any active client portal without the client password or a second admin password. Other staff can open only clients explicitly approved for support. All sessions are audited, end after 15 minutes (5 minutes idle), and cannot approve contracts, submit payments or change client security. One client review at a time per admin session.</p><?php
    if(support_binding()!=='')foreach(all('SELECT s.*,c.name FROM '.table('support_sessions').' s JOIN '.table('studio_clients').' c ON c.id=s.client_id WHERE s.binding_hash=? AND s.ended_at IS NULL',[support_binding()]) as $open){?><div class="notice info">Your support session for <?=esc($open['name'])?>: <a href="<?=esc(site_url('portal/index.php?'.http_build_query(['support'=>$open['id']])))?>">Resume or return from this session</a>.</div><?php }
    $owner=support_owner_review_allowed($user);
    $page=gint($_GET,'support_page',1,100000,1);
    $offset=($page-1)*100;
    $sql='SELECT c.*,p.allowed FROM '.table('studio_clients').' c LEFT JOIN '.table('support_clients').' p ON p.client_id=c.id'.($owner?'':' WHERE c.active=1 AND p.allowed=1').' ORDER BY c.id DESC LIMIT 101 OFFSET '.$offset;
    $clients=all($sql);$more=count($clients)>100;$clients=array_slice($clients,0,100);
    ?><h2>Client accounts</h2><div class="workspace-grid"><?php foreach($clients as $client){?><section class="workspace-card"><h3><?=esc($client['name'])?></h3><p><?=esc($client['email'])?></p><p><?=(int)$client['active']!==1?'Account disabled':((int)($client['allowed']??0)===1?'Staff support permitted':'Staff support restricted')?></p><?php
        if($owner){?><form method="post"><?php admin_action('support_policy');studio_hidden('client_id',$client['id']);admin_check('allowed','Allow assigned staff to open this client under their support agreement',$client['allowed']??0);?><button class="btn outline">Save staff access policy</button></form><?php }
        if((int)$client['active']===1)support_open_form((int)$client['id']);?></section><?php }?></div>
    <nav aria-label="Client account pages"><?php if($page>1):?><a href="<?=esc(admin_url('support',['support_page'=>$page-1]))?>">Previous clients</a> <?php endif;if($more):?><a href="<?=esc(admin_url('support',['support_page'=>$page+1]))?>">Next clients</a><?php endif;?></nav>
    <section class="workspace-card"><h2>Support activity</h2><p>Recent 100 events. Records retain the real staff member, client and support session. Message contents and credentials are not stored in this audit.</p><div class="table-wrap support-audit"><table><thead><tr><th>Time (UTC)</th><th>Staff / client</th><th>Action / object</th><th>Result</th><th>Session</th></tr></thead><tbody><?php
    $events=all('SELECT e.*,u.name AS actor_name,c.name AS client_name FROM '.table('support_events').' e LEFT JOIN '.table('users').' u ON u.id=e.actor_id LEFT JOIN '.table('studio_clients').' c ON c.id=e.client_id'.($owner?'':' WHERE e.actor_id=?').' ORDER BY e.id DESC LIMIT 100',$owner?[]:[$user['id']]);
    foreach($events as $e){?><tr><td><?=esc(gmdate('Y-m-d H:i:s',(int)$e['occurred_at']))?></td><td><?=esc(($e['actor_name']??'Staff '.$e['actor_id']).' / '.($e['client_name']??'Policy'))?></td><td><?=esc($e['action'].' / '.$e['object_type'].' '.$e['object_id'])?></td><td><?=esc($e['outcome'])?></td><td><?=esc($e['support_id']??'—')?></td></tr><?php }?></tbody></table></div></section><?php admin_foot();exit;
}
function support_banner(): void {
    $ctx=support_current();if(!$ctx)return;
    ?><aside class="support-banner" aria-label="Support session"><div class="container"><strong><?=($ctx['session']['context']==='Owner account review')?'Owner review':'Support session'?> · <?=esc($ctx['client']['name'])?></strong><p>Staff: <?=esc($ctx['actor']['name'])?> · Ends <?=esc(gmdate('H:i',(int)$ctx['session']['expires_at']))?> UTC (5-minute idle limit). Actions are audited as staff. Client approvals, payments and security changes are blocked.</p><form method="post" action="<?=esc(studio_url())?>"><?=support_csrf_field()?><input type="hidden" name="action" value="support_end"><button class="btn gold">Return to admin</button></form></div></aside><?php
}
function support_unavailable(): never {
    http_response_code(403);login_head('Support session unavailable');echo '<p>This session ended or your access changed. It cannot switch to another client session.</p><a class="btn gold" href="'.esc(admin_url('support')).'">Return to admin</a>';login_foot();exit;
}
