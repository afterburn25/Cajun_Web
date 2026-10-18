<?php
/** Bounded current-authorized projections; never a substitute for mutation authority. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/plans/records.php';
require_once CWP_CODE_ROOT.'/app/plans/forms.php';

function service_plans_page(int $page,bool $subscriptions=false,?int $client=null):array {
    if($page<0 || $page>10000)throw new InvalidArgumentException('Choose an available page.');
    if(db()->inTransaction())throw new LogicException('Plan listings cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($page,$subscriptions,$client):array {
        if($client!==null){if(!$subscriptions)throw new LogicException('Clients may list only their own agreements.');service_plans_current_client($client);}
        else service_plans_owner();
        $table=$subscriptions?'service_subscriptions':'service_plans';$where=$client===null?'':' WHERE client_id=?';
        $rows=all('SELECT * FROM '.table($table).$where.' ORDER BY created_epoch DESC,id DESC LIMIT 11 OFFSET '.($page*10),$client===null?[]:[$client]);
        $more=count($rows)>10;$items=[];
        foreach(array_slice($rows,0,10) as $row){
            $doc=service_plans_validate($row,$subscriptions);$item=['document'=>$doc];
            if($subscriptions && $client===null){$c=service_plans_client_row((int)$row['client_id'],false);$item['client_name']=$c['name'];$item['client_id']=(int)$c['id'];}
            $items[]=$item;
        }
        return ['items'=>$items,'more'=>$more,'page'=>$page];
    }));
}
function service_plans_owner_agreement(string $id):array {
    if(db()->inTransaction())throw new LogicException('Agreement reads cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($id):array {
        service_plans_owner();$row=service_plans_row($id,true);$doc=service_plans_validate($row,true);$client=service_plans_client_row((int)$row['client_id'],false);
        return ['document'=>$doc,'client'=>['id'=>(int)$client['id'],'name'=>$client['name'],'active'=>(int)$client['active']===1]];
    }));
}
function service_plans_client_choices(string $search):array {
    if(strlen($search)>190 || !preg_match('//u',$search) || str_contains($search,"\0"))throw new InvalidArgumentException('Use a shorter client name or email.');
    if(db()->inTransaction())throw new LogicException('Client choices cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($search):array {
        service_plans_owner();
        $rows=all('SELECT id,name,email FROM '.table('studio_clients').' WHERE active=1 AND (LOCATE(?,name)>0 OR LOCATE(?,email)>0) ORDER BY name,id LIMIT 26',[$search,$search]);
        return ['items'=>array_slice($rows,0,25),'more'=>count($rows)>25];
    }));
}
