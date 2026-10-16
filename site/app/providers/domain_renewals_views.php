<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewals.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements.php';
function domain_renewals_privacy(?bool $value):string {return $value===null?'Ask the studio':($value?'Request privacy renewal':'Do not request privacy renewal');}
function domain_renewals_render_history(array $doc,bool $admin):void {
    $d=$doc['request'];$state=match($doc['state']){'declined'=>'Declined','withdrawn'=>'Withdrawn',default=>'Pending studio review'};
    echo '<article class="workspace-card" data-renewal-request><h3 style="overflow-wrap:anywhere">'.esc($d['domain']).'</h3><p><strong>'.esc($state).'</strong> · Requested '.esc(gmdate('Y-m-d H:i:s',$d['created_epoch'])).' UTC</p><p>Requested term: '.$d['requested_years'].' year(s) · '.esc(domain_renewals_privacy($d['renew_privacy'])).'</p><p class="hint">This historical request does not confirm a price, eligible term, expiry date or completed renewal.</p>';
    if($admin){$client=one('SELECT name FROM '.table('studio_clients').' WHERE id=?',[$d['client_id']]);echo '<p>Client: '.esc($client['name']??'Unavailable client').' · Request '.esc($d['id']).'</p>';}
    foreach($doc['events'] as $e){echo '<div class="workspace-message"><small>'.esc($e['actor_kind']==='staff'?'Studio review':'Client withdrawal').' · '.esc(gmdate('Y-m-d H:i:s',$e['recorded_epoch'])).' UTC</small><p>'.esc($e['note']!==''?$e['note']:'Request withdrawn.').'</p></div>';}
    if($admin)echo '<p><a class="btn outline" href="'.esc(admin_url('domain-renewal-agreements',['request'=>$d['id']])).'">Review renewal service offers</a></p>';
    foreach(domain_renewal_agreements_request_links($d['id'],$d['client_id'],!$admin) as $offer){$url=$admin?admin_url('service-agreement',['record'=>$offer['agreement_id']]):studio_url(['agreement'=>$offer['agreement_id']]);echo '<p><a href="'.esc($url).'">Renewal offer '.esc((string)$offer['revision']).' · USD '.esc(number_format($offer['price_minor']/100,2)).' · review terms and history</a></p>';}
    if($doc['state']==='pending_review'){
        $retry=isset($_POST['request_id'])&&is_string($_POST['request_id'])&&$_POST['request_id']===$d['id'];
        $revision=$retry&&is_string($_POST['request_revision']??null)?substr($_POST['request_revision'],0,10):(string)$doc['revision'];$event=$retry&&is_string($_POST['event_id']??null)?substr($_POST['event_id'],0,32):bin2hex(random_bytes(16));
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="'.($admin?'provider_inventory_renewal_review':'domain_renewal_withdraw').'"><input type="hidden" name="request_id" value="'.esc($d['id']).'"><input type="hidden" name="request_revision" value="'.esc($revision).'"><input type="hidden" name="event_id" value="'.esc($event).'">';
        if($admin){$note=$retry&&is_string($_POST['review_note']??null)?substr($_POST['review_note'],0,1000):'';echo '<label>Message for the client<textarea name="review_note" maxlength="1000" required>'.esc($note).'</textarea></label><label>Your current owner password<input type="password" name="current_password" autocomplete="current-password" required></label><div class="button-row"><button class="btn gold" name="command" value="reviewed">Save review message</button><button class="btn outline" name="command" value="declined">Decline request</button></div><p class="hint">A review message leaves the request pending. It does not approve or place a renewal.</p>';}
        else echo '<button class="btn outline">Withdraw request</button>';echo '</form>';
    }echo '</article>';
}
