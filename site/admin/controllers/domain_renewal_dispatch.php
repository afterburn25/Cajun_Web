<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_dispatch_routes.php';
function domain_renewal_dispatch_admin_action(string $action):never {
    check_csrf();domain_renewal_dispatch_owner(null,false);$password=posted('owner_password',1000);$request='';
    if($action==='provider_renewal_dispatch_prepare'){domain_renewal_dispatch_prepare($password);flash('Renewal invoice and approval history is ready.');}
    elseif($action==='provider_renewal_dispatch_allocate'){
        $request=posted('request_id',32);domain_renewal_dispatch_allocate(posted('allocation_id',32),$request,posted('binding_sha256',64),posted('agreement_sha256',64),gint($_POST,'invoice_id',1,2147483647),posted('invoice_sha256',64),posted('allocation_confirmed',1)==='1',$password);flash('The unpaid invoice is permanently reserved for these accepted renewal terms. No payment or renewal was submitted.');
    }elseif($action==='provider_renewal_dispatch_create_invoice'){
        $request=posted('request_id',32);$choice=posted('project_choice',80);
        if(!preg_match('/\A([1-9][0-9]{0,9}):([a-f0-9]{64})\z/',$choice,$project)||(int)$project[1]>2147483647)throw new InvalidArgumentException('Choose a project from the current renewal invoice review.');
        domain_renewal_dispatch_create_invoice(posted('allocation_id',32),$request,posted('binding_sha256',64),posted('agreement_sha256',64),(int)$project[1],$project[2],posted('create_invoice_confirmed',1)==='1',$password);
        flash('The renewal invoice is ready in the client’s project and permanently reserved for the accepted terms.');
    }elseif($action==='provider_renewal_dispatch_send'){
        $review=[];foreach(['eligible_years','privacy_scope','provider_cost','balance_charge'] as $key)$review[$key]=posted($key,1)==='1';$result=domain_renewal_dispatch_send(posted('dispatch_id',32),posted('allocation_id',32),posted('review_sha256',64),$review,$password);$request=$result['request_id'];flash(domain_renewal_dispatch_status($result),$result['status']==='provider_reported_success'?'success':'error');
    }else throw new InvalidArgumentException('Unknown renewal payment action.');
    if($request==='')$request=is_string($_GET['request']??null)?$_GET['request']:'';redirect(admin_url('domain-renewal-dispatch',['request'=>$request]));
}
function domain_renewal_dispatch_admin_render(string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try{$actor=provider_settings_manager(false);service_plans_owner(false);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('Renewal payment unavailable','provider-settings',require_admin());echo '<p class="notice error">'.esc($e->getMessage()).'</p>';admin_foot();exit;}
    admin_head('Renewal payment and approval','provider-settings',$actor);show_flash();if($error!=='')echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    try{$request=is_string($_GET['request']??null)?$_GET['request']:'';DomainRenewalInvoiceDocument::id($request);$context=domain_renewal_dispatch_owner_request($request,max(1,(int)($_GET['invoice_page']??1)),max(1,(int)($_GET['project_page']??1)));domain_renewal_dispatch_owner_view($context,$_POST);echo '<p><a href="'.esc(admin_url('domain-renewal-agreements',['request'=>$request])).'">Review original renewal agreements</a></p>';if($context['prepared']){echo '<nav aria-label="Matching invoice pages">';if($context['page']>1)echo '<a href="'.esc(admin_url('domain-renewal-dispatch',['request'=>$request,'invoice_page'=>$context['page']-1,'project_page'=>$context['project_page']])).'">Newer invoices</a> ';if($context['page']<10000)echo '<a href="'.esc(admin_url('domain-renewal-dispatch',['request'=>$request,'invoice_page'=>$context['page']+1,'project_page'=>$context['project_page']])).'">Older invoices</a>';echo '</nav>';}}
    catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';}
    admin_foot();exit;
}
