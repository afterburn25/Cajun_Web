<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function attempts_admin_action(string $action): never {
    check_csrf();$password=posted('owner_password',1000);
    if($action==='payments_trial_setup'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm a current file and database backup.');
        attempts_migrate($password);flash('Sandbox trial history enabled. Real payments remain disabled.');
    }elseif($action==='payments_trial_submit'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm a sandbox trial using test card data only.');
        // Unexpected raw-card fields are rejected, never rendered, persisted, or logged by the app.
        foreach(array_keys($_POST) as $key)if(!in_array($key,['csrf','action','owner_password','confirm','request_id','revision','attempt_key','dataValue'],true))throw new InvalidArgumentException('Unexpected sandbox form fields. Reload this page.');
        $id=attempts_submit(gint($_POST,'request_id',1,2147483647),gint($_POST,'revision',1,2147483647),posted('attempt_key',64),posted('dataValue',4096),$password);
        flash('Sandbox attempt '.$id.' recorded. Review its verified status below. No real invoice was marked paid.');
    }elseif($action==='payments_trial_verify'){
        attempts_reconcile(gint($_POST,'attempt_id',1,2147483647),posted('transaction_id',20),gint($_POST,'revision',1,2147483647),$password);
        flash('Sandbox transaction checked. Review the latest observation below. Real invoice balances are unchanged.');
    }else throw new InvalidArgumentException('Unknown sandbox action.');
    redirect(admin_url('payment-trials'));
}
function attempts_admin_render(array $user,string $error): never {
    staff_require('staff.manage');$blocked=support_current()!==null;
    $request=null;$credentials=null;$reason='';$gateway=payments_ready()?payments_gateway('sandbox'):['revision'=>0];
    if(!$blocked&&attempts_ready()&&isset($_GET['request_id'])&&is_string($_GET['request_id'])){
        try{
            if(!https_request())throw new RuntimeException('Open this page over HTTPS to use sandbox card tokenization.');
            $request=one('SELECT * FROM '.table('payment_requests').' WHERE id=?',[(int)$_GET['request_id']]);
            if(!$request||payments_request_state($request)!=='prepared')throw new RuntimeException('Choose a current prepared request.');
            if(one('SELECT id FROM '.table('payment_attempts').' WHERE request_id=?',[$request['id']]))throw new RuntimeException('This request already has a trial. Review its history below.');
            attempts_assert_clear((int)$request['invoice_id']);$credentials=attempts_credentials((int)$gateway['revision']);
        }catch(RuntimeException $e){$reason=$e->getMessage();$credentials=null;}
    }
    if($credentials!==null){
        // Narrow exception only on an authorized, ready trial page. All other pages keep self-only CSP.
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-".$GLOBALS['cwp_nonce']."' https://jstest.authorize.net; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self' https://apitest.authorize.net; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('Referrer-Policy: no-referrer');
    }
    admin_head('Sandbox payment trials','payment-trials',$user);
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    ?><p class="notice warning"><strong>Sandbox only · Test card data only.</strong> These trials do not collect real money, pay client invoices, send receipts or provision services. Production and client checkout remain disabled.</p><?php
    if($blocked){echo '<p>Leave the support session before working with payments.</p>';admin_foot();exit;}
    if(!attempts_ready()){
        if(payments_ready()){?><section class="workspace-card"><h2>Enable sandbox trial history</h2><p>Add attempt and event records while retaining existing credentials, requests and invoices.</p><form method="post"><?php admin_action('payments_trial_setup');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current file and database backup.',false);?><button class="btn gold">Enable sandbox trials</button></form></section><?php }
        else echo '<p>Enable <a href="'.esc(admin_url('payments')).'">payment preparation</a> first.</p>';
        admin_foot();exit;
    }
    ?><p>First save and check <a href="<?=esc(admin_url('payment-settings'))?>">sandbox credentials</a>. Use a USD-only sandbox account with its gateway test mode off. Prepare an invoice request, then choose it below. Each request permits one attempt. Uncertain results require verification before any replacement or manual invoice change.</p><?php
    if($reason)echo '<p class="notice">'.esc($reason).'</p>';
    if($credentials!==null){?><section class="workspace-card" id="sandbox-trial" data-login="<?=esc($credentials['api_login_id'])?>" data-client-key="<?=esc($credentials['public_client_key'])?>"><h2>Trial for request <?=(int)$request['id']?></h2><p><strong>$<?=gmoney((int)$request['amount_cents'])?> USD · Sandbox test</strong></p><p>Card fields go directly to Authorize.net for tokenization. Use only test card details. A tokenized custom form does not establish PCI compliance; production acceptance is a separate gate.</p><div id="sandbox-card-fields"><label>Test card number<input id="sandbox-card" inputmode="numeric" autocomplete="off" maxlength="19"></label><label>Expiry month<input id="sandbox-month" inputmode="numeric" autocomplete="off" maxlength="2"></label><label>Expiry year (four digits)<input id="sandbox-year" inputmode="numeric" autocomplete="off" maxlength="4"></label><label>Test security code<input id="sandbox-code" type="password" inputmode="numeric" autocomplete="off" maxlength="4"></label></div><form method="post" id="sandbox-token-form" autocomplete="off"><?php admin_action('payments_trial_submit');studio_hidden('request_id',$request['id']);studio_hidden('revision',$gateway['revision']);studio_hidden('attempt_key',bin2hex(random_bytes(32)));studio_hidden('dataValue','');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','Run a sandbox trial with test card data only. This will not pay the real invoice.',false);?><button type="button" id="sandbox-send" class="btn gold">Run sandbox trial</button><p id="sandbox-feedback" role="status" aria-live="polite"></p></form></section><script src="https://jstest.authorize.net/v1/Accept.js" charset="utf-8" nonce="<?=nonce()?>"></script><script src="<?=esc(asset('payment-trial.js'))?>" nonce="<?=nonce()?>" defer></script><?php }
    ?><section class="workspace-card"><h2>Prepared requests</h2><div class="table-wrap"><table><thead><tr><th>Request</th><th>Amount</th><th>Action</th></tr></thead><tbody><?php foreach(all('SELECT r.* FROM '.table('payment_requests')." r LEFT JOIN ".table('payment_attempts')." a ON a.request_id=r.id WHERE r.status='prepared' AND a.id IS NULL ORDER BY r.id DESC LIMIT 100") as $r){if(payments_request_state($r)!=='prepared')continue;?><tr><td><?=(int)$r['id']?> · Invoice <?=(int)$r['invoice_id']?></td><td>$<?=gmoney((int)$r['amount_cents'])?> USD</td><td><a href="<?=esc(admin_url('payment-trials',['request_id'=>$r['id']]))?>">Open sandbox trial</a></td></tr><?php }?></tbody></table></div></section><section class="workspace-card"><h2>Sandbox attempt history</h2><p>Latest 100 attempts. Captured means awaiting sandbox settlement; settled is still test money. Unknown, submitting and held states block request replacement and manual invoice changes. If the connection stopped, locate the transaction by its reference in the sandbox merchant portal and verify its ID here. A missing transaction is not proof of failure; never resubmit blindly.</p><?php foreach(all('SELECT * FROM '.table('payment_attempts').' ORDER BY id DESC LIMIT 100') as $a){?><article class="workspace-version"><h3>Attempt <?=(int)$a['id']?> · <?=esc($a['state'])?> (sandbox)</h3><p>Request <?=(int)$a['request_id']?> · Invoice <?=(int)$a['invoice_id']?> · $<?=gmoney((int)$a['amount_cents'])?> USD<br>Reference: <?=esc($a['reference'])?><br>Transaction: <?=esc($a['transaction_id']??'Not yet known')?></p><details><summary>Event history</summary><ul><?php foreach(all('SELECT event,actor_id,created_at FROM '.table('payment_attempt_events').' WHERE attempt_id=? ORDER BY id',[$a['id']]) as $event)echo '<li>'.esc($event['created_at'].' UTC · '.$event['event'].' · Administrator '.$event['actor_id']).'</li>';?></ul></details><form method="post"><?php admin_action('payments_trial_verify');studio_hidden('attempt_id',$a['id']);studio_hidden('revision',$gateway['revision']);admin_input('transaction_id','Sandbox transaction ID',$a['transaction_id']??'','text','required inputmode="numeric" maxlength="20"');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn outline">Verify transaction</button></form></article><?php }?></section><?php admin_foot();exit;
}
