<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/marketing/proposals.php';

function proposals_admin_action(string $action): never {
    if($action==='proposal_setup'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you saved a current database and private-storage backup.');
        proposals_migrate(posted('owner_password',72));flash('Proposals enabled.');redirect(admin_url('proposals'));
    }
    if($action==='proposal_create'){
        $id=proposal_create(gint($_POST,'client_id',1,2147483647),$_POST,posted('request_key',64));flash('Draft proposal saved.');redirect(admin_url('proposal',['id'=>$id]));
    }
    if($action==='proposal_revise'){
        $id=posted('id',32);proposal_revise($id,$_POST);flash('A new proposal version was saved.');redirect(admin_url('proposal',['id'=>$id]));
    }
    if($action==='proposal_send'){
        $id=posted('id',32);proposal_send($id);flash('Proposal sent to the client for review.');redirect(admin_url('proposal',['id'=>$id]));
    }
    if($action==='proposal_withdraw'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm withdrawal of this proposal.');
        $id=posted('id',32);proposal_withdraw($id);flash('Proposal withdrawn.');redirect(admin_url('proposal',['id'=>$id]));
    }
    throw new InvalidArgumentException('Unknown proposal action.');
}

function proposal_version_fields(array $v=[]): void {
    echo '<label>Proposal title<input name="title" maxlength="160" required value="'.esc($v['title']??'').'"></label>';
    echo '<label>Deliverables — one per line<textarea name="deliverables" rows="6" required>'.esc($v['deliverables_text']??'').'</textarea></label>';
    echo '<label>Exclusions — one per line<textarea name="exclusions" rows="4">'.esc($v['exclusions_text']??'').'</textarea></label>';
    echo '<label>Milestones — "Label | amount" per line, USD<textarea name="milestones" rows="5" placeholder="Design and build | 1200.00">'.esc($v['milestones_text']??'').'</textarea></label>';
    echo '<label>Delivery schedule<textarea name="schedule_notes" maxlength="6000" rows="4" required>'.esc($v['schedule_notes']??'').'</textarea></label>';
}
function proposal_version_prefill(array $v): array {
    return ['title'=>$v['title'],'deliverables_text'=>implode("\n",json_decode($v['deliverables'],true)),'exclusions_text'=>implode("\n",json_decode($v['exclusions'],true)?:[]),'milestones_text'=>implode("\n",array_map(fn($m)=>$m['label'].' | '.number_format($m['amountCents']/100,2,'.',''),json_decode($v['milestones'],true)?:[])),'schedule_notes'=>$v['schedule_notes']];
}
function proposal_version_card(array $v,bool $current): void {
    $total=array_sum(array_column(json_decode($v['milestones'],true)?:[],'amountCents'));
    echo '<article class="workspace-version"><span class="eyebrow">VERSION '.(int)$v['version'].($current?' · CURRENT':' · SUPERSEDED').'</span><h3>'.esc($v['title']).'</h3><ul class="delivery-manifest">';
    foreach(json_decode($v['deliverables'],true) as $d)echo '<li>'.esc($d).'</li>';
    echo '</ul>';$exclusions=json_decode($v['exclusions'],true)?:[];if($exclusions){echo '<h4>Exclusions</h4><ul class="delivery-manifest">';foreach($exclusions as $x)echo '<li>'.esc($x).'</li>';echo '</ul>';}
    $milestones=json_decode($v['milestones'],true)?:[];if($milestones){echo '<h4>Milestones</h4><ul class="delivery-manifest">';foreach($milestones as $m)echo '<li>'.esc($m['label']).' — $'.number_format($m['amountCents']/100,2).'</li>';echo '</ul><p><strong>Total $'.number_format($total/100,2).' USD</strong></p>';}
    echo '<div class="prose">'.nl2br(esc($v['schedule_notes'])).'</div><p class="delivery-hash">SHA-256: <code>'.esc($v['content_hash']).'</code> · '.esc($v['created_at']).' UTC</p></article>';
}
function proposal_status(string $status): string {return ['draft'=>'Draft','sent'=>'Awaiting client decision','changes-requested'=>'Changes requested','approved'=>'Approved','withdrawn'=>'Withdrawn'][$status]??$status;}

function proposals_admin_render(string $view,array $user,string $error): never {
    admin_head($view==='proposal'?'Proposal details':'Client proposals',$view,$user);if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(!proposals_ready()){
        echo '<section class="section"><div class="container"><div class="workspace-card"><h2>Enable proposals</h2><p>Proposals store versioned scope, deliverables, exclusions, milestones and schedules for a client workspace account. The client approves or requests changes against an exact version; every transition and decision is attributed in an append-only history. This is a commercial record — not a legal e-signature and not a payment authorization.</p><p>Setup adds proposal tables only and fails if unrecognized proposal records already exist.</p>';
        if(staff_can('staff.manage')){echo '<form method="post">';admin_action('proposal_setup');admin_check('confirm','I saved a current database and private-storage backup.',false);admin_input('owner_password','Current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable proposals</button></form>';}
        else echo '<p>An owner must enable proposals.</p>';
        echo '</div></div></section>';admin_foot();exit;
    }
    if($view==='proposal'){
        $row=proposal_row(is_string($_GET['id']??null)?$_GET['id']:'');
        $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$row['client_id']]);
        $versions=proposal_versions($row['id']);$current=proposal_version_row($row,(int)$row['current_version']);
        echo '<section class="section"><div class="container"><p><a href="'.esc(admin_url('proposals')).'">← All proposals</a></p><div class="workspace-card"><span class="workspace-status">'.esc(proposal_status($row['status'])).'</span><h2>'.esc($row['title']).'</h2><p>'.esc($client['name']??'Removed account').' · '.esc($client['email']??'').'</p><p>Created '.esc($row['created_at']).' UTC'.($row['sent_at']?' · Sent '.esc($row['sent_at']).' UTC':'').($row['decided_at']?' · Decided '.esc($row['decided_at']).' UTC':'').'</p>';
        $decision=one('SELECT * FROM '.table('proposal_decisions').' WHERE proposal_id=? ORDER BY version DESC LIMIT 1',[$row['id']]);
        if($decision)echo '<p><strong>'.esc($decision['decision']==='approved'?'Client approved version '.(int)$decision['version']:'Client requested changes on version '.(int)$decision['version']).'</strong>'.($decision['note']!==''?' — '.esc($decision['note']):'').'</p>';
        echo '</div>';
        if(in_array($row['status'],['draft','changes-requested'],true)){
            echo '<div class="workspace-card"><h3>'.($row['status']==='draft'?'Edit as a new version':'Revise after change request').'</h3><p>Changes never edit a sent version — they create the next numbered version so an approval can never apply to silently-changed terms.</p><form method="post">';admin_action('proposal_revise');studio_hidden('id',$row['id']);proposal_version_fields(proposal_version_prefill($current));echo '<button class="btn gold">Save version '.((int)$row['current_version']+1).'</button></form></div>';
        }
        if($row['status']==='draft'){echo '<form method="post" class="inline">';admin_action('proposal_send');studio_hidden('id',$row['id']);echo '<button class="btn gold">Send current version to client</button></form>';}
        if(in_array($row['status'],['draft','sent','changes-requested'],true)){echo '<form method="post" class="inline">';admin_action('proposal_withdraw');studio_hidden('id',$row['id']);admin_check('confirm','Withdraw this proposal. Approved proposals cannot be withdrawn.',false);echo '<button class="btn outline">Withdraw</button></form>';}
        foreach($versions as $v)proposal_version_card($v,(int)$v['version']===(int)$row['current_version']);
        echo '<div class="workspace-card"><h3>History</h3>';foreach(proposal_history($row['id']) as $e)echo '<p class="workspace-message"><small>'.esc($e['created_at']).' UTC · '.esc($e['actor_type'].' #'.$e['actor_id']).'</small> '.esc($e['event'].($e['detail']!==''?' · '.$e['detail']:'')).'</p>';echo '</div>';
        echo '</div></section>';admin_foot();exit;
    }
    echo '<section class="section"><div class="container">';
    echo '<div class="workspace-card"><h2>New proposal</h2><form method="post">';admin_action('proposal_create');studio_hidden('request_key',hash('sha256',random_bytes(32)));
    $clients=all('SELECT id,name,email FROM '.table('studio_clients').' ORDER BY name LIMIT 200');
    echo '<label>Client workspace account<select name="client_id" required><option value="">Choose a client…</option>';foreach($clients as $c)echo '<option value="'.(int)$c['id'].'">'.esc($c['name'].' · '.$c['email']).'</option>';echo '</select></label>';
    proposal_version_fields();echo '<button class="btn gold">Save draft</button></form></div>';
    $rows=proposals_all();
    echo '<div class="workspace-grid">';foreach($rows as $r)echo '<article class="workspace-card"><span class="workspace-status">'.esc(proposal_status($r['status'])).'</span><h3><a href="'.esc(admin_url('proposal',['id'=>$r['id']])).'">'.esc($r['title']).'</a></h3><p>'.esc($r['client_name']).' · v'.(int)$r['current_version'].'</p><p>'.esc($r['created_at']).' UTC</p></article>';
    if(!$rows)echo '<p>No proposals yet. Draft scope, pricing milestones and schedules, then send for client review.</p>';
    echo '</div></div></section>';admin_foot();exit;
}

/** Portal list of proposals on the workspace landing. */
function proposals_portal_section(array $client): void {
    $rows=proposals_for_client((int)$client['id']);if(!$rows)return;
    echo '<div class="workspace-card"><h2>Studio proposals</h2>';foreach($rows as $r)echo '<p><a class="btn outline" href="'.esc(studio_url(['proposal'=>$r['id']])).'">'.esc($r['title']).' — '.esc(proposal_status($r['status'])).' →</a></p>';echo '</div>';
}

/** Portal detail + decision surface for the owning client. */
function proposals_portal_render(string $error): never {
    if(!proposals_ready())fail_page('Proposal not found.',404);
    if(!$client=studio_client())fail_page('Sign in to review proposals.',403);
    try{$row=proposal_client_row(is_string($_GET['proposal']??null)?$_GET['proposal']:'',$client);}catch(InvalidArgumentException){fail_page('Proposal not found.',404);}
    if(!in_array($row['status'],['sent','changes-requested','approved'],true))fail_page('Proposal not found.',404);
    $versions=proposal_versions($row['id']);$current=proposal_version_row($row,(int)$row['current_version']);
    site_head('Proposal · '.$row['title'],'portal');support_banner();
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">STUDIO PROPOSAL</span><h1>'.esc($row['title']).'</h1><p><a class="btn outline" href="'.esc(studio_url()).'">← Your workspace</a></p></div></section><section class="section"><div class="container">';
    show_flash();if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    echo '<div class="workspace-card"><span class="workspace-status">'.esc(proposal_status($row['status'])).'</span><p>Version '.(int)$row['current_version'].($row['sent_at']?' · sent '.esc($row['sent_at']).' UTC':'').'</p>';
    if($row['status']==='sent'&&!support_current()){
        echo '<h3>Your decision</h3><p>Approving records your confirmation of the scope and milestone amounts in version '.(int)$row['current_version'].' exactly as shown. This is a commercial confirmation — it is not a legal e-signature and does not authorize charges.</p><form method="post">';echo support_csrf_field();echo '<input type="hidden" name="action" value="proposal_decide"><input type="hidden" name="proposal_id" value="'.esc($row['id']).'">';
        echo '<label>Decision<select name="choice"><option value="approve">Approve this version</option><option value="changes">Request changes</option></select></label><label>Notes for the studio — required when requesting changes<textarea name="note" maxlength="2000" rows="4"></textarea></label><label class="check"><input type="checkbox" name="confirm" value="1" required>I reviewed this version and confirm my decision applies to it exactly.</label><button class="btn gold">Record decision</button></form>';
    }elseif($row['status']==='changes-requested')echo '<p>You requested changes — the studio is preparing the next version.</p>';
    elseif($row['status']==='approved')echo '<p><strong>You approved version '.(int)$row['current_version'].'.</strong></p>';
    echo '</div>';
    foreach(array_reverse($versions) as $v)proposal_version_card($v,(int)$v['version']===(int)$row['current_version']);
    echo '</div></section>';site_foot(false);exit;
}
