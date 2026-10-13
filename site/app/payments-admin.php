<?php
declare(strict_types=1);
defined('CWP_BOOT') || exit;
require_once __DIR__.'/payment-trial-view.php';
require_once __DIR__.'/payment-notification-view.php';
function payments_admin_action(string $action): never {
    check_csrf();
    if(str_starts_with($action,'payments_notification_'))notifications_admin_action($action);
    if(str_starts_with($action,'payments_trial_'))attempts_admin_action($action);
    if($action==='payments_migrate'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you have a current backup.');
        payments_migrate(posted('owner_password',1000));flash('Payment preparation enabled. Client checkout remains disabled.');redirect(admin_url('payments'));
    }
    if(in_array($action,['payments_save','payments_clear','payments_verify'],true)){
        $env=posted('environment',10);$rev=gint($_POST,'revision',0,2147483647);$password=posted('owner_password',1000);
        if($action==='payments_verify'){
            $status=payments_verify($env,$rev,$password);flash(match($status){'authenticated'=>'Sandbox API Login ID and Transaction Key authenticated. This does not verify the Signature Key, Public Client Key or a payment.','rejected'=>'Sandbox authentication was rejected. Check your sandbox credentials.',default=>'Sandbox connection could not be verified. Check PHP cURL, TLS and network access; retry later.'},$status==='authenticated'?'success':'warning');
        }else{
            if($action==='payments_clear'&&posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm removal of credentials for this environment.');
            $input=[];foreach(payments_fields() as $key=>$label)$input[$key]=posted($key,128);
            payments_save($env,$rev,$input,$password,$action==='payments_clear');flash($action==='payments_clear'?'Credentials removed for this environment.':'Credentials protected and saved. Connection status reset; client checkout remains disabled.');
        }
        redirect(admin_url('payment-settings',['environment'=>$env]));
    }
    payments_require();
    if($action==='payments_prepare'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the invoice amount and replacement of any earlier request.');
        payments_prepare(gint($_POST,'invoice_id',1,2147483647),posted('purpose',20),gint($_POST,'days',1,30),posted('invoice_hash',64),posted('request_key',64));
        flash('Payment request prepared. No charge was made and no payment email was sent.');redirect(admin_url('payments'));
    }
    if($action==='payments_revoke'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm revocation of this request.');payments_revoke(gint($_POST,'request_id',1,2147483647));flash('Request revoked. Invoice and payment status are unchanged.');redirect(admin_url('payments'));
    }
    throw new InvalidArgumentException('Unknown payment preparation action.');
}
function payments_admin_render(string $view,array $user,string $error): never {
    if($view==='payment-notifications')notifications_admin_render($user,$error);
    if($view==='payment-trials')attempts_admin_render($user,$error);
    if($view==='payments'&&!staff_can('clients.manage',$user)){http_response_code(403);admin_head('Payment requests',$view,$user);echo '<p>Payment-request and client-workspace permissions are both required.</p>';admin_foot();exit;}
    admin_head($view==='payment-settings'?'Authorize.net settings':'Payment requests',$view,$user);
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    echo '<p class="notice">Preparation only · Client checkout and production processing are disabled. Existing invoices remain payable through the studio’s manual instructions.</p>';
    if(!payments_ready()){
        if(staff_ready()&&studio_ready()&&staff_can('staff.manage',$user)){?><section class="workspace-card"><h2>Enable payment preparation</h2><p>Create protected gateway settings and invoice request records. Existing client, catalog and invoice records are retained.</p><form method="post"><?php admin_action('payments_migrate');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current file and database backup.',false);?><button class="btn gold">Enable payment preparation</button></form></section><?php }
        else echo '<p>Ask an owner to enable the client workspace, administrator accounts and payment preparation.</p>';
        admin_foot();exit;
    }
    if($view==='payment-settings'){
        $env=is_string($_GET['environment']??null)&&$_GET['environment']==='production'?'production':'sandbox';$r=payments_gateway($env);
        ?><p><a class="btn outline" href="<?=esc(admin_url('payment-settings',['environment'=>'sandbox']))?>">Sandbox</a> <a class="btn outline" href="<?=esc(admin_url('payment-settings',['environment'=>'production']))?>">Production</a></p><section class="workspace-card"><h2><?=esc(ucfirst($env))?> credentials</h2><p><strong>Connection status: <?=esc(str_replace('_',' ',$r['validation']))?></strong><?php if($r['validated_at'])echo ' · '.esc($r['validated_at']).' UTC';?></p><p>All four saved fields remain hidden. Leave a field blank to keep its saved value. Replacing any value requires a fresh connection check. Production credentials may be stored separately; production connections and payments cannot be enabled here.</p><p>The credential master key requires owner-only private storage outside the web document root. Keep its protected backup separately from database and website backups. Losing that key makes saved credentials unreadable; never regenerate it to repair a missing key.</p><form method="post" autocomplete="off"><?php admin_action('payments_save');studio_hidden('environment',$env);studio_hidden('revision',$r['revision']);foreach(payments_fields() as $field=>$label)admin_input($field,$label,'','password','maxlength="128" autocomplete="new-password"');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Save <?=esc(ucfirst($env))?> credentials</button></form></section>
        <section class="workspace-card"><h2>Connection check</h2><p>This checks only the sandbox API Login ID and Transaction Key. A successful response is not a card payment and does not verify the Signature Key, Public Client Key, webhooks or production readiness.</p><?php if($env==='sandbox'&&$r['revision']&&$r['validation']!=='not_configured'){?><form method="post"><?php admin_action('payments_verify');studio_hidden('environment',$env);studio_hidden('revision',$r['revision']);admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn outline">Check sandbox authentication</button></form><?php }else echo '<p>'.($env==='production'?'Production checks are disabled.':'Save sandbox credentials first.').'</p>';?></section>
        <?php if($r['revision']&&$r['validation']!=='not_configured'){?><section class="workspace-card"><h2>Remove credentials</h2><form method="post"><?php admin_action('payments_clear');studio_hidden('environment',$env);studio_hidden('revision',$r['revision']);admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','Remove all four credentials for this environment.',false);?><button class="btn outline">Remove <?=esc(ucfirst($env))?> credentials</button></form></section><?php }
        admin_foot();exit;
    }
    ?><p>Prepare one request for the full amount of an existing invoice. For a deposit, milestone or final balance, issue an invoice for that agreed amount first. These labels do not calculate a remaining project balance or create a subscription. Requests are visible only in the client’s signed-in project; private payment links and checkout are pending.</p><?php
    $id=is_string($_GET['invoice_id']??null)?(int)$_GET['invoice_id']:0;
    if($id){try{$i=payments_invoice($id);}catch(RuntimeException $e){echo '<p>'.esc($e->getMessage()).'</p>';admin_foot();exit;}
        ?><section class="workspace-card payment-review"><h2>Review payment request</h2><p><strong><?=esc($i['reference'].' · '.$i['title'])?></strong><br>Full invoice amount: <strong>$<?=gmoney((int)$i['amount_cents'])?> USD</strong></p><?php catalog_invoice_view($i);?><form method="post"><?php admin_action('payments_prepare');studio_hidden('invoice_id',$id);studio_hidden('invoice_hash',payments_invoice_hash($i));studio_hidden('request_key',bin2hex(random_bytes(32)));admin_select('purpose','Purpose',payments_purposes(),'full');admin_input('days','Expires in days','7','number','min="1" max="30" required');admin_check('confirm','I reviewed the agreed invoice amount. Replace any earlier prepared request for this invoice.',false);?><button class="btn gold">Prepare request</button></form></section><?php
    }
    ?><section class="workspace-card"><h2>Unpaid invoices</h2><div class="table-wrap"><table><thead><tr><th>Invoice / project / client</th><th>USD</th><th>Action</th></tr></thead><tbody><?php foreach(all('SELECT i.id,i.reference,i.title,i.amount_cents,j.title AS project,c.name FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id JOIN '.table('studio_clients')." c ON c.id=j.client_id WHERE i.status='issued' AND i.currency='USD' AND c.active=1 ORDER BY i.id DESC LIMIT 100") as $i){?><tr><td><?=esc($i['reference'].' · '.$i['title'])?><br><?=esc($i['project'].' · '.$i['name'])?></td><td>$<?=gmoney((int)$i['amount_cents'])?></td><td><a href="<?=esc(admin_url('payments',['invoice_id'=>$i['id']]))?>">Review request</a></td></tr><?php }?></tbody></table></div></section><section class="workspace-card"><h2>Request history</h2><p>Latest 100 requests. Expired, revoked and superseded records remain available for review. No record here is evidence that money was received.</p><?php foreach(all('SELECT r.*,i.reference FROM '.table('payment_requests').' r JOIN '.table('studio_invoices').' i ON i.id=r.invoice_id ORDER BY r.id DESC LIMIT 100') as $r){?><article class="workspace-version"><h3><?=esc($r['reference'])?> · Request <?=(int)$r['id']?></h3><p><?=esc(payments_purposes()[$r['purpose']]??$r['purpose'])?> · $<?=gmoney((int)$r['amount_cents'])?> USD · <strong><?=esc(payments_request_state($r))?></strong><br>Expires <?=esc(gmdate('Y-m-d H:i:s',(int)$r['expires_at']))?> UTC</p><?php if($r['status']==='prepared'){?><form method="post"><?php admin_action('payments_revoke');studio_hidden('request_id',$r['id']);admin_check('confirm','Revoke this request; keep the invoice.',false);?><button class="btn outline">Revoke request</button></form><?php }?></article><?php }?></section><?php admin_foot();exit;
}
