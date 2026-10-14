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
