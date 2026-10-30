<?php
/** Administrator portal hub. Customer authentication and support scopes stay separate. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function master_portal_url(array $query=[]): string {
    return site_url('portal/index.php').'?'.http_build_query(['master'=>'1']+$query);
}
/** A fixed destination, never a user-supplied return URL. */
function master_portal_login_query(): array {
    return ($_GET['portal']??null)==='master'?['portal'=>'master']:[];
}
function master_portal_login_destination(array $user): string {
    return master_portal_login_query()?master_portal_url():admin_url(staff_home($user));
}
/** Explicit routes do not override invitations, checkout, support or client deep links. */
function master_portal_entry(array $query,string $method,bool $adminSignedIn): string {
    if (array_key_exists('master',$query)) {
        if (($query['master']??null)!=='1' || array_diff(array_keys($query),['master','q','portal_page'])) return 'invalid';
        return $method==='GET'?'hub':'method';
    }
    return $method==='GET' && $query===[] && $adminSignedIn?'redirect':'none';
}
function master_portal_filters(array $query): array {
    $q=$query['q']??'';$page=$query['portal_page']??'1';
    if (!is_string($q)||strlen($q)>120||preg_match('//u',$q)!==1||preg_match('/[\x00-\x1f\x7f]/',$q)) throw new InvalidArgumentException('Search using a name or email of at most 120 bytes.');
    if (!is_string($page)||!preg_match('/^[1-9][0-9]{0,4}$/D',$page)) throw new InvalidArgumentException('Choose a valid client page.');
    return ['q'=>trim($q),'page'=>(int)$page];
}
/** Bound values; wildcard characters are literal. Staff cannot discover other clients. */
function master_portal_client_query(bool $owner,array $filters): array {
    $sql='SELECT c.id,c.name,c.email FROM '.table('studio_clients').' c';$params=[];
    if (!$owner) $sql.=' INNER JOIN '.table('support_clients').' p ON p.client_id=c.id AND p.allowed=1';
    $sql.=' WHERE c.active=1';
    if ($filters['q']!=='') {
        $sql.=" AND (c.name LIKE ? ESCAPE '=' OR c.email LIKE ? ESCAPE '=')";
        $needle='%'.strtr($filters['q'],['='=>'==','%'=>'=%','_'=>'=_']).'%';$params=[$needle,$needle];
    }
    $sql.=' ORDER BY c.id DESC LIMIT 51 OFFSET '.(($filters['page']-1)*50);
    return [$sql,$params];
}
function master_portal_clients(array $query): array {
    $filters=master_portal_filters($query);
    return staff_locked(fn()=>staff_transaction(function()use($filters):array {
        $actor=admin_user();
        if (!$actor||!admin_session_matches($actor)||!staff_can('support.login',$actor)) throw new RuntimeException('An active administrator with client-portal access is required.');
        if (!support_ready()||setting('support_policy_confirmed')!=='1') throw new RuntimeException('Enable client-portal review before listing accounts.');
        [$sql,$params]=master_portal_client_query(support_owner_review_allowed($actor),$filters);
        $rows=all($sql,$params);$more=count($rows)>50;
        return $filters+['clients'=>array_slice($rows,0,50),'more'=>$more];
    }));
}
function master_portal_render(array $actor): never {
    // The portal router does not load admin email controllers. Load the passive
    // navigation access policies before rendering the shared admin shell.
    require_once CWP_CODE_ROOT.'/app/mail/accounts.php';
    require_once CWP_CODE_ROOT.'/app/mail/notices.php';
    $data=null;$error=null;
    if (staff_can('support.login',$actor)&&support_ready()&&setting('support_policy_confirmed')==='1') {
        try {$data=master_portal_clients($_GET);}catch(InvalidArgumentException|RuntimeException $e) {
            if ($e instanceof PDOException) throw $e;
            http_response_code($e instanceof InvalidArgumentException?400:403);$error=$e->getMessage();
        }
    }
    admin_head('Master client portal','support',$actor);
    if ($error!==null) {echo '<div class="notice error" role="alert">'.esc($error).'</div>';admin_foot();exit;}
    ?><p class="admin-intro">One administrator sign-in. Choose a client workspace below, or manage services and billing from the shortcuts. Client review keeps your staff identity and audit trail; it never signs in as the customer.</p>
    <section class="workspace-card"><h2>Your administration workspace</h2><div class="button-row"><?php
    foreach (['studio-clients'=>'Manage clients','catalog'=>'Products & prices','hosting-offers'=>'Hosting offers','hosting-purchases'=>'Hosting purchases','recurring-billing'=>'Recurring billing','payments'=>'Payment requests','provider-settings'=>'20i workspace','support'=>'Client access settings'] as $view=>$label) {
        $permission=staff_view_permission($view);
        if ($permission!==null&&staff_can($permission,$actor)) echo '<a class="btn outline small" href="'.esc(admin_url($view)).'">'.esc($label).'</a>';
    }
    ?></div><p class="hint">Your administrator account is not converted into a customer account. Customer purchases, payment approvals, signatures and security settings remain separate.</p></section><?php
    if (!staff_can('support.login',$actor)) {
        echo '<div class="notice warning">Your administrator account does not have client-portal review permission. An owner can manage your permissions in Administrators.</div>';admin_foot();exit;
    }
    if (!support_ready()||setting('support_policy_confirmed')!=='1') {
        echo '<section class="workspace-card"><h2>Set up client-portal access</h2><p>Client review must be explicitly enabled once. No customer account is created or changed by opening this page.</p>';
        if (staff_can('staff.manage',$actor)) echo '<a class="btn gold" href="'.esc(admin_url('support')).'">Set up client access</a>';
        else echo '<p>Ask an owner to enable client access.</p>';
        echo '</section>';admin_foot();exit;
    }
    if (support_binding()!=='') {
        $open=one('SELECT id FROM '.table('support_sessions').' WHERE binding_hash=? AND actor_id=? AND ended_at IS NULL AND expires_at>? AND last_seen_at>? ORDER BY started_at DESC LIMIT 1',[support_binding(),$actor['id'],time(),time()-300]);
        if ($open) echo '<div class="notice info">A client review is already open. End it before choosing another client. <a href="'.esc(site_url('portal/index.php?'.http_build_query(['support'=>$open['id']]))).'">Resume current review</a></div>';
    }
    ?><section class="workspace-card" id="master-client-accounts"><h2>Client portals</h2><p><?=support_owner_review_allowed($actor)?'Owner access: all active client accounts.':'Only active clients approved for your staff support access are listed.'?></p>
    <form method="get" action="<?=esc(site_url('portal/index.php'))?>"><input type="hidden" name="master" value="1"><label>Find a client by name or email<input type="search" name="q" maxlength="120" value="<?=esc($data['q'])?>"></label><div class="button-row"><button class="btn gold">Search clients</button><a class="btn outline" href="<?=esc(master_portal_url())?>">Clear search</a></div></form>
    <p class="hint">Each review is time-limited and audited. Customer payments, signatures and password changes are not available in review mode.</p>
    <div class="workspace-grid"><?php foreach($data['clients'] as $client):?><article class="workspace-card"><h3><?=esc($client['name'])?></h3><p><?=esc($client['email'])?></p><?php support_open_form((int)$client['id']);?></article><?php endforeach;?></div>
    <?php if(!$data['clients']):?><p role="status">No accessible active clients match this search. Clear the search or review client access settings.</p><?php endif;?>
    <nav aria-label="Master portal client pages"><?php if($data['page']>1):?><a href="<?=esc(master_portal_url(['q'=>$data['q'],'portal_page'=>$data['page']-1]))?>">Previous clients</a> <?php endif;if($data['more']):?><a href="<?=esc(master_portal_url(['q'=>$data['q'],'portal_page'=>$data['page']+1]))?>">Next clients</a><?php endif;?></nav></section><?php
    admin_foot();exit;
}
function master_portal_dispatch(): void {
    $requested=array_key_exists('master',$_GET);
    if (support_current()||array_key_exists('support',$_GET)) {
        if ($requested) fail_page('End client review before opening the master portal.',403);
        return;
    }
    $actor=admin_user();$entry=master_portal_entry($_GET,$_SERVER['REQUEST_METHOD']??'GET',$actor!==null);
    if ($entry==='none') return;
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    if ($entry==='invalid') fail_page('Invalid master portal request.',400);
    if ($entry==='method') {header('Allow: GET');fail_page('Use the individual action forms for client administration.',405);}
    if ($entry==='redirect') redirect(master_portal_url());
    if (!$actor) redirect(admin_url('login',['portal'=>'master']));
    master_portal_render($actor);
}
