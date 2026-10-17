<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_dispatch_routes.php';
function ledger_admin_action(string $action): never {
    check_csrf();
    if($action==='payments_ledger_setup'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm a current backup and the import of legacy paid balances.');
        ledger_migrate(posted('owner_password',1000));flash('Invoice payment history enabled. Legacy paid balances remain labeled as imported.');redirect(admin_url('invoice-payments'));
    }
    if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the reviewed payment or recording correction.');
    if($action==='payments_ledger_receive'){
        $invoice=gint($_POST,'invoice_id',1,2147483647);$amount=gcents(posted('amount',20));if($amount===null)throw new InvalidArgumentException('Enter a valid USD amount with at most two decimal places.');
        $id=ledger_receive($invoice,$amount,posted('method',20),posted('reference',500),posted('received_on',10),posted('request_key',64),posted('review_hash',64));
        flash('External payment recorded as receipt '.$id.'. No card was charged and no email was sent.');
    }elseif($action==='payments_ledger_reverse'){
        $id=ledger_reverse(gint($_POST,'entry_id',1,2147483647),posted('reason',500),posted('request_key',64),posted('review_hash',64),posted('owner_password',1000));
        $invoice=(int)one('SELECT invoice_id FROM '.table('invoice_payments').' WHERE id=?',[$id])['invoice_id'];flash('Recording corrected with a retained reversal. No money was refunded or sent.');
    }else throw new InvalidArgumentException('Unknown invoice payment action.');
    redirect(admin_url('invoice-payments',['invoice_id'=>$invoice]));
}
function ledger_totals_view(array $i,array $b): void { ?>
    <div class="ledger-totals"><div><small>Invoice total</small><strong>$<?=gmoney((int)$i['amount_cents'])?> <?=esc($i['currency'])?></strong></div><div><small>Recorded payments</small><strong>$<?=gmoney($b['paid_cents'])?> <?=esc($i['currency'])?></strong></div><div><small>Remaining balance</small><strong>$<?=gmoney($b['due_cents'])?> <?=esc($i['currency'])?></strong></div></div>
<?php }
function ledger_admin_render(array $user,string $error): never {
    if(!staff_can('clients.manage',$user)){http_response_code(403);admin_head('Invoice payments','invoice-payments',$user);echo '<p>Payment and client-workspace access are both required.</p>';admin_foot();exit;}
    admin_head('Invoice payments','invoice-payments',$user);if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    echo '<p class="notice">Record only payments you independently verified outside this website. This page does not charge a card or issue a refund.</p>';
    if(support_current()){echo '<p>Leave support before managing payments.</p>';admin_foot();exit;}
    if(!ledger_ready()){
        if(payments_ready()&&staff_can('staff.manage',$user)){?><section class="workspace-card"><h2>Enable invoice payments</h2><p>Add payment history and balances. Previously paid invoices become labeled legacy balances; they are not new receipts or newly verified payments.</p><form method="post"><?php admin_action('payments_ledger_setup');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current file/database backup and understand the legacy balance import.',false);?><button class="btn gold">Enable invoice payments</button></form></section><?php }
        else echo '<p>Ask an owner to enable payment preparation and invoice payments.</p>';
        admin_foot();exit;
    }
    $id=is_string($_GET['invoice_id']??null)?(int)$_GET['invoice_id']:0;
    if($id){
        $before=is_string($_GET['before_entry']??null)&&ctype_digit($_GET['before_entry'])?(int)$_GET['before_entry']:PHP_INT_MAX;
        try{[$i,$b,$review,$rows]=staff_locked(function() use($id,$before): array {$i=ledger_invoice($id);return [$i,ledger_balance($i),ledger_review_hash($i),all('SELECT p.*,r.id AS correction_id FROM '.table('invoice_payments').' p LEFT JOIN '.table('invoice_payments').' r ON r.reversal_of=p.id WHERE p.invoice_id=? AND p.id<? ORDER BY p.id DESC LIMIT 50',[$id,$before])];});$renewalPurpose=domain_renewal_dispatch_invoice_display((int)$i['id'],(int)$i['client_id'],false);}
        catch(RuntimeException $e){echo '<p>'.esc($e->getMessage()).'</p>';admin_foot();exit;}
        ?><p><a href="<?=esc(admin_url('invoice-payments'))?>">All invoices</a> · <a href="<?=esc(admin_url('studio-job',['id'=>$i['job_id']]))?>">Open project</a></p><section class="workspace-card"><h2><?=esc($i['reference'].' · '.$i['title'])?></h2><p><?=esc($i['client_name'])?> · <?=esc($i['status'])?></p><?php ledger_totals_view($i,$b);catalog_invoice_view($i);if($renewalPurpose!==null)domain_renewal_dispatch_card($renewalPurpose);?></section><?php
        if($i['status']==='issued'&&(int)$i['active']===1&&$i['currency']==='USD'){?><section class="workspace-card"><h2>Record external payment</h2><form method="post"><?php admin_action('payments_ledger_receive');studio_hidden('invoice_id',$id);studio_hidden('request_key',bin2hex(random_bytes(32)));studio_hidden('review_hash',$review);admin_input('amount','Amount received in USD',gmoney($b['due_cents']),'text','required inputmode="decimal" maxlength="20"');admin_select('method','Payment method',ledger_methods(),'bank');admin_input('reference','Private verified payment reference (no card details)','','text','required minlength="5" maxlength="500"');admin_input('received_on','Date received (UTC)',gmdate('Y-m-d'),'date','required max="'.gmdate('Y-m-d').'"');admin_check('confirm','I independently verified receipt and reviewed the remaining invoice balance.',false);?><button class="btn gold">Record payment</button></form></section><?php }
        ?><section class="workspace-card"><h2>Payment history</h2><p>Original records remain visible after a correction. Private references and correction reasons appear only to authorized administrators.</p><?php if(!$rows)echo '<p>No payments recorded.</p>';foreach($rows as $p){?><article class="workspace-version"><h3><?=esc(match($p['kind']){'legacy'=>'Legacy paid balance','reversal'=>'Recording correction',default=>'Receipt'})?> #<?=(int)$p['id']?> · <?=$p['kind']==='reversal'?'−':''?>$<?=gmoney((int)$p['amount_cents'])?> <?=esc($p['currency'])?></h3><p><?=esc($p['received_on'].' · '.(ledger_methods()[$p['method']]??'Legacy record'))?><?php if($p['correction_id'])echo ' · Reversed by correction #'.(int)$p['correction_id'];if($p['reversal_of'])echo ' · Reverses #'.(int)$p['reversal_of'];?></p><p><?=esc($p['private_note'])?></p><?php if(!$p['correction_id']&&$p['kind']!=='reversal'&&staff_can('staff.manage',$user)){?><details><summary>Correct this recording</summary><p>This reverses the whole recorded amount and reopens the balance. It does not refund or move money. Use this only to correct a mistaken record.</p><form method="post"><?php admin_action('payments_ledger_reverse');studio_hidden('entry_id',$p['id']);studio_hidden('request_key',bin2hex(random_bytes(32)));studio_hidden('review_hash',$review);admin_input('reason','Private correction reason','','text','required minlength="10" maxlength="500"');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','Correct this mistaken record without sending a refund.',false);?><button class="btn outline">Reverse recording</button></form></details><?php }?></article><?php }if(count($rows)===50)echo '<p><a href="'.esc(admin_url('invoice-payments',['invoice_id'=>$id,'before_entry'=>end($rows)['id']])).'">Older payment entries</a></p>';?></section><?php
    }else{
        $before=is_string($_GET['before']??null)&&ctype_digit($_GET['before'])?(int)$_GET['before']:PHP_INT_MAX;
        $rows=staff_locked(function() use($before): array {$rows=all('SELECT i.*,j.client_id,c.active,c.name AS client_name FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE i.id<? ORDER BY i.id DESC LIMIT 50',[$before]);foreach($rows as &$i){try{$i['balance']=ledger_balance($i);}catch(RuntimeException){$i['balance']=null;}}return $rows;});
        ?><section class="workspace-card"><h2>Invoice balances</h2><p>Balances cover issued invoices only. They do not establish the total agreed project price.</p><div class="table-wrap"><table><thead><tr><th>Invoice / client</th><th>Status</th><th>Total</th><th>Paid</th><th>Remaining</th></tr></thead><tbody><?php foreach($rows as $i){?><tr><td><a href="<?=esc(admin_url('invoice-payments',['invoice_id'=>$i['id']]))?>"><?=esc($i['reference'].' · '.$i['title'])?></a><br><?=esc($i['client_name'])?></td><td><?=esc($i['status'])?></td><td>$<?=gmoney((int)$i['amount_cents'])?> <?=esc($i['currency'])?></td><td><?=esc($i['balance']?'$'.gmoney($i['balance']['paid_cents']):'Review needed')?></td><td><?=esc($i['balance']?'$'.gmoney($i['balance']['due_cents']):'Review needed')?></td></tr><?php }?></tbody></table></div><?php if(count($rows)===50)echo '<p><a href="'.esc(admin_url('invoice-payments',['before'=>end($rows)['id']])).'">Older invoices</a></p>';?></section><?php
    }
    admin_foot();exit;
}
function ledger_client_link(array $invoice): void {
    if(!ledger_ready())return;
    try{[$i,$b]=staff_locked(function() use($invoice): array {$i=ledger_client_invoice((int)$invoice['id']);return [$i,ledger_balance($i)];});}
    catch(RuntimeException){echo '<p>Payment history needs review. Contact the studio.</p>';return;}
    echo '<p>Recorded: $'.esc(gmoney($b['paid_cents'])).' · Remaining: $'.esc(gmoney($b['due_cents'])).' '.esc($i['currency']).'<br><a href="'.esc(studio_url(['invoice'=>$i['id']])).'">View invoice statement and receipts</a></p>';
}
function ledger_portal_render(): never {
    $receipt=array_key_exists('receipt',$_GET);$id=is_string($_GET[$receipt?'receipt':'invoice']??null)?(int)$_GET[$receipt?'receipt':'invoice']:0;
    if(!studio_client())fail_page('Sign in to view payment records.',403);
    if($receipt&&support_current()){support_record('receipt.view','receipt',(string)$id,'denied');fail_page('Receipts are unavailable during support.',403);}
    try{
        if($receipt){$p=staff_locked(fn()=>ledger_client_receipt($id));$i=ledger_client_invoice((int)$p['invoice_id']);}
        else{
            $before=is_string($_GET['before_entry']??null)&&ctype_digit($_GET['before_entry'])?(int)$_GET['before_entry']:PHP_INT_MAX;
            [$i,$b,$rows]=staff_locked(function() use($id,$before): array {$i=ledger_client_invoice($id);return [$i,ledger_balance($i),all('SELECT p.id,p.kind,p.amount_cents,p.currency,p.method,p.received_on,p.reversal_of,r.id AS correction_id FROM '.table('invoice_payments').' p LEFT JOIN '.table('invoice_payments').' r ON r.reversal_of=p.id WHERE p.invoice_id=? AND p.client_id=? AND p.id<? ORDER BY p.id DESC LIMIT 50',[$id,$i['client_id'],$before])];});
        }
        $renewalPurpose=domain_renewal_dispatch_invoice_display((int)$i['id'],(int)$i['client_id'],true);
    }catch(RuntimeException){if(support_current())support_record('invoice.view','invoice',(string)$id,'denied');fail_page('Payment record unavailable. Contact the studio if you need help.',404);}
    if(support_current())support_record('invoice.view','invoice',(string)$i['id']);
    site_head($receipt?'Payment receipt':'Invoice statement','portal');support_banner();?><section class="section ledger-document"><div class="container"><p class="no-print"><a href="<?=esc(studio_url(['job'=>$i['job_id']]))?>">Back to project</a><?php if($receipt):?> · <a href="<?=esc(studio_url(['invoice'=>$i['id']]))?>">Current invoice statement</a><?php endif;?></p><article class="workspace-card">
    <?php if($renewalPurpose!==null)domain_renewal_dispatch_card($renewalPurpose);if($receipt){?><span class="eyebrow">CAJUNWEBPRO / PAYMENT RECORD</span><h1>Receipt #<?=(int)$p['id']?></h1><?php if($p['correction_id']):?><p class="notice">This recording was reversed by correction #<?=(int)$p['correction_id']?>. It no longer reduces the invoice balance.</p><?php endif;?><p>Client: <?=esc($p['client_name'])?><br>Invoice: <?=esc($p['invoice_reference'].' · '.$p['invoice_title'])?></p><h2>$<?=gmoney((int)$p['amount_cents'])?> <?=esc($p['currency'])?></h2><p><?=esc(ledger_methods()[$p['method']]??'External payment')?> · Received <?=esc($p['received_on'])?></p><p>Recorded by CajunWebPro on <?=esc($p['created_at'])?> UTC after verification outside this website.</p><p class="no-print">Use your browser’s Print or Save as PDF option to keep a copy. Check the current statement for later payments or corrections.</p><?php }
    else{?><span class="eyebrow">CAJUNWEBPRO / YOUR INVOICE</span><h1><?=esc($i['reference'])?></h1><h2><?=esc($i['title'])?></h2><p>Status: <?=esc($i['status'])?></p><?php ledger_totals_view($i,$b);catalog_invoice_view($i);?><p><?=nl2br(esc($i['instructions']))?></p><p>Online card checkout is not available yet. Follow the studio’s payment instructions. These balances cover this invoice only.</p><h2>Payment history</h2><?php if(!$rows)echo '<p>No payments recorded yet.</p>';foreach($rows as $p){?><div class="workspace-version" id="payment-<?=(int)$p['id']?>"><h3><?=esc(match($p['kind']){'legacy'=>'Imported legacy paid balance','reversal'=>'Recording correction',default=>'Recorded payment'})?> #<?=(int)$p['id']?></h3><p><?=$p['kind']==='reversal'?'−':''?>$<?=gmoney((int)$p['amount_cents'])?> <?=esc($p['currency'])?> · <?=esc($p['received_on'])?></p><?php if($p['kind']==='legacy')echo '<p>Previously marked paid before this payment history was enabled; no new receipt was issued.</p>';if($p['reversal_of'])echo '<p>Reverses recording #'.(int)$p['reversal_of'].'.</p>';if($p['correction_id'])echo '<p>Reversed by correction #'.(int)$p['correction_id'].'.</p>';if($p['kind']==='manual'&&!support_current())echo '<p><a href="'.esc(studio_url(['receipt'=>$p['id']])).'">Printable receipt #'.(int)$p['id'].'</a></p>';?></div><?php }if(count($rows)===50)echo '<p class="no-print"><a href="'.esc(studio_url(['invoice'=>$i['id'],'before_entry'=>end($rows)['id']])).'">Older payment entries</a></p>';}
    ?></article></div></section><?php site_foot(false);exit;
}
