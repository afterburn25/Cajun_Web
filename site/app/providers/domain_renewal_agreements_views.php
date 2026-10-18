<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
/** Preserve captured authority and entered scope on an error; never silently adopt a newer revision. */
function domain_renewal_agreements_form(ServiceSubscriptionDocument $candidate,array $request,?string $previous,array $input):array {
    $d=$candidate->data();$values=['binding_id'=>bin2hex(random_bytes(16)),'request_id'=>$request['request']['id'],'request_revision'=>(string)$request['revision'],'request_sha256'=>$request['sha256'],'agreement_id'=>$d['id'],'agreement_sha256'=>$candidate->fingerprint(),'previous_sha256'=>$previous??'','offer_expires'=>gmdate('Y-m-d\TH:i',time()+604800),'renew_privacy'=>$request['request']['renew_privacy']===null?'':($request['request']['renew_privacy']?'1':'0')];
    if(($input['action']??null)==='provider_inventory_renewal_agreement_bind'&&($input['agreement_id']??null)===$d['id'])foreach($values as $key=>$unused){$v=$input[$key]??'';if(!is_string($v)||strlen($v)>128||preg_match('//u',$v)!==1)$v='';$values[$key]=$v;}
    return $values;
}
/** Public projection only; this layout performs no queries, preparation or provider calls. */
function domain_renewal_agreements_card(array $c):void {
    $state=match($c['state']){'abandoned'=>'Renewal offer withdrawn','cancelled'=>'Renewal agreement ended',default=>$c['consented']?'Renewal service terms accepted':'Renewal terms for review'};
    ?><section class="workspace-card renewal-agreement-scope"><h3><?=esc($state)?></h3>
    <dl class="details-grid"><div><dt>Registered domain</dt><dd><?=esc($c['domain'])?></dd></div><div><dt>Requested renewal</dt><dd><?=esc((string)$c['years'])?> year<?= $c['years']===1?'':'s' ?></dd></div><div><dt>Quoted privacy scope</dt><dd><?= $c['renew_privacy']?'Include renewal of privacy':'Do not renew privacy' ?></dd></div><div><dt>Studio price</dt><dd>USD <?=esc(number_format($c['price_minor']/100,2,'.',','))?> · one time</dd></div><div><dt>Accept by</dt><dd><?=esc(gmdate('Y-m-d H:i',$c['expires_epoch']))?> UTC</dd></div></dl>
    <?php if($c['requested_privacy']===null):?><p>The request asked the studio to recommend a privacy choice. The quoted choice above is part of the terms you review.</p><?php endif;?>
    <p>Acceptance records your agreement to the studio’s service terms. It does not submit a renewal, collect a payment, or confirm registry eligibility. The studio will arrange the next steps separately.</p>
    <?php if(!$c['actionable']&&!in_array($c['state'],['abandoned','cancelled'],true)):?><p class="notice">This saved offer is not currently available for a new agreement action. The studio must review the request and current assignment; expired offers need new terms. You can still end this agreement and read its history.</p><?php endif;?></section><?php
}
