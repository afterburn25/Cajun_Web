<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/forwarding.php';
function provider_forwarding_render_history(array $doc,bool $admin):void {
    $d=$doc['request'];$p=$d['payload'];$state=match($doc['state']){'declined'=>'Declined','cancelled'=>'Cancelled',default=>'Pending studio review'};
    echo '<article class="workspace-card" data-forwarding-request><h3 style="overflow-wrap:anywhere">'.esc($p['local'].'@'.$p['resource']['binding']['domain']).'</h3><p style="overflow-wrap:anywhere">Requested destination: '.esc($p['remote']).'</p><p><strong>'.esc($state).'</strong> · '.esc(gmdate('Y-m-d H:i:s',$d['recorded_epoch'])).' UTC</p><p class="hint">This local request has not changed email delivery. Forwarding is not active through this workflow.</p>';
    if($admin){$client=one('SELECT name FROM '.table('studio_clients').' WHERE id=?',[$d['client_id']]);echo '<p>Client: '.esc($client['name']??'Unavailable client').'</p>';}
    foreach($doc['events'] as $e)echo '<div class="workspace-message"><small>'.esc($e['kind']==='cancel'?'Client cancellation':'Studio review').' · '.esc(gmdate('Y-m-d H:i:s',$e['recorded_epoch'])).' UTC</small><p>'.esc($e['payload']['note']?:'Request cancelled.').'</p></div>';
    if($doc['state']==='pending_review'){
        $retry=is_string($_POST['request_id']??null)&&$_POST['request_id']===$d['id'];$field=static fn(string $key,string $fallback,int $max=32):string=>$retry&&is_string($_POST[$key]??null)?substr($_POST[$key],0,$max):$fallback;
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="'.($admin?'provider_inventory_forwarding_review':'forwarding_cancel').'"><input type="hidden" name="request_id" value="'.esc($d['id']).'"><input type="hidden" name="expected_event" value="'.esc($field('expected_event',$doc['latest_event'])).'"><input type="hidden" name="event_id" value="'.esc($field('event_id',bin2hex(random_bytes(16)))).'">';
        if($admin)echo '<label>Message for the client<textarea name="review_note" maxlength="1000" required>'.esc($field('review_note','',1000)).'</textarea></label><div class="button-row"><button class="btn gold" name="command" value="review">Save review message</button><button class="btn outline" name="command" value="decline">Decline request</button></div><p class="hint">Saving a message keeps the request pending. It does not submit forwarding to 20i.</p>';
        else echo '<button class="btn outline">Cancel request</button>';echo '</form>';
    }echo '</article>';
}
