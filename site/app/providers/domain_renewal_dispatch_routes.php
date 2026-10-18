<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_dispatch.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_dispatch_views.php';
/** Existing request queries can run inside creation or from a saved-only client GET. */
function domain_renewal_dispatch_intake_guard(string $domain):void {
    $check=static function()use($domain):void{domain_renewal_agreements_locked();if(domain_renewal_dispatch_ready())domain_renewal_dispatch_fence($domain);};
    if(db()->inTransaction())$check();else staff_locked(fn()=>staff_transaction($check));
}
function domain_renewal_dispatch_request_cards(string $request,int $client,bool $asClient):array {
    if(db()->inTransaction())throw new LogicException('Renewal history rendering cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($request,$client,$asClient):array{
        if($asClient)domain_renewals_actor($client);else provider_settings_manager();if(!domain_renewal_dispatch_ready())return [];
        if(!one('SELECT id FROM '.table('domain_renewal_requests').' WHERE id=? AND client_id=?',[$request,$client]))throw new RuntimeException('Renewal history unavailable.');
        $out=[];foreach(all('SELECT id FROM '.table('domain_renewal_invoices').' WHERE request_id=? AND client_id=? ORDER BY id LIMIT 129',[$request,$client]) as $n=>$r){if($n>=128)throw new RuntimeException('Renewal history exceeds its review limit.');$out[]=domain_renewal_dispatch_public(domain_renewal_dispatch_allocation($r['id']));}return $out;
    }));
}
/** No optional-family requirements on ordinary invoices. Established missing history still refuses. */
function domain_renewal_dispatch_invoice_display(int $invoice,int $client,bool $asClient):?array {
    if(db()->inTransaction())throw new LogicException('Renewal purpose rendering cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($invoice,$client,$asClient):?array{
        if(!domain_renewal_dispatch_ready())return null;$r=one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE invoice_id=?',[$invoice]);if(!$r)return null;
        if($asClient)domain_renewals_actor($client);else payments_require();
        $a=domain_renewal_dispatch_allocation($r['id']);if((int)$a['invoice']['client_id']!==$client||($asClient&&(int)$a['invoice']['active']!==1))throw new RuntimeException('Renewal invoice unavailable.');return domain_renewal_dispatch_public($a);
    }));
}
