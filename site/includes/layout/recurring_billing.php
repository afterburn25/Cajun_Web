<?php
/** Escaped, ordinary staff/client forms backed by the native recurring agreement rules. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_actions.php';
require_once CWP_CODE_ROOT.'/includes/layout/plans.php';
use CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms;

function recurring_ui_money(int $value):string {return '$'.gmoney($value).' USD';}
function recurring_ui_hidden(string $name,string|int $value):void {echo '<input type="hidden" name="'.esc($name).'" value="'.esc((string)$value).'">';}
function recurring_ui_start(string $action,array $values,array $keys,string $url=''):void {
    echo '<form method="post"'.($url!==''?' action="'.esc($url).'"':'').'>'.csrf_field();recurring_ui_hidden('action',$action);foreach($keys as $key)recurring_ui_hidden($key,$values[$key]??'');
}
function recurring_ui_input(array $a,string $name,string $label,string $type='text',bool $required=true):void {
    $max=recurring_ui_limits()[$name];echo '<label>'.esc($label).'<input type="'.esc($type).'" name="'.esc($name).'" value="'.esc($a[$name]??'').'" maxlength="'.$max.'"'.($required?' required':'').'></label>';
}
function recurring_ui_textarea(array $a,string $name,string $label):void {
    echo '<label>'.esc($label).'<textarea name="'.esc($name).'" rows="5" maxlength="'.recurring_ui_limits()[$name].'">'.esc($a[$name]??'').'</textarea></label>';
}
function recurring_ui_confirmation(string $label):void {echo '<label class="check"><input type="checkbox" name="confirm" value="1" required>'.esc($label).'</label>';}
function recurring_ui_pages(array $slice,string $key,bool $staff,array $params):void {
    if(!$slice['page']&&!$slice['more'])return;
    echo '<nav class="recurring-pages" aria-label="'.esc(str_replace('_',' ',$key)).'"><span>Page '.($slice['page']+1).'</span>';
    foreach(['Previous'=>$slice['page']-1,'Next'=>$slice['page']+1] as $label=>$page)if($page>=0&&($label==='Previous'||$slice['more']))echo '<a class="btn outline" href="'.esc(recurring_ui_url($staff,array_replace($params,[$key=>$page]))).'">'.esc($label).'</a>';
    echo '</nav>';
}
function recurring_ui_terms(array $t):void {
    echo '<h2>'.esc($t['title']).'</h2><dl class="recurring-facts">';
    foreach(['Price per whole period'=>recurring_ui_money($t['amount_cents']),'Billing frequency'=>service_plans_cadences()[$t['cadence']],
        'Original billing start'=>$t['anchor'],'Date alignment'=>$t['alignment']==='month-end'?'Last day of the month':'Original day of the month',
        'Billing timezone'=>$t['timezone'],'Invoice due'=>$t['due_after_days'].' calendar days after period start'] as $label=>$value)echo '<dt>'.esc($label).'</dt><dd>'.esc($value).'</dd>';
    echo '</dl><h3>Billing terms</h3><div class="recurring-text">'.esc($t['terms']).'</div><p>Invoices only. This agreement does not authorize automatic payments. Changes apply to future whole billing periods; no partial-period charge or credit is calculated here. Ending future billing preserves invoices already issued.</p>';
}
function recurring_ui_offer_editor(array $view):void {
    $s=$view['source'];$attempt=$GLOBALS['cwp_recurring_attempt']??null;
    $eligible=$view['client']['active']&&$s['state']==='active'&&in_array($s['cadence'],['monthly','quarterly','annually'],true)&&in_array($view['previous_phase'],[null,'declined','withdrawn','ended'],true);
    echo '<section class="workspace-card recurring-agreement"><h2>Prepare recurring billing terms</h2><p>For '.esc($view['client']['name']).' · '.esc($view['project']['title']).'</p><p>Agreed service: '.esc($s['title']).' · '.esc(recurring_ui_money($s['amount_cents'])).' · '.esc(service_plans_cadences()[$s['cadence']]).'</p>';
    if(!$eligible)echo '<p class="notice info">A current active recurring service agreement and an ended or withdrawn previous billing agreement are required for a new offer.</p>';
    if(!$eligible&&!$attempt){echo '</section>';return;}
    $a=$attempt??array_replace(array_fill_keys(array_keys(recurring_ui_limits()),''),[
        'invalid'=>[],'job_id'=>(string)$view['project']['id'],'client_id'=>(string)$view['client']['id'],'request_id'=>bin2hex(random_bytes(16)),
        'source_id'=>$s['id'],'source_revision'=>(string)$s['revision'],'source_sha256'=>$s['sha256'],
        'previous_id'=>$view['previous_id'],'previous_sha256'=>$view['previous_sha256'],'title'=>$s['title'],
        'anchor'=>$view['floor']['date']??'','alignment'=>'day-of-month','timezone'=>$view['floor']['timezone']??'','due_after_days'=>'0']);
    if($attempt){echo '<p class="notice info">Your entered draft and original review references are retained below. Review any error before resubmitting.</p><p><a href="'.esc(recurring_ui_url(true,['job'=>$view['project']['id'],'source'=>$s['id']])).'">Start again from the current service agreement</a></p>';}
    if($view['floor'])echo '<p>Replacement billing must start on or after '.esc($view['floor']['date']).' in '.esc($view['floor']['timezone']).'.</p>';
    recurring_ui_start('recurring_offer',$a,['job_id','client_id','request_id','source_id','source_revision','source_sha256','previous_id','previous_sha256']);
    recurring_ui_input($a,'title','Agreement title');recurring_ui_textarea($a,'terms','Billing terms shown to the client');
    echo '<div class="recurring-grid">';recurring_ui_input($a,'anchor','First billing period start','date');
    echo '<label>Date alignment<select name="alignment" required>';if(!in_array($a['alignment'],['day-of-month','month-end'],true))echo '<option selected value="'.esc($a['alignment']).'">'.esc($a['alignment']===''?'Choose alignment':$a['alignment']).'</option>';foreach(['day-of-month'=>'Original day of the month','month-end'=>'Last day of the month'] as $key=>$label)echo '<option value="'.$key.'"'.($a['alignment']===$key?' selected':'').'>'.$label.'</option>';echo '</select></label>';
    recurring_ui_input($a,'timezone','Billing timezone, such as America/Chicago');recurring_ui_input($a,'due_after_days','Days from period start until due (0–60)','number');echo '</div>';
    echo '<p>The service agreement supplies the price and frequency. New commercial terms require a separately agreed service. Review the calendar and invoice-only terms before publishing.</p><button class="btn outline" name="intent" value="preview" formnovalidate>Preview billing terms</button>';
    recurring_ui_confirmation('I reviewed the service price, calendar and billing terms for this client.');echo '<button class="btn gold" name="intent" value="publish">Offer terms to client</button></form></section>';
    if(isset($GLOBALS['cwp_recurring_preview'])){echo '<section class="workspace-card recurring-agreement"><p class="notice info">Preview only. No agreement, invoice or payment was saved.</p>';recurring_ui_terms($GLOBALS['cwp_recurring_preview']);echo '</section>';}
    echo '<details class="workspace-card"><summary>Read the agreed service scope</summary>';service_plans_terms_view($s['plan']);echo '</details>';
}
function recurring_ui_command_seed(array $view,string $command):array {
    $doc=$view['document'];$t=$doc->data()['terms'];return array_replace(array_fill_keys(array_keys(recurring_ui_limits()),''),[
        'job_id'=>(string)$view['project']['id'],'client_id'=>(string)$view['client']['id'],'schedule_id'=>$t['id'],
        'request_id'=>bin2hex(random_bytes(16)),'revision'=>(string)$doc->revision(),'sha256'=>$doc->fingerprint(),
        'terms_sha256'=>RecurringBillingTerms::fromArray($t)->fingerprint(),'command'=>$command,
        'effective_date'=>$command==='end'?$view['next_date']:$view['change_date'],'period_date'=>$view['assessment']['period']['start'],'reason'=>'']);
}
function recurring_ui_command_label(string $command):string {
    return ['accept'=>'Accept these billing terms','decline'=>'Decline these billing terms','withdraw'=>'Withdraw this offer',
        'activate'=>'Start billing','pause'=>'Pause billing','resume'=>'Resume billing','end'=>'End future billing','issue'=>'Issue this period invoice','checkout_accept'=>'Billing terms confirmed at checkout'][$command]??'Review retained action';
}
function recurring_ui_command_form(array $a,bool $retained=false):void {
    $command=$a['command'];$label=recurring_ui_command_label($command);
    echo '<section class="workspace-card"><h3>'.esc($retained?'Your retained attempt':$label).'</h3>';
    if($retained)echo '<p>The original details and review references are preserved. This action may no longer be available. A retry must pass the current checks.</p>';
    $target=$command==='issue'?recurring_ui_url(true,['job'=>$a['job_id'],'schedule'=>$a['schedule_id'],'period_date'=>$a['period_date']]):'';
    recurring_ui_start('recurring_command',$a,['job_id','client_id','schedule_id','request_id','revision','sha256','terms_sha256','command'],$target);
    if(in_array($command,['activate','pause','resume','end'],true))recurring_ui_input($a,'effective_date','Effective billing period start','date');else recurring_ui_hidden('effective_date',$a['effective_date']);
    recurring_ui_hidden('period_date',$a['period_date']);if($command==='issue')echo '<p>Reviewed invoice period starts '.esc($a['period_date']).'. Use Review a billing period above to choose another period.</p>';
    if(in_array($command,['decline','withdraw','pause','resume','end'],true))recurring_ui_textarea($a,'reason','Reason');else recurring_ui_hidden('reason',$a['reason']);
    recurring_ui_confirmation($command==='issue'?'I reviewed this whole period, amount and due date and want to issue its invoice.':'I reviewed these exact terms and confirm this billing decision.');
    echo '<button class="btn gold">'.esc($label).'</button></form></section>';
}
function recurring_ui_invoice_card(array $invoice,bool $staff):void {
    echo '<article class="workspace-version"><h3>'.esc($invoice['reference']).'</h3><p>Period '.esc($invoice['period']['start']).' to '.esc($invoice['period']['end']).' (end exclusive) · Due '.esc($invoice['period']['due']).' · '.esc($invoice['period']['timezone']).'</p><p>Recorded payments: '.esc(recurring_ui_money($invoice['paid_cents'])).' · Invoice balance: '.esc(recurring_ui_money($invoice['due_cents'])).'</p><a href="'.esc($staff?admin_url('invoice-payments',['invoice_id'=>$invoice['id']]):studio_url(['invoice'=>$invoice['id']])).'">View invoice and payment history</a>';
    if($staff&&isset($invoice['payment_review'])){
        $r=$invoice['payment_review'];echo '<div class="recurring-payment-review"><h4>Payment evidence for service setup</h4><p>Verified external receipts: '.esc(recurring_ui_money($r['verified_cents'])).' · Historical credited balance: '.esc(recurring_ui_money($r['legacy_cents'])).'</p>';
        if($r['payment_evidence_ready'])echo '<p>Verified receipts cover this invoice. An approved service order is still required before setup.</p>';
        else{
            echo '<ul>';foreach($r['blockers'] as $reason)echo '<li>'.esc(match($reason){
                'client-inactive'=>'This client account is inactive.','invoice-void'=>'This invoice is void.',
                'no-charge-policy-required'=>'A no-charge service requires an explicit fulfillment policy.',
                'payment-review-required'=>'An unresolved payment trial requires review.',
                'unverified-legacy-balance'=>'Imported balances do not verify a new payment.',
                default=>'Verified receipts do not yet cover this invoice.'}).'</li>';echo '</ul>';
        }echo '</div>';
    }echo '</article>';
}
function recurring_ui_detail(array $v):void {
    $staff=$v['staff'];$doc=$v['document'];$data=$doc->data();$t=$data['terms'];$terms=RecurringBillingTerms::fromArray($t);$params=['job'=>$v['project']['id'],'schedule'=>$t['id']];
    echo '<section class="workspace-card recurring-agreement">';recurring_ui_terms($t);
    echo '<p>Latest recorded decision: <strong>'.esc(ucfirst($doc->phase())).'</strong>. This may describe a scheduled future change. Review its effective date in the history below.</p><p>Service agreement now: '.esc($v['source']['state']).'.</p></section>';
    echo '<details class="workspace-card"><summary>Read the agreed service scope</summary>';service_plans_terms_view($v['source']['plan']);echo '</details>';
    $a=$v['assessment'];$p=$a['period'];echo '<section class="workspace-card recurring-agreement"><h2>Review a billing period</h2><form method="get">';
    recurring_ui_hidden($staff?'view':'recurring',$staff?'recurring-billing':'1');foreach($params as $key=>$value)recurring_ui_hidden($key,$value);
    recurring_ui_input(['period_date'=>$p['start']],'period_date','Billing period start','date');echo '<button class="btn outline">Review period</button></form><dl class="recurring-facts">';
    foreach(['Starts'=>$p['start'],'Ends (exclusive)'=>$p['end'],'Invoice due'=>$p['due'],'Timezone'=>$p['timezone'],'Agreed amount'=>recurring_ui_money($p['amount_cents'])] as $label=>$value)echo '<dt>'.esc($label).'</dt><dd>'.esc($value).'</dd>';echo '</dl>';
    if($v['selected_invoice'])recurring_ui_invoice_card($v['selected_invoice'],$staff);
    else{
        $reason=match($a['reason']){'eligible'=>'This period meets the recorded billing terms. No invoice has been issued.','not-started'=>'This billing period has not started.','zero-price'=>'This period has no charge.','awaiting-consent'=>'Client agreement is required.','not-activated','before-activation'=>'Billing has not started for this period.','paused'=>'Billing is paused for this period.','ended'=>'Billing has ended for this period.','declined'=>'These billing terms were declined.','withdrawn'=>'These billing terms were withdrawn.',default=>'This period is unavailable for invoicing.'};
        echo '<p>'.esc($reason).'</p>';if($v['source']['state']!=='active')echo '<p>New invoices require the service agreement to be active.</p>';
    }echo '</section>';
    $attempt=$GLOBALS['cwp_recurring_attempt']??null;
    if($attempt&&$attempt['action']==='recurring_command'){
        recurring_ui_command_form($attempt,true);echo '<p><a href="'.esc(recurring_ui_url($staff,$params)).'">Reload and review current billing details</a></p>';
    }else{
        $commands=[];
        if($v['current'])$commands=$staff?match($doc->phase()){'offered'=>['withdraw'],'accepted'=>['activate','withdraw'],'running'=>['pause','end'],'paused'=>['resume','end'],default=>[]}:match($doc->phase()){'offered'=>['accept','decline'],'running','paused'=>['end'],default=>[]};
        if($staff&&$a['eligible']&&!$v['selected_invoice']&&$v['client']['active']&&$v['source']['state']==='active')$commands[]='issue';
        if($commands)echo '<p>Next future billing boundary: <strong>'.esc($v['next_date']).'</strong> ('.esc($t['timezone']).'). Changes use the original calendar. Starting, pausing or resuming cannot move earlier than a previously scheduled change; ending future billing can override it.</p>';
        foreach($commands as $command){if(in_array($command,['accept','activate','resume'],true)&&(!$v['client']['active']||$v['source']['state']!=='active'))continue;recurring_ui_command_form(recurring_ui_command_seed($v,$command));}
    }
    echo '<section class="workspace-card"><h2>Billing history</h2>';
    if($data['format']===2)echo '<p>Confirmed by Client #'.(int)$data['created_by_client'].' on '.esc(gmdate('Y-m-d H:i:s',$data['created_at'])).' UTC.</p><ol>';
    else echo '<p>Offered by Studio #'.(int)$data['created_by'].' on '.esc(gmdate('Y-m-d H:i:s',$data['created_at'])).' UTC.</p><ol>';
    foreach($v['history']['items'] as $event){echo '<li><strong>'.esc(recurring_ui_command_label($event['command'])).'</strong> · '.esc(str_replace(['staff:','client:'],['Studio #','Client #'],$event['actor'])).' · '.esc(gmdate('Y-m-d H:i:s',$event['at'])).' UTC';
        if(isset($event['arguments']['period_index']))echo '<p>Effective '.esc($terms->period($event['arguments']['period_index'])['start']).' ('.esc($t['timezone']).').</p>';
        if(isset($event['arguments']['reason']))echo '<p class="recurring-text">'.esc($event['arguments']['reason']).'</p>';echo '</li>';
    }echo '</ol>';recurring_ui_pages($v['history'],'history_page',$staff,$params);echo '</section><section class="workspace-card"><h2>Period invoices</h2>';
    if(!$v['invoices']['items'])echo '<p>No invoices are listed on this page.</p>';foreach($v['invoices']['items'] as $invoice)recurring_ui_invoice_card($invoice,$staff);
    recurring_ui_pages($v['invoices'],'invoices_page',$staff,$params);echo '</section>';
}
function recurring_ui_page(array $v,bool $owner=false):void {
    $staff=$v['staff'];echo '<p><a class="btn outline" href="'.esc(recurring_ui_url($staff)).'">Recurring billing projects</a></p>';
    if(!$v['ready']){
        echo '<section class="workspace-card"><h2>Prepare recurring billing</h2><p>Recurring agreements are not enabled yet. Service agreements, payment preparation and the invoice ledger must be ready first. Existing records are preserved.</p>';
        if($staff&&$owner){recurring_ui_start('recurring_setup',[],[]);echo '<label>Your current owner password<input type="password" name="owner_password" autocomplete="current-password" required></label>';recurring_ui_confirmation('I have a current database backup and want to enable recurring agreement storage.');echo '<button class="btn gold">Enable recurring billing</button></form>';}
        else echo '<p>An owner must prepare this section.</p>';echo '</section>';return;
    }
    if(isset($v['projects'])){
        echo '<div class="workspace-grid">';foreach($v['projects']['items'] as $job){echo '<article class="workspace-card"><h2>'.esc($job['title']).'</h2>';if($staff)echo '<p>'.esc($job['client_name']).'</p>';echo '<a class="btn gold" href="'.esc(recurring_ui_url($staff,['job'=>$job['id']])).'">View recurring billing</a></article>';}
        if(!$v['projects']['items'])echo '<p>No projects are listed on this page.</p>';echo '</div>';recurring_ui_pages($v['projects'],'p',$staff,[]);return;
    }
    $job=$v['project'];echo '<p><a href="'.esc($staff?admin_url('studio-job',['id'=>$job['id']]):studio_url(['job'=>$job['id']])).'">Back to '.esc($job['title']).'</a></p>';
    if(isset($v['document'])){recurring_ui_detail($v);return;}
    if(isset($v['source'])){recurring_ui_offer_editor($v);return;}
    echo '<section class="workspace-card"><h2>Recurring agreements · '.esc($job['title']).'</h2>';
    if(!$v['schedules']['items'])echo '<p>No recurring agreements are listed on this page.</p>';
    foreach($v['schedules']['items'] as $s)echo '<article class="workspace-version"><h3>'.esc($s['title']).'</h3><p>'.esc(recurring_ui_money($s['amount_cents'])).' · '.esc(service_plans_cadences()[$s['cadence']]).' · '.esc(ucfirst($s['phase'])).'</p><a href="'.esc(recurring_ui_url($staff,['job'=>$job['id'],'schedule'=>$s['id']])).'">Read terms, history and invoices</a></article>';
    recurring_ui_pages($v['schedules'],'schedules_page',$staff,['job'=>$job['id']]);echo '</section>';
    if($staff){echo '<section class="workspace-card"><h2>Client service agreements</h2><p>Select the agreed recurring service that will be billed to this project.</p>';
        foreach($v['sources']['items'] as $s){echo '<article class="workspace-version"><h3>'.esc($s['title']).'</h3><p>'.esc(recurring_ui_money($s['amount_cents'])).' · '.esc(service_plans_cadences()[$s['cadence']]).' · '.esc($s['state']).'</p>';
            if($s['state']==='active'&&in_array($s['cadence'],['monthly','quarterly','annually'],true)&&$v['client']['active'])echo '<a href="'.esc(recurring_ui_url(true,['job'=>$job['id'],'source'=>$s['id']])).'">Prepare billing terms</a>';else echo '<p>An active recurring service agreement is required.</p>';echo '</article>';
        }
        if(!$v['sources']['items'])echo '<p>No service agreements are listed on this page. Create and agree the service terms first.</p>';recurring_ui_pages($v['sources'],'sources_page',true,['job'=>$job['id']]);echo '</section>';
    }
}
