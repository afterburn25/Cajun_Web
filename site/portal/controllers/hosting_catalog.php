<?php
/** Client hosting requests. Router owns login and CSRF; services recheck current authority atomically. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers.php';
require_once CWP_CODE_ROOT.'/app/plans/forms.php';
require_once CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php';

function hosting_catalog_portal_query(array $query):array {
    $tab=$query['hosting']??null;
    if (!is_string($tab)||!in_array($tab,['offers','requests','checkout'],true)) throw new InvalidArgumentException('Choose available plans or your requests.');
    $page=service_plans_form_number(['page'=>$query['p']??'1'],'page',100000);
    if ($page<1) throw new InvalidArgumentException('Choose an available page.');
    return ['tab'=>$tab,'page'=>$page];
}
/** Allowlisted redisplay values only; no password, arbitrary field, array or secret is retained. */
function hosting_catalog_portal_values(array $input):array {
    $values=[];
    foreach (['action'=>40,'request_id'=>32,'publication_id'=>32,'publication_sha256'=>64,'decision_id'=>32,'request_sha256'=>64,'reason'=>1000] as $key=>$max) {
        $value=$input[$key]??'';
        $values[$key]=is_string($value)&&strlen($value)<=$max&&preg_match('//u',$value)===1&&!str_contains($value,"\0")?$value:'';
    }
    return $values;
}
/** Original POST identities survive even when outer CSRF rejected before this controller was reached. */
function hosting_catalog_portal_attempt():?array {
    return ($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'hosting_catalog_')?hosting_catalog_portal_values($_POST):null;
}
function hosting_catalog_portal_action(string $action,array $client):void {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') throw new InvalidArgumentException('Submit the reviewed form to continue.');
    $query=hosting_catalog_portal_query($_GET);
    if ($query['tab']==='checkout') {
        require_once CWP_CODE_ROOT.'/portal/controllers/hosting_checkout.php';
        hosting_checkout_portal_action($action,$client);return;
    }
    service_plans_form_confirm($_POST);
    if ($action==='hosting_catalog_request'&&$query['tab']==='offers') {
        $requestId=service_plans_form_id($_POST,'request_id');$publicationId=service_plans_form_id($_POST,'publication_id');$publicationHash=service_plans_form_hash($_POST,'publication_sha256');
        if (!consume_rate('client-hosting-action-'.(int)$client['id'],20,3600)) throw new RuntimeException('Please wait before making more hosting requests.');
        hosting_catalog_request($requestId,$publicationId,$publicationHash,(int)$client['id']);
        flash('Your hosting request was saved. The studio will review it before offering a service agreement.');
    } elseif ($action==='hosting_catalog_withdraw_request'&&$query['tab']==='requests') {
        $decisionId=service_plans_form_id($_POST,'decision_id');$requestId=service_plans_form_id($_POST,'request_id');$requestHash=service_plans_form_hash($_POST,'request_sha256');$reason=service_plans_form_text($_POST,'reason',1000,true);
        if (!consume_rate('client-hosting-action-'.(int)$client['id'],20,3600)) throw new RuntimeException('Please wait before making more hosting requests.');
        hosting_catalog_close_request($decisionId,$requestId,$requestHash,$reason,(int)$client['id'],null);
        flash('Your pending request was withdrawn. Existing service agreements were not changed.');
    } else throw new InvalidArgumentException('Reload the hosting page for this action.');
    redirect(studio_url(['hosting'=>'requests']));
}
/** Fixed client actions only; identity bytes are escaped but never replaced with current hashes. */
function hosting_catalog_portal_form(array $values,bool $enabled=true,int $page=1):void {
    if ($page<1||$page>100000) throw new InvalidArgumentException('Choose an available page.');
    $action=$values['action']??'';
    if (!in_array($action,['hosting_catalog_request','hosting_catalog_withdraw_request'],true)) return;
    $request=$action==='hosting_catalog_request';$tab=$request?'offers':'requests';
    echo '<form method="post" action="'.esc(studio_url(['hosting'=>$tab,'p'=>$page])).'">'.csrf_field().'<input type="hidden" name="action" value="'.esc($action).'">';
    foreach ($request?['request_id','publication_id','publication_sha256']:['decision_id','request_id','request_sha256'] as $key) echo '<input type="hidden" name="'.esc($key).'" value="'.esc($values[$key]??'').'">';
    if (!$request) echo '<label>Reason (optional)<textarea name="reason" maxlength="1000" rows="3">'.esc($values['reason']??'').'</textarea></label>';
    echo '<label class="check"><input type="checkbox" name="confirm" value="1" required'.($enabled?'':' disabled').'>'.($request?'I request one package on the displayed terms.':'Withdraw this pending request. This does not cancel an existing service agreement.').'</label>';
    echo '<button class="btn '.($request?'gold':'outline').'"'.($enabled?'':' disabled').'>'.($request?'Request this plan':'Withdraw request').'</button></form>';
}
function hosting_catalog_portal_render(array $client,string $error):never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    if (($_GET['hosting']??null)==='checkout') {
        require_once CWP_CODE_ROOT.'/portal/controllers/hosting_checkout.php';
        hosting_checkout_portal_render($client,$error);
    }
    try {recurring_billing_touch(fn()=>recurring_billing_locked(fn()=>service_plans_current_client((int)$client['id'])),false);}
    catch (Throwable $e) {if ($e instanceof PDOException||!($e instanceof InvalidArgumentException||$e instanceof RuntimeException)) {report_error($e);fail_page('Hosting requests are temporarily unavailable.',503);}fail_page('Sign in as the client outside support access to view hosting requests.',403);}
    try {$query=hosting_catalog_portal_query($_GET);}
    catch (InvalidArgumentException) {fail_page('Hosting page not found.',404);}
    $attempt=hosting_catalog_portal_attempt();$unavailable=false;
    try {$page=$query['tab']==='offers'?hosting_catalog_public($query['page']):hosting_catalog_client_requests((int)$client['id'],$query['page']);}
    catch (Throwable $e) {report_error($e);$page=['offers'=>[],'requests'=>[],'has_more'=>false,'unavailable'=>true];$unavailable=true;}
    site_head('Your hosting plans','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR CLIENT WORKSPACE</span><h1>Hosting <span class="gold-text">plans</span></h1><p>Review a plan, send a request and follow the studio’s response.</p><a class="btn outline" href="'.esc(studio_url()).'">All projects</a></div></section><section class="section"><div class="container narrow">';show_flash();
    if ($error!=='') echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/portal/views/hosting_catalog.php';
    echo '</div></section>';site_foot(false);exit;
}
