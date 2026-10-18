<?php
/** Primary-client checkout pages. Retained reviews stay server-side; confirmation owns its transaction. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout.php';
require_once CWP_CODE_ROOT.'/app/plans/forms.php';
require_once CWP_CODE_ROOT.'/includes/layout/hosting_catalog.php';

function hosting_checkout_portal_values(array $input): array {
    $values=[];
    foreach (['action'=>40,'request_id'=>32,'publication_id'=>32,'publication_sha256'=>64,'review_sha256'=>64,'project_mode'=>16,'project_id'=>10] as $key=>$max) {
        $v=$input[$key]??'';
        $values[$key]=is_string($v)&&strlen($v)<=$max&&preg_match('//u',$v)===1&&!str_contains($v,"\0")?$v:'';
    }
    return $values;
}
function hosting_checkout_portal_project(array $input): array {
    $mode=service_plans_form_text($input,'project_mode',16);
    if ($mode==='new-hosting') return hosting_checkout_project(['mode'=>$mode]);
    if ($mode!=='existing') throw new InvalidArgumentException('Choose a new hosting project or one of your existing projects.');
    return hosting_checkout_project(['mode'=>$mode,'id'=>service_plans_form_number($input,'project_id',2147483647)]);
}
function hosting_checkout_portal_action(string $action,array $client): void {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') throw new InvalidArgumentException('Use the reviewed hosting form.');
    check_csrf();hosting_checkout_reject_contact();
    if (!in_array($action,['hosting_catalog_checkout_preview','hosting_catalog_checkout_confirm'],true)) throw new InvalidArgumentException('Choose an available checkout action.');
    $request=service_plans_form_id($_POST,'request_id');
    if ($action==='hosting_catalog_checkout_preview') {
        $publication=service_plans_form_id($_POST,'publication_id');$hash=service_plans_form_hash($_POST,'publication_sha256');$project=hosting_checkout_portal_project($_POST);
        if (!consume_rate('client-hosting-checkout-'.(int)$client['id'],20,3600)) throw new RuntimeException('Please wait before reviewing another hosting purchase.');
        $GLOBALS['hosting_checkout_review']=hosting_checkout_preview($request,$publication,$hash,(int)$client['id'],$project);
        return;
    }
    $hash=service_plans_form_hash($_POST,'review_sha256');service_plans_form_confirm($_POST);
    if (!consume_rate('client-hosting-checkout-'.(int)$client['id'],20,3600)) throw new RuntimeException('Please wait before submitting again. Your saved review and invoice remain available.');
    hosting_checkout_confirm($request,$hash,(int)$client['id'],true);
    redirect(studio_url(['hosting'=>'checkout','checkout_result'=>$request]));
}
/** Read-only selection under fresh primary-client authority, with bounded owned project choices. */
function hosting_checkout_portal_selection(array $client,array $values,array $query): array {
    $publication=service_plans_form_id($values,'publication_id');$hash=service_plans_form_hash($values,'publication_sha256');
    $search=service_plans_form_text($query,'project_search',160);$page=service_plans_form_number(['page'=>$query['p']??'1'],'page',100000);
    if ($page<1) throw new InvalidArgumentException('Choose an available project page.');
    return hosting_checkout_locked(function()use($client,$publication,$hash,$search,$page):array {
        hosting_checkout_primary_client((int)$client['id']);hosting_checkout_require();$doc=hosting_checkout_publication($publication);
        if (!hash_equals($doc->fingerprint(),$hash)) throw new RuntimeException('This hosting offer changed. Review the current offer before continuing.');
        $scope=hosting_catalog_read_scope([$doc->data()['product']['id']]);$offer=$doc->publicProjection(hosting_catalog_current_context($doc,$scope),time());
        $rows=all('SELECT id,title FROM '.table('studio_jobs').' WHERE client_id=? AND LOCATE(?,title)>0 ORDER BY id DESC LIMIT 21 OFFSET '.(($page-1)*20),[(int)$client['id'],$search]);
        $projects=[];foreach(array_slice($rows,0,20) as $row)$projects[]=['id'=>(int)$row['id'],'title'=>$row['title']];
        return ['offer'=>$offer,'projects'=>$projects,'has_more'=>count($rows)>20,'page'=>$page,'search'=>$search];
    });
}
/** Reload exact retained review; an invalid submitted fingerprint never receives a replacement. */
function hosting_checkout_portal_review(string $request,string $hash,int $client): array {
    service_plans_id($request);hosting_catalog_hash($hash);
    return hosting_checkout_locked(function()use($request,$hash,$client):array {
        hosting_checkout_primary_client($client);hosting_checkout_require();
        $row=one('SELECT * FROM '.table('hosting_checkout_reviews').' WHERE id=? AND client_id=?',[$request,$client]);
        if (!$row) throw new RuntimeException('Hosting review unavailable.');$review=hosting_checkout_review_row($row);
        if (!hash_equals($review->fingerprint(),$hash)) throw new RuntimeException('Review the exact saved hosting terms before confirming.');
        $result=one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=? AND client_id=?',[$request,$client]);
        if ($result) return ['result'=>hosting_checkout_projection(hosting_checkout_result_row($result),true)];
        $doc=hosting_checkout_publication($row['publication_id']);$scope=hosting_catalog_read_scope([$doc->data()['product']['id']]);
        [$id,$owner]=hosting_checkout_project_facts($review->data()['project'],$client);
        return ['review'=>$review->publicProjection(hosting_catalog_current_context($doc,$scope),$client,$id,$owner,time())];
    });
}
function hosting_checkout_portal_hidden(array $fields): void {
    foreach($fields as $key=>$value) {
        if (!array_key_exists($key,hosting_checkout_portal_values([]))||!is_string($value)) throw new LogicException('Unknown checkout form field.');
        echo '<input type="hidden" name="'.esc($key).'" value="'.esc($value).'">';
    }
}
function hosting_checkout_portal_render(array $client,string $error): never {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    $attempt=($_SERVER['REQUEST_METHOD']??'GET')==='POST'?hosting_checkout_portal_values($_POST):null;
    $data=[];$unavailable=false;$review=null;$result=null;$values=$attempt;
    try {
        if (array_key_exists('checkout_result',$_GET)) {
            $request=service_plans_form_id($_GET,'checkout_result');$result=hosting_checkout_result($request,(int)$client['id']);
        } elseif ($attempt&&$attempt['action']==='hosting_catalog_checkout_confirm') {
            $saved=hosting_checkout_portal_review($attempt['request_id'],$attempt['review_sha256'],(int)$client['id']);
            $review=$saved['review']??null;$result=$saved['result']??null;
        } elseif (isset($GLOBALS['hosting_checkout_review'])) {
            $r=$GLOBALS['hosting_checkout_review'];$saved=hosting_checkout_portal_review($r['request_id'],$r['review_sha256'],(int)$client['id']);
            $review=$saved['review']??null;$result=$saved['result']??null;
        } else {
            if ($values===null) $values=['action'=>'hosting_catalog_checkout_preview','request_id'=>bin2hex(random_bytes(16)),
                'publication_id'=>service_plans_form_id($_GET,'offer'),'publication_sha256'=>service_plans_form_hash($_GET,'offer_sha256'),'review_sha256'=>'','project_mode'=>'new-hosting','project_id'=>''];
            $data=hosting_checkout_portal_selection($client,$values,$_GET);
        }
    } catch (Throwable $e) {
        if ($e instanceof PDOException||!($e instanceof RuntimeException||$e instanceof InvalidArgumentException||$e instanceof LogicException)) {report_error($e);$error='Hosting checkout is temporarily unavailable. Your existing records were preserved.';}
        elseif ($error==='') $error=$e->getMessage();
        $unavailable=true;
    }
    site_head('Review hosting','portal');
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">YOUR CLIENT WORKSPACE</span><h1>Review <span class="gold-text">hosting</span></h1><p>Choose a project, review the service and billing dates, then confirm your first invoice.</p><a class="btn outline" href="'.esc(studio_url(['hosting'=>'offers'])).'">Available plans</a></div></section><section class="section"><div class="container narrow">';show_flash();
    if ($error!=='') echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    require CWP_CODE_ROOT.'/portal/views/hosting_checkout.php';
    echo '</div></section>';site_foot(false);exit;
}
