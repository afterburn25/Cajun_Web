<?php
/** Escaped service-agreement content shared by owner and client screens. No queries or actions. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/plans/forms.php';

function service_plans_cadences():array {
    return ['one-time'=>'One-time service','monthly'=>'Monthly','quarterly'=>'Every three months','annually'=>'Yearly'];
}
function service_plans_agreement_label(ServiceSubscriptionDocument $doc):string {
    $model=$doc->model();
    return match($model->state()) {
        'pending-consent'=>$model->consent()?'Agreed · awaiting studio activation':'Awaiting your agreement',
        'active'=>'Active agreement','paused'=>'Paused agreement','cancelled'=>'Ended agreement','abandoned'=>'Declined offer',
        default=>'Unavailable',
    };
}
function service_plans_terms_view(ServicePlanDocument $doc):void {
    $plan=$doc->model();
    echo '<section class="workspace-card"><h2>'.esc($plan->name).'</h2><p><strong>$'.esc(gmoney($plan->priceAmount)).' USD</strong> · '.esc(service_plans_cadences()[$plan->cadence]).'</p>';
    foreach(['Included work'=>$plan->inclusions,'Not included'=>$plan->exclusions] as $label=>$items) {
        echo '<h3>'.esc($label).'</h3>';
        if(!$items)echo '<p>No additional exclusions listed.</p>';
        else {echo '<ul>';foreach($items as $text)echo '<li>'.esc($text).'</li>';echo '</ul>';}
    }
    echo '<h3>Usage limits</h3>';
    if(!$plan->limits)echo '<p>No separately metered usage is listed. The included work still defines the service scope.</p>';
    else {echo '<dl>';foreach($plan->limits as $name=>$cap)echo '<dt>'.esc(str_replace('_',' ',$name)).'</dt><dd>'.esc((string)$cap).'</dd>';echo '</dl>';}
    echo '<h3>Support expectations</h3><p>'.nl2br(esc($plan->supportExpectation)).'</p></section>';
}
function service_plans_terms_fields(array $values):void {
    foreach(['name'=>'Plan name','price'=>'Price in USD'] as $key=>$label) {
        echo '<label>'.esc($label).'<input name="'.esc($key).'" value="'.esc($values[$key]??'').'" maxlength="'.($key==='name'?120:12).'"'.($key==='price'?' inputmode="decimal"':'').' required></label>';
    }
    echo '<label>Service period<select name="cadence" required><option value="">Choose a service period</option>';
    foreach(service_plans_cadences() as $key=>$label)echo '<option value="'.esc($key).'"'.(($values['cadence']??'')===$key?' selected':'').'>'.esc($label).'</option>';
    echo '</select></label>';
    foreach(['inclusions'=>['Included work · one item per line',33000,5,true],'exclusions'=>['Not included · one item per line',33000,4,false],'limits'=>['Usage limits · one name = number per line',33000,4,false],'support'=>['Support expectations',1000,4,true]] as $key=>[$label,$max,$rows,$required]) {
        echo '<label>'.esc($label).'<textarea name="'.esc($key).'" maxlength="'.$max.'" rows="'.$rows.'"'.($required?' required':'').'>'.esc($values[$key]??'').'</textarea></label>';
    }
    echo '<p class="hint">Use up to 32 lines per list. Example usage limit: updates = 4. Describe concrete work and response expectations.</p>';
}
/** Caller chooses a fixed allowlisted action and provides its existing CSRF field. */
function service_plans_intent_fields(string $action,array $intent,string $csrf):void {
    echo $csrf;
    echo '<input type="hidden" name="action" value="'.esc($action).'">';
    foreach(['record_id','revision','event_id'] as $key)echo '<input type="hidden" name="'.esc($key).'" value="'.esc((string)$intent[$key]).'">';
}
function service_plans_confirmation(string $label,bool $owner=false):void {
    echo '<label class="check"><input type="checkbox" name="confirm" value="1" required>'.esc($label).'</label>';
    if($owner)echo '<label>Your current password<input type="password" name="current_password" autocomplete="current-password" required></label>';
}
function service_plans_agreement_history(ServiceSubscriptionDocument $doc):void {
    $d=$doc->data();echo '<section class="workspace-card"><h2>Agreement history</h2><p>Offered '.esc(gmdate('Y-m-d H:i:s',$d['created_at'])).' UTC by the studio.</p><ol>';
    foreach($d['events'] as $event) {
        $actor=str_starts_with($event['actor'],'client:')?'Client':'Studio';
        $label=match($event['command']) {'consent'=>'Service terms accepted','activate'=>'Agreement activated','pause'=>'Agreement paused','resume'=>'Agreement resumed','cancel'=>'Agreement ended','abandon'=>'Offer declined',default=>'Recorded action'};
        echo '<li><strong>'.esc($label).'</strong> · '.esc($actor).' · '.esc(gmdate('Y-m-d H:i:s',$event['at'])).' UTC';
        if($event['command']==='cancel')echo '<p>'.nl2br(esc($event['arguments']['reason'])).'</p>';
        echo '</li>';
    }
    echo '</ol><p class="hint">Accepting service terms does not authorize automatic payments. Review invoices and payment arrangements separately.</p></section>';
}
function service_plans_ui_intent(ServicePlanDocument|ServiceSubscriptionDocument $doc,string $action):array {
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??null)===$action) {
        $values=service_plans_form_values($_POST);return array_intersect_key($values,array_flip(['record_id','revision','event_id']));
    }
    return ['record_id'=>$doc->data()['id'],'revision'=>$doc->revision(),'event_id'=>bin2hex(random_bytes(16))];
}
function service_plans_ui_page():int {return service_plans_form_number(['page'=>$_GET['p']??'0'],'page',10000);}
function service_plans_ui_pagination(array $page,callable $url):void {
    echo '<nav class="button-row" aria-label="Agreement pages">';
    if($page['page']>0)echo '<a class="btn outline" href="'.esc($url($page['page']-1)).'">Previous</a>';
    if($page['more'])echo '<a class="btn outline" href="'.esc($url($page['page']+1)).'">Next</a>';
    echo '</nav>';
}
function service_plans_agreement_controls(ServiceSubscriptionDocument $doc,bool $owner,bool $clientActive=true):void {
    $model=$doc->model();$commands=[];
    if($model->state()==='pending-consent') {
        if($owner && $clientActive && $model->consent())$commands['activate']='Activate this agreement';
        if(!$owner && !$model->consent())$commands['consent']='Accept these service terms';
        $commands['abandon']=$owner?'Withdraw this offer':'Decline this offer';
    }elseif(in_array($model->state(),['active','paused'],true)) {
        if($owner && ($model->state()==='active' || $clientActive))$commands[$model->state()==='active'?'pause':'resume']=$model->state()==='active'?'Pause this agreement':'Resume this agreement';
        $commands['cancel']='End this agreement';
    }
    foreach($commands as $command=>$label) {
        $action=($owner?'service_plans_':'service_agreements_').$command;$intent=service_plans_ui_intent($doc,$action);
        echo '<section class="workspace-card"><h2>'.esc($label).'</h2><form method="post">';service_plans_intent_fields($action,$intent,csrf_field());
        if($owner)echo '<input type="hidden" name="client_id" value="'.(int)$doc->data()['client_id'].'">';
        if($command==='consent') {
            $hash=($_POST['action']??null)===$action?service_plans_form_values($_POST)['terms_hash']:$model->plan->termsHash();
            echo '<input type="hidden" name="terms_hash" value="'.esc($hash).'"><p>Accept the exact service scope and price shown above. This does not authorize automatic payments.</p>';
        }
        if($command==='cancel') {
            $reason=($_POST['action']??null)===$action?service_plans_form_values($_POST)['reason']:'';
            echo '<label>Reason for ending this agreement<textarea name="reason" maxlength="1000" rows="3" required>'.esc($reason).'</textarea></label><p class="hint">This records the end of this agreement. Any final balance or refund must be arranged separately with the studio.</p>';
        }
        service_plans_confirmation($command==='consent'?'I agree to the service terms and price shown above.':'I have reviewed and confirm this agreement change.',$owner);
        echo '<button class="btn gold">'.esc($label).'</button></form></section>';
    }
}
