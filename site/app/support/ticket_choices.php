<?php
/** Small current-authorized selection lists; never mutation authorization. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/support/ticket_views.php';
function support_tickets_client_choices(string $search):array {
    if(strlen($search)>180||!preg_match('//u',$search)||str_contains($search,"\0"))throw new InvalidArgumentException('Use a shorter client name.');
    return support_tickets_locked(function()use($search):array {support_tickets_staff();$rows=all('SELECT id,name FROM '.table('studio_clients').' WHERE active=1 AND LOCATE(?,name)>0 ORDER BY name,id LIMIT 26',[$search]);return ['items'=>array_slice($rows,0,25),'more'=>count($rows)>25];});
}
function support_tickets_client_choice(int $client):array {return support_tickets_locked(function()use($client):array {support_tickets_staff();$row=support_tickets_client_row($client);return ['id'=>(int)$row['id'],'name'=>$row['name']];});}
function support_tickets_staff_choices():array {
    return support_tickets_locked(function():array {
        support_tickets_staff();$rows=all('SELECT u.* FROM '.table('users').' u JOIN '.table('staff_profiles').' p ON p.user_id=u.id WHERE p.enabled=1 AND u.password_hash<>\'\' AND (p.role=\'owner\' OR JSON_CONTAINS(p.permissions,?)) ORDER BY u.name,u.id LIMIT 101',['"support.tickets"']);$out=[];
        foreach($rows as $row)if(staff_profile($row))$out[]=['id'=>(int)$row['id'],'name'=>$row['name']];return ['items'=>array_slice($out,0,100),'more'=>count($out)>100];
    });
}
function support_tickets_link_choices(int $client,bool $staff):array {
    return support_tickets_locked(function()use($client,$staff):array {
        if($staff){$user=support_tickets_staff();support_tickets_client_row($client);if(!staff_can('clients.manage',$user))return [];}
        else {$actor=support_tickets_portal_identity($client);if($actor['type']!=='client')throw new RuntimeException('Choose related work from your own client account.');}
        $result=[];
        $result['project']=all('SELECT id,title FROM '.table('studio_jobs').' WHERE client_id=? ORDER BY id DESC LIMIT 26',[$client]);
        $result['invoice']=all('SELECT i.id,i.title FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id WHERE j.client_id=? ORDER BY i.id DESC LIMIT 26',[$client]);
        if((!$staff||$user['role']==='owner')&&service_plans_ready()){
            $result['subscription']=[];
            foreach(all('SELECT * FROM '.table('service_subscriptions').' WHERE client_id=? ORDER BY created_epoch DESC,id DESC LIMIT 26',[$client]) as $row){$doc=service_plans_validate($row,true);$result['subscription'][]=['id'=>$row['id'],'title'=>$doc->model()->plan->name];}
        }
        foreach($result as $kind=>$rows)$result[$kind]=['items'=>array_slice($rows,0,25),'more'=>count($rows)>25];return $result;
    });
}
