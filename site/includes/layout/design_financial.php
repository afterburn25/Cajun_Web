<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/design_payment_actions.php';
require_once CWP_CODE_ROOT.'/includes/layout/design_financial_editor.php';
use CajunWebPro\Foundation\DesignPayments\DesignPaymentSchedule;
function design_financial_money(int $cents):string{return '$'.design_financial_decimal_text($cents).' USD';}
function design_financial_hidden(string $name,mixed $value):void {echo '<input type="hidden" name="'.esc($name).'" value="'.esc((string)$value).'">';}
function design_financial_terms(array $schedule,array $quote,array $stages,array $invoices=[],bool $staff=false,array $milestones=[],bool $historic=false):void {
    echo '<h3>'.esc($schedule['title']).'</h3><p>Proposal version '.(int)$schedule['proposal']['version'].' · USD</p><ol class="financial-items">';
    foreach($quote['items'] as $item){echo '<li><p class="financial-text">'.esc($item['description']).'</p><dl class="financial-facts">';
        foreach(['Quantity'=>(string)$item['quantity'],'Unit price'=>design_financial_money($item['unit_cents']),'Subtotal'=>design_financial_money($item['subtotal_cents']),'Discount'=>design_financial_money($item['discount_cents']),'Tax'=>design_financial_money($item['tax_cents']),'Item total'=>design_financial_money($item['total_cents'])] as $label=>$value)echo '<dt>'.esc($label).'</dt><dd>'.esc($value).'</dd>';echo '</dl></li>';
    }echo '</ol><dl class="financial-facts financial-total">';
    foreach(['Subtotal'=>'subtotal_cents','Discount'=>'discount_cents','Tax'=>'tax_cents','Agreement total'=>'total_cents'] as $label=>$key)echo '<dt>'.esc($label).'</dt><dd>'.esc(design_financial_money($quote[$key])).'</dd>';
    echo '</dl><h4>Financial terms</h4><div class="financial-text">'.esc($schedule['terms']).'</div><h4>Payment schedule</h4><p>Scheduled amounts are agreed allocations. Payments and remaining invoice balances appear only after an invoice is issued.</p><ol class="financial-stages">';
    foreach($stages as $stage){echo '<li><strong>'.esc($stage['title']).'</strong><p>'.esc(ucfirst($stage['kind'])).' · Scheduled: '.esc(design_financial_money($stage['amount_cents'])).'</p>';
        if($stage['due_date']!=='')echo '<p>Agreed due date: '.esc($stage['due_date']).'</p>';
        if($stage['milestone_id']!=='')echo '<p>Project milestone: '.esc($milestones[$stage['milestone_id']]??$stage['milestone_id']).'</p>';
        if(isset($invoices[$stage['id']])){$i=$invoices[$stage['id']];$url=$staff?admin_url('invoice-payments',['invoice_id'=>$i['id']]):studio_url(['invoice'=>$i['id']]);echo '<p>Issued: '.esc($i['reference']).' · Recorded payments: '.esc(design_financial_money($i['paid_cents'])).' · Invoice balance: '.esc(design_financial_money($i['due_cents'])).'</p><a href="'.esc($url).'">View invoice and payment history</a>';}
        else echo $historic?'<p>Original scheduled allocation. See the current agreement for invoice status.</p>':'<p>Invoice not issued.</p>';echo '</li>';
    }echo '</ol>';
}
function design_financial_history(array $view,bool $staff):void {
    echo '<div id="financial-history"><h3>Financial history</h3><p>Recorded actions and original offers. All times are UTC.</p>';
    foreach(array_reverse($view['items']) as $event){echo '<article class="workspace-version" id="financial-event-'.esc($event['id']).'" tabindex="-1"><h4>'.esc(ucfirst($event['kind'])).' · '.esc(ucfirst($event['actor'])).'</h4><time datetime="'.esc(gmdate('Y-m-d\TH:i:s\Z',$event['occurred_at'])).'">'.esc(gmdate('M j, Y · H:i:s',$event['occurred_at'])).' UTC</time>';
        if($event['kind']==='offered'){$s=DesignPaymentSchedule::fromArray($event['payload']);echo '<details><summary>Read this original offer</summary>';design_financial_terms($s->data(),$s->quote(),$s->stages(),[],false,[],true);echo '</details>';}
        elseif($event['kind']==='issued'){$i=$event['payload']['invoice'];echo '<p>'.esc($i['reference'].' · '.$i['title'].' · '.design_financial_money($i['amount_cents'])).'</p><p class="financial-text">'.esc($i['instructions']).'</p>';}
        else{echo '<p class="financial-text">'.esc($event['payload']['comment']).'</p>';}
        echo '</article>';
    }
    if($view['count']){echo '<nav class="progress-pages" aria-label="Financial history pages"><span>Page '.(int)$view['page'].' of '.(int)$view['pages'].'</span>';
        foreach(['Older actions'=>$view['page']-1,'Newer actions'=>$view['page']+1] as $label=>$page)if($page>=1&&$page<=$view['pages']){$params=[$staff?'id':'job'=>$view['project']['id'],'financial_page'=>$page];echo '<a class="btn outline" href="'.esc(($staff?admin_url('studio-job',$params):studio_url($params)).'#financial-history').'">'.esc($label).'</a>';}
        echo '</nav>';
    }else echo '<p>No financial offer has been published.</p>';echo '</div>';
}
function design_financial_command(array $view,int $client):array{return ['job_id'=>(string)$view['project']['id'],'client_id'=>(string)$client,'request_id'=>bin2hex(random_bytes(16)),'expected'=>$view['expected'],'schedule_id'=>$view['schedule']['id'],'fingerprint'=>$view['fingerprint'],'stage_id'=>'','choice'=>'','comment'=>'','confirm'=>'','invalid'=>[]];}
function design_financial_command_hidden(array $a):void{foreach(['job_id','client_id','request_id','expected','schedule_id','fingerprint','stage_id'] as $key)design_financial_hidden($key,$a[$key]);}
function design_financial_reload(array $view,bool $staff):void {echo '<p><a href="'.esc(($staff?admin_url('studio-job',['id'=>$view['project']['id']]):studio_url(['job'=>$view['project']['id']])).'#financial').'">Reload and review the current agreement</a></p>';}
function design_financial_client_decision(array $view,int $client,bool $support):void {
    if($support){echo '<p>Support access is read only here. The client must make their own financial decision.</p>';return;}
    $attempt=$GLOBALS['cwp_financial_client_attempt']??null;if($view['status']!=='offered'&&!$attempt)return;
    $a=$attempt??design_financial_command($view,$client);$stale=$a['expected']!==$view['expected']||$a['schedule_id']!==($view['schedule']['id']??null);
    echo '<form method="post" class="financial-decision">'.support_csrf_field();design_financial_hidden('action','financial_decide');design_financial_command_hidden($a);
    echo '<h3>Your financial decision</h3><p>Proposal approval is separate. Your decision here applies to the prices, payment schedule and terms above.</p>';
    if($attempt){echo '<p class="notice info">Your earlier decision and feedback are retained. '.($stale?'The offer changed; reload and review before making a new decision.':'Correct the issue above and submit the original decision again.').'</p>';design_financial_reload($view,false);}
    design_financial_select('choice','Decision',[''=>'Choose a decision','accepted'=>'Accept these financial terms','declined'=>'Decline these financial terms'],$a['choice'],'financial-choice');
    design_financial_textarea('comment','Feedback (required when declining)',$a['comment'],'financial-comment',5000,3);
    echo '<label class="check"><input type="checkbox" name="confirm" value="1" required'.($a['confirm']==='1'?' checked':'').'>I reviewed the exact prices, schedule and financial terms for this decision.</label><button class="btn gold"'.($stale||$a['invalid']?' disabled':'').'>Record financial decision</button></form>';
}
function design_financial_staff_commands(array $view):void {
    $attempt=$GLOBALS['cwp_financial_command_attempt']??null;
    if($attempt){$a=$attempt['form'];$action=$attempt['action'];echo '<form method="post" class="financial-command">';admin_action($action);design_financial_command_hidden($a);echo '<h3>Retained earlier request</h3><p>Your original request is preserved. Reload to review the current agreement before starting a different action.</p>';
        if($action==='design_financial_withdraw')design_financial_textarea('comment','Withdrawal reason',$a['comment'],'financial-withdraw-retained',5000,3);
        echo '<button class="btn outline"'.($a['expected']!==$view['expected']||$a['invalid']?' disabled':'').'>Retry original '.($action==='design_financial_issue'?'invoice request':'withdrawal').'</button></form>';design_financial_reload($view,true);return;
    }
    if($view['status']==='accepted'&&design_payment_invoices_ready())foreach($view['stages'] as $stage)if(!isset($view['invoices'][$stage['id']])){$a=design_financial_command($view,$view['client_id']);$a['stage_id']=$stage['id'];echo '<form method="post" class="financial-command">';admin_action('design_financial_issue');design_financial_command_hidden($a);echo '<p>Issue '.esc($stage['title']).': <strong>'.esc(design_financial_money($stage['amount_cents'])).'</strong></p><button class="btn gold">Issue invoice for '.esc($stage['title']).'</button></form>';}
    if(!$view['frozen']&&in_array($view['status'],['offered','accepted'],true)){$a=design_financial_command($view,$view['client_id']);echo '<details><summary>Withdraw these financial terms</summary><form method="post">';admin_action('design_financial_withdraw');design_financial_command_hidden($a);design_financial_textarea('comment','Withdrawal reason','','financial-withdraw',5000,3);echo '<button class="btn outline">Withdraw agreement</button></form></details>';}
}
function design_financial_setup(int $job):void {
    if(!payments_ready()||!ledger_ready()){echo '<p>Enable <a href="'.esc(admin_url('payment-settings')).'">payment preparation</a> and the <a href="'.esc(admin_url('invoice-payments')).'">invoice payment ledger</a> before preparing financial agreements.</p>';return;}
    if(!staff_can('staff.manage')){echo '<p>An owner must prepare financial agreement storage.</p>';return;}
    echo '<details><summary>Prepare financial agreement storage</summary><p>This adds financial records without changing existing projects, invoices or payments.</p><form method="post">';admin_action('design_financial_setup');
    echo '<label for="financial-owner-password">Your current owner password</label><input id="financial-owner-password" type="password" name="owner_password" value="" required autocomplete="current-password"><label class="check"><input type="checkbox" name="confirm" value="1" required>I have a current database backup.</label><button class="btn outline">Prepare financial agreements and invoices</button></form></details>';
}
function design_financial_section(int $job,bool $staff,int $client=0,?string $support=null):void {
    if($staff&&(!staff_can('payments.manage')||!staff_can('clients.manage')))return;
    echo '<section class="workspace-card financial-agreement" id="financial" aria-labelledby="financial-heading"><span class="eyebrow">DESIGN SERVICES</span><h2 id="financial-heading">Financial agreement</h2><p>Review design prices and agreed payment stages here. Online payment is not available; use the studio’s invoice instructions.</p>';
    try{
        if(!design_financial_mode()){if($staff)design_financial_setup($job);else echo '<p>Financial agreements have not been enabled for this workspace.</p>';echo '</section>';return;}
        $page=design_financial_page($_GET);$view=$staff?design_financial_staff_context($job,$page):design_payments_client_get($job,$client,$page,$support,false,true);
        if($view['schedule']){echo '<p class="workspace-status">'.esc(ucfirst($view['status'])).'</p>';design_financial_terms($view['schedule'],$view['quote'],$view['stages'],$view['invoices'],$staff,$view['milestones']??[]);}
        if($staff){
            if(!design_payment_invoices_ready())design_financial_setup($job);
            if($view['frozen'])echo '<p class="notice info">These agreed terms are frozen because an invoice has been issued.</p>';
            if(($view['frozen']||!$view['client_active'])&&isset($GLOBALS['cwp_financial_offer_attempt']))design_financial_retained_draft($GLOBALS['cwp_financial_offer_attempt']);
            if($view['client_active']){design_financial_staff_commands($view);if(!$view['frozen'])design_financial_editor($view);}else echo '<p>Reactivate this client account before publishing terms or issuing invoices.</p>';
        }else design_financial_client_decision($view,$client,$support!==null);
        design_financial_history($view,$staff);
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)report_error($e);echo '<p class="notice error" role="alert">Financial agreement unavailable. '.($staff&&!($e instanceof PDOException)?esc($e->getMessage()):'Refresh or contact the studio. Existing records were preserved.').'</p>';}
    echo '</section>';
}
