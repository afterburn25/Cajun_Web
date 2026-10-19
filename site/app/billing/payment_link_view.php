<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

/** A bearer request authorizes only its unchanged invoice; terminal links expose no invoice fields. */
function payments_link_projection(array $request):array {
    if($request['status']!=='prepared')return ['state'=>in_array($request['status'],['revoked','superseded'],true)?$request['status']:'unavailable'];
    if((int)$request['expires_at']<=time())return ['state'=>'expired'];
    $invoice=one('SELECT i.*,j.client_id,c.active FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE i.id=?',[$request['invoice_id']]);
    if(!$invoice||(int)$invoice['job_id']!==(int)$request['job_id']||(int)$invoice['client_id']!==(int)$request['client_id']||(int)$invoice['active']!==1)return ['state'=>'unavailable'];
    if($invoice['status']==='paid')return ['state'=>'paid'];
    if($invoice['status']!=='issued'||$invoice['currency']!=='USD'||(int)$invoice['amount_cents']<1||(int)$invoice['amount_cents']>99999999)return ['state'=>'unavailable'];
    try{
        if(!hash_equals($request['invoice_hash'],payments_invoice_hash($invoice)))return ['state'=>'invoice changed'];
    }catch(RuntimeException){return ['state'=>'unavailable'];}
    // The fields rendered below come from the same invoice snapshot whose fingerprint was checked.
    return ['state'=>'prepared','details'=>[
        'reference'=>$invoice['reference'],'title'=>$invoice['title'],'instructions'=>$invoice['instructions'],
        'amount_cents'=>(int)$request['amount_cents'],'currency'=>$request['currency'],
        'purpose'=>payments_purposes()[$request['purpose']]??'Invoice payment','expires_at'=>(int)$request['expires_at'],
    ]];
}

function payments_link_card(array $view):void {
    $state=$view['state'];
    echo '<article class="workspace-card"><span class="workspace-status">'.esc($state).'</span>';
    if($state==='prepared'){
        $details=$view['details'];
        echo '<h2>'.esc($details['reference'].' · '.$details['title']).'</h2>';
        echo '<p><strong>'.esc($details['purpose']).'</strong> · $'.esc(number_format($details['amount_cents']/100,2,'.','')).' '.esc($details['currency']).' due · request expires '.esc(gmdate('Y-m-d H:i:s',$details['expires_at'])).' UTC</p>';
        echo '<div class="prose">'.nl2br(esc($details['instructions'])).'</div><p class="hint">Online card payment is being enabled and is not available on this link yet. Viewing this link does not record a payment — pay using the studio’s instructions above, or contact the studio for help. This link is private to this invoice; do not forward it.</p>';
    }elseif($state==='paid')echo '<p>This invoice has already been paid. No further payment is due.</p>';
    elseif($state==='revoked')echo '<p>The studio revoked this payment request. Contact the studio before paying.</p>';
    elseif($state==='superseded')echo '<p>A newer payment request replaced this link. Use the studio’s latest link or contact the studio.</p>';
    elseif($state==='expired')echo '<p>This payment link expired. Ask the studio for a new link.</p>';
    else echo '<p>The invoice details changed or are no longer payable through this link. Contact the studio for a current request.</p>';
    echo '</article>';
}
