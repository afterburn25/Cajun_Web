<?php
/** Saved hosting purchases: current authority, original checkout records and local setup status. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_checkout.php';
require_once __DIR__.'/orders.php';

/** Missing established order history must never become a new, unstarted purchase. */
function hosting_purchases_orders_ready(): bool {
    hosting_catalog_guard();
    $storage=service_orders_storage();
    if ($storage['version']==='1') {
        service_orders_require();
        foreach (service_orders_schema(config()['db']['prefix']) as $name=>$sql) service_orders_validate_schema($name,$sql);
        return true;
    }
    if ($storage['version']!=='' || one('SELECT id FROM '.table('audit').' WHERE LEFT(action,15)=? LIMIT 1',['service_orders.'])) {
        throw new RuntimeException('Hosting order history is unavailable. Restore its original storage and records.');
    }
    foreach ($storage['existing'] as $name) {
        if (one('SELECT 1 AS present FROM '.table($name).' LIMIT 1')) throw new RuntimeException('Hosting order history is unavailable. Restore its original storage and records.');
    }
    return false;
}

/** Whitelist the visible fields; payment status and local setup phase remain separate facts. */
function hosting_purchases_item(array $row,bool $owner,bool $ordersReady,?int $client): array {
    hosting_catalog_guard();
    $validated=hosting_checkout_result_row($row);$d=$validated['document'];$invoice=$validated['invoice'];
    if ($client!==null && $d['client_id']!==$client) throw new RuntimeException('Hosting purchase unavailable.');
    $agreement=service_plans_validate(service_plans_row($d['agreement_id'],true,true),true);
    if ($agreement->data()['client_id']!==$d['client_id']) hosting_checkout_recovery();
    $project=recurring_billing_job($d['project_id'],$d['client_id']);
    $order=null;
    if ($ordersReady) {
        $saved=one('SELECT * FROM '.table('service_orders').' WHERE agreement_id=?',[$d['agreement_id']]);
        if ($saved) {
            $document=service_orders_validate($saved);$o=$document->data();$state=$document->state();
            if ($o['agreement']['id']!==$d['agreement_id'] || $o['client_id']!==$d['client_id'] || $o['invoice']['id']!==$d['invoice_id']) hosting_checkout_recovery();
            $order=['id'=>$o['id'],'phase'=>$state['phase'],'domain_name'=>$state['domain_name']];
        } elseif (one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? LIMIT 1',['service_orders.offered','% agreement '.$d['agreement_id'].' invoice %'])) {
            throw new RuntimeException('Hosting order history is missing. Restore the original database.');
        }
    }
    $item=['request_id'=>$d['request_id'],'created_epoch'=>$d['accepted_at'],'offer_title'=>$validated['publication']->data()['product']['title'],
        'invoice'=>['id'=>$d['invoice_id'],'reference'=>$invoice['reference'],'status'=>$invoice['status'],'amount_cents'=>(int)$invoice['amount_cents'],'currency'=>$invoice['currency']],
        'project'=>['id'=>$d['project_id'],'title'=>$project['title']],
        'agreement'=>['id'=>$d['agreement_id'],'state'=>$agreement->model()->state()],'order'=>$order];
    if ($owner) {
        $account=service_plans_client_row($d['client_id'],false);
        $item['client']=['id'=>$d['client_id'],'name'=>$account['name']];
    }
    return $item;
}

/** Internal selection runs only inside the current caller's locked, authenticated transaction. */
function hosting_purchases_page(int $page,?int $client): array {
    hosting_catalog_guard();
    if ($page<1 || $page>100000) throw new InvalidArgumentException('Choose an available purchase page.');
    $result=['items'=>[],'page'=>$page,'has_more'=>false,'ready'=>hosting_checkout_ready()];
    if (!$result['ready']) return $result;
    $ordersReady=hosting_purchases_orders_ready();
    $where=$client===null?'':' WHERE client_id=?';$parameters=$client===null?[]:[$client];
    $rows=all('SELECT * FROM '.table('hosting_checkout_results').$where.' ORDER BY created_epoch DESC,id DESC LIMIT 21 OFFSET '.(($page-1)*20),$parameters);
    $result['has_more']=count($rows)>20;
    foreach (array_slice($rows,0,20) as $row) $result['items'][]=hosting_purchases_item($row,$client===null,$ordersReady,$client);
    return $result;
}

function hosting_purchases_owner_page(int $page=1): array {
    return recurring_billing_locked(function()use($page):array {
        unset($GLOBALS['cwp_settings']);
        $actor=service_plans_owner(false);
        if (!staff_can('payments.manage',$actor)) throw new RuntimeException('Current owner financial access is required.');
        return hosting_purchases_page($page,null);
    });
}

function hosting_purchases_client_page(int $client,int $page=1): array {
    // Reject contact provenance before an authority helper can normalize its session.
    hosting_checkout_reject_contact();
    return recurring_billing_locked(function()use($client,$page):array {
        unset($GLOBALS['cwp_settings']);
        hosting_checkout_primary_client($client);
        return hosting_purchases_page($page,$client);
    });
}
