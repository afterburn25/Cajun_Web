<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_offer_views.php';
require_once CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php';

function hosting_catalog_admin_action(string $action): void {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') throw new InvalidArgumentException('Use the hosting offer form.');
    check_csrf();hosting_catalog_admin_owner();
    $intent=hosting_catalog_admin_intent($action,$_POST);
    if ($action==='hosting_catalog_preview') {
        $GLOBALS['hosting_catalog_admin_preview']=hosting_catalog_preview($intent['publication_id'],$intent['product_id'],$intent['plan_id']);
        return;
    }
    $password=service_plans_form_text($_POST,'current_password',200);
    if ($action==='hosting_catalog_setup') {
        hosting_catalog_prepare($password);flash('Hosting offers are ready.');redirect(admin_url('hosting-offers'));
    }
    if ($action==='hosting_catalog_publish') {
        hosting_catalog_publish($intent['publication_id'],$intent['product_id'],$intent['plan_id'],$intent['review_sha256'],$password);
        flash('Hosting offer published. Clients can request these terms.');redirect(admin_url('hosting-offers',['product_id'=>$intent['product_id']]));
    }
    if ($action==='hosting_catalog_withdraw') {
        hosting_catalog_withdraw($intent['withdrawal_id'],$intent['publication_id'],$intent['publication_sha256'],$intent['reason'],$password);
        flash('Hosting offer withdrawn. Existing requests and agreements remain in history.');redirect(admin_url('hosting-offers'));
    }
    if ($action==='hosting_catalog_convert') {
        $agreement=hosting_catalog_convert($intent['decision_id'],$intent['request_id'],$intent['request_sha256'],$intent['job_id'],$password);
        flash('Agreement offered. The client must review and agree before activation.');redirect(admin_url('service-agreement',['record'=>$agreement->data()['id']]));
    }
    hosting_catalog_close_request($intent['decision_id'],$intent['request_id'],$intent['request_sha256'],$intent['reason'],null,$password);
    flash('Hosting request declined.');redirect(admin_url('hosting-offers',['tab'=>'requests','request_id'=>$intent['request_id']]));
}
/** On a failed POST every existing identity/hash stays as submitted, including invalid/empty values. */
function hosting_catalog_admin_retry(string $action,array $fresh): array {
    if (($_POST['action']??null)!==$action) return $fresh;
    $values=hosting_catalog_admin_values($_POST);
    foreach ($fresh as $key=>$value) $fresh[$key]=$values[$key]??'';
    return $fresh;
}
function hosting_catalog_admin_fields(string $action,array $fields): void {
    if (!in_array($action,hosting_catalog_admin_actions(),true)) throw new LogicException('Unknown hosting form.');
    admin_action($action);$allowed=hosting_catalog_admin_values([]);
    foreach ($fields as $key=>$value) {
        if (!array_key_exists($key,$allowed)||$key==='action'||!is_scalar($value)) throw new LogicException('Invalid hosting form field.');
        echo '<input type="hidden" name="'.esc($key).'" value="'.esc((string)$value).'">';
    }
}
function hosting_catalog_admin_confirmation(string $label): void {
    admin_input('current_password','Your current password','','password','required autocomplete="current-password"');
    admin_check('confirm',$label,false);
}
/** A damaged/unavailable read must not silently replace a failed write's submitted selection. */
function hosting_catalog_admin_preserved_submission(): void {
    $values=hosting_catalog_admin_values($_POST);$action=$values['action'];
    if (!in_array($action,hosting_catalog_admin_actions(),true)||$action==='hosting_catalog_setup') return;
    unset($values['action']);
    echo '<section class="workspace-card"><h2>Submitted selection retained</h2><p>The original selection is retained, but cannot be submitted again until its saved details are available for review.</p><form method="post">';
    hosting_catalog_admin_fields($action,$values);
    echo '<button class="btn outline" disabled>Review required</button></form></section>';
}
function hosting_catalog_admin_pagination(int $page,bool $more,array $context,string $field='p'): void {
    echo '<div class="button-row">';
    if ($page>1) echo '<a class="btn outline" href="'.esc(admin_url('hosting-offers',array_replace($context,[$field=>$page-1]))).'">Previous page</a>';
    if ($more&&$page<100000) echo '<a class="btn outline" href="'.esc(admin_url('hosting-offers',array_replace($context,[$field=>$page+1]))).'">Next page</a>';
    echo '</div>';
}
function hosting_catalog_admin_render(string $error): never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try {$actor=hosting_catalog_admin_owner();}
    catch (RuntimeException|InvalidArgumentException $e) {
        if ($e instanceof PDOException) throw $e;
        http_response_code(403);admin_head('Hosting offers','hosting-offers',admin_user());
        echo '<div class="notice error">Sign in as the current owner outside client support to manage hosting offers.</div>';admin_foot();exit;
    }
    admin_head('Hosting offers','hosting-offers',$actor);
    if ($error!=='') echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    try {
        $data=hosting_catalog_admin_data($_GET,$_POST);$values=hosting_catalog_admin_values($_POST);
        $preview=$GLOBALS['hosting_catalog_admin_preview']??null;$previewError='';
        if ($values['action']==='hosting_catalog_publish') {
            // Redisplay a password/transport failure only if the card still matches the submitted review.
            // Never replace a stale review hash with a newly computed value.
            try {
                $intent=hosting_catalog_admin_intent('hosting_catalog_preview',$_POST);
                $intent['review_sha256']=service_plans_form_hash($_POST,'review_sha256');
                $current=hosting_catalog_preview($intent['publication_id'],$intent['product_id'],$intent['plan_id']);
                if (!hash_equals($intent['review_sha256'],$current['review_sha256'])) throw new RuntimeException('The saved offer changed. Start a new preview before publishing.');
                $preview=$current;
            } catch (RuntimeException|InvalidArgumentException|LogicException $e) {
                if ($e instanceof PDOException) throw $e;
                $preview=null;$previewError=$e->getMessage();
            }
        }
        require CWP_CODE_ROOT.'/admin/views/hosting_catalog.php';
    } catch (RuntimeException|InvalidArgumentException|LogicException $e) {
        if ($e instanceof PDOException) throw $e;
        echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';
        hosting_catalog_admin_preserved_submission();
        echo '<p><a href="'.esc(admin_url('hosting-offers')).'">Return to hosting offers</a></p>';
    }
    admin_foot();exit;
}
